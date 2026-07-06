<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

/**
 * Repositorio del Anexo Transaccional Simplificado (ATS).
 *
 * Provee, filtrando SIEMPRE por id_empresa + eliminado = false, los datos
 * necesarios para construir el bloque <compras> del ats.xml:
 *   - Informante (empresa + nº de establecimientos).
 *   - Compras y liquidaciones del período (con bases IVA/ICE agregadas
 *     desde *_detalle_impuestos, porque compras_cabecera no las almacena).
 *   - Retenciones atadas a cada documento (retencion_compra_cabecera/detalle).
 *   - Formas de pago por documento.
 *
 * Las consultas son de solo lectura (reporte), por lo que no usan transacción.
 */
class AtsRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('compras_cabecera');
    }

    private function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Datos del informante: empresa + número de establecimientos activos.
     */
    public function getInformante(int $idEmpresa): ?array
    {
        $sql = "SELECT e.id, e.ruc, e.nombre AS razon_social, e.nombre_comercial,
                       e.tipo_ambiente,
                       (SELECT COUNT(*) FROM empresa_establecimiento est
                          WHERE est.id_empresa = e.id AND est.eliminado = false) AS num_establecimientos
                FROM empresas e
                WHERE e.id = :id AND e.eliminado = false";
        $row = $this->query($sql, [':id' => $idEmpresa])->fetch();
        return $row ?: null;
    }

    /**
     * Compras del período, filtradas por FECHA DE EMISIÓN del comprobante.
     * fechaRegistro del ATS se reporta igual a la fecha de emisión.
     * Las bases IVA/ICE se agregan desde compras_detalle_impuestos.
     */
    public function getCompras(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT c.id,
                       'compra' AS origen,
                       c.tipo_comprobante,
                       c.numero_autorizacion,
                       c.establecimiento_prov,
                       c.punto_emision_prov,
                       c.secuencial_prov,
                       c.fecha_emision,
                       c.fecha_emision AS fecha_registro,
                       c.importe_total,
                       c.parte_relacionada,
                       c.documento_modificado,
                       p.tipo_id_proveedor,
                       p.identificacion AS prov_identificacion,
                       p.razon_social   AS prov_razon_social,
                       p.relacionado    AS prov_relacionado,
                       p.tipo_empresa   AS prov_tipo_empresa,
                       st.codigo        AS cod_sustento,
                       imp.base_no_gra_iva, imp.base_imponible_0, imp.base_imponible_grav,
                       imp.base_imponible_exe, imp.monto_iva, imp.monto_ice
                FROM compras_cabecera c
                INNER JOIN proveedores p ON p.id = c.id_proveedor
                LEFT  JOIN sustento_tributario st ON st.id = c.id_sustento_tributario
                LEFT  JOIN LATERAL (
                    SELECT
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '6' THEN di.base_imponible END), 0) AS base_no_gra_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '0' THEN di.base_imponible END), 0) AS base_imponible_0,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.base_imponible END), 0) AS base_imponible_grav,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '7' THEN di.base_imponible END), 0) AS base_imponible_exe,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.valor END), 0) AS monto_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '3' THEN di.valor END), 0) AS monto_ice
                    FROM compras_detalle d
                    INNER JOIN compras_detalle_impuestos di ON di.id_compra_detalle = d.id
                    WHERE d.id_compra = c.id
                ) imp ON true
                WHERE c.id_empresa = :id_empresa
                  AND c.eliminado = false
                  AND COALESCE(c.tipo_ambiente, '1') = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND c.fecha_emision BETWEEN :desde AND :hasta
                ORDER BY c.fecha_emision, c.id";
        return $this->query($sql, [
            ':id_empresa' => $idEmpresa,
            ':desde'      => $desde,
            ':hasta'      => $hasta,
        ])->fetchAll();
    }

    /**
     * Liquidaciones de compra del período (tipoComprobante = 03).
     * Usa fecha_emision como fecha de registro/emisión.
     */
    public function getLiquidaciones(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT l.id,
                       'liquidacion' AS origen,
                       '03' AS tipo_comprobante,
                       l.numero_autorizacion,
                       l.establecimiento AS establecimiento_prov,
                       l.punto_emision   AS punto_emision_prov,
                       l.secuencial      AS secuencial_prov,
                       l.fecha_emision,
                       l.fecha_emision AS fecha_registro,
                       l.importe_total,
                       false AS parte_relacionada,
                       NULL  AS documento_modificado,
                       p.tipo_id_proveedor,
                       p.identificacion AS prov_identificacion,
                       p.razon_social   AS prov_razon_social,
                       p.relacionado    AS prov_relacionado,
                       p.tipo_empresa   AS prov_tipo_empresa,
                       st.codigo        AS cod_sustento,
                       imp.base_no_gra_iva, imp.base_imponible_0, imp.base_imponible_grav,
                       imp.base_imponible_exe, imp.monto_iva, imp.monto_ice
                FROM liquidaciones_cabecera l
                INNER JOIN proveedores p ON p.id = l.id_proveedor
                LEFT  JOIN sustento_tributario st ON st.id = l.id_sustento_tributario
                LEFT  JOIN LATERAL (
                    SELECT
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '6' THEN di.base_imponible END), 0) AS base_no_gra_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '0' THEN di.base_imponible END), 0) AS base_imponible_0,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.base_imponible END), 0) AS base_imponible_grav,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '7' THEN di.base_imponible END), 0) AS base_imponible_exe,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.valor END), 0) AS monto_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '3' THEN di.valor END), 0) AS monto_ice
                    FROM liquidaciones_detalle d
                    INNER JOIN liquidaciones_detalle_impuestos di ON di.id_detalle = d.id
                    WHERE d.id_cabecera = l.id
                ) imp ON true
                WHERE l.id_empresa = :id_empresa
                  AND l.eliminado = false
                  AND COALESCE(l.tipo_ambiente, '1') = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND l.fecha_emision BETWEEN :desde AND :hasta
                ORDER BY l.fecha_emision, l.id";
        return $this->query($sql, [
            ':id_empresa' => $idEmpresa,
            ':desde'      => $desde,
            ':hasta'      => $hasta,
        ])->fetchAll();
    }

    /**
     * Retenciones (cabecera + detalle) atadas a un conjunto de documentos.
     *
     * @param string $columnaVinculo 'id_compra' o 'id_liquidacion'
     * @param int[]  $ids            IDs de compras/liquidaciones
     * @return array Filas planas cabecera+detalle (indexar por id en el Service)
     */
    public function getRetenciones(int $idEmpresa, string $columnaVinculo, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || !in_array($columnaVinculo, ['id_compra', 'id_liquidacion'], true)) {
            return [];
        }

        $place = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($ids as $i => $idv) {
            $ph = ":d{$i}";
            $place[] = $ph;
            $params[$ph] = $idv;
        }
        $inList = implode(',', $place);

        $sql = "SELECT rc.id AS id_retencion,
                       rc.{$columnaVinculo} AS id_documento,
                       rc.establecimiento, rc.punto_emision, rc.secuencial,
                       rc.numero_autorizacion, rc.fecha_emision,
                       rd.codigo_impuesto, rd.codigo_retencion, rd.concepto,
                       rd.base_imponible, rd.porcentaje_retener, rd.valor_retenido
                FROM retencion_compra_cabecera rc
                INNER JOIN retencion_compra_detalle rd ON rd.id_retencion = rc.id
                WHERE rc.id_empresa = :id_empresa
                  AND rc.eliminado = false
                  AND rc.estado <> 'anulada'
                  AND rc.{$columnaVinculo} IN ({$inList})
                ORDER BY rc.{$columnaVinculo}, rc.fecha_emision, rc.id, rd.id";
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Formas de pago de un conjunto de documentos.
     *
     * @param string $tabla     'compras_pagos' o 'liquidaciones_pagos'
     * @param string $columnaFk 'id_compra' o 'id_cabecera'
     * @param int[]  $ids
     * @return array Filas (id_documento, forma_pago)
     */
    public function getFormasPago(string $tabla, string $columnaFk, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []
            || !in_array($tabla, ['compras_pagos', 'liquidaciones_pagos', 'ventas_pagos'], true)
            || !in_array($columnaFk, ['id_compra', 'id_cabecera', 'id_venta'], true)) {
            return [];
        }

        $place = [];
        $params = [];
        foreach ($ids as $i => $idv) {
            $ph = ":p{$i}";
            $place[] = $ph;
            $params[$ph] = $idv;
        }
        $inList = implode(',', $place);

        $sql = "SELECT {$columnaFk} AS id_documento, forma_pago, total
                FROM {$tabla}
                WHERE {$columnaFk} IN ({$inList})
                ORDER BY {$columnaFk}, id";
        return $this->query($sql, $params)->fetchAll();
    }

    // ── VENTAS ───────────────────────────────────────────────────────────────

    /**
     * Ventas (facturas emitidas y autorizadas) del período, por fecha de emisión.
     * Una fila por factura, con bases IVA/ICE agregadas desde ventas_detalle_impuestos.
     * El Service las agrupa por cliente + tipoComprobante + tipoEmisión.
     */
    public function getVentas(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT v.id,
                       v.establecimiento, v.punto_emision, v.secuencial,
                       v.clave_acceso, v.importe_total,
                       c.tipo_id       AS cli_tipo_id,
                       c.identificacion AS cli_identificacion,
                       c.nombre        AS cli_nombre,
                       imp.base_no_gra_iva, imp.base_imponible_0, imp.base_imponible_grav,
                       imp.monto_iva, imp.monto_ice
                FROM ventas_cabecera v
                INNER JOIN clientes c ON c.id = v.id_cliente
                LEFT  JOIN LATERAL (
                    SELECT
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '6' THEN di.base_imponible END), 0) AS base_no_gra_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje = '0' THEN di.base_imponible END), 0) AS base_imponible_0,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.base_imponible END), 0) AS base_imponible_grav,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '2' AND di.codigo_porcentaje NOT IN ('0','6','7') THEN di.valor END), 0) AS monto_iva,
                        COALESCE(SUM(CASE WHEN di.codigo_impuesto = '3' THEN di.valor END), 0) AS monto_ice
                    FROM ventas_detalle d
                    INNER JOIN ventas_detalle_impuestos di ON di.id_venta_detalle = d.id
                    WHERE d.id_venta = v.id
                ) imp ON true
                WHERE v.id_empresa = :id_empresa
                  AND v.eliminado = false
                  AND v.estado = 'autorizado'
                  AND COALESCE(v.tipo_ambiente, '1') = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND v.fecha_emision BETWEEN :desde AND :hasta
                ORDER BY v.id";
        return $this->query($sql, [
            ':id_empresa' => $idEmpresa,
            ':desde'      => $desde,
            ':hasta'      => $hasta,
        ])->fetchAll();
    }

    /**
     * Retenciones que los clientes nos practicaron, por venta.
     * @param int[] $idsVenta
     * @return array Filas (id_venta, codigo_impuesto, valor_retenido)
     */
    public function getRetencionesVenta(int $idEmpresa, array $idsVenta): array
    {
        $idsVenta = array_values(array_unique(array_map('intval', $idsVenta)));
        if ($idsVenta === []) {
            return [];
        }
        $place = [];
        $params = [':id_empresa' => $idEmpresa];
        foreach ($idsVenta as $i => $idv) {
            $ph = ":v{$i}";
            $place[] = $ph;
            $params[$ph] = $idv;
        }
        $inList = implode(',', $place);

        $sql = "SELECT rc.id_venta, rd.codigo_impuesto, rd.valor_retenido
                FROM retencion_venta_cabecera rc
                INNER JOIN retencion_venta_detalle rd ON rd.id_retencion = rc.id
                WHERE rc.id_empresa = :id_empresa
                  AND rc.eliminado = false
                  AND rc.id_venta IN ({$inList})";
        return $this->query($sql, $params)->fetchAll();
    }

    /** Códigos de establecimiento activos del RUC (para ventasEstablecimiento). */
    public function getEstablecimientos(int $idEmpresa): array
    {
        $sql = "SELECT codigo
                FROM empresa_establecimiento
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY codigo";
        return array_column($this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll(), 'codigo');
    }

    /**
     * Comprobantes anulados del período (por fecha de emisión).
     * Por ahora, facturas de venta con estado anulado (tipoComprobante 01).
     */
    public function getAnulados(int $idEmpresa, string $desde, string $hasta): array
    {
        $sql = "SELECT '01' AS tipo_comprobante,
                       establecimiento, punto_emision, secuencial, clave_acceso
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa
                  AND eliminado = false
                  AND estado IN ('anulado','anulada')
                  AND COALESCE(tipo_ambiente, '1') = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                  AND fecha_emision BETWEEN :desde AND :hasta
                ORDER BY establecimiento, punto_emision, secuencial";
        return $this->query($sql, [
            ':id_empresa' => $idEmpresa,
            ':desde'      => $desde,
            ':hasta'      => $hasta,
        ])->fetchAll();
    }
}
