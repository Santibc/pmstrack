<?php

namespace App\Models;

use Database\Factories\SeccionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('seccionales')]
#[Fillable(['codigo', 'nombre', 'nombre_normalizado', 'activo'])]
class Seccional extends Model
{
    /** @use HasFactory<SeccionalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * @return HasMany<Ciudad, $this>
     */
    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class);
    }

    /**
     * @return HasMany<Zona, $this>
     */
    public function zonas(): HasMany
    {
        return $this->hasMany(Zona::class)->orderBy('orden')->orderBy('codigo');
    }

    /**
     * @return HasOne<Bodega, $this>
     */
    public function bodega(): HasOne
    {
        return $this->hasOne(Bodega::class);
    }

    /**
     * @return HasMany<Repartidor, $this>
     */
    public function repartidores(): HasMany
    {
        return $this->hasMany(Repartidor::class);
    }

    /**
     * @param  Builder<Seccional>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * Seccionales que el usuario puede operar (un coordinador solo ve la suya).
     *
     * @param  Builder<Seccional>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        $query->when($user->seccionalRestringida(), fn (Builder $q, int $id) => $q->whereKey($id));
    }
}
