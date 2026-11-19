<?php

namespace App\Models;

use App\Enums\TipoCausal;
use Database\Factories\CausalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Table('causales')]
#[Fillable(['tipo', 'codigo', 'nombre', 'afecta_calidad_datos', 'orden', 'activo'])]
class Causal extends Model
{
    /** @use HasFactory<CausalFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCausal::class,
            'afecta_calidad_datos' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('configuracion')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @param  Builder<Causal>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
