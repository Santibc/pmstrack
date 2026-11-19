<?php

declare(strict_types=1);

namespace App\Services\Ans;

use App\Services\Calendario\CalendarioLaboral;
use App\Services\Parametros;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * ANS de una guía: horas tomadas del campo HORARIO_DE_ATENCION de Credibanco
 * y fecha límite contada desde FECHA_REGISTRO (documento técnico 5.1).
 */
final class CalculadoraAns
{
    public function __construct(
        private readonly Parametros $parametros,
        private readonly CalendarioLaboral $calendario,
    ) {}

    /**
     * "Lunes a Viernes 8:30 - 17:30 - 168 Horas" → 168. Devuelve null si no vienen horas.
     */
    public function extraerHoras(?string $horarioDeAtencion): ?int
    {
        if (! preg_match_all('/(\d{1,4})\s*h(?:ora)?s?\b/iu', (string) $horarioDeAtencion, $coincidencias)) {
            return null;
        }

        $horas = (int) end($coincidencias[1]);

        return $horas > 0 ? $horas : null;
    }

    public function horasPorDefecto(): int
    {
        return max(1, $this->parametros->entero('ans_horas_defecto'));
    }

    public function vencimiento(CarbonInterface $fechaRegistro, int $horas): CarbonImmutable
    {
        if ($this->parametros->texto('ans_tipo_horas') === 'habiles') {
            return $this->calendario->sumarHorasHabiles($fechaRegistro, $horas);
        }

        return CarbonImmutable::instance($fechaRegistro)->addHours($horas);
    }

    public function esUrgente(int $horas): bool
    {
        return $horas <= $this->parametros->entero('ans_umbral_urgente_horas');
    }
}
