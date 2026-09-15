<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var bool $esAprobador */
/** @var string $rutaModulo */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var string $buscar */
/** @var string $ordenCol */
/** @var string $ordenDir */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');

$rows       = $rows ?? [];
$total      = (int) ($total ?? 0);
$page       = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$perPage    = (int) ($perPage ?? 20);
$buscar     = $buscar ?? '';
$ordenCol   = $ordenCol ?? 'numero';
$ordenDir   = $ordenDir ?? 'DESC';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = $total > 0 ? min($page * $perPage, $total) : 0;

$vistaConfig = \App\Helpers\PreferenciasHelper::getPreferenciasVista($rutaModulo);

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig);
?>
<style>
    .cargas-header {
        flex-shrink: 0;
    }

    .cargas-scroll {
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
    }

    .cargas-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    .carga-row {
        cursor: pointer;
    }

    .carga-row:hover {
        background-color: rgba(0, 0, 0, .04);
    }
</style>

<div class="cargas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i><?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CI_abrirImportar()">
            <i class="bi bi-upload me-1"></i> Importar carga
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- Buscador y Exportación -->
        <div class="d-flex align-items-center gap-2">
            <link rel="stylesheet" href="<?= rtrim(BASE_URL, '/') ?>/css/components/filtros_busqueda.css?v=<?= asset_ver('/css/components/filtros_busqueda.css') ?>">
            <script src="<?= rtrim(BASE_URL, '/') ?>/js/components/filtros_busqueda.js?v=<?= asset_ver('/js/components/filtros_busqueda.js') ?>"></script>
            <div id="fbBuscadorCI" style="width: 480px;"></div>
            <input type="hidden" id="ci-buscar" value="<?= htmlspecialchars($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosBusqueda) return;
                    new FiltrosBusqueda({
                        containerId: 'fbBuscadorCI',
                        hiddenInputId: 'ci-buscar',
                        fields: [
                            { key: 'numero',      label: 'N° de carga',  icon: 'bi-hash',             type: 'number_range' },
                            { key: 'fecha',       label: 'Fecha',        icon: 'bi-calendar-event',   type: 'date_range' },
                            { key: 'observacion', label: 'Observación / Ref', icon: 'bi-chat-left-text', type: 'text' },
                            { key: 'creado',      label: 'Creado por',   icon: 'bi-person',           type: 'text' },
                            { key: 'aprobado',    label: 'Aprobado por', icon: 'bi-person-check',     type: 'text' },
                            { key: 'lineas',      label: 'Líneas',       icon: 'bi-list-ol',          type: 'number_range' },
                            { key: 'tipo',        label: 'Tipo',         icon: 'bi-arrow-left-right', type: 'select', options: [
                                { v: 'entrada', l: 'Entrada' },
                                { v: 'salida',  l: 'Salida' },
                                { v: 'ajuste',  l: 'Ajuste' },
                            ]},
                            { key: 'estado',      label: 'Estado',       icon: 'bi-flag',             type: 'select', options: [
                                { v: 'pendiente', l: 'Pendiente' },
                                { v: 'aprobada',  l: 'Aprobada' },
                                { v: 'rechazada', l: 'Rechazada' },
                            ]},
                        ],
                        quickFilters: [
                            { id: 'qf_pendiente', label: 'Pendientes', mk: () => ({ key: 'estado', op: '=', value: 'pendiente', display: 'Pendiente' }) },
                            { id: 'qf_aprobada',  label: 'Aprobadas',  mk: () => ({ key: 'estado', op: '=', value: 'aprobada',  display: 'Aprobada' }) },
                            { id: 'qf_rechazada', label: 'Rechazadas', mk: () => ({ key: 'estado', op: '=', value: 'rechazada', display: 'Rechazada' }) },
                            { id: 'qf_entrada',   label: 'Entradas',   mk: () => ({ key: 'tipo',   op: '=', value: 'entrada',   display: 'Entrada' }) },
                            { id: 'qf_salida',    label: 'Salidas',    mk: () => ({ key: 'tipo',   op: '=', value: 'salida',    display: 'Salida' }) },
                        ],
                        onApply: () => window.CI_buscar && window.CI_buscar(1),
                    }).init();
                });
            </script>

            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero'      => 'N°',
                    'fecha'       => 'Fecha',
                    'tipo'        => 'Tipo',
                    'lineas'      => 'Líneas',
                    'estado'      => 'Estado',
                    'creado'      => 'Creado por',
                    'aprobado'    => 'Aprobado por',
                    'observacion' => 'Observación',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo);
                ?>

                <a id="ci-btn-pdf" href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                   target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a id="ci-btn-excel" href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam ?? '') ?>"
                   class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>

        <!-- Paginación -->
        <div class="d-flex align-items-center gap-3">
            <span id="ci-pagination-info" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="ci-pagination" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card-body p-0">
        <div class="cargas-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2 sortable-header" role="button" data-sort="numero" data-col="numero">N° <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="fecha" data-col="fecha">Fecha <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="tipo" data-col="tipo">Tipo <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="lineas" data-col="lineas">Líneas <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center sortable-header" role="button" data-sort="estado" data-col="estado">Estado <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="creado" data-col="creado">Creado por <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sortable-header" role="button" data-sort="aprobado" data-col="aprobado">Aprobado por <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="pe-3 sortable-header" role="button" data-sort="observacion" data-col="observacion">Observación <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                    </tr>
                </thead>
                <tbody id="ci-tbody">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>No hay cargas de inventario registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php include __DIR__ . '/_fila.php'; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Importar carga -->
