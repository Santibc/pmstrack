<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Seguimiento;

use App\Livewire\Concerns\EligeSeccional;
use App\Livewire\Concerns\Notifica;
use App\Models\Novedad;
use App\Models\Parada;
use App\Services\Parametros;
use App\Services\Planeacion\PlaneacionService;
use App\Services\Seguimiento\SeguimientoService;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Seguimiento en vivo: avance de cada ruta, alertas automáticas y novedades
 * del día. Se actualiza solo cada 30 segundos.
 */
#[Title('Seguimiento en vivo')]
class Index extends Component
{
    use EligeSeccional, Notifica;

    /** todos | en_reparto | alerta | cerrada */
    public string $filtro = 'todos';

    public function priorizar(int $paradaId, PlaneacionService $planeacion): void
    {
        $parada = $this->paradaDeLaSeccional($paradaId);

        $this->intentar(function () use ($planeacion, $parada): void {
            $planeacion->priorizarParada($parada, auth()->user());
            $this->avisar('Quedó de primera en la ruta y el repartidor recibió el aviso.');
        });
    }

    public function atender(int $novedadId): void
    {
        $novedad = Novedad::query()
            ->whereHas('repartidor', fn ($q) => $q->where('seccional_id', $this->seccional->id))
            ->findOrFail($novedadId);

        $novedad->update(['estado' => Novedad::ATENDIDA, 'atendida_por' => auth()->id(), 'atendida_at' => now()]);
        $this->avisar('Novedad marcada como atendida.');
    }

    public function render(SeguimientoService $seguimiento, Parametros $parametros)
    {
        $tablero = $seguimiento->tablero($this->seccional, today());
        $rutas = $tablero['rutas'];

        $conteos = [
            'todos' => $rutas->count(),
            'en_reparto' => $rutas->where('estado', SeguimientoService::EN_REPARTO)->count(),
            'alerta' => $rutas->where('alerta', true)->count(),
            'cerrada' => $rutas->where('estado', SeguimientoService::CERRADA)->count(),
        ];

        return view('livewire.admin.seguimiento.index', [
            ...$tablero,
            'rutas' => match ($this->filtro) {
                'en_reparto' => $rutas->where('estado', SeguimientoService::EN_REPARTO)->values(),
                'alerta' => $rutas->where('alerta', true)->values(),
                'cerrada' => $rutas->where('estado', SeguimientoService::CERRADA)->values(),
                default => $rutas,
            },
            'conteos' => $conteos,
            'sinActividadMin' => $parametros->entero('alerta_sin_actividad_min'),
            'horaInicio' => $parametros->texto('alerta_inicio_ruta_hora'),
        ]);
    }

    private function paradaDeLaSeccional(int $paradaId): Parada
    {
        return Parada::query()
            ->whereHas('ruta', fn ($q) => $q->where('seccional_id', $this->seccional->id))
            ->findOrFail($paradaId);
    }
}
