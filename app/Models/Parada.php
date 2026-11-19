<?php

namespace App\Models;

use App\Enums\EstadoParada;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una guía dentro de la ruta de un repartidor.
 */
#[Table('paradas')]
#[Fillable(['ruta_id', 'guia_id', 'zona_id', 'orden', 'es_nueva', 'estado', 'visitada_at'])]
class Parada extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoParada::class,
            'es_nueva' => 'boolean',
            'visitada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    /**
     * @return BelongsTo<Guia, $this>
     */
    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return HasMany<Visita, $this>
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class);
    }

    /**
     * @return HasOne<Visita, $this>
     */
    public function visita(): HasOne
    {
        return $this->hasOne(Visita::class)->whereNull('anulada_at')->latestOfMany('registrada_at');
    }

    /**
     * Orden de visita: primero las nuevas sin ordenar, luego el orden guardado.
     *
     * @param  Builder<Parada>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderByDesc('es_nueva')->orderByRaw('orden IS NULL')->orderBy('orden')->orderBy('id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoParada::Pendiente;
    }
}
