<?php

declare(strict_types=1);

namespace App\Livewire\Guias;

use App\Models\Ciudad;
use App\Models\Repartidor;
use App\Models\Seccional;
use App\Queries\FiltroGuias;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de guías con su estado, tiempos y evidencia. Es la misma pantalla
 * para PMS ("Guías e histórico") y para Credibanco ("Servicios e histórico").
 */
class Listado extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $seccional = '';

    #[Url(except: '')]
    public string $ciudad = '';

    #[Url(except: '')]
    public string $estado = '';

    #[Url(except: '')]
    public string $ans = '';

    #[Url(except: '')]
    public string $repartidor = '';

    /** La pantalla se abrió desde el portal Credibanco (solo lectura). */
    #[Locked]
    public bool $portal = false;

    public function mount(): void
    {
        $this->portal = request()->routeIs('portal.*');

        // Por defecto, el mes en curso (como en el portal de Credibanco).
        if ($this->desde === '' && $this->hasta === '' && $this->buscar === '') {
            $this->desde = now()->startOfMonth()->toDateString();
            $this->hasta = now()->toDateString();
        }
    }

    public function updated(string $propiedad): void
    {
        if ($propiedad !== 'paginators') {
            $this->resetPage();
        }

        if ($propiedad === 'seccional') {
            $this->reset('ciudad', 'repartidor');
        }
    }

    public function limpiar(): void
    {
        $this->reset('buscar', 'seccional', 'ciudad', 'estado', 'ans', 'repartidor');
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    public function filtros(): array
    {
        return [
            'q' => $this->buscar,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'seccional' => $this->seccional,
            'ciudad' => $this->ciudad,
            'estado' => $this->estado,
            'ans' => $this->ans,
            'repartidor' => $this->repartidor,
        ];
    }

    public function render()
    {
        $usuario = auth()->user();
        $portal = $this->portal;
        $filtros = $this->filtros();
        $base = FiltroGuias::aplicar($filtros, $usuario, conEstado: false);

        $porEstado = (clone $base)->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->toBase()->pluck('total', 'estado');
        $contar = fn (string $grupo): int => (int) collect(FiltroGuias::GRUPOS[$grupo])->sum(fn ($estado) => $porEstado[$estado->value] ?? 0);

        $seccionales = Seccional::query()->activas()->visiblesPara($usuario)->orderBy('id')->get();
        $seccionalId = $seccionales->firstWhere('codigo', $this->seccional)?->id;

        return view('livewire.guias.listado', [
            'portal' => $portal,
            'area' => $portal ? 'portal' : 'admin',
            'guias' => FiltroGuias::aplicar($filtros, $usuario)
                ->with(['comercio', 'ciudad', 'repartidor.user', 'ultimaVisita.causal'])
                ->orderByDesc('es_urgente')
                ->latest('fecha_registro')
                ->paginate(12),
            'conteos' => [
                '' => (int) $porEstado->sum(),
                'entregados' => $contar('entregados'),
                'en_reparto' => $contar('en_reparto'),
                'no_entregados' => $contar('no_entregados'),
                'anulados' => $contar('anulados'),
                'pendientes' => $contar('pendientes'),
                'riesgo' => (clone $base)->ansEnRiesgo()->count(),
            ],
            'seccionales' => $seccionales,
            'ciudades' => Ciudad::query()->when($seccionalId, fn ($q, $id) => $q->where('seccional_id', $id))->whereIn('seccional_id', $seccionales->pluck('id'))->orderBy('nombre')->get(['id', 'nombre']),
            'repartidores' => $portal ? collect() : Repartidor::query()->with('user')->when($seccionalId, fn ($q, $id) => $q->where('seccional_id', $id))->whereIn('seccional_id', $seccionales->pluck('id'))->get()->sortBy('nombre'),
            'consulta' => array_filter($filtros, fn (string $valor): bool => $valor !== ''),
        ])->title($portal ? 'Servicios e histórico' : 'Guías e histórico');
    }
}
