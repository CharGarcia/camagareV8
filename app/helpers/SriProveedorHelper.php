<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Resolución SRI NAC-DGERCGC26-00000027 (Art. 5): todo comprobante electrónico debe
 * incluir, en la sección de información adicional, el número de RUC del PROVEEDOR del
 * sistema informático de facturación electrónica (no el del emisor, que ya viaja en
 * infoTributaria). El RUC es global (config 'sri_ruc_proveedor_sistema').
 *
 * Lo consumen los generadores XML de los 5 comprobantes (factura, NC, liquidación,
 * guía, retención) y los RIDE, para que el impreso refleje lo mismo que el XML.
 */
class SriProveedorHelper
{
    /**
     * Nombre OFICIAL del campoAdicional según la Ficha Técnica v2.34, Anexo 26:
     * atributo nombre = "RUC Proveedor" (alfanumérico, máx. 300).
     */
    public const CAMPO_NOMBRE = 'RUC Proveedor';

    /** Máximo de campos de infoAdicional permitido por la Ficha Técnica (punto 9.11). */
    private const MAX_CAMPOS = 15;

    /**
     * RUC del proveedor del sistema. Fuente primaria: tabla global
     * configuracion_sistema (editable en /config/sri-proveedor, nivel 3);
     * respaldo: config/app.php ('sri_ruc_proveedor_sistema') para instalaciones
     * donde la migración aún no corrió. Vacío = requisito apagado.
     */
    public static function rucProveedor(): string
    {
        static $ruc = null;
        if ($ruc !== null) {
            return $ruc;
        }

        $ruc = '';
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare(
                "SELECT valor FROM configuracion_sistema
                  WHERE clave = 'sri_ruc_proveedor_sistema' AND eliminado = false
                  LIMIT 1"
            );
            $st->execute();
            $ruc = trim((string) ($st->fetchColumn() ?: ''));
        } catch (\Throwable $e) {
            // Tabla aún no migrada u otro problema de BD: se usa el respaldo de config.
        }

        if ($ruc === '') {
            $cfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
            $ruc = trim((string) ($cfg['sri_ruc_proveedor_sistema'] ?? ''));
        }
        return $ruc;
    }

    /**
     * Devuelve la información adicional del documento CON el campo del RUC del proveedor.
     *
     * - Si la config está vacía, devuelve el arreglo tal cual (requisito desactivado).
     * - No duplica: si el campo ya está (p. ej. re-emisión), lo deja como está.
     * - Respeta el tope de 15 campos: el campo normativo SIEMPRE entra; si con él se
     *   supera el tope, se recortan los últimos campos del usuario (cumplimiento ante
     *   el SRI manda sobre campos informativos) y se deja rastro en el log.
     *
     * @param array $infoAdicional Filas con claves 'nombre' y 'valor'
     */
    public static function conRucProveedor(array $infoAdicional): array
    {
        $ruc = self::rucProveedor();
        if ($ruc === '') {
            return $infoAdicional;
        }

        // Si ya existe un campo con ese nombre (re-emisión o agregado a mano), se FUERZA
        // el valor oficial de la config: el RUC del proveedor no es editable por el usuario.
        foreach ($infoAdicional as $i => $ia) {
            if (strcasecmp(trim((string) ($ia['nombre'] ?? '')), self::CAMPO_NOMBRE) === 0) {
                $infoAdicional[$i]['nombre'] = self::CAMPO_NOMBRE;
                $infoAdicional[$i]['valor']  = $ruc;
                return $infoAdicional;
            }
        }

        if (count($infoAdicional) >= self::MAX_CAMPOS) {
            $exceso = count($infoAdicional) - (self::MAX_CAMPOS - 1);
            error_log('[SriProveedorHelper] infoAdicional excede el tope de ' . self::MAX_CAMPOS
                . ' campos: se recortaron ' . $exceso . ' campo(s) del final para incluir el RUC del proveedor (Res. NAC-DGERCGC26-00000027).');
            $infoAdicional = array_slice($infoAdicional, 0, self::MAX_CAMPOS - 1);
        }

        $infoAdicional[] = ['nombre' => self::CAMPO_NOMBRE, 'valor' => $ruc];
        return $infoAdicional;
    }
}
