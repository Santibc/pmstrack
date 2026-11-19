<?php

declare(strict_types=1);

namespace App\Livewire\Reportes;

use App\Services\Reportes\Reportes;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Reportes: el Excel de entregas en el formato de Credibanco y los reportes
 * de ANS, causales, productividad y corte para facturación.
 */
class Index extends Component
{
    #[Locked]
    public bool $portal = false;

    #[Url(except: 'ans')]
    public string $tipo = 'ans';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        $this->portal = request()->routeIs('portal.*');
        $this->normalizar();
    }

    public function updated(): void
    {
        $this->normalizar();
    }

    /**
     * Credibanco ve los reportes de servicio; PMS, además, los de operación.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function tipos(): array
    {
        return $this->portal ? array_intersect_key(Reportes::TIPOS, array_flip(['ans', 'causales'])) : Reportes::TIPOS;
    }

    public function render(Reportes $reportes)
    {
        $desde = CarbonImmutable::parse($this->desde)->startOfDay();
        $hasta = CarbonImmutable::parse($this->hasta)->endOfDay();
        $area = $this->portal ? 'portal' : 'admin';

        return view('livewire.reportes.index', [
            'area' => $area,
            'tipos' => $this->tipos(),
            'reporte' => $reportes->generar($this->tipo, auth()->user(), $desde, $hasta),
            'rango' => ['desde' => $this->desde, 'hasta' => $this->hasta],
        ])->title($this->portal ? 'Reportes Excel' : 'Reportes');
    }

    private function normalizar(): void
    {
        if (! isset($this->tipos()[$this->tipo])) {
            $this->tipo = 'ans';
        }

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
