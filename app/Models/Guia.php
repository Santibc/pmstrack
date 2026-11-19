<?php

namespace App\Models;

use App\Enums\EstadoGuia;
use App\Enums\SemaforoAns;
use App\Support\Duracion;
use Carbon\CarbonInterface;
use Database\Factories\GuiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una guía es un servicio de entrega de rollos solicitado por Credibanco.
 * El número de SERVICIO es su llave única.
 */
#[Table('guias')]
#[Fillable([
    'servicio', 'numero_guia', 'carga_id', 'comercio_id', 'direccion_id', 'ciudad_id', 'seccional_id',
    'zona_id', 'estado', 'nombre_comercio', 'cadena', 'direccion_original', 'direccion_normalizada',
    'complemento', 'ciudad_original', 'departamento', 'codigo_ciudad', 'geolocalizacion', 'telefono',
    'contacto_nombre', 'contacto_telefono', 'horario_comercio', 'horario_atencion', 'rollos_solicitados',
    'observaciones', 'alertas', 'fecha_registro', 'ans_horas', 'ans_vence_at', 'es_urgente',
    'repartidor_id', 'intentos', 'fecha_programada', 'entregada_at', 'rollos_entregados', 'cerrada_at',
    'ultimo_movimiento', 'ultimo_movimiento_at',
])]
class Guia extends Model
{
    /** @use HasFactory<GuiaFactory> */
    use HasFactory;

