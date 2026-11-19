<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Planeación del día de una seccional: qué repartidor atiende cada zona.
 */
#[Table('planeaciones')]
#[Fillable(['seccional_id', 'fecha', 'estado', 'publicada_at', 'publicada_por'])]
class Planeacion extends Model
{
    public const string BORRADOR = 'borrador';

    public const string PUBLICADA = 'publicada';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'publicada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publicadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicada_por');
    }

    /**
     * @return HasMany<PlaneacionZona, $this>
     */
    public function zonas(): HasMany
    {
        return $this->hasMany(PlaneacionZona::class);
    }

    /**
     * @return HasMany<Ruta, $this>
     */
    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class);
    }

    public function estaPublicada(): bool
    {
        return $this->estado === self::PUBLICADA;
    }
}
