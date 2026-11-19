<div>
    <x-ui.page-header titulo="Repartidores" subtitulo="Cada repartidor entra a la app con su cédula y su contraseña.">
        <x-slot:acciones>
            <x-ui.button variant="outline" icon="smartphone" :href="route('app.descargar')">Descargar la app (APK)</x-ui.button>
            <x-ui.button icon="user-plus" wire:click="nuevo">Nuevo repartidor</x-ui.button>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-5 p-5 sm:p-8 lg:p-10">
        <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-64 flex-1 sm:max-w-md">
                <x-ui.input type="search" wire:model.live.debounce.300ms="buscar" icono="search" placeholder="Buscar por nombre o cédula" aria-label="Buscar repartidor" />
            </div>
            @if ($seccionales->count() > 1)
                <select wire:model.live="seccional" class="campo h-11 w-auto min-w-52 py-0 font-semibold" aria-label="Seccional">
                    <option value="">Todas las seccionales</option>
                    @foreach ($seccionales as $opcion)
                        <option value="{{ $opcion->codigo }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        @if ($repartidores->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty icono="truck" titulo="Aún no hay repartidores" texto="Crea a cada repartidor con su cédula y una contraseña. Con esos datos entra a la app en su celular.">
                    <x-ui.button icon="user-plus" wire:click="nuevo">Nuevo repartidor</x-ui.button>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3" x-data x-animar>
                @foreach ($repartidores as $repartidor)
                    @php
                        $dispositivo = $repartidor->user->dispositivos->sortByDesc('ultimo_uso_at')->first();
                        $efectividad = $repartidor->visitas_mes > 0 ? round($repartidor->entregas_mes * 100 / $repartidor->visitas_mes) : null;
                    @endphp
                    <article wire:key="repartidor-{{ $repartidor->id }}" @class(['rounded-2xl border border-ink-200 bg-white p-5 shadow-card', 'opacity-60' => ! $repartidor->activo])>
                        <div class="flex items-start gap-4">
                            <x-ui.rollo :segmentos="[[$efectividad ?? 0, 'ok']]" :total="100" grosor="8" class="size-16">
                                <span class="text-sm font-extrabold text-ink-900">{{ $repartidor->iniciales }}</span>
                            </x-ui.rollo>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-lg font-bold tracking-tight text-ink-900">{{ $repartidor->nombre }}</h2>
                                <p class="text-sm text-ink-500">C.C. {{ number_format((int) $repartidor->cedula, 0, ',', '.') }} · {{ $repartidor->seccional->nombre }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @if (! $repartidor->activo)
                                        <x-ui.badge color="gray">Inactivo</x-ui.badge>
                                    @elseif ($repartidor->disponible)
                                        <x-ui.badge color="green">Disponible</x-ui.badge>
                                    @else
                                        <x-ui.badge color="amber">No disponible hoy</x-ui.badge>
                                    @endif
                                    @if ($repartidor->zonaHabitual)
                                        <x-ui.badge color="gray">{{ $repartidor->zonaHabitual->nombre_completo }}</x-ui.badge>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-3 divide-x divide-ink-200 rounded-xl bg-ink-50 py-3 text-center">
                            <div><dd class="cifra cifra-grande text-xl font-extrabold">{{ $repartidor->entregas_mes }}</dd><dt class="text-xs text-ink-500">entregas del mes</dt></div>
                            <div><dd class="cifra cifra-grande text-xl font-extrabold {{ $efectividad !== null && $efectividad >= 90 ? 'text-ok-600' : '' }}">{{ $efectividad !== null ? $efectividad.' %' : '—' }}</dd><dt class="text-xs text-ink-500">efectividad</dt></div>
                            <div><dd class="cifra cifra-grande text-xl font-extrabold">{{ $repartidor->visitas_mes }}</dd><dt class="text-xs text-ink-500">visitas</dt></div>
                        </dl>

                        <dl class="mt-4 space-y-1.5 text-[15px]">
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Celular</dt><dd class="font-semibold">{{ \App\Support\Telefono::formatear($repartidor->celular) ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Vehículo</dt><dd class="font-semibold">{{ $repartidor->vehiculo ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">App</dt><dd class="truncate font-semibold">{{ $dispositivo ? trim(($dispositivo->modelo ?? 'Celular').' · v'.($dispositivo->app_version ?? '?')) : 'Aún no ha ingresado' }}</dd></div>
                        </dl>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-ink-200 pt-4">
                            <x-ui.button size="sm" variant="outline" icon="pencil" wire:click="editar({{ $repartidor->id }})">Editar</x-ui.button>
                            @if ($repartidor->activo)
                                <x-ui.button size="sm" variant="ghost" wire:click="alternarDisponible({{ $repartidor->id }})">{{ $repartidor->disponible ? 'No disponible hoy' : 'Marcar disponible' }}</x-ui.button>
                            @endif
                            <div class="relative ml-auto" x-data="{ abierto: false }">
                                <button type="button" class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 hover:text-ink-900" x-on:click="abierto = ! abierto" aria-label="Más acciones">
                                    <x-lucide-sliders-horizontal class="size-[18px]" />
                                </button>
                                <div x-show="abierto" x-cloak x-transition.origin.bottom.right x-on:click.outside="abierto = false" class="absolute right-0 bottom-full z-10 mb-1 w-64 rounded-xl border border-ink-200 bg-white p-1.5 shadow-pop">
                                    <button type="button" wire:click="cerrarSesiones({{ $repartidor->id }})" x-on:click="abierto = false" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-[15px] font-medium text-ink-800 hover:bg-ink-100"><x-lucide-log-out class="size-[18px]" /> Cerrar su sesión en la app</button>
                                    <button type="button" wire:click="alternarActivo({{ $repartidor->id }})" wire:confirm="{{ $repartidor->activo ? 'El repartidor no podrá entrar a la app. ¿Desactivar?' : '¿Volver a activar al repartidor?' }}" x-on:click="abierto = false" @class(['flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-[15px] font-medium', $repartidor->activo ? 'text-bad-700 hover:bg-bad-50' : 'text-ok-700 hover:bg-ok-50'])>
                                        <x-dynamic-component :component="'lucide-'.($repartidor->activo ? 'ban' : 'circle-check')" class="size-[18px]" /> {{ $repartidor->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <x-ui.modal wire:model="mostrarFormulario" :titulo="$repartidorId ? 'Editar repartidor' : 'Nuevo repartidor'" subtitulo="Entra a la app con su cédula y la contraseña que definas aquí." ancho="max-w-2xl">
        <form wire:submit="guardar" id="form-repartidor" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><x-ui.input label="Nombre completo" wire:model="nombre" maxlength="120" autocomplete="off" /></div>
            <x-ui.input label="Cédula" wire:model="cedula" inputmode="numeric" maxlength="15" ayuda="Es su usuario en la app." />
            <x-ui.input label="Celular" wire:model="celular" inputmode="numeric" maxlength="14" opcional />
            <x-ui.select label="Seccional" wire:model.live="seccionalId">
                @foreach ($seccionales as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select label="Zona habitual" wire:model="zonaHabitualId" ayuda="Solo de referencia: la zona del día se elige en la planeación.">
                <option value="">Sin zona habitual</option>
                @foreach ($zonasFormulario as $zona)
                    <option value="{{ $zona->id }}">{{ $zona->nombre_completo }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select label="Vehículo" wire:model="vehiculoTipo">
                @foreach (\App\Livewire\Admin\Repartidores\Index::VEHICULOS as $vehiculo)
                    <option value="{{ $vehiculo }}">{{ $vehiculo }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input label="Placa" wire:model="placa" maxlength="10" opcional />
            <x-ui.input label="Correo" type="email" wire:model="email" opcional ayuda="Sirve para recuperar la contraseña." />
            <x-ui.input :label="$repartidorId ? 'Contraseña nueva' : 'Contraseña'" type="text" wire:model="password" autocomplete="off" :opcional="(bool) $repartidorId" :ayuda="$repartidorId ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres, con letras y números.'" />
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-repartidor" icon="check" wire:loading.attr="disabled">{{ $repartidorId ? 'Guardar cambios' : 'Crear repartidor' }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
