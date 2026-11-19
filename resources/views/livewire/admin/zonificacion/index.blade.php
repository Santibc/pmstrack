<div>
    <x-ui.page-header titulo="Zonificación de direcciones" subtitulo="Elige la zona de cada dirección nueva una sola vez. Desde la siguiente carga, el sistema la asigna solo.">
        <x-slot:acciones>
            @if ($automatico !== null)
                <span class="inline-flex items-center gap-2 rounded-full bg-ok-50 px-4 py-2.5 text-[15px] font-bold text-ok-700">
                    <x-lucide-trending-up class="size-[18px]" />
                    {{ $automatico }} % de las direcciones de {{ now()->isoFormat('MMMM') }} se asignaron solas
                </span>
            @endif
            <x-ui.seccional :seccionales="$this->seccionales" />
        </x-slot:acciones>

        <nav class="-mb-px flex gap-7 overflow-x-auto text-[16px]" aria-label="Secciones de zonificación">
            @foreach ([
                'pendientes' => 'Por zonificar ('.$totalPendientes.')',
                'directorio' => 'Directorio de direcciones ('.number_format($totalDirectorio, 0, ',', '.').')',
                'zonas' => 'Zonas ('.$this->zonas->count().')',
            ] as $clave => $nombre)
                <button type="button" wire:click="$set('pestana', '{{ $clave }}')" @class([
                    'border-b-[3px] pb-3.5 whitespace-nowrap transition',
                    'border-brand-500 font-bold text-ink-900' => $pestana === $clave,
                    'border-transparent font-medium text-ink-600 hover:text-ink-900' => $pestana !== $clave,
                ])>{{ $nombre }}</button>
            @endforeach
        </nav>
    </x-ui.page-header>

    <div class="grid gap-6 p-5 sm:p-8 lg:p-10 2xl:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
        <div class="min-w-0 space-y-6">
            {{-- ───────── Por zonificar ───────── --}}
            @if ($pestana === 'pendientes')
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        ['1', 'Llega una dirección nueva', 'en el Excel de Credibanco.', false],
                        ['2', 'La administradora elige la zona', 'una sola vez.', false],
                        ['', 'La próxima vez se asigna sola', 'por código del comercio o dirección.', true],
                    ] as [$numero, $fuerte, $resto, $hecho])
                        <div class="flex items-start gap-3.5 rounded-2xl border border-ink-200 bg-white p-5 shadow-card">
                            <span @class(['cifra inline-flex size-9 shrink-0 items-center justify-center rounded-full text-[15px] font-bold', $hecho ? 'bg-ok-50 text-ok-600' : 'bg-brand-100 text-brand-700'])>
                                @if ($hecho)
                                    <x-lucide-check class="size-5" stroke-width="2.6" />
                                @else
                                    {{ $numero }}
                                @endif
                            </span>
                            <p class="text-[15px] leading-6 text-ink-600"><strong class="font-bold text-ink-900">{{ $fuerte }}</strong> {{ $resto }}</p>
                        </div>
                    @endforeach
                </div>

                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <p class="text-[15px] font-semibold text-ink-700">
                            @if (count($seleccionadas) > 0)
                                {{ count($seleccionadas) }} {{ count($seleccionadas) === 1 ? 'seleccionada' : 'seleccionadas' }}
                            @else
                                Marca varias direcciones para asignarles la misma zona
                            @endif
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <select wire:model="zonaMasiva" class="campo h-11 w-auto min-w-52 py-0" aria-label="Zona para las seleccionadas">
                                <option value="">Elegir zona</option>
                                @foreach ($this->zonas as $zona)
                                    <option value="{{ $zona->id }}">{{ $zona->nombre_completo }}</option>
                                @endforeach
                            </select>
                            <x-ui.button variant="dark" wire:click="asignarSeleccionadas" :disabled="count($seleccionadas) === 0">Asignar a seleccionadas</x-ui.button>
                        </div>
                    </div>

                    @if ($pendientes->isEmpty())
                        <x-ui.empty icono="circle-check" titulo="No hay direcciones por zonificar" texto="Todas las direcciones de esta seccional ya tienen zona. Las nuevas aparecerán aquí después de cada carga." class="border-t border-ink-200" />
                    @else
                        <div class="overflow-x-auto">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th class="w-12"><span class="sr-only">Seleccionar</span></th>
                                        <th>Comercio</th>
                                        <th>Dirección (como llegó)</th>
                                        <th>Zona</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendientes as $direccion)
                                        @php
                                            $guias = $direccion->guias;
                                            $primera = $guias->first();
                                            $sugerida = $sugeridas->get($direccion->id);
                                            $marcada = in_array($direccion->id, $seleccionadas, true);
                                        @endphp
                                        <tr wire:key="pendiente-{{ $direccion->id }}" @class(['bg-brand-50/60' => $marcada])>
                                            <td>
                                                <input type="checkbox" wire:model.live="seleccionadas" value="{{ $direccion->id }}" class="size-5 rounded border-ink-300 accent-brand-500" aria-label="Seleccionar {{ $primera?->nombre_comercio }}" />
                                            </td>
                                            <td class="max-w-60">
                                                <p class="font-bold text-ink-900">{{ $primera?->nombre_comercio ?? '—' }}</p>
                                                <p class="mt-0.5 text-sm text-ink-500">
                                                    CU {{ $primera?->comercio->codigo_unico }} · {{ $guias->sum('rollos_solicitados') }} rollos
                                                    @if ($guias->count() > 1) · {{ $guias->count() }} guías @endif
                                                </p>
                                                @if ($guias->contains('es_urgente', true))
                                                    <x-ui.badge color="red" solid size="sm" class="mt-1.5 tracking-wide uppercase">Urgente · ANS 8 h</x-ui.badge>
                                                @endif
                                            </td>
                                            <td class="max-w-56">
                                                {{ $primera?->direccion_original }}
                                                <span class="mt-0.5 block text-sm text-ink-500">{{ $direccion->ciudad->nombre }}@if ($direccion->es_rural) · rural @endif</span>
                                            </td>
                                            <td class="min-w-56">
                                                <select wire:change="asignar({{ $direccion->id }}, $event.target.value)" class="campo h-11 py-0" aria-label="Zona de {{ $direccion->direccion }}">
                                                    <option value="">Elegir zona</option>
                                                    @foreach ($this->zonas as $zona)
                                                        <option value="{{ $zona->id }}">{{ $zona->nombre_completo }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($sugerida)
                                                    <button type="button" wire:click="asignar({{ $direccion->id }}, {{ $sugerida['zona']->id }})" class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-ok-50 px-2.5 py-1 text-xs font-bold text-ok-700 hover:bg-ok-100" title="Cerca de {{ $sugerida['referencia'] }}, que ya está en esta zona">
                                                        <x-lucide-zap class="size-3.5" /> Sugerida: {{ $sugerida['zona']->nombre_completo }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-200 px-6 py-4">
                            <p class="text-[15px] text-ink-500">Se guarda automáticamente al elegir la zona.</p>
                            {{ $pendientes->links('components.ui.paginacion') }}
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- ───────── Directorio ───────── --}}
            @if ($pestana === 'directorio')
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center gap-3 px-6 py-4">
                        <div class="min-w-64 flex-1">
                            <x-ui.input type="search" wire:model.live.debounce.350ms="buscar" icono="search" placeholder="Buscar una dirección" aria-label="Buscar dirección" />
                        </div>
                        <select wire:model.live="filtroZona" class="campo h-11 w-auto min-w-52 py-0" aria-label="Filtrar por zona">
                            <option value="">Todas las zonas</option>
                            <option value="sin">Sin zona</option>
                            @foreach ($this->zonas as $zona)
                                <option value="{{ $zona->id }}">{{ $zona->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead><tr><th>Dirección</th><th>Ciudad</th><th class="text-right">Guías</th><th>Zona</th></tr></thead>
                            <tbody>
                                @forelse ($directorio as $direccion)
                                    <tr wire:key="directorio-{{ $direccion->id }}">
                                        <td class="font-semibold text-ink-900">{{ $direccion->direccion }}</td>
                                        <td>{{ $direccion->ciudad->nombre }}@if ($direccion->es_rural) <x-ui.badge color="amber" size="sm">Rural</x-ui.badge> @endif</td>
                                        <td class="text-right">{{ $direccion->guias_count }}</td>
                                        <td class="min-w-56">
                                            <select wire:change="asignar({{ $direccion->id }}, $event.target.value)" class="campo h-10 py-0 text-sm" aria-label="Zona de {{ $direccion->direccion }}">
                                                <option value="" @selected($direccion->zona_id === null)>Sin zona</option>
                                                @foreach ($this->zonas as $zona)
                                                    <option value="{{ $zona->id }}" @selected($direccion->zona_id === $zona->id)>{{ $zona->nombre_completo }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-10 text-center text-ink-500">No hay direcciones con ese criterio.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($directorio->hasPages())
                        <div class="border-t border-ink-200 px-6 py-4">{{ $directorio->links('components.ui.paginacion') }}</div>
                    @endif
                </x-ui.card>
            @endif

            {{-- ───────── Zonas ───────── --}}
            @if ($pestana === 'zonas')
                <x-ui.card titulo="Zonas de {{ $this->seccional->nombre }}" subtitulo="Cada zona se asigna a un repartidor en la planeación del día." :padding="false">
                    <x-slot:acciones>
                        @can('configuracion.gestionar')
                            <x-ui.button icon="plus" wire:click="nuevaZona">Nueva zona</x-ui.button>
                        @endcan
                    </x-slot:acciones>

                    <table class="tabla mt-2">
                        <thead><tr><th>Zona</th><th class="text-right">Direcciones conocidas</th><th>Estado</th><th class="w-0"></th></tr></thead>
                        <tbody>
                            @foreach ($todasLasZonas as $zona)
                                <tr wire:key="zona-{{ $zona->id }}" @class(['text-ink-500' => ! $zona->activo])>
                                    <td>
                                        <span class="flex items-center gap-3">
                                            <span class="size-3.5 shrink-0 rounded-full" style="background: {{ $zona->color }}"></span>
                                            <span class="font-bold text-ink-900">{{ $zona->codigo }}</span> {{ $zona->nombre }}
                                        </span>
                                    </td>
                                    <td class="text-right">{{ number_format($zona->direcciones_count, 0, ',', '.') }}</td>
                                    <td><x-ui.badge :color="$zona->activo ? 'green' : 'gray'">{{ $zona->activo ? 'Activa' : 'Inactiva' }}</x-ui.badge></td>
                                    <td>
                                        @can('configuracion.gestionar')
                                            <div class="flex justify-end gap-2">
                                                <x-ui.button size="sm" variant="outline" icon="pencil" wire:click="editarZona({{ $zona->id }})">Editar</x-ui.button>
                                                <x-ui.button size="sm" variant="ghost" wire:click="alternarZona({{ $zona->id }})">{{ $zona->activo ? 'Desactivar' : 'Activar' }}</x-ui.button>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>
            @endif
        </div>

        {{-- Panel de zonas --}}
        <aside>
            <x-ui.card titulo="Zonas de {{ $this->seccional->nombre }}">
                @php $totalConocidas = max(1, $this->zonas->sum('direcciones_count')); @endphp

                <div class="flex h-3 overflow-hidden rounded-full bg-ink-200">
                    @foreach ($this->zonas as $zona)
                        @if ($zona->direcciones_count > 0)
                            <span style="width: {{ round($zona->direcciones_count * 100 / $totalConocidas, 2) }}%; background: {{ $zona->color }}" title="{{ $zona->nombre_completo }}"></span>
                        @endif
                    @endforeach
                </div>

                <ul class="mt-5 space-y-3 text-[16px]">
                    @foreach ($this->zonas as $zona)
                        <li class="flex items-center justify-between gap-3" wire:key="lado-zona-{{ $zona->id }}">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="size-3 shrink-0 rounded-full" style="background: {{ $zona->color }}"></span>
                                <span class="truncate"><span class="font-semibold">{{ $zona->codigo }}</span> {{ $zona->nombre }}</span>
                            </span>
                            <span class="cifra text-ink-600">{{ number_format($zona->direcciones_count, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-5 text-sm leading-6 text-ink-500">
                    Número de direcciones conocidas por zona.
                    <button type="button" class="enlace" wire:click="$set('pestana', 'zonas')">Crear o editar zonas</button>
                </p>
            </x-ui.card>
        </aside>
    </div>

    <x-ui.modal wire:model="mostrarZona" :titulo="$zonaId ? 'Editar zona' : 'Nueva zona'" subtitulo="Seccional {{ $this->seccional->nombre }}">
        <form wire:submit="guardarZona" class="space-y-4" id="form-zona">
            <div class="grid grid-cols-[minmax(0,7rem)_minmax(0,1fr)] gap-4">
                <x-ui.input label="Código" wire:model="zonaCodigo" maxlength="10" placeholder="Z08" />
                <x-ui.input label="Nombre" wire:model="zonaNombre" maxlength="80" placeholder="Fontibón · Engativá" />
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-ink-800">Color</p>
                <div class="flex flex-wrap gap-2.5">
                    @foreach (\App\Livewire\Admin\Zonificacion\Index::COLORES as $color)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="zonaColor" value="{{ $color }}" class="peer sr-only" />
                            <span class="block size-9 rounded-full ring-2 ring-transparent ring-offset-2 peer-checked:ring-ink-900" style="background: {{ $color }}"></span>
                        </label>
                    @endforeach
                </div>
                @error('zonaColor') <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-zona" icon="check">Guardar zona</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
