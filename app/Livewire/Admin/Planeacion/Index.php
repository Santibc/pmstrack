<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Planeacion;

use App\Enums\EstadoParada;
use App\Livewire\Concerns\EligeSeccional;
use App\Livewire\Concerns\Notifica;
use App\Models\Bodega;
use App\Models\Guia;
use App\Models\Parada;
use App\Models\Planeacion;
use App\Models\Repartidor;
use App\Models\Zona;
use App\Services\Calendario\CalendarioLaboral;
use App\Services\Planeacion\PlaneacionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Planeación del día (documento técnico 4.1): cada zona se asigna a un
 * repartidor y se publica. Un repartidor puede llevar varias zonas.
 */
#[Title('Planeación del día')]
class Index extends Component
{
    use EligeSeccional, Notifica;

    #[Url(as: 'fecha', except: '')]
    public string $fecha = '';

    /** Zona cuyo detalle de guías está abierto. */
    public ?int $zonaAbierta = null;

    public bool $mostrarZona = false;

    public function mount(): void
    {
        if (! $this->fechaValida($this->fecha)) {
            $this->fecha = today()->toDateString();
        }
    }

    public function alCambiarSeccional(): void
    {
        $this->cerrarZona();
        unset($this->planeacion);
    }

    public function updatedFecha(): void
    {
        if (! $this->fechaValida($this->fecha)) {
            $this->fecha = today()->toDateString();
        }

        $this->cerrarZona();
        unset($this->planeacion);
    }

    public function irA(string $destino): void
    {
        $this->fecha = match ($destino) {
            'anterior' => $this->dia()->subDay()->toDateString(),
            'siguiente' => $this->dia()->addDay()->toDateString(),
            default => today()->toDateString(),
        };

        $this->updatedFecha();
    }

    public function asignar(int $zonaId, string $repartidorId, PlaneacionService $planeacion): void
    {
        $zona = Zona::query()->where('seccional_id', $this->seccional->id)->findOrFail($zonaId);
        $repartidor = $repartidorId === '' ? null : Repartidor::query()->with('user')->where('seccional_id', $this->seccional->id)->findOrFail((int) $repartidorId);

        $this->intentar(fn () => $planeacion->asignarZona($this->planeacion, $zona, $repartidor));
    }

    public function repetirAnterior(PlaneacionService $planeacion): void
    {
        $this->intentar(function () use ($planeacion): void {
            $zonas = $planeacion->repetirAnterior($this->planeacion);

            $this->avisar($zonas === 0
                ? 'Ninguna zona de la planeación anterior tiene guías hoy o su repartidor no está disponible.'
                : ($zonas === 1 ? 'Se repitió la asignación de 1 zona.' : "Se repitió la asignación de {$zonas} zonas."), $zonas === 0 ? 'aviso' : 'ok');
        });
    }

    public function publicar(PlaneacionService $planeacion): void
    {
        $this->intentar(function () use ($planeacion): void {
            $resultado = $planeacion->publicar($this->planeacion, auth()->user());
            unset($this->planeacion);

            $this->avisar("Publicada: {$resultado['guias']} guías y {$resultado['rollos']} rollos para {$resultado['repartidores']} ".($resultado['repartidores'] === 1 ? 'repartidor' : 'repartidores').'. Ya recibieron el aviso en la app.');
        });
    }

    public function verZona(int $zonaId): void
    {
        $this->zonaAbierta = Zona::query()->where('seccional_id', $this->seccional->id)->findOrFail($zonaId)->id;
        $this->mostrarZona = true;
    }

    public function cerrarZona(): void
    {
        $this->reset('zonaAbierta', 'mostrarZona');
    }

