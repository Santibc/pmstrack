<div>
    <x-ui.page-header
        :titulo="$portal ? 'Servicios e histórico' : 'Guías e histórico'"
        :subtitulo="$portal ? 'Todos los servicios enviados a PMS, con su estado, tiempos y evidencia' : 'Busca cualquier guía por servicio, código único, comercio o dirección'"
    >
        <x-slot:acciones>
            <x-ui.button variant="outline" icon="printer" :href="route($area.'.guias.exportar', ['formato' => 'pdf'] + $consulta)" target="_blank">Exportar PDF</x-ui.button>
            <x-ui.button variant="dark" icon="file-spreadsheet" :href="route($area.'.guias.exportar', ['formato' => 'xlsx'] + $consulta)">Exportar Excel (formato Credibanco)</x-ui.button>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-5 p-5 sm:p-8 lg:p-10">
        {{-- Filtros --}}
        <section class="rounded-2xl border border-ink-200 bg-white p-4 shadow-card">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-64 flex-[2]">
                    <x-ui.input type="search" wire:model.live.debounce.350ms="buscar" icono="search" placeholder="Buscar por servicio, código único o comercio" aria-label="Buscar" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="date" wire:model.live="desde" class="campo h-11 w-auto py-0" aria-label="Desde" />
                    <span class="text-ink-400">–</span>
                    <input type="date" wire:model.live="hasta" class="campo h-11 w-auto py-0" aria-label="Hasta" />
                </div>
                @if ($seccionales->count() > 1)
                    <select wire:model.live="seccional" class="campo h-11 w-auto min-w-44 py-0" aria-label="Seccional">
                        <option value="">Seccional: todas</option>
                        @foreach ($seccionales as $opcion)
                            <option value="{{ $opcion->codigo }}">{{ $opcion->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <select wire:model.live="ciudad" class="campo h-11 w-auto min-w-40 py-0" aria-label="Ciudad">
                    <option value="">Ciudad: todas</option>
                    @foreach ($ciudades as $opcion)
                        <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
                <select wire:model.live="ans" class="campo h-11 w-auto min-w-40 py-0" aria-label="ANS">
                    <option value="">ANS: todos</option>
                    <option value="cumplido">Cumplido</option>
                    <option value="riesgo">En riesgo</option>
                    <option value="vencido">Vencido</option>
                </select>
                @if (! $portal)
                    <select wire:model.live="repartidor" class="campo h-11 w-auto min-w-48 py-0" aria-label="Repartidor">
                        <option value="">Repartidor: todos</option>
                        @foreach ($repartidores as $opcion)
                            <option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="button" wire:click="limpiar" class="enlace px-2 text-[15px]">Limpiar</button>
            </div>
        </section>

        <div class="flex flex-wrap gap-2.5">
            @foreach ([
                ['', 'Todos', 'gray', null],
                ['entregados', 'Entregados', 'green', null],
                ['en_reparto', 'En reparto', 'blue', null],
                ['no_entregados', 'No entregados', 'amber', null],
                ['anulados', 'Anulados', 'red', null],
                ['pendientes', 'Por salir', 'gray', null],
                ['riesgo', 'ANS en riesgo', 'warn', 'clock'],
            ] as [$clave, $nombre, $tono, $icono])
                @if ($clave === '' || $conteos[$clave] > 0 || $estado === $clave)
                    <x-ui.pill :activo="$estado === $clave" :tono="$tono" :icono="$icono" wire:click="$set('estado', '{{ $clave }}')">{{ $nombre }} · {{ number_format($conteos[$clave], 0, ',', '.') }}</x-ui.pill>
                @endif
            @endforeach
        </div>

        <x-ui.card :padding="false" wire:loading.class="cargando" wire:target="buscar,desde,hasta,seccional,ciudad,estado,ans,repartidor,limpiar">
            @if ($guias->isEmpty())
                <x-ui.empty icono="search" titulo="No hay servicios con esos filtros" texto="Prueba con otro rango de fechas o quita algún filtro. La búsqueda acepta el número de servicio, la guía PMS, el código único o el nombre del comercio.">
                    <x-ui.button variant="outline" wire:click="limpiar">Quitar filtros</x-ui.button>
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>Servicio</th><th>Comercio</th><th>Dirección</th><th class="text-right">Rollos</th><th>Estado</th><th>Último movimiento</th><th>ANS</th><th class="w-0"></th></tr></thead>
                        <tbody>
                            @foreach ($guias as $guia)
                                @php
                                    $semaforo = $guia->semaforo();
                                    $destacar = in_array($semaforo, [\App\Enums\SemaforoAns::EnRiesgo, \App\Enums\SemaforoAns::Vencido], true);
                                    $detalle = route($area.'.guias.show', $guia);
                                @endphp
                                <tr wire:key="guia-{{ $guia->id }}" @class(['group cursor-pointer transition-colors hover:bg-ink-50', 'bg-warn-50/60' => $destacar]) x-data x-on:click="Livewire.navigate('{{ $detalle }}')">
                                    <td class="whitespace-nowrap">
                                        <p class="text-[16px] font-bold text-ink-900">{{ $guia->servicio }}</p>
                                        <p class="text-sm text-ink-500">{{ $guia->fecha_registro->isoFormat('D MMM · h:mm a') }}</p>
                                    </td>
                                    <td class="max-w-60">
                                        <p class="font-bold text-ink-900">{{ $guia->nombre_comercio }}</p>
                                        <p class="text-sm text-ink-500">CU {{ $guia->comercio->codigo_unico }}</p>
                                    </td>
                                    <td class="max-w-56">
                                        <p>{{ $guia->direccionCompleta() }}</p>
                                        <p class="text-sm text-ink-500">{{ $guia->ciudad->nombre }}</p>
                                    </td>
                                    <td class="text-right">{{ $guia->rollos_solicitados }}</td>
                                    <td>
                                        <x-guia.estado :guia="$guia" :modo="$portal ? 'servicio' : 'guia'" />
                                        @if ($guia->es_urgente && ! $guia->estado->esFinal())
                                            <x-ui.badge color="red" solid size="sm" class="mt-1.5">Urgente · ANS {{ $guia->ans_horas }} h</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="max-w-64 text-ink-700">
                                        @if ($guia->estado === \App\Enums\EstadoGuia::Entregada)
                                            {{ $guia->entregada_at->isoFormat('D MMM · h:mm a') }}@if ($guia->intentos > 1) · {{ $guia->intentos }}.º intento @endif
                                        @elseif ($guia->estado === \App\Enums\EstadoGuia::NoEntregada)
                                            Intento {{ $guia->intentos }} · {{ str($guia->ultimaVisita?->causal?->nombre)->lower() }}
                                        @elseif (in_array($guia->estado, [\App\Enums\EstadoGuia::Asignada, \App\Enums\EstadoGuia::EnReparto], true) && $guia->repartidor)
                                            {{ $guia->estado === \App\Enums\EstadoGuia::EnReparto ? 'En ruta con' : 'Asignado a' }} {{ $portal ? $guia->repartidor->nombre_corto : $guia->repartidor->nombre }}
                                        @else
                                            {{ $guia->ultimo_movimiento ?? '—' }}
                                        @endif
                                    </td>
                                    <td><x-ui.badge :color="$semaforo->color()">{{ $guia->semaforoTexto() }}</x-ui.badge></td>
                                    <td>
                                        <a href="{{ $detalle }}" wire:navigate x-on:click.stop class="enlace inline-flex items-center gap-1 whitespace-nowrap">Ver <x-lucide-chevron-right class="size-4 transition-transform group-hover:translate-x-0.5" /></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-ink-200 px-6 py-4">{{ $guias->links('components.ui.paginacion') }}</div>
            @endif
        </x-ui.card>
    </div>
</div>
