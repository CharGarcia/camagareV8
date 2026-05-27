<?php
/**
 * Controlador ConfiguracionWhatsappController
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\WhatsappConfig;
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
}
