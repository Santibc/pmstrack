<?php

namespace App\Models;

use App\Enums\ResultadoFila;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del Excel ya validada, a la espera de que se confirme la carga.
 */
#[Table('carga_filas')]
#[Fillable(['carga_id', 'numero_fila', 'servicio', 'resultado', 'datos', 'alertas', 'zona_id', 'zona_origen', 'guia_id'])]
class CargaFila extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoFila::class,
            'datos' => 'array',
            'alertas' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Carga, $this>
     */
    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return BelongsTo<Guia, $this>
     */
    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
    }

    public function tieneAlerta(string $codigo): bool
    {
        return collect($this->alertas ?? [])->contains('codigo', $codigo);
    }
}
