<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ProformaRepository;
use App\Services\modulos\ProformaService;
use App\Rules\modulos\ProformaRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class ProformasController extends BaseModuloController
{
    private ProformaRepository $repository;
    private ProformaService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/proformas';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ProformaRepository();
        $rules            = new ProformaRules();
        $logService       = new LogSistemaService();
        $this->service    = new ProformaService($this->repository, $rules, $logService);
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        $empresaModel     = new Empresa();
        $empresaData      = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = $this->cargarTodosPuntos($idEmpresa);

        $vendedorRepo = new \App\repositories\modulos\VendedorRepository();
        $vendedores   = $vendedorRepo->getListado($idEmpresa, '', 1, 1000, 'nombre', 'ASC')['rows'];

        $tarifasIva = (new \App\models\TarifaIva())->getActivos();

        $this->viewWithLayout('layouts.main', 'modulos/proformas/index', [
            'titulo'          => 'Proformas',
            'perm'            => $perm,
            'rows'            => $result['rows'],
            'total'           => $total,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'perPage'         => $perPage,
            'from'            => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'              => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'          => $buscar,
            'ordenCol'        => $ordenCol,
            'ordenDir'        => $ordenDir,
            'vistaConfig'     => $prefsVista,
            'rutaModulo'      => $this->getRutaModulo(),
            'empresa'         => $empresaData,
            'establecimientos' => $establecimientos,
            'puntos'          => $puntos,
            'vendedores'      => $vendedores,
            'tarifasIva'      => $tarifasIva,
            'fullWidth'       => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = (int) ceil($total / $perPage);
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($result['rows'])) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-text fs-3 d-block mb-2"></i>No se encontraron proformas.</td></tr>';
        } else {
            foreach ($result['rows'] as $r) {
                echo $this->renderFilaHtml($r);
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1)           ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = rtrim(BASE_URL, '/') . '/' . $this->getRutaModulo();
        $bEnc    = urlencode($buscar);
        $sEnc    = urlencode($ordenCol);
        $dEnc    = urlencode($ordenDir);

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => "{$urlBase}/export-pdf?b={$bEnc}&sort={$sEnc}&dir={$dEnc}",
            'excel_url'  => "{$urlBase}/export-excel?b={$bEnc}&sort={$sEnc}&dir={$dEnc}",
        ]);
        exit;
    }

    public function getProformaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido']);
            exit;
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
            echo json_encode(['ok' => false, 'mensaje' => 'Proforma no encontrada']);
            exit;
        }

        $productoRepo = new \App\repositories\modulos\ProductoRepository();
        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
            // Precios reales del producto (para el select de lista de precios al editar)
            $d['precios_lista'] = !empty($d['id_producto'])
                ? $productoRepo->getPrecios((int) $d['id_producto'], $idEmpresa)
                : [];
        }
        unset($d);

        echo json_encode([
            'ok'             => true,
            'cabecera'       => $cabecera,
            'detalles'       => $detalles,
            'info_adicional' => $this->repository->getInfoAdicional($id),
        ]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $rawBody = file_get_contents('php://input');
            $data    = !empty($rawBody) ? (json_decode($rawBody, true) ?? $_POST) : $_POST;

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = $idUsuario;

            // Tipo ambiente desde empresa (para compatibilidad con SecuencialRepository)
            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa);
            $data['tipo_ambiente'] = (string) ($empresa['tipo_ambiente'] ?? '1');

            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                $this->requireActualizar();
                $id = $this->service->actualizar($id, $data);
                $msg = 'Proforma actualizada correctamente.';
            } else {
                $this->requireCrear();
                $id = $this->service->crear($data);
                $msg = 'Proforma creada correctamente.';
            }

            $proforma = $this->repository->getPorId($id);
            $rowHtml  = $proforma ? $this->renderFilaHtml($proforma) : '';

            echo json_encode(['ok' => true, 'id' => $id, 'msg' => $msg, 'rowHtml' => $rowHtml]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $estado    = trim($_POST['estado'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id || !$estado) {
                throw new \RuntimeException('Parámetros inválidos.');
            }

            $this->requireActualizar();
            $this->service->cambiarEstado($id, $estado, $idEmpresa, $idUsuario);

            $proforma = $this->repository->getPorId($id);
            $rowHtml  = $proforma ? $this->renderFilaHtml($proforma) : '';

            echo json_encode(['ok' => true, 'rowHtml' => $rowHtml]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if (!$id) throw new \RuntimeException('ID requerido.');

            $this->requireEliminar();
            $ok = $this->service->eliminar($id, $idEmpresa, $idUsuario);

            echo json_encode(['ok' => $ok]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo      = new \App\repositories\modulos\ClienteRepository();
        // soloActivos = true: excluir clientes inactivos en la selección.
        $result    = $repo->getListado($idEmpresa, $q, 1, 10, 'nombre', 'ASC', null, true);
        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $q         = trim($_GET['q'] ?? '');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $repo      = new \App\repositories\modulos\ProductoRepository();
        $result    = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC', null, 'venta', true);

        $rows = array_map(function ($p) use ($repo, $idEmpresa) {
            $p['precios_lista'] = $repo->getPrecios((int)$p['id'], $idEmpresa);
            return $p;
        }, $result['rows']);

        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if (!$idPunto) {
            echo json_encode(['ok' => false, 'error' => 'Punto de emisión requerido.']);
            exit;
        }
        try {
            $res = $this->service->getSiguienteSecuencial($idPunto);
            echo json_encode(array_merge(['ok' => true], $res));
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getPuntosEmisionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEst = (int) ($_GET['id_establecimiento'] ?? 0);
        $puntos = (new Empresa())->getPuntosEmision($idEst);
        echo json_encode(['ok' => true, 'data' => $puntos]);
        exit;
    }

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
            http_response_code(404);
            echo 'Proforma no encontrada.';
            exit;
        }

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);

        $adicional = $this->repository->getInfoAdicional($id);

        try {
            $empresaModel = new Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
            $estabs       = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($estabs[0]['logo_ruta'])) {
                $empresa['logo_ruta'] = $estabs[0]['logo_ruta'];
            }

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'proforma');

            if ($plantilla) {
                $renderer->generar($plantilla, $cabecera, $detalles, [], $adicional, $empresa);
            } else {
                // No existe plantilla de proforma → generar una básica inline
                $this->renderPdfBasico($cabecera, $detalles, $adicional);
            }
        } catch (\Throwable $e) {
            $this->renderPdfBasico($cabecera, $detalles, $adicional);
        }
        exit;
    }

    /**
     * Envía la proforma por correo (PDF adjunto) a los destinatarios indicados.
     * Reutiliza EnvioDocumentosSRIService::enviarPdfSimple (igual maquinaria que factura).
     */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $correos   = trim((string) ($_POST['correos'] ?? ''));
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id)           { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }
        if ($correos === '') { echo json_encode(['ok' => false, 'mensaje' => 'Debe indicar al menos un correo.']); exit; }

        try {
            $cabecera = $this->repository->getPorId($id);
            if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
                echo json_encode(['ok' => false, 'mensaje' => 'Proforma no encontrada.']);
                exit;
            }

            $pdf = $this->generarPdfProformaString($id, $idEmpresa, $cabecera);
            if ($pdf === null || $pdf === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo generar el PDF de la proforma.']);
                exit;
            }

            $numero = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                    . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $empresaNombre = $empresa['razon_social'] ?? ($empresa['nombre_comercial'] ?? 'CaMaGaRe');

            $asunto = "Proforma {$numero}";
            $cuerpo = '<p>Estimado(a) ' . htmlspecialchars($cabecera['cliente_nombre'] ?? 'cliente') . ',</p>'
                    . '<p>Adjuntamos la proforma <strong>' . htmlspecialchars($numero) . '</strong> para su revisión.</p>'
                    . '<p>Saludos cordiales,<br>' . htmlspecialchars((string) $empresaNombre) . '</p>';

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa,
                $correos,
                (string) ($cabecera['cliente_nombre'] ?? ''),
                $asunto,
                $cuerpo,
                $pdf,
                "Proforma_{$numero}",
                (string) $empresaNombre
            );

            if ($enviado) {
                echo json_encode(['ok' => true, 'mensaje' => 'Correo enviado correctamente.']);
            } else {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.']);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Lista las plantillas de WhatsApp aprobadas por Meta y el teléfono del cliente.
     * (Mismo mecanismo que Facturas de Venta.)
     */
    public function getPlantillasWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id        = (int) ($_GET['id'] ?? 0);

        try {
            $config = (new \App\models\WhatsappConfig())->obtenerConfiguracion($idEmpresa);
            if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
                echo json_encode(['ok' => true, 'configurado' => false]);
                exit;
            }

            $todas = (new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa);
            // Excluir plantillas de enlaces de pago (flujo especial, no aplica a proformas)
            $excluir    = ['link_pago_payphone', 'link_pago_nuvei'];
            $plantillas = array_values(array_filter($todas, fn($p) => !in_array($p['nombre'], $excluir, true)));

            // Teléfono del cliente normalizado a formato Ecuador (593…)
            $telefonoCliente = '593';
            if ($id > 0) {
                $prof = $this->repository->getPorId($id);
                if ($prof && (int) ($prof['id_empresa'] ?? 0) === $idEmpresa && !empty($prof['id_cliente'])) {
                    $stmt = \App\core\Database::getConnection()->prepare(
                        "SELECT telefono FROM clientes WHERE id = ? AND id_empresa = ? AND eliminado = FALSE"
                    );
                    $stmt->execute([(int) $prof['id_cliente'], $idEmpresa]);
                    $tel = trim((string) ($stmt->fetchColumn() ?: ''));
                    if ($tel !== '') {
                        if (str_starts_with($tel, '0'))        $telefonoCliente = '593' . substr($tel, 1);
                        elseif (!str_starts_with($tel, '593'))  $telefonoCliente = '593' . $tel;
                        else                                    $telefonoCliente = $tel;
                    }
                }
            }

            // Plantilla por defecto: la que contenga "proforma"
            $idDefault = 0;
            foreach ($plantillas as $p) {
                if (stripos($p['nombre'], 'proforma') !== false) { $idDefault = (int) $p['id']; break; }
            }

            echo json_encode([
                'ok'                   => true,
                'configurado'          => true,
                'plantillas'           => $plantillas,
                'telefono_cliente'     => $telefonoCliente,
                'id_plantilla_default' => $idDefault,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Envía la proforma por WhatsApp (PDF adjunto) usando una plantilla aprobada por Meta.
     * Mapea las variables por posición: 1=Cliente, 2=Número, 3=Total.
     */
    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $idUsuario   = (int) $_SESSION['id_usuario'];
        $id          = (int) ($_POST['id'] ?? 0);
        $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);
        $telefono    = preg_replace('/[^0-9]/', '', trim((string) ($_POST['telefono'] ?? '')));

        if ($id <= 0 || $idPlantilla <= 0 || $telefono === '') {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }
        if (str_starts_with($telefono, '593') && strlen($telefono) !== 12) {
            echo json_encode(['ok' => false, 'error' => 'El teléfono para Ecuador (593) debe tener 12 dígitos.']);
            exit;
        }

        try {
            $cabecera = $this->repository->getPorId($id);
            if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
                echo json_encode(['ok' => false, 'error' => 'Proforma no encontrada.']);
                exit;
            }

            $db   = \App\core\Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ?");
            $stmt->execute([$idPlantilla, $idEmpresa]);
            $plantillaMeta = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$plantillaMeta || ($plantillaMeta['estado_meta'] ?? '') !== 'APPROVED') {
                echo json_encode(['ok' => false, 'error' => 'Plantilla no válida o no aprobada por Meta.']);
                exit;
            }

            $pdfString = $this->generarPdfProformaString($id, $idEmpresa, $cabecera);
            if (empty($pdfString)) {
                echo json_encode(['ok' => false, 'error' => 'No se pudo generar el PDF de la proforma.']);
                exit;
            }

            $numero        = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                           . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $nombreCliente = $cabecera['cliente_nombre'] ?? 'Cliente';
            $total         = number_format((float) ($cabecera['importe_total'] ?? 0), 2);

            $whatsappService = new \App\services\WhatsappService();

            // Subir el PDF a los servidores de Meta (devuelve media_id)
            $tmp = sys_get_temp_dir() . '/proforma_' . $id . '_' . time() . '.pdf';
            file_put_contents($tmp, $pdfString);
            $upload = $whatsappService->uploadMessageMedia($idEmpresa, $tmp, 'application/pdf');
            @unlink($tmp);
            if (empty($upload['success'])) {
                echo json_encode(['ok' => false, 'error' => 'Error subiendo PDF a Meta: ' . ($upload['message'] ?? '')]);
                exit;
            }

            // Mapear componentes de la plantilla (variables por posición: 1=Cliente, 2=Número, 3=Total)
            $componentes   = json_decode($plantillaMeta['componentes'] ?? '[]', true) ?? [];
            $valoresPorPos = [$nombreCliente, $numero, '$' . $total];
            $apiComponents = [];
            foreach ($componentes as $comp) {
                $type = strtoupper($comp['type'] ?? '');
                if ($type === 'HEADER' && strtoupper($comp['format'] ?? '') === 'DOCUMENT') {
                    $apiComponents[] = [
                        'type' => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => ['id' => $upload['media_id'], 'filename' => 'Proforma_' . $numero . '.pdf'],
                        ]],
                    ];
                } elseif ($type === 'BODY') {
                    $texto = $comp['text'] ?? '';
                    if (preg_match_all('/{{(\d+)}}/', $texto, $m)) {
                        $numVars = max(array_map('intval', $m[1]));
                        $params  = [];
                        for ($i = 1; $i <= $numVars; $i++) {
                            $params[] = ['type' => 'text', 'text' => (string) ($valoresPorPos[$i - 1] ?? ' ')];
                        }
                        $apiComponents[] = ['type' => 'body', 'parameters' => $params];
                    }
                }
            }

            $result = $whatsappService->sendTemplateMessage(
                $idEmpresa, $telefono, $plantillaMeta['nombre'], $plantillaMeta['idioma'], $apiComponents
            );
            if (empty($result['success'])) {
                echo json_encode(['ok' => false, 'error' => 'Error enviando mensaje: ' . ($result['message'] ?? '')]);
                exit;
            }

            // Historial de chat (no crítico)
            try {
                $metaMessageId = $result['data']['messages'][0]['id'] ?? null;
                $repoMsj = new \App\repositories\modulos\WhatsappMensajeRepository();
                $idChat  = $repoMsj->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Proforma enviada', false);

                $vars = [];
                foreach ($apiComponents as $c) {
                    if (strtolower($c['type'] ?? '') === 'body') {
                        foreach ($c['parameters'] ?? [] as $p) $vars[] = $p['text'] ?? '';
                        break;
                    }
                }
                $templateText = '';
                foreach ($componentes as $c) {
                    if (($c['type'] ?? '') === 'BODY') {
                        $templateText = $c['text'] ?? '';
                        foreach ($vars as $idx => $v) $templateText = str_replace('{{' . ($idx + 1) . '}}', $v, $templateText);
                        break;
                    }
                }
                $repoMsj->saveMessage($idEmpresa, $idChat, 'OUT', $telefono, 'template', [
                    'template'      => $plantillaMeta['nombre'],
                    'variables'     => $vars,
                    'template_text' => $templateText,
                ], $metaMessageId, 'sent');
            } catch (\Throwable $ex) {
                error_log('[Proforma WA] guardar chat: ' . $ex->getMessage());
            }

            echo json_encode(['ok' => true, 'mensaje' => 'Proforma enviada por WhatsApp exitosamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Genera el PDF de la proforma como STRING (para adjuntar en correo/WhatsApp).
     * Usa la plantilla configurable 'proforma' si existe; si no, un PDF básico TCPDF.
     */
    private function generarPdfProformaString(int $id, int $idEmpresa, ?array $cabecera = null): ?string
    {
        $cabecera = $cabecera ?? $this->repository->getPorId($id);
        if (!$cabecera) return null;

        $detalles = $this->repository->getDetalles($id);
        foreach ($detalles as &$d) {
            $d['impuestos'] = $this->repository->getImpuestosDetalle((int) $d['id']);
        }
        unset($d);
        $adicional = $this->repository->getInfoAdicional($id);

        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $estabs       = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($estabs[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $estabs[0]['logo_ruta'];
        }

        try {
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'proforma');
            if ($plantilla) {
                $pdf = $renderer->generar($plantilla, $cabecera, $detalles, [], $adicional, $empresa, 'S');
                if (is_string($pdf) && $pdf !== '') return $pdf;
            }
        } catch (\Throwable $e) {
            // cae al fallback TCPDF básico
        }

        return $this->generarPdfBasicoString($cabecera, $detalles);
    }

    /** PDF básico (TCPDF) como string: fallback cuando no hay plantilla de proforma. */
    private function generarPdfBasicoString(array $cabecera, array $detalles): ?string
    {
        try {
            $numero = ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
                    . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('CaMaGaRe');
            $pdf->SetTitle('Proforma ' . $numero);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(12, 12, 12);
            $pdf->AddPage();

            $html  = '<h2 style="text-align:center;">PROFORMA</h2>';
            $html .= '<h3 style="text-align:center;">' . htmlspecialchars($numero) . '</h3>';
            $html .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($cabecera['cliente_nombre'] ?? '')
                   . ' — ' . htmlspecialchars($cabecera['cliente_ruc'] ?? '') . '</p>';
            $html .= '<p><strong>Fecha:</strong> ' . date('d-m-Y', strtotime($cabecera['fecha_emision'] ?? 'now')) . '</p>';
            $html .= '<table border="1" cellpadding="4"><thead><tr style="background-color:#f0f0f0;">'
                   . '<th>#</th><th>Descripción</th><th>Cant.</th><th>P.Unit.</th><th>Desc.</th><th>Subtotal</th></tr></thead><tbody>';
            foreach ($detalles as $i => $d) {
                $html .= '<tr>'
                    . '<td>' . ($i + 1) . '</td>'
                    . '<td>' . htmlspecialchars($d['descripcion'] ?? '') . '</td>'
                    . '<td align="right">' . number_format((float) ($d['cantidad'] ?? 0), 2) . '</td>'
                    . '<td align="right">$' . number_format((float) ($d['precio_unitario'] ?? 0), 4) . '</td>'
                    . '<td align="right">$' . number_format((float) ($d['descuento'] ?? 0), 2) . '</td>'
                    . '<td align="right">$' . number_format((float) ($d['precio_total_sin_impuesto'] ?? 0), 2) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '<p style="text-align:right;"><strong>Subtotal:</strong> $' . number_format((float) ($cabecera['total_sin_impuestos'] ?? 0), 2) . '</p>';
            $html .= '<p style="text-align:right;"><strong>TOTAL:</strong> $' . number_format((float) ($cabecera['importe_total'] ?? 0), 2) . '</p>';
            if (!empty($cabecera['observaciones'])) {
                $html .= '<p><strong>Observaciones:</strong> ' . htmlspecialchars($cabecera['observaciones']) . '</p>';
            }

            $pdf->writeHTML($html, true, false, true, false, '');
            return $pdf->Output('', 'S');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Devuelve los datos de la proforma para pre-llenar el formulario de ventas.
     */
    public function convertirAFacturaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $forzar    = !empty($_POST['forzar']) || ($_GET['forzar'] ?? '') === '1';

        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID requerido.']);
            exit;
        }

        try {
            $res = $this->service->convertirAFactura($id, $idEmpresa, $idUsuario, $forzar);

            // La proforma ya tiene factura: la UI debe confirmar antes de crear otra.
            if (!empty($res['requiere_confirmacion'])) {
                echo json_encode([
                    'ok' => false,
                    'requiere_confirmacion' => true,
                    'mensaje' => $res['mensaje'] ?? 'Esta proforma ya tiene una factura asociada. ¿Desea continuar?',
                ]);
                exit;
            }

            // La configuración de la empresa exige stock y no hay saldo suficiente.
            if (!empty($res['stock_insuficiente'])) {
                echo json_encode([
                    'ok' => false,
                    'stock_insuficiente' => true,
                    'faltantes' => $res['faltantes'] ?? [],
                ]);
                exit;
            }

            echo json_encode(['ok' => true, 'id_factura' => (int) ($res['id_factura'] ?? 0)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Genera un recibo de venta a partir de la proforma, aplicando las mismas reglas
     * de inventario que la factura (stock + lotes FEFO).
     */
    public function convertirAReciboAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID requerido.']);
            exit;
        }

        try {
            $res = $this->service->convertirARecibo($id, $idEmpresa, $idUsuario);

            // La configuración de la empresa exige stock y no hay saldo suficiente.
            if (!empty($res['stock_insuficiente'])) {
                echo json_encode([
                    'ok' => false,
                    'stock_insuficiente' => true,
                    'faltantes' => $res['faltantes'] ?? [],
                ]);
                exit;
            }

            echo json_encode(['ok' => true, 'id_recibo' => (int) ($res['id_recibo'] ?? 0)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Lista las facturas generadas desde una proforma (pestaña Facturas del modal).
     */
    public function getFacturasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID requerido.']);
            exit;
        }

        try {
            $proforma = $this->repository->getPorId($id);
            if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Proforma no encontrada.');
            }
            $facturaRepo = new \App\repositories\modulos\FacturaVentaRepository();
            $facturas = $facturaRepo->getPorProforma($id, $idEmpresa);
            echo json_encode(['ok' => true, 'facturas' => $facturas]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Marca la proforma como convertida (llamado desde ventas al guardar la factura).
     */
    public function marcarConvertidaAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $idProforma = (int) ($_POST['id_proforma'] ?? 0);
            $idFactura  = (int) ($_POST['id_factura'] ?? 0);
            $idEmpresa  = (int) $_SESSION['id_empresa'];
            $idUsuario  = (int) $_SESSION['id_usuario'];

            if (!$idProforma || !$idFactura) {
                throw new \RuntimeException('Parámetros inválidos.');
            }

            $proforma = $this->repository->getPorId($idProforma);
            if (!$proforma || (int) $proforma['id_empresa'] !== $idEmpresa) {
                throw new \RuntimeException('Proforma no encontrada.');
            }

            $this->repository->marcarConvertida($idProforma, $idFactura, $idUsuario);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function cargarTodosPuntos(int $idEmpresa): array
    {
        $db = \App\core\Database::getConnection();
        try {
            $st = $db->prepare(
                "SELECT p.id              AS id,
                        p.id             AS id_punto,
                        e.codigo         AS cod_establecimiento,
                        e.id             AS id_establecimiento,
                        p.codigo_punto,
                        p.nombre
                 FROM empresa_punto_emision p
                 JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                 WHERE p.id_empresa = ?
                   AND p.eliminado  = false
                   AND e.eliminado  = false
                   AND LOWER(p.estado) = 'activo'
                 ORDER BY e.codigo, p.codigo_punto"
            );
            $st->execute([$idEmpresa]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // fallback tabla antigua
            try {
                $st2 = $db->prepare(
                    "SELECT id,
                            id               AS id_punto,
                            establecimiento  AS cod_establecimiento,
                            punto            AS codigo_punto,
                            id_establecimiento,
                            '' AS nombre
                     FROM empresa_puntos_emision
                     WHERE id_empresa = ? AND eliminado = false
                     ORDER BY establecimiento, punto"
                );
                $st2->execute([$idEmpresa]);
                return $st2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) { return []; }
        }
    }

    private function renderFilaHtml(array $r): string
    {
        $rowData  = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $numero   = htmlspecialchars(($r['establecimiento'] ?? '') . '-' . ($r['punto_emision'] ?? '') . '-' . ($r['secuencial'] ?? ''));
        $fecha    = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '-';
        $estado   = $r['estado'] ?? 'borrador';

        $estadoClass = match ($estado) {
            'aprobada'   => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            'anulada'    => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
            'convertida' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
            'rechazada'  => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
            default      => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
        };

        $estadoLabel = match ($estado) {
            'borrador'   => 'Borrador',
            'aprobada'   => 'Aprobada',
            'rechazada'  => 'Rechazada',
            'convertida' => 'Convertida',
            'anulada'    => 'Anulada',
            default      => ucfirst($estado),
        };

        $cliente  = htmlspecialchars($r['cliente_nombre'] ?? '-');
        $ruc      = htmlspecialchars($r['cliente_ruc'] ?? '-');
        $total    = number_format((float) ($r['importe_total'] ?? 0), 2);
        $vendedor = htmlspecialchars($r['vendedor_nombre'] ?? '-');
        $obs      = htmlspecialchars(mb_substr($r['observaciones'] ?? '', 0, 60));
        $id       = (int) $r['id'];

        return "
        <tr class=\"proforma-row\" role=\"button\" tabindex=\"0\" data-id=\"{$id}\"
            data-row=\"{$rowData}\" onclick=\"PF.verDetalle({$id})\">
            <td class=\"ps-3 fw-medium\" data-col=\"numero\"><code class=\"text-secondary\">{$numero}</code></td>
            <td data-col=\"fecha_emision\">{$fecha}</td>
            <td class=\"text-truncate\" style=\"max-width:220px;\" data-col=\"cliente_nombre\">{$cliente}</td>
            <td data-col=\"cliente_ruc\">{$ruc}</td>
            <td data-col=\"vendedor_nombre\">{$vendedor}</td>
            <td class=\"text-end fw-semibold\" data-col=\"importe_total\">\${$total}</td>
            <td class=\"text-center\" data-col=\"estado\">
                <span class=\"badge {$estadoClass}\">{$estadoLabel}</span>
            </td>
            <td class=\"text-truncate text-muted small\" style=\"max-width:200px;\" data-col=\"observaciones\">{$obs}</td>
        </tr>";
    }

    private function renderPdfBasico(array $cabecera, array $detalles, array $adicional): void
    {
        $numero = htmlspecialchars(($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-' . ($cabecera['secuencial'] ?? ''));

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Proforma ' . $numero . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;font-size:12px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;} .text-end{text-align:right;} .header{text-align:center;margin-bottom:20px;}</style>';
        echo '</head><body>';
        echo '<div class="header"><h2>PROFORMA</h2><h3>' . $numero . '</h3></div>';
        echo '<p><strong>Cliente:</strong> ' . htmlspecialchars($cabecera['cliente_nombre'] ?? '') . ' — ' . htmlspecialchars($cabecera['cliente_ruc'] ?? '') . '</p>';
        echo '<p><strong>Fecha:</strong> ' . date('d-m-Y', strtotime($cabecera['fecha_emision'] ?? 'now')) . '</p>';
        echo '<table><tr><th>#</th><th>Descripción</th><th>Cant.</th><th>P.Unit.</th><th>Desc.</th><th>Subtotal</th></tr>';
        foreach ($detalles as $i => $d) {
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . htmlspecialchars($d['descripcion']) . '</td>';
            echo '<td class="text-end">' . number_format((float)$d['cantidad'], 2) . '</td>';
            echo '<td class="text-end">$' . number_format((float)$d['precio_unitario'], 4) . '</td>';
            echo '<td class="text-end">$' . number_format((float)$d['descuento'], 2) . '</td>';
            echo '<td class="text-end">$' . number_format((float)$d['precio_total_sin_impuesto'], 2) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<p class="text-end"><strong>Subtotal:</strong> $' . number_format((float)($cabecera['total_sin_impuestos'] ?? 0), 2) . '</p>';
        echo '<p class="text-end"><strong>TOTAL:</strong> $' . number_format((float)($cabecera['importe_total'] ?? 0), 2) . '</p>';
        if ($cabecera['observaciones']) {
            echo '<p><strong>Observaciones:</strong> ' . htmlspecialchars($cabecera['observaciones']) . '</p>';
        }
        echo '<script>window.print();</script>';
        echo '</body></html>';
    }
}
