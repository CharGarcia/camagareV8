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
        'estado', 'created_at'
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
            $whereSql .= " AND (oc.numero_orden ILIKE :b OR p.razon_social ILIKE :b OR p.identificacion ILIKE :b OR oc.observaciones ILIKE :b)";
            $params[':b'] = '%' . $parsed['texto_libre'] . '%';
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
                       p.identificacion AS proveedor_identificacion
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
