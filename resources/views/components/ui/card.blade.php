@props([
    'padding' => true,
    'titulo' => null,
    'subtitulo' => null,
])

<section {{ $attributes->class(['rounded-2xl border border-ink-200 bg-white shadow-card', 'p-6' => $padding && ! $titulo]) }}>
    @if ($titulo)
        <header @class(['flex flex-wrap items-start justify-between gap-3 px-6 pt-5', 'pb-4' => $padding])>
            <div class="min-w-0">
                <h2 class="text-lg font-bold tracking-tight text-ink-900">{{ $titulo }}</h2>
                @if ($subtitulo)
                    <p class="mt-0.5 text-sm text-ink-500">{{ $subtitulo }}</p>
                @endif
            </div>
            @isset($acciones)
                <div class="flex shrink-0 items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
        <div @class(['px-6 pb-6' => $padding])>{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
