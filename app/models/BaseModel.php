<?php
/**
 * Modelo base (PostgreSQL / PDO)
 */

declare(strict_types=1);

namespace App\models;

use App\core\Database as Db;

abstract class BaseModel
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Db::getConnection();
    }

    protected function query(string $sql): array
    {
        try {
            $stmt = $this->db->query($sql);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error SQL: ' . $e->getMessage() . ' | ' . $sql, 0, $e);
        }
    }

    /**
     * INSERT/UPDATE/DELETE sin conjunto de resultados. Retorna false si falla.
     */
    protected function execute(string $sql): bool
    {
        try {
            $this->db->exec($sql);

            return true;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Error de ejecución SQL: ' . $e->getMessage() . ' | ' . $sql, 0, $e);
        }
    }

    /**
     * Filas afectadas por INSERT/UPDATE/DELETE; false si error.
     */
    protected function execRowCount(string $sql): int|false
    {
        try {
            return $this->db->exec($sql);
        } catch (\PDOException $e) {
            return false;
        }
    }

    protected function lastInsertId(?string $sequence = null): int
    {
        return (int) $this->db->lastInsertId($sequence);
    }

    /**
     * Contenido escapado para interpolar dentro de literales SQL entre comillas simples.
     */
    protected function escape(string $value): string
    {
        $q = $this->db->quote($value);
        if ($q === false) {
            return '';
        }

        return substr($q, 1, -1);
    }
}
