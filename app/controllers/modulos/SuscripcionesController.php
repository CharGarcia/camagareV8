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
        $stmt = $db->query("SELECT id, tarifa, porcentaje_iva FROM tarifa_iva WHERE status = 1 ORDER BY porcentaje_iva ASC");
        $tarifasIva = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
            echo '<td class="ps-3">' . htmlspecialchars($r['nombre_cliente'] ?? '') . '</td>';
            echo '<td><small class="text-muted">' . htmlspecialchars($r['identificacion_cliente'] ?? '') . '</small></td>';
            echo '<td>' . htmlspecialchars($r['nombre_periodicidad'] ?? '—') . '</td>';
            echo '<td class="text-center">' . $iconoCobro . ' ' . ucfirst($r['forma_cobro'] ?? '') . '</td>';
            echo '<td class="text-center fw-medium">' . $proxCobro . '</td>';
            echo '<td class="text-center">' . $fechaIni . '</td>';
            echo '<td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary border">' . $totalItems . ' ítem' . ($totalItems !== 1 ? 's' : '') . '</span></td>';
            echo '<td class="text-center">' . (int) ($r['total_pagos'] ?? 0) . '</td>';
            echo '<td class="text-center pe-3">';
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
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
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

            $kushki    = new KushkiService();
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
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC');

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
        $result = $repo->getListado($idEmpresa, $buscar, 1, 12, 'nombre', 'ASC', null, 'venta');

        echo json_encode(['ok' => true, 'rows' => $result['rows']]);
        exit;
    }
}
