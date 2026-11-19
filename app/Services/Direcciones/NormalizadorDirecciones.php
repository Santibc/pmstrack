<?php

declare(strict_types=1);

namespace App\Services\Direcciones;

use App\Support\Texto;

/**
 * Normaliza direcciones colombianas para poder compararlas (documento técnico 5.2).
 *
 * "Cr7 # 61- 48" y "CR 7 61 48, BOGOTA" quedan como "KR 7 # 61 - 48".
 * El complemento (local, piso, oficina, centro comercial) se separa aparte.
 */
final class NormalizadorDirecciones
{
    /** Tipos de vía de dos palabras; se evalúan antes que los de una. */
    private const array VIAS_COMPUESTAS = [
        'AK' => ['AVENIDA CARRERA', 'AVENIDA CRA', 'AVENIDA KR', 'AVENIDA CR', 'AV CARRERA', 'AV CRA', 'AV KR', 'AV CR', 'AV KRA', 'AVDA CARRERA', 'AVDA CRA', 'AVDA KR', 'AVDA CR'],
        'AC' => ['AVENIDA CALLE', 'AVENIDA CLL', 'AVENIDA CL', 'AV CALLE', 'AV CLL', 'AV CL', 'AVDA CALLE', 'AVDA CLL', 'AVDA CL'],
    ];

    private const array VIAS = [
        'CL' => ['CALLE', 'CLL', 'CLLE', 'CL'],
        'KR' => ['CARRERA', 'CRA', 'CARR', 'KRA', 'KRR', 'CR', 'KR'],
        'AK' => ['AK'],
        'AC' => ['AC'],
        'DG' => ['DIAGONAL', 'DIAG', 'DG'],
        'TV' => ['TRANSVERSAL', 'TRANSV', 'TRANS', 'TRV', 'TV'],
        'AV' => ['AVENIDA', 'AVDA', 'AVE', 'AV'],
        'AUT' => ['AUTOPISTA', 'AUTOP', 'AUT'],
        'CIRCULAR' => ['CIRCULAR', 'CIRC'],
        'KM' => ['KILOMETRO', 'KM'],
        'VRD' => ['VEREDA', 'VRD', 'VDA'],
    ];

    /** Tipos de vía que por sí solos indican una dirección sin nomenclatura urbana. */
    private const array VIAS_RURALES = ['KM', 'VRD'];

    private const array CARDINALES = ['SUR', 'NORTE', 'ESTE', 'OESTE', 'BIS'];

    /** Palabras con las que empieza el complemento. */
    private const array COMPLEMENTOS = [
        'LOCAL', 'LOCALES', 'LC', 'LOC', 'PISO', 'OFICINA', 'OF', 'OFC', 'CENTRO', 'CC', 'INTERIOR', 'INT',
        'BODEGA', 'BG', 'BOD', 'EDIFICIO', 'ED', 'EDIF', 'BARRIO', 'BRR', 'TORRE', 'TO', 'APTO', 'APARTAMENTO',
        'AP', 'CASA', 'MANZANA', 'MZ', 'HOTEL', 'PLAZA', 'NIVEL', 'KM', 'KILOMETRO', 'VIA', 'PRIMER', 'SEGUNDO',
        'TERCER', 'CUARTO', 'ESQUINA', 'CONSULTORIO', 'CS', 'PJ', 'CONJUNTO', 'CONJ', 'ETAPA', 'BLOQUE', 'BL',
        'SOTANO', 'MEZANINE', 'ISLA', 'STAND', 'MODULO', 'ENTRADA', 'FRENTE', 'DIAGONAL', 'AL', 'JUNTO',
    ];

    private const array PALABRAS_RURALES = ['KM', 'KILOMETRO', 'VIA', 'VEREDA', 'VRD', 'VDA', 'FINCA', 'CORREGIMIENTO', 'PARCELA', 'HACIENDA'];

