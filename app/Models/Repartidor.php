<?php

namespace App\Models;

use Database\Factories\RepartidorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Table('repartidores')]
#[Fillable(['user_id', 'seccional_id', 'cedula', 'celular', 'vehiculo_tipo', 'placa', 'zona_habitual_id', 'disponible', 'activo'])]
class Repartidor extends Model
{
    /** @use HasFactory<RepartidorFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('repartidores')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zonaHabitual(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_habitual_id');
    }

    /**
     * @return HasMany<Ruta, $this>
     */
    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class);
    }

    /**
     * @return HasMany<Visita, $this>
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class);
    }

    /**
     * @param  Builder<Repartidor>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function nombre(): Attribute
    {
        return Attribute::get(fn (): string => $this->user?->name ?? 'Repartidor');
    }

    /**
     * "Carlos R." — forma corta para mostrar a Credibanco y en la consulta pública.
     *
     * @return Attribute<string, never>
     */
    protected function nombreCorto(): Attribute
    {
        return Attribute::get(function (): string {
            $partes = Str::of($this->nombre)->squish()->explode(' ');
            $apellido = $partes->get(1);

            return $partes->first().($apellido ? ' '.Str::upper(Str::substr($apellido, 0, 1)).'.' : '');
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function iniciales(): Attribute
    {
        return Attribute::get(fn (): string => $this->user?->iniciales() ?? 'R');
    }

    /**
     * "Moto · ABC12D".
     *
     * @return Attribute<string|null, never>
     */
    protected function vehiculo(): Attribute
    {
        return Attribute::get(fn (): ?string => collect([$this->vehiculo_tipo, $this->placa])->filter()->implode(' · ') ?: null);
    }
}
