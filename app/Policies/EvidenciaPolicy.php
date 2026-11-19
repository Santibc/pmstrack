<?php

namespace App\Policies;

use App\Models\Evidencia;
use App\Models\User;

class EvidenciaPolicy
{
    /**
     * La evidencia la ven quienes consultan guías en PMS (dentro de su
     * seccional) y los usuarios autorizados de Credibanco.
     */
    public function ver(User $usuario, Evidencia $evidencia): bool
    {
        if ($usuario->can('portal.ver')) {
            return true;
        }

        return $usuario->can('guias.ver')
            && $usuario->puedeVerSeccional($evidencia->visita->guia->seccional_id);
    }
}
