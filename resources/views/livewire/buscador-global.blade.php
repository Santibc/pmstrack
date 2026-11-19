<div
    x-data="buscadorGlobal"
    x-on:keydown.window.ctrl.k.prevent="abrir()"
    x-on:keydown.window.meta.k.prevent="abrir()"
    x-on:abrir-buscador.window="abrir()"
    x-on:keydown.escape.window="cerrar()"
>
    <div x-show="abierto" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Buscador">
        <div x-show="abierto" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink-950/50 backdrop-blur-[3px]" x-on:click="cerrar()"></div>

        <div
            x-show="abierto"
            x-trap.noscroll="abierto"
            x-transition:enter="transition duration-150 ease-out"
            x-transition:enter-start="-translate-y-2 scale-[0.98] opacity-0"
            x-transition:leave="transition duration-100 ease-in"
            x-transition:leave-end="opacity-0"
            class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-pop ring-1 ring-ink-900/10"
        >
            <div class="flex items-center gap-3 border-b border-ink-200 px-5">
                <x-lucide-search class="size-5 shrink-0 text-ink-500" />
                <input
                    x-ref="entrada"
                    type="text"
                    wire:model.live.debounce.220ms="termino"
                    x-on:keydown.down.prevent="mover(1)"
                    x-on:keydown.up.prevent="mover(-1)"
                    x-on:keydown.enter.prevent="elegir()"
                    x-on:input="activo = 0"
                    placeholder="Busca un servicio, una guía, un comercio o una pantalla"
                    class="h-16 w-full bg-transparent text-lg text-ink-900 placeholder:text-ink-400 focus:outline-none"
                    autocomplete="off"
                />
                <span wire:loading wire:target="termino" class="size-5 shrink-0 animate-spin rounded-full border-2 border-ink-200 border-t-brand-500"></span>
                <kbd class="hidden shrink-0 rounded-md border border-ink-200 bg-ink-50 px-2 py-1 text-xs font-semibold text-ink-500 sm:block">Esc</kbd>
            </div>

            <div class="max-h-[56vh] overflow-y-auto p-2">
                @php $indice = 0; @endphp

                @if ($guias->isNotEmpty())
                    <p class="px-3 pt-2 pb-1.5 text-sm font-semibold text-ink-500">Servicios</p>
                    @foreach ($guias as $guia)
                        <a href="{{ route($area.'.guias.show', $guia) }}" wire:navigate wire:key="buscar-guia-{{ $guia->id }}" data-opcion x-on:click="cerrar()" x-on:mouseenter="activo = {{ $indice }}"
                            class="flex items-center gap-3.5 rounded-xl px-3 py-2.5" :class="activo === {{ $indice }} && 'bg-brand-100'">
                            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-600"><x-lucide-package class="size-5" stroke-width="1.8" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-bold text-ink-900">{{ $guia->servicio }} · {{ $guia->nombre_comercio }}</span>
                                <span class="block truncate text-sm text-ink-500">{{ $guia->numero_guia }} · CU {{ $guia->comercio->codigo_unico }} · {{ $guia->direccionCompleta() }}, {{ $guia->ciudad->nombre }}</span>
                            </span>
                            <x-guia.estado :guia="$guia" size="sm" />
                        </a>
                        @php $indice++; @endphp
                    @endforeach
                @elseif ($buscando)
                    <p class="px-3 py-6 text-center text-[15px] text-ink-500">Ningún servicio coincide con «{{ $termino }}».</p>
                @endif

                @if ($pantallas->isNotEmpty())
                    <p class="px-3 pt-3 pb-1.5 text-sm font-semibold text-ink-500">Ir a</p>
                    @foreach ($pantallas as $pantalla)
                        <a href="{{ route($pantalla['ruta']) }}" wire:navigate wire:key="buscar-pantalla-{{ $pantalla['ruta'] }}" data-opcion x-on:click="cerrar()" x-on:mouseenter="activo = {{ $indice }}"
                            class="flex items-center gap-3.5 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-ink-800" :class="activo === {{ $indice }} && 'bg-brand-100'">
                            <x-dynamic-component :component="'lucide-'.$pantalla['icono']" class="size-5 text-ink-500" stroke-width="1.8" />
                            {{ $pantalla['etiqueta'] }}
                        </a>
                        @php $indice++; @endphp
                    @endforeach
                @endif
            </div>

            <p class="flex items-center gap-4 border-t border-ink-200 bg-ink-50 px-5 py-2.5 text-xs text-ink-500">
                <span><kbd class="rounded border border-ink-200 bg-white px-1.5 py-0.5 font-semibold">↑</kbd> <kbd class="rounded border border-ink-200 bg-white px-1.5 py-0.5 font-semibold">↓</kbd> para moverte</span>
                <span><kbd class="rounded border border-ink-200 bg-white px-1.5 py-0.5 font-semibold">Enter</kbd> para abrir</span>
            </p>
        </div>
    </div>
</div>
