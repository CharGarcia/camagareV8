<?php
/** @var string $titulo */
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $rows */
/** @var int    $total */
/** @var int    $page */
/** @var int    $totalPages */
/** @var int    $from */
/** @var int    $to */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var array  $filtros */
/** @var array  $resumen */
/** @var array  $bodegas */
/** @var array  $vistaConfig */

$idModulo = str_replace('-', '_', basename($rutaModulo));
$urlBase  = rtrim(BASE_URL, '/') . '/' . $rutaModulo;
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .tri-scroll { overflow-x: auto; }
    .tri-scroll thead th { background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .tri-row { cursor: pointer; }
    .tri-row:hover { background-color: rgba(0, 0, 0, .04); }
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-tri .form-select,
    #form-filtros-tri .form-control,
    #form-filtros-tri .input-group-text,
    #form-filtros-tri .btn { height: 28px; font-size: .75rem; }
    /* Filas compactas de la tabla de líneas del modal */
    #tri-tbody-detalle td { vertical-align: middle; }
    #tri-tbody-detalle input,
    #tri-tbody-detalle select { padding: 0 4px; height: 22px; font-size: 0.78rem; border: 1px solid #dee2e6; border-radius: 3px; width: 100%; }
    #tri-tbody-detalle input[readonly] { background: #f8f9fa; }
    .tri-dropdown-prod { max-height: 220px; overflow-y: auto; }
    /* La tabla se extiende libremente hacia abajo; hace scroll la página */
    @media (max-width: 767.98px) {
        #modulo-<?= $idModulo ?> .tri-scroll { max-height: none !important; height: auto !important; overflow-y: visible !important; }
    }
</style>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?= $idModulo ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <?php if (!empty($perm['crear'])): ?>
                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.TRI_nueva()">
                    <i class="bi bi-plus-lg me-1"></i> Nueva
                </button>
            <?php endif; ?>
        </div>

        <div class="card-body p-3">
            <form id="form-filtros-tri" onsubmit="event.preventDefault(); window.TRI_buscar(1);" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha desde</label>
                    <input type="date" id="tri-desde" class="form-control form-control-sm shadow-none border" style="width:115px;"
                           value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha hasta</label>
                    <input type="date" id="tri-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;"
                           value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Bodega</label>
                    <select id="tri-bodega" class="form-select form-select-sm shadow-none border" style="width:180px;" onchange="window.TRI_buscar(1)">
                        <option value="">Todas</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (int) ($filtros['id_bodega'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nombre']) ?><?= !empty($b['establecimiento_codigo']) ? ' (' . htmlspecialchars($b['establecimiento_codigo']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="tri-estado" class="form-select form-select-sm shadow-none border" style="width:130px;" onchange="window.TRI_buscar(1)">
                        <option value="">Todos</option>
                        <option value="registrada" <?= ($filtros['estado'] ?? '') === 'registrada' ? 'selected' : '' ?>>Registradas</option>
                        <option value="anulada"    <?= ($filtros['estado'] ?? '') === 'anulada' ? 'selected' : '' ?>>Anuladas</option>
                    </select>
                </div>

                <!-- Buscador + botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="tri-buscar" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Número, bodega, responsable u observación…" autocomplete="off"
                                   value="<?= htmlspecialchars($buscar) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="window.TRI_limpiarFiltros()">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="bi bi-search me-1"></i>Aplicar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-file-earmark-text bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="tri-stat-documentos"><?= (int) ($resumen['documentos'] ?? 0) ?></div>
                        <div class="cmg-control-card__stat-label">Transferencias</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-boxes bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="tri-stat-unidades"><?= number_format((float) ($resumen['unidades'] ?? 0), 2) ?></div>
                        <div class="cmg-control-card__stat-label">Unidades movidas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-info">$<span id="tri-stat-costo"><?= number_format((float) ($resumen['costo'] ?? 0), 2) ?></span></div>
                        <div class="cmg-control-card__stat-label">Costo transferido</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-signpost-split bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-warning" id="tri-stat-inter"><?= (int) ($resumen['interestablecimiento'] ?? 0) ?></div>
                        <div class="cmg-control-card__stat-label">Entre establecimientos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-danger" onclick="window.TRI_exportar('pdf')">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="window.TRI_exportar('excel')">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                    </button>
                </div>
                <div class="btn-group btn-group-sm">
                    <?php
                    $columnasTabla = [
                        'numero'              => 'Número',
                        'fecha_transferencia' => 'Fecha',
                        'origen_nombre'       => 'Origen',
                        'destino_nombre'      => 'Destino',
                        'lineas'              => 'Líneas',
                        'total_items'         => 'Unidades',
                        'total_costo'         => 'Costo',
                        'estado'              => 'Estado',
                    ];
                    echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo);
                    ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="tri-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
                <div id="tri-paginacion" class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="window.TRI_cambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="window.TRI_cambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="tri-scroll w-100">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <?php
                            $th = function (string $key, string $label, string $align = '', string $extra = '') use ($ordenCol, $ordenDir) {
                                $icon = 'bi-arrow-down-up small text-muted';
                                if ($ordenCol === $key) {
                                    $icon = strtoupper($ordenDir) === 'ASC' ? 'bi-sort-alpha-down text-primary' : 'bi-sort-alpha-up text-primary';
                                }
                                $cls = $align === 'right' ? 'text-end' : ($align === 'center' ? 'text-center' : '');
                                echo '<th class="ps-3 py-2 sortable-header ' . $cls . ' ' . $extra . '" role="button" data-col="' . $key . '" onclick="window.TRI_ordenar(\'' . $key . '\')">'
                                    . $label . ' <i class="bi ' . $icon . ' ms-1"></i></th>';
                            };
                            $th('numero', 'Número');
                            $th('fecha_transferencia', 'Fecha');
                            $th('origen_nombre', 'Origen');
                            $th('destino_nombre', 'Destino');
                            ?>
                            <th class="text-end" data-col="lineas">Líneas</th>
                            <?php
                            $th('total_items', 'Unidades', 'right');
                            $th('total_costo', 'Costo', 'right');
                            $th('estado', 'Estado', 'center', 'pe-3');
                            ?>
                        </tr>
                    </thead>
                    <tbody id="tri-tbody">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>No se encontraron transferencias.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $anulada  = ($r['estado'] ?? '') === 'anulada';
                                $entreEst = !empty($r['entre_establecimientos']) && $r['entre_establecimientos'] !== 'f';
                                ?>
                                <tr class="tri-row" role="button" onclick="window.TRI_verTransferencia(<?= (int) $r['id'] ?>)">
                                    <td class="ps-3" data-col="numero">
                                        <code><?= htmlspecialchars((string) $r['numero']) ?></code>
                                        <?php if ($entreEst): ?><i class="bi bi-signpost-split text-warning" title="Entre establecimientos"></i><?php endif; ?>
                                    </td>
                                    <td data-col="fecha_transferencia"><?= date('d-m-Y H:i:s', strtotime((string) $r['fecha_transferencia'])) ?></td>
                                    <td data-col="origen_nombre"><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $r['origen_nombre']) ?></span></td>
                                    <td data-col="destino_nombre"><i class="bi bi-arrow-right text-muted small me-1"></i><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $r['destino_nombre']) ?></span></td>
                                    <td class="text-end" data-col="lineas"><?= (int) ($r['lineas'] ?? 0) ?></td>
                                    <td class="text-end" data-col="total_items"><?= number_format((float) $r['total_items'], 2) ?></td>
                                    <td class="text-end" data-col="total_costo">$<?= number_format((float) $r['total_costo'], 2) ?></td>
                                    <td class="text-center pe-3" data-col="estado">
                                        <?php if ($anulada): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulada</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Registrada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal.php'; ?>

<script>
    window.TRI_URL_BASE = '<?= $urlBase ?>';
    window.TRI_BODEGAS  = <?= json_encode($bodegas, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
    window.TRI_PERM     = <?= json_encode([
        'crear'      => (bool) ($perm['crear'] ?? false),
        'actualizar' => (bool) ($perm['actualizar'] ?? false),
        'eliminar'   => (bool) ($perm['eliminar'] ?? false),
    ]) ?>;
    window.TRI_ORDEN_COL = '<?= htmlspecialchars($ordenCol) ?>';
    window.TRI_ORDEN_DIR = '<?= htmlspecialchars($ordenDir) ?>';
    window.TRI_MODULO    = '<?= $rutaModulo ?>';
</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/transferencias_inventario.js?v=<?= time() ?>"></script>
