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
     * Invalida la caché del aviso "submódulo nuevo" tras cualquier escritura en
     * modulos_asignados. Nunca debe romper el guardado si la caché falla.
     */
    private function invalidarAvisoNuevo(int $idUsuario, int $idEmpresa): void
    {
        try {
            \App\Services\ContadoresNavbarService::invalidarSubmodulosNuevos($idUsuario, $idEmpresa);
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

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
        $rows = $this->query("SELECT id_submodulo, COALESCE(r,0) AS r, COALESCE(w,0) AS w, COALESCE(u,0) AS u, COALESCE(d,0) AS d, COALESCE(t,0) AS t
            FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE}");
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['id_submodulo']] = [
                'ver' => (int)($r['r'] ?? 0),
                'crear' => (int)($r['w'] ?? 0),
                'actualizar' => (int)($r['u'] ?? 0),
                'eliminar' => (int)($r['d'] ?? 0),
                't' => (int)($r['t'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Guardar permisos en modulos_asignados.
     * Solo se guardan filas con al menos un permiso (ver, crear, actualizar, eliminar).
     * Se eliminan las asignaciones que quedaron sin ningún permiso.
     */
    /**
     * Copia (modo REEMPLAZAR) los permisos de un usuario+empresa origen a un usuario+empresa destino.
     * Borra los permisos previos del destino en esa empresa y replica exactamente los del origen.
     */
    public function copiarPermisosUsuario(int $idUsuarioOrigen, int $idEmpresaOrigen, int $idUsuarioDestino, int $idEmpresaDestino): bool
    {
        $uo = (int) $idUsuarioOrigen;
        $eo = (int) $idEmpresaOrigen;
        $ud = (int) $idUsuarioDestino;
        $ed = (int) $idEmpresaDestino;
        if ($uo <= 0 || $eo <= 0 || $ud <= 0 || $ed <= 0) return false;

        $origen = $this->query("SELECT id_modulo, id_submodulo, COALESCE(r,0) AS r, COALESCE(w,0) AS w,
                                       COALESCE(u,0) AS u, COALESCE(d,0) AS d, COALESCE(t,0) AS t
                                FROM modulos_asignados WHERE id_usuario = {$uo} AND id_empresa = {$eo}");

        $this->db->beginTransaction();
        try {
            // Reemplazar: borrar permisos previos del destino en la empresa destino
            $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$ud} AND id_empresa = {$ed}");

            foreach ($origen as $row) {
                $idMod = (int) $row['id_modulo'];
                $idSub = (int) $row['id_submodulo'];
                if ($idMod <= 0 || $idSub <= 0) continue;
                $r = (int) $row['r']; $w = (int) $row['w'];
                $u = (int) $row['u']; $d = (int) $row['d']; $t = (int) $row['t'];

                $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                    VALUES ({$ud}, {$ed}, {$idMod}, {$idSub}, {$r}, {$w}, {$u}, {$d}, {$t})");
            }

            $this->db->commit();
            $this->invalidarAvisoNuevo($ud, $ed);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Guarda (upsert/delete) el permiso de UN solo submódulo. Pensado para guardado inmediato vía AJAX.
     */
    public function guardarPermisoSubmodulo(int $idUsuario, int $idEmpresa, int $idModulo, int $idSubmodulo, array $p): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $idM = (int) $idModulo;
        $idS = (int) $idSubmodulo;
        if ($idU <= 0 || $idE <= 0 || $idM <= 0 || $idS <= 0) return false;

        $r = !empty($p['ver']) ? 1 : 0;
        $w = !empty($p['crear']) ? 1 : 0;
        $u = !empty($p['actualizar']) ? 1 : 0;
        $d = !empty($p['eliminar']) ? 1 : 0;
        $t = !empty($p['t']) ? 1 : 0;

        $this->db->beginTransaction();
        try {
            // Si no queda ningún permiso marcado, eliminar la asignación
            if ($r + $w + $u + $d + $t === 0) {
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
                $this->db->commit();
                return true;
            }

            $existe = $this->query("SELECT 1 FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
            if (!empty($existe)) {
                $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}, t = {$t}
                    WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
            } else {
                $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                    VALUES ({$idU}, {$idE}, {$idM}, {$idS}, {$r}, {$w}, {$u}, {$d}, {$t})");
            }
            $this->db->commit();
            $this->invalidarAvisoNuevo($idU, $idE);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function guardarPermisos(int $idUsuario, int $idEmpresa, array $permisos, array $idModuloPorSub): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $this->db->beginTransaction();
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
                $t = isset($p['t']) && $p['t'] ? 1 : 0;

                $existe = $this->query("SELECT 1 FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                if (!empty($existe)) {
                    $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}, t = {$t}
                        WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                } else {
                    $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                        VALUES ({$idU}, {$idE}, {$idMod}, {$idSub}, {$r}, {$w}, {$u}, {$d}, {$t})");
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
            $this->invalidarAvisoNuevo($idU, $idE);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Catálogo completo de submódulos agrupado por módulo, sin depender de un
     * usuario/empresa real. Usado por la asignación masiva (selector "qué submódulo asignar").
     */
    public function getCatalogoSubmodulos(): array
    {
        return $this->getModulosSuperAdmin(0, 0);
    }

    /**
     * Pares (id_usuario, id_empresa) que YA tienen una fila para ese submódulo.
     * Retorna un set indexado "idUsuario:idEmpresa" => true, para cruzar en PHP
     * contra la lista de destinos resuelta (evita SQL de tuplas dinámico).
     */
    public function getAsignacionesExistentes(int $idSubmodulo): array
    {
        $idS = (int) $idSubmodulo;
        if ($idS <= 0) return [];
        $rows = $this->query("SELECT id_usuario, id_empresa FROM modulos_asignados WHERE id_submodulo = {$idS}");
        $set = [];
        foreach ($rows as $r) {
            $set[(int)$r['id_usuario'] . ':' . (int)$r['id_empresa']] = true;
        }
        return $set;
    }

    /**
     * Asigna un submódulo en lote a una lista de destinos [ ['id_usuario'=>, 'id_empresa'=>], ... ].
     * Si el destino ya tiene ese submódulo asignado: se omite, salvo que $sobrescribir sea true
     * (en cuyo caso se actualizan sus permisos). Transaccional.
     *
     * @return array{insertados:int,actualizados:int,omitidos:int}
     */
    public function asignarSubmoduloEnLote(int $idModulo, int $idSubmodulo, array $destinos, array $permisosDefault, bool $sobrescribir): array
    {
        $idM = (int) $idModulo;
        $idS = (int) $idSubmodulo;
        $resultado = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        if ($idM <= 0 || $idS <= 0 || empty($destinos)) return $resultado;

        $r = !empty($permisosDefault['ver']) ? 1 : 0;
        $w = !empty($permisosDefault['crear']) ? 1 : 0;
        $u = !empty($permisosDefault['actualizar']) ? 1 : 0;
        $d = !empty($permisosDefault['eliminar']) ? 1 : 0;
        $t = !empty($permisosDefault['t']) ? 1 : 0;
        if ($r + $w + $u + $d + $t === 0) return $resultado;

        $existentes = $this->getAsignacionesExistentes($idS);

        $this->db->beginTransaction();
        try {
            foreach ($destinos as $dest) {
                $idU = (int) ($dest['id_usuario'] ?? 0);
                $idE = (int) ($dest['id_empresa'] ?? 0);
                if ($idU <= 0 || $idE <= 0) continue;
                $clave = $idU . ':' . $idE;

                if (isset($existentes[$clave])) {
                    if (!$sobrescribir) {
                        $resultado['omitidos']++;
                        continue;
                    }
                    $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}, t = {$t}
                        WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
                    $resultado['actualizados']++;
                } else {
                    $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                        VALUES ({$idU}, {$idE}, {$idM}, {$idS}, {$r}, {$w}, {$u}, {$d}, {$t})");
                    $existentes[$clave] = true;
                    $resultado['insertados']++;
                }
            }
            $this->db->commit();
            $invalidados = [];
            foreach ($destinos as $dest) {
                $idU = (int) ($dest['id_usuario'] ?? 0);
                $idE = (int) ($dest['id_empresa'] ?? 0);
                if ($idU <= 0 || $idE <= 0) continue;
                $clave = $idU . ':' . $idE;
                if (isset($invalidados[$clave])) continue;
                $invalidados[$clave] = true;
                $this->invalidarAvisoNuevo($idU, $idE);
            }
            return $resultado;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Normaliza ruta legacy de submodulos_menu para comparar con config/modulos_mvc.php.
     */
    private function normalizarRutaSubmodulo(string $ruta): string
    {
        $r = strtolower(trim($ruta));
        $r = str_replace(['../', './'], '', $r);
        $r = preg_replace('#^(sistema/)+#', '', $r);

        return ltrim($r, '/');
    }

    /**
     * Filas de submodulos_menu (soporta id_submodulo o id).
     */
    private function listarRutasSubmodulos(): array
    {
        $queries = [
            "SELECT id_submodulo, ruta FROM submodulos_menu WHERE COALESCE(status, 1) = 1",
            "SELECT id AS id_submodulo, ruta FROM submodulos_menu WHERE COALESCE(status, 1) = 1",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * Resuelve id_submodulo para una ruta MVC (ej. modulos/clientes) usando config/modulos_mvc.php.
     */
    public function getIdSubmoduloPorRutaMvc(string $pathMvc): ?int
    {
        $cfgFile = MVC_CONFIG . '/modulos_mvc.php';
        $all = is_file($cfgFile) ? require $cfgFile : [];
        $entry = $all[$pathMvc] ?? [];

        if (!empty($entry['id_submodulo'])) {
            $id = (int) $entry['id_submodulo'];

            return $id > 0 ? $id : null;
        }
        $legacy = $entry['legacy_rutas'] ?? [];
        $targets = [];
        // Soportar también la ruta MVC exacta por si el submódulo ya se guardó con esa ruta limpia en la BD
        $targets[$this->normalizarRutaSubmodulo($pathMvc)] = true;
        
        foreach ($legacy as $lr) {
            $targets[$this->normalizarRutaSubmodulo((string) $lr)] = true;
        }
        if ($targets === []) {
            return null;
        }
        foreach ($this->listarRutasSubmodulos() as $row) {
            $norm = $this->normalizarRutaSubmodulo((string) ($row['ruta'] ?? ''));
            if ($norm !== '' && isset($targets[$norm])) {
                $id = (int) ($row['id_submodulo'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }

    /**
     * Submódulos con permiso 'ver' que el usuario aún NO ha visitado (sin fila en
     * submodulos_vistos). Alimenta el aviso del navbar "submódulo nuevo asignado".
     */
    public function getSubmodulosNuevosDeUsuario(int $idUsuario, int $idEmpresa, int $limite = 30): array
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        if ($idU <= 0 || $idE <= 0) return [];
        $lim = max(1, $limite);

        $queries = [
            "SELECT sm.id_submodulo, sm.nombre_submodulo, sm.ruta, mm.nombre_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id_modulo = ma.id_modulo
                LEFT JOIN submodulos_vistos sv ON sv.id_usuario = ma.id_usuario AND sv.id_empresa = ma.id_empresa AND sv.id_submodulo = ma.id_submodulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE} AND COALESCE(ma.r, 0) = 1 AND sv.id_submodulo IS NULL
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo
                LIMIT {$lim}",
            "SELECT sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, mm.nombre_modulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                INNER JOIN modulos_menu mm ON mm.id = ma.id_modulo
                LEFT JOIN submodulos_vistos sv ON sv.id_usuario = ma.id_usuario AND sv.id_empresa = ma.id_empresa AND sv.id_submodulo = ma.id_submodulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE} AND COALESCE(ma.r, 0) = 1 AND sv.id_submodulo IS NULL
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo
                LIMIT {$lim}",
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

    /** Marca un submódulo como visitado por el usuario (idempotente). */
    public function marcarSubmoduloVisto(int $idUsuario, int $idEmpresa, int $idSubmodulo): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        $idS = (int) $idSubmodulo;
        if ($idU <= 0 || $idE <= 0 || $idS <= 0) return false;

        $ok = $this->execute("INSERT INTO submodulos_vistos (id_usuario, id_empresa, id_submodulo)
            VALUES ({$idU}, {$idE}, {$idS}) ON CONFLICT (id_usuario, id_empresa, id_submodulo) DO NOTHING");
        $this->invalidarAvisoNuevo($idU, $idE);
        return $ok;
    }
}
