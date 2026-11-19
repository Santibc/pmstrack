<div>
    <x-ui.page-header titulo="Calidad de datos" subtitulo="Comercios con dirección errada, cerrados o con teléfono incompleto, para depurar la base de Credibanco.">
        <x-slot:acciones>
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
            <x-ui.button variant="dark" icon="download" :href="route($area.'.calidad.exportar', $consulta)">Descargar listado</x-ui.button>
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-5 p-5 sm:p-8 lg:p-10">
        <div class="flex flex-wrap gap-2.5">
            @foreach (['' => 'Todos', 'terreno' => 'Detectados en terreno', 'telefono' => 'Teléfono incompleto', 'ciudad' => 'Ciudad y seccional no coinciden'] as $clave => $nombre)
                <x-ui.pill :activo="$tipo === $clave" wire:click="$set('tipo', '{{ $clave }}')">{{ $nombre }} · {{ $conteos[$clave] }}</x-ui.pill>
            @endforeach
        </div>

        <x-ui.card :padding="false" wire:loading.class="cargando">
            @if ($guias->isEmpty())
                <x-ui.empty icono="circle-check" titulo="Sin datos por corregir en este periodo" texto="Cuando un repartidor encuentre una dirección errada o un comercio cerrado, o llegue un teléfono incompleto en el archivo, aparecerá aquí." />
            @else
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>Comercio</th><th>Servicio</th><th>Dato que llegó</th><th>Qué corregir</th><th class="w-0"></th></tr></thead>
                        <tbody>
                            @foreach ($guias as $guia)
                                <tr wire:key="calidad-{{ $guia->id }}">
                                    <td class="max-w-60">
                                        <p class="font-bold text-ink-900">{{ $guia->nombre_comercio }}</p>
                                        <p class="text-sm text-ink-500">CU {{ $guia->comercio->codigo_unico }}</p>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <p class="font-semibold">{{ $guia->servicio }}</p>
                                        <p class="text-sm text-ink-500">{{ $guia->fecha_registro->isoFormat('D MMM YYYY') }}</p>
                                    </td>
                                    <td class="max-w-64 text-ink-700">
                                        <p>{{ $guia->direccion_original }}</p>
                                        <p class="text-sm text-ink-500">{{ $guia->ciudad_original }} · {{ $guia->telefono ?? 'sin teléfono' }}</p>
                                    </td>
                                    <td class="max-w-96">
                                        <ul class="space-y-1 text-[15px] leading-5 text-ink-800">
                                            @foreach ($calidad->hallazgos($guia) as $hallazgo)
                                                <li class="flex gap-2"><x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0 text-warn-600" /> {{ $hallazgo }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td><a href="{{ route($area.'.guias.show', $guia) }}" wire:navigate class="enlace whitespace-nowrap">Ver</a></td>
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
