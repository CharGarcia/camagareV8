<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de Cambios de productos (`modulos/cambio-producto-cv`).
 *
 * Un cambio agrupa, por cliente y en un solo documento:
 *   - líneas de DEVOLUCIÓN (tipo_linea='devolucion') → entrada de inventario. Su
 *     origen es una línea de factura de venta (ventas_detalle) o una línea de
 *     ENTREGA de un cambio anterior (encadenado). Se controla el saldo.
 *   - líneas de ENTREGA (tipo_linea='entrega') → salida de inventario (catálogo).
 */
class CambioProductoCvRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('cambios_producto_cv');
    }

    // ─── LISTADO PAGINADO ─────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $where  = "WHERE r.id_empresa = :e AND r.eliminado = false";
        $params = [':e' => $idEmpresa];

        if ($idUsuarioFiltro !== null) {
            $where .= " AND r.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= " AND (r.secuencial ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b OR r.estado ILIKE :b OR r.motivo ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'  => [
                'cliente'    => 'c.nombre',
                'secuencial' => 'r.secuencial',
                'motivo'     => 'r.motivo',
            ],
            'exacto' => [
                'estado'     => 'r.estado',
            ],
            'fecha'  => [
                'fecha'      => 'r.fecha_cambio',
            ],
            'numerico' => [
                'diferencia' => 'r.diferencia',
            ],
        ]);

        $sqlCount = "
            SELECT COUNT(*)
            FROM cambios_producto_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            $where
        ";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $colMap = [
            'fecha_cambio' => 'r.fecha_cambio',
            'secuencial'   => 'r.secuencial',
            'cliente'      => 'c.nombre',
            'estado'       => 'r.estado',
            'diferencia'   => 'r.diferencia',
        ];
        $sort = $colMap[$ordenCol] ?? 'r.fecha_cambio';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT r.*,
                   c.nombre as cliente_nombre, c.identificacion as cliente_identificacion
            FROM cambios_producto_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            $where
            ORDER BY $sort $dir, r.id DESC
            $limitClause
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── LÍNEAS DISPONIBLES PARA DEVOLVER (origen factura + cambios previos) ────

    /**
     * Líneas del cliente que pueden devolverse, con saldo pendiente (> 0):
     *   (a) líneas de facturas de venta (ventas_detalle) → origen_tipo 'FACTURA';
     *   (b) líneas de ENTREGA de cambios previos Emitida → origen_tipo 'CAMBIO'.
     *
     * saldo = cantidad_origen − Σ(devuelto en cambios Emitida que referencian esa línea).
     * $q filtra por nombre/código de producto o número de documento origen.
     */
    public function getLineasDisponiblesCliente(int $idEmpresa, int $idCliente, string $q, ?int $excluirCambio = null): array
    {
        $params = [':e' => $idEmpresa, ':cli' => $idCliente, ':q' => '%' . $q . '%'];

        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $params[':exc'] = $excluirCambio;
        }

        // Subconsulta reutilizable de "cantidad ya devuelta" para un origen dado.
        $devuelto = function (string $origenTipo, string $colDetalle) use ($excSql): string {
            return "COALESCE((
                SELECT SUM(cd.cantidad)
                FROM cambios_producto_cv_detalles cd
                INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = '$origenTipo'
                  AND cd.id_origen_detalle = $colDetalle
                  AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                  $excSql
            ), 0)";
        };

        $sql = "
            SELECT * FROM (
                -- (a) Líneas de facturas de venta
                SELECT
                    'FACTURA'          AS origen_tipo,
                    v.id               AS id_origen,
                    d.id               AS id_origen_detalle,
                    (v.serie_num)      AS doc_numero,
                    v.fecha_emision    AS doc_fecha,
                    d.id_producto,
                    p.codigo           AS producto_codigo,
                    p.nombre           AS producto_nombre,
                    p.inventariable,
                    p.tipo_produccion,
                    d.precio_unitario,
                    NULL::integer      AS id_impuesto,
                    COALESCE((SELECT MAX(vdi.tarifa) FROM ventas_detalle_impuestos vdi WHERE vdi.id_venta_detalle = d.id), 0) AS porcentaje_impuesto,
                    d.numero_lote      AS lote,
                    d.nup              AS nup,
                    d.fecha_caducidad,
                    d.id_bodega,
                    b.nombre           AS bodega_nombre,
                    d.cantidad         AS cantidad_origen,
                    " . $devuelto('FACTURA', 'd.id') . " AS cantidad_devuelta
                FROM ventas_detalle d
                INNER JOIN (
                    SELECT vc.id, vc.id_cliente, vc.fecha_emision,
                           (COALESCE(vc.establecimiento,'') || '-' || COALESCE(vc.punto_emision,'') || '-' || COALESCE(vc.secuencial,'')) AS serie_num
                    FROM ventas_cabecera vc
                    WHERE vc.id_empresa = :e AND vc.id_cliente = :cli
                      AND vc.eliminado = false AND LOWER(COALESCE(vc.estado,'')) = 'autorizado'
                ) v ON v.id = d.id_venta
                INNER JOIN productos p ON p.id = d.id_producto
                LEFT JOIN bodegas b ON b.id = d.id_bodega
                WHERE COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios

                UNION ALL

                -- (b) Líneas de ENTREGA de cambios anteriores (Emitida) del cliente
                SELECT
                    'CAMBIO'           AS origen_tipo,
                    e.id_cambio        AS id_origen,
                    e.id               AS id_origen_detalle,
                    (cx.serie || '-' || cx.secuencial) AS doc_numero,
                    cx.fecha_cambio    AS doc_fecha,
                    e.id_producto,
                    p.codigo           AS producto_codigo,
                    p.nombre           AS producto_nombre,
                    p.inventariable,
                    p.tipo_produccion,
                    e.precio_unitario,
                    e.id_impuesto,
                    e.porcentaje_impuesto,
                    e.lote,
                    e.nup,
                    e.fecha_caducidad,
                    e.id_bodega,
                    b.nombre           AS bodega_nombre,
                    e.cantidad         AS cantidad_origen,
                    " . $devuelto('CAMBIO', 'e.id') . " AS cantidad_devuelta
                FROM cambios_producto_cv_detalles e
                INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                INNER JOIN productos p ON p.id = e.id_producto
                LEFT JOIN bodegas b ON b.id = e.id_bodega
                WHERE e.tipo_linea = 'entrega' AND e.eliminado = false
                  AND cx.id_empresa = :e AND cx.id_cliente = :cli
                  AND cx.eliminado = false AND cx.estado = 'Emitida'
                  AND COALESCE(p.tipo_produccion,'01') = '01'   -- solo bienes/productos, no servicios
            ) t
            WHERE (t.cantidad_origen - t.cantidad_devuelta) > 0
              AND (t.producto_nombre ILIKE :q OR t.producto_codigo ILIKE :q OR t.doc_numero ILIKE :q)
            ORDER BY t.doc_fecha DESC, t.id_origen DESC, t.id_origen_detalle ASC
            LIMIT 30
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['saldo_pendiente'] = (float) $r['cantidad_origen'] - (float) $r['cantidad_devuelta'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Saldo pendiente de devolver de UNA línea de origen (para validación en el Service).
     */
    public function getSaldoLineaOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa, ?int $excluirCambio = null): float
    {
        $cantidad = $this->getCantidadOrigen($origenTipo, $idOrigenDetalle, $idEmpresa);
        if ($cantidad <= 0) {
            return 0.0;
        }

        $paramsDev = [':id' => $idOrigenDetalle, ':ot' => $origenTipo];
        $excSql = '';
        if ($excluirCambio !== null) {
            $excSql = ' AND cc.id <> :exc';
            $paramsDev[':exc'] = $excluirCambio;
        }

        $sqlDev = "SELECT COALESCE(SUM(cd.cantidad), 0)
                   FROM cambios_producto_cv_detalles cd
                   INNER JOIN cambios_producto_cv cc ON cc.id = cd.id_cambio
                   WHERE cd.tipo_linea = 'devolucion' AND cd.origen_tipo = :ot
                     AND cd.id_origen_detalle = :id
                     AND cd.eliminado = false AND cc.eliminado = false AND cc.estado = 'Emitida'
                     $excSql";
        $stDev = $this->db->prepare($sqlDev);
        $stDev->execute($paramsDev);
        $devuelto = (float) $stDev->fetchColumn();

        return $cantidad - $devuelto;
    }

    /** Cantidad original de la línea de origen (factura o entrega de cambio previo). */
    private function getCantidadOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): float
    {
        if ($origenTipo === 'FACTURA') {
            $sql = "SELECT d.cantidad
                    FROM ventas_detalle d
                    INNER JOIN ventas_cabecera v ON v.id = d.id_venta
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND LOWER(COALESCE(v.estado,'')) = 'autorizado'";
        } else { // CAMBIO
            $sql = "SELECT e.cantidad
                    FROM cambios_producto_cv_detalles e
                    INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                    WHERE e.id = :id AND e.tipo_linea = 'entrega' AND cx.id_empresa = :e
                      AND e.eliminado = false AND cx.eliminado = false AND cx.estado = 'Emitida'";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrigenDetalle, ':e' => $idEmpresa]);
        $v = $st->fetchColumn();
        return $v === false ? 0.0 : (float) $v;
    }

    /**
     * Datos autoritativos "tal cual" de la línea de origen a devolver (para el Service).
     * Devuelve producto, precio, impuesto, lote, nup, bodega, caducidad e id_origen.
     */
    public function getDatosLineaOrigen(string $origenTipo, int $idOrigenDetalle, int $idEmpresa): ?array
    {
        if ($origenTipo === 'FACTURA') {
            $sql = "SELECT v.id AS id_origen, d.id_producto,
                           d.precio_unitario,
                           NULL::integer AS id_impuesto,
                           COALESCE((SELECT MAX(vdi.tarifa) FROM ventas_detalle_impuestos vdi WHERE vdi.id_venta_detalle = d.id), 0) AS porcentaje_impuesto,
                           d.id_bodega, d.numero_lote AS lote, d.nup, d.fecha_caducidad,
                           p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                    FROM ventas_detalle d
                    INNER JOIN ventas_cabecera v ON v.id = d.id_venta
                    INNER JOIN productos p ON p.id = d.id_producto
                    WHERE d.id = :id AND v.id_empresa = :e AND v.eliminado = false
                      AND LOWER(COALESCE(v.estado,'')) = 'autorizado'";
        } else { // CAMBIO
            $sql = "SELECT e.id_cambio AS id_origen, e.id_producto,
                           e.precio_unitario, e.id_impuesto, e.porcentaje_impuesto,
                           e.id_bodega, e.lote, e.nup, e.fecha_caducidad,
                           p.nombre AS producto_nombre, p.inventariable, p.tipo_produccion
                    FROM cambios_producto_cv_detalles e
                    INNER JOIN cambios_producto_cv cx ON cx.id = e.id_cambio
                    INNER JOIN productos p ON p.id = e.id_producto
                    WHERE e.id = :id AND e.tipo_linea = 'entrega' AND cx.id_empresa = :e
                      AND e.eliminado = false AND cx.eliminado = false AND cx.estado = 'Emitida'";
        }
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idOrigenDetalle, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Datos del producto de catálogo para una línea de ENTREGA (autoritativo). */
    public function getProductoParaEntrega(int $idProducto, int $idEmpresa): ?array
    {
        $sql = "SELECT p.id AS id_producto, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       p.inventariable, p.tipo_produccion
                FROM productos p
                WHERE p.id = :id AND p.id_empresa = :e AND p.eliminado = false
                  AND COALESCE(p.tipo_produccion,'01') = '01'";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO cambios_producto_cv (" . implode(', ', $fields) . ")
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
        $sql = "INSERT INTO cambios_producto_cv_detalles (
                    id_cambio, id_empresa, tipo_linea, origen_tipo, id_origen, id_origen_detalle,
                    id_producto, cantidad, precio_unitario, subtotal, id_impuesto, porcentaje_impuesto,
                    valor_impuesto, total, id_bodega, lote, nup, fecha_caducidad, eliminado
                ) VALUES (
                    :idc, :e, :tl, :ot, :io, :iod, :prod, :cant, :pu, :sub, :idi, :pi,
                    :vi, :tot, :idb, :lote, :nup, :fc, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':idc'  => $d['id_cambio'],
            ':e'    => $d['id_empresa'],
            ':tl'   => $d['tipo_linea'],
            ':ot'   => $d['origen_tipo'] ?? null,
            ':io'   => $d['id_origen'] ?? null,
            ':iod'  => $d['id_origen_detalle'] ?? null,
            ':prod' => $d['id_producto'],
            ':cant' => $d['cantidad'],
            ':pu'   => $d['precio_unitario'] ?? 0,
            ':sub'  => $d['subtotal'] ?? 0,
            ':idi'  => $d['id_impuesto'] ?? null,
            ':pi'   => $d['porcentaje_impuesto'] ?? 0,
            ':vi'   => $d['valor_impuesto'] ?? 0,
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
        $sql = "SELECT r.*,
                       c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                       c.direccion as cliente_direccion, c.email as cliente_email
                FROM cambios_producto_cv r
                INNER JOIN clientes c ON c.id = r.id_cliente
                WHERE r.id = :id AND r.id_empresa = :e AND r.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idCambio, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*,
                   p.nombre as producto_nombre, p.codigo as producto_codigo, p.inventariable, p.tipo_produccion,
                   b.nombre as bodega_nombre
            FROM cambios_producto_cv_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            WHERE d.id_cambio = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.tipo_linea DESC, d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCambio, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vincula (o desvincula con null) el asiento contable generado para el cambio. */
    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE cambios_producto_cv SET id_asiento_contable = :a WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAsiento, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Marca lógicamente como eliminados los detalles (para reemplazarlos al editar). */
    public function deleteDetalles(int $idCambio, int $idEmpresa): void
    {
        $sql = "UPDATE cambios_producto_cv_detalles SET eliminado = true
                WHERE id_cambio = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCambio, ':e' => $idEmpresa]);
    }

    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE cambios_producto_cv SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE cambios_producto_cv
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE cambios_producto_cv
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE cambios_producto_cv_detalles
                   SET eliminado = true
                   WHERE id_cambio = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }
}
