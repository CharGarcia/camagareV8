<?php
/**
 * Modelo ModuloMenu - Módulos y submodulos según usuario y empresa
 *
 * Estructura de tablas (legacy):
 * - modulos_menu: id_modulo (PK), nombre_modulo, id_icono
 * - submodulos_menu: id_submodulo (PK), id_modulo, nombre_submodulo, ruta, status
 * - modulos_asignados: id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d
 */

declare(strict_types=1);

namespace App\models;

class ModuloMenu extends BaseModel
{
    /**
     * Mapa de rutas legacy → MVC (controller/action).
     * Rutas no mapeadas van a home/moduloEnConstruccion.
     */
    private const RUTA_LEGACY_A_MVC = [
        'modulos/clientes.php' => 'cliente/index',
        'modulos/cliente.php' => 'cliente/index',
        'paginas/empresa_set.php' => 'empresa/index',
        'paginas/menu_de_empresas.php' => 'empresa/index',
        'paginas/opciones_de_modulos.php' => 'config/modulo',
        'modulos/plan_de_cuentas.php' => 'config/plan-cuentas-modelo',
        'modulos/provincia_ciudad.php' => 'config/provincia-ciudad',
        'modulos/provincias.php' => 'config/provincia-ciudad',
        'modulos/ciudades.php' => 'config/provincia-ciudad',
    ];

    /**
     * Obtiene módulos con submodulos para el usuario y empresa actuales.
     * Nivel 3 (super admin): todos los módulos.
     * Nivel 1-2: solo los asignados en modulos_asignados.
     *
     * @return array [['id_modulo','nombre_modulo','icono_modulo','submodulos'=>[['id_submodulo','nombre_submodulo','ruta','icono_submodulo']]]]
     */
    public function getModulosConSubmodulos(int $idUsuario, int $idEmpresa, int $nivel): array
    {
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;

        $rows = [];
        try {
            $rows = $this->ejecutarQueryModulos($idU, $idE, $nivel);
        } catch (\Throwable $e) {
            try {
                $rows = $this->ejecutarQueryModulosAlternativa($idU, $idE, $nivel);
            } catch (\Throwable $e2) {
                return [];
            }
        }

        $rows = $this->agregarIconos($rows);
        return $this->agruparPorModulo($rows);
    }

