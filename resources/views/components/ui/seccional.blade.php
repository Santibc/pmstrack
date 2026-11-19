@props(['seccionales', 'prefijo' => 'Seccional: '])

{{-- Selector de seccional enlazado a la propiedad seccionalCodigo del componente. --}}
@if ($seccionales->count() > 1)
    <div class="relative">
        <select wire:model.live="seccionalCodigo" {{ $attributes->class('campo h-11 w-auto min-w-52 py-0 pr-10 font-semibold') }} aria-label="Seccional">
            @foreach ($seccionales as $opcion)
                <option value="{{ $opcion->codigo }}">{{ $prefijo }}{{ $opcion->nombre }}</option>
            @endforeach
        </select>
    </div>
@else
    <span {{ $attributes->class('inline-flex h-11 items-center gap-2 rounded-xl border border-ink-200 bg-ink-50 px-4 text-[15px] font-semibold text-ink-700') }}>
        <x-lucide-map-pin class="size-4" /> {{ $prefijo }}{{ $seccionales->first()?->nombre }}
    </span>
@endif
