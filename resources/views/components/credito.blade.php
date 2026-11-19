@props(['tono' => 'claro'])

{{-- Crédito del desarrollador. El teléfono abre WhatsApp. --}}
@php
    $nombre = config('pms.desarrollador.nombre');
    $telefono = preg_replace('/\D/', '', (string) config('pms.desarrollador.telefono'));
    $legible = strlen($telefono) === 10 ? substr($telefono, 0, 3).' '.substr($telefono, 3, 3).' '.substr($telefono, 6) : $telefono;
@endphp

@if ($nombre)
    <p {{ $attributes->class(['text-[13px] leading-5', $tono === 'oscuro' ? 'text-ink-400' : 'text-ink-500']) }}>
        Desarrollado por
        <span @class(['font-semibold', $tono === 'oscuro' ? 'text-white' : 'text-ink-800'])>{{ $nombre }}</span>
        @if ($telefono)
            <a href="https://wa.me/57{{ $telefono }}" target="_blank" rel="noopener" @class(['cifra font-semibold whitespace-nowrap underline-offset-2 hover:underline', $tono === 'oscuro' ? 'text-brand-300' : 'text-brand-700'])>{{ $legible }}</a>
        @endif
    </p>
@endif
