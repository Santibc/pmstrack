<?php

namespace Database\Factories;

use App\Enums\TipoCausal;
use App\Models\Causal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Causal>
 */
class CausalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoCausal::NoEntregado,
            'codigo' => fake()->unique()->slug(2),
            'nombre' => fake()->sentence(3),
            'afecta_calidad_datos' => false,
            'orden' => 1,
            'activo' => true,
        ];
    }

    public function anulado(): static
    {
        return $this->state(fn (): array => ['tipo' => TipoCausal::Anulado]);
    }
}
