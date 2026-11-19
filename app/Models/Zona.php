<?php

namespace App\Models;

use Database\Factories\ZonaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Table('zonas')]
#[Fillable(['seccional_id', 'codigo', 'nombre', 'color', 'orden', 'activo'])]
class Zona extends Model
{
    /** @use HasFactory<ZonaFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('configuracion')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * @return HasMany<Direccion, $this>
     */
    public function direcciones(): HasMany
    {
        return $this->hasMany(Direccion::class);
    }

    /**
     * @return HasMany<Guia, $this>
     */
    public function guias(): HasMany
    {
        return $this->hasMany(Guia::class);
    }

    /**
     * @param  Builder<Zona>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * "Z01 Norte · Usaquén".
     *
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->codigo.' '.$this->nombre));
    }
}
