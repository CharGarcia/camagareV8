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

    /**
     * Otro registro ya usa el mismo nombre (trim, comparación sin distinguir mayúsculas).
     */
    public function existeNombreOtro(string $nombre, ?int $excluirId = null): bool
    {
        $nombreTrim = trim($nombre);
        if ($nombreTrim === '') {
            return false;
        }
        $escaped = $this->escape($nombreTrim);
        $queries = [
            "SELECT id_icono AS id FROM iconos_fontawesome WHERE LOWER(TRIM(nombre_icono)) = LOWER('{$escaped}')",
            "SELECT id FROM iconos_fontawesome WHERE LOWER(TRIM(nombre_icono)) = LOWER('{$escaped}')",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $r) {
                $rid = (int) ($r['id'] ?? 0);
                if ($excluirId !== null && $rid === $excluirId) {
                    continue;
                }
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Referencias en modulos_menu + submodulos_menu por id de icono (lote).
     *
     * @param array<int> $ids
     * @return array<int, int> id => total de usos
     */
    public function contarReferenciasPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', $ids);
        $map = array_fill_keys($ids, 0);
        foreach ([
            "SELECT id_icono AS iid, COUNT(*) AS c FROM modulos_menu WHERE id_icono IN ({$in}) GROUP BY id_icono",
            "SELECT id_icono AS iid, COUNT(*) AS c FROM submodulos_menu WHERE id_icono IN ({$in}) GROUP BY id_icono",
        ] as $sql) {
            try {
                foreach ($this->query($sql) as $row) {
                    $iid = (int) ($row['iid'] ?? 0);
                    if ($iid > 0 && isset($map[$iid])) {
                        $map[$iid] += (int) ($row['c'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $map;
    }

    /**
     * Filas en modulos_menu o submodulos_menu que usan este id de icono.
     */
    public function contarReferenciasEnMenus(int $idIcono): int
    {
        $id = (int) $idIcono;
        if ($id <= 0) {
            return 0;
        }
        $total = 0;
        $queries = [
            "SELECT COUNT(*) AS c FROM modulos_menu WHERE id_icono = {$id}",
            "SELECT COUNT(*) AS c FROM submodulos_menu WHERE id_icono = {$id}",
        ];
        foreach ($queries as $sql) {
            try {
                $r = $this->query($sql);
                $total += (int) ($r[0]['c'] ?? 0);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $total;
    }

    /**
     * Elimina el icono si existe. Comprobar contarReferenciasEnMenus antes.
     */
    public function eliminar(int $id): bool
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        foreach ([
            "DELETE FROM iconos_fontawesome WHERE id_icono = {$id} LIMIT 1",
            "DELETE FROM iconos_fontawesome WHERE id = {$id} LIMIT 1",
        ] as $sql) {
            try {
                if (!$this->execute($sql)) {
                    continue;
                }
                if ($this->db->affected_rows > 0) {
                    return true;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
