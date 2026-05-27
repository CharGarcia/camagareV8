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
/** @var array $vistaConfig */
/** @var array $establecimientos */
/** @var array $puntosEmision */

$base      = BASE_URL;
$urlBaseOc = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows       ?? [];
$total      = $total      ?? 0;
$page       = $page       ?? 1;
$totalPages = $totalPages ?? 1;
$perPage    = $perPage    ?? 20;
$ordenCol   = $ordenCol   ?? 'created_at';
$ordenDir   = $ordenDir   ?? 'desc';
$buscar     = $buscar     ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;
?>
<style>
    .oc-header { flex-shrink: 0; }
    .oc-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
    .oc-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
    .oc-row { cursor: pointer; }
    .oc-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<div class="oc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cart3"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="ocAbrirCrear()">
            <i class="bi bi-plus-lg"></i> Nueva
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= time() ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= time() ?>"></script>
            <form id="formOC" method="POST" action="<?= $urlBaseOc ?>" class="d-flex align-items-center m-0">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
                <input type="hidden" name="dir"  value="<?= htmlspecialchars($ordenDir) ?>">
                <input type="hidden" name="b" id="ocInputBuscar" value="<?= htmlspecialchars($buscar) ?>">
                <div id="fbBuscadorOC" style="width: 480px;"></div>
            </form>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    let debounceSubmit;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorOC',
                        hiddenInputId: 'ocInputBuscar',
                        placeholder: 'Buscar...',
                        fields: [
                            { key: 'proveedor', label: 'Proveedor',    icon: 'bi-building',        type: 'text' },
                            { key: 'ruc',       label: 'RUC',          icon: 'bi-card-text',       type: 'text' },
                            { key: 'numero',    label: 'Nº orden',     icon: 'bi-hash',            type: 'text' },
                            { key: 'fecha',     label: 'Fecha',        icon: 'bi-calendar-event',  type: 'date_range' },
                            { key: 'monto',     label: 'Monto total',  icon: 'bi-currency-dollar', type: 'number_range' },
                            { key: 'estado',    label: 'Estado',       icon: 'bi-flag',            type: 'select', options: [
                                { v: 'borrador', l: 'Borrador' },
                                { v: 'aprobado', l: 'Aprobado' },
                                { v: 'anulado',  l: 'Anulado' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_borrador', label: 'Borrador',    mk: () => ({ key: 'estado', op: '=', value: 'borrador', display: 'Borrador' }) },
                            { id: 'qf_aprobado', label: 'Aprobadas',   mk: () => ({ key: 'estado', op: '=', value: 'aprobado', display: 'Aprobado' }) },
                            { id: 'qf_anulado',  label: 'Anuladas',    mk: () => ({ key: 'estado', op: '=', value: 'anulado',  display: 'Anulado' }) },
                            { id: 'qf_mes',      label: 'Este mes',    mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
                            { id: 'qf_anio',     label: 'Este año',    mk: () => FiltrosBusqueda.helpers.esteAnio('fecha') },
                        ],
                        onApply: () => {
                            clearTimeout(debounceSubmit);
                            debounceSubmit = setTimeout(() => document.getElementById('formOC').submit(), 500);
                        },
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero_orden'              => 'N° Orden',
                    'fecha_orden'               => 'Fecha Orden',
                    'proveedor_nombre'          => 'Proveedor',
                    'proveedor_identificacion'  => 'Identificación',
                    'fecha_recepcion'           => 'Fecha Recepción',
                    'observaciones'             => 'Observaciones',
                    'estado'                    => 'Estado',
                ];
                ?>
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnExportPdf"
                   href="<?= $urlBaseOc ?>/export-pdf?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-danger" title="Exportar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="btnExportExcel"
                   href="<?= $urlBaseOc ?>/export-excel?b=<?= urlencode($buscar) ?>&sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>"
                   class="btn btn-outline-success" title="Exportar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span id="paginationInfo" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginationContainer" class="btn-group btn-group-sm">
                <?php if ($page <= 1): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-left"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="ocCambiarPagina(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <?php endif; ?>
                <?php if ($page >= $totalPages): ?>
                    <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="ocCambiarPagina(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="oc-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" role="button" data-sort="numero_orden" data-col="numero_orden">N° Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_orden" data-col="fecha_orden">Fecha Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_nombre" data-col="proveedor_nombre">Proveedor <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="proveedor_identificacion" data-col="proveedor_identificacion">Identificación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha_recepcion" data-col="fecha_recepcion">Fecha Recepción <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="observaciones" data-col="observaciones">Observaciones <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header text-center" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="tbodyOrdenesCompra">
                    <?php
                    $estadoBadgeMap = [
                        'borrador' => '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Borrador</span>',
                        'aprobado' => '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Aprobado</span>',
                        'anulado'  => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>',
                        'recibido' => '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Recibido</span>',
                    ];
                    if (empty($rows)):
                    ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-cart3 fs-3 d-block mb-2"></i>No se encontraron órdenes de compra.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $rowData     = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                            $estadoBadge = $estadoBadgeMap[$r['estado'] ?? 'borrador'] ?? '<span class="badge bg-secondary">-</span>';
                        ?>
                        <tr class="oc-row" role="button" tabindex="0" data-row='<?= $rowData ?>' onclick="ocAbrirEditar(this)">
                            <td class="ps-3" data-col="numero_orden"><code class="text-secondary"><?= htmlspecialchars($r['numero_orden'] ?? '') ?></code></td>
                            <td data-col="fecha_orden"><?= htmlspecialchars($r['fecha_orden'] ?? '-') ?></td>
                            <td class="fw-medium text-truncate" data-col="proveedor_nombre" style="max-width:250px"><?= htmlspecialchars($r['proveedor_nombre'] ?? '-') ?></td>
                            <td data-col="proveedor_identificacion"><small><?= htmlspecialchars($r['proveedor_identificacion'] ?? '-') ?></small></td>
                            <td data-col="fecha_recepcion"><?= htmlspecialchars($r['fecha_recepcion'] ?? '-') ?></td>
                            <td class="text-truncate" data-col="observaciones" style="max-width:200px"><small><?= htmlspecialchars($r['observaciones'] ?? '-') ?></small></td>
                            <td class="text-center pe-3" data-col="estado"><?= $estadoBadge ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_orden.php'; ?>
<?php include __DIR__ . '/../proveedores/modal_proveedor.php'; ?>
<script defer src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/proveedores_modal.js?v=<?= time() ?>"></script>

<script>
const OC_URL_BASE  = '<?= $urlBaseOc ?>';
const OC_PERM      = <?= json_encode($perm) ?>;
const OC_ESTABLECIMIENTOS = <?= json_encode($establecimientos ?? []) ?>;
const OC_PUNTOS_EMISION   = <?= json_encode($puntosEmision ?? []) ?>;

let ocCurrentSort  = '<?= $ordenCol ?>';
let ocCurrentDir   = '<?= $ordenDir ?>';
let ocCurrentPage  = <?= $page ?>;
let ocCurrentBuscar= '';

// ── Auto-fill proveedor al crear uno nuevo desde el modal ────────────────────
document.addEventListener('proveedorGuardado', function(e) {
    const d = e.detail;
    if (!d || !d.id) return;
    const nombre = d.nombre || d.data?.razon_social || '';
    document.getElementById('oc_proveedor_id').value    = d.id;
    document.getElementById('oc_proveedor_texto').value = nombre;
    document.getElementById('oc_lista_proveedores').classList.add('d-none');
});

// ── Búsqueda ─────────────────────────────────────────────────────────────────
const ocInputBuscar = document.getElementById('ocInputBuscar');
let ocDebounceTimer = null;
if (ocInputBuscar) {
    ocInputBuscar.addEventListener('input', function() {
        clearTimeout(ocDebounceTimer);
        ocDebounceTimer = setTimeout(() => { ocCurrentBuscar = this.value; ocBuscar(1); }, 400);
    });
}

// ── Ordenamiento ──────────────────────────────────────────────────────────────
document.querySelectorAll('.sortable-header').forEach(th => {
    th.addEventListener('click', function() {
        const field = this.dataset.sort;
        if (!field) return;
        if (ocCurrentSort === field) {
            ocCurrentDir = ocCurrentDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            ocCurrentSort = field;
            ocCurrentDir  = 'ASC';
        }
        ocBuscar(1);
    });
});

// ── Paginación ────────────────────────────────────────────────────────────────
window.ocCambiarPagina = function(page) { ocBuscar(page); };

// ── AJAX búsqueda ─────────────────────────────────────────────────────────────
async function ocBuscar(page = 1) {
    ocCurrentPage = page;
    const params = new URLSearchParams({
        b:    ocCurrentBuscar,
        page: page,
        sort: ocCurrentSort,
        dir:  ocCurrentDir,
    });
    try {
        const resp = await fetch(`${OC_URL_BASE}/searchAjax?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (!data.ok) return;
        document.getElementById('tbodyOrdenesCompra').innerHTML = data.rows;
        document.getElementById('paginationContainer').innerHTML = data.pagination;
        document.getElementById('paginationInfo').textContent     = data.info;
        document.getElementById('btnExportPdf').href   = data.pdf_url;
        document.getElementById('btnExportExcel').href = data.excel_url;
    } catch (e) { console.error(e); }
}

// ── Poblar select serie (un único select combinado cod_est-cod_punto) ──────────
function ocPopularPuntosSelect() {
    const sel = document.getElementById('oc_id_punto_emision');
    sel.innerHTML = '';
    OC_PUNTOS_EMISION.forEach(p => {
        const opt = document.createElement('option');
        opt.value          = p.id;
        opt.dataset.est    = p.id_establecimiento;
        opt.dataset.codEst = p.cod_establecimiento;
        opt.dataset.codPunto = p.codigo_punto;
        opt.textContent    = `${p.cod_establecimiento}-${p.codigo_punto}`;
        sel.appendChild(opt);
    });
}

// ── Sync serie: setea establecimiento y obtiene secuencial (solo en nuevo) ────
async function ocSyncSerie(idPunto) {
    if (!idPunto) return;
    const sel = document.getElementById('oc_id_punto_emision');
    const opt = sel.options[sel.selectedIndex];
    if (opt) {
        document.getElementById('oc_id_establecimiento').value = opt.dataset.est || '';
    }
    if (document.getElementById('oc_id').value) return; // edición: no sobreescribir secuencial
    try {
        const resp = await fetch(`${OC_URL_BASE}/getSiguienteSecuencial?id_punto_emision=${idPunto}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (data.ok) {
            document.getElementById('oc_secuencial').value = data.secuencial;
        }
    } catch(e) {}
}

// ── Parsear fecha a YYYY-MM-DD independientemente del formato recibido ────────
function ocParseDate(str) {
    if (!str) return '';
    if (/^\d{4}-\d{2}-\d{2}/.test(str)) return str.substring(0, 10);
    if (/^\d{2}-\d{2}-\d{4}/.test(str)) {
        const p = str.substring(0, 10).split('-');
        return `${p[2]}-${p[1]}-${p[0]}`;
    }
    return '';
}

// ── Abrir modal crear ─────────────────────────────────────────────────────────
window.ocAbrirCrear = function() {
    document.getElementById('oc_id').value = '';
    document.getElementById('oc_titulo_modal').textContent = 'Nueva Orden de Compra';
    document.getElementById('oc_fecha_orden').value = new Date().toISOString().split('T')[0];
    document.getElementById('oc_fecha_recepcion').value = '';
    document.getElementById('oc_observaciones').value   = '';
    document.getElementById('oc_estado').value           = 'borrador';
    document.getElementById('oc_proveedor_id').value    = '';
    document.getElementById('oc_proveedor_texto').value = '';

    ocPopularPuntosSelect();
    const sel = document.getElementById('oc_id_punto_emision');
    if (sel.options.length > 0) {
        sel.selectedIndex = 0;
        ocSyncSerie(sel.value);
    } else {
        document.getElementById('oc_id_establecimiento').value = '';
        document.getElementById('oc_secuencial').value = '';
    }

    ocLimpiarDetalle();
    ocAgregarFilaDetalle();
    const btnElim = document.getElementById('oc_btn_eliminar');
    if (btnElim) btnElim.classList.add('d-none');

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalOrdenCompra')).show();
};

// ── Abrir modal editar ────────────────────────────────────────────────────────
window.ocAbrirEditar = function(tr) {
    const d = JSON.parse(tr.dataset.row);

    document.getElementById('oc_id').value = d.id ?? '';
    document.getElementById('oc_titulo_modal').textContent = `Editar Orden de Compra #${d.numero_orden || ''}`;
    document.getElementById('oc_fecha_orden').value     = ocParseDate(d.fecha_orden ?? '');
    document.getElementById('oc_fecha_recepcion').value = ocParseDate(d.fecha_recepcion ?? '');
    document.getElementById('oc_observaciones').value   = d.observaciones ?? '';
    document.getElementById('oc_estado').value          = d.estado ?? 'borrador';
    document.getElementById('oc_proveedor_id').value    = d.id_proveedor ?? '';
    document.getElementById('oc_proveedor_texto').value = d.proveedor_nombre ?? '';

    ocPopularPuntosSelect();
    const sel = document.getElementById('oc_id_punto_emision');
    if (d.id_punto_emision) {
        sel.value = d.id_punto_emision;
        const opt = sel.options[sel.selectedIndex];
        if (opt) document.getElementById('oc_id_establecimiento').value = opt.dataset.est || '';
    }
    document.getElementById('oc_secuencial').value = d.secuencial ?? '';

    ocLimpiarDetalle();
    fetch(`${OC_URL_BASE}/getDetalle?id=${d.id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => { if (res.ok && res.detalle) res.detalle.forEach(item => ocAgregarFilaDetalle(item)); })
    .catch(() => {});

    const btnElim = document.getElementById('oc_btn_eliminar');
    if (btnElim) btnElim.classList.remove('d-none');

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalOrdenCompra')).show();
};

document.getElementById('oc_id_punto_emision')?.addEventListener('change', function() {
    ocSyncSerie(this.value);
});

// ── Guardar ───────────────────────────────────────────────────────────────────
document.getElementById('formOrdenCompra')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const id    = document.getElementById('oc_id').value;
    const items = ocObtenerItems();

    if (!document.getElementById('oc_proveedor_id').value) {
        Swal.fire({ icon: 'warning', title: 'Proveedor requerido', text: 'Debe seleccionar un proveedor.', confirmButtonColor: '#0d6efd' });
        return;
    }
    if (!document.getElementById('oc_id_establecimiento').value) {
        Swal.fire({ icon: 'warning', title: 'Establecimiento requerido', text: 'Debe seleccionar un establecimiento.', confirmButtonColor: '#0d6efd' });
        return;
    }
    if (!document.getElementById('oc_id_punto_emision').value) {
        Swal.fire({ icon: 'warning', title: 'Punto de emisión requerido', text: 'Debe seleccionar un punto de emisión.', confirmButtonColor: '#0d6efd' });
        return;
    }
    if (items.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Sin ítems', text: 'Debe agregar al menos un ítem al detalle.', confirmButtonColor: '#0d6efd' });
        return;
    }

    const url    = id ? `${OC_URL_BASE}/update` : `${OC_URL_BASE}/store`;
    const fd     = new FormData(this);
    fd.set('id_proveedor', document.getElementById('oc_proveedor_id').value);
    fd.set('items', JSON.stringify(items));
    if (id) fd.set('id', id);

    const btn = document.getElementById('oc_btn_guardar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await resp.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalOrdenCompra'))?.hide();
            Swal.fire({ icon: 'success', title: 'Guardado', text: data.msg, timer: 1800, showConfirmButton: false });
            ocBuscar(ocCurrentPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error ?? 'Error al guardar.', confirmButtonColor: '#0d6efd' });
        }
    } catch(err) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.', confirmButtonColor: '#0d6efd' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle"></i> Guardar';
    }
});

// ── Eliminar ──────────────────────────────────────────────────────────────────
window.ocEliminar = async function() {
    const id = document.getElementById('oc_id').value;
    if (!id) return;

    const confirm = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar orden?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    });
    if (!confirm.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('id_eliminar', id);
        const resp = await fetch(`${OC_URL_BASE}/delete`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await resp.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalOrdenCompra'))?.hide();
            Swal.fire({ icon: 'success', title: 'Eliminado', text: data.msg, timer: 1800, showConfirmButton: false });
            ocBuscar(ocCurrentPage);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.error, confirmButtonColor: '#0d6efd' });
        }
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación.', confirmButtonColor: '#0d6efd' });
    }
};

// ── Búsqueda de proveedor ─────────────────────────────────────────────────────
let ocProvDebounce = null;
document.getElementById('oc_proveedor_texto')?.addEventListener('input', function() {
    clearTimeout(ocProvDebounce);
    const q = this.value.trim();
    if (!q) {
        document.getElementById('oc_proveedor_id').value = '';
        document.getElementById('oc_lista_proveedores').classList.add('d-none');
        return;
    }
    ocProvDebounce = setTimeout(() => ocBuscarProveedores(q), 350);
});

async function ocBuscarProveedores(q) {
    try {
        const resp = await fetch(`${OC_URL_BASE}/getProveedoresAjax?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        const lista = document.getElementById('oc_lista_proveedores');
        lista.innerHTML = '';
        if (data.ok && data.data.length > 0) {
            data.data.forEach(p => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action py-1 px-2 small';
                a.textContent = p.razon_social + ' - ' + p.identificacion;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('oc_proveedor_id').value    = p.id;
                    document.getElementById('oc_proveedor_texto').value = p.razon_social;
                    lista.classList.add('d-none');
                });
                lista.appendChild(a);
            });
            lista.classList.remove('d-none');
        } else {
            lista.classList.add('d-none');
        }
    } catch(e) {}
}

document.addEventListener('click', function(e) {
    const lista = document.getElementById('oc_lista_proveedores');
    if (lista && !lista.contains(e.target) && e.target.id !== 'oc_proveedor_texto') {
        lista.classList.add('d-none');
    }
});
</script>
