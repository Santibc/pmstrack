<div>
    <x-ui.page-header titulo="Cargar archivo de Credibanco" subtitulo="Sube el Excel tal como llega. El sistema lo valida, detecta duplicados y asigna las zonas que ya conoce.">
        {{-- Pasos del asistente --}}
        <ol class="flex flex-wrap items-center gap-x-3 gap-y-2 pb-5 text-[16px]">
            @foreach ([1 => 'Subir archivo', 2 => 'Revisar validación', 3 => 'Confirmar carga'] as $numero => $nombre)
                <li class="flex items-center gap-3">
                    <span @class([
                        'cifra inline-flex size-8 items-center justify-center rounded-full text-sm font-bold',
                        'bg-ok-500 text-white' => $paso > $numero || $paso === 3,
                        'bg-brand-500 text-ink-950' => $paso === $numero && $paso !== 3,
                        'bg-ink-100 text-ink-500' => $paso < $numero,
                    ])>
                        @if ($paso > $numero || $paso === 3)
                            <x-lucide-check class="size-4.5" stroke-width="3" />
                        @else
                            {{ $numero }}
                        @endif
                    </span>
                    <span @class([
                        'font-semibold',
                        'text-ok-700' => $paso > $numero || $paso === 3,
                        'text-ink-900' => $paso === $numero && $paso !== 3,
                        'text-ink-500' => $paso < $numero,
                    ])>{{ $nombre }}</span>
                    @unless ($loop->last)
                        <span class="hidden h-px w-10 bg-ink-300 sm:block"></span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </x-ui.page-header>

    <div class="grid gap-6 p-5 sm:p-8 lg:p-10 2xl:grid-cols-[minmax(0,1fr)_minmax(0,400px)]">
        <div class="min-w-0 space-y-6">
            {{-- Paso 1: zona para soltar el archivo --}}
            @if ($paso === 1)
                <div
                    x-data="{ sobre: false, subiendo: false, progreso: 0 }"
                    x-on:dragover.prevent="sobre = true"
                    x-on:dragleave.prevent="sobre = false"
                    x-on:drop.prevent="sobre = false; $refs.archivo.files = $event.dataTransfer.files; $refs.archivo.dispatchEvent(new Event('change', { bubbles: true }))"
                    x-on:livewire-upload-start="subiendo = true; progreso = 0"
                    x-on:livewire-upload-progress="progreso = $event.detail.progress"
                    x-on:livewire-upload-finish="progreso = 100"
                    x-on:livewire-upload-error="subiendo = false"
                    x-on:livewire-upload-cancel="subiendo = false"
                    class="rounded-2xl border-2 border-dashed bg-white px-6 py-14 text-center transition"
                    :class="sobre ? 'border-brand-500 bg-brand-50' : 'border-brand-300'"
                >
                    <input type="file" x-ref="archivo" wire:model="archivo" accept=".xlsx,.xls,.csv" class="sr-only" id="archivo-excel" />

                    <span class="mx-auto mb-5 inline-flex size-16 items-center justify-center rounded-2xl bg-ok-50 text-ok-600">
                        <x-lucide-file-spreadsheet class="size-8" stroke-width="1.7" />
                    </span>

                    <div x-show="! subiendo">
                        <p class="text-xl font-bold text-ink-900">Arrastra aquí el Excel diario de Credibanco</p>
                        <p class="mx-auto mt-2 max-w-lg text-[15px] leading-6 text-ink-500">Tal como llega por correo, sin copiar, pegar ni separar a mano. Acepta .xlsx, .xls y .csv de hasta 10 MB.</p>
                        <label for="archivo-excel" class="mt-6 inline-flex h-12 cursor-pointer items-center gap-2 rounded-xl bg-brand-500 px-6 text-base font-semibold text-ink-950 shadow-card transition hover:bg-brand-600">
                            <x-lucide-upload class="size-5" /> Elegir archivo
                        </label>
                    </div>

                    <div x-show="subiendo" x-cloak class="mx-auto max-w-sm">
                        <p class="text-lg font-bold text-ink-900" x-text="progreso < 100 ? 'Subiendo el archivo…' : 'Validando los servicios…'"></p>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-ink-200">
                            <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width: ${progreso}%`"></div>
                        </div>
                        <p class="mt-3 text-sm text-ink-500">Se revisan duplicados, direcciones, ANS y datos de contacto.</p>
                    </div>

                    @error('archivo')
                        <p class="mt-5 inline-flex items-center gap-2 rounded-xl bg-bad-50 px-4 py-2.5 text-[15px] font-semibold text-bad-700"><x-lucide-circle-alert class="size-[18px]" /> {{ $message }}</p>
                    @enderror
                </div>

                @if ($pendientes->isNotEmpty())
                    <x-ui.card titulo="Cargas sin confirmar" subtitulo="Quedaron en revisión. Puedes retomarlas o descartarlas.">
                        <ul class="divide-y divide-ink-200">
                            @foreach ($pendientes as $pendiente)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="pendiente-{{ $pendiente->id }}">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">{{ $pendiente->nombre_archivo }}</p>
                                        <p class="text-sm text-ink-500">{{ $pendiente->created_at->isoFormat('D MMM · h:mm a') }} · {{ $pendiente->nuevos }} servicios nuevos</p>
                                    </div>
                                    <x-ui.button size="sm" variant="outline" wire:click="abrir({{ $pendiente->id }})">Retomar</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            @endif

            {{-- Pasos 2 y 3: resumen del archivo --}}
            @if ($carga)
                <div @class(['flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 bg-white px-6 py-5', 'border-dashed border-brand-300' => $paso === 2, 'border-ok-100' => $paso === 3])>
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-2xl bg-ok-50 text-ok-600"><x-lucide-file-spreadsheet class="size-7" stroke-width="1.7" /></span>
                        <div class="min-w-0">
                            <p class="truncate text-lg font-bold text-ink-900">{{ $carga->nombre_archivo }}</p>
                            <p class="text-[15px] text-ink-500">
                                {{ $carga->hoja }} · {{ $carga->total_filas }} filas · {{ $carga->columnas_reconocidas }} columnas reconocidas ·
                                {{ $paso === 3 ? 'confirmada '.$carga->confirmada_at->isoFormat('D MMM, h:mm a') : 'subido '.($carga->created_at->isToday() ? 'hoy '.$carga->created_at->isoFormat('h:mm a') : $carga->created_at->isoFormat('D MMM, h:mm a')) }}
                            </p>
                        </div>
                    </div>
                    @if ($paso === 2)
                        <x-ui.button variant="outline" wire:click="cancelar" wire:confirm="Se descarta esta revisión para subir otro archivo. ¿Continuar?">Cambiar archivo</x-ui.button>
                    @else
                        <x-ui.button variant="outline" icon="plus" wire:click="nueva">Cargar otro archivo</x-ui.button>
                    @endif
                </div>

                @if ($paso === 3)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ok-100 bg-ok-50 px-6 py-5">
                        <div class="flex items-start gap-3">
                            <x-lucide-circle-check class="mt-0.5 size-6 shrink-0 text-ok-600" />
                            <div>
                                <p class="text-lg font-bold text-ink-900">Carga confirmada: {{ $carga->nuevos }} {{ $carga->nuevos === 1 ? 'servicio nuevo' : 'servicios nuevos' }} y {{ number_format($carga->total_rollos, 0, ',', '.') }} rollos</p>
                                <p class="mt-0.5 text-[15px] text-ink-600">
                                    @if ($carga->por_zonificar > 0)
                                        {{ $carga->por_zonificar }} quedaron por zonificar: elige su zona una sola vez y la próxima carga se asignan solas.
                                    @else
                                        Todas las direcciones ya eran conocidas: las guías están listas para la planeación.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @if ($carga->por_zonificar > 0)
                                <x-ui.button :href="route('admin.zonificacion')" icon="map" wire:navigate>Zonificar direcciones</x-ui.button>
                            @endif
                            <x-ui.button :href="route('admin.planeacion')" variant="dark" icon="user-round-check" wire:navigate>Ir a planeación</x-ui.button>
                        </div>
                    </div>
                @endif

                {{-- Resumen de la validación --}}
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 2xl:grid-cols-5">
                    <x-ui.stat :valor="$carga->nuevos" etiqueta="Servicios nuevos" :detalle="number_format($carga->total_rollos, 0, ',', '.').' rollos en total'" />
                    <x-ui.stat :valor="$carga->con_zona" etiqueta="Zona automática" detalle="Direcciones ya conocidas" tono="ok" />
                    <x-ui.stat :valor="$carga->por_zonificar" etiqueta="Por zonificar" detalle="Direcciones nuevas" :tono="$carga->por_zonificar > 0 ? 'brand' : 'default'" />
                    <x-ui.stat :valor="$carga->duplicados" etiqueta="Duplicados" detalle="Ya cargados: se omiten" />
                    <x-ui.stat :valor="$carga->urgentes" etiqueta="Urgentes" detalle="ANS de 8 horas" :tono="$carga->urgentes > 0 ? 'bad' : 'default'" />
                </div>

                @if ($carga->errores > 0)
                    <div class="flex items-start gap-3 rounded-2xl border border-bad-100 bg-bad-50 p-5" role="alert">
                        <x-lucide-circle-x class="mt-0.5 size-5 shrink-0 text-bad-600" />
                        <div>
                            <p class="text-[16px] font-bold text-ink-900">{{ $carga->errores }} {{ $carga->errores === 1 ? 'fila no se puede cargar' : 'filas no se pueden cargar' }}</p>
                            <p class="mt-0.5 text-[15px] text-ink-700">Les falta un dato obligatorio. El resto del archivo se carga con normalidad.
                                <button type="button" class="enlace" wire:click="$set('filtro', 'errores')">Ver las filas</button>
                            </p>
                        </div>
                    </div>
                @endif

                @if ($alertas->isNotEmpty())
                    <x-ui.card>
                        <p class="flex items-center gap-2.5 text-lg font-bold text-ink-900">
                            <x-lucide-triangle-alert class="size-[22px] text-warn-600" />
                            {{ $carga->con_alertas }} {{ $carga->con_alertas === 1 ? 'fila con alertas' : 'filas con alertas' }} de datos (se cargan, pero conviene revisarlas)
                        </p>
                        <ul class="mt-3 space-y-2 pl-9 text-[15px] leading-6 text-ink-700">
                            @foreach ($verTodasLasAlertas ? $alertas : $alertas->take(4) as $alerta)
                                <li class="list-disc" wire:key="alerta-{{ $loop->index }}"><strong class="text-ink-900">Fila {{ $alerta['fila'] }}:</strong> {{ $alerta['mensaje'] }}</li>
                            @endforeach
                        </ul>
                        @if ($alertas->count() > 4)
                            <button type="button" class="enlace mt-3 ml-9 text-[15px]" wire:click="$toggle('verTodasLasAlertas')">
                                {{ $verTodasLasAlertas ? 'Ver menos' : 'Ver las '.$alertas->count().' alertas' }}
                            </button>
                        @endif
                    </x-ui.card>
                @endif

                {{-- Vista previa --}}
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                        <h2 class="text-lg font-bold text-ink-900">Vista previa del archivo</h2>
                        <p class="text-[15px] text-ink-500">Mostrando {{ $filas->count() }} de {{ $filas->total() }} filas</p>
                    </div>

                    <div class="flex flex-wrap gap-2 px-6 pb-4">
                        @foreach (['todas' => 'Todas', 'alertas' => 'Con alertas', 'zonificar' => 'Por zonificar', 'duplicados' => 'Duplicados', 'errores' => 'Con error'] as $clave => $nombre)
                            @if ($clave === 'todas' || $conteos[$clave] > 0)
                                <button type="button" wire:click="$set('filtro', '{{ $clave }}')" @class([
                                    'rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                                    'border-ink-900 bg-ink-900 text-white' => $filtro === $clave,
                                    'border-ink-300 bg-white text-ink-700 hover:border-ink-400' => $filtro !== $clave,
                                ])>{{ $nombre }} · {{ $conteos[$clave] }}</button>
                            @endif
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="tabla">
                            <thead>
                                <tr><th>Fila</th><th>Servicio</th><th>Comercio</th><th>Dirección</th><th class="text-right">Rollos</th><th>ANS</th><th>Resultado</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($filas as $fila)
                                    @php
                                        $datos = $fila->datos;
                                        $mensaje = collect($fila->alertas)->pluck('mensaje')->map(fn ($m) => ucfirst($m))->implode(' ');
                                    @endphp
                                    <tr wire:key="fila-{{ $fila->id }}" @class(['bg-ink-50/60 text-ink-500' => $fila->resultado !== \App\Enums\ResultadoFila::Nuevo])>
                                        <td class="text-ink-500">{{ $fila->numero_fila }}</td>
                                        <td class="font-bold text-ink-900">{{ $fila->servicio ?? '—' }}</td>
                                        <td class="max-w-56">{{ $datos['nombre_comercio'] ?? '—' }}</td>
                                        <td class="max-w-64">
                                            {{ $datos['direccion'] ?? '—' }}
                                        </td>
                                        <td class="text-right">{{ $datos['rollos'] ?? '—' }}</td>
                                        <td>
                                            @if ($datos['es_urgente'] ?? false)
                                                <x-ui.badge color="red" solid class="tracking-wide uppercase">{{ $datos['ans_horas'] }} h · Urgente</x-ui.badge>
                                            @elseif (isset($datos['ans_horas']))
                                                {{ $datos['ans_horas'] }} h
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td title="{{ $mensaje }}">
                                            @if ($fila->resultado === \App\Enums\ResultadoFila::Error)
                                                <x-ui.badge color="red">No se carga</x-ui.badge>
                                                <span class="mt-1 block max-w-64 text-xs leading-4 text-bad-700">{{ $mensaje }}</span>
                                            @elseif ($fila->resultado === \App\Enums\ResultadoFila::Duplicado)
                                                <x-ui.badge color="gray">Duplicado · se omite</x-ui.badge>
                                            @elseif ($fila->tieneAlerta(\App\Services\Cargas\AnalizadorFilas::ALERTA_CIUDAD))
                                                <x-ui.badge color="amber">Revisar · ciudad</x-ui.badge>
                                            @elseif ($fila->tieneAlerta(\App\Services\Cargas\AnalizadorFilas::ALERTA_RURAL))
                                                <x-ui.badge color="amber">Revisar · zona rural</x-ui.badge>
                                            @elseif ($fila->zona)
                                                <x-ui.badge color="green">Zona {{ $fila->zona->nombre_completo }}</x-ui.badge>
                                            @else
                                                <x-ui.badge color="orange">Por zonificar</x-ui.badge>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-8 text-center text-ink-500">No hay filas con este filtro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($filas->hasPages())
                        <div class="border-t border-ink-200 px-6 py-4">{{ $filas->links('components.ui.paginacion') }}</div>
                    @endif
                </x-ui.card>

                @if ($paso === 2)
                    <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-ink-200 bg-white/95 p-4 shadow-pop backdrop-blur">
                        <p class="mr-auto hidden text-[15px] text-ink-500 md:block">Al confirmar se crean las guías. Los duplicados y las filas con error no se cargan.</p>
                        <x-ui.button variant="outline" size="lg" wire:click="cancelar" wire:confirm="Se descarta esta revisión. ¿Continuar?">Cancelar</x-ui.button>
                        <x-ui.button size="lg" icon="check" wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar" :disabled="$carga->nuevos === 0">
                            <span wire:loading.remove wire:target="confirmar">Confirmar carga de {{ $carga->nuevos }} {{ $carga->nuevos === 1 ? 'servicio' : 'servicios' }}</span>
                            <span wire:loading wire:target="confirmar">Creando las guías…</span>
                        </x-ui.button>
                    </div>
                @endif
            @endif
        </div>

        {{-- Columna lateral --}}
        <aside class="grid content-start gap-6 md:grid-cols-2 2xl:grid-cols-1">
            <x-ui.card titulo="Columnas reconocidas">
                <p class="text-[15px] leading-6 text-ink-500">El formato es el mismo que Credibanco envía hoy. No tienen que cambiar nada.</p>
                <ul class="mt-4 space-y-2.5 text-[15px] text-ink-800">
                    @foreach ([
                        'SERVICIO → número de servicio (llave única)',
                        'CODIGO_UNICO → comercio',
                        'DIRECCION, CIUDAD, SECCIONAL',
                        'PAPELERIA_ENTREGAR → rollos',
                        'HORARIO_DE_ATENCION → ANS',
                        'OBSERVACIONES → contacto y horario',
                    ] as $linea)
                        <li class="flex items-start gap-2.5"><x-lucide-check class="mt-0.5 size-[18px] shrink-0 text-ok-600" stroke-width="2.5" /> <span>{{ $linea }}</span></li>
                    @endforeach
                    <li class="flex items-start gap-2.5 text-ink-500">
                        <x-lucide-check class="mt-0.5 size-[18px] shrink-0 text-ok-600" stroke-width="2.5" />
                        <span>
                            @if ($carga)
                                …y {{ max(0, $carga->columnas_reconocidas - 8) }} columnas más ({{ $carga->columnas_reconocidas }} de {{ count($columnas) }})
                            @else
                                …y 8 columnas más ({{ count($columnas) }} en total)
                            @endif
                        </span>
                    </li>
                </ul>
            </x-ui.card>

            <x-ui.card titulo="Historial de cargas">
                @forelse ($historial as $anterior)
                    <button type="button" wire:click="abrir({{ $anterior->id }})" wire:key="historial-{{ $anterior->id }}" class="-mx-2 flex w-[calc(100%+1rem)] items-center justify-between gap-3 rounded-lg px-2 py-2 text-left text-[15px] hover:bg-ink-50">
                        <span class="min-w-0">
                            <span class="block truncate text-ink-900">{{ $anterior->confirmada_at->isoFormat('D MMM · h:mm a') }}</span>
                            <span class="block truncate text-sm text-ink-500">{{ $anterior->user->name }}</span>
                        </span>
                        <span class="shrink-0 text-ink-600">{{ $anterior->nuevos }} servicios</span>
                    </button>
                @empty
                    <p class="text-[15px] text-ink-500">Aún no hay cargas confirmadas. La primera aparecerá aquí.</p>
                @endforelse
            </x-ui.card>
        </aside>
    </div>
</div>
