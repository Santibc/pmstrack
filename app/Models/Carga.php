<?php

namespace App\Models;

use App\Enums\EstadoCarga;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una subida del Excel diario de Credibanco. Queda "pendiente" mientras la
 * administradora revisa la validación y pasa a "confirmada" al crear las guías.
 */
#[Table('cargas')]
#[Fillable([
    'user_id', 'nombre_archivo', 'ruta_archivo', 'hash_sha256', 'hoja', 'estado',
    'columnas_reconocidas', 'total_filas', 'nuevos', 'duplicados', 'errores',
    'con_zona', 'por_zonificar', 'urgentes', 'con_alertas', 'total_rollos', 'confirmada_at',
])]
class Carga extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoCarga::class,
            'confirmada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CargaFila, $this>
     */
    public function filas(): HasMany
    {
        return $this->hasMany(CargaFila::class)->orderBy('numero_fila');
    }

    /**
     * @return HasMany<Guia, $this>
     */
    public function guias(): HasMany
    {
        return $this->hasMany(Guia::class);
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoCarga::Pendiente;
    }
}
