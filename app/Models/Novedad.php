<?php

namespace App\Models;

use App\Enums\TipoNovedad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incidente que un repartidor reporta desde la app (vía cerrada, accidente…).
 */
#[Table('novedades')]
#[Fillable([
    'uuid', 'repartidor_id', 'ruta_id', 'guia_id', 'tipo', 'descripcion', 'latitud', 'longitud',
    'reportada_at', 'estado', 'atendida_por', 'atendida_at', 'respuesta',
])]
class Novedad extends Model
{
    public const string ABIERTA = 'abierta';

    public const string ATENDIDA = 'atendida';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoNovedad::class,
            'latitud' => 'float',
            'longitud' => 'float',
            'reportada_at' => 'datetime',
            'atendida_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
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
     * @return BelongsTo<User, $this>
     */
    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }

    /**
     * @param  Builder<Novedad>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->where('estado', self::ABIERTA);
    }
}
