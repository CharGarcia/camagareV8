<?php
/**
 * Controlador API v1: menú de módulos/submódulos asignados al usuario.
 * Reutiliza tal cual App\models\ModuloMenu::getModulosConSubmodulos() — la MISMA
 * fuente de datos que arma el navbar de la web (nivel 3 ve todo; el resto solo lo
 * asignado en modulos_asignados) — para no duplicar la lógica de permisos del menú.
 */

declare(strict_types=1);

namespace App\controllers\api\v1;

use App\controllers\api\ApiBaseController;
use App\models\ModuloMenu;

class MenuController extends ApiBaseController
{
    /**
     * No aplica: este endpoint no depende de un único submódulo (lista todos los
     * asignados), solo exige requireAuthApi(). Se implementa por el contrato abstracto.
     */
    protected function getRutaModulo(): string
    {
        return 'menu';
    }

    /**
     * GET /api/v1/menu
     */
    public function index(): void
    {
        $this->requireAuthApi();

        $idUsuario = (int) $_SESSION['id_usuario'];
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $nivel = (int) $_SESSION['nivel'];

        if ($idEmpresa <= 0) {
            $this->jsonError('SIN_EMPRESA', 'No hay empresa activa.', 403);
        }

        $modulos = (new ModuloMenu())->getModulosConSubmodulos($idUsuario, $idEmpresa, $nivel);

        // getModulosConSubmodulos() antepone BASE_URL a 'ruta' (para <a href> en la web).
        // La app necesita la ruta MVC cruda (ej. "modulos/pedidos") para poder
        // compararla contra sus propias pantallas implementadas.
        $prefijo = rtrim(BASE_URL, '/') . '/';
        foreach ($modulos as &$modulo) {
            if (empty($modulo['submodulos'])) {
                continue;
            }
            // OJO: "foreach ($x ?? [] as &$v)" NO modifica $x (el ?? rompe la referencia,
            // itera sobre una copia temporal) — iterar sobre el array real directamente.
            foreach ($modulo['submodulos'] as &$sub) {
                if (isset($sub['ruta']) && str_starts_with($sub['ruta'], $prefijo)) {
                    $sub['ruta'] = substr($sub['ruta'], strlen($prefijo));
                }
            }
            unset($sub);
        }
        unset($modulo);

        // El navbar web oculta módulos sin submódulos visibles; igual aquí.
        $modulos = array_values(array_filter($modulos, static fn ($m) => !empty($m['submodulos'])));

        $this->jsonOk($modulos);
    }
}
