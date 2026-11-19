<?php

namespace Database\Seeders;

use App\Enums\TipoCausal;
use App\Models\Bodega;
use App\Models\Causal;
use App\Models\Ciudad;
use App\Models\Parametro;
use App\Models\Seccional;
use App\Models\Zona;
use App\Services\Parametros;
use App\Support\Texto;
use Illuminate\Database\Seeder;

/**
 * Datos iniciales de la operación. Es idempotente: se puede volver a ejecutar
 * sin duplicar ni pisar lo que ya se editó desde Configuración.
 *
 * Las zonas son una propuesta inicial; PMS las ajusta desde Configuración.
 */
class CatalogosSeeder extends Seeder
{
    private const array SECCIONALES = [
        'BOG' => [
            'nombre' => 'Bogotá',
            'stock_minimo' => 3000,
            'ciudades' => [
                'Bogotá, D. C.' => ['Bogotá'],
                'Cundinamarca' => ['Soacha', 'Chía', 'Cajicá', 'Zipaquirá', 'Mosquera', 'Funza', 'Madrid', 'Facatativá', 'Cota', 'La Calera', 'Sopó', 'Tocancipá', 'Fusagasugá', 'Girardot'],
            ],
            'zonas' => [
                ['Z01', 'Norte · Usaquén', '#F07D2E'],
                ['Z02', 'Chapinero', '#3B6FE0'],
                ['Z03', 'Centro', '#8B6FE8'],
                ['Z04', 'Occidente', '#2FA455'],
                ['Z05', 'Suba', '#E5A30B'],
                ['Z06', 'Sur', '#E5527A'],
                ['Z07', 'Kennedy · Bosa', '#12A5A0'],
            ],
        ],
        'MED' => [
            'nombre' => 'Medellín',
            'stock_minimo' => 1500,
            'ciudades' => [
                'Antioquia' => ['Medellín', 'Bello', 'Envigado', 'Itagüí', 'Sabaneta', 'La Estrella', 'Caldas', 'Copacabana', 'Girardota', 'Barbosa', 'Rionegro', 'La Ceja', 'Marinilla', 'Guarne', 'San Luis'],
            ],
            'zonas' => [
                ['Z01', 'Poblado', '#F07D2E'],
                ['Z02', 'Laureles · Estadio', '#3B6FE0'],
                ['Z03', 'Centro · Norte', '#8B6FE8'],
            ],
        ],
        'CAL' => [
            'nombre' => 'Cali',
            'stock_minimo' => 1500,
            'ciudades' => [
                'Valle del Cauca' => ['Cali', 'Palmira', 'Yumbo', 'Jamundí', 'Candelaria', 'Buga', 'Tuluá', 'Cartago', 'Buenaventura'],
            ],
            'zonas' => [
                ['Z01', 'Norte', '#F07D2E'],
                ['Z02', 'Sur', '#3B6FE0'],
            ],
        ],
    ];

    /** [tipo, código, nombre, afecta la calidad de datos de Credibanco] */
    private const array CAUSALES = [
        [TipoCausal::NoEntregado, 'local_cerrado', 'Local cerrado', false],
        [TipoCausal::NoEntregado, 'no_quiso_recibir', 'Comercio no quiso recibir', false],
        [TipoCausal::NoEntregado, 'encargado_ausente', 'Encargado ausente', false],
        [TipoCausal::NoEntregado, 'fuera_de_horario', 'Fuera del horario de atención', false],
        [TipoCausal::NoEntregado, 'otro_no_entregado', 'Otro', false],
        [TipoCausal::Anulado, 'direccion_errada', 'Dirección errada', true],
        [TipoCausal::Anulado, 'comercio_no_existe', 'Comercio no existe en la dirección', true],
        [TipoCausal::Anulado, 'cerro_definitivamente', 'Cerró definitivamente', true],
        [TipoCausal::Anulado, 'comercio_trasladado', 'Comercio se trasladó', true],
        [TipoCausal::Anulado, 'solicitud_credibanco', 'Por solicitud de Credibanco', false],
        [TipoCausal::Anulado, 'otro_anulado', 'Otro', false],
    ];

    public function run(): void
    {
        foreach (self::SECCIONALES as $codigo => $datos) {
            $seccional = Seccional::query()->firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $datos['nombre'], 'nombre_normalizado' => Texto::claveLugar($datos['nombre'])],
            );

            foreach ($datos['ciudades'] as $departamento => $ciudades) {
                foreach ($ciudades as $ciudad) {
                    Ciudad::query()->firstOrCreate(
                        ['nombre_normalizado' => Texto::claveLugar($ciudad), 'seccional_id' => $seccional->id],
                        [
                            'nombre' => $ciudad,
                            'departamento' => $departamento,
                            'departamento_normalizado' => Texto::claveLugar($departamento),
                        ],
                    );
                }
            }

            foreach ($datos['zonas'] as $orden => [$codigoZona, $nombre, $color]) {
                Zona::query()->firstOrCreate(
                    ['seccional_id' => $seccional->id, 'codigo' => $codigoZona],
                    ['nombre' => $nombre, 'color' => $color, 'orden' => $orden + 1],
                );
            }

            Bodega::query()->firstOrCreate(
                ['seccional_id' => $seccional->id],
                ['nombre' => 'Bodega '.$datos['nombre'], 'stock_minimo' => $datos['stock_minimo']],
            );
        }

        foreach (self::CAUSALES as $orden => [$tipo, $codigo, $nombre, $calidad]) {
            Causal::query()->firstOrCreate(
                ['codigo' => $codigo],
                ['tipo' => $tipo, 'nombre' => $nombre, 'afecta_calidad_datos' => $calidad, 'orden' => $orden + 1],
            );
        }

        $parametros = app(Parametros::class);

        foreach (Parametros::DEFINICION as $clave => $definicion) {
            if (! Parametro::query()->where('clave', $clave)->exists()) {
                $parametros->guardar($clave, $definicion['defecto']);
            }
        }
    }
}
