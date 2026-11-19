<?php

namespace App\Http\Resources;

use App\Enums\ResultadoVisita;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil del repartidor con sus indicadores del mes (pantalla A8 de la app).
 *
 * @mixin User
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $repartidor = $this->repartidor;

        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'email' => $this->email,
            'iniciales' => $this->iniciales(),
            'repartidor' => $repartidor === null ? null : [
                'id' => $repartidor->id,
                'cedula' => $repartidor->cedula,
                'celular' => $repartidor->celular,
                'vehiculo' => $repartidor->vehiculo,
                'seccional' => $repartidor->seccional?->only(['id', 'nombre']),
                'zona_habitual' => $repartidor->zonaHabitual === null ? null : [
                    'id' => $repartidor->zonaHabitual->id,
                    'codigo' => $repartidor->zonaHabitual->codigo,
                    'nombre' => $repartidor->zonaHabitual->nombre,
                ],
            ],
            'indicadores' => $this->when($repartidor !== null, fn (): array => $this->indicadoresDelMes($repartidor->id)),
        ];
    }

    /**
     * @return array{entregas_mes: int, efectividad: int|null, rollos_mes: int}
     */
    private function indicadoresDelMes(int $repartidorId): array
    {
        $visitas = Visita::query()
            ->vigentes()
            ->where('repartidor_id', $repartidorId)
            ->whereBetween('registrada_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN resultado = ? THEN 1 ELSE 0 END) as entregas', [ResultadoVisita::Entregado->value])
            ->selectRaw('SUM(rollos_entregados) as rollos')
            ->toBase()
            ->first();

        $total = (int) ($visitas->total ?? 0);
        $entregas = (int) ($visitas->entregas ?? 0);

        return [
            'entregas_mes' => $entregas,
            'efectividad' => $total > 0 ? (int) round($entregas * 100 / $total) : null,
            'rollos_mes' => (int) ($visitas->rollos ?? 0),
        ];
    }
}
