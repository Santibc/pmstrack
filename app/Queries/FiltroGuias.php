<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\EstadoGuia;
use App\Models\Guia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Filtros del listado de guías. Los comparten la pantalla, la exportación a
 * Excel y la exportación a PDF, para que siempre muestren lo mismo.
 */
final class FiltroGuias
{
    /** Grupos de estado de las pestañas del listado. */
    public const array GRUPOS = [
        'entregados' => [EstadoGuia::Entregada],
        'en_reparto' => [EstadoGuia::Asignada, EstadoGuia::EnReparto],
        'no_entregados' => [EstadoGuia::NoEntregada],
        'anulados' => [EstadoGuia::Anulada, EstadoGuia::DevueltaCredibanco],
        'pendientes' => [EstadoGuia::Recibida, EstadoGuia::PorZonificar, EstadoGuia::ListaParaAsignar],
    ];

    /**
     * @param  array{q?: string|null, desde?: string|null, hasta?: string|null, seccional?: string|null, ciudad?: int|string|null, estado?: string|null, ans?: string|null, repartidor?: int|string|null, zona?: int|string|null}  $filtros
     * @return Builder<Guia>
     */
    public static function aplicar(array $filtros, User $usuario, bool $conEstado = true): Builder
    {
        $termino = trim((string) ($filtros['q'] ?? ''));
        $desde = self::fecha($filtros['desde'] ?? null)?->startOfDay();
        $hasta = self::fecha($filtros['hasta'] ?? null)?->endOfDay();
        $estado = $filtros['estado'] ?? null;

        return Guia::query()
            ->visiblesPara($usuario)
            ->when($termino !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($termino): void {
                $como = '%'.$termino.'%';

                $w->where('servicio', 'like', $termino.'%')
                    ->orWhere('numero_guia', 'like', $como)
                    ->orWhere('nombre_comercio', 'like', $como)
                    ->orWhere('direccion_normalizada', 'like', $como)
                    ->orWhere('direccion_original', 'like', $como)
                    ->orWhereHas('comercio', fn (Builder $c) => $c->where('codigo_unico', 'like', ltrim($termino, '0').'%'));
            }))
            ->when($desde, fn (Builder $q) => $q->where('fecha_registro', '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->where('fecha_registro', '<=', $hasta))
            ->when($filtros['seccional'] ?? null, fn (Builder $q, string $codigo) => $q->whereHas('seccional', fn (Builder $s) => $s->where('codigo', $codigo)))
            ->when($filtros['ciudad'] ?? null, fn (Builder $q, $id) => $q->where('ciudad_id', (int) $id))
            ->when($filtros['repartidor'] ?? null, fn (Builder $q, $id) => $q->where('repartidor_id', (int) $id))
            ->when($filtros['zona'] ?? null, fn (Builder $q, $id) => $q->where('zona_id', (int) $id))
            ->when($conEstado && $estado !== null && isset(self::GRUPOS[$estado]), fn (Builder $q) => $q->whereIn('estado', self::GRUPOS[$estado]))
            ->when($conEstado && $estado === 'riesgo', fn (Builder $q) => $q->ansEnRiesgo())
            ->when(($filtros['ans'] ?? null) === 'cumplido', fn (Builder $q) => $q->where('estado', EstadoGuia::Entregada)->whereColumn('entregada_at', '<=', 'ans_vence_at'))
            ->when(($filtros['ans'] ?? null) === 'vencido', fn (Builder $q) => $q->where(function (Builder $w): void {
                $w->where(fn (Builder $e) => $e->where('estado', EstadoGuia::Entregada)->whereColumn('entregada_at', '>', 'ans_vence_at'))
                    ->orWhere(fn (Builder $a) => $a->whereIn('estado', EstadoGuia::abiertos())->where('ans_vence_at', '<', now()));
            }))
            ->when(($filtros['ans'] ?? null) === 'riesgo', fn (Builder $q) => $q->ansEnRiesgo());
    }

    private static function fecha(?string $valor): ?CarbonImmutable
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valor) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
