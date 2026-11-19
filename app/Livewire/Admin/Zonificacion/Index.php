<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Zonificacion;

use App\Enums\EstadoGuia;
use App\Livewire\Concerns\EligeSeccional;
use App\Livewire\Concerns\Notifica;
use App\Models\Direccion;
use App\Models\Zona;
use App\Services\Zonificacion\SugerenciaZona;
use App\Services\Zonificacion\ZonificacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Zonificación de direcciones (documento técnico 4.1 y 5.2): la administradora
 * elige la zona de cada dirección nueva una sola vez y el sistema la recuerda.
 */
#[Title('Zonificación')]
class Index extends Component
{
    use EligeSeccional, Notifica, WithPagination;

    /** Colores sugeridos para las zonas nuevas. */
    public const array COLORES = ['#F07D2E', '#3B6FE0', '#8B6FE8', '#2FA455', '#E5A30B', '#E5527A', '#12A5A0', '#6B7482', '#B4532A', '#2B8AC6'];

    /** pendientes | directorio | zonas */
    #[Url(as: 'ver', except: 'pendientes')]
    public string $pestana = 'pendientes';

    /** @var list<int> Direcciones marcadas para asignación masiva. */
    public array $seleccionadas = [];

    public ?int $zonaMasiva = null;

    // Directorio.
    public string $buscar = '';

    public string $filtroZona = '';

    // Formulario de zona.
    public bool $mostrarZona = false;

    public ?int $zonaId = null;

    public string $zonaCodigo = '';

    public string $zonaNombre = '';

    public string $zonaColor = '#F07D2E';

