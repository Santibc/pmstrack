<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoEvento;
use App\Models\Guia;
use App\Models\GuiaEvento;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Bitácora de trazabilidad de las guías: cada cambio queda con quién, qué y cuándo.
 */
final class Bitacora
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function registrar(
        Guia $guia,
        TipoEvento $tipo,
        string $titulo,
        ?string $detalle = null,
        ?User $usuario = null,
        ?CarbonInterface $ocurridoAt = null,
        array $meta = [],
        bool $actualizarUltimoMovimiento = true,
    ): GuiaEvento {
        $ocurridoAt ??= now();

        $evento = $guia->eventos()->create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'user_id' => $usuario?->id,
            'meta' => $meta === [] ? null : $meta,
            'ocurrido_at' => $ocurridoAt,
        ]);

        if ($actualizarUltimoMovimiento) {
            $guia->forceFill([
                'ultimo_movimiento' => $titulo,
                'ultimo_movimiento_at' => $ocurridoAt,
            ])->save();
        }

        return $evento;
    }
}
