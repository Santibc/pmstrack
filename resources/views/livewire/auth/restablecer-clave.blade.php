<x-auth.marco titulo="Crea tu contraseña nueva" texto="Mínimo 8 caracteres, con letras y números.">
    <form wire:submit="guardar" class="space-y-5" novalidate>
        <x-ui.input label="Correo" type="email" wire:model="email" icono="mail" autocomplete="username" />
        <x-ui.input label="Contraseña nueva" type="password" wire:model="password" icono="lock" autocomplete="new-password" autofocus />
        <x-ui.input label="Repite la contraseña" type="password" wire:model="password_confirmation" icono="lock" autocomplete="new-password" />
        <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">Guardar contraseña</x-ui.button>
    </form>
</x-auth.marco>
