<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class MarcaRepository extends BaseRepository
{
    protected string $table = 'marcas';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Devuelve el listado paginado y con búsqueda para Marcas.
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

        // Validación de columnas permitidas para order by
        $cols = ['nombre' => 'm.nombre', 'status' => 'm.status'];
        $col  = $cols[$ordenCol] ?? 'm.nombre';
        $dir  = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} m {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT m.id, m.nombre, m.status
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

    /**
     * Verifica si existe otra marca con el mismo nombre para la misma empresa
     */
    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND UPPER(nombre) = UPPER(:nombre) 
                  AND eliminado = false";
        $params = [
            ':id_empresa' => $idEmpresa,
            ':nombre'     => $nombre
        ];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Obtiene el detalle de una marca incluyendo nombres de auditoría.
     */
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

    /**
     * Cuenta cuántos productos activos tiene asignada la marca.
     */
    public function contarProductosAsignados(int $id, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM productos 
                WHERE id_marca = :id 
                  AND id_empresa = :id_empresa 
                  AND status = 1"; // en productos quizas tienen eliminado o status
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    /**
     * Crea una nueva marca.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, nombre, status, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :nombre, :status, :eliminado, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_usuario' => $data['id_usuario'],
            ':created_by' => $data['created_by'],
            ':nombre'     => $data['nombre'],
            ':status'     => $data['status'],
            ':eliminado'  => $data['eliminado'] ? 'true' : 'false'
        ]);
        return $this->lastInsertId();
    }

    /**
     * Actualiza una marca existente.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                nombre = :nombre,
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'     => $data['nombre'],
            ':status'     => $data['status'],
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica con campos de auditoría.
     */
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
