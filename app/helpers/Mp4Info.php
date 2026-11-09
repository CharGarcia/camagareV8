<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Lee de un MP4/MOV la duración, el tamaño del video y el códec, recorriendo
 * sus cajas (ISO BMFF) sin cargar el archivo en memoria ni depender de ffprobe.
 * Lo usa la biblioteca de medios del CRM (fase 3) para validar los reels.
 *
 * Devuelve null si el archivo no es un MP4/MOV legible.
 * @return array{duracion:float, ancho:int, alto:int, codec:string, audio:string}|null
 */
final class Mp4Info
{
    private const CONTENEDORES = ['moov', 'trak', 'mdia', 'minf', 'stbl'];

    public static function leer(string $ruta): ?array
    {
        $f = @fopen($ruta, 'rb');
        if (!$f) {
            return null;
        }
        try {
            $tam = (int) filesize($ruta);
            $ftyp = fread($f, 12);
            if (strlen($ftyp) < 12 || substr($ftyp, 4, 4) !== 'ftyp') {
                return null;
            }
            $info = ['duracion' => 0.0, 'ancho' => 0, 'alto' => 0, 'codec' => '', 'audio' => ''];
            $pista = null;   // datos de la pista que se está leyendo
            self::recorrer($f, 0, $tam, $info, $pista, 0);
            return $info['duracion'] > 0 ? $info : null;
        } finally {
            fclose($f);
        }
    }

    /** Recorre las cajas entre $inicio y $fin. */
    private static function recorrer($f, int $inicio, int $fin, array &$info, ?array &$pista, int $nivel): void
    {
        $pos = $inicio;
        $vueltas = 0;
        while ($pos + 8 <= $fin && $vueltas++ < 10000 && $nivel < 8) {
            fseek($f, $pos);
            $cab = fread($f, 8);
            if (strlen($cab) < 8) {
                return;
            }
            $tam = unpack('N', substr($cab, 0, 4))[1];
            $tipo = substr($cab, 4, 4);
            $cabTam = 8;
            if ($tam === 1) {   // tamaño de 64 bits
                $ext = fread($f, 8);
                if (strlen($ext) < 8) {
                    return;
                }
                $p = unpack('N2', $ext);
                $tam = $p[1] * 4294967296 + $p[2];
                $cabTam = 16;
            } elseif ($tam === 0) {
                $tam = $fin - $pos;   // hasta el final
            }
            if ($tam < $cabTam) {
                return;
            }
            $datos = $pos + $cabTam;
            if ($tipo === 'trak') {
                $pista = ['tipo' => '', 'ancho' => 0, 'alto' => 0, 'codec' => ''];
                self::recorrer($f, $datos, $pos + $tam, $info, $pista, $nivel + 1);
                if ($pista['tipo'] === 'vide' && $info['codec'] === '') {
                    $info['codec'] = $pista['codec'];
                    $info['ancho'] = $pista['ancho'];
                    $info['alto'] = $pista['alto'];
                } elseif ($pista['tipo'] === 'soun' && $info['audio'] === '') {
                    $info['audio'] = $pista['codec'];
                }
                $pista = null;
            } elseif (in_array($tipo, self::CONTENEDORES, true)) {
                self::recorrer($f, $datos, $pos + $tam, $info, $pista, $nivel + 1);
            } elseif ($tipo === 'mvhd') {
                $b = fread($f, 32);
                if (strlen($b) >= 20) {
                    if (ord($b[0]) === 1 && strlen($b) >= 32) {
                        $escala = unpack('N', substr($b, 20, 4))[1];
                        $d = unpack('N2', substr($b, 24, 8));
                        $dur = $d[1] * 4294967296 + $d[2];
                    } else {
                        $escala = unpack('N', substr($b, 12, 4))[1];
                        $dur = unpack('N', substr($b, 16, 4))[1];
                    }
                    $info['duracion'] = $escala > 0 ? round($dur / $escala, 2) : 0.0;
                }
            } elseif ($tipo === 'tkhd' && $pista !== null) {
                fseek($f, $pos + $tam - 8);   // ancho y alto: últimos 8 bytes (16.16)
                $wh = fread($f, 8);
                if (strlen($wh) === 8) {
                    $p = unpack('N2', $wh);
                    $pista['ancho'] = $p[1] >> 16;
                    $pista['alto'] = $p[2] >> 16;
                }
            } elseif ($tipo === 'hdlr' && $pista !== null) {
                $b = fread($f, 12);
                $pista['tipo'] = strlen($b) >= 12 ? substr($b, 8, 4) : '';
            } elseif ($tipo === 'stsd' && $pista !== null) {
                $b = fread($f, 16);   // versión/flags, cantidad, tamaño y formato de la 1.ª entrada
                $pista['codec'] = strlen($b) >= 16 ? substr($b, 12, 4) : '';
            }
            $pos += $tam;
        }
    }
}
