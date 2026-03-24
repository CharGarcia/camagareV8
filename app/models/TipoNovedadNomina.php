<?php
/**
 * Modelo TipoNovedadNomina - CRUD de tipos de novedades para nómina
 * Tabla: tipos_novedades_nomina
 * Campos: codigo, nombre, status (1=activo, 0=inactivo)
 */

declare(strict_types=1);

namespace App\models;

class TipoNovedadNomina extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'status'];

    /**
     * Lista todos los tipos de novedades con orden y búsqueda
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
            "SELECT id_tipos_novedades_nomina AS id, codigo, nombre, status FROM tipos_novedades_nomina{$where} ORDER BY {$col} {$dir}",
            "SELECT id AS id, codigo, nombre, status FROM tipos_novedades_nomina{$where} ORDER BY {$col} {$dir}",
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
     * Verifica si ya existe un tipo con el código dado.
     */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $cod = $this->escape(trim($codigo));
        $excluir = $excluirId !== null ? ' AND id_tipos_novedades_nomina != ' . (int) $excluirId : '';
        $excluirAlt = $excluirId !== null ? ' AND id != ' . (int) $excluirId : '';
        $queries = [
            "SELECT 1 FROM tipos_novedades_nomina WHERE codigo = '{$cod}'{$excluir}",
            "SELECT 1 FROM tipos_novedades_nomina WHERE codigo = '{$cod}'{$excluirAlt}",
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
     * Elimina un tipo de novedad
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        $queries = [
            "DELETE FROM tipos_novedades_nomina WHERE id_tipos_novedades_nomina = {$id}",
            "DELETE FROM tipos_novedades_nomina WHERE id = {$id}",
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
     * Crea un tipo de novedad
     */
    public function crear(string $codigo, string $nombre, int $status): int
    {
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $sql = "INSERT INTO tipos_novedades_nomina (codigo, nombre, status) VALUES ('{$cod}', '{$nom}', {$st})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un tipo de novedad
     */
    public function actualizar(int $id, string $codigo, string $nombre, int $status): bool
    {
        $id = (int) $id;
        $cod = $this->escape($codigo);
        $nom = $this->escape($nombre);
        $st = $status ? 1 : 0;
        $queries = [
            "UPDATE tipos_novedades_nomina SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id_tipos_novedades_nomina={$id}",
            "UPDATE tipos_novedades_nomina SET codigo='{$cod}', nombre='{$nom}', status={$st} WHERE id={$id}",
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
