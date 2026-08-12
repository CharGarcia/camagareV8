<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\SuscripcionesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\SuscripcionesService;
use App\Services\modulos\KushkiService;

class SuscripcionesController extends BaseModuloController
{
    private SuscripcionesService $service;
    private const RUTA_MODULO = 'modulos/suscripciones';

    public function __construct()
    {
        parent::__construct();
        $repository    = new SuscripcionesRepository();
        $rules         = new SuscripcionesRules();
        $logService    = new LogSistemaService();
        $this->service = new SuscripcionesService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm       = $this->getPermisos();
        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'proximo_cobro');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);

        foreach ($result['rows'] as &$r) {
            if (!empty($r['created_at']))   $r['created_at']   = date('d-m-Y H:i:s', strtotime($r['created_at']));
            if (!empty($r['updated_at']))   $r['updated_at']   = date('d-m-Y H:i:s', strtotime($r['updated_at']));
            if (!empty($r['proximo_cobro'])) $r['proximo_cobro_fmt'] = date('d-m-Y', strtotime($r['proximo_cobro']));
            if (!empty($r['fecha_inicio']))  $r['fecha_inicio_fmt']  = date('d-m-Y', strtotime($r['fecha_inicio']));
        }
        unset($r);

        $totalPages     = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;
        $periodicidades = $this->service->getPeriodicidades();

        $db = \App\core\Database::getConnection();
        $stmt = $db->query("SELECT id, codigo, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC");
        $tarifasIva = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $empresaModel = new \App\models\Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        // Los decimales se configuran a nivel de establecimiento (no en la tabla empresas),
        // igual que en la factura de venta.
        $decimalesPrecio   = 2;
        $decimalesCantidad = 2;
        $calculoIva        = 'linea_linea';
        if (!empty($establecimientos)) {
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $decimalesPrecio   = (int) ($estConfig['decimales_precio']   ?? 2);
                    $decimalesCantidad = (int) ($estConfig['decimales_cantidad'] ?? 2);
                    $calculoIva        = ($estConfig['calculo_iva_facturacion'] ?? 'linea_linea') === 'subtotal'
                        ? 'subtotal' : 'linea_linea';
                }
            } catch (\Throwable $e) {
                // Migración pendiente — se usan valores por defecto.
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos.suscripciones.index', [
            'titulo'         => 'Suscripciones',
            'perm'           => $perm,
            'rutaModulo'     => self::RUTA_MODULO,
            'rows'           => $result['rows'],
            'total'          => $result['total'],
            'page'           => $page,
            'totalPages'     => $totalPages,
            'perPage'        => $perPage,
            'buscar'         => $buscar,
            'ordenCol'       => $ordenCol,
            'ordenDir'       => $ordenDir,
            'vistaConfig'    => $prefsVista,
            'periodicidades' => $periodicidades,
            'tarifasIva'     => $tarifasIva,
            'puntos'         => $puntos,
            'decimalesPrecio'   => $decimalesPrecio,
            'decimalesCantidad' => $decimalesCantidad,
            'calculoIva'        => $calculoIva,
            'fullWidth'      => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa       = (int) $_SESSION['id_empresa'];
        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
        $prefsVista      = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'proximo_cobro');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        $estadoClases = [
            'activo'     => 'success',
            'pausado'    => 'warning',
            'suspendido' => 'danger',
            'cancelado'  => 'secondary',
        ];

        ob_start();
        foreach ($result['rows'] as $r) {
            $cls        = $estadoClases[$r['estado'] ?? 'activo'] ?? 'secondary';
            $lbl        = ucfirst($r['estado'] ?? 'activo');
            $proxCobro  = !empty($r['proximo_cobro']) ? date('d-m-Y', strtotime($r['proximo_cobro'])) : '—';
            $fechaIni   = !empty($r['fecha_inicio'])  ? date('d-m-Y', strtotime($r['fecha_inicio']))  : '—';
            $iconoCobro = ($r['forma_cobro'] ?? '') === 'tarjeta'
                ? '<i class="bi bi-credit-card text-primary" title="Tarjeta"></i>'
                : '<i class="bi bi-file-text text-muted" title="Crédito"></i>';
            $totalItems = (int) ($r['total_items'] ?? 0);

            echo '<tr class="susc-row" role="button" data-susc=\'' . htmlspecialchars(json_encode($r), ENT_QUOTES) . '\' onclick="abrirModalSuscEditar(this)">';
            echo '<td class="ps-3 fw-medium" data-col="nombre_cliente">' . htmlspecialchars($r['nombre_cliente'] ?? '') . '</td>';
            echo '<td data-col="identificacion_cliente"><small class="text-muted">' . htmlspecialchars($r['identificacion_cliente'] ?? '') . '</small></td>';
            echo '<td data-col="nombre_periodicidad">' . htmlspecialchars($r['nombre_periodicidad'] ?? '—') . '</td>';
            echo '<td class="text-center" data-col="tipo_comprobante"><small class="text-muted">' . htmlspecialchars(ucwords(str_replace('_', ' ', $r['tipo_comprobante'] ?? 'Factura'))) . '</small></td>';
            echo '<td class="text-center" data-col="forma_cobro">' . $iconoCobro . ' ' . ucfirst($r['forma_cobro'] ?? '') . '</td>';
            echo '<td class="text-center fw-medium" data-col="proximo_cobro">' . $proxCobro . '</td>';
            echo '<td class="text-center" data-col="fecha_inicio">' . $fechaIni . '</td>';
            echo '<td class="text-center" data-col="total_items"><span class="badge bg-secondary bg-opacity-10 text-secondary border">' . $totalItems . ' ítem' . ($totalItems !== 1 ? 's' : '') . '</span></td>';
            $fin        = !empty($r['fecha_fin'])     ? date('d-m-Y', strtotime($r['fecha_fin']))     : '—';
            echo '<td class="text-center" data-col="fecha_fin">' . $fin . '</td>';
            echo '<td class="text-center pe-3" data-col="estado">';
            echo "<span class=\"badge bg-{$cls} bg-opacity-10 text-{$cls} border border-{$cls} border-opacity-25\">{$lbl}</span>";
            echo '</td>';
            echo '</tr>';
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        echo '<button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(' . ($page - 1) . ')" ' . ($page <= 1 ? 'disabled' : '') . '><i class="bi bi-chevron-left"></i></button>';
        echo '<button type="button" class="btn btn-outline-secondary" onclick="cambiarPaginaAjax(' . ($page + 1) . ')" ' . ($page >= $totalPages ? 'disabled' : '') . '><i class="bi bi-chevron-right"></i></button>';
        $paginHtml = ob_get_clean();

        // URLs de exportación con el buscador/orden actuales, para que el JS
        // mantenga los botones PDF/Excel sincronizados con el filtro aplicado.
        $qs      = 'b=' . urlencode($buscar) . '&sort=' . urlencode($ordenCol) . '&dir=' . urlencode($ordenDir);
        $urlBase = BASE_URL . '/' . self::RUTA_MODULO;

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginHtml,
            'info'       => "$from-$to/$total",
            'pdf_url'    => $urlBase . '/export-pdf?' . $qs,
            'excel_url'  => $urlBase . '/export-excel?' . $qs,
        ]);
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data               = $_POST;
            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $id = $this->service->crear($data);

            // Si la suscripción se crea con cobro por tarjeta vía Nuvei y aún no
            // tiene una tarjeta vinculada, enviar automáticamente el enlace de
            // registro al cliente para que active el cobro recurrente.
            $mensajeExtra = '';
            if (($data['forma_cobro'] ?? '') === 'tarjeta' && ($data['pasarela_tarjeta'] ?? '') === 'nuvei' && empty($data['id_nuvei_tarjeta'])) {
                try {
                    $envio = $this->enviarRegistroTarjetaNuvei($id, (int) $data['id_empresa'], (int) $data['id_usuario']);
                    if ($envio['ok']) {
                        $mensajeExtra = ' Se envió un enlace al cliente para registrar su tarjeta.';
                    }
                } catch (\Throwable $e) {
                    error_log('[Suscripciones] Error enviando registro de tarjeta automático: ' . $e->getMessage());
                }
            }

            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Suscripción creada correctamente.' . $mensajeExtra]);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al crear la suscripción: ' . $e->getMessage()]);
        }
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id                 = (int) ($_POST['id'] ?? 0);
            $idEmpresa          = (int) $_SESSION['id_empresa'];
            $data               = $_POST;
            $data['id_empresa'] = $idEmpresa;
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];

            $this->service->actualizar($id, $idEmpresa, $data);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripción actualizada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function cambiarEstado(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $estado    = trim($_POST['estado'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->cambiarEstado($id, $idEmpresa, $estado, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Estado actualizado correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function delete(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Suscripción eliminada correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $detalle   = $this->service->getDetalle($idSusc, $idEmpresa);
            echo json_encode(['ok' => true, 'detalle' => $detalle]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function getPagosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $pagos     = $this->service->getPagosPorSuscripcion($idSusc, $idEmpresa);
            echo json_encode(['ok' => true, 'pagos' => $pagos]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function tokenizarTarjetaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id           = (int) ($_POST['id'] ?? 0);
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) $_SESSION['id_usuario'];
            $onetimeToken = trim($_POST['kushki_token'] ?? '');

            if (!$onetimeToken) {
                throw new \InvalidArgumentException('Token de Kushki no recibido.');
            }

            $kushki    = new KushkiService($idEmpresa);
            $tokenData = $kushki->crearTokenSuscripcion($onetimeToken);

            $this->service->guardarTokenKushki($id, $idEmpresa, $tokenData, $idUsuario);

            echo json_encode([
                'ok'      => true,
                'last4'   => $tokenData['last4'],
                'brand'   => $tokenData['brand'],
                'mensaje' => 'Tarjeta guardada correctamente.',
            ]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Reenvía manualmente el enlace de registro de tarjeta Nuvei para una
     * suscripción existente (botón "Enviar enlace" en la pestaña Cobro).
     */
    public function enviarRegistroTarjetaNuveiAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            if ($id <= 0) {
                throw new \InvalidArgumentException('Suscripción inválida.');
            }

            $correoDestino = trim($_POST['correo_destino'] ?? '');

            $resultado = $this->enviarRegistroTarjetaNuvei($id, $idEmpresa, $idUsuario, $correoDestino ?: null);
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Lista las tarjetas Nuvei ya guardadas del cliente de una suscripción,
     * para permitir reutilizar una tarjeta existente sin pedir un registro nuevo.
     */
    public function getTarjetasClienteNuveiAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idSusc    = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];

            $repo = new SuscripcionesRepository();
            $susc = $repo->findById($idSusc, $idEmpresa);
            if (!$susc) {
                throw new \Exception('Suscripción no encontrada.');
            }

            $nuvei    = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
            $tarjetas = $nuvei->getTarjetasCliente($idEmpresa, (int) $susc['id_cliente']);

            echo json_encode(['ok' => true, 'tarjetas' => $tarjetas]);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Vincula directamente una tarjeta Nuvei ya guardada del cliente a la
     * suscripción, sin necesidad de un nuevo enlace de registro.
     */
    public function vincularTarjetaNuveiAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idSusc         = (int) ($_POST['id'] ?? 0);
            $idNuveiTarjeta = (int) ($_POST['id_nuvei_tarjeta'] ?? 0);
            $idEmpresa      = (int) $_SESSION['id_empresa'];

            $repo = new SuscripcionesRepository();
            $susc = $repo->findById($idSusc, $idEmpresa);
            if (!$susc) {
                throw new \Exception('Suscripción no encontrada.');
            }

            $nuvei   = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
            $tarjeta = $nuvei->getTarjetasCliente($idEmpresa, (int) $susc['id_cliente']);
            $existe  = array_filter($tarjeta, fn($t) => (int) $t['id'] === $idNuveiTarjeta);
            if (empty($existe)) {
                throw new \Exception('La tarjeta seleccionada no pertenece a este cliente.');
            }

            $repo->updateNuveiTarjeta($idSusc, $idNuveiTarjeta);
            echo json_encode(['ok' => true, 'mensaje' => 'Tarjeta vinculada correctamente.']);
        } catch (\Exception $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /**
     * Genera la solicitud de registro de tarjeta Nuvei y envía el enlace por
     * correo al cliente de la suscripción. Reutilizado por store() (envío
     * automático al crear) y por enviarRegistroTarjetaNuveiAjax() (reenvío manual).
     */
    private function enviarRegistroTarjetaNuvei(int $idSuscripcion, int $idEmpresa, int $idUsuario, ?string $correoOverride = null): array
    {
        $db = \App\core\Database::getConnection();
        $st = $db->prepare(
            "SELECT s.id, s.id_cliente, c.nombre AS cliente_nombre, c.email AS cliente_email
             FROM suscripciones s
             LEFT JOIN clientes c ON c.id = s.id_cliente
             WHERE s.id = ? AND s.id_empresa = ? AND s.eliminado = false"
        );
        $st->execute([$idSuscripcion, $idEmpresa]);
        $susc = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$susc) {
            return ['ok' => false, 'mensaje' => 'Suscripción no encontrada.'];
        }

        // Permite corregir el correo desde el modal antes de enviar (mismo patrón
        // que "Enviar cobro con Nuvei/Payphone" en Facturas de Venta), sin tocar
        // el correo registrado del cliente si solo se usa para este envío puntual.
        $correoCliente = filter_var($correoOverride, FILTER_VALIDATE_EMAIL)
            ? $correoOverride
            : trim((string) ($susc['cliente_email'] ?? ''));
        if (!filter_var($correoCliente, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'No hay un correo válido para enviar el enlace. Ingresa uno.'];
        }

        $nuvei = new \App\Services\NuveiService(new \App\repositories\NuveiRepository());
        $sol   = $nuvei->prepararSolicitudTarjeta($idEmpresa, [
            'id_cliente'    => (int) $susc['id_cliente'],
            'modulo'        => 'suscripcion',
            'id_referencia' => $idSuscripcion,
            'email'         => $correoCliente,
            'id_usuario'    => $idUsuario,
        ]);

        if (!$sol['ok']) {
            return $sol;
        }

        $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $urlBaseAbs = $scheme . '://' . $host . rtrim(BASE_URL, '/');
        $urlRegistro = $urlBaseAbs . '/nuvei/tarjeta/' . $sol['token'];

        $empresaModel  = new \App\models\Empresa();
        $empresaData   = $empresaModel->getPorId($idEmpresa) ?? [];
        $empresaNombre = $empresaData['nombre_comercial'] ?? $empresaData['razon_social'] ?? '';

        $emailSvc = new \App\Services\EnvioDocumentosSRIService();
        $enviado  = $emailSvc->enviarRegistroTarjeta(
            $idEmpresa,
            $correoCliente,
            $susc['cliente_nombre'] ?? 'Cliente',
            $empresaNombre,
            $urlRegistro
        );

        if (!$enviado) {
            return ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifica la configuración de correo de la empresa.'];
        }

        return ['ok' => true, 'mensaje' => 'Enlace de registro enviado a ' . $correoCliente, 'correo' => $correoCliente];
    }

    public function getPeriodicidadesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $periodicidades = $this->service->getPeriodicidades();
        echo json_encode(['ok' => true, 'periodicidades' => $periodicidades]);
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        // soloActivos = true: excluir clientes inactivos en la selección.
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC', null, true);

        echo json_encode(['ok' => true, 'rows' => $result['rows']]);
        exit;
    }

    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProductoRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC', null, 'venta', true);

        echo json_encode(['ok' => true, 'rows' => $result['rows']]);
        exit;
    }

    public function generarFacturasManualAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        $idEmpresa          = (int) $_SESSION['id_empresa'];
        $idPuntoEmision     = (int) ($_POST['id_punto_emision'] ?? 0);
        $idPeriodicidad     = (int) ($_POST['id_periodicidad'] ?? 0);
        $textoItem          = trim($_POST['texto_item'] ?? '');
        $infoConcepto       = trim($_POST['info_concepto'] ?? '');
        $infoDetalle        = trim($_POST['info_detalle'] ?? '');

        if ($idPuntoEmision <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Debe seleccionar una serie válida.']);
            return;
        }

        if ($idPeriodicidad <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Debe seleccionar una periodicidad.']);
            return;
        }

        try {
            $db = \App\core\Database::getConnection();
            
            // 1. Obtener datos del Establecimiento y Punto de Emisión seleccionado
            $stab = $db->prepare(
                "SELECT ep.*, pe.id AS id_punto_emision, pe.codigo_punto AS punto_emision_codigo
                 FROM empresa_establecimiento ep
                 JOIN empresa_punto_emision pe ON pe.id_establecimiento = ep.id
                 WHERE pe.id = :id_punto AND ep.id_empresa = :id_empresa AND ep.estado = 'activo' AND pe.eliminado = false AND ep.eliminado = false
                 LIMIT 1"
            );
            $stab->execute([':id_punto' => $idPuntoEmision, ':id_empresa' => $idEmpresa]);
            $estabConfig = $stab->fetch(\PDO::FETCH_ASSOC);

            if (!$estabConfig) {
                echo json_encode(['ok' => false, 'mensaje' => 'La serie seleccionada no es válida o está inactiva.']);
                return;
            }

            // 2. Obtener configuración de la empresa
            $empresaModel = new \App\models\Empresa();
            $empresaConfig = $empresaModel->getPorId($idEmpresa);
            if (empty($empresaConfig)) {
                echo json_encode(['ok' => false, 'mensaje' => 'No hay configuración de empresa.']);
                return;
            }

            // 3. Obtener suscripciones vencidas para la periodicidad seleccionada
            $suscRepo = new \App\repositories\modulos\SuscripcionesRepository();
            $suscripciones = $suscRepo->getParaGeneracionManual($idEmpresa, $idPeriodicidad);
            if (empty($suscripciones)) {
                echo json_encode(['ok' => false, 'mensaje' => 'No hay suscripciones activas y vencidas para la periodicidad seleccionada.']);
                return;
            }

            // Generación unificada: el service decide Factura o Recibo de Venta
            // según el tipo_comprobante de cada suscripción. Es el mismo punto de
            // generación que usa la automatización/cron (SuscripcionesHandler).
            $facturacion = new \App\Services\modulos\SuscripcionFacturacionService(
                new \App\Services\modulos\FacturaVentaService(
                    new \App\repositories\modulos\FacturaVentaRepository(),
                    new \App\Rules\modulos\FacturaVentaRules(),
                    new \App\Services\LogSistemaService()
                ),
                new \App\Services\SecuencialService(),
                new \App\Services\modulos\ReciboVentaService(
                    new \App\repositories\modulos\ReciboVentaRepository(),
                    new \App\Rules\modulos\ReciboVentaRules(),
                    new \App\Services\LogSistemaService()
                )
            );

            $idUsuario = (int) $_SESSION['id_usuario'];
            $extras = [
                'texto_item'    => $textoItem,
                'info_concepto' => $infoConcepto,
                'info_detalle'  => $infoDetalle,
            ];

            $generadas = 0;
            $errores   = 0;
            $errorMsgs = [];

            foreach ($suscripciones as $susc) {
                $idSusc = (int) $susc['id'];
                $meses  = (int) ($susc['periodicidad_meses'] ?? 1);
                $codigo = (string) ($susc['periodicidad_codigo'] ?? '');

                try {
                    $detalle = $suscRepo->getDetalle($idSusc);
                    if (empty($detalle)) continue;

                    // El período generado alimenta los placeholders ({mes}, {anio}, ...)
                    $periodo = (string) $susc['proximo_cobro'];

                    $res = $facturacion->generarUnPeriodo(
                        $idEmpresa, $idUsuario, $susc, $detalle, $estabConfig, $empresaConfig, $extras, $periodo
                    );

                    // Avanzar el próximo cobro SOLO si el documento se creó. La generación
                    // está DESACOPLADA del cobro: si la suscripción usa tarjeta vía Nuvei, el
                    // cargo real lo hace la automatización "Cobrar suscripciones (Nuvei)" —
                    // aquí solo se deja el pago en 'pendiente'. Crédito y Kushki no se tocan.
                    $nuevoProximo = $this->service->calcularProximoCobro($periodo, $meses, $codigo);
                    // TEMPORAL: diagnóstico del bug reportado "no aplicó la próxima fecha"
                    // (periodicidad Semestral). Quitar una vez confirmada la causa.
                    \App\Services\ErrorLogService::registrarManual(
                        "DIAG proximo_cobro susc#{$idSusc}: periodo_in={$periodo} meses={$meses} codigo={$codigo} nuevo={$nuevoProximo}",
                        ['ruta' => static::class, 'accion' => __FUNCTION__ . '#diag_' . $idSusc, 'tipo' => 'manual']
                    );
                    $suscRepo->updateProximoCobro($idSusc, $nuevoProximo);

                    $esNuveiTarjeta = ($susc['forma_cobro'] ?? '') === 'tarjeta'
                        && ($susc['pasarela_tarjeta'] ?? '') === 'nuvei'
                        && !empty($susc['id_nuvei_tarjeta']);

                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'id_recibo'      => $res['id_recibo'],
                        'fecha_cobro'    => date('Y-m-d'),
                        'monto'          => $res['importe'],
                        'estado'         => $esNuveiTarjeta ? 'pendiente' : 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                } catch (\Throwable $e) {
                    $errorMsgs[] = "Suscripcion {$idSusc}: " . $e->getMessage();
                    $errores++;
                    \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__ . '#suscripcion_' . $idSusc]);
                }
            }

            if ($generadas === 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron generar documentos. Detalles: ' . implode(' | ', $errorMsgs)], JSON_INVALID_UTF8_SUBSTITUTE);
                return;
            }

            $mensaje = "Se generaron $generadas documento(s) correctamente.";
            if ($errores > 0) {
                $mensaje .= " Hubo $errores con error: " . implode(' | ', $errorMsgs);
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => $mensaje,
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    // ── Exportar listado ───────────────────────────────────────────────────────

    /** Filas del listado según el buscador/orden actual (sin paginar). */
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'proximo_cobro');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'asc'));

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'] ?? [];
    }

    private const CABECERAS_EXPORT = [
        'Cliente', 'Identificación', 'Periodicidad', 'Comprobante', 'Forma de cobro',
        'Próximo cobro', 'Fecha inicio', 'Fecha fin', 'Ítems', 'Estado',
    ];

    /** Una fila del listado a array de celdas, en el orden de CABECERAS_EXPORT. */
    private function filaExport(array $r): array
    {
        $fecha = static fn($v) => !empty($v) ? date('d-m-Y', strtotime((string) $v)) : '-';
        return [
            (string) ($r['nombre_cliente'] ?? ''),
            (string) ($r['identificacion_cliente'] ?? ''),
            (string) ($r['nombre_periodicidad'] ?? '-'),
            ucfirst((string) ($r['tipo_comprobante'] ?? 'factura')),
            ucfirst((string) ($r['forma_cobro'] ?? '')),
            $fecha($r['proximo_cobro'] ?? null),
            $fecha($r['fecha_inicio'] ?? null),
            $fecha($r['fecha_fin'] ?? null),
            (string) ((int) ($r['total_items'] ?? 0)),
            ucfirst((string) ($r['estado'] ?? 'activo')),
        ];
    }

    public function exportPdf(): void
    {
        $this->requireLeer();

        try {
            $rows = $this->filasParaExportar();

            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE DE SUSCRIPCIONES';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $anchos = ['22%', '13%', '10%', '9%', '9%', '9%', '9%', '9%', '4%', '6%'];

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 7.5pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 12px; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="8mm" backright="8mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Listado de Suscripciones</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (self::CABECERAS_EXPORT as $i => $h): ?>
                                <th style="width: <?= $anchos[$i] ?? 'auto' ?>"><?= htmlspecialchars($h) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($this->filaExport($r) as $celda): ?>
                                    <td><?= htmlspecialchars($celda) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Suscripciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $_SESSION['suscripciones_msg'] = ['danger', 'Error al generar PDF: ' . $e->getMessage()];
            $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        try {
            $rows = $this->filasParaExportar();

            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $exportData = array_map(fn($r) => $this->filaExport($r), $rows);

            $reportService = new \App\Services\ReportService();
            $reportService->exportToExcel(
                'Suscripciones',
                self::CABECERAS_EXPORT,
                $exportData,
                'Listado de Suscripciones',
                $nombreEmpresa
            );
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (!headers_sent()) {
                $_SESSION['suscripciones_msg'] = ['danger', 'Error al generar Excel: ' . $e->getMessage()];
                $this->redirect(BASE_URL . '/' . self::RUTA_MODULO);
            }
            exit;
        }
    }

}
