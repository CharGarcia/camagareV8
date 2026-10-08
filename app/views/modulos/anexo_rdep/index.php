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

$base = BASE_URL;
$urlBaseRdep = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 25;
$ordenCol   = $ordenCol ?? 'anio';
$ordenDir   = $ordenDir ?? 'DESC';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
$colores = ['borrador' => 'secondary', 'generado' => 'success'];
$fmt = fn($v) => '$' . number_format((float) $v, 2);
$anioDefecto = (int) date('Y') - 1;
?>
<style>
    .rdep-header { flex-shrink: 0; }
    .anexo-rdep-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .anexo-rdep-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .rdep-row { cursor: pointer; }
    .rdep-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="rdep-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-code"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="RDEP_abrirModalNuevo()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<?php if (isset($instalado) && !$instalado): ?>
    <div class="alert alert-warning shadow-sm">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <b>El módulo todavía no está instalado en esta base de datos.</b> Falta ejecutar
        <code>database/migrations/20261008_create_anexo_rdep.sql</code> (crea las tablas y el menú).
    </div>
<?php endif; ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            $opcionesAnio = array_map(fn($a) => ['v' => (string) $a, 'l' => (string) $a], $aniosDisponibles ?? []);
            $tR = 'Anexo';
            $filtrosRdep = [
                ['tab' => $tR, 'key' => 'anio',       'label' => 'Ejercicio',          'icon' => 'bi-calendar3',      'type' => 'select',       'grupo' => 'Período', 'col' => 4, 'options' => $opcionesAnio],
                ['tab' => $tR, 'key' => 'generado',   'label' => 'Fecha de generación','icon' => 'bi-calendar-event', 'type' => 'date_range',   'grupo' => 'Período', 'col' => 8, 'atajos' => true],
                ['tab' => $tR, 'key' => 'estado',     'label' => 'Estado',             'icon' => 'bi-flag',           'type' => 'select',       'grupo' => 'Estado',  'col' => 4, 'options' => [
                    ['v' => 'borrador', 'l' => 'Borrador'], ['v' => 'generado', 'l' => 'Generado'],
                ]],
                ['tab' => $tR, 'key' => 'empleador',  'label' => 'Tipo de empleador',  'icon' => 'bi-building',       'type' => 'select',       'grupo' => 'Estado',  'col' => 4, 'options' => [
                    ['v' => 'PRIVADO_MIXTO', 'l' => 'Privado o mixto'], ['v' => 'PUBLICO', 'l' => 'Público'],
                ]],
                ['tab' => $tR, 'key' => 'trabajadores', 'label' => 'Trabajadores',     'icon' => 'bi-people',         'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'ingresos',   'label' => 'Ingresos gravados',  'icon' => 'bi-cash-stack',     'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
                ['tab' => $tR, 'key' => 'retenido',   'label' => 'Retenido',           'icon' => 'bi-cash-coin',      'type' => 'number_range', 'grupo' => 'Valores', 'col' => 4],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorRDEP"></div>
            <input type="hidden" id="buscarRdep" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorRDEP',
                        hiddenInputId: 'buscarRdep',
                        placeholder: 'Buscar por ejercicio, RUC o razón social...',
                        titulo: 'Filtros del Anexo RDEP',
                        inputWidth: 340,
                        extraId: 'fmExtraRDEP',
                        fields: <?= json_encode($filtrosRdep, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyRdep',
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>
            <div id="fmExtraRDEP" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'anio' => 'Ejercicio', 'empleador' => 'Empleador', 'trabajadores' => 'Trabajadores',
                    'ingresos' => 'Ingresos gravados', 'retenido' => 'Retenido', 'observaciones' => 'Observaciones',
                    'estado' => 'Estado', 'generado' => 'Generado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf" href="<?= $urlBaseRdep ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseRdep ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="anexo-rdep-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="anio" data-col="anio">Ejercicio <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th data-col="empleador">Empleador</th>
                        <th class="sortable-header text-center" role="button" data-sort="trabajadores" data-col="trabajadores">Trabajadores <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="ingresos" data-col="ingresos">Ingresos gravados <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-end" role="button" data-sort="retenido" data-col="retenido">Retenido <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center" data-col="observaciones">Observaciones</th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="generado" data-col="generado">Generado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3" style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody id="tbodyRdep">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-code fs-3 d-block mb-2"></i>No se encontraron anexos.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $c = $colores[$r['estado']] ?? 'secondary';
                            $graves = (int) $r['total_graves']; $leves = (int) $r['total_leves'];
                        ?>
                            <tr class="rdep-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="RDEP_abrir(this)">
                                <td class="ps-3 fw-medium" data-col="anio"><?= (int) $r['anio'] ?></td>
                                <td data-col="empleador"><?= htmlspecialchars(\App\Helpers\CatalogoRdep::TIPO_EMPLEADOR[$r['tipo_empleador']] ?? (string) $r['tipo_empleador']) ?></td>
                                <td class="text-center" data-col="trabajadores"><?= (int) $r['total_trabajadores'] ?></td>
                                <td class="text-end" data-col="ingresos"><?= $fmt($r['total_ingresos']) ?></td>
                                <td class="text-end fw-bold" data-col="retenido"><?= $fmt($r['total_retenido']) ?></td>
                                <td class="text-center" data-col="observaciones">
                                    <?php if ($graves > 0): ?><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 me-1" title="Observaciones graves"><?= $graves ?></span><?php endif; ?>
                                    <?php if ($leves > 0): ?><span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25" title="Observaciones leves"><?= $leves ?></span><?php endif; ?>
                                    <?php if ($graves === 0 && $leves === 0): ?><i class="bi bi-check-circle text-success"></i><?php endif; ?>
                                </td>
                                <td class="text-center" data-col="estado"><span class="badge bg-<?= $c ?> bg-opacity-10 text-<?= $c ?> border border-<?= $c ?> border-opacity-25"><?= htmlspecialchars(ucfirst((string) $r['estado'])) ?></span></td>
                                <td data-col="generado"><?= !empty($r['generado_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['generado_at'])) : '-' ?></td>
                                <td class="text-center pe-3" onclick="event.stopPropagation()">
                                    <?php if (!empty($r['archivo_xml']) && $r['estado'] === 'generado'): ?>
                                        <button class="btn btn-outline-secondary btn-xs border-0 px-2" onclick="RDEP_descargar('<?= htmlspecialchars(str_replace('.xml', '.zip', (string) $r['archivo_xml'])) ?>')" title="Descargar ZIP"><i class="bi bi-file-earmark-zip"></i></button>
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

<!-- Modal Nuevo: elegir el ejercicio -->
<div class="modal fade" id="modalNuevoRdep" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formNuevoRdep" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-code text-primary me-2"></i>Nuevo Anexo RDEP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-3 py-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold d-block">Ejercicio *</label>
                            <input type="number" class="form-control form-control-sm" name="anio" id="rdep_nuevo_anio" value="<?= $anioDefecto ?>" min="2006" max="2100">
                        </div>
                        <div class="col-md-8">
                            <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-0">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Se abre el anexo del ejercicio con los datos del empleador y se arma desde la nómina
                                (roles mensuales, décimos, utilidades y gastos personales). Si ya existe, se abre el existente.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4" id="btnNuevoRdep"><i class="bi bi-check2-circle me-1"></i> Abrir anexo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
    window.RDEP_ESTABLECIMIENTOS = <?= json_encode($establecimientos ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.RDEP_PAISES = <?= json_encode(\App\Helpers\CatalogoPaisesSri::ordenados(), JSON_UNESCAPED_UNICODE) ?>;
    window.RDEP_PERM = <?= json_encode(['crear' => !empty($perm['crear']), 'actualizar' => !empty($perm['actualizar']), 'eliminar' => !empty($perm['eliminar'])]) ?>;
</script>
<?php include __DIR__ . '/modal_anexo.php'; ?>
<?php include __DIR__ . '/modal_trabajador.php'; ?>
<script src="<?= $base ?>/js/modulos/anexo_rdep.js?v=<?= asset_ver('/js/modulos/anexo_rdep.js') ?>"></script>

<script>
    (function () {
        'use strict';
        const urlBase = '<?= $urlBaseRdep ?>';
        const inputBuscar = document.getElementById('buscarRdep');
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir = '<?= $ordenDir ?>';
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        window.currentPage = <?= $page ?>;
        let sorter = null;
        let timerId;
        function debounce(func, delay = 350) {
            return (...args) => { clearTimeout(timerId); timerId = setTimeout(() => func.apply(this, args), delay); };
        }
        window.cambiarPaginaAjax = (n) => window.fetchSearch(n);
        window.fetchSearch = async (page = 1) => {
            const term = inputBuscar ? inputBuscar.value.trim() : '';
            const orden = window.CMG_ordenParam(window.currentSorts || []);
            const tbody = document.getElementById('tbodyRdep');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(`${urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`);
                const data = await resp.json();
                if (data.ok) {
                    window.currentPage = page;
                    tbody.innerHTML = data.rows;
                    document.getElementById('paginationContainer').innerHTML = data.pagination;
                    document.getElementById('paginationInfo').textContent = data.info;
                    document.getElementById('btnExportPdf').href = data.pdf_url;
                    document.getElementById('btnExportExcel').href = data.excel_url;
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) {
                console.error('Error en búsqueda de anexos RDEP:', e);
            } finally {
                if (tbody) tbody.classList.remove('fm-cargando-target');
            }
        };
        sorter = window.CMG_initSort('anexo_rdep', (col, dir, sorts) => {
            window.currentSort = col; window.currentDir = dir; window.currentSorts = sorts; fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.anexo-rdep-scroll', reload: false });
        if (inputBuscar) inputBuscar.addEventListener('input', debounce(() => fetchSearch(1), 400));
        window.addEventListener('rdepActualizado', () => fetchSearch(window.currentPage || 1));
    })();
</script>
