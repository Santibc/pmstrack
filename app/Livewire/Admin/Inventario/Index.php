<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Inventario;

use App\Enums\EstadoParada;
use App\Livewire\Concerns\EligeSeccional;
use App\Livewire\Concerns\Notifica;
use App\Models\Bodega;
use App\Models\Guia;
use App\Models\Ruta;
use App\Services\Inventario\InventarioService;
use App\Support\Rollos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Inventario de rollos por bodega (documento técnico 5.6). Los rollos llegan
 * en cajas y salen por cajas completas o por rollos sueltos.
 */
#[Title('Inventario de rollos')]
class Index extends Component
{
    use EligeSeccional, Notifica;

    #[Url(as: 'fecha', except: '')]
    public string $fecha = '';

    // Entrada por remisión.
    public bool $mostrarEntrada = false;

    public string $remision = '';

    public string $remisionFecha = '';

    public int|string $entradaCajas = 0;

    public int|string $entradaSueltos = 0;

    // Ajuste.
    public bool $mostrarAjuste = false;

    public string $ajusteSentido = 'restar';

    public int|string $ajusteCajas = 0;

    public int|string $ajusteSueltos = 0;

    public string $ajusteMotivo = '';

    // Despacho real a un repartidor.
    public bool $mostrarDespacho = false;

    public ?int $despachoRutaId = null;

    public int|string $despachoCajas = 0;

    public int|string $despachoSueltos = 0;

    public function mount(): void
    {
        if (! $this->fechaValida($this->fecha)) {
            $this->fecha = today()->toDateString();
        }
    }

    public function alCambiarSeccional(): void
    {
        unset($this->bodega);
    }

    public function updatedFecha(): void
    {
        if (! $this->fechaValida($this->fecha)) {
            $this->fecha = today()->toDateString();
        }
    }

    public function abrirEntrada(): void
    {
        Gate::authorize('inventario.gestionar');

        $this->resetValidation();
        $this->reset('remision', 'entradaCajas', 'entradaSueltos');
        $this->remisionFecha = today()->toDateString();
        $this->mostrarEntrada = true;
    }

    public function registrarEntrada(InventarioService $inventario): void
    {
        Gate::authorize('inventario.gestionar');

        $datos = $this->validate([
            'remision' => ['required', 'string', 'max:40'],
            'remisionFecha' => ['required', 'date', 'before_or_equal:today'],
            'entradaCajas' => ['required', 'integer', 'min:0', 'max:100000'],
            'entradaSueltos' => ['required', 'integer', 'min:0', 'max:100000'],
        ], attributes: ['remision' => 'número de remisión', 'remisionFecha' => 'fecha', 'entradaCajas' => 'cajas', 'entradaSueltos' => 'rollos sueltos']);

        $rollos = Rollos::total((int) $datos['entradaCajas'], (int) $datos['entradaSueltos']);

        $this->intentar(function () use ($inventario, $datos, $rollos): void {
            $inventario->entrada($this->bodega, $rollos, trim($datos['remision']), CarbonImmutable::parse($datos['remisionFecha']), auth()->user());
            unset($this->bodega);
            $this->mostrarEntrada = false;
            $this->avisar('Entrada registrada: '.Rollos::enCajas($rollos).' ('.Rollos::numero($rollos).' rollos).');
        });
    }

    public function abrirAjuste(): void
    {
        Gate::authorize('inventario.gestionar');

        $this->resetValidation();
        $this->reset('ajusteCajas', 'ajusteSueltos', 'ajusteMotivo');
        $this->ajusteSentido = 'restar';
        $this->mostrarAjuste = true;
    }

