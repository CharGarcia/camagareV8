<?php
declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

abstract class BaseRepository
{
    protected PDO $db;
    protected string $table;

    public function __construct(string $table)
    {
        $this->db = Database::getConnection();
        $this->table = $table;
    }

    /**
     * Inicia una transacción.
     */
    public function beginTransaction(): bool
    {
        if (!$this->db->inTransaction()) {
            return $this->db->beginTransaction();
        }
        return true;
    }

    /**
     * Confirma una transacción.
     */
    public function commit(): bool
    {
        if ($this->db->inTransaction()) {
            return $this->db->commit();
        }
        return true;
    }

    /**
     * Revierte una transacción.
     */
    public function rollBack(): bool
    {
        if ($this->db->inTransaction()) {
            return $this->db->rollBack();
        }
        return true;
    }

    /**
     * Obtiene el último ID insertado.
     */
    public function lastInsertId(?string $sequence = null): int
    {
        return (int) $this->db->lastInsertId($sequence);
    }

    /**
     * Aplica filtro de id_empresa y eliminado = false por defecto.
     */
    protected function getBaseWhere(int $idEmpresa, string $alias = '', ?int $idUsuarioFiltro = null, string $userColumn = 'created_by'): string
    {
        $prefix = $alias !== '' ? "{$alias}." : '';
        $where = "WHERE {$prefix}id_empresa = :id_empresa AND {$prefix}eliminado = false";
        if ($idUsuarioFiltro !== null) {
            $where .= " AND {$prefix}{$userColumn} = :id_usuario_filtro";
        }
        return $where;
    }

    /**
     * Expone la conexión PDO para uso directo en Services cuando sea necesario.
     */
    public function getDb(): \PDO
    {
        return $this->db;
    }

    /**
     * Método genérico para obtener un registro por ID y empresa.
     */
    public function findById(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
