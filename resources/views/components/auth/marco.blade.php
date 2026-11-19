@props(['titulo', 'texto' => null])

<div class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-12">
    <x-anillos class="pointer-events-none absolute -top-48 -right-48 size-[560px] opacity-70" />

    <div class="relative w-full max-w-[460px] rounded-3xl border border-ink-200 bg-white p-8 shadow-pop sm:p-10">
        <a href="{{ route('consulta') }}" wire:navigate class="inline-block"><x-logo alto="h-14" /></a>
        <h1 class="mt-7 text-[26px] leading-tight font-extrabold tracking-tight text-ink-900">{{ $titulo }}</h1>
        @if ($texto)
            <p class="mt-2 text-[15px] leading-6 text-ink-500">{{ $texto }}</p>
        @endif
        <div class="mt-7">{{ $slot }}</div>
        <x-credito class="mt-8 border-t border-ink-200 pt-5" />
    </div>
</div>
