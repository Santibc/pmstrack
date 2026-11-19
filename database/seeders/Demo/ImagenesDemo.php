<?php

namespace Database\Seeders\Demo;

/**
 * Dibuja evidencias ficticias para la demostración: una "foto" de entrega
 * (caja de rollos, datáfono y recibo con sello) y una firma.
 */
final class ImagenesDemo
{
    /**
     * @return string Ruta de un JPEG temporal.
     */
    public static function foto(int $semilla, bool $fachada = false): string
    {
        mt_srand($semilla);
        $ancho = 1280;
        $alto = 960;
        $img = imagecreatetruecolor($ancho, $alto);
        imageantialias($img, true);

        $color = fn (int $r, int $g, int $b) => imagecolorallocate($img, $r, $g, $b);
        $tono = mt_rand(-12, 12);

        // Pared y mostrador.
        imagefilledrectangle($img, 0, 0, $ancho, $alto, $color(232 + $tono, 222 + $tono, 208 + $tono));
        imagefilledrectangle($img, 0, 0, $ancho, 70, $color(214 + $tono, 200 + $tono, 182 + $tono));

        if ($fachada) {
            // Fachada con la reja abajo.
            imagefilledrectangle($img, 180, 190, 1100, 960, $color(96, 104, 114));
            for ($y = 200; $y < 960; $y += 38) {
                imagefilledrectangle($img, 180, $y, 1100, $y + 6, $color(70, 77, 86));
            }
            imagefilledrectangle($img, 140, 110, 1140, 190, $color(52, 58, 66));
            imagefilledrectangle($img, 0, 880, $ancho, $alto, $color(150, 150, 150));
        } else {
            imagefilledrectangle($img, 0, 560, $ancho, $alto, $color(146 + $tono, 96 + $tono, 62 + $tono));
            imagefilledrectangle($img, 0, 560, $ancho, 588, $color(120 + $tono, 76 + $tono, 46 + $tono));

            // Caja de rollos.
            $x = 120 + mt_rand(0, 60);
            imagefilledrectangle($img, $x, 300, $x + 470, 600, $color(203, 166, 122));
            imagefilledrectangle($img, $x, 300, $x + 470, 330, $color(186, 148, 104));
            imageline($img, $x + 235, 300, $x + 235, 330, $color(150, 116, 78));
            imagefilledrectangle($img, $x + 130, 420, $x + 340, 492, $color(240, 125, 46));
            imagestring($img, 5, $x + 196, 448, 'ROLLOS', $color(31, 35, 40));

            // Rollos sueltos.
            foreach ([0, 150, 300] as $i => $dx) {
                $cx = $x + 90 + $dx + mt_rand(-8, 8);
                imagefilledellipse($img, $cx, 660, 136, 136, $color(250, 250, 248));
                imageellipse($img, $cx, 660, 136, 136, $color(205, 205, 200));
                imagefilledellipse($img, $cx, 660, 44, 44, $color(200, 200, 196));
            }

            // Datáfono.
            $dx = 800 + mt_rand(-30, 30);
            imagefilledrectangle($img, $dx, 300, $dx + 190, 640, $color(38, 44, 54));
            imagefilledrectangle($img, $dx + 46, 250, $dx + 144, 312, $color(252, 252, 250));
            imagefilledrectangle($img, $dx + 26, 340, $dx + 164, 440, $color(118, 170, 222));
            for ($f = 0; $f < 3; $f++) {
                for ($c = 0; $c < 3; $c++) {
                    imagefilledellipse($img, $dx + 52 + $c * 44, 490 + $f * 46, 24, 24, $color(112, 122, 136));
                }
            }

            // Recibo con sello.
            $rx = 640 + mt_rand(-20, 20);
            imagefilledpolygon($img, [$rx, 700, $rx + 430, 668, $rx + 446, 820, $rx + 18, 850], $color(252, 252, 250));
            for ($l = 0; $l < 3; $l++) {
                imagefilledrectangle($img, $rx + 40, 730 + $l * 26, $rx + 200, 738 + $l * 26, $color(190, 194, 200));
            }
            imagesetthickness($img, 5);
            imageellipse($img, $rx + 330, 760, 170, 84, $color(96, 72, 190));
            imagesetthickness($img, 1);
            imagestring($img, 5, $rx + 294, 752, 'RECIBIDO', $color(96, 72, 190));
        }

        $ruta = tempnam(sys_get_temp_dir(), 'pmsdemo').'.jpg';
        imagejpeg($img, $ruta, 84);

        return $ruta;
    }

    /**
     * @return string Ruta de un PNG temporal con una firma.
     */
    public static function firma(int $semilla): string
    {
        mt_srand($semilla);
        $ancho = 640;
        $alto = 240;
        $img = imagecreatetruecolor($ancho, $alto);
        imageantialias($img, true);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $tinta = imagecolorallocate($img, 30, 58, 138);
        imagesetthickness($img, 4);

        $a = mt_rand(26, 50);
        $f1 = mt_rand(26, 40) / 1000;
        $f2 = mt_rand(55, 80) / 1000;
        $fase = mt_rand(0, 628) / 100;
        $anterior = null;

        for ($x = 70; $x <= 520; $x += 3) {
            $progreso = ($x - 70) / 450;
            $y = 128 + sin($x * $f1 + $fase) * $a * (1 - $progreso * 0.55) + sin($x * $f2) * 16 - $progreso * 22;

            if ($anterior !== null) {
                imageline($img, $anterior[0], (int) $anterior[1], $x, (int) $y, $tinta);
            }

            $anterior = [$x, $y];
        }

        // Rúbrica final.
        imageline($img, 96, 186, 560, 172 + mt_rand(-8, 8), $tinta);

        $ruta = tempnam(sys_get_temp_dir(), 'pmsdemo').'.png';
        imagepng($img, $ruta);

        return $ruta;
    }
}
