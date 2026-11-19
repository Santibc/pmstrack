<?php

namespace Database\Seeders;

use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles del documento técnico (3.1) y los permisos de cada uno.
 */
class RolesYPermisosSeeder extends Seeder
{
    /** @var array<string, string> permiso => descripción */
    public const array PERMISOS = [
        'dashboard.ver' => 'Ver el dashboard operativo',
        'cargas.gestionar' => 'Cargar el Excel de Credibanco',
        'zonificacion.gestionar' => 'Zonificar direcciones y editar el directorio',
        'planeacion.gestionar' => 'Planear el día, publicar y reasignar',
        'inventario.ver' => 'Ver el inventario de rollos',
        'inventario.gestionar' => 'Registrar entradas y ajustes de inventario',
        'seguimiento.ver' => 'Ver el seguimiento en vivo y atender novedades',
        'guias.ver' => 'Consultar guías, evidencia y bitácora',
        'guias.gestionar' => 'Reprogramar, anular y devolver guías a Credibanco',
        'repartidores.gestionar' => 'Crear y editar repartidores',
        'reportes.ver' => 'Generar reportes',
        'configuracion.gestionar' => 'Editar zonas, causales, ciudades y parámetros',
        'usuarios.gestionar' => 'Administrar usuarios y roles',
        'portal.ver' => 'Entrar al portal Credibanco (solo lectura)',
        'app.usar' => 'Usar la app del repartidor',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISOS) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $operacion = [
            'dashboard.ver', 'cargas.gestionar', 'zonificacion.gestionar', 'planeacion.gestionar',
            'inventario.ver', 'inventario.gestionar', 'seguimiento.ver', 'guias.ver', 'guias.gestionar',
            'repartidores.gestionar', 'reportes.ver',
        ];

        $porRol = [
            Rol::Superadmin->value => array_keys(self::PERMISOS),
            Rol::Administradora->value => [...$operacion, 'configuracion.gestionar', 'usuarios.gestionar', 'portal.ver'],
            Rol::Coordinador->value => $operacion,
            Rol::Repartidor->value => ['app.usar'],
            Rol::Credibanco->value => ['portal.ver'],
        ];

        foreach ($porRol as $rol => $permisos) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
