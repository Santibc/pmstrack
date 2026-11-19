@props([
    'label' => null,
    'name' => null,
    'ayuda' => null,
    'icono' => null,
    'opcional' => false,
])

@php
    // El nombre del error sale del wire:model cuando no se indica uno.
    $campo = $name ?? $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id', $campo ? 'campo-'.str_replace(['.', '[', ']'], '-', $campo) : null);
@endphp

<div class="w-full">
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-sm font-semibold text-ink-800">
            {{ $label }}
            @if ($opcional)
                <span class="font-normal text-ink-500">(opcional)</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icono)
            <x-dynamic-component :component="'lucide-'.$icono" class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-ink-500" stroke-width="1.9" />
        @endif

        <input
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->except('id')->class(['campo', 'pl-11' => $icono, 'campo-error' => $campo && $errors->has($campo)]) }}
        />
    </div>

    @if ($campo && $errors->has($campo))
        <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $errors->first($campo) }}</p>
    @elseif ($ayuda)
        <p class="mt-1.5 text-sm text-ink-500">{{ $ayuda }}</p>
    @endif
</div>
