<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class MesaRepository extends BaseRepository
{
    protected string $table = 'mesas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Devuelve el listado paginado y con búsqueda para Mesas.
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'm', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        if ($buscar !== '') {
            $whereSql .= " AND (m.nombre ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        $cols = [
            'id'     => 'm.id',
            'nombre' => 'm.nombre',
            'estado' => 'm.estado'
        ];
        $col  = $cols[$ordenCol] ?? 'm.id';
        $dir  = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        $sqlCount = "SELECT COUNT(*) FROM {$this->table} m {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT m.id, m.nombre, m.estado, m.created_at
                    FROM {$this->table} m
                    {$whereSql}
                    ORDER BY {$col} {$dir}";
                    
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

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND UPPER(nombre) = UPPER(:nombre) 
                  AND eliminado = false";
        $params = [
            ':id_empresa' => $idEmpresa,
            ':nombre'      => $nombre
        ];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT m.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} m
                LEFT JOIN usuarios u_crea ON u_crea.id = m.created_by
                LEFT JOIN usuarios u_act ON u_act.id = m.updated_by
                WHERE m.id = :id AND m.id_empresa = :id_empresa AND m.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, nombre, estado, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :nombre, :estado, :eliminado, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_usuario' => $data['id_usuario'],
            ':created_by' => $data['created_by'],
            ':nombre'      => $data['nombre'],
            ':estado'      => $data['estado'] ?? 'disponible',
            ':eliminado'   => $data['eliminado'] ? 'true' : 'false'
        ]);
        return (int) $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                nombre = :nombre,
                estado = :estado,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'      => $data['nombre'],
            ':estado'      => $data['estado'],
            ':updated_by'  => $data['updated_by'],
            ':id'          => $id,
            ':id_empresa'  => $idEmpresa
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
}
