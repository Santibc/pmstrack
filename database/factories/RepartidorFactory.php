<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Repartidor;
use App\Models\Seccional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<Repartidor>
 */
class RepartidorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seccional_id' => Seccional::factory(),
            'cedula' => fake()->unique()->numerify('10########'),
            'celular' => fake()->numerify('3#########'),
            'vehiculo_tipo' => 'Moto',
            'placa' => fake()->bothify('???##?'),
            'disponible' => true,
            'activo' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Repartidor $repartidor): void {
            if (Role::query()->where('name', Rol::Repartidor->value)->exists()) {
                $repartidor->user->assignRole(Rol::Repartidor->value);
            }
        });
    }
}
