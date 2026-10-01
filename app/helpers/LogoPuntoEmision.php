<?php

declare(strict_types=1);

namespace App\Helpers;

use App\repositories\modulos\EmpresaRepository;

/**
 * Logo de un documento según su punto de emisión.
 *
 * Regla: si el punto de emisión del documento tiene logo propio
 * (empresa_punto_emision.logo_ruta), el PDF/correo usa ese; si no, se queda con
 * el logo del establecimiento, que es lo que cada ruta de PDF ya cargó en
 * $empresa['logo_ruta'] antes de llamar aquí.
 *
 * Uso: después de armar $empresa (con el establecimiento y su config ya
 * mezclados) y antes de generar el PDF:
 *
 *     LogoPuntoEmision::aplicar($empresa, $idEmpresa, $doc['id_punto_emision'] ?? null);
 *
 * Solo aplica a documentos con serie (factura, NC, ND, retención, guía, recibo,
 * proforma, pedido…). Los reportes y listados no tienen punto y siguen con el
 * logo del establecimiento.
 */
final class LogoPuntoEmision
{
    /** Cache por request: id_empresa:id_punto => logo ('' = sin logo propio). */
    private static array $cache = [];

    /** Logo propio del punto ('' si no tiene, si no se indica punto o si falla la consulta). */
    public static function ruta(int $idEmpresa, $idPuntoEmision): string
    {
        $idPunto = (int) ($idPuntoEmision ?? 0);
        if ($idEmpresa <= 0 || $idPunto <= 0) {
            return '';
        }
        $clave = $idEmpresa . ':' . $idPunto;
        if (!array_key_exists($clave, self::$cache)) {
            try {
                self::$cache[$clave] = (new EmpresaRepository())->getLogoPuntoEmision($idPunto, $idEmpresa);
            } catch (\Throwable $e) {
                // Sin el logo del punto el documento sale igual con el del establecimiento.
                self::$cache[$clave] = '';
            }
        }
        return self::$cache[$clave];
    }

    /**
     * Si el punto tiene logo propio, reemplaza el del establecimiento en $empresa['logo_ruta'],
     * que es la clave que leen todos los servicios PDF (misma URL pública que el del establecimiento).
     */
    public static function aplicar(array &$empresa, int $idEmpresa, $idPuntoEmision): void
    {
        $logo = self::ruta($idEmpresa, $idPuntoEmision);
        if ($logo !== '') {
            $empresa['logo_ruta'] = $logo;
        }
    }

    /** Logo final del documento: el del punto o, si no tiene, el recibido del establecimiento. */
    public static function resolver(int $idEmpresa, $idPuntoEmision, string $logoEstablecimiento): string
    {
        $logo = self::ruta($idEmpresa, $idPuntoEmision);
        return $logo !== '' ? $logo : $logoEstablecimiento;
    }
}
