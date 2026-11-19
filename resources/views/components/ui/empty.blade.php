@props([
    'icono' => 'inbox',
    'titulo',
    'texto' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-14 text-center') }}>
    <span class="mb-4 inline-flex size-14 items-center justify-center rounded-2xl bg-ink-100 text-ink-500">
        <x-dynamic-component :component="'lucide-'.$icono" class="size-7" stroke-width="1.7" />
    </span>
    <h3 class="text-lg font-bold text-ink-900">{{ $titulo }}</h3>
    @if ($texto)
        <p class="mt-1.5 max-w-md text-[15px] leading-6 text-ink-500">{{ $texto }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
