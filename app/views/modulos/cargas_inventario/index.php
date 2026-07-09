<?php
/** @var array $perm */
/** @var array $rows */
/** @var int $total */
/** @var bool $esAprobador */
/** @var string $rutaModulo */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
$rows    = $rows ?? [];
$total   = (int) ($total ?? 0);
$page    = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$vistaConfig = \App\Helpers\PreferenciasHelper::getPreferenciasVista($rutaModulo);

$estadoBadge = function (string $estado): string {
    return match ($estado) {
        'aprobada'  => '<span class="badge bg-success bg-opacity-10 text-success border border-success">Aprobada</span>',
        'rechazada' => '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Rechazada</span>',
        default     => '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Pendiente</span>',
    };
};
$tipoBadge = function (string $tipo): string {
    $c = match ($tipo) { 'entrada' => 'success', 'salida' => 'danger', default => 'secondary' };
    return '<span class="badge bg-' . $c . ' bg-opacity-10 text-' . $c . ' border border-' . $c . '">' . ucfirst($tipo) . '</span>';
};

echo \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 px-1">
    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam text-primary me-2"></i> <?= htmlspecialchars($titulo) ?></h5>
    <?php if (!empty($perm['crear'])): ?>
        <button type="button" class="btn btn-primary btn-sm px-3" onclick="CI_abrirImportar()">
            <i class="bi bi-upload me-1"></i> Importar carga
        </button>
    <?php endif; ?>
</div>

<div class="card cmg-table-card border-0 shadow-sm rounded-3 w-100">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <form class="d-flex align-items-center m-0" onsubmit="event.preventDefault(); CI_buscar(1);">
                <div class="input-group input-group-sm" style="width: 300px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="ci-buscar" class="form-control border-start-0 ps-0 shadow-none border" placeholder="Buscar por número, tipo, estado…" value="<?= htmlspecialchars($buscar ?? '') ?>" autocomplete="off">
                    <?php if (!empty($buscar)): ?>
                        <a href="<?= $urlBase ?>/index" class="btn border border-start-0 text-muted" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="btn-group btn-group-sm">
                <?php
                $columnasTabla = [
                    'numero' => 'N°', 'fecha' => 'Fecha', 'tipo' => 'Tipo', 'lineas' => 'Líneas',
                    'estado' => 'Estado', 'creado' => 'Creado por', 'aprobado' => 'Aprobado por',
                ];
                echo \App\Helpers\PreferenciasHelper::renderDropdownColumnas($columnasTabla, $vistaConfig, $rutaModulo);
                ?>
                <a href="<?= $urlBase ?>/export-pdf?b=<?= urlencode($buscar ?? '') ?>&sort=<?= urlencode($ordenCol ?? '') ?>&dir=<?= urlencode($ordenDir ?? '') ?>" target="_blank" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a href="<?= $urlBase ?>/export-excel?b=<?= urlencode($buscar ?? '') ?>&sort=<?= urlencode($ordenCol ?? '') ?>&dir=<?= urlencode($ordenDir ?? '') ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-medium"><?= $total ?> registros</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="CI_buscar(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="cargas-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-2" data-col="numero">N°</th>
                        <th data-col="fecha">Fecha</th>
                        <th data-col="tipo">Tipo</th>
                        <th class="text-center" data-col="lineas">Líneas</th>
                        <th class="text-center" data-col="estado">Estado</th>
                        <th data-col="creado">Creado por</th>
                        <th data-col="aprobado">Aprobado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-box-seam fs-2 d-block mb-2"></i> No hay cargas de inventario registradas.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr style="cursor:pointer;" onclick="CI_verDetalle(<?= (int) $r['id'] ?>)">
                            <td class="ps-3 fw-bold" data-col="numero">#<?= (int) $r['numero'] ?></td>
                            <td data-col="fecha"><?= $r['fecha'] ? date('d-m-Y', strtotime($r['fecha'])) : '-' ?></td>
                            <td data-col="tipo"><?= $tipoBadge($r['tipo_movimiento'] ?? '') ?></td>
                            <td class="text-center" data-col="lineas"><?= (int) $r['total_lineas'] ?></td>
                            <td class="text-center" data-col="estado">
                                <?= $estadoBadge($r['estado'] ?? 'pendiente') ?>
                                <?php if (($r['estado'] ?? '') === 'pendiente' && (empty($r['validada']) || $r['validada'] === 'f')): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Tiene líneas con error; no se puede aprobar"></i>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted" data-col="creado"><?= htmlspecialchars($r['creado_por_nombre'] ?? '-') ?></td>
                            <td class="small text-muted" data-col="aprobado"><?= htmlspecialchars($r['aprobado_por_nombre'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
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
            <div class="modal-body">
                <div id="ci-detalle-msg"></div>
                <div id="ci-detalle-cuerpo" class="small text-muted text-center py-4">Cargando…</div>
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
let CI_currentSort = '<?= $ordenCol ?? 'numero' ?>';
let CI_currentDir  = '<?= $ordenDir ?? 'DESC' ?>';
let CI_cargaActual = null;

function CI_buscar(p = 1) {
    const b = document.getElementById('ci-buscar').value;
    window.location.href = `${CI_URL}/index?b=${encodeURIComponent(b)}&page=${p}&sort=${CI_currentSort}&dir=${CI_currentDir}`;
}

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
    document.getElementById('ci-detalle-cuerpo').innerHTML = 'Cargando…';
    document.getElementById('ci-det-numero').textContent = '';
    ['ci-btn-aprobar', 'ci-btn-rechazar', 'ci-btn-eliminar'].forEach(b => { const el = document.getElementById(b); if (el) el.classList.add('d-none'); });
    new bootstrap.Modal(document.getElementById('ci-modal-detalle')).show();

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
</script>
