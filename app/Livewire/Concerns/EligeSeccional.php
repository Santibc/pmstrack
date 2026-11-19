<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Seccional;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Selector de seccional de las pantallas de operación. Un coordinador de
 * seccional queda fijo en la suya.
 */
trait EligeSeccional
{
    #[Url(as: 'seccional', except: '')]
    public string $seccionalCodigo = '';

    public function mountEligeSeccional(): void
    {
        $disponibles = $this->seccionales();

        if (! $disponibles->contains('codigo', $this->seccionalCodigo)) {
            $propia = auth()->user()->seccional_id;

            $this->seccionalCodigo = ($disponibles->firstWhere('id', $propia) ?? $disponibles->first())?->codigo ?? '';
        }
    }

    /**
     * @return Collection<int, Seccional>
     */
    #[Computed]
    public function seccionales(): Collection
    {
        return Seccional::query()->activas()->visiblesPara(auth()->user())->orderBy('id')->get();
    }

    #[Computed]
    public function seccional(): Seccional
    {
        return $this->seccionales()->firstWhere('codigo', $this->seccionalCodigo) ?? abort(403, 'No tienes acceso a esta seccional.');
    }

    public function updatedSeccionalCodigo(): void
    {
        unset($this->seccional);

        if (method_exists($this, 'alCambiarSeccional')) {
            $this->alCambiarSeccional();
        }
    }
}
