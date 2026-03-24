<?php
/**
 * Modelo PermisoSubmodulo - Permisos CRUD usando modulos_asignados
 *
 * modulos_asignados: id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d
 * r=ver, w=crear, u=actualizar, d=eliminar (1=con permiso, 0=sin permiso)
 */

declare(strict_types=1);

namespace App\models;

class PermisoSubmodulo extends BaseModel
{
    /**
     * Módulos con submódulos según nivel del actual:
     * Super admin: asignados (modulos_asignados) + todos de submodulos_menu.
     * Admin/usuario: solo modulos_asignados (relacionados con submodulos_menu).
     */
    public function getModulosConSubmodulosParaPermisos(int $idUsuarioActual, int $idEmpresaActual, int $nivel): array
    {
        $idU = (int) $idUsuarioActual;
        $idE = (int) $idEmpresaActual;

        if ($nivel >= 3) {
            return $this->getModulosSuperAdmin($idU, $idE);
        }
        return $this->getModulosAdminUsuario($idU, $idE);
    }

    /** Super admin: UNION de asignados + todos de submodulos_menu */
    private function getModulosSuperAdmin(int $idU, int $idE): array
    {
        $queries = [
            // Schema legacy: id_modulo, id_submodulo
            "SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id_modulo = ma.id_modulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
            UNION
            SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_menu mm
                INNER JOIN submodulos_menu sm ON sm.id_modulo = mm.id_modulo AND COALESCE(sm.status, 1) = 1
                ORDER BY nombre_modulo, nombre_submodulo",
            // Schema alternativo: id como PK
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id = ma.id_modulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
            UNION
            SELECT mm.id AS id_modulo, mm.nombre_modulo, sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_menu mm
                INNER JOIN submodulos_menu sm ON sm.id_modulo = mm.id AND COALESCE(sm.status, 1) = 1
                ORDER BY nombre_modulo, nombre_submodulo",
            // Solo todos de submodulos_menu (fallback si UNION falla)
            "SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_menu mm
                INNER JOIN submodulos_menu sm ON sm.id_modulo = mm.id_modulo AND COALESCE(sm.status, 1) = 1
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_menu mm
                INNER JOIN submodulos_menu sm ON sm.id_modulo = mm.id AND COALESCE(sm.status, 1) = 1
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                if (!empty($rows)) return $this->normalizarFilasPermisos($rows);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /** Admin/usuario: solo modulos_asignados JOIN submodulos_menu */
    private function getModulosAdminUsuario(int $idU, int $idE): array
    {
        $queries = [
            "SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id_modulo = ma.id_modulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
            "SELECT mm.id AS id_modulo, mm.nombre_modulo, sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_modulo AS sm_id_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id = ma.id_modulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                return $this->normalizarFilasPermisos($rows);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /** Asegura que las filas tengan id_modulo e id_submodulo unificados */
    private function normalizarFilasPermisos(array $rows): array
    {
        $out = [];
        $vistos = [];
        foreach ($rows as $r) {
            $idMod = (int)($r['id_modulo'] ?? $r['sm_id_modulo'] ?? 0);
            $idSub = (int)($r['id_submodulo'] ?? 0);
            $key = "{$idMod}_{$idSub}";
            if (isset($vistos[$key])) continue;
            $vistos[$key] = true;
            $out[] = [
                'id_modulo' => $idMod,
                'nombre_modulo' => $r['nombre_modulo'] ?? '',
                'id_submodulo' => $idSub,
                'nombre_submodulo' => $r['nombre_submodulo'] ?? '',
                'ruta' => $r['ruta'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * Permisos actuales de un usuario en una empresa (desde modulos_asignados)
     * id_submodulo => [ver, crear, actualizar, eliminar]
     */
    public function getPermisosDeUsuario(int $idUsuario, int $idEmpresa): array
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $rows = $this->query("SELECT id_submodulo, COALESCE(r,0) AS r, COALESCE(w,0) AS w, COALESCE(u,0) AS u, COALESCE(d,0) AS d
            FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE}");
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['id_submodulo']] = [
                'ver' => (int)($r['r'] ?? 0),
                'crear' => (int)($r['w'] ?? 0),
                'actualizar' => (int)($r['u'] ?? 0),
                'eliminar' => (int)($r['d'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Guardar permisos en modulos_asignados.
     * Solo se guardan filas con al menos un permiso (ver, crear, actualizar, eliminar).
     * Se eliminan las asignaciones que quedaron sin ningún permiso.
     */
    public function guardarPermisos(int $idUsuario, int $idEmpresa, array $permisos, array $idModuloPorSub): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $this->db->begin_transaction();
        try {
            $idsAGuardar = [];
            foreach ($permisos as $idSub => $p) {
                $idSub = (int) $idSub;
                if ($idSub <= 0) continue;
                $idMod = (int)($idModuloPorSub[$idSub] ?? 0);
                if ($idMod <= 0) continue;

                $r = isset($p['ver']) && $p['ver'] ? 1 : 0;
                $w = isset($p['crear']) && $p['crear'] ? 1 : 0;
                $u = isset($p['actualizar']) && $p['actualizar'] ? 1 : 0;
                $d = isset($p['eliminar']) && $p['eliminar'] ? 1 : 0;

                $existe = $this->query("SELECT 1 FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                if (!empty($existe)) {
                    $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}
                        WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                } else {
                    $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d)
                        VALUES ({$idU}, {$idE}, {$idMod}, {$idSub}, {$r}, {$w}, {$u}, {$d})");
                }
                $idsAGuardar[] = $idSub;
            }

            // Eliminar asignaciones que ya no tienen ningún permiso (filas no incluidas en $permisos)
            if (!empty($idsAGuardar)) {
                $idsStr = implode(',', array_map('intval', $idsAGuardar));
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo NOT IN ({$idsStr})");
            } else {
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE}");
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }
}
