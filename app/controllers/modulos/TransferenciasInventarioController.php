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

        // Las filas se arman con el mismo método que usa el refresco AJAX: así el
        // listado no se pinta de dos formas distintas.
        $filasHtml = '';
        foreach ($result['rows'] as $r) {
            $filasHtml .= $this->renderFila($r);
        }

        $this->viewWithLayout('layouts.main', 'modulos/transferencias_inventario/index', [
            'titulo'      => 'Transferencias de Inventario',
            'filasHtml'   => $filasHtml,
            'recepcionOk' => $this->service->soportaRecepcion(),
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
            echo '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron transferencias.</td></tr>';
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

        // Botón de envío del acta por correo: no debe abrir el modal de la fila.
        $btnCorreo = $anulada
            ? ''
            : '<button type="button" class="btn btn-sm btn-outline-info px-2 py-0" title="Enviar el acta por correo"'
                . ' onclick="event.stopPropagation(); window.TRI_abrirEnvioCorreo(' . (int) $r['id'] . ')">'
                . '<i class="bi bi-envelope"></i></button>';

        return '<tr class="tri-row" role="button" onclick="window.TRI_verTransferencia(' . (int) $r['id'] . ')">'
            . '<td class="ps-3" data-col="numero"><code>' . htmlspecialchars((string) ($r['numero'] ?? '')) . '</code>' . $entreEst . '</td>'
            . '<td data-col="fecha_transferencia">' . $fecha . '</td>'
            . '<td data-col="origen_nombre"><span class="badge bg-light text-dark border">' . htmlspecialchars((string) ($r['origen_nombre'] ?? '')) . '</span></td>'
            . '<td data-col="destino_nombre"><i class="bi bi-arrow-right text-muted small me-1"></i><span class="badge bg-light text-dark border">' . htmlspecialchars((string) ($r['destino_nombre'] ?? '')) . '</span></td>'
            . '<td class="text-end" data-col="lineas">' . (int) ($r['lineas'] ?? 0) . '</td>'
            . '<td class="text-end" data-col="total_items">' . number_format((float) ($r['total_items'] ?? 0), 2) . '</td>'
            . '<td class="text-end" data-col="total_costo">$' . number_format((float) ($r['total_costo'] ?? 0), 2) . '</td>'
            . '<td class="text-center" data-col="estado">' . $badge . '</td>'
            . '<td class="text-center" data-col="recepcion">' . self::badgeRecepcion($r, $anulada) . '</td>'
            . '<td class="text-center pe-3" data-col="acciones">' . $btnCorreo . '</td>'
            . '</tr>';
    }

    /**
     * Badge del estado de recepción (conformidad del destino). Mientras la
     * migración 20260921 no esté aplicada, la columna llega vacía y se muestra
     * como "pendiente", que es exactamente lo que significa.
     */
    private static function badgeRecepcion(array $r, bool $anulada = false): string
    {
        if ($anulada) {
            return '<span class="text-muted">—</span>';
        }

        $estado = (string) ($r['recepcion_estado'] ?? 'pendiente');
        $titulo = '';
        if ($estado === 'recibida' && !empty($r['recepcion_fecha'])) {
            $titulo = ' title="' . htmlspecialchars(trim(((string) ($r['recepcion_nombre'] ?? '')) . ' · ' . date('d-m-Y H:i:s', strtotime((string) $r['recepcion_fecha']))), ENT_QUOTES) . '"';
        }

        return match ($estado) {
            'recibida'  => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"' . $titulo . '><i class="bi bi-check2-circle me-1"></i>Recibida</span>',
            'rechazada' => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-x-circle me-1"></i>Rechazada</span>',
            'enviada'   => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-send me-1"></i>Enviada</span>',
            default     => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Pendiente</span>',
        };
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

        // Lotes que existen en otra bodega pero no aquí: el modal los muestra como
        // aviso para que el usuario sepa que el lote que busca no se perdió, solo
        // está en otro sitio (y con qué bodega de origen lo encontraría).
        $enLaBodega = array_flip(array_column($lotes, 'numero_lote'));
        $otras = array_values(array_filter(
            $this->repository->getLotesEnOtrasBodegas(
                $idProducto,
                $idBodega,
                $idEmp,
                (new BodegaRepository())->getIdsBodegasDenegadas(
                    (int) $_SESSION['id_usuario'],
                    $idEmp,
                    (int) ($_SESSION['nivel'] ?? 1)
                )
            ),
            fn($l) => !isset($enLaBodega[$l['numero_lote']])
        ));

        echo json_encode([
            'ok'                  => true,
            'stock'               => $repo->getStockActual($idProducto, $idBodega, $idEmp),
            'costo'               => $this->repository->getCostoOrigen($idProducto, $idBodega, $idEmp),
            'lotes'               => $lotes,
            'lotes_otras_bodegas' => $otras,
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
                            <th style="width:11%">Número</th>
                            <th style="width:14%">Fecha</th>
                            <th style="width:18%">Origen</th>
                            <th style="width:18%">Destino</th>
                            <th style="width:10%" class="text-end">Unidades</th>
                            <th style="width:10%" class="text-end">Costo</th>
                            <th style="width:9%">Estado</th>
                            <th style="width:10%">Recepción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php // Html2Pdf: el ancho debe repetirse en CADA celda, no basta con el <th>. ?>
                            <td style="width:11%"><?= htmlspecialchars((string) $r['numero']) ?></td>
                            <td style="width:14%"><?= date('d-m-Y H:i:s', strtotime((string) $r['fecha_transferencia'])) ?></td>
                            <td style="width:18%"><?= htmlspecialchars((string) $r['origen_nombre']) ?></td>
                            <td style="width:18%"><?= htmlspecialchars((string) $r['destino_nombre']) ?></td>
                            <td style="width:10%" class="text-end"><?= number_format((float) $r['total_items'], 2) ?></td>
                            <td style="width:10%" class="text-end"><?= number_format((float) $r['total_costo'], 2) ?></td>
                            <td style="width:9%"><?= ucfirst((string) $r['estado']) ?></td>
                            <td style="width:10%"><?= ($r['estado'] ?? '') === 'anulada' ? '-' : ucfirst((string) ($r['recepcion_estado'] ?? 'pendiente')) ?></td>
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

            $headers = ['Número', 'Fecha', 'Origen', 'Destino', 'Entre establecimientos', 'Líneas', 'Unidades', 'Costo', 'Estado', 'Recepción', 'Recibió', 'Fecha recepción', 'Registró'];
            $data    = [];
            foreach ($rows as $r) {
                $recepcion = ($r['estado'] ?? '') === 'anulada' ? '' : ucfirst((string) ($r['recepcion_estado'] ?? 'pendiente'));
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
                    $recepcion,
                    (string) ($r['recepcion_nombre'] ?? ''),
                    !empty($r['recepcion_fecha']) ? date('d-m-Y H:i:s', strtotime((string) $r['recepcion_fecha'])) : '',
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
            $this->generarActaPdf($doc, (int) $_SESSION['id_empresa'], 'D');
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Genera el acta. 'D' la descarga; 'S' la devuelve como string (que es lo que
     * se adjunta al correo). El armado vive en TransferenciaActaPdfService porque
     * la página pública de recepción genera esa misma acta sin sesión.
     */
    private function generarActaPdf(array $doc, int $idEmpresa, string $destino): string
    {
        return (new \App\Services\modulos\TransferenciaActaPdfService())->generar($doc, $idEmpresa, $destino);
    }

    // ────────────────────────────────────────────────────────────────
    // ENVÍO DEL ACTA POR CORREO + CONFIRMACIÓN DE RECEPCIÓN
    // ────────────────────────────────────────────────────────────────

    /**
     * Datos para el modal de envío: usuarios del sistema con correo (marcando
     * quiénes operan la bodega de destino) y los destinatarios del último envío.
     */
    public function getDestinatariosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $id        = (int) ($_GET['id'] ?? 0);
        $doc       = $this->service->getPorId($id, $idEmpresa);

        if (!$doc) {
            echo json_encode(['ok' => false, 'mensaje' => 'La transferencia no existe.']);
            exit;
        }

        $usuarios = array_map(static fn($u) => [
            'id'     => (int) $u['id'],
            'nombre' => (string) $u['nombre'],
            'mail'   => (string) $u['mail'],
            'acceso' => !empty($u['acceso']) && $u['acceso'] !== 'f',
        ], $this->repository->getUsuariosParaCorreo($idEmpresa, (int) $doc['id_bodega_destino']));

        echo json_encode([
            'ok'        => true,
            'numero'    => (string) $doc['numero'],
            'destino'   => (string) ($doc['destino_nombre'] ?? ''),
            'recibe'    => (string) ($doc['responsable_recibe'] ?? ''),
            'anulada'   => ($doc['estado'] ?? '') === 'anulada',
            'recepcion' => (string) ($doc['recepcion_estado'] ?? 'pendiente'),
            'correos'   => (string) ($doc['recepcion_correos'] ?? ''),
            'usuarios'  => $usuarios,
        ]);
        exit;
    }

    /**
     * Envía el acta de la transferencia por correo con el enlace para que el
     * destinatario confirme la recepción. Usa la misma maquinaria SMTP que el
     * resto de documentos (EnvioDocumentosSRIService::enviarPdfSimple).
     */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        // Se suelta el candado de la sesión antes de armar el PDF y hablar con el
        // SMTP: si no, las demás peticiones del usuario hacen fila hasta terminar.
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        session_write_close();
        header('Content-Type: application/json');

        $id       = (int) ($_POST['id'] ?? 0);
        $correos  = trim((string) ($_POST['correos'] ?? ''));
        $mensaje  = trim((string) ($_POST['mensaje'] ?? ''));
        $adjuntar = !isset($_POST['adjuntar_pdf']) || !in_array((string) $_POST['adjuntar_pdf'], ['0', 'false', ''], true);

        if (!$id) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        $destinos = [];
        foreach (preg_split('/[\s,;]+/', $correos) ?: [] as $c) {
            $c = trim((string) $c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $destinos[strtolower($c)] = $c;
            }
        }
        if (empty($destinos)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Indique al menos un correo válido.']);
            exit;
        }
        $destinos = array_values($destinos);

        try {
            $doc = $this->service->getPorId($id, $idEmpresa);
            if (!$doc) {
                echo json_encode(['ok' => false, 'mensaje' => 'La transferencia no existe.']);
                exit;
            }
            if (($doc['estado'] ?? '') === 'anulada') {
                echo json_encode(['ok' => false, 'mensaje' => 'La transferencia está anulada: no se puede enviar para confirmar.']);
                exit;
            }

            $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
            $empresaNombre = (string) ($empresa['nombre_comercial'] ?? ($empresa['nombre'] ?? 'CaMaGaRe'));

            // Enlaces públicos (el mismo token sirve para ver el acta y para
            // confirmar). El de confirmación solo se ofrece mientras la recepción
            // siga abierta; el del PDF va siempre que haya token, porque permite
            // abrir el acta aunque el cliente de correo bloquee el adjunto. Si la
            // migración no está aplicada no hay token y el correo sale sin enlaces.
            $urlConfirmar = '';
            $urlPdf       = '';
            $recepcion    = (string) ($doc['recepcion_estado'] ?? 'pendiente');
            if ($this->service->soportaRecepcion()) {
                try {
                    $token  = $this->service->obtenerTokenRecepcion($id, $idEmpresa);
                    $urlPdf = $this->urlPublica('/recepcion-transferencia/' . $token . '/pdf');
                    if (!in_array($recepcion, ['recibida', 'rechazada'], true)) {
                        $urlConfirmar = $this->urlPublica('/recepcion-transferencia/' . $token);
                    }
                } catch (\Throwable $e) {
                    $urlConfirmar = '';
                    $urlPdf       = '';
                }
            }

            $pdf = '';
            if ($adjuntar) {
                try {
                    $pdf = $this->generarActaPdf($doc, $idEmpresa, 'S');
                } catch (\Throwable $e) {
                    \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
                    $pdf = '';
                }
            }

            $enviado = (new \App\Services\EnvioDocumentosSRIService())->enviarPdfSimple(
                $idEmpresa,
                implode(',', $destinos),
                (string) ($doc['responsable_recibe'] ?? ''),
                'Transferencia de inventario ' . $doc['numero'] . ' · ' . $empresaNombre,
                $this->construirCorreoTransferencia($doc, $empresaNombre, $mensaje, $urlConfirmar, $urlPdf, $pdf !== ''),
                $pdf,
                'Transferencia_' . $doc['numero'],
                $empresaNombre
            );

            if (!$enviado) {
                echo json_encode(['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.']);
                exit;
            }

            $this->service->registrarEnvioCorreo($id, $idEmpresa, $idUsuario, implode(', ', $destinos));

            echo json_encode([
                'ok'      => true,
                'mensaje' => $urlConfirmar !== ''
                    ? 'Acta enviada a ' . implode(', ', $destinos) . '. El destinatario puede confirmar la recepción desde el correo.'
                    : 'Acta enviada a ' . implode(', ', $destinos) . '.',
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Cuerpo HTML del correo: resumen del acta + enlace al PDF + botón de confirmación. */
    private function construirCorreoTransferencia(array $doc, string $empresaNombre, string $mensaje, string $urlConfirmar, string $urlPdf = '', bool $conAdjunto = true): string
    {
        $e     = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $fecha = !empty($doc['fecha_transferencia']) ? date('d-m-Y H:i:s', strtotime((string) $doc['fecha_transferencia'])) : '';

        $filas = '';
        foreach (($doc['detalles'] ?? []) as $d) {
            $filas .= '<tr>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #eee;">'
                . (!empty($d['producto_codigo']) ? '<b>' . $e($d['producto_codigo']) . '</b> — ' : '')
                . $e($d['producto_nombre'] ?? '')
                . (!empty($d['numero_lote']) ? '<span style="color:#777;font-size:12px;"> · lote ' . $e($d['numero_lote']) . '</span>' : '')
                . (!empty($d['nup']) ? '<span style="color:#777;font-size:12px;"> · serie ' . $e($d['nup']) . '</span>' : '')
                . '</td>'
                . '<td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:right;">' . number_format((float) ($d['cantidad'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        // Botonera: el acta en PDF y, mientras la recepción siga abierta, la
        // confirmación. Se usa una tabla porque los clientes de correo no
        // respetan flex ni gap entre bloques en línea.
        $botones = '';
        if ($urlConfirmar !== '' || $urlPdf !== '') {
            $celdas = '';
            if ($urlConfirmar !== '') {
                $celdas .= '<td style="padding-right:10px;">'
                    . '<a href="' . $e($urlConfirmar) . '" style="background:#16a34a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">'
                    . 'Confirmar recepción</a></td>';
            }
            if ($urlPdf !== '') {
                $celdas .= '<td>'
                    . '<a href="' . $e($urlPdf) . '" style="background:#fff;color:#b91c1c;border:1px solid #b91c1c;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">'
                    . 'Ver el acta en PDF</a></td>';
            }
            $botones = '<table style="border-collapse:collapse;margin:24px 0 8px;"><tr>' . $celdas . '</tr></table>';
        }

        $pie = '';
        if ($urlConfirmar !== '') {
            $pie .= '<p style="color:#777;font-size:12px;margin:4px 0;">Si algo no llegó conforme, en esa misma página puede rechazar la recepción indicando el motivo.</p>';
        }
        if ($urlPdf !== '') {
            $pie .= '<p style="color:#777;font-size:12px;margin:4px 0;">Si el botón no funciona, copie este enlace en su navegador:<br>'
                 . '<a href="' . $e($urlPdf) . '" style="color:#2563eb;">' . $e($urlPdf) . '</a></p>';
        }
        $boton = $botones . $pie;

        $nota = $mensaje === '' ? '' :
            '<p style="background:#f8fafc;border-left:3px solid #2563eb;padding:10px 14px;color:#334155;">' . nl2br($e($mensaje)) . '</p>';

        return '
            <div style="font-family:Arial,sans-serif;color:#333;max-width:640px;margin:auto;">
                <h2 style="color:#2563eb;margin-bottom:4px;">Transferencia de inventario ' . $e($doc['numero']) . '</h2>
                <p style="color:#666;margin-top:0;">' . $e($empresaNombre) . '</p>
                ' . $nota . '
                <table style="border-collapse:collapse;font-size:14px;margin-top:12px;">
                    <tr><td style="padding:4px 12px;color:#666;">Fecha</td><td style="padding:4px 12px;"><strong>' . $e($fecha) . '</strong></td></tr>
                    <tr><td style="padding:4px 12px;color:#666;">Desde</td><td style="padding:4px 12px;">' . $e($doc['origen_nombre'] ?? '') . '</td></tr>
                    <tr><td style="padding:4px 12px;color:#666;">Hacia</td><td style="padding:4px 12px;"><strong>' . $e($doc['destino_nombre'] ?? '') . '</strong></td></tr>
                    <tr><td style="padding:4px 12px;color:#666;">Entrega</td><td style="padding:4px 12px;">' . $e($doc['responsable_envia'] ?? '—') . '</td></tr>
                    <tr><td style="padding:4px 12px;color:#666;">Recibe</td><td style="padding:4px 12px;">' . $e($doc['responsable_recibe'] ?? '—') . '</td></tr>
                </table>

                <table style="border-collapse:collapse;width:100%;font-size:13px;margin-top:18px;">
                    <thead><tr style="background:#f8fafc;color:#475569;">
                        <th style="padding:6px 8px;text-align:left;">Producto</th>
                        <th style="padding:6px 8px;text-align:right;width:90px;">Cantidad</th>
                    </tr></thead>
                    <tbody>' . $filas . '</tbody>
                    <tfoot><tr>
                        <td style="padding:6px 8px;text-align:right;font-weight:bold;">Total</td>
                        <td style="padding:6px 8px;text-align:right;font-weight:bold;">' . number_format((float) ($doc['total_items'] ?? 0), 2) . '</td>
                    </tr></tfoot>
                </table>
                ' . $boton . '
                <p style="color:#888;font-size:12px;margin-top:24px;">
                    ' . ($conAdjunto ? 'El acta completa va adjunta en PDF.' : '') . '
                    Este documento es de control interno de inventario y no sustituye a la guía de remisión
                    exigida para el traslado de mercadería.
                </p>
            </div>';
    }

    /**
     * URL absoluta (con dominio) para los enlaces que viajan en un correo:
     * BASE_URL es solo la ruta del subdirectorio y fuera del navegador no sirve.
     */
    private function urlPublica(string $ruta): string
    {
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base   = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');

        if ($host === '' && defined('APP_URL') && APP_URL !== '') {
            return rtrim(APP_URL, '/') . $ruta;
        }
        return ($host !== '' ? $scheme . '://' . $host : '') . $base . $ruta;
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
        $recepcion = trim($_GET['recepcion'] ?? '');

        return [
            'desde'     => trim($_GET['desde'] ?? ''),
            'hasta'     => trim($_GET['hasta'] ?? ''),
            'id_bodega' => (int) ($_GET['id_bodega'] ?? 0),
            'estado'    => trim($_GET['estado'] ?? ''),
            'recepcion' => in_array($recepcion, ['pendiente', 'enviada', 'recibida', 'rechazada'], true) ? $recepcion : '',
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
