{{-- Paginación de Livewire con el estilo de la plataforma. --}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Paginación">
        <p class="text-[15px] text-ink-500">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ number_format($paginator->total(), 0, ',', '.') }}
        </p>

        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) class="inline-flex size-10 items-center justify-center rounded-lg border border-ink-300 bg-white text-ink-700 hover:border-ink-400 disabled:opacity-40" aria-label="Página anterior">
                <x-lucide-chevron-left class="size-4.5" />
            </button>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 text-ink-500">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:key="pagina-{{ $paginator->getPageName() }}-{{ $page }}" @class([
                            'cifra inline-flex h-10 min-w-10 items-center justify-center rounded-lg border px-2.5 text-[15px] font-semibold',
                            'border-ink-900 bg-ink-900 text-white' => $page === $paginator->currentPage(),
                            'border-ink-300 bg-white text-ink-800 hover:border-ink-400' => $page !== $paginator->currentPage(),
                        ]) @if ($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</button>
                    @endforeach
                @endif
            @endforeach

            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages()) class="inline-flex size-10 items-center justify-center rounded-lg border border-ink-300 bg-white text-ink-700 hover:border-ink-400 disabled:opacity-40" aria-label="Página siguiente">
                <x-lucide-chevron-right class="size-4.5" />
            </button>
        </div>
    </nav>
@endif
