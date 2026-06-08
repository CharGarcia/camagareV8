<?php
/** @var string   $titulo */
/** @var array    $perm */
/** @var string   $rutaModulo */
/** @var array    $vistaConfig */
/** @var string   $tab */
/** @var array    $rowsTipos */
/** @var int      $totalTipos */
/** @var int      $pageTipos */
/** @var int      $totalPagesTipos */
/** @var string   $buscarTipos */
/** @var string   $sortTipos */
/** @var string   $dirTipos */
/** @var array    $rowsUni */
/** @var int      $totalUni */
/** @var int      $pageUni */
/** @var int      $totalPagesUni */
/** @var string   $buscarUni */
/** @var string   $sortUni */
/** @var string   $dirUni */
/** @var int|null $filtroTipo */
/** @var array    $tiposSelect */

$base       = BASE_URL;
$urlBase    = rtrim($base, '/') . '/modulos/unidades-medida';
$tab        = $tab ?? 'tipos';
$rowsTipos  = $rowsTipos ?? [];
$rowsUni    = $rowsUni   ?? [];
$totalTipos = $totalTipos ?? 0;
$totalUni   = $totalUni  ?? 0;
$pageTipos  = $pageTipos ?? 1;
$pageUni    = $pageUni   ?? 1;
$totalPagesTipos = $totalPagesTipos ?? 1;
$totalPagesUni   = $totalPagesUni   ?? 1;
$buscarTipos = $buscarTipos ?? '';
$buscarUni   = $buscarUni   ?? '';
$sortTipos  = $sortTipos ?? 'nombre';
$dirTipos   = $dirTipos  ?? 'asc';
$sortUni    = $sortUni   ?? 'nombre';
$dirUni     = $dirUni    ?? 'asc';
$filtroTipo = $filtroTipo ?? null;
$tiposSelect = $tiposSelect ?? [];
$perPage    = 20;

