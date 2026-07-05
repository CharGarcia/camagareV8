<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de Retornos de Consignaciones en Ventas.
 *
 * Un retorno es la devolución (entrada de inventario) de mercadería entregada
 * previamente al cliente en una o varias consignaciones de venta.
 */
class RetornoCvRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('retornos_cv');
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
                'fecha'      => 'r.fecha_retorno',
            ],
            'numerico' => [
                'total'      => 'r.total',
            ],
        ]);

        $sqlCount = "
            SELECT COUNT(*)
            FROM retornos_cv r
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
            'fecha_retorno' => 'r.fecha_retorno',
            'secuencial'    => 'r.secuencial',
            'cliente'       => 'c.nombre',
            'estado'        => 'r.estado',
            'total'         => 'r.total',
        ];
        $sort = $colMap[$ordenCol] ?? 'r.fecha_retorno';
        $dir  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT r.*,
                   c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                   rt.nombre as responsable_traslado_nombre
            FROM retornos_cv r
            INNER JOIN clientes c ON c.id = r.id_cliente
            LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
            $where
            ORDER BY $sort $dir, r.id DESC
            $limitClause
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    // ─── CONSIGNACIONES PENDIENTES POR CLIENTE ────────────────────────────────

    /**
     * Devuelve todas las líneas de consignaciones del cliente con saldo pendiente
     * de retornar (> 0), con los datos "tal cual" de la consignación de origen.
     *
     * saldo_pendiente = cantidad_consignada - Σ(retornos activos de esa línea)
     */
    public function getLineasPendientesPorCliente(int $idEmpresa, int $idCliente, ?int $excluirRetorno = null): array
    {
        $params = [':e' => $idEmpresa, ':cli' => $idCliente];

        // Al editar un retorno, sus propias líneas no deben restar del saldo disponible.
        $excludeSql = '';
        if ($excluirRetorno !== null) {
            $excludeSql = ' AND rc.id <> :exc';
            $params[':exc'] = $excluirRetorno;
        }

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
                    cv.establecimiento,
                    cv.punto_emision,
                    p.nombre  AS producto_nombre,
                    p.codigo  AS producto_codigo,
                    p.inventariable,
                    p.tipo_produccion,
                    b.nombre  AS bodega_nombre,
                    COALESCE((
                        SELECT SUM(rcd.cantidad)
                        FROM retornos_cv_detalles rcd
                        INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                        WHERE rcd.id_consignacion_detalle = cvd.id
                          AND rcd.eliminado = false
                          AND rc.eliminado = false
                          AND rc.estado = 'Emitida'
                          $excludeSql
                    ), 0) AS cantidad_retornada
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                LEFT JOIN bodegas b ON b.id = cvd.id_bodega
                WHERE cv.id_empresa = :e
                  AND cv.id_cliente = :cli
                  AND cv.eliminado = false
                  AND cv.estado = 'Entregada'
                  AND cvd.eliminado = false
            ) t
            WHERE (t.cantidad_consignada - t.cantidad_retornada) > 0
            ORDER BY t.fecha_emision DESC, t.id_consignacion DESC, t.id_consignacion_detalle ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['saldo_pendiente'] = (float) $r['cantidad_consignada'] - (float) $r['cantidad_retornada'];
        }
        unset($r);

        return $rows;
    }

    /**
     * Busca consignaciones que tengan al menos una línea con saldo pendiente de retornar,
     * coincidiendo por nombre/identificación del cliente o por número de consignación (serie-secuencial).
     * Devuelve una fila por consignación con el cliente y el número.
     */
    public function buscarConsignacionesPendientes(int $idEmpresa, string $q): array
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
                      AND (cvd.cantidad - COALESCE((
                            SELECT SUM(rcd.cantidad)
                            FROM retornos_cv_detalles rcd
                            INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                            WHERE rcd.id_consignacion_detalle = cvd.id
                              AND rcd.eliminado = false AND rc.eliminado = false AND rc.estado = 'Emitida'
                      ), 0)) > 0
              )
            ORDER BY cv.fecha_emision DESC, cv.id DESC
            LIMIT 15
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':q' => '%' . $q . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo pendiente de retornar de UNA línea de consignación (para validación en el Service).
     */
    public function getSaldoLineaConsignacion(int $idConsignacionDetalle, int $idEmpresa, ?int $excluirRetorno = null): float
    {
        // Cantidad original de la línea de consignación (validando empresa y no eliminada).
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

        $paramsRet = [':id' => $idConsignacionDetalle];
        $excludeSql = '';
        if ($excluirRetorno !== null) {
            $excludeSql = ' AND rc.id <> :exc';
            $paramsRet[':exc'] = $excluirRetorno;
        }

        $sqlRet = "SELECT COALESCE(SUM(rcd.cantidad), 0)
                   FROM retornos_cv_detalles rcd
                   INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno
                   WHERE rcd.id_consignacion_detalle = :id
                     AND rcd.eliminado = false
                     AND rc.eliminado = false
                     AND rc.estado = 'Emitida'
                     $excludeSql";
        $stRet = $this->db->prepare($sqlRet);
        $stRet->execute($paramsRet);
        $retornado = (float) $stRet->fetchColumn();

        return (float) $cantidad - $retornado;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO retornos_cv (" . implode(', ', $fields) . ")
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
        $sql = "INSERT INTO retornos_cv_detalles (
                    id_retorno, id_empresa, id_consignacion, id_consignacion_detalle, id_producto,
                    cantidad, precio_unitario, subtotal, id_impuesto, porcentaje_impuesto,
                    valor_impuesto, total, id_bodega, lote, nup, fecha_caducidad, eliminado
                ) VALUES (
                    :idr, :e, :idc, :idcd, :prod, :cant, :pu, :sub, :idi, :pi,
                    :vi, :tot, :idb, :lote, :nup, :fc, false
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':idr'  => $d['id_retorno'],
            ':e'    => $d['id_empresa'],
            ':idc'  => $d['id_consignacion'],
            ':idcd' => $d['id_consignacion_detalle'],
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
                       c.direccion as cliente_direccion, c.email as cliente_email,
                       rt.nombre as responsable_traslado_nombre
                FROM retornos_cv r
                INNER JOIN clientes c ON c.id = r.id_cliente
                LEFT JOIN responsables_traslado rt ON rt.id = r.id_responsable_traslado
                WHERE r.id = :id AND r.id_empresa = :e AND r.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalles(int $idRetorno, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*,
                   p.nombre as producto_nombre, p.codigo as producto_codigo, p.inventariable, p.tipo_produccion,
                   b.nombre as bodega_nombre,
                   cv.serie as consignacion_serie, cv.secuencial as consignacion_secuencial
            FROM retornos_cv_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            LEFT JOIN consignaciones_ventas cv ON cv.id = d.id_consignacion
            WHERE d.id_retorno = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRetorno, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cantidad retornada por cada línea de consignación: [id_consignacion_detalle => cantidad]. */
    public function getRetornadoPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT rcd.id_consignacion_detalle AS idd, COALESCE(SUM(rcd.cantidad), 0) AS cant
            FROM retornos_cv_detalles rcd
            INNER JOIN retornos_cv r ON r.id = rcd.id_retorno AND r.eliminado = false
            WHERE rcd.id_consignacion = :idc AND rcd.id_empresa = :e
              AND (rcd.eliminado = false OR rcd.eliminado IS NULL)
            GROUP BY rcd.id_consignacion_detalle
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int)$r['idd']] = (float)$r['cant'];
        }
        return $map;
    }

    /** Líneas de retorno asociadas a una consignación específica (para la pestaña Retornos). */
    public function getPorConsignacion(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT d.id, d.cantidad, d.precio_unitario, d.subtotal, d.total,
                   d.lote, d.nup, d.fecha_caducidad,
                   p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                   b.nombre AS bodega_nombre,
                   r.id AS id_retorno, r.fecha_retorno, r.serie, r.secuencial,
                   r.estado, r.motivo
            FROM retornos_cv_detalles d
            INNER JOIN retornos_cv r ON r.id = d.id_retorno AND r.eliminado = false
            INNER JOIN productos p   ON p.id = d.id_producto
            LEFT JOIN bodegas b      ON b.id = d.id_bodega
            WHERE d.id_consignacion = :idc AND d.id_empresa = :e
              AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY r.fecha_retorno DESC, r.id DESC, d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':idc' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vincula (o desvincula con null) el asiento contable generado para el retorno. */
    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE retornos_cv SET id_asiento_contable = :a WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':a' => $idAsiento, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Marca lógicamente como eliminados los detalles de un retorno (para reemplazarlos al editar). */
    public function deleteDetalles(int $idRetorno, int $idEmpresa): void
    {
        $sql = "UPDATE retornos_cv_detalles SET eliminado = true
                WHERE id_retorno = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idRetorno, ':e' => $idEmpresa]);
    }

    /** Actualiza campos de la cabecera del retorno. */
    public function updateCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }
        $sql = "UPDATE retornos_cv SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
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
        $sql = "UPDATE retornos_cv
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE retornos_cv
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE retornos_cv_detalles
                   SET eliminado = true
                   WHERE id_retorno = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }
}
