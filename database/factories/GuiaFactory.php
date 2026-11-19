<?php

namespace Database\Factories;

use App\Enums\EstadoGuia;
use App\Models\Comercio;
use App\Models\Direccion;
use App\Models\Guia;
use App\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guia>
 */
class GuiaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registro = now()->subHours(fake()->numberBetween(2, 30));

        return [
            'servicio' => fake()->unique()->numerify('39#####'),
            'numero_guia' => 'TST-D-'.fake()->unique()->numerify('#####'),
            'comercio_id' => Comercio::factory(),
            'direccion_id' => Direccion::factory(),
            'ciudad_id' => fn (array $atributos) => Direccion::query()->findOrFail($atributos['direccion_id'])->ciudad_id,
            'seccional_id' => fn (array $atributos) => Direccion::query()->with('ciudad')->findOrFail($atributos['direccion_id'])->ciudad->seccional_id,
            'estado' => EstadoGuia::PorZonificar,
            'nombre_comercio' => fake()->company(),
            'direccion_original' => fake()->streetAddress(),
            'direccion_normalizada' => fn (array $atributos) => Direccion::query()->findOrFail($atributos['direccion_id'])->direccion,
            'rollos_solicitados' => fake()->numberBetween(8, 40),
            'fecha_registro' => $registro,
            'ans_horas' => 168,
            'ans_vence_at' => $registro->copy()->addHours(168),
            'es_urgente' => false,
        ];
    }

    /**
     * Guía con zona, lista para entrar a la planeación.
     */
    public function enZona(Zona $zona): static
    {
        return $this->state(fn (): array => [
            'zona_id' => $zona->id,
            'seccional_id' => $zona->seccional_id,
            'estado' => EstadoGuia::ListaParaAsignar,
        ]);
    }

    public function urgente(): static
    {
        return $this->state(function (): array {
            $registro = now()->subHours(2);

            return [
                'fecha_registro' => $registro,
                'ans_horas' => 8,
                'ans_vence_at' => $registro->copy()->addHours(8),
                'es_urgente' => true,
            ];
        });
    }
}