<div class="modal fade" id="ci-modal-importar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i>Importar carga de inventario</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ci-form-importar">
                <div class="modal-body">
                    <div id="ci-importar-msg"></div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Tipo de movimiento</label>
                            <select name="tipo_movimiento" class="form-select form-select-sm">
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Observación</label>
                            <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Archivo Excel</label>
                                <a href="<?= $urlBase ?>/descargarPlantilla" class="btn btn-outline-success btn-sm py-0 px-2" title="Descargar plantilla de ejemplo">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Descargar plantilla
                                </a>
                            </div>
                            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" required>
                            <div class="form-text" style="font-size:0.72rem;">
                                Formato <strong>Excel (.xlsx)</strong>. Columnas: <code>codigo_producto, bodega, cantidad, costo_unitario, numero_lote, fecha_caducidad, nup, observacion</code>.
                                La plantilla incluye hojas <strong>Productos</strong> y <strong>Bodegas</strong> con los valores válidos de la empresa. El movimiento se aplica al inventario solo al aprobarse (si la empresa exige aprobación).
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle -->
<div class="modal fade" id="ci-modal-detalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Carga de inventario <span id="ci-det-numero"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative">
                <div id="ci-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando información de la carga...</div>
                </div>
                <div id="ci-detalle-msg"></div>
                <div id="ci-detalle-cuerpo" class="small text-muted text-center py-4"></div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" id="ci-btn-eliminar" class="btn btn-outline-danger btn-sm d-none" onclick="CI_eliminar()"><i class="bi bi-trash me-1"></i>Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!empty($esAprobador)): ?>
                        <button type="button" id="ci-btn-rechazar" class="btn btn-outline-danger btn-sm d-none" onclick="CI_rechazar()"><i class="bi bi-x-circle me-1"></i>Rechazar</button>
                        <button type="button" id="ci-btn-aprobar" class="btn btn-success btn-sm d-none" onclick="CI_aprobar()"><i class="bi bi-check-circle me-1"></i>Aprobar</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CI_URL = '<?= $urlBase ?>';
const CI_ES_APROBADOR  = <?= !empty($esAprobador) ? 'true' : 'false' ?>;
const CI_ES_SUPERADMIN = <?= !empty($esSuperAdmin) ? 'true' : 'false' ?>;
const CI_ID_USUARIO    = <?= (int) ($idUsuarioActual ?? 0) ?>;
const CI_APROBADORES   = <?= json_encode(array_values($aprobadoresNombres ?? []), JSON_UNESCAPED_UNICODE) ?>;
let CI_currentSort = '<?= $ordenCol ?>';
let CI_currentDir  = '<?= $ordenDir ?>';
// Orden múltiple (Shift+clic): lista completa de criterios, en el formato que lee
// OrdenListado en PHP. CI_currentSort/CI_currentDir quedan como el principal.
let CI_currentSorts = <?= $ordenJson ?? '[]' ?>;
let CI_currentPage = <?= $page ?>;
let CI_cargaActual = null;
let CI_sorter = null;

/**
 * Refresca el listado por AJAX (búsqueda, orden y paginación) sin recargar la
 * página: repinta filas, paginación, contador y los enlaces de PDF/Excel.
 */
window.CI_buscar = async function (p = 1) {
    const b = (document.getElementById('ci-buscar')?.value || '').trim();
    const orden = window.CMG_ordenParam(CI_currentSorts || []);
    const uri = `${CI_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&orden=${encodeURIComponent(orden)}`;
    try {
        const resp = await fetch(uri);
        const data = await resp.json();
        if (!data.ok) return;
        CI_currentPage = p;
        document.getElementById('ci-tbody').innerHTML = data.rows;
        document.getElementById('ci-pagination').innerHTML = data.pagination;
        document.getElementById('ci-pagination-info').textContent = data.info;
        document.getElementById('ci-btn-pdf').href = data.pdf_url;
        document.getElementById('ci-btn-excel').href = data.excel_url;

        // Los íconos (incluida la prioridad 1/2/3 del orden múltiple) los repinta
        // el motor global; aquí solo se le pide que se refresque.
        if (CI_sorter) CI_sorter.refreshIcons();
    } catch (e) {
        console.error('Error en búsqueda de cargas de inventario:', e);
    }
};

