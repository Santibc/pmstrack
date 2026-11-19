@php
    $semaforo = $guia->semaforo();
    $foto = $visita?->evidencia(\App\Enums\TipoEvidencia::Foto) ?? $visita?->evidencia(\App\Enums\TipoEvidencia::Fachada);
    $firma = $visita?->evidencia(\App\Enums\TipoEvidencia::Firma);
    $entregada = $guia->estado === \App\Enums\EstadoGuia::Entregada;
@endphp

<div>
    <x-ui.page-header :titulo="'Servicio '.$guia->servicio">
        <x-slot:migas>
            <a href="{{ route($area.'.guias') }}" wire:navigate class="hover:text-ink-900">{{ $portal ? 'Servicios e histórico' : 'Guías e histórico' }}</a>
            <x-lucide-chevron-right class="size-4" />
            <span class="font-semibold text-ink-900">Servicio {{ $guia->servicio }}</span>
        </x-slot:migas>

        <x-slot:insignias>
            <x-guia.estado :guia="$guia" size="lg" :modo="$portal ? 'servicio' : 'guia'" />
            <x-ui.badge color="gray" size="lg">Guía PMS {{ $guia->numero_guia }}</x-ui.badge>
            <x-ui.badge :color="$semaforo->color()" size="lg">
                ANS {{ $guia->ans_horas }} h ·
                @if ($semaforo === \App\Enums\SemaforoAns::Cumplido)
                    cumplido en {{ \App\Support\Duracion::corta($guia->minutosHastaEntrega()) }}
                @elseif ($semaforo === \App\Enums\SemaforoAns::Incumplido)
                    entregado en {{ \App\Support\Duracion::corta($guia->minutosHastaEntrega()) }}
                @else
                    {{ str($guia->semaforoTexto())->lower() }}
                @endif
            </x-ui.badge>
            @if ($guia->es_urgente && ! $guia->estado->esFinal())
                <x-ui.badge color="red" solid size="lg">Urgente</x-ui.badge>
            @endif
        </x-slot:insignias>

        <div class="flex flex-wrap items-center gap-3 pb-6">
            <span x-data="copiar(@js(route($area.'.guias.show', $guia)))">
                <x-ui.button variant="outline" icon="share-2" x-on:click="copiar()"><span x-text="copiado ? 'Enlace copiado' : 'Compartir enlace'">Compartir enlace</span></x-ui.button>
            </span>
            @if ($entregada)
                <x-ui.button variant="dark" icon="download" :href="route($area.'.guias.comprobante', $guia)" target="_blank">Comprobante de entrega PDF</x-ui.button>
            @endif

            @if ($puede['reprogramar'])
                <x-ui.button variant="outline" icon="calendar-clock" wire:click="abrirReprogramar">Reprogramar</x-ui.button>
            @endif
            @if ($puede['devolver'])
                <x-ui.button variant="outline" icon="undo-2" wire:click="$set('mostrarDevolver', true)">Devolver a Credibanco</x-ui.button>
            @endif
            @if ($puede['anular'])
                <x-ui.button variant="danger-outline" icon="ban" wire:click="$set('mostrarAnular', true)">Anular</x-ui.button>
            @endif
        </div>
    </x-ui.page-header>

    <div class="grid gap-6 p-5 sm:p-8 lg:p-10 2xl:grid-cols-[minmax(0,1fr)_minmax(0,460px)]">
        <div class="min-w-0 space-y-6">
            <x-ui.card>
                <x-guia.pasos :pasos="$pasos" />
            </x-ui.card>

            @if ($gestiona && $guia->estado === \App\Enums\EstadoGuia::NoEntregada && $guia->intentos >= $maxIntentos)
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-warn-100 bg-warn-50 px-5 py-4 text-[15px] text-ink-900" role="alert">
                    <x-lucide-triangle-alert class="size-5 shrink-0 text-warn-700" />
                    <span><strong>Llegó a {{ $maxIntentos }} intentos fallidos.</strong> Decide si se reprograma de nuevo o se devuelve a Credibanco.</span>
                </div>
            @endif

            {{-- Evidencia --}}
            @if ($visita)
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                        <h2 class="text-xl font-bold tracking-tight text-ink-900">{{ $entregada ? 'Evidencia de la entrega' : 'Registro de la visita' }}</h2>
                        <p class="flex items-center gap-2 text-[15px] text-ink-600"><x-lucide-shield-check class="size-[18px] text-ok-600" /> Capturada en la app, no editable</p>
                    </div>

                    <div class="grid gap-6 px-6 pb-6 md:grid-cols-2">
                        <figure>
                            @if ($foto)
                                <a href="{{ $foto->url() }}" target="_blank" class="block overflow-hidden rounded-2xl border border-ink-200 bg-ink-100">
                                    <img src="{{ $foto->url() }}" alt="Foto de la {{ $entregada ? 'entrega' : 'fachada' }} del servicio {{ $guia->servicio }}" class="aspect-[4/3] w-full object-cover transition duration-300 hover:scale-[1.02]" loading="lazy" />
                                </a>
                                <figcaption class="mt-3 text-[15px] leading-6 text-ink-600">Foto tomada desde la app con marca de fecha, hora, GPS y número de guía. Huella SHA-256 <span class="font-mono text-xs text-ink-500" title="{{ $foto->sha256 }}">{{ substr($foto->sha256, 0, 12) }}…</span></figcaption>
                            @else
                                <div class="flex aspect-[4/3] w-full flex-col items-center justify-center rounded-2xl border border-dashed border-ink-300 bg-ink-50 text-ink-500">
                                    <x-lucide-camera class="mb-2 size-8" stroke-width="1.6" />
                                    <p class="text-[15px]">Esta visita no tiene foto</p>
                                </div>
                            @endif
                        </figure>

                        <div>
                            @if ($entregada)
                                <div class="flex h-36 items-center justify-center rounded-2xl border border-ink-200 bg-white p-3">
                                    @if ($firma)
                                        <img src="{{ $firma->url() }}" alt="Firma de quien recibe" class="max-h-full max-w-full object-contain" loading="lazy" />
                                    @else
                                        <span class="text-[15px] text-ink-500">Sin firma</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-[15px] text-ink-600">Firma de quien recibe</p>
                            @endif

                            <dl class="mt-4">
                                @if ($entregada)
                                    <x-ui.dato etiqueta="Recibió" fuerte>{{ $visita->recibe_nombre }}</x-ui.dato>
                                    <x-ui.dato etiqueta="Cédula">{{ number_format((int) $visita->recibe_cedula, 0, ',', '.') }}</x-ui.dato>
                                    @if ($visita->recibe_telefono)
                                        <x-ui.dato etiqueta="Teléfono">{{ \App\Support\Telefono::formatear($visita->recibe_telefono) }}</x-ui.dato>
                                    @endif
                                    <x-ui.dato etiqueta="Rollos" fuerte>{{ $visita->rollos_entregados }} entregados de {{ $visita->rollos_solicitados }}</x-ui.dato>
                                @else
                                    <x-ui.dato etiqueta="Resultado" fuerte>{{ $visita->resultado->etiqueta() }}</x-ui.dato>
                                    <x-ui.dato etiqueta="Motivo">{{ $visita->causal?->nombre ?? '—' }}</x-ui.dato>
                                    <x-ui.dato etiqueta="Observación">{{ $visita->observacion }}</x-ui.dato>
                                    <x-ui.dato etiqueta="Intento">{{ $visita->numero_intento }} de {{ $maxIntentos }}</x-ui.dato>
                                @endif
                                <x-ui.dato etiqueta="Fecha y hora">{{ $visita->registrada_at->isoFormat('D MMM YYYY, h:mm a') }}</x-ui.dato>
                                <x-ui.dato etiqueta="Repartidor">{{ $portal ? $visita->repartidor->nombre_corto : $visita->repartidor->nombre }}</x-ui.dato>
                                @if ($entregada)
                                    <x-ui.dato etiqueta="Autorización">{{ $visita->autoriza_datos ? 'Aceptada (Ley 1581)' : 'No registrada' }}</x-ui.dato>
                                @endif
                            </dl>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Ubicación --}}
                <x-ui.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                        <h2 class="text-xl font-bold tracking-tight text-ink-900">Ubicación {{ $entregada ? 'de la entrega' : 'del registro' }}</h2>
                        @if ($visita->tieneUbicacion())
                            <x-ui.badge color="green" size="lg">Registrada con GPS · precisión {{ round((float) $visita->precision_m) }} m</x-ui.badge>
                        @else
                            <x-ui.badge color="gray" size="lg">Sin GPS en este registro</x-ui.badge>
                        @endif
                    </div>

                    @if ($visita->tieneUbicacion())
                        <div class="px-6" wire:ignore>
                            <div x-data="mapaEntrega({ latitud: {{ $visita->latitud }}, longitud: {{ $visita->longitud }}, precision: {{ (float) ($visita->precision_m ?? 0) }} })" class="relative">
                                <div x-ref="mapa" class="h-80 w-full rounded-2xl border border-ink-200"></div>
                                <p class="absolute bottom-4 left-4 z-[500] flex items-center gap-2.5 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-[15px] font-medium shadow-card">
                                    <span class="size-3 rounded-full bg-brand-500"></span> Punto donde se registró {{ $entregada ? 'la entrega' : 'la visita' }}
                                </p>
                            </div>
                        </div>
                        <p class="px-6 pt-4 pb-6 text-[15px] text-ink-600">
                            Coordenadas {{ number_format($visita->latitud, 4, '.', '') }}, {{ number_format($visita->longitud, 4, '.', '') }} · precisión del GPS {{ round((float) $visita->precision_m) }} m
                            @if ($visita->dispositivo) · {{ $visita->dispositivo }} @endif
                            @if ($visita->app_version) · app PMS Track {{ $visita->app_version }} @endif
                        </p>
                    @else
                        <p class="px-6 pb-6 text-[15px] leading-6 text-ink-600">El celular no tenía señal de GPS o permiso de ubicación al momento del registro. La hora y la evidencia sí quedaron guardadas.</p>
                    @endif
                </x-ui.card>
            @else
                <x-ui.card :padding="false">
                    <x-ui.empty icono="package" titulo="Aún no hay visitas registradas" :texto="'Cuando el repartidor visite el comercio, aquí verás la foto, la firma, la ubicación y la hora del registro. Estado actual: '.str($guia->estado->etiqueta())->lower().'.'" />
                </x-ui.card>
            @endif

            {{-- Historial de visitas --}}
            @if ($guia->visitas->count() > 1 || ($gestiona && $guia->visitas->isNotEmpty()))
                <x-ui.card titulo="Visitas registradas" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="tabla mt-3">
                            <thead><tr><th>Intento</th><th>Fecha</th><th>Resultado</th><th>Motivo u observación</th><th>Repartidor</th>@if ($gestiona)<th class="w-0"></th>@endif</tr></thead>
                            <tbody>
                                @foreach ($guia->visitas as $registro)
                                    <tr wire:key="visita-{{ $registro->id }}" @class(['text-ink-400 line-through decoration-ink-300' => $registro->estaAnulada()])>
                                        <td>{{ $registro->numero_intento }}</td>
                                        <td class="whitespace-nowrap">{{ $registro->registrada_at->isoFormat('D MMM · h:mm a') }}</td>
                                        <td><x-ui.badge :color="$registro->estaAnulada() ? 'gray' : $registro->resultado->color()">{{ $registro->resultado->etiqueta() }}{{ $registro->estaAnulada() ? ' · anulada' : '' }}</x-ui.badge></td>
                                        <td class="max-w-72">{{ $registro->estaAnulada() ? $registro->anulacion_motivo : trim(($registro->causal?->nombre ?? '').' '.($registro->observacion ?? '')) }}</td>
                                        <td>{{ $portal ? $registro->repartidor->nombre_corto : $registro->repartidor->nombre }}</td>
                                        @if ($gestiona)
                                            <td>
                                                @unless ($registro->estaAnulada())
                                                    <x-ui.button size="sm" variant="ghost" wire:click="abrirAnularVisita({{ $registro->id }})">Anular visita</x-ui.button>
                                                @endunless
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <aside class="grid content-start gap-6 lg:grid-cols-2 2xl:grid-cols-1">
            <x-ui.card titulo="Datos del comercio">
                <dl>
                    <x-ui.dato etiqueta="Comercio" fuerte>{{ $guia->nombre_comercio }}</x-ui.dato>
                    @if ($guia->cadena)
                        <x-ui.dato etiqueta="Cadena">{{ $guia->cadena }}</x-ui.dato>
                    @endif
                    <x-ui.dato etiqueta="Código único">{{ $guia->comercio->codigo_unico }}</x-ui.dato>
                    <x-ui.dato etiqueta="Dirección">{{ $guia->direccionCompleta() }}</x-ui.dato>
                    <x-ui.dato etiqueta="Ciudad">{{ $guia->ciudad->nombre }} · Seccional {{ $guia->seccional->nombre }}</x-ui.dato>
                    @if ($guia->horario_comercio)
                        <x-ui.dato etiqueta="Horario">{{ $guia->horario_comercio }}</x-ui.dato>
                    @endif
                    <x-ui.dato etiqueta="Zona PMS">{{ $guia->zona?->nombre_completo ?? 'Por zonificar' }}</x-ui.dato>
                    <x-ui.dato etiqueta="Rollos solicitados">{{ $guia->rollos_solicitados }}</x-ui.dato>
                    @if (! $portal && ($guia->contacto_nombre || $guia->telefonoDeContacto()))
                        <x-ui.dato etiqueta="Contacto">{{ $guia->contacto_nombre }} {{ \App\Support\Telefono::formatear($guia->telefonoDeContacto()) }}</x-ui.dato>
                    @endif
                </dl>

                @if ($guia->observaciones)
                    <div class="mt-4 rounded-xl bg-ink-50 p-4">
                        <p class="text-[15px] font-bold text-ink-900">Observación de Credibanco</p>
                        <p class="mt-1.5 text-[15px] leading-6 text-ink-600">{{ $guia->observaciones }}</p>
                    </div>
                @endif

                @if (! $portal && $guia->alertas)
                    <div class="mt-3 rounded-xl bg-warn-50 p-4">
                        <p class="flex items-center gap-2 text-[15px] font-bold text-warn-700"><x-lucide-triangle-alert class="size-4" /> Alertas de la carga</p>
                        <ul class="mt-1.5 space-y-1 text-sm leading-5 text-ink-700">
                            @foreach ($guia->alertas as $alerta)
                                <li>{{ ucfirst($alerta['mensaje']) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card titulo="Trazabilidad completa">
                <ol class="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-[6px] before:w-0.5 before:bg-ink-200">
                    @foreach ($guia->eventos as $evento)
                        @php
                            $punto = ['gray' => 'bg-ink-300', 'purple' => 'bg-plum-500', 'blue' => 'bg-info-500', 'green' => 'bg-ok-500', 'amber' => 'bg-warn-500', 'red' => 'bg-bad-500'][$evento->tipo->color()];
                        @endphp
                        <li class="relative flex gap-4" wire:key="evento-{{ $evento->id }}">
                            <span @class(['relative z-10 mt-1.5 size-3.5 shrink-0 rounded-full ring-4 ring-white', $punto])></span>
                            <div class="min-w-0">
                                <p class="text-[16px] leading-6 font-bold text-ink-900">{{ $evento->titulo }}</p>
                                <p class="text-sm text-ink-500">
                                    {{ $evento->ocurrido_at->isoFormat('D MMM · h:mm a') }}
                                    @if ($evento->detalle) · {{ $evento->detalle }} @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </aside>
    </div>

    @if ($gestiona)
        <x-ui.modal wire:model="mostrarReprogramar" titulo="Reprogramar la guía" subtitulo="Volverá a la planeación del día que elijas.">
            <form wire:submit="reprogramar" id="form-reprogramar" class="space-y-4">
                <x-ui.input label="Fecha" type="date" wire:model="reprogramarFecha" min="{{ today()->toDateString() }}" />
                <x-ui.textarea label="Nota" wire:model="reprogramarNota" placeholder="El comercio pidió que vuelvan el jueves en la mañana." />
            </form>
            <x-slot:pie>
                <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-reprogramar" icon="check">Reprogramar</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal wire:model="mostrarAnular" titulo="Anular la guía" subtitulo="Es un estado final: la guía no vuelve a planearse.">
            <form wire:submit="anular" id="form-anular" class="space-y-4">
                <x-ui.select label="Motivo" wire:model="anularCausal">
                    <option value="">Elige un motivo</option>
                    @foreach ($causalesAnulacion as $causal)
                        <option value="{{ $causal->id }}">{{ $causal->nombre }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.textarea label="Observación" wire:model="anularObservacion" obligatorio />
            </form>
            <x-slot:pie>
                <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-anular" variant="danger" icon="ban">Anular guía</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal wire:model="mostrarDevolver" titulo="Devolver a Credibanco" :subtitulo="'Tras '.$guia->intentos.' '.($guia->intentos === 1 ? 'intento' : 'intentos').' sin poder entregar. Es un estado final.'">
            <form wire:submit="devolver" id="form-devolver">
                <x-ui.textarea label="Observación" wire:model="devolverObservacion" obligatorio placeholder="Tres visitas con el local cerrado; el teléfono no contesta." />
            </form>
            <x-slot:pie>
                <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-devolver" variant="dark" icon="undo-2">Devolver a Credibanco</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal wire:model="mostrarAnularVisita" titulo="Anular la visita" subtitulo="La evidencia no se borra: la visita queda marcada como anulada y la guía vuelve a quedar pendiente.">
            <form wire:submit="anularVisita" id="form-anular-visita">
                <x-ui.textarea label="Justificación" wire:model="anularVisitaMotivo" obligatorio placeholder="El repartidor registró la entrega en la guía equivocada." />
            </form>
            <x-slot:pie>
                <x-ui.button variant="outline" x-on:click="abierto = false">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="form-anular-visita" variant="danger">Anular visita</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
