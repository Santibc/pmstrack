<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Exceptions\ArchivoInvalido;
use App\Exceptions\OperacionNoPermitida;
use Closure;

/**
 * Avisos breves en pantalla y manejo uniforme de las reglas de negocio que
 * impiden una operación.
 */
trait Notifica
{
    protected function avisar(string $mensaje, string $tipo = 'ok'): void
    {
        $this->dispatch('notificar', tipo: $tipo, mensaje: $mensaje);
    }

    /**
     * Ejecuta la acción; si una regla de negocio la impide, muestra el motivo.
     *
     * @return bool La acción se completó.
     */
    protected function intentar(Closure $accion): bool
    {
        try {
            $accion();

            return true;
        } catch (OperacionNoPermitida|ArchivoInvalido $error) {
            $this->avisar($error->getMessage(), 'error');

            return false;
        }
    }
}
