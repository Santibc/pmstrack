@props([
    'titulo',
    'subtitulo' => null,
    'ancho' => 'max-w-lg',
])

{{--
    Ventana modal controlada por una propiedad booleana del componente Livewire:
    <x-ui.modal wire:model="mostrarAjuste" titulo="Ajuste de inventario">…</x-ui.modal>
--}}
<div x-data="{ abierto: $wire.entangle('{{ $attributes->wire('model')->value() }}') }">
@teleport('body')
<div
    x-show="abierto"
    x-cloak
    x-on:keydown.escape.window="abierto = false"
    x-trap.noscroll.noautofocus="abierto"
    x-effect="abierto && $nextTick(() => $el.querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select, textarea')?.focus())"
    class="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
>
    <div x-show="abierto" x-transition.opacity.duration.200ms class="absolute inset-0 bg-ink-950/50 backdrop-blur-[2px]" x-on:click="abierto = false"></div>

    <div
        x-show="abierto"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[0.97]"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-end="opacity-0"
        {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-pop sm:rounded-2xl', $ancho]) }}
    >
        <header class="flex items-start justify-between gap-4 border-b border-ink-200 px-6 py-5">
            <div class="min-w-0">
                <h2 class="text-xl font-bold tracking-tight text-ink-900">{{ $titulo }}</h2>
                @if ($subtitulo)
                    <p class="mt-1 text-sm text-ink-500">{{ $subtitulo }}</p>
                @endif
            </div>
            <button type="button" class="-mt-1 -mr-2 rounded-lg p-2 text-ink-500 hover:bg-ink-100 hover:text-ink-900" x-on:click="abierto = false" aria-label="Cerrar">
                <x-lucide-x class="size-5" />
            </button>
        </header>

        <div class="overflow-y-auto px-6 py-5">{{ $slot }}</div>

        @isset($pie)
            <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-ink-200 bg-ink-50 px-6 py-4">{{ $pie }}</footer>
        @endisset
    </div>
</div>
@endteleport
</div>
