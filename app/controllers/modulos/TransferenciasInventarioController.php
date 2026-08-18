<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\models\Empresa;
use App\repositories\modulos\BodegaRepository;
use App\repositories\modulos\InventarioRepository;
use App\repositories\modulos\TransferenciaInventarioRepository;
use App\Rules\modulos\TransferenciaInventarioRules;
use App\Services\LogSistemaService;
use App\Services\modulos\TransferenciaInventarioService;

/**
 * Transferencias de inventario entre bodegas (y entre establecimientos del
 * mismo RUC). Solo recibe la petición, valida lo básico y delega en el Service.
 */
class TransferenciasInventarioController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/transferencias-inventario';

    private TransferenciaInventarioService $service;
    private TransferenciaInventarioRepository $repository;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new TransferenciaInventarioRepository();
        $this->service    = new TransferenciaInventarioService(
            $this->repository,
            new InventarioRepository(),
            new TransferenciaInventarioRules(),
            new LogSistemaService()
        );
    }

    // ────────────────────────────────────────────────────────────────
    // LISTADO
    // ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm      = $this->getPermisos();
        $prefs     = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefs['__ordenCol__'] ?? 'fecha_transferencia');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefs['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $filtros         = $this->getFiltrosDesdeRequest();
        $idUsuarioFiltro = $this->getIdUsuarioFiltro($perm);

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $filtros);
        $resumen    = $this->service->getResumen($idEmpresa, $idUsuarioFiltro, $filtros);
        $total      = (int) $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $bodegas = $this->getBodegasDelUsuario($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/transferencias_inventario/index', [
            'titulo'      => 'Transferencias de Inventario',
            'perm'        => $perm,
            'rutaModulo'  => self::RUTA_MODULO,
            'rows'        => $result['rows'],
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'perPage'     => $perPage,
            'from'        => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'          => $total > 0 ? min($page * $perPage, $total) : 0,
            'buscar'      => $buscar,
            'ordenCol'    => $ordenCol,
            'ordenDir'    => $ordenDir,
            'filtros'     => $filtros,
            'resumen'     => $resumen,
            'bodegas'     => $bodegas,
            'empresa'     => (new Empresa())->getPorId($idEmpresa) ?? [],
            'vistaConfig' => $prefs,
            'base'        => BASE_URL,
            'fullWidth'   => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm      = $this->getPermisos();
        $prefs     = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefs['__ordenCol__'] ?? 'fecha_transferencia');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefs['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $filtros         = $this->getFiltrosDesdeRequest();
        $idUsuarioFiltro = $this->getIdUsuarioFiltro($perm);

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $filtros);
        $resumen    = $this->service->getResumen($idEmpresa, $idUsuarioFiltro, $filtros);
        $rows       = $result['rows'];
        $total      = (int) $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron transferencias.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo $this->renderFila($r);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDis = $page <= 1 ? 'disabled' : '';
        $nextDis = $page >= $totalPages ? 'disabled' : '';
        $paginationHtml =
            '<button type="button" class="btn btn-outline-secondary" ' . $prevDis . ' onclick="window.TRI_cambiarPagina(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>'
          . '<button type="button" class="btn btn-outline-secondary" ' . $nextDis . ' onclick="window.TRI_cambiarPagina(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "{$from}-{$to}/{$total}",
            'resumen'    => [
                'documentos'           => (int) ($resumen['documentos'] ?? 0),
                'unidades'             => (float) ($resumen['unidades'] ?? 0),
                'costo'                => (float) ($resumen['costo'] ?? 0),
                'interestablecimiento' => (int) ($resumen['interestablecimiento'] ?? 0),
            ],
        ]);
        exit;
    }

    /** Fila del listado (se usa en la carga inicial y en el refresco AJAX). */
    private function renderFila(array $r): string
    {
        $anulada = ($r['estado'] ?? '') === 'anulada';
        $badge = $anulada
            ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>'
            : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Registrada</span>';

        $entreEst = !empty($r['entre_establecimientos']) && $r['entre_establecimientos'] !== 'f'
            ? ' <i class="bi bi-signpost-split text-warning" title="Entre establecimientos"></i>'
            : '';

        $fecha = !empty($r['fecha_transferencia']) ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_transferencia'])) : '—';

        return '<tr class="tri-row" role="button" onclick="window.TRI_verTransferencia(' . (int) $r['id'] . ')">'
            . '<td class="ps-3" data-col="numero"><code>' . htmlspecialchars((string) ($r['numero'] ?? '')) . '</code>' . $entreEst . '</td>'
            . '<td data-col="fecha_transferencia">' . $fecha . '</td>'
            . '<td data-col="origen_nombre"><span class="badge bg-light text-dark border">' . htmlspecialchars((string) ($r['origen_nombre'] ?? '')) . '</span></td>'
            . '<td data-col="destino_nombre"><i class="bi bi-arrow-right text-muted small me-1"></i><span class="badge bg-light text-dark border">' . htmlspecialchars((string) ($r['destino_nombre'] ?? '')) . '</span></td>'
            . '<td class="text-end" data-col="lineas">' . (int) ($r['lineas'] ?? 0) . '</td>'
            . '<td class="text-end" data-col="total_items">' . number_format((float) ($r['total_items'] ?? 0), 2) . '</td>'
            . '<td class="text-end" data-col="total_costo">$' . number_format((float) ($r['total_costo'] ?? 0), 2) . '</td>'
            . '<td class="text-center pe-3" data-col="estado">' . $badge . '</td>'
            . '</tr>';
    }

    // ────────────────────────────────────────────────────────────────
    // FICHA / ESCRITURA
    // ────────────────────────────────────────────────────────────────

    public function getTransferenciaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id  = (int) ($_GET['id'] ?? 0);
        $doc = $this->service->getPorId($id, (int) $_SESSION['id_empresa']);

        if (!$doc) {
            echo json_encode(['ok' => false, 'mensaje' => 'La transferencia no existe.']);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $doc]);
        exit;
    }

    public function guardarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $detalles = $_POST['detalles'] ?? [];
            if (is_string($detalles)) {
                $detalles = json_decode($detalles, true) ?: [];
            }

            $id = $this->service->registrar([
                'id_empresa'          => (int) $_SESSION['id_empresa'],
                'id_usuario'          => (int) $_SESSION['id_usuario'],
                'nivel_usuario'       => (int) ($_SESSION['nivel'] ?? 1),
                'fecha_transferencia' => trim($_POST['fecha_transferencia'] ?? date('Y-m-d')),
                'id_bodega_origen'    => (int) ($_POST['id_bodega_origen'] ?? 0),
                'id_bodega_destino'   => (int) ($_POST['id_bodega_destino'] ?? 0),
                'responsable_envia'   => trim($_POST['responsable_envia'] ?? ''),
                'responsable_recibe'  => trim($_POST['responsable_recibe'] ?? ''),
                'observaciones'       => trim($_POST['observaciones'] ?? ''),
                'detalles'            => $detalles,
            ]);

            echo json_encode([
                'ok'      => true,
                'id'      => $id,
                'mensaje' => 'Transferencia registrada. El stock ya se movió entre las bodegas.',
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $this->service->anular(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                (int) ($_SESSION['nivel'] ?? 1)
            );
            echo json_encode(['ok' => true, 'mensaje' => 'Transferencia anulada. El stock volvió a su bodega de origen.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $this->service->eliminar(
                (int) ($_POST['id'] ?? 0),
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario']
            );
            echo json_encode(['ok' => true, 'mensaje' => 'Transferencia eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // APOYO AL MODAL (productos, lotes, series, stock)
    // ────────────────────────────────────────────────────────────────

    public function buscarProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idBodega = (int) ($_GET['id_bodega'] ?? 0);
        $texto    = trim($_GET['q'] ?? '');

        if ($idBodega <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Seleccione primero la bodega de origen.', 'data' => []]);
            exit;
        }

        $data = $this->repository->buscarProductosConStock((int) $_SESSION['id_empresa'], $idBodega, $texto);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    public function getLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros.', 'lotes' => []]);
            exit;
        }

        $repo  = new InventarioRepository();
        $idEmp = (int) $_SESSION['id_empresa'];

        // El costo con el que saldrá la mercadería lo decide el servidor; se
        // adelanta aquí solo para mostrarlo en el modal antes de guardar.
        $lotes = $repo->getLotesDisponibles($idProducto, $idBodega, $idEmp);
        foreach ($lotes as &$lote) {
            $lote['costo'] = $this->repository->getCostoOrigen($idProducto, $idBodega, $idEmp, (string) $lote['numero_lote']);
        }
        unset($lote);

        echo json_encode([
            'ok'    => true,
            'stock' => $repo->getStockActual($idProducto, $idBodega, $idEmp),
            'costo' => $this->repository->getCostoOrigen($idProducto, $idBodega, $idEmp),
            'lotes' => $lotes,
        ]);
        exit;
    }

    public function getSeriesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
        $lote       = trim($_GET['lote'] ?? '');

        if (!$idProducto || !$idBodega) {
            echo json_encode(['ok' => false, 'mensaje' => 'Faltan parámetros.', 'series' => []]);
            exit;
        }

        echo json_encode([
            'ok'     => true,
            'series' => $this->repository->getSeriesDisponibles($idProducto, $idBodega, (int) $_SESSION['id_empresa'], $lote !== '' ? $lote : null),
        ]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // GUÍA DE REMISIÓN (opcional, solo entre establecimientos)
    // ────────────────────────────────────────────────────────────────

    /**
     * Deja en sesión los datos de la transferencia para que el módulo de Guías
     * de Remisión abra su modal ya precargado (productos, direcciones, motivo).
     * El id viaja en sesión, no en la URL.
     */
    public function prepararGuiaAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id  = (int) ($_POST['id'] ?? 0);
            $doc = $this->service->getPorId($id, (int) $_SESSION['id_empresa']);

            if (!$doc) {
                echo json_encode(['ok' => false, 'mensaje' => 'La transferencia no existe.']);
                exit;
            }
            if (($doc['estado'] ?? '') === 'anulada') {
                echo json_encode(['ok' => false, 'mensaje' => 'La transferencia está anulada.']);
                exit;
            }

            $items = [];
            foreach ($doc['detalles'] as $d) {
                $items[] = [
                    'id_producto'      => (int) $d['id_producto'],
                    'codigo_principal' => (string) ($d['producto_codigo'] ?? ''),
                    'descripcion'      => (string) ($d['producto_nombre'] ?? ''),
                    'cantidad'         => (float) $d['cantidad'],
                ];
            }

            $_SESSION['gr_prefill'] = [
                'origen'              => 'transferencia_inventario',
                'id_transferencia'    => (int) $doc['id'],
                'numero'              => (string) $doc['numero'],
                'fecha'               => date('Y-m-d', strtotime((string) $doc['fecha_transferencia'])),
                'motivo'              => 'TRANSFERENCIA DE MERCADERÍA ENTRE ESTABLECIMIENTOS - ' . $doc['numero'],
                'direccion_partida'   => (string) ($doc['establecimiento_origen_direccion'] ?? ''),
                'direccion_destino'   => (string) ($doc['establecimiento_destino_direccion'] ?? ''),
                'cod_est_destino'     => (string) ($doc['establecimiento_destino_codigo'] ?? ''),
                'items'               => $items,
            ];

            echo json_encode([
                'ok'  => true,
                'url' => rtrim(BASE_URL, '/') . '/modulos/guias-remision',
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // EXPORTACIONES
    // ────────────────────────────────────────────────────────────────

    public function exportPdf(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm      = $this->getPermisos();
        $rows      = $this->service->getListado(
            $idEmpresa,
            trim($_GET['b'] ?? ''),
            1,
            5000,
            trim($_GET['sort'] ?? 'fecha_transferencia'),
            strtoupper(trim($_GET['dir'] ?? 'DESC')),
            $this->getIdUsuarioFiltro($perm),
            $this->getFiltrosDesdeRequest()
        )['rows'];

        try {
            $empresa  = (new Empresa())->getPorId($idEmpresa) ?? [];
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .text-end { text-align: right; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm" orientation="landscape">
                <div class="header">
                    <h1><?= htmlspecialchars((string) ($empresa['nombre'] ?? '')) ?></h1>
                    <h2>Transferencias de Inventario</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:12%">Número</th>
                            <th style="width:15%">Fecha</th>
                            <th style="width:20%">Origen</th>
                            <th style="width:20%">Destino</th>
                            <th style="width:11%" class="text-end">Unidades</th>
                            <th style="width:11%" class="text-end">Costo</th>
                            <th style="width:11%">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $r['numero']) ?></td>
                            <td><?= date('d-m-Y H:i:s', strtotime((string) $r['fecha_transferencia'])) ?></td>
                            <td><?= htmlspecialchars((string) $r['origen_nombre']) ?></td>
                            <td><?= htmlspecialchars((string) $r['destino_nombre']) ?></td>
                            <td class="text-end"><?= number_format((float) $r['total_items'], 2) ?></td>
                            <td class="text-end"><?= number_format((float) $r['total_costo'], 2) ?></td>
                            <td><?= ucfirst((string) $r['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
            <?php
            $html = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('Transferencias_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    public function exportExcel(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $perm      = $this->getPermisos();
        $rows      = $this->service->getListado(
            $idEmpresa,
            trim($_GET['b'] ?? ''),
            1,
            10000,
            trim($_GET['sort'] ?? 'fecha_transferencia'),
            strtoupper(trim($_GET['dir'] ?? 'DESC')),
            $this->getIdUsuarioFiltro($perm),
            $this->getFiltrosDesdeRequest()
        )['rows'];

        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];

            $headers = ['Número', 'Fecha', 'Origen', 'Destino', 'Entre establecimientos', 'Líneas', 'Unidades', 'Costo', 'Estado', 'Registró'];
            $data    = [];
            foreach ($rows as $r) {
                $data[] = [
                    (string) $r['numero'],
                    date('d-m-Y H:i:s', strtotime((string) $r['fecha_transferencia'])),
                    (string) ($r['origen_nombre'] ?? ''),
                    (string) ($r['destino_nombre'] ?? ''),
                    (!empty($r['entre_establecimientos']) && $r['entre_establecimientos'] !== 'f') ? 'SÍ' : 'NO',
                    (int) ($r['lineas'] ?? 0),
                    (float) $r['total_items'],
                    (float) $r['total_costo'],
                    ucfirst((string) $r['estado']),
                    (string) ($r['usuario_nombre'] ?? ''),
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Transferencias',
                $headers,
                $data,
                'Transferencias_Inventario',
                (string) ($empresa['nombre'] ?? '')
            );
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /** Acta de la transferencia (para firmar la entrega/recepción). */
    public function pdfDocumento(): void
    {
        $this->requireLeer();

        $id  = (int) ($_GET['id'] ?? 0);
        $doc = $this->service->getPorId($id, (int) $_SESSION['id_empresa']);

        if (!$doc) {
            http_response_code(404);
            echo 'Transferencia no encontrada';
            exit;
        }

        try {
            $empresa  = (new Empresa())->getPorId((int) $_SESSION['id_empresa']) ?? [];
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
            include MVC_APP . '/views/modulos/transferencias_inventario/pdf_documento.php';
            $html = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('Transferencia_' . $doc['numero'] . '.pdf', 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    // AUXILIARES
    // ────────────────────────────────────────────────────────────────

    /** Sin permiso "todo", el usuario solo ve las transferencias que él registró. */
    private function getIdUsuarioFiltro(array $perm): ?int
    {
        return empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'desde'     => trim($_GET['desde'] ?? ''),
            'hasta'     => trim($_GET['hasta'] ?? ''),
            'id_bodega' => (int) ($_GET['id_bodega'] ?? 0),
            'estado'    => trim($_GET['estado'] ?? ''),
        ];
    }

    /**
     * Bodegas que el usuario puede operar, con su establecimiento. Cruza las
     * permitidas (usuarios_bodegas) con el catálogo que trae el establecimiento.
     */
    private function getBodegasDelUsuario(int $idEmpresa): array
    {
        $permitidas = [];
        foreach ((new BodegaRepository())->getBodegasPermitidas((int) $_SESSION['id_usuario'], $idEmpresa, (int) ($_SESSION['nivel'] ?? 1)) as $b) {
            $permitidas[(int) $b['id']] = true;
        }

        return array_values(array_filter(
            $this->repository->getBodegasConEstablecimiento($idEmpresa),
            fn($b) => isset($permitidas[(int) $b['id']])
        ));
    }
}
