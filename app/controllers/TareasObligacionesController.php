<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\ObligacionRepository;
use App\repositories\TareaRepository;
use App\Rules\ObligacionRules;
use App\Rules\TareaRules;
use App\Services\LogSistemaService;
use App\Services\ObligacionService;
use App\Services\TareaService;

/**
 * TareasObligacionesController
 *
 * Módulo global de Tareas y Obligaciones.
 * No depende de id_empresa. Accesible para todos los usuarios autenticados.
 * Enrutado desde ConfigController como proxy.
 */
class TareasObligacionesController extends Controller
{
    private ObligacionService $obligacionService;
    private TareaService      $tareaService;

    private const STORAGE_DIR     = 'storage/tareas';
    private const MAX_UPLOAD_BYTES = 200 * 1024; // 200 KB
    private const TIPOS_PERMITIDOS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/xml',
        'application/xml',
    ];

    public function __construct()
    {
        $logService              = new LogSistemaService();
        $this->obligacionService = new ObligacionService(new ObligacionRepository(), new ObligacionRules(), $logService);
        $this->tareaService      = new TareaService(new TareaRepository(), new TareaRules(), $logService);
    }

    // ════════════════════════════════════════════════════════
    //  VISTA PRINCIPAL
    // ════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requireAuth();

        $tab      = $_GET['tab'] ?? 'tareas';
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel     = (int) ($_SESSION['nivel'] ?? 1);

        $obligacionesActivas = $this->obligacionService->getAllActivas();
        // Obtener responsables (ahora sin filtro por idEmpresa)
        $responsablesFiltro  = $this->tareaService->getResponsablesParaFiltro();

        // Obtener todas las empresas asignadas al usuario para mostrar en el navbar
        // (aunque este módulo es global, el navbar necesita mostrar todas las empresas)
        $empresasModel = new \App\models\Empresa();
        $empresas = [];
        try {
            $empresas = $empresasModel->getEmpresasAsignadas($idUsuario);
        } catch (\Throwable $e) {
            $empresas = [];
        }

        $this->viewWithLayout('layouts.main', 'tareasObligaciones.index', [
            'titulo'              => 'Tareas y Obligaciones',
            'tab'                 => $tab,
            'idUsuarioActual'     => $idUsuario,
            'nivelUsuarioActual'  => $nivel,
            'obligacionesActivas' => $obligacionesActivas,
            'responsablesFiltro'  => $responsablesFiltro,
            'empresas'            => $empresas,  // Agregar explícitamente para navbar
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  AJAX — OBLIGACIONES
    // ════════════════════════════════════════════════════════

    public function obligacionesSearchAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $perPage  = 20;

        $result     = $this->obligacionService->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-journal-text fs-3 d-block mb-2"></i>No se encontraron obligaciones.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $statusBadge = ((int)($r['status'] ?? 1) === 1)
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activa</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactiva</span>';
                $fechaC = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime($r['created_at'])) : '-';
                $dataAttr = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                echo '<tr class="oblig-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalObligEdit(this)">
                        <td class="ps-3 fw-medium">' . htmlspecialchars($r['nombre']) . '</td>
                        <td class="text-muted small">' . htmlspecialchars($r['descripcion'] ?? '') . '</td>
                        <td class="text-center">' . $statusBadge . '</td>
                        <td class="text-center pe-3 text-muted small">' . $fechaC . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevD = ($page <= 1) ? 'disabled' : '';
        $nextD = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevD . ' onclick="cambiarPaginaOblig(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextD . ' onclick="cambiarPaginaOblig(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
        ]);
        exit;
    }

    public function obligacionesStore(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $data = [
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'status'      => (int) ($_POST['status'] ?? 1),
            'created_by'  => $idUsuario,
        ];

        try {
            $id = $this->obligacionService->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Obligación creada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function obligacionesUpdate(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $data = [
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'status'      => (int) ($_POST['status'] ?? 1),
            'updated_by'  => $idUsuario,
        ];

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->obligacionService->actualizar($id, $data);
            echo json_encode(['ok' => true, 'msg' => 'Obligación actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function obligacionesDelete(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->obligacionService->eliminar($id, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Obligación eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ════════════════════════════════════════════════════════
    //  AJAX — TAREAS
    // ════════════════════════════════════════════════════════

    public function tareasSearchAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $buscar             = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page               = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol           = trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_tarea');
        $ordenDir           = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'ASC'));
        $incluirArchivadas  = (int) ($_GET['archivadas'] ?? 0) === 1;
        $idUsuario          = (int) ($_SESSION['id_usuario'] ?? 0);
        $nivel              = (int) ($_SESSION['nivel'] ?? 1);
        $perPage            = 20;

        $filtros = [
            'desde'          => trim($_GET['desde'] ?? ''),
            'hasta'          => trim($_GET['hasta'] ?? ''),
            'obligacion'     => trim($_GET['obligacion'] ?? ''),
            'responsable'    => trim($_GET['responsable'] ?? ''),
            'estado'         => trim($_GET['estado'] ?? ''),
        ];

        $result     = $this->tareaService->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir, $incluirArchivadas, $idUsuario, $filtros, $nivel);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $estadoLabels = [
            'por_realizar'        => ['label' => 'Por realizar',      'class' => 'warning'],
            'realizada_continua'  => ['label' => 'Realizada y continua', 'class' => 'primary'],
            'realizada_finalizada' => ['label' => 'Realizada y finalizada', 'class' => 'success'],
            'vencida'             => ['label' => 'Vencida',           'class' => 'danger'],
            'cancelada'           => ['label' => 'Cancelada',         'class' => 'secondary'],
        ];
        $periodicidadLabels = [
            'semanal'    => 'Semanal',
            'quincenal'    => 'Quincenal',
            'mensual'    => 'Mensual',
            'trimestral' => 'Trimestral',
            'semestral'  => 'Semestral',
            'anual'      => 'Anual',
            'dos_anios'  => '2 Años',
            'tres_anios' => '3 Años',
            'cuatro_anios'    => '4 Años',
            'cinco_anios'    => '5 Años',
        ];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-list-check fs-3 d-block mb-2"></i>No se encontraron tareas.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $estado     = $r['estado'] ?? 'por_realizar';
                $estadoInfo = $estadoLabels[$estado] ?? ['label' => $estado, 'class' => 'secondary'];
                $estadoBadge = '<span class="badge bg-' . $estadoInfo['class'] . ' bg-opacity-10 text-' . $estadoInfo['class'] . ' border border-' . $estadoInfo['class'] . ' border-opacity-25">' . $estadoInfo['label'] . '</span>';
                $periodo   = $periodicidadLabels[$r['periodicidad'] ?? ''] ?? ($r['periodicidad'] ?? '-');
                $fecha     = !empty($r['fecha_tarea']) ? date('d/m/Y', strtotime($r['fecha_tarea'])) : '-';

                // Responsables (máx 3 mostrados + contador)
                $resps = $r['responsables'] ?? [];
                $respHtml = '';
                foreach (array_slice($resps, 0, 3) as $resp) {
                    $respHtml .= '<span class="badge bg-light text-dark border me-1" title="' . htmlspecialchars($resp['mail'] ?? '') . '">'
                        . htmlspecialchars(explode(' ', $resp['nombre'])[0] ?? '')
                        . '</span>';
                }
                if (count($resps) > 3) {
                    $respHtml .= '<span class="badge bg-secondary bg-opacity-10 text-secondary">+' . (count($resps) - 3) . '</span>';
                }
                if (empty($resps)) {
                    $respHtml = '<span class="text-muted small">—</span>';
                }

                // Archivada badge
                $archivadaBadge = ($r['archivada'] ?? false) ? ' <span class="badge bg-secondary bg-opacity-10 text-secondary" title="Archivada"><i class="bi bi-archive"></i></span>' : '';

                // Data para modal (sin adjuntos en listado)
                $rData = $r;
                unset($rData['responsables']);
                $dataAttr = htmlspecialchars(json_encode($rData), ENT_QUOTES, 'UTF-8');

                echo '<tr class="tarea-row" role="button" tabindex="0" data-row=\'' . $dataAttr . '\' onclick="abrirModalTareaEditar(this)">
                        <td class="ps-3"><span class="fw-medium">' . htmlspecialchars($r['cliente_nombre']) . '</span><br><small class="text-muted">' . htmlspecialchars($r['cliente_correo']) . '</small></td>
                        <td>' . htmlspecialchars($r['obligacion_nombre'] ?? '—') . $archivadaBadge . '</td>
                        <td class="text-muted"><small>' . htmlspecialchars($r['creado_por_nombre'] ?? '—') . '</small></td>
                        <td>' . $respHtml . '</td>
                        <td class="text-center">' . $periodo . '</td>
                        <td class="text-center">' . $fecha . '</td>
                        <td class="text-center">' . $estadoBadge . '</td>
                        <td class="text-muted small pe-3" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . htmlspecialchars(mb_strimwidth($r['notas'] ?? '', 0, 60, '…', 'UTF-8')) . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevD = ($page <= 1) ? 'disabled' : '';
        $nextD = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevD . ' onclick="cambiarPaginaTarea(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextD . ' onclick="cambiarPaginaTarea(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'total'      => $total,
        ]);
        exit;
    }

    public function tareasStore(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idUsuario    = (int) ($_SESSION['id_usuario'] ?? 0);
        $responsables = $_POST['responsables'] ?? [];
        if (is_string($responsables)) {
            $responsables = json_decode($responsables, true) ?? [];
        }

        $data = $this->recogerDatosTarea();
        $data['created_by']   = $idUsuario;
        $data['responsables'] = $responsables;

        try {
            $id = $this->tareaService->crear($data);
            echo json_encode(['ok' => true, 'msg' => 'Tarea creada correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tareasUpdate(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id           = (int) ($_POST['id'] ?? 0);
        $idUsuario    = (int) ($_SESSION['id_usuario'] ?? 0);
        $responsables = $_POST['responsables'] ?? [];
        if (is_string($responsables)) {
            $responsables = json_decode($responsables, true) ?? [];
        }

        $data = $this->recogerDatosTarea();
        $data['updated_by']   = $idUsuario;
        $data['responsables'] = $responsables;

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->tareaService->actualizar($id, $data);
            echo json_encode(['ok' => true, 'msg' => 'Tarea actualizada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tareasDelete(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $this->tareaService->eliminar($id, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Tarea eliminada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tareasGetDetalle(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        try {
            if ($id <= 0) throw new \Exception('ID no válido.');
            $tarea = $this->tareaService->getTareaCompleta($id);
            if (!$tarea) throw new \Exception('Tarea no encontrada.');

            // Formatear fechas
            $fmt = fn($d) => !empty($d) ? date('d-m-Y H:i:s', strtotime($d)) : '-';
            $tarea['created_at'] = $fmt($tarea['created_at'] ?? null);
            $tarea['updated_at'] = $fmt($tarea['updated_at'] ?? null);

            echo json_encode(['ok' => true, 'data' => $tarea]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ════════════════════════════════════════════════════════
    //  AJAX — ADJUNTOS
    // ════════════════════════════════════════════════════════

    public function tareasUploadAdjunto(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idTarea   = (int) ($_POST['id_tarea'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            if ($idTarea <= 0) throw new \Exception('ID de tarea no válido.');
            if (empty($_FILES['adjunto'])) throw new \Exception('No se recibió ningún archivo.');

            $file = $_FILES['adjunto'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new \Exception('Error al subir el archivo.');

            if ($file['size'] > self::MAX_UPLOAD_BYTES) {
                throw new \Exception('El archivo supera el tamaño máximo de 200 KB.');
            }

            // Verificar tipo MIME real
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, self::TIPOS_PERMITIDOS, true)) {
                throw new \Exception('Tipo de archivo no permitido: ' . $mime);
            }

            // Crear directorio si no existe
            $storageBase = MVC_ROOT . '/' . self::STORAGE_DIR;
            if (!is_dir($storageBase)) {
                mkdir($storageBase, 0755, true);
            }

            $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nombreUnico  = date('Ymd_His') . '_' . $idTarea . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $rutaFisica   = $storageBase . '/' . $nombreUnico;
            $rutaRelativa = self::STORAGE_DIR . '/' . $nombreUnico;

            if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {
                throw new \Exception('No se pudo guardar el archivo en el servidor.');
            }

            $idAdjunto = $this->tareaService->addAdjunto([
                'id_tarea'       => $idTarea,
                'nombre_archivo' => $file['name'],
                'ruta_archivo'   => $rutaRelativa,
                'tipo_mime'      => $mime,
                'tamanio'        => $file['size'],
                'created_by'     => $idUsuario,
            ]);

            echo json_encode(['ok' => true, 'msg' => 'Archivo adjunto guardado.', 'id' => $idAdjunto, 'nombre' => $file['name']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function tareasDeleteAdjunto(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idAdjunto = (int) ($_POST['id_adjunto'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            if ($idAdjunto <= 0) throw new \Exception('ID no válido.');
            $ruta = $this->tareaService->deleteAdjunto($idAdjunto, $idUsuario);

            // Eliminar archivo físico si existe
            if ($ruta) {
                $rutaFisica = MVC_ROOT . '/' . $ruta;
                if (file_exists($rutaFisica)) {
                    @unlink($rutaFisica);
                }
            }

            echo json_encode(['ok' => true, 'msg' => 'Adjunto eliminado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ════════════════════════════════════════════════════════
    //  AJAX — BUSQUEDAS
    // ════════════════════════════════════════════════════════

    public function buscarClientes(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $buscar  = trim($_GET['q'] ?? $_POST['q'] ?? '');
        $propios = $this->tareaService->buscarClientesTareas($buscar);

        // También buscar en clientes de la empresa (tabla operativa)
        $empresa = [];
        try {
            $idEmpresa = isset($_SESSION['id_empresa']) ? (int) $_SESSION['id_empresa'] : null;
            $empresa   = $this->tareaService->buscarClientesEmpresa($buscar, $idEmpresa);
        } catch (\Throwable $e) {
            // Silencioso si falla
        }

        $this->json(['ok' => true, 'propios' => $propios, 'empresa' => $empresa]);
    }

    public function buscarUsuarios(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $buscar = trim($_GET['q'] ?? $_POST['q'] ?? '');
        // Devuelve usuarios del sistema y responsables propios
        $resultado = $this->tareaService->buscarUsuarios($buscar);
        echo json_encode([
            'ok'     => true,
            'sistema' => $resultado['sistema'],
            'propios' => $resultado['propios'],
        ]);
        exit;
    }

    /**
     * Crea o actualiza un responsable en responsables_tareas.
     */
    public function crearResponsableTarea(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $cedula   = trim($_POST['cedula'] ?? '');
        $nombre   = trim($_POST['nombre'] ?? '');
        $correo   = trim($_POST['correo'] ?? '');
        $tel      = trim($_POST['telefono'] ?? '');

        if ($nombre === '' || $correo === '') {
            echo json_encode(['ok' => false, 'error' => 'Nombre y correo son obligatorios.']);
            exit;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'error' => 'El correo no es válido.']);
            exit;
        }

        try {
            $existente = $cedula !== '' ? $this->tareaService->findResponsableTareaByCedula($cedula) : null;
            if ($existente) {
                $this->tareaService->updateResponsableTarea($existente['id'], [
                    'cedula'     => $cedula,
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'telefono'   => $tel,
                    'updated_by' => $idUsuario,
                ]);
                echo json_encode(['ok' => true, 'id' => $existente['id'], 'nombre' => $nombre, 'mail' => $correo, 'tipo' => 'propio', 'msg' => 'Responsable actualizado.']);
            } else {
                $id = $this->tareaService->createResponsableTarea([
                    'cedula'     => $cedula ?: null,
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'telefono'   => $tel ?: null,
                    'created_by' => $idUsuario,
                ]);
                echo json_encode(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mail' => $correo, 'tipo' => 'propio', 'msg' => 'Responsable creado.']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getCorreosCliente(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $nombre = trim($_GET['nombre'] ?? '');
        $rows   = $this->tareaService->getCorreosClienteTarea($nombre);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }


    /**
     * Crea o actualiza un cliente en clientes_tareas.
     */
    public function crearClienteTarea(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $ruc    = trim($_POST['ruc'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');

        if ($nombre === '' || $correo === '') {
            echo json_encode(['ok' => false, 'error' => 'Nombre y correo son obligatorios.']);
            exit;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'error' => 'El correo no es válido.']);
            exit;
        }

        try {
            // Si ya existe por RUC, actualizamos; si no, creamos
            $existente = $ruc !== '' ? $this->tareaService->findClienteTareaByRuc($ruc) : null;
            if ($existente) {
                $this->tareaService->updateClienteTarea($existente['id'], [
                    'ruc'        => $ruc,
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'telefono'   => $tel,
                    'updated_by' => $idUsuario,
                ]);
                echo json_encode(['ok' => true, 'id' => $existente['id'], 'msg' => 'Cliente actualizado.']);
            } else {
                $id = $this->tareaService->createClienteTarea([
                    'ruc'        => $ruc ?: null,
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'telefono'   => $tel ?: null,
                    'created_by' => $idUsuario,
                ]);
                echo json_encode(['ok' => true, 'id' => $id, 'msg' => 'Cliente creado.']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ════════════════════════════════════════════════════════
    //  PRIVATE
    // ════════════════════════════════════════════════════════

    private function recogerDatosTarea(): array
    {
        return [
            'id_obligacion'      => (int) ($_POST['id_obligacion'] ?? 0),
            'id_cliente'         => !empty($_POST['id_cliente']) ? (int) $_POST['id_cliente'] : null,
            'cliente_nombre'     => trim($_POST['cliente_nombre'] ?? ''),
            'cliente_correo'     => trim($_POST['cliente_correo'] ?? ''),
            'periodicidad'       => trim($_POST['periodicidad'] ?? ''),
            'fecha_tarea'        => trim($_POST['fecha_tarea'] ?? ''),
            'estado'             => trim($_POST['estado'] ?? 'por_realizar'),
            'notas'              => trim($_POST['notas'] ?? ''),
            'resumen'            => trim($_POST['resumen'] ?? ''),
            'motivo_cancelacion' => trim($_POST['motivo_cancelacion'] ?? ''),
            'id_tarea_origen'    => !empty($_POST['id_tarea_origen']) ? (int) $_POST['id_tarea_origen'] : null,
            'id_empresa'         => (int) ($_SESSION['id_empresa'] ?? 0),
        ];
    }

    public function tareasAlertasCountAjax(): void
    {
        $this->requireAuth();
        try {
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            $count = $this->tareaService->getAlertaTareasCount($idUsuario);
            $this->json(['ok' => true, 'count' => $count]);
        } catch (\Exception $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
