<?php
/**
 * Controlador PlantillasWhatsappController
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\WhatsappConfig;
use App\services\WhatsappService;

class PlantillasWhatsappController extends BaseModuloController
{
    private WhatsappConfig $configModel;
    private WhatsappService $whatsappService;

    public function __construct()
    {
        parent::__construct();
        $this->configModel = new WhatsappConfig();
        $this->whatsappService = new WhatsappService();
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/plantillas-whatsapp';
    }

    /**
     * Muestra la vista principal con las pestañas de Configuración y Plantillas
     */
    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        // Obtener configuración actual para llenar el formulario
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);
        
        // Pasar permisos a la vista
        $permisos = $this->getPermisos();

        $data = [
            'titulo' => 'WhatsApp y Plantillas',
            'config' => $config,
            'permisos' => $permisos
        ];

        // Usar render para que envuelva la vista en el layout principal
        $this->viewWithLayout('layouts.main', 'modulos.plantillas_whatsapp.index', $data);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? $_POST['q'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));
        $perPage   = 20;

        $model = new \App\models\WhatsappPlantilla();
        $result = $model->getFiltradas($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="fab fa-whatsapp fs-3 d-block mb-2 text-success opacity-50"></i>Aún no hay plantillas sincronizadas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $estado = htmlspecialchars($r['estado_meta'] ?? 'APPROVED');
                $badgeClass = 'bg-success text-success border-success';
                if ($estado === 'PENDING' || $estado === 'IN_APPEAL') $badgeClass = 'bg-warning text-warning border-warning';
                else if ($estado === 'REJECTED' || $estado === 'DELETED') $badgeClass = 'bg-danger text-danger border-danger';

                echo '<tr class="plantilla-row" role="button" tabindex="0">
                        <td class="ps-3" data-col="nombre">' . htmlspecialchars($r['nombre'] ?? '') . '</td>
                        <td data-col="categoria">' . htmlspecialchars($r['categoria'] ?? '') . '</td>
                        <td data-col="idioma">' . htmlspecialchars($r['idioma'] ?? '') . '</td>
                        <td class="text-center" data-col="estado">
                            <span class="badge ' . $badgeClass . ' bg-opacity-10 border border-opacity-25">' . $estado . '</span>
                        </td>
                        <td class="text-center pe-3">
                            <button class="btn btn-sm btn-outline-secondary me-1" title="Ver detalles" onclick="WA_verDetalles(' . $r['id'] . ')"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-outline-primary me-1" title="Probar Envío" onclick="WA_abrirModalProbar(' . $r['id'] . ')"><i class="bi bi-send"></i></button>
                            <button class="btn btn-sm btn-outline-warning" title="Editar" onclick="WA_abrirModalEditar(' . $r['id'] . ')"><i class="bi bi-pencil"></i></button>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => '#',
            'excel_url' => '#'
        ]);
        exit;
    }

    public function sincronizarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $result = $this->whatsappService->syncTemplates($idEmpresa);

        if (!$result['success']) {
            echo json_encode(['ok' => false, 'error' => $result['message']]);
            return;
        }

        $plantillas = $result['data'];
        $model = new \App\models\WhatsappPlantilla();
        $count = 0;

        foreach ($plantillas as $p) {
            if ($model->upsertPlantilla($idEmpresa, $p, $idUsuario)) {
                $count++;
            }
        }

        echo json_encode(['ok' => true, 'mensaje' => "Se han sincronizado $count plantillas correctamente."]);
        exit;
    }

    public function getDetallesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
        $stmt->execute([$id, $idEmpresa]);
        $plantilla = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantilla) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada']);
            return;
        }

        $componentes = json_decode($plantilla['componentes'], true) ?? [];
        $html = '<div class="text-start">';
        $html .= '<h6 class="fw-bold mb-3 border-bottom pb-2">Contenido de la Plantilla</h6>';

        foreach ($componentes as $comp) {
            $type = $comp['type'] ?? '';
            if ($type === 'HEADER') {
                $format = $comp['format'] ?? '';
                $html .= '<div class="mb-3">';
                $html .= '<span class="badge bg-secondary mb-1">CABECERA (' . htmlspecialchars($format) . ')</span><br>';
                if ($format === 'TEXT') {
                    $html .= '<strong>' . htmlspecialchars($comp['text'] ?? '') . '</strong>';
                } else {
                    $html .= '<span class="text-muted fst-italic">Adjunto multimedia requerido al enviar.</span>';
                }
                $html .= '</div>';
            } elseif ($type === 'BODY') {
                $html .= '<div class="mb-3">';
                $html .= '<span class="badge bg-secondary mb-1">CUERPO</span><br>';
                $texto = htmlspecialchars($comp['text'] ?? '');
                // Resaltar variables
                $texto = preg_replace('/{{(\d+)}}/', '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{$1}}</span>', $texto);
                $html .= '<div class="p-3 bg-light rounded border">' . nl2br($texto) . '</div>';
                $html .= '</div>';
            } elseif ($type === 'FOOTER') {
                $html .= '<div class="mb-3">';
                $html .= '<span class="badge bg-secondary mb-1">PIE DE PÁGINA</span><br>';
                $html .= '<small class="text-muted">' . htmlspecialchars($comp['text'] ?? '') . '</small>';
                $html .= '</div>';
            } elseif ($type === 'BUTTONS') {
                $html .= '<div class="mb-3">';
                $html .= '<span class="badge bg-secondary mb-1">BOTONES</span><div class="d-flex gap-2 flex-wrap mt-2">';
                foreach ($comp['buttons'] as $btn) {
                    $btnType = $btn['type'] ?? '';
                    $btnText = htmlspecialchars($btn['text'] ?? '');
                    $icon = 'bi-circle';
                    if ($btnType === 'URL') $icon = 'bi-link-45deg';
                    else if ($btnType === 'PHONE_NUMBER') $icon = 'bi-telephone';
                    else if ($btnType === 'QUICK_REPLY') $icon = 'bi-reply';
                    $html .= '<span class="badge bg-white text-dark border"><i class="bi ' . $icon . ' text-muted me-1"></i>' . $btnText . '</span>';
                }
                $html .= '</div></div>';
            }
        }

        $html .= '</div>';

        // Mostrar información de variables para el usuario
        if (strpos($html, '{{1}}') !== false) {
            $html .= '<div class="alert alert-info mt-4 mb-0 small">';
            $html .= '<i class="bi bi-info-circle me-1"></i><strong>Sobre las variables:</strong> Las variables <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{1}}</span> son espacios dinámicos que se llenarán automáticamente con el nombre del cliente, el número de factura o la información correspondiente al momento de enviar el mensaje.';
            $html .= '</div>';
        }

        echo json_encode(['ok' => true, 'html' => $html]);
        exit;
    }

    public function getFormularioPruebaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
        $stmt->execute([$id, $idEmpresa]);
        $plantilla = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantilla) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada']);
            return;
        }

        $componentes = json_decode($plantilla['componentes'], true) ?? [];
        $html = '';

        $numVariables = 0;
        $reqDocument = false;

        foreach ($componentes as $comp) {
            $type = $comp['type'] ?? '';
            if ($type === 'HEADER') {
                $format = $comp['format'] ?? '';
                if ($format === 'DOCUMENT') {
                    $reqDocument = true;
                }
            } elseif ($type === 'BODY') {
                $texto = $comp['text'] ?? '';
                if (preg_match_all('/{{(\d+)}}/', $texto, $matches)) {
                    $numVariables = max($numVariables, max($matches[1]));
                }
            }
        }

        if ($reqDocument) {
            $html .= '
            <div class="mb-3">
                <label class="form-label fw-bold">Documento Adjunto (PDF) <span class="text-danger">*</span></label>
                <input type="file" name="archivo" class="form-control form-control-sm" accept="application/pdf" required>
                <div class="form-text text-muted">Esta plantilla requiere que adjuntes un archivo PDF.</div>
            </div>';
        }

        if ($numVariables > 0) {
            $html .= '<p class="fw-bold mb-2">Variables del Cuerpo:</p>';
            for ($i = 1; $i <= $numVariables; $i++) {
                $html .= '
                <div class="mb-2">
                    <label class="form-label small mb-1">Variable {{' . $i . '}} <span class="text-danger">*</span></label>
                    <input type="text" name="vars[' . $i . ']" class="form-control form-control-sm" placeholder="Valor para {{' . $i . '}}" required>
                </div>';
            }
        }

        if (empty($html)) {
            $html = '<div class="alert alert-info py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i> Esta plantilla no requiere variables ni adjuntos. Solo ingresa el teléfono destino.</div>';
        }

        echo json_encode(['ok' => true, 'html' => $html]);
        exit;
    }

    public function enviarPruebaAjax(): void
    {
        $this->requireLeer(); // Se requiere al menos permiso de lectura de plantilla para probar
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);
        $telefono = trim($_POST['telefono'] ?? '');
        $vars = $_POST['vars'] ?? [];

        if ($idPlantilla <= 0 || empty($telefono)) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
        $stmt->execute([$idPlantilla, $idEmpresa]);
        $plantilla = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantilla) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada.']);
            return;
        }

        $componentes = json_decode($plantilla['componentes'], true) ?? [];
        $apiComponents = [];

        foreach ($componentes as $comp) {
            $type = $comp['type'] ?? '';

            if ($type === 'HEADER') {
                $format = $comp['format'] ?? '';
                if ($format === 'DOCUMENT') {
                    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(['ok' => false, 'error' => 'Debe adjuntar un archivo PDF.']);
                        return;
                    }

                    $mimeType = $_FILES['archivo']['type'];
                    $tmpName = $_FILES['archivo']['tmp_name'];

                    $uploadResult = $this->whatsappService->uploadMessageMedia($idEmpresa, $tmpName, $mimeType);

                    if (!$uploadResult['success']) {
                        echo json_encode(['ok' => false, 'error' => $uploadResult['message']]);
                        return;
                    }

                    $apiComponents[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => ['id' => $uploadResult['media_id']]
                            ]
                        ]
                    ];
                }
            } elseif ($type === 'BODY') {
                $texto = $comp['text'] ?? '';
                if (preg_match_all('/{{(\d+)}}/', $texto, $matches)) {
                    $numVars = max($matches[1]);
                    $parameters = [];
                    for ($i = 1; $i <= $numVars; $i++) {
                        $val = trim($vars[$i] ?? '');
                        if ($val === '') {
                            echo json_encode(['ok' => false, 'error' => "Falta la variable {{" . $i . "}}."]);
                            return;
                        }
                        $parameters[] = [
                            'type' => 'text',
                            'text' => $val
                        ];
                    }

                    $apiComponents[] = [
                        'type' => 'body',
                        'parameters' => $parameters
                    ];
                }
            }
        }

        $result = $this->whatsappService->sendTemplateMessage(
            $idEmpresa,
            $telefono,
            $plantilla['nombre'],
            $plantilla['idioma'],
            $apiComponents
        );

        if (!$result['success']) {
            echo json_encode(['ok' => false, 'error' => $result['message']]);
            return;
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Mensaje de prueba enviado exitosamente.']);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $nombre = trim(strtolower($_POST['nombre'] ?? ''));
        $categoria = trim($_POST['categoria'] ?? 'MARKETING');
        $idioma = trim($_POST['idioma'] ?? 'es');
        $cuerpo = trim($_POST['cuerpo'] ?? '');
        $tipoCabecera = trim($_POST['tipo_cabecera'] ?? 'NONE');

        if (empty($nombre) || empty($cuerpo)) {
            echo json_encode(['ok' => false, 'error' => 'El nombre y el cuerpo del mensaje son obligatorios.']);
            return;
        }

        $components = [];

        // CABECERA
        if ($tipoCabecera === 'DOCUMENT') {
            if (!isset($_FILES['pdf_ejemplo']) || $_FILES['pdf_ejemplo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['ok' => false, 'error' => 'Debe subir un archivo PDF de ejemplo.']);
                return;
            }

            // Validar que sea PDF
            $mimeType = $_FILES['pdf_ejemplo']['type'];
            if ($mimeType !== 'application/pdf') {
                echo json_encode(['ok' => false, 'error' => 'El archivo debe ser un PDF válido.']);
                return;
            }

            // Obtener App ID de la configuración
            $configModel = new \App\models\WhatsappConfig();
            $config = $configModel->obtenerConfiguracion($idEmpresa);
            if (empty($config['app_id'])) {
                echo json_encode(['ok' => false, 'error' => 'Debe configurar el App ID de Meta en Configuración WhatsApp para poder subir archivos.']);
                return;
            }

            // Subir a Meta para obtener el handle
            $tmpName = $_FILES['pdf_ejemplo']['tmp_name'];
            $fileSize = filesize($tmpName);

            $uploadResult = $this->whatsappService->uploadMedia($idEmpresa, $config['app_id'], $tmpName, $mimeType, $fileSize);

            if (!$uploadResult['success']) {
                echo json_encode(['ok' => false, 'error' => $uploadResult['message']]);
                return;
            }

            $handle = $uploadResult['handle'];

            $components[] = [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'example' => [
                    'header_handle' => [$handle]
                ]
            ];
        }

        // CUERPO (Analizar variables {{1}}, {{2}})
        preg_match_all('/{{(\d+)}}/', $cuerpo, $matches);
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $cuerpo
        ];

        if (!empty($matches[1])) {
            // Meta exige ejemplos para cada variable en el body
            $numVars = max($matches[1]);
            $ejemplos = [];
            for ($i = 1; $i <= $numVars; $i++) {
                $ejemplos[] = "ejemplo_$i";
            }
            $bodyComponent['example'] = [
                'body_text' => [$ejemplos]
            ];
        }

        $components[] = $bodyComponent;

        $data = [
            'name' => $nombre,
            'category' => $categoria,
            'language' => $idioma,
            'components' => $components
        ];

        $createResult = $this->whatsappService->createTemplate($idEmpresa, $data);

        if (!$createResult['success']) {
            echo json_encode(['ok' => false, 'error' => $createResult['message']]);
            return;
        }

        // Si se creó exitosamente, la sincronizamos (guardamos en DB)
        $metaTemplate = $createResult['data'];
        $model = new \App\models\WhatsappPlantilla();
        $model->upsertPlantilla($idEmpresa, $metaTemplate, $idUsuario);

        echo json_encode(['ok' => true, 'mensaje' => 'Plantilla creada exitosamente. Se encuentra en revisión por Meta.']);
        exit;
    }

    public function getParaEditarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
        $stmt->execute([$id, $idEmpresa]);
        $plantilla = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantilla) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada']);
            return;
        }

        $componentes = json_decode($plantilla['componentes'], true) ?? [];
        $cuerpo = '';
        $tipoCabecera = 'NONE';
        
        foreach ($componentes as $comp) {
            $type = $comp['type'] ?? '';
            if ($type === 'BODY') {
                $cuerpo = $comp['text'] ?? '';
            } elseif ($type === 'HEADER') {
                $tipoCabecera = $comp['format'] ?? 'NONE';
            }
        }

        echo json_encode([
            'ok' => true,
            'plantilla' => [
                'id' => $plantilla['id'],
                'nombre' => $plantilla['nombre'],
                'categoria' => $plantilla['categoria'],
                'idioma' => $plantilla['idioma'],
                'cuerpo' => $cuerpo,
                'tipo_cabecera' => $tipoCabecera
            ]
        ]);
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);
        $nuevoCuerpo = trim($_POST['cuerpo'] ?? '');

        if ($idPlantilla <= 0 || empty($nuevoCuerpo)) {
            echo json_encode(['ok' => false, 'error' => 'El cuerpo del mensaje es obligatorio.']);
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM whatsapp_plantillas WHERE id = ? AND id_empresa = ? AND eliminado = FALSE");
        $stmt->execute([$idPlantilla, $idEmpresa]);
        $plantilla = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$plantilla || empty($plantilla['meta_id'])) {
            echo json_encode(['ok' => false, 'error' => 'Plantilla no encontrada o sin ID de Meta.']);
            return;
        }

        // ── Partir de los componentes actuales almacenados en la BD ───────────
        // Meta exige recibir TODOS los componentes existentes (HEADER, BODY,
        // FOOTER, BUTTONS). Sólo modificamos el BODY con el nuevo texto.
        $componentesActuales = json_decode($plantilla['componentes'] ?? '[]', true) ?? [];

        $updatedComponents = [];
        $newHeaderHandle   = null; // solo si se sube nuevo PDF

        foreach ($componentesActuales as $comp) {
            $type   = strtoupper($comp['type'] ?? '');
            $format = strtoupper($comp['format'] ?? '');

            if ($type === 'HEADER') {
                // Si es DOCUMENT y el usuario subió un PDF nuevo, actualizar handle
                if ($format === 'DOCUMENT'
                    && isset($_FILES['pdf_ejemplo'])
                    && $_FILES['pdf_ejemplo']['error'] === UPLOAD_ERR_OK
                ) {
                    $mimeType = $_FILES['pdf_ejemplo']['type'];
                    if ($mimeType !== 'application/pdf') {
                        echo json_encode(['ok' => false, 'error' => 'El archivo debe ser un PDF válido.']);
                        return;
                    }

                    $configModel = new \App\models\WhatsappConfig();
                    $config      = $configModel->obtenerConfiguracion($idEmpresa);
                    if (empty($config['app_id'])) {
                        echo json_encode(['ok' => false, 'error' => 'Falta App ID de Meta en Configuración.']);
                        return;
                    }

                    $tmpName    = $_FILES['pdf_ejemplo']['tmp_name'];
                    $fileSize   = filesize($tmpName);
                    $uploadResult = $this->whatsappService->uploadMedia($idEmpresa, $config['app_id'], $tmpName, $mimeType, $fileSize);

                    if (!$uploadResult['success']) {
                        echo json_encode(['ok' => false, 'error' => $uploadResult['message']]);
                        return;
                    }

                    $newHeaderHandle = $uploadResult['handle'];
                    $headerComp      = [
                        'type'    => 'HEADER',
                        'format'  => 'DOCUMENT',
                        'example' => ['header_handle' => [$newHeaderHandle]]
                    ];
                    $updatedComponents[] = $headerComp;
                } else {
                    // Conservar el componente HEADER tal como está (sin example innecesario)
                    $keep = ['type' => $comp['type'], 'format' => $comp['format']];
                    if (!empty($comp['text'])) {
                        $keep['text'] = $comp['text'];
                    }
                    $updatedComponents[] = $keep;
                }

            } elseif ($type === 'BODY') {
                // Reemplazar el texto con el nuevo cuerpo
                // NO incluir 'example' en actualizaciones (Meta lo rechaza en algunos casos)
                $updatedComponents[] = [
                    'type' => 'BODY',
                    'text' => $nuevoCuerpo,
                ];

            } elseif ($type === 'FOOTER') {
                $updatedComponents[] = [
                    'type' => 'FOOTER',
                    'text' => $comp['text'] ?? '',
                ];

            } elseif ($type === 'BUTTONS') {
                // Conservar botones tal como están
                $updatedComponents[] = $comp;

            } else {
                // Cualquier otro componente: conservar sin modificar
                $updatedComponents[] = $comp;
            }
        }

        // Si la plantilla no tenía BODY (caso raro), agregarlo
        $tieneBody = false;
        foreach ($updatedComponents as $uc) {
            if (strtoupper($uc['type'] ?? '') === 'BODY') {
                $tieneBody = true;
                break;
            }
        }
        if (!$tieneBody) {
            $updatedComponents[] = ['type' => 'BODY', 'text' => $nuevoCuerpo];
        }

        $data = ['components' => $updatedComponents];

        $updateResult = $this->whatsappService->updateTemplate($idEmpresa, (string)$plantilla['meta_id'], $data);

        if (!$updateResult['success']) {
            echo json_encode(['ok' => false, 'error' => $updateResult['message']]);
            return;
        }

        // Actualizar componentes en la BD con los nuevos valores
        $this->db->prepare("UPDATE whatsapp_plantillas SET componentes = ?, estado_meta = 'PENDING', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                 ->execute([json_encode($updatedComponents, JSON_UNESCAPED_UNICODE), $idPlantilla]);

        echo json_encode(['ok' => true, 'mensaje' => 'Plantilla actualizada correctamente. Estado: PENDING (en revisión por Meta).']);
        exit;
    }
}