    public function registrarAjuste(InventarioService $inventario): void
    {
        Gate::authorize('inventario.gestionar');

        $datos = $this->validate([
            'ajusteSentido' => ['required', 'in:sumar,restar'],
            'ajusteCajas' => ['required', 'integer', 'min:0', 'max:100000'],
            'ajusteSueltos' => ['required', 'integer', 'min:0', 'max:100000'],
            'ajusteMotivo' => ['required', 'string', 'min:5', 'max:255'],
        ], attributes: ['ajusteCajas' => 'cajas', 'ajusteSueltos' => 'rollos sueltos', 'ajusteMotivo' => 'motivo']);

        $rollos = Rollos::total((int) $datos['ajusteCajas'], (int) $datos['ajusteSueltos']);

        $this->intentar(function () use ($inventario, $datos, $rollos): void {
            $inventario->ajuste($this->bodega, $datos['ajusteSentido'] === 'sumar' ? $rollos : -$rollos, trim($datos['ajusteMotivo']), auth()->user());
            unset($this->bodega);
            $this->mostrarAjuste = false;
            $this->avisar('Ajuste registrado. Queda en la bitácora con tu usuario y el motivo.');
        });
    }

    /**
     * Abre el registro de lo que el repartidor realmente sacó de bodega.
     */
    public function abrirDespacho(int $rutaId): void
    {
        Gate::authorize('inventario.gestionar');

        $ruta = $this->rutaDeLaBodega($rutaId);
        $desglose = Rollos::desglose($ruta->rollos_salida);

        $this->resetValidation();
        $this->despachoRutaId = $ruta->id;
        $this->despachoCajas = $desglose['cajas'];
        $this->despachoSueltos = $desglose['sueltos'];
        $this->mostrarDespacho = true;
    }

    public function registrarDespacho(InventarioService $inventario): void
    {
        Gate::authorize('inventario.gestionar');

        $datos = $this->validate([
            'despachoCajas' => ['required', 'integer', 'min:0', 'max:10000'],
            'despachoSueltos' => ['required', 'integer', 'min:0', 'max:100000'],
        ], attributes: ['despachoCajas' => 'cajas', 'despachoSueltos' => 'rollos sueltos']);

        $ruta = $this->rutaDeLaBodega((int) $this->despachoRutaId);
        $rollos = Rollos::total((int) $datos['despachoCajas'], (int) $datos['despachoSueltos']);

        $this->intentar(function () use ($inventario, $ruta, $rollos): void {
            $movimiento = $inventario->ajustarDespacho($ruta, $rollos, auth()->user());
            unset($this->bodega);
            $this->mostrarDespacho = false;
            $this->avisar($movimiento === null ? 'El despacho ya estaba registrado con esa cantidad.' : 'Despacho actualizado: '.Rollos::enCajas($rollos).'.');
        });
    }

    #[Computed]
    public function bodega(): Bodega
    {
        return Bodega::query()->where('seccional_id', $this->seccional->id)->firstOrFail();
    }

    public function dia(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha)->startOfDay();
    }

    public function render(InventarioService $inventario)
    {
        $bodega = $this->bodega;
        $dia = $this->dia();

        return view('livewire.admin.inventario.index', [
            'bodega' => $bodega,
            'dia' => $dia,
            'esHoy' => $dia->isToday(),
            'resumen' => $inventario->resumenDelDia($bodega, $dia),
            'movimientos' => $bodega->movimientos()->with(['user', 'repartidor.user'])->whereDate('created_at', $dia)->orderBy('id')->get(),
            'enLaCalle' => $inventario->rollosEnLaCalle($bodega, $dia),
            'bodegas' => Bodega::query()->with('seccional')->whereIn('seccional_id', $this->seccionales->pluck('id'))->orderBy('seccional_id')->get(),
            'porCaja' => Rollos::porCaja(),
            'rutaDespacho' => $this->despachoRutaId ? Ruta::query()->with('repartidor.user')->find($this->despachoRutaId) : null,
            'rollosGuiasDespacho' => $this->despachoRutaId ? (int) Guia::query()->whereHas('paradas', fn ($q) => $q->where('ruta_id', $this->despachoRutaId)->whereIn('estado', EstadoParada::activas()))->sum('rollos_solicitados') : 0,
        ]);
    }

    private function rutaDeLaBodega(int $rutaId): Ruta
    {
        return Ruta::query()->where('seccional_id', $this->seccional->id)->findOrFail($rutaId);
    }

    private function fechaValida(string $fecha): bool
    {
        try {
            return $fecha !== '' && CarbonImmutable::createFromFormat('Y-m-d', $fecha) !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
