<?php

declare(strict_types=1);

namespace App\Services\Visitas;

use GdImage;
use RuntimeException;

/**
 * Prepara la foto de evidencia: corrige la orientación, la reduce y le imprime
 * una franja con fecha, hora, coordenadas y número de guía (documento técnico 5.4).
 */
final class MarcaDeAgua
{
    /**
     * @return array{contenido: string, ancho: int, alto: int, mime: string}
     */
    public function aplicar(string $rutaOrigen, string $texto, int $anchoMaximo, int $calidad): array
    {
        $imagen = $this->abrir($rutaOrigen);
        $imagen = $this->orientar($imagen, $rutaOrigen);
        $imagen = $this->reducir($imagen, $anchoMaximo);

        $this->imprimirFranja($imagen, $texto);

        ob_start();
        imagejpeg($imagen, null, $calidad);
        $contenido = (string) ob_get_clean();

        return [
            'contenido' => $contenido,
            'ancho' => imagesx($imagen),
            'alto' => imagesy($imagen),
            'mime' => 'image/jpeg',
        ];
    }

    private function abrir(string $ruta): GdImage
    {
        $datos = @file_get_contents($ruta);
        $imagen = $datos === false ? false : @imagecreatefromstring($datos);

        if ($imagen === false) {
            throw new RuntimeException('La foto no se pudo leer.');
        }

        // Fondo blanco para imágenes con transparencia (PNG, WebP).
        $lienzo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

        return $lienzo;
    }

    /**
     * Las fotos del celular traen la orientación en EXIF; se aplica para que no queden giradas.
     */
    private function orientar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $angulo = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angulo === 0) {
            return $imagen;
        }

        $girada = imagerotate($imagen, $angulo, 0);

        return $girada === false ? $imagen : $girada;
    }

    private function reducir(GdImage $imagen, int $anchoMaximo): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $mayor = max($ancho, $alto);

        if ($mayor <= $anchoMaximo) {
            return $imagen;
        }

        $escala = $anchoMaximo / $mayor;
        $reducida = imagescale($imagen, (int) round($ancho * $escala), (int) round($alto * $escala), IMG_BICUBIC);

        return $reducida === false ? $imagen : $reducida;
    }

    private function imprimirFranja(GdImage $imagen, string $texto): void
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $fuente = resource_path('fonts/DejaVuSans-Bold.ttf');
        $tamano = max(11, (int) round($ancho / 46));
        $margen = (int) round($tamano * 0.9);
        $altoFranja = $tamano + $margen * 2;

        imagealphablending($imagen, true);
        imagefilledrectangle($imagen, 0, $alto - $altoFranja, $ancho, $alto, imagecolorallocatealpha($imagen, 16, 18, 22, 38));
        $blanco = imagecolorallocate($imagen, 255, 255, 255);

        if (is_file($fuente) && function_exists('imagettftext')) {
            // Reduce el texto hasta que quepa en el ancho de la foto.
            while ($tamano > 8) {
                $caja = imagettfbbox($tamano, 0, $fuente, $texto);

                if ($caja !== false && ($caja[2] - $caja[0]) <= $ancho - $margen * 2) {
                    break;
                }

                $tamano--;
            }

            imagettftext($imagen, $tamano, 0, $margen, $alto - $margen - (int) round(($altoFranja - $margen * 2 - $tamano) / 2), $blanco, $fuente, $texto);

            return;
        }

        imagestring($imagen, 5, $margen, $alto - $altoFranja + (int) (($altoFranja - 15) / 2), $texto, $blanco);
    }
}
