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
        // El usuario NO se preselecciona: el selector debe quedar vacío para que se vea
        // como un buscador (placeholder "Buscar usuario..."), igual que el de empresa.
        // Con un nombre ya puesto, los usuarios creían que el campo no permitía buscar.
        $mostrar = isset($_GET['mostrar']) && $_GET['mostrar'] === '1';

        $modulos = [];
        $usuarioSel = null;
        $empresaSel = null;
        $empresaAsignada = true;

        if ($mostrar && $idUsuarioSel > 0 && $idEmpresaSel > 0) {
            if ($idUsuarioSel > 0) {
                $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuarioSel);
            }
            if ($idEmpresaSel > 0 && $usuarioSel) {
                $empresaSel = $this->resolverEmpresaSel($idUsuarioSel, $idActual, $nivel, $idEmpresaSel);
            }
            $empresaAsignada = $this->empresaEstaAsignada($idUsuarioSel, $idEmpresaSel, $usuarioSel);
            $rows = $this->modelPermiso->getModulosConSubmodulosParaPermisos($idActual, $idEmpresaSel, $nivel);
            $permisos = $this->modelPermiso->getPermisosDeUsuario($idUsuarioSel, $idEmpresaSel);
            $modulos = $this->agruparPorModuloOrdenado($rows, $permisos, $idUsuarioSel, $idEmpresaSel);

            $_SESSION['permisos_vista'] = [
                'idUsuarioSel' => $idUsuarioSel,
                'idEmpresaSel' => $idEmpresaSel,
                'usuarioSel' => $usuarioSel,
                'empresaSel' => $empresaSel,
                'empresaAsignada' => $empresaAsignada,
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
            $empresaAsignada = (bool) ($v['empresaAsignada'] ?? true);
        } else {
            if ($idUsuarioSel > 0) {
                $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuarioSel);
            }
            if ($idEmpresaSel > 0 && $usuarioSel) {
                $empresaSel = $this->resolverEmpresaSel($idUsuarioSel, $idActual, $nivel, $idEmpresaSel);
            }
            $empresaAsignada = $this->empresaEstaAsignada($idUsuarioSel, $idEmpresaSel, $usuarioSel);
        }

        $rowsUsuarios = $this->modelEmpresa->getUsuariosParaSelect($idActual, $nivel, '', 500);
        $opcionesUsuarios = array_map(function ($r) {
            return ['value' => (int)$r['id'], 'text' => ($r['nombre'] ?? '') . ' (' . ($r['cedula'] ?? '') . ')', 'nivel' => (int)($r['nivel'] ?? 0)];
        }, $rowsUsuarios);
        $usuarioActual = $this->modelEmpresa->getUsuarioPorId($idActual);
        if ($usuarioActual) {
            $optActual = ['value' => (int)$idActual, 'text' => ($usuarioActual['nombre'] ?? '') . ' (' . ($usuarioActual['cedula'] ?? '') . ')', 'nivel' => (int)($usuarioActual['nivel'] ?? 0)];
            $existe = false;
            foreach ($opcionesUsuarios as $o) {
                if (($o['value'] ?? 0) === $idActual) { $existe = true; break; }
            }
            if (!$existe) {
                array_unshift($opcionesUsuarios, $optActual);
            }
        }

        // El superadministrador trabaja con TODAS las empresas activas del sistema,
        // estén o no asignadas al usuario; el administrador solo con las asignadas.
        $rowsEmpresas = [];
        if ($nivel >= 3) {
            // Sin usuario elegido no hay nada que marcar como asignado: la lista la
            // pide el JS por AJAX en cuanto se selecciona el usuario.
            if ($idUsuarioSel > 0) {
                $rowsEmpresas = $this->modelEmpresa->getTodasEmpresasConAsignacion($idUsuarioSel, '', 500);
            }
        } else {
            $rowsEmpresas = $this->modelEmpresa->getEmpresasParaSelect($idUsuarioSel ?: 0, $idActual, $nivel, '', 500);
        }
        $opcionesEmpresas = array_map(function ($r) {
            return $this->opcionEmpresa($r);
        }, $rowsEmpresas);

        $limiteUsuarios = null;
        $idEmpresaActual = (int) ($_SESSION['id_empresa'] ?? 0);
        if ($idEmpresaActual > 0) {
            $limiteUsuarios = $this->modelEmpresa->getLimiteUsuariosEmpresa($idEmpresaActual);
        }

        $combosActivos = (new \App\models\ComboSubmodulo())->getActivos();

        $this->viewWithLayout('layouts.main', 'permisosModulos.index', [
            'titulo' => 'Permisos de módulos a usuarios',
            'nivel' => $nivel,
            'idUsuarioSel' => $idUsuarioSel,
            'idEmpresaSel' => $idEmpresaSel,
            'usuarioSel' => $usuarioSel,
            'empresaSel' => $empresaSel,
            'empresaAsignada' => $empresaAsignada,
            'modulos' => $modulos,
            'opcionesUsuarios' => $opcionesUsuarios,
            'opcionesEmpresas' => $opcionesEmpresas,
            'limiteUsuarios' => $limiteUsuarios,
            'combosActivos' => $combosActivos,
        ]);
    }

    public function usuariosJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['q'] ?? $_GET['b'] ?? '');
        $rows = $this->modelEmpresa->getUsuariosParaSelect($idActual, $nivel, $buscar, 200);
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
        // Superadministrador: todas las empresas activas del sistema, marcando cuáles
        // ya tiene asignadas el usuario. Administrador: solo las asignadas.
        $rows = $nivel >= 3
            ? $this->modelEmpresa->getTodasEmpresasConAsignacion($idUsuario, $buscar, 500)
            : $this->modelEmpresa->getEmpresasParaSelect($idUsuario, $idActual, $nivel, $buscar);
        $out = array_map(function ($r) {
            return $this->opcionEmpresa($r);
        }, $rows);
        $this->json($out);
    }

    /**
     * Empresas que el usuario actual puede asignar al crear un usuario. AJAX/JSON.
     * Superadministrador: todas las empresas activas. Administrador: solo las suyas.
     */
    public function empresasAsignablesJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['q'] ?? $_GET['b'] ?? '');

        $rows = $this->modelEmpresa->getEmpresasAsignablesParaSelect($idActual, $nivel, $buscar, 200);
        $out = array_map(function ($r) {
            return $this->opcionEmpresa($r);
        }, $rows);
        $this->json($out);
    }
    /**
     * Guardado inmediato de un solo submódulo vía AJAX (JSON).
     */
    public function guardarUno(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idModulo  = (int) ($_POST['id_modulo'] ?? 0);
        $idSub     = (int) ($_POST['id_submodulo'] ?? 0);
        $idActual  = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        if ($idUsuario <= 0 || $idEmpresa <= 0 || $idModulo <= 0 || $idSub <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        if (!$this->puedeGestionarUsuario($idActual, $nivel, $idUsuario)) {
            echo json_encode(['ok' => false, 'error' => 'Sin permiso para gestionar este usuario.']);
            exit;
        }

        $p = [
            'ver'        => !empty($_POST['ver']),
            'crear'      => !empty($_POST['crear']),
            'actualizar' => !empty($_POST['actualizar']),
            'eliminar'   => !empty($_POST['eliminar']),
            't'          => !empty($_POST['t']),
        ];

        // El superadministrador puede marcar permisos en una empresa que el usuario
        // todavía no tiene asignada: la asignación se crea aquí, si no el permiso
        // quedaría guardado pero el usuario nunca podría entrar a esa empresa.
        // Solo al conceder algo: desmarcar la última casilla no debe asignar nada.
        $empresaRecienAsignada = false;
        if (in_array(true, $p, true)) {
            $empresaRecienAsignada = $this->asegurarEmpresaAsignada($idUsuario, $idEmpresa, $idActual, $nivel);
        }

        $ok = $this->modelPermiso->guardarPermisoSubmodulo($idUsuario, $idEmpresa, $idModulo, $idSub, $p, $idActual);

        // Mantener la vista en sesión sincronizada
        if ($ok && isset($_SESSION['permisos_vista']['modulos'])) {
            foreach ($_SESSION['permisos_vista']['modulos'] as &$mod) {
                foreach ($mod['submodulos'] as &$s) {
                    if ((int)$s['id_submodulo'] === $idSub) {
                        $s['ver']        = $p['ver'] ? 1 : 0;
                        $s['crear']      = $p['crear'] ? 1 : 0;
                        $s['actualizar'] = $p['actualizar'] ? 1 : 0;
                        $s['eliminar']   = $p['eliminar'] ? 1 : 0;
                        $s['t']          = $p['t'] ? 1 : 0;
                    }
                }
                unset($s);
            }
            unset($mod);
        }

        if ($ok && $empresaRecienAsignada) {
            $_SESSION['permisos_vista']['empresaAsignada'] = true;
        }

        echo json_encode([
            'ok'                => $ok,
            'empresa_asignada'  => $empresaRecienAsignada,
            'error'             => $ok ? null : 'Error al guardar.',
        ]);
        exit;
    }

    /**
     * Copia (REEMPLAZAR) los permisos de un usuario+empresa origen a un usuario+empresa destino. AJAX/JSON.
     */
    public function copiarPermisos(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $idUsuarioOrigen = (int) ($_POST['id_usuario_origen'] ?? 0);
        $idEmpresaOrigen = (int) ($_POST['id_empresa_origen'] ?? 0);
        $idUsuarioDestino = (int) ($_POST['id_usuario_destino'] ?? 0);
        $idEmpresaDestino = (int) ($_POST['id_empresa_destino'] ?? 0);
        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);

        if ($idUsuarioOrigen <= 0 || $idEmpresaOrigen <= 0 || $idUsuarioDestino <= 0 || $idEmpresaDestino <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        if ($idUsuarioOrigen === $idUsuarioDestino && $idEmpresaOrigen === $idEmpresaDestino) {
            echo json_encode(['ok' => false, 'error' => 'El origen y el destino son iguales.']);
            exit;
        }

        // El gestor debe poder gestionar ambos usuarios
        if (!$this->puedeGestionarUsuario($idActual, $nivel, $idUsuarioOrigen)
            || !$this->puedeGestionarUsuario($idActual, $nivel, $idUsuarioDestino)) {
            echo json_encode(['ok' => false, 'error' => 'Sin permiso para gestionar alguno de los usuarios.']);
            exit;
        }

        // Validar combinación de niveles permitida:
        // admin→admin (2→2), admin→usuario (2→1), usuario→usuario (1→1).
        // No se permite usuario→admin (1→2) ni copiar desde/hacia superadministradores (nivel 3).
        $usuOrigen  = $this->modelEmpresa->getUsuarioPorId($idUsuarioOrigen);
        $usuDestino = $this->modelEmpresa->getUsuarioPorId($idUsuarioDestino);
        $nivelOrigen  = (int) ($usuOrigen['nivel'] ?? 0);
        $nivelDestino = (int) ($usuDestino['nivel'] ?? 0);

        if ($nivelOrigen >= 3 || $nivelDestino >= 3) {
            echo json_encode(['ok' => false, 'error' => 'No se pueden copiar permisos desde o hacia un superadministrador.']);
            exit;
        }
        if ($nivelOrigen < 1 || $nivelDestino < 1) {
            echo json_encode(['ok' => false, 'error' => 'Nivel de usuario no válido.']);
            exit;
        }
        // Solo permitido cuando el origen tiene nivel mayor o igual al destino
        if ($nivelOrigen < $nivelDestino) {
            echo json_encode(['ok' => false, 'error' => 'Un usuario (nivel 1) no puede copiar permisos a un administrador (nivel 2).']);
            exit;
        }

        // Empresas válidas. El superadministrador trabaja con cualquier empresa activa
        // del sistema (la asignación al usuario destino se crea más abajo); el
        // administrador sigue limitado a las empresas ya asignadas.
        if ($nivel >= 3) {
            if (!$this->modelEmpresa->getEmpresaActivaPorId($idEmpresaOrigen)) {
                echo json_encode(['ok' => false, 'error' => 'La empresa origen no existe o no está activa.']);
                exit;
            }
            if (!$this->modelEmpresa->getEmpresaActivaPorId($idEmpresaDestino)) {
                echo json_encode(['ok' => false, 'error' => 'La empresa destino no existe o no está activa.']);
                exit;
            }
        } else {
            // La empresa origen debe ser accesible para el usuario origen y para el gestor
            $empresasOrigen = $this->modelEmpresa->getEmpresasParaPermisos($idUsuarioOrigen, $idActual, $nivel);
            if (!$this->buscarEmpresaEnLista($empresasOrigen, $idEmpresaOrigen)) {
                echo json_encode(['ok' => false, 'error' => 'La empresa origen no está asignada al usuario origen.']);
                exit;
            }

            // La empresa destino debe estar asignada al usuario destino
            $empresasDestino = $this->modelEmpresa->getEmpresasParaPermisos($idUsuarioDestino, $idActual, $nivel);
            if (!$this->buscarEmpresaEnLista($empresasDestino, $idEmpresaDestino)) {
                echo json_encode(['ok' => false, 'error' => 'La empresa destino no está asignada al usuario destino.']);
                exit;
            }
        }

        $empresaRecienAsignada = $this->asegurarEmpresaAsignada($idUsuarioDestino, $idEmpresaDestino, $idActual, $nivel);

        $ok = $this->modelPermiso->copiarPermisosUsuario($idUsuarioOrigen, $idEmpresaOrigen, $idUsuarioDestino, $idEmpresaDestino, $idActual);
        echo json_encode([
            'ok'               => $ok,
            'empresa_asignada' => $empresaRecienAsignada,
            'error'            => $ok ? null : 'Error al copiar los permisos.',
        ]);
        exit;
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
            $t = !empty($vals['t']);
            // Solo guardar filas con al menos un permiso marcado (o si tiene t, aunque t sin 'ver' no tiene mucho sentido, nos aseguramos)
            if (!$ver && !$crear && !$actualizar && !$eliminar && !$t) continue;
            $permisos[$idSub] = [
                'ver' => $ver,
                'crear' => $crear,
                'actualizar' => $actualizar,
                'eliminar' => $eliminar,
                't' => $t,
            ];
            $idModuloPorSub[$idSub] = (int)($vals['id_modulo'] ?? 0);
        }

        if ($idEmpresa <= 0) {
            $_SESSION['permisos_msg'] = ['danger', 'Debe seleccionar una empresa.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        // Igual que en el guardado individual: si el superadministrador está asignando
        // permisos en una empresa que el usuario no tiene, se le asigna la empresa.
        // Un guardado que deja todo en blanco no asigna nada.
        $empresaRecienAsignada = false;
        if (!empty($permisos)) {
            $empresaRecienAsignada = $this->asegurarEmpresaAsignada($idUsuario, $idEmpresa, $idActual, $nivel);
        }

        if ($this->modelPermiso->guardarPermisos($idUsuario, $idEmpresa, $permisos, $idModuloPorSub, $idActual)) {
            $_SESSION['permisos_msg'] = ['success', $empresaRecienAsignada
                ? 'Permisos guardados. La empresa se asignó al usuario.'
                : 'Permisos guardados correctamente.'];
            $usuarioSel = $this->modelEmpresa->getUsuarioPorId($idUsuario);
            $empresaSel = $this->resolverEmpresaSel($idUsuario, $idActual, $nivel, $idEmpresa);
            $rows = $this->modelPermiso->getModulosConSubmodulosParaPermisos($idActual, $idEmpresa, $nivel);
            $permisosActualizados = $this->modelPermiso->getPermisosDeUsuario($idUsuario, $idEmpresa);
            $modulos = $this->agruparPorModuloOrdenado($rows, $permisosActualizados, $idUsuario, $idEmpresa);
            $_SESSION['permisos_vista'] = [
                'idUsuarioSel' => $idUsuario,
                'idEmpresaSel' => $idEmpresa,
                'usuarioSel' => $usuarioSel,
                'empresaSel' => $empresaSel,
                'empresaAsignada' => $this->empresaEstaAsignada($idUsuario, $idEmpresa, $usuarioSel),
                'modulos' => $modulos,
            ];
        } else {
            $_SESSION['permisos_msg'] = ['danger', 'Error al guardar permisos.'];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . '?v=1');
    }

    /**
     * Asigna al usuario la empresa seleccionada. Exclusivo del superadministrador:
     * desde esta pantalla puede elegir cualquier empresa activa del sistema, y esta
     * acción crea la asignación (empresa_asignada) sin salir del módulo. AJAX/JSON.
     */
    public function asignarEmpresa(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            exit;
        }

        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        $idActual  = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        if ($idUsuario <= 0 || $idEmpresa <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        $usuario = $this->modelEmpresa->getUsuarioPorId($idUsuario);
        if (!$usuario) {
            echo json_encode(['ok' => false, 'error' => 'El usuario no existe o está inactivo.']);
            exit;
        }
        if ((int) ($usuario['nivel'] ?? 0) >= 3) {
            echo json_encode(['ok' => false, 'error' => 'Un superadministrador ya accede a todas las empresas; no necesita asignación.']);
            exit;
        }
        if (!$this->modelEmpresa->getEmpresaActivaPorId($idEmpresa)) {
            echo json_encode(['ok' => false, 'error' => 'La empresa no existe o no está activa.']);
            exit;
        }
        if ($this->modelEmpresa->estaEmpresaAsignada($idEmpresa, $idUsuario)) {
            $_SESSION['permisos_vista']['empresaAsignada'] = true;
            echo json_encode(['ok' => true, 'ya_estaba' => true]);
            exit;
        }

        $ok = $this->asegurarEmpresaAsignada($idUsuario, $idEmpresa, $idActual, $nivel);
        if ($ok) {
            $_SESSION['permisos_vista']['empresaAsignada'] = true;
        }

        echo json_encode([
            'ok'    => $ok,
            'error' => $ok ? null : 'No se pudo asignar la empresa.',
        ]);
        exit;
    }

    /**
     * Opcion para los selectores de empresa. Cuando la consulta trae la marca
     * "asignada" (solo el superadministrador), se agrupa en el desplegable para
     * distinguir las empresas que el usuario ya tiene de las que aún no.
     */
    private function opcionEmpresa(array $r): array
    {
        $text = $r['nombre_comercial'] ?? $r['ruc'] ?? 'Empresa';
        if (!empty($r['ruc'])) $text .= ' (' . $r['ruc'] . ')';

        $opt = ['value' => (int) ($r['id_empresa'] ?? $r['id'] ?? 0), 'text' => $text];

        if (array_key_exists('asignada', $r)) {
            $asignada = (int) $r['asignada'] === 1;
            $opt['asignada'] = $asignada;
            $opt['grupo'] = $asignada ? 'asignadas' : 'otras';
        }
        return $opt;
    }

    /**
     * Empresa seleccionada para mostrarla en pantalla. El superadministrador puede
     * trabajar con cualquier empresa activa (esté o no asignada); el administrador
     * solo con las empresas asignadas al usuario que él también tiene.
     */
    private function resolverEmpresaSel(int $idUsuario, int $idActual, int $nivel, int $idEmpresa): array
    {
        $empresa = null;
        if ($idEmpresa > 0) {
            if ($nivel >= 3) {
                $empresa = $this->modelEmpresa->getEmpresaActivaPorId($idEmpresa);
            } else {
                $empresas = $this->modelEmpresa->getEmpresasParaPermisos($idUsuario, $idActual, $nivel);
                $empresa = $this->buscarEmpresaEnLista($empresas, $idEmpresa);
            }
        }
        if (!$empresa) {
            $empresa = ['id_empresa' => $idEmpresa, 'nombre_comercial' => 'Empresa #' . $idEmpresa, 'ruc' => ''];
        }
        return $empresa;
    }

    /**
     * False solo cuando queda algo pendiente: el usuario destino necesita la empresa
     * asignada y todavía no la tiene. Un superadministrador (nivel 3 destino) accede
     * a todas las empresas sin asignación, así que nunca está pendiente.
     */
    private function empresaEstaAsignada(int $idUsuario, int $idEmpresa, ?array $usuarioSel): bool
    {
        if ($idUsuario <= 0 || $idEmpresa <= 0) return true;
        if ((int) ($usuarioSel['nivel'] ?? 0) >= 3) return true;
        return $this->modelEmpresa->estaEmpresaAsignada($idEmpresa, $idUsuario);
    }

    /**
     * Crea la asignación de empresa cuando el superadministrador guarda permisos en
     * una empresa que el usuario todavía no tiene. Sin esto el permiso quedaría
     * guardado pero el usuario no podría entrar nunca a esa empresa.
     * Devuelve true solo si la asignación se creó en esta llamada.
     */
    private function asegurarEmpresaAsignada(int $idUsuario, int $idEmpresa, int $idActual, int $nivel): bool
    {
        if ($nivel < 3 || $idUsuario <= 0 || $idEmpresa <= 0) return false;

        $usuario = $this->modelEmpresa->getUsuarioPorId($idUsuario);
        if (!$usuario || (int) ($usuario['nivel'] ?? 0) >= 3) return false;
        if ($this->modelEmpresa->estaEmpresaAsignada($idEmpresa, $idUsuario)) return false;
        if (!$this->modelEmpresa->getEmpresaActivaPorId($idEmpresa)) return false;

        $ok = $this->modelEmpresa->asignar($idEmpresa, $idUsuario, $idActual);
        if ($ok) {
            try {
                (new \App\Services\LogSistemaService())->registrar(
                    $idActual,
                    $idEmpresa,
                    'asignar_empresa_usuario',
                    'empresa_asignada',
                    null,
                    null,
                    [
                        'id_usuario' => $idUsuario,
                        'id_empresa' => $idEmpresa,
                        'origen'     => 'permisos-modulos',
                    ]
                );
            } catch (\Throwable $e) {
                // La auditoría no debe bloquear la operación.
            }
        }
        return $ok;
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
                't' => (int)($p['t'] ?? 0),
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
