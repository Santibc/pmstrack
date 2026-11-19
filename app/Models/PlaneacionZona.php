<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('planeacion_zonas')]
#[Fillable(['planeacion_id', 'zona_id', 'repartidor_id'])]
class PlaneacionZona extends Model
{
    /**
     * @return BelongsTo<Planeacion, $this>
     */
    public function planeacion(): BelongsTo
    {
        return $this->belongsTo(Planeacion::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }
}
