<?php

namespace Database\Factories;

use App\Models\Seccional;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seccional>
 */
class SeccionalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->city();

        return [
            'codigo' => fake()->unique()->lexify('???'),
            'nombre' => $nombre,
            'nombre_normalizado' => Texto::claveLugar($nombre),
            'activo' => true,
        ];
    }
}
