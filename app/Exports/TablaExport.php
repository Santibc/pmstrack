<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de Excel sencilla (encabezados + filas) con el estilo de PMS Track.
 * La usan los reportes pequeños: inventario, calidad de datos, productividad…
 */
final class TablaExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
    ) {}

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return $this->filas;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->encabezados;
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $hoja): array
    {
        $hoja->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2328']]],
        ];
    }
}