    public function normalizar(?string $direccion, ?string $ciudad = null, ?string $geolocalizacion = null): DireccionNormalizada
    {
        $tokens = $this->tokenizar($this->limpiar((string) $direccion, $ciudad));

        if ($tokens === []) {
            return new DireccionNormalizada('SIN DIRECCION', null, 'SIN DIRECCION', false, false);
        }

        $via = $this->ubicarVia($tokens);
        $geoRural = Texto::normalizar($geolocalizacion) === 'RURAL';

        if ($via === null || in_array($via['tipo'], self::VIAS_RURALES, true)) {
            return $this->sinNomenclatura($tokens, $geoRural);
        }

        $prefijo = array_slice($tokens, 0, $via['inicio']);
        $resto = array_slice($tokens, $via['inicio'] + $via['largo']);

        [$designacion, $complemento] = in_array('#', $resto, true)
            ? $this->conNumeral($resto)
            : $this->sinNumeral($resto);

        if ($designacion === null) {
            return $this->sinNomenclatura($tokens, $geoRural);
        }

        $principal = $via['tipo'].' '.$designacion;
        $complemento = $this->unirComplemento([...$prefijo, ...$complemento]);

        return new DireccionNormalizada(
            principal: $principal,
            complemento: $complemento,
            clave: $this->clave($principal),
            esRural: $geoRural || $this->contieneRural([...$prefijo, ...$this->palabras($complemento)]),
            estructurada: true,
        );
    }

    /**
     * Mayúsculas, sin tildes, símbolos unificados y sin la ciudad al final.
     */
    private function limpiar(string $direccion, ?string $ciudad): string
    {
        // "N°" y "Nº" se reemplazan antes de quitar tildes para no perder el símbolo.
        $texto = preg_replace('/\b[Nn]\s*[°º]\.?/u', ' # ', $direccion) ?? $direccion;
        $texto = Texto::normalizar($texto);

        // "No.", "NRO", "NUMERO" → "#".
        $texto = preg_replace('/\bNUMERO\b|\bNRO\b\.?|\bNUM\b\.?|\bNO\.(?=\s*\d)|\bNO(?=\s+\d)/', ' # ', $texto) ?? $texto;
        // "55_129" y "160/72" → "55-129" / "160-72".
        $texto = preg_replace('/(?<=[0-9A-Z])\s*[_\/]\s*(?=\d)/', '-', $texto) ?? $texto;
        $texto = str_replace(['.', ';', '(', ')', '"', "'"], ' ', $texto);

        $segmentos = array_values(array_filter(array_map('trim', explode(',', $texto)), fn (string $s): bool => $s !== ''));

        // La ciudad escrita al final ("…, BOGOTA") no hace parte de la dirección.
        if (count($segmentos) > 1 && $this->esCiudad((string) end($segmentos), $ciudad)) {
            array_pop($segmentos);
        }

        // La coma separa la dirección de su complemento ("…125-26, primer piso").
        return implode(' , ', $segmentos);
    }

    private function esCiudad(string $segmento, ?string $ciudad): bool
    {
        $clave = Texto::claveLugar($segmento);

        if ($ciudad !== null && $clave === Texto::claveLugar($ciudad)) {
            return true;
        }

        return in_array($clave, ['BOGOTA', 'MEDELLIN', 'CALI', 'COLOMBIA'], true);
    }

    /**
     * @return list<string>
     */
    private function tokenizar(string $texto): array
    {
        $texto = preg_replace('/\s*#\s*/', ' # ', $texto) ?? $texto;
        // Un guion entre números/letras separa placa: "61- 48", "2 SUR-120".
        $texto = preg_replace('/\s*-\s*/', ' - ', $texto) ?? $texto;

        $tokens = [];

        foreach (preg_split('/\s+/', trim($texto)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }

            // "CRA13" → "CRA 13" · "KM1" → "KM 1" (solo si el prefijo es un tipo de vía).
            if (preg_match('/^([A-Z]+)(\d[0-9A-Z]*)$/', $token, $m) && $this->tipoDeVia($m[1]) !== null) {
                array_push($tokens, $m[1], ...$this->separarCardinal($m[2]));

                continue;
            }

            array_push($tokens, ...$this->separarCardinal($token));
        }

        return $tokens;
    }

    /**
     * "8NORTE" → ["8", "NORTE"] · "25A" queda igual.
     *
     * @return list<string>
     */
    private function separarCardinal(string $token): array
    {
        if (preg_match('/^(\d+[A-Z]{0,2}?)(SUR|NORTE|ESTE|OESTE)$/', $token, $m)) {
            return [$m[1], $m[2]];
        }

        return [$token];
    }

