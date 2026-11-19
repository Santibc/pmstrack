<?php

namespace Database\Factories;

use App\Models\Seccional;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zona>
 */
class ZonaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccional_id' => Seccional::factory(),
            'codigo' => 'Z'.fake()->unique()->numerify('##'),
            'nombre' => fake()->streetName(),
            'color' => fake()->hexColor(),
            'orden' => fake()->numberBetween(1, 20),
            'activo' => true,
        ];
    }
}
