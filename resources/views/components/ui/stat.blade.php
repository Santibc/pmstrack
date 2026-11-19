@props([
    'valor',
    'etiqueta',
    'detalle' => null,
    'tono' => 'default',
    'icono' => null,
    'encabezado' => null,
])

@php
    $marcos = [
        'default' => 'border-ink-200 bg-white',
        'brand' => 'border-brand-300 bg-brand-50',
        'bad' => 'border-ink-200 bg-white',
        'ok' => 'border-ink-200 bg-white',
    ];

    $cifras = [
        'default' => 'text-ink-900',
        'brand' => 'text-brand-700',
        'bad' => 'text-bad-600',
        'ok' => 'text-ok-600',
    ];
@endphp

<div {{ $attributes->class(['rounded-2xl border p-5 shadow-card', $marcos[$tono]]) }}>
    @if ($encabezado)
        <p class="mb-2 flex items-center gap-2 text-[15px] font-medium text-ink-600">
            @if ($icono)
                <x-dynamic-component :component="'lucide-'.$icono" class="size-[18px] text-ink-500" stroke-width="1.9" />
            @endif
            {{ $encabezado }}
        </p>
    @endif

    {{-- La cifra transita al valor nuevo cuando el servidor la actualiza. --}}
    <p x-data x-cifra @class(['cifra cifra-grande text-[38px] leading-none font-extrabold tracking-tight', $cifras[$tono]])>{{ $valor }}</p>

    @if ($etiqueta)
        <p class="mt-2.5 text-[15px] font-semibold text-ink-900">{{ $etiqueta }}</p>
    @endif

    @if ($detalle)
        <p class="mt-1 text-sm leading-5 text-ink-500">{{ $detalle }}</p>
    @endif

    {{ $slot }}
</div>
