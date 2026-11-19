<div>
    <x-ui.page-header titulo="Inventario de rollos" :subtitulo="ucfirst($dia->isoFormat('dddd D [de] MMMM')).' · '.$bodega->nombre.' · cajas de '.$porCaja.' rollos'">
        <x-slot:acciones>
            <input type="date" wire:model.live="fecha" max="{{ today()->toDateString() }}" class="campo h-11 w-auto py-0 font-semibold" aria-label="Día" />
            <x-ui.seccional :seccionales="$this->seccionales" prefijo="Bodega " />
            @can('inventario.gestionar')
                <x-ui.button variant="outline" icon="sliders-horizontal" wire:click="abrirAjuste">Ajuste</x-ui.button>
                <x-ui.button icon="plus" wire:click="abrirEntrada">Registrar entrada</x-ui.button>
            @endcan
        </x-slot:acciones>
    </x-ui.page-header>

    <div class="space-y-6 p-5 sm:p-8 lg:p-10">
        @if ($bodega->saldo < 0)
            <div class="flex items-center gap-3 rounded-2xl border border-bad-100 bg-bad-50 px-5 py-4 text-[15px] text-ink-900" role="alert">
                <x-lucide-triangle-alert class="size-5 shrink-0 text-bad-600" />
                <span><strong>El saldo está en negativo.</strong> Salieron más rollos de los registrados: falta registrar una entrada por remisión.</span>
            </div>
        @endif

        {{-- Indicadores del día: una sola franja --}}
        <section class="grid divide-y divide-ink-200 overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_repeat(4,minmax(0,1fr))] xl:divide-x xl:divide-y-0">
            <div class="flex items-center gap-4 p-5 sm:col-span-2 xl:col-span-1">
                <x-ui.rollo-papel :nivel="$bodega->stock_minimo > 0 ? $bodega->saldo / ($bodega->stock_minimo * 2) : ($bodega->saldo > 0 ? 1 : 0)" :bajo="$bodega->estaBajoMinimo()" class="size-[76px] shrink-0" />
                <div class="min-w-0">
                    <p class="text-[15px] font-medium text-ink-600">En bodega</p>
                    <p x-data x-cifra @class(['cifra cifra-grande text-[34px] leading-none font-extrabold tracking-tight', $bodega->estaBajoMinimo() ? 'text-warn-700' : 'text-ink-900'])>{{ \App\Support\Rollos::numero($resumen['en_bodega']) }}</p>
                    <p class="mt-1.5 text-sm font-semibold text-ink-800">{{ \App\Support\Rollos::enCajas($resumen['en_bodega']) }}</p>
                </div>
            </div>

            @foreach ([
                ['Entraron', ($resumen['entraron'] > 0 ? '+' : '').\App\Support\Rollos::numero($resumen['entraron']), $resumen['ultima_remision'] ? 'Remisión Credibanco '.$resumen['ultima_remision'] : 'Sin remisiones este día', $resumen['entraron'] > 0 ? 'text-ok-600' : 'text-ink-900'],
                ['Salieron a ruta', \App\Support\Rollos::numero($resumen['salieron']), $resumen['guias_en_ruta'].' guías en '.$resumen['zonas_en_ruta'].' '.($resumen['zonas_en_ruta'] === 1 ? 'zona' : 'zonas'), 'text-ink-900'],
                ['Volvieron a bodega', \App\Support\Rollos::numero($resumen['volvieron']), $resumen['visitas_sin_entrega'].' '.($resumen['visitas_sin_entrega'] === 1 ? 'visita sin entrega' : 'visitas sin entrega'), 'text-ink-900'],
                ['En la calle', \App\Support\Rollos::numero($resumen['en_la_calle']), 'En manos de '.$resumen['repartidores_con_rollos'].' '.($resumen['repartidores_con_rollos'] === 1 ? 'repartidor' : 'repartidores'), 'text-ink-900'],
            ] as [$titulo, $cifra, $detalle, $color])
                <div class="p-5">
                    <p class="text-[15px] font-medium text-ink-600">{{ $titulo }}</p>
                    <p x-data x-cifra class="cifra cifra-grande mt-2 text-[34px] leading-none font-extrabold tracking-tight {{ $color }}">{{ $cifra }}</p>
                    <p class="mt-2 text-sm leading-5 text-ink-500">{{ $detalle }}</p>
                </div>
            @endforeach
        </section>

        <p class="-mt-2 text-sm text-ink-500">
            Mínimo de la bodega: {{ \App\Support\Rollos::numero($bodega->stock_minimo) }} rollos ({{ \App\Support\Rollos::enCajas($bodega->stock_minimo) }}).
            @if ($resumen['dias_cobertura'] !== null)
                Al ritmo de los últimos días, alcanza para unos {{ $resumen['dias_cobertura'] }} {{ $resumen['dias_cobertura'] === 1 ? 'día' : 'días' }}.
            @endif
        </p>

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
            <div class="min-w-0 space-y-6">
                {{-- Movimientos --}}
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-5 pb-4">
                        <div>
                            <h2 class="text-lg font-bold tracking-tight text-ink-900">Movimientos {{ $esHoy ? 'de hoy' : 'del '.$dia->isoFormat('D [de] MMMM') }}</h2>
                            <p class="mt-0.5 text-sm text-ink-500">Saldo al abrir el día: {{ \App\Support\Rollos::numero($resumen['saldo_apertura']) }} rollos · cada salida se descuenta al publicar la planeación</p>
                        </div>
                        <x-ui.button variant="outline" icon="download" :href="route('admin.inventario.exportar', ['seccional' => $this->seccionalCodigo, 'desde' => $dia->startOfMonth()->toDateString(), 'hasta' => $dia->toDateString()])">Exportar Excel</x-ui.button>
                    </div>

                    @if ($movimientos->isEmpty())
                        <x-ui.empty icono="warehouse" titulo="Sin movimientos este día" texto="Las entradas se registran con la remisión de Credibanco. Las salidas aparecen solas al publicar la planeación y las devoluciones al cerrar cada ruta." class="border-t border-ink-200" />
                    @else
                        <div class="overflow-x-auto">
                            <table class="tabla">
                                <thead><tr><th>Hora</th><th>Movimiento</th><th>Detalle</th><th class="text-right">Rollos</th><th class="text-right">Saldo</th><th>Registró</th></tr></thead>
                                <tbody x-data x-animar>
                                    @foreach ($movimientos as $movimiento)
                                        <tr wire:key="mov-{{ $movimiento->id }}">
                                            <td class="whitespace-nowrap text-ink-600">{{ $movimiento->created_at->isoFormat('h:mm a') }}</td>
                                            <td><x-ui.badge :color="$movimiento->tipo->color()">{{ $movimiento->tipo->etiqueta() }}</x-ui.badge></td>
                                            <td class="max-w-80">
                                                {{ $movimiento->detalle }}
                                                @if ($movimiento->motivo)
                                                    <span class="mt-0.5 block text-sm text-ink-500">{{ $movimiento->motivo }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right whitespace-nowrap">
                                                <span @class(['font-bold', 'text-ok-600' => $movimiento->cantidad > 0, 'text-ink-900' => $movimiento->cantidad < 0])>{{ $movimiento->cantidad > 0 ? '+' : '−' }}{{ \App\Support\Rollos::numero(abs($movimiento->cantidad)) }}</span>
                                                @if (abs($movimiento->cantidad) >= $porCaja)
                                                    <span class="block text-xs text-ink-500">{{ \App\Support\Rollos::enCajas(abs($movimiento->cantidad)) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-semibold">{{ \App\Support\Rollos::numero($movimiento->saldo) }}</td>
                                            <td class="text-ink-600">{{ $movimiento->registradoPor() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui.card>

                <div class="flex items-start gap-4 rounded-2xl bg-ink-900 p-6 text-white">
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-ink-950"><x-lucide-scale class="size-6" /></span>
                    <div>
                        <h2 class="text-lg font-bold">Cuadre al cierre de ruta</h2>
                        <p class="mt-1 text-[15px] leading-6 text-ink-300">El repartidor declara en la app las cajas y los rollos que devuelve. El sistema los compara con lo que sacó de bodega menos lo que entregó; si no cuadra, queda marcado aquí y en el seguimiento.</p>
                    </div>
                </div>
            </div>

            <aside class="grid content-start gap-6 md:grid-cols-2 2xl:grid-cols-1">
                {{-- Rollos en la calle --}}
                <x-ui.card titulo="Rollos en la calle por repartidor">
                    <ul class="divide-y divide-ink-200" x-data x-animar>
                        @forelse ($enLaCalle as $fila)
                            @php $ruta = $fila['ruta']; @endphp
                            <li class="flex items-center gap-4 py-3.5 first:pt-0 last:pb-0" wire:key="calle-{{ $ruta->id }}">
                                <x-ui.rollo :segmentos="[[$fila['entregados'], 'ok'], [$fila['devueltos'] ?? 0, 'warn']]" :total="max(1, $fila['salieron'])" class="size-12" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[16px] font-bold text-ink-900">{{ $ruta->repartidor->nombre }}</p>
                                    <p class="text-sm text-ink-500">Salieron {{ \App\Support\Rollos::numero($fila['salieron']) }} · entregados {{ \App\Support\Rollos::numero($fila['entregados']) }}</p>
                                    @if ($ruta->estaCerrada() && $ruta->cuadre_ok === false)
                                        <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-bold text-bad-700"><x-lucide-triangle-alert class="size-4" /> No cuadra: se esperaban {{ $ruta->rollos_devueltos_esperados }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    @if ($ruta->estaCerrada())
                                        <x-ui.badge :color="$ruta->cuadre_ok === false ? 'red' : 'green'">Devolvió {{ \App\Support\Rollos::numero((int) $fila['devueltos']) }}</x-ui.badge>
                                    @else
                                        <p class="cifra text-xl font-extrabold text-ink-900">{{ \App\Support\Rollos::numero($fila['en_mano']) }} <span class="text-sm font-medium text-ink-500">en mano</span></p>
                                        @can('inventario.gestionar')
                                            <button type="button" wire:click="abrirDespacho({{ $ruta->id }})" class="enlace text-sm">Ajustar despacho</button>
                                        @endcan
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="text-[15px] text-ink-500">Ningún repartidor tiene rollos en la calle este día.</li>
                        @endforelse
                    </ul>
                </x-ui.card>

                {{-- Existencias por bodega --}}
                <x-ui.card titulo="Existencias por bodega">
                    <ul class="space-y-5">
                        @foreach ($bodegas as $otra)
                            <li class="flex items-center gap-4" wire:key="bodega-{{ $otra->id }}">
                                <x-ui.rollo-papel :nivel="$otra->stock_minimo > 0 ? $otra->saldo / ($otra->stock_minimo * 2) : ($otra->saldo > 0 ? 1 : 0)" :bajo="$otra->estaBajoMinimo()" class="size-14 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="truncate text-[16px] font-bold text-ink-900">{{ $otra->seccional->nombre }}</span>
                                        <span class="cifra text-[16px] font-extrabold text-ink-900">{{ \App\Support\Rollos::numero($otra->saldo) }}</span>
                                    </div>
                                    <p class="text-sm text-ink-500">{{ \App\Support\Rollos::enCajas($otra->saldo) }} · mínimo {{ \App\Support\Rollos::numero($otra->stock_minimo) }}</p>
                                    @if ($otra->estaBajoMinimo())
                                        <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-bold text-warn-700"><x-lucide-triangle-alert class="size-4" /> Bajo el mínimo</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </aside>
        </div>
    </div>

    {{-- Registrar entrada --}}
    <x-ui.modal wire:model="mostrarEntrada" titulo="Registrar entrada" :subtitulo="'Remisión de rollos que llega de Credibanco a '.$bodega->nombre">
        <form wire:submit="registrarEntrada" id="form-entrada" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="Número de remisión" wire:model="remision" placeholder="R-10458" maxlength="40" />
                <x-ui.input label="Fecha de la remisión" type="date" wire:model="remisionFecha" max="{{ today()->toDateString() }}" />
            </div>
            <x-ui.cajas cajas="entradaCajas" sueltos="entradaSueltos" :por-caja="$porCaja" />
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-entrada" icon="check" wire:loading.attr="disabled">Registrar entrada</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    {{-- Ajuste --}}
    <x-ui.modal wire:model="mostrarAjuste" titulo="Ajuste de inventario" subtitulo="Para corregir diferencias de conteo. Queda en la bitácora con tu usuario y el motivo.">
        <form wire:submit="registrarAjuste" id="form-ajuste" class="space-y-5">
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-ink-100 p-1">
                @foreach (['restar' => 'Restar de la bodega', 'sumar' => 'Sumar a la bodega'] as $valor => $nombre)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="ajusteSentido" value="{{ $valor }}" class="peer sr-only" />
                        <span class="block rounded-lg px-3 py-2.5 text-center text-[15px] font-semibold text-ink-600 peer-checked:bg-white peer-checked:text-ink-900 peer-checked:shadow-card">{{ $nombre }}</span>
                    </label>
                @endforeach
            </div>
            <x-ui.cajas cajas="ajusteCajas" sueltos="ajusteSueltos" :por-caja="$porCaja" />
            <x-ui.textarea label="Motivo" wire:model="ajusteMotivo" obligatorio placeholder="Conteo físico del viernes: faltan 2 cajas dañadas por humedad." />
        </form>
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-ajuste" variant="dark" icon="check" wire:loading.attr="disabled">Registrar ajuste</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    {{-- Despacho real --}}
    <x-ui.modal wire:model="mostrarDespacho" :titulo="'Despacho a '.($rutaDespacho?->repartidor->nombre ?? 'repartidor')" subtitulo="Registra lo que realmente sacó de bodega: cajas completas o rollos sueltos.">
        @if ($rutaDespacho)
            <form wire:submit="registrarDespacho" id="form-despacho" class="space-y-5">
                <p class="rounded-xl bg-brand-50 px-4 py-3 text-[15px] leading-6 text-ink-800">
                    Sus guías de hoy suman <strong>{{ \App\Support\Rollos::numero($rollosGuiasDespacho) }} rollos</strong>.
                    Al publicar se descontó esa cantidad; si se llevó cajas completas, anótalo aquí y la diferencia volverá al cerrar la ruta.
                </p>
                <x-ui.cajas cajas="despachoCajas" sueltos="despachoSueltos" :por-caja="$porCaja" />
            </form>
        @endif
        <x-slot:pie>
            <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="form-despacho" icon="check" wire:loading.attr="disabled">Guardar despacho</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
