<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posición que cada repartidor le dio a una dirección dentro de una zona.
 * Con esta memoria las guías conocidas llegan ya ordenadas (documento técnico 5.3).
 */
#[Table('orden_memoria')]
#[Fillable(['repartidor_id', 'zona_id', 'direccion_id', 'posicion'])]
class OrdenMemoria extends Model
{
    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return BelongsTo<Direccion, $this>
     */
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }
}
