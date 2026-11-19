<div>
    <x-ui.page-header titulo="Consulta rápida" subtitulo="Escribe el número de servicio, el código único o el nombre del comercio." />

    <div class="mx-auto max-w-4xl p-5 sm:p-8 lg:p-10">
        <div class="relative">
            <x-lucide-search class="pointer-events-none absolute top-1/2 left-5 size-6 -translate-y-1/2 text-ink-500" />
            <input type="search" wire:model.live.debounce.300ms="termino" autofocus placeholder="3937452, 18452276 o Café Altamira"
                class="campo h-16 rounded-2xl pl-14 text-xl font-semibold placeholder:font-normal" aria-label="Buscar servicio" />
            <span wire:loading wire:target="termino" class="absolute top-1/2 right-5 size-5 -translate-y-1/2 animate-spin rounded-full border-2 border-ink-200 border-t-brand-500"></span>
        </div>
        <p class="mt-3 text-sm text-ink-500">También puedes abrir el buscador desde cualquier pantalla con <kbd class="rounded border border-ink-200 bg-white px-1.5 py-0.5 font-semibold">Ctrl</kbd> + <kbd class="rounded border border-ink-200 bg-white px-1.5 py-0.5 font-semibold">K</kbd>.</p>

        <ul class="mt-6 space-y-3" x-data x-animar>
            @foreach ($guias as $guia)
                <li wire:key="rapida-{{ $guia->id }}">
                    <a href="{{ route('portal.guias.show', $guia) }}" wire:navigate class="group flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-ink-200 bg-white p-5 shadow-card transition hover:border-brand-300">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2.5">
                                <span class="cifra cifra-grande text-2xl font-extrabold tracking-tight text-ink-900">{{ $guia->servicio }}</span>
                                <x-guia.estado :guia="$guia" />
                            </p>
                            <p class="mt-1 truncate text-[16px] font-semibold text-ink-800">{{ $guia->nombre_comercio }} <span class="font-normal text-ink-500">· CU {{ $guia->comercio->codigo_unico }}</span></p>
                            <p class="truncate text-sm text-ink-500">{{ $guia->direccionCompleta() }}, {{ $guia->ciudad->nombre }} · solicitado el {{ $guia->fecha_registro->isoFormat('D MMM YYYY') }}</p>
                        </div>
                        <x-ui.badge :color="$guia->semaforo()->color()">{{ $guia->semaforoTexto() }}</x-ui.badge>
                        <x-lucide-chevron-right class="size-5 text-ink-400 transition-transform group-hover:translate-x-1" />
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($buscando && $guias->isEmpty())
            <x-ui.empty icono="search" titulo="Ningún servicio coincide" :texto="'No encontramos servicios con «'.$termino.'». Revisa el número o prueba con el nombre del comercio.'" />
        @elseif (! $buscando)
            <x-ui.empty icono="package" titulo="Encuentra cualquier servicio en segundos" texto="Verás su estado, la foto, la firma, la ubicación de la entrega y el cumplimiento del ANS." />
        @endif
    </div>
</div>
