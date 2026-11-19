<div>
    <x-ui.page-header :titulo="$portal ? 'Reportes Excel' : 'Reportes'" subtitulo="Se generan con los datos del momento, sin digitación.">
        <x-slot:acciones>
            <div class="flex items-center gap-2">
                <input type="date" wire:model.live="desde" class="campo h-11 w-auto py-0 font-semibold" aria-label="Desde" />
                <span class="text-ink-400">–</span>
                <input type="date" wire:model.live="hasta" class="campo h-11 w-auto py-0 font-semibold" aria-label="Hasta" />
            </div>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-6 p-5 sm:p-8 lg:p-10">
        {{-- El reporte principal: entregas en el formato de Credibanco --}}
        <section class="flex flex-wrap items-center justify-between gap-5 rounded-2xl bg-ink-900 p-6 text-white">
            <div class="flex items-start gap-4">
                <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-ink-950"><x-lucide-file-spreadsheet class="size-7" /></span>
                <div>
                    <h2 class="text-xl font-bold tracking-tight">Reporte de entregas en el formato de Credibanco</h2>
                    <p class="mt-1 max-w-2xl text-[15px] leading-6 text-ink-300">Las mismas 16 columnas del archivo que envía Credibanco, en el mismo orden, más el resultado de cada servicio: estado, fecha de entrega, quien recibió, ANS y el enlace a la evidencia.</p>
                </div>
            </div>
            <x-ui.button size="lg" icon="download" :href="route($area.'.guias.exportar', ['formato' => 'xlsx'] + $rango)">Descargar Excel</x-ui.button>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
            <nav class="space-y-2.5" aria-label="Tipo de reporte">
                @foreach ($tipos as $clave => [$nombre, $descripcion, $icono])
                    <button type="button" wire:click="$set('tipo', '{{ $clave }}')" @class([
                        'flex w-full items-start gap-3.5 rounded-2xl border p-4 text-left transition',
                        'border-brand-400 bg-brand-50 shadow-card' => $tipo === $clave,
                        'border-ink-200 bg-white hover:border-ink-300' => $tipo !== $clave,
                    ])>
                        <span @class(['inline-flex size-10 shrink-0 items-center justify-center rounded-lg', $tipo === $clave ? 'bg-brand-500 text-ink-950' : 'bg-ink-100 text-ink-600'])>
                            <x-dynamic-component :component="'lucide-'.$icono" class="size-5" />
                        </span>
                        <span>
                            <span class="block text-[16px] font-bold text-ink-900">{{ $nombre }}</span>
                            <span class="mt-0.5 block text-sm leading-5 text-ink-500">{{ $descripcion }}</span>
                        </span>
                    </button>
                @endforeach
            </nav>

            <x-ui.card :padding="false" wire:loading.class="cargando">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-ink-900">{{ $reporte['titulo'] }}</h2>
                        <p class="mt-0.5 text-sm text-ink-500">Del {{ \Carbon\Carbon::parse($desde)->isoFormat('D [de] MMMM') }} al {{ \Carbon\Carbon::parse($hasta)->isoFormat('D [de] MMMM [de] YYYY') }}</p>
                    </div>
                    <x-ui.button variant="outline" icon="download" :href="route($area.'.reportes.exportar', ['tipo' => $tipo] + $rango)">Descargar Excel</x-ui.button>
                </div>

                @if ($reporte['filas'] === [])
                    <x-ui.empty icono="chart-column" titulo="Sin datos en este periodo" texto="Cambia el rango de fechas para ver el reporte." class="border-t border-ink-200" />
                @else
                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead>
                                <tr>
                                    @foreach ($reporte['encabezados'] as $encabezado)
                                        <th @class(['text-right' => ! $loop->first && $tipo !== 'causales' || ($tipo === 'causales' && $loop->index > 1)])>{{ $encabezado }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reporte['filas'] as $fila)
                                    <tr wire:key="fila-{{ $tipo }}-{{ $loop->index }}">
                                        @foreach ($fila as $celda)
                                            <td @class(['font-bold text-ink-900' => $loop->first, 'text-right' => is_numeric($celda) || (is_string($celda) && preg_match('/^[\d.,]+ (%|h)$|^—$/u', $celda))])>
                                                {{ is_int($celda) ? number_format($celda, 0, ',', '.') : $celda }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
