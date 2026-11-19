<?php

namespace App\Http\Resources;

use App\Models\Parada;
use App\Support\Telefono;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una parada de la ruta con todo lo que el repartidor necesita en la calle.
 *
 * @mixin Parada
 */
class ParadaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guia = $this->guia;
        $visita = $this->relationLoaded('visita') ? $this->visita : null;

        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'es_nueva' => $this->es_nueva,
            'estado' => $this->estado->value,
            'visitada_at' => $this->visitada_at?->toIso8601String(),
            'zona' => $this->zona === null ? null : [
                'id' => $this->zona->id,
                'codigo' => $this->zona->codigo,
                'nombre' => $this->zona->nombre,
                'color' => $this->zona->color,
            ],
            'guia' => [
                'id' => $guia->id,
                'servicio' => $guia->servicio,
                'numero_guia' => $guia->numero_guia,
                'comercio' => $guia->nombre_comercio,
                'direccion' => $guia->direccion_normalizada,
                'complemento' => $guia->complemento,
                'direccion_original' => $guia->direccion_original,
                'ciudad' => $guia->ciudad?->nombre,
                'rollos' => $guia->rollos_solicitados,
                'es_urgente' => $guia->es_urgente,
                'ans_horas' => $guia->ans_horas,
                'ans_vence_at' => $guia->ans_vence_at->toIso8601String(),
                'horario' => $guia->horario_comercio,
                'contacto_nombre' => $guia->contacto_nombre,
                'contacto_telefono' => Telefono::limpiar($guia->telefonoDeContacto()),
                'observaciones' => $guia->observaciones,
                'intentos' => $guia->intentos,
            ],
            'visita' => $visita === null ? null : [
                'uuid' => $visita->uuid,
                'resultado' => $visita->resultado->value,
                'causal' => $visita->causal?->nombre,
                'observacion' => $visita->observacion,
                'rollos_entregados' => $visita->rollos_entregados,
                'registrada_at' => $visita->registrada_at->toIso8601String(),
            ],
        ];
    }
}
