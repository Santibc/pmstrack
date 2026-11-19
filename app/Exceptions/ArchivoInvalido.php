<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * El archivo subido no se puede cargar. El mensaje se muestra tal cual a la administradora.
 */
final class ArchivoInvalido extends RuntimeException
{
    /**
     * @param  list<string>  $faltantes
     */
    public static function columnasFaltantes(array $faltantes): self
    {
        $lista = implode(', ', $faltantes);
        $verbo = count($faltantes) === 1 ? 'Falta la columna obligatoria' : 'Faltan las columnas obligatorias';

        return new self("{$verbo}: {$lista}. Revisa que sea el Excel diario de Credibanco sin modificar.");
    }

    public static function sinEncabezados(): self
    {
        return new self('No se encontró la fila de encabezados (SERVICIO, CODIGO_UNICO, DIRECCION…). Revisa que sea el Excel diario de Credibanco.');
    }

    public static function sinFilas(): self
    {
        return new self('El archivo no tiene servicios: solo trae los encabezados.');
    }

    public static function ilegible(): self
    {
        return new self('No se pudo leer el archivo. Debe ser un Excel (.xlsx o .xls) o un CSV.');
    }
}
