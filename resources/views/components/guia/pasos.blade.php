@props(['pasos'])

{{-- Línea de tiempo horizontal de cuatro pasos (se apila en celular). --}}
@php
    $circulos = [
        'ok' => 'bg-ok-500 text-white',
        'warn' => 'bg-warn-500 text-white',
        'bad' => 'bg-bad-500 text-white',
        'gray' => 'border-2 border-ink-300 bg-white text-ink-400',
    ];
    $lineas = ['ok' => 'bg-ok-500', 'warn' => 'bg-warn-500', 'bad' => 'bg-bad-500', 'gray' => 'bg-ink-200'];
@endphp

<ol {{ $attributes->class('grid gap-6 sm:grid-cols-4 sm:gap-4') }}>
    @foreach ($pasos as $indice => $paso)
        @php $siguiente = $pasos[$indice + 1] ?? null; @endphp
        <li class="relative flex gap-4 sm:block">
            <div class="flex flex-col items-center sm:flex-row sm:items-center">
                <span @class(['relative z-10 inline-flex size-11 shrink-0 items-center justify-center rounded-full', $circulos[$paso['tono']], 'ring-[6px] ring-ok-50' => $loop->last && $paso['tono'] === 'ok'])>
                    @if ($paso['estado'] === 'pendiente')
                        <span class="size-2.5 rounded-full bg-ink-300"></span>
                    @elseif ($paso['tono'] === 'bad')
                        <x-lucide-x class="size-5" stroke-width="2.6" />
                    @elseif ($paso['tono'] === 'warn')
                        <x-lucide-rotate-ccw class="size-5" stroke-width="2.4" />
                    @else
                        <x-lucide-check class="size-5" stroke-width="2.8" />
                    @endif
                </span>
                @if ($siguiente)
                    <span @class(['mt-1 w-1 flex-1 rounded-full sm:mt-0 sm:ml-3 sm:h-1 sm:w-auto', $siguiente['estado'] === 'pendiente' ? $lineas['gray'] : $lineas[$paso['tono']]])></span>
                @endif
            </div>
            <div class="pb-1 sm:mt-4 sm:pr-4">
                <p @class(['text-[17px] font-bold', $paso['estado'] === 'pendiente' ? 'text-ink-500' : 'text-ink-900'])>{{ $paso['titulo'] }}</p>
                @if ($paso['fecha'])
                    <p class="mt-0.5 text-[15px] text-ink-700">{{ $paso['fecha']->isoFormat('ddd D MMM · h:mm a') }}</p>
                @endif
                @if ($paso['detalle'])
                    <p class="mt-0.5 text-sm leading-5 text-ink-500">{{ $paso['detalle'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
