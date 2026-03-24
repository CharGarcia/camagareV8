<?php
/**
 * Modelo UnidadMedida - Unidades de medida
 * Tabla: unidades_medida_modelo
 * FK: id_tipo -> unidades_tipo_modelo(id)
 */

declare(strict_types=1);

namespace App\models;

class UnidadMedida extends BaseModel
{
    public const COLUMNAS_ORDEN = ['codigo', 'nombre', 'abreviatura', 'id_tipo', 'estado'];

    public function getAll(string $ordenCol = 'nombre', string $ordenDir = 'ASC', string $buscar = '', ?int $idTipo = null): array
    {
        $col = in_array($ordenCol, self::COLUMNAS_ORDEN, true) ? $ordenCol : 'nombre';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = [];
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where[] = "(u.codigo LIKE '%{$b}%' OR u.nombre LIKE '%{$b}%' OR u.abreviatura LIKE '%{$b}%' OR t.nombre LIKE '%{$b}%')";
        }
        if ($idTipo !== null && $idTipo > 0) {
            $idTipo = (int) $idTipo;
            $where[] = "u.id_tipo = {$idTipo}";
        }
        $whereClause = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

        $sql = "SELECT u.id, u.id_tipo, u.codigo, u.nombre, u.abreviatura, u.es_base, u.factor_base, u.estado, t.nombre AS tipo_nombre
                FROM unidades_medida_modelo u
                LEFT JOIN unidades_tipo_modelo t ON t.id = u.id_tipo
                {$whereClause}
                ORDER BY u.{$col} {$dir}";
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        $id = (int) $id;
        $rows = $this->query("SELECT id, id_tipo, codigo, nombre, abreviatura, es_base, factor_base, estado FROM unidades_medida_modelo WHERE id = {$id}");
        return $rows[0] ?? null;
    }

    public function crear(int $idTipo, string $codigo, string $nombre, string $abreviatura, int $esBase, float $factorBase, int $estado): int
    {
        $idTipo = (int) $idTipo;
        $codigo = $this->escape(trim($codigo));
        $nombre = $this->escape(trim($nombre));
        $abreviatura = $this->escape(trim($abreviatura));
        $esBase = $esBase ? 1 : 0;
        $factorBase = (float) $factorBase;
        $estado = $estado ? 1 : 0;
        $this->execute("INSERT INTO unidades_medida_modelo (id_tipo, codigo, nombre, abreviatura, es_base, factor_base, estado) VALUES ({$idTipo}, '{$codigo}', '{$nombre}', '{$abreviatura}', {$esBase}, {$factorBase}, {$estado})");
        return $this->lastInsertId();
    }

    public function actualizar(int $id, int $idTipo, string $codigo, string $nombre, string $abreviatura, int $esBase, float $factorBase, int $estado): bool
    {
        $id = (int) $id;
        $idTipo = (int) $idTipo;
        $codigo = $this->escape(trim($codigo));
        $nombre = $this->escape(trim($nombre));
        $abreviatura = $this->escape(trim($abreviatura));
        $esBase = $esBase ? 1 : 0;
        $factorBase = (float) $factorBase;
        $estado = $estado ? 1 : 0;
        return $this->execute("UPDATE unidades_medida_modelo SET id_tipo={$idTipo}, codigo='{$codigo}', nombre='{$nombre}', abreviatura='{$abreviatura}', es_base={$esBase}, factor_base={$factorBase}, estado={$estado} WHERE id={$id}");
    }
}
