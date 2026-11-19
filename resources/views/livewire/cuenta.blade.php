<div>
    <x-ui.page-header titulo="Mi cuenta" subtitulo="Tus datos de acceso y los últimos ingresos a la plataforma" />

    <div class="grid gap-6 p-5 sm:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:p-10">
        <div class="space-y-6">
            <x-ui.card>
                <div class="flex items-center gap-4">
                    <x-ui.avatar :iniciales="$usuario->iniciales()" tono="brand" size="lg" />
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold">{{ $usuario->name }}</p>
                        <p class="truncate text-[15px] text-ink-500">{{ $usuario->email }}</p>
                    </div>
                </div>
                <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-ink-200 pt-5 text-[15px] sm:grid-cols-2">
                    <div><dt class="text-ink-500">Rol</dt><dd class="font-semibold">{{ $usuario->rolPrincipal()?->etiqueta() ?? '—' }}</dd></div>
                    <div><dt class="text-ink-500">Seccional</dt><dd class="font-semibold">{{ $usuario->seccional?->nombre ?? 'Todas' }}</dd></div>
                    <div><dt class="text-ink-500">Cargo</dt><dd class="font-semibold">{{ $usuario->cargo ?: '—' }}</dd></div>
                    <div><dt class="text-ink-500">Contraseña cambiada</dt><dd class="font-semibold">{{ $usuario->password_cambiada_at?->isoFormat('D MMM YYYY') ?? 'Nunca' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card titulo="Cambiar contraseña" subtitulo="Mínimo 8 caracteres, con letras y números. Cierra tus otras sesiones.">
                <form wire:submit="cambiarPassword" class="space-y-4" novalidate>
                    <x-ui.input label="Contraseña actual" type="password" wire:model="password_actual" autocomplete="current-password" />
                    <x-ui.input label="Contraseña nueva" type="password" wire:model="password" autocomplete="new-password" />
                    <x-ui.input label="Repite la contraseña nueva" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                    <x-ui.button type="submit" variant="dark" icon="key-round" wire:loading.attr="disabled">Actualizar contraseña</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <x-ui.card titulo="Últimos ingresos" subtitulo="Si no reconoces alguno, cambia tu contraseña." :padding="false">
            <table class="tabla mt-4">
                <thead><tr><th>Fecha</th><th>Resultado</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($accesos as $acceso)
                        <tr wire:key="acceso-{{ $acceso->id }}">
                            <td>{{ $acceso->created_at->isoFormat('D MMM · h:mm a') }}</td>
                            <td><x-ui.badge :color="$acceso->exitoso ? 'green' : 'red'">{{ $acceso->exitoso ? 'Ingreso correcto' : 'Intento fallido' }}</x-ui.badge></td>
                            <td class="font-mono text-sm text-ink-600">{{ $acceso->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-ink-500">Aún no hay ingresos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>
</div>
