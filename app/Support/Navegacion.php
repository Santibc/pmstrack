<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\Reportes\CalidadDatos;
use App\Services\Zonificacion\ZonificacionService;

/**
 * Menú lateral de cada área (administración de PMS y portal Credibanco).
 */
final class Navegacion
{
    public function __construct(
        private readonly ZonificacionService $zonificacion,
        private readonly CalidadDatos $calidad,
    ) {}

    /**
     * @return list<array{ruta: string, activa: string, etiqueta: string, icono: string, contador?: int|null}>
     */
    public function administracion(User $usuario): array
    {
        $items = [
            ['ruta' => 'admin.dashboard', 'activa' => 'admin.dashboard', 'etiqueta' => 'Dashboard operativo', 'icono' => 'layout-dashboard', 'permiso' => 'dashboard.ver'],
            ['ruta' => 'admin.cargas', 'activa' => 'admin.cargas*', 'etiqueta' => 'Cargar archivo', 'icono' => 'file-up', 'permiso' => 'cargas.gestionar'],
            ['ruta' => 'admin.zonificacion', 'activa' => 'admin.zonificacion*', 'etiqueta' => 'Zonificación', 'icono' => 'map', 'permiso' => 'zonificacion.gestionar', 'contador' => fn (): int => $this->zonificacion->totalPendientes($usuario->seccionalRestringida())],
            ['ruta' => 'admin.planeacion', 'activa' => 'admin.planeacion*', 'etiqueta' => 'Planeación del día', 'icono' => 'user-round-check', 'permiso' => 'planeacion.gestionar'],
            ['ruta' => 'admin.inventario', 'activa' => 'admin.inventario*', 'etiqueta' => 'Inventario', 'icono' => 'warehouse', 'permiso' => 'inventario.ver'],
            ['ruta' => 'admin.seguimiento', 'activa' => 'admin.seguimiento*', 'etiqueta' => 'Seguimiento en vivo', 'icono' => 'route', 'permiso' => 'seguimiento.ver'],
            ['ruta' => 'admin.guias', 'activa' => 'admin.guias*', 'etiqueta' => 'Guías e histórico', 'icono' => 'history', 'permiso' => 'guias.ver'],
            ['ruta' => 'admin.repartidores', 'activa' => 'admin.repartidores*', 'etiqueta' => 'Repartidores', 'icono' => 'truck', 'permiso' => 'repartidores.gestionar'],
            ['ruta' => 'admin.reportes', 'activa' => 'admin.reportes*', 'etiqueta' => 'Reportes', 'icono' => 'chart-column', 'permiso' => 'reportes.ver'],
            ['ruta' => 'admin.configuracion', 'activa' => 'admin.configuracion*', 'etiqueta' => 'Configuración', 'icono' => 'settings', 'permiso' => 'configuracion.gestionar'],
        ];

        return $this->resolver($items, $usuario);
    }

    /**
     * @return list<array{ruta: string, activa: string, etiqueta: string, icono: string, contador?: int|null}>
     */
    public function portal(User $usuario): array
    {
        $items = [
            ['ruta' => 'portal.dashboard', 'activa' => 'portal.dashboard', 'etiqueta' => 'Dashboard', 'icono' => 'layout-dashboard', 'permiso' => 'portal.ver'],
            ['ruta' => 'portal.guias', 'activa' => 'portal.guias*', 'etiqueta' => 'Servicios e histórico', 'icono' => 'file-text', 'permiso' => 'portal.ver'],
            ['ruta' => 'portal.consulta', 'activa' => 'portal.consulta', 'etiqueta' => 'Consulta rápida', 'icono' => 'search', 'permiso' => 'portal.ver'],
            ['ruta' => 'portal.reportes', 'activa' => 'portal.reportes', 'etiqueta' => 'Reportes Excel', 'icono' => 'file-spreadsheet', 'permiso' => 'portal.ver'],
            ['ruta' => 'portal.calidad', 'activa' => 'portal.calidad', 'etiqueta' => 'Calidad de datos', 'icono' => 'triangle-alert', 'permiso' => 'portal.ver', 'contador' => fn (): int => $this->calidad->total(), 'tono' => 'warn'],
        ];

        return $this->resolver($items, $usuario);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolver(array $items, User $usuario): array
    {
        $visibles = array_filter($items, fn (array $item): bool => $usuario->can($item['permiso']));

        return array_values(array_map(function (array $item): array {
            $item['contador'] = isset($item['contador']) ? ($item['contador'])() : null;
            $item['tono'] ??= 'brand';

            return $item;
        }, $visibles));
    }
}
