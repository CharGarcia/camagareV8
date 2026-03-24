<?php
/**
 * Modelo ComprobanteAutorizado - CRUD de comprobantes autorizados
 * Tabla: comprobantes_autorizados
 * Campos: id_comprobante, codigo_comprobante, comprobante, status
 */

declare(strict_types=1);

namespace App\models;

class ComprobanteAutorizado extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo_comprobante', 'comprobante', 'status'];

    /**
     * Lista todos los comprobantes con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo_comprobante', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo_comprobante';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo_comprobante LIKE '%{$b}%' OR comprobante LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_comprobante AS id, codigo_comprobante, comprobante, status FROM comprobantes_autorizados{$where} ORDER BY {$col} {$dir}",
            "SELECT id, codigo_comprobante, comprobante, status FROM comprobantes_autorizados{$where} ORDER BY {$col} {$dir}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /**
     * Verifica si ya existe un comprobante con el código dado.
     * @param string $codigo Código a verificar
     * @param int|null $excluirId ID a excluir (para update; null en create)
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_comprobante != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM comprobantes_autorizados WHERE codigo_comprobante = '{$cod}'{$excluir}",
        ];
        foreach ($queries as $sql) {
            try {
                return !empty($this->query($sql));
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Elimina un comprobante autorizado
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM comprobantes_autorizados WHERE id_comprobante = {$id}",
            "DELETE FROM comprobantes_autorizados WHERE id = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }

    /**
     * Crea un comprobante autorizado
     */
    public function crear(string $codigoComprobante, string $comprobante, int $status): int
    {
        $codigo = $this->escape($codigoComprobante);
        $comp = $this->escape($comprobante);
        $status = $status ? 1 : 0;
        $sql = "INSERT INTO comprobantes_autorizados (codigo_comprobante, comprobante, status) VALUES ('{$codigo}', '{$comp}', {$status})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un comprobante autorizado
     */
    public function actualizar(int $id, string $codigoComprobante, string $comprobante, int $status): bool
    {
        $id = (int) $id;
        $codigo = $this->escape($codigoComprobante);
        $comp = $this->escape($comprobante);
        $status = $status ? 1 : 0;
        $queries = [
            "UPDATE comprobantes_autorizados SET codigo_comprobante='{$codigo}', comprobante='{$comp}', status={$status} WHERE id_comprobante={$id}",
            "UPDATE comprobantes_autorizados SET codigo_comprobante='{$codigo}', comprobante='{$comp}', status={$status} WHERE id={$id}",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->execute($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
