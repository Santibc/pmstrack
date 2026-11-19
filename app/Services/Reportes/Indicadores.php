<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Enums\EstadoGuia;
use App\Enums\EstadoRuta;
use App\Enums\ResultadoVisita;
use App\Models\Guia;
use App\Models\Ruta;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Visita;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Parametros;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de los dashboards de PMS y de Credibanco: servicios recibidos y
 * entregados, cumplimiento de ANS, tiempos, estados y motivos de no entrega.
 */
final class Indicadores
{
    public function __construct(
        private readonly Parametros $parametros,
        private readonly CalendarioLaboral $calendario,
    ) {}

    /**
     * @return Builder<Guia>
     */
    private function guias(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId): Builder
    {
        return Guia::query()
            ->visiblesPara($usuario)
            ->whereBetween('fecha_registro', [$desde, $hasta])
            ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id));
    }

    /**
     * Cifras principales del periodo y su comparación con el periodo anterior de igual duración.
     *
     * @return array<string, mixed>
     */
    public function resumen(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId = null): array
    {
        $actual = $this->cifras($this->guias($usuario, $desde, $hasta, $seccionalId));

        $dias = max(1, (int) $desde->diffInDays($hasta) + 1);
        $previoHasta = CarbonImmutable::instance($desde)->subSecond();
        $previoDesde = $previoHasta->subDays($dias - 1)->startOfDay();
        $previo = $this->cifras($this->guias($usuario, $previoDesde, $previoHasta, $seccionalId));

        return [
            ...$actual,
            'meta_ans' => $this->parametros->entero('ans_meta_cumplimiento'),
            'variacion_recibidos' => $previo['recibidos'] > 0 ? round(($actual['recibidos'] - $previo['recibidos']) * 100 / $previo['recibidos'], 1) : null,
            'variacion_horas' => $actual['horas_promedio'] !== null && $previo['horas_promedio'] !== null ? $actual['horas_promedio'] - $previo['horas_promedio'] : null,
            'periodo_anterior' => $previoDesde->isoFormat('D MMM').' – '.$previoHasta->isoFormat('D MMM'),
        ];
    }

    /**
     * @param  Builder<Guia>  $guias
     * @return array<string, mixed>
     */
    private function cifras(Builder $guias): array
    {
        $fila = (clone $guias)->toBase()
            ->selectRaw('COUNT(*) as recibidos')
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as entregados', [EstadoGuia::Entregada->value])
            ->selectRaw('SUM(CASE WHEN estado = ? AND entregada_at <= ans_vence_at THEN 1 ELSE 0 END) as a_tiempo', [EstadoGuia::Entregada->value])
            ->selectRaw('AVG(CASE WHEN estado = ? THEN '.$this->sqlMinutos('fecha_registro', 'entregada_at').' END) as minutos', [EstadoGuia::Entregada->value])
            ->selectRaw('SUM(rollos_solicitados) as rollos')
            ->selectRaw('SUM(COALESCE(rollos_entregados, 0)) as rollos_entregados')
            ->first();

        $recibidos = (int) ($fila->recibidos ?? 0);
        $entregados = (int) ($fila->entregados ?? 0);
        $aTiempo = (int) ($fila->a_tiempo ?? 0);

        return [
            'recibidos' => $recibidos,
            'entregados' => $entregados,
            'porcentaje_entregados' => $recibidos > 0 ? round($entregados * 100 / $recibidos, 1) : null,
            'a_tiempo' => $aTiempo,
            'cumplimiento_ans' => $entregados > 0 ? round($aTiempo * 100 / $entregados, 1) : null,
            'horas_promedio' => $fila->minutos !== null ? (int) round((float) $fila->minutos / 60) : null,
            'rollos' => (int) ($fila->rollos ?? 0),
            'rollos_entregados' => (int) ($fila->rollos_entregados ?? 0),
        ];
    }

    /**
     * Servicios por estado, como los ve Credibanco.
     *
     * @return array{entregados: int, en_reparto: int, no_entregados: int, anulados: int, por_salir: int, total: int}
     */
    public function estados(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId = null): array
    {
        $porEstado = $this->guias($usuario, $desde, $hasta, $seccionalId)->toBase()
            ->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $suma = fn (EstadoGuia ...$estados): int => (int) collect($estados)->sum(fn (EstadoGuia $e) => $porEstado[$e->value] ?? 0);

        return [
            'entregados' => $suma(EstadoGuia::Entregada),
            'en_reparto' => $suma(EstadoGuia::Asignada, EstadoGuia::EnReparto),
            'no_entregados' => $suma(EstadoGuia::NoEntregada),
            'anulados' => $suma(EstadoGuia::Anulada, EstadoGuia::DevueltaCredibanco),
            'por_salir' => $suma(EstadoGuia::Recibida, EstadoGuia::PorZonificar, EstadoGuia::ListaParaAsignar),
            'total' => (int) $porEstado->sum(),
        ];
    }

    /**
     * Recibidos y entregados por día hábil del periodo (para la gráfica de barras).
     *
     * @return array{etiquetas: list<string>, titulos: list<string>, recibidos: list<int>, entregados: list<int>, resaltar: int|null}
     */
    public function porDia(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId = null): array
    {
        $recibidos = $this->guias($usuario, $desde, $hasta, $seccionalId)->toBase()
            ->selectRaw('DATE(fecha_registro) as dia, COUNT(*) as total')->groupBy('dia')->pluck('total', 'dia');

        $entregados = Guia::query()->visiblesPara($usuario)
            ->where('estado', EstadoGuia::Entregada)
            ->whereBetween('entregada_at', [$desde, $hasta])
            ->when($seccionalId, fn (Builder $q, int $id) => $q->where('seccional_id', $id))
            ->toBase()
            ->selectRaw('DATE(entregada_at) as dia, COUNT(*) as total')->groupBy('dia')->pluck('total', 'dia');

        $datos = ['etiquetas' => [], 'titulos' => [], 'recibidos' => [], 'entregados' => [], 'resaltar' => null];
        $dia = CarbonImmutable::instance($desde)->startOfDay();
        $fin = CarbonImmutable::instance($hasta)->startOfDay();

        // Con periodos largos se muestran los últimos 31 días para que la gráfica se lea.
        if ($dia->diffInDays($fin) > 45) {
            $dia = $fin->subDays(45);
        }

        while ($dia->lessThanOrEqualTo($fin)) {
            $clave = $dia->toDateString();
            $hayDatos = isset($recibidos[$clave]) || isset($entregados[$clave]);

            if ($this->calendario->esHabil($dia) || $hayDatos) {
                if ($dia->isToday()) {
                    $datos['resaltar'] = count($datos['etiquetas']);
                }

                $datos['etiquetas'][] = $dia->isToday() ? 'Hoy' : (string) $dia->day;
                $datos['titulos'][] = ucfirst($dia->isoFormat('dddd D [de] MMMM'));
                $datos['recibidos'][] = (int) ($recibidos[$clave] ?? 0);
                $datos['entregados'][] = (int) ($entregados[$clave] ?? 0);
            }

            $dia = $dia->addDay();
        }

        return $datos;
    }

    /**
     * Cumplimiento de ANS por seccional.
     *
     * @return Collection<int, array{seccional: string, cumplimiento: float|null, entregas: int}>
     */
    public function ansPorSeccional(User $usuario, CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        $filas = $this->guias($usuario, $desde, $hasta, null)->toBase()
            ->where('estado', EstadoGuia::Entregada->value)
            ->selectRaw('seccional_id, COUNT(*) as entregas, SUM(CASE WHEN entregada_at <= ans_vence_at THEN 1 ELSE 0 END) as a_tiempo')
            ->groupBy('seccional_id')
            ->get()
            ->keyBy('seccional_id');

        return Seccional::query()->activas()->visiblesPara($usuario)->orderBy('id')->get()->map(fn (Seccional $seccional): array => [
            'seccional' => $seccional->nombre,
            'entregas' => (int) ($filas[$seccional->id]->entregas ?? 0),
            'cumplimiento' => isset($filas[$seccional->id]) && $filas[$seccional->id]->entregas > 0
                ? round($filas[$seccional->id]->a_tiempo * 100 / $filas[$seccional->id]->entregas, 1)
                : null,
        ]);
    }

    /**
     * Motivos de no entrega y anulación más frecuentes del periodo.
     *
     * @return Collection<int, array{nombre: string, tipo: string, total: int}>
     */
    public function motivos(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, ?int $seccionalId = null, int $limite = 6): Collection
    {
        return Visita::query()
            ->vigentes()
            ->whereIn('resultado', [ResultadoVisita::NoEntregado, ResultadoVisita::Anulado])
            ->whereBetween('registrada_at', [$desde, $hasta])
            ->whereHas('guia', fn (Builder $q) => $q->visiblesPara($usuario)->when($seccionalId, fn (Builder $g, int $id) => $g->where('seccional_id', $id)))
            ->join('causales', 'causales.id', '=', 'visitas.causal_id')
            ->groupBy('causales.id', 'causales.nombre', 'causales.tipo')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limite)
            ->toBase()
            ->get(['causales.nombre', 'causales.tipo', DB::raw('COUNT(*) as total')])
            ->map(fn ($fila): array => ['nombre' => $fila->nombre, 'tipo' => $fila->tipo, 'total' => (int) $fila->total]);
    }

    public function repartidoresEnRuta(User $usuario): int
    {
        return Ruta::query()
            ->whereDate('fecha', today())
            ->where('estado', EstadoRuta::EnCurso)
            ->when($usuario->seccionalRestringida(), fn (Builder $q, int $id) => $q->where('seccional_id', $id))
            ->count();
    }

    /**
     * Minutos entre dos columnas de fecha, en MariaDB/MySQL y en SQLite (pruebas).
     */
    private function sqlMinutos(string $desde, string $hasta): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$hasta}) - julianday({$desde})) * 1440"
            : "TIMESTAMPDIFF(MINUTE, {$desde}, {$hasta})";
    }
}
