<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Configuracion;

use App\Enums\Rol;
use App\Enums\TipoCausal;
use App\Livewire\Concerns\Notifica;
use App\Models\Bodega;
use App\Models\Causal;
use App\Models\Seccional;
use App\Models\User;
use App\Services\Parametros;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Configuración: parámetros de operación, causales, mínimos de bodega,
 * usuarios y roles, y la bitácora de cambios.
 */
#[Title('Configuración')]
class Index extends Component
{
    use Notifica;

    public const array GRUPOS = [
        'ans' => 'ANS',
        'operacion' => 'Operación',
        'inventario' => 'Inventario',
        'alertas' => 'Alertas del seguimiento',
        'seguridad' => 'Seguridad',
        'contacto' => 'Datos de contacto de PMS',
        'app' => 'App del repartidor',
    ];

    /** parametros | causales | bodegas | usuarios | bitacora */
    #[Url(as: 'ver', except: 'parametros')]
    public string $pestana = 'parametros';

    /** @var array<string, string> */
    public array $valores = [];

    /** @var array<int, int|string> Stock mínimo por bodega. */
    public array $minimos = [];

    // Causal.
    public bool $mostrarCausal = false;

    public ?int $causalId = null;

    public string $causalTipo = 'no_entregado';

    public string $causalNombre = '';

    public bool $causalCalidad = false;

    // Usuario.
    public bool $mostrarUsuario = false;

    public ?int $usuarioId = null;

    public string $usuarioNombre = '';

    public string $usuarioEmail = '';

    public string $usuarioRol = 'administradora';

    public string $usuarioSeccional = '';

    public string $usuarioCargo = '';

    public string $usuarioPassword = '';

    public function mount(Parametros $parametros): void
    {
        foreach (array_keys(Parametros::DEFINICION) as $clave) {
            $this->valores[$clave] = $parametros->texto($clave);
        }

        $this->minimos = Bodega::query()->pluck('stock_minimo', 'id')->all();
    }

    public function guardarParametros(Parametros $parametros): void
    {
        Gate::authorize('configuracion.gestionar');

        $this->validate([
            'valores.ans_horas_defecto' => ['required', 'integer', 'min:1', 'max:2000'],
            'valores.ans_tipo_horas' => ['required', 'in:calendario,habiles'],
            'valores.ans_umbral_urgente_horas' => ['required', 'integer', 'min:1', 'max:200'],
            'valores.ans_meta_cumplimiento' => ['required', 'integer', 'min:1', 'max:100'],
            'valores.horario_laboral_inicio' => ['required', 'date_format:H:i'],
            'valores.horario_laboral_fin' => ['required', 'date_format:H:i', 'after:valores.horario_laboral_inicio'],
            'valores.max_intentos' => ['required', 'integer', 'min:1', 'max:10'],
            'valores.guia_consecutivo' => ['required', 'integer', 'min:1'],
            'valores.rollos_por_caja' => ['required', 'integer', 'min:1', 'max:10000'],
            'valores.alerta_sin_actividad_min' => ['required', 'integer', 'min:5', 'max:600'],
            'valores.alerta_inicio_ruta_hora' => ['required', 'date_format:H:i'],
            'valores.bloqueo_intentos' => ['required', 'integer', 'min:3', 'max:20'],
            'valores.bloqueo_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'valores.consulta_publica_limite' => ['required', 'integer', 'min:1', 'max:120'],
            'valores.contacto_linea' => ['nullable', 'string', 'max:80'],
            'valores.contacto_direccion' => ['nullable', 'string', 'max:160'],
            'valores.app_version_actual' => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
            'valores.app_version_minima' => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
        ], attributes: collect(Parametros::DEFINICION)->mapWithKeys(fn (array $d, string $clave): array => ["valores.{$clave}" => Str::lower($d['etiqueta'])])->all());

        foreach ($this->valores as $clave => $valor) {
            if (isset(Parametros::DEFINICION[$clave]) && $parametros->texto($clave) !== (string) $valor) {
                $parametros->guardar($clave, (string) $valor);
            }
        }

        $this->avisar('Parámetros guardados. Aplican desde ya.');
    }

    public function guardarMinimo(int $bodegaId): void
    {
        Gate::authorize('configuracion.gestionar');

        $this->validate(["minimos.{$bodegaId}" => ['required', 'integer', 'min:0', 'max:10000000']], attributes: ["minimos.{$bodegaId}" => 'stock mínimo']);

        Bodega::query()->findOrFail($bodegaId)->update(['stock_minimo' => (int) $this->minimos[$bodegaId]]);
        $this->avisar('Stock mínimo actualizado.');
    }

    public function editarCausal(?int $id = null): void
    {
        $causal = $id ? Causal::query()->findOrFail($id) : null;

        $this->resetValidation();
        $this->causalId = $causal?->id;
        $this->causalTipo = $causal?->tipo->value ?? 'no_entregado';
        $this->causalNombre = $causal?->nombre ?? '';
        $this->causalCalidad = $causal?->afecta_calidad_datos ?? false;
        $this->mostrarCausal = true;
    }

    public function guardarCausal(): void
    {
        Gate::authorize('configuracion.gestionar');

        $datos = $this->validate([
            'causalTipo' => ['required', Rule::enum(TipoCausal::class)],
            'causalNombre' => ['required', 'string', 'min:3', 'max:120'],
            'causalCalidad' => ['boolean'],
        ], attributes: ['causalTipo' => 'tipo', 'causalNombre' => 'nombre']);

        $causal = $this->causalId ? Causal::query()->findOrFail($this->causalId) : new Causal(['codigo' => Str::slug($datos['causalNombre'], '_').'_'.Str::lower(Str::random(4)), 'orden' => Causal::query()->max('orden') + 1]);
        $causal->fill(['tipo' => $datos['causalTipo'], 'nombre' => trim($datos['causalNombre']), 'afecta_calidad_datos' => $datos['causalCalidad']])->save();

        $this->mostrarCausal = false;
        $this->avisar('Causal guardada. La app la recibe en su próxima sincronización.');
    }

