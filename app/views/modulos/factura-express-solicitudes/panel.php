<?php
/** @var array   $perm */
/** @var string  $rutaModulo */
/** @var int     $idPlantilla */
/** @var ?string $nombrePlantilla */
/** @var array   $tarifasIva */
/** @var int     $decimalesPrec */

$base    = BASE_URL;
$urlBase = rtrim($base, '/') . '/modulos/factura-express-solicitudes';
$urlConfig = rtrim($base, '/') . '/modulos/factura-express-config';

$idPlantilla     = (int) ($idPlantilla ?? 0);
$nombrePlantilla = $nombrePlantilla ?? null;
$tarifasIva      = $tarifasIva ?? [];
$decimalesPrec   = $decimalesPrec ?? 2;

$estadosOpc = [
    'pendiente' => ['label' => 'Pendientes', 'color' => 'warning'],
    'aprobada'  => ['label' => 'Aprobadas',  'color' => 'info'],
    'rechazada' => ['label' => 'Rechazadas', 'color' => 'danger'],
    'facturada' => ['label' => 'Facturadas', 'color' => 'success'],
    ''          => ['label' => 'Todas',      'color' => 'secondary'],
];
?>
<style>
    .fexp-wrap { max-width: 640px; margin: 0 auto; }
    .fexp-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
    .fexp-chip { border-radius: 999px; flex: 0 0 auto; }
    .fexp-card { cursor: pointer; transition: background .15s; }
    .fexp-card:active { background: rgba(0,0,0,.05); }
    .fexp-item { border: 1px solid #e9ecef; border-radius: .5rem; padding: .5rem; margin-bottom: .5rem; }
    .fexp-item .form-control, .fexp-item .form-select { font-size: .9rem; }
    .fexp-desc { border:0; border-bottom:1px solid #dee2e6; border-radius:0; padding-left:0; }
    .fexp-desc:focus { box-shadow:none; border-bottom-color:#0d6efd; }
    #fexpDropProductos { z-index: 1085 !important; }
    #modalFexpDetalle .modal-footer { position: sticky; bottom: 0; background: #fff; }
</style>

<div class="fexp-wrap">

    <!-- Encabezado -->
    <div class="d-flex align-items-center gap-2 mb-2">
        <?php if (empty($fexpEmbedded)): ?>
            <a href="<?= $urlConfig ?>" class="btn btn-light btn-sm border" title="Volver a configuración"><i class="bi bi-arrow-left"></i></a>
        <?php endif; ?>
        <div class="flex-grow-1">
            <h6 class="mb-0 fw-bold"><i class="bi bi-bell text-warning me-1"></i>Solicitudes</h6>
            <?php if ($nombrePlantilla !== null): ?>
                <small class="text-muted" id="fexpSubtitulo"><i class="bi bi-qr-code me-1"></i><?= htmlspecialchars($nombrePlantilla) ?></small>
            <?php endif; ?>
        </div>
        <span id="fexpTotalBadge" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">0</span>
    </div>

    <?php if ($idPlantilla > 0): ?>
        <div class="form-check form-switch small mb-2">
            <input class="form-check-input" type="checkbox" id="fexpTodasPlantillas">
            <label class="form-check-label text-muted" for="fexpTodasPlantillas">Ver solicitudes de todas las plantillas</label>
        </div>
    <?php endif; ?>

    <!-- Filtros de estado -->
    <div class="fexp-chips mb-2 pb-1">
        <?php foreach ($estadosOpc as $key => $opc): ?>
            <button type="button"
                class="btn btn-sm fexp-chip fexp-estado-btn <?= $key === 'pendiente' ? 'btn-' . $opc['color'] : 'btn-outline-' . $opc['color'] ?>"
                data-estado="<?= $key ?>" data-color="<?= $opc['color'] ?>"
                onclick="fexpFiltrar('<?= $key ?>')">
                <?= $opc['label'] ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Buscador -->
    <div class="input-group input-group-sm mb-3">
        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
        <input type="text" id="fexpBuscar" class="form-control" placeholder="Buscar cliente, cédula o correo..." autocomplete="off">
    </div>

    <!-- Tarjetas -->
    <div id="fexpCards"></div>

    <div id="fexpVacio" class="text-center text-muted py-5 d-none">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        <span id="fexpVacioMsg">No hay solicitudes.</span>
    </div>

    <div class="text-center my-3">
        <button type="button" id="fexpCargarMas" class="btn btn-outline-secondary btn-sm d-none" onclick="fexpCargarMas()">
            <i class="bi bi-arrow-down-circle me-1"></i>Cargar más
        </button>
        <div id="fexpSpinner" class="text-muted small d-none"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</div>
    </div>
</div>

<!-- Detalle a pantalla completa -->
<div class="modal fade" id="modalFexpDetalle" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text text-primary me-1"></i>Solicitud</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Datos del cliente -->
                <div class="p-2 border rounded-3 bg-light mb-3 small">
                    <div class="fw-bold" id="fexpDetNombre"></div>
                    <div class="text-muted"><span id="fexpDetTipo"></span>: <span id="fexpDetIdentificacion"></span></div>
                    <div class="text-muted"><i class="bi bi-envelope me-1"></i><span id="fexpDetCorreo"></span></div>
                    <div class="text-muted"><i class="bi bi-telephone me-1"></i><span id="fexpDetTelefono"></span></div>
                </div>

                <!-- Estado info (si ya procesada) -->
                <div id="fexpDetEstadoInfo" class="d-none alert py-2 px-3 small mb-3"></div>

                <!-- Ítems editables -->
                <div id="fexpItemsBox">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-medium small">Ítems</span>
                        <span class="fw-bold">Total: <span id="fexpTotal" class="text-primary">$0.00</span></span>
                    </div>
                    <div id="fexpItems"></div>
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="fexpAgregarItem()">
                        <i class="bi bi-plus-circle me-1"></i>Agregar ítem
                    </button>
                </div>

                <!-- Motivo de rechazo -->
                <div id="fexpNotaRechazar" class="d-none mb-2">
                    <label class="form-label small fw-medium">Motivo del rechazo <span class="text-danger">*</span></label>
                    <textarea id="fexpInputNota" class="form-control form-control-sm" rows="2" placeholder="Ingrese el motivo..."></textarea>
                </div>

                <input type="hidden" id="fexpDetId">
            </div>
            <div class="modal-footer p-2 d-flex gap-2" id="fexpFooter"></div>
        </div>
    </div>
</div>

<div id="fexpDropProductos" class="list-group shadow position-fixed d-none" style="z-index:1090; min-width:280px; max-height:240px; overflow-y:auto; background:#fff;"></div>

<script>
(function () {
    'use strict';
    const urlBase        = '<?= $urlBase ?>';
    const TARIFAS_IVA    = <?= json_encode($tarifasIva, JSON_UNESCAPED_UNICODE) ?>;
    const DEC_PRECIO     = <?= (int) $decimalesPrec ?>;
    const PUEDE_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const ID_PLANTILLA   = <?= $idPlantilla ?>;

    const estadoColor = { pendiente:'warning', aprobada:'info', rechazada:'danger', facturada:'success' };
    let estado    = 'pendiente';
    let buscar    = '';
    let page      = 1;
    let pages     = 1;
    let soloPlantilla = ID_PLANTILLA > 0;
    let cargando  = false;
    let timer;
    const dropProd = document.getElementById('fexpDropProductos');

    // ─── Listado ────────────────────────────────────────────────────────────
    function paramPlantilla() {
        return (soloPlantilla && ID_PLANTILLA > 0) ? `&id_plantilla=${ID_PLANTILLA}` : '';
    }

    async function fexpCargar(reset = true) {
        if (cargando) return;
        cargando = true;
        if (reset) { page = 1; document.getElementById('fexpCards').innerHTML = ''; }
        document.getElementById('fexpSpinner').classList.remove('d-none');
        document.getElementById('fexpCargarMas').classList.add('d-none');

        const uri = `${urlBase}/panelAjax?b=${encodeURIComponent(buscar)}&estado=${encodeURIComponent(estado)}&page=${page}${paramPlantilla()}`;
        try {
            const r = await fetch(uri, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('sesion');
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'error');

            pages = d.pages;
            document.getElementById('fexpTotalBadge').textContent = d.total;
            const cont = document.getElementById('fexpCards');
            (d.rows || []).forEach(row => cont.appendChild(fexpCrearTarjeta(row)));

            const vacio = (d.total === 0);
            document.getElementById('fexpVacio').classList.toggle('d-none', !vacio);
            document.getElementById('fexpCargarMas').classList.toggle('d-none', page >= pages || vacio);
        } catch (e) {
            const cont = document.getElementById('fexpCards');
            if (reset) cont.innerHTML = `<div class="alert alert-warning small">No se pudo cargar. Si tu sesión expiró, recarga la página.</div>`;
        } finally {
            cargando = false;
            document.getElementById('fexpSpinner').classList.add('d-none');
        }
    }

    window.fexpCargarMas = function() { page++; fexpCargar(false); };

    window.fexpFiltrar = function(e) {
        estado = e;
        document.querySelectorAll('.fexp-estado-btn').forEach(b => {
            const c = b.dataset.color, act = b.dataset.estado === e;
            b.classList.toggle('btn-' + c, act);
            b.classList.toggle('btn-outline-' + c, !act);
        });
        fexpCargar(true);
    };

    document.getElementById('fexpBuscar').addEventListener('input', (ev) => {
        clearTimeout(timer);
        buscar = ev.target.value.trim();
        timer = setTimeout(() => fexpCargar(true), 400);
    });

    const chkTodas = document.getElementById('fexpTodasPlantillas');
    if (chkTodas) {
        chkTodas.addEventListener('change', () => {
            soloPlantilla = !chkTodas.checked;
            const sub = document.getElementById('fexpSubtitulo');
            if (sub) sub.classList.toggle('d-none', chkTodas.checked);
            fexpCargar(true);
        });
    }

    function fexpCrearTarjeta(r) {
        const cls   = estadoColor[r.estado] || 'secondary';
        const fecha = r.created_at ? r.created_at.substring(8,10)+'-'+r.created_at.substring(5,7)+'-'+r.created_at.substring(0,4)+' '+r.created_at.substring(11,16) : '';
        const monto = parseFloat(r.monto_total || 0).toFixed(2);
        const div = document.createElement('div');
        div.className = 'card fexp-card border-0 shadow-sm rounded-3 mb-2';
        div.innerHTML = `
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="pe-2">
                        <div class="fw-bold">${escHtml(r.nombre_cliente || '')}</div>
                        <div class="small text-muted">${escHtml(r.identificacion || '')}</div>
                        <div class="small text-muted"><i class="bi bi-clock me-1"></i>${fecha}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">$${monto}</div>
                        <span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 mt-1">${cap(r.estado || '')}</span>
                    </div>
                </div>
            </div>`;
        div.addEventListener('click', () => fexpAbrir(r));
        return div;
    }

    // ─── Detalle ──────────────────────────────────────────────────────────────
    window.fexpAbrir = function(r) {
        document.getElementById('fexpDetId').value             = r.id;
        document.getElementById('fexpDetNombre').textContent   = r.nombre_cliente || '';
        document.getElementById('fexpDetTipo').textContent     = r.tipo_identificacion || 'ID';
        document.getElementById('fexpDetIdentificacion').textContent = r.identificacion || '';
        document.getElementById('fexpDetCorreo').textContent   = r.correo_cliente || '—';
        document.getElementById('fexpDetTelefono').textContent = r.telefono_cliente || '—';
        document.getElementById('fexpInputNota').value = '';
        document.getElementById('fexpNotaRechazar').classList.add('d-none');

        const items    = JSON.parse(r.items_json || '[]');
        const editable = (r.estado === 'pendiente' && PUEDE_ACTUALIZAR);

        const itemsBox = document.getElementById('fexpItemsBox');
        document.getElementById('fexpItems').innerHTML = '';

        if (editable) {
            itemsBox.querySelector('button').classList.remove('d-none');
            if (items.length) items.forEach(it => fexpAgregarItem(it));
            else fexpAgregarItem();
        } else {
            // Solo lectura
            itemsBox.querySelector('button').classList.add('d-none');
            let html = '';
            items.forEach(it => {
                const sub = (parseFloat(it.cantidad||0) * parseFloat(it.precio_unitario||0)).toFixed(2);
                html += `<div class="fexp-item small"><div class="fw-medium">${escHtml(it.descripcion||'')}</div>
                    <div class="d-flex justify-content-between text-muted">
                        <span>${parseFloat(it.cantidad||0)} × $${parseFloat(it.precio_unitario||0).toFixed(2)} · IVA ${parseFloat(it.porcentaje_iva||0).toFixed(0)}%</span>
                        <span class="fw-bold">$${sub}</span></div></div>`;
            });
            document.getElementById('fexpItems').innerHTML = html || '<div class="text-muted small">Sin ítems</div>';
            document.getElementById('fexpTotal').textContent = '$' + parseFloat(r.monto_total||0).toFixed(2);
        }

        // Info de estado si ya fue procesada
        const info = document.getElementById('fexpDetEstadoInfo');
        if (r.estado && r.estado !== 'pendiente') {
            const cls = estadoColor[r.estado] || 'secondary';
            info.className = `alert alert-${cls} py-2 px-3 small mb-3`;
            info.innerHTML = `<b>${cap(r.estado)}</b>` + (r.nota_aprobacion ? ` — ${escHtml(r.nota_aprobacion)}` : '');
            info.classList.remove('d-none');
        } else {
            info.classList.add('d-none');
        }

        // Botones del footer
        const footer = document.getElementById('fexpFooter');
        footer.innerHTML = '';
        if (editable) {
            footer.innerHTML = `
                <button type="button" class="btn btn-outline-danger" id="fexpBtnRechazar" onclick="fexpMostrarRechazar()"><i class="bi bi-x-circle me-1"></i>Rechazar</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="fexpBtnCancelar" onclick="fexpCancelarRechazar()"><i class="bi bi-arrow-left me-1"></i>Cancelar</button>
                <button type="button" class="btn btn-danger d-none" id="fexpBtnGuardarRech" onclick="fexpRechazar()"><i class="bi bi-save me-1"></i>Guardar</button>
                <button type="button" class="btn btn-success flex-grow-1" id="fexpBtnAprobar" onclick="fexpAprobar()"><i class="bi bi-check-circle me-1"></i>Aprobar y facturar</button>`;
        } else {
            footer.innerHTML = `<button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>`;
        }

        if (editable) fexpCalcTotal();
        new bootstrap.Modal(document.getElementById('modalFexpDetalle')).show();
    };

    // ─── Ítems editables ───────────────────────────────────────────────────────
    function ivaOptions(pct) {
        return TARIFAS_IVA.map(t => {
            const v = parseFloat(t.porcentaje_iva);
            const sel = (pct !== null && Math.abs(v - pct) < 0.001) ? 'selected' : '';
            return `<option value="${v}" ${sel}>${escHtml(t.tarifa ?? (v + '%'))}</option>`;
        }).join('');
    }

    window.fexpAgregarItem = function(it) {
        it = it || {};
        const pct = (it.porcentaje_iva !== undefined && it.porcentaje_iva !== null) ? parseFloat(it.porcentaje_iva) : null;
        const wrap = document.createElement('div');
        wrap.className = 'fexp-item';
        wrap.innerHTML = `
            <div class="position-relative mb-2">
                <input type="text" class="form-control form-control-sm fexp-desc fexp-i-desc" placeholder="Buscar producto o escribir..." value="${escAttr(it.descripcion ?? '')}">
                <input type="hidden" class="fexp-i-idprod" value="${parseInt(it.id_producto) || ''}">
            </div>
            <div class="row g-1 align-items-center">
                <div class="col-3"><input type="number" class="form-control form-control-sm text-center fexp-i-cant" inputmode="decimal" value="${parseFloat(it.cantidad ?? 1)}" step="any" min="0" placeholder="Cant"></div>
                <div class="col-4"><input type="number" class="form-control form-control-sm text-end fexp-i-precio" inputmode="decimal" value="${parseFloat(it.precio_unitario ?? 0).toFixed(DEC_PRECIO)}" step="any" min="0" placeholder="Precio"></div>
                <div class="col-3"><select class="form-select form-select-sm fexp-i-iva">${ivaOptions(pct)}</select></div>
                <div class="col-2 text-end"><button type="button" class="btn btn-link btn-sm text-danger p-0 fexp-i-del"><i class="bi bi-trash3"></i></button></div>
            </div>
            <div class="text-end small text-muted mt-1">Subtotal: $<span class="fexp-i-sub">0.00</span></div>`;
        document.getElementById('fexpItems').appendChild(wrap);

        const desc = wrap.querySelector('.fexp-i-desc');
        desc.addEventListener('input', debounce(() => fexpBuscarProd(desc, wrap), 350));
        desc.addEventListener('blur', () => setTimeout(() => dropProd.classList.add('d-none'), 200));
        wrap.querySelector('.fexp-i-cant').addEventListener('input', () => fexpCalcFila(wrap));
        wrap.querySelector('.fexp-i-precio').addEventListener('input', () => fexpCalcFila(wrap));
        wrap.querySelector('.fexp-i-precio').addEventListener('blur', (e) => e.target.value = parseFloat(e.target.value||0).toFixed(DEC_PRECIO));
        wrap.querySelector('.fexp-i-iva').addEventListener('change', () => fexpCalcFila(wrap));
        wrap.querySelector('.fexp-i-del').addEventListener('click', () => { wrap.remove(); fexpCalcTotal(); });

        fexpCalcFila(wrap);
    };

    async function fexpBuscarProd(input, wrap) {
        const q = input.value.trim();
        if (q.length < 2) { dropProd.classList.add('d-none'); return; }
        const rect = input.getBoundingClientRect();
        dropProd.style.top = `${rect.bottom + 2}px`;
        dropProd.style.left = `${rect.left}px`;
        dropProd.style.width = `${rect.width}px`;
        dropProd.classList.remove('d-none');
        dropProd.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
        try {
            const r = await fetch(`${urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            dropProd.innerHTML = '';
            if (d.ok && d.data && d.data.length) {
                d.data.forEach(p => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action small py-2';
                    b.innerHTML = `<div class="d-flex justify-content-between"><span class="fw-medium">${escHtml(p.nombre||'')}</span><span class="text-primary">$${parseFloat(p.precio_base||0).toFixed(2)}</span></div>`;
                    b.onmousedown = (ev) => { ev.preventDefault(); fexpSelProd(p, wrap); dropProd.classList.add('d-none'); };
                    dropProd.appendChild(b);
                });
            } else {
                dropProd.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias (puede escribir libre)</div>';
            }
        } catch (e) { dropProd.classList.add('d-none'); }
    }

    function fexpSelProd(p, wrap) {
        wrap.querySelector('.fexp-i-desc').value   = p.nombre || '';
        wrap.querySelector('.fexp-i-idprod').value = p.id || '';
        wrap.querySelector('.fexp-i-precio').value = parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO);
        const pct = (p.porcentaje_iva_final !== undefined && p.porcentaje_iva_final !== null) ? parseFloat(p.porcentaje_iva_final) : null;
        if (pct !== null) {
            const sel = wrap.querySelector('.fexp-i-iva');
            const opt = Array.from(sel.options).find(o => Math.abs(parseFloat(o.value) - pct) < 0.001);
            if (opt) sel.value = opt.value;
        }
        fexpCalcFila(wrap);
    }

    function fexpCalcFila(wrap) {
        const c = parseFloat(wrap.querySelector('.fexp-i-cant').value) || 0;
        const p = parseFloat(wrap.querySelector('.fexp-i-precio').value) || 0;
        wrap.querySelector('.fexp-i-sub').textContent = (Math.round(c * p * 100) / 100).toFixed(2);
        fexpCalcTotal();
    }

    function fexpCalcTotal() {
        let total = 0;
        document.querySelectorAll('#fexpItems .fexp-item').forEach(w => {
            const c = parseFloat(w.querySelector('.fexp-i-cant').value) || 0;
            const p = parseFloat(w.querySelector('.fexp-i-precio').value) || 0;
            const iva = parseFloat(w.querySelector('.fexp-i-iva').value) || 0;
            const base = Math.round(c * p * 100) / 100;
            total += base + Math.round(base * (iva/100) * 100) / 100;
        });
        document.getElementById('fexpTotal').textContent = '$' + total.toFixed(2);
    }

    function fexpRecolectar() {
        const items = [];
        document.querySelectorAll('#fexpItems .fexp-item').forEach(w => {
            const desc = w.querySelector('.fexp-i-desc').value.trim();
            const c    = parseFloat(w.querySelector('.fexp-i-cant').value) || 0;
            if (!desc || c <= 0) return;
            items.push({
                id_item: 0,
                id_producto: parseInt(w.querySelector('.fexp-i-idprod').value) || null,
                descripcion: desc,
                cantidad: c,
                precio_unitario: parseFloat(w.querySelector('.fexp-i-precio').value) || 0,
                porcentaje_iva: parseFloat(w.querySelector('.fexp-i-iva').value) || 0,
            });
        });
        return items;
    }

    // ─── Aprobar / Rechazar ─────────────────────────────────────────────────────
    window.fexpAprobar = async function() {
        const id  = document.getElementById('fexpDetId').value;
        const btn = document.getElementById('fexpBtnAprobar');
        const items = fexpRecolectar();
        if (!items.length) { Swal.fire({ icon:'warning', title:'Sin ítems', text:'Debe haber al menos un ítem con descripción y cantidad mayor a cero.' }); return; }

        const ok = await Swal.fire({ icon:'question', title:'¿Aprobar y facturar?', text:'Se generará la factura con los ítems mostrados.', showCancelButton:true, confirmButtonText:'Sí, aprobar', cancelButtonText:'Cancelar', confirmButtonColor:'#198754' });
        if (!ok.isConfirmed) return;

        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('items_json', JSON.stringify(items));
            const res = await fetch(`${urlBase}/aprobar`, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest' } });
            const d = await res.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Aprobada', text:d.mensaje, timer:1400, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexpDetalle'))?.hide();
                fexpCargar(true);
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.mensaje });
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aprobar y facturar';
            }
        } catch (e) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aprobar y facturar';
        }
    };

    window.fexpMostrarRechazar = function() {
        document.getElementById('fexpNotaRechazar').classList.remove('d-none');
        document.getElementById('fexpBtnRechazar').classList.add('d-none');
        document.getElementById('fexpBtnCancelar').classList.remove('d-none');
        document.getElementById('fexpBtnGuardarRech').classList.remove('d-none');
        document.getElementById('fexpBtnAprobar').disabled = true;
        setTimeout(() => document.getElementById('fexpInputNota').focus(), 50);
    };

    window.fexpCancelarRechazar = function() {
        document.getElementById('fexpNotaRechazar').classList.add('d-none');
        document.getElementById('fexpInputNota').value = '';
        document.getElementById('fexpBtnRechazar').classList.remove('d-none');
        document.getElementById('fexpBtnCancelar').classList.add('d-none');
        document.getElementById('fexpBtnGuardarRech').classList.add('d-none');
        document.getElementById('fexpBtnAprobar').disabled = false;
    };

    window.fexpRechazar = async function() {
        const id   = document.getElementById('fexpDetId').value;
        const nota = document.getElementById('fexpInputNota').value.trim();
        const btn  = document.getElementById('fexpBtnGuardarRech');
        if (!nota) { Swal.fire({ icon:'warning', title:'Motivo requerido', text:'Debe ingresar el motivo del rechazo.' }); document.getElementById('fexpInputNota').focus(); return; }

        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('nota', nota);
            const res = await fetch(`${urlBase}/rechazar`, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest' } });
            const d = await res.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Rechazada', text:d.mensaje, timer:1400, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexpDetalle'))?.hide();
                fexpCargar(true);
            } else {
                Swal.fire({ icon:'error', title:'Error', text:d.mensaje });
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar';
            }
        } catch (e) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar';
        }
    };

    // ─── Utilidades ─────────────────────────────────────────────────────────────
    function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
    function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
    function escAttr(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    // Inicial
    fexpCargar(true);
})();
</script>
