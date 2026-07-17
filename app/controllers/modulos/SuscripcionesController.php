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

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginHtml,
            'info'       => "$from-$to/$total",
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
            echo json_encode(['ok' => true, 'id' => $id, 'mensaje' => 'Suscripción creada correctamente.']);
        } catch (\InvalidArgumentException $e) {
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
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
            $parts = explode('|', $e->getMessage());
            $mensaje = $parts[0];
            $focus = $parts[1] ?? '';
            echo json_encode(['ok' => false, 'mensaje' => $mensaje, 'focus' => $focus]);
        } catch (\Exception $e) {
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
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
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

                    // Avanzar el próximo cobro SOLO si el documento se creó.
                    $suscRepo->updateProximoCobro(
                        $idSusc,
                        $this->service->calcularProximoCobro($periodo, $meses, $codigo)
                    );

                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'id_recibo'      => $res['id_recibo'],
                        'fecha_cobro'    => date('Y-m-d'),
                        'monto'          => $res['importe'],
                        'estado'         => 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                } catch (\Throwable $e) {
                    $errorMsgs[] = "Suscripcion {$idSusc}: " . $e->getMessage();
                    $errores++;
                }
            }

            if ($generadas === 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron generar documentos. Detalles: ' . implode(' | ', $errorMsgs)], JSON_INVALID_UTF8_SUBSTITUTE);
                return;
            }

            echo json_encode([
                'ok'      => true,
                'mensaje' => "Se generaron $generadas documento(s) correctamente." . ($errores > 0 ? " (Hubo $errores con error)." : ''),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
}
