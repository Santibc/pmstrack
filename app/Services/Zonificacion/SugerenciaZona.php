<?php

declare(strict_types=1);

namespace App\Services\Zonificacion;

use App\Models\Direccion;
use App\Models\Zona;

/**
 * Sugiere la zona de una dirección nueva a partir de las direcciones ya
 * zonificadas sobre la misma vía: "KR 7 # 61 - 48" probablemente va en la
 * misma zona que "KR 7 # 63 - 10".
 *
 * Es solo una ayuda: la administradora confirma con un clic o elige otra zona.
 */
final class SugerenciaZona
{
    /** Diferencia máxima entre los cruces para considerar que dos direcciones son vecinas. */
    private const int CUADRAS = 12;

    /**
     * @return array{zona: Zona, referencia: string}|null
     */
    public function para(Direccion $direccion): ?array
    {
        $partes = $this->partes($direccion->clave_normalizada);

        if ($partes === null) {
            return null;
        }

        [$via, $cruce] = $partes;

        $vecina = Direccion::query()
            ->with('zona')
            ->where('ciudad_id', $direccion->ciudad_id)
            ->whereKeyNot($direccion->id)
            ->whereNotNull('zona_id')
            ->where('clave_normalizada', 'like', str_replace(' ', '', $via).'#%')
            ->limit(300)
            ->get()
            ->filter(fn (Direccion $otra): bool => $otra->zona?->activo ?? false)
            ->map(fn (Direccion $otra): array => ['direccion' => $otra, 'distancia' => abs(($this->partes($otra->clave_normalizada)[1] ?? PHP_INT_MAX) - $cruce)])
            ->sortBy('distancia')
            ->first();

        if ($vecina === null || $vecina['distancia'] > self::CUADRAS) {
            return null;
        }

        return [
            'zona' => $vecina['direccion']->zona,
            'referencia' => $vecina['direccion']->direccion,
        ];
    }

    /**
     * Clave "KR7#61-48" → ["KR7", 61]. Null si la dirección no tiene nomenclatura.
     *
     * @return array{0: string, 1: int}|null
     */
    private function partes(string $direccion): ?array
    {
        if (! preg_match('/^(?<via>[A-Z]+[0-9A-Z]+?)#(?<cruce>\d+)/', $direccion, $m)) {
            return null;
        }

        return [$m['via'], (int) $m['cruce']];
    }
}
