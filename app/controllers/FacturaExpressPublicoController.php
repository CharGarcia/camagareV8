<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\FacturaExpressQrRepository;
use App\Rules\modulos\FacturaExpressQrRules;
use App\Services\LogSistemaService;
use App\Services\modulos\FacturaExpressQrService;

/**
 * Controlador PÚBLICO — sin autenticación.
 * Muestra y procesa el formulario Express QR accesible por token.
 */
class FacturaExpressPublicoController extends Controller
{
    private FacturaExpressQrService    $service;
    private FacturaExpressQrRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new FacturaExpressQrRepository();
        $this->service = new FacturaExpressQrService(
            $this->repo,
            new FacturaExpressQrRules(),
            new LogSistemaService()
        );
    }

    // ── GET: mostrar formulario público ──────────────────────────────────────
    public function index(): void
    {
        $token     = trim($_GET['token'] ?? '');
        $plantilla = $this->repo->getPlantillaByToken($token);

        if (!$plantilla) {
            $this->renderError('Este enlace no está disponible o ha sido desactivado.');
            return;
        }

        $items  = $this->repo->getItemsActivosPorPlantilla((int) $plantilla['id']);
        $config = json_decode($plantilla['campos_config'] ?? '{}', true) ?: [];

        $this->renderFormulario($plantilla, $items, $config, [], null);
    }

    // ── POST: procesar solicitud ──────────────────────────────────────────────
    public function enviar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        $token = trim($_GET['token'] ?? '');

        // Reconstruir ítems seleccionados desde el POST
        $itemsSeleccionados = [];
        $itemsRaw = $_POST['items'] ?? [];
        if (is_array($itemsRaw)) {
            foreach ($itemsRaw as $item) {
                $cantidad = (float) ($item['cantidad'] ?? 0);
                if ($cantidad <= 0) continue;
                $itemsSeleccionados[] = [
                    'id_item'        => (int) ($item['id_item'] ?? 0),
                    'id_producto'    => (int) ($item['id_producto'] ?? 0) ?: null,
                    'descripcion'    => trim($item['descripcion'] ?? ''),
                    'cantidad'       => $cantidad,
                    'precio_unitario'=> (float) ($item['precio_unitario'] ?? 0),
                    'porcentaje_iva' => (float) ($item['porcentaje_iva'] ?? 0),
                ];
            }
        }

        $formData = [
            'nombre_cliente'      => trim($_POST['nombre_cliente'] ?? ''),
            'identificacion'      => trim($_POST['identificacion'] ?? ''),
            'tipo_identificacion' => trim($_POST['tipo_identificacion'] ?? 'cedula'),
            'correo_cliente'      => trim($_POST['correo_cliente'] ?? ''),
            'telefono_cliente'    => trim($_POST['telefono_cliente'] ?? ''),
        ];

        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        try {
            $resultado = $this->service->recibirSolicitudPublica($token, $formData, $itemsSeleccionados, $ip, $userAgent);

            // Enviar correo al dueño del negocio
            $this->enviarCorreoDueno($resultado);

            // Enviar correo al cliente si tiene correo
            if (!empty($formData['correo_cliente'])) {
                $this->enviarCorreoCliente($resultado);
            }

            // Mostrar página de confirmación
            $this->renderConfirmacion($resultado);

        } catch (\InvalidArgumentException $e) {
            // Error de validación: volver al formulario con el error
            $plantilla = $this->repo->getPlantillaByToken($token);
            $items     = $plantilla ? $this->repo->getItemsActivosPorPlantilla((int)$plantilla['id']) : [];
            $config    = json_decode($plantilla['campos_config'] ?? '{}', true) ?: [];
            $this->renderFormulario($plantilla, $items, $config, $formData, $e->getMessage());
        } catch (\Exception $e) {
            $this->renderError('Ocurrió un error al procesar tu solicitud. Por favor intenta nuevamente.');
        }
    }

    // ── GET: estado de la solicitud (para el cliente) ─────────────────────────
    public function estado(): void
    {
        $token    = trim($_GET['token'] ?? '');
        $solicitud = $this->repo->getSolicitudByTokenCliente($token);

        if (!$solicitud) {
            $this->renderError('No se encontró información para este enlace.');
            return;
        }

        $solicitud['items'] = json_decode($solicitud['items_json'] ?? '[]', true) ?: [];
        $this->renderEstado($solicitud);
    }

    // ── GET AJAX: consultar identificación (local primero, luego SRI) ─────────
    public function consultarSri(): void
    {
        header('Content-Type: application/json');
        $identificacion = preg_replace('/\D/', '', trim($_GET['identificacion'] ?? ''));
        if (strlen($identificacion) < 10) {
            echo json_encode(['ok' => false]);
            return;
        }
        try {
            $sriService = new \App\Services\SriIdentificationService();
            $resultado  = $sriService->consultar($identificacion);
            if ($resultado['ok'] && !empty($resultado['data'])) {
                $data = $resultado['data'];
                echo json_encode([
                    'ok'     => true,
                    'nombre' => $data['nombre'] ?? '',
                    'correo' => $data['mail']   ?? '',
                ]);
            } else {
                echo json_encode(['ok' => false, 'error' => $resultado['error'] ?? '']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false]);
        }
    }

    // ── Renderizar formulario ─────────────────────────────────────────────────
    private function renderFormulario(?array $plantilla, array $items, array $config, array $formData, ?string $error): void
    {
        extract([
            'plantilla' => $plantilla,
            'items'     => $items,
            'config'    => $config,
            'formData'  => $formData,
            'error'     => $error,
        ]);
        $viewPath = MVC_APP . '/views/publica/factura-express/formulario.php';
        require $viewPath;
    }

    private function renderConfirmacion(array $resultado): void
    {
        extract(['resultado' => $resultado]);
        $viewPath = MVC_APP . '/views/publica/factura-express/confirmacion.php';
        require $viewPath;
    }

    private function renderEstado(array $solicitud): void
    {
        extract(['solicitud' => $solicitud]);
        $viewPath = MVC_APP . '/views/publica/factura-express/estado.php';
        require $viewPath;
    }

    private function renderError(string $mensaje): void
    {
        extract(['mensaje' => $mensaje]);
        $viewPath = MVC_APP . '/views/publica/factura-express/error.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            http_response_code(404);
            echo '<!DOCTYPE html><html><body><div style="font-family:sans-serif;text-align:center;padding:60px"><h2>Enlace no disponible</h2><p>' . htmlspecialchars($mensaje) . '</p></div></body></html>';
        }
    }

    // ── Envío de correos ──────────────────────────────────────────────────────
    private function enviarCorreoDueno(array $resultado): void
    {
        try {
            require_once MVC_APP . '/helpers/mail.php';
            if (function_exists('enviar_correo_factura_express_dueno')) {
                $enviado = enviar_correo_factura_express_dueno($resultado);
                if ($enviado) {
                    $this->repo->marcarCorreoEnviadoDueno((int)$resultado['id']);
                }
            }
        } catch (\Throwable $e) {
            // Correo fallido no debe interrumpir el flujo
        }
    }

    private function enviarCorreoCliente(array $resultado): void
    {
        try {
            require_once MVC_APP . '/helpers/mail.php';
            if (function_exists('enviar_correo_factura_express_cliente')) {
                $enviado = enviar_correo_factura_express_cliente($resultado);
                if ($enviado) {
                    $this->repo->marcarCorreoEnviadoCliente((int)$resultado['id']);
                }
            }
        } catch (\Throwable $e) {
            // Correo fallido no debe interrumpir el flujo
        }
    }
}
