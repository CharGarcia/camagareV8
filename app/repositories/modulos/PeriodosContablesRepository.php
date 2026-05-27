<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\core\Database;
use PDO;
use Exception;

class PeriodosContablesRepository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $offset = ($page - 1) * $perPage;
        
        $validCols = ['nombre', 'fecha_inicial', 'fecha_final', 'status', 'created_at'];
        if (!in_array($ordenCol, $validCols, true)) {
            $ordenCol = 'fecha_inicial';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $where = "id_empresa = :id_empresa AND eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $where .= " AND (nombre ILIKE :b)";
            $params[':b'] = "%$buscar%";
        }

        $sqlCount = "SELECT COUNT(*) FROM periodos_contables WHERE $where";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT id, nombre, fecha_inicial, fecha_final, status, created_at, updated_at
            FROM periodos_contables
            WHERE $where
            ORDER BY $ordenCol $ordenDir
        ";

        if ($perPage > 0) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        
        if ($perPage > 0) {
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'rows'  => $rows,
            'total' => $total
        ];
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO periodos_contables 
            (id_empresa, id_usuario, nombre, fecha_inicial, fecha_final, status, created_by, created_at)
            VALUES 
            (:id_empresa, :id_usuario, :nombre, :fecha_inicial, :fecha_final, :status, :created_by, CURRENT_TIMESTAMP)
            RETURNING id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa'    => $data['id_empresa'],
            ':id_usuario'    => $data['id_usuario'] ?? $data['created_by'],
            ':nombre'        => $data['nombre'],
            ':fecha_inicial' => $data['fecha_inicial'],
            ':fecha_final'   => $data['fecha_final'],
            ':status'        => $data['status'] ? 1 : 0,
            ':created_by'    => $data['created_by']
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, int $idEmpresa, array $data): void
    {
        $sql = "
            UPDATE periodos_contables 
            SET nombre = :nombre,
                fecha_inicial = :fecha_inicial,
                fecha_final = :fecha_final,
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre'        => $data['nombre'],
            ':fecha_inicial' => $data['fecha_inicial'],
            ':fecha_final'   => $data['fecha_final'],
            ':status'        => $data['status'] ? 1 : 0,
            ':updated_by'    => $data['updated_by'],
            ':id'            => $id,
            ':id_empresa'    => $idEmpresa
        ]);
    }

    public function findById(int $id, int $idEmpresa): ?array
    {
        $sql = "
            SELECT * FROM periodos_contables 
            WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "
            SELECT pc.*, 
                   uc.nombre as creado_por_nombre,
                   uu.nombre as actualizado_por_nombre
            FROM periodos_contables pc
            LEFT JOIN usuarios uc ON pc.created_by = uc.id
            LEFT JOIN usuarios uu ON pc.updated_by = uu.id
            WHERE pc.id = :id AND pc.id_empresa = :id_empresa AND pc.eliminado = false
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "
            UPDATE periodos_contables 
            SET eliminado = true, 
                deleted_at = CURRENT_TIMESTAMP, 
                deleted_by = :deleted_by
            WHERE id = :id AND id_empresa = :id_empresa
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':deleted_by' => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    /**
     * Valida si una fecha específica cae dentro de un periodo cerrado.
     */
    public function isFechaEnPeriodoCerrado(string $fecha, int $idEmpresa): bool
    {
        $sql = "
            SELECT id FROM periodos_contables
            WHERE id_empresa = :id_empresa 
              AND eliminado = false
              AND status = 0 -- 0 = Cerrado
              AND :fecha >= fecha_inicial 
              AND :fecha <= fecha_final
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':fecha'      => $fecha
        ]);
        
        return (bool) $stmt->fetch();
    }
}
