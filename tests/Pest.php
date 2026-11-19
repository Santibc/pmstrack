<?php

use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Repartidor;
use App\Models\Seccional;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Casos de prueba
|--------------------------------------------------------------------------
|
| Las pruebas de Feature usan la aplicación completa con una base SQLite en
| memoria (ver phpunit.xml), que se recrea en cada prueba, y parten de los
| catálogos reales de la operación (seccionales, zonas, causales, roles).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed([RolesYPermisosSeeder::class, CatalogosSeeder::class]);
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Ayudas
|--------------------------------------------------------------------------
*/

function usuarioCon(Rol $rol, array $atributos = []): User
{
    return User::factory()->conRol($rol)->create($atributos);
}

function administradora(): User
{
    return usuarioCon(Rol::Administradora, ['name' => 'Administradora PMS']);
}

function seccional(string $codigo = 'BOG'): Seccional
{
    return Seccional::query()->where('codigo', $codigo)->firstOrFail();
}

function zona(string $codigo = 'Z01', string $seccional = 'BOG'): Zona
{
    return Zona::query()->where('seccional_id', seccional($seccional)->id)->where('codigo', $codigo)->firstOrFail();
}

function bodega(string $seccional = 'BOG'): Bodega
{
    return Bodega::query()->where('seccional_id', seccional($seccional)->id)->firstOrFail();
}

function repartidor(string $nombre = 'Carlos Rodríguez', string $seccional = 'BOG', array $atributos = []): Repartidor
{
    return Repartidor::factory()
        ->for(User::factory()->state(['name' => $nombre]))
        ->create(['seccional_id' => seccional($seccional)->id, ...$atributos]);
}
