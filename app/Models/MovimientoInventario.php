<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del inventario de rollos de una bodega. Se crea únicamente
 * desde InventarioService, que mantiene el saldo de la bodega.
 */
#[Table('movimientos_inventario')]
#[Fillable([
    'bodega_id', 'tipo', 'cantidad', 'saldo', 'detalle', 'remision_numero', 'remision_fecha',
    'motivo', 'origen', 'ruta_id', 'repartidor_id', 'user_id',
])]
class MovimientoInventario extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'remision_fecha' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Bodega, $this>
     */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    /**
     * @return BelongsTo<Ruta, $this>
     */
    public function ruta(): BelongsTo
    {
        return $this->belongsTo(Ruta::class);
    }

    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quién registró el movimiento, como se muestra en la tabla de inventario.
     */
    public function registradoPor(): string
    {
        return match ($this->origen) {
            'planeacion' => 'Planeación',
            'app' => ($this->repartidor?->nombre ?? 'Repartidor').' (app)',
            'sistema' => 'Sistema',
            default => $this->user?->name ?? 'Administración',
        };
    }
}
