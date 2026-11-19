<?php

namespace Tests\Support;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera un Excel con el mismo formato del archivo diario de Credibanco
 * (16 columnas, fecha como número de serie) y datos ficticios.
 */
final class ArchivoCredibanco
{
    public const array ENCABEZADOS = [
        'SERVICIO', 'CADENA', 'CODIGO_UNICO', 'FECHA_REGISTRO', 'NOMBRE_COMERCIO', 'CODIGO_CIUDAD', 'CIUDAD',
        'GEOLOCALIZACION', 'SECCIONAL', 'DEPARTAMENTO', 'DIRECCION', 'TELEFONO', 'CONTACTO',
        'HORARIO_DE_ATENCION', 'PAPELERIA_ENTREGAR', 'OBSERVACIONES',
    ];

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    public static function fila(array $cambios = []): array
    {
        static $consecutivo = 3937600;
        $consecutivo++;

        return array_merge([
            'SERVICIO' => $consecutivo,
            'CADENA' => 'INVERSIONES ALTAMIRA S A S',
            'CODIGO_UNICO' => 18000000 + $consecutivo % 100000,
            'FECHA_REGISTRO' => new DateTimeImmutable('2026-10-22 16:12:00'),
            'NOMBRE_COMERCIO' => 'CAFE ALTAMIRA USAQUEN',
            'CODIGO_CIUDAD' => 0,
            'CIUDAD' => 'BOGOTA',
            'GEOLOCALIZACION' => 'Urbano',
            'SECCIONAL' => 'BOGOTA',
            'DEPARTAMENTO' => 'BOGOTA, D. C.',
            'DIRECCION' => 'Calle 116 #18b-09 local 2',
            'TELEFONO' => '+57 3215550187',
            'CONTACTO' => null,
            'HORARIO_DE_ATENCION' => 'Lunes a Viernes 8:30 - 17:30 - 168 Horas',
            'PAPELERIA_ENTREGAR' => 23,
            'OBSERVACIONES' => 'Se comunica Rosa Castellanos a través de WhatsApp y solicita papelería para su comercio, indica que atiende en horario de 8am a 4pm en la dirección Calle 116 #18b-09 local 2, número de contacto 3215550187 en la ciudad de Bogotá',
        ], $cambios);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>|null  $encabezados
     * @return string Ruta del archivo temporal.
     */
    public static function crear(array $filas, ?array $encabezados = null): string
    {
        $encabezados ??= self::ENCABEZADOS;
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Hoja1');
        $hoja->fromArray($encabezados, null, 'A1');

        foreach ($filas as $indice => $fila) {
            foreach ($encabezados as $columna => $nombre) {
                $valor = $fila[$nombre] ?? null;

                if ($valor instanceof DateTimeImmutable) {
                    $valor = Date::PHPToExcel($valor);
                }

                $hoja->setCellValue([$columna + 1, $indice + 2], $valor);
            }
        }

        $ruta = tempnam(sys_get_temp_dir(), 'pms').'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
