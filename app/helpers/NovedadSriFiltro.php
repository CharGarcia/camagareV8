<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Filtro del buscador `sri:novedad`: documentos electrónicos cuyo ÚLTIMO intento
 * de envío al SRI (tabla `sri_envio_log`, en el ambiente actual de la empresa)
 * terminó devuelto, no autorizado o con error.
 *
 * Es el mismo criterio del aviso "Documentos con novedad del SRI" del navbar
 * (`ContadoresNavbarRepository::getNovedadesSri()`): al hacer clic en el aviso,
 * el módulo se abre con este filtro y muestra exactamente esos documentos.
 *
 * Solo arma el fragmento SQL (con parámetros PDO); lo ejecuta el repository.
 * Uso en el getListado() del repository, ANTES de FiltrosBusqueda::aplicarFiltros():
 *   NovedadSriFiltro::aplicar($where, $params, $filtros, 'factura_venta', 'v.id', $idEmpresa);
 */
class NovedadSriFiltro
{
    /** Acciones de `sri_envio_log` que significan "el SRI no aceptó el documento". */
    public const ACCIONES = ['devuelta', 'no_autorizado', 'no_autorizada', 'error'];

    /** Valores aceptados en `sri:...` (sinónimos). */
    private const VALORES = ['novedad', 'novedades', 'rechazado', 'rechazada', 'rechazados', 'devuelto', 'devuelta', 'error'];

    /** `tipo_comprobante` válidos en `sri_envio_log` (whitelist: van interpolados). */
    private const TIPOS = ['factura_venta', 'liquidacion_compra', 'retencion_compra', 'nota_credito', 'guia_remision', 'nota_debito'];

    /**
     * Si en $filtros viene la clave `sri` (o `novedad`), la quita de $filtros (para que
     * aplicarFiltros() no la vea) y agrega la condición al WHERE. `-sri:novedad` niega.
     *
     * @param string $colId Columna id del documento en la consulta (p. ej. 'v.id'); constante del repository.
     */
    public static function aplicar(string &$where, array &$params, array &$filtros, string $tipoComprobante, string $colId, int $idEmpresa): void
    {
        $filtro = $filtros['sri'] ?? $filtros['novedad'] ?? null;
        unset($filtros['sri'], $filtros['novedad']);
        if ($filtro === null || !in_array($tipoComprobante, self::TIPOS, true)) {
            return;
        }

        $valores = is_array($filtro['valor'] ?? null) ? $filtro['valor'] : [$filtro['valor'] ?? ''];
        $valido = false;
        foreach ($valores as $v) {
            if (in_array(strtolower(trim((string) $v)), self::VALORES, true)) {
                $valido = true;
                break;
            }
        }
        if (!$valido) {
            return;
        }

        $acciones = "'" . implode("','", self::ACCIONES) . "'";
        $cond = "{$colId} IN (
            SELECT u.id_comprobante FROM (
                SELECT DISTINCT ON (l.id_comprobante) l.id_comprobante, l.accion
                FROM sri_envio_log l
                WHERE l.id_empresa = :nsri_emp
                  AND l.tipo_comprobante = '{$tipoComprobante}'
                  AND l.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :nsri_emp2)
                ORDER BY l.id_comprobante, l.id DESC
            ) u
            WHERE u.accion IN ({$acciones}))";

        $params[':nsri_emp']  = $idEmpresa;
        $params[':nsri_emp2'] = $idEmpresa;
        $where .= !empty($filtro['neg']) ? " AND NOT ({$cond})" : " AND {$cond}";
    }
}
