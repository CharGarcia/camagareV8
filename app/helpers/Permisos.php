<?php

declare(strict_types=1);

namespace App\Helpers;

use App\models\PermisoSubmodulo;

/**
 * Resolución de permisos por ruta MVC (modulos/{nombre}) llamable desde
 * CUALQUIER vista o servicio, sin depender de que el controlador los pase.
 *
 * Fuente de verdad única: replica la lógica de PermisoModuloTrait
 * (que ahora delega aquí). Se basa en la sesión (nivel, id_usuario,
 * id_empresa) y en el modelo PermisoSubmodulo, con caché por request.
 *
 * Uso típico para ocultar un botón de atajo "crear X" en un modal:
 *   <?php if (\App\Helpers\Permisos::puedeCrear('modulos/clientes')): ?>
 *       <button onclick="abrirModalClienteCrear()">Nuevo cliente</button>
 *   <?php endif; ?>
 *
 * OJO: ocultar el botón es solo UX. El endpoint de crear del módulo destino
 * DEBE seguir validando el permiso (requireCrear) — el guard real vive allí.
 */
class Permisos
{
    /** @var array<string,array> Caché por (ruta|usuario|empresa) dentro del request. */
    private static array $cache = [];

    private static ?PermisoSubmodulo $model = null;

    private static function model(): PermisoSubmodulo
    {
        if (self::$model === null) {
            self::$model = new PermisoSubmodulo();
        }
        return self::$model;
    }

    /**
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,todo:bool,id_submodulo:?int}
     */
    public static function porRuta(string $pathMvc): array
    {
        $idU = (int) ($_SESSION['id_usuario'] ?? 0);
        $idE = (int) ($_SESSION['id_empresa'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        return self::porRutaEnEmpresa($pathMvc, $idE, $idU, $nivel);
    }

    /**
     * Igual que porRuta(), pero para una empresa arbitraria (no necesariamente la
     * empresa activa en sesión). Usado por flujos que escriben en OTRA empresa del
     * mismo usuario (p. ej. replicar un cliente hacia otra empresa asignada):
     * el permiso de "crear" debe validarse contra la empresa destino, no la actual.
     *
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,todo:bool,id_submodulo:?int}
     */
    public static function porRutaEnEmpresa(string $pathMvc, int $idEmpresa, ?int $idUsuario = null, ?int $nivel = null): array
    {
        $idU = $idUsuario ?? (int) ($_SESSION['id_usuario'] ?? 0);
        $idE = (int) $idEmpresa;
        $nv  = $nivel ?? (int) ($_SESSION['nivel'] ?? 1);

        $key = $pathMvc . '|' . $idU . '|' . $idE;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $todos = [
            'ver' => true, 'crear' => true, 'actualizar' => true,
            'eliminar' => true, 'todo' => true,
        ];

        // Nivel 3 (superadmin): acceso total incondicional. NO depende de modulos_asignados
        // — ni de que no exista fila, ni de lo que esa fila diga si existiera. Se resuelve
        // antes de tocar el modelo/BD para no quedar nunca sujeto a datos de permisos.
        if ($nv >= 3) {
            return self::$cache[$key] = $todos + ['id_submodulo' => null];
        }

        try {
            $model = self::model();
            // Puede haber más de un submódulo con la misma ruta (el mismo módulo
            // colgado de dos menús): vale el permiso asignado en cualquiera de ellos.
            $idsSub = $model->getIdsSubmoduloPorRutaMvc($pathMvc);
            $idSub = $idsSub[0] ?? null;

            $base = [
                'ver' => false, 'crear' => false, 'actualizar' => false,
                'eliminar' => false, 'todo' => false, 'id_submodulo' => $idSub,
            ];

            if ($idSub === null) {
                return self::$cache[$key] = $base;
            }

            $map = $model->getPermisosDeUsuario($idU, $idE);
            $p = null;
            foreach ($idsSub as $candidato) {
                if (isset($map[$candidato])) {
                    $p = $map[$candidato];
                    $idSub = $candidato;
                    break;
                }
            }
            if ($p === null) {
                return self::$cache[$key] = $base;
            }
            return self::$cache[$key] = [
                'ver'          => !empty($p['ver']),
                'crear'        => !empty($p['crear']),
                'actualizar'   => !empty($p['actualizar']),
                'eliminar'     => !empty($p['eliminar']),
                'todo'         => !empty($p['t']),
                'id_submodulo' => $idSub,
            ];
        } catch (\Throwable $e) {
            // Nivel 3 ya se resolvió arriba sin tocar el modelo; aquí solo caen niveles 1-2.
            return self::$cache[$key] = [
                'ver' => false, 'crear' => false, 'actualizar' => false,
                'eliminar' => false, 'todo' => false, 'id_submodulo' => null,
            ];
        }
    }

    public static function puedeVer(string $ruta): bool        { return !empty(self::porRuta($ruta)['ver']); }
    public static function puedeCrear(string $ruta): bool       { return !empty(self::porRuta($ruta)['crear']); }
    public static function puedeActualizar(string $ruta): bool  { return !empty(self::porRuta($ruta)['actualizar']); }
    public static function puedeEliminar(string $ruta): bool    { return !empty(self::porRuta($ruta)['eliminar']); }
}
