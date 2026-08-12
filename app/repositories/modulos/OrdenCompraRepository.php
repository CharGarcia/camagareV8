<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class OrdenCompraRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'numero_orden', 'fecha_orden', 'fecha_recepcion',
        'proveedor_nombre', 'proveedor_identificacion',
        'estado', 'observaciones', 'created_at'
    ];

    public function __construct()
    {
        parent::__construct('ordenes_compra');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'created_at';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'oc', $idUsuarioFiltro);
        $whereSql .= " AND oc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)";
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['oc.numero_orden', 'p.razon_social', 'p.identificacion', 'oc.observaciones'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'proveedor'      => 'p.razon_social',
                'ruc'            => 'p.identificacion',
                'identificacion' => 'p.identificacion',
                'numero'         => 'oc.numero_orden',
                'nro'            => 'oc.numero_orden',
                'obs'            => 'oc.observaciones',
            ],
            'exacto'   => [ 'estado' => 'oc.estado' ],
            'fecha'    => [ 'fecha' => 'oc.fecha_orden', 'fecha_orden' => 'oc.fecha_orden' ],
            'numerico' => [ 'monto' => 'oc.total', 'total' => 'oc.total' ],
        ]);

        $sqlCount = "SELECT COUNT(*) FROM ordenes_compra oc
                     LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                     {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $orderExpr = match($ordenCol) {
            'proveedor_nombre'         => 'p.razon_social',
            'proveedor_identificacion' => 'p.identificacion',
            default                    => "oc.{$ordenCol}"
        };

        $limitSql = $perPage > 0 ? "LIMIT :limit OFFSET :offset" : '';

        $sqlRows = "SELECT oc.*,
                           p.razon_social AS proveedor_nombre,
                           p.identificacion AS proveedor_identificacion,
                           p.email AS proveedor_email,
                           u_created.nombre AS creado_por_nombre,
                           u_updated.nombre AS actualizado_por_nombre
                    FROM ordenes_compra oc
                    LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                    LEFT JOIN usuarios u_created ON u_created.id = oc.created_by
                    LEFT JOIN usuarios u_updated ON u_updated.id = oc.updated_by
                    {$whereSql}
                    ORDER BY {$orderExpr} {$dir}
                    {$limitSql}";

        $stRows = $this->db->prepare($sqlRows);
        foreach ($params as $k => $v) {
            $stRows->bindValue($k, $v);
        }
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $stRows->bindValue(':limit',  $perPage, PDO::PARAM_INT);
            $stRows->bindValue(':offset', $offset,  PDO::PARAM_INT);
        }
        $stRows->execute();
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function getById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT oc.*,
                       p.razon_social AS proveedor_nombre,
                       p.identificacion AS proveedor_identificacion,
                       p.email AS proveedor_email
                FROM ordenes_compra oc
                LEFT JOIN proveedores p ON p.id = oc.id_proveedor
                WHERE oc.id = :id AND oc.id_empresa = :id_empresa AND oc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDetalle(int $idOrden, int $idEmpresa): array
    {
        $sql = "SELECT d.*, COALESCE(p.codigo, '') AS codigo
                FROM ordenes_compra_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                WHERE d.id_orden = :id_orden AND d.id_empresa = :id_empresa
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertar(array $data): int
    {
        $sql = "INSERT INTO ordenes_compra
                    (id_empresa, id_proveedor, id_establecimiento, id_punto_emision,
                     establecimiento, punto_emision, secuencial,
                     fecha_orden, fecha_recepcion, observaciones, estado,
                     created_at, updated_at, created_by, updated_by, eliminado)
                VALUES
                    (:id_empresa, :id_proveedor, :id_establecimiento, :id_punto_emision,
                     :establecimiento, :punto_emision, :secuencial,
                     :fecha_orden, :fecha_recepcion, :observaciones, :estado,
                     NOW(), NOW(), :created_by, :updated_by, false)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'         => $data['id_empresa'],
            ':id_proveedor'       => $data['id_proveedor'],
            ':id_establecimiento' => $data['id_establecimiento'],
            ':id_punto_emision'   => $data['id_punto_emision'],
            ':establecimiento'    => $data['establecimiento'],
            ':punto_emision'      => $data['punto_emision'],
            ':secuencial'         => $data['secuencial'],
            ':fecha_orden'        => $data['fecha_orden'],
            ':fecha_recepcion'    => $data['fecha_recepcion'] ?: null,
            ':observaciones'      => $data['observaciones'] ?: null,
            ':estado'             => $data['estado'] ?? 'borrador',
            ':created_by'         => $data['created_by'],
            ':updated_by'         => $data['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function insertarDetalle(array $item): void
    {
        $sql = "INSERT INTO ordenes_compra_detalle
                    (id_orden, id_empresa, id_producto, descripcion, cantidad, precio_unitario, created_at, created_by)
                VALUES
                    (:id_orden, :id_empresa, :id_producto, :descripcion, :cantidad, :precio_unitario, NOW(), :created_by)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_orden'        => $item['id_orden'],
            ':id_empresa'      => $item['id_empresa'],
            ':id_producto'     => $item['id_producto'] ?: null,
            ':descripcion'     => $item['descripcion'],
            ':cantidad'        => $item['cantidad'],
            ':precio_unitario' => $item['precio_unitario'],
            ':created_by'      => $item['created_by'],
        ]);
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $sql = "UPDATE ordenes_compra SET
                    id_proveedor       = :id_proveedor,
                    id_establecimiento = :id_establecimiento,
                    id_punto_emision   = :id_punto_emision,
                    establecimiento    = :establecimiento,
                    punto_emision      = :punto_emision,
                    fecha_orden        = :fecha_orden,
                    fecha_recepcion    = :fecha_recepcion,
                    observaciones      = :observaciones,
                    estado             = :estado,
                    updated_at         = NOW(),
                    updated_by         = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa,
            ':id_proveedor'       => $data['id_proveedor'],
            ':id_establecimiento' => $data['id_establecimiento'],
            ':id_punto_emision'   => $data['id_punto_emision'],
            ':establecimiento'    => $data['establecimiento'],
            ':punto_emision'      => $data['punto_emision'],
            ':fecha_orden'        => $data['fecha_orden'],
            ':fecha_recepcion'    => $data['fecha_recepcion'] ?: null,
            ':observaciones'      => $data['observaciones'] ?: null,
            ':estado'             => $data['estado'] ?? 'borrador',
            ':updated_by'         => $data['updated_by'],
        ]);
    }

    /**
     * Órdenes de un proveedor disponibles para vincular con una compra: no
     * eliminadas, en borrador/aprobado, y sin otra compra vigente ya vinculada
     * (salvo la propia $idCompraActual, para poder reabrir la compra que ya
     * tiene esta orden vinculada y seguir viéndola en la lista).
     */
    public function getAbiertasPorProveedor(int $idProveedor, int $idEmpresa, ?int $idCompraActual = null): array
    {
        $sql = "SELECT oc.id, oc.numero_orden, oc.fecha_orden, oc.fecha_recepcion, oc.estado,
                       COALESCE((SELECT SUM(d.cantidad * d.precio_unitario)
                                 FROM ordenes_compra_detalle d
                                 WHERE d.id_orden = oc.id), 0) AS total
                FROM ordenes_compra oc
                WHERE oc.id_empresa = :id_empresa
                  AND oc.id_proveedor = :id_proveedor
                  AND oc.eliminado = false
                  AND oc.estado IN ('borrador', 'aprobado')
                  AND NOT EXISTS (
                        SELECT 1 FROM compras_cabecera cc
                        WHERE cc.id_orden_compra = oc.id
                          AND cc.eliminado = false
                          AND cc.id IS DISTINCT FROM :id_compra_actual
                  )
                ORDER BY oc.fecha_orden DESC, oc.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'       => $idEmpresa,
            ':id_proveedor'     => $idProveedor,
            ':id_compra_actual' => $idCompraActual,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cambia solo el estado (y opcionalmente marca fecha_recepcion) sin tocar el resto de la cabecera. */
    public function cambiarEstado(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $marcarFechaRecepcion = false): void
    {
        $sql = "UPDATE ordenes_compra SET
                    estado     = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by"
                . ($marcarFechaRecepcion ? ", fecha_recepcion = COALESCE(fecha_recepcion, CURRENT_DATE)" : "")
                . " WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
            ':estado'     => $estado,
            ':updated_by' => $idUsuario,
        ]);
    }

    public function eliminarDetalle(int $idOrden, int $idEmpresa): void
    {
        $sql = "DELETE FROM ordenes_compra_detalle WHERE id_orden = :id_orden AND id_empresa = :id_empresa";
        $st  = $this->db->prepare($sql);
        $st->execute([':id_orden' => $idOrden, ':id_empresa' => $idEmpresa]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE ordenes_compra SET
                    eliminado  = true,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'         => $id,
            ':id_empresa' => $idEmpresa,
            ':deleted_by' => $idUsuario,
            ':updated_by' => $idUsuario,
        ]);
    }
}
