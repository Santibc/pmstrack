<?php

namespace App\Models;

use Database\Factories\ComercioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('comercios')]
#[Fillable(['codigo_unico', 'nombre', 'cadena', 'zona_id'])]
class Comercio extends Model
{
    /** @use HasFactory<ComercioFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return HasMany<Guia, $this>
     */
    public function guias(): HasMany
    {
        return $this->hasMany(Guia::class);
    }
}
