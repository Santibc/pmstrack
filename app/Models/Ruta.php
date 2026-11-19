<?php

namespace App\Models;

use App\Enums\EstadoParada;
use App\Enums\EstadoRuta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La ruta de un repartidor en un día. Puede cubrir varias zonas.
 */
#[Table('rutas')]
#[Fillable([
    'planeacion_id', 'repartidor_id', 'seccional_id', 'fecha', 'estado', 'iniciada_at', 'cerrada_at',
    'orden_guardado_at', 'rollos_salida', 'rollos_devueltos_declarados', 'rollos_devueltos_esperados',
    'cuadre_ok', 'observacion_cierre',
])]
class Ruta extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoRuta::class,
            'fecha' => 'date',
            'iniciada_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'orden_guardado_at' => 'datetime',
            'cuadre_ok' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Planeacion, $this>
     */
    public function planeacion(): BelongsTo
    {
        return $this->belongsTo(Planeacion::class);
    }

    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * Paradas vigentes de la ruta (excluye las que se reasignaron a otro repartidor).
     *
     * @return HasMany<Parada, $this>
     */
    public function paradas(): HasMany
    {
        return $this->hasMany(Parada::class)->whereIn('estado', EstadoParada::activas());
    }

    /**
     * @return HasMany<Parada, $this>
     */
    public function todasLasParadas(): HasMany
    {
        return $this->hasMany(Parada::class);
    }

    /**
     * @return HasMany<Visita, $this>
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class);
    }

    /**
     * @return HasMany<Novedad, $this>
     */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    /**
     * @param  Builder<Ruta>  $query
     */
    public function scopeDelDia(Builder $query, mixed $fecha = null): void
    {
        $query->whereDate('fecha', $fecha ?? today());
    }

    public function estaCerrada(): bool
    {
        return $this->estado === EstadoRuta::Cerrada;
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === EstadoRuta::EnCurso;
    }
}
