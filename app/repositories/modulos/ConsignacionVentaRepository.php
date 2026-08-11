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

        $parsed     = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        $textoLibre = $parsed['texto_libre'];
        $filtros    = $parsed['filtros'];

        if ($textoLibre !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['cv.secuencial', 'c.nombre', 'c.identificacion', 'v.nombre', 'cv.estado', 'cv.observaciones'],
                $textoLibre,
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $filtros, [
            'texto' => [
                'serie'          => 'cv.secuencial',
                'secuencial'     => 'cv.secuencial',
                'numero'         => 'cv.secuencial',
                'cliente'        => 'c.nombre',
                'ruc'            => 'c.identificacion',
                'identificacion' => 'c.identificacion',
                'vendedor'       => 'v.nombre',
                'asesor'         => 'v.nombre',
                'responsable'    => 'rt.nombre',
                'obs'            => 'cv.observaciones',
                'observacion'    => 'cv.observaciones',
                'observaciones'  => 'cv.observaciones',
            ],
            'exacto' => [
                'estado' => 'cv.estado',
            ],
            'fecha' => [
                'fecha'         => 'cv.fecha_emision',
                'fecha_emision' => 'cv.fecha_emision',
                'entrega'       => 'cv.fecha_entrega',
                'fecha_entrega' => 'cv.fecha_entrega',
            ],
            'numerico' => [
                'total'    => 'cv.total',
                'monto'    => 'cv.total',
                'subtotal' => 'cv.subtotal',
            ],
        ]);

        $sqlCount = "
            SELECT COUNT(*)
            FROM consignaciones_ventas cv
            INNER JOIN clientes c ON c.id = cv.id_cliente
            LEFT JOIN vendedores v ON v.id = cv.id_vendedor
            LEFT JOIN responsables_traslado rt ON rt.id = cv.id_responsable_traslado
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

    /**
     * Kardex de la consignación: un movimiento por fila (consignación inicial, retorno,
     * facturación), ordenados cronológicamente, con saldo corriente por PRODUCTO
     * (columna "saldo" = SUM ventana particionada por id_producto).
     */
    public function getKardex(int $idConsignacion, int $idEmpresa): array
    {
        $sql = "
            SELECT t.*,
                   SUM(t.mov) OVER (
                       PARTITION BY t.id_producto
                       ORDER BY t.fecha, t.orden, t.orden_id
                       ROWS UNBOUNDED PRECEDING
                   ) AS saldo
            FROM (
                -- 1. Consignación inicial (una fila por línea)
                SELECT cvd.id AS id_consignacion_detalle, cv.fecha_emision AS fecha, 1 AS orden, cvd.id AS orden_id,
                       'Consignación Inicial' AS tipo,
                       (cv.serie || '-' || cv.secuencial) AS documento, NULL AS estado_doc,
                       cvd.id_producto, p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                       cvd.lote, cvd.nup,
                       cvd.cantidad AS entrada, 0 AS salida, cvd.cantidad AS mov
                FROM consignaciones_ventas_detalles cvd
                INNER JOIN consignaciones_ventas cv ON cv.id = cvd.id_consignacion
                INNER JOIN productos p ON p.id = cvd.id_producto
                WHERE cvd.id_consignacion = :id1 AND cvd.id_empresa = :e1 AND (cvd.eliminado = false OR cvd.eliminado IS NULL)

                UNION ALL

                -- 2. Retornos activos (Emitida)
                SELECT rcd.id_consignacion_detalle, rc.fecha_retorno, 2, rcd.id,
                       'Retorno',
                       (rc.serie || '-' || rc.secuencial), rc.estado,
                       rcd.id_producto, p.nombre, p.codigo,
                       rcd.lote, rcd.nup,
                       0, rcd.cantidad, -rcd.cantidad
                FROM retornos_cv_detalles rcd
                INNER JOIN retornos_cv rc ON rc.id = rcd.id_retorno AND rc.eliminado = false AND rc.estado = 'Emitida'
                INNER JOIN productos p ON p.id = rcd.id_producto
                WHERE rcd.id_consignacion = :id2 AND rcd.id_empresa = :e2 AND rcd.eliminado = false

                UNION ALL

                -- 3. Facturaciones (facturada)
                SELECT cfd.id_consignacion_detalle, cf.fecha_emision, 3, cfd.id,
                       'Facturación',
                       COALESCE(cf.numero_factura, (cf.serie || '-' || cf.secuencial)), cf.estado,
                       cfd.id_producto, p.nombre, p.codigo,
                       cfd.lote, cfd.nup,
                       0, cfd.cantidad, -cfd.cantidad
                FROM consignaciones_facturas_detalles cfd
                INNER JOIN consignaciones_facturas cf ON cf.id = cfd.id_consignacion_factura AND cf.eliminado = false AND cf.estado = 'facturada'
                INNER JOIN productos p ON p.id = cfd.id_producto
                WHERE cfd.id_consignacion = :id3 AND cfd.id_empresa = :e3 AND (cfd.eliminado = false OR cfd.eliminado IS NULL)
            ) t
            ORDER BY t.producto_nombre ASC, t.fecha ASC, t.orden ASC, t.orden_id ASC
        ";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id1' => $idConsignacion, ':e1' => $idEmpresa,
            ':id2' => $idConsignacion, ':e2' => $idEmpresa,
            ':id3' => $idConsignacion, ':e3' => $idEmpresa,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿La consignación proviene de una migración? (tiene fila en migracion_mysql_map). */
    public function esMigrado(int $id, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM migracion_mysql_map
              WHERE entidad = 'consignaciones' AND id_destino = :id AND id_empresa = :e
              LIMIT 1"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
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
