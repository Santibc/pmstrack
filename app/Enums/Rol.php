<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Actores del sistema (documento técnico 3.1).
 */
enum Rol: string
{
    case Superadmin = 'superadmin';
    case Administradora = 'administradora';
    case Coordinador = 'coordinador';
    case Repartidor = 'repartidor';
    case Credibanco = 'credibanco';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadministrador',
            self::Administradora => 'Administradora PMS',
            self::Coordinador => 'Coordinador de seccional',
            self::Repartidor => 'Repartidor',
            self::Credibanco => 'Usuario Credibanco',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Superadmin => 'Soporte técnico: configuración, usuarios, parámetros y respaldos.',
            self::Administradora => 'Carga archivos, zonifica, planea, monitorea, anula y genera reportes.',
            self::Coordinador => 'Lo mismo que la administradora, limitado a su seccional.',
            self::Repartidor => 'Ve sus guías, ordena su ruta y registra visitas desde la app.',
            self::Credibanco => 'Dashboard, histórico, evidencia y reportes en solo lectura.',
        };
    }

    /**
     * Roles que operan la plataforma de administración de PMS.
     *
     * @return list<string>
     */
    public static function administracion(): array
    {
        return [self::Superadmin->value, self::Administradora->value, self::Coordinador->value];
    }
}
