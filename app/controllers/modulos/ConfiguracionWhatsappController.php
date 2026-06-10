<?php
/**
 * Controlador ConfiguracionWhatsappController
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\WhatsappConfig;
use App\models\WhatsappPlantilla;
use App\services\WhatsappService;

class ConfiguracionWhatsappController extends BaseModuloController
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
        return 'modulos/configuracion-whatsapp';
    }

    /**
     * Muestra la vista principal de Configuración
     */
    public function index(): void
    {
        $this->requireLeer();
        
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config = $this->configModel->obtenerConfiguracion($idEmpresa);
        $permisos = $this->getPermisos();

        $data = [
            'titulo' => 'Configuración de WhatsApp',
            'config' => $config,
            'permisos' => $permisos
        ];

        $this->viewWithLayout('layouts.main', 'modulos.configuracion_whatsapp.index', $data);
    }

    /**
     * Guarda o actualiza la configuración de la API (AJAX)
     */
    public function guardarConfiguracion(): void
    {
        $this->requireCrear();
        
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $datos = [
            'access_token' => $_POST['access_token'] ?? '',
            'phone_number_id' => $_POST['phone_number_id'] ?? '',
            'waba_id' => $_POST['waba_id'] ?? '',
            'app_id' => $_POST['app_id'] ?? '',
            'webhook_verify_token' => $_POST['webhook_verify_token'] ?? ''
        ];

        if (empty($datos['access_token']) || empty($datos['phone_number_id']) || empty($datos['waba_id']) || empty($datos['app_id'])) {
            echo json_encode(['ok' => false, 'error' => 'Los campos de Token, Phone ID, WABA ID y App ID son obligatorios']);
            return;
        }

        $resultado = $this->configModel->guardarConfiguracion($idEmpresa, $datos, $idUsuario);

        if ($resultado) {
            echo json_encode(['ok' => true, 'mensaje' => 'Configuración guardada correctamente']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar la configuración']);
        }
    }

    /**
     * Prueba la conexión con la API de Meta (AJAX)
     */
    public function probarConexion(): void
    {
        $this->requireLeer();

        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $resultado = $this->whatsappService->testConnection($idEmpresa);

        echo json_encode(['ok' => $resultado['success'], 'mensaje' => $resultado['message']]);
    }

    // =========================================================================
    // AVISOS DE MENSAJES NO LEÍDOS
    // =========================================================================

    /**
     * Devuelve la configuración de avisos + lista de números (AJAX GET)
     */
    public function getAvisoConfigAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $stmtCfg = $this->db->prepare(
            "SELECT activo, umbral_minutos, cooldown_minutos, plantilla_nombre, plantilla_idioma
             FROM whatsapp_aviso_config
             WHERE id_empresa = ? AND eliminado = FALSE
             LIMIT 1"
        );
        $stmtCfg->execute([$idEmpresa]);
        $config = $stmtCfg->fetch(\PDO::FETCH_ASSOC);

        $stmtNums = $this->db->prepare(
            "SELECT id, telefono, nombre, activo
             FROM whatsapp_aviso_numeros
             WHERE id_empresa = ? AND eliminado = FALSE
             ORDER BY id ASC"
        );
        $stmtNums->execute([$idEmpresa]);
        $numeros = $stmtNums->fetchAll(\PDO::FETCH_ASSOC);

        // Último aviso enviado
        $stmtLog = $this->db->prepare(
            "SELECT fecha_envio, chats_pendientes, numeros_notificados
             FROM whatsapp_aviso_log
             WHERE id_empresa = ?
             ORDER BY fecha_envio DESC
             LIMIT 1"
        );
        $stmtLog->execute([$idEmpresa]);
        $ultimoAviso = $stmtLog->fetch(\PDO::FETCH_ASSOC);

        // Plantillas aprobadas disponibles
        $plantillas = (new WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa);

        echo json_encode([
            'ok'           => true,
            'config'       => $config ?: null,
            'numeros'      => $numeros,
            'ultimo_aviso' => $ultimoAviso ?: null,
            'plantillas'   => $plantillas,
        ]);
    }

    /**
     * Guarda la configuración de avisos (AJAX POST)
     */
    public function guardarAvisoConfigAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa       = (int) $_SESSION['id_empresa'];
        $idUsuario       = (int) $_SESSION['id_usuario'];
        $activo          = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $umbral          = max(1, min(1440, (int) ($_POST['umbral_minutos'] ?? 30)));
        $cooldown        = $umbral; // El intervalo entre avisos es igual al tiempo de espera configurado
        $plantillaNombre = trim($_POST['plantilla_nombre'] ?? '');

        // Resolver idioma automáticamente desde la plantilla seleccionada
        $plantillaIdioma = 'es';
        if (!empty($plantillaNombre)) {
            $stmtIdioma = $this->db->prepare(
                "SELECT idioma FROM whatsapp_plantillas
                  WHERE id_empresa = ? AND nombre = ? AND eliminado = FALSE
                  LIMIT 1"
            );
            $stmtIdioma->execute([$idEmpresa, $plantillaNombre]);
            $plantillaIdioma = $stmtIdioma->fetchColumn() ?: 'es';
        }

        // Upsert
        $stmtCheck = $this->db->prepare(
            "SELECT id FROM whatsapp_aviso_config WHERE id_empresa = ? AND eliminado = FALSE"
        );
        $stmtCheck->execute([$idEmpresa]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe) {
            $this->db->prepare(
                "UPDATE whatsapp_aviso_config
                    SET activo = ?, umbral_minutos = ?, cooldown_minutos = ?,
                        plantilla_nombre = ?, plantilla_idioma = ?,
                        updated_at = CURRENT_TIMESTAMP, updated_by = ?
                  WHERE id_empresa = ? AND eliminado = FALSE"
            )->execute([$activo, $umbral, $cooldown, $plantillaNombre ?: null, $plantillaIdioma, $idUsuario, $idEmpresa]);
        } else {
            $this->db->prepare(
                "INSERT INTO whatsapp_aviso_config
                    (id_empresa, activo, umbral_minutos, cooldown_minutos, plantilla_nombre, plantilla_idioma, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$idEmpresa, $activo, $umbral, $cooldown, $plantillaNombre ?: null, $plantillaIdioma, $idUsuario, $idUsuario]);
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Configuración de avisos guardada correctamente.']);
    }

    /**
     * Agrega un número de teléfono para recibir avisos (AJAX POST)
     */
    public function agregarNumeroAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
            return;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $telefono  = preg_replace('/[^0-9+]/', '', trim($_POST['telefono'] ?? ''));
        $nombre    = trim($_POST['nombre'] ?? '');

        if (strlen($telefono) < 7) {
            echo json_encode(['ok' => false, 'error' => 'Teléfono inválido (mínimo 7 dígitos).']);
            return;
        }

        // Verificar que no exista ya el mismo teléfono en esta empresa
        $stmtDup = $this->db->prepare(
            "SELECT id FROM whatsapp_aviso_numeros
             WHERE id_empresa = ? AND telefono = ? AND eliminado = FALSE"
        );
        $stmtDup->execute([$idEmpresa, $telefono]);
        if ($stmtDup->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => 'El número ' . $telefono . ' ya está registrado.']);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO whatsapp_aviso_numeros
                (id_empresa, telefono, nombre, activo, created_by, updated_by)
             VALUES (?, ?, ?, TRUE, ?, ?)
             RETURNING id"
        );
        $stmt->execute([$idEmpresa, $telefono, $nombre ?: null, $idUsuario, $idUsuario]);
        $newId = (int) $stmt->fetchColumn();

        echo json_encode([
            'ok'     => true,
            'numero' => ['id' => $newId, 'telefono' => $telefono, 'nombre' => $nombre, 'activo' => true],
            'mensaje' => 'Número agregado correctamente.',
        ]);
    }

    /**
     * Elimina (lógicamente) un número de teléfono (AJAX POST)
     */
    public function eliminarNumeroAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        $this->db->prepare(
            "UPDATE whatsapp_aviso_numeros
                SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = ?
              WHERE id = ? AND id_empresa = ?"
        )->execute([$idUsuario, $id, $idEmpresa]);

        echo json_encode(['ok' => true, 'mensaje' => 'Número eliminado correctamente.']);
    }

    /**
     * Activa/desactiva un número (AJAX POST)
     */
    public function toggleNumeroAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $id        = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
            return;
        }

        $stmtGet = $this->db->prepare(
            "SELECT activo FROM whatsapp_aviso_numeros WHERE id = ? AND id_empresa = ? AND eliminado = FALSE"
        );
        $stmtGet->execute([$id, $idEmpresa]);
        $row = $stmtGet->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Número no encontrado.']);
            return;
        }

        $nuevoEstado = !$row['activo'];
        $this->db->prepare(
            "UPDATE whatsapp_aviso_numeros
                SET activo = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ?
              WHERE id = ? AND id_empresa = ?"
        )->execute([$nuevoEstado, $idUsuario, $id, $idEmpresa]);

        echo json_encode(['ok' => true, 'activo' => $nuevoEstado]);
    }
}
