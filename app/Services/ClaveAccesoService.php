<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Genera claves de acceso de 49 dígitos según el estándar SRI Ecuador.
 *
 * Estructura de la clave:
 *   [8]  Fecha de emisión          ddmmaaaa
 *   [2]  Tipo de comprobante       01=Factura, 03=Liquidación, 04=NC, 05=ND, 06=Guía, 07=Retención
 *   [13] RUC del emisor
 *   [1]  Tipo de ambiente          1=Pruebas, 2=Producción
 *   [3]  Establecimiento
 *   [3]  Punto de emisión
 *   [9]  Secuencial (con padding de ceros)
 *   [8]  Código numérico           aleatorio, se genera una vez y se reutiliza
 *   [1]  Tipo de emisión           1=Normal
 *   [1]  Dígito verificador        módulo 11
 */
class ClaveAccesoService
{
    // Códigos de tipo de comprobante SRI
    public const FACTURA_VENTA      = '01';
    public const LIQUIDACION_COMPRA = '03';
    public const NOTA_CREDITO       = '04';
    public const NOTA_DEBITO        = '05';
    public const GUIA_REMISION      = '06';
    public const RETENCION          = '07';

    /**
     * Genera una clave de acceso de 49 dígitos.
     *
     * @param string      $fechaEmision    Fecha en formato Y-m-d o d/m/Y
     * @param string      $tipoComprobante Código SRI del tipo de documento (usar constantes de esta clase)
     * @param string      $ruc             RUC del emisor (13 dígitos)
     * @param string      $tipoAmbiente    '1' pruebas | '2' producción
     * @param string      $establecimiento Código del establecimiento (se rellena a 3 dígitos)
     * @param string      $puntoEmision    Código del punto de emisión (se rellena a 3 dígitos)
     * @param string      $secuencial      Número secuencial (se rellena a 9 dígitos)
     * @param string      $tipoEmision     '1' normal
     * @param string|null $codigoNumerico  8 dígitos; si es null se genera aleatoriamente
     */
    public static function generar(
        string  $fechaEmision,
        string  $tipoComprobante,
        string  $ruc,
        string  $tipoAmbiente,
        string  $establecimiento,
        string  $puntoEmision,
        string  $secuencial,
        string  $tipoEmision    = '1',
        ?string $codigoNumerico = null
    ): string {
        $fecha = self::formatearFecha($fechaEmision);
        $est   = str_pad($establecimiento, 3, '0', STR_PAD_LEFT);
        $pto   = str_pad($puntoEmision,    3, '0', STR_PAD_LEFT);
        $sec   = str_pad($secuencial,      9, '0', STR_PAD_LEFT);
        $cod   = $codigoNumerico !== null
            ? str_pad(substr($codigoNumerico, 0, 8), 8, '0', STR_PAD_LEFT)
            : self::codigoNumericoAleatorio();

        $base = $fecha
            . $tipoComprobante
            . $ruc
            . $tipoAmbiente
            . $est
            . $pto
            . $sec
            . $cod
            . $tipoEmision;

        return $base . self::modulo11($base);
    }

    /**
     * Extrae el código numérico (posiciones 40-47, indexadas desde 1) de una clave ya generada.
     * Permite reutilizarlo al regenerar la clave de un documento existente (ej: al editar en borrador).
     */
    public static function extraerCodigoNumerico(string $claveAcceso): ?string
    {
        if (strlen($claveAcceso) !== 49) {
            return null;
        }
        return substr($claveAcceso, 39, 8); // posiciones 40-47 (offset 39, longitud 8)
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private static function formatearFecha(string $fecha): string
    {
        // Y-m-d  → ddmmaaaa
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m)) {
            return $m[3] . $m[2] . $m[1];
        }
        // d/m/Y  → ddmmaaaa
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $fecha, $m)) {
            return $m[1] . $m[2] . $m[3];
        }
        throw new \InvalidArgumentException("Formato de fecha no reconocido para clave de acceso: '{$fecha}'");
    }

    private static function codigoNumericoAleatorio(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Dígito verificador módulo 11 (SRI).
     * Multiplica cada dígito (de derecha a izquierda) ciclando por [2, 3, 4, 5, 6, 7].
     * resto = suma % 11 → verificador = (resto <= 1) ? resto : 11 - resto
     */
    private static function modulo11(string $cadena): string
    {
        $suma    = 0;
        $ciclo   = [2, 3, 4, 5, 6, 7];
        $len     = strlen($cadena);

        for ($i = $len - 1, $j = 0; $i >= 0; $i--, $j++) {
            $suma += (int) $cadena[$i] * $ciclo[$j % 6];
        }

        $resto = $suma % 11;

        return (string) ($resto <= 1 ? $resto : 11 - $resto);
    }
}
