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
$urlBaseCC = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = $total ?? 0;
$page       = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage ?? 20;
$ordenCol   = $ordenCol ?? 'nombre';
$ordenDir   = $ordenDir ?? 'asc';
$buscar     = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .cc-header { flex-shrink: 0; }
    .cc-scroll { max-height: calc(100dvh - 240px); overflow-y: auto; }
    .cc-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .cc-row { cursor: pointer; }
    .cc-row:hover { background-color: rgba(0, 0, 0, .04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="cc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if ($perm['crear']): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo</button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            // Buscador estándar (FiltrosModal): texto libre sobre las columnas del listado +
            // botón embudo que abre un modal con todos los filtros + chips de los activos.
            // Las claves (key) deben existir en los mapas de CentroCostoRepository::getListado().
            $opcFiltro = $opcionesFiltro ?? [];
            $opcIdNombre = fn(string $k) => array_map(fn($x) => ['v' => (string) $x['id'], 'l' => (string) $x['nombre']], $opcFiltro[$k] ?? []);
            $tCC = 'Centro de costo';
            // Filas de 12 columnas:
            //   Datos:    [Código 4][Nombre 4][Descripción 4]
            //   Estado:   [Estado 12]
            //   Registro: [Fecha de registro 6][Usuario 6]
            $filtrosCC = [
                ['tab' => $tCC, 'key' => 'codigo',      'label' => 'Código',      'icon' => 'bi-hash',      'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCC, 'key' => 'nombre',      'label' => 'Nombre',      'icon' => 'bi-diagram-3', 'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCC, 'key' => 'descripcion', 'label' => 'Descripción', 'icon' => 'bi-card-text', 'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCC, 'key' => 'estado',      'label' => 'Estado',      'icon' => 'bi-flag',      'type' => 'select', 'grupo' => 'Estado', 'col' => 12, 'options' => [
                    ['v' => 'activo',   'l' => 'Activo'],
                    ['v' => 'inactivo', 'l' => 'Inactivo'],
                ]],
                ['tab' => $tCC, 'key' => 'registro', 'label' => 'Fecha de registro',    'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Registro', 'col' => 6, 'atajos' => true],
                ['tab' => $tCC, 'key' => 'usuario',  'label' => 'Usuario que registró', 'icon' => 'bi-person-gear',    'type' => 'select',     'grupo' => 'Registro', 'col' => 6, 'options' => $opcIdNombre('usuarios')],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCC"></div>
            <input type="hidden" id="buscarCC" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCC',
                        hiddenInputId: 'buscarCC',
                        placeholder: 'Buscar en todas las columnas...',
                        titulo: 'Filtros de centros de costo',
                        inputWidth: 420,
                        extraId: 'fmExtraCC',   // columnas + PDF + Excel, pegados al final del grupo
                        fields: <?= json_encode($filtrosCC, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyCC',   // se atenúa mientras se busca
                        onApply: () => window.fetchSearch && window.fetchSearch(1),
                    }).init();
                });
            </script>

            <?php // FiltrosModal (extraId) mueve estos botones dentro del input-group del buscador; si el JS no corre, quedan aquí. ?>
            <div id="fmExtraCC" class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'codigo' => 'Código',
                    'nombre' => 'Nombre',
                    'descripcion' => 'Descripción',
                    'estado' => 'Estado'
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>

                <a id="btnExportPdf" href="<?= $urlBaseCC ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExportExcel" href="<?= $urlBaseCC ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= ($page <= 1) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= ($page >= $totalPages) ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="cc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="codigo" data-col="codigo">Código <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="nombre" data-col="nombre">Nombre <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="descripcion" data-col="descripcion">Descripción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3 sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyCC">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>No se encontraron centros de costo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr class="cc-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalEditar(this)">
                                <td class="ps-3" data-col="codigo"><code class="text-secondary"><?= htmlspecialchars($r['codigo'] ?? '-') ?></code></td>
                                <td class="fw-medium" data-col="nombre"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td class="text-truncate" style="max-width:300px" data-col="descripcion"><?= htmlspecialchars($r['descripcion'] ?? '-') ?></td>
                                <td class="text-center pe-3" data-col="estado">
                                    <span class="badge <?= ($r['estado'] === 'activo') ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25' ?>">
                                        <?= ucfirst($r['estado'] ?? '-') ?>
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

