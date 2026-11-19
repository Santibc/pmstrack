<?php

namespace App\Policies;

use App\Models\Parada;
use App\Models\User;

class ParadaPolicy
{
    /**
     * El repartidor solo registra visitas de las paradas de su ruta.
     */
    public function registrarVisita(User $usuario, Parada $parada): bool
    {
        return $usuario->repartidor !== null && $usuario->repartidor->id === $parada->ruta->repartidor_id;
    }
}
