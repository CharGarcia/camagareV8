<?php
/**
 * Controlador EmpresasSistema - Gestión de empresas del sistema
 * Tabla empresas. Crear empresas, ver ficha con General, Usuarios asignados, Cobro/Vigencia.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Empresa;
use App\models\EmpresaAsignada;
use App\models\EmpresaDocumento;
use App\models\Provincia;
use App\models\Ciudad;
use App\Services\SriIdentificationService;

class EmpresasSistemaController extends Controller
{
    private Empresa $model;
    private const BASE_PATH = '/config/empresas-sistema';

    public function __construct()
    {
        parent::__construct();
        $this->model = new Empresa();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idActual = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage = 20;

        if (!in_array($ordenCol, Empresa::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_comercial';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $result = $this->model->getTodosParaListado($idActual, $nivel, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'empresasSistema.index', [
            'titulo' => 'Empresas del sistema',
            'fullWidth' => true,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'nivel' => $nivel,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requireNivel(3);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $data = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'nombre_comercial' => trim($_POST['nombre_comercial'] ?? ''),
            'ruc' => trim($_POST['ruc'] ?? ''),
            'establecimiento' => trim($_POST['establecimiento'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? '01'),
            'nom_rep_legal' => trim($_POST['nom_rep_legal'] ?? ''),
            'ced_rep_legal' => trim($_POST['ced_rep_legal'] ?? ''),
            'mail' => trim($_POST['mail'] ?? ''),
            'cod_prov' => trim($_POST['cod_prov'] ?? ''),
            'cod_ciudad' => trim($_POST['cod_ciudad'] ?? ''),
            'nombre_contador' => trim($_POST['nombre_contador'] ?? ''),
            'ruc_contador' => trim($_POST['ruc_contador'] ?? ''),
            'estado' => trim($_POST['estado'] ?? '1'),
            'id_usuario' => (string) $idUsuario,
            'valor_cobro' => $_POST['valor_cobro'] ?? null,
            'periodo_vigencia_desde' => trim($_POST['periodo_vigencia_desde'] ?? ''),
            'periodo_vigencia_hasta' => trim($_POST['periodo_vigencia_hasta'] ?? ''),
            'estado_pago' => trim($_POST['estado_pago'] ?? 'pendiente'),
        ];

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        try {
            $id = $this->model->crear($data);
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            if ($idUsuario > 0) {
                $ea = new EmpresaAsignada();
                $ea->asignar($id, $idUsuario, $idUsuario);
            }
            $empCreada = $this->model->getPorId($id);
            if ($empCreada) {
                $_SESSION['id_empresa'] = $id;
                $_SESSION['ruc_empresa'] = $empCreada['ruc'] ?? '';
            }
            $_SESSION['empresas_msg'] = ['success', 'Empresa creada correctamente.'];
            if ($esAjax) {
                $this->json(['ok' => true, 'msg' => 'Empresa creada correctamente.']);
                return;
            }
        } catch (\InvalidArgumentException $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => $e->getMessage()]);
                return;
            }
            $_SESSION['empresas_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['empresas_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'ID inválido.']);
                return;
            }
            $_SESSION['empresas_msg'] = ['danger', 'ID inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel < 3) {
            $empAsignada = new EmpresaAsignada();
            $misEmpresas = $empAsignada->getEmpresasDeUsuario((int) $_SESSION['id_usuario']);
            $ids = array_column($misEmpresas, 'id_empresa');
            if (!in_array($id, $ids)) {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'No tiene permiso para editar esta empresa.']);
                    return;
                }
                $_SESSION['empresas_msg'] = ['danger', 'No tiene permiso para editar esta empresa.'];
                $this->redirect(BASE_URL . self::BASE_PATH);
            }
        }

        $allKeys = ['nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion', 'telefono', 'mail', 'nom_rep_legal', 'ced_rep_legal', 'cod_prov', 'cod_ciudad', 'nombre_contador', 'ruc_contador', 'estado', 'valor_cobro', 'periodo_vigencia_desde', 'periodo_vigencia_hasta', 'estado_pago'];
        $data = [];
        foreach ($allKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                if ($k === 'valor_cobro') {
                    $data[$k] = $_POST[$k] === '' ? null : (float) $_POST[$k];
                } else {
                    $data[$k] = trim($_POST[$k] ?? '');
                }
                if ($k === 'estado' && $data[$k] === '') {
                    $data[$k] = '1';
                }
                if ($k === 'estado_pago' && $data[$k] === '') {
                    $data[$k] = 'pendiente';
                }
            }
        }

        try {
            if ($this->model->actualizar($id, $data)) {
                $_SESSION['empresas_msg'] = ['success', 'Empresa actualizada correctamente.'];
                if ($esAjax) {
                    $this->json(['ok' => true, 'msg' => 'Empresa actualizada correctamente.']);
                    return;
                }
            } else {
                if ($esAjax) {
                    $this->json(['ok' => false, 'error' => 'Error al actualizar.']);
                    return;
                }
                $_SESSION['empresas_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\InvalidArgumentException $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => $e->getMessage()]);
                return;
            }
            $_SESSION['empresas_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            if ($esAjax) {
                $this->json(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
                return;
            }
            $_SESSION['empresas_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    /**
     * AJAX: provincias (retorna JSON)
     */
    public function provinciasJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $model = new Provincia();
        $this->json(['provincias' => $model->getTodas()]);
    }

    /**
     * AJAX: ciudades por provincia (retorna JSON). GET: cod_prov
     */
    public function ciudadesJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        $codProv = trim($_GET['cod_prov'] ?? '');
        $model = new Ciudad();
        $ciudades = $codProv !== '' ? $model->getPorProvincia($codProv) : [];
        $this->json(['ciudades' => $ciudades]);
    }

    /**
     * AJAX: consulta identificación (RUC/cédula) al SRI. GET: numero
     */
    public function sriIdentificacionJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $numero = trim($_GET['numero'] ?? $_POST['numero'] ?? '');
        if ($numero === '') {
            $this->json(['ok' => false, 'error' => 'Ingrese el número de identificación.']);
            return;
        }

        $service = new SriIdentificationService();
        $resultado = $service->consultar($numero);
        $this->json($resultado);
    }

    /**
     * AJAX: usuarios asignados a una empresa (retorna JSON)
     */
    public function usuariosEmpresaJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idEmpresa = (int) ($_GET['id'] ?? 0);
        if ($idEmpresa <= 0) {
            $this->json(['usuarios' => []]);
            return;
        }

        $model = new EmpresaAsignada();
        $usuarios = $model->getUsuariosDeEmpresa($idEmpresa);

        $idActual = (int) $_SESSION['id_usuario'];
        $html = '';
        foreach ($usuarios as $u) {
            $nivel = (int) ($u['nivel'] ?? 1);
            $badge = $nivel >= 3 ? 'danger' : ($nivel >= 2 ? 'info' : 'secondary');
            $nivelTxt = $nivel >= 3 ? 'Super Admin' : ($nivel >= 2 ? 'Admin' : 'Usuario');
            $puedeQuitar = (int) ($u['usu_asignador'] ?? 0) === $idActual;
            $html .= '<tr><td>' . htmlspecialchars($u['nombre'] ?? '') . '</td>';
            $html .= '<td><code>' . htmlspecialchars($u['cedula'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($u['mail'] ?? '—') . '</td>';
            $html .= '<td><span class="badge bg-' . $badge . '">' . htmlspecialchars($nivelTxt) . '</span></td>';
            $html .= '<td class="text-end">';
            if ($puedeQuitar) {
                $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-usuario-empresa" data-id="' . (int)($u['id_registro'] ?? 0) . '" title="Quitar"><i class="bi bi-trash"></i></button>';
            } else {
                $html .= '<span class="text-muted small">Asignado por otro</span>';
            }
            $html .= '</td></tr>';
        }

        $this->json(['html' => $html, 'usuarios' => $usuarios]);
    }

    /**
     * AJAX: usuarios disponibles para asignar a una empresa (retorna JSON)
     */
    public function usuariosDisponiblesEmpresaJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idEmpresa = (int) ($_GET['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            $this->json(['usuarios' => []]);
            return;
        }

        $this->verificarAccesoEmpresa($idEmpresa);

        $buscar = trim($_GET['q'] ?? '');
        $idActual = (int) $_SESSION['id_usuario'];
        $nivel = (int) $_SESSION['nivel'];

        $model = new EmpresaAsignada();
        $usuarios = $model->getUsuariosDisponiblesParaEmpresa($idEmpresa, $idActual, $nivel, $buscar);

        $this->json(['usuarios' => $usuarios]);
    }

    /**
     * AJAX: documentos de una empresa (retorna JSON)
     */
    public function documentosEmpresaJson(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idEmpresa = (int) ($_GET['id'] ?? 0);
        if ($idEmpresa <= 0) {
            $this->json(['documentos' => [], 'html' => '']);
            return;
        }

        $this->verificarAccesoEmpresa($idEmpresa);

        $model = new EmpresaDocumento();
        $docs = $model->getPorEmpresa($idEmpresa);

        $base = rtrim(BASE_URL, '/');
        $html = '';
        foreach ($docs as $d) {
            $tipo = $d['tipo_documento'] ?? 'otro';
            $tipoTxt = match ($tipo) {
                'contrato' => 'Contrato',
                'ruc' => 'RUC',
                'licencia' => 'Licencia',
                'poder' => 'Poder',
                default => 'Otro',
            };
            $desc = htmlspecialchars($d['descripcion'] ?? $d['nombre_original'] ?? '');
            $urlDescarga = $base . '/config/empresas-sistema?action=descargarDocumento&id=' . (int) $d['id'];
            $html .= '<tr><td><span class="badge bg-secondary">' . $tipoTxt . '</span></td>';
            $html .= '<td>' . htmlspecialchars($d['nombre_original'] ?? '') . '</td>';
            $html .= '<td class="small text-muted">' . $desc . '</td>';
            $html .= '<td class="text-end"><a href="' . htmlspecialchars($urlDescarga) . '" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-download"></i></a> ';
            $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-doc" data-id="' . (int) $d['id'] . '" title="Eliminar"><i class="bi bi-trash"></i></button></td></tr>';
        }

        $this->json(['documentos' => $docs, 'html' => $html]);
    }

    /**
     * Subir documento para una empresa
     */
    public function uploadDocumento(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            $_SESSION['empresas_msg'] = ['danger', 'Empresa inválida.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $this->verificarAccesoEmpresa($idEmpresa);

        if (empty($_FILES['archivo']['name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['empresas_msg'] = ['danger', 'Seleccione un archivo válido.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $extPermitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx'];
        $nomOrig = $_FILES['archivo']['name'];
        $ext = strtolower(pathinfo($nomOrig, PATHINFO_EXTENSION));
        if (!in_array($ext, $extPermitidas, true)) {
            $_SESSION['empresas_msg'] = ['danger', 'Tipo de archivo no permitido. Use: ' . implode(', ', $extPermitidas)];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $dir = MVC_ROOT . '/storage/empresa_documentos';
        $nomGuardado = $idEmpresa . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nomOrig);
        $ruta = $dir . '/' . $nomGuardado;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta)) {
            $_SESSION['empresas_msg'] = ['danger', 'Error al guardar el archivo.'];
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $tipoDoc = trim($_POST['tipo_documento'] ?? 'otro');
        $descripcion = trim($_POST['descripcion'] ?? '');

        try {
            $model = new EmpresaDocumento();
            $model->crear($idEmpresa, $tipoDoc, $nomGuardado, $nomOrig, $descripcion !== '' ? $descripcion : null);
            $_SESSION['empresas_msg'] = ['success', 'Documento subido correctamente.'];
        } catch (\Throwable $e) {
            @unlink($ruta);
            $_SESSION['empresas_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    /**
     * Eliminar documento
     */
    public function deleteDocumento(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'msg' => 'ID inválido']);
            return;
        }

        $model = new EmpresaDocumento();
        $doc = $model->getPorId($id);
        if ($doc === null) {
            $this->json(['ok' => false, 'msg' => 'Documento no encontrado']);
            return;
        }

        $this->verificarAccesoEmpresa((int) $doc['id_empresa']);

        $ruta = MVC_ROOT . '/storage/empresa_documentos/' . ($doc['nombre_archivo'] ?? '');
        if (file_exists($ruta)) {
            @unlink($ruta);
        }
        $model->eliminar($id);
        $this->json(['ok' => true]);
    }

    /**
     * Descargar documento
     */
    public function descargarDocumento(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $model = new EmpresaDocumento();
        $doc = $model->getPorId($id);
        if ($doc === null) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $this->verificarAccesoEmpresa((int) $doc['id_empresa']);

        $ruta = MVC_ROOT . '/storage/empresa_documentos/' . ($doc['nombre_archivo'] ?? '');
        if (!file_exists($ruta)) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $nomOrig = $doc['nombre_original'] ?? 'documento';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($nomOrig) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    private function verificarAccesoEmpresa(int $idEmpresa): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 1);
        if ($nivel >= 3) return;

        $empAsignada = new EmpresaAsignada();
        $misEmpresas = $empAsignada->getEmpresasDeUsuario((int) $_SESSION['id_usuario']);
        $ids = array_column($misEmpresas, 'id_empresa');
        if (!in_array($idEmpresa, array_map('intval', $ids))) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'msg' => 'Sin permiso'], 403);
            }
            $_SESSION['empresas_msg'] = ['danger', 'No tiene permiso para esta empresa.'];
            header('Location: ' . BASE_URL . self::BASE_PATH);
            exit;
        }
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['empresas_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
