<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Usuarios con los que se entra por primera vez al sistema.
 *
 * La contraseña inicial se toma de PMS_PASSWORD_INICIAL; si no está definida
 * se genera una aleatoria y se muestra una sola vez en la consola.
 */
class UsuariosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['Soporte MY Tech Solutions', 'soporte@pmstrack.test', Rol::Superadmin, 'Soporte técnico'],
            ['Administradora PMS', 'operaciones@pmstrack.test', Rol::Administradora, 'Operaciones'],
            ['Analista Credibanco', 'analista.credibanco@pmstrack.test', Rol::Credibanco, 'Solo lectura'],
        ];

        $password = config('pms.password_inicial') ?: Str::password(14, symbols: false);
        $creados = [];

        foreach ($usuarios as [$nombre, $email, $rol, $cargo]) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $usuario = User::query()->create([
                'name' => $nombre,
                'email' => $email,
                'password' => $password,
                'cargo' => $cargo,
                'activo' => true,
            ]);
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->assignRole($rol->value);

            $creados[] = [$nombre, $email, $rol->etiqueta()];
        }

        if ($creados !== [] && $this->command) {
            $this->command->table(['Nombre', 'Correo', 'Rol'], $creados);
            $this->command->warn("Contraseña inicial: {$password}  (cámbiala al ingresar)");
        }
    }
}
