<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Enums\EstadoGuia;
use App\Enums\ResultadoVisita;
use App\Models\Guia;
use App\Models\Repartidor;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Visita;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reportes tabulares (documento técnico 4.1): cumplimiento de ANS, causales,
 * productividad por repartidor y corte mensual para facturación.
 *
 * Cada reporte devuelve ['titulo', 'encabezados', 'filas'] y sirve igual para
 * mostrarlo en pantalla que para descargarlo en Excel.
 */
final class Reportes
{
    public const array TIPOS = [
        'ans' => ['Cumplimiento de ANS', 'Entregas a tiempo y fuera de tiempo por seccional.', 'shield-check'],
        'causales' => ['Causales', 'Motivos de no entrega y de anulación, con su frecuencia.', 'triangle-alert'],
        'productividad' => ['Productividad por repartidor', 'Visitas, entregas, efectividad y rollos de cada repartidor.', 'truck'],
        'corte' => ['Corte para facturación', 'Servicios y rollos del periodo por seccional y resultado.', 'file-check-2'],
    ];

    public function __construct(private readonly Indicadores $indicadores) {}

    /**
     * @return array{titulo: string, encabezados: list<string>, filas: list<list<mixed>>}
     */
    public function generar(string $tipo, User $usuario, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return match ($tipo) {
            'causales' => $this->causales($usuario, $desde, $hasta),
            'productividad' => $this->productividad($usuario, $desde, $hasta),
            'corte' => $this->corte($usuario, $desde, $hasta),
            default => $this->ans($usuario, $desde, $hasta),
        };
    }

    /**
     * @return array{titulo: string, encabezados: list<string>, filas: list<list<mixed>>}
     */
    private function ans(User $usuario, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = [];

        foreach ($this->seccionales($usuario) as $seccional) {
            $resumen = $this->indicadores->resumen($usuario, $desde, $hasta, $seccional->id);

            $filas[] = [
                $seccional->nombre,
                $resumen['recibidos'],
                $resumen['entregados'],
                $resumen['a_tiempo'],
                $resumen['entregados'] - $resumen['a_tiempo'],
                $resumen['cumplimiento_ans'] !== null ? $resumen['cumplimiento_ans'].' %' : '—',
                $resumen['horas_promedio'] !== null ? $resumen['horas_promedio'].' h' : '—',
            ];
        }

        return [
            'titulo' => 'Cumplimiento de ANS',
            'encabezados' => ['Seccional', 'Recibidos', 'Entregados', 'A tiempo', 'Fuera de tiempo', 'Cumplimiento', 'Tiempo promedio'],
            'filas' => $filas,
        ];
    }

    /**
     * @return array{titulo: string, encabezados: list<string>, filas: list<list<mixed>>}
     */
    private function causales(User $usuario, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $motivos = $this->indicadores->motivos($usuario, $desde, $hasta, limite: 100);
        $total = max(1, (int) $motivos->sum('total'));

        return [
            'titulo' => 'Causales',
            'encabezados' => ['Motivo', 'Tipo', 'Visitas', 'Participación'],
            'filas' => $motivos->map(fn (array $motivo): array => [
                $motivo['nombre'],
                $motivo['tipo'] === 'anulado' ? 'Anulado' : 'No entregado',
                $motivo['total'],
                round($motivo['total'] * 100 / $total, 1).' %',
            ])->all(),
        ];
    }

    /**
     * @return array{titulo: string, encabezados: list<string>, filas: list<list<mixed>>}
     */
    private function productividad(User $usuario, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $porRepartidor = Visita::query()
            ->vigentes()
            ->whereBetween('registrada_at', [$desde, $hasta])
            ->toBase()
            ->selectRaw('repartidor_id, COUNT(*) as visitas, COUNT(DISTINCT ruta_id) as rutas')
            ->selectRaw('SUM(CASE WHEN resultado = ? THEN 1 ELSE 0 END) as entregas', [ResultadoVisita::Entregado->value])
            ->selectRaw('SUM(CASE WHEN resultado = ? THEN 1 ELSE 0 END) as no_entregadas', [ResultadoVisita::NoEntregado->value])
            ->selectRaw('SUM(CASE WHEN resultado = ? THEN 1 ELSE 0 END) as anuladas', [ResultadoVisita::Anulado->value])
            ->selectRaw('SUM(rollos_entregados) as rollos')
            ->groupBy('repartidor_id')
            ->get()
            ->keyBy('repartidor_id');

        $filas = Repartidor::query()
            ->with(['user', 'seccional'])
            ->when($usuario->seccionalRestringida(), fn (Builder $q, int $id) => $q->where('seccional_id', $id))
            ->whereKey($porRepartidor->keys())
            ->get()
            ->map(function (Repartidor $repartidor) use ($porRepartidor): array {
                $fila = $porRepartidor[$repartidor->id];

                return [
                    $repartidor->nombre,
                    $repartidor->seccional->nombre,
                    (int) $fila->rutas,
                    (int) $fila->visitas,
                    (int) $fila->entregas,
                    (int) $fila->no_entregadas,
                    (int) $fila->anuladas,
                    round($fila->entregas * 100 / max(1, $fila->visitas), 1).' %',
                    (int) $fila->rollos,
                ];
            })
            ->sortByDesc(fn (array $fila): int => $fila[4])
            ->values()
            ->all();

        return [
            'titulo' => 'Productividad por repartidor',
            'encabezados' => ['Repartidor', 'Seccional', 'Rutas', 'Visitas', 'Entregas', 'No entregadas', 'Anuladas', 'Efectividad', 'Rollos entregados'],
            'filas' => $filas,
        ];
    }

    /**
     * Corte del periodo: lo entregado se cuenta por la fecha de entrega.
     *
     * @return array{titulo: string, encabezados: list<string>, filas: list<list<mixed>>}
     */
    private function corte(User $usuario, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = [];

        foreach ($this->seccionales($usuario) as $seccional) {
            $entregas = Guia::query()->where('seccional_id', $seccional->id)->where('estado', EstadoGuia::Entregada)->whereBetween('entregada_at', [$desde, $hasta]);
            $cerradas = fn (EstadoGuia $estado): int => Guia::query()->where('seccional_id', $seccional->id)->where('estado', $estado)->whereBetween('cerrada_at', [$desde, $hasta])->count();

            $filas[] = [
                $seccional->nombre,
                Guia::query()->where('seccional_id', $seccional->id)->whereBetween('fecha_registro', [$desde, $hasta])->count(),
                (clone $entregas)->count(),
                (int) (clone $entregas)->sum('rollos_entregados'),
                $cerradas(EstadoGuia::Anulada),
                $cerradas(EstadoGuia::DevueltaCredibanco),
                Visita::query()->vigentes()->whereBetween('registrada_at', [$desde, $hasta])->whereHas('guia', fn (Builder $q) => $q->where('seccional_id', $seccional->id))->count(),
            ];
        }

        return [
            'titulo' => 'Corte para facturación',
            'encabezados' => ['Seccional', 'Servicios recibidos', 'Servicios entregados', 'Rollos entregados', 'Anulados', 'Devueltos a Credibanco', 'Visitas realizadas'],
            'filas' => $filas,
        ];
    }

    private function seccionales(User $usuario)
    {
        return Seccional::query()->activas()->visiblesPara($usuario)->orderBy('id')->get();
    }
}
