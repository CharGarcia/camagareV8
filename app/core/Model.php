<?php
/**
 * Modelo base (legado / compatibilidad)
 */

declare(strict_types=1);

namespace App\core;

abstract class Model
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
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

    protected function execute(string $sql): bool
    {
        try {
            $this->db->exec($sql);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    protected function lastInsertId(?string $sequence = null): int
    {
        return (int) $this->db->lastInsertId($sequence);
    }

    protected function escape(string $value): string
    {
        $q = $this->db->quote($value);
        if ($q === false) {
            return '';
        }

        return substr($q, 1, -1);
    }
}
