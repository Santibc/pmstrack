<?php

use App\Services\Direcciones\NormalizadorDirecciones;

/**
 * Direcciones tomadas del archivo real Entrega_diaria_papeleria_16-10-2026.xlsx.
 */
dataset('direcciones reales', [
    // [como llegó, ciudad, principal esperada, complemento esperado]
    'sin separadores' => ['Cra13 90 36', 'BOGOTA', 'KR 13 # 90 - 36', null],
    'ejemplo del documento A' => ['Cr7 # 61- 48', 'BOGOTA', 'KR 7 # 61 - 48', null],
    'ejemplo del documento B' => ['CR 7 61 48, BOGOTA', 'BOGOTA', 'KR 7 # 61 - 48', null],
    'avenida carrera con complemento' => ['AVDA. CRA 19 # 125-26, primer piso', 'BOGOTA', 'AK 19 # 125 - 26', 'PRIMER PISO'],
    'guion bajo' => ['Calle 16 # 55_129', 'MEDELLIN', 'CL 16 # 55 - 129', null],
    'centro comercial y local' => ['Calle 9 #48-51 cc palmetto plaza local 255', 'CALI', 'CL 9 # 48 - 51', 'CC PALMETTO PLAZA LOCAL 255'],
    'letra separada sin numeral' => ['CARRERA 4 64 A 14, BOGOTA', 'BOGOTA', 'KR 4 # 64A - 14', null],
    'pegada con sur' => ['Cra25A#1A sur 45', 'BOGOTA', 'KR 25A # 1A SUR - 45', null],
    'barrio antes de la vía' => ['Poblado cra 35# 2 sur-120 hotel soul lifestyle Local 101', 'MEDELLIN', 'KR 35 # 2 SUR - 120', 'POBLADO HOTEL SOUL LIFESTYLE LOCAL 101'],
    'avenida calle' => ['Av calle 24a 43a-28 pj corferias', 'BOGOTA', 'AC 24A # 43A - 28', 'PJ CORFERIAS'],
    'letra antes del numeral' => ['Calle 94 a # 67a-74', 'BOGOTA', 'CL 94A # 67A - 74', null],
    'placa con letras dobles' => ['Cra 76 # 32 EE 36', 'MEDELLIN', 'KR 76 # 32EE - 36', null],
    'palabra numero' => ['Calle 50 número 70-124', 'MEDELLIN', 'CL 50 # 70 - 124', null],
    'simbolo de numero' => ['Avenida carrera 45 N° 103-03', 'BOGOTA', 'AK 45 # 103 - 03', null],
    'barra en la placa' => ['Cra 62 #160/72', 'BOGOTA', 'KR 62 # 160 - 72', null],
    'autopista con nombre' => ['AUT NORTE 175B-50 LC 205, BOGOTA', 'BOGOTA', 'AUT NORTE # 175B - 50', 'LC 205'],
    'diagonal' => ['DG 23 69 11 LC 2 103, BOGOTA', 'BOGOTA', 'DG 23 # 69 - 11', 'LC 2 103'],
    'letra de la vía separada' => ['CR 111 C 86 05 LC 229, BOGOTÁ', 'BOGOTÁ', 'KR 111C # 86 - 05', 'LC 229'],
    'avenida con nombre' => ['avenida colombia 1 a oeste 35 barrio santa teresita hotel b', 'CALI', 'AV COLOMBIA # 1A OESTE - 35', 'BARRIO SANTA TERESITA HOTEL B'],
    'norte pegado' => ['Av 8norte 15AN-06 local papa johns', 'CALI', 'AV 8 NORTE # 15AN - 06', 'LOCAL PAPA JOHNS'],
    'guiones como separador' => ['Cra 70 1-141 - Local 9823 - Centro comercial Arkadia', 'MEDELLIN', 'KR 70 # 1 - 141', 'LOCAL 9823 CENTRO COMERCIAL ARKADIA'],
    'local con guion' => ['Carrera 15 # 78-77 local 1-105 centro comercial unilago', 'BOGOTA', 'KR 15 # 78 - 77', 'LOCAL 1-105 CENTRO COMERCIAL UNILAGO'],
    'ak abreviado' => ['Ak 9 110 50 local 301 centro comercial Santa Ana', 'BOGOTA', 'AK 9 # 110 - 50', 'LOCAL 301 CENTRO COMERCIAL SANTA ANA'],
    'sur sin numeral' => ['CARRERA 43A 1 SUR 150', 'MEDELLIN', 'KR 43A # 1 SUR - 150', null],
    'interior y edificio' => ['CARRERA 33 7 29 INTERIOR 101 EDIFICIO BI, MEDELLIN', 'MEDELLIN', 'KR 33 # 7 - 29', 'INTERIOR 101 EDIFICIO BI'],
    'plaza' => ['CR 7 115 60 PLAZA ENTRADA PRINCIPAL, BOGOTA', 'BOGOTA', 'KR 7 # 115 - 60', 'PLAZA ENTRADA PRINCIPAL'],
    'edificio' => ['Calle 21 # 1 - 20 edificio santo domingo', 'BOGOTÁ', 'CL 21 # 1 - 20', 'EDIFICIO SANTO DOMINGO'],
    'placa con espacio' => ['Calle 26 # 33 07', 'BOGOTÁ', 'CL 26 # 33 - 07', null],
    'pegada completa' => ['Carrera 44a#46-49', 'BELLO', 'KR 44A # 46 - 49', null],
]);

