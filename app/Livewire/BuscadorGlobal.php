<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Queries\FiltroGuias;
use App\Support\Navegacion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Buscador global (Ctrl/⌘ + K): encuentra una guía por servicio, guía PMS,
 * código único, comercio o dirección, y salta a cualquier pantalla.
 */
class BuscadorGlobal extends Component
{
    public string $termino = '';

    #[Locked]
    public bool $portal = false;

    public function mount(): void
    {
        $this->portal = request()->routeIs('portal.*');
    }

    public function render(Navegacion $navegacion)
    {
        $usuario = auth()->user();
        $termino = trim($this->termino);
        $area = $this->portal ? 'portal' : 'admin';

        $guias = mb_strlen($termino) >= 2
            ? FiltroGuias::aplicar(['q' => $termino], $usuario)->with(['comercio', 'ciudad'])->latest('fecha_registro')->limit(7)->get()
            : new Collection;

        $pantallas = collect($this->portal ? $navegacion->portal($usuario) : $navegacion->administracion($usuario))
            ->filter(fn (array $item): bool => $termino === '' || str_contains(mb_strtolower($item['etiqueta']), mb_strtolower($termino)))
            ->take($termino === '' ? 10 : 4)
            ->values();

        return view('livewire.buscador-global', [
            'guias' => $guias,
            'pantallas' => $pantallas,
            'area' => $area,
            'buscando' => mb_strlen($termino) >= 2,
        ]);
    }
}
