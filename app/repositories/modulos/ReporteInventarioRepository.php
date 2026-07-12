<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de solo lectura para el Reporte de Inventarios (4 pestañas:
 * Existencias, Movimientos/Kardex, Valorización, Consignaciones). No escribe
 * en ninguna tabla. Sigue el mismo patrón (Controller → Repository, sin
 * Service) que ReporteVentasRepository / ReporteComprasRepository.
 */
class ReporteInventarioRepository extends BaseRepository
{
    /** Etiquetas legibles para inventario_kardex.referencia_tipo (origen del movimiento). */
    private const ORIGEN_LABELS = [
        'ajuste_manual'                  => 'Ajuste manual',
        'carga_inventario'               => 'Carga de inventario',
        'compra'                         => 'Compra',
        'factura_venta'                  => 'Factura de venta',
        'nota_credito'                   => 'Nota de crédito',
        'recibo_venta'                   => 'Recibo de venta',
        'SALDO_INICIAL'                  => 'Saldo inicial',
        'CONSIGNACION_VENTA'             => 'Consignación',
        'EDICION_CONSIGNACION_VENTA'     => 'Consignación (editada)',
        'ELIMINACION_CONSIGNACION_VENTA' => 'Consignación (reversa)',
        'FACTURACION_CV'                 => 'Facturación de consignación',
        'RETORNO_CV'                     => 'Retorno de consignación',
        'ELIMINACION_RETORNO_CV'         => 'Retorno de consignación (reversa)',
        'CAMBIO_ESTADO_RETORNO_CV'       => 'Retorno de consignación (cambio de estado)',
        'CAMBIO_PRODUCTO_CV'             => 'Cambio de producto',
        'migracion'                      => 'Migración histórica',
    ];

    public function __construct()
    {
        parent::__construct('inventario_kardex');
    }

    public static function labelOrigen(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return 'Sin origen';
        }
        return self::ORIGEN_LABELS[$tipo] ?? $tipo;
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 1 — EXISTENCIAS (stock actual)
    // ════════════════════════════════════════════════════════════════════

