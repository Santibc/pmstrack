<?php

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Seccional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bodega>
 */
class BodegaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccional_id' => Seccional::factory(),
            'nombre' => 'Bodega '.fake()->city(),
            'stock_minimo' => 1000,
        ];
    }
}
