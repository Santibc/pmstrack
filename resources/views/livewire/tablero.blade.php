<div>
    <x-ui.page-header :titulo="$portal ? 'Dashboard de entregas' : 'Dashboard operativo'" subtitulo="Datos en tiempo real" en-vivo>
        <x-slot:acciones>
            <div class="flex items-center gap-1 rounded-xl border border-ink-300 bg-white p-1 shadow-card">
                @foreach (['mes' => 'Este mes', 'semana' => '7 días', 'anterior' => 'Mes anterior'] as $clave => $nombre)
                    <button type="button" wire:click="periodo('{{ $clave }}')" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-700 hover:bg-ink-100">{{ $nombre }}</button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="desde" class="campo h-11 w-auto py-0 font-semibold" aria-label="Desde" />
                <span class="text-ink-400">–</span>
                <input type="date" wire:model.live="hasta" class="campo h-11 w-auto py-0 font-semibold" aria-label="Hasta" />
            </div>
            @if ($seccionales->count() > 1)
                <select wire:model.live="seccional" class="campo h-11 w-auto min-w-52 py-0 font-semibold" aria-label="Seccional">
                    <option value="">Todas las seccionales</option>
                    @foreach ($seccionales as $opcion)
                        <option value="{{ $opcion->codigo }}">{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            @endif
            <x-ui.button variant="dark" icon="download" :href="route($area.'.guias.exportar', ['formato' => 'xlsx'] + $consulta)">Descargar Excel</x-ui.button>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-6 p-5 sm:p-8 lg:p-10" wire:loading.class="cargando" wire:target="periodo,desde,hasta,seccional">
        {{-- Solo PMS: el pulso de hoy y lo que requiere atención --}}
        @if ($hoy)
            <section class="grid gap-6 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
                <div class="flex items-center gap-6 rounded-2xl bg-ink-900 p-6 text-white">
                    <x-ui.rollo :segmentos="[[$hoy['entregadas'], 'lima'], [$hoy['no_entregadas'], 'warn'], [$hoy['anuladas'], 'bad']]" :total="max(1, $hoy['total'])" grosor="10" class="size-32 [&_circle:first-child]:stroke-white/15">
                        <span x-data x-cifra class="cifra cifra-grande text-3xl font-extrabold">{{ $hoy['entregadas'] }}</span>
                        <span class="mt-1 text-xs text-ink-300">de {{ $hoy['total'] }}</span>
                    </x-ui.rollo>
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold tracking-tight">Hoy en la calle</h2>
                        <p class="mt-1 text-[15px] leading-6 text-ink-300">
                            @if ($hoy['total'] === 0)
                                Aún no hay rutas publicadas para hoy.
                            @else
                                {{ $hoy['entregadas'] }} entregadas, {{ $hoy['no_entregadas'] }} no entregadas, {{ $hoy['anuladas'] }} anuladas y {{ $hoy['por_visitar'] }} por visitar.
                            @endif
                        </p>
                        <a href="{{ $hoy['total'] === 0 ? route('admin.planeacion') : route('admin.seguimiento') }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 text-[15px] font-bold text-brand-300 hover:text-brand-200">
                            {{ $hoy['total'] === 0 ? 'Planear el día' : 'Ver seguimiento en vivo' }} <x-lucide-chevron-right class="size-4" />
                        </a>
                    </div>
                </div>

                <x-ui.card titulo="Requiere tu atención">
                    @if ($hoy['pendientes'] === [])
                        <p class="flex items-center gap-2.5 rounded-xl bg-ok-50 p-4 text-[15px] font-semibold text-ok-700"><x-lucide-circle-check class="size-5" /> Todo al día: no hay cargas, direcciones ni alertas pendientes.</p>
                    @else
                        <ul class="grid gap-2.5 md:grid-cols-2" x-data x-animar>
                            @foreach ($hoy['pendientes'] as $item)
                                <li wire:key="pendiente-{{ $item['icono'] }}">
                                    <a href="{{ $item['ruta'] }}" wire:navigate class="group flex items-center gap-3.5 rounded-xl border border-ink-200 p-3.5 transition hover:border-ink-300 hover:bg-ink-50">
                                        <span @class(['inline-flex size-10 shrink-0 items-center justify-center rounded-lg', ['brand' => 'bg-brand-100 text-brand-700', 'warn' => 'bg-warn-50 text-warn-700', 'bad' => 'bg-bad-50 text-bad-700'][$item['tono']]])>
                                            <x-dynamic-component :component="'lucide-'.$item['icono']" class="size-5" />
                                        </span>
                                        <span class="min-w-0 flex-1 text-[15px] leading-5 text-ink-700"><strong class="cifra text-lg font-extrabold text-ink-900">{{ $item['total'] }}</strong> {{ $item['texto'][$item['total'] === 1 ? 0 : 1] }}</span>
                                        <x-lucide-chevron-right class="size-4 shrink-0 text-ink-400 transition-transform group-hover:translate-x-0.5" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </section>
        @endif

        {{-- Cifras del periodo --}}
        <section class="grid divide-y divide-ink-200 overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card md:grid-cols-2 md:divide-x xl:grid-cols-4 xl:divide-y-0">
            <div class="p-6">
                <p class="flex items-center gap-2 text-[15px] font-medium text-ink-600"><x-lucide-file-up class="size-[18px]" /> Servicios recibidos</p>
                <p x-data x-cifra class="cifra cifra-grande mt-3 text-[42px] leading-none font-extrabold tracking-tight">{{ number_format($resumen['recibidos'], 0, ',', '.') }}</p>
                <p class="mt-3 text-[15px] font-semibold {{ ($resumen['variacion_recibidos'] ?? 0) >= 0 ? 'text-ok-700' : 'text-bad-700' }}">
                    @if ($resumen['variacion_recibidos'] !== null)
                        <x-dynamic-component :component="'lucide-'.($resumen['variacion_recibidos'] >= 0 ? 'trending-up' : 'trending-down')" class="mr-1 inline size-[18px]" />{{ $resumen['variacion_recibidos'] > 0 ? '+' : '' }}{{ number_format($resumen['variacion_recibidos'], 1, ',', '.') }} % frente al periodo anterior
                    @else
                        <span class="font-normal text-ink-500">{{ $rango }}</span>
                    @endif
                </p>
            </div>

            <div class="p-6">
                <p class="flex items-center gap-2 text-[15px] font-medium text-ink-600"><x-lucide-circle-check class="size-[18px]" /> Entregados</p>
                <p class="mt-3 flex items-baseline gap-2.5">
                    <span x-data x-cifra class="cifra cifra-grande text-[42px] leading-none font-extrabold tracking-tight">{{ number_format($resumen['entregados'], 0, ',', '.') }}</span>
                    @if ($resumen['porcentaje_entregados'] !== null)
                        <span x-data x-cifra class="cifra text-xl font-bold text-ok-600">{{ number_format($resumen['porcentaje_entregados'], 1, ',', '.') }} %</span>
                    @endif
                </p>
                <x-ui.progress class="mt-4" :segmentos="[[$resumen['entregados'], 'ok']]" :total="max(1, $resumen['recibidos'])" />
            </div>

            <div class="flex items-center gap-5 p-6">
                <x-ui.rollo :segmentos="[[$resumen['cumplimiento_ans'] ?? 0, ($resumen['cumplimiento_ans'] ?? 100) >= $resumen['meta_ans'] ? 'ok' : 'warn']]" :total="100" grosor="11" class="size-20">
                    <x-lucide-shield-check class="size-7 text-ink-400" stroke-width="1.7" />
                </x-ui.rollo>
                <div class="min-w-0">
                    <p class="text-[15px] font-medium text-ink-600">Cumplimiento de ANS</p>
                    <p x-data x-cifra class="cifra cifra-grande mt-1.5 text-[30px] leading-none font-extrabold tracking-tight whitespace-nowrap">{{ $resumen['cumplimiento_ans'] !== null ? number_format($resumen['cumplimiento_ans'], 1, ',', '.').' %' : '—' }}</p>
                    <p class="mt-2 text-sm leading-5 text-ink-500">{{ number_format($resumen['a_tiempo'], 0, ',', '.') }} de {{ number_format($resumen['entregados'], 0, ',', '.') }} a tiempo · meta {{ $resumen['meta_ans'] }} %</p>
                </div>
            </div>

            <div class="p-6">
                <p class="flex items-center gap-2 text-[15px] font-medium text-ink-600"><x-lucide-clock class="size-[18px]" /> Tiempo promedio de entrega</p>
                <p x-data x-cifra class="cifra cifra-grande mt-3 text-[42px] leading-none font-extrabold tracking-tight">{{ $resumen['horas_promedio'] !== null ? $resumen['horas_promedio'].' h' : '—' }}</p>
                <p class="mt-3 text-[15px] font-semibold {{ ($resumen['variacion_horas'] ?? 0) <= 0 ? 'text-ok-700' : 'text-bad-700' }}">
                    @if ($resumen['variacion_horas'] !== null && $resumen['variacion_horas'] !== 0)
                        {{ abs($resumen['variacion_horas']) }} h {{ $resumen['variacion_horas'] < 0 ? 'menos' : 'más' }} que en el periodo anterior
                    @else
                        <span class="font-normal text-ink-500">Desde la solicitud hasta la entrega</span>
                    @endif
                </p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,440px)]">
            <x-ui.card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5">
                    <h2 class="text-xl font-bold tracking-tight text-ink-900">Servicios recibidos y entregados por día</h2>
                    <p class="flex items-center gap-5 text-[15px] text-ink-600">
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-[#c9ced6]"></span> Recibidos</span>
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-brand-500"></span> Entregados</span>
                    </p>
                </div>
                <div class="px-4 pt-4 pb-2" wire:key="grafica-{{ md5(json_encode($grafica)) }}">
                    <div x-data="graficaBarras(@js($grafica))" class="h-72"><canvas x-ref="lienzo" role="img" aria-label="Servicios recibidos y entregados por día"></canvas></div>
                </div>
                <p class="px-6 pb-5 text-sm leading-5 text-ink-500">Días hábiles del periodo ({{ $rango }}). El día de hoy se va llenando a medida que los repartidores registran entregas.</p>
            </x-ui.card>

            <x-ui.card titulo="Estado de los servicios">
                <div class="flex flex-wrap items-center gap-6">
                    <div class="relative size-44 shrink-0" wire:key="dona-{{ md5(json_encode($estados)) }}">
                        <div x-data="graficaDona(@js(['etiquetas' => ['Entregados', 'En reparto', 'No entregados', 'Anulados', 'Por salir'], 'valores' => [$estados['entregados'], $estados['en_reparto'], $estados['no_entregados'], $estados['anulados'], $estados['por_salir']], 'colores' => ['verde', 'azul', 'ambar', 'rojo', 'gris']]))" class="size-full"><canvas x-ref="lienzo"></canvas></div>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span x-data x-cifra class="cifra cifra-grande text-3xl font-extrabold">{{ number_format($estados['total'], 0, ',', '.') }}</span>
                            <span class="text-sm text-ink-500">servicios</span>
                        </div>
                    </div>
                    <ul class="min-w-44 flex-1 space-y-3 text-[16px]">
                        @foreach ([['Entregados', 'entregados', 'bg-ok-500'], ['En reparto ahora', 'en_reparto', 'bg-info-500'], ['No entregados (se reprograman)', 'no_entregados', 'bg-warn-500'], ['Anulados', 'anulados', 'bg-bad-500'], ['Por salir a ruta', 'por_salir', 'bg-ink-300']] as [$nombre, $clave, $color])
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2.5 text-ink-700"><span class="size-3 shrink-0 rounded-full {{ $color }}"></span> {{ $nombre }}</span>
                                <span x-data x-cifra class="cifra font-extrabold">{{ number_format($estados[$clave], 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <p class="mt-6 flex items-center gap-3 rounded-xl bg-ink-50 px-4 py-3.5 text-[15px] text-ink-700">
                    <x-lucide-truck class="size-5 text-info-500" />
                    {{ $enRuta === 0 ? 'Ningún repartidor en ruta en este momento' : $enRuta.' '.($enRuta === 1 ? 'repartidor en ruta' : 'repartidores en ruta').' en este momento' }}
                </p>
            </x-ui.card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <x-ui.card titulo="Cumplimiento de ANS por seccional">
                <ul class="space-y-5">
                    @foreach ($ansPorSeccional as $fila)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-[16px]">
                                <span class="font-bold text-ink-900">{{ $fila['seccional'] }}</span>
                                <span class="text-ink-600"><strong class="cifra font-extrabold text-ink-900">{{ $fila['cumplimiento'] !== null ? number_format($fila['cumplimiento'], 1, ',', '.').' %' : '—' }}</strong> · {{ number_format($fila['entregas'], 0, ',', '.') }} entregas</span>
                            </div>
                            <x-ui.progress class="mt-2 h-2.5" :segmentos="[[$fila['cumplimiento'] ?? 0, 'brand']]" :total="100" />
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 text-sm leading-5 text-ink-500">Meta de cumplimiento: {{ $resumen['meta_ans'] }} %. ANS según el campo de Credibanco (168 h u 8 h).</p>
            </x-ui.card>

            <x-ui.card titulo="Motivos de no entrega y anulación">
                @php $maximo = max(1, (int) $motivos->max('total')); @endphp
                @if ($motivos->isEmpty())
                    <p class="text-[15px] text-ink-500">Sin visitas fallidas en el periodo.</p>
                @else
                    <ul class="space-y-3.5">
                        @foreach ($motivos as $motivo)
                            <li class="text-[16px]">
                                <p class="flex items-baseline justify-between gap-3"><span class="text-ink-800">{{ $motivo['nombre'] }}</span><span class="cifra font-extrabold">{{ $motivo['total'] }}</span></p>
                                <x-ui.progress class="mt-1.5 h-2" :segmentos="[[$motivo['total'], $motivo['tipo'] === 'anulado' ? 'bad' : 'warn']]" :total="$maximo" />
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-5 flex items-center gap-5 text-sm text-ink-600">
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-warn-500"></span> No entregado</span>
                        <span class="flex items-center gap-2"><span class="size-3 rounded-sm bg-bad-500"></span> Anulado</span>
                    </p>
                @endif
            </x-ui.card>

            <section class="rounded-2xl bg-ink-900 p-6 text-white lg:col-span-2 xl:col-span-1">
                <x-ui.badge color="amber" icon="triangle-alert" class="!bg-warn-500/20 !text-warn-500">Calidad de datos</x-ui.badge>
                <h2 class="mt-4 text-2xl leading-tight font-bold tracking-tight">
                    @if ($calidad === 0)
                        Sin comercios por actualizar en el periodo
                    @else
                        {{ $calidad }} {{ $calidad === 1 ? 'comercio' : 'comercios' }} con dirección o teléfono para actualizar
                    @endif
                </h2>
                <p class="mt-3 text-[15px] leading-6 text-ink-300">Detectados por los repartidores en terreno y por la carga del archivo: direcciones que no existen, comercios trasladados o teléfonos incompletos.</p>
                @if ($calidad > 0)
                    <div class="mt-5 flex flex-wrap gap-3">
                        <x-ui.button icon="download" :href="route($area.'.calidad.exportar', $consulta)">Descargar listado</x-ui.button>
                        <x-ui.button variant="ghost" class="text-white hover:bg-white/10" :href="route($area.'.calidad', $consulta)" wire:navigate>Ver en pantalla</x-ui.button>
                    </div>
                @endif
            </section>
        </div>

        {{-- Últimos movimientos --}}
        <x-ui.card :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                <h2 class="text-xl font-bold tracking-tight text-ink-900">Últimos movimientos</h2>
                <a href="{{ route($area.'.guias') }}" wire:navigate class="enlace text-[15px]">Ver todo el histórico</a>
            </div>
            @if ($movimientos->isEmpty())
                <x-ui.empty icono="history" titulo="Aún no hay movimientos" texto="Aquí aparecerá cada servicio a medida que se cargue, se asigne y se entregue." class="border-t border-ink-200" />
            @else
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>Servicio</th><th>Comercio</th><th>Ciudad</th><th class="text-right">Rollos</th><th>Estado</th><th>Fecha y hora</th><th>Evidencia</th></tr></thead>
                        <tbody x-data x-animar>
                            @foreach ($movimientos as $guia)
                                @php $visita = $guia->ultimaVisita; @endphp
                                <tr wire:key="movimiento-{{ $guia->id }}" class="cursor-pointer hover:bg-ink-50" x-on:click="Livewire.navigate('{{ route($area.'.guias.show', $guia) }}')">
                                    <td class="font-bold text-ink-900">{{ $guia->servicio }}</td>
                                    <td>{{ $guia->nombre_comercio }}</td>
                                    <td>{{ $guia->ciudad->nombre }}</td>
                                    <td class="text-right">{{ $guia->rollos_solicitados }}</td>
                                    <td><x-guia.estado :guia="$guia" :detalle="$guia->estado->esFinal() || $guia->estado === \App\Enums\EstadoGuia::NoEntregada ? ($guia->estado === \App\Enums\EstadoGuia::Entregada ? null : $visita?->causal?->nombre) : null" /></td>
                                    <td class="whitespace-nowrap text-ink-700">{{ $guia->ultimo_movimiento_at->isoFormat('D MMM · h:mm a') }}</td>
                                    <td>
                                        @if ($visita)
                                            <span class="flex items-center gap-2.5 text-ink-600">
                                                @if ($visita->evidencias->whereIn('tipo', [\App\Enums\TipoEvidencia::Foto, \App\Enums\TipoEvidencia::Fachada])->isNotEmpty())<x-lucide-camera class="size-5" aria-label="Con foto" />@endif
                                                @if ($visita->evidencias->contains('tipo', \App\Enums\TipoEvidencia::Firma))<x-lucide-pen-line class="size-5" aria-label="Con firma" />@endif
                                                @if ($visita->tieneUbicacion())<x-lucide-map-pin class="size-5" aria-label="Con GPS" />@endif
                                            </span>
                                        @else
                                            <span class="text-ink-400">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
