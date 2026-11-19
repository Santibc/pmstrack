{{--
    Avisos breves. Se disparan desde Livewire con $this->dispatch('notificar', tipo: 'ok', mensaje: '…')
    o llegan como mensaje de sesión (status / error) tras una redirección.
--}}
@php
    $iniciales = array_values(array_filter([
        session('status') ? ['tipo' => 'ok', 'mensaje' => session('status')] : null,
        session('error') ? ['tipo' => 'error', 'mensaje' => session('error')] : null,
    ]));
@endphp

<div
    x-data="{
        avisos: [],
        agregar(aviso) {
            const id = Date.now() + Math.random();
            this.avisos.push({ id, tipo: aviso.tipo ?? 'ok', mensaje: aviso.mensaje });
            setTimeout(() => this.cerrar(id), aviso.tipo === 'error' ? 8000 : 4500);
        },
        cerrar(id) {
            this.avisos = this.avisos.filter((aviso) => aviso.id !== id);
        },
    }"
    x-init="@js($iniciales).forEach((aviso) => agregar(aviso))"
    x-on:notificar.window="agregar($event.detail)"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-3 p-5 sm:inset-x-auto sm:right-0 sm:items-end"
    aria-live="polite"
>
    <template x-for="aviso in avisos" :key="aviso.id">
        <div
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-pop"
            :class="{
                'border-ok-100': aviso.tipo === 'ok',
                'border-bad-100': aviso.tipo === 'error',
                'border-warn-100': aviso.tipo === 'aviso',
            }"
        >
            <span
                class="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'bg-ok-50 text-ok-600': aviso.tipo === 'ok',
                    'bg-bad-50 text-bad-600': aviso.tipo === 'error',
                    'bg-warn-50 text-warn-700': aviso.tipo === 'aviso',
                }"
            >
                <x-lucide-check x-show="aviso.tipo === 'ok'" class="size-4" stroke-width="2.5" />
                <x-lucide-x x-show="aviso.tipo === 'error'" class="size-4" stroke-width="2.5" />
                <x-lucide-triangle-alert x-show="aviso.tipo === 'aviso'" class="size-4" stroke-width="2.25" />
            </span>
            <p class="min-w-0 flex-1 text-[15px] leading-6 font-medium text-ink-900" x-text="aviso.mensaje"></p>
            <button type="button" class="-m-1 rounded-lg p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700" x-on:click="cerrar(aviso.id)" aria-label="Cerrar aviso">
                <x-lucide-x class="size-4" />
            </button>
        </div>
    </template>
</div>
