<?php
/**
 * Modelo base
 */

declare(strict_types=1);

namespace App\core;

abstract class Model
{
    protected \mysqli $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    protected function query(string $sql): array
    {
        $result = $this->db->query($sql);
        if ($result === false) {
            throw new \RuntimeException('Error SQL: ' . $this->db->error . ' | ' . $sql);
        }
        return $result->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    protected function execute(string $sql): bool
    {
        return $this->db->query($sql) !== false;
    }

    protected function lastInsertId(): int
    {
        return (int) $this->db->insert_id;
    }

    protected function escape(string $value): string
    {
        return $this->db->real_escape_string($value);
    }
}
