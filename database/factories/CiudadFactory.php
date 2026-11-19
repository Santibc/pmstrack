<?php

namespace Database\Factories;

use App\Models\Ciudad;
use App\Models\Seccional;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciudad>
 */
class CiudadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->city();

        return [
            'seccional_id' => Seccional::factory(),
            'nombre' => $nombre,
            'nombre_normalizado' => Texto::claveLugar($nombre),
            'departamento' => 'Cundinamarca',
            'departamento_normalizado' => 'CUNDINAMARCA',
            'activo' => true,
        ];
    }
}
