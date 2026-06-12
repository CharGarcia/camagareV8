<?php

declare(strict_types=1);

namespace App\Controllers\modulos;

use App\core\Controller;
use App\Services\SriService;
use App\Services\modulos\DocumentoAutomatedRegisterService;
use App\Services\modulos\SriDescargaAutomaticaService;
use App\repositories\modulos\DocumentoIgnoradoRepository;
use App\repositories\modulos\SriConfigDescargaRepository;
use Exception;

class DescargasSriController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        
        // Descomentar cuando el módulo sea registrado en la base de datos
        // $this->requirePermiso('descargas_sri', 'ver');

        $this->viewWithLayout('layouts.main', 'modulos.descargas_sri.index', [
            'titulo'      => 'Descargas SRI',
            'rucEmpresa'  => $_SESSION['ruc_empresa'] ?? '',
            // 'perm' => $this->getPermisos('descargas_sri')
        ]);
    }

    /**
     * Procesa una clave de acceso (o varias separadas por coma/salto de línea)
     */
    public function procesarClavesAccesoAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $claves = $_POST['claves'] ?? '';
        $clavesArr = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $claves))));

        if (empty($clavesArr)) {
            echo json_encode(['ok' => false, 'error' => 'No se proporcionaron claves de acceso válidas.']);
            return;
        }

        $sriService = new SriService();
        $registerService = new DocumentoAutomatedRegisterService();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $resultados = [];

        foreach ($clavesArr as $clave) {
            if (strlen($clave) !== 49) {
                $resultados[] = [
                    'clave' => $clave,
                    'estado' => 'ERROR',
                    'mensaje' => 'La longitud de la clave no es 49 dígitos.'
                ];
                continue;
            }

            $respuesta = $sriService->obtenerComprobanteXml($clave);
            
            $xmlFile = null;
            $mensaje = $respuesta['mensaje'] ?? '';
            $infoDetalle = null;
            $estadoRegistro = 'PENDIENTE';

            if ($respuesta['ok'] && !empty($respuesta['xml'])) {
                $xmlFile = base64_encode($respuesta['xml']);
                
                // Registro automático
                $resRegistro = $registerService->procesarYRegistrar($respuesta['xml'], $idEmpresa, $idUsuario);
                
                if ($resRegistro['ok']) {
                    $estadoRegistro = $resRegistro['estado_registro'] ?? 'PROCESADO';
                    $mensaje = $resRegistro['mensaje'] ?? 'Procesado correctamente.';
                    $infoDetalle = $resRegistro;
                } else {
                    $estadoRegistro = 'ERROR';
                    $mensaje = 'XML obtenido pero error en registro: ' . ($resRegistro['error'] ?? 'Error desconocido.');
                }
            } elseif (empty($mensaje)) {
                $mensaje = 'Error al consultar el comprobante en el SRI.';
            }

            $resultados[] = [
                'clave' => $clave,
                'estado' => $respuesta['estado'] ?? 'DESCONOCIDO',
                'estado_registro' => $estadoRegistro,
                'mensaje' => $mensaje,
                'info' => $infoDetalle,
                'ok' => $respuesta['ok'],
                'xml_base64' => $xmlFile
            ];
        }

        echo json_encode(['ok' => true, 'resultados' => $resultados]);
        exit;
    }

    public function registrarComprobanteAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $clave = $_POST['clave'] ?? '';
        $xmlBase64 = $_POST['xml_base64'] ?? '';
        
        if (empty($clave)) {
            echo json_encode(['ok' => false, 'error' => 'No se proporcionó la clave de acceso.']);
            return;
        }

        try {
            $xmlString = '';
            if (!empty($xmlBase64)) {
                $xmlString = base64_decode($xmlBase64);
            } else {
                // Si no viene el XML, intentamos consultarlo de nuevo (o buscarlo en cache si hubiera)
                $sriService = new SriService();
                $resp = $sriService->obtenerComprobanteXml($clave);
                if ($resp['ok']) {
                    $xmlString = $resp['xml'];
                } else {
                    throw new Exception("No se pudo obtener el XML del SRI para la clave $clave");
                }
            }

            $registerService = new DocumentoAutomatedRegisterService();
            $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

            $res = $registerService->procesarYRegistrar($xmlString, $idEmpresa, $idUsuario);

            echo json_encode($res);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Procesa el archivo TXT descargado del SRI
     */
    public function procesarTxtSriAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!isset($_FILES['archivo_txt']) || $_FILES['archivo_txt']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo subir el archivo TXT.']);
            return;
        }

        $contenido = file_get_contents($_FILES['archivo_txt']['tmp_name']);
        $lineas = explode("\n", str_replace("\r", "", $contenido));
        
        $clavesEncontradas = [];
        $headerIndex = -1;

        if (!empty($lineas)) {
            // Intentar detectar la columna CLAVE_ACCESO en la primera línea o cabecera
            $headers = explode("\t", $lineas[0]);
            foreach ($headers as $idx => $h) {
                if (trim($h) === 'CLAVE_ACCESO') {
                    $headerIndex = $idx;
                    break;
                }
            }

            if ($headerIndex !== -1) {
                // Procesar por columna
                for ($i = 1; $i < count($lineas); $i++) {
                    $cols = explode("\t", $lineas[$i]);
                    if (isset($cols[$headerIndex])) {
                        $c = trim($cols[$headerIndex]);
                        if (strlen($c) === 49 && is_numeric($c)) {
                            $clavesEncontradas[] = $c;
                        }
                    }
                }
            } else {
                // Fallback a regex si no se detecta la estructura de columnas
                preg_match_all('/\b\d{49}\b/', $contenido, $matches);
                $clavesEncontradas = $matches[0] ?? [];
            }
        }

        $clavesEncontradas = array_values(array_unique($clavesEncontradas));

        if (empty($clavesEncontradas)) {
            echo json_encode(['ok' => false, 'error' => 'No se encontraron claves de acceso válidas en el archivo TXT.']);
            return;
        }

        echo json_encode([
            'ok' => true, 
            'claves' => $clavesEncontradas,
            'total' => count($clavesEncontradas),
            'mensaje' => count($clavesEncontradas) . ' claves detectadas. Listas para procesar.'
        ]);
        exit;
    }

    /**
     * Procesa archivos XML subidos masivamente
     */
    public function procesarArchivosXmlAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if (!isset($_FILES['archivos_xml'])) {
            echo json_encode(['ok' => false, 'error' => 'No se recibieron archivos XML.']);
            return;
        }

        $archivos = $_FILES['archivos_xml'];
        $total = count($archivos['name']);
        $resultados = [];
        
        $registerService = new DocumentoAutomatedRegisterService();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        for ($i = 0; $i < $total; $i++) {
            $nombre = $archivos['name'][$i];
            $tmp = $archivos['tmp_name'][$i];
            $error = $archivos['error'][$i];

            if ($error !== UPLOAD_ERR_OK) {
                $resultados[] = ['clave' => $nombre, 'estado' => 'ERROR', 'mensaje' => 'Error al subir archivo.'];
                continue;
            }

            $xmlContent = file_get_contents($tmp);
            
            try {
                $res = $registerService->procesarYRegistrar($xmlContent, $idEmpresa, $idUsuario);
                
                $resultados[] = [
                    'clave' => $res['numero_documento'] ?? $nombre,
                    'estado' => $res['ok'] ? 'AUTORIZADO' : 'ERROR',
                    'estado_registro' => $res['estado_registro'] ?? 'ERROR',
                    'mensaje' => $res['mensaje'] ?? ($res['error'] ?? 'Error desconocido'),
                    'info' => $res,
                    'ok' => $res['ok'],
                    'xml_base64' => base64_encode($xmlContent)
                ];
            } catch (Exception $e) {
                $resultados[] = ['clave' => $nombre, 'estado' => 'ERROR', 'mensaje' => $e->getMessage()];
            }
        }

        echo json_encode(['ok' => true, 'resultados' => $resultados, 'total' => $total]);
        exit;
    }
    public function listarDocumentosIgnoradosAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');
        
        $repo = new DocumentoIgnoradoRepository();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        
        $lista = $repo->getListado($idEmpresa);
        echo json_encode(['ok' => true, 'data' => $lista]);
        exit;
    }

    public function agregarDocumentoIgnoradoAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');
        
        $clave = $_POST['clave_acceso'] ?? '';
        $np    = $_POST['nombre_proveedor'] ?? '';
        $fd    = $_POST['fecha_documento'] ?? '';
        $obs   = $_POST['observaciones'] ?? '';
        
        if (strlen($clave) !== 49) {
            echo json_encode(['ok' => false, 'error' => 'La clave de acceso debe tener 49 dígitos.']);
            return;
        }

        $repo = new DocumentoIgnoradoRepository();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($repo->existeClave($clave, $idEmpresa)) {
            echo json_encode(['ok' => false, 'error' => 'Esta clave ya se encuentra en la lista de ignorados.']);
            return;
        }

        $res = $repo->insertar([
            'id_empresa'       => $idEmpresa,
            'clave_acceso'     => $clave,
            'nombre_proveedor' => $np,
            'fecha_documento'  => $fd,
            'observaciones'    => $obs,
            'id_usuario'       => $idUsuario
        ]);

        echo json_encode(['ok' => $res, 'mensaje' => $res ? 'Documento añadido a la lista negra.' : 'Error al guardar.']);
        exit;
    }

    public function eliminarDocumentoIgnoradoAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['ok' => false, 'error' => 'ID no válido.']);
            return;
        }

        $repo = new DocumentoIgnoradoRepository();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        $res = $repo->eliminar($id, $idEmpresa, $idUsuario);
        echo json_encode(['ok' => $res, 'mensaje' => $res ? 'Registro eliminado.' : 'Error al eliminar.']);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESCARGA AUTOMÁTICA — configuración, ejecución manual e historial
    // ─────────────────────────────────────────────────────────────────────────

    public function obtenerConfigDescargaAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idEmpresa  = (int)    ($_SESSION['id_empresa']  ?? 0);
        $rucEmpresa = (string) ($_SESSION['ruc_empresa'] ?? '');

        $repo   = new SriConfigDescargaRepository();
        $config = $repo->getConfigEmpresa($idEmpresa);

        $config = $config ?? [
            'estado'                  => 'inactivo',
            'tipos_documento'         => 'todos',
            'sri_clave_guardada'      => false,
            'login_bloqueado'         => false,
            'login_bloqueado_motivo'  => null,
            'ultima_descarga'         => null,
            'ultimo_estado'           => null,
            'ultimo_mensaje'          => null,
        ];

        // Siempre exponer el RUC de la empresa activa como usuario SRI
        $config['sri_usuario'] = $rucEmpresa;

        echo json_encode(['ok' => true, 'config' => $config]);
        exit;
    }

    public function guardarConfigDescargaAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            return;
        }

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        $repo = new SriConfigDescargaRepository();
        $res  = $repo->guardarConfig($_POST, $idEmpresa, $idUsuario);

        echo json_encode($res);
        exit;
    }

    public function ejecutarDescargaManualAjax(): void
    {
        set_time_limit(0);
        $this->requireAuth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            return;
        }

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        $ano  = isset($_POST['ano'])  ? (int) $_POST['ano']  : null;
        $mes  = isset($_POST['mes'])  ? (int) $_POST['mes']  : null;
        $dia  = isset($_POST['dia'])  ? (int) $_POST['dia']  : null;
        $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : null;

        if ($mes  === 0) $mes  = null;
        if ($dia  === 0) $dia  = null;
        if ($tipo === 'todos') $tipo = null;

        // Desactivar buffering para streaming en tiempo real
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 'false');
        @ini_set('implicit_flush', '1');
        @ob_end_clean();
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Accel-Buffering: no');
        
        session_write_close(); // Permitir que el usuario navegue en otras pestañas

        try {
            $svc = new SriDescargaAutomaticaService();
            $svc->ejecutarParaEmpresaStream($idEmpresa, $idUsuario, $ano, $mes, $dia, $tipo);
        } catch (Exception $e) {
            echo json_encode(['type' => 'error', 'error' => $e->getMessage()]) . "\n";
        }
        exit;
    }

    public function historialDescargasAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $limite    = max(1, min(200, (int) ($_GET['limite'] ?? 5)));

        $repo = new SriConfigDescargaRepository();
        $data = $repo->getHistorial($idEmpresa, $limite);

        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function detalleLogAjax(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idLog     = (int) ($_GET['id'] ?? 0);

        if (!$idLog) { echo json_encode(['ok' => false, 'error' => 'ID inválido']); exit; }

        $logMod = new \App\models\SriDescargaAutoLog();
        $fila   = $logMod->getPorId($idLog, $idEmpresa);

        if (!$fila) { echo json_encode(['ok' => false, 'error' => 'Log no encontrado']); exit; }

        $detalle = json_decode($fila['detalle_json'] ?? '{}', true) ?: [];

        echo json_encode(['ok' => true, 'log' => $fila, 'detalle' => $detalle]);
        exit;
    }
}
