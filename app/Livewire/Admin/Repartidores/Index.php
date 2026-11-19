<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Repartidores;

use App\Enums\ResultadoVisita;
use App\Enums\Rol;
use App\Livewire\Concerns\Notifica;
use App\Models\Repartidor;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Zona;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Repartidores: sus datos, su acceso a la app y su disponibilidad del día.
 */
#[Title('Repartidores')]
class Index extends Component
{
    use Notifica;

    public const array VEHICULOS = ['Moto', 'Carro', 'Bicicleta', 'A pie'];

    #[Url(except: '')]
    public string $seccional = '';

    public string $buscar = '';

    public bool $mostrarFormulario = false;

    public ?int $repartidorId = null;

    public string $nombre = '';

    public string $cedula = '';

    public string $celular = '';

    public string $email = '';

    public string $vehiculoTipo = 'Moto';

    public string $placa = '';

    public string $seccionalId = '';

    public string $zonaHabitualId = '';

    public string $password = '';

    public function nuevo(): void
    {
        $this->resetValidation();
        $this->reset('repartidorId', 'nombre', 'cedula', 'celular', 'email', 'placa', 'zonaHabitualId', 'password');
        $this->vehiculoTipo = 'Moto';
        $this->seccionalId = (string) ($this->seccionales()->firstWhere('codigo', $this->seccional)?->id ?? $this->seccionales()->first()?->id);
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $repartidor = $this->consulta()->with('user')->findOrFail($id);

        $this->resetValidation();
        $this->repartidorId = $repartidor->id;
        $this->nombre = $repartidor->user->name;
        $this->cedula = $repartidor->cedula;
        $this->celular = (string) $repartidor->celular;
        $this->email = str_ends_with($repartidor->user->email, '@repartidor.invalid') ? '' : $repartidor->user->email;
        $this->vehiculoTipo = $repartidor->vehiculo_tipo ?: 'Moto';
        $this->placa = (string) $repartidor->placa;
        $this->seccionalId = (string) $repartidor->seccional_id;
        $this->zonaHabitualId = (string) $repartidor->zona_habitual_id;
        $this->password = '';
        $this->mostrarFormulario = true;
    }

