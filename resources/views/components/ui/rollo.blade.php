@props([
    // Lista de [valor, tono]: tono = brand | ok | warn | bad | info | ink
    'segmentos' => [],
    'total' => null,
    'grosor' => 9,
])

{{--
    Anillo de avance. Es la firma visual de PMS Track: los anillos del logo,
    que también son un rollo de papel visto de frente. Se traza al aparecer y
    transita cuando cambian los valores.
--}}
@php
    $tonos = [
        'brand' => 'var(--color-brand-500)',
        'ok' => 'var(--color-ok-500)',
        'warn' => 'var(--color-warn-500)',
        'bad' => 'var(--color-bad-500)',
        'info' => 'var(--color-info-500)',
        'ink' => 'var(--color-ink-900)',
        'lima' => 'var(--color-lima)',
    ];

    $radio = 50 - $grosor / 2 - 1;
    $largo = 2 * M_PI * $radio;
    $base = max(1, $total ?? array_sum(array_column($segmentos, 0)));
    $acumulado = 0;
@endphp

<div {{ $attributes->class('relative inline-flex shrink-0 items-center justify-center') }}>
    <svg viewBox="0 0 100 100" class="size-full -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" r="{{ $radio }}" fill="none" stroke="var(--color-ink-200)" stroke-width="{{ $grosor }}" />
        @foreach ($segmentos as [$valor, $tono])
            @php
                $tramo = min($largo, max(0, $valor) * $largo / $base);
                $desplazamiento = -$acumulado;
                $acumulado += $tramo;
            @endphp
            @if ($tramo > 0)
                <circle
                    class="rollo-arco"
                    cx="50" cy="50" r="{{ $radio }}" fill="none"
                    stroke="{{ $tonos[$tono] ?? $tonos['brand'] }}"
                    stroke-width="{{ $grosor }}"
                    stroke-linecap="{{ count($segmentos) === 1 ? 'round' : 'butt' }}"
                    stroke-dasharray="{{ round($tramo, 2) }} {{ round($largo, 2) }}"
                    stroke-dashoffset="{{ round($desplazamiento, 2) }}"
                    style="--rollo-largo: {{ round($largo, 2) }}"
                />
            @endif
        @endforeach
    </svg>

    @if ($slot->isNotEmpty())
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center leading-none">{{ $slot }}</div>
    @endif
</div>