    public function alCambiarSeccional(): void
    {
        $this->reset('seleccionadas', 'zonaMasiva', 'buscar', 'filtroZona');
        $this->resetPage();
        unset($this->zonas);
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['pestana', 'buscar', 'filtroZona'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Elegir la zona de una dirección la guarda de inmediato.
     */
    public function asignar(int $direccionId, ?int $zonaId, ZonificacionService $zonificacion): void
    {
        if (! $zonaId) {
            return;
        }

        $direccion = Direccion::query()->deSeccional($this->seccional->id)->findOrFail($direccionId);
        $zona = $this->zonas->firstWhere('id', $zonaId) ?? abort(404);

        $this->intentar(function () use ($zonificacion, $direccion, $zona): void {
            $liberadas = $zonificacion->asignarZona($direccion, $zona, auth()->user());
            $this->seleccionadas = array_values(array_diff($this->seleccionadas, [$direccion->id]));

            $this->avisar($liberadas > 0
                ? "Guardada en {$zona->nombre_completo}. ".($liberadas === 1 ? '1 guía quedó lista' : "{$liberadas} guías quedaron listas").' para planear.'
                : "Guardada en {$zona->nombre_completo}.");
        });
    }

    public function asignarSeleccionadas(ZonificacionService $zonificacion): void
    {
        $zona = $this->zonas->firstWhere('id', $this->zonaMasiva);

        if ($zona === null || $this->seleccionadas === []) {
            $this->avisar('Marca las direcciones y elige la zona que les quieres asignar.', 'aviso');

            return;
        }

        $ids = Direccion::query()->deSeccional($this->seccional->id)->whereKey($this->seleccionadas)->pluck('id')->all();

        $this->intentar(function () use ($zonificacion, $ids, $zona): void {
            $total = $zonificacion->asignarMasivo($ids, $zona, auth()->user());
            $this->reset('seleccionadas', 'zonaMasiva');
            $this->avisar($total === 1 ? "1 dirección guardada en {$zona->nombre_completo}." : "{$total} direcciones guardadas en {$zona->nombre_completo}.");
        });
    }

    public function nuevaZona(): void
    {
        $siguiente = $this->zonas->count() + 1;

        $this->resetValidation();
        $this->zonaId = null;
        $this->zonaCodigo = 'Z'.str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT);
        $this->zonaNombre = '';
        $this->zonaColor = self::COLORES[($siguiente - 1) % count(self::COLORES)];
        $this->mostrarZona = true;
    }

    public function editarZona(int $zonaId): void
    {
        $zona = Zona::query()->where('seccional_id', $this->seccional->id)->findOrFail($zonaId);

        $this->resetValidation();
        $this->zonaId = $zona->id;
        $this->zonaCodigo = $zona->codigo;
        $this->zonaNombre = $zona->nombre;
        $this->zonaColor = $zona->color;
        $this->mostrarZona = true;
    }

    public function guardarZona(): void
    {
        Gate::authorize('configuracion.gestionar');

        $datos = $this->validate([
            'zonaCodigo' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('zonas', 'codigo')->where('seccional_id', $this->seccional->id)->ignore($this->zonaId)],
            'zonaNombre' => ['required', 'string', 'max:80'],
            'zonaColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], attributes: ['zonaCodigo' => 'código', 'zonaNombre' => 'nombre', 'zonaColor' => 'color']);

        Zona::query()->updateOrCreate(
            ['id' => $this->zonaId],
            [
                'seccional_id' => $this->seccional->id,
                'codigo' => strtoupper($datos['zonaCodigo']),
                'nombre' => trim($datos['zonaNombre']),
                'color' => strtoupper($datos['zonaColor']),
                'orden' => $this->zonaId ? Zona::query()->whereKey($this->zonaId)->value('orden') : $this->zonas->max('orden') + 1,
            ],
        );

        $this->mostrarZona = false;
        unset($this->zonas);
        $this->avisar('Zona guardada.');
    }

    public function alternarZona(int $zonaId): void
    {
        Gate::authorize('configuracion.gestionar');

        $zona = Zona::query()->where('seccional_id', $this->seccional->id)->findOrFail($zonaId);
        $zona->update(['activo' => ! $zona->activo]);

        unset($this->zonas);
        $this->avisar($zona->activo ? "{$zona->nombre_completo} quedó activa." : "{$zona->nombre_completo} quedó inactiva: ya no se asigna a direcciones nuevas.");
    }

    /**
     * @return Collection<int, Zona>
     */
    #[Computed]
    public function zonas(): Collection
    {
        return Zona::query()->where('seccional_id', $this->seccional->id)->activas()->withCount('direcciones')->orderBy('orden')->orderBy('codigo')->get();
    }

    public function render(ZonificacionService $zonificacion, SugerenciaZona $sugerencias)
    {
        $seccional = $this->seccional;
        $pendientes = $this->pestana === 'pendientes' ? $this->pendientes($zonificacion) : null;

        return view('livewire.admin.zonificacion.index', [
            'pendientes' => $pendientes,
            'sugeridas' => $pendientes ? $pendientes->getCollection()->mapWithKeys(fn (Direccion $d): array => [$d->id => $sugerencias->para($d)])->filter() : collect(),
            'directorio' => $this->pestana === 'directorio' ? $this->directorio() : null,
            'todasLasZonas' => $this->pestana === 'zonas' ? Zona::query()->where('seccional_id', $seccional->id)->withCount('direcciones')->orderBy('orden')->orderBy('codigo')->get() : null,
            'totalPendientes' => $zonificacion->totalPendientes($seccional->id),
            'totalDirectorio' => Direccion::query()->deSeccional($seccional->id)->count(),
            'automatico' => $zonificacion->porcentajeAutomatico(now()->startOfMonth(), now()->endOfMonth(), $seccional->id),
        ]);
    }

    private function pendientes(ZonificacionService $zonificacion)
    {
        return $zonificacion->pendientes($this->seccional->id)
            ->with([
                'ciudad',
                'guias' => fn ($q) => $q->where('estado', EstadoGuia::PorZonificar)->with('comercio')->orderByDesc('es_urgente')->orderBy('ans_vence_at'),
            ])
            ->orderBy('direccion')
            ->paginate(12, pageName: 'pendientes');
    }

    private function directorio()
    {
        return Direccion::query()
            ->deSeccional($this->seccional->id)
            ->with(['ciudad', 'zona'])
            ->withCount('guias')
            ->when($this->buscar !== '', fn (Builder $q) => $q->where(function (Builder $w): void {
                $termino = '%'.strtoupper(trim($this->buscar)).'%';
                $w->where('direccion', 'like', $termino)->orWhere('clave_normalizada', 'like', str_replace(' ', '', $termino));
            }))
            ->when($this->filtroZona === 'sin', fn (Builder $q) => $q->whereNull('zona_id'))
            ->when(is_numeric($this->filtroZona), fn (Builder $q) => $q->where('zona_id', (int) $this->filtroZona))
            ->orderBy('direccion')
            ->paginate(15, pageName: 'directorio');
    }
}
