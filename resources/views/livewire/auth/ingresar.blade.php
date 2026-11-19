<div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,560px)]">
    {{-- Panel de marca --}}
    <section class="relative hidden overflow-hidden bg-ink-900 text-white lg:flex lg:flex-col lg:justify-between lg:p-14">
        <x-anillos class="pointer-events-none absolute -top-40 -right-44 size-[620px] opacity-90" />
        <x-anillos class="pointer-events-none absolute -bottom-56 -left-52 size-[520px] opacity-25" />

        <div class="relative">
            <span class="inline-flex rounded-xl bg-white px-4 py-2.5"><x-logo alto="h-12" /></span>
        </div>

        <div class="relative max-w-xl">
            <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-sm font-semibold text-brand-300">
                <span class="size-2 rounded-full bg-lima"></span> PMS Track
            </p>
            <h1 class="text-[44px] leading-[1.08] font-extrabold tracking-tight">Cada entrega de rollos, con evidencia y en tiempo real.</h1>
            <p class="mt-5 text-lg leading-8 text-ink-300">Del Excel diario de Credibanco a la entrega con foto, firma y ubicación, visible en segundos.</p>

            <dl class="mt-10 grid grid-cols-3 gap-6 border-t border-white/10 pt-8">
                @foreach ([['Excel de siempre', 'Sin cambiar el envío'], ['Evidencia digital', 'Foto, firma, cédula y GPS'], ['ANS medido', 'Servicio por servicio']] as [$titulo, $texto])
                    <div>
                        <dt class="text-[15px] font-bold text-white">{{ $titulo }}</dt>
                        <dd class="mt-1 text-sm leading-5 text-ink-400">{{ $texto }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="relative flex flex-wrap items-end justify-between gap-3">
            <p class="text-sm text-ink-400">© {{ now()->year }} Precision Marketing Solutions Ltda.</p>
            <x-credito tono="oscuro" />
        </div>
    </section>

    {{-- Formulario --}}
    <section class="flex flex-col justify-center bg-white px-6 py-12 sm:px-14">
        <div class="mx-auto w-full max-w-[400px]">
            <div class="mb-10 lg:hidden"><x-logo alto="h-16" /></div>

            <h2 class="text-[30px] leading-tight font-extrabold tracking-tight text-ink-900">Ingresa a tu cuenta</h2>
            <p class="mt-2 text-[15px] leading-6 text-ink-500">Plataforma de administración de PMS y portal de Credibanco.</p>

            <form wire:submit="ingresar" class="mt-9 space-y-5" novalidate>
                <x-ui.input label="Correo" type="email" wire:model="email" icono="mail" autocomplete="username" autofocus placeholder="nombre@empresa.com" />

                <div x-data="{ ver: false }">
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="campo-password" class="text-sm font-semibold text-ink-800">Contraseña</label>
                        <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-semibold text-brand-700 hover:underline">¿La olvidaste?</a>
                    </div>
                    <div class="relative">
                        <x-lucide-lock class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-ink-500" stroke-width="1.9" />
                        <input id="campo-password" :type="ver ? 'text' : 'password'" wire:model="password" autocomplete="current-password" @class(['campo px-11', 'campo-error' => $errors->has('password')]) />
                        <button type="button" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-lg p-2 text-ink-500 hover:text-ink-900" x-on:click="ver = ! ver" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                            <x-lucide-eye x-show="! ver" class="size-[18px]" />
                            <x-lucide-eye-off x-show="ver" x-cloak class="size-[18px]" />
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2.5 text-[15px] text-ink-700">
                    <input type="checkbox" wire:model="recordar" class="size-[18px] rounded border-ink-300 accent-brand-500" />
                    Mantener la sesión en este equipo
                </label>

                <x-ui.button type="submit" variant="primary" size="lg" class="w-full" icon-after="arrow-right" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="ingresar">Ingresar</span>
                    <span wire:loading wire:target="ingresar">Verificando…</span>
                </x-ui.button>
            </form>

            <div class="mt-10 rounded-2xl border border-ink-200 bg-ink-50 p-5">
                <p class="flex items-center gap-2 text-[15px] font-bold text-ink-900">
                    <x-lucide-search class="size-[18px] text-brand-600" /> ¿Solo quieres consultar un servicio?
                </p>
                <p class="mt-1 text-sm leading-6 text-ink-500">No necesitas cuenta: basta con el número de servicio y el código único del comercio.</p>
                <a href="{{ route('consulta') }}" wire:navigate class="enlace mt-2 inline-flex items-center gap-1.5 text-[15px]">Ir a la consulta pública <x-lucide-arrow-right class="size-4" /></a>
            </div>

            <p class="mt-8 text-sm leading-6 text-ink-500">
                <x-lucide-smartphone class="mr-1 inline size-4 -translate-y-px" />
                Los repartidores ingresan desde la app PMS Track en su celular.
            </p>

            <x-credito class="mt-8 border-t border-ink-200 pt-5 lg:hidden" />
        </div>
    </section>
</div>
