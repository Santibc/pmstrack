<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'seccional_id', 'telefono', 'cargo', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password_cambiada_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuarios')
            ->logOnly(['name', 'email', 'seccional_id', 'telefono', 'cargo', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Seccional, $this>
     */
    public function seccional(): BelongsTo
    {
        return $this->belongsTo(Seccional::class);
    }

    /**
     * @return HasOne<Repartidor, $this>
     */
    public function repartidor(): HasOne
    {
        return $this->hasOne(Repartidor::class);
    }

    /**
     * @return HasMany<Dispositivo, $this>
     */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    public function esAdministracion(): bool
    {
        return $this->hasAnyRole(Rol::administracion());
    }

    public function esCredibanco(): bool
    {
        return $this->hasRole(Rol::Credibanco->value);
    }

    public function esRepartidor(): bool
    {
        return $this->hasRole(Rol::Repartidor->value);
    }

    /**
     * Seccional a la que está limitado el usuario (coordinador de seccional).
     * Devuelve null cuando puede ver todas.
     */
    public function seccionalRestringida(): ?int
    {
        return $this->hasRole(Rol::Coordinador->value) ? $this->seccional_id : null;
    }

    public function puedeVerSeccional(int $seccionalId): bool
    {
        $restringida = $this->seccionalRestringida();

        return $restringida === null || $restringida === $seccionalId;
    }

    public function rolPrincipal(): ?Rol
    {
        $nombre = $this->roles->first()?->name;

        return $nombre ? Rol::tryFrom($nombre) : null;
    }

    /**
     * Ruta de inicio según el rol del usuario.
     */
    public function rutaInicio(): string
    {
        return match (true) {
            $this->esAdministracion() => route('admin.dashboard'),
            $this->esCredibanco() => route('portal.dashboard'),
            default => route('consulta'),
        };
    }

    public function iniciales(): string
    {
        return Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->take(2)
            ->map(fn (string $parte): string => Str::upper(Str::substr($parte, 0, 1)))
            ->implode('');
    }
}
