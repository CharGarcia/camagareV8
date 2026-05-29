<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\models\Cliente;
use App\models\Empresa;
use App\repositories\modulos\WhatsappMensajeRepository;
use App\services\WhatsappService;

class WhatsappCampanasController extends Controller
{
    private WhatsappMensajeRepository $repository;
    private WhatsappService $whatsappService;
    private Cliente $clienteModel;

    public function __construct()
    {
        parent::__construct();
        $this->repository = new WhatsappMensajeRepository();
        $this->whatsappService = new WhatsappService();
        $this->clienteModel = new Cliente();
    }

    public function index(): void
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        // Verificar si la empresa tiene Whatsapp configurado
        $config = $this->db->query("SELECT phone_number_id, access_token FROM empresa_whatsapp_config WHERE id_empresa = {$idEmpresa}")->fetch(\PDO::FETCH_ASSOC);
        $configurado = !empty($config['phone_number_id']) && !empty($config['access_token']);

        $this->viewWithLayout('layouts.main', 'modulos/whatsapp_campanas/index', [
            'titulo' => 'Campañas Masivas de WhatsApp',
            'configurado' => $configurado
        ]);
    }

    public function getClientesAjax(): void
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        // Obtener clientes activos
        // Filtramos para asegurar que tengan telfono (aunque sea por JS o en SQL)
        $sql = "SELECT id, nombre, identificacion, telefono 
                FROM clientes 
                WHERE id_empresa = :id_empresa AND status = 1 AND eliminado = false 
                AND telefono IS NOT NULL AND telefono != ''
                ORDER BY nombre ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $clientes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        echo json_encode(['ok' => true, 'clientes' => $clientes]);
    }

    public function getPlantillasAjax(): void
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $sql = "SELECT nombre, idioma, componentes 
                FROM whatsapp_plantillas 
                WHERE id_empresa = :id_empresa AND estado_meta = 'APPROVED' AND status = true AND eliminado = false";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_empresa' => $idEmpresa]);
        $plantillas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Formatear plantillas para extraer las variables necesarias
        $plantillasData = [];
        foreach ($plantillas as $p) {
            $componentes = json_decode($p['componentes'], true);
            $variablesBody = 0;
            
            // Buscar si el BODY tiene variables
            if (is_array($componentes)) {
                foreach ($componentes as $comp) {
                    if (($comp['type'] ?? '') === 'BODY' && isset($comp['example']['body_text'][0])) {
                        // El example array tiene un elemento por cada variable
                        $variablesBody = count($comp['example']['body_text'][0]);
                    }
                }
            }
            
            $plantillasData[] = [
                'nombre' => $p['nombre'],
                'idioma' => $p['idioma'],
                'variables' => $variablesBody
            ];
        }
        
        echo json_encode(['ok' => true, 'plantillas' => $plantillasData]);
    }

    public function sendCampanaMessageAjax(): void
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        
        $input = json_decode(file_get_contents('php://input'), true);
        $telefono = $input['telefono'] ?? '';
        $nombreCliente = $input['nombreCliente'] ?? '';
        $plantilla = $input['plantilla'] ?? '';
        $idioma = $input['idioma'] ?? 'es';
        $variables = $input['variables'] ?? [];

        if (empty($telefono) || empty($plantilla)) {
            echo json_encode(['ok' => false, 'error' => 'Telfono y plantilla son requeridos.']);
            return;
        }

        // Limpiar telfono
        if (str_starts_with($telefono, '0')) {
            $telefono = '593' . substr($telefono, 1);
        } else if (!str_starts_with($telefono, '593')) {
            $telefono = '593' . $telefono; // Asumir Ecuador por defecto si no tiene prefijo, segn regla
        }

        // Mapear variables al formato de componentes de Meta
        $apiComponents = [];
        if (!empty($variables)) {
            $paramsList = [];
            foreach ($variables as $val) {
                $paramsList[] = [
                    'type' => 'text',
                    'text' => (string) $val
                ];
            }
            $apiComponents[] = [
                'type' => 'body',
                'parameters' => $paramsList
            ];
        }

        // Enviar va API
        $response = $this->whatsappService->sendTemplateMessage(
            $idEmpresa,
            $telefono,
            $plantilla,
            $idioma,
            $apiComponents
        );

        if ($response['success'] ?? false) {
            // Obtener el texto de la plantilla de la BD para guardarlo en el historial
            $sql = "SELECT componentes FROM whatsapp_plantillas WHERE id_empresa = ? AND nombre = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idEmpresa, $plantilla]);
            $plantillaRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $templateText = '';
            if ($plantillaRow && !empty($plantillaRow['componentes'])) {
                $comp = json_decode($plantillaRow['componentes'], true);
                if (is_array($comp)) {
                    foreach ($comp as $c) {
                        if (($c['type'] ?? '') === 'BODY') {
                            $templateText = $c['text'] ?? '';
                            // Reemplazar las variables {{1}}, {{2}} con sus valores
                            foreach ($variables as $idx => $val) {
                                $varNum = $idx + 1;
                                $templateText = str_replace('{{' . $varNum . '}}', $val, $templateText);
                            }
                        }
                    }
                }
            }

            // Guardar en la base de datos para que aparezca en el Chat Center
            try {
                $metaMessageId = $response['data']['messages'][0]['id'] ?? null;
                $idChat = $this->repository->getOrCreateChat($idEmpresa, $telefono, $nombreCliente, 'Campaña: ' . $plantilla, false);
                
                $contenidoArray = [
                    'template' => $plantilla,
                    'variables' => $variables,
                    'template_text' => $templateText
                ];

                $this->repository->saveMessage(
                    $idEmpresa,
                    $idChat,
                    'OUT',
                    $telefono,
                    'template',
                    $contenidoArray,
                    $metaMessageId,
                    'sent'
                );

                echo json_encode(['ok' => true]);
            } catch (\Exception $e) {
                // El mensaje se envi pero fall el guardado local
                error_log("Error guardando mensaje de campaa en BD: " . $e->getMessage());
                echo json_encode(['ok' => true, 'warning' => 'Enviado pero no guardado en historial.']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => $response['message'] ?? 'Error desconocido al enviar']);
        }
    }
}
