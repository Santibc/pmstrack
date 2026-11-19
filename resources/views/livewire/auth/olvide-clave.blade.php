<x-auth.marco titulo="Recupera tu contraseña" texto="Escribe el correo con el que ingresas y te enviaremos un enlace para crear una contraseña nueva.">
    @if ($enviado)
        <div class="rounded-2xl border border-ok-100 bg-ok-50 p-5">
            <p class="flex items-center gap-2 text-[15px] font-bold text-ok-700"><x-lucide-circle-check class="size-5" /> Revisa tu correo</p>
            <p class="mt-1.5 text-[15px] leading-6 text-ink-700">Si <strong>{{ $email }}</strong> está registrado, en unos minutos recibirás el enlace. Vence en 60 minutos.</p>
        </div>
    @else
        <form wire:submit="enviar" class="space-y-5" novalidate>
            <x-ui.input label="Correo" type="email" wire:model="email" icono="mail" autocomplete="username" autofocus />
            <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">Enviar enlace</x-ui.button>
        </form>
    @endif

    <a href="{{ route('login') }}" wire:navigate class="enlace mt-7 inline-flex items-center gap-1.5 text-[15px]"><x-lucide-chevron-left class="size-4" /> Volver a ingresar</a>
</x-auth.marco>
