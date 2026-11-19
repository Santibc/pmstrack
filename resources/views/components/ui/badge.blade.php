@props([
    'color' => 'gray',
    'icon' => null,
    'size' => 'md',
    'solid' => false,
])

@php
    $suaves = [
        'gray' => 'bg-ink-100 text-ink-700',
        'orange' => 'bg-brand-100 text-brand-700',
        'green' => 'bg-ok-50 text-ok-700',
        'amber' => 'bg-warn-50 text-warn-700',
        'red' => 'bg-bad-50 text-bad-700',
        'blue' => 'bg-info-50 text-info-700',
        'purple' => 'bg-plum-50 text-plum-700',
        'dark' => 'bg-ink-900 text-white',
    ];

    $solidos = [
        'gray' => 'bg-ink-600 text-white',
        'orange' => 'bg-brand-500 text-ink-950',
        'green' => 'bg-ok-500 text-white',
        'amber' => 'bg-warn-500 text-ink-950',
        'red' => 'bg-bad-500 text-white',
        'blue' => 'bg-info-500 text-white',
        'purple' => 'bg-plum-500 text-white',
        'dark' => 'bg-ink-900 text-white',
    ];

    $tamanos = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-[13px] leading-4',
        'lg' => 'px-3.5 py-1.5 text-sm',
    ];

    $paleta = $solid ? $solidos : $suaves;
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full font-semibold whitespace-nowrap', $paleta[$color] ?? $paleta['gray'], $tamanos[$size]]) }}>
    @if ($icon)
        <x-dynamic-component :component="'lucide-'.$icon" class="size-3.5 shrink-0" stroke-width="2.25" />
    @endif
    {{ $slot }}
</span>
