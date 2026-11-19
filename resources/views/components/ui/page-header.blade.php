@props([
    'titulo',
    'subtitulo' => null,
    'enVivo' => false,
])

<header {{ $attributes->class('border-b border-ink-200 bg-white px-5 pt-6 sm:px-8 lg:px-10') }}>
    @isset($migas)
        <nav class="mb-2 flex items-center gap-2 text-[15px] text-ink-500" aria-label="Ruta de navegación">{{ $migas }}</nav>
    @endisset

    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4 pb-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-[28px] leading-tight font-extrabold tracking-tight text-ink-900">{{ $titulo }}</h1>
                {{ $insignias ?? '' }}
            </div>

            @if ($subtitulo)
                <p class="mt-1 flex items-center gap-2 text-[15px] text-ink-500">
                    @if ($enVivo)
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-ok-500 opacity-60"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-ok-500"></span>
                        </span>
                    @endif
                    <span>{{ $subtitulo }}</span>
                </p>
            @endif
        </div>

        @isset($acciones)
            <div class="flex flex-wrap items-center gap-3">{{ $acciones }}</div>
        @endisset
    </div>

    {{ $slot }}
</header>
