<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Queries\FiltroGuias;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Consulta rápida de Credibanco: un dato basta (servicio, código único o
 * comercio) y muestra la evidencia completa, sin enmascarar.
 */
#[Title('Consulta rápida')]
class ConsultaRapida extends Component
{
    #[Url(as: 'q', except: '')]
    public string $termino = '';

    public function render()
    {
        $termino = trim($this->termino);

        return view('livewire.portal.consulta-rapida', [
            'guias' => mb_strlen($termino) >= 3
                ? FiltroGuias::aplicar(['q' => $termino], auth()->user())->with(['comercio', 'ciudad', 'ultimaVisita.causal'])->latest('fecha_registro')->limit(12)->get()
                : new Collection,
            'buscando' => mb_strlen($termino) >= 3,
        ]);
    }
}
