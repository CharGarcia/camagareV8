<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class CentroCostoRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'estado'];

    public function __construct()
    {
        parent::__construct('centro_costos');
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

        $whereSql = $this->getBaseWhere($idEmpresa, 'cc', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (cc.codigo ILIKE :b OR cc.nombre ILIKE :b OR cc.descripcion ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} cc {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT cc.* FROM {$this->table} cc {$whereSql} ORDER BY cc.{$ordenCol} {$dir}";
                    
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
                    eliminado, created_by, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :codigo, :nombre, :descripcion, :estado,
                    :eliminado, :created_by, CURRENT_TIMESTAMP
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
            ':created_by'   => $data['created_by']
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
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':codigo'       => $data['codigo'] ?? null,
            ':nombre'       => $data['nombre'],
            ':descripcion'  => $data['descripcion'] ?? null,
            ':estado'       => $data['estado'] ?? 'activo',
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
        $sql = "SELECT cc.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} cc
                LEFT JOIN usuarios u_crea ON u_crea.id = cc.created_by
                LEFT JOIN usuarios u_act ON u_act.id = cc.updated_by
                WHERE cc.id = :id AND cc.id_empresa = :id_empresa AND cc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
