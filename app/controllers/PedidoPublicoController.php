<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\CajaSesionRepository;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MenuRepository;
use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Rules\modulos\ComandaRules;
use App\Services\LogSistemaService;
use App\Services\modulos\CajaSesionService;
use App\Services\modulos\ComandaService;
use App\Services\modulos\PosVentaService;
use Exception;

/**
 * Portal público QR de la mesa (POS Restaurantes) — sin autenticación, sin
 * sesión de usuario. El cliente escanea el QR de su mesa, ve el Menú (solo
 * modulos/menu, no el catálogo interno de Stock General) y pide directo:
 * los ítems se agregan a la comanda de esa mesa (self-service — la abre si
 * no hay una) y puede "confirmar pedido" (enviarlos a cocina/barra). Reutiliza
 * el mismo ComandaService que usa el mesero desde modulos/comandas/ver — no
 * duplica lógica de negocio, solo cambia quién puede llamarlo y cómo se
 * identifica la comanda (por token de mesa, no por sesión).
 *
 * Fase 1: sin datos de facturación del cliente ni cobro/pago — eso llega
 * después de probar este flujo (ver plan del módulo).
 */
class PedidoPublicoController extends Controller
{
    private ComandaService $comandaService;
    private MesaRepository $mesaRepo;
    private MenuRepository $menuRepo;
    private CajaSesionService $cajaService;

    public function __construct()
    {
        parent::__construct();
        $this->mesaRepo = new MesaRepository();
        $this->menuRepo = new MenuRepository();
        $logService = new LogSistemaService();
        $this->cajaService = new CajaSesionService(new CajaSesionRepository(), new CajaSesionRules(), $logService);
        $ventaService = new PosVentaService($this->cajaService, $logService);
        $this->comandaService = new ComandaService(
            new ComandaRepository(),
            new ComandaRules(),
            $this->mesaRepo,
            $logService,
            $ventaService,
            $this->menuRepo
        );
    }

