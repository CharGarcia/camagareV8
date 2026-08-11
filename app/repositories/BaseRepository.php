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
     * Condición de rango para Descargas Masivas: por fecha (BETWEEN sobre
     * $colFecha) o por número (BETWEEN sobre $colSecuencial, casteado a
     * entero) — modos EXCLUYENTES, nunca ambos a la vez. El llamador decide
     * el modo pasando uno u otro par de valores; agrega los parámetros
     * necesarios a $params por referencia.
     */
    protected function condicionRangoDescargaMasiva(
        string $prefijo,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $numeroDesde,
        ?int $numeroHasta,
        array &$params,
        string $colFecha = 'fecha_emision',
        string $colSecuencial = 'secuencial'
    ): string {
        if ($numeroDesde !== null && $numeroHasta !== null) {
            $params[':numero_desde'] = $numeroDesde;
            $params[':numero_hasta'] = $numeroHasta;
            return "AND {$prefijo}{$colSecuencial} ~ '^[0-9]+$'
                     AND CAST({$prefijo}{$colSecuencial} AS INTEGER) BETWEEN :numero_desde AND :numero_hasta";
        }
        $params[':desde'] = $fechaDesde;
        $params[':hasta'] = $fechaHasta;
        return "AND {$prefijo}{$colFecha} BETWEEN :desde AND :hasta";
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
