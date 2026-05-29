<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\WhatsappConfig;
use App\models\WhatsappPlantilla;
use App\repositories\modulos\WhatsappMensajeRepository;
use App\services\WhatsappService;

class WhatsappCampanasController extends BaseModuloController
{
    private WhatsappMensajeRepository $repository;
    private WhatsappService $whatsappService;
    private WhatsappConfig $configModel;
    private WhatsappPlantilla $plantillaModel;

    public function __construct()
    {
        parent::__construct();
        $this->repository      = new WhatsappMensajeRepository();
        $this->whatsappService = new WhatsappService();
        $this->configModel     = new WhatsappConfig();
        $this->plantillaModel  = new WhatsappPlantilla();
    }

    protected function getRutaModulo(): string
    {
        return 'modulos/whatsapp-campanas';
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $config     = $this->configModel->obtenerConfiguracion($idEmpresa);
        $configurado = ($config && !empty($config['phone_number_id']) && !empty($config['access_token']));

        $this->viewWithLayout('layouts.main', 'modulos/whatsapp_campanas/index', [
            'titulo'      => 'Campañas Masivas de WhatsApp',
            'configurado' => $configurado,
            'permisos'    => $this->getPermisos(),
        ]);
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $stmt = $this->db->prepare(
            "SELECT id, nombre, identificacion, telefono
             FROM clientes
             WHERE id_empresa = :id_empresa
               AND status = 1
               AND eliminado = false
               AND telefono IS NOT NULL
               AND telefono != ''
             ORDER BY nombre ASC"
        );
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'clientes' => $clientes]);
    }

    public function getPlantillasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $plantillas = $this->plantillaModel->getAprobadas($idEmpresa);

        $plantillasData = [];
        foreach ($plantillas as $p) {
            $componentes  = is_string($p['componentes']) ? json_decode($p['componentes'], true) : $p['componentes'];
            $variablesBody = 0;

            if (is_array($componentes)) {
                foreach ($componentes as $comp) {
                    if (($comp['type'] ?? '') === 'BODY' && isset($comp['example']['body_text'][0])) {
                        $variablesBody = count($comp['example']['body_text'][0]);
                    }
                }
            }

            $plantillasData[] = [
                'nombre'    => $p['nombre'],
                'idioma'    => $p['idioma'],
                'variables' => $variablesBody,
            ];
        }

        echo json_encode(['ok' => true, 'plantillas' => $plantillasData]);
    }

    public function sendCampanaMessageAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $input         = json_decode(file_get_contents('php://input'), true) ?? [];
        $telefono      = trim($input['telefono']      ?? '');
        $nombreCliente = trim($input['nombreCliente'] ?? '');
        $plantilla     = trim($input['plantilla']     ?? '');
        $idioma        = trim($input['idioma']        ?? 'es');
        $variables     = $input['variables']          ?? [];

        if (empty($telefono) || empty($plantilla)) {
            echo json_encode(['ok' => false, 'error' => 'Teléfono y plantilla son requeridos.']);
            return;
        }

        // Normalizar teléfono a formato internacional Ecuador
        if (str_starts_with($telefono, '0')) {
            $telefono = '593' . substr($telefono, 1);
        } elseif (!str_starts_with($telefono, '593')) {
            $telefono = '593' . $telefono;
        }

        // Armar componentes para la API de Meta
        $apiComponents = [];
        if (!empty($variables)) {
            $paramsList = [];
            foreach ($variables as $val) {
                $paramsList[] = ['type' => 'text', 'text' => (string) $val];
            }
            $apiComponents[] = ['type' => 'body', 'parameters' => $paramsList];
        }

        $response = $this->whatsappService->sendTemplateMessage(
            $idEmpresa,
            $telefono,
            $plantilla,
            $idioma,
            $apiComponents
        );

        if ($response['success'] ?? false) {
            // Obtener texto de la plantilla para el historial
            $templateText = $this->plantillaModel->extraerTextoCuerpo($idEmpresa, $plantilla, $variables);

            try {
                $metaMessageId = $response['data']['messages'][0]['id'] ?? null;
                $idChat = $this->repository->getOrCreateChat(
                    $idEmpresa,
                    $telefono,
                    $nombreCliente,
                    'Campaña: ' . $plantilla,
                    false
                );

                $this->repository->saveMessage(
                    $idEmpresa,
                    $idChat,
                    'OUT',
                    $telefono,
                    'template',
                    ['template' => $plantilla, 'variables' => $variables, 'template_text' => $templateText],
                    $metaMessageId,
                    'sent'
                );

                echo json_encode(['ok' => true]);
            } catch (\Throwable $e) {
                error_log('Error guardando mensaje de campaña en BD: ' . $e->getMessage());
                echo json_encode(['ok' => true, 'warning' => 'Enviado pero no guardado en historial.']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => $response['message'] ?? 'Error desconocido al enviar']);
        }
    }
}
