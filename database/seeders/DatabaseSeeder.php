<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos necesarios para operar. Los datos de demostración (comercios y
     * personas ficticias) se cargan aparte con: php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            CatalogosSeeder::class,
            UsuariosInicialesSeeder::class,
        ]);
    }
}
