@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconAfter' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold whitespace-nowrap transition select-none disabled:cursor-not-allowed disabled:opacity-55';

    $variantes = [
        'primary' => 'bg-brand-500 text-ink-950 shadow-card hover:bg-brand-600 active:bg-brand-700',
        'dark' => 'bg-ink-900 text-white shadow-card hover:bg-ink-800 active:bg-ink-950',
        'outline' => 'border border-ink-300 bg-white text-ink-900 shadow-card hover:border-ink-400 hover:bg-ink-50',
        'soft' => 'bg-brand-100 text-brand-800 hover:bg-brand-200',
        'ghost' => 'text-ink-700 hover:bg-ink-100',
        'danger' => 'bg-bad-500 text-white shadow-card hover:bg-bad-600',
        'danger-outline' => 'border border-bad-100 bg-white text-bad-700 hover:bg-bad-50',
    ];

    $tamanos = [
        'sm' => 'h-9 px-3.5 text-sm',
        'md' => 'h-11 px-4.5 text-[15px]',
        'lg' => 'h-13 px-6 text-base',
    ];

    $icono = ['sm' => 'size-4', 'md' => 'size-[18px]', 'lg' => 'size-5'][$size];
    $clases = $base.' '.$variantes[$variant].' '.$tamanos[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" :class="$icono.' shrink-0'" stroke-width="2" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-dynamic-component :component="'lucide-'.$iconAfter" :class="$icono.' shrink-0'" stroke-width="2" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($clases) }}>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" :class="$icono.' shrink-0'" stroke-width="2" />
        @endif
        {{ $slot }}
        @if ($iconAfter)
            <x-dynamic-component :component="'lucide-'.$iconAfter" :class="$icono.' shrink-0'" stroke-width="2" />
        @endif
    </button>
@endif
