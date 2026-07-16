<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TraspasoRepository;
use App\repositories\modulos\FormaPagoRepository;
use App\Services\modulos\TraspasoService;
use App\Rules\modulos\TraspasoRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class TraspasosController extends BaseModuloController
{
    private TraspasoService $service;
    private TraspasoRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/traspasos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new TraspasoRepository();
        $this->service    = new TraspasoService(
            $this->repository,
            new TraspasoRules(),
            new FormaPagoRepository(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        // Formas de pago disponibles para origen (pueden perder dinero) y destino (pueden recibir dinero),
        // excluyendo anticipos: un traspaso mueve dinero real entre cuentas/caja, no saldo de terceros.
        $fpRepo = new FormaPagoRepository();
        $formasEgreso  = $fpRepo->getFormasFiltradas($idEmpresa, 'EGRESO');
        $formasIngreso = $fpRepo->getFormasFiltradas($idEmpresa, 'INGRESO');
        $formasPago = [];
        foreach (array_merge($formasEgreso, $formasIngreso) as $fp) {
            if (($fp['tipo'] ?? '') === 'ANTICIPO') continue;
            $formasPago[(int) $fp['id']] = $fp;
        }

        $saldosFormas = $fpRepo->getSaldosActuales($idEmpresa);
        foreach ($formasPago as &$fp) {
            $fp['saldo'] = (float) ($saldosFormas[(int) $fp['id']] ?? 0);
        }
        unset($fp);
        $formasPago = array_values($formasPago);

        $this->viewWithLayout('layouts.main', 'modulos/traspasos/index', [
            'titulo'            => 'Traspasos de Fondos',
            'perm'              => $perm,
            'rows'              => $result['rows'],
            'total'             => $result['total'],
            'page'              => $page,
            'totalPages'        => $totalPages,
            'perPage'           => $perPage,
            'from'              => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'                => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'            => $buscar,
            'ordenCol'          => $ordenCol,
            'ordenDir'          => $ordenDir,
            'vistaConfig'       => $prefsVista,
            'rutaModulo'        => $this->getRutaModulo(),
            'empresa'           => $empresaData,
            'establecimientos'  => $establecimientos,
            'puntos'            => $puntos,
            'formasPago'        => $formasPago,
            'fullWidth'         => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron traspasos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $fecha = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';

                $estado = $r['estado'] ?? 'registrado';
                $estCls = match ($estado) {
                    'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                    default   => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $badge = '<span class="badge ' . $estCls . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="traspaso-row" role="button" onclick="abrirModalTraspasoVer(' . $r['id'] . ')">
                        <td class="ps-3" data-col="numero_traspaso"><code>' . htmlspecialchars($r['numero_traspaso'] ?? '') . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td data-col="origen_nombre"><span class="badge bg-light text-dark border">' . htmlspecialchars($r['origen_nombre'] ?? '') . '</span></td>
                        <td data-col="destino_nombre"><span class="badge bg-light text-dark border">' . htmlspecialchars($r['destino_nombre'] ?? '') . '</span> <i class="bi bi-arrow-right text-muted small"></i></td>
                        <td class="text-end fw-bold" data-col="monto">$' . number_format((float) $r['monto'], 2) . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $badge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDis = ($page <= 1) ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDis . ' onclick="window.TRP_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDis . ' onclick="window.TRP_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function getTraspasoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $traspaso = $this->service->getPorId($id, $idEmpresa);
        if (!$traspaso) {
            echo json_encode(['ok' => false, 'mensaje' => 'Traspaso no encontrado.']);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $traspaso]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipo    = 'Traspasos';

        $secService = new \App\Services\SecuencialService();
        $res = $secService->obtenerSiguienteSecuencial($idPunto, $tipo);

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    /** Saldo actual de una forma de pago (para mostrar disponible al elegir el origen). */
    public function getSaldoFormaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idForma   = (int) ($_GET['id_forma'] ?? 0);

        $fpRepo = new FormaPagoRepository();
        $saldos = $fpRepo->getSaldosActuales($idEmpresa);

        echo json_encode(['ok' => true, 'saldo' => $saldos[$idForma] ?? null]);
        exit;
    }

    public function guardarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['usuario_id'] = (int) $_SESSION['id_usuario'];

            // Componer número de traspaso
            $est = str_pad((string) ($data['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $pto = str_pad((string) ($data['punto_emision'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $sec = str_pad((string) ($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $data['numero_traspaso'] = "{$est}-{$pto}-{$sec}";

            $id = $this->service->registrar($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Traspaso registrado satisfactoriamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getAsientoContableAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $asiento   = $this->service->getAsientoContable($id, $idEmpresa);
            echo json_encode(['ok' => true, 'asiento' => $asiento]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Traspaso anulado con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function verificarPeriodoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $fecha     = trim($_GET['fecha'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if (!$fecha) {
                echo json_encode(['ok' => false, 'mensaje' => 'Fecha no proporcionada.']);
                exit;
            }
            $this->service->verificarPeriodo($fecha, $idEmpresa);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Genera el PDF (Comprobante de Traspaso). Si la empresa tiene una plantilla
     * activa para 'traspaso' se usa el diseñador; si no, el modelo general con logo.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $traspaso = $this->service->getPorId($id, $idEmpresa);
            if (!$traspaso) { http_response_code(404); echo 'Traspaso no encontrado'; exit; }

            $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
            $asiento = $this->service->getAsientoContable($id, $idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'traspaso');
            if ($plantilla) {
                $renderer->generar($plantilla, $traspaso, [], [], [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\ComprobanteCajaPdfService())
                    ->generarTraspaso($traspaso, $empresa, 'D', $asiento);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }
}
