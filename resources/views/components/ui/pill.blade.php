@props([
    'activo' => false,
    'tono' => 'gray',
    'icono' => null,
])

@php
    $inactivos = [
        'gray' => 'border-ink-300 bg-white text-ink-800 hover:border-ink-400',
        'green' => 'border-ink-300 bg-white text-ok-700 hover:border-ok-500',
        'blue' => 'border-ink-300 bg-white text-info-700 hover:border-info-500',
        'amber' => 'border-ink-300 bg-white text-warn-700 hover:border-warn-500',
        'red' => 'border-ink-300 bg-white text-bad-700 hover:border-bad-500',
        'warn' => 'border-warn-500/50 bg-warn-50 text-warn-700 hover:border-warn-500',
    ];
@endphp

<button
    type="button"
    {{ $attributes->class([
        'inline-flex h-11 items-center gap-2 rounded-full border px-4.5 text-[15px] font-semibold whitespace-nowrap transition',
        'border-ink-900 bg-ink-900 text-white' => $activo,
        $inactivos[$tono] => ! $activo,
    ]) }}
    aria-pressed="{{ $activo ? 'true' : 'false' }}"
>
    @if ($icono)
        <x-dynamic-component :component="'lucide-'.$icono" class="size-4" stroke-width="2" />
    @endif
    {{ $slot }}
</button>
