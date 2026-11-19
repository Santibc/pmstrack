@props([
    'label' => null,
    'name' => null,
    'ayuda' => null,
    'obligatorio' => false,
])

@php
    $campo = $name ?? $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id', $campo ? 'campo-'.str_replace(['.', '[', ']'], '-', $campo) : null);
@endphp

<div class="w-full">
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="mb-1.5 block text-sm font-semibold text-ink-800">
            {{ $label }}
            @if ($obligatorio)
                <span class="font-semibold text-bad-600">(obligatoria)</span>
            @endif
        </label>
    @endif

    <textarea
        @if ($id) id="{{ $id }}" @endif
        @if ($name) name="{{ $name }}" @endif
        {{ $attributes->except('id')->merge(['rows' => 3])->class(['campo resize-y', 'campo-error' => $campo && $errors->has($campo)]) }}
    >{{ $slot }}</textarea>

    @if ($campo && $errors->has($campo))
        <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $errors->first($campo) }}</p>
    @elseif ($ayuda)
        <p class="mt-1.5 text-sm text-ink-500">{{ $ayuda }}</p>
    @endif
</div>
