<?php

namespace App\Http\Resources;

use App\Enums\EstadoParada;
use App\Enums\ResultadoVisita;
use App\Models\Parada;
use App\Models\Ruta;
use App\Services\Parametros;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * La ruta del día del repartidor: resumen, zonas y paradas en orden de visita.
 * Requiere las relaciones cargadas por RutaResource::cargar().
 *
 * @mixin Ruta
 */
class RutaResource extends JsonResource
{
    /**
     * Relaciones que necesita el recurso, con las paradas ya en orden de visita.
     */
    public static function cargar(Ruta $ruta): Ruta
    {
        return $ruta->load([
            'paradas' => fn ($q) => $q->enOrden(),
            'paradas.zona',
            'paradas.guia.ciudad',
            'paradas.visita.causal',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Parada> $paradas */
        $paradas = $this->paradas;
        $entregados = (int) $paradas->sum(fn (Parada $p): int => $p->visita?->resultado === ResultadoVisita::Entregado ? $p->visita->rollos_entregados : 0);

        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'estado' => $this->estado->value,
            'iniciada_at' => $this->iniciada_at?->toIso8601String(),
            'cerrada_at' => $this->cerrada_at?->toIso8601String(),
            'orden_guardado_at' => $this->orden_guardado_at?->toIso8601String(),
            'max_intentos' => app(Parametros::class)->maxIntentos(),
            'rollos_por_caja' => app(Parametros::class)->rollosPorCaja(),
            'rollos_salida' => $this->rollos_salida,
            'resumen' => [
                'guias' => $paradas->count(),
                'rollos' => (int) $paradas->sum('guia.rollos_solicitados'),
                'urgentes' => $paradas->where('guia.es_urgente', true)->where('estado', EstadoParada::Pendiente)->count(),
                'nuevas' => $paradas->where('es_nueva', true)->where('estado', EstadoParada::Pendiente)->count(),
                'visitadas' => $paradas->filter(fn (Parada $p): bool => $p->estado->fueVisitada())->count(),
                'entregadas' => $paradas->where('estado', EstadoParada::Entregada)->count(),
                'no_entregadas' => $paradas->where('estado', EstadoParada::NoEntregada)->count(),
                'anuladas' => $paradas->where('estado', EstadoParada::Anulada)->count(),
                'pendientes' => $paradas->where('estado', EstadoParada::Pendiente)->count(),
                'rollos_entregados' => $entregados,
            ],
            'zonas' => $paradas->groupBy('zona_id')->map(fn (Collection $grupo): array => [
                'id' => $grupo->first()->zona?->id,
                'codigo' => $grupo->first()->zona?->codigo,
                'nombre' => $grupo->first()->zona?->nombre,
                'color' => $grupo->first()->zona?->color,
                'guias' => $grupo->count(),
                'rollos' => (int) $grupo->sum('guia.rollos_solicitados'),
            ])->values(),
            'paradas' => ParadaResource::collection($paradas),
            'cierre' => $this->estaCerrada() ? [
                'rollos_salida' => $this->rollos_salida,
                'rollos_entregados' => $entregados,
                'rollos_devueltos_declarados' => $this->rollos_devueltos_declarados,
                'rollos_devueltos_esperados' => $this->rollos_devueltos_esperados,
                'cuadre_ok' => $this->cuadre_ok,
            ] : null,
        ];
    }
}
