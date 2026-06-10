<?php
/**
 * Permisos por submódulo según modulos_asignados (r,w,u,d) y empresa actual.
 * Usar en controladores de módulos bajo rutas tipo modulos/{nombre}.
 */
declare(strict_types=1);

namespace App\Traits;

use App\models\PermisoSubmodulo;

trait PermisoModuloTrait
{
    private ?PermisoSubmodulo $permisoSubmoduloModel = null;

    protected function getPermisoSubmoduloModel(): PermisoSubmodulo
    {
        if ($this->permisoSubmoduloModel === null) {
            $this->permisoSubmoduloModel = new PermisoSubmodulo();
        }

        return $this->permisoSubmoduloModel;
    }

    /** Requiere sesión e id_empresa (selección de empresa). */
    protected function requireEmpresaSesion(): void
    {
        $this->requireAuth();
        if (empty($_SESSION['id_empresa'])) {
            if ($this->esAjaxRequest()) {
                $this->json(['ok' => false, 'error' => 'No hay empresa activa. Por favor selecciona una empresa.'], 403);
            }
            $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
        }
    }

    protected function esAjaxRequest(): bool
    {
        $isXhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        $isFetch = str_contains($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '', 'cors');
        return $isXhr || $isJson || $isFetch;
    }

    /**
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,id_submodulo:?int}
     */
    protected function permisosModuloPorRuta(string $pathMvc): array
    {
        $model = $this->getPermisoSubmoduloModel();
        $idSub = $model->getIdSubmoduloPorRutaMvc($pathMvc);
        $base = [
            'ver' => false,
            'crear' => false,
            'actualizar' => false,
            'eliminar' => false,
            'todo' => false,
            'id_submodulo' => $idSub,
        ];
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($idSub === null) {
            // Regla general: Nivel 3 tiene acceso a todo, incluso si no hay id_submodulo detectado
            if ($nivel >= 3) {
                return [
                    'ver' => true,
                    'crear' => true,
                    'actualizar' => true,
                    'eliminar' => true,
                    'todo' => true,
                    'id_submodulo' => null,
                ];
            }
            return $base;
        }
        $idU = (int) ($_SESSION['id_usuario'] ?? 0);
        $idE = (int) ($_SESSION['id_empresa'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $map = $model->getPermisosDeUsuario($idU, $idE);
        if (!isset($map[$idSub])) {
            if ($nivel >= 3) {
                return [
                    'ver' => true,
                    'crear' => true,
                    'actualizar' => true,
                    'eliminar' => true,
                    'todo' => true,
                    'id_submodulo' => $idSub,
                ];
            }

            return $base;
        }
        $p = $map[$idSub];

        return [
            'ver' => !empty($p['ver']),
            'crear' => !empty($p['crear']),
            'actualizar' => !empty($p['actualizar']),
            'eliminar' => !empty($p['eliminar']),
            'todo' => !empty($p['t']),
            'id_submodulo' => $idSub,
        ];
    }

    /** Sin permiso de lectura: responde JSON 403 en AJAX, o redirige al menú. */
    protected function requirePermisoVerModulo(string $pathMvc): void
    {
        $p = $this->permisosModuloPorRuta($pathMvc);
        if (!$p['ver']) {
            if ($this->esAjaxRequest()) {
                $this->json(['ok' => false, 'error' => 'No tiene permiso para esta acción.'], 403);
            }
            $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
        }
    }

    /**
     * $letra: r (ver), w (crear), u (actualizar), d (eliminar).
     * Si la petición es AJAX, responde JSON 403.
     */
    protected function requirePermisoModulo(string $pathMvc, string $letra): void
    {
        $mapLetra = ['r' => 'ver', 'w' => 'crear', 'u' => 'actualizar', 'd' => 'eliminar'];
        $key = $mapLetra[$letra] ?? 'ver';
        $p = $this->permisosModuloPorRuta($pathMvc);
        if (!empty($p[$key])) {
            return;
        }
        if ($this->esAjaxRequest()) {
            $this->json(['ok' => false, 'error' => 'No tiene permiso para esta acción.'], 403);
        }
        $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
    }
}
