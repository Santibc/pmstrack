<?php

namespace App\Models;

use App\Enums\ResultadoVisita;
use App\Enums\TipoEvidencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Resultado de visitar un comercio. Una visita registrada no se edita:
 * solo la administradora puede anularla, con justificación.
 */
#[Table('visitas')]
#[Fillable([
    'uuid', 'parada_id', 'guia_id', 'ruta_id', 'repartidor_id', 'resultado', 'causal_id', 'observacion',
    'numero_intento', 'rollos_solicitados', 'rollos_entregados', 'recibe_nombre', 'recibe_cedula',
    'recibe_telefono', 'autoriza_datos', 'latitud', 'longitud', 'precision_m', 'registrada_at',
    'sincronizada_at', 'dispositivo', 'app_version', 'anulada_at', 'anulada_por', 'anulacion_motivo',
])]
class Visita extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoVisita::class,
            'autoriza_datos' => 'boolean',
            'latitud' => 'float',
            'longitud' => 'float',
            'precision_m' => 'float',
            'registrada_at' => 'datetime',
            'sincronizada_at' => 'datetime',
            'anulada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Parada, $this>
     */
    public function parada(): BelongsTo
    {
        return $this->belongsTo(Parada::class);
    }

    /**
     * @return BelongsTo<Guia, $this>
     */
    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
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
     * @return BelongsTo<Causal, $this>
     */
    public function causal(): BelongsTo
    {
        return $this->belongsTo(Causal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    /**
     * @return HasMany<Evidencia, $this>
     */
    public function evidencias(): HasMany
    {
        return $this->hasMany(Evidencia::class);
    }

    /**
     * @param  Builder<Visita>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('anulada_at');
    }

    public function evidencia(TipoEvidencia $tipo): ?Evidencia
    {
        return $this->evidencias->firstWhere('tipo', $tipo);
    }

    public function estaAnulada(): bool
    {
        return $this->anulada_at !== null;
    }

    public function esParcial(): bool
    {
        return $this->resultado === ResultadoVisita::Entregado && $this->rollos_entregados < $this->rollos_solicitados;
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * Nombre enmascarado para la consulta pública: "Rosa C***".
     */
    public function recibeNombreEnmascarado(): ?string
    {
        if (blank($this->recibe_nombre)) {
            return null;
        }

        $partes = Str::of($this->recibe_nombre)->squish()->explode(' ');
        $apellido = $partes->get(1);

        return Str::title($partes->first()).($apellido ? ' '.Str::upper(Str::substr($apellido, 0, 1)).'***' : '');
    }

    /**
     * Cédula enmascarada para la consulta pública: "****4418".
     */
    public function recibeCedulaEnmascarada(): ?string
    {
        if (blank($this->recibe_cedula)) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $this->recibe_cedula);

        return '****'.Str::substr($digitos, -4);
    }
}
