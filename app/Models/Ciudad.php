<?php

namespace App\Models;

use Database\Factories\CiudadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ciudades')]
#[Fillable(['seccional_id', 'nombre', 'nombre_normalizado', 'departamento', 'departamento_normalizado', 'codigo_dane', 'activo'])]
class Ciudad extends Model
{
    /** @use HasFactory<CiudadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }
}
