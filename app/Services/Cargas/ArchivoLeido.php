<?php

declare(strict_types=1);

namespace App\Services\Cargas;

/**
 * Contenido del Excel de Credibanco ya ubicado por columnas.
 */
final readonly class ArchivoLeido
{
    /**
     * @param  list<string>  $columnasReconocidas  Nombres canónicos encontrados.
     * @param  list<array{numero: int, valores: array<string, mixed>}>  $filas
     */
    public function __construct(
        public string $hoja,
        public array $columnasReconocidas,
        public int $totalColumnas,
        public array $filas,
    ) {}
}