function CI_abrirImportar() {
    document.getElementById('ci-importar-msg').innerHTML = '';
    document.getElementById('ci-form-importar').reset();
    new bootstrap.Modal(document.getElementById('ci-modal-importar')).show();
}

document.getElementById('ci-form-importar').addEventListener('submit', async function (e) {
    e.preventDefault();
    const msg = document.getElementById('ci-importar-msg');
    msg.innerHTML = '';
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
        const res = await fetch(`${CI_URL}/importarAjax`, { method: 'POST', body: new FormData(this) });
        const json = await res.json();
        if (json.ok) {
            const d = json.data;
            let extra = '';
            if (!d.validada) {
                extra = `<div class="mt-2 small"><strong>Líneas con error (no se podrá aprobar hasta corregir):</strong><ul class="mb-0">${(d.errores || []).map(x => '<li>' + x + '</li>').join('')}</ul></div>`;
            }
            const estadoTxt = d.estado === 'aprobada' ? 'aplicada al inventario' : 'creada como pendiente';
            msg.innerHTML = `<div class="alert alert-${d.validada ? 'success' : 'warning'} py-2 px-3 small mb-0">Carga #${d.numero} ${estadoTxt}.${extra}</div>`;
            setTimeout(() => window.location.reload(), d.validada ? 1200 : 3000);
        } else {
            msg.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">${json.mensaje || 'Error al importar'}</div>`;
            btn.disabled = false;
        }
    } catch (err) {
        msg.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">Error de conexión con el servidor.</div>`;
        btn.disabled = false;
    }
});

