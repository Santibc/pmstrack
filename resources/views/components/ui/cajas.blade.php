@props([
    'cajas',
    'sueltos',
    'porCaja' => 360,
])

{{--
    Captura de una cantidad de rollos como cajas completas más rollos sueltos.
    $cajas y $sueltos son los nombres de las propiedades Livewire.
--}}
<div x-data="{ cajas: $wire.entangle('{{ $cajas }}'), sueltos: $wire.entangle('{{ $sueltos }}'), porCaja: {{ $porCaja }} }">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="campo-{{ $cajas }}" class="mb-1.5 block text-sm font-semibold text-ink-800">Cajas</label>
            <input id="campo-{{ $cajas }}" type="number" min="0" inputmode="numeric" x-model.number="cajas" @class(['campo cifra text-lg font-bold', 'campo-error' => $errors->has($cajas)]) />
            <p class="mt-1 text-sm text-ink-500">{{ $porCaja }} rollos cada una</p>
        </div>
        <div>
            <label for="campo-{{ $sueltos }}" class="mb-1.5 block text-sm font-semibold text-ink-800">Rollos sueltos</label>
            <input id="campo-{{ $sueltos }}" type="number" min="0" inputmode="numeric" x-model.number="sueltos" @class(['campo cifra text-lg font-bold', 'campo-error' => $errors->has($sueltos)]) />
        </div>
    </div>

    <p class="mt-3 flex items-baseline justify-between rounded-xl bg-ink-50 px-4 py-3">
        <span class="text-[15px] text-ink-600">Total</span>
        <span class="cifra cifra-grande text-2xl font-extrabold text-ink-900">
            <span x-text="new Intl.NumberFormat('es-CO').format((Number(cajas) || 0) * porCaja + (Number(sueltos) || 0))"></span>
            <span class="text-base font-semibold text-ink-500">rollos</span>
        </span>
    </p>

    @error($cajas) <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $message }}</p> @enderror
    @error($sueltos) <p class="mt-1.5 text-sm font-medium text-bad-700">{{ $message }}</p> @enderror
</div>
