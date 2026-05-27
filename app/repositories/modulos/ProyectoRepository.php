<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class ProyectoRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'codigo', 'nombre', 'estado', 'cliente_nombre', 'presupuesto', 
        'porcentaje_ejecucion', 'fecha_inicio', 'fecha_fin'
    ];

    public function __construct()
    {
        parent::__construct('proyectos');
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
            $ordenCol = 'nombre';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = $this->getBaseWhere($idEmpresa, 'p', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (p.codigo ILIKE :b OR p.nombre ILIKE :b OR p.descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $orderExpr = match($ordenCol) {
            'cliente_nombre' => 'cl.nombre',
            default          => "p.{$ordenCol}"
        };

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} p {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT p.*, cl.nombre AS cliente_nombre 
                    FROM {$this->table} p 
                    LEFT JOIN clientes cl ON cl.id = p.id_cliente
                    {$whereSql} 
                    ORDER BY {$orderExpr} {$dir}";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, codigo, nombre, descripcion, estado,
                    eliminado, created_by, created_at, id_cliente, presupuesto, 
                    fecha_inicio, fecha_fin, porcentaje_ejecucion
                ) VALUES (
                    :id_empresa, :id_usuario, :codigo, :nombre, :descripcion, :estado,
                    :eliminado, :created_by, CURRENT_TIMESTAMP, :id_cliente, :presupuesto,
                    :fecha_inicio, :fecha_fin, :porc_ejec
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $data['id_empresa'],
            ':id_usuario'   => $data['id_usuario'],
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
            ':eliminado'    => 'false',
            ':created_by'   => $data['created_by'],
            ':id_cliente'   => $data['id_cliente'] ?: null,
            ':presupuesto'  => $data['presupuesto'] ?? 0,
            ':fecha_inicio' => $data['fecha_inicio'] ?: null,
            ':fecha_fin'    => $data['fecha_fin'] ?: null,
            ':porc_ejec'    => $data['porcentaje_ejecucion'] ?? 0
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                estado = :estado,
                id_cliente = :id_cliente,
                presupuesto = :presupuesto,
                fecha_inicio = :fecha_inicio,
                fecha_fin = :fecha_fin,
                porcentaje_ejecucion = :porc_ejec,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
            ':id_cliente'   => $data['id_cliente'] ?: null,
            ':presupuesto'  => $data['presupuesto'] ?? 0,
            ':fecha_inicio' => $data['fecha_inicio'] ?: null,
            ':fecha_fin'    => $data['fecha_fin'] ?: null,
            ':porc_ejec'    => $data['porcentaje_ejecucion'] ?? 0,
            ':updated_by'   => $data['updated_by'],
            ':id'           => $id,
            ':id_empresa'   => $idEmpresa
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_by = :id_u,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'         => $id, 
            ':id_empresa' => $idEmpresa,
            ':id_u'       => $idUsuario
        ]);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT p.*, cl.nombre AS cliente_nombre,
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} p
                LEFT JOIN clientes cl ON cl.id = p.id_cliente
                LEFT JOIN usuarios u_crea ON u_crea.id = p.created_by
                LEFT JOIN usuarios u_act ON u_act.id = p.updated_by
                WHERE p.id = :id AND p.id_empresa = :id_empresa AND p.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
