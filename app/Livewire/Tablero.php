<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\EstadoCarga;
use App\Enums\EstadoGuia;
use App\Enums\EstadoParada;
use App\Models\Bodega;
use App\Models\Carga;
use App\Models\Guia;
use App\Models\Novedad;
use App\Models\Parada;
use App\Models\Seccional;
use App\Services\Parametros;
use App\Services\Reportes\CalidadDatos;
use App\Services\Reportes\Indicadores;
use App\Services\Zonificacion\ZonificacionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Dashboard. Para Credibanco: entregas, ANS y motivos del periodo. Para PMS,
 * además, el pulso de la operación de hoy y lo que requiere atención.
 */
class Tablero extends Component
{
    #[Locked]
    public bool $portal = false;

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $seccional = '';

    public function mount(): void
    {
        $this->portal = request()->routeIs('portal.*');

        if (! $this->fechaValida($this->desde) || ! $this->fechaValida($this->hasta)) {
            $this->periodo('mes');
        }
    }

    public function periodo(string $cual): void
    {
        [$desde, $hasta] = match ($cual) {
            'semana' => [now()->subDays(6), now()],
            'anterior' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => [now()->startOfMonth(), now()],
        };

        $this->desde = $desde->toDateString();
        $this->hasta = $hasta->toDateString();
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['desde', 'hasta'], true) && (! $this->fechaValida($this->desde) || ! $this->fechaValida($this->hasta) || $this->desde > $this->hasta)) {
            $this->periodo('mes');
        }
    }

    public function render(Indicadores $indicadores, CalidadDatos $calidad)
    {
        $usuario = auth()->user();
        $desde = CarbonImmutable::parse($this->desde)->startOfDay();
        $hasta = CarbonImmutable::parse($this->hasta)->endOfDay();
        $seccionales = Seccional::query()->activas()->visiblesPara($usuario)->orderBy('id')->get();
        $seccionalId = $seccionales->firstWhere('codigo', $this->seccional)?->id;
        $porDia = $indicadores->porDia($usuario, $desde, $hasta, $seccionalId);

        return view('livewire.tablero', [
            'area' => $this->portal ? 'portal' : 'admin',
            'seccionales' => $seccionales,
            'rango' => $desde->isSameMonth($hasta) ? $desde->day.' – '.$hasta->isoFormat('D MMM YYYY') : $desde->isoFormat('D MMM').' – '.$hasta->isoFormat('D MMM YYYY'),
            'resumen' => $indicadores->resumen($usuario, $desde, $hasta, $seccionalId),
            'estados' => $indicadores->estados($usuario, $desde, $hasta, $seccionalId),
            'grafica' => [
                'etiquetas' => $porDia['etiquetas'],
                'titulos' => $porDia['titulos'],
                'resaltar' => $porDia['resaltar'],
                'series' => [
                    ['nombre' => 'Recibidos', 'color' => 'gris', 'valores' => $porDia['recibidos']],
                    ['nombre' => 'Entregados', 'color' => 'naranja', 'valores' => $porDia['entregados']],
                ],
            ],
            'ansPorSeccional' => $indicadores->ansPorSeccional($usuario, $desde, $hasta),
            'motivos' => $indicadores->motivos($usuario, $desde, $hasta, $seccionalId),
            'enRuta' => $indicadores->repartidoresEnRuta($usuario),
            'calidad' => $calidad->total($desde, $hasta, $seccionalId),
            'movimientos' => Guia::query()->visiblesPara($usuario)
                ->with(['ciudad', 'ultimaVisita.causal', 'ultimaVisita.evidencias'])
                ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id))
                ->whereNotNull('ultimo_movimiento_at')
                ->latest('ultimo_movimiento_at')
                ->limit(6)
                ->get(),
            'consulta' => array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'seccional' => $this->seccional]),
            'hoy' => $this->portal ? null : $this->operacionDeHoy($seccionales->pluck('id')->all()),
        ])->title($this->portal ? 'Dashboard de entregas' : 'Dashboard operativo');
    }

    /**
     * El pulso de hoy y la lista de lo que requiere atención de la administradora.
     *
     * @param  list<int>  $seccionalIds
     * @return array<string, mixed>
     */
    private function operacionDeHoy(array $seccionalIds): array
    {
        $paradas = Parada::query()
            ->whereIn('estado', EstadoParada::activas())
            ->whereHas('ruta', fn (Builder $q) => $q->whereDate('fecha', today())->whereIn('seccional_id', $seccionalIds))
            ->toBase()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $maxIntentos = app(Parametros::class)->maxIntentos();
        $abiertas = Guia::query()->whereIn('seccional_id', $seccionalIds);
        $restringida = auth()->user()->seccionalRestringida();

        $pendientes = array_values(array_filter([
            ['icono' => 'file-up', 'total' => Carga::query()->where('estado', EstadoCarga::Pendiente)->count(), 'texto' => ['carga sin confirmar', 'cargas sin confirmar'], 'ruta' => route('admin.cargas'), 'tono' => 'brand'],
            ['icono' => 'map', 'total' => app(ZonificacionService::class)->totalPendientes($restringida), 'texto' => ['dirección nueva por zonificar', 'direcciones nuevas por zonificar'], 'ruta' => route('admin.zonificacion'), 'tono' => 'brand'],
            ['icono' => 'user-round-check', 'total' => (clone $abiertas)->planeablesPara(today(), $maxIntentos)->count(), 'texto' => ['guía lista sin salir a ruta', 'guías listas sin salir a ruta'], 'ruta' => route('admin.planeacion'), 'tono' => 'brand'],
            ['icono' => 'clock', 'total' => (clone $abiertas)->ansEnRiesgo()->count(), 'texto' => ['guía con el ANS en riesgo o vencido', 'guías con el ANS en riesgo o vencido'], 'ruta' => route('admin.guias', ['estado' => 'riesgo', 'desde' => '', 'hasta' => '']), 'tono' => 'bad'],
            ['icono' => 'rotate-ccw', 'total' => (clone $abiertas)->where('estado', EstadoGuia::NoEntregada)->where('intentos', '>=', $maxIntentos)->count(), 'texto' => ["guía llegó a {$maxIntentos} intentos: decide si se devuelve", "guías llegaron a {$maxIntentos} intentos: decide si se devuelven"], 'ruta' => route('admin.guias', ['estado' => 'no_entregados']), 'tono' => 'warn'],
            ['icono' => 'flag', 'total' => Novedad::query()->abiertas()->whereDate('reportada_at', today())->whereHas('repartidor', fn (Builder $q) => $q->whereIn('seccional_id', $seccionalIds))->count(), 'texto' => ['novedad sin atender', 'novedades sin atender'], 'ruta' => route('admin.seguimiento'), 'tono' => 'warn'],
            ['icono' => 'warehouse', 'total' => Bodega::query()->whereIn('seccional_id', $seccionalIds)->where('stock_minimo', '>', 0)->whereColumn('saldo', '<', 'stock_minimo')->count(), 'texto' => ['bodega bajo el mínimo de rollos', 'bodegas bajo el mínimo de rollos'], 'ruta' => route('admin.inventario'), 'tono' => 'warn'],
        ], fn (array $item): bool => $item['total'] > 0));

        $entregadas = (int) ($paradas[EstadoParada::Entregada->value] ?? 0);
        $noEntregadas = (int) ($paradas[EstadoParada::NoEntregada->value] ?? 0);
        $anuladas = (int) ($paradas[EstadoParada::Anulada->value] ?? 0);
        $porVisitar = (int) ($paradas[EstadoParada::Pendiente->value] ?? 0);

        return [
            'entregadas' => $entregadas,
            'no_entregadas' => $noEntregadas,
            'anuladas' => $anuladas,
            'por_visitar' => $porVisitar,
            'total' => $entregadas + $noEntregadas + $anuladas + $porVisitar,
            'pendientes' => $pendientes,
        ];
    }

    private function fechaValida(string $fecha): bool
    {
        try {
            return $fecha !== '' && CarbonImmutable::createFromFormat('Y-m-d', $fecha) !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