    public function index(): void
    {
        $token = trim($_GET['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
        } catch (\Throwable $e) {
            $this->renderError($e->getMessage());
            return;
        }

        $this->view('publica.pedido.index', [
            'titulo'     => 'Pedir — Mesa ' . ($mesa['nombre'] ?? ''),
            'token'      => $token,
            'mesa'       => $mesa,
            'comanda'    => $comanda,
            'documentos' => $this->getDocumentosPermitidos($mesa),
        ]);
    }

    /** Qué documento(s) puede pedir el cliente al "Pedir mi cuenta" — configurado por mesa (modulos/mesas). Factura es la opción principal cuando ambas están permitidas. */
    private function getDocumentosPermitidos(array $mesa): array
    {
        $permiteFactura = $mesa['permite_factura'] === 'true' || $mesa['permite_factura'] === true;
        $permiteRecibo  = $mesa['permite_recibo'] === 'true' || $mesa['permite_recibo'] === true;
        if (!$permiteFactura && !$permiteRecibo) {
            $permiteFactura = true; // nunca dejar sin ninguna opción
        }
        return ['factura' => $permiteFactura, 'recibo' => $permiteRecibo];
    }

    /** Consulta al SRI (cédula/RUC) para autocompletar nombre/correo/teléfono/dirección al "pedir la cuenta" — si la identificación ya es cliente de esta empresa, trae sus datos locales primero (más completos que el SRI). */
    public function sriAjax(): void
    {
        $token = trim($_GET['token'] ?? '');
        try {
            [$mesa,] = $this->resolver($token);
            $identificacion = preg_replace('/\D/', '', trim($_GET['identificacion'] ?? ''));
            if (strlen($identificacion) < 10) {
                $this->json(['ok' => false, 'error' => 'Identificación no válida.']);
                return;
            }
            $resultado = (new \App\Services\SriIdentificationService())->consultar($identificacion, (int) $mesa['id_empresa']);
            if (!empty($resultado['ok']) && !empty($resultado['data'])) {
                $d = $resultado['data'];
                $this->json([
                    'ok'        => true,
                    'nombre'    => $d['nombre'] ?? '',
                    'correo'    => $d['mail'] ?? '',
                    'telefono'  => $d['telefono'] ?? '',
                    'direccion' => $d['direccion'] ?? '',
                ]);
            } else {
                $this->json(['ok' => false, 'error' => $resultado['error'] ?? 'No encontrado.']);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }


    /** Carta del Menú disponible (con foto) — mismo origen que usa el selector del mesero, sin el catálogo de Stock General. */
    public function menuAjax(): void
    {
        $token = trim($_GET['token'] ?? '');
        try {
            [$mesa,] = $this->resolver($token);
            $items = $this->menuRepo->getDisponibles((int) $mesa['id_empresa'], '');
            $this->json(['ok' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Estado actual de la comanda (para que varios celulares en la misma mesa vean el mismo pedido en vivo). */
    public function estadoAjax(): void
    {
        $token = trim($_GET['token'] ?? '');
        try {
            [, $comanda] = $this->resolver($token);
            $this->json(['ok' => true, 'data' => $comanda]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function agregarAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
            $idMenuItem = (int) ($_POST['id_menu_item'] ?? 0);
            if ($idMenuItem <= 0) {
                throw new Exception('Selecciona un ítem del menú.');
            }
            $this->comandaService->agregarLinea((int) $comanda['id'], (int) $mesa['id_empresa'], (int) $comanda['id_usuario_mesero'], [
                'id_menu_item'     => $idMenuItem,
                'cantidad'         => (float) ($_POST['cantidad'] ?? 1),
                'observacion_item' => trim($_POST['observacion_item'] ?? ''),
            ]);
            $this->json(['ok' => true, 'msg' => 'Agregado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Solo se puede quitar mientras la línea no se haya enviado a preparación (self-service, sin supervisión de un mesero). */
    public function quitarAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
            $idLinea = (int) ($_POST['id_linea'] ?? 0);
            $linea = null;
            foreach (($comanda['detalles'] ?? []) as $d) {
                if ((int) $d['id'] === $idLinea) { $linea = $d; break; }
            }
            if (!$linea) {
                throw new Exception('Ítem no encontrado.');
            }
            if ($linea['estado_linea'] !== 'pendiente') {
                throw new Exception('Este ítem ya se envió a preparación; pídele a un mesero que lo quite.');
            }
            $this->comandaService->anularLinea($idLinea, (int) $comanda['id'], (int) $mesa['id_empresa'], (int) $comanda['id_usuario_mesero']);
            $this->json(['ok' => true, 'msg' => 'Quitado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** "Confirmar pedido": envía todo lo pendiente a su estación de cocina/barra — mismo motor que usa el mesero. */
    public function enviarAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
            $n = $this->comandaService->enviarACocina((int) $comanda['id'], (int) $mesa['id_empresa'], (int) $comanda['id_usuario_mesero']);
            $this->json(['ok' => true, 'msg' => $n > 0 ? "Se confirmaron {$n} ítem(s)." : 'No había ítems pendientes por confirmar.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * "Pedir la cuenta": el cliente elige los ítems YA ENTREGADOS que quiere
     * pagar y deja sus datos de facturación — arma el grupo de cobro con el
     * cliente y el tipo de documento ya resueltos. Todavía NO cobra (sin pago
     * en línea en esta fase): el mesero ve la solicitud en comandas/ver y
     * solo confirma la forma de pago física para cerrarla.
     */
    public function cuentaAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
            $idEmpresa = (int) $mesa['id_empresa'];
            $idUsuarioAtribuido = (int) $comanda['id_usuario_mesero'];

            $idsLineas = array_map('intval', json_decode($_POST['ids_lineas'] ?? '[]', true) ?: []);
            if (empty($idsLineas)) {
                throw new Exception('Selecciona al menos un ítem para pagar.');
            }

            // Qué documento puede pedir el cliente lo decide la mesa (modulos/mesas,
            // permite_factura/permite_recibo — Factura es la opción principal).
            $permitidos = $this->getDocumentosPermitidos($mesa);
            $tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? ($permitidos['factura'] ? 'FACTURA' : 'RECIBO')));
            if (!in_array($tipoDocumento, ['FACTURA', 'RECIBO'], true)) {
                throw new Exception('Tipo de documento no válido.');
            }
            if (($tipoDocumento === 'FACTURA' && !$permitidos['factura']) || ($tipoDocumento === 'RECIBO' && !$permitidos['recibo'])) {
                throw new Exception('Esta mesa no permite pedir ese tipo de documento desde el portal QR.');
            }

            if (!empty($_POST['consumidor_final'])) {
                $idCliente = $this->comandaService->resolverClienteConsumidorFinal($idEmpresa, $idUsuarioAtribuido);
            } else {
                $datosCliente = [
                    'nombre'              => trim($_POST['nombre'] ?? ''),
                    'tipo_identificacion' => trim($_POST['tipo_identificacion'] ?? 'cedula'),
                    'identificacion'      => trim($_POST['identificacion'] ?? ''),
                    'correo'              => trim($_POST['correo'] ?? ''),
                    'telefono'            => trim($_POST['telefono'] ?? ''),
                    'direccion'           => trim($_POST['direccion'] ?? ''),
                ];
                $idCliente = $this->comandaService->resolverClienteQr($datosCliente, $idEmpresa, $idUsuarioAtribuido);
            }
            $idGrupo = $this->comandaService->crearGrupoCobroQr((int) $comanda['id'], $idEmpresa, $idUsuarioAtribuido, $idsLineas, $idCliente, $tipoDocumento);

            $this->json(['ok' => true, 'msg' => 'Listo — ya se avisó para que te cobren esta cuenta.', 'id_grupo' => $idGrupo]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * "Pagar ahora con tarjeta": el cliente paga en línea (Payphone) el grupo
     * que acaba de pedir en cuentaAjax(), sin esperar a que un mesero se
     * acerque. Devuelve la URL pública de la Cajita de Pagos para redirigir
     * al cliente — la aprobación real llega después, de forma asíncrona, a
     * PayphoneController::procesarAprobacion() (branch 'comanda_grupo_cobro'),
     * que es quien efectivamente cobra el grupo y genera la Factura/Recibo.
     */
    public function pagarAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [$mesa, $comanda] = $this->resolver($token);
            $idEmpresa = (int) $mesa['id_empresa'];
            $idGrupo = (int) ($_POST['id_grupo'] ?? 0);

            $grupo = null;
            foreach (($comanda['grupos'] ?? []) as $g) {
                if ((int) $g['id'] === $idGrupo) { $grupo = $g; break; }
            }
            if (!$grupo) {
                throw new Exception('Esa cuenta no existe o ya no pertenece a esta mesa.');
            }
            if (($grupo['estado'] ?? '') !== 'pendiente') {
                throw new Exception('Esta cuenta ya fue cobrada.');
            }

            $ppRepo = new \App\repositories\PayphoneRepository();
            $cfg = $ppRepo->getConfig($idEmpresa);
            if (!$cfg || empty($cfg['activo'])) {
                throw new Exception('El pago en línea no está disponible en este restaurante todavía; espera a que te cobren en la mesa.');
            }
            $formaCobro = $ppRepo->getFormaCobroPayphone($idEmpresa);
            if (!$formaCobro) {
                throw new Exception('El pago en línea no está disponible en este restaurante todavía; espera a que te cobren en la mesa.');
            }

            // Reutiliza una transacción pendiente reciente en vez de crear otra
            // cada vez que el cliente toca "Pagar" (p. ej. si duda y reintenta).
            $stPend = $this->db->prepare(
                "SELECT client_transaction_id FROM payphone_transacciones
                 WHERE id_empresa = :e AND modulo = 'comanda_grupo_cobro' AND id_referencia = :g
                   AND estado = 'pendiente' AND eliminado = false
                   AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '15 minutes')
                 ORDER BY id DESC LIMIT 1"
            );
            $stPend->execute([':e' => $idEmpresa, ':g' => $idGrupo]);
            $ctidExistente = $stPend->fetchColumn();
            $urlBase = $this->urlPublicaBase();
            if ($ctidExistente) {
                $this->json(['ok' => true, 'redirect' => $urlBase . '/pago/' . $ctidExistente]);
                return;
            }

            $totalBase = 0.0;
            $totalIva  = 0.0;
            foreach (($comanda['detalles'] ?? []) as $d) {
                if ((int) ($d['id_grupo_cobro'] ?? 0) !== $idGrupo) continue;
                $base = (float) $d['subtotal'];
                $totalBase += $base;
                $totalIva  += $base * ((float) ($d['porcentaje_iva'] ?? 0) / 100);
            }
            if ($totalBase <= 0) {
                throw new Exception('Esta cuenta no tiene ítems por cobrar.');
            }

            $pp = new \App\Services\PayphoneService($ppRepo);
            $cajita = $pp->prepararCajita($idEmpresa, [
                'monto'           => \App\Services\PayphoneService::dolaresACentavos($totalBase + $totalIva),
                'impuesto'        => \App\Services\PayphoneService::dolaresACentavos($totalIva),
                'descripcion'     => 'Mesa ' . ($mesa['nombre'] ?? '') . ' — Cuenta ' . ($grupo['etiqueta'] ?? $idGrupo),
                'modulo'          => 'comanda_grupo_cobro',
                'id_referencia'   => $idGrupo,
                'id_forma_cobro'  => (int) $formaCobro['id'],
                'url_retorno'     => $urlBase . '/payphone/cajita-retorno',
                'url_cancelacion' => $urlBase . '/payphone/cancelacion',
                'url_exito'       => $urlBase . '/pedido/' . $token . '?pago=ok',
            ]);

            $this->json(['ok' => true, 'redirect' => $urlBase . '/pago/' . $cajita['client_transaction_id']]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Mismo criterio que MesasController::urlPublicaQr() — dominio público absoluto (APP_URL si está configurado, si no el host de la petición). */
    private function urlPublicaBase(): string
    {
        $dominio = defined('APP_URL') && APP_URL !== '' ? rtrim(APP_URL, '/') : '';
        if ($dominio === '') {
            $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dominio = $esquema . '://' . $host;
        }
        return $dominio . rtrim(BASE_URL ?? '', '/');
    }

    /** "Llamar al mesero" — aviso visible en el tablero y en la comanda hasta que alguien lo atienda. */
    public function asistenciaAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [, $comanda] = $this->resolver($token);
            $this->comandaService->solicitarAsistencia((int) $comanda['id'], (int) $comanda['id_empresa']);
            $this->json(['ok' => true, 'msg' => 'Listo, ya avisamos a un mesero.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** El cliente cancela su propio aviso (p. ej. presionó la campana por error). */
    public function cancelarAsistenciaAjax(): void
    {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        try {
            [, $comanda] = $this->resolver($token);
            $this->comandaService->cancelarAsistencia((int) $comanda['id'], (int) $comanda['id_empresa']);
            $this->json(['ok' => true, 'msg' => 'Aviso cancelado.']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Token → [mesa, comanda] (con detalle ya cargado). Abre la comanda en
     * self-service si la mesa no tiene una — solo si el restaurante tiene un
     * turno de caja abierto (si no, no tiene sentido dejar pedir).
     */
    private function resolver(string $token): array
    {
        if ($token === '') {
            throw new Exception('Este código QR no es válido.');
        }
        $mesa = $this->mesaRepo->getByQrToken($token);
        if (!$mesa) {
            throw new Exception('Este código QR no es válido o ya no está activo.');
        }
        $idEmpresa = (int) $mesa['id_empresa'];
        $idMesa = (int) $mesa['id'];

        $sesion = $this->cajaService->getSesionAbiertaEmpresa($idEmpresa);
        if (!$sesion) {
            throw new Exception('El restaurante no está recibiendo pedidos en este momento.');
        }

        $idComanda = $this->comandaService->resolverComandaQr($idMesa, $idEmpresa, $sesion);
        $comanda = $this->comandaService->getDetalle($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('No se pudo abrir el pedido de esta mesa.');
        }

        return [$mesa, $comanda];
    }

    private function renderError(string $mensaje): void
    {
        http_response_code(404);
        $viewPath = MVC_APP . '/views/publica/pedido/error.php';
        if (is_file($viewPath)) {
            extract(['mensaje' => $mensaje]);
            require $viewPath;
            return;
        }
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>No disponible</title></head><body style="font-family:sans-serif;text-align:center;padding:60px 20px;">'
           . '<h3>' . htmlspecialchars($mensaje) . '</h3></body></html>';
    }
}
