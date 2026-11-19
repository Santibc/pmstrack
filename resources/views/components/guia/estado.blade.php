@props([
    'guia',
    // "servicio" usa el vocabulario de Credibanco (Entregado); "guia" el de la operación de PMS (Entregada).
    'modo' => 'servicio',
    'size' => 'md',
    'detalle' => null,
])

@php
    $estado = $guia->estado;
    $icono = match ($estado) {
        \App\Enums\EstadoGuia::Entregada => 'circle-check',
        \App\Enums\EstadoGuia::Anulada, \App\Enums\EstadoGuia::DevueltaCredibanco => 'ban',
        default => null,
    };
@endphp

<x-ui.badge :color="$estado->color()" :icon="$size === 'lg' ? $icono : null" :size="$size" {{ $attributes }}>
    {{ $modo === 'servicio' ? $estado->etiquetaServicio() : $estado->etiqueta() }}@if ($detalle) · {{ $detalle }}@endif
</x-ui.badge>
