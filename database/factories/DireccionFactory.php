<?php

namespace Database\Factories;

use App\Models\Ciudad;
use App\Models\Direccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Direccion>
 */
class DireccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $via = fake()->randomElement(['CL', 'KR']);
        $principal = sprintf('%s %d # %d - %d', $via, fake()->numberBetween(1, 190), fake()->numberBetween(1, 120), fake()->numberBetween(1, 99));

        return [
            'ciudad_id' => Ciudad::factory(),
            'clave_normalizada' => str_replace(' ', '', $principal).fake()->unique()->numerify('###'),
            'direccion' => $principal,
            'es_rural' => false,
        ];
    }
}
