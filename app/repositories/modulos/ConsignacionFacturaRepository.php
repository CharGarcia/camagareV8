<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del documento "Facturación de Consignaciones en Ventas".
 *
 * Documento con estructura de factura (fecha, serie, secuencial, cliente a
 * facturar, vendedor) cuyas líneas provienen de una o varias consignaciones
 * ENTREGADAS. Al "Generar factura" se crea la Factura de Venta relacionada.
 *
 * Estados del documento: borrador | facturada | anulada.
 * Saldo facturable por línea de consignación:
 *   saldo = cantidad_consignada − retornado − facturado (solo docs 'facturada').
 */
class ConsignacionFacturaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('consignaciones_facturas');
    }

    // ─── SALDO FACTURABLE ─────────────────────────────────────────────────────

    /**
     * Líneas de una consignación ENTREGADA con saldo facturable ( > 0 ), con los
     * datos "tal cual" de la consignación de origen.
     */
    public function getLineasFacturables(int $idEmpresa, int $idConsignacion): array
    {
        $sql = "
            SELECT * FROM (
                SELECT
                    cvd.id                AS id_consignacion_detalle,
                    cvd.id_consignacion   AS id_consignacion,
                    cvd.id_producto,
                    cvd.cantidad          AS cantidad_consignada,
                    cvd.precio_unitario,
                    cvd.subtotal,
                    cvd.id_impuesto,
                    cvd.porcentaje_impuesto,
                    cvd.valor_impuesto,
                    cvd.total,
                    cvd.id_bodega,
                    cvd.lote,
                    cvd.nup,
                    cvd.fecha_caducidad,
                    cv.serie,
                    cv.secuencial,
                    cv.fecha_emision,
                    cv.id_cliente,
                    p.nombre  AS producto_nombre,
                    p.codigo  AS producto_codigo,
                    p.inventariable,
                    p.tipo_produccion,
                    b.nombre  AS bodega_nombre,
                    COALESCE(( " . $this->sqlRetornado('cvd.id') . " ), 0) AS cantidad_retornada,
                    COALESCE(( " . $this->sqlFacturado('cvd.id') . " ), 0) AS cantidad_facturada
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas b ON b.id = cvd.id_bodega
                WHERE cv.id = :idc
                  AND cv.id_empresa = :e
                  AND cv.eliminado = false
                  AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false
            ) t
            WHERE (t.cantidad_consignada - t.cantidad_retornada - t.cantidad_facturada) > 0
            ORDER BY t.id_consignacion_detalle ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['saldo_facturable'] = (float) $r['cantidad_consignada']
                - (float) $r['cantidad_retornada']
                - (float) $r['cantidad_facturada'];
        }
        unset($r);

        return $rows;
    }

    /** Saldo facturable de UNA línea de consignación (validación en el Service). */
    public function getSaldoFacturableLinea(int $idConsignacionDetalle, int $idEmpresa, ?int $excluirDoc = null): float
    {
        $sqlCant = "SELECT cvd.cantidad
                    FROM consignaciones_ventas_detalles cvd
                    INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                    WHERE cvd.id = :id AND cvd.id_empresa = :e
                      AND cvd.eliminado = false AND cv.eliminado = false";
        $stCant = $this->db->prepare($sqlCant);
        $stCant->execute([':id' => $idConsignacionDetalle, ':e' => $idEmpresa]);
        $cantidad = $stCant->fetchColumn();
        if ($cantidad === false) {
            return 0.0;
        }

        $stRet = $this->db->prepare("SELECT COALESCE((" . $this->sqlRetornado(':id') . "), 0)");
        $stRet->execute([':id' => $idConsignacionDetalle]);
        $retornado = (float) $stRet->fetchColumn();

        $excSql = '';
        $params = [':id' => $idConsignacionDetalle];
        if ($excluirDoc !== null) {
            $excSql = ' AND cf.id <> :exc';
            $params[':exc'] = $excluirDoc;
        }
        $stFac = $this->db->prepare("SELECT COALESCE((" . $this->sqlFacturado(':id', $excSql) . "), 0)");
        $stFac->execute($params);
        $facturado = (float) $stFac->fetchColumn();

        return (float) $cantidad - $retornado - $facturado;
    }

    /** Cantidad facturada (docs 'facturada') por línea de consignación. */
    public function getFacturadoPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT cfd.id_consignacion_detalle AS idd, COALESCE(SUM(cfd.cantidad), 0) AS cant
            FROM consignaciones_facturas_detalles cfd
            INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
            WHERE cfd.id_consignacion = :idc AND cfd.id_empresa = :e
              AND (cfd.eliminado = false OR cfd.eliminado IS NULL)
              AND cf.eliminado = false AND cf.estado = 'facturada'
            GROUP BY cfd.id_consignacion_detalle
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['idd']] = (float) $r['cant'];
        }
        return $map;
    }

    /** Subconsulta de cantidad retornada activa de una línea de consignación. */
    private function sqlRetornado(string $idExpr): string
    {
        return "SELECT SUM(rcd.cantidad)
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                WHERE rcd.id_consignacion_detalle = $idExpr
                  AND rcd.eliminado = false
                  AND rc.eliminado = false
                  AND rc.estado = 'Emitida'";
    }

    /** Subconsulta de cantidad facturada (docs 'facturada') de una línea de consignación. */
    private function sqlFacturado(string $idExpr, string $excSql = ''): string
    {
        return "SELECT SUM(cfd.cantidad)
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura
                WHERE cfd.id_consignacion_detalle = $idExpr
                  AND cfd.eliminado = false
                  AND cf.eliminado = false AND cf.estado = 'facturada'
                  $excSql";
    }

    // ─── LISTADO ──────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE cf.id_empresa = :e AND cf.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND cf.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (cf.secuencial ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b
                             OR cf.numero_factura ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'secuencial' => 'cf.secuencial',
                'cliente'    => 'c.nombre',
                'factura'    => 'cf.numero_factura',
            ],
            'exacto' => [
                'estado' => 'cf.estado',
            ],
            'fecha'  => [
                'fecha' => 'cf.fecha_emision',
            ],
            'numerico' => [
                'total' => 'cf.total',
            ],
        ]);

        $sqlCount = "SELECT COUNT(*)
                     FROM consignaciones_facturas cf
                     LEFT JOIN clientes c ON c.id = cf.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $colMap = [
            'fecha'      => 'cf.fecha_emision',
            'secuencial' => 'cf.secuencial',
            'cliente'    => 'c.nombre',
            'factura'    => 'cf.numero_factura',
            'total'      => 'cf.total',
            'estado'     => 'cf.estado',
        ];
        $sort = $colMap[$ordenCol] ?? 'cf.fecha_emision';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT cf.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       v.nombre AS vendedor_nombre,
                       vc.estado AS estado_factura, vc.eliminado AS factura_eliminada
                FROM consignaciones_facturas cf
                LEFT JOIN clientes c ON c.id = cf.id_cliente
                LEFT JOIN vendedores v ON v.id = cf.id_vendedor
                LEFT JOIN ventas_cabecera vc ON vc.id = cf.id_factura
                $where
                ORDER BY $sort $dir, cf.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /** Consignaciones ENTREGADAS con saldo facturable ( > 0 ), buscador del modal. */
    public function buscarConsignacionesFacturables(int $idEmpresa, string $q): array
    {
        $sql = "
            SELECT cv.id AS id_consignacion, cv.serie, cv.secuencial, cv.fecha_emision,
                   cv.id_cliente, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion
            FROM consignaciones_ventas cv
            INNER JOIN clientes c ON c.id = cv.id_cliente
            WHERE cv.id_empresa = :e AND cv.eliminado = false
              AND cv.estado = 'Entregada'
              AND (
                    c.nombre ILIKE :q OR c.identificacion ILIKE :q
                    OR cv.secuencial ILIKE :q
                    OR (cv.serie || '-' || cv.secuencial) ILIKE :q
              )
              AND EXISTS (
                    SELECT 1
                    FROM consignaciones_ventas_detalles cvd
                    WHERE cvd.id_consignacion = cv.id AND cvd.eliminado = false
                      AND (cvd.cantidad
                           - COALESCE(( " . $this->sqlRetornado('cvd.id') . " ), 0)
                           - COALESCE(( " . $this->sqlFacturado('cvd.id') . " ), 0)) > 0
              )
            ORDER BY cv.fecha_emision DESC, cv.id DESC
            LIMIT 15
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── CRUD del documento ───────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        $sql = "INSERT INTO consignaciones_facturas (" . implode(', ', $fields) . ")
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function insertDetalle(array $d): int
    {
        $sql = "INSERT INTO consignaciones_facturas_detalles (
                    id_consignacion_factura, id_empresa, id_consignacion, id_consignacion_detalle,
                    id_producto, cantidad, precio_unitario, descuento, id_impuesto, porcentaje_impuesto, valor_impuesto,
                    subtotal, total, id_bodega, lote, nup, fecha_caducidad, eliminado
                ) VALUES (
                    :cf, :e, :idc, :idcd, :prod, :cant, :pu, :desc, :idi, :pi, :vi,
                    :sub, :tot, :idb, :lote, :nup, :fc, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':cf'   => $d['id_consignacion_factura'],
            ':e'    => $d['id_empresa'],
            ':idc'  => $d['id_consignacion'],
            ':idcd' => $d['id_consignacion_detalle'],
            ':prod' => $d['id_producto'],
            ':cant' => $d['cantidad'],
            ':pu'   => $d['precio_unitario'] ?? 0,
            ':desc' => $d['descuento'] ?? 0,
            ':idi'  => $d['id_impuesto'] ?? null,
            ':pi'   => $d['porcentaje_impuesto'] ?? 0,
            ':vi'   => $d['valor_impuesto'] ?? 0,
            ':sub'  => $d['subtotal'] ?? 0,
            ':tot'  => $d['total'] ?? 0,
            ':idb'  => $d['id_bodega'] ?? null,
            ':lote' => (isset($d['lote']) && $d['lote'] !== '') ? $d['lote'] : null,
            ':nup'  => (isset($d['nup']) && $d['nup'] !== '') ? $d['nup'] : null,
            ':fc'   => (isset($d['fecha_caducidad']) && $d['fecha_caducidad'] !== '') ? $d['fecha_caducidad'] : null,
        ]);
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT cf.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion,
                       c.direccion AS cliente_direccion, c.email AS cliente_email,
                       v.nombre AS vendedor_nombre,
                       vc.estado AS estado_factura, vc.eliminado AS factura_eliminada, vc.importe_total
                FROM consignaciones_facturas cf
                LEFT JOIN clientes c ON c.id = cf.id_cliente
                LEFT JOIN vendedores v ON v.id = cf.id_vendedor
                LEFT JOIN ventas_cabecera vc ON vc.id = cf.id_factura
                WHERE cf.id = :id AND cf.id_empresa = :e AND cf.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idDoc, int $idEmpresa): array
    {
        // saldo_facturable = cantidad de la línea de consignación − retornado − facturado
        // (docs 'facturada'). Para editar un borrador es el máximo permitido por línea.
        $saldoExpr = "(
            (SELECT cvd2.cantidad FROM consignaciones_ventas_detalles cvd2 WHERE cvd2.id = cfd.id_consignacion_detalle)
            - COALESCE(( " . $this->sqlRetornado('cfd.id_consignacion_detalle') . " ), 0)
            - COALESCE(( " . $this->sqlFacturado('cfd.id_consignacion_detalle') . " ), 0)
        )";
        $sql = "SELECT cfd.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       p.inventariable, p.tipo_produccion,
                       b.nombre AS bodega_nombre,
                       cv.serie AS consignacion_serie, cv.secuencial AS consignacion_secuencial,
                       $saldoExpr AS saldo_facturable
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN productos p ON p.id = cfd.id_producto
                LEFT JOIN bodegas b ON b.id = cfd.id_bodega
                LEFT JOIN consignaciones_ventas cv ON cv.id = cfd.id_consignacion
                WHERE cfd.id_consignacion_factura = :id AND cfd.id_empresa = :e
                  AND (cfd.eliminado = false OR cfd.eliminado IS NULL)
                ORDER BY cfd.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDoc, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE consignaciones_facturas SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function deleteDetalles(int $idDoc, int $idEmpresa): void
    {
        $sql = "UPDATE consignaciones_facturas_detalles SET eliminado = true
                WHERE id_consignacion_factura = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idDoc, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_facturas
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $this->deleteDetalles($id, $idEmpresa);
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_facturas
                SET estado = :est, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':est' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Enlaza la factura creada y marca el documento como 'facturada'. */
    public function linkFactura(int $id, int $idEmpresa, int $idFactura, string $numeroFactura, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_facturas
                SET id_factura = :f, numero_factura = :n, estado = 'facturada',
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':f' => $idFactura, ':n' => $numeroFactura, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function updateAsientoReingreso(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE consignaciones_facturas SET id_asiento_reingreso = :a, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->bindValue(':a', $idAsiento, $idAsiento === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->execute();
    }

    // ─── Reversión (al anular/eliminar la factura de origen) ──────────────────

    /** Documento 'facturada' cuya factura es $idFactura (para la reversión automática). */
    public function getDocPorFactura(int $idFactura, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM consignaciones_facturas
                WHERE id_factura = :f AND id_empresa = :e AND eliminado = false AND estado = 'facturada'
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':f' => $idFactura, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Documentos de facturación que incluyen una consignación (historial read-only del modal de consignación). */
    public function getFacturasDeConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT cf.id, cf.serie, cf.secuencial, cf.fecha_emision, cf.numero_factura,
                       cf.total, cf.estado, cf.id_factura, vc.eliminado AS factura_eliminada, vc.estado AS estado_factura
                FROM consignaciones_facturas cf
                INNER JOIN consignaciones_facturas_detalles cfd ON cfd.id_consignacion_factura = cf.id
                LEFT JOIN ventas_cabecera vc ON vc.id = cf.id_factura
                WHERE cfd.id_consignacion = :idc AND cf.id_empresa = :e AND cf.eliminado = false
                  AND cf.estado = 'facturada'
                  AND (cfd.eliminado = false OR cfd.eliminado IS NULL)
                ORDER BY cf.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    /** Línea de consignación de origen con datos autoritativos. */
    public function getConsignacionDetalle(int $idConsDet, int $idEmpresa): ?array
    {
        $sql = "SELECT cvd.*, cv.serie, cv.secuencial, cv.id_cliente, cv.id_vendedor,
                       cv.id_punto_emision, cv.establecimiento, cv.punto_emision, cv.estado AS consignacion_estado,
                       p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       p.inventariable, p.tipo_produccion, p.tarifa_iva
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                WHERE cvd.id = :id AND cvd.id_empresa = :e
                  AND cvd.eliminado = false AND cv.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsDet, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getEstablecimientoPorPunto(int $idPunto): ?int
    {
        foreach (['empresa_punto_emision', 'empresa_puntos_emision'] as $tabla) {
            try {
                $st = $this->db->prepare("SELECT id_establecimiento FROM $tabla WHERE id = ? LIMIT 1");
                $st->execute([$idPunto]);
                $val = $st->fetchColumn();
                if ($val !== false && $val !== null) {
                    return (int) $val;
                }
            } catch (\Throwable $e) {
                // Tabla inexistente: probar la siguiente.
            }
        }
        return null;
    }

    /** Costo unitario al que salió la mercadería en la consignación (kardex de la salida). */
    public function getCostoUnitarioConsignacion(int $idConsignacion, int $idProducto): float
    {
        $sql = "SELECT COALESCE(SUM(costo_total), 0) / NULLIF(SUM(ABS(cantidad)), 0)
                FROM inventario_kardex
                WHERE referencia_tipo = 'CONSIGNACION_VENTA'
                  AND referencia_id   = :idc
                  AND id_producto     = :prod
                  AND tipo_movimiento = 'salida'
                  AND eliminado       = false";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':prod' => $idProducto]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    /** Tarifa de IVA (id, porcentaje_iva, codigo SRI) configurada en el producto. */
    public function getTarifaIvaProducto(int $idProducto): ?array
    {
        $sql = "SELECT ti.id, ti.porcentaje_iva, ti.codigo
                FROM productos p
                JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                WHERE p.id = ?";
        $st = $this->db->prepare($sql);
        $st->execute([$idProducto]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Tarifa de IVA por id. */
    public function getTarifaIvaById(int $idTarifa): ?array
    {
        $st = $this->db->prepare("SELECT id, porcentaje_iva, codigo FROM tarifa_iva WHERE id = ?");
        $st->execute([$idTarifa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
