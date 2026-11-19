<div wire:poll.30s>
    <x-ui.page-header titulo="Seguimiento en vivo" :subtitulo="'Hoy, '.now()->isoFormat('dddd D [de] MMMM').' · '.$this->seccional->nombre.' · se actualiza solo cada 30 s'" en-vivo>
        <x-slot:acciones>
            <x-ui.seccional :seccionales="$this->seccionales" />
            <dl class="flex flex-wrap items-baseline gap-x-6 gap-y-1 rounded-2xl border border-ink-200 bg-white px-5 py-3 shadow-card">
                @foreach ([['entregadas', 'entregadas', 'text-ok-700'], ['no_entregadas', 'no entregadas', 'text-warn-700'], ['anuladas', 'anuladas', 'text-bad-700'], ['pendientes', 'pendientes', 'text-ink-600']] as [$clave, $nombre, $color])
                    <div class="flex items-baseline gap-1.5">
                        <dd x-data x-cifra class="cifra cifra-grande text-2xl font-extrabold text-ink-900">{{ $totales[$clave] }}</dd>
                        <dt class="text-[15px] font-semibold {{ $color }}">{{ $nombre }}</dt>
                    </div>
                @endforeach
            </dl>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="grid gap-6 p-5 sm:p-8 lg:p-10 2xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card :padding="false">
                <div class="px-6 pt-5">
                    <h2 class="text-xl font-bold tracking-tight text-ink-900">Repartidores en ruta</h2>
                    <p class="mt-0.5 text-[15px] text-ink-500">{{ $conteos['todos'] }} {{ $conteos['todos'] === 1 ? 'repartidor' : 'repartidores' }} · {{ $totales['guias'] }} guías · las alertas aparecen primero</p>

                    <div class="mt-4 flex flex-wrap gap-2.5 pb-5">
                        @foreach (['todos' => 'Todos', 'en_reparto' => 'En reparto', 'alerta' => 'Con alerta', 'cerrada' => 'Ruta cerrada'] as $clave => $nombre)
                            <x-ui.pill :activo="$filtro === $clave" wire:click="$set('filtro', '{{ $clave }}')">{{ $nombre }} · {{ $conteos[$clave] }}</x-ui.pill>
                        @endforeach
                    </div>
                </div>

                @if ($rutas->isEmpty())
                    <x-ui.empty icono="route" :titulo="$conteos['todos'] === 0 ? 'Hoy no hay rutas publicadas' : 'Ningún repartidor en este filtro'" :texto="$conteos['todos'] === 0 ? 'Cuando publiques la planeación del día, aquí verás el avance de cada repartidor en tiempo real.' : null" class="border-t border-ink-200">
                        @if ($conteos['todos'] === 0)
                            <x-ui.button :href="route('admin.planeacion')" icon="user-round-check" wire:navigate>Ir a planeación</x-ui.button>
                        @endif
                    </x-ui.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead><tr><th>Repartidor</th><th>Avance de la ruta</th><th>Última actividad y próxima parada</th><th>Estado</th></tr></thead>
                            <tbody x-data x-animar>
                                @foreach ($rutas as $fila)
                                    @php
                                        $ruta = $fila['ruta'];
                                        $ultima = $fila['ultima'];
                                        $alerta = $fila['alerta'];
                                    @endphp
                                    <tr wire:key="ruta-{{ $ruta->id }}" @class(['bg-warn-50/70' => $alerta])>
                                        <td>
                                            <div class="flex items-center gap-3.5">
                                                <x-ui.avatar :iniciales="$ruta->repartidor->iniciales" />
                                                <div class="min-w-0">
                                                    <p class="truncate text-[16px] font-bold text-ink-900">{{ $ruta->repartidor->nombre }}</p>
                                                    <p class="truncate text-sm text-ink-500">{{ $fila['zonas'] ?: 'Sin zona' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="min-w-52">
                                            <div class="flex items-center gap-3.5">
                                                <x-ui.rollo :segmentos="[[$fila['entregadas'], 'ok'], [$fila['no_entregadas'], 'warn'], [$fila['anuladas'], 'bad']]" :total="max(1, $fila['total'])" class="size-12">
                                                    <span class="cifra text-[13px] font-extrabold text-ink-900">{{ $fila['total'] > 0 ? round($fila['visitadas'] * 100 / $fila['total']) : 0 }}%</span>
                                                </x-ui.rollo>
                                                <p class="text-[15px]"><strong class="cifra font-bold text-ink-900">{{ $fila['visitadas'] }} de {{ $fila['total'] }}</strong> <span class="text-ink-500">visitadas</span></p>
                                            </div>
                                        </td>
                                        <td class="max-w-80">
                                            @if ($fila['estado'] === \App\Services\Seguimiento\SeguimientoService::SIN_ACTIVIDAD)
                                                <p class="font-bold text-warn-700">Sin registros hace {{ \App\Support\Duracion::corta($fila['minutos_sin_actividad']) }}</p>
                                            @elseif ($ruta->estaCerrada())
                                                <p class="font-semibold text-ink-900">Cerró la ruta</p>
                                            @elseif ($ultima)
                                                <p class="font-semibold text-ink-900">{{ $ultima->visita->resultado === \App\Enums\ResultadoVisita::Entregado ? 'Entregó en' : ($ultima->visita->resultado === \App\Enums\ResultadoVisita::Anulado ? 'Anuló' : 'No entregó en') }} {{ $ultima->guia->nombre_comercio }}</p>
                                            @elseif ($ruta->iniciada_at)
                                                <p class="font-semibold text-ink-900">Salió a ruta</p>
                                            @else
                                                <p class="font-semibold text-ink-700">Aún no inicia la ruta</p>
                                            @endif
                                            <p class="mt-0.5 truncate text-sm text-ink-500">
                                                @if ($ruta->estaCerrada())
                                                    {{ $ruta->cerrada_at->isoFormat('h:mm a') }}@if ($fila['no_entregadas'] > 0) · {{ $fila['no_entregadas'] }} {{ $fila['no_entregadas'] === 1 ? 'reprogramada' : 'reprogramadas' }}@endif
                                                @else
                                                    {{ ($ultima?->visitada_at ?? $ruta->iniciada_at)?->isoFormat('h:mm a') }}@if ($fila['siguiente']){{ ($ultima || $ruta->iniciada_at) ? ' · ' : '' }}Sigue: {{ $fila['siguiente']->guia->nombre_comercio }}@endif
                                                @endif
                                            </p>
                                        </td>
                                        <td>
                                            @switch($fila['estado'])
                                                @case(\App\Services\Seguimiento\SeguimientoService::SIN_ACTIVIDAD)
                                                    <x-ui.badge color="amber" icon="triangle-alert">Sin actividad</x-ui.badge>
                                                    @break
                                                @case(\App\Services\Seguimiento\SeguimientoService::SIN_INICIAR)
                                                    <x-ui.badge color="amber" icon="triangle-alert">Ruta sin iniciar</x-ui.badge>
                                                    @break
                                                @case(\App\Services\Seguimiento\SeguimientoService::CERRADA)
                                                    <x-ui.badge :color="$ruta->cuadre_ok === false ? 'red' : 'green'">{{ $ruta->cuadre_ok === false ? 'Cerrada · no cuadra' : 'Ruta cerrada' }}</x-ui.badge>
                                                    @break
                                                @case(\App\Services\Seguimiento\SeguimientoService::POR_SALIR)
                                                    <x-ui.badge color="gray">Por salir</x-ui.badge>
                                                    @break
                                                @default
                                                    <x-ui.badge color="blue">En reparto</x-ui.badge>
                                            @endswitch
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-ink-200 px-6 py-4 text-sm text-ink-600">
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-ok-500"></span> Entregada</span>
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-warn-500"></span> No entregada</span>
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-bad-500"></span> Anulada</span>
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-ink-200"></span> Pendiente</span>
                    </p>
                @endif
            </x-ui.card>

            <x-ui.card titulo="Alertas automáticas">
                <x-slot:acciones>
                    @can('configuracion.gestionar')
                        <a href="{{ route('admin.configuracion') }}" wire:navigate class="enlace text-[15px]">Configurar</a>
                    @endcan
                </x-slot:acciones>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        ['clock', 'ANS en riesgo', 'Queda menos del 25 % del tiempo y la guía aún no se ha visitado.'],
                        ['triangle-alert', 'Sin actividad', "El repartidor no registra visitas en {$sinActividadMin} minutos."],
                        ['route', 'Ruta sin iniciar', 'A las '.\Carbon\Carbon::createFromFormat('H:i', $horaInicio)->isoFormat('h:mm a').' aún no ha tocado «Empezar ruta».'],
                    ] as [$icono, $titulo, $texto])
                        <div class="flex items-start gap-3.5 rounded-xl bg-ink-50 p-4">
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-ink-200 bg-white text-brand-600"><x-dynamic-component :component="'lucide-'.$icono" class="size-5" /></span>
                            <div>
                                <p class="text-[15px] font-bold text-ink-900">{{ $titulo }}</p>
                                <p class="mt-0.5 text-sm leading-5 text-ink-500">{{ $texto }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <aside class="grid content-start gap-6 md:grid-cols-2 2xl:grid-cols-1">
            <x-ui.card titulo="Novedades del día">
                <div class="space-y-3.5" x-data x-animar>
                    @foreach ($alertas as $alerta)
                        <div wire:key="alerta-{{ $alerta['tipo'] }}-{{ $loop->index }}" @class(['rounded-xl p-4', $alerta['gravedad'] <= 1 ? 'bg-bad-50' : 'bg-warn-50'])>
                            <p @class(['text-[15px] font-bold', $alerta['gravedad'] <= 1 ? 'text-bad-700' : 'text-warn-700'])>{{ $alerta['titulo'] }} · Alerta del sistema</p>
                            <p class="mt-1 text-[15px] leading-6 text-ink-900">{{ $alerta['texto'] }}</p>
                            @if ($alerta['tipo'] === 'ans')
                                <div class="mt-3 flex flex-wrap gap-2.5">
                                    <x-ui.button size="sm" variant="dark" wire:click="priorizar({{ $alerta['parada_id'] }})" wire:loading.attr="disabled">Priorizar en la ruta</x-ui.button>
                                    <x-ui.button size="sm" variant="outline" :href="route('admin.guias.show', $alerta['servicio'])" wire:navigate>Ver guía</x-ui.button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @foreach ($novedades as $novedad)
                        <div wire:key="novedad-{{ $novedad->id }}" @class(['rounded-xl p-4', $novedad->estado === \App\Models\Novedad::ABIERTA ? 'bg-ink-100' : 'bg-ink-50 opacity-70'])>
                            <p class="text-[15px] font-bold text-ink-900">{{ $novedad->reportada_at->isoFormat('h:mm a') }} · {{ $novedad->repartidor->nombre }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-ink-600">{{ $novedad->tipo->etiqueta() }}@if ($novedad->guia) · {{ $novedad->guia->nombre_comercio }}@endif</p>
                            <p class="mt-1 text-[15px] leading-6 text-ink-800">{{ $novedad->descripcion }}</p>
                            @if ($novedad->estado === \App\Models\Novedad::ABIERTA)
                                <x-ui.button size="sm" variant="outline" class="mt-3" icon="check" wire:click="atender({{ $novedad->id }})">Marcar como atendida</x-ui.button>
                            @else
                                <p class="mt-2 text-sm font-semibold text-ok-700">Atendida</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($alertas->isEmpty() && $novedades->isEmpty())
                        <p class="flex items-center gap-2.5 rounded-xl bg-ok-50 p-4 text-[15px] font-semibold text-ok-700"><x-lucide-circle-check class="size-5" /> Sin alertas ni novedades por ahora.</p>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card titulo="Actividad reciente">
                <ol class="space-y-4" x-data x-animar>
                    @forelse ($actividad as $evento)
                        <li class="flex gap-3" wire:key="actividad-{{ $evento['cuando']->timestamp }}-{{ $loop->index }}">
                            <span @class(['mt-1.5 size-3 shrink-0 rounded-full', match ($evento['tono']) { 'green' => 'bg-ok-500', 'amber' => 'bg-warn-500', 'red' => 'bg-bad-500', default => 'bg-ink-900' }])></span>
                            <div class="min-w-0">
                                <p class="text-[15px] leading-6 text-ink-800"><strong class="font-bold text-ink-900">{{ $evento['quien'] }}</strong> {{ $evento['texto'] }}</p>
                                <p class="text-sm text-ink-500">{{ $evento['cuando']->isoFormat('h:mm a') }}@if ($evento['detalle']) · {{ $evento['detalle'] }}@endif</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-[15px] text-ink-500">Aquí aparecerá cada visita apenas el repartidor la registre.</li>
                    @endforelse
                </ol>
            </x-ui.card>
        </aside>
    </div>
</div>