$fromTipos = $totalTipos > 0 ? (($pageTipos - 1) * $perPage) + 1 : 0;
$toTipos   = $totalTipos > 0 ? min($pageTipos * $perPage, $totalTipos) : 0;
$fromUni   = $totalUni   > 0 ? (($pageUni - 1) * $perPage) + 1 : 0;
$toUni     = $totalUni   > 0 ? min($pageUni * $perPage, $totalUni)   : 0;
?>
<style>
.um-scroll { max-height: calc(100dvh - 300px); overflow-y: auto; }
.um-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.tipo-row, .unidad-row { cursor: pointer; }
.tipo-row:hover, .unidad-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-rulers"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
    <div class="d-flex gap-2">
        <button type="button" id="btnNuevoTipo" class="btn btn-outline-primary btn-sm px-3 <?= $tab !== 'tipos' ? 'd-none' : '' ?>" onclick="abrirModalTipoCrear()"><i class="bi bi-plus-lg"></i> Nuevo Tipo</button>
        <button type="button" id="btnNuevaUnidad" class="btn btn-primary btn-sm px-3 <?= $tab !== 'unidades' ? 'd-none' : '' ?>" onclick="abrirModalUnidadCrear()"><i class="bi bi-plus-lg"></i> Nueva Unidad</button>
    </div>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-3 w-100">

    <!-- Pestañas -->
    <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-3">
        <ul class="nav nav-tabs border-bottom-0" id="tabsUM" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-medium py-2 <?= $tab === 'tipos' ? 'active' : '' ?>"
                        id="tab-tipos-btn" data-tab="tipos" type="button" role="tab" onclick="switchTab('tipos')">
                    <i class="bi bi-tag me-1"></i> Tipos de Medida
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" id="badgeTotalTipos"><?= $totalTipos ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-medium py-2 <?= $tab === 'unidades' ? 'active' : '' ?>"
                        id="tab-unidades-btn" data-tab="unidades" type="button" role="tab" onclick="switchTab('unidades')">
                    <i class="bi bi-rulers me-1"></i> Unidades de Medida
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1" id="badgeTotalUnidades"><?= $totalUni ?></span>
                </button>
            </li>
        </ul>
    </div>

    <div class="border-bottom mx-3"></div>

    <!-- ── PESTAÑA TIPOS ────────────────────────────────────────────── -->
    <div id="pane-tipos" class="<?= $tab !== 'tipos' ? 'd-none' : '' ?>">
        <div class="card-header bg-white py-2 px-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <!-- Buscador Tipos -->
                <div class="input-group input-group-sm" style="width:260px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscarTipos" class="form-control border-start-0 ps-0 shadow-none border"
                           placeholder="Buscar tipo..." value="<?= htmlspecialchars($buscarTipos) ?>" autocomplete="off">
                    <button type="button" id="btnLimpiarBuscarTipos" class="btn border border-start-0 text-muted <?= $buscarTipos === '' ? 'd-none' : '' ?>" title="Limpiar" onclick="limpiarBusquedaTipos()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="btn-group btn-group-sm">
                    <?php
                    $columnasTipos = ['codigo' => 'Código', 'nombre' => 'Nombre', 'total_unidades' => 'Unidades', 'status' => 'Estado'];
                    echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTipos, $vistaConfig ?? [], $rutaModulo . '_tipos');
                    ?>
                    <a id="btnExportPdfTipos" href="<?= $urlBase ?>/export-pdf?tab=tipos&b=<?= urlencode($buscarTipos) ?>&sort=<?= urlencode($sortTipos) ?>&dir=<?= urlencode($dirTipos) ?>"
                       class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <a id="btnExportExcelTipos" href="<?= $urlBase ?>/export-excel?tab=tipos&b=<?= urlencode($buscarTipos) ?>&sort=<?= urlencode($sortTipos) ?>&dir=<?= urlencode($dirTipos) ?>"
                       class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="paginationInfoTipos" class="text-muted small fw-medium"><?= $fromTipos ?>-<?= $toTipos ?>/<?= $totalTipos ?></span>
                <div id="paginationTipos" class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" <?= $pageTipos <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaTiposAjax(<?= $pageTipos - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary rounded-start-0" <?= $pageTipos >= $totalPagesTipos ? 'disabled' : '' ?> onclick="cambiarPaginaTiposAjax(<?= $pageTipos + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="um-scroll w-100">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header-tipos" role="button" data-sort="codigo" data-col="codigo" style="width:120px">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header-tipos" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center sortable-header-tipos" role="button" data-sort="total_unidades" data-col="total_unidades" style="width:110px">Unidades <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center pe-3 sortable-header-tipos" role="button" data-sort="status" data-col="status" style="width:100px">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyTipos">
                        <?php if (empty($rowsTipos)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-rulers fs-3 d-block mb-2"></i>No hay tipos de medida registrados.</td></tr>
                        <?php else: ?>
                        <?php foreach ($rowsTipos as $r): ?>
                        <tr class="tipo-row" role="button" tabindex="0"
                            data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                            onclick="abrirModalTipoEditar(this)">
                            <td class="ps-3" data-col="codigo"><code class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '-') ?></code></td>
                            <td class="fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                            <td class="text-center" data-col="total_unidades">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= (int)($r['total_unidades'] ?? 0) ?></span>
                            </td>
                            <td class="text-center pe-3" data-col="status">
                                <?php if (($r['status'] ?? true) == true || $r['status'] === 't' || $r['status'] === '1'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
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

    <!-- ── PESTAÑA UNIDADES ─────────────────────────────────────────── -->
    <div id="pane-unidades" class="<?= $tab !== 'unidades' ? 'd-none' : '' ?>">
        <div class="card-header bg-white py-2 px-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Buscador Unidades -->
                <div class="input-group input-group-sm" style="width:240px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscarUnidades" class="form-control border-start-0 ps-0 shadow-none border"
                           placeholder="Buscar unidad..." value="<?= htmlspecialchars($buscarUni) ?>" autocomplete="off">
                    <button type="button" id="btnLimpiarBuscarUni" class="btn border border-start-0 text-muted <?= $buscarUni === '' ? 'd-none' : '' ?>" title="Limpiar" onclick="limpiarBusquedaUnidades()"><i class="bi bi-x-lg"></i></button>
                </div>
                <!-- Filtro por tipo -->
                <select id="filtroTipoUni" class="form-select form-select-sm shadow-none" style="width:180px;" onchange="fetchSearchUnidades(1)">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tiposSelect as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $filtroTipo === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="btn-group btn-group-sm">
                    <?php
                    $columnasUni = ['codigo' => 'Código', 'nombre' => 'Nombre', 'abreviatura' => 'Abrev.', 'tipo_nombre' => 'Tipo', 'factor_base' => 'Factor', 'status' => 'Estado'];
                    echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasUni, $vistaConfig ?? [], $rutaModulo . '_unidades');
                    ?>
                    <a id="btnExportPdfUni" href="<?= $urlBase ?>/export-pdf?tab=unidades&b=<?= urlencode($buscarUni) ?>&sort=<?= urlencode($sortUni) ?>&dir=<?= urlencode($dirUni) ?><?= $filtroTipo ? '&f_tipo=' . $filtroTipo : '' ?>"
                       class="btn btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <a id="btnExportExcelUni" href="<?= $urlBase ?>/export-excel?tab=unidades&b=<?= urlencode($buscarUni) ?>&sort=<?= urlencode($sortUni) ?>&dir=<?= urlencode($dirUni) ?><?= $filtroTipo ? '&f_tipo=' . $filtroTipo : '' ?>"
                       class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="paginationInfoUni" class="text-muted small fw-medium"><?= $fromUni ?>-<?= $toUni ?>/<?= $totalUni ?></span>
                <div id="paginationUni" class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" <?= $pageUni <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaUnidadesAjax(<?= $pageUni - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary rounded-start-0" <?= $pageUni >= $totalPagesUni ? 'disabled' : '' ?> onclick="cambiarPaginaUnidadesAjax(<?= $pageUni + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="um-scroll w-100">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 sortable-header-uni" role="button" data-sort="codigo" data-col="codigo" style="width:100px">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header-uni" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header-uni" role="button" data-sort="abreviatura" data-col="abreviatura" style="width:90px">Abrev. <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="sortable-header-uni" role="button" data-sort="tipo_nombre" data-col="tipo_nombre" style="width:160px">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-end sortable-header-uni" role="button" data-sort="factor_base" data-col="factor_base" style="width:100px">Factor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                            <th class="text-center pe-3 sortable-header-uni" role="button" data-sort="status" data-col="status" style="width:100px">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        </tr>
                    </thead>
                    <tbody id="tbodyUnidades">
                        <?php if (empty($rowsUni)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-rulers fs-3 d-block mb-2"></i>No hay unidades de medida registradas.</td></tr>
                        <?php else: ?>
                        <?php foreach ($rowsUni as $r):
                            $esBaseVal = ($r['es_base'] ?? false);
                            $esBase    = ($esBaseVal === true || $esBaseVal === 't' || $esBaseVal === '1' || $esBaseVal === 1);
                        ?>
                        <tr class="unidad-row" role="button" tabindex="0"
                            data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'
                            onclick="abrirModalUnidadEditar(this)">
                            <td class="ps-3" data-col="codigo"><code class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '-') ?></code></td>
                            <td class="fw-medium" data-col="nombre">
                                <?= htmlspecialchars($r['nombre'] ?? '') ?>
                                <?php if ($esBase): ?>
                                <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 ms-1" title="Unidad base del tipo"><i class="bi bi-star-fill" style="font-size:0.6rem;"></i></span>
                                <?php endif; ?>
                            </td>
                            <td data-col="abreviatura"><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['abreviatura'] ?? '') ?></span></td>
                            <td class="text-muted small" data-col="tipo_nombre"><?= htmlspecialchars($r['tipo_nombre'] ?? '-') ?></td>
                            <td class="text-end pe-3" data-col="factor_base">
                                <?php if ($esBase): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Base</span>
                                <?php else: ?>
                                <?= htmlspecialchars((string)($r['factor_base'] ?? '1')) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3" data-col="status">
                                <?php if (($r['status'] ?? true) == true || $r['status'] === 't' || $r['status'] === '1'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Inactivo</span>
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

<?php
include 'modal_tipo.php';
include 'modal_unidad.php';
?>

<script>window.BASE_URL = '<?= $base ?>';</script>
<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlBase ?>';

    // ── Estado actual ─────────────────────────────────────────────────────
    let currentTab      = '<?= $tab ?>';
    let sortTipos       = '<?= $sortTipos ?>';
    let dirTipos        = '<?= strtolower($dirTipos) ?>';
    let pageTipos       = <?= $pageTipos ?>;
    let sortUni         = '<?= $sortUni ?>';
    let dirUni          = '<?= strtolower($dirUni) ?>';
    let pageUni         = <?= $pageUni ?>;

    // ── Switch de pestaña ─────────────────────────────────────────────────
    window.switchTab = function (tab) {
        currentTab = tab;
        document.getElementById('pane-tipos').classList.toggle('d-none', tab !== 'tipos');
        document.getElementById('pane-unidades').classList.toggle('d-none', tab !== 'unidades');
        document.getElementById('tab-tipos-btn').classList.toggle('active', tab === 'tipos');
        document.getElementById('tab-unidades-btn').classList.toggle('active', tab === 'unidades');
        if (document.getElementById('btnNuevoTipo'))   document.getElementById('btnNuevoTipo').classList.toggle('d-none', tab !== 'tipos');
        if (document.getElementById('btnNuevaUnidad')) document.getElementById('btnNuevaUnidad').classList.toggle('d-none', tab !== 'unidades');
    };

    // ── Búsqueda Tipos ────────────────────────────────────────────────────
    const inputTipos = document.getElementById('buscarTipos');
    let timerTipos;

    window.fetchSearchTipos = async function (page = 1) {
        const b = inputTipos ? inputTipos.value.trim() : '';
        const url = `${urlBase}/searchAjax?tab=tipos&b=${encodeURIComponent(b)}&page=${page}&sort=${sortTipos}&dir=${dirTipos}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (!data.ok) return;
            pageTipos = page;
            document.getElementById('tbodyTipos').innerHTML          = data.rows;
            document.getElementById('paginationTipos').innerHTML     = data.pagination;
            document.getElementById('paginationInfoTipos').textContent = data.info;
            document.getElementById('badgeTotalTipos').textContent   = data.total;
            document.getElementById('btnExportPdfTipos').href        = data.pdf_url;
            document.getElementById('btnExportExcelTipos').href      = data.excel_url;
            const limpiar = document.getElementById('btnLimpiarBuscarTipos');
            if (limpiar) limpiar.classList.toggle('d-none', b === '');
            actualizarIconosOrdenTipos();
        } catch (e) { console.error(e); }
    };

    window.cambiarPaginaTiposAjax = function (n) { fetchSearchTipos(n); };
    window.limpiarBusquedaTipos   = function ()  { if (inputTipos) { inputTipos.value = ''; fetchSearchTipos(1); } };

    if (inputTipos) {
        inputTipos.addEventListener('input', () => {
            clearTimeout(timerTipos);
            timerTipos = setTimeout(() => fetchSearchTipos(1), 350);
        });
    }

    document.querySelectorAll('.sortable-header-tipos').forEach(th => {
        th.addEventListener('click', () => {
            const f = th.dataset.sort;
            if (sortTipos === f) { dirTipos = dirTipos === 'asc' ? 'desc' : 'asc'; }
            else { sortTipos = f; dirTipos = 'asc'; }
            fetchSearchTipos(1);
        });
    });

    function actualizarIconosOrdenTipos() {
        document.querySelectorAll('.sortable-header-tipos').forEach(th => {
            const i = th.querySelector('i');
            if (!i) return;
            i.className = th.dataset.sort === sortTipos
                ? (dirTipos === 'asc' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1')
                : 'bi bi-arrow-down-up small text-muted ms-1';
        });
    }

    // ── Búsqueda Unidades ─────────────────────────────────────────────────
    const inputUni   = document.getElementById('buscarUnidades');
    const selectTipo = document.getElementById('filtroTipoUni');
    let timerUni;

    window.fetchSearchUnidades = async function (page = 1) {
        const b      = inputUni   ? inputUni.value.trim() : '';
        const ftipo  = selectTipo ? selectTipo.value       : '';
        const url = `${urlBase}/searchAjax?tab=unidades&b=${encodeURIComponent(b)}&page=${page}&sort=${sortUni}&dir=${dirUni}${ftipo ? '&f_tipo=' + ftipo : ''}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (!data.ok) return;
            pageUni = page;
            document.getElementById('tbodyUnidades').innerHTML        = data.rows;
            document.getElementById('paginationUni').innerHTML        = data.pagination;
            document.getElementById('paginationInfoUni').textContent  = data.info;
            document.getElementById('badgeTotalUnidades').textContent = data.total;
            document.getElementById('btnExportPdfUni').href           = data.pdf_url;
            document.getElementById('btnExportExcelUni').href         = data.excel_url;
            const limpiar = document.getElementById('btnLimpiarBuscarUni');
            if (limpiar) limpiar.classList.toggle('d-none', b === '');
            actualizarIconosOrdenUni();
        } catch (e) { console.error(e); }
    };

    window.cambiarPaginaUnidadesAjax = function (n) { fetchSearchUnidades(n); };
    window.limpiarBusquedaUnidades   = function ()  { if (inputUni) { inputUni.value = ''; fetchSearchUnidades(1); } };

    if (inputUni) {
        inputUni.addEventListener('input', () => {
            clearTimeout(timerUni);
            timerUni = setTimeout(() => fetchSearchUnidades(1), 350);
        });
    }

    document.querySelectorAll('.sortable-header-uni').forEach(th => {
        th.addEventListener('click', () => {
            const f = th.dataset.sort;
            if (sortUni === f) { dirUni = dirUni === 'asc' ? 'desc' : 'asc'; }
            else { sortUni = f; dirUni = 'asc'; }
            fetchSearchUnidades(1);
        });
    });

    function actualizarIconosOrdenUni() {
        document.querySelectorAll('.sortable-header-uni').forEach(th => {
            const i = th.querySelector('i');
            if (!i) return;
            i.className = th.dataset.sort === sortUni
                ? (dirUni === 'asc' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1')
                : 'bi bi-arrow-down-up small text-muted ms-1';
        });
    }

    // Refresco del selector de tipos en el modal de unidades tras crear un tipo nuevo
    window.addEventListener('tipoMedidaGuardado', function () {
        fetchSearchTipos(1);
        // Recargar opciones del select de tipo en modal unidad
        fetch(`${urlBase}/searchAjax?tab=tipos&b=&page=1&sort=nombre&dir=asc&per_page=999`)
            .then(r => r.json())
            .catch(() => {});
    });

})();
</script>