async function CI_verDetalle(id) {
    CI_cargaActual = null;
    document.getElementById('ci-detalle-msg').innerHTML = '';
    document.getElementById('ci-detalle-cuerpo').innerHTML = '';
    document.getElementById('ci-det-numero').textContent = '';
    ['ci-btn-aprobar', 'ci-btn-rechazar', 'ci-btn-eliminar'].forEach(b => { const el = document.getElementById(b); if (el) el.classList.add('d-none'); });
    new bootstrap.Modal(document.getElementById('ci-modal-detalle')).show();
    document.getElementById('ci-modal-loader')?.classList.remove('d-none');

    try {
        const res = await fetch(`${CI_URL}/getDetalleAjax?id=${id}`);
        const json = await res.json();
        if (!json.ok) { document.getElementById('ci-detalle-cuerpo').innerHTML = `<div class="text-danger">${json.mensaje}</div>`; return; }
        const c = json.data;
        CI_cargaActual = c;
        document.getElementById('ci-det-numero').textContent = '#' + c.numero;

        const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const validada = c.validada === true || c.validada === 't' || c.validada === '1';
        let filas = (c.detalle || []).map(d => {
            const ok = d.linea_valida === true || d.linea_valida === 't' || d.linea_valida === '1';
            return `<tr>
                <td>${esc(d.producto_nombre || d.cod_producto_raw || '—')}</td>
                <td>${esc(d.bodega_nombre || d.cod_bodega_raw || '—')}</td>
                <td class="text-end">${parseFloat(d.cantidad || 0)}</td>
                <td class="text-end">$ ${parseFloat(d.costo_unitario || 0).toFixed(2)}</td>
                <td class="text-center">${ok ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger" title="' + esc(d.error_linea) + '"></i>'}</td>
            </tr>`;
        }).join('');

        const estadoTxt = { pendiente: 'Pendiente', aprobada: 'Aprobada', rechazada: 'Rechazada' }[c.estado] || c.estado;
        document.getElementById('ci-detalle-cuerpo').innerHTML = `
            <div class="row g-2 small mb-3">
                <div class="col-md-3"><div class="text-muted" style="font-size:.65rem;">Fecha</div><div class="fw-bold">${c.fecha ? c.fecha.split('-').reverse().join('-') : '-'}</div></div>
                <div class="col-md-3"><div class="text-muted" style="font-size:.65rem;">Tipo</div><div class="fw-bold text-capitalize">${esc(c.tipo_movimiento)}</div></div>
                <div class="col-md-3"><div class="text-muted" style="font-size:.65rem;">Estado</div><div class="fw-bold">${estadoTxt}</div></div>
                <div class="col-md-3"><div class="text-muted" style="font-size:.65rem;">Comprobada</div><div class="fw-bold ${validada ? 'text-success' : 'text-danger'}">${validada ? 'Sí' : 'No'}</div></div>
                ${c.observacion ? `<div class="col-12"><div class="text-muted" style="font-size:.65rem;">Observación</div><div>${esc(c.observacion)}</div></div>` : ''}
                ${c.motivo_rechazo ? `<div class="col-12"><div class="text-muted" style="font-size:.65rem;">Motivo rechazo</div><div class="text-danger">${esc(c.motivo_rechazo)}</div></div>` : ''}
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:.78rem;">
                    <thead class="table-light"><tr><th>Producto</th><th>Bodega</th><th class="text-end">Cantidad</th><th class="text-end">Costo</th><th class="text-center">OK</th></tr></thead>
                    <tbody>${filas || '<tr><td colspan="5" class="text-center text-muted">Sin líneas</td></tr>'}</tbody>
                </table>
            </div>`;

        // Botones según estado + segregación de funciones.
        if (c.estado === 'pendiente') {
            const esCreador = String(c.created_by) === String(CI_ID_USUARIO);
            const puedeAprobar = CI_ES_APROBADOR && (CI_ES_SUPERADMIN || !esCreador);
            const btnAp = document.getElementById('ci-btn-aprobar');
            const btnRe = document.getElementById('ci-btn-rechazar');
            if (btnAp) {
                if (puedeAprobar) { btnAp.classList.remove('d-none'); btnAp.disabled = !validada; btnAp.title = validada ? '' : 'La carga tiene líneas con error'; }
                else btnAp.classList.add('d-none');
            }
            if (btnRe) {
                if (puedeAprobar) btnRe.classList.remove('d-none');
                else btnRe.classList.add('d-none');
            }
            if (!puedeAprobar) {
                const quien = CI_APROBADORES.length ? CI_APROBADORES.join(', ') : 'un usuario autorizado (configúrelos en Empresa → Inventario)';
                document.getElementById('ci-detalle-msg').innerHTML = `<div class="alert alert-info py-2 px-3 small mb-2"><i class="bi bi-hourglass-split me-1"></i>Pendiente de aprobación por: <strong>${quien}</strong>.</div>`;
            }
        }
        if (c.estado !== 'aprobada') {
            const btnEl = document.getElementById('ci-btn-eliminar');
            if (btnEl) btnEl.classList.remove('d-none');
        }
    } catch (err) {
        document.getElementById('ci-detalle-cuerpo').innerHTML = '<div class="text-danger">Error de conexión.</div>';
    } finally {
        document.getElementById('ci-modal-loader')?.classList.add('d-none');
    }
}

async function CI_accion(url, body, confirmMsg) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    const msg = document.getElementById('ci-detalle-msg');
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        const json = await res.json();
        if (json.ok) {
            msg.innerHTML = `<div class="alert alert-success py-2 px-3 small mb-2">${json.mensaje || 'Listo'}</div>`;
            setTimeout(() => window.location.reload(), 1000);
        } else {
            msg.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-2">${json.mensaje}</div>`;
        }
    } catch (err) {
        msg.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-2">Error de conexión.</div>`;
    }
}

function CI_aprobar() {
    if (!CI_cargaActual) return;
    CI_accion(`${CI_URL}/aprobarAjax`, `id=${CI_cargaActual.id}`, '¿Aprobar esta carga? Se aplicará al inventario.');
}
function CI_rechazar() {
    if (!CI_cargaActual) return;
    const motivo = prompt('Motivo del rechazo:');
    if (!motivo) return;
    CI_accion(`${CI_URL}/rechazarAjax`, `id=${CI_cargaActual.id}&motivo=${encodeURIComponent(motivo)}`);
}
function CI_eliminar() {
    if (!CI_cargaActual) return;
    CI_accion(`${CI_URL}/eliminarAjax`, `id=${CI_cargaActual.id}`, '¿Eliminar esta carga?');
}

// multi: clic normal ordena por una columna; Shift+clic encadena hasta 3
// (ASC → DESC → fuera del orden), con la prioridad numerada en cada encabezado.
// reload:false porque CI_buscar repinta todo lo que depende del orden.
CI_sorter = window.CMG_initSort('<?= $rutaModulo ?>', (col, dir, sorts) => {
    CI_currentSort  = col;
    CI_currentDir   = dir;
    CI_currentSorts = sorts;
    CI_buscar(1);
}, { sorts: CI_currentSorts, multi: true, container: '.cargas-scroll', reload: false });
</script>