    /** Schema con id_modulo, id_submodulo (legacy) */
    private function ejecutarQueryModulos(int $idU, int $idE, int $nivel): array
    {
        if ($nivel >= 3) {
            return $this->query("SELECT mm.id_modulo, mm.nombre_modulo, mm.id_icono AS mm_id_icono,
                sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_icono AS sm_id_icono
                FROM modulos_menu mm
                LEFT JOIN submodulos_menu sm ON sm.id_modulo = mm.id_modulo
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo");
        }
        return $this->query("SELECT mm.id_modulo, mm.nombre_modulo, mm.id_icono AS mm_id_icono,
            sm.id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_icono AS sm_id_icono
            FROM modulos_asignados ma
            INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo
            INNER JOIN modulos_menu mm ON mm.id_modulo = ma.id_modulo
            WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
            ORDER BY mm.nombre_modulo, sm.nombre_submodulo");
    }

    /** Schema con id como PK (modulos_menu.id, submodulos_menu.id) */
    private function ejecutarQueryModulosAlternativa(int $idU, int $idE, int $nivel): array
    {
        if ($nivel >= 3) {
            return $this->query("SELECT mm.id AS id_modulo, mm.nombre_modulo, mm.id_icono AS mm_id_icono,
                sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_icono AS sm_id_icono
                FROM modulos_menu mm
                LEFT JOIN submodulos_menu sm ON sm.id_modulo = mm.id
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo");
        }
        return $this->query("SELECT mm.id AS id_modulo, mm.nombre_modulo, mm.id_icono AS mm_id_icono,
            sm.id AS id_submodulo, sm.nombre_submodulo, sm.ruta, sm.id_icono AS sm_id_icono
            FROM modulos_asignados ma
            INNER JOIN submodulos_menu sm ON sm.id = ma.id_submodulo
            INNER JOIN modulos_menu mm ON mm.id = ma.id_modulo
            WHERE ma.id_usuario = {$idU} AND ma.id_empresa = {$idE}
            ORDER BY mm.nombre_modulo, sm.nombre_submodulo");
    }

    private function agregarIconos(array $rows): array
    {
        $idsIcono = [];
        foreach ($rows as $r) {
            $id = (int)($r['mm_id_icono'] ?? $r['id_icono'] ?? 0);
            if ($id > 0) $idsIcono[$id] = true;
            $id = (int)($r['sm_id_icono'] ?? 0);
            if ($id > 0) $idsIcono[$id] = true;
        }
        $idsIcono = array_keys($idsIcono);
        $mapaIconos = $this->obtenerMapaIconosPorId($idsIcono);

        foreach ($rows as &$r) {
            $idMod = (int)($r['mm_id_icono'] ?? $r['id_icono'] ?? 0);
            $idSub = (int)($r['sm_id_icono'] ?? 0);
            $r['icono_modulo'] = $mapaIconos[$idMod] ?? 'fas fa-folder';
            $r['icono_submodulo'] = $mapaIconos[$idSub] ?? 'fas fa-file';
        }
        return $rows;
    }

    /** Obtiene mapa id_icono => nombre_icono desde iconos_fontawesome */
    private function obtenerMapaIconosPorId(array $ids): array
    {
        if (empty($ids)) return [];
        $idsStr = implode(',', array_map('intval', $ids));
        $queries = [
            "SELECT id_icono, nombre_icono FROM iconos_fontawesome WHERE id_icono IN ({$idsStr})",
            "SELECT id, nombre_icono FROM iconos_fontawesome WHERE id IN ({$idsStr})",
        ];
        foreach ($queries as $sql) {
            try {
                $res = $this->query($sql);
                $mapa = [];
                foreach ($res as $row) {
                    $id = (int)($row['id_icono'] ?? $row['id'] ?? 0);
                    if ($id > 0 && !empty($row['nombre_icono'])) {
                        $mapa[$id] = trim($row['nombre_icono']);
                    }
                }
                return $mapa;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    private function agruparPorModulo(array $rows): array
    {
        $modulos = [];
        foreach ($rows as $r) {
            $idMod = (int) ($r['id_modulo'] ?? 0);
            if ($idMod === 0) continue;

            if (!isset($modulos[$idMod])) {
                $modulos[$idMod] = [
                    'id_modulo' => $idMod,
                    'nombre_modulo' => $r['nombre_modulo'] ?? '',
                    'icono_modulo' => $r['icono_modulo'] ?? 'fas fa-folder',
                    'submodulos' => [],
                ];
            }

            $idSub = (int) ($r['id_submodulo'] ?? 0);
            if ($idSub > 0 && ($r['nombre_submodulo'] ?? '') !== '') {
                $ruta = $this->construirRuta($r['ruta'] ?? '');
                $modulos[$idMod]['submodulos'][] = [
                    'id_submodulo' => $idSub,
                    'nombre_submodulo' => $r['nombre_submodulo'],
                    'ruta' => $ruta,
                    'icono_submodulo' => $r['icono_submodulo'] ?? 'fas fa-file',
                ];
            }
        }
        return array_values($modulos);
    }

    private function construirRuta(string $ruta): string
    {
        $ruta = trim($ruta);
        if ($ruta === '') return '#';
        if (preg_match('#^https?://#', $ruta)) return $ruta;
        if (str_starts_with($ruta, '/')) return $ruta;

        $rutaNorm = str_replace(['../', './'], '', $ruta);
        $rutaNorm = strtolower(ltrim($rutaNorm, '/'));
        $mvc = self::RUTA_LEGACY_A_MVC[$rutaNorm] ?? null;

        $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
        if ($mvc !== null) {
            return $base . '/' . $mvc;
        }
        return $base . '/home/moduloEnConstruccion';
    }
}
