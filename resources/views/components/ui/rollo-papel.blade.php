@props([
    // Existencias frente a la referencia (1 = lleno).
    'nivel' => 0,
    'bajo' => false,
])

{{--
    Un rollo de papel visto de frente: entre más existencias, más grueso.
    Se usa para mostrar el inventario de cada bodega.
--}}
@php
    $nivel = max(0, min(1, (float) $nivel));
    $nucleo = 13;
    $exterior = $nucleo + 3 + 32 * $nivel;
    $papel = $bajo ? 'var(--color-warn-50)' : '#fff';
    $borde = $bajo ? 'var(--color-warn-500)' : 'var(--color-ink-300)';
@endphp

<svg viewBox="0 0 100 100" {{ $attributes->merge(['aria-hidden' => 'true']) }}>
    <circle cx="50" cy="50" r="48" fill="var(--color-ink-50)" stroke="var(--color-ink-200)" stroke-dasharray="2 4" />
    <circle class="rollo-papel" cx="50" cy="50" r="{{ round($exterior, 2) }}" fill="{{ $papel }}" stroke="{{ $borde }}" stroke-width="1.5" />
    {{-- Vueltas del papel --}}
    @for ($vuelta = $nucleo + 5; $vuelta < $exterior - 2; $vuelta += 4.5)
        <circle cx="50" cy="50" r="{{ $vuelta }}" fill="none" stroke="{{ $borde }}" stroke-opacity=".45" stroke-width=".8" />
    @endfor
    {{-- Núcleo de cartón --}}
    <circle cx="50" cy="50" r="{{ $nucleo }}" fill="#fff" stroke="var(--color-brand-500)" stroke-width="4" />
    <circle cx="50" cy="50" r="4.5" fill="var(--color-lima)" />
</svg>
