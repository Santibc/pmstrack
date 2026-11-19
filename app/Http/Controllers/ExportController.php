<?php

namespace App\Http\Controllers;

use App\Enums\EstadoGuia;
use App\Enums\TipoEvidencia;
use App\Exports\GuiasExport;
use App\Exports\TablaExport;
use App\Models\Bodega;
use App\Models\Guia;
use App\Models\Seccional;
use App\Queries\FiltroGuias;
use App\Services\Parametros;
use App\Services\Reportes\CalidadDatos;
use App\Services\Reportes\Reportes;
use App\Support\Rollos;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Descargas: reporte de entregas en Excel y PDF, inventario, calidad de
 * datos, reportes y comprobante de entrega.
 */
class ExportController extends Controller
{
    /** Tope de filas del PDF del listado, para que se genere rápido y sea legible. */
    private const int MAXIMO_PDF = 400;

    /**
     * Excel de entregas en el formato de Credibanco, o el listado en PDF.
     */
    public function guias(Request $request): Response
    {
        $area = $request->routeIs('portal.*') ? 'portal' : 'admin';
        $consulta = FiltroGuias::aplicar($request->query(), $request->user());
        $nombre = 'Entregas_'.now()->format('d-m-Y');

        if ($request->query('formato') === 'pdf') {
            $guias = $consulta->with(['comercio', 'ciudad', 'ultimaVisita.causal'])->latest('fecha_registro')->limit(self::MAXIMO_PDF + 1)->get();

            return Pdf::loadView('pdf.listado', [
                'guias' => $guias->take(self::MAXIMO_PDF),
                'recortado' => $guias->count() > self::MAXIMO_PDF,
                'maximo' => self::MAXIMO_PDF,
                'filtros' => $request->query(),
            ])->setPaper('letter', 'landscape')->stream($nombre.'.pdf');
        }

        return Excel::download(new GuiasExport($consulta, $area.'.guias.show'), $nombre.'.xlsx');
    }

    /**
     * Comprobante de entrega en PDF, con la foto, la firma y la ubicación.
     */
    public function comprobante(Request $request, Guia $guia, Parametros $parametros): Response
    {
        abort_unless($request->user()->puedeVerSeccional($guia->seccional_id), 403);
        abort_unless($guia->estado === EstadoGuia::Entregada, 404, 'El comprobante se genera cuando el servicio está entregado.');

        $guia->load(['comercio', 'ciudad', 'seccional', 'zona', 'ultimaVisita.evidencias', 'ultimaVisita.repartidor.user']);
        $visita = $guia->ultimaVisita;

        $imagen = function (?TipoEvidencia $tipo) use ($visita): ?string {
            $evidencia = $tipo ? $visita?->evidencia($tipo) : null;
            $contenido = $evidencia?->contenido();

            return $contenido ? 'data:'.$evidencia->mime.';base64,'.base64_encode($contenido) : null;
        };

        return Pdf::loadView('pdf.comprobante', [
            'guia' => $guia,
            'visita' => $visita,
            'foto' => $imagen(TipoEvidencia::Foto),
            'firma' => $imagen(TipoEvidencia::Firma),
            'huella' => $visita?->evidencia(TipoEvidencia::Foto)?->sha256,
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('img/logo-pms-blanco.png'))),
            'linea' => $parametros->texto('contacto_linea'),
            'direccionPms' => $parametros->texto('contacto_direccion'),
        ])->setPaper('letter')->stream('Comprobante_'.$guia->servicio.'.pdf');
    }

    /**
     * Movimientos de inventario de una bodega en un rango de fechas.
     */
    public function inventario(Request $request): Response
    {
        [$desde, $hasta] = $this->rango($request);
        $seccional = Seccional::query()->visiblesPara($request->user())->where('codigo', $request->query('seccional'))->firstOrFail();
        $bodega = Bodega::query()->where('seccional_id', $seccional->id)->firstOrFail();

        $filas = $bodega->movimientos()->with(['user', 'repartidor.user'])
            ->whereBetween('created_at', [$desde, $hasta])->orderBy('id')->get()
            ->map(fn ($m): array => [
                $m->created_at->format('d/m/Y H:i'),
                $m->tipo->etiqueta(),
                $m->detalle,
                $m->remision_numero,
                $m->cantidad,
                Rollos::enCajas($m->cantidad),
                $m->saldo,
                $m->motivo,
                $m->registradoPor(),
            ])->all();

        return Excel::download(
            new TablaExport('Inventario '.$seccional->nombre, ['Fecha', 'Movimiento', 'Detalle', 'Remisión', 'Rollos', 'En cajas', 'Saldo', 'Motivo', 'Registró'], $filas),
            'Inventario_'.$seccional->codigo.'_'.$desde->format('d-m-Y').'_a_'.$hasta->format('d-m-Y').'.xlsx',
        );
    }

    /**
     * Comercios con dirección errada, cerrados o con teléfono incompleto.
     */
    public function calidad(Request $request, CalidadDatos $calidad): Response
    {
        [$desde, $hasta] = $this->rango($request);
        $seccionalId = Seccional::query()->visiblesPara($request->user())->where('codigo', $request->query('seccional'))->value('id');

        $filas = $calidad->consulta($desde, $hasta, $seccionalId)
            ->visiblesPara($request->user())
            ->with(['comercio', 'ciudad', 'seccional', 'visitas.causal'])
            ->latest('fecha_registro')
            ->get()
            ->map(fn (Guia $guia): array => [
                $guia->comercio->codigo_unico,
                $guia->nombre_comercio,
                $guia->servicio,
                $guia->fecha_registro->format('d/m/Y'),
                $guia->seccional->nombre,
                $guia->ciudad_original,
                $guia->direccion_original,
                $guia->telefono,
                implode(' | ', $calidad->hallazgos($guia)),
            ])->all();

        return Excel::download(
            new TablaExport('Calidad de datos', ['Código único', 'Comercio', 'Servicio', 'Fecha', 'Seccional', 'Ciudad', 'Dirección', 'Teléfono', 'Qué corregir'], $filas),
            'Calidad_de_datos_'.now()->format('d-m-Y').'.xlsx',
        );
    }

    /**
     * Reportes tabulares: ANS, causales, productividad y corte para facturación.
     */
    public function reporte(Request $request, string $tipo, Reportes $reportes): Response
    {
        abort_unless(isset(Reportes::TIPOS[$tipo]), 404);

        [$desde, $hasta] = $this->rango($request);
        $reporte = $reportes->generar($tipo, $request->user(), $desde, $hasta);

        return Excel::download(
            new TablaExport($reporte['titulo'], $reporte['encabezados'], $reporte['filas']),
            str($reporte['titulo'])->slug('_').'_'.$desde->format('d-m-Y').'_a_'.$hasta->format('d-m-Y').'.xlsx',
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rango(Request $request): array
    {
        try {
            $desde = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('desde'))->startOfDay();
            $hasta = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('hasta'))->endOfDay();
        } catch (Throwable) {
            $desde = CarbonImmutable::now()->startOfMonth();
            $hasta = CarbonImmutable::now()->endOfDay();
        }

        return $desde->lessThanOrEqualTo($hasta) ? [$desde, $hasta] : [$hasta->startOfDay(), $desde->endOfDay()];
    }
}
