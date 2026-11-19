<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Cargas;

use App\Enums\EstadoCarga;
use App\Enums\ResultadoFila;
use App\Livewire\Concerns\Notifica;
use App\Models\Carga;
use App\Models\CargaFila;
use App\Services\Cargas\AnalizadorFilas;
use App\Services\Cargas\CargaService;
use App\Services\Cargas\LectorExcelCredibanco;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Carga del Excel diario de Credibanco en tres pasos: subir, revisar la
 * validación y confirmar (documento técnico 4.1 y 5.1).
 */
#[Title('Cargar archivo')]
class Cargar extends Component
{
    use Notifica, WithFileUploads, WithPagination;

    private const array PRIORIDAD_ALERTAS = [
        AnalizadorFilas::ALERTA_CIUDAD => 1,
        AnalizadorFilas::ALERTA_RURAL => 2,
        AnalizadorFilas::ALERTA_TELEFONO => 3,
        AnalizadorFilas::ALERTA_ROLLOS => 4,
        AnalizadorFilas::ALERTA_CIUDAD_NUEVA => 5,
    ];

    public $archivo;

    #[Url(as: 'carga', except: null)]
    public ?int $cargaId = null;

    /** todas | alertas | zonificar | duplicados | errores */
    public string $filtro = 'todas';

    public bool $verTodasLasAlertas = false;

    public function mount(): void
    {
        if ($this->cargaId !== null && ! Carga::query()->whereKey($this->cargaId)->exists()) {
            $this->cargaId = null;
        }
    }

    public function updatedArchivo(CargaService $cargas): void
    {
        $this->validate(
            ['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']],
            ['archivo.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls) o un CSV.', 'archivo.max' => 'El archivo no puede pesar más de 10 MB.'],
        );

        $completada = $this->intentar(function () use ($cargas): void {
            $this->cargaId = $cargas->procesar($this->archivo, auth()->user())->id;
        });

        $this->reset('archivo', 'filtro', 'verTodasLasAlertas');
        $this->resetPage();

        if ($completada) {
            unset($this->carga);
        }
    }

    public function confirmar(CargaService $cargas): void
    {
        $this->intentar(function () use ($cargas): void {
            $carga = $cargas->confirmar($this->carga, auth()->user());
            unset($this->carga);

            $this->avisar($carga->nuevos === 1 ? 'Se cargó 1 servicio nuevo.' : "Se cargaron {$carga->nuevos} servicios nuevos.");
        });
    }

    /**
     * Descarta la carga en revisión para subir otro archivo.
     */
    public function cancelar(CargaService $cargas): void
    {
        if ($this->carga?->estaPendiente()) {
            $this->intentar(fn () => $cargas->cancelar($this->carga, auth()->user()));
        }

        $this->nueva();
    }

    public function nueva(): void
    {
        $this->reset('cargaId', 'archivo', 'filtro', 'verTodasLasAlertas');
        $this->resetPage();
        unset($this->carga);
    }

    public function abrir(int $cargaId): void
    {
        $this->cargaId = $cargaId;
        $this->reset('filtro', 'verTodasLasAlertas');
        $this->resetPage();
        unset($this->carga);
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function carga(): ?Carga
    {
        return $this->cargaId ? Carga::query()->with('user')->find($this->cargaId) : null;
    }

    /**
     * Paso actual del asistente: 1 subir, 2 revisar, 3 confirmada.
     */
    public function paso(): int
    {
        return match ($this->carga?->estado) {
            EstadoCarga::Pendiente => 2,
            EstadoCarga::Confirmada => 3,
            default => 1,
        };
    }

    public function render()
    {
        $carga = $this->carga;

        return view('livewire.admin.cargas.cargar', [
            'carga' => $carga,
            'paso' => $this->paso(),
            'filas' => $carga ? $this->filas($carga) : null,
            'alertas' => $carga ? $this->alertas($carga) : collect(),
            'conteos' => $carga ? $this->conteos($carga) : [],
            'columnas' => array_keys(LectorExcelCredibanco::COLUMNAS),
            'historial' => Carga::query()
                ->with('user')
                ->where('estado', EstadoCarga::Confirmada)
                ->latest('confirmada_at')
                ->limit($carga ? 5 : 12)
                ->get(),
            'pendientes' => Carga::query()->where('estado', EstadoCarga::Pendiente)->when($carga, fn (Builder $q) => $q->whereKeyNot($carga->id))->latest()->limit(3)->get(),
        ]);
    }

    private function filas(Carga $carga)
    {
        return $carga->filas()
            ->with('zona')
            ->when($this->filtro === 'alertas', fn (Builder $q) => $q->where('resultado', ResultadoFila::Nuevo)->whereNotNull('alertas'))
            ->when($this->filtro === 'zonificar', fn (Builder $q) => $q->where('resultado', ResultadoFila::Nuevo)->whereNull('zona_id'))
            ->when($this->filtro === 'duplicados', fn (Builder $q) => $q->where('resultado', ResultadoFila::Duplicado))
            ->when($this->filtro === 'errores', fn (Builder $q) => $q->where('resultado', ResultadoFila::Error))
            ->paginate(12);
    }

    /**
     * Alertas de datos de las filas que sí se cargan, listas para leer: "Fila 8: …".
     */
    private function alertas(Carga $carga)
    {
        return $carga->filas()
            ->where('resultado', ResultadoFila::Nuevo)
            ->whereNotNull('alertas')
            ->get(['id', 'numero_fila', 'alertas'])
            ->flatMap(fn (CargaFila $fila) => collect($fila->alertas)->map(fn (array $alerta): array => [
                'fila' => $fila->numero_fila,
                'mensaje' => $alerta['mensaje'],
                'codigo' => $alerta['codigo'],
            ]))
            // Primero lo que más afecta la operación: ciudad, zona rural y teléfono.
            ->sortBy(fn (array $alerta): int => self::PRIORIDAD_ALERTAS[$alerta['codigo']] ?? 9)
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function conteos(Carga $carga): array
    {
        return [
            'todas' => $carga->total_filas,
            'alertas' => $carga->con_alertas,
            'zonificar' => $carga->por_zonificar,
            'duplicados' => $carga->duplicados,
            'errores' => $carga->errores,
        ];
    }
}
