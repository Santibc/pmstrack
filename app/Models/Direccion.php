<?php

namespace App\Models;

use Database\Factories\DireccionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Directorio de direcciones: la memoria con la que el sistema asigna la zona
 * automáticamente desde la segunda vez que llega una dirección.
 */
#[Table('direcciones')]
#[Fillable(['ciudad_id', 'clave_normalizada', 'direccion', 'zona_id', 'es_rural', 'zonificada_por', 'zonificada_at'])]
class Direccion extends Model
{
    /** @use HasFactory<DireccionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_rural' => 'boolean',
            'zonificada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function zonificadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zonificada_por');
    }

    /**
     * @return HasMany<Guia, $this>
     */
    public function guias(): HasMany
    {
        return $this->hasMany(Guia::class);
    }

    /**
     * @param  Builder<Direccion>  $query
     */
    public function scopeSinZona(Builder $query): void
    {
        $query->whereNull('zona_id');
    }

    /**
     * @param  Builder<Direccion>  $query
     */
    public function scopeDeSeccional(Builder $query, int $seccionalId): void
    {
        $query->whereHas('ciudad', fn (Builder $q) => $q->where('seccional_id', $seccionalId));
    }
}
