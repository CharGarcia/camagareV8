<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CuentasPorCobrarRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * CTE que calcula lo cobrado por factura desde ingresos_detalle.
     */
    private function getCteCobrado(): string
    {
        return "
            SELECT id2.id_referencia_documento AS id_venta,
                   SUM(id2.monto_cobrado)       AS total_cobrado
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic2
                   ON ic2.id = id2.id_ingreso
            WHERE id2.tipo_documento = 'FACTURA'
              AND ic2.estado    != 'anulado'
              AND ic2.eliminado  = false
            GROUP BY id2.id_referencia_documento
        ";
    }

    /**
     * CTE que calcula lo retenido por factura desde retencion_venta_cabecera.
     * Cubre dos vías de enlace: id_venta directo y num_doc_sustento en el detalle.
     */
    private function getCteRetenido(): string
    {
        return "
            SELECT tmp.id_venta, SUM(tmp.monto) AS total_retenido
            FROM (
                SELECT r.id_venta,
                       (r.total_renta + r.total_iva + r.total_isd) AS monto,
                       r.id AS id_ret
                FROM retencion_venta_cabecera r
                WHERE r.eliminado = false AND r.id_venta IS NOT NULL

                UNION

                SELECT vc.id AS id_venta,
                       (r.total_renta + r.total_iva + r.total_isd) AS monto,
                       r.id AS id_ret
                FROM retencion_venta_cabecera r
                JOIN retencion_venta_detalle rd ON rd.id_retencion = r.id
                JOIN ventas_cabecera vc
                     ON rd.num_doc_sustento = CONCAT(vc.establecimiento, '-', vc.punto_emision, '-', vc.secuencial)
                WHERE r.eliminado = false
            ) tmp
            GROUP BY tmp.id_venta
        ";
    }

    /**
     * CTE que calcula el total de notas de crédito aplicadas por número de factura.
     */
    private function getCteNC(): string
    {
        return "
            SELECT nc.num_doc_modificado,
                   SUM(nc.importe_total) AS total_nc
            FROM notas_credito_cabecera nc
            WHERE nc.estado   != 'anulado'
              AND nc.eliminado = false
            GROUP BY nc.num_doc_modificado
        ";
    }

    /**
     * Listado principal de cuentas por cobrar.
     */
    public function getListado(int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->buildWhere($idEmpresa, $filtros);

        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado() . "),
                 retenido AS (" . $this->getCteRetenido() . "),
                 nc_aplic AS (" . $this->getCteNC() . ")
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                c.id                        AS id_cliente,
                c.nombre                    AS cliente_nombre,
                c.identificacion            AS cliente_ruc,
                COALESCE(c.email,'')        AS cliente_email,
                COALESCE(c.telefono,'')     AS cliente_telefono,
                v.importe_total             AS total,
                COALESCE(cb.total_cobrado, 0)                                                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                    AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                v.fecha_emision + INTERVAL '1 day' * v.dias_credito AS fecha_vencimiento,
                v.dias_credito,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
            WHERE {$where}
            ORDER BY fecha_vencimiento ASC, v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas para las tarjetas superiores.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        // Para estadísticas, no aplicar filtro de estado pues queremos todos los saldos
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhere($idEmpresa, $filtrosSinEstado);

        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado() . "),
                 retenido AS (" . $this->getCteRetenido() . "),
                 nc_aplic AS (" . $this->getCteNC() . ")
            SELECT
                COUNT(v.id) AS total_facturas,
                SUM(v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) AS total_saldo,
                SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)
                    ELSE 0
                END) AS total_vencido,
                SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)
                    ELSE 0
                END) AS total_al_dia,
                COUNT(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE THEN 1
                END) AS facturas_vencidas
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_facturas'   => (int)($r['total_facturas']   ?? 0),
            'total_saldo'      => (float)($r['total_saldo']    ?? 0),
            'total_vencido'    => (float)($r['total_vencido']  ?? 0),
            'total_al_dia'     => (float)($r['total_al_dia']   ?? 0),
            'facturas_vencidas'=> (int)($r['facturas_vencidas'] ?? 0),
        ];
    }

    /**
     * Análisis de antigüedad (aging) para el gráfico.
     */
    public function getAntiguedad(int $idEmpresa, array $filtros): array
    {
        $filtrosSinEstado = array_merge($filtros, ['estado' => 'PENDIENTES']);
        [$where, $params] = $this->buildWhere($idEmpresa, $filtrosSinEstado);

        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado() . "),
                 retenido AS (" . $this->getCteRetenido() . "),
                 nc_aplic AS (" . $this->getCteNC() . ")
            SELECT
                SUM(CASE WHEN dias_vencido BETWEEN 1 AND 30
                    THEN saldo ELSE 0 END) AS tramo_1_30,
                SUM(CASE WHEN dias_vencido BETWEEN 31 AND 60
                    THEN saldo ELSE 0 END) AS tramo_31_60,
                SUM(CASE WHEN dias_vencido BETWEEN 61 AND 90
                    THEN saldo ELSE 0 END) AS tramo_61_90,
                SUM(CASE WHEN dias_vencido > 90
                    THEN saldo ELSE 0 END) AS tramo_mas_90,
                SUM(CASE WHEN dias_vencido <= 0
                    THEN saldo ELSE 0 END) AS tramo_vigente
            FROM (
                SELECT
                    v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                    (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
                FROM ventas_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN cobrado  cb ON cb.id_venta = v.id
                LEFT JOIN retenido rt ON rt.id_venta = v.id
                LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
                WHERE {$where}
            ) sub
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'vigente'    => (float)($r['tramo_vigente']  ?? 0),
            'tramo_1_30' => (float)($r['tramo_1_30']    ?? 0),
            'tramo_31_60'=> (float)($r['tramo_31_60']   ?? 0),
            'tramo_61_90'=> (float)($r['tramo_61_90']   ?? 0),
            'mas_90'     => (float)($r['tramo_mas_90']  ?? 0),
        ];
    }

    /**
     * Historial de cobros de una factura específica.
     */
    public function getHistorialCobros(int $idVenta, int $idEmpresa): array
    {
        $sql = "
            SELECT
                ic.id,
                ic.fecha_emision,
                ic.numero_ingreso,
                ic.observaciones,
                id2.monto_cobrado,
                u.nombre AS usuario_nombre,
                efp.nombre AS forma_cobro
            FROM ingresos_detalle id2
            INNER JOIN ingresos_cabecera ic  ON ic.id  = id2.id_ingreso
            LEFT  JOIN usuarios          u   ON u.id   = ic.id_usuario
            LEFT  JOIN ingresos_pagos    ip  ON ip.id_ingreso = ic.id
            LEFT  JOIN empresa_formas_pago efp ON efp.id = ip.id_forma_cobro
            WHERE id2.tipo_documento           = 'FACTURA'
              AND id2.id_referencia_documento  = :id_venta
              AND ic.id_empresa                = :id_empresa
              AND ic.estado                   != 'anulado'
              AND ic.eliminado                 = false
              AND (efp.id IS NULL OR efp.id_empresa = :id_empresa)
            ORDER BY ic.fecha_emision DESC, ic.id DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id_venta' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene datos de una factura para validar el cobro.
     */
    public function getFacturaParaCobro(int $idVenta, int $idEmpresa): ?array
    {
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado() . "),
                 retenido AS (" . $this->getCteRetenido() . "),
                 nc_aplic AS (" . $this->getCteNC() . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                COALESCE(cb.total_cobrado, 0)                                                                                AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                                                               AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                                                     AS total_nc,
                v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
            WHERE v.id         = :id
              AND v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
        ";

        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Puntos de emisión activos de la empresa (para el select Serie del cobro).
     */
    public function getPuntosEmision(int $idEmpresa): array
    {
        try {
            $sql = "SELECT p.id         AS id_punto,
                           e.codigo     AS cod_establecimiento,
                           p.codigo_punto,
                           p.id_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id_empresa = :id_empresa
                      AND p.eliminado  = false
                      AND e.eliminado  = false
                    ORDER BY e.codigo, p.codigo_punto";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Fallback tabla alternativa
            try {
                $sql2 = "SELECT id AS id_punto,
                                establecimiento AS cod_establecimiento,
                                punto AS codigo_punto,
                                id_establecimiento
                         FROM empresa_puntos_emision
                         WHERE id_empresa = :id_empresa AND eliminado = false
                         ORDER BY establecimiento, punto";
                $st2 = $this->db->prepare($sql2);
                $st2->execute([':id_empresa' => $idEmpresa]);
                return $st2->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }

    /**
     * Datos de un punto de emisión específico (para construir el número de ingreso).
     */
    public function getPuntoEmisionPorId(int $idPunto, int $idEmpresa): ?array
    {
        try {
            $sql = "SELECT p.id,
                           e.codigo       AS establecimiento,
                           p.codigo_punto AS punto,
                           p.id_establecimiento
                    FROM empresa_punto_emision p
                    JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                    WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
            $st = $this->db->prepare($sql);
            $st->execute([':id' => $idPunto, ':id_empresa' => $idEmpresa]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        } catch (\Throwable $e) {}

        try {
            $sql2 = "SELECT id, establecimiento, punto, id_establecimiento
                     FROM empresa_puntos_emision
                     WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
            $st2 = $this->db->prepare($sql2);
            $st2->execute([':id' => $idPunto, ':id_empresa' => $idEmpresa]);
            return $st2->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e2) {
            return null;
        }
    }

    /**
     * Conceptos de ingreso activos de la empresa.
     */
    public function getConceptos(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, comportamiento
                    FROM empresa_opciones_ingreso_egreso
                    WHERE id_empresa = :id_empresa
                      AND aplica_ingresos = TRUE
                      AND UPPER(estado) = 'ACTIVO'
                      AND eliminado = FALSE
                    ORDER BY nombre ASC";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Formas de cobro activas de la empresa.
     */
    public function getFormasCobro(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo FROM empresa_formas_pago
                WHERE id_empresa = :id_empresa
                  AND eliminado  = false
                  AND activo     = true
                  AND (aplica_en IN ('AMBAS','INGRESO','COBRO') OR aplica_en IS NULL)
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Plantillas de WhatsApp aprobadas de la empresa.
     * Retorna [] si la tabla no existe (módulo no instalado).
     */
    public function getPlantillasWA(int $idEmpresa): array
    {
        try {
            $sql = "SELECT id, nombre, idioma, componentes
                    FROM whatsapp_plantillas
                    WHERE id_empresa   = :id_empresa
                      AND estado_meta  = 'APPROVED'
                      AND eliminado    = false
                    ORDER BY nombre";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verifica si la empresa tiene WhatsApp configurado.
     * Retorna false si la tabla no existe (módulo no instalado).
     */
    public function tieneWhatsappConfigurado(int $idEmpresa): bool
    {
        try {
            $sql = "SELECT COUNT(*) FROM empresa_whatsapp_config
                    WHERE id_empresa       = :id_empresa
                      AND eliminado        = false
                      AND access_token     IS NOT NULL AND access_token     <> ''
                      AND phone_number_id  IS NOT NULL AND phone_number_id  <> ''";
            $st = $this->db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Años disponibles con facturas autorizadas.
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                FROM ventas_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false
                  AND estado IN ('autorizado','autorizada')
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /**
     * Facturas con saldo pendiente para el envío de estados de cuenta
     * (automatizaciones). Una fila por factura, con datos de contacto del cliente.
     * Si $soloVencidas o $diasMin > 0, limita a facturas vencidas.
     */
    public function getFacturasPendientesParaEnvio(int $idEmpresa, bool $soloVencidas, int $diasMin): array
    {
        $sql = "
            WITH cobrado  AS (" . $this->getCteCobrado() . "),
                 retenido AS (" . $this->getCteRetenido() . "),
                 nc_aplic AS (" . $this->getCteNC() . ")
            SELECT
                v.id,
                v.id_cliente,
                c.nombre                AS cliente_nombre,
                COALESCE(c.email,'')    AS cliente_email,
                COALESCE(c.telefono,'') AS cliente_telefono,
                c.identificacion        AS cliente_ruc,
                v.fecha_emision,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura,
                (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date     AS fecha_vencimiento,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido,
                v.importe_total                                                              AS total,
                COALESCE(cb.total_cobrado, 0)                                               AS total_cobrado,
                COALESCE(rt.total_retenido, 0)                                              AS total_retenido,
                COALESCE(nc.total_nc, 0)                                                    AS total_nc,
                (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) AS saldo
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado  cb ON cb.id_venta = v.id
            LEFT JOIN retenido rt ON rt.id_venta = v.id
            LEFT JOIN nc_aplic nc ON nc.num_doc_modificado = CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial)
            WHERE v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)
              AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
        ";

        $params = [':id_empresa' => $idEmpresa, ':id_empresa_ta' => $idEmpresa];

        if ($soloVencidas || $diasMin > 0) {
            $sql .= " AND (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) >= :dmin";
            $params[':dmin'] = max(1, $diasMin);
        }

        $sql .= " ORDER BY c.nombre ASC, v.fecha_emision ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Líneas (ítems) de una factura, para el detalle "por línea".
     */
    public function getLineasFactura(int $idVenta): array
    {
        $sql = "SELECT descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto
                FROM ventas_detalle
                WHERE id_venta = :id
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idVenta]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SALDOS INICIALES CXC
    // ─────────────────────────────────────────────────────────────────────

    public function getSaldosInicialesCxc(int $idEmpresa, array $filtros = []): array
    {
        $where  = "id_empresa = :id_empresa AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODOS') {
            $where .= " AND estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        $sql = "SELECT
                    id, nro_documento, fecha_emision, fecha_vencimiento,
                    ruc_cliente, nombre_cliente,
                    CAST(saldo_inicial   AS NUMERIC(16,2)) AS saldo_inicial,
                    CAST(monto_cobrado   AS NUMERIC(16,2)) AS monto_cobrado,
                    CAST(saldo_pendiente AS NUMERIC(16,2)) AS saldo_pendiente,
                    estado, observaciones,
                    CASE WHEN fecha_vencimiento < CURRENT_DATE AND estado != 'PAGADO'
                         THEN CURRENT_DATE - fecha_vencimiento ELSE 0 END AS dias_vencido
                FROM saldos_iniciales_cxc
                WHERE {$where}
                ORDER BY fecha_emision ASC, nro_documento ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(int $idEmpresa, array $filtros): array
    {
        $where = "v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa_ta)";

        $params = [
            ':id_empresa'    => $idEmpresa,
            ':id_empresa_ta' => $idEmpresa,
        ];

        // Filtro de estado CxC
        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0) - COALESCE(rt.total_retenido, 0) - COALESCE(nc.total_nc, 0)) <= 0";
        }
        // TODOS → sin filtro extra

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND v.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND v.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['id_cliente'])) {
            $rawClientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : explode(',', (string)$filtros['id_cliente']);
            $clientes = array_filter(array_map('intval', $rawClientes));
            if (!empty($clientes)) {
                $in = [];
                foreach (array_values($clientes) as $i => $id) {
                    $k = ":cli{$i}"; $in[] = $k; $params[$k] = $id;
                }
                $where .= " AND v.id_cliente IN (" . implode(',', $in) . ")";
            }
        }

        return [$where, $params];
    }
}
