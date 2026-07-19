<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ConsignacionVentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('consignaciones_ventas');
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE cv.id_empresa = :e AND cv.eliminado = false";

        if ($idUsuarioFiltro !== null) {
            $where .= " AND cv.created_by = :uid";
            $params[':uid'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $where .= " AND (cv.secuencial ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b OR v.nombre ILIKE :b OR cv.estado ILIKE :b)";
            $params[':b'] = "%$buscar%";
        }

        $sqlCount = "
            SELECT COUNT(*)
            FROM consignaciones_ventas cv
            INNER JOIN clientes c ON c.id = cv.id_cliente
            LEFT JOIN vendedores v ON v.id = cv.id_vendedor
            $where
        ";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        } else {
            $limitClause = "";
        }

        $colMap = [
            'fecha_emision' => 'cv.fecha_emision',
            'secuencial' => 'cv.secuencial',
            'cliente' => 'c.nombre',
            'vendedor' => 'v.nombre',
            'estado' => 'cv.estado',
            'total' => 'cv.total'
        ];
        $sort = $colMap[$ordenCol] ?? 'cv.fecha_emision';
        $dir = $ordenDir === 'DESC' ? 'DESC' : 'ASC';

        $sql = "
            SELECT cv.*, 
                   c.nombre as cliente_nombre, c.identificacion as cliente_identificacion,
                   v.nombre as vendedor_nombre,
                   rt.nombre as responsable_traslado_nombre
            FROM consignaciones_ventas cv
            INNER JOIN clientes c ON c.id = cv.id_cliente
            LEFT JOIN vendedores v ON v.id = cv.id_vendedor
            LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
            $where
            ORDER BY $sort $dir, cv.id DESC
            $limitClause
        ";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * Consignaciones en estado 'Emitida' (pendientes de entrega) para el módulo Entregas
     * de la app móvil. $idsResponsables: si viene no-null, filtra a esos responsables de
     * traslado (repartidor sin "acceso total"); null = ve todas (acceso total).
     */
    public function getPendientesEntrega(int $idEmpresa, ?array $idsResponsables, string $buscar, int $page, int $perPage): array
    {
        $params = [':e' => $idEmpresa];
        $where = "WHERE cv.id_empresa = :e AND cv.eliminado = false AND cv.estado = 'Emitida'";

        if ($idsResponsables !== null) {
            if (empty($idsResponsables)) {
                return ['total' => 0, 'rows' => []];
            }
            $marcadores = [];
            foreach (array_values($idsResponsables) as $i => $idResp) {
                $clave = ":r{$i}";
                $marcadores[] = $clave;
                $params[$clave] = $idResp;
            }
            $where .= " AND cv.id_responsable_traslado IN (" . implode(',', $marcadores) . ")";
        }

        if ($buscar !== '') {
            $where .= " AND (cv.secuencial ILIKE :b OR c.nombre ILIKE :b OR c.identificacion ILIKE :b)";
            $params[':b'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM consignaciones_ventas cv
                     INNER JOIN clientes c ON c.id = cv.id_cliente
                     $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $limitClause = '';
        if ($perPage > 0) {
            $offset = ($page - 1) * $perPage;
            $limitClause = "LIMIT $perPage OFFSET $offset";
        }

        $sql = "SELECT cv.id, cv.serie, cv.secuencial, cv.fecha_emision, cv.fecha_entrega,
                       cv.hora_entrega_desde, cv.hora_entrega_hasta, cv.punto_partida, cv.punto_llegada,
                       cv.total, cv.estado,
                       c.nombre AS cliente_nombre, c.direccion AS cliente_direccion, c.identificacion AS cliente_identificacion,
                       rt.nombre AS responsable_traslado_nombre
                FROM consignaciones_ventas cv
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                $where
                ORDER BY cv.fecha_entrega ASC NULLS LAST, cv.id DESC
                $limitClause";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getDetalles(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT d.*, p.nombre as producto_nombre, p.codigo as producto_codigo, p.tipo_produccion, p.inventariable, p.precio_base as precio_base,
                   b.nombre as bodega_nombre
            FROM consignaciones_ventas_detalles d
            INNER JOIN productos p ON p.id = d.id_producto
            LEFT JOIN bodegas b ON b.id = d.id_bodega
            WHERE d.id_consignacion = :id AND d.id_empresa = :e AND (d.eliminado = false OR d.eliminado IS NULL)
            ORDER BY d.id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO consignaciones_ventas (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";

        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->execute();
        
        // PostgreSQL RETURNING id
        return (int) $st->fetchColumn();
    }

    public function updateEstado(int $id, int $idEmpresa, string $estado, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET estado = :est, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':est' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function updateEntregaConfirmada(int $id, int $idEmpresa, ?int $idEntrega): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET id_entrega_confirmada = :en, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->bindValue(':en', $idEntrega, $idEntrega === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, \PDO::PARAM_INT);
        $st->execute();
    }

    public function updateAsientoContable(int $id, int $idEmpresa, ?int $idAsiento): void
    {
        $sql = "UPDATE consignaciones_ventas
                   SET id_asiento_contable = :a, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->bindValue(':a', $idAsiento, $idAsiento === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->bindValue(':e', $idEmpresa, \PDO::PARAM_INT);
        $st->execute();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT cv.*,
                       c.nombre as cliente_nombre, c.identificacion as cliente_identificacion, c.direccion as cliente_direccion,
                       c.email as cliente_email,
                       v.nombre as vendedor_nombre,
                       rt.nombre as responsable_traslado_nombre
                FROM consignaciones_ventas cv
                INNER JOIN clientes c ON c.id = cv.id_cliente
                LEFT JOIN vendedores v ON v.id = cv.id_vendedor
                LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
                WHERE cv.id = :id AND cv.id_empresa = :e AND cv.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
        }

        $sql = "UPDATE consignaciones_ventas SET " . implode(', ', $fields) . " WHERE id = :id_ AND id_empresa = :e_";
        $st = $this->db->prepare($sql);
        foreach ($data as $k => $v) {
            $st->bindValue(":$k", $v);
        }
        $st->bindValue(':id_', $id);
        $st->bindValue(':e_', $idEmpresa);
        $st->execute();
    }

    public function deleteDetalles(int $idConsignacion, int $idEmpresa): void
    {
        $sql = "UPDATE consignaciones_ventas_detalles SET eliminado = true WHERE id_consignacion = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idConsignacion, ':e' => $idEmpresa]);
    }
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE consignaciones_ventas 
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u 
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa, ':u' => $idUsuario]);

        $sqlDet = "UPDATE consignaciones_ventas_detalles 
                   SET eliminado = true 
                   WHERE id_consignacion = :id AND id_empresa = :e AND eliminado = false";
        $stDet = $this->db->prepare($sqlDet);
        $stDet->execute([':id' => $id, ':e' => $idEmpresa]);
    }
}
