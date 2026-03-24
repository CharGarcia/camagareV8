<?php
/**
 * Controlador PermisosModulos - Permisos CRUD por submódulo a usuarios
 * Super admin: todos los módulos. Admin: solo módulos asignados.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\EmpresaAsignada;
use App\models\PermisoSubmodulo;

class PermisosModulosController extends Controller
{
    private EmpresaAsignada $modelEmpresa;
    private PermisoSubmodulo $modelPermiso;
    private const BASE_PATH = '/config/permisos-modulos';
    private const PER_PAGE = 10;

    public function __construct()
    {
        parent::__construct();
        $this->modelEmpresa = new EmpresaAsignada();
        $this->modelPermiso = new PermisoSubmodulo();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idUsuarioSel = (int) ($_GET['u'] ?? 0);
        $idEmpresaSel = (int) ($_GET['e'] ?? 0);
        if ($idUsuarioSel <= 0) {
            $idUsuarioSel = $idActual;
        }
        $mostrar = isset($_GET['mostrar']) && $_GET['mostrar'] === '1';

        $modulos = [];
        $usuarioSel = null;
        $empresaSel = null;

        if ($mostrar && $idUsuarioSel > 0 && $idEmpresaSel > 0) {
            if ($idUsuarioSel > 0) {
                $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuarioSel);
            }
            if ($idEmpresaSel > 0 && $usuarioSel) {
                $empresas = $this->modelEmpresa->getEmpresasParaPermisos($idUsuarioSel, $idActual, $nivel);
                $empresaSel = $this->buscarEmpresaEnLista($empresas, $idEmpresaSel);
                if (!$empresaSel) {
                    $empresaSel = ['id_empresa' => $idEmpresaSel, 'nombre_comercial' => 'Empresa #' . $idEmpresaSel, 'ruc' => ''];
                }
            }
            $rows = $this->modelPermiso->getModulosConSubmodulosParaPermisos($idActual, $idEmpresaSel, $nivel);
            $permisos = $this->modelPermiso->getPermisosDeUsuario($idUsuarioSel, $idEmpresaSel);
            $modulos = $this->agruparPorModuloOrdenado($rows, $permisos, $idUsuarioSel, $idEmpresaSel);

            $_SESSION['permisos_vista'] = [
                'idUsuarioSel' => $idUsuarioSel,
                'idEmpresaSel' => $idEmpresaSel,
                'usuarioSel' => $usuarioSel,
                'empresaSel' => $empresaSel,
                'modulos' => $modulos,
            ];
            $this->redirect(BASE_URL . self::BASE_PATH . '?v=1');
        }

        if (isset($_GET['limpiar']) && $_GET['limpiar'] === '1') {
            unset($_SESSION['permisos_vista']);
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if (!isset($_GET['v']) || $_GET['v'] !== '1') {
            unset($_SESSION['permisos_vista']);
        }

        if (isset($_SESSION['permisos_vista'])) {
            $v = $_SESSION['permisos_vista'];
            $idUsuarioSel = (int) ($v['idUsuarioSel'] ?? 0);
            $idEmpresaSel = (int) ($v['idEmpresaSel'] ?? 0);
            $usuarioSel = $v['usuarioSel'] ?? null;
            $empresaSel = $v['empresaSel'] ?? null;
            $modulos = $v['modulos'] ?? [];
        } else {
            if ($idUsuarioSel > 0) {
                $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuarioSel);
            }
            if ($idEmpresaSel > 0 && $usuarioSel) {
                $empresas = $this->modelEmpresa->getEmpresasParaPermisos($idUsuarioSel, $idActual, $nivel);
                $empresaSel = $this->buscarEmpresaEnLista($empresas, $idEmpresaSel);
                if (!$empresaSel) {
                    $empresaSel = ['id_empresa' => $idEmpresaSel, 'nombre_comercial' => 'Empresa #' . $idEmpresaSel, 'ruc' => ''];
                }
            }
        }

        $rowsUsuarios = $this->modelEmpresa->getUsuariosParaSelect($idActual, $nivel, '', 500);
        $opcionesUsuarios = array_map(function ($r) {
            return ['value' => (int)$r['id'], 'text' => ($r['nombre'] ?? '') . ' (' . ($r['cedula'] ?? '') . ')'];
        }, $rowsUsuarios);
        $usuarioActual = $this->modelEmpresa->getUsuarioPorId($idActual);
        if ($usuarioActual) {
            $optActual = ['value' => (int)$idActual, 'text' => ($usuarioActual['nombre'] ?? '') . ' (' . ($usuarioActual['cedula'] ?? '') . ')'];
            $existe = false;
            foreach ($opcionesUsuarios as $o) {
                if (($o['value'] ?? 0) === $idActual) { $existe = true; break; }
            }
            if (!$existe) {
                array_unshift($opcionesUsuarios, $optActual);
            }
        }

        $rowsEmpresas = $this->modelEmpresa->getEmpresasParaSelect($idUsuarioSel ?: 0, $idActual, $nivel, '', 500);
        $opcionesEmpresas = array_map(function ($r) {
            $text = $r['nombre_comercial'] ?? $r['ruc'] ?? 'Empresa';
            if (!empty($r['ruc'])) $text .= ' (' . $r['ruc'] . ')';
            return ['value' => (int)($r['id_empresa'] ?? $r['id'] ?? 0), 'text' => $text];
        }, $rowsEmpresas);

        $this->viewWithLayout('layouts.main', 'permisosModulos.index', [
            'titulo' => 'Permisos de módulos a usuarios',
            'nivel' => $nivel,
            'idUsuarioSel' => $idUsuarioSel,
            'idEmpresaSel' => $idEmpresaSel,
            'usuarioSel' => $usuarioSel,
            'empresaSel' => $empresaSel,
            'modulos' => $modulos,
            'opcionesUsuarios' => $opcionesUsuarios,
            'opcionesEmpresas' => $opcionesEmpresas,
        ]);
    }

    public function usuariosJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $rows = $this->modelEmpresa->getUsuariosParaSelect($idActual, $nivel, $buscar);
        $out = array_map(function ($r) {
            return ['value' => (int)$r['id'], 'text' => ($r['nombre'] ?? '') . ' (' . ($r['cedula'] ?? '') . ')'];
        }, $rows);
        $this->json($out);
    }

    public function empresasJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $idUsuario = (int) ($_GET['u'] ?? 0);
        $buscar = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $rows = $this->modelEmpresa->getEmpresasParaSelect($idUsuario, $idActual, $nivel, $buscar);
        $out = array_map(function ($r) {
            $text = $r['nombre_comercial'] ?? $r['ruc'] ?? 'Empresa';
            if (!empty($r['ruc'])) $text .= ' (' . $r['ruc'] . ')';
            return ['value' => (int)($r['id_empresa'] ?? $r['id'] ?? 0), 'text' => $text];
        }, $rows);
        $this->json($out);
    }

    public function guardar(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        if ($idUsuario <= 0) {
            $_SESSION['permisos_msg'] = ['danger', 'Usuario no válido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if (!$this->puedeGestionarUsuario($idActual, $nivel, $idUsuario)) {
            $_SESSION['permisos_msg'] = ['danger', 'No tiene permiso para gestionar ese usuario.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $permisos = [];
        $idModuloPorSub = [];
        foreach ($_POST['perm'] ?? [] as $idSub => $vals) {
            $idSub = (int) $idSub;
            if ($idSub <= 0) continue;
            $ver = !empty($vals['ver']);
            $crear = !empty($vals['crear']);
            $actualizar = !empty($vals['actualizar']);
            $eliminar = !empty($vals['eliminar']);
            // Solo guardar filas con al menos un permiso marcado
            if (!$ver && !$crear && !$actualizar && !$eliminar) continue;
            $permisos[$idSub] = [
                'ver' => $ver,
                'crear' => $crear,
                'actualizar' => $actualizar,
                'eliminar' => $eliminar,
            ];
            $idModuloPorSub[$idSub] = (int)($vals['id_modulo'] ?? 0);
        }

        if ($idEmpresa <= 0) {
            $_SESSION['permisos_msg'] = ['danger', 'Debe seleccionar una empresa.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        if ($this->modelPermiso->guardarPermisos($idUsuario, $idEmpresa, $permisos, $idModuloPorSub)) {
            $_SESSION['permisos_msg'] = ['success', 'Permisos guardados correctamente.'];
            $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuario);
            $empresas = $this->modelEmpresa->getEmpresasParaPermisos($idUsuario, $idActual, $nivel);
            $empresaSel = $this->buscarEmpresaEnLista($empresas, $idEmpresa);
            if (!$empresaSel) {
                $empresaSel = ['id_empresa' => $idEmpresa, 'nombre_comercial' => 'Empresa #' . $idEmpresa, 'ruc' => ''];
            }
            $rows = $this->modelPermiso->getModulosConSubmodulosParaPermisos($idActual, $idEmpresa, $nivel);
            $permisosActualizados = $this->modelPermiso->getPermisosDeUsuario($idUsuario, $idEmpresa);
            $modulos = $this->agruparPorModuloOrdenado($rows, $permisosActualizados, $idUsuario, $idEmpresa);
            $_SESSION['permisos_vista'] = [
                'idUsuarioSel' => $idUsuario,
                'idEmpresaSel' => $idEmpresa,
                'usuarioSel' => $usuarioSel,
                'empresaSel' => $empresaSel,
                'modulos' => $modulos,
            ];
        } else {
            $_SESSION['permisos_msg'] = ['danger', 'Error al guardar permisos.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . '?v=1');
    }

    private function agruparPorModuloOrdenado(array $rows, array $permisos, int $idUsuario, int $idEmpresa): array
    {
        $modulos = [];
        $asignados = [];

        foreach ($rows as $r) {
            $idMod = (int) ($r['id_modulo'] ?? 0);
            $idSub = (int) ($r['id_submodulo'] ?? 0);
            if ($idMod === 0 || $idSub === 0) continue;

            if (!isset($modulos[$idMod])) {
                $modulos[$idMod] = [
                    'id_modulo' => $idMod,
                    'nombre_modulo' => $r['nombre_modulo'] ?? '',
                    'submodulos' => [],
                ];
            }

            $p = $permisos[$idSub] ?? ['ver' => 0, 'crear' => 0, 'actualizar' => 0, 'eliminar' => 0];
            $asignado = isset($permisos[$idSub]);
            $sub = [
                'id_submodulo' => $idSub,
                'nombre_submodulo' => $r['nombre_submodulo'] ?? '',
                'ver' => (int)($p['ver'] ?? 0),
                'crear' => (int)($p['crear'] ?? 0),
                'actualizar' => (int)($p['actualizar'] ?? 0),
                'eliminar' => (int)($p['eliminar'] ?? 0),
                'asignado' => $asignado,
            ];
            $modulos[$idMod]['submodulos'][] = $sub;
        }

        foreach ($modulos as &$mod) {
            usort($mod['submodulos'], function ($a, $b) {
                if ($a['asignado'] !== $b['asignado']) return $a['asignado'] ? -1 : 1;
                return strcmp($a['nombre_submodulo'], $b['nombre_submodulo']);
            });
        }
        return array_values($modulos);
    }

    private function buscarUsuarioEnLista(array $rows, int $id): ?array
    {
        foreach ($rows as $r) {
            if ((int)($r['id_usuario'] ?? 0) === $id) return $r;
        }
        return null;
    }

    private function buscarEmpresaEnLista(array $empresas, int $id): ?array
    {
        foreach ($empresas as $e) {
            if ((int)($e['id_empresa'] ?? 0) === $id) return $e;
        }
        return null;
    }

    private function puedeGestionarUsuario(int $idActual, int $nivel, int $idUsuarioDestino): bool
    {
        if ($idActual === $idUsuarioDestino) return true;
        if ($nivel >= 3) return true;
        $data = $this->modelEmpresa->getUsuariosAsignables($idActual, $nivel, '', 1, 1000);
        foreach ($data['rows'] as $r) {
            if ((int)($r['id_usuario'] ?? 0) === $idUsuarioDestino) return true;
        }
        return false;
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['permisos_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