    /**
     * Mueve una guía suelta a otra zona, solo para esta planeación.
     */
    public function moverGuia(int $guiaId, string $zonaId, PlaneacionService $planeacion): void
    {
        if ($zonaId === '') {
            return;
        }

        $guia = Guia::query()->where('seccional_id', $this->seccional->id)->findOrFail($guiaId);
        $zona = Zona::query()->where('seccional_id', $this->seccional->id)->findOrFail((int) $zonaId);

        $this->intentar(function () use ($planeacion, $guia, $zona): void {
            $planeacion->moverGuiaDeZona($guia, $zona, auth()->user());
            $this->avisar("{$guia->nombre_comercio} pasó a {$zona->nombre_completo}.");
        });
    }

    /**
     * Reasigna una parada que ya salió a ruta a otro repartidor.
     */
    public function reasignar(int $paradaId, string $repartidorId, PlaneacionService $planeacion): void
    {
        if ($repartidorId === '') {
            return;
        }

        $parada = Parada::query()->whereHas('ruta', fn ($q) => $q->where('planeacion_id', $this->planeacion->id))->findOrFail($paradaId);
        $repartidor = Repartidor::query()->with('user')->where('seccional_id', $this->seccional->id)->findOrFail((int) $repartidorId);

        $this->intentar(function () use ($planeacion, $parada, $repartidor): void {
            $planeacion->reasignarParada($parada, $repartidor, auth()->user());
            $this->avisar("Reasignada a {$repartidor->nombre}. Ambos repartidores recibieron el aviso.");
        });
    }

    #[Computed]
    public function planeacion(): Planeacion
    {
        return app(PlaneacionService::class)->obtener($this->seccional, $this->dia());
    }

    public function dia(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha)->startOfDay();
    }

    public function render(PlaneacionService $servicio, CalendarioLaboral $calendario)
    {
        $planeacion = $this->planeacion->load('publicadaPor');
        $zonas = $servicio->zonasDelDia($planeacion);
        $carga = $servicio->cargaPorRepartidor($planeacion);
        $porDespachar = (int) $zonas->sum('rollos');
        $bodega = Bodega::query()->where('seccional_id', $this->seccional->id)->first();

        return view('livewire.admin.planeacion.index', [
            'planeacion' => $planeacion,
            'dia' => $this->dia(),
            'zonas' => $zonas,
            'carga' => $carga,
            'repartidores' => $carga->pluck('repartidor'),
            'maximaCarga' => max(1, (int) $carga->max('guias')),
            'totales' => [
                'guias' => (int) $zonas->sum('guias'),
                'rollos' => $porDespachar,
                'reprogramadas' => (int) $zonas->sum('reprogramadas'),
                'sin_repartidor' => $zonas->where('guias', '>', 0)->whereNull('repartidor_id')->count(),
                'en_ruta' => (int) $zonas->sum('en_ruta'),
                'por_publicar' => (int) $zonas->whereNotNull('repartidor_id')->sum('guias'),
            ],
            'bodega' => $bodega,
            'faltanRollos' => $bodega !== null && $porDespachar > $bodega->saldo,
            'esHabil' => $calendario->esHabil($this->dia()),
            'detalle' => $this->zonaAbierta ? $this->detalleZona($servicio, $planeacion) : null,
        ]);
    }

    /**
     * Guías de la zona abierta: las que faltan por publicar y las que ya están en ruta.
     *
     * @return array{zona: Zona, por_publicar: Collection, en_ruta: Collection}
     */
    private function detalleZona(PlaneacionService $servicio, Planeacion $planeacion): array
    {
        return [
            'zona' => Zona::query()->findOrFail($this->zonaAbierta),
            'por_publicar' => $servicio->guiasPlaneables($planeacion)
                ->where('zona_id', $this->zonaAbierta)
                ->orderByDesc('es_urgente')->orderBy('ans_vence_at')
                ->get(),
            'en_ruta' => Parada::query()
                ->with(['guia', 'ruta.repartidor.user'])
                ->where('zona_id', $this->zonaAbierta)
                ->whereIn('estado', EstadoParada::activas())
                ->whereHas('ruta', fn ($q) => $q->where('planeacion_id', $planeacion->id))
                ->enOrden()
                ->get(),
        ];
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
