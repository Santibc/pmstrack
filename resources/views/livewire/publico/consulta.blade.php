<div class="flex min-h-screen flex-col">
    {{-- Encabezado --}}
    <header class="border-b border-ink-200 bg-white">
        <div class="mx-auto flex max-w-[1180px] items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <a href="{{ route('consulta') }}" wire:navigate class="flex items-center gap-4">
                <x-logo alto="h-12 sm:h-14" />
                <span class="hidden border-l border-ink-200 pl-4 sm:block">
                    <span class="block text-lg leading-tight font-extrabold tracking-tight text-ink-900">PMS Track</span>
                    <span class="block text-sm text-ink-500">Consulta de entregas de papelería</span>
                </span>
            </a>

            @auth
                <x-ui.button :href="auth()->user()->rutaInicio()" variant="outline" icon="layout-dashboard" wire:navigate>Ir a mi panel</x-ui.button>
            @else
                <x-ui.button :href="route('login')" variant="outline" icon="lock" wire:navigate>
                    <span class="hidden sm:inline">Ingreso usuarios Credibanco</span><span class="sm:hidden">Ingresar</span>
                </x-ui.button>
            @endauth
        </div>
    </header>

    {{-- Buscador --}}
    <section class="relative overflow-hidden border-b border-ink-200 bg-white">
        <x-anillos class="pointer-events-none absolute top-1/2 right-[-90px] hidden size-[380px] -translate-y-1/2 lg:block xl:right-[8%]" />

        <div class="relative mx-auto max-w-[1180px] px-5 py-12 sm:px-8 sm:py-16">
            <x-ui.badge color="orange" icon="shield-check" size="lg">Consulta pública · no requiere iniciar sesión</x-ui.badge>

            <h1 class="mt-6 max-w-[860px] text-[34px] leading-[1.1] font-extrabold tracking-tight text-ink-900 sm:text-[46px]">
                Consulta en qué va la entrega de rollos de un comercio
            </h1>
            <p class="mt-5 max-w-[820px] text-lg leading-8 text-ink-600">
                Escribe el número de servicio de Credibanco y el código único del comercio. Se piden los dos datos para proteger la información de cada comercio.
            </p>

            <form wire:submit="consultar" class="mt-9 max-w-[900px]" novalidate>
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <label for="servicio" class="mb-2 block text-[15px] font-semibold text-ink-800">Número de servicio</label>
                        <div class="relative">
                            <x-lucide-search class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-500" />
                            <input id="servicio" type="text" inputmode="numeric" wire:model="servicio" autocomplete="off" autofocus placeholder="3937452"
                                @class(['campo h-14 rounded-xl pl-12 text-xl font-bold tracking-tight placeholder:font-medium', 'campo-error' => $errors->has('servicio')]) />
                        </div>
                    </div>
                    <div>
                        <label for="codigo-unico" class="mb-2 block text-[15px] font-semibold text-ink-800">Código único del comercio</label>
                        <div class="relative">
                            <x-lucide-store class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-500" />
                            <input id="codigo-unico" type="text" inputmode="numeric" wire:model="codigoUnico" autocomplete="off" placeholder="18452276"
                                @class(['campo h-14 rounded-xl pl-12 text-xl font-bold tracking-tight placeholder:font-medium', 'campo-error' => $errors->has('codigoUnico')]) />
                        </div>
                    </div>
                    <x-ui.button type="submit" variant="dark" size="lg" class="h-14 px-8 text-lg" icon-after="arrow-right" wire:loading.attr="disabled" wire:target="consultar">
                        <span wire:loading.remove wire:target="consultar">Consultar</span>
                        <span wire:loading wire:target="consultar">Buscando…</span>
                    </x-ui.button>
                </div>

                {{-- Campo trampa para robots: oculto para las personas y los lectores de pantalla. --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>No llenar <input type="text" wire:model="sitio" tabindex="-1" autocomplete="off" /></label>
                </div>

                @if ($llaveCaptcha)
                    <div
                        class="mt-5"
                        wire:ignore
                        x-data="{
                            widget: null,
                            montar() {
                                if (! window.turnstile) { return setTimeout(() => this.montar(), 300); }
                                this.widget = window.turnstile.render(this.$refs.caja, {
                                    sitekey: @js($llaveCaptcha),
                                    language: 'es',
                                    callback: (token) => $wire.set('captcha', token, false),
                                });
                            },
                        }"
                        x-init="montar()"
                        x-on:captcha-reiniciar.window="widget !== null && window.turnstile.reset(widget)"
                    >
                        <div x-ref="caja"></div>
                    </div>
                    @assets
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
                    @endassets
                @endif

                @if ($errors->any())
                    <p class="mt-4 flex items-center gap-2 text-[15px] font-semibold text-bad-700"><x-lucide-circle-alert class="size-[18px]" /> {{ $errors->first() }}</p>
                @endif

                <p class="mt-4 text-[15px] text-ink-500">Ambos datos están en el Excel de Credibanco y en la solicitud del comercio. Protegido contra consultas automáticas.</p>
            </form>
        </div>
    </section>

    <main class="flex-1">
        <div class="mx-auto max-w-[1180px] px-5 py-10 sm:px-8">
            @if ($mensaje)
                <div class="mb-8 flex items-start gap-3 rounded-2xl border border-warn-100 bg-warn-50 p-5" role="alert">
                    <x-lucide-triangle-alert class="mt-0.5 size-5 shrink-0 text-warn-700" />
                    <p class="text-[16px] leading-6 font-medium text-ink-900">{{ $mensaje }}</p>
                </div>
            @endif

            @if ($guia)
                @php
                    $visita = $guia->ultimaVisita;
                    $entregada = $guia->estado === \App\Enums\EstadoGuia::Entregada;
                    $semaforo = $guia->semaforo();
                    $minutos = $guia->minutosHastaEntrega() ?? (int) $guia->fecha_registro->diffInMinutes(now());
                @endphp

                <article class="overflow-hidden rounded-3xl border border-ink-200 bg-white shadow-card" wire:key="resultado-{{ $guia->id }}">
                    {{-- Cabecera del servicio --}}
                    <header class="flex flex-wrap items-start justify-between gap-6 border-b border-ink-200 px-6 py-7 sm:px-8">
                        <div>
                            <p class="text-[15px] text-ink-500">Servicio Credibanco</p>
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <p class="cifra text-[38px] leading-none font-extrabold tracking-tight text-ink-900">{{ $guia->servicio }}</p>
                                <x-guia.estado :guia="$guia" size="lg" />
                            </div>
                            <p class="mt-3 text-[16px] text-ink-600">Guía PMS {{ $guia->numero_guia }} · {{ $guia->nombre_comercio }} · {{ $guia->ciudad->nombre }}</p>
                        </div>

                        <div class="sm:text-right">
                            <p class="text-[15px] text-ink-500">{{ $entregada ? 'Tiempo desde la solicitud' : 'Tiempo transcurrido' }}</p>
                            <p class="cifra mt-1 text-[30px] leading-none font-extrabold tracking-tight text-ink-900">{{ \App\Support\Duracion::corta($minutos) }}</p>
                            <p @class([
                                'mt-2.5 inline-flex items-center gap-1.5 text-[15px] font-bold',
                                'text-ok-700' => in_array($semaforo, [\App\Enums\SemaforoAns::Cumplido, \App\Enums\SemaforoAns::EnCurso], true),
                                'text-warn-700' => $semaforo === \App\Enums\SemaforoAns::EnRiesgo,
                                'text-bad-700' => in_array($semaforo, [\App\Enums\SemaforoAns::Vencido, \App\Enums\SemaforoAns::Incumplido], true),
                                'text-ink-500' => $semaforo === \App\Enums\SemaforoAns::NoAplica,
                            ])>
                                <x-lucide-shield-check class="size-[18px]" />
                                @switch($semaforo)
                                    @case(\App\Enums\SemaforoAns::Cumplido) Cumple el ANS de {{ $guia->ans_horas }} horas @break
                                    @case(\App\Enums\SemaforoAns::Incumplido) Superó el ANS de {{ $guia->ans_horas }} horas @break
                                    @case(\App\Enums\SemaforoAns::Vencido) ANS de {{ $guia->ans_horas }} horas vencido @break
                                    @case(\App\Enums\SemaforoAns::EnRiesgo) ANS de {{ $guia->ans_horas }} horas por vencer @break
                                    @case(\App\Enums\SemaforoAns::EnCurso) Dentro del ANS de {{ $guia->ans_horas }} horas @break
                                    @default ANS de {{ $guia->ans_horas }} horas · no aplica
                                @endswitch
                            </p>
                        </div>
                    </header>

                    <div class="border-b border-ink-200 px-6 py-8 sm:px-8">
                        <x-guia.pasos :pasos="$pasos" />
                    </div>

                    {{-- Datos del servicio y de la entrega --}}
                    <div class="grid divide-y divide-ink-200 border-b border-ink-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                        <section class="px-6 py-7 sm:px-8">
                            <h2 class="mb-3 text-lg font-bold text-ink-900">Datos del servicio</h2>
                            <dl>
                                <x-ui.dato etiqueta="Comercio" fuerte>{{ $guia->nombre_comercio }}</x-ui.dato>
                                <x-ui.dato etiqueta="Código único">{{ $guia->comercio->codigo_unico }}</x-ui.dato>
                                <x-ui.dato etiqueta="Dirección">{{ $guia->direccionCompleta() }}</x-ui.dato>
                                <x-ui.dato etiqueta="Ciudad">{{ $guia->ciudad->nombre }} · Seccional {{ $guia->seccional->nombre }}</x-ui.dato>
                                <x-ui.dato etiqueta="Solicitud registrada">{{ $guia->fecha_registro->isoFormat('ddd D MMM YYYY · h:mm a') }}</x-ui.dato>
                                <x-ui.dato etiqueta="Rollos solicitados">{{ $guia->rollos_solicitados }} rollos</x-ui.dato>
                            </dl>
                        </section>

                        <section class="px-6 py-7 sm:px-8">
                            <h2 class="mb-3 text-lg font-bold text-ink-900">{{ $entregada ? 'Detalle de la entrega' : 'Estado de la entrega' }}</h2>
                            <dl>
                                @if ($entregada && $visita)
                                    <x-ui.dato etiqueta="Fecha y hora" fuerte>{{ $guia->entregada_at->isoFormat('ddd D MMM YYYY · h:mm a') }}</x-ui.dato>
                                    <x-ui.dato etiqueta="Rollos entregados">{{ $visita->rollos_entregados }} de {{ $visita->rollos_solicitados }}</x-ui.dato>
                                    {{-- Datos personales enmascarados (Ley 1581 de 2012). --}}
                                    <x-ui.dato etiqueta="Recibió">{{ $visita->recibeNombreEnmascarado() }} · C.C. {{ $visita->recibeCedulaEnmascarada() }}</x-ui.dato>
                                @else
                                    <x-ui.dato etiqueta="Estado" fuerte>{{ $guia->estado->etiquetaServicio() }}</x-ui.dato>
                                    @if ($visita?->causal)
                                        <x-ui.dato etiqueta="Motivo">{{ $visita->causal->nombre }}</x-ui.dato>
                                    @endif
                                    @if ($guia->fecha_programada && ! $guia->estado->esFinal())
                                        <x-ui.dato etiqueta="Programado para">{{ $guia->fecha_programada->isoFormat('dddd D [de] MMMM') }}</x-ui.dato>
                                    @endif
                                @endif
                                @if ($guia->repartidor)
                                    <x-ui.dato etiqueta="Repartidor">{{ $guia->repartidor->nombre_corto }}@if ($guia->zona) · Zona {{ $guia->zona->nombre }}@endif</x-ui.dato>
                                @endif
                                <x-ui.dato etiqueta="Intentos de entrega">
                                    @if ($guia->intentos === 0)
                                        Aún no se ha visitado
                                    @elseif ($entregada)
                                        {{ $guia->intentos }} ({{ $guia->intentos === 1 ? 'entregado en la primera visita' : 'entregado en la visita '.$guia->intentos }})
                                    @else
                                        {{ $guia->intentos }}
                                    @endif
                                </x-ui.dato>
                            </dl>
                        </section>
                    </div>

                    {{-- Evidencia (bloqueada en la consulta pública) --}}
                    @if ($entregada && $visita)
                        <section class="bg-ink-50 px-6 py-7 sm:px-8">
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <h2 class="text-lg font-bold text-ink-900">Evidencia digital de la entrega</h2>
                                <a href="{{ route('portal.guias.show', $guia) }}" class="enlace inline-flex items-center gap-1.5 text-[15px]">Ver evidencia completa en el portal Credibanco <x-lucide-arrow-right class="size-4" /></a>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-3">
                                @php
                                    $tiene = fn (\App\Enums\TipoEvidencia $tipo): bool => $visita->evidencias->contains('tipo', $tipo);
                                    $piezas = [
                                        ['foto', $tiene(\App\Enums\TipoEvidencia::Foto), 'Foto de los rollos y el sello del comercio'],
                                        ['firma', $tiene(\App\Enums\TipoEvidencia::Firma), 'Firma digital de quien recibe'],
                                        ['gps', $visita->tieneUbicacion(), 'Ubicación GPS registrada en la entrega'],
                                    ];
                                @endphp

                                @foreach ($piezas as [$tipo, $disponible, $texto])
                                    <figure>
                                        <div class="relative flex h-44 items-center justify-center overflow-hidden rounded-2xl border border-ink-200 bg-white">
                                            @if ($tipo === 'foto')
                                                <div class="absolute inset-0 scale-110 bg-linear-to-b from-[#efe6dc] via-[#e3cdb6] to-[#b98a63] blur-md"></div>
                                            @elseif ($tipo === 'firma')
                                                <svg viewBox="0 0 300 80" class="absolute w-3/4 text-info-700/40 blur-[3px]" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"><path d="M8 58c18-40 30-44 34-14s14 26 26-2 22-26 28 4 16 22 30-6 26-20 34 6 30 4 58-14 44-12 74-10" /></svg>
                                            @else
                                                <svg viewBox="0 0 300 130" preserveAspectRatio="xMidYMid slice" class="absolute inset-0 size-full" aria-hidden="true">
                                                    <rect width="300" height="130" fill="#eef0ea" />
                                                    <rect x="14" y="4" width="76" height="40" rx="6" fill="#d9e8cf" /><rect x="212" y="84" width="74" height="44" rx="6" fill="#d9e8cf" />
                                                    <path d="M0 68h300" stroke="#f3d27a" stroke-width="12" /><path d="M56 0v130M140 0v130M232 0v130M0 20h300M0 108h300" stroke="#fff" stroke-width="7" /><path d="M300 0 96 130" stroke="#fff" stroke-width="6" />
                                                    <circle cx="160" cy="68" r="22" fill="#F07D2E" fill-opacity=".18" stroke="#F07D2E" stroke-opacity=".5" />
                                                    <path d="M160 70s-13-12-13-22a13 13 0 1 1 26 0c0 10-13 22-13 22Z" fill="#F07D2E" stroke="#fff" stroke-width="2" /><circle cx="160" cy="48" r="4.5" fill="#fff" />
                                                </svg>
                                            @endif

                                            @if ($tipo !== 'gps')
                                                <span class="relative flex flex-col items-center gap-1.5 text-[15px] font-bold text-ink-900">
                                                    <x-lucide-lock class="size-6" /> Visible con usuario
                                                </span>
                                            @endif
                                        </div>
                                        <figcaption @class(['mt-3 flex items-center gap-2 text-[15px] font-semibold', $disponible ? 'text-ink-800' : 'text-ink-400'])>
                                            <x-dynamic-component :component="'lucide-'.($disponible ? 'circle-check' : 'circle-x')" @class(['size-[18px] shrink-0', 'text-ok-600' => $disponible]) />
                                            {{ $disponible ? $texto : 'Sin '.str($texto)->lcfirst() }}
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </div>

                            <p class="mt-5 text-sm leading-6 text-ink-500">
                                Por protección de datos personales (Ley 1581 de 2012), los datos de quien recibe se muestran de forma parcial. La foto, la firma y el comprobante en PDF están disponibles para los usuarios autorizados de Credibanco.
                            </p>
                        </section>
                    @endif
                </article>
            @elseif (! $mensaje)
                <div class="rounded-3xl border border-dashed border-ink-300 bg-white/60 px-6 py-12 text-center">
                    <span class="mx-auto mb-4 inline-flex size-14 items-center justify-center rounded-2xl bg-brand-100 text-brand-700"><x-lucide-package class="size-7" stroke-width="1.7" /></span>
                    <p class="text-lg font-bold text-ink-900">Aquí verás el estado del servicio</p>
                    <p class="mx-auto mt-1.5 max-w-lg text-[15px] leading-6 text-ink-500">Recibido, asignado a ruta, en reparto, entregado, no entregado o anulado, con la fecha y hora de cada paso y el cumplimiento del ANS.</p>
                </div>
            @endif

            {{-- Ayuda --}}
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                <section class="rounded-2xl border border-ink-200 bg-white p-6 shadow-card">
                    <h2 class="text-lg font-bold text-ink-900">¿Qué significa cada estado?</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.badge color="gray">Recibido</x-ui.badge>
                        <x-ui.badge color="purple">Asignado a ruta</x-ui.badge>
                        <x-ui.badge color="blue">En reparto</x-ui.badge>
                        <x-ui.badge color="green">Entregado</x-ui.badge>
                        <x-ui.badge color="amber">No entregado · se reprograma</x-ui.badge>
                        <x-ui.badge color="red">Anulado</x-ui.badge>
                    </div>
                </section>

                <section class="rounded-2xl border border-ink-200 bg-white p-6 shadow-card">
                    <h2 class="text-lg font-bold text-ink-900">Tiempos de entrega (ANS)</h2>
                    <p class="mt-3 text-[15px] leading-6 text-ink-600">Cada servicio llega con su ANS desde Credibanco (168 horas u 8 horas en urgentes). El sistema mide el tiempo desde la solicitud hasta la entrega.</p>
                </section>

                <section class="rounded-2xl border border-ink-200 bg-white p-6 shadow-card">
                    <h2 class="text-lg font-bold text-ink-900">¿Necesitas ayuda?</h2>
                    <div class="mt-3 space-y-1 text-[15px] leading-6 text-ink-600">
                        @if ($linea)
                            <p>Línea de servicio PMS: <span class="font-semibold text-ink-900">{{ $linea }}</span></p>
                        @endif
                        @if ($direccionPms)
                            <p>{{ $direccionPms }}</p>
                        @endif
                        @if (! $linea && ! $direccionPms)
                            <p>Comunícate con tu contacto en PMS o con la mesa de servicio de Credibanco.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer class="border-t border-ink-200 bg-white">
        <div class="mx-auto flex max-w-[1180px] flex-wrap items-center justify-between gap-3 px-5 py-5 text-sm text-ink-500 sm:px-8">
            <p>© {{ now()->year }} Precision Marketing Solutions Ltda. · Operación de papelería para Credibanco</p>
            <a href="{{ route('privacidad') }}" wire:navigate class="underline underline-offset-2 hover:text-ink-900">Política de tratamiento de datos personales</a>
            <x-credito class="w-full border-t border-ink-200 pt-3" />
        </div>
    </footer>
</div>