    public function alternarCausal(int $id): void
    {
        Gate::authorize('configuracion.gestionar');

        $causal = Causal::query()->findOrFail($id);
        $causal->update(['activo' => ! $causal->activo]);
        $this->avisar($causal->activo ? 'Causal activada.' : 'Causal desactivada: ya no aparece en la app.');
    }

    public function editarUsuario(?int $id = null): void
    {
        Gate::authorize('usuarios.gestionar');

        $usuario = $id ? $this->usuarios()->findOrFail($id) : null;

        $this->resetValidation();
        $this->usuarioId = $usuario?->id;
        $this->usuarioNombre = $usuario?->name ?? '';
        $this->usuarioEmail = $usuario?->email ?? '';
        $this->usuarioRol = $usuario?->rolPrincipal()?->value ?? Rol::Administradora->value;
        $this->usuarioSeccional = (string) ($usuario?->seccional_id ?? '');
        $this->usuarioCargo = $usuario?->cargo ?? '';
        $this->usuarioPassword = '';
        $this->mostrarUsuario = true;
    }

    public function guardarUsuario(): void
    {
        Gate::authorize('usuarios.gestionar');

        $datos = $this->validate([
            'usuarioNombre' => ['required', 'string', 'min:3', 'max:120'],
            'usuarioEmail' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->usuarioId)],
            'usuarioRol' => ['required', Rule::in(array_keys($this->rolesAsignables()))],
            'usuarioSeccional' => [Rule::requiredIf($this->usuarioRol === Rol::Coordinador->value), 'nullable', 'exists:seccionales,id'],
            'usuarioCargo' => ['nullable', 'string', 'max:80'],
            'usuarioPassword' => [$this->usuarioId ? 'nullable' : 'required', Password::defaults()],
        ], attributes: ['usuarioNombre' => 'nombre', 'usuarioEmail' => 'correo', 'usuarioRol' => 'rol', 'usuarioSeccional' => 'seccional', 'usuarioCargo' => 'cargo', 'usuarioPassword' => 'contraseña']);

        $usuario = $this->usuarioId ? $this->usuarios()->findOrFail($this->usuarioId) : new User;

        $usuario->fill([
            'name' => trim($datos['usuarioNombre']),
            'email' => Str::lower($datos['usuarioEmail']),
            'seccional_id' => $datos['usuarioSeccional'] ?: null,
            'cargo' => $datos['usuarioCargo'] ?: null,
        ]);

        if ($datos['usuarioPassword']) {
            $usuario->password = $datos['usuarioPassword'];
            $usuario->password_cambiada_at = now();
        }

        $usuario->save();
        $usuario->syncRoles([$datos['usuarioRol']]);

        $this->mostrarUsuario = false;
        $this->avisar($this->usuarioId ? 'Usuario actualizado.' : 'Usuario creado. Ya puede ingresar con su correo y la contraseña.');
    }

    public function alternarUsuario(int $id): void
    {
        Gate::authorize('usuarios.gestionar');

        $usuario = $this->usuarios()->findOrFail($id);

        if ($usuario->is(auth()->user())) {
            $this->avisar('No puedes desactivar tu propio usuario.', 'error');

            return;
        }

        $usuario->update(['activo' => ! $usuario->activo]);
        $this->avisar($usuario->activo ? 'Usuario activado.' : 'Usuario desactivado: no podrá ingresar.');
    }

    public function desbloquear(int $id): void
    {
        Gate::authorize('usuarios.gestionar');

        $this->usuarios()->findOrFail($id)->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $this->avisar('Usuario desbloqueado.');
    }

    /**
     * Solo soporte técnico puede crear otros superadministradores.
     *
     * @return array<string, string>
     */
    public function rolesAsignables(): array
    {
        $roles = [Rol::Administradora, Rol::Coordinador, Rol::Credibanco];

        if (auth()->user()->hasRole(Rol::Superadmin->value)) {
            array_unshift($roles, Rol::Superadmin);
        }

        return collect($roles)->mapWithKeys(fn (Rol $rol): array => [$rol->value => $rol->etiqueta()])->all();
    }

    public function render()
    {
        return view('livewire.admin.configuracion.index', [
            'definicion' => collect(Parametros::DEFINICION)->groupBy('grupo', preserveKeys: true),
            'causales' => $this->pestana === 'causales' ? Causal::query()->orderBy('tipo')->orderBy('orden')->get()->groupBy(fn (Causal $c): string => $c->tipo->value) : collect(),
            'bodegas' => $this->pestana === 'bodegas' ? Bodega::query()->with('seccional')->orderBy('seccional_id')->get() : collect(),
            'usuarios' => $this->pestana === 'usuarios' ? $this->usuarios()->with(['roles', 'seccional'])->orderBy('name')->get() : collect(),
            'seccionales' => Seccional::query()->activas()->orderBy('id')->get(),
            'bitacora' => $this->pestana === 'bitacora' ? Activity::query()->with('causer')->latest()->limit(40)->get() : collect(),
            'roles' => $this->rolesAsignables(),
        ]);
    }

    /**
     * Usuarios de la plataforma web (los repartidores se administran en su propia pantalla).
     */
    private function usuarios()
    {
        return User::query()
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', Rol::Repartidor->value))
            ->when(! auth()->user()->hasRole(Rol::Superadmin->value), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', Rol::Superadmin->value)));
    }
}
