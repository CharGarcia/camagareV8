<?php
$urlBase         = BASE_URL . '/modulos/suscripciones';
$urlBaseClientes = BASE_URL . '/modulos/clientes';
$urlBaseProductos = BASE_URL . '/modulos/productos';
$permSusc        = $perm ?? [];

$vistaConfigSusc = \App\Helpers\PreferenciasHelper::getPreferenciasVista('suscripciones');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigSusc, 'estiloVistaPestanasSusc');
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
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-arrow-repeat text-primary me-2"></i>
                        <span id="tituloModalSusc">Nueva Suscripción</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="susc_id" name="id" value="">

                    <!-- Barra de Acciones Superior -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalProductoCrear()" title="Registrar nuevo producto"><i class="bi bi-box-seam fs-6"></i></button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" id="susc-tab-servicios-btn" data-bs-toggle="tab" href="#pane-susc-servicios" role="tab" title="Detalle suscripción">
                                    <i class="bi bi-receipt me-1"></i>Detalle suscripción
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link py-2 small" id="susc-tab-cobro-btn" data-bs-toggle="tab" href="#pane-susc-cobro" role="tab" title="Forma de pago">
                                    <i class="bi bi-credit-card me-1"></i>Forma de pago
                                </a>
                            </li>
                        </ul>
                        <div class="pb-1 flex-shrink-0">
                            <?php
                            $pestanasConfigSusc = [
                                'pane-susc-servicios' => 'Detalle suscripción',
                                'pane-susc-cobro'     => 'Forma de pago',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigSusc, $vistaConfigSusc ?? [], 'suscripciones');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3" style="overflow: visible !important;">

                        <!-- ══ PESTAÑA 1: Cliente + Servicios ══════════════════════════════════ -->
                        <div class="tab-pane fade show active" id="pane-susc-servicios" role="tabpanel">

                            <!-- Cabecera: cliente + fechas + periodicidad -->
                            <div class="p-3 bg-white border-bottom">
                                <div class="row g-2">

                                    <!-- Buscador de cliente -->
                                    <div class="col-12">
                                        <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-12 position-relative">
                                                    <div class="input-group input-group-sm rounded-pill overflow-hidden border bg-white">
                                                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                        <input type="text" class="form-control border-0 px-1"
                                                               id="susc_search_cliente"
                                                               placeholder="Buscar cliente por RUC o razón social..."
                                                               autocomplete="off">
                                                        <input type="hidden" name="id_cliente" id="susc_id_cliente">
                                                    </div>
                                                    <div id="susc_dropdown_clientes"
                                                         class="list-group shadow dropdown-predictivo position-absolute d-none"
                                                         style="z-index:1090; width:100%; max-height:250px; overflow-y:auto; top:35px; left:0;"></div>
                                                </div>
                                                <div class="col-12 d-none px-2 mt-1" id="susc_info_cliente" style="font-size:.72rem; color:#6c757d;">
                                                    <span class="fw-bold text-dark border-end pe-2 me-1" id="susc_lbl_cli_ruc"></span>
                                                    <i class="bi bi-envelope me-1"></i><span id="susc_lbl_cli_email"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Estado (junto al buscador de cliente) -->
                                    <div class="col-md-2">
                                        <label>Estado</label>
                                        <select class="form-select form-select-sm" name="estado" id="susc_estado">
                                            <option value="activo">Activo</option>
                                            <option value="pausado">Pausado</option>
                                            <option value="suspendido">Suspendido</option>
                                            <option value="cancelado">Cancelado</option>
                                        </select>
                                    </div>

                                    <!-- Fechas y periodicidad -->
                                    <div class="col-md-2">
                                        <label>Comprobante *</label>
                                        <select class="form-select form-select-sm" name="tipo_comprobante" id="susc_tipo_comprobante" required>
                                            <option value="factura">Factura de Venta</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fecha Inicio *</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_inicio" id="susc_fecha_inicio" required onchange="suscRecalcularProximoCobro()">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Fecha Fin <small class="text-muted fw-normal">(opc)</small></label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_fin" id="susc_fecha_fin">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Periodicidad *</label>
                                        <select class="form-select form-select-sm" name="id_periodicidad" id="susc_id_periodicidad" required onchange="suscRecalcularProximoCobro()">
                                            <option value="">- Seleccione -</option>
                                            <?php foreach ($periodicidades ?? [] as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-meses="<?= $p['meses'] ?>" data-codigo="<?= htmlspecialchars($p['codigo'] ?? '') ?>">
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Próximo Cobro</label>
                                        <input type="date" class="form-control form-control-sm" name="proximo_cobro" id="susc_proximo_cobro" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de productos/servicios -->
                            <div class="p-3">
                                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <div class="table-responsive" style="max-height: 350px;">
                                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                                            <thead>
                                                <tr class="table-light border-bottom">
                                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width:40%;">Descripción</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Cant.</th>
                                                    <th class="py-2 small fw-bold text-muted text-end" style="width:15%;">Precio Unit.</th>
                                                    <th class="py-2 small fw-bold text-muted text-center" style="width:15%;">IVA</th>
                                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width:15%;">Subtotal</th>
                                                    <th style="width:5%;"></th>
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

                                    <!-- Pie de tabla: agregar línea -->
                                    <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center position-relative">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="suscAgregarFilaVacia()">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                        </button>
                                        <div class="small fw-bold text-muted pe-3">
                                            Items: <span id="susc_count_items">0</span>
                                        </div>
                                        <div id="susc_dropdown_productos"
                                             class="list-group shadow dropdown-predictivo d-none position-fixed"
                                             style="z-index: 2000; width: 380px; max-height: 250px; overflow-y: auto;"></div>
                                    </div>
                                </div>

                                <!-- Totales e Información Adicional -->
                                <div class="row mt-3 justify-content-between">
                                    <div class="col-md-7">
                                        <!-- Información Adicional (concepto / detalle) -->
                                        <label>Información Adicional</label>
                                        <div class="border rounded-2 overflow-hidden bg-white">
                                            <div class="table-responsive" style="max-height: 180px;">
                                                <table class="table table-sm mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                            <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                            <th class="py-0" style="width: 10%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="susc_tbody_info_adicional"></tbody>
                                                </table>
                                            </div>
                                            <div class="p-1 border-top bg-light">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="suscAgregarInfoAdicional()">
                                                    <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                            <!-- Subtotal General -->
                                            <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                                <span class="text-muted">Subtotal</span>
                                                <span id="susc_lbl_subtotal">$0.00</span>
                                            </div>

                                            <!-- Subtotales agrupados por tarifa IVA -->
                                            <div id="susc_lbl_subtotales_iva" class="mb-1"></div>

                                            <!-- IVA agrupado por tarifa (solo los > 0) -->
                                            <div id="susc_lbl_ivas_grupo" class="mb-1"></div>

                                            <hr class="my-1 opacity-25">

                                            <!-- Total -->
                                            <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                                <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                                <span class="fw-bold text-dark" style="font-size:1rem;" id="susc_lbl_total">$0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /pane-susc-servicios -->

                        <!-- ══ PESTAÑA 2: Cobro ════════════════════════════════════════════════ -->
                        <div class="tab-pane fade" id="pane-susc-cobro" role="tabpanel">
                            <div class="p-3">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Forma de Cobro *</label>
                                        <select class="form-select form-select-sm" name="forma_cobro" id="susc_forma_cobro" required onchange="suscOnFormaCobro()">
                                            <option value="credito">Crédito (pago manual)</option>
                                            <option value="tarjeta">Tarjeta (cobro automático)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label>Observaciones</label>
                                        <textarea class="form-control form-control-sm" name="observaciones" id="susc_observaciones" rows="2" maxlength="500"></textarea>
                                    </div>

                                    <!-- Sección tarjeta (solo si forma_cobro = tarjeta) -->
                                    <div class="col-12" id="susc_sec_tarjeta" style="display:none;">
                                        <div class="border rounded-3 p-3 bg-light">
                                            <h6 class="fw-bold small mb-3"><i class="bi bi-credit-card me-1 text-primary"></i>Cobro con Tarjeta</h6>
                                            <div id="susc_tarjeta_actual" class="alert alert-info py-2 small mb-3 d-none">
                                                <i class="bi bi-credit-card me-1"></i>
                                                Tarjeta registrada: <strong id="susc_tarjeta_info"></strong>
                                            </div>
                                            <div class="alert alert-secondary py-2 small mb-0">
                                                <i class="bi bi-info-circle me-1"></i>
                                                El cobro automático con tarjeta estará disponible próximamente con integración de tokenización.
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
    const tarifasIva = <?= json_encode($tarifasIva ?? []) ?>;
    // Decimales configurados en el módulo empresa (precio unitario y cantidad).
    const SUSC_DEC_PRECIO = <?= (int) ($decimalesPrecio ?? 2) ?>;
    const SUSC_DEC_CANT   = <?= (int) ($decimalesCantidad ?? 2) ?>;
    const suscStepDec = (d) => d > 0 ? '0.' + '0'.repeat(d - 1) + '1' : '1';
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
            const r = await fetch(urlBase + '/getClientesAjax?q=' + encodeURIComponent(q));
            const d = await r.json();
            const lista = d.rows ?? [];
            if (!lista.length) { ddCli.innerHTML = '<div class="list-group-item text-muted small py-1 px-2">Sin resultados</div>'; ddCli.classList.remove('d-none'); return; }
            ddCli.innerHTML = lista.slice(0, 12).map(c => {
                const nombre = (c.nombre ?? '').replace(/</g, '&lt;');
                const ruc    = (c.identificacion ?? '').replace(/</g, '&lt;');
                const enc    = encodeURIComponent(JSON.stringify(c));
                return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small" onclick='suscSelCliente("${enc}")'><strong>${ruc}</strong> - ${nombre}</button>`;
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
    const ddProd  = document.getElementById('susc_dropdown_productos');
    let timerProd;
    let inputFilaActiva = null;

    document.getElementById('susc_tbody_detalle').addEventListener('input', (e) => {
        if(e.target.classList.contains('det-desc')) {
            clearTimeout(timerProd);
            const q = e.target.value.trim();
            inputFilaActiva = e.target;
            if (q.length < 2) { ddProd.classList.add('d-none'); return; }
            timerProd = setTimeout(() => suscBuscarProductos(q, e.target), 300);
        }
    });

    document.addEventListener('click', (e) => {
        if(!e.target.closest('#susc_dropdown_productos') && !e.target.classList.contains('det-desc')) {
            ddProd.classList.add('d-none');
        }
    });

    async function suscBuscarProductos(q, inputEl) {
        try {
            const r = await fetch(urlBase + '/getProductosAjax?q=' + encodeURIComponent(q));
            const d = await r.json();
            const lista = d.rows ?? [];
            
            const rect = inputEl.getBoundingClientRect();
            ddProd.style.position = 'fixed';
            ddProd.style.top = (rect.bottom) + 'px';
            ddProd.style.left = rect.left + 'px';
            ddProd.style.width = Math.max(380, rect.width) + 'px';

            if (!lista.length) { ddProd.innerHTML = '<div class="list-group-item text-muted small py-1 px-2">Sin resultados</div>'; ddProd.classList.remove('d-none'); return; }
            ddProd.innerHTML = lista.slice(0, 10).map(p => {
                const nombre  = (p.nombre ?? '').replace(/</g, '&lt;');
                const precio  = parseFloat(p.precio_base ?? p.precio_unitario ?? 0);
                const iva     = parseFloat(p.porcentaje_iva_final ?? p.porcentaje_iva ?? p.iva ?? 0).toFixed(2);
                const enc     = encodeURIComponent(JSON.stringify({id: p.id, n: nombre, p: precio, i: iva, tid: p.tarifa_iva ?? null}));
                return `<button type="button" class="list-group-item list-group-item-action py-1 px-2 small" onclick='suscAsignarProductoFila("${enc}")'><strong>${nombre}</strong> - $${precio.toFixed(SUSC_DEC_PRECIO)}</button>`;
            }).join('');
            ddProd.classList.remove('d-none');
        } catch (e) { console.error(e); }
    }

    window.suscAsignarProductoFila = function(enc) {
        if(!inputFilaActiva) return;
        const p = JSON.parse(decodeURIComponent(enc));
        const tr = inputFilaActiva.closest('tr');
        
        tr.querySelector('.det-id-prod').value = p.id;
        tr.querySelector('.det-desc').value = p.n;
        tr.querySelector('.det-price').value = parseFloat(p.p ?? 0).toFixed(SUSC_DEC_PRECIO);
        
        let selectIva = tr.querySelector('.det-iva');
        if (p.tid) {
            // Coincidencia exacta por ID de tarifa_iva (concepto SRI preciso)
            for (let i = 0; i < selectIva.options.length; i++) {
                if (parseInt(selectIva.options[i].value) === parseInt(p.tid)) {
                    selectIva.selectedIndex = i;
                    break;
                }
            }
        } else {
            // Fallback: coincidencia por porcentaje
            let valBuscado = parseFloat(p.i);
            for (let i = 0; i < selectIva.options.length; i++) {
                if (parseFloat(selectIva.options[i].dataset.porcentaje) === valBuscado) {
                    selectIva.selectedIndex = i;
                    break;
                }
            }
        }
        const hidPct = tr.querySelector('.det-porcentaje-iva');
        if (hidPct) hidPct.value = parseFloat(selectIva.options[selectIva.selectedIndex]?.dataset?.porcentaje ?? 0);

        suscRecalcFila(tr.querySelector('.det-qty'));
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
            <td class="ps-3 position-relative">
                <input type="hidden" name="detalle[${idx}][id_producto]" class="det-id-prod" value="${item.id_producto ?? ''}">
                <input type="text" class="form-control form-control-sm input-detalle det-desc" name="detalle[${idx}][descripcion]"
                       value="${(item.descripcion ?? '').replace(/"/g, '&quot;')}"
                       placeholder="Escribe o busca un producto/servicio..." autocomplete="off">
            </td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm input-detalle text-center det-qty" name="detalle[${idx}][cantidad]"
                       value="${parseFloat(item.cantidad ?? 1).toFixed(SUSC_DEC_CANT)}" min="0" step="${suscStepDec(SUSC_DEC_CANT)}"
                       oninput="suscRecalcFila(this)" onblur="this.value=parseFloat(this.value||0).toFixed(SUSC_DEC_CANT)">
            </td>
            <td class="text-end">
                <input type="number" class="form-control form-control-sm input-detalle text-end det-price" name="detalle[${idx}][precio_unitario]"
                       value="${parseFloat(item.precio_unitario ?? 0).toFixed(SUSC_DEC_PRECIO)}" min="0" step="${suscStepDec(SUSC_DEC_PRECIO)}"
                       oninput="suscRecalcFila(this)" onblur="this.value=parseFloat(this.value||0).toFixed(SUSC_DEC_PRECIO)">
            </td>
            <td class="text-center align-middle">
                <input type="hidden" class="det-porcentaje-iva" name="detalle[${idx}][porcentaje_iva]" value="${parseFloat(item.porcentaje_iva ?? 0)}">
                <select class="form-select form-select-sm input-detalle text-center det-iva" name="detalle[${idx}][id_tarifa_iva]" onchange="suscOnCambiarIva(this)">
                    ${tarifasIva.map(t => {
                        const sel = item.id_tarifa_iva
                            ? parseInt(item.id_tarifa_iva) === parseInt(t.id)
                            : parseFloat(item.porcentaje_iva ?? 0) === parseFloat(t.porcentaje_iva);
                        return `<option value="${t.id}" data-porcentaje="${t.porcentaje_iva}" data-codigo="${t.codigo ?? ''}" ${sel ? 'selected' : ''}>${t.tarifa}</option>`;
                    }).join('')}
                </select>
            </td>
            <td class="text-end pe-4 align-middle">
                <span class="det-subtotal" style="font-size:.82rem; font-weight:600;">$${subtot}</span>
            </td>
            <td class="text-center p-0 align-middle">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="suscEliminarFila(this)" title="Eliminar ítem">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        // Sync hidden porcentaje_iva with the initially selected IVA option
        const selIvaEl = tr.querySelector('.det-iva');
        if (selIvaEl && selIvaEl.selectedIndex >= 0) {
            const hidPct = tr.querySelector('.det-porcentaje-iva');
            if (hidPct) hidPct.value = parseFloat(selIvaEl.options[selIvaEl.selectedIndex]?.dataset?.porcentaje ?? 0);
        }
        suscRecalcTotales();

        if(!item.id_producto) {
            setTimeout(() => tr.querySelector('.det-desc').focus(), 50);
        }
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

    window.suscOnCambiarIva = function(sel) {
        const tr  = sel.closest('tr');
        const opt = sel.options[sel.selectedIndex];
        const pct = parseFloat(opt?.dataset?.porcentaje ?? 0);
        const hid = tr.querySelector('.det-porcentaje-iva');
        if (hid) hid.value = pct;
        suscRecalcFila(sel);
    };

    function suscRecalcTotales() {
        let subGeneral = 0;
        let totalGeneral = 0;
        
        let basesIva = {}; 

        document.querySelectorAll('#susc_tbody_detalle tr.row-susc-det').forEach(tr => {
            const qty  = parseFloat(tr.querySelector('.det-qty')?.value)   || 0;
            const prc  = parseFloat(tr.querySelector('.det-price')?.value)  || 0;
            const ivaP = parseFloat(tr.querySelector('.det-porcentaje-iva')?.value) || 0;
            const sub  = qty * prc;
            
            subGeneral += sub;
            
            if (!basesIva[ivaP]) basesIva[ivaP] = 0;
            basesIva[ivaP] += sub;
        });

        document.getElementById('susc_lbl_subtotal').textContent = '$' + subGeneral.toFixed(2);
        
        // Renderizar subtotales por tarifa
        const contSubIva = document.getElementById('susc_lbl_subtotales_iva');
        contSubIva.innerHTML = '';
        let sortedTasas = Object.keys(basesIva).map(Number).sort((a,b)=>b-a);
        sortedTasas.forEach(tasa => {
            if (basesIva[tasa] >= 0 || Object.keys(basesIva).length > 0) {
                contSubIva.innerHTML += `
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-muted">Subtotal ${tasa}%</span>
                        <span class="fw-bold text-dark">$${basesIva[tasa].toFixed(2)}</span>
                    </div>`;
            }
        });

        // Renderizar ivas agrupados
        const contIvasGrupo = document.getElementById('susc_lbl_ivas_grupo');
        contIvasGrupo.innerHTML = '';
        let sumaIvas = 0;
        sortedTasas.forEach(tasa => {
            const montoIva = basesIva[tasa] * (tasa / 100);
            sumaIvas += montoIva;
            if (tasa > 0 && montoIva > 0) {
                contIvasGrupo.innerHTML += `
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-muted">IVA ${tasa}%</span>
                        <span class="fw-bold text-dark">$${montoIva.toFixed(2)}</span>
                    </div>`;
            }
        });

        totalGeneral = subGeneral + sumaIvas;
        document.getElementById('susc_lbl_total').textContent = '$' + totalGeneral.toFixed(2);
        
        if(document.getElementById('susc_count_items')) {
            document.getElementById('susc_count_items').textContent = document.querySelectorAll('#susc_tbody_detalle tr.row-susc-det').length;
        }
    }

    /* ── Información adicional (concepto / detalle) ───────────────────────────── */
    let suscInfoIdx = 0;

    window.suscAgregarInfoAdicional = function (item) {
        const idx   = suscInfoIdx++;
        const tbody = document.getElementById('susc_tbody_info_adicional');
        const concepto = (item?.concepto ?? '').replace(/"/g, '&quot;');
        const detalle  = (item?.detalle  ?? '').replace(/"/g, '&quot;');

        const tr = document.createElement('tr');
        tr.className = 'row-susc-info';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent" style="padding:0 4px;height:20px;font-size:0.78rem;" name="info_adicional[${idx}][concepto]" value="${concepto}" placeholder="Concepto..."></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent" style="padding:0 4px;height:20px;font-size:0.78rem;" name="info_adicional[${idx}][detalle]" value="${detalle}" placeholder="Detalle..."></td>
            <td class="p-0 text-center pe-1">
                <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        if (!item) tr.querySelector('input').focus();
    };

    function suscLimpiarInfoAdicional() {
        suscInfoIdx = 0;
        const tbody = document.getElementById('susc_tbody_info_adicional');
        if (tbody) tbody.innerHTML = '';
    }

    function suscCargarInfoAdicional(raw) {
        suscLimpiarInfoAdicional();
        let filas = raw;
        if (typeof filas === 'string' && filas.trim() !== '') {
            try { filas = JSON.parse(filas); } catch (e) { filas = []; }
        }
        if (Array.isArray(filas)) {
            filas.forEach(f => suscAgregarInfoAdicional({ concepto: f.concepto ?? '', detalle: f.detalle ?? '' }));
        }
        // Si no había info adicional guardada, dejar una línea lista para ingresar.
        if (!document.querySelector('#susc_tbody_info_adicional tr')) {
            suscAgregarInfoAdicional();
        }
    }

    /* ── Recalcular próximo cobro ─────────────────────────────────────────────── */
    window.suscRecalcularProximoCobro = function () {
        const fechaInicio = document.getElementById('susc_fecha_inicio').value;
        const selPer      = document.getElementById('susc_id_periodicidad');
        const opt         = selPer.options[selPer.selectedIndex];
        const meses       = parseInt(opt?.dataset.meses ?? 0);
        const codigo      = opt?.dataset.codigo ?? '';
        
        if (!fechaInicio || selPer.value === '') return;
        const dt = new Date(fechaInicio + 'T00:00:00');
        
        if (codigo === 'DIARIO') {
            dt.setDate(dt.getDate() + 1);
        } else if (codigo === 'SEMANAL') {
            dt.setDate(dt.getDate() + 7);
        } else if (codigo === 'QUINCENAL') {
            dt.setDate(dt.getDate() + 15);
        } else {
            dt.setMonth(dt.getMonth() + meses);
        }
        
        document.getElementById('susc_proximo_cobro').value = dt.toISOString().split('T')[0];
    };

    /* ── Forma de cobro ───────────────────────────────────────────────────────── */
    window.suscOnFormaCobro = function () {
        const forma = document.getElementById('susc_forma_cobro').value;
        const sec   = document.getElementById('susc_sec_tarjeta');
        sec.style.display = forma === 'tarjeta' ? 'block' : 'none';
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
                    id_tarifa_iva:   item.id_tarifa_iva ?? null,
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
        document.getElementById('susc_tarjeta_actual')?.classList.add('d-none');
        suscLimpiarDetalle();
        suscAgregarFilaVacia();
        suscLimpiarInfoAdicional();
        suscAgregarInfoAdicional();
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
        if (s.tipo_comprobante) document.getElementById('susc_tipo_comprobante').value = s.tipo_comprobante;
        if (s.fecha_inicio) document.getElementById('susc_fecha_inicio').value = s.fecha_inicio;
        if (s.fecha_fin) document.getElementById('susc_fecha_fin').value = s.fecha_fin;
        if (s.id_periodicidad) document.getElementById('susc_id_periodicidad').value = s.id_periodicidad;
        if (s.proximo_cobro) document.getElementById('susc_proximo_cobro').value = s.proximo_cobro;
        document.getElementById('susc_forma_cobro').value       = s.forma_cobro ?? 'credito';
        document.getElementById('susc_estado').value            = s.estado ?? 'activo';
        document.getElementById('susc_observaciones').value     = s.observaciones ?? '';
        document.getElementById('tituloModalSusc').textContent  = 'Suscripción: ' + (s.nombre_cliente ?? '');
        document.getElementById('btnEliminarSusc')?.classList.remove('d-none');

        suscCargarInfoAdicional(s.info_adicional ?? null);


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
        const btn    = document.getElementById('btnGuardarSusc');
        btn.disabled = true;

        try {
            const r = await fetch(`${urlBase}/${method}`, { method: 'POST', body: fd });
            const d = await r.json();
            
            if (d.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: d.mensaje,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalSusc'))?.hide();
                    window.fetchSearch(window.currentPage ?? 1);
                });
            } else {
                Swal.fire('Atención', d.mensaje, 'warning').then(() => {
                    if (d.focus) {
                        const el = document.querySelector(d.focus);
                        if (el) {
                            const tabPane = el.closest('.tab-pane');
                            if (tabPane && !tabPane.classList.contains('active')) {
                                const tabLink = document.querySelector(`a[href="#${tabPane.id}"]`);
                                if (tabLink) {
                                    bootstrap.Tab.getOrCreateInstance(tabLink).show();
                                    setTimeout(() => el.focus(), 250);
                                    return;
                                }
                            }
                            el.focus();
                        }
                    }
                });
            }
        } catch (err) {
            Swal.fire('Error', 'Error de conexión.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ── Eliminar ─────────────────────────────────────────────────────────────── */
    window.suscEliminar = async function () {
        const id = document.getElementById('susc_id').value;
        if (!id) return;
        
        const confirmacion = await Swal.fire({
            title: '¿Eliminar esta suscripción?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        
        if (!confirmacion.isConfirmed) return;
        
        const fd = new FormData(); fd.append('id', id);
        try {
            const r = await fetch(urlBase + '/delete', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                Swal.fire('Eliminado', 'La suscripción ha sido eliminada.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalSusc'))?.hide();
                window.fetchSearch(1);
            } else { Swal.fire('Error', d.mensaje, 'error'); }
        } catch (e) { Swal.fire('Error', 'Error de conexión.', 'error'); }
    };

    // Alias para compatibilidad con modal_pagos
    window.eliminarSusc = window.suscEliminar;



})();
</script>
