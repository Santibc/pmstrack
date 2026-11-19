<?php

declare(strict_types=1);

namespace App\Livewire\Guias;

use App\Enums\EstadoGuia;
use App\Enums\TipoCausal;
use App\Livewire\Concerns\Notifica;
use App\Models\Causal;
use App\Models\Guia;
use App\Models\Visita;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Guias\GuiaService;
use App\Services\Guias\LineaDeTiempo;
use App\Services\Parametros;
use App\Services\Visitas\VisitaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de un servicio con su evidencia y trazabilidad completa. PMS ve
 * además las acciones de operación; Credibanco lo ve en solo lectura.
 */
class Detalle extends Component
{
    use Notifica;

    #[Locked]
    public int $guiaId;

    #[Locked]
    public bool $portal = false;

    public bool $mostrarReprogramar = false;

    public string $reprogramarFecha = '';

    public string $reprogramarNota = '';

    public bool $mostrarAnular = false;

    public string $anularCausal = '';

    public string $anularObservacion = '';

    public bool $mostrarDevolver = false;

    public string $devolverObservacion = '';

    public bool $mostrarAnularVisita = false;

    public ?int $visitaId = null;

    public string $anularVisitaMotivo = '';

    public function mount(Guia $guia): void
    {
        abort_unless(auth()->user()->puedeVerSeccional($guia->seccional_id), 403);

        $this->guiaId = $guia->id;
        $this->portal = request()->routeIs('portal.*');
    }

    public function abrirReprogramar(CalendarioLaboral $calendario): void
    {
        $this->resetValidation();
        $this->reprogramarFecha = $calendario->siguienteDiaHabil(today()->subDay())->toDateString();
        $this->reprogramarNota = '';
        $this->mostrarReprogramar = true;
    }

    public function reprogramar(GuiaService $guias): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'reprogramarFecha' => ['required', 'date', 'after_or_equal:today'],
            'reprogramarNota' => ['nullable', 'string', 'max:250'],
        ], attributes: ['reprogramarFecha' => 'fecha', 'reprogramarNota' => 'nota']);

        $this->intentar(function () use ($guias, $datos): void {
            $guias->reprogramar($this->guia(), CarbonImmutable::parse($datos['reprogramarFecha']), auth()->user(), $datos['reprogramarNota'] ?: null);
            $this->mostrarReprogramar = false;
            $this->avisar('Guía reprogramada. Aparecerá en la planeación de ese día.');
        });
    }

    public function anular(GuiaService $guias): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'anularCausal' => ['required', 'exists:causales,id'],
            'anularObservacion' => ['required', 'string', 'min:5', 'max:1000'],
        ], attributes: ['anularCausal' => 'motivo', 'anularObservacion' => 'observación']);

        $this->intentar(function () use ($guias, $datos): void {
            $guias->anular($this->guia(), Causal::query()->findOrFail($datos['anularCausal']), trim($datos['anularObservacion']), auth()->user());
            $this->mostrarAnular = false;
            $this->reset('anularCausal', 'anularObservacion');
            $this->avisar('Guía anulada. Quedó en la bitácora con el motivo.');
        });
    }

    public function devolver(GuiaService $guias): void
    {
        $this->autorizar();

        $datos = $this->validate(
            ['devolverObservacion' => ['required', 'string', 'min:5', 'max:1000']],
            attributes: ['devolverObservacion' => 'observación'],
        );

        $this->intentar(function () use ($guias, $datos): void {
            $guias->devolverACredibanco($this->guia(), trim($datos['devolverObservacion']), auth()->user());
            $this->mostrarDevolver = false;
            $this->reset('devolverObservacion');
            $this->avisar('Servicio devuelto a Credibanco.');
        });
    }

    public function abrirAnularVisita(int $visitaId): void
    {
        $this->resetValidation();
        $this->visitaId = $visitaId;
        $this->anularVisitaMotivo = '';
        $this->mostrarAnularVisita = true;
    }

    public function anularVisita(VisitaService $visitas): void
    {
        $this->autorizar();

        $datos = $this->validate(
            ['anularVisitaMotivo' => ['required', 'string', 'min:5', 'max:250']],
            attributes: ['anularVisitaMotivo' => 'justificación'],
        );

        $visita = Visita::query()->where('guia_id', $this->guiaId)->findOrFail($this->visitaId);

        $this->intentar(function () use ($visitas, $visita, $datos): void {
            $visitas->anular($visita, auth()->user(), trim($datos['anularVisitaMotivo']));
            $this->mostrarAnularVisita = false;
            $this->avisar('Visita anulada. La guía volvió a quedar pendiente.');
        });
    }

    public function render(LineaDeTiempo $linea, Parametros $parametros)
    {
        $guia = Guia::query()
            ->with([
                'comercio', 'ciudad', 'seccional', 'zona', 'repartidor.user', 'carga',
                'eventos.user',
                'visitas' => fn ($q) => $q->with(['causal', 'evidencias', 'repartidor.user', 'anuladaPor'])->latest('registrada_at'),
                'ultimaVisita.causal', 'ultimaVisita.evidencias', 'ultimaVisita.repartidor.user',
            ])
            ->findOrFail($this->guiaId);

        $gestiona = ! $this->portal && auth()->user()->can('guias.gestionar');

        return view('livewire.guias.detalle', [
            'guia' => $guia,
            'visita' => $guia->ultimaVisita,
            'pasos' => $linea->pasos($guia),
            'area' => $this->portal ? 'portal' : 'admin',
            'gestiona' => $gestiona,
            'puede' => [
                'reprogramar' => $gestiona && in_array($guia->estado, [EstadoGuia::NoEntregada, EstadoGuia::ListaParaAsignar], true),
                'anular' => $gestiona && ! $guia->estado->esFinal(),
                'devolver' => $gestiona && $guia->estado === EstadoGuia::NoEntregada,
            ],
            'causalesAnulacion' => $gestiona ? Causal::query()->where('tipo', TipoCausal::Anulado)->activas()->get() : collect(),
            'maxIntentos' => $parametros->maxIntentos(),
        ])->title('Servicio '.$guia->servicio);
    }

    private function guia(): Guia
    {
        return Guia::query()->findOrFail($this->guiaId);
    }

    private function autorizar(): void
    {
        abort_if($this->portal, 403);
        Gate::authorize('guias.gestionar');
    }
}