    private function tipoDeVia(string $palabra): ?string
    {
        foreach (self::VIAS as $tipo => $alias) {
            if (in_array($palabra, $alias, true)) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * Busca el primer tipo de vía seguido de cerca por un número.
     *
     * @param  list<string>  $tokens
     * @return array{tipo: string, inicio: int, largo: int}|null
     */
    private function ubicarVia(array $tokens): ?array
    {
        $total = count($tokens);

        for ($i = 0; $i < $total; $i++) {
            if ($tokens[$i] === ',') {
                break;
            }

            $doble = $i + 1 < $total ? $tokens[$i].' '.$tokens[$i + 1] : null;

            foreach (self::VIAS_COMPUESTAS as $tipo => $alias) {
                if ($doble !== null && in_array($doble, $alias, true) && $this->hayNumeroCerca($tokens, $i + 2)) {
                    return ['tipo' => $tipo, 'inicio' => $i, 'largo' => 2];
                }
            }

            $tipo = $this->tipoDeVia($tokens[$i]);

            if ($tipo !== null && (in_array($tipo, self::VIAS_RURALES, true) || $this->hayNumeroCerca($tokens, $i + 1))) {
                return ['tipo' => $tipo, 'inicio' => $i, 'largo' => 1];
            }
        }

        return null;
    }

    /**
     * Hay un número en las siguientes posiciones (permite un nombre: "AV COLOMBIA 1…", "AUT NORTE 175…").
     *
     * @param  list<string>  $tokens
     */
    private function hayNumeroCerca(array $tokens, int $desde): bool
    {
        foreach (array_slice($tokens, $desde, 3) as $token) {
            if ($this->empiezaConNumero($token)) {
                return true;
            }

            if ($token === '#' || $token === ',' || in_array($token, self::COMPLEMENTOS, true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Dirección con "#": todo lo anterior es la vía; después vienen placa y complemento.
     *
     * @param  list<string>  $tokens
     * @return array{0: string|null, 1: list<string>}
     */
    private function conNumeral(array $tokens): array
    {
        $posicion = (int) array_search('#', $tokens, true);
        $izquierda = array_values(array_filter(array_slice($tokens, 0, $posicion), fn (string $t): bool => $t !== '-' && $t !== ','));
        $derecha = array_slice($tokens, $posicion + 1);

        if ($izquierda === []) {
            return [null, []];
        }

        $via = $this->unirSufijos($izquierda);
        [$grupos, $complemento] = $this->leerGrupos($derecha, 2);

        if ($grupos === []) {
            return [$via, $complemento];
        }

        return [$via.' # '.implode(' - ', $grupos), $complemento];
    }

    /**
     * Dirección sin "#": se infiere "vía número número" a partir de los grupos numéricos.
     *
     * @param  list<string>  $tokens
     * @return array{0: string|null, 1: list<string>}
     */
    private function sinNumeral(array $tokens): array
    {
        // Vía con nombre propio: "AUT NORTE 175B-50", "AV COLOMBIA 1A OESTE 35".
        $nombre = [];

        while ($tokens !== [] && ! $this->empiezaConNumero($tokens[0]) && ! in_array($tokens[0], ['-', ','], true)) {
            if (in_array($tokens[0], self::COMPLEMENTOS, true) && ! in_array($tokens[0], self::CARDINALES, true)) {
                break;
            }

            $nombre[] = array_shift($tokens);
        }

        [$grupos, $complemento] = $this->leerGrupos($tokens, $nombre === [] ? 3 : 2);

        if ($grupos === [] && $nombre === []) {
            return [null, []];
        }

        if ($nombre !== []) {
            $via = implode(' ', $nombre);

            return [$grupos === [] ? $via : $via.' # '.implode(' - ', $grupos), $complemento];
        }

        $via = array_shift($grupos);

        return [$grupos === [] ? $via : $via.' # '.implode(' - ', $grupos), $complemento];
    }

    /**
     * Lee hasta $maximo grupos numéricos ("25A", "1A SUR", "45"); lo que sobra es complemento.
     *
     * @param  list<string>  $tokens
     * @return array{0: list<string>, 1: list<string>}
     */
    private function leerGrupos(array $tokens, int $maximo): array
    {
        $grupos = [];
        $i = 0;
        $total = count($tokens);

        while ($i < $total && count($grupos) < $maximo) {
            $token = $tokens[$i];

            if ($token === '-') {
                $i++;

                continue;
            }

            if (! $this->empiezaConNumero($token)) {
                break;
            }

            $grupo = $token;
            $i++;

            // Sufijos del número: letra ("64 A" → "64A") y cardinal ("2 SUR").
            while ($i < $total && $this->esSufijo($tokens[$i], $tokens[$i + 1] ?? null)) {
                $grupo .= in_array($tokens[$i], self::CARDINALES, true) ? ' '.$tokens[$i] : $tokens[$i];
                $i++;
            }

            $grupos[] = $grupo;
        }

        return [$grupos, array_slice($tokens, $i)];
    }

    /**
     * Une la letra suelta al número de la vía: ["94", "A"] → "94A".
     *
     * @param  list<string>  $tokens
     */
    private function unirSufijos(array $tokens): string
    {
        $salida = [];

        foreach ($tokens as $i => $token) {
            $anterior = $salida === [] ? null : $salida[array_key_last($salida)];

            if ($anterior !== null && $this->empiezaConNumero($anterior) && preg_match('/^[A-Z]{1,2}$/', $token) && ! in_array($token, self::COMPLEMENTOS, true)) {
                $salida[array_key_last($salida)] = $anterior.$token;

                continue;
            }

            $salida[] = $token;
        }

        return implode(' ', $salida);
    }

    private function esSufijo(string $token, ?string $siguiente): bool
    {
        if (in_array($token, self::CARDINALES, true)) {
            return true;
        }

        if (in_array($token, self::COMPLEMENTOS, true)) {
            return false;
        }

        // Una o dos letras entre números ("64 A 14", "32 EE 36") son parte de la placa.
        return (bool) preg_match('/^[A-Z]{1,2}$/', $token)
            && ($siguiente === null || $siguiente === '-' || $this->empiezaConNumero($siguiente) || in_array($siguiente, self::CARDINALES, true));
    }

    /**
     * Direcciones por kilómetro, vereda o sin nomenclatura reconocible.
     *
     * @param  list<string>  $tokens
     */
    private function sinNomenclatura(array $tokens, bool $geoRural): DireccionNormalizada
    {
        $tokens = array_values(array_filter($tokens, fn (string $t): bool => $t !== ','));
        $rural = $geoRural || $this->contieneRural($tokens);

        $corte = null;

        if (! $rural) {
            foreach ($tokens as $i => $token) {
                if ($i > 0 && in_array($token, ['LOCAL', 'LOCALES', 'LC', 'LOC', 'PISO', 'OFICINA', 'OF', 'NIVEL', 'INTERIOR', 'INT'], true)) {
                    $corte = $i;
                    break;
                }
            }
        }

        $principal = implode(' ', $corte === null ? $tokens : array_slice($tokens, 0, $corte));
        $principal = $this->normalizarAbreviaturasRurales($principal);
        $complemento = $corte === null ? null : implode(' ', array_slice($tokens, $corte));

        return new DireccionNormalizada(
            principal: $principal,
            complemento: $complemento,
            clave: $principal,
            esRural: $rural,
            estructurada: false,
        );
    }

    private function normalizarAbreviaturasRurales(string $texto): string
    {
        $texto = preg_replace('/\bKILOMETRO\b/', 'KM', $texto) ?? $texto;

        return preg_replace('/\b(VEREDA|VDA)\b/', 'VRD', $texto) ?? $texto;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function contieneRural(array $tokens): bool
    {
        return array_intersect($tokens, self::PALABRAS_RURALES) !== [];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function unirComplemento(array $tokens): ?string
    {
        $texto = implode(' ', $tokens);
        // Conserva el guion entre números ("LOCAL 1-105") y descarta los separadores sueltos.
        $texto = preg_replace('/(?<=\d) - (?=\d)/', '-', $texto) ?? $texto;
        $texto = trim((string) preg_replace('/\s+/', ' ', str_replace([' - ', ' , ', ' # '], ' ', " {$texto} ")), ' -,#');

        return $texto === '' ? null : $texto;
    }

    /**
     * @return list<string>
     */
    private function palabras(?string $texto): array
    {
        return $texto === null ? [] : explode(' ', $texto);
    }

    private function empiezaConNumero(string $token): bool
    {
        return $token !== '' && ctype_digit($token[0]);
    }

    /**
     * Clave de comparación: sin espacios y sin ceros a la izquierda ("3 - 05" ≡ "3 - 5").
     */
    private function clave(string $principal): string
    {
        $clave = preg_replace('/\b0+(\d)/', '$1', $principal) ?? $principal;

        return str_replace(' ', '', $clave);
    }
}
