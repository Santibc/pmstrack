<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\EstadoGuia;
use App\Models\Guia;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reporte de entregas para Credibanco: las 16 columnas de su propio archivo,
 * en el mismo orden, seguidas del resultado de PMS y el enlace a la evidencia.
 */
final class GuiasExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** Columnas que agrega PMS a las 16 de Credibanco. */
    private const array RESULTADO = [
        'GUIA_PMS', 'ESTADO', 'FECHA_ENTREGA', 'ROLLOS_ENTREGADOS', 'RECIBE_NOMBRE', 'RECIBE_CEDULA', 'INTENTOS',
        'MOTIVO', 'OBSERVACION_PMS', 'REPARTIDOR', 'ZONA', 'ANS_HORAS', 'ANS_VENCE', 'CUMPLE_ANS',
        'HORAS_HASTA_ENTREGA', 'EVIDENCIA',
    ];

    /**
     * @param  Builder<Guia>  $consulta
     */
    public function __construct(private readonly Builder $consulta, private readonly string $rutaDetalle) {}

    public function query(): Builder
    {
        return $this->consulta
            ->with(['comercio', 'ciudad', 'seccional', 'zona', 'repartidor.user', 'ultimaVisita.causal'])
            ->orderBy('fecha_registro')
            ->orderBy('id');
    }

    public function title(): string
    {
        return 'Entregas';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'SERVICIO', 'CADENA', 'CODIGO_UNICO', 'FECHA_REGISTRO', 'NOMBRE_COMERCIO', 'CODIGO_CIUDAD', 'CIUDAD',
            'GEOLOCALIZACION', 'SECCIONAL', 'DEPARTAMENTO', 'DIRECCION', 'TELEFONO', 'CONTACTO',
            'HORARIO_DE_ATENCION', 'PAPELERIA_ENTREGAR', 'OBSERVACIONES',
            ...self::RESULTADO,
        ];
    }

    /**
     * @param  Guia  $guia
     * @return list<mixed>
     */
    public function map($guia): array
    {
        $visita = $guia->ultimaVisita;
        $entregada = $guia->estado === EstadoGuia::Entregada;
        $minutos = $guia->minutosHastaEntrega();

        return [
            is_numeric($guia->servicio) ? (int) $guia->servicio : $guia->servicio,
            $guia->cadena,
            is_numeric($guia->comercio->codigo_unico) ? (int) $guia->comercio->codigo_unico : $guia->comercio->codigo_unico,
            Date::dateTimeToExcel($guia->fecha_registro->toDateTime()),
            $guia->nombre_comercio,
            $guia->codigo_ciudad,
            $guia->ciudad_original ?? $guia->ciudad->nombre,
            $guia->geolocalizacion,
            mb_strtoupper($guia->seccional->nombre),
            $guia->departamento,
            $guia->direccion_original,
            $guia->telefono,
            $guia->contacto_nombre,
            $guia->horario_atencion,
            $guia->rollos_solicitados,
            $guia->observaciones,
            // Resultado de PMS.
            $guia->numero_guia,
            $guia->estado->etiquetaServicio(),
            $guia->entregada_at ? Date::dateTimeToExcel($guia->entregada_at->toDateTime()) : null,
            $entregada ? $guia->rollos_entregados : null,
            $entregada ? $visita?->recibe_nombre : null,
            $entregada ? $visita?->recibe_cedula : null,
            $guia->intentos,
            $entregada ? null : $visita?->causal?->nombre,
            $entregada ? null : $visita?->observacion,
            $guia->repartidor?->nombre,
            $guia->zona?->nombre_completo,
            $guia->ans_horas,
            Date::dateTimeToExcel($guia->ans_vence_at->toDateTime()),
            match ($guia->cumplioAns()) {
                true => 'SI',
                false => 'NO',
                null => '',
            },
            $minutos !== null ? round($minutos / 60, 1) : null,
            route($this->rutaDetalle, $guia),
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $hoja): array
    {
        $ultima = $hoja->getHighestRow();

        foreach (['D', 'S', 'AC'] as $columna) {
            $hoja->getStyle("{$columna}2:{$columna}{$ultima}")->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        }

        $hoja->freezePane('B2');
        $hoja->setAutoFilter($hoja->calculateWorksheetDimension());

        // Las columnas de Credibanco en gris; las de PMS en el naranja de la marca.
        return [
            'A1:P1' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '39404B']]],
            'Q1:AF1' => ['font' => ['bold' => true, 'color' => ['rgb' => '1F2328']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F07D2E']]],
            "O2:O{$ultima}" => ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_NUMBER]],
        ];
    }
}
