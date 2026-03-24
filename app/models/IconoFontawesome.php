<?php
/**
 * Modelo IconoFontawesome - CRUD de iconos FontAwesome
 * Tabla: iconos_fontawesome
 * Campos: id_icono/id, nombre_icono
 */

declare(strict_types=1);

namespace App\models;

class IconoFontawesome extends BaseModel
{
    /** Columnas ordenables */
    public const COLUMNAS_ORDEN = ['nombre_icono'];

    /**
     * Lista todos los iconos con orden y búsqueda
     */
    public function getAll(string $ordenCol = 'nombre_icono', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'nombre_icono';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE nombre_icono LIKE '%{$b}%'";
        }
        $queries = [
            "SELECT id_icono AS id, nombre_icono FROM iconos_fontawesome{$where} ORDER BY {$col} {$dir}",
            "SELECT id, nombre_icono FROM iconos_fontawesome{$where} ORDER BY {$col} {$dir}",
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
     * Crea un icono
     */
    public function crear(string $nombreIcono): int
    {
        $nombreIcono = $this->escape($nombreIcono);
        $sql = "INSERT INTO iconos_fontawesome (nombre_icono) VALUES ('{$nombreIcono}')";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un icono
     */
    public function actualizar(int $id, string $nombreIcono): bool
    {
        $id = (int) $id;
        $nombreIcono = $this->escape($nombreIcono);
        $queries = [
            "UPDATE iconos_fontawesome SET nombre_icono='{$nombreIcono}' WHERE id_icono={$id}",
            "UPDATE iconos_fontawesome SET nombre_icono='{$nombreIcono}' WHERE id={$id}",
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
