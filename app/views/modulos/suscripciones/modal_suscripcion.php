<?php
$urlBase         = BASE_URL . '/modulos/suscripciones';
$urlBaseClientes = BASE_URL . '/modulos/clientes';
$urlBaseProductos = BASE_URL . '/modulos/productos';
$permSusc        = $perm ?? [];
?>

<style>
    /* ── Modal Suscripción ─────────────────────────────────────────────────────── */
    .modal-susc .modal-header      { padding: 0.75rem 1rem; background: #f8f9fa; }
    .modal-susc .modal-body        { padding: 0 !important; }
    .modal-susc label              { font-size: 0.83rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    .modal-susc .nav-tabs .nav-link { font-size: 0.875rem; }

    /* Tabla de detalle */
    .modal-susc .table-detalle th  { font-size: 0.70rem !important; padding: 4px 6px !important; background: #f8f9fa; text-transform: uppercase; }
    .modal-susc .table-detalle td  { padding: 0 !important; vertical-align: middle; }
    .modal-susc .input-detalle     { height: 30px !important; font-size: 0.82rem !important; padding: 2px 6px !important;
                                     border: none; background: transparent; width: 100%; }
    .modal-susc .input-detalle:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    .row-susc-det:hover            { background-color: rgba(13,110,253,.03); }
    .row-susc-det .btn-remove-det  { opacity: 0; transition: opacity .2s; color: #dc3545; }
    .row-susc-det:hover .btn-remove-det { opacity: 1; }

    /* Buscador clientes */
    .dropdown-susc-cli             { z-index: 1090 !important; position: absolute; width: 100%;
                                     max-height: 240px; overflow-y: auto; top: 36px; left: 0; }
    /* Totales */
    .susc-total-label { font-size: 0.78rem; color: #6c757d; font-weight: 600; }
    .susc-total-value { font-size: 0.95rem; font-weight: 700; }
    .susc-total-grande { font-size: 1.2rem; font-weight: 700; color: #0d6efd; }
</style>

<div class="modal fade modal-susc" id="modalSusc" tabindex="-1" aria-labelledby="modalSuscLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formSusc" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold fs-6" id="modalSuscLabel">
                        <i class="bi bi-arrow-repeat text-primary me-2"></i>
                        <span id="tituloModalSusc">Nueva Suscripción</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="suscAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" id="susc_id" name="id" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" data-bs-toggle="tab" href="#pane-susc-servicios" role="tab">
                                    <i class="bi bi-receipt me-1"></i>Cliente y Servicios
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-susc-cobro" role="tab">
                                    <i class="bi bi-credit-card me-1"></i>Cobro
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom bg-light"></div>

                    <div class="tab-content">

                        <!-- ══ PESTAÑA 1: Cliente + Servicios ══════════════════════════════════ -->
                        <div class="tab-pane fade show active" id="pane-susc-servicios" role="tabpanel">

                            <!-- Cabecera: cliente + fechas + periodicidad -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">

                                    <!-- Buscador de cliente -->
                                    <div class="col-12">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-12 position-relative">
                                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                        <input type="text" class="form-control border-0 px-1"
                                                               id="susc_search_cliente"
                                                               placeholder="Buscar cliente por RUC o razón social..."
                                                               autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="susc_id_cliente">
                                                    </div>
                                                    <div id="susc_dropdown_clientes"
                                                         class="list-group shadow dropdown-susc-cli d-none"
                                                         style="z-index:1090"></div>
                                                </div>
                                                <div class="col-12 d-none px-2" id="susc_info_cliente" style="font-size:.72rem; color:#6c757d;">
                                                    <span class="fw-bold text-dark border-end pe-2 me-1" id="susc_lbl_cli_ruc"></span>
                                                    <i class="bi bi-envelope me-1"></i><span id="susc_lbl_cli_email"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fechas y periodicidad -->
                                    <div class="col-md-3">
                                        <label>Fecha Inicio *</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_inicio" id="susc_fecha_inicio" required onchange="suscRecalcularProximoCobro()">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Fecha Fin <small class="text-muted fw-normal">(opcional)</small></label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_fin" id="susc_fecha_fin">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Periodicidad *</label>
                                        <select class="form-select form-select-sm" name="id_periodicidad" id="susc_id_periodicidad" required onchange="suscRecalcularProximoCobro()">
                                            <option value="">- Seleccione -</option>
                                            <?php foreach ($periodicidades ?? [] as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-meses="<?= $p['meses'] ?>">
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Próximo Cobro</label>
                                        <input type="date" class="form-control form-control-sm" name="proximo_cobro" id="susc_proximo_cobro" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de productos/servicios -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height:320px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3" style="width:35%;">Descripción</th>
                                                    <th style="width:10%;" class="text-center">Cant.</th>
                                                    <th style="width:15%;" class="text-end">Precio Unit.</th>
                                                    <th style="width:10%;" class="text-center">IVA %</th>
                                                    <th style="width:15%;" class="text-end pe-3">Subtotal</th>
                                                    <th style="width:40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="susc_tbody_detalle">
                                                <tr id="susc_row_vacia">
                                                    <td colspan="6" class="text-center text-muted py-3 small">
                                                        <i class="bi bi-box-seam me-1"></i>Agregue productos o servicios
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pie de tabla: agregar línea + buscador productos -->
                                    <div class="border-top p-2 bg-light d-flex align-items-center gap-2 position-relative">
                                        <div class="input-group input-group-sm" style="max-width:380px;">
                                            <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-box-seam"></i></span>
                                            <input type="text" class="form-control border-start-0 ps-0 shadow-none"
                                                   id="susc_search_producto"
                                                   placeholder="Buscar producto o servicio..."
                                                   autocomplete="off">
                                        </div>
                                        <div id="susc_dropdown_productos"
                                             class="list-group shadow position-absolute d-none"
                                             style="z-index:1090; bottom:48px; left:10px; width:380px; max-height:220px; overflow-y:auto;"></div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="suscAgregarFilaVacia()">
                                            <i class="bi bi-plus-lg me-1"></i>Agregar línea
                                        </button>
                                    </div>
                                </div>

                                <!-- Totales -->
                                <div class="d-flex justify-content-end mt-3">
                                    <div class="border rounded-3 bg-white shadow-sm px-4 py-3" style="min-width:260px;">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="susc-total-label">Subtotal sin IVA</span>
                                            <span class="susc-total-value" id="susc_total_subtotal">$0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="susc-total-label">IVA</span>
                                            <span class="susc-total-value" id="susc_total_iva">$0.00</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="susc-total-label">TOTAL</span>
                                            <span class="susc-total-grande" id="susc_total_total">$0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /pane-susc-servicios -->

                        <!-- ══ PESTAÑA 2: Cobro ════════════════════════════════════════════════ -->
                        <div class="tab-pane fade" id="pane-susc-cobro" role="tabpanel">
                            <div class="p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Forma de Cobro *</label>
                                        <select class="form-select form-select-sm" name="forma_cobro" id="susc_forma_cobro" required onchange="suscOnFormaCobro()">
                                            <option value="credito">Crédito (pago manual)</option>
                                            <option value="tarjeta">Tarjeta (cobro automático)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estado</label>
                                        <select class="form-select form-select-sm" name="estado" id="susc_estado">
                                            <option value="activo">Activo</option>
                                            <option value="pausado">Pausado</option>
                                            <option value="suspendido">Suspendido</option>
                                            <option value="cancelado">Cancelado</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label>Observaciones</label>
                                        <textarea class="form-control form-control-sm" name="observaciones" id="susc_observaciones" rows="2" maxlength="500"></textarea>
                                    </div>

                                    <!-- Sección Kushki (solo si forma_cobro = tarjeta) -->
                                    <div class="col-12" id="susc_sec_tarjeta" style="display:none;">
                                        <div class="border rounded-3 p-3 bg-light">
                                            <h6 class="fw-bold small mb-3"><i class="bi bi-credit-card me-1 text-primary"></i>Tarjeta Kushki</h6>
                                            <div id="susc_tarjeta_actual" class="alert alert-info py-2 small mb-3 d-none">
                                                <i class="bi bi-credit-card me-1"></i>
                                                Tarjeta registrada: <strong id="susc_tarjeta_info"></strong>
                                            </div>
                                            <div class="alert alert-warning py-2 small mb-3">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                Para registrar una tarjeta necesita las credenciales de Kushki configuradas en el sistema.
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <label>Token de tarjeta (Kushki)</label>
                                                    <input type="text" class="form-control form-control-sm" id="susc_kushki_token"
                                                           placeholder="Token generado por Kushki JS" autocomplete="off">
                                                    <small class="text-muted">Se completará automáticamente al integrar el formulario Kushki.</small>
                                                </div>
                                                <div class="col-12">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="suscGuardarTarjeta()">
                                                        <i class="bi bi-shield-check me-1"></i>Guardar Tarjeta
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /pane-susc-cobro -->

                    </div><!-- /tab-content -->
                </div><!-- /modal-body -->

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($permSusc['eliminar'] ?? false): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarSusc" onclick="suscEliminar()">
                                <i class="bi bi-trash3 me-1"></i>Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-info btn-sm d-none" id="btnVerPagosSusc" onclick="abrirModalPagos()">
                            <i class="bi bi-clock-history me-1"></i>Historial Pagos
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarSusc">
                            <i class="bi bi-check2-circle me-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const urlBase  = '<?= $urlBase ?>';
    const urlCli   = '<?= $urlBaseClientes ?>';
    const urlProd  = '<?= $urlBaseProductos ?>';
    let suscDetLineIdx = 0;

    /* ── Buscador de clientes ─────────────────────────────────────────────────── */
    const inpCli  = document.getElementById('susc_search_cliente');
    const ddCli   = document.getElementById('susc_dropdown_clientes');
    const hidCli  = document.getElementById('susc_id_cliente');
    const infoCli = document.getElementById('susc_info_cliente');
    let timerCli;

    inpCli.addEventListener('input', () => {
        clearTimeout(timerCli);
        const q = inpCli.value.trim();
        if (q.length < 2) { ddCli.classList.add('d-none'); return; }
        timerCli = setTimeout(() => suscBuscarClientes(q), 300);
    });

    inpCli.addEventListener('blur', () => setTimeout(() => ddCli.classList.add('d-none'), 200));

    async function suscBuscarClientes(q) {
        try {
            const r = await fetch(urlCli + '/searchAjax?b=' + encodeURIComponent(q) + '&page=1&sort=nombre&dir=ASC');
            const d = await r.json();
            const lista = d.rows ?? [];
            if (!lista.length) { ddCli.innerHTML = '<div class="list-group-item text-muted small py-1 px-2">Sin resultados</div>'; ddCli.classList.remove('d-none'); return; }
            ddCli.innerHTML = lista.slice(0, 12).map(c => {
                const nombre = (c.nombre ?? '').replace(/</g, '&lt;');
                const ruc    = (c.identificacion ?? '').replace(/</g, '&lt;');
                const enc    = encodeURIComponent(JSON.stringify(c));
                return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small" onclick='suscSelCliente(${JSON.stringify(enc)})'><strong>${ruc}</strong> - ${nombre}</button>`;
            }).join('');
            ddCli.classList.remove('d-none');
        } catch (e) { console.error(e); }
    }

    window.suscSelCliente = function(enc) {
        const c = JSON.parse(decodeURIComponent(enc));
        hidCli.value                                           = c.id;
        inpCli.value                                           = (c.nombre ?? '') + (c.identificacion ? ' (' + c.identificacion + ')' : '');
        document.getElementById('susc_lbl_cli_ruc').textContent   = c.identificacion ?? '';
        document.getElementById('susc_lbl_cli_email').textContent  = c.correo ?? c.email ?? '';
        infoCli.classList.remove('d-none');
        ddCli.classList.add('d-none');
    };

    function suscSetCliente(c) {
        hidCli.value = c.id;
        inpCli.value = (c.nombre ?? '') + (c.identificacion ? ' (' + c.identificacion + ')' : '');
        document.getElementById('susc_lbl_cli_ruc').textContent  = c.identificacion ?? '';
        document.getElementById('susc_lbl_cli_email').textContent = c.correo ?? c.email ?? '';
        infoCli.classList.remove('d-none');
    }

    /* ── Buscador de productos ────────────────────────────────────────────────── */
    const inpProd = document.getElementById('susc_search_producto');
    const ddProd  = document.getElementById('susc_dropdown_productos');
    let timerProd;

    inpProd.addEventListener('input', () => {
        clearTimeout(timerProd);
        const q = inpProd.value.trim();
        if (q.length < 2) { ddProd.classList.add('d-none'); return; }
        timerProd = setTimeout(() => suscBuscarProductos(q), 300);
    });

    inpProd.addEventListener('blur', () => setTimeout(() => ddProd.classList.add('d-none'), 200));

    async function suscBuscarProductos(q) {
        try {
            const r = await fetch(urlProd + '/searchAjax?b=' + encodeURIComponent(q) + '&page=1&sort=nombre&dir=ASC');
            const d = await r.json();
            const lista = d.rows ?? [];
            if (!lista.length) { ddProd.innerHTML = '<div class="list-group-item text-muted small py-1 px-2">Sin resultados</div>'; ddProd.classList.remove('d-none'); return; }
            ddProd.innerHTML = lista.slice(0, 10).map(p => {
                const nombre  = (p.nombre ?? '').replace(/</g, '&lt;');
                const precio  = parseFloat(p.precio_unitario ?? p.pvp ?? 0).toFixed(2);
                const enc     = encodeURIComponent(JSON.stringify(p));
                return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small" onclick='suscAgregarProducto(${JSON.stringify(enc)})'><strong>${nombre}</strong> - $${precio}</button>`;
            }).join('');
            ddProd.classList.remove('d-none');
        } catch (e) { console.error(e); }
    }

    window.suscAgregarProducto = function(enc) {
        const p = JSON.parse(decodeURIComponent(enc));
        suscAgregarFila({
            id_producto:     p.id,
            descripcion:     p.nombre ?? '',
            cantidad:        1,
            precio_unitario: parseFloat(p.precio_unitario ?? p.pvp ?? 0),
            porcentaje_iva:  parseFloat(p.porcentaje_iva ?? p.iva ?? 0),
        });
        inpProd.value = '';
        ddProd.classList.add('d-none');
    };

    window.suscAgregarFilaVacia = function() {
        suscAgregarFila({ id_producto: '', descripcion: '', cantidad: 1, precio_unitario: 0, porcentaje_iva: 0 });
    };

    function suscAgregarFila(item) {
        const idx = suscDetLineIdx++;
        document.getElementById('susc_row_vacia')?.remove();

        const tbody  = document.getElementById('susc_tbody_detalle');
        const subtot = ((item.cantidad ?? 1) * (item.precio_unitario ?? 0)).toFixed(2);

        const tr = document.createElement('tr');
        tr.className = 'row-susc-det';
        tr.dataset.idx = idx;
        tr.innerHTML = `
            <td class="ps-2">
                <input type="hidden" name="detalle[${idx}][id_producto]" class="det-id-prod" value="${item.id_producto ?? ''}">
                <input type="text" class="input-detalle w-100 det-desc" name="detalle[${idx}][descripcion]"
                       value="${(item.descripcion ?? '').replace(/"/g, '&quot;')}"
                       placeholder="Descripción..." style="min-width:180px;">
            </td>
            <td class="text-center">
                <input type="number" class="input-detalle text-center det-qty" name="detalle[${idx}][cantidad]"
                       value="${item.cantidad ?? 1}" min="0.001" step="0.001" style="width:70px;"
                       oninput="suscRecalcFila(this)">
            </td>
            <td class="text-end">
                <input type="number" class="input-detalle text-end det-price" name="detalle[${idx}][precio_unitario]"
                       value="${parseFloat(item.precio_unitario ?? 0).toFixed(2)}" min="0" step="0.01" style="width:90px;"
                       oninput="suscRecalcFila(this)">
            </td>
            <td class="text-center">
                <input type="number" class="input-detalle text-center det-iva" name="detalle[${idx}][porcentaje_iva]"
                       value="${parseFloat(item.porcentaje_iva ?? 0).toFixed(2)}" min="0" step="0.01" style="width:60px;"
                       oninput="suscRecalcFila(this)">
            </td>
            <td class="text-end pe-2">
                <span class="det-subtotal" style="font-size:.82rem; font-weight:600;">$${subtot}</span>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-remove-det px-1 py-0" onclick="suscEliminarFila(this)" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        suscRecalcTotales();
    }

    window.suscEliminarFila = function(btn) {
        btn.closest('tr').remove();
        if (!document.querySelector('#susc_tbody_detalle tr')) {
            document.getElementById('susc_tbody_detalle').innerHTML =
                '<tr id="susc_row_vacia"><td colspan="6" class="text-center text-muted py-3 small"><i class="bi bi-box-seam me-1"></i>Agregue productos o servicios</td></tr>';
        }
        suscRecalcTotales();
    };

    window.suscRecalcFila = function(input) {
        const tr  = input.closest('tr');
        const qty = parseFloat(tr.querySelector('.det-qty').value) || 0;
        const prc = parseFloat(tr.querySelector('.det-price').value) || 0;
        tr.querySelector('.det-subtotal').textContent = '$' + (qty * prc).toFixed(2);
        suscRecalcTotales();
    };

    function suscRecalcTotales() {
        let subtotal = 0, iva = 0;
        document.querySelectorAll('#susc_tbody_detalle tr.row-susc-det').forEach(tr => {
            const qty  = parseFloat(tr.querySelector('.det-qty')?.value)   || 0;
            const prc  = parseFloat(tr.querySelector('.det-price')?.value)  || 0;
            const ivaP = parseFloat(tr.querySelector('.det-iva')?.value)    || 0;
            const sub  = qty * prc;
            subtotal += sub;
            iva      += sub * (ivaP / 100);
        });
        document.getElementById('susc_total_subtotal').textContent = '$' + subtotal.toFixed(2);
        document.getElementById('susc_total_iva').textContent      = '$' + iva.toFixed(2);
        document.getElementById('susc_total_total').textContent    = '$' + (subtotal + iva).toFixed(2);
    }

    /* ── Recalcular próximo cobro ─────────────────────────────────────────────── */
    window.suscRecalcularProximoCobro = function () {
        const fechaInicio = document.getElementById('susc_fecha_inicio').value;
        const selPer      = document.getElementById('susc_id_periodicidad');
        const opt         = selPer.options[selPer.selectedIndex];
        const meses       = parseInt(opt?.dataset.meses ?? 0);
        if (!fechaInicio || !meses) return;
        const dt = new Date(fechaInicio + 'T00:00:00');
        dt.setMonth(dt.getMonth() + meses);
        document.getElementById('susc_proximo_cobro').value = dt.toISOString().split('T')[0];
    };

    /* ── Forma de cobro ───────────────────────────────────────────────────────── */
    window.suscOnFormaCobro = function () {
        const forma = document.getElementById('susc_forma_cobro').value;
        const sec   = document.getElementById('susc_sec_tarjeta');
        sec.style.display = forma === 'tarjeta' ? 'block' : 'none';
    };

    /* ── Guardar tarjeta Kushki ───────────────────────────────────────────────── */
    window.suscGuardarTarjeta = async function () {
        const id    = document.getElementById('susc_id').value;
        const token = document.getElementById('susc_kushki_token').value.trim();
        if (!id || !token) { alert('Primero guarde la suscripción y proporcione el token de Kushki.'); return; }
        const fd = new FormData();
        fd.append('id', id);
        fd.append('kushki_token', token);
        try {
            const r = await fetch(urlBase + '/tokenizarTarjetaAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                document.getElementById('susc_tarjeta_info').textContent = `${d.brand} **** ${d.last4}`;
                document.getElementById('susc_tarjeta_actual').classList.remove('d-none');
                alert(d.mensaje);
            } else { alert(d.mensaje); }
        } catch (e) { alert('Error de conexión.'); }
    };

    /* ── Limpiar tabla detalle ────────────────────────────────────────────────── */
    function suscLimpiarDetalle() {
        suscDetLineIdx = 0;
        document.getElementById('susc_tbody_detalle').innerHTML =
            '<tr id="susc_row_vacia"><td colspan="6" class="text-center text-muted py-3 small"><i class="bi bi-box-seam me-1"></i>Agregue productos o servicios</td></tr>';
        suscRecalcTotales();
    }

    /* ── Cargar detalle existente ─────────────────────────────────────────────── */
    async function suscCargarDetalle(idSusc) {
        suscLimpiarDetalle();
        try {
            const r = await fetch(urlBase + '/getDetalleAjax?id=' + idSusc);
            const d = await r.json();
            if (d.ok && d.detalle?.length) {
                d.detalle.forEach(item => suscAgregarFila({
                    id_producto:     item.id_producto,
                    descripcion:     item.descripcion ?? item.nombre_producto ?? '',
                    cantidad:        parseFloat(item.cantidad),
                    precio_unitario: parseFloat(item.precio_unitario),
                    porcentaje_iva:  parseFloat(item.porcentaje_iva),
                }));
            }
        } catch (e) { console.error(e); }
    }

    /* ── Abrir modal crear ────────────────────────────────────────────────────── */
    window.abrirModalSuscCrear = async function () {
        document.getElementById('susc_id').value                = '';
        hidCli.value                                             = '';
        inpCli.value                                             = '';
        infoCli.classList.add('d-none');
        document.getElementById('susc_fecha_inicio').value      = new Date().toISOString().split('T')[0];
        document.getElementById('susc_fecha_fin').value         = '';
        document.getElementById('susc_id_periodicidad').value   = '';
        document.getElementById('susc_proximo_cobro').value     = '';
        document.getElementById('susc_forma_cobro').value       = 'credito';
        document.getElementById('susc_estado').value            = 'activo';
        document.getElementById('susc_observaciones').value     = '';
        document.getElementById('tituloModalSusc').textContent  = 'Nueva Suscripción';
        document.getElementById('btnEliminarSusc')?.classList.add('d-none');
        document.getElementById('btnVerPagosSusc')?.classList.add('d-none');
        document.getElementById('suscAlert').className          = 'alert d-none';
        document.getElementById('susc_tarjeta_actual')?.classList.add('d-none');
        suscLimpiarDetalle();
        suscOnFormaCobro();
        // Activar primera pestaña
        const tabEl = document.querySelector('#modalSusc a[href="#pane-susc-servicios"]');
        if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
        new bootstrap.Modal(document.getElementById('modalSusc')).show();
    };

    /* ── Abrir modal editar ───────────────────────────────────────────────────── */
    window.abrirModalSuscEditar = async function (el) {
        const s = JSON.parse(el.dataset.susc);

        document.getElementById('susc_id').value                = s.id;
        document.getElementById('susc_id_periodicidad').value   = s.id_periodicidad ?? '';
        document.getElementById('susc_fecha_inicio').value      = s.fecha_inicio ?? '';
        document.getElementById('susc_fecha_fin').value         = s.fecha_fin ?? '';
        document.getElementById('susc_proximo_cobro').value     = s.proximo_cobro ?? '';
        document.getElementById('susc_forma_cobro').value       = s.forma_cobro ?? 'credito';
        document.getElementById('susc_estado').value            = s.estado ?? 'activo';
        document.getElementById('susc_observaciones').value     = s.observaciones ?? '';
        document.getElementById('tituloModalSusc').textContent  = 'Suscripción: ' + (s.nombre_cliente ?? '');
        document.getElementById('btnEliminarSusc')?.classList.remove('d-none');
        document.getElementById('btnVerPagosSusc')?.classList.remove('d-none');
        document.getElementById('suscAlert').className          = 'alert d-none';

        // Poblar cliente
        if (s.id_cliente) {
            suscSetCliente({ id: s.id_cliente, nombre: s.nombre_cliente ?? '', identificacion: s.identificacion_cliente ?? '' });
        }

        // Tarjeta
        if (s.kushki_card_last4) {
            document.getElementById('susc_tarjeta_info').textContent = `${s.kushki_card_brand ?? ''} **** ${s.kushki_card_last4}`;
            document.getElementById('susc_tarjeta_actual')?.classList.remove('d-none');
        } else {
            document.getElementById('susc_tarjeta_actual')?.classList.add('d-none');
        }

        suscOnFormaCobro();
        window._suscId = s.id;

        // Activar primera pestaña
        const tabEl = document.querySelector('#modalSusc a[href="#pane-susc-servicios"]');
        if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();

        new bootstrap.Modal(document.getElementById('modalSusc')).show();

        // Cargar detalle tras abrir modal
        await suscCargarDetalle(s.id);
    };

    /* ── Guardar (crear / actualizar) ────────────────────────────────────────── */
    document.getElementById('formSusc').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id     = document.getElementById('susc_id').value;
        const method = id ? 'update' : 'store';
        const fd     = new FormData(this);
        const alerta = document.getElementById('suscAlert');
        const btn    = document.getElementById('btnGuardarSusc');
        btn.disabled = true;

        try {
            const r = await fetch(`${urlBase}/${method}`, { method: 'POST', body: fd });
            const d = await r.json();
            alerta.className   = 'alert mx-3 mt-3 mb-0 py-2 small shadow-sm border-0 ' + (d.ok ? 'alert-success' : 'alert-danger');
            alerta.textContent = d.mensaje;
            if (d.ok) {
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalSusc'))?.hide();
                    window.fetchSearch(window.currentPage ?? 1);
                }, 800);
            }
        } catch (err) {
            alerta.className   = 'alert mx-3 mt-3 mb-0 py-2 small shadow-sm border-0 alert-danger';
            alerta.textContent = 'Error de conexión.';
        } finally {
            btn.disabled = false;
        }
    });

    /* ── Eliminar ─────────────────────────────────────────────────────────────── */
    window.suscEliminar = async function () {
        const id = document.getElementById('susc_id').value;
        if (!id || !confirm('¿Eliminar esta suscripción?')) return;
        const fd = new FormData(); fd.append('id', id);
        try {
            const r = await fetch(urlBase + '/delete', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalSusc'))?.hide();
                window.fetchSearch(1);
            } else { alert(d.mensaje); }
        } catch (e) { alert('Error de conexión.'); }
    };

    // Alias para compatibilidad con modal_pagos
    window.eliminarSusc = window.suscEliminar;

    /* ── Abrir modal pagos ────────────────────────────────────────────────────── */
    window.abrirModalPagos = function () {
        const id = document.getElementById('susc_id').value;
        if (id) window.cargarModalPagos(id);
    };

})();
</script>
