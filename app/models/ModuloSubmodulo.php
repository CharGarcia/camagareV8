<?php
/**
 * Modelo ModuloSubmodulo - CRUD de módulos, submodulos e iconos
 *
 * Tablas (PK = id):
 * - modulos_menu: id, nombre_modulo, id_icono, orden
 * - submodulos_menu: id, nombre_submodulo, ruta, id_modulo, id_icono, orden, status
 * - iconos_fontawesome: id, nombre_icono
 */

declare(strict_types=1);

namespace App\models;

class ModuloSubmodulo extends BaseModel
{
    /**
     * Lista de módulos con su icono (para select)
     */
    public function getModulos(): array
    {
        $queries = [
            "SELECT mm.id_modulo, mm.nombre_modulo, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id_icono = mm.id_icono ORDER BY mm.nombre_modulo",
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id = mm.id_icono ORDER BY mm.nombre_modulo",
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
     * Lista de módulos con paginación y búsqueda.
     * Usa LEFT JOIN para incluir todos los módulos aunque no tengan icono asignado.
     */
    public function getModulosListado(string $buscar = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $where = '1=1';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND mm.nombre_modulo LIKE '%{$b}%'";
        }
        $orderBy = "ORDER BY mm.nombre_modulo ASC";

        $countSql = "SELECT COUNT(*) AS total FROM modulos_menu mm WHERE {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);

        $rows = [];
        $queries = [
            "SELECT mm.id_modulo, mm.nombre_modulo, mm.id_icono, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id_icono = mm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT mm.id_modulo, mm.nombre_modulo, mm.id_icono, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id = mm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, mm.id_icono, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id_icono = mm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, mm.id_icono, ico.nombre_icono FROM modulos_menu mm LEFT JOIN iconos_fontawesome ico ON ico.id = mm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Lista de submodulos con paginación y búsqueda.
     * Usa LEFT JOIN para incluir todos los submódulos aunque no tengan icono asignado.
     */
    public function getSubmodulosListado(string $buscar = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $where = '1=1';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (sm.nombre_submodulo LIKE '%{$b}%' OR mm.nombre_modulo LIKE '%{$b}%')";
        }
        $orderBy = "ORDER BY mm.nombre_modulo, sm.nombre_submodulo ASC";

        $countSql = "SELECT COUNT(*) AS total FROM submodulos_menu sm
            INNER JOIN modulos_menu mm ON mm.id = sm.id_modulo
            WHERE {$where}";
        try {
            $total = (int) ($this->query($countSql)[0]['total'] ?? 0);
        } catch (\Throwable $e) {
            $countSql = "SELECT COUNT(*) AS total FROM submodulos_menu sm
                INNER JOIN modulos_menu mm ON mm.id_modulo = sm.id_modulo
                WHERE {$where}";
            $total = (int) ($this->query($countSql)[0]['total'] ?? 0);
        }

        $queries = [
            "SELECT sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo, sm.id_icono, sm.status, ico.nombre_icono, mm.nombre_modulo AS nombre_modulo FROM submodulos_menu sm INNER JOIN modulos_menu mm ON mm.id = sm.id_modulo LEFT JOIN iconos_fontawesome ico ON ico.id_icono = sm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo, sm.id_icono, sm.status, ico.nombre_icono, mm.nombre_modulo AS nombre_modulo FROM submodulos_menu sm INNER JOIN modulos_menu mm ON mm.id = sm.id_modulo LEFT JOIN iconos_fontawesome ico ON ico.id = sm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo, sm.id_icono, sm.status, ico.nombre_icono, mm.nombre_modulo AS nombre_modulo FROM submodulos_menu sm INNER JOIN modulos_menu mm ON mm.id_modulo = sm.id_modulo LEFT JOIN iconos_fontawesome ico ON ico.id_icono = sm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
            "SELECT sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo, sm.id_icono, sm.status, ico.nombre_icono, mm.nombre_modulo AS nombre_modulo FROM submodulos_menu sm INNER JOIN modulos_menu mm ON mm.id_modulo = sm.id_modulo LEFT JOIN iconos_fontawesome ico ON ico.id = sm.id_icono WHERE {$where} {$orderBy} LIMIT {$offset}, {$perPage}",
        ];
        $rows = [];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Lista de iconos (iconos_fontawesome)
     */
    public function getIconos(): array
    {
        foreach (['id_icono AS id', 'id'] as $idCol) {
            try {
                return $this->query("SELECT {$idCol}, nombre_icono FROM iconos_fontawesome ORDER BY nombre_icono");
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /**
     * Crear módulo
     */
    public function crearModulo(string $nombre, int $idIcono): int
    {
        $nombre = $this->escape($nombre);
        $idIcono = (int) $idIcono;
        $sql = "INSERT INTO modulos_menu (nombre_modulo, id_icono) VALUES ('{$nombre}', {$idIcono})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualizar módulo
     */
    public function actualizarModulo(int $idModulo, string $nombre, int $idIcono): bool
    {
        $nombre = $this->escape($nombre);
        $idModulo = (int) $idModulo;
        $idIcono = (int) $idIcono;
        $sql = "UPDATE modulos_menu SET nombre_modulo='{$nombre}', id_icono={$idIcono} WHERE id={$idModulo}";
        return $this->execute($sql);
    }

    /**
     * Crear submodulo
     */
    public function crearSubmodulo(int $idModulo, string $nombre, string $ruta, int $idIcono): int
    {
        $nombre = $this->escape($nombre);
        $ruta = $this->escape($ruta);
        $idModulo = (int) $idModulo;
        $idIcono = (int) $idIcono;
        $sql = "INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, id_icono) VALUES ('{$nombre}', '{$ruta}', {$idModulo}, {$idIcono})";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualizar submodulo
     */
    public function actualizarSubmodulo(int $idSubmodulo, int $idModulo, string $nombre, string $ruta, int $idIcono): bool
    {
        $nombre = $this->escape($nombre);
        $ruta = $this->escape($ruta);
        $idSubmodulo = (int) $idSubmodulo;
        $idModulo = (int) $idModulo;
        $idIcono = (int) $idIcono;
        $sql = "UPDATE submodulos_menu SET nombre_submodulo='{$nombre}', ruta='{$ruta}', id_modulo={$idModulo}, id_icono={$idIcono} WHERE id={$idSubmodulo}";
        if (!$this->execute($sql)) return false;
        $sql2 = "UPDATE modulos_asignados SET id_modulo={$idModulo} WHERE id_submodulo={$idSubmodulo}";
        $this->execute($sql2);
        return true;
    }

    /**
     * Eliminar módulo (submodulos, asignaciones y módulo)
     */
    public function eliminarModulo(int $idModulo): bool
    {
        $idModulo = (int) $idModulo;
        $subs = $this->query("SELECT id FROM submodulos_menu WHERE id_modulo={$idModulo}");
        foreach ($subs as $s) {
            $this->execute("DELETE FROM modulos_asignados WHERE id_submodulo=" . (int)$s['id']);
        }
        $this->execute("DELETE FROM submodulos_menu WHERE id_modulo={$idModulo}");
        $this->execute("DELETE FROM modulos_asignados WHERE id_modulo={$idModulo}");
        return $this->execute("DELETE FROM modulos_menu WHERE id={$idModulo}");
    }

    /**
     * Eliminar submodulo (y sus asignaciones)
     */
    public function eliminarSubmodulo(int $idSubmodulo): bool
    {
        $idSubmodulo = (int) $idSubmodulo;
        $this->execute("DELETE FROM modulos_asignados WHERE id_submodulo={$idSubmodulo}");
        return $this->execute("DELETE FROM submodulos_menu WHERE id={$idSubmodulo}");
    }

    /**
     * Cambiar estado activo/inactivo de submódulo (status: 1=activo, 0=inactivo)
     */
    public function toggleStatusSubmodulo(int $idSubmodulo): bool
    {
        $idSubmodulo = (int) $idSubmodulo;
        $r = $this->query("SELECT status FROM submodulos_menu WHERE id={$idSubmodulo}");
        if (empty($r)) return false;
        $nuevo = ($r[0]['status'] ?? 1) == 1 ? 0 : 1;
        return $this->execute("UPDATE submodulos_menu SET status={$nuevo} WHERE id={$idSubmodulo}");
    }

    /**
     * Verificar si existe módulo con ese nombre (excluyendo id)
     */
    public function existeModuloNombre(string $nombre, ?int $excluirId = null): bool
    {
        $nombre = $this->escape($nombre);
        $sql = "SELECT 1 FROM modulos_menu WHERE nombre_modulo='{$nombre}'";
        if ($excluirId !== null) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }

    /**
     * Verificar si existe submodulo con ese nombre (excluyendo id)
     */
    public function existeSubmoduloNombre(string $nombre, ?int $excluirId = null): bool
    {
        $nombre = $this->escape($nombre);
        $sql = "SELECT 1 FROM submodulos_menu WHERE nombre_submodulo='{$nombre}'";
        if ($excluirId !== null) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }

    /**
     * Lista de iconos con paginación
     */
    public function getIconosListado(string $buscar = '', int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        $where = '';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where = "WHERE nombre_icono LIKE '%{$b}%'";
        }
        $countSql = "SELECT COUNT(*) AS total FROM iconos_fontawesome {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);
        $rows = [];
        foreach (['id_icono AS id', 'id'] as $idCol) {
            try {
                $rows = $this->query("SELECT {$idCol}, nombre_icono FROM iconos_fontawesome {$where} ORDER BY nombre_icono LIMIT {$offset}, {$perPage}");
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Crear icono
     */
    public function crearIcono(string $nombreIcono): int
    {
        $nombreIcono = $this->escape($nombreIcono);
        $sql = "INSERT INTO iconos_fontawesome (nombre_icono) VALUES ('{$nombreIcono}')";
        $this->execute($sql);
        return $this->lastInsertId();
    }

    /**
     * Actualizar icono
     */
    public function actualizarIcono(int $id, string $nombreIcono): bool
    {
        $nombreIcono = $this->escape($nombreIcono);
        $id = (int) $id;
        $sql = "UPDATE iconos_fontawesome SET nombre_icono='{$nombreIcono}' WHERE id={$id}";
        return $this->execute($sql);
    }

    /**
     * Verificar si existe icono con ese nombre
     */
    public function existeIconoNombre(string $nombre, ?int $excluirId = null): bool
    {
        $nombre = $this->escape($nombre);
        $sql = "SELECT 1 FROM iconos_fontawesome WHERE nombre_icono='{$nombre}'";
        if ($excluirId !== null) {
            $sql .= " AND id != " . (int) $excluirId;
        }
        $r = $this->query($sql);
        return !empty($r);
    }
}