    private function buildWhereExistencias(int $idEmpresa, array $filtros): array
    {
        $where = "pb.id_empresa = :id_empresa AND pb.eliminado = false
                   AND p.eliminado = false AND p.inventariable = true
                   AND b.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['id_bodega'])) {
            $where .= " AND pb.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND p.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND pb.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        return [$where, $params];
    }

    /** Base: una fila por producto×bodega, con costo unitario (último movimiento) y estado calculado. */
    private function baseExistencias(string $where): string
    {
        return "
            SELECT * FROM (
                SELECT pb.id_producto, pb.id_bodega, pb.stock_actual, pb.stock_minimo, pb.stock_maximo,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       p.id_categoria, COALESCE(cat.nombre, 'Sin categoría') AS categoria_nombre,
                       p.id_marca, COALESCE(mar.nombre, 'Sin marca') AS marca_nombre,
                       b.nombre AS bodega_nombre,
                       COALESCE((
                           SELECT k.costo_unitario FROM inventario_kardex k
                           WHERE k.id_producto = pb.id_producto AND k.id_bodega = pb.id_bodega
                             AND k.id_empresa = pb.id_empresa AND k.eliminado = false
                           ORDER BY k.fecha_movimiento DESC, k.id DESC LIMIT 1
                       ), 0) AS costo_unitario
                FROM productos_bodegas pb
                INNER JOIN productos p ON p.id = pb.id_producto AND p.id_empresa = pb.id_empresa
                INNER JOIN bodegas b ON b.id = pb.id_bodega
                LEFT JOIN categorias cat ON cat.id = p.id_categoria
                LEFT JOIN marcas mar ON mar.id = p.id_marca
                WHERE {$where}
            ) base
        ";
    }

    private function wrapValorYEstado(string $baseSql): string
    {
        return "
            SELECT t.*, (t.stock_actual * t.costo_unitario) AS valor_total,
                   CASE
                       WHEN t.stock_actual <= 0 THEN 'QUIEBRE'
                       WHEN t.stock_minimo > 0 AND t.stock_actual <= t.stock_minimo THEN 'ALERTA'
                       WHEN t.stock_maximo > 0 AND t.stock_actual > t.stock_maximo THEN 'EXCESO'
                       ELSE 'NORMAL'
                   END AS estado_stock
            FROM ({$baseSql}) t
        ";
    }

    public function getExistenciasDetalle(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $sql = "SELECT * FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e WHERE 1=1";
        if (!empty($filtros['estado_stock'])) {
            $sql .= " AND e.estado_stock = :estado_stock";
            $params[':estado_stock'] = $filtros['estado_stock'];
        }
        $sql .= " ORDER BY e.producto_nombre ASC, e.bodega_nombre ASC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getExistenciasAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $base = $this->wrapValorYEstado($this->baseExistencias($where));

        $sql = "SELECT * FROM (
                    SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                           SUM(stock_actual) AS stock_actual,
                           SUM(stock_minimo) AS stock_minimo,
                           SUM(stock_maximo) AS stock_maximo,
                           SUM(valor_total) AS valor_total,
                           CASE WHEN SUM(stock_actual) > 0 THEN SUM(valor_total) / SUM(stock_actual) ELSE 0 END AS costo_unitario,
                           COUNT(DISTINCT id_producto) AS cantidad_productos
                    FROM ({$base}) t
                    GROUP BY {$campoId}
                ) g
                ORDER BY valor_total DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExistenciasAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    public function getExistenciasAgrupadoCategoria(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_categoria', 'categoria_nombre');
    }

    public function getExistenciasAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getExistenciasAgrupado($idEmpresa, $filtros, 'id_bodega', 'bodega_nombre');
    }

    public function getExistenciasKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $sql = "SELECT
                    COUNT(*) AS total_filas,
                    COUNT(DISTINCT id_producto) AS total_productos,
                    COALESCE(SUM(valor_total), 0) AS valor_total,
                    COUNT(*) FILTER (WHERE estado_stock = 'QUIEBRE') AS en_quiebre,
                    COUNT(*) FILTER (WHERE estado_stock = 'ALERTA')  AS en_alerta,
                    COUNT(*) FILTER (WHERE estado_stock = 'EXCESO')  AS en_exceso
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_productos' => (int) ($row['total_productos'] ?? 0),
            'valor_total'     => (float) ($row['valor_total'] ?? 0),
            'en_quiebre'      => (int) ($row['en_quiebre'] ?? 0),
            'en_alerta'       => (int) ($row['en_alerta'] ?? 0),
            'en_exceso'       => (int) ($row['en_exceso'] ?? 0),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 2 — MOVIMIENTOS (Kardex)
    // ════════════════════════════════════════════════════════════════════

    private function buildWhereMovimientos(int $idEmpresa, array $filtros): array
    {
        $where = "k.id_empresa = :id_empresa AND k.eliminado = false
                   AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND k.fecha_movimiento >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND k.fecha_movimiento <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND k.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND k.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['id_categoria'])) {
            $where .= " AND p.id_categoria = :id_categoria";
            $params[':id_categoria'] = (int) $filtros['id_categoria'];
        }
        if (!empty($filtros['id_marca'])) {
            $where .= " AND p.id_marca = :id_marca";
            $params[':id_marca'] = (int) $filtros['id_marca'];
        }
        if (!empty($filtros['tipo_movimiento'])) {
            $where .= " AND k.tipo_movimiento = :tipo_movimiento";
            $params[':tipo_movimiento'] = $filtros['tipo_movimiento'];
        }
        if (!empty($filtros['referencia_tipo'])) {
            $where .= " AND k.referencia_tipo = :referencia_tipo";
            $params[':referencia_tipo'] = $filtros['referencia_tipo'];
        }
        if (!empty($filtros['id_usuario'])) {
            $where .= " AND k.created_by = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['numero_lote'])) {
            $where .= " AND k.numero_lote ILIKE :numero_lote";
            $params[':numero_lote'] = '%' . $filtros['numero_lote'] . '%';
        }
        if (!empty($filtros['nup'])) {
            $where .= " AND k.nup ILIKE :nup";
            $params[':nup'] = '%' . $filtros['nup'] . '%';
        }
        if (!empty($filtros['buscar'])) {
            $where .= " AND (p.nombre ILIKE :buscar OR p.codigo ILIKE :buscar OR b.nombre ILIKE :buscar OR k.observaciones ILIKE :buscar)";
            $params[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        return [$where, $params];
    }

    private function fromMovimientos(string $where): string
    {
        return "FROM inventario_kardex k
                 INNER JOIN productos p ON p.id = k.id_producto
                 INNER JOIN bodegas b ON b.id = k.id_bodega
                 LEFT JOIN usuarios u ON u.id = k.created_by
                 LEFT JOIN unidades_medida um ON um.id = k.id_medida
                 LEFT JOIN categorias cat ON cat.id = p.id_categoria
                 LEFT JOIN marcas mar ON mar.id = p.id_marca
                 WHERE {$where}";
    }

    public function getMovimientosDetalle(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = "SELECT k.id, k.fecha_movimiento, k.tipo_movimiento, k.referencia_tipo, k.referencia_id,
                       k.cantidad, k.costo_unitario, k.costo_total, k.stock_anterior, k.stock_posterior,
                       k.numero_lote, k.fecha_caducidad, k.nup, k.observaciones,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       b.nombre AS bodega_nombre, u.nombre AS usuario_nombre,
                       um.abreviatura AS medida_abreviatura
                " . $this->fromMovimientos($where) . "
                ORDER BY k.fecha_movimiento DESC, k.id DESC
                LIMIT 3000";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['origen_label'] = self::labelOrigen($r['referencia_tipo']);
        }
        unset($r);
        return $rows;
    }

    private function getMovimientosAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabelExpr, string $orderBy): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabelExpr}) AS nombre_grupo,
                       COUNT(*) AS cantidad_movimientos,
                       SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END) AS total_entradas,
                       SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END) AS total_salidas,
                       SUM(k.cantidad) AS saldo_neto,
                       SUM(k.costo_total) AS costo_total
                " . $this->fromMovimientos($where) . "
                GROUP BY {$campoId}
                ORDER BY {$orderBy}";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovimientosAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.id_producto', "p.codigo || ' - ' || p.nombre", 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.id_bodega', 'b.nombre', 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoTipo(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'k.tipo_movimiento', 'k.tipo_movimiento', 'cantidad_movimientos DESC');
    }

    public function getMovimientosAgrupadoOrigen(int $idEmpresa, array $filtros): array
    {
        $rows = $this->getMovimientosAgrupado($idEmpresa, $filtros, "COALESCE(k.referencia_tipo, '')", "COALESCE(k.referencia_tipo, '')", 'cantidad_movimientos DESC');
        foreach ($rows as &$r) {
            $r['nombre_grupo'] = self::labelOrigen($r['nombre_grupo'] !== '' ? $r['nombre_grupo'] : null);
        }
        unset($r);
        return $rows;
    }

    public function getMovimientosAgrupadoFecha(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, 'CAST(k.fecha_movimiento AS DATE)', 'CAST(k.fecha_movimiento AS DATE)', 'id_grupo DESC');
    }

    public function getMovimientosAgrupadoMes(int $idEmpresa, array $filtros): array
    {
        return $this->getMovimientosAgrupado($idEmpresa, $filtros, "TO_CHAR(k.fecha_movimiento, 'YYYY-MM')", "TO_CHAR(k.fecha_movimiento, 'YYYY-MM')", 'id_grupo DESC');
    }

    public function getMovimientosKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereMovimientos($idEmpresa, $filtros);
        $sql = "SELECT
                    COUNT(*) AS total_movimientos,
                    COALESCE(SUM(CASE WHEN k.cantidad > 0 THEN k.cantidad ELSE 0 END), 0) AS total_entradas,
                    COALESCE(SUM(CASE WHEN k.cantidad < 0 THEN ABS(k.cantidad) ELSE 0 END), 0) AS total_salidas,
                    COALESCE(SUM(k.cantidad), 0) AS saldo_neto
                " . $this->fromMovimientos($where);

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_movimientos' => (int) ($row['total_movimientos'] ?? 0),
            'total_entradas'    => (float) ($row['total_entradas'] ?? 0),
            'total_salidas'     => (float) ($row['total_salidas'] ?? 0),
            'saldo_neto'        => (float) ($row['saldo_neto'] ?? 0),
        ];
    }

    public function getAniosMovimientos(int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_movimiento) AS anio
                FROM inventario_kardex
                WHERE id_empresa = :id_empresa AND eliminado = false
                ORDER BY anio DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 3 — VALORIZACIÓN (a la fecha actual)
    // ════════════════════════════════════════════════════════════════════

    private function getValorizacionAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $base = $this->wrapValorYEstado($this->baseExistencias($where));

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                       SUM(stock_actual) AS stock_actual,
                       SUM(valor_total) AS valor_total,
                       CASE WHEN SUM(stock_actual) > 0 THEN SUM(valor_total) / SUM(stock_actual) ELSE 0 END AS costo_promedio,
                       COUNT(DISTINCT id_producto) AS cantidad_productos
                FROM ({$base}) t
                WHERE valor_total > 0
                GROUP BY {$campoId}
                ORDER BY valor_total DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getValorizacionAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    public function getValorizacionAgrupadoCategoria(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_categoria', 'categoria_nombre');
    }

    public function getValorizacionAgrupadoBodega(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_bodega', 'bodega_nombre');
    }

    public function getValorizacionAgrupadoMarca(int $idEmpresa, array $filtros): array
    {
        return $this->getValorizacionAgrupado($idEmpresa, $filtros, 'id_marca', 'marca_nombre');
    }

    public function getValorizacionKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereExistencias($idEmpresa, $filtros);
        $sql = "SELECT
                    COALESCE(SUM(valor_total), 0) AS valor_total,
                    COUNT(DISTINCT id_producto) AS total_productos,
                    COUNT(DISTINCT id_categoria) AS total_categorias
                FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e
                WHERE valor_total > 0";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $sqlTop = "SELECT producto_nombre, valor_total
                   FROM (" . $this->wrapValorYEstado($this->baseExistencias($where)) . ") e
                   ORDER BY valor_total DESC LIMIT 1";
        $stTop = $this->db->prepare($sqlTop);
        $stTop->execute($params);
        $top = $stTop->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'valor_total'       => (float) ($row['valor_total'] ?? 0),
            'total_productos'   => (int) ($row['total_productos'] ?? 0),
            'total_categorias'  => (int) ($row['total_categorias'] ?? 0),
            'producto_top'      => $top['producto_nombre'] ?? null,
            'producto_top_valor'=> (float) ($top['valor_total'] ?? 0),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    // PESTAÑA 4 — CONSIGNACIONES (saldo vigente en poder de clientes)
    // ════════════════════════════════════════════════════════════════════

    /** Cantidad retornada activa de una línea de consignación (mismo criterio que ConsignacionFacturaRepository). */
    private function sqlRetornadoCv(): string
    {
        return "SELECT SUM(rcd.cantidad)
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = cvd.id
                  AND rcd.eliminado = false AND rc.eliminado = false AND rc.estado = 'Emitida'";
    }

    /** Cantidad facturada (docs 'facturada') de una línea de consignación. */
    private function sqlFacturadoCv(): string
    {
        return "SELECT SUM(cfd.cantidad)
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle = cvd.id
                  AND cfd.eliminado = false AND cf.eliminado = false AND cf.estado = 'facturada'";
    }

    private function buildWhereConsignaciones(int $idEmpresa, array $filtros): array
    {
        $where = "cv.id_empresa = :id_empresa AND cv.eliminado = false AND cv.estado = 'Entregada'
                   AND cvd.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if (!empty($filtros['id_cliente'])) {
            $where .= " AND cv.id_cliente = :id_cliente";
            $params[':id_cliente'] = (int) $filtros['id_cliente'];
        }
        if (!empty($filtros['id_producto'])) {
            $where .= " AND cvd.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }
        if (!empty($filtros['id_bodega'])) {
            $where .= " AND cvd.id_bodega = :id_bodega";
            $params[':id_bodega'] = (int) $filtros['id_bodega'];
        }
        if (!empty($filtros['id_vendedor'])) {
            $where .= " AND cv.id_vendedor = :id_vendedor";
            $params[':id_vendedor'] = (int) $filtros['id_vendedor'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where .= " AND cv.fecha_emision >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where .= " AND cv.fecha_emision <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha_hasta'];
        }

        return [$where, $params];
    }

    /** Base: una fila por línea de consignación, con saldo y valor a costo calculados. */
    private function baseConsignaciones(string $where): string
    {
        return "
            SELECT * FROM (
                SELECT cv.id AS id_consignacion, cv.secuencial, cv.fecha_emision,
                       cv.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       cv.id_vendedor, COALESCE(v.nombre, '-') AS vendedor_nombre,
                       cvd.id AS id_detalle, cvd.id_producto,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       cvd.id_bodega, COALESCE(bo.nombre, '-') AS bodega_nombre,
                       cvd.cantidad AS cantidad_consignada,
                       COALESCE((" . $this->sqlRetornadoCv() . "), 0) AS cantidad_retornada,
                       COALESCE((" . $this->sqlFacturadoCv() . "), 0) AS cantidad_facturada,
                       COALESCE((
                           SELECT k.costo_unitario FROM inventario_kardex k
                           WHERE k.referencia_tipo = 'CONSIGNACION_VENTA' AND k.referencia_id = cv.id
                             AND k.id_producto = cvd.id_producto AND k.id_empresa = cv.id_empresa AND k.eliminado = false
                           ORDER BY k.fecha_movimiento DESC, k.id DESC LIMIT 1
                       ), 0) AS costo_unitario
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas bo ON bo.id = cvd.id_bodega
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                WHERE {$where}
            ) base
        ";
    }

    private function wrapSaldoConsignacion(string $baseSql): string
    {
        return "
            SELECT t.*, (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada) AS saldo,
                   (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada) * t.costo_unitario AS valor_saldo
            FROM ({$baseSql}) t
        ";
    }

    public function getConsignacionesDetalle(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $sql = "SELECT * FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s WHERE 1=1";
        if (empty($filtros['incluir_liquidadas'])) {
            $sql .= " AND s.saldo > 0";
        }
        $sql .= " ORDER BY s.fecha_emision DESC, s.id_consignacion DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getConsignacionesAgrupado(int $idEmpresa, array $filtros, string $campoId, string $campoLabel): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $base = $this->wrapSaldoConsignacion($this->baseConsignaciones($where));
        $havingSaldo = empty($filtros['incluir_liquidadas']) ? "WHERE s.saldo > 0" : "";

        $sql = "SELECT {$campoId} AS id_grupo, MAX({$campoLabel}) AS nombre_grupo,
                       SUM(s.saldo) AS saldo,
                       SUM(s.valor_saldo) AS valor_saldo,
                       COUNT(DISTINCT s.id_consignacion) AS cantidad_consignaciones
                FROM ({$base}) s
                {$havingSaldo}
                GROUP BY {$campoId}
                ORDER BY valor_saldo DESC";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConsignacionesAgrupadoCliente(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_cliente', 'cliente_nombre');
    }

    public function getConsignacionesAgrupadoProducto(int $idEmpresa, array $filtros): array
    {
        return $this->getConsignacionesAgrupado($idEmpresa, $filtros, 'id_producto', "producto_codigo || ' - ' || producto_nombre");
    }

    public function getConsignacionesKpis(int $idEmpresa, array $filtros): array
    {
        list($where, $params) = $this->buildWhereConsignaciones($idEmpresa, $filtros);
        $sql = "SELECT
                    COALESCE(SUM(saldo), 0) AS unidades_vigentes,
                    COALESCE(SUM(valor_saldo), 0) AS valor_vigente,
                    COUNT(DISTINCT id_cliente) AS clientes_con_saldo,
                    COUNT(DISTINCT id_consignacion) AS consignaciones_activas
                FROM (" . $this->wrapSaldoConsignacion($this->baseConsignaciones($where)) . ") s
                WHERE saldo > 0";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'unidades_vigentes'      => (float) ($row['unidades_vigentes'] ?? 0),
            'valor_vigente'          => (float) ($row['valor_vigente'] ?? 0),
            'clientes_con_saldo'     => (int) ($row['clientes_con_saldo'] ?? 0),
            'consignaciones_activas' => (int) ($row['consignaciones_activas'] ?? 0),
        ];
    }
}