    /** Porcentaje del tiempo de ANS restante por debajo del cual la guía está en riesgo. */
    public const float UMBRAL_RIESGO = 0.25;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoGuia::class,
            'alertas' => 'array',
            'fecha_registro' => 'datetime',
            'ans_vence_at' => 'datetime',
            'es_urgente' => 'boolean',
            'fecha_programada' => 'date',
            'entregada_at' => 'datetime',
            'cerrada_at' => 'datetime',
            'ultimo_movimiento_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'servicio';
    }

    /**
     * @return BelongsTo<Carga, $this>
     */
    public function carga(): BelongsTo
    {
        return $this->belongsTo(Carga::class);
    }

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return BelongsTo<Direccion, $this>
     */
    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    /**
     * @return BelongsTo<Ciudad, $this>
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
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
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return BelongsTo<Repartidor, $this>
     */
    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    /**
     * @return HasMany<GuiaEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(GuiaEvento::class)->orderBy('ocurrido_at')->orderBy('id');
    }

    /**
     * @return HasMany<Parada, $this>
     */
    public function paradas(): HasMany
    {
        return $this->hasMany(Parada::class);
    }

    /**
     * @return HasMany<Visita, $this>
     */
    public function visitas(): HasMany
    {
        return $this->hasMany(Visita::class)->orderBy('registrada_at');
    }

    /**
     * Última visita vigente (no anulada por la administradora).
     *
     * @return HasOne<Visita, $this>
     */
    public function ultimaVisita(): HasOne
    {
        return $this->hasOne(Visita::class)->whereNull('anulada_at')->latestOfMany('registrada_at');
    }

    /**
     * @param  Builder<Guia>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereIn('estado', EstadoGuia::abiertos());
    }

    /**
     * @param  Builder<Guia>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        $query->when($user->seccionalRestringida(), fn (Builder $q, int $id) => $q->where('seccional_id', $id));
    }

    /**
     * Guías abiertas con menos del 25 % del tiempo de ANS restante (o ya vencidas).
     *
     * @param  Builder<Guia>  $query
     */
    public function scopeAnsEnRiesgo(Builder $query, ?CarbonInterface $ahora = null): void
    {
        $ahora ??= now();

        $query->abiertas()->where(function (Builder $q) use ($ahora) {
            // Equivale a: restante < 25 % de la ventana  ⇔  vence − ahora < 0,25 · ans_horas.
            $q->whereRaw(
                'ans_vence_at <= '.self::sqlSumarHoras('?', 'ans_horas * '.self::UMBRAL_RIESGO),
                [$ahora->toDateTimeString()],
            );
        });
    }

    /**
     * Guías que se pueden incluir en la planeación de una fecha: listas para
     * asignar o no entregadas cuya reprogramación ya llegó.
     *
     * @param  Builder<Guia>  $query
     */
    public function scopePlaneablesPara(Builder $query, CarbonInterface $fecha, int $maxIntentos): void
    {
        $query->whereNotNull('zona_id')->where(function (Builder $q) use ($fecha, $maxIntentos) {
            $q->where(function (Builder $lista) use ($fecha) {
                $lista->where('estado', EstadoGuia::ListaParaAsignar)
                    ->where(fn (Builder $f) => $f->whereNull('fecha_programada')->orWhereDate('fecha_programada', '<=', $fecha));
            })->orWhere(function (Builder $reprogramada) use ($fecha, $maxIntentos) {
                $reprogramada->where('estado', EstadoGuia::NoEntregada)
                    ->where('intentos', '<', $maxIntentos)
                    ->whereDate('fecha_programada', '<=', $fecha);
            });
        });
    }

    public function semaforo(?CarbonInterface $ahora = null): SemaforoAns
    {
        $ahora ??= now();

        if ($this->estado === EstadoGuia::Entregada && $this->entregada_at) {
            return $this->entregada_at->lessThanOrEqualTo($this->ans_vence_at)
                ? SemaforoAns::Cumplido
                : SemaforoAns::Incumplido;
        }

        if ($this->estado->esFinal()) {
            return SemaforoAns::NoAplica;
        }

        if ($ahora->greaterThan($this->ans_vence_at)) {
            return SemaforoAns::Vencido;
        }

        $ventana = max(1, $this->fecha_registro->diffInSeconds($this->ans_vence_at));
        $restante = $ahora->diffInSeconds($this->ans_vence_at);

        return $restante / $ventana < self::UMBRAL_RIESGO ? SemaforoAns::EnRiesgo : SemaforoAns::EnCurso;
    }

    /**
     * Texto del semáforo como en el portal: "Cumplido · 41 h", "En riesgo · 1 h 50 min".
     */
    public function semaforoTexto(?CarbonInterface $ahora = null): string
    {
        $ahora ??= now();
        $semaforo = $this->semaforo($ahora);

        return match ($semaforo) {
            SemaforoAns::NoAplica => $semaforo->etiqueta(),
            SemaforoAns::Cumplido, SemaforoAns::Incumplido => $semaforo->etiqueta().' · '.Duracion::horas($this->minutosHastaEntrega()),
            SemaforoAns::Vencido => $semaforo->etiqueta().' · '.Duracion::horas((int) $this->fecha_registro->diffInMinutes($ahora)),
            SemaforoAns::EnRiesgo => $semaforo->etiqueta().' · '.Duracion::corta((int) $ahora->diffInMinutes($this->ans_vence_at)),
            SemaforoAns::EnCurso => $semaforo->etiqueta().' · '.Duracion::horas((int) $ahora->diffInMinutes($this->ans_vence_at)),
        };
    }

    /**
     * Minutos entre la solicitud en Credibanco y la entrega.
     */
    public function minutosHastaEntrega(): ?int
    {
        return $this->entregada_at ? (int) $this->fecha_registro->diffInMinutes($this->entregada_at) : null;
    }

    public function cumplioAns(): ?bool
    {
        return $this->entregada_at ? $this->entregada_at->lessThanOrEqualTo($this->ans_vence_at) : null;
    }

    public function direccionCompleta(): string
    {
        return trim($this->direccion_normalizada.' '.($this->complemento ?? ''));
    }

    /**
     * Teléfono al que el repartidor debe llamar: primero el contacto real de las observaciones.
     */
    public function telefonoDeContacto(): ?string
    {
        return $this->contacto_telefono ?: $this->telefono;
    }

    /**
     * Expresión SQL "fecha + N horas" válida en MariaDB/MySQL y en SQLite (pruebas).
     */
    private static function sqlSumarHoras(string $fecha, string $horas): string
    {
        return self::query()->getConnection()->getDriverName() === 'sqlite'
            ? "datetime({$fecha}, '+' || CAST(ROUND(({$horas}) * 60) AS INTEGER) || ' minutes')"
            : "DATE_ADD({$fecha}, INTERVAL ROUND(({$horas}) * 60) MINUTE)";
    }
}
