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
     * @return array{ver:bool,crear:bool,actualizar:bool,eliminar:bool,todo:bool,id_submodulo:?int}
     *
     * Delegado en \App\Helpers\Permisos (fuente de verdad única, con caché),
     * para que la misma resolución esté disponible desde vistas/servicios.
     */
    protected function permisosModuloPorRuta(string $pathMvc): array
    {
        return \App\Helpers\Permisos::porRuta($pathMvc);
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
        // Empresa demo (cuenta de prueba para revisores de Apple/Google Play):
        // nunca puede eliminar nada, sin importar qué permiso 'd' tenga asignado
        // el usuario ni en qué módulo — refuerzo centralizado, un solo punto para
        // todos los controladores (web y API). Nivel 3 queda exento: el superadmin
        // real sigue pudiendo administrar/limpiar los datos de la empresa demo.
        if ($letra === 'd' && (int) ($_SESSION['nivel'] ?? 0) < 3 && $this->empresaActualEsDemo()) {
            if ($this->esAjaxRequest()) {
                $this->json(['ok' => false, 'error' => 'Esta es una empresa de demostración: no se permite eliminar registros.'], 403);
            }
            $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
        }

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

    /** Cachea por request: la empresa activa no cambia a media petición. */
    private function empresaActualEsDemo(): bool
    {
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            return false;
        }
        static $cache = [];
        if (!array_key_exists($idEmpresa, $cache)) {
            $cache[$idEmpresa] = (new \App\repositories\modulos\EmpresaRepository())->esDemo($idEmpresa);
        }
        return $cache[$idEmpresa];
    }
}
