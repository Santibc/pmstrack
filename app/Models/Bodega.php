<?php

namespace App\Models;

use Database\Factories\BodegaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('bodegas')]
#[Fillable(['seccional_id', 'nombre', 'stock_minimo'])]
class Bodega extends Model
{
    /** @use HasFactory<BodegaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function estaBajoMinimo(): bool
    {
        return $this->stock_minimo > 0 && $this->saldo < $this->stock_minimo;
    }
}
