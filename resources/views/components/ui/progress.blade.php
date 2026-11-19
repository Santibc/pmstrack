@props([
    // Lista de [valor, color]: color = brand | ok | warn | bad | info | gray
    'segmentos' => [],
    'total' => null,
    'alto' => 'h-2',
])

@php
    $colores = [
        'brand' => 'bg-brand-500',
        'ok' => 'bg-ok-500',
        'warn' => 'bg-warn-500',
        'bad' => 'bg-bad-500',
        'info' => 'bg-info-500',
        'gray' => 'bg-ink-300',
    ];

    $suma = array_sum(array_column($segmentos, 0));
    $base = max(1, $total ?? $suma);
@endphp

<div {{ $attributes->class(['flex w-full overflow-hidden rounded-full bg-ink-200', $alto]) }} role="progressbar" aria-valuemin="0" aria-valuemax="{{ $base }}" aria-valuenow="{{ $suma }}">
    @foreach ($segmentos as [$valor, $color])
        @if ($valor > 0)
            <span class="{{ $colores[$color] ?? $colores['brand'] }} h-full" style="width: {{ min(100, round($valor * 100 / $base, 2)) }}%"></span>
        @endif
    @endforeach
</div>
