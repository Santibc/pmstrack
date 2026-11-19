<?php

namespace Database\Factories;

use App\Models\Comercio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comercio>
 */
class ComercioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_unico' => fake()->unique()->numerify('1#######'),
            'nombre' => fake()->company(),
            'cadena' => fake()->company().' S.A.S.',
        ];
    }
}
