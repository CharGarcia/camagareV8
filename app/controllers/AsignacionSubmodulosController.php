<?php
/**
 * Controlador AsignacionSubmodulos - Asignación masiva de un submódulo a
 * varios usuarios/empresas de una sola vez. Exclusivo de superadministrador (nivel 3).
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\EmpresaAsignada;
use App\models\PermisoSubmodulo;
use App\Rules\AsignacionSubmodulosRules;
use App\Services\AsignacionSubmodulosService;

class AsignacionSubmodulosController extends Controller
{
    private EmpresaAsignada $modelEmpresa;
    private PermisoSubmodulo $modelPermiso;
    private AsignacionSubmodulosRules $rules;
    private AsignacionSubmodulosService $service;

    public function __construct()
    {
        parent::__construct();
        $this->modelEmpresa = new EmpresaAsignada();
        $this->modelPermiso = new PermisoSubmodulo();
        $this->rules = new AsignacionSubmodulosRules();
        $this->service = new AsignacionSubmodulosService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $catalogo = $this->agruparCatalogo($this->modelPermiso->getCatalogoSubmodulos());
        $empresas = $this->modelEmpresa->getTodasEmpresasParaSelect();

        $this->viewWithLayout('layouts.main', 'config.asignacion_submodulos', [
            'titulo'   => 'Asignación masiva de submódulos',
            'catalogo' => $catalogo,
            'empresas' => $empresas,
        ]);
    }

    public function usuariosJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $buscar = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $rows = $this->modelEmpresa->getUsuariosParaSelect($idActual, 3, $buscar, 500);
        $out = array_map(static function (array $r) {
            return [
                'value' => (int) $r['id'],
                'text'  => ($r['nombre'] ?? '') . ' (' . ($r['cedula'] ?? '') . ')',
                'nivel' => (int) ($r['nivel'] ?? 0),
            ];
        }, array_filter($rows, static fn (array $r) => (int) ($r['nivel'] ?? 0) < 3));
        $this->json(array_values($out));
    }

    public function previsualizarAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        [$errores, $idModulo, $idSubmodulo, $nombreSubmodulo, $permisos, $modo, $params] = $this->leerYValidarSeleccion();
        if (!empty($errores)) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)]);
        }

        $destinos = $this->service->resolverDestinos($modo, $params);
        if (empty($destinos)) {
            $this->json(['ok' => false, 'error' => 'No se encontraron destinatarios con los criterios elegidos.']);
        }

        $preview = $this->service->previsualizar($idSubmodulo, $destinos);
        $this->json(['ok' => true] + $preview);
    }

    public function aplicarAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        [$errores, $idModulo, $idSubmodulo, $nombreSubmodulo, $permisos, $modo, $params, $sobrescribir, $excluidos] = $this->leerYValidarSeleccion();
        if (!empty($errores)) {
            $this->json(['ok' => false, 'error' => implode(' ', $errores)]);
        }

        // Se vuelve a resolver en el servidor: nunca se confía en una lista de pares
        // usuario/empresa enviada desde el navegador. Los "excluidos" (desmarcados en
        // la previsualización) solo pueden ACHICAR el resultado, nunca ampliarlo.
        $destinos = $this->service->resolverDestinos($modo, $params);
        if (!empty($excluidos)) {
            $destinos = array_values(array_filter(
                $destinos,
                static fn (array $d) => !isset($excluidos[$d['id_usuario'] . ':' . $d['id_empresa']])
            ));
        }
        if (empty($destinos)) {
            $this->json(['ok' => false, 'error' => 'No quedó ningún destinatario para asignar.']);
        }

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        try {
            $resultado = $this->service->aplicar($idActual, $idModulo, $idSubmodulo, $nombreSubmodulo, $destinos, $permisos, $sobrescribir);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Error al aplicar la asignación.']);
        }

        $this->json(['ok' => true] + $resultado);
    }

    /**
     * @return array{0: string[], 1: int, 2: int, 3: string, 4: array, 5: string, 6: array, 7: bool, 8: array}
     */
    private function leerYValidarSeleccion(): array
    {
        $idModulo = (int) ($_POST['id_modulo'] ?? 0);
        $idSubmodulo = (int) ($_POST['id_submodulo'] ?? 0);
        $nombreSubmodulo = trim((string) ($_POST['nombre_submodulo'] ?? ''));

        $permisos = [
            'ver'        => !empty($_POST['ver']),
            'crear'      => !empty($_POST['crear']),
            'actualizar' => !empty($_POST['actualizar']),
            'eliminar'   => !empty($_POST['eliminar']),
            't'          => !empty($_POST['t']),
        ];

        $modo = trim((string) ($_POST['modo'] ?? ''));
        $idsUsuario = array_map('intval', (array) ($_POST['ids_usuario'] ?? []));
        $params = [
            'ids_usuario'        => array_values(array_filter($idsUsuario, static fn (int $v) => $v > 0)),
            'nivel'              => trim((string) ($_POST['nivel'] ?? '')),
            'id_empresa'         => (int) ($_POST['id_empresa'] ?? 0),
            'id_empresa_filtro'  => (int) ($_POST['id_empresa_filtro'] ?? 0),
        ];
        $sobrescribir = !empty($_POST['sobrescribir']);

        // Pares "idUsuario:idEmpresa" que el usuario desmarcó en la previsualización.
        $excluidos = [];
        foreach ((array) ($_POST['excluidos'] ?? []) as $clave) {
            $clave = trim((string) $clave);
            if (preg_match('/^\d+:\d+$/', $clave)) {
                $excluidos[$clave] = true;
            }
        }

        $errores = $this->rules->validar($idSubmodulo, $idModulo, $permisos, $modo, $params);

        return [$errores, $idModulo, $idSubmodulo, $nombreSubmodulo, $permisos, $modo, $params, $sobrescribir, $excluidos];
    }

    /** Agrupa el catálogo plano (id_modulo, id_submodulo, ...) en módulo => submódulos. */
    private function agruparCatalogo(array $rows): array
    {
        $modulos = [];
        foreach ($rows as $r) {
            $idMod = (int) ($r['id_modulo'] ?? 0);
            $idSub = (int) ($r['id_submodulo'] ?? 0);
            if ($idMod <= 0 || $idSub <= 0) continue;
            if (!isset($modulos[$idMod])) {
                $modulos[$idMod] = [
                    'id_modulo'     => $idMod,
                    'nombre_modulo' => $r['nombre_modulo'] ?? '',
                    'submodulos'    => [],
                ];
            }
            $modulos[$idMod]['submodulos'][] = [
                'id_submodulo'     => $idSub,
                'nombre_submodulo' => $r['nombre_submodulo'] ?? '',
            ];
        }
        usort($modulos, static fn (array $a, array $b) => strcmp((string) $a['nombre_modulo'], (string) $b['nombre_modulo']));
        foreach ($modulos as &$m) {
            usort($m['submodulos'], static fn (array $a, array $b) => strcmp((string) $a['nombre_submodulo'], (string) $b['nombre_submodulo']));
        }
        return array_values($modulos);
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['ok' => false, 'error' => 'No tiene permisos.'], 403);
            }
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta herramienta.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
