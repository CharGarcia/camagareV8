<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */

$base = BASE_URL;
$urlBasePedidos = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'numero_pedido';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .ped-header {
        flex-shrink: 0;
    }

    .ped-scroll {
        max-height: calc(100vh - 240px);
        overflow: auto;
    }

    .ped-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .pedido-row {
        cursor: pointer;
    }

    .pedido-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }

    /* Estilos Premium para la tabla de detalles del modal */
    .table-detalle th {
        font-size: 0.7rem !important;
        padding: 4px 8px !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
    }

    .table-detalle td {
        padding: 0 !important;
        vertical-align: middle;
    }

    .input-detalle {
        border: none;
        background: transparent;
        height: 30px !important;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
        border-radius: 0;
    }

    .input-detalle:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd !important;
        outline: none;
        border-radius: 4px;
    }

    .row-detalle:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="ped-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart-check text-primary me-2"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="nuevoPedido()">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="<?= $urlBasePedidos ?>" class="d-flex align-items-center m-0">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="b" id="buscar-pedido" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar pedido, cliente..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
                    <?php if ($buscar !== ''): ?>
                        <a href="<?= $urlBasePedidos ?>" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
                <button type="submit" class="d-none">Buscar</button>
            </form>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_pedido'  => 'Nro. Pedido',
                    'fecha_pedido'   => 'Fecha Emisión',
                    'fecha_entrega'  => 'Fecha Entrega',
                    'rango_horario'  => 'Rango Horario',
                    'cliente_nombre' => 'Cliente',
                    'responsable_entrega' => 'Resp. Entrega',
                    'observaciones'  => 'Observaciones',
                    'observaciones_internas' => 'Obs. Internas',
                    'estado'         => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBasePedidos ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-danger" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel" href="<?= $urlBasePedidos ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                    class="btn btn-outline-success" title="Exportar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="info-paginacion" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginacion-pedidos" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="PED_cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>

                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="PED_cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="ped-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_pedido" data-col="numero_pedido">Nro. Pedido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_pedido" data-col="fecha_pedido">Fecha Emisión <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_entrega" data-col="fecha_entrega">Fecha Entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="rango_horario" data-col="rango_horario">Rango Horario <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="cliente_nombre" data-col="cliente_nombre">Cliente <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="responsable_entrega" data-col="responsable_entrega">Resp. Entrega <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones_internas" data-col="observaciones_internas">Obs. Internas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="lista-pedidos">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-3 d-block mb-2"></i>No se encontraron pedidos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $estadoVal  = $r['estado'] ?? 'Pendiente';
                            $badgeColor = match(strtoupper($estadoVal)) {
                                'PENDIENTE' => 'warning',
                                'FACTURADO', 'PROCESADO' => 'success',
                                'ANULADO'   => 'danger',
                                default     => 'secondary',
                            };

                            $fechaEntrega = !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '';
                            $rangoHorario = '';
                            if (!empty($r['hora_inicial_entrega']) || !empty($r['hora_maxima_entrega'])) {
                                $ini = !empty($r['hora_inicial_entrega']) ? date('H:i', strtotime($r['hora_inicial_entrega'])) : '--:--';
                                $max = !empty($r['hora_maxima_entrega']) ? date('H:i', strtotime($r['hora_maxima_entrega'])) : '--:--';
                                $rangoHorario = "$ini - $max";
                            }
                            ?>
                            <tr class="pedido-row" role="button" tabindex="0" onclick="editarPedido(<?= $r['id'] ?>)">
                                <td class="ps-3" data-col="numero_pedido"><code class="text-secondary"><?= htmlspecialchars($r['numero_pedido'] ?? '') ?></code></td>
                                <td data-col="fecha_pedido"><?= !empty($r['fecha_pedido']) ? date('d-m-Y', strtotime($r['fecha_pedido'])) : '' ?></td>
                                <td data-col="fecha_entrega"><?= htmlspecialchars($fechaEntrega) ?></td>
                                <td data-col="rango_horario"><?= htmlspecialchars($rangoHorario) ?></td>
                                <td class="fw-medium text-truncate" style="max-width:250px" data-col="cliente_nombre"><?= htmlspecialchars($r['cliente_nombre'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="responsable_entrega"><?= htmlspecialchars($r['responsable_entrega'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="observaciones"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:200px" data-col="observaciones_internas"><?= htmlspecialchars($r['observaciones_internas'] ?? '') ?></td>
                                <td class="text-center" data-col="estado">
                                    <span class="badge bg-<?= $badgeColor ?> bg-opacity-10 text-<?= $badgeColor ?> border border-<?= $badgeColor ?> border-opacity-25">
                                        <?= htmlspecialchars($estadoVal) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.BASE_URL    = '<?= $base ?>';
    window.CMG_urlBase = '<?= $base ?>/modulos/pedidos';
    window.APP_FAVORITOS = window.APP_FAVORITOS || {};
    window.currentSort = '<?= $ordenCol ?>';
    window.currentDir  = '<?= $ordenDir ?>';
    window.currentPage = <?= $page ?>;
    const TARIFAS_IVA    = <?= json_encode($tarifasIva ?? []) ?>;
    const UNIDADES       = <?= json_encode($unidades ?? []) ?>;
    const EMPRESA_CONFIG = <?= json_encode($empresa ?? []) ?>;
    const DEC_PRECIO     = <?= (int)($empresa['decimales_precio'] ?? 2) ?>;
</script>

<?php include __DIR__ . '/modal_pedido.php'; ?>

<?php
// Copia de seguridad de la ruta y permisos del módulo de pedidos
$rutaModuloOriginal = $rutaModulo ?? 'modulos/pedidos';
$permOriginal = $perm;

// Variables requeridas por el modal de clientes para evitar errores de PHP durante la inclusión
$urlBaseClientes = BASE_URL . '/modulos/clientes';
$permForModal = [
    'ver'        => $perm['ver'] ?? true,
    'crear'      => $perm['crear'] ?? true,
    'actualizar' => $perm['actualizar'] ?? true,
    'eliminar'   => $perm['eliminar'] ?? true,
    'todo'       => $perm['todo'] ?? true
];
$perm = $permForModal;
$canCreateVend = true;
$ordenCol   = 'nombre';
$ordenDir   = 'ASC';
$page       = 1;
$totalPages = 1;

// Incluir modal original de clientes
include dirname(__DIR__) . '/clientes/modal_cliente.php';

// RESTAURAR variables originales para pedidos
$rutaModulo = $rutaModuloOriginal;
$perm = $permOriginal;
?>

<script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= time() ?>"></script>
<script src="<?= $base ?>/js/modulos/pedidos.js?v=<?= time() ?>"></script>