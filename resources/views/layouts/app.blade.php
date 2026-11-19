@php
    $usuario = auth()->user();
    $esPortal = request()->routeIs('portal.*');
    $navegacion = app(\App\Support\Navegacion::class);
    $items = $esPortal ? $navegacion->portal($usuario) : $navegacion->administracion($usuario);
    $inicio = $esPortal ? route('portal.dashboard') : route('admin.dashboard');
@endphp
<!DOCTYPE html>
<html lang="es">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen" x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-lg focus:bg-ink-900 focus:px-4 focus:py-2 focus:text-white">Saltar al contenido</a>

        {{-- Barra superior en celular --}}
        <div class="sticky top-0 z-30 flex items-center justify-between border-b border-ink-200 bg-white px-4 py-2.5 lg:hidden">
            <a href="{{ $inicio }}" wire:navigate><x-logo alto="h-10" /></a>
            <button type="button" class="rounded-xl p-2.5 text-ink-800 hover:bg-ink-100" x-on:click="menu = true" aria-label="Abrir menú">
                <x-lucide-menu class="size-6" />
            </button>
        </div>

        <div x-show="menu" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink-950/45 lg:hidden" x-on:click="menu = false"></div>

        {{-- Barra lateral --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[284px] -translate-x-full flex-col border-r border-ink-200 bg-white transition-transform duration-200 lg:translate-x-0"
            :class="menu && '!translate-x-0'"
        >
            <div class="px-6 pt-6 pb-5">
                <a href="{{ $inicio }}" wire:navigate class="inline-block"><x-logo alto="h-[68px]" /></a>
                <div class="mt-3">
                    @if ($esPortal)
                        <span class="inline-flex rounded-full bg-ink-900 px-3.5 py-1.5 text-[13px] font-bold text-white">Portal Credibanco</span>
                    @else
                        <span class="inline-flex rounded-full bg-brand-500 px-3.5 py-1.5 text-[13px] font-bold text-ink-950">Administración PMS</span>
                    @endif
                </div>
            </div>

            <div class="px-4 pb-4">
                <button type="button" x-on:click="menu = false; $dispatch('abrir-buscador')" class="flex h-11 w-full items-center gap-3 rounded-xl border border-ink-200 bg-ink-50 px-3.5 text-[15px] text-ink-500 transition hover:border-ink-300 hover:bg-white hover:text-ink-800">
                    <x-lucide-search class="size-[18px]" />
                    <span class="flex-1 text-left">Buscar un servicio</span>
                    <kbd class="rounded-md border border-ink-200 bg-white px-1.5 py-0.5 text-xs font-semibold text-ink-500">Ctrl K</kbd>
                </button>
            </div>

            <div class="mx-5 border-t border-ink-200"></div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5" aria-label="Menú principal">
                @foreach ($items as $item)
                    @php $activa = request()->routeIs($item['activa']); @endphp
                    <a
                        href="{{ route($item['ruta']) }}"
                        wire:navigate
                        x-on:click="menu = false"
                        @class([
                            'group flex items-center gap-3.5 rounded-xl px-3.5 py-3 text-[16px] transition',
                            'bg-brand-100 font-bold text-brand-700' => $activa,
                            'font-medium text-ink-700 hover:bg-ink-100 hover:text-ink-900' => ! $activa,
                        ])
                        @if ($activa) aria-current="page" @endif
                    >
                        <x-dynamic-component :component="'lucide-'.$item['icono']" class="size-[22px] shrink-0" stroke-width="{{ $activa ? 2.1 : 1.8 }}" />
                        <span class="min-w-0 flex-1 truncate">{{ $item['etiqueta'] }}</span>
                        @if ($item['contador'])
                            <span @class([
                                'cifra inline-flex min-w-7 items-center justify-center rounded-full px-2 py-0.5 text-[13px] font-bold',
                                'bg-brand-500 text-ink-950' => $activa && $item['tono'] === 'brand',
                                'bg-brand-100 text-brand-700' => ! $activa && $item['tono'] === 'brand',
                                'bg-warn-50 text-warn-700' => $item['tono'] === 'warn',
                            ])>{{ $item['contador'] }}</span>
                        @endif
                    </a>
                @endforeach

                @if ($usuario->esAdministracion() && $usuario->can('portal.ver'))
                    <div class="mx-1.5 my-4 border-t border-ink-200"></div>
                    <a href="{{ $esPortal ? route('admin.dashboard') : route('portal.dashboard') }}" wire:navigate class="flex items-center gap-3.5 rounded-xl px-3.5 py-3 text-[15px] font-medium text-ink-600 hover:bg-ink-100 hover:text-ink-900">
                        <x-lucide-arrow-up-right class="size-5 shrink-0" stroke-width="1.8" />
                        {{ $esPortal ? 'Volver a Administración' : 'Ver el portal Credibanco' }}
                    </a>
                @endif
            </nav>

            <div class="mx-5 border-t border-ink-200"></div>

            <div class="flex items-center gap-3 px-5 py-4" x-data="{ abierto: false }">
                <x-ui.avatar :iniciales="$usuario->iniciales()" :tono="$esPortal ? 'info' : 'brand'" size="lg" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[15px] font-bold text-ink-900">{{ $usuario->name }}</p>
                    <p class="truncate text-sm text-ink-500">{{ $usuario->cargo ?: $usuario->rolPrincipal()?->etiqueta() }}</p>
                </div>
                <div class="relative">
                    <button type="button" class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 hover:text-ink-900" x-on:click="abierto = ! abierto" aria-label="Opciones de la cuenta" :aria-expanded="abierto">
                        <x-lucide-chevron-down class="size-5 rotate-180" />
                    </button>
                    <div x-show="abierto" x-cloak x-transition.origin.bottom.right x-on:click.outside="abierto = false" class="absolute right-0 bottom-full mb-2 w-56 rounded-xl border border-ink-200 bg-white p-1.5 shadow-pop">
                        <a href="{{ route('cuenta') }}" wire:navigate class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-[15px] font-medium text-ink-800 hover:bg-ink-100">
                            <x-lucide-key-round class="size-[18px]" stroke-width="1.8" /> Mi cuenta
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-[15px] font-medium text-bad-700 hover:bg-bad-50">
                                <x-lucide-log-out class="size-[18px]" stroke-width="1.8" /> Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <x-credito class="border-t border-ink-200 px-5 py-3" />
        </aside>

        <main id="contenido" class="min-h-screen lg:pl-[284px]">
            {{ $slot }}
        </main>

        <livewire:buscador-global />

        <x-ui.toasts />
    </body>
</html>