    public function guardar(): void
    {
        $this->cedula = Texto::digitos($this->cedula);
        $this->celular = Texto::digitos($this->celular);
        $usuarioId = $this->repartidorId ? Repartidor::query()->whereKey($this->repartidorId)->value('user_id') : null;

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'cedula' => ['required', 'digits_between:5,12', Rule::unique('repartidores', 'cedula')->ignore($this->repartidorId)],
            'celular' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuarioId)],
            'vehiculoTipo' => ['required', Rule::in(self::VEHICULOS)],
            'placa' => ['nullable', 'string', 'max:10'],
            'seccionalId' => ['required', Rule::in($this->seccionales()->pluck('id')->all())],
            'zonaHabitualId' => ['nullable', Rule::exists('zonas', 'id')->where('seccional_id', (int) $this->seccionalId)],
            'password' => [$this->repartidorId ? 'nullable' : 'required', Password::defaults()],
        ], attributes: ['cedula' => 'cédula', 'email' => 'correo', 'vehiculoTipo' => 'vehículo', 'seccionalId' => 'seccional', 'zonaHabitualId' => 'zona habitual', 'password' => 'contraseña']);

        DB::transaction(function () use ($datos, $usuarioId): void {
            $usuario = $usuarioId ? User::query()->findOrFail($usuarioId) : new User;

            $usuario->fill([
                'name' => trim($datos['nombre']),
                // Los repartidores entran con la cédula; el correo es opcional.
                'email' => $datos['email'] ?: $datos['cedula'].'@repartidor.invalid',
                'seccional_id' => (int) $datos['seccionalId'],
                'telefono' => $datos['celular'] ?: null,
                'cargo' => 'Repartidor',
            ]);

            if ($datos['password']) {
                $usuario->password = $datos['password'];
                $usuario->password_cambiada_at = now();
            }

            $usuario->save();

            if (! $usuario->hasRole(Rol::Repartidor->value)) {
                $usuario->assignRole(Rol::Repartidor->value);
            }

            Repartidor::query()->updateOrCreate(['id' => $this->repartidorId], [
                'user_id' => $usuario->id,
                'seccional_id' => (int) $datos['seccionalId'],
                'cedula' => $datos['cedula'],
                'celular' => $datos['celular'] ?: null,
                'vehiculo_tipo' => $datos['vehiculoTipo'],
                'placa' => $datos['placa'] ? mb_strtoupper(trim($datos['placa'])) : null,
                'zona_habitual_id' => $datos['zonaHabitualId'] ?: null,
            ]);

            // Con contraseña nueva, los celulares deben volver a iniciar sesión.
            if ($datos['password'] && $usuarioId) {
                $usuario->tokens()->delete();
            }
        });

        $this->mostrarFormulario = false;
        $this->avisar($this->repartidorId ? 'Repartidor actualizado.' : 'Repartidor creado. Ya puede entrar a la app con su cédula y la contraseña.');
    }

    public function alternarDisponible(int $id): void
    {
        $repartidor = $this->consulta()->with('user')->findOrFail($id);
        $repartidor->update(['disponible' => ! $repartidor->disponible]);

        $this->avisar($repartidor->disponible ? "{$repartidor->nombre} vuelve a estar disponible." : "{$repartidor->nombre} no estará disponible: no aparece para asignarle zonas.");
    }

    public function alternarActivo(int $id): void
    {
        $repartidor = $this->consulta()->with('user')->findOrFail($id);

        DB::transaction(function () use ($repartidor): void {
            $repartidor->update(['activo' => ! $repartidor->activo]);
            $repartidor->user->update(['activo' => $repartidor->activo]);

            if (! $repartidor->activo) {
                $repartidor->user->tokens()->delete();
            }
        });

        $this->avisar($repartidor->activo ? "{$repartidor->nombre} quedó activo." : "{$repartidor->nombre} quedó inactivo y se cerró su sesión en la app.");
    }

    public function cerrarSesiones(int $id): void
    {
        $repartidor = $this->consulta()->with('user')->findOrFail($id);
        $repartidor->user->tokens()->delete();

        $this->avisar("Se cerró la sesión de {$repartidor->nombre} en todos sus celulares.");
    }

    public function render()
    {
        $inicioMes = now()->startOfMonth();

        return view('livewire.admin.repartidores.index', [
            'seccionales' => $this->seccionales(),
            'zonasFormulario' => $this->seccionalId !== '' ? Zona::query()->where('seccional_id', (int) $this->seccionalId)->activas()->orderBy('orden')->get() : collect(),
            'repartidores' => $this->consulta()
                ->with(['user.dispositivos', 'seccional', 'zonaHabitual'])
                ->withCount([
                    'visitas as entregas_mes' => fn (Builder $q) => $q->whereNull('anulada_at')->where('resultado', ResultadoVisita::Entregado)->where('registrada_at', '>=', $inicioMes),
                    'visitas as visitas_mes' => fn (Builder $q) => $q->whereNull('anulada_at')->where('registrada_at', '>=', $inicioMes),
                ])
                ->when($this->seccional !== '', fn (Builder $q) => $q->whereHas('seccional', fn (Builder $s) => $s->where('codigo', $this->seccional)))
                ->when(trim($this->buscar) !== '', fn (Builder $q) => $q->where(function (Builder $w): void {
                    $w->where('cedula', 'like', '%'.Texto::digitos($this->buscar).'%')
                        ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', '%'.trim($this->buscar).'%'));
                }))
                ->get()
                ->sortBy([['activo', 'desc'], ['nombre', 'asc']])
                ->values(),
        ]);
    }

    /**
     * @return Builder<Repartidor>
     */
    private function consulta(): Builder
    {
        return Repartidor::query()->whereIn('seccional_id', $this->seccionales()->pluck('id'));
    }

    private function seccionales()
    {
        return once(fn () => Seccional::query()->activas()->visiblesPara(auth()->user())->orderBy('id')->get());
    }
}
