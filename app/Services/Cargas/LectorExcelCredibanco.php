<?php

declare(strict_types=1);

namespace App\Services\Cargas;

use App\Exceptions\ArchivoInvalido;
use App\Support\Texto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Lee el Excel diario de Credibanco tal como llega y reconoce sus columnas
 * por nombre, sin depender del orden.
 */
final class LectorExcelCredibanco
{
    /** Columnas del archivo y los nombres con los que pueden llegar. */
    public const array COLUMNAS = [
        'SERVICIO' => ['SERVICIO', 'NUMERO_SERVICIO', 'NUMERO_DE_SERVICIO', 'NO_SERVICIO'],
        'CADENA' => ['CADENA'],
        'CODIGO_UNICO' => ['CODIGO_UNICO', 'CODIGO_UNICO_COMERCIO', 'CU'],
        'FECHA_REGISTRO' => ['FECHA_REGISTRO', 'FECHA_DE_REGISTRO', 'FECHA'],
        'NOMBRE_COMERCIO' => ['NOMBRE_COMERCIO', 'NOMBRE_DEL_COMERCIO', 'COMERCIO'],
        'CODIGO_CIUDAD' => ['CODIGO_CIUDAD', 'COD_CIUDAD'],
        'CIUDAD' => ['CIUDAD'],
        'GEOLOCALIZACION' => ['GEOLOCALIZACION'],
        'SECCIONAL' => ['SECCIONAL'],
        'DEPARTAMENTO' => ['DEPARTAMENTO'],
        'DIRECCION' => ['DIRECCION'],
        'TELEFONO' => ['TELEFONO'],
        'CONTACTO' => ['CONTACTO'],
        'HORARIO_DE_ATENCION' => ['HORARIO_DE_ATENCION', 'HORARIO_ATENCION', 'HORARIO'],
        'PAPELERIA_ENTREGAR' => ['PAPELERIA_ENTREGAR', 'PAPELERIA_A_ENTREGAR', 'ROLLOS'],
        'OBSERVACIONES' => ['OBSERVACIONES', 'OBSERVACION'],
    ];

    /** Sin estas columnas el archivo se rechaza (documento técnico 5.1). */
    public const array OBLIGATORIAS = [
        'SERVICIO', 'CODIGO_UNICO', 'NOMBRE_COMERCIO', 'CIUDAD', 'SECCIONAL', 'DIRECCION', 'PAPELERIA_ENTREGAR',
    ];

    private const int FILAS_PARA_BUSCAR_ENCABEZADO = 15;

    public function leer(string $ruta): ArchivoLeido
    {
        try {
            $libro = IOFactory::load($ruta);
        } catch (Throwable) {
            throw ArchivoInvalido::ilegible();
        }

        $faltantes = self::OBLIGATORIAS;

        foreach ($libro->getAllSheets() as $hoja) {
            $matriz = $hoja->toArray(null, true, false, false);
            $encabezado = $this->ubicarEncabezado($matriz);

            if ($encabezado === null) {
                continue;
            }

            [$indiceFila, $mapa] = $encabezado;
            $faltantes = array_values(array_diff(self::OBLIGATORIAS, array_keys($mapa)));

            if ($faltantes !== []) {
                continue;
            }

            return $this->construir($hoja, $matriz, $indiceFila, $mapa);
        }

        throw count($faltantes) === count(self::OBLIGATORIAS)
            ? ArchivoInvalido::sinEncabezados()
            : ArchivoInvalido::columnasFaltantes($faltantes);
    }

    /**
     * @param  array<int, array<int, mixed>>  $matriz
     * @return array{0: int, 1: array<string, int>}|null Índice de la fila y mapa columna canónica → posición.
     */
    private function ubicarEncabezado(array $matriz): ?array
    {
        foreach (array_slice($matriz, 0, self::FILAS_PARA_BUSCAR_ENCABEZADO, true) as $indice => $fila) {
            $mapa = [];

            foreach ($fila as $posicion => $celda) {
                $canonica = $this->columnaCanonica($celda);

                if ($canonica !== null && ! isset($mapa[$canonica])) {
                    $mapa[$canonica] = $posicion;
                }
            }

            if (isset($mapa['SERVICIO']) && count($mapa) >= 4) {
                return [$indice, $mapa];
            }
        }

        return null;
    }

    private function columnaCanonica(mixed $celda): ?string
    {
        if (! is_string($celda) && ! is_numeric($celda)) {
            return null;
        }

        $nombre = trim((string) preg_replace('/[^A-Z0-9]+/', '_', Texto::normalizar((string) $celda)), '_');

        foreach (self::COLUMNAS as $canonica => $alias) {
            if (in_array($nombre, $alias, true)) {
                return $canonica;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $matriz
     * @param  array<string, int>  $mapa
     */
    private function construir(Worksheet $hoja, array $matriz, int $indiceEncabezado, array $mapa): ArchivoLeido
    {
        $filas = [];

        foreach ($matriz as $indice => $celdas) {
            if ($indice <= $indiceEncabezado) {
                continue;
            }

            $valores = [];

            foreach ($mapa as $canonica => $posicion) {
                $valores[$canonica] = $this->limpiarCelda($celdas[$posicion] ?? null);
            }

            if (array_filter($valores, fn (mixed $v): bool => $v !== null) === []) {
                continue;
            }

            // Número de fila como lo ve la administradora en Excel (empieza en 1).
            $filas[] = ['numero' => $indice + 1, 'valores' => $valores];
        }

        if ($filas === []) {
            throw ArchivoInvalido::sinFilas();
        }

        $totalColumnas = count(array_filter(
            $matriz[$indiceEncabezado] ?? [],
            fn (mixed $celda): bool => $celda !== null && trim((string) $celda) !== '',
        ));

        return new ArchivoLeido(
            hoja: $hoja->getTitle(),
            columnasReconocidas: array_keys($mapa),
            totalColumnas: $totalColumnas,
            filas: $filas,
        );
    }

    private function limpiarCelda(mixed $valor): mixed
    {
        if (is_string($valor)) {
            $valor = trim($valor);

            return $valor === '' ? null : $valor;
        }

        return $valor;
    }
}
