<?php

declare(strict_types=1);

namespace App\Services\Calendario;

use App\Services\Parametros;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendario laboral colombiano: festivos (incluida la Ley Emiliani),
 * días hábiles y suma de horas hábiles.
 */
final class CalendarioLaboral
{
    /** Festivos de fecha fija [mes, día]. */
    private const array FIJOS = [[1, 1], [5, 1], [7, 20], [8, 7], [12, 8], [12, 25]];

    /** Festivos que se trasladan al lunes siguiente (Ley 51 de 1983) [mes, día]. */
    private const array TRASLADABLES = [[1, 6], [3, 19], [6, 29], [8, 15], [10, 12], [11, 1], [11, 11]];

    /** @var array<int, array<string, true>> */
    private array $cache = [];

    public function __construct(private readonly Parametros $parametros) {}

    public function esFestivo(CarbonInterface $fecha): bool
    {
        return isset($this->festivos($fecha->year)[$fecha->format('Y-m-d')]);
    }

    public function esHabil(CarbonInterface $fecha): bool
    {
        if ($fecha->isSunday() || $this->esFestivo($fecha)) {
            return false;
        }

        return ! $fecha->isSaturday() || $this->parametros->booleano('sabado_habil');
    }

    /**
     * Primer día hábil estrictamente posterior a la fecha.
     */
    public function siguienteDiaHabil(CarbonInterface $fecha): CarbonImmutable
    {
        $dia = CarbonImmutable::instance($fecha)->startOfDay()->addDay();

        while (! $this->esHabil($dia)) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /**
     * Suma horas contando solo el horario laboral de los días hábiles.
     */
    public function sumarHorasHabiles(CarbonInterface $desde, int $horas): CarbonImmutable
    {
        [$horaInicio, $minutoInicio] = $this->hora('horario_laboral_inicio');
        [$horaFin, $minutoFin] = $this->hora('horario_laboral_fin');

        $cursor = CarbonImmutable::instance($desde);
        $pendiente = $horas * 60;

        // Límite defensivo: ningún ANS razonable recorre más de dos años de calendario.
        for ($i = 0; $i < 800 && $pendiente > 0; $i++) {
            $inicio = $cursor->setTime($horaInicio, $minutoInicio);
            $fin = $cursor->setTime($horaFin, $minutoFin);

            if (! $this->esHabil($cursor) || $cursor->greaterThanOrEqualTo($fin)) {
                $cursor = $cursor->addDay()->setTime($horaInicio, $minutoInicio);

                continue;
            }

            if ($cursor->lessThan($inicio)) {
                $cursor = $inicio;
            }

            $disponible = (int) $cursor->diffInMinutes($fin);

            if ($pendiente <= $disponible) {
                return $cursor->addMinutes($pendiente);
            }

            $pendiente -= $disponible;
            $cursor = $cursor->addDay()->setTime($horaInicio, $minutoInicio);
        }

        return $cursor;
    }

    /**
     * @return array<string, true> Festivos del año, indexados por "Y-m-d".
     */
    public function festivos(int $anio): array
    {
        if (isset($this->cache[$anio])) {
            return $this->cache[$anio];
        }

        $fechas = [];

        foreach (self::FIJOS as [$mes, $dia]) {
            $fechas[] = CarbonImmutable::create($anio, $mes, $dia);
        }

        foreach (self::TRASLADABLES as [$mes, $dia]) {
            $fechas[] = $this->alLunes(CarbonImmutable::create($anio, $mes, $dia));
        }

        $pascua = $this->domingoDePascua($anio);
        $fechas[] = $pascua->subDays(3);                    // Jueves Santo
        $fechas[] = $pascua->subDays(2);                    // Viernes Santo
        $fechas[] = $this->alLunes($pascua->addDays(39));   // Ascensión del Señor
        $fechas[] = $this->alLunes($pascua->addDays(60));   // Corpus Christi
        $fechas[] = $this->alLunes($pascua->addDays(68));   // Sagrado Corazón

        $indice = [];

        foreach ($fechas as $fecha) {
            $indice[$fecha->format('Y-m-d')] = true;
        }

        return $this->cache[$anio] = $indice;
    }

    private function alLunes(CarbonImmutable $fecha): CarbonImmutable
    {
        return $fecha->isMonday() ? $fecha : $fecha->next(CarbonInterface::MONDAY);
    }

    /**
     * Algoritmo de Butcher-Meeus para el calendario gregoriano.
     */
    private function domingoDePascua(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($anio, $mes, $dia);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function hora(string $clave): array
    {
        [$hora, $minuto] = array_pad(explode(':', $this->parametros->texto($clave)), 2, '0');

        return [(int) $hora, (int) $minuto];
    }
}
