<?php
/**
 * Modelo SustentoTributario - CRUD de sustento tributario
 * Tabla: sustento_tributario
 * Campos: codigo, nombre, tipo_comprobante, status
 */

declare(strict_types=1);

namespace App\models;

class SustentoTributario extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'tipo_comprobante', 'status'];

    /**
     * Lista todos los sustentos con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'codigo', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'codigo';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%' OR tipo_comprobante LIKE '%{$b}%')";
        }
        $queries = [
            "SELECT id_sustento AS id, codigo, nombre, tipo_comprobante, status FROM sustento_tributario{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, tipo_comprobante, status FROM sustento_tributario{$where} ORDER BY {$col} {$dir}",
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
     * Verifica si ya existe un sustento con el código dado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_sustento != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM sustento_tributario WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM sustento_tributario WHERE codigo = '{$cod}'{$excluirAlt}",
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
     * Verifica si ya existe un sustento con el nombre dado.
     */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $nom = $this->escape(trim($nombre));
        $excluir = $excluirId !== null ? ' AND id_sustento != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM sustento_tributario WHERE nombre = '{$nom}'{$excluir}",
            "SELECT 1 FROM sustento_tributario WHERE nombre = '{$nom}'{$excluirAlt}",
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
     * Elimina un sustento tributario
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM sustento_tributario WHERE id_sustento = {$id}",
            "DELETE FROM sustento_tributario WHERE id = {$id}",
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
     * Crea un sustento tributario
     */
    public function crear(string $codigo, string $nombre, string $tipoComprobante, int $status): int
    {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $tip = $this->escape($tipoComprobante);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO sustento_tributario (codigo, nombre, tipo_comprobante, status) VALUES ('{$cod}', '{$nom}', '{$tip}', {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un sustento tributario
     */
    public function actualizar(int $id, string $codigo, string $nombre, string $tipoComprobante, int $status): bool
    {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $tip = $this->escape($tipoComprobante);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE sustento_tributario SET codigo='{$cod}', nombre='{$nom}', tipo_comprobante='{$tip}', status={$st} WHERE id_sustento={$id}",
            "UPDATE sustento_tributario SET codigo='{$cod}', nombre='{$nom}', tipo_comprobante='{$tip}', status={$st} WHERE id={$id}",
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