it('normaliza las direcciones reales de Credibanco', function (string $original, string $ciudad, string $principal, ?string $complemento) {
    $resultado = (new NormalizadorDirecciones)->normalizar($original, $ciudad);

    expect($resultado->principal)->toBe($principal)
        ->and($resultado->complemento)->toBe($complemento)
        ->and($resultado->estructurada)->toBeTrue();
})->with('direcciones reales');

it('produce la misma clave para dos formas de escribir la misma dirección', function () {
    $normalizador = new NormalizadorDirecciones;

    expect($normalizador->normalizar('Cr7 # 61- 48', 'BOGOTA')->clave)
        ->toBe($normalizador->normalizar('CR 7 61 48, BOGOTA', 'BOGOTA')->clave)
        ->toBe('KR7#61-48');
});

it('ignora los ceros a la izquierda al comparar', function () {
    $normalizador = new NormalizadorDirecciones;

    expect($normalizador->normalizar('CL 20 # 3 - 05')->clave)
        ->toBe($normalizador->normalizar('Calle 20 #3-5')->clave);
});

it('no usa el complemento para comparar', function () {
    $normalizador = new NormalizadorDirecciones;

    expect($normalizador->normalizar('Calle 116 #18b-09 local 2', 'BOGOTA')->clave)
        ->toBe($normalizador->normalizar('CL 116 # 18B - 09 LC 5 PISO 3', 'BOGOTA')->clave);
});

it('marca como rural una vía por kilómetro', function () {
    $resultado = (new NormalizadorDirecciones)->normalizar('Km1 via Mosquera la mesa', 'BOGOTA');

    expect($resultado->principal)->toBe('KM 1 VIA MOSQUERA LA MESA')
        ->and($resultado->esRural)->toBeTrue()
        ->and($resultado->estructurada)->toBeFalse();
});

it('marca como rural una vereda y quita la ciudad del final', function () {
    $resultado = (new NormalizadorDirecciones)->normalizar('VRD EL SILENCIO, SAN LUIS', 'SAN LUIS');

    expect($resultado->principal)->toBe('VRD EL SILENCIO')
        ->and($resultado->esRural)->toBeTrue();
});

it('marca como rural una dirección urbana con kilómetro en el complemento', function () {
    $resultado = (new NormalizadorDirecciones)->normalizar('Calle 10 # 22-039 km 5 via paso La torre', 'PALMIRA');

    expect($resultado->principal)->toBe('CL 10 # 22 - 039')
        ->and($resultado->complemento)->toBe('KM 5 VIA PASO LA TORRE')
        ->and($resultado->esRural)->toBeTrue();
});

it('separa el local cuando no hay nomenclatura', function () {
    $resultado = (new NormalizadorDirecciones)->normalizar('Centro comercial viva Envigado local 212Z nivel 2', 'ENVIGADO');

    expect($resultado->principal)->toBe('CENTRO COMERCIAL VIVA ENVIGADO')
        ->and($resultado->complemento)->toBe('LOCAL 212Z NIVEL 2')
        ->and($resultado->esRural)->toBeFalse()
        ->and($resultado->estructurada)->toBeFalse();
});

it('respeta la geolocalización rural que envía Credibanco', function () {
    $resultado = (new NormalizadorDirecciones)->normalizar('Calle 5 # 3-20', 'SAN LUIS', 'Rural');

    expect($resultado->esRural)->toBeTrue();
});

it('devuelve un marcador cuando la dirección llega vacía', function () {
    expect((new NormalizadorDirecciones)->normalizar('  ')->principal)->toBe('SIN DIRECCION');
});
