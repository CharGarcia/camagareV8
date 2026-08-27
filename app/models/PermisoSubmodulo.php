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
            // Y el flag "atiende soporte", que se cachea por usuario (no por
            // empresa): quitar o dar el Chat de Soporte debe reflejarse en el
            // navbar sin esperar a que expire el TTL.
            \App\Services\modulos\SoporteChatService::invalidarAgente($idUsuario);
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /**
     * Registra en log_sistema un cambio de permiso sobre modulos_asignados.
     * $idUsuarioAfectado (dueño del permiso) va dentro de antes/despues porque
     * el actor de la auditoría es quien hizo el cambio, no el afectado.
     * Nunca debe romper el guardado si la auditoría falla.
     */
    private function auditarPermiso(
        int $idUsuarioActual,
        int $idEmpresa,
        string $accion,
        int $idSubmodulo,
        int $idUsuarioAfectado,
        ?array $antes,
        ?array $despues
    ): void {
        try {
            (new \App\Services\LogSistemaService())->registrar(
                $idUsuarioActual,
                $idEmpresa,
                $accion,
                'modulos_asignados',
                $idSubmodulo,
                $antes === null ? null : array_merge(['id_usuario' => $idUsuarioAfectado], $antes),
                $despues === null ? null : array_merge(['id_usuario' => $idUsuarioAfectado], $despues)
            );
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
    public function copiarPermisosUsuario(int $idUsuarioOrigen, int $idEmpresaOrigen, int $idUsuarioDestino, int $idEmpresaDestino, int $idUsuarioActual = 0): bool
    {
        $uo = (int) $idUsuarioOrigen;
        $eo = (int) $idEmpresaOrigen;
        $ud = (int) $idUsuarioDestino;
        $ed = (int) $idEmpresaDestino;
        if ($uo <= 0 || $eo <= 0 || $ud <= 0 || $ed <= 0) return false;

        $origen = $this->query("SELECT id_modulo, id_submodulo, COALESCE(r,0) AS r, COALESCE(w,0) AS w,
                                       COALESCE(u,0) AS u, COALESCE(d,0) AS d, COALESCE(t,0) AS t
                                FROM modulos_asignados WHERE id_usuario = {$uo} AND id_empresa = {$eo}");
        // Estado previo del destino, para que la auditoría muestre qué permisos perdió al ser reemplazados.
        $destinoAntes = $this->query("SELECT id_submodulo, COALESCE(r,0) AS r, COALESCE(w,0) AS w,
                                       COALESCE(u,0) AS u, COALESCE(d,0) AS d, COALESCE(t,0) AS t
                                FROM modulos_asignados WHERE id_usuario = {$ud} AND id_empresa = {$ed}");

        $this->db->beginTransaction();
        try {
            // Reemplazar: borrar permisos previos del destino en la empresa destino
            $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$ud} AND id_empresa = {$ed}");

            $insertados = [];
            foreach ($origen as $row) {
                $idMod = (int) $row['id_modulo'];
                $idSub = (int) $row['id_submodulo'];
                if ($idMod <= 0 || $idSub <= 0) continue;
                $r = (int) $row['r']; $w = (int) $row['w'];
                $u = (int) $row['u']; $d = (int) $row['d']; $t = (int) $row['t'];

                $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                    VALUES ({$ud}, {$ed}, {$idMod}, {$idSub}, {$r}, {$w}, {$u}, {$d}, {$t})");
                $insertados[] = ['id_submodulo' => $idSub, 'r' => $r, 'w' => $w, 'u' => $u, 'd' => $d, 't' => $t];
            }

            $this->db->commit();
            $this->invalidarAvisoNuevo($ud, $ed);
            try {
                (new \App\Services\LogSistemaService())->registrar(
                    $idUsuarioActual,
                    $ed,
                    'copiar_permisos_usuario',
                    'modulos_asignados',
                    null,
                    ['id_usuario' => $ud, 'submodulos' => $destinoAntes],
                    ['id_usuario' => $ud, 'id_usuario_origen' => $uo, 'id_empresa_origen' => $eo, 'submodulos' => $insertados]
                );
            } catch (\Throwable $e) {
                // Silencioso a propósito.
            }
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Guarda (upsert/delete) el permiso de UN solo submódulo. Pensado para guardado inmediato vía AJAX.
     */
    public function guardarPermisoSubmodulo(int $idUsuario, int $idEmpresa, int $idModulo, int $idSubmodulo, array $p, int $idUsuarioActual = 0): bool
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

        $filaAntes = $this->query("SELECT COALESCE(r,0) AS r, COALESCE(w,0) AS w, COALESCE(u,0) AS u, COALESCE(d,0) AS d, COALESCE(t,0) AS t
            FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
        $antes = $filaAntes[0] ?? null;
        if ($antes !== null) {
            $antes = ['r' => (int)$antes['r'], 'w' => (int)$antes['w'], 'u' => (int)$antes['u'], 'd' => (int)$antes['d'], 't' => (int)$antes['t']];
        }

        $this->db->beginTransaction();
        try {
            // Si no queda ningún permiso marcado, eliminar la asignación
            if ($r + $w + $u + $d + $t === 0) {
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
                $this->db->commit();
                if ($antes !== null) {
                    $this->auditarPermiso($idUsuarioActual, $idE, 'quitar_permiso_submodulo', $idS, $idU, $antes, null);
                }
                return true;
            }

            $existe = $antes !== null;
            if ($existe) {
                $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}, t = {$t}
                    WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idS}");
            } else {
                $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                    VALUES ({$idU}, {$idE}, {$idM}, {$idS}, {$r}, {$w}, {$u}, {$d}, {$t})");
            }
            $this->db->commit();
            $this->invalidarAvisoNuevo($idU, $idE);
            $despues = ['r' => $r, 'w' => $w, 'u' => $u, 'd' => $d, 't' => $t];
            $this->auditarPermiso($idUsuarioActual, $idE, $existe ? 'actualizar_permiso_submodulo' : 'asignar_permiso_submodulo', $idS, $idU, $antes, $despues);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function guardarPermisos(int $idUsuario, int $idEmpresa, array $permisos, array $idModuloPorSub, int $idUsuarioActual = 0): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        // Estado previo completo, para poder auditar diffs reales (no solo el resultado final).
        $existentesAntes = $this->getPermisosDeUsuario($idU, $idE);

        $this->db->beginTransaction();
        try {
            $idsAGuardar = [];
            $cambios = [];
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

                $antes = $existentesAntes[$idSub] ?? null;
                $despues = ['ver' => $r, 'crear' => $w, 'actualizar' => $u, 'eliminar' => $d, 't' => $t];
                $existe = $antes !== null;

                if ($existe) {
                    $this->execute("UPDATE modulos_asignados SET r = {$r}, w = {$w}, u = {$u}, d = {$d}, t = {$t}
                        WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                } else {
                    $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                        VALUES ({$idU}, {$idE}, {$idMod}, {$idSub}, {$r}, {$w}, {$u}, {$d}, {$t})");
                }
                $idsAGuardar[] = $idSub;

                if ($antes !== $despues) {
                    $cambios[] = [$idSub, $existe ? 'actualizar_permiso_submodulo' : 'asignar_permiso_submodulo', $antes, $despues];
                }
            }

            // Eliminar asignaciones que ya no tienen ningún permiso (filas no incluidas en $permisos)
            $idsEliminados = [];
            if (!empty($idsAGuardar)) {
                $idsStr = implode(',', array_map('intval', $idsAGuardar));
                foreach ($existentesAntes as $idSubExistente => $permExistente) {
                    if (!in_array($idSubExistente, $idsAGuardar, true)) {
                        $idsEliminados[] = $idSubExistente;
                    }
                }
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo NOT IN ({$idsStr})");
            } else {
                $idsEliminados = array_keys($existentesAntes);
                $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE}");
            }
            foreach ($idsEliminados as $idSubElim) {
                $cambios[] = [$idSubElim, 'quitar_permiso_submodulo', $existentesAntes[$idSubElim], null];
            }

            $this->db->commit();
            $this->invalidarAvisoNuevo($idU, $idE);
            foreach ($cambios as [$idSubCambio, $accionCambio, $antesCambio, $despuesCambio]) {
                $this->auditarPermiso($idUsuarioActual, $idE, $accionCambio, $idSubCambio, $idU, $antesCambio, $despuesCambio);
            }
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
        // El ltrim va ANTES de quitar el prefijo 'sistema/': hay submódulos guardados
        // como '/sistema/modulos/xxx' y con el orden inverso el prefijo sobrevivía
        // (el patrón está anclado al inicio) y la ruta nunca casaba.
        $r = ltrim($r, '/');
        $r = preg_replace('#^(sistema/)+#', '', $r);
        $r = ltrim($r, '/');
        // Unificar guión medio y bajo: el Router (toCamelCase) trata '-' y '_' como
        // equivalentes al resolver el controlador, así que el resolutor de permisos
        // debe hacer lo mismo. Sin esto, un submódulo guardado como
        // 'modulos/unidades_medida' no resuelve contra getRutaModulo()
        // 'modulos/unidades-medida' y el usuario no-admin es enviado al dashboard.
        return str_replace('_', '-', $r);
    }

    /**
     * Filas de submodulos_menu (soporta id_submodulo o id).
     * Cacheado por request: se consulta una vez por cada ruta MVC cuyo permiso
     * se resuelve (una vista puede preguntar por varios módulos).
     */
    private function listarRutasSubmodulos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $queries = [
            "SELECT id_submodulo, ruta FROM submodulos_menu WHERE COALESCE(status, 1) = 1",
            "SELECT id AS id_submodulo, ruta FROM submodulos_menu WHERE COALESCE(status, 1) = 1",
        ];
        foreach ($queries as $sql) {
            try {
                $rows = $this->query($sql);
                if (!empty($rows)) {
                    return $cache = $rows;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $cache = [];
    }

    /**
     * ¿El usuario tiene el permiso $letra sobre alguno de estos submódulos en
     * ALGUNA empresa asignada (no necesariamente la activa)?
     *
     * Los permisos son por empresa (§4), así que esto es una EXCEPCIÓN y solo debe
     * usarse en módulos cuyo alcance real no es la empresa activa: la bandeja del
     * chat de soporte es global —atiende consultas de todas las empresas—, así que
     * quien la tenga asignada en una empresa la sigue atendiendo mientras trabaja
     * en otra. No usar esto para módulos operativos normales.
     *
     * Solo cuentan las empresas vigentes: una asignación sobre una empresa inactiva
     * o eliminada no debe habilitar nada.
     *
     * @param int[]  $idsSubmodulo
     * @param string $letra r (ver), w (crear), u (actualizar), d (eliminar), t (todo)
     */
    public function tienePermisoEnAlgunaEmpresa(int $idUsuario, array $idsSubmodulo, string $letra = 'r'): bool
    {
        $idU = (int) $idUsuario;
        if ($idU <= 0) {
            return false;
        }

        $ids = [];
        foreach ($idsSubmodulo as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return false;
        }
        $inIds = implode(',', array_keys($ids));
        $col   = in_array($letra, ['r', 'w', 'u', 'd', 't'], true) ? $letra : 'r';

        // Primero con JOIN a empresas (descarta asignaciones sobre empresas dadas de
        // baja); si ese esquema no responde, se cae a la consulta simple para no
        // dejar sin bandeja a una instalación con otro esquema de empresas.
        $queries = [
            // La PK de empresas es 'id' (ver App\models\Empresa); la segunda variante
            // cubre el esquema alterno con 'id_empresa', igual que el resto del modelo.
            "SELECT 1 FROM modulos_asignados ma
                INNER JOIN empresas e ON e.id = ma.id_empresa
                    AND e.estado = '1' AND e.eliminado = false
                WHERE ma.id_usuario = {$idU}
                  AND ma.id_submodulo IN ({$inIds})
                  AND COALESCE(ma.{$col}, 0) = 1
                LIMIT 1",
            "SELECT 1 FROM modulos_asignados ma
                INNER JOIN empresas e ON e.id_empresa = ma.id_empresa
                    AND e.estado = '1' AND e.eliminado = false
                WHERE ma.id_usuario = {$idU}
                  AND ma.id_submodulo IN ({$inIds})
                  AND COALESCE(ma.{$col}, 0) = 1
                LIMIT 1",
            "SELECT 1 FROM modulos_asignados
                WHERE id_usuario = {$idU}
                  AND id_submodulo IN ({$inIds})
                  AND COALESCE({$col}, 0) = 1
                LIMIT 1",
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
     * Resuelve id_submodulo para una ruta MVC (ej. modulos/clientes) usando config/modulos_mvc.php.
     *
     * El id_submodulo del config es solo una PISTA: los ids de submodulos_menu se
     * asignan por instalación (cada base los genera al insertar el menú), así que un
     * id fijo en el repositorio puede no corresponder a esa ruta en la base del
     * cliente. Si no corresponde, el usuario no-superadmin queda sin permiso 'ver'
     * aunque el superadmin se lo haya asignado, y el guard lo manda al dashboard.
     *
     * Por eso la BASE MANDA: si algún submódulo activo tiene la ruta MVC (o una de
     * sus legacy_rutas), ese es el id. El id del config solo se usa cuando la ruta
     * no está registrada en submodulos_menu, y nunca si apunta a otra ruta distinta.
     */
    public function getIdSubmoduloPorRutaMvc(string $pathMvc): ?int
    {
        return $this->getIdsSubmoduloPorRutaMvc($pathMvc)[0] ?? null;
    }

    /**
     * Todos los submódulos que corresponden a una ruta MVC, en orden de preferencia.
     *
     * Normalmente es uno solo, pero submodulos_menu puede tener la MISMA ruta en dos
     * filas (p. ej. 'Vehículos' colgando de Mecánica y de Car-Wash): las dos abren el
     * mismo módulo, así que el permiso asignado en cualquiera de ellas debe valer. Si
     * solo se mirara la primera, asignar el permiso en la otra no serviría de nada y
     * el usuario terminaría en el dashboard con el permiso marcado en pantalla.
     *
     * @return int[]
     */
    public function getIdsSubmoduloPorRutaMvc(string $pathMvc): array
    {
        $cfgFile = MVC_CONFIG . '/modulos_mvc.php';
        $all = is_file($cfgFile) ? require $cfgFile : [];
        $entry = $all[$pathMvc] ?? [];
        $idCfg = (int) ($entry['id_submodulo'] ?? 0);

        $targets = [];
        // Soportar también la ruta MVC exacta por si el submódulo ya se guardó con esa ruta limpia en la BD
        $targets[$this->normalizarRutaSubmodulo($pathMvc)] = true;
        foreach ($entry['legacy_rutas'] ?? [] as $lr) {
            $targets[$this->normalizarRutaSubmodulo((string) $lr)] = true;
        }

        $idsPorRuta = [];
        $rutaDelIdCfg = null;
        foreach ($this->listarRutasSubmodulos() as $row) {
            $id = (int) ($row['id_submodulo'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $norm = $this->normalizarRutaSubmodulo((string) ($row['ruta'] ?? ''));
            if ($idCfg > 0 && $id === $idCfg) {
                $rutaDelIdCfg = $norm;
            }
            if ($norm === '' || !isset($targets[$norm])) {
                continue;
            }
            // El id del config, si es uno de los que tienen esta ruta, va primero.
            if ($idCfg > 0 && $id === $idCfg) {
                array_unshift($idsPorRuta, $id);
                continue;
            }
            $idsPorRuta[] = $id;
        }

        if ($idsPorRuta !== []) {
            return array_values(array_unique($idsPorRuta));
        }
        // La ruta no está en submodulos_menu: solo se acepta el id del config si no
        // se pudo comprobar que pertenece a OTRA ruta (si pertenece, daría los
        // permisos de un módulo ajeno).
        if ($idCfg > 0 && $rutaDelIdCfg === null) {
            return [$idCfg];
        }

        return [];
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

    /**
     * Marca TODOS los submódulos "nuevos" pendientes de un usuario+empresa como
     * vistos de una sola vez (botón "Marcar todos como vistos" del navbar). Idempotente.
     */
    public function marcarTodosSubmodulosVistos(int $idUsuario, int $idEmpresa): bool
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;
        if ($idU <= 0 || $idE <= 0) return false;

        $queries = [
            "INSERT INTO submodulos_vistos (id_usuario, id_empresa, id_submodulo)
                SELECT ma.id_usuario, ma.id_empresa, ma.id_submodulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                LEFT JOIN submodulos_vistos sv ON sv.id_usuario = ma.id_usuario AND sv.id_empresa = ma.id_empresa AND sv.id_submodulo = ma.id_submodulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE} AND COALESCE(ma.r, 0) = 1 AND sv.id_submodulo IS NULL
                ON CONFLICT (id_usuario, id_empresa, id_submodulo) DO NOTHING",
            "INSERT INTO submodulos_vistos (id_usuario, id_empresa, id_submodulo)
                SELECT ma.id_usuario, ma.id_empresa, ma.id_submodulo
                FROM modulos_asignados ma
                INNER JOIN submodulos_menu sm ON sm.id = ma.id_submodulo AND COALESCE(sm.status, 1) = 1
                LEFT JOIN submodulos_vistos sv ON sv.id_usuario = ma.id_usuario AND sv.id_empresa = ma.id_empresa AND sv.id_submodulo = ma.id_submodulo
                WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE} AND COALESCE(ma.r, 0) = 1 AND sv.id_submodulo IS NULL
                ON CONFLICT (id_usuario, id_empresa, id_submodulo) DO NOTHING",
        ];
        foreach ($queries as $sql) {
            try {
                $this->execute($sql);
                $this->invalidarAvisoNuevo($idU, $idE);
                return true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return false;
    }
}
