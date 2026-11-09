<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * CRM fase 3: reglas de la biblioteca de medios (docs/diseno/crm-redes-fase3.md).
 * Todo se valida por el CONTENIDO del archivo, no por su nombre ni por lo que
 * diga el navegador.
 */
class CrmMedioRules
{
    /** Imágenes que se aceptan al subir; siempre se guardan como JPEG (Instagram solo publica JPEG). */
    public const IMAGEN_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    public const IMAGEN_MAX_BYTES = 20 * 1024 * 1024;   // al subir; el JPEG final queda muy por debajo de 8 MB
    public const IMAGEN_MIN_LADO = 320;
    public const IMAGEN_MAX_LADO = 1440;               // lado mayor del JPEG guardado
    public const IMAGEN_MAX_PIXELES = 40_000_000;      // para no agotar la memoria al decodificar

    /** Videos (reels). Decisión 4 del diseño: 100 MB y 90 s. */
    public const VIDEO_MIME = ['video/mp4', 'video/quicktime'];
    public const VIDEO_MAX_BYTES = 100 * 1024 * 1024;
    public const VIDEO_MIN_SEG = 3;
    public const VIDEO_MAX_SEG = 90;
    public const VIDEO_CODECS = ['avc1', 'avc3', 'hvc1', 'hev1'];   // H.264 / H.265
    public const VIDEO_MAX_LADO = 1920;

    public const DIAS_PURGA = 90;

    /** @return string 'imagen' | 'video' */
    public function tipoDe(string $rutaTmp, int $bytes): string
    {
        if ($rutaTmp === '' || !is_file($rutaTmp) || $bytes <= 0) {
            throw new \InvalidArgumentException('No se recibió el archivo.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTmp);
        if (in_array($mime, self::IMAGEN_MIME, true)) {
            return 'imagen';
        }
        if (in_array($mime, self::VIDEO_MIME, true)) {
            return 'video';
        }
        throw new \InvalidArgumentException('Solo se admiten imágenes (JPG, PNG, WEBP, GIF) y videos MP4 o MOV.');
    }

    /** @return array{ancho:int, alto:int} */
    public function validarImagen(string $rutaTmp, int $bytes): array
    {
        if ($bytes > self::IMAGEN_MAX_BYTES) {
            throw new \InvalidArgumentException('La imagen supera 20 MB.');
        }
        $i = @getimagesize($rutaTmp);
        if (!$i || $i[0] <= 0 || $i[1] <= 0) {
            throw new \InvalidArgumentException('El archivo no es una imagen válida.');
        }
        if ($i[0] * $i[1] > self::IMAGEN_MAX_PIXELES) {
            throw new \InvalidArgumentException('La imagen es demasiado grande en píxeles. Redúzcala antes de subirla.');
        }
        if (min($i[0], $i[1]) < self::IMAGEN_MIN_LADO) {
            throw new \InvalidArgumentException('La imagen es muy pequeña: mínimo ' . self::IMAGEN_MIN_LADO . ' píxeles por lado.');
        }
        return ['ancho' => (int) $i[0], 'alto' => (int) $i[1]];
    }

    /** $info: resultado de Mp4Info::leer(). */
    public function validarVideo(?array $info, int $bytes): array
    {
        if ($bytes > self::VIDEO_MAX_BYTES) {
            throw new \InvalidArgumentException('El video supera 100 MB.');
        }
        if (!$info) {
            throw new \InvalidArgumentException('No se pudo leer el video. Expórtelo como MP4 (H.264) e intente de nuevo.');
        }
        if ($info['duracion'] < self::VIDEO_MIN_SEG || $info['duracion'] > self::VIDEO_MAX_SEG) {
            throw new \InvalidArgumentException('El video debe durar entre ' . self::VIDEO_MIN_SEG . ' y ' . self::VIDEO_MAX_SEG . ' segundos (dura '
                . number_format($info['duracion'], 1, ',', '.') . ').');
        }
        if (!in_array($info['codec'], self::VIDEO_CODECS, true)) {
            throw new \InvalidArgumentException('El video debe estar en H.264 o H.265 (HEVC). Expórtelo de nuevo como MP4.');
        }
        if (max($info['ancho'], $info['alto']) > self::VIDEO_MAX_LADO) {
            throw new \InvalidArgumentException('El video supera 1920 píxeles por lado. Expórtelo en 1080p.');
        }
        return $info;
    }

    /**
     * Avisos (no bloquean): proporciones que Instagram recorta en el feed
     * (admite de 4:5 vertical a 1,91:1 horizontal) y videos no verticales para reels.
     */
    public function avisos(string $tipo, int $ancho, int $alto): array
    {
        if ($ancho <= 0 || $alto <= 0) {
            return [];
        }
        $r = $ancho / $alto;
        if ($tipo === 'imagen' && ($r < 0.8 - 0.01 || $r > 1.91 + 0.01)) {
            return ['Instagram recortará esta imagen en el feed: admite de 4:5 (vertical) a 1,91:1 (horizontal).'];
        }
        if ($tipo === 'video' && $r > 0.6) {
            return ['Para reels conviene un video vertical 9:16 (por ejemplo 1080 × 1920).'];
        }
        return [];
    }

    public function validarTextoAlternativo(?string $alt): ?string
    {
        $alt = trim((string) $alt);
        if ($alt === '') {
            return null;
        }
        if (mb_strlen($alt) > 1000) {
            throw new \InvalidArgumentException('El texto alternativo admite hasta 1000 caracteres.');
        }
        return $alt;
    }
}
