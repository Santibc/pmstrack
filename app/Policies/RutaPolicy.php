<?php

namespace App\Policies;

use App\Models\Ruta;
use App\Models\User;

class RutaPolicy
{
    /**
     * El repartidor solo opera su propia ruta.
     */
    public function operar(User $usuario, Ruta $ruta): bool
    {
        return $usuario->repartidor !== null && $usuario->repartidor->id === $ruta->repartidor_id;
    }
}
