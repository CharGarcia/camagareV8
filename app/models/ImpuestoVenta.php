<?php
/**
 * Modelo ImpuestoVenta - CRUD de impuestos de ventas
 * Tabla: impuestos_ventas
 * Campos: codigo, nombre, status
 */

declare(strict_types=1);

namespace App\models;

class ImpuestoVenta extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'status'];

    /**
     * Lista todos los impuestos de ventas con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_impuesto_venta AS id, codigo, nombre, status FROM impuestos_ventas{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, status FROM impuestos_ventas{$where} ORDER BY {$col} {$dir}",
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
     * Verifica si ya existe un impuesto de venta con el código dado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_impuesto_venta != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM impuestos_ventas WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM impuestos_ventas WHERE codigo = '{$cod}'{$excluirAlt}",
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
     * Elimina un impuesto de venta
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM impuestos_ventas WHERE id_impuesto_venta = {$id}",
            "DELETE FROM impuestos_ventas WHERE id = {$id}",
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
     * Crea un impuesto de venta
     */
    public function crear(string $codigo, string $nombre, int $status): int
    {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO impuestos_ventas (codigo, nombre, status) VALUES ('{$cod}', '{$nom}', {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un impuesto de venta
     */
    public function actualizar(int $id, string $codigo, string $nombre, int $status): bool
    {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE impuestos_ventas SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id_impuesto_venta={$id}",
            "UPDATE impuestos_ventas SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id={$id}",
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
