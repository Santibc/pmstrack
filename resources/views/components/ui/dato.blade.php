@props(['etiqueta', 'fuerte' => false])

{{-- Fila "etiqueta · valor" de las fichas de datos. --}}
<div {{ $attributes->class('grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-4 py-1.5 text-[15px]') }}>
    <dt class="text-ink-500">{{ $etiqueta }}</dt>
    <dd @class(['min-w-0 break-words text-ink-900', 'font-bold' => $fuerte])>{{ $slot }}</dd>
</div>
