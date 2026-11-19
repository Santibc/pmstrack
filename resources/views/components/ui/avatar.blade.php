@props([
    'iniciales',
    'tono' => 'dark',
    'size' => 'md',
])

@php
    $tonos = [
        'dark' => 'bg-ink-900 text-white',
        'brand' => 'bg-brand-100 text-brand-700',
        'info' => 'bg-info-50 text-info-700',
        'gray' => 'bg-ink-100 text-ink-600',
    ];

    $tamanos = [
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-12 text-base',
    ];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full font-bold tracking-wide', $tonos[$tono], $tamanos[$size]]) }} aria-hidden="true">{{ $iniciales }}</span>
