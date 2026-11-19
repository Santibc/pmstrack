<?php

declare(strict_types=1);

namespace App\Services\Cargas;

use App\Support\Telefono;
use App\Support\Texto;
use Illuminate\Support\Str;

/**
 * Toma nombre, teléfono y horario del comercio desde OBSERVACIONES.
 * La columna CONTACTO llega vacía; el dato real viene en este texto.
 */
final class ExtractorContacto
{
    /** Texto estándar del canal de WhatsApp de Credibanco. */
    private const string ESTANDAR = '/se comunica\s+(?<nombre>.+?)\s+a trav[eé]s de whatsapp.*?horario de\s+(?<horario>.+?)\s+en la direcci[oó]n\s+.+?,?\s*n[uú]mero de contacto\s+(?<telefono>[\d\s\-\(\)\+]+)(?:\s*en la ciudad de\s+(?<ciudad>.+))?$/isu';

    public function extraer(?string $observaciones): ContactoExtraido
    {
        $texto = (string) Str::of((string) $observaciones)->squish();

        if ($texto === '') {
            return new ContactoExtraido;
        }

        if (preg_match(self::ESTANDAR, $texto, $m)) {
            return new ContactoExtraido(
                nombre: $this->nombre($m['nombre']),
                telefono: Telefono::limpiar($m['telefono']),
                horario: $this->horario($m['horario']),
                ciudad: isset($m['ciudad']) ? $this->ciudad($m['ciudad']) : null,
            );
        }

        return $this->conEtiquetas($texto) ?? $this->libre($texto);
    }

    /**
     * "TEL: 3147557754HORARIO: Lunes a Sabado de 9Am a 6PmP.CONTACTO: Yolima".
     */
    private function conEtiquetas(string $texto): ?ContactoExtraido
    {
        $telefono = preg_match('/\bTEL(?:EFONO)?S?\s*:\s*(?<valor>[\d\s\-\(\)\+]{6,})/iu', $texto, $t) ? Telefono::limpiar($t['valor']) : null;
        $horario = preg_match('/\bHORARIOS?\s*:\s*(?<valor>.+?)(?=\s*(?:TEL(?:EFONO)?S?\s*:|P(?:ER)?\.?\s*CONTACTO\s*:|$))/isu', $texto, $h) ? $this->horario($h['valor']) : null;
        $nombre = preg_match('/\bP(?:ER)?\.?\s*CONTACTO\s*:\s*(?<valor>[^\d:]+?)\s*$/isu', $texto, $n) ? $this->nombre($n['valor']) : null;

        if ($telefono === null && $horario === null && $nombre === null) {
            return null;
        }

        return new ContactoExtraido($nombre, $telefono, $horario);
    }

    /**
     * "CONTACTO 3178067738 STIVEN" · "CONTACTO GENESIS CAROLINA 322-5328725".
     */
    private function libre(string $texto): ContactoExtraido
    {
        if (! preg_match('/\bCONTACTO\s+(?:(?<antes>[\pL ]+?)\s+)?(?<telefono>\d[\d\-\s]{6,}\d)(?:\s+(?<despues>[\pL]+(?:\s+[\pL]+)?))?/iu', $texto, $m)) {
            return new ContactoExtraido;
        }

        $nombre = trim($m['antes'] ?? '') !== '' ? $m['antes'] : ($m['despues'] ?? null);

        return new ContactoExtraido(
            nombre: $nombre ? $this->nombre($nombre) : null,
            telefono: Telefono::limpiar($m['telefono']),
        );
    }

    private function nombre(string $valor): ?string
    {
        $nombre = Texto::nombrePropio(trim($valor, " \t.,;:-"));

        return $nombre === '' ? null : Str::limit($nombre, 120, '');
    }

    private function horario(string $valor): ?string
    {
        $horario = (string) Str::of($valor)->squish()->trim(' .,;:-');

        return $horario === '' ? null : Str::limit($horario, 190, '');
    }

    private function ciudad(string $valor): ?string
    {
        $ciudad = (string) Str::of($valor)->squish()->trim(' .,;');

        return $ciudad === '' ? null : $ciudad;
    }
}
