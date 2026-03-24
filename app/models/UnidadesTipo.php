<?php
/**
 * Modelo UnidadesTipo - Tipos de unidades de medida
 * Tabla: unidades_tipo_modelo
 */

declare(strict_types=1);

namespace App\models;

class UnidadesTipo extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'estado'];

    public function getAll(string $ordenCol = 'nombre', string $ordenDir = 'ASC', string $buscar = ''): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'nombre';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = " WHERE (codigo LIKE '%{$b}%' OR nombre LIKE '%{$b}%' OR descripcion LIKE '%{$b}%')";
        }
        $sql = "SELECT id, codigo, nombre, descripcion, estado FROM unidades_tipo_modelo{$where} ORDER BY {$col} {$dir}";
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getActivos(): array
    {
        try {
            return $this->query("SELECT id, codigo, nombre FROM unidades_tipo_modelo WHERE estado = 1 ORDER BY nombre ASC");
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        $id = (int) $id;
        $rows = $this->query("SELECT id, codigo, nombre, descripcion, estado FROM unidades_tipo_modelo WHERE id = {$id}");
        return $rows[0] ?? null;
    }

    public function crear(string $codigo, string $nombre, string $descripcion, int $estado): int
    {
        $codigo = $this->escape(trim($codigo));
        $nombre = $this->escape(trim($nombre));
        $descripcion = $this->escape(trim($descripcion));
        $estado = $estado ? 1 : 0;
        $this->execute("INSERT INTO unidades_tipo_modelo (codigo, nombre, descripcion, estado) VALUES ('{$codigo}', '{$nombre}', '{$descripcion}', {$estado})");
        return $this->lastInsertId();
    }

    public function actualizar(int $id, string $codigo, string $nombre, string $descripcion, int $estado): bool
    {
        $id = (int) $id;
        $codigo = $this->escape(trim($codigo));
        $nombre = $this->escape(trim($nombre));
        $descripcion = $this->escape(trim($descripcion));
        $estado = $estado ? 1 : 0;
        return $this->execute("UPDATE unidades_tipo_modelo SET codigo='{$codigo}', nombre='{$nombre}', descripcion='{$descripcion}', estado={$estado} WHERE id={$id}");
    }
}
