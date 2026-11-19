<div>
    <x-ui.page-header titulo="Configuración" subtitulo="Parámetros de la operación, catálogos, usuarios y bitácora de cambios.">
        <nav class="-mb-px flex gap-7 overflow-x-auto text-[16px]" aria-label="Secciones de configuración">
            @foreach (['parametros' => 'Parámetros', 'causales' => 'Causales', 'bodegas' => 'Bodegas', 'usuarios' => 'Usuarios y roles', 'bitacora' => 'Bitácora'] as $clave => $nombre)
                @if ($clave !== 'usuarios' || auth()->user()->can('usuarios.gestionar'))
                    <button type="button" wire:click="$set('pestana', '{{ $clave }}')" @class([
                        'border-b-[3px] pb-3.5 whitespace-nowrap transition',
                        'border-brand-500 font-bold text-ink-900' => $pestana === $clave,
                        'border-transparent font-medium text-ink-600 hover:text-ink-900' => $pestana !== $clave,
                    ])>{{ $nombre }}</button>
                @endif
            @endforeach
        </nav>
    </x-ui.page-header>

    <div class="p-5 sm:p-8 lg:p-10">
        {{-- ───────── Parámetros ───────── --}}
        @if ($pestana === 'parametros')
            <form wire:submit="guardarParametros" class="max-w-5xl space-y-6">
                @foreach (\App\Livewire\Admin\Configuracion\Index::GRUPOS as $grupo => $titulo)
                    @continue(! isset($definicion[$grupo]))
                    <x-ui.card :titulo="$titulo">
                        <div class="grid gap-x-6 gap-y-5 md:grid-cols-2">
                            @foreach ($definicion[$grupo] as $clave => $parametro)
                                @if ($parametro['tipo'] === 'opcion')
                                    <x-ui.select :label="$parametro['etiqueta']" wire:model="valores.{{ $clave }}" :ayuda="$parametro['descripcion']">
                                        <option value="calendario">Horas calendario (corridas)</option>
                                        <option value="habiles">Horas hábiles (lunes a viernes)</option>
                                    </x-ui.select>
                                @elseif ($parametro['tipo'] === 'booleano')
                                    <x-ui.select :label="$parametro['etiqueta']" wire:model="valores.{{ $clave }}" :ayuda="$parametro['descripcion']">
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </x-ui.select>
                                @else
                                    <x-ui.input
                                        :label="$parametro['etiqueta']"
                                        wire:model="valores.{{ $clave }}"
                                        :type="['entero' => 'number', 'hora' => 'time'][$parametro['tipo']] ?? 'text'"
                                        :ayuda="$parametro['descripcion']"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach

                <div class="sticky bottom-4 flex justify-end rounded-2xl border border-ink-200 bg-white/95 p-4 shadow-pop backdrop-blur">
                    <x-ui.button type="submit" size="lg" icon="save" wire:loading.attr="disabled">Guardar parámetros</x-ui.button>
                </div>
            </form>
        @endif

        {{-- ───────── Causales ───────── --}}
        @if ($pestana === 'causales')
            <div class="max-w-5xl space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="max-w-2xl text-[15px] leading-6 text-ink-600">Motivos que el repartidor elige en la app cuando no entrega o anula. Los marcados como «dato errado» alimentan el reporte de calidad de datos de Credibanco.</p>
                    <x-ui.button icon="plus" wire:click="editarCausal">Nueva causal</x-ui.button>
                </div>

                @foreach (\App\Enums\TipoCausal::cases() as $tipo)
                    <x-ui.card :titulo="$tipo->etiqueta()" :padding="false">
                        <table class="tabla mt-3">
                            <thead><tr><th>Motivo</th><th>Calidad de datos</th><th>Estado</th><th class="w-0"></th></tr></thead>
                            <tbody>
                                @forelse ($causales[$tipo->value] ?? [] as $causal)
                                    <tr wire:key="causal-{{ $causal->id }}" @class(['text-ink-500' => ! $causal->activo])>
                                        <td class="font-semibold text-ink-900">{{ $causal->nombre }}</td>
                                        <td>@if ($causal->afecta_calidad_datos)<x-ui.badge color="amber">Dato errado</x-ui.badge>@else <span class="text-ink-400">—</span> @endif</td>
                                        <td><x-ui.badge :color="$causal->activo ? 'green' : 'gray'">{{ $causal->activo ? 'Activa' : 'Inactiva' }}</x-ui.badge></td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <x-ui.button size="sm" variant="outline" icon="pencil" wire:click="editarCausal({{ $causal->id }})">Editar</x-ui.button>
                                                <x-ui.button size="sm" variant="ghost" wire:click="alternarCausal({{ $causal->id }})">{{ $causal->activo ? 'Desactivar' : 'Activar' }}</x-ui.button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-ink-500">Sin causales de este tipo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </x-ui.card>
                @endforeach
            </div>
        @endif

        {{-- ───────── Bodegas ───────── --}}
        @if ($pestana === 'bodegas')
            <div class="grid max-w-5xl gap-5 md:grid-cols-3">
                @foreach ($bodegas as $bodega)
                    <x-ui.card wire:key="bodega-{{ $bodega->id }}">
                        <div class="flex items-center gap-4">
                            <x-ui.rollo-papel :nivel="$bodega->stock_minimo > 0 ? $bodega->saldo / ($bodega->stock_minimo * 2) : ($bodega->saldo > 0 ? 1 : 0)" :bajo="$bodega->estaBajoMinimo()" class="size-16 shrink-0" />
                            <div>
                                <h2 class="text-lg font-bold tracking-tight">{{ $bodega->nombre }}</h2>
                                <p class="text-sm text-ink-500">{{ \App\Support\Rollos::numero($bodega->saldo) }} rollos · {{ \App\Support\Rollos::enCajas($bodega->saldo) }}</p>
                            </div>
                        </div>
                        <form wire:submit="guardarMinimo({{ $bodega->id }})" class="mt-5 flex items-end gap-3">
                            <x-ui.input label="Stock mínimo (rollos)" type="number" min="0" wire:model="minimos.{{ $bodega->id }}" ayuda="Por debajo de este saldo aparece la alerta." />
                            <x-ui.button type="submit" variant="outline" class="mb-[26px]">Guardar</x-ui.button>
                        </form>
                    </x-ui.card>
                @endforeach
                <p class="text-[15px] leading-6 text-ink-500 md:col-span-3">Las zonas de cada seccional se crean y editan en <a href="{{ route('admin.zonificacion', ['ver' => 'zonas']) }}" wire:navigate class="enlace">Zonificación → Zonas</a>.</p>
            </div>
        @endif

        {{-- ───────── Usuarios ───────── --}}
        @if ($pestana === 'usuarios')
            <x-ui.card titulo="Usuarios de la plataforma" subtitulo="Los repartidores se administran en su propia pantalla." :padding="false">
                <x-slot:acciones><x-ui.button icon="user-plus" wire:click="editarUsuario">Nuevo usuario</x-ui.button></x-slot:acciones>
                <div class="overflow-x-auto">
                    <table class="tabla mt-3">
                        <thead><tr><th>Usuario</th><th>Rol</th><th>Seccional</th><th>Último ingreso</th><th>Estado</th><th class="w-0"></th></tr></thead>
                        <tbody>
                            @foreach ($usuarios as $usuario)
                                <tr wire:key="usuario-{{ $usuario->id }}" @class(['text-ink-500' => ! $usuario->activo])>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar :iniciales="$usuario->iniciales()" tono="brand" />
                                            <div class="min-w-0">
                                                <p class="truncate font-bold text-ink-900">{{ $usuario->name }}</p>
                                                <p class="truncate text-sm text-ink-500">{{ $usuario->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $usuario->rolPrincipal()?->etiqueta() ?? '—' }}</td>
                                    <td>{{ $usuario->seccional?->nombre ?? 'Todas' }}</td>
                                    <td class="whitespace-nowrap text-ink-600">{{ $usuario->ultimo_acceso_at?->isoFormat('D MMM · h:mm a') ?? 'Nunca' }}</td>
                                    <td>
                                        @if ($usuario->estaBloqueado())
                                            <x-ui.badge color="red">Bloqueado</x-ui.badge>
                                        @else
                                            <x-ui.badge :color="$usuario->activo ? 'green' : 'gray'">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if ($usuario->estaBloqueado())
                                                <x-ui.button size="sm" variant="soft" wire:click="desbloquear({{ $usuario->id }})">Desbloquear</x-ui.button>
                                            @endif
                                            <x-ui.button size="sm" variant="outline" icon="pencil" wire:click="editarUsuario({{ $usuario->id }})">Editar</x-ui.button>
                                            <x-ui.button size="sm" variant="ghost" wire:click="alternarUsuario({{ $usuario->id }})">{{ $usuario->activo ? 'Desactivar' : 'Activar' }}</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        {{-- ───────── Bitácora ───────── --}}
        @if ($pestana === 'bitacora')
            <x-ui.card titulo="Bitácora de cambios" subtitulo="Quién hizo qué y cuándo: configuración, usuarios, cargas, planeación e inventario." :padding="false">
                <table class="tabla mt-3">
                    <thead><tr><th>Cuándo</th><th>Quién</th><th>Área</th><th>Qué pasó</th></tr></thead>
                    <tbody>
                        @forelse ($bitacora as $registro)
                            <tr wire:key="bitacora-{{ $registro->id }}">
                                <td class="whitespace-nowrap text-ink-600">{{ $registro->created_at->isoFormat('D MMM · h:mm a') }}</td>
                                <td class="font-semibold">{{ $registro->causer?->name ?? 'Sistema' }}</td>
                                <td><x-ui.badge color="gray">{{ ucfirst((string) $registro->log_name) }}</x-ui.badge></td>
                                <td class="max-w-xl">{{ in_array($registro->description, ['created', 'updated', 'deleted'], true) ? ['created' => 'Creó', 'updated' => 'Modificó', 'deleted' => 'Eliminó'][$registro->description].' '.Str::of(class_basename((string) $registro->subject_type))->snake(' ') : $registro->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-ink-500">Aún no hay cambios registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>
        @endif
    </div>

    <x-ui.modal wire:model="mostrarCausal" :titulo="$causalId ? 'Editar causal' : 'Nueva causal'">
        <form wire:submit="guardarCausal" id="form-causal" class="space-y-4">
            <x-ui.select label="Tipo" wire:model="causalTipo">
                @foreach (\App\Enums\TipoCausal::cases() as $tipo)
                    <option value="{{ $tipo->value }}">{{ $tipo->etiqueta() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input label="Motivo" wire:model="causalNombre" maxlength="120" placeholder="Local cerrado" />
            <label class="flex items-start gap-3 text-[15px] leading-6 text-ink-700">
                <input type="checkbox" wire:model="causalCalidad" class="mt-1 size-[18px] rounded border-ink-300 accent-brand-500" />
                <span>Indica un dato errado en la base de Credibanco (dirección que no existe, comercio trasladado o cerrado).</span>
            </label>
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-causal" icon="check">Guardar causal</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    <x-ui.modal wire:model="mostrarUsuario" :titulo="$usuarioId ? 'Editar usuario' : 'Nuevo usuario'" ancho="max-w-2xl">
        <form wire:submit="guardarUsuario" id="form-usuario" class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Nombre" wire:model="usuarioNombre" maxlength="120" />
            <x-ui.input label="Correo" type="email" wire:model="usuarioEmail" maxlength="150" autocomplete="off" />
            <x-ui.select label="Rol" wire:model.live="usuarioRol" :ayuda="\App\Enums\Rol::tryFrom($usuarioRol)?->descripcion()">
                @foreach ($roles as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select label="Seccional" wire:model="usuarioSeccional" ayuda="Obligatoria para el coordinador de seccional.">
                <option value="">Todas</option>
                @foreach ($seccionales as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input label="Cargo" wire:model="usuarioCargo" maxlength="80" opcional />
            <x-ui.input :label="$usuarioId ? 'Contraseña nueva' : 'Contraseña'" type="text" wire:model="usuarioPassword" autocomplete="off" :opcional="(bool) $usuarioId" :ayuda="$usuarioId ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres, con letras y números.'" />
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-usuario" icon="check">{{ $usuarioId ? 'Guardar cambios' : 'Crear usuario' }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
