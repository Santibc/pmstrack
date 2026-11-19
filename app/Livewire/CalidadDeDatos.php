<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Seccional;
use App\Services\Reportes\CalidadDatos;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Calidad de datos (documento técnico 4.2): comercios con dirección errada,
 * cerrados o con teléfono incompleto, para depurar la base de Credibanco.
 */
class CalidadDeDatos extends Component
{
    use WithPagination;

    #[Locked]
    public bool $portal = false;

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $seccional = '';

    /** '' | terreno | telefono | ciudad */
    #[Url(except: '')]
    public string $tipo = '';

    public function mount(): void
    {
        $this->portal = request()->routeIs('portal.*');
        $this->normalizar();
    }

    public function updated(): void
    {
        $this->normalizar();
        $this->resetPage();
    }

    public function render(CalidadDatos $calidad)
    {
        $usuario = auth()->user();
        $desde = CarbonImmutable::parse($this->desde)->startOfDay();
        $hasta = CarbonImmutable::parse($this->hasta)->endOfDay();
        $seccionales = Seccional::query()->activas()->visiblesPara($usuario)->orderBy('id')->get();
        $seccionalId = $seccionales->firstWhere('codigo', $this->seccional)?->id;
        $contar = fn (?string $tipo): int => $calidad->consulta($desde, $hasta, $seccionalId, $tipo)->visiblesPara($usuario)->count();

        return view('livewire.calidad-de-datos', [
            'area' => $this->portal ? 'portal' : 'admin',
            'seccionales' => $seccionales,
            'calidad' => $calidad,
            'guias' => $calidad->consulta($desde, $hasta, $seccionalId, $this->tipo ?: null)
                ->visiblesPara($usuario)
                ->with(['comercio', 'ciudad', 'visitas.causal'])
                ->latest('fecha_registro')
                ->paginate(12),
            'conteos' => [
                '' => $contar(null),
                CalidadDatos::TERRENO => $contar(CalidadDatos::TERRENO),
                CalidadDatos::TELEFONO => $contar(CalidadDatos::TELEFONO),
                CalidadDatos::CIUDAD => $contar(CalidadDatos::CIUDAD),
            ],
            'consulta' => array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'seccional' => $this->seccional]),
        ])->title('Calidad de datos');
    }

    private function normalizar(): void
    {
        try {
            $valido = CarbonImmutable::createFromFormat('Y-m-d', $this->desde) && CarbonImmutable::createFromFormat('Y-m-d', $this->hasta) && $this->desde <= $this->hasta;
        } catch (Throwable) {
            $valido = false;
        }

        if (! $valido) {
            $this->desde = now()->startOfMonth()->toDateString();
            $this->hasta = now()->toDateString();
        }
    }
}
