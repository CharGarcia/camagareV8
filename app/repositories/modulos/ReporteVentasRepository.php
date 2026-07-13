<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ReporteVentasRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('ventas_cabecera');
    }

    /**
     * Configuración de la fuente de datos según el tipo de documento:
     * FACTURA (ventas_*) o RECIBO (recibos_venta_*). Las tablas de recibos
     * son espejo de las de ventas, con FKs distintas y sin retenciones/clave.
     */
    private function fuente(array $filtros): array
    {
        $esRecibo = (($filtros['tipo_documento'] ?? 'FACTURA') === 'RECIBO');

        if ($esRecibo) {
            return [
                'cab'         => 'recibos_venta_cabecera',
                'det'         => 'recibos_venta_detalle',
                'imp'         => 'recibos_venta_detalle_impuestos',
                'adic'        => 'recibos_venta_adicional',
                'fk_det'      => 'id_recibo',          // detalle.id_recibo = cabecera.id
                'fk_imp'      => 'id_recibo_detalle',  // impuestos.id_recibo_detalle = detalle.id
                'fk_adic'     => 'id_recibo',
                'estado_ok'   => "{alias}.estado NOT IN ('borrador', 'anulado')",
                'retenciones' => false,
                'clave'       => false,
            ];
        }

        return [
            'cab'         => 'ventas_cabecera',
            'det'         => 'ventas_detalle',
            'imp'         => 'ventas_detalle_impuestos',
            'adic'        => 'ventas_adicional',
            'fk_det'      => 'id_venta',
            'fk_imp'      => 'id_venta_detalle',
            'fk_adic'     => 'id_venta',
            'estado_ok'   => "{alias}.estado IN ('autorizado', 'autorizada', 'AUTORIZADO', 'AUTORIZADA')",
            'retenciones' => true,
            'clave'       => true,
        ];
    }

    /**
     * Años disponibles (facturas autorizadas + recibos emitidos/facturados).
     */
    public function getAniosDisponibles(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT anio FROM (
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int AS anio
                    FROM ventas_cabecera
                    WHERE id_empresa = :e AND eliminado = false AND estado IN ('autorizado','autorizada')
                    UNION
                    SELECT EXTRACT(YEAR FROM fecha_emision)::int
                    FROM recibos_venta_cabecera
                    WHERE id_empresa = :e2 AND eliminado = false AND estado NOT IN ('borrador','anulado')
                ) t
                WHERE anio IS NOT NULL
                ORDER BY anio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
    }

    /**
     * CTE de bases e impuestos para sumatorias (según la fuente).
     */
    private function getCteBasesImpuestos(array $f): string
    {
        return "
            SELECT
                d.{$f['fk_det']} AS id_doc,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(i.valor) as valor_iva
            FROM {$f['det']} d
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            GROUP BY d.{$f['fk_det']}
        ";
    }

    /**
     * Construye las condiciones WHERE a partir de los filtros.
     */
    private function buildWhereYParams(int $idEmpresa, array $filtros, string $aliasVenta, string $aliasDetalle = null, bool $filtrarEstado = true): array
    {
        $f = $this->fuente($filtros);

        $where = "{$aliasVenta}.id_empresa = :id_empresa
                  AND {$aliasVenta}.eliminado = false
                  AND {$aliasVenta}.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";

        if ($filtrarEstado) {
            $where .= " AND " . str_replace('{alias}', $aliasVenta, $f['estado_ok']);
        }

        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND {$aliasVenta}.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND {$aliasVenta}.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_cliente'])) {
            $clientes = is_array($filtros['id_cliente']) ? $filtros['id_cliente'] : [$filtros['id_cliente']];
            $inNames = [];
            foreach ($clientes as $i => $id) {
                $pName = ":cli$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            $where .= " AND {$aliasVenta}.id_cliente IN (" . implode(',', $inNames) . ")";
        }

        if (!empty($filtros['id_producto'])) {
            $productos = is_array($filtros['id_producto']) ? $filtros['id_producto'] : [$filtros['id_producto']];
            $inNames = [];
            foreach ($productos as $i => $id) {
                $pName = ":prod$i";
                $inNames[] = $pName;
                $params[$pName] = $id;
            }
            if ($aliasDetalle) {
                $where .= " AND {$aliasDetalle}.id_producto IN (" . implode(',', $inNames) . ")";
            } else {
                $where .= " AND EXISTS (SELECT 1 FROM {$f['det']} vd WHERE vd.{$f['fk_det']} = {$aliasVenta}.id AND vd.id_producto IN (" . implode(',', $inNames) . "))";
            }
        }

        // Filtro por Producto = texto de los ítems del documento (descripción o código de línea)
        if (!empty($filtros['producto_texto'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['det']} vdp
                WHERE vdp.{$f['fk_det']} = {$aliasVenta}.id
                  AND (vdp.descripcion ILIKE :prodtxt OR vdp.codigo_principal ILIKE :prodtxt)
            )";
            $params[':prodtxt'] = '%' . trim($filtros['producto_texto']) . '%';
        }

        // Filtro por Información Adicional del documento (campos adicionales nombre/valor)
        if (!empty($filtros['buscar_info'])) {
            $where .= " AND EXISTS (
                SELECT 1 FROM {$f['adic']} va
                WHERE va.{$f['fk_adic']} = {$aliasVenta}.id
                  AND (va.nombre ILIKE :info OR va.valor ILIKE :info)
            )";
            $params[':info'] = '%' . trim($filtros['buscar_info']) . '%';
        }

        return [$where, $params];
    }

    /**
     * Reporte detallado (por documento).
     */
    public function getReporteDetallado(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $clave = $f['clave'] ? "COALESCE(v.clave_acceso, '')" : "''";
        $reten = $f['retenciones']
            ? "COALESCE((SELECT SUM(r.total_iva + r.total_renta + r.total_isd) FROM retencion_venta_cabecera r WHERE r.id_venta = v.id AND r.eliminado = false), 0)"
            : "0";

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                v.id,
                v.fecha_emision,
                CONCAT(v.establecimiento, '-', v.punto_emision, '-', v.secuencial) as numero_factura,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                v.estado,
                COALESCE(b.base_0, 0)   as base_0,
                COALESCE(b.base_iva, 0) as base_iva,
                COALESCE(b.valor_iva, 0) as valor_iva,
                v.importe_total          as total,
                COALESCE(vend.nombre, '')   as vendedor_nombre,
                COALESCE(ucaj.nombre, '')   as cajero_nombre,
                COALESCE(uusr.nombre, '')   as usuario_nombre,
                {$clave} as clave_acceso,
                {$reten} as retenciones
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id
            LEFT JOIN vendedores  vend ON vend.id = v.id_vendedor
            LEFT JOIN usuarios    ucaj ON ucaj.id = v.id_usuario
            LEFT JOIN usuarios    uusr ON uusr.id = v.created_by
            WHERE {$where}
            ORDER BY v.fecha_emision DESC, v.secuencial DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por cliente.
     */
    public function getReporteAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                c.id as id_cliente,
                c.identificacion as cliente_ruc,
                c.nombre as cliente_nombre,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            JOIN clientes c ON c.id = v.id_cliente
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY c.id, c.identificacion, c.nombre
            ORDER BY total DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por producto.
     */
    public function getReporteAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', 'd');

        $sql = "
            SELECT
                d.id_producto,
                COALESCE(p.codigo, '') as producto_codigo,
                COALESCE(p.nombre, d.descripcion) as producto_nombre,
                COALESCE(i.tarifa, 0) as tarifa_iva,
                SUM(d.cantidad) as cantidad_vendida,
                SUM(CASE WHEN i.tarifa = 0 THEN i.base_imponible ELSE 0 END) as base_0,
                SUM(CASE WHEN i.tarifa > 0 THEN i.base_imponible ELSE 0 END) as base_iva,
                SUM(COALESCE(i.valor, 0)) as valor_iva,
                SUM(d.precio_total_sin_impuesto + COALESCE(i.valor, 0)) as total
            FROM {$f['det']} d
            JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
            LEFT JOIN productos p ON p.id = d.id_producto
            LEFT JOIN {$f['imp']} i ON i.{$f['fk_imp']} = d.id
            WHERE {$where}
            GROUP BY d.id_producto, p.codigo, COALESCE(p.nombre, d.descripcion), COALESCE(i.tarifa, 0)
            ORDER BY cantidad_vendida DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por fecha.
     */
    public function getReporteAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                v.fecha_emision as fecha,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY v.fecha_emision
            ORDER BY v.fecha_emision DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reporte agrupado por mes (año-mes).
     */
    public function getReporteAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                TO_CHAR(v.fecha_emision, 'YYYY-MM') as mes,
                COUNT(v.id) as cantidad_facturas,
                SUM(COALESCE(b.base_0, 0)) as base_0,
                SUM(COALESCE(b.base_iva, 0)) as base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as valor_iva,
                SUM(v.importe_total) as total
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
            GROUP BY TO_CHAR(v.fecha_emision, 'YYYY-MM')
            ORDER BY mes DESC
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Autocompletado: descripciones distintas de los ítems del documento.
     */
    public function buscarItems(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        $sql = "SELECT DISTINCT TRIM(d.descripcion) AS valor
                FROM {$f['det']} d
                JOIN {$f['cab']} v ON v.id = d.{$f['fk_det']}
                WHERE v.id_empresa = :ie AND v.eliminado = false
                  AND d.descripcion IS NOT NULL AND TRIM(d.descripcion) <> ''
                  AND d.descripcion ILIKE :q
                ORDER BY valor
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_map(fn($v) => ['valor' => $v, 'label' => $v], $rows);
    }

    /**
     * Autocompletado: info adicional (nombre/valor distintos del documento).
     */
    public function buscarInfoAdicional(int $idEmpresa, string $q, string $tipoDocumento = 'FACTURA', int $limit = 15): array
    {
        $f = $this->fuente(['tipo_documento' => $tipoDocumento]);
        $sql = "SELECT DISTINCT va.nombre, va.valor
                FROM {$f['adic']} va
                JOIN {$f['cab']} v ON v.id = va.{$f['fk_adic']}
                WHERE v.id_empresa = :ie AND v.eliminado = false
                  AND COALESCE(va.valor, '') <> ''
                  AND (va.nombre ILIKE :q OR va.valor ILIKE :q)
                ORDER BY va.nombre, va.valor
                LIMIT {$limit}";
        $st = $this->db->prepare($sql);
        $st->execute([':ie' => $idEmpresa, ':q' => '%' . $q . '%']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => [
            'valor' => $r['valor'],
            'label' => $r['valor'],
            'sub'   => $r['nombre'],
        ], $rows);
    }

    /**
     * Obtiene estadísticas globales para el rango de fechas.
     */
    public function getEstadisticas(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v');

        $sql = "
            WITH bases AS (" . $this->getCteBasesImpuestos($f) . ")
            SELECT
                SUM(COALESCE(b.base_0, 0)) as total_base_0,
                SUM(COALESCE(b.base_iva, 0)) as total_base_iva,
                SUM(COALESCE(b.valor_iva, 0)) as total_iva,
                SUM(v.importe_total) as gran_total,
                COUNT(v.id) as total_documentos
            FROM {$f['cab']} v
            LEFT JOIN bases b ON b.id_doc = v.id
            WHERE {$where}
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return [
            'total_base_0'     => (float)($row['total_base_0'] ?? 0),
            'total_base_iva'   => (float)($row['total_base_iva'] ?? 0),
            'total_iva'        => (float)($row['total_iva'] ?? 0),
            'gran_total'       => (float)($row['gran_total'] ?? 0),
            'total_documentos' => (int)($row['total_documentos'] ?? 0),
        ];
    }

    public function getResumenEstados(int $idEmpresa, array $filtros): array
    {
        $f = $this->fuente($filtros);
        list($where, $params) = $this->buildWhereYParams($idEmpresa, $filtros, 'v', null, false);

        $sql = "
            SELECT
                LOWER(estado) as estado,
                COUNT(*) as cantidad
            FROM {$f['cab']} v
            WHERE {$where}
            GROUP BY LOWER(estado)
        ";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

        $resumen = [
            'autorizados' => 0,
            'anulados'    => 0,
            'borradores'  => 0
        ];

        foreach ($rows as $row) {
            $estado = $row['estado'];
            $cantidad = (int) $row['cantidad'];
            // "Autorizados" agrupa los documentos emitidos/válidos (facturas autorizadas y recibos emitidos/facturados)
            if (in_array($estado, ['autorizado', 'autorizada', 'emitido', 'facturado'])) {
                $resumen['autorizados'] += $cantidad;
            } elseif ($estado === 'anulado') {
                $resumen['anulados'] += $cantidad;
            } elseif ($estado === 'borrador') {
                $resumen['borradores'] += $cantidad;
            }
        }

        return $resumen;
    }
}
