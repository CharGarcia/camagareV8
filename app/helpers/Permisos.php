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
        $key = $pathMvc . '|' . $idU . '|' . $idE;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $todos = [
            'ver' => true, 'crear' => true, 'actualizar' => true,
            'eliminar' => true, 'todo' => true,
        ];

        try {
            $model = self::model();
            $idSub = $model->getIdSubmoduloPorRutaMvc($pathMvc);

            $base = [
                'ver' => false, 'crear' => false, 'actualizar' => false,
                'eliminar' => false, 'todo' => false, 'id_submodulo' => $idSub,
            ];

            // Nivel 3 (superadmin): acceso total, aun sin id_submodulo detectado.
            if ($idSub === null) {
                $res = $nivel >= 3 ? ($todos + ['id_submodulo' => null]) : $base;
                return self::$cache[$key] = $res;
            }

            $map = $model->getPermisosDeUsuario($idU, $idE);
            if (!isset($map[$idSub])) {
                $res = $nivel >= 3 ? ($todos + ['id_submodulo' => $idSub]) : $base;
                return self::$cache[$key] = $res;
            }

            $p = $map[$idSub];
            return self::$cache[$key] = [
                'ver'          => !empty($p['ver']),
                'crear'        => !empty($p['crear']),
                'actualizar'   => !empty($p['actualizar']),
                'eliminar'     => !empty($p['eliminar']),
                'todo'         => !empty($p['t']),
                'id_submodulo' => $idSub,
            ];
        } catch (\Throwable $e) {
            // Ante cualquier error, nivel 3 conserva acceso; el resto queda sin permiso.
            return self::$cache[$key] = ($nivel >= 3
                ? ($todos + ['id_submodulo' => null])
                : ['ver' => false, 'crear' => false, 'actualizar' => false, 'eliminar' => false, 'todo' => false, 'id_submodulo' => null]);
        }
    }

    public static function puedeVer(string $ruta): bool        { return !empty(self::porRuta($ruta)['ver']); }
    public static function puedeCrear(string $ruta): bool       { return !empty(self::porRuta($ruta)['crear']); }
    public static function puedeActualizar(string $ruta): bool  { return !empty(self::porRuta($ruta)['actualizar']); }
    public static function puedeEliminar(string $ruta): bool    { return !empty(self::porRuta($ruta)['eliminar']); }
}
