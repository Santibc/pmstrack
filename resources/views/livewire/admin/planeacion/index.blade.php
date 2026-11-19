<div>
    <x-ui.page-header
        titulo="Planeación del día"
        :subtitulo="ucfirst($dia->isoFormat('dddd D [de] MMMM [de] YYYY')).' · Seccional '.$this->seccional->nombre.' · asigna cada zona a un repartidor'"
    >
        <x-slot:acciones>
            <div class="flex items-center rounded-xl border border-ink-300 bg-white shadow-card">
                <button type="button" wire:click="irA('anterior')" class="rounded-l-xl p-3 text-ink-600 hover:bg-ink-100" aria-label="Día anterior"><x-lucide-chevron-left class="size-[18px]" /></button>
                <input type="date" wire:model.live="fecha" class="h-11 border-x border-ink-200 bg-transparent px-3 text-[15px] font-semibold text-ink-900 focus:outline-none" aria-label="Fecha de la planeación" />
                <button type="button" wire:click="irA('siguiente')" class="rounded-r-xl p-3 text-ink-600 hover:bg-ink-100" aria-label="Día siguiente"><x-lucide-chevron-right class="size-[18px]" /></button>
            </div>
            <x-ui.seccional :seccionales="$this->seccionales" />
            <x-ui.button variant="outline" icon="rotate-ccw" wire:click="repetirAnterior" wire:loading.attr="disabled">Repetir asignación anterior</x-ui.button>
            <x-ui.button icon="send" wire:click="publicar" wire:loading.attr="disabled" wire:target="publicar"
                wire:confirm="Se crean las rutas, se descuentan los rollos del inventario y se avisa a los repartidores. ¿Publicar?"
                :disabled="$totales['por_publicar'] === 0">
                <span wire:loading.remove wire:target="publicar">Publicar y notificar a repartidores</span>
                <span wire:loading wire:target="publicar">Publicando…</span>
            </x-ui.button>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-6 p-5 sm:p-8 lg:p-10">
        {{-- Avisos del día --}}
        @if (! $esHabil)
            <div class="flex items-center gap-3 rounded-2xl border border-warn-100 bg-warn-50 px-5 py-4 text-[15px] font-medium text-ink-900">
                <x-lucide-calendar-clock class="size-5 shrink-0 text-warn-700" /> Este día no es hábil (fin de semana o festivo). Puedes planearlo igual si hay operación.
            </div>
        @endif

        @if ($planeacion->estaPublicada())
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl border border-ok-100 bg-ok-50 px-5 py-4 text-[15px] text-ink-900">
                <x-lucide-circle-check class="size-5 shrink-0 text-ok-600" />
                <span class="font-bold">Publicada a las {{ $planeacion->publicada_at->isoFormat('h:mm a') }}</span>
                <span class="text-ink-600">por {{ $planeacion->publicadaPor?->name }} · {{ $totales['en_ruta'] }} guías en ruta.</span>
                @if ($totales['guias'] > 0)
                    <span class="font-semibold text-brand-700">Hay {{ $totales['guias'] }} guías nuevas por publicar.</span>
                @endif
                <a href="{{ route('admin.seguimiento', ['seccional' => $this->seccionalCodigo]) }}" wire:navigate class="enlace ml-auto">Ver seguimiento en vivo</a>
            </div>
        @endif

        @if ($faltanRollos)
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-bad-100 bg-bad-50 px-5 py-4 text-[15px] text-ink-900" role="alert">
                <x-lucide-triangle-alert class="size-5 shrink-0 text-bad-600" />
                <span><strong>La bodega no alcanza:</strong> hay {{ \App\Support\Rollos::enCajas($bodega->saldo) }} ({{ \App\Support\Rollos::numero($bodega->saldo) }} rollos) y la planeación necesita {{ \App\Support\Rollos::numero($totales['rollos']) }}.</span>
                <a href="{{ route('admin.inventario', ['seccional' => $this->seccionalCodigo]) }}" wire:navigate class="enlace ml-auto">Registrar una entrada</a>
            </div>
        @endif

        {{-- Totales --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat :valor="$totales['guias']" etiqueta="" :detalle="'guías por publicar en '.$this->seccional->nombre" />
            <x-ui.stat :valor="\App\Support\Rollos::numero($totales['rollos'])" etiqueta="" :detalle="'rollos por despachar · '.\App\Support\Rollos::enCajas($totales['rollos'])" />
            <x-ui.stat :valor="$totales['reprogramadas']" etiqueta="" detalle="reprogramadas de días anteriores (incluidas)" />
            <x-ui.stat :valor="$totales['sin_repartidor']" etiqueta="" :detalle="$totales['sin_repartidor'] === 1 ? 'zona sin repartidor' : 'zonas sin repartidor'" :tono="$totales['sin_repartidor'] > 0 ? 'brand' : 'default'" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,400px)]">
            {{-- Zonas del día --}}
            <div class="min-w-0">
                @if ($zonas->isEmpty())
                    <x-ui.card :padding="false">
                        <x-ui.empty icono="calendar-clock" titulo="No hay guías para planear este día" texto="Las guías aparecen aquí cuando tienen zona. Carga el archivo de Credibanco o termina de zonificar las direcciones nuevas.">
                            <x-ui.button :href="route('admin.cargas')" icon="file-up" wire:navigate>Cargar archivo</x-ui.button>
                            <x-ui.button :href="route('admin.zonificacion')" variant="outline" icon="map" wire:navigate>Zonificar</x-ui.button>
                        </x-ui.empty>
                    </x-ui.card>
                @else
                    <div class="grid gap-5 md:grid-cols-2" x-data x-animar>
                        @foreach ($zonas as $fila)
                            @php
                                $zona = $fila['zona'];
                                $asignado = $repartidores->firstWhere('id', $fila['repartidor_id']);
                                $pendiente = $fila['guias'] > 0 && ! $asignado;
                            @endphp
                            <article wire:key="zona-{{ $zona->id }}" @class([
                                'overflow-hidden rounded-2xl border bg-white shadow-card',
                                'border-dashed border-brand-400 bg-brand-50/50' => $pendiente,
                                'border-ink-200' => ! $pendiente,
                            ])>
                                <div class="h-1.5" style="background: {{ $zona->color }}"></div>
                                <div class="p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h2 class="truncate text-xl font-bold tracking-tight text-ink-900">{{ $zona->nombre_completo }}</h2>
                                            <p class="mt-0.5 text-[15px] text-ink-500">
                                                @if ($fila['guias'] > 0)
                                                    {{ $fila['guias'] }} {{ $fila['guias'] === 1 ? 'guía' : 'guías' }} · {{ \App\Support\Rollos::numero($fila['rollos']) }} rollos
                                                @else
                                                    Todo publicado
                                                @endif
                                                @if ($fila['en_ruta'] > 0)
                                                    <span class="text-ok-700"> · {{ $fila['en_ruta'] }} ya en ruta</span>
                                                @endif
                                            </p>
                                        </div>
                                        @if ($fila['urgentes'] > 0)
                                            <x-ui.badge color="red" solid>{{ $fila['urgentes'] }} {{ $fila['urgentes'] === 1 ? 'urgente' : 'urgentes' }}</x-ui.badge>
                                        @endif
                                    </div>

                                    <div class="relative mt-4">
                                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2">
                                            @if ($asignado)
                                                <x-ui.avatar :iniciales="$asignado->iniciales" size="sm" />
                                            @else
                                                <span class="inline-flex size-8 items-center justify-center rounded-full bg-brand-100 text-brand-700"><x-lucide-plus class="size-4" stroke-width="2.5" /></span>
                                            @endif
                                        </span>
                                        <select wire:change="asignar({{ $zona->id }}, $event.target.value)" @class(['campo h-13 py-0 pl-14 font-semibold', 'border-brand-400 text-brand-700' => $pendiente]) aria-label="Repartidor de {{ $zona->nombre_completo }}">
                                            <option value="">Elegir repartidor</option>
                                            @foreach ($repartidores as $repartidor)
                                                <option value="{{ $repartidor->id }}" @selected($fila['repartidor_id'] === $repartidor->id) @disabled(! $repartidor->disponible)>
                                                    {{ $repartidor->nombre }}{{ $repartidor->disponible ? '' : ' (no disponible hoy)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button type="button" wire:click="verZona({{ $zona->id }})" class="enlace mt-3 inline-flex items-center gap-1.5 text-sm">
                                        <x-lucide-list-ordered class="size-4" /> Ver y mover guías
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Carga por repartidor --}}
            <aside>
                <x-ui.card titulo="Carga por repartidor">
                    <ul class="space-y-5" x-data x-animar>
                        @forelse ($carga as $fila)
                            @php $repartidor = $fila['repartidor']; @endphp
                            <li wire:key="carga-{{ $repartidor->id }}" @class(['opacity-55' => ! $repartidor->disponible])>
                                <div class="flex items-baseline justify-between gap-3 text-[16px]">
                                    <span class="truncate font-bold text-ink-900">{{ $repartidor->nombre }}</span>
                                    @if (! $repartidor->disponible)
                                        <span class="shrink-0 text-ink-500">No disponible hoy</span>
                                    @elseif ($fila['guias'] === 0)
                                        <span class="shrink-0 font-bold text-ok-700">Disponible</span>
                                    @else
                                        <span class="shrink-0 text-ink-600">{{ $fila['guias'] }} {{ $fila['guias'] === 1 ? 'guía' : 'guías' }}@if ($fila['zonas'] > 1) · {{ $fila['zonas'] }} zonas @endif</span>
                                    @endif
                                </div>
                                <x-ui.progress class="mt-2" :segmentos="[[$fila['en_ruta'], 'ok'], [$fila['guias'] - $fila['en_ruta'], 'brand']]" :total="$maximaCarga" />
                            </li>
                        @empty
                            <li class="text-[15px] text-ink-500">
                                Aún no hay repartidores en esta seccional.
                                <a href="{{ route('admin.repartidores') }}" wire:navigate class="enlace">Crear repartidores</a>
                            </li>
                        @endforelse
                    </ul>

                    <p class="mt-6 flex items-center gap-4 text-sm text-ink-500">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-ok-500"></span> Ya en ruta</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand-500"></span> Por publicar</span>
                    </p>

                    <p class="mt-5 rounded-xl bg-ink-50 p-4 text-[15px] leading-6 text-ink-600">
                        Al publicar, cada repartidor recibe una notificación en la app con sus guías del día y los rollos salen del inventario. Si cambias algo después, la app se actualiza sola.
                    </p>
                </x-ui.card>
            </aside>
        </div>
    </div>

    {{-- Detalle de una zona --}}
    <x-ui.modal wire:model="mostrarZona" :titulo="$detalle ? $detalle['zona']->nombre_completo : 'Zona'" subtitulo="Mueve guías sueltas a otra zona o reasigna las que ya salieron a ruta." ancho="max-w-4xl">
        @if ($detalle)
            @if ($detalle['por_publicar']->isNotEmpty())
                <h3 class="mb-2 text-[15px] font-bold text-ink-900">Por publicar ({{ $detalle['por_publicar']->count() }})</h3>
                <div class="overflow-x-auto rounded-xl border border-ink-200">
                    <table class="tabla text-sm">
                        <thead><tr><th>Comercio</th><th>Dirección</th><th class="text-right">Rollos</th><th>ANS</th><th>Mover a</th></tr></thead>
                        <tbody>
                            @foreach ($detalle['por_publicar'] as $guia)
                                <tr wire:key="mover-{{ $guia->id }}">
                                    <td class="font-semibold">{{ $guia->nombre_comercio }} @if ($guia->intentos > 0)<x-ui.badge color="amber" size="sm">Intento {{ $guia->intentos + 1 }}</x-ui.badge>@endif</td>
                                    <td class="text-ink-600">{{ $guia->direccionCompleta() }}</td>
                                    <td class="text-right">{{ $guia->rollos_solicitados }}</td>
                                    <td><x-ui.badge :color="$guia->semaforo()->color()" size="sm">{{ $guia->semaforoTexto() }}</x-ui.badge></td>
                                    <td>
                                        <select wire:change="moverGuia({{ $guia->id }}, $event.target.value)" class="campo h-9 w-44 py-0 text-sm" aria-label="Mover {{ $guia->nombre_comercio }} a otra zona">
                                            <option value="">Otra zona…</option>
                                            @foreach ($zonas->pluck('zona')->merge(\App\Models\Zona::query()->where('seccional_id', $this->seccional->id)->activas()->orderBy('orden')->get())->unique('id')->where('id', '!=', $detalle['zona']->id) as $otra)
                                                <option value="{{ $otra->id }}">{{ $otra->nombre_completo }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($detalle['en_ruta']->isNotEmpty())
                <h3 class="mt-6 mb-2 text-[15px] font-bold text-ink-900">Ya en ruta ({{ $detalle['en_ruta']->count() }})</h3>
                <div class="overflow-x-auto rounded-xl border border-ink-200">
                    <table class="tabla text-sm">
                        <thead><tr><th>Comercio</th><th>Repartidor</th><th>Estado</th><th>Reasignar a</th></tr></thead>
                        <tbody>
                            @foreach ($detalle['en_ruta'] as $parada)
                                <tr wire:key="reasignar-{{ $parada->id }}">
                                    <td class="font-semibold">{{ $parada->guia->nombre_comercio }}</td>
                                    <td>{{ $parada->ruta->repartidor->nombre }}</td>
                                    <td><x-ui.badge :color="$parada->estado->fueVisitada() ? ($parada->estado === \App\Enums\EstadoParada::Entregada ? 'green' : 'amber') : 'gray'" size="sm">{{ $parada->estado->etiqueta() }}</x-ui.badge></td>
                                    <td>
                                        @if ($parada->estaPendiente())
                                            <select wire:change="reasignar({{ $parada->id }}, $event.target.value)" class="campo h-9 w-48 py-0 text-sm" aria-label="Reasignar {{ $parada->guia->nombre_comercio }}">
                                                <option value="">Otro repartidor…</option>
                                                @foreach ($repartidores->where('disponible', true)->where('id', '!=', $parada->ruta->repartidor_id) as $otro)
                                                    <option value="{{ $otro->id }}">{{ $otro->nombre }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="text-ink-500">Ya visitada</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($detalle['por_publicar']->isEmpty() && $detalle['en_ruta']->isEmpty())
                <p class="py-6 text-center text-ink-500">Esta zona no tiene guías para este día.</p>
            @endif
        @endif
    </x-ui.modal>
</div>
