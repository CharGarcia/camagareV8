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
     * Listado principal de cuentas por cobrar.
     */
    public function getListado(int $idEmpresa, array $filtros): array
    {
        [$where, $params] = $this->buildWhere($idEmpresa, $filtros);

        $sql = "
            WITH cobrado AS (" . $this->getCteCobrado() . ")
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
                COALESCE(cb.total_cobrado, 0)                    AS total_cobrado,
                v.importe_total - COALESCE(cb.total_cobrado, 0)  AS saldo,
                v.fecha_emision + INTERVAL '1 day' * v.dias_credito AS fecha_vencimiento,
                v.dias_credito,
                (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
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
            WITH cobrado AS (" . $this->getCteCobrado() . ")
            SELECT
                COUNT(v.id) AS total_facturas,
                SUM(v.importe_total - COALESCE(cb.total_cobrado, 0)) AS total_saldo,
                SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0)
                    ELSE 0
                END) AS total_vencido,
                SUM(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE
                    THEN v.importe_total - COALESCE(cb.total_cobrado, 0)
                    ELSE 0
                END) AS total_al_dia,
                COUNT(CASE
                    WHEN (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE THEN 1
                END) AS facturas_vencidas
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
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
            WITH cobrado AS (" . $this->getCteCobrado() . ")
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
                    v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                    (CURRENT_DATE - (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date) AS dias_vencido
                FROM ventas_cabecera v
                JOIN clientes c ON c.id = v.id_cliente
                LEFT JOIN cobrado cb ON cb.id_venta = v.id
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
            WITH cobrado AS (" . $this->getCteCobrado() . ")
            SELECT
                v.*,
                c.nombre         AS cliente_nombre,
                c.email          AS cliente_email,
                c.telefono       AS cliente_telefono,
                c.identificacion AS cliente_ruc,
                COALESCE(cb.total_cobrado, 0)                   AS total_cobrado,
                v.importe_total - COALESCE(cb.total_cobrado, 0) AS saldo,
                CONCAT(v.establecimiento,'-',v.punto_emision,'-',v.secuencial) AS numero_factura
            FROM ventas_cabecera v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN cobrado cb ON cb.id_venta = v.id
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
     */
    public function getPlantillasWA(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, idioma, componentes
                FROM whatsapp_plantillas
                WHERE id_empresa   = :id_empresa
                  AND estado_meta  = 'APPROVED'
                  AND eliminado    = false
                ORDER BY nombre";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si la empresa tiene WhatsApp configurado.
     */
    public function tieneWhatsappConfigurado(int $idEmpresa): bool
    {
        $sql = "SELECT COUNT(*) FROM whatsapp_config
                WHERE id_empresa = :id_empresa AND activo = true";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return (int)$st->fetchColumn() > 0;
    }

    /**
     * Genera el siguiente secuencial de ingreso para la empresa.
     */
    public function getSiguienteSecuencial(int $idEmpresa): string
    {
        $sql = "SELECT COALESCE(MAX(CAST(NULLIF(regexp_replace(secuencial,'[^0-9]','','g'),'') AS INTEGER)), 0) + 1
                FROM ingresos_cabecera
                WHERE id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return str_pad((string)($st->fetchColumn() ?: 1), 9, '0', STR_PAD_LEFT);
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

    // ─────────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    private function buildWhere(int $idEmpresa, array $filtros): array
    {
        $where = "v.id_empresa = :id_empresa
              AND v.eliminado  = false
              AND v.estado    IN ('autorizado','autorizada')
              AND v.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        $params = [':id_empresa' => $idEmpresa];

        // Filtro de estado CxC
        $estado = $filtros['estado'] ?? 'PENDIENTES';
        if ($estado === 'PENDIENTES') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0)) > 0";
        } elseif ($estado === 'VENCIDAS') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date < CURRENT_DATE";
        } elseif ($estado === 'AL_DIA') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0)) > 0
                        AND (v.fecha_emision + INTERVAL '1 day' * v.dias_credito)::date >= CURRENT_DATE";
        } elseif ($estado === 'PAGADAS') {
            $where .= " AND (v.importe_total - COALESCE(cb.total_cobrado, 0)) <= 0";
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