<!-- Modal Centro de Costos -->
<div class="modal fade" id="modalCC" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formCC" novalidate>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i><span id="tituloModal">Nuevo Centro de Costo</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="cc_id" value="">

                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsCC" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#tab-general" role="tab">General</a>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom mx-3 mb-3"></div>

                    <div class="tab-content px-3 pb-3">
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Código</label>
                                    <input type="text" class="form-control form-control-sm" name="codigo" id="cc_codigo" maxlength="20" placeholder="Opcional">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="cc_nombre" required maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Descripción</label>
                                    <textarea class="form-control form-control-sm" name="descripcion" id="cc_descripcion" rows="3" maxlength="500"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Estado</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" role="switch" name="estado" id="cc_estado" value="1" checked>
                                        <label class="form-check-label small" for="cc_estado">Activo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($perm['eliminar']): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminar" onclick="eliminarRegistro()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        const urlBase = '<?= $urlBaseCC ?>';
        const form = document.getElementById('formCC');
        let modalInst = null;
        let currentPage = <?= $page ?>;
        window.currentSort = '<?= $ordenCol ?>';
        window.currentDir  = '<?= $ordenDir ?>';
        // Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
        // OrdenListado en PHP. currentSort/currentDir quedan como el principal.
        window.currentSorts = <?= $ordenJson ?? '[]' ?>;
        let sorter = null;

        function getModal() {
            if (!modalInst) modalInst = new bootstrap.Modal(document.getElementById('modalCC'));
            return modalInst;
        }

        window.abrirModalCrear = function() {
            form.reset();
            document.getElementById('cc_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nuevo Centro de Costo';
            document.getElementById('modalAlert').classList.add('d-none');
            document.getElementById('btnEliminar')?.classList.add('d-none');
            document.getElementById('cc_estado').checked = true;
            const tabGen = document.getElementById('tab-general-btn');
            if (tabGen) (bootstrap.Tab.getInstance(tabGen) || new bootstrap.Tab(tabGen)).show();
            getModal().show();
            setTimeout(() => document.getElementById('cc_nombre').focus(), 400);
        };

        window.abrirModalEditar = function(row) {
            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('cc_id').value = data.id;
            document.getElementById('cc_codigo').value = data.codigo || '';
            document.getElementById('cc_nombre').value = data.nombre || '';
            document.getElementById('cc_descripcion').value = data.descripcion || '';
            document.getElementById('cc_estado').checked = data.estado === 'activo';
            document.getElementById('tituloModal').textContent = 'Editar Centro de Costo';
            document.getElementById('modalAlert').classList.add('d-none');
            document.getElementById('btnEliminar')?.classList.remove('d-none');
            const tabGen = document.getElementById('tab-general-btn');
            if (tabGen) (bootstrap.Tab.getInstance(tabGen) || new bootstrap.Tab(tabGen)).show();

            getModal().show();
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnGuardar');
            const alertEl = document.getElementById('modalAlert');
            const id = document.getElementById('cc_id').value;
            const url = id ? `${urlBase}/update` : `${urlBase}/store`;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';

            try {
                const fd = new FormData(form);
                const resp = await fetch(url, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    alertEl.textContent = json.msg;
                    alertEl.className = 'alert alert-success mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                    setTimeout(() => {
                        getModal().hide();
                        fetchSearch(currentPage);
                    }, 800);
                } else {
                    alertEl.textContent = json.error;
                    alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                }
            } catch (err) {
                alertEl.textContent = 'Error de conexión';
                alertEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            }
        });

        window.fetchSearch = async function(page = 1) {
            currentPage = page;
            const buscar = document.getElementById('buscarCC').value.trim();
            const orden  = window.CMG_ordenParam(window.currentSorts || []);
            const url = `${urlBase}/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&orden=${encodeURIComponent(orden)}`;
            // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras
            // carga, también al paginar u ordenar.
            const tbody = document.getElementById('tbodyCC');
            if (tbody) tbody.classList.add('fm-cargando-target');
            try {
                const resp = await fetch(url);
                const json = await resp.json();
                if (json.ok) {
                    tbody.innerHTML = json.rows;
                    document.getElementById('paginationContainer').innerHTML = json.pagination;
                    document.getElementById('paginationInfo').textContent = json.info;
                    document.getElementById('btnExportPdf').href = json.pdf_url;
                    document.getElementById('btnExportExcel').href = json.excel_url;
                    // Los iconos (incluida la prioridad 1/2/3 del orden múltiple) los
                    // repinta el motor global; aquí solo se le pide que se refresque.
                    if (sorter) sorter.refreshIcons();
                }
            } catch (e) { console.error(e); }
            finally { if (tbody) tbody.classList.remove('fm-cargando-target'); }
        };

        window.cambiarPaginaAjax = (p) => fetchSearch(p);

        // multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
        // (ASC -> DESC -> fuera del orden), con la prioridad numerada en cada encabezado.
        // reload:false porque fetchSearch repinta todo lo que depende del orden.
        sorter = window.CMG_initSort('centro-costos', (col, dir, sorts) => {
            window.currentSort  = col;
            window.currentDir   = dir;
            window.currentSorts = sorts;
            fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.cc-scroll', reload: false });

        window.eliminarRegistro = async function() {
            if (!confirm('¿Seguro que desea eliminar este centro de costo?')) return;
            const id = document.getElementById('cc_id').value;
            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    fetchSearch(currentPage);
                } else {
                    alert(json.error);
                }
            } catch (e) { alert('Error de conexión'); }
        };
    })();
</script>
