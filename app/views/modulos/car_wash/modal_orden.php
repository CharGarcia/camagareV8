<?php
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalOrdenCW" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="cwTitulo"><i class="bi bi-droplet-half me-1 text-info"></i> Nueva orden de Car-Wash</h5>
                <span id="cw_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Barra de acciones (estilo factura) -->
            <div class="px-3 pt-2 d-flex flex-wrap gap-1 border-bottom pb-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearVehiculo()" title="Registrar nuevo vehículo">
                    <i class="bi bi-car-front"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearCliente()" title="Registrar nuevo cliente">
                    <i class="bi bi-person-plus"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="cwCrearProducto()" title="Registrar nuevo servicio/producto">
                    <i class="bi bi-box-seam"></i>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="cw_btn_factura" class="btn btn-outline-success btn-sm px-2" onclick="cwGenerarDocumento('FACTURA')" title="Generar Factura electrónica" disabled>
                    <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Factura</span>
                </button>
                <button type="button" id="cw_btn_recibo" class="btn btn-outline-success btn-sm px-2" onclick="cwGenerarDocumento('RECIBO')" title="Generar Recibo de venta" disabled>
                    <i class="bi bi-receipt-cutoff"></i> <span class="d-none d-md-inline">Recibo</span>
                </button>
                <div class="vr mx-1"></div>
                <button type="button" id="cw_btn_pdf" class="btn btn-outline-danger btn-sm px-2" onclick="cwPdf()" title="PDF del documento" disabled><i class="bi bi-file-earmark-pdf"></i></button>
                <button type="button" id="cw_btn_correo" class="btn btn-outline-info btn-sm px-2" onclick="cwCorreo()" title="Enviar por correo" disabled><i class="bi bi-envelope"></i></button>
                <button type="button" id="cw_btn_whatsapp" class="btn btn-outline-success btn-sm px-2" onclick="cwWhatsapp()" title="Enviar por WhatsApp" disabled><i class="bi bi-whatsapp"></i></button>
            </div>

            <div class="modal-body">
                <form id="formOrdenCW" autocomplete="off">
                    <input type="hidden" id="cw_id">
                    <input type="hidden" id="cw_id_vehiculo">
                    <input type="hidden" id="cw_id_cliente">
                    <input type="hidden" id="cw_serie">
                    <input type="hidden" id="cw_id_punto_emision">
                    <input type="hidden" id="cw_id_establecimiento">

                    <!-- Cabecera (diseño factura) -->
                    <div class="p-2 bg-white border rounded-3 mb-2">
                        <!-- Fila 1: fecha, serie, secuencial, cliente -->
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Fecha ingreso</label>
                                <input type="datetime-local" id="cw_fecha_ingreso" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/car-wash', 'cw_select_serie', 'id_punto_emision') ?></label>
                                <select id="cw_select_serie" name="id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="cwSerieChange()" style="height:31px;">
                                    <?php if (empty($puntos)): ?>
                                        <option value="">— Sin puntos —</option>
                                    <?php else: foreach ($puntos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                            data-id-est="<?= (int)($p['id_establecimiento'] ?? 0) ?>"
                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                            <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                <input type="text" id="cw_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light" style="height:31px;" readonly placeholder="000000001" maxlength="9">
                            </div>
                            <div class="col-12 col-md-6 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Cliente <span class="text-danger">*</span></label>
                                <input type="text" id="cw_cliente_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Seleccionar cliente..." oninput="cwBuscarClientes(this.value)">
                                <div id="cw_cli_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                        </div>
                        <!-- Fila 2: vehículo, kilometraje, combustible, próxima cita, bodega -->
                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-12 col-md-4 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Vehículo <span class="text-danger">*</span></label>
                                <input type="text" id="cw_vehiculo_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Placa, marca o propietario..." oninput="cwBuscarVehiculos(this.value)">
                                <div id="cw_veh_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Kilometraje</label>
                                <input type="number" id="cw_kilometraje" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;" min="0" placeholder="Km">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Combustible</label>
                                <select id="cw_nivel_combustible" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;">
                                    <option value="">—</option>
                                    <option value="E">E - Vacío</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="F">F - Lleno</option>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1"><i class="bi bi-calendar-event"></i> Próx. cita</label>
                                <input type="date" id="cw_proxima_cita" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Bodega <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/car-wash', 'cw_id_bodega', 'id_bodega') ?></label>
                                <select id="cw_id_bodega" name="id_bodega" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;" title="Bodega de donde se toma el inventario al facturar">
                                    <option value="">Seleccione...</option>
                                    <?php if (isset($bodegas)): ?>
                                        <?php foreach ($bodegas as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" id="cw_numero_orden">
                        <div class="mt-1" id="cw_info_cliente" style="font-size:.78rem"></div>
                    </div>

                    <!-- Servicios / Productos (grilla igual que factura de venta) -->
                    <div class="mt-2 border rounded-3 overflow-hidden bg-white shadow-sm">
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-detalle mb-0 text-nowrap">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width: 20%;">Descripción</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 8%;">Adicional</th>
                                        <th class="py-2 small fw-bold text-muted col-medida-header <?= (($empresa['mostrar_unidad_medida'] ?? true) === 'true' || ($empresa['mostrar_unidad_medida'] ?? true) === true) ? '' : 'd-none' ?>" style="width: 7%;">Medida</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 11%;">Bodega</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Cant.</th>
                                        <th class="py-2 small fw-bold text-muted" style="width: 10%;">Precios</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Sin Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 8%;">P. Con Imp.</th>
                                        <th class="py-2 small fw-bold text-muted text-end" style="width: 6%;">Desc.</th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width: 6%;">Iva</th>
                                        <?php if (!empty($empresa['obligatorio_lotes']) && ($empresa['obligatorio_lotes'] === 'true' || $empresa['obligatorio_lotes'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Lote</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_caducidad']) && ($empresa['obligatorio_caducidad'] === 'true' || $empresa['obligatorio_caducidad'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">Caducidad</th>
                                        <?php endif; ?>
                                        <?php if (!empty($empresa['obligatorio_nup']) && ($empresa['obligatorio_nup'] === 'true' || $empresa['obligatorio_nup'] === true)): ?>
                                            <th class="py-2 small fw-bold text-muted text-center" style="width:9%;">NUP / Serial</th>
                                        <?php endif; ?>
                                        <th class="py-2 small fw-bold text-muted text-end pe-4" style="width: 10%;">Subtotal</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cw_tbodyDetalle"></tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="cwAgregarLinea()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="small fw-bold text-muted pe-3">Items: <span id="cw-count-items">0</span></div>
                        </div>
                    </div>

                    <div class="row g-2 mt-2">
                        <!-- Información Adicional (pestaña, igual que factura de venta) -->
                        <div class="col-12 col-md-7">
                            <ul class="nav nav-tabs nav-tabs-sm mb-0" role="tablist">
                                <li class="nav-item"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#cw-subtab-info" type="button"><i class="bi bi-info-circle me-1"></i>Info. Adicional</button></li>
                            </ul>
                            <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height:120px;">
                                <div class="tab-pane fade show active" id="cw-subtab-info" role="tabpanel">
                                    <div class="border rounded-2 overflow-hidden bg-white">
                                        <div class="table-responsive" style="max-height: 200px;">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                        <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                        <th class="py-0" style="width: 10%;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="cw_info_body"></tbody>
                                            </table>
                                        </div>
                                        <div class="p-1 border-top bg-light">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="cwAgregarInfo()">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Totales (estilo factura) -->
                        <div class="col-12 col-md-5">
                            <div class="border rounded-3 p-2 bg-light small">
                                <div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted">Subtotal</span><span id="cw-lbl-subtotal">0.00</span></div>
                                <div id="cw-lbl-subtotales-iva"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 text-danger"><span>Descuento</span><span id="cw-lbl-descuento">0.00</span></div>
                                <div id="cw-lbl-ivas-grupo"></div>
                                <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="cw-lbl-ice-row"><span class="text-muted">(+) ICE</span><span id="cw-lbl-ice">0.00</span></div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL</span><span id="cw-lbl-total">0.00</span></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cw_btn_eliminar" onclick="cwEliminar()"><i class="bi bi-trash"></i> Eliminar</button>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="cw_btn_guardar" onclick="cwGuardar()"><i class="bi bi-save me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = window.RUTA_MODULO_CW;
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal, vehTimer = null, cliTimer = null, prodTimers = {};
    let CW_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '' };

    function getModal() { if (!modal) modal = new bootstrap.Modal(document.getElementById('modalOrdenCW')); return modal; }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function cwFocus(id) { const el = document.getElementById(id); if (el) { try { el.focus(); if (el.select) el.select(); } catch (e) {} } }

    // ─── Reset / apertura ─────────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('formOrdenCW').reset();
        ['cw_id','cw_id_vehiculo','cw_id_cliente','cw_serie','cw_id_punto_emision','cw_id_establecimiento','cw_numero_orden'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cw_secuencial').value = '';
        document.getElementById('cw_secuencial').dataset.sec = '';
        const selSerie = document.getElementById('cw_select_serie');
        if (selSerie) { selSerie.selectedIndex = 0; selSerie.disabled = false; }
        document.getElementById('cw_tbodyDetalle').innerHTML = '';
        document.getElementById('cw_info_body').innerHTML = '';
        document.getElementById('cw_info_cliente').innerHTML = '';
        CW_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '' };
        cwToggleDocBtns(false);
        cwRecalcular();
    }

    function setEditable(editable) {
        ['cw_fecha_ingreso','cw_select_serie','cw_vehiculo_busqueda','cw_cliente_busqueda','cw_kilometraje',
         'cw_nivel_combustible','cw_proxima_cita','cw_id_bodega'].forEach(id => {
            const el = document.getElementById(id); if (el) el.disabled = !editable;
        });
        document.getElementById('cw_btn_guardar').classList.toggle('d-none', !editable);
    }

    window.cwAbrirNuevo = function () {
        resetForm();
        setEditable(true);
        document.getElementById('cwTitulo').innerHTML = '<i class="bi bi-droplet-half me-1 text-info"></i> Nueva orden de Car-Wash';
        pintarBadge('borrador', 'Borrador');
        document.getElementById('cw_btn_eliminar').classList.add('d-none');
        // fecha/hora local
        const d = new Date(); const off = d.getTimezoneOffset();
        document.getElementById('cw_fecha_ingreso').value = new Date(d.getTime() - off * 60000).toISOString().slice(0, 16);
        // Serie: arranca marcada (primer punto o favorito) y carga el secuencial (como factura).
        if (typeof window.aplicarFavoritosModal === 'function') { try { window.aplicarFavoritosModal('#modalOrdenCW'); } catch (e) {} }
        const selSerie = document.getElementById('cw_select_serie');
        if (selSerie.value) cwSerieChange();
        cwAgregarLinea();
        cwAgregarInfo();   // una línea de info general lista por defecto
        getModal().show();
        // El cursor empieza en Vehículo (tras la animación del modal).
        setTimeout(() => cwFocus('cw_vehiculo_busqueda'), 250);
    };

    window.cwAbrirVer = function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        cwAbrirVerId(row.id, false);
    };

    window.cwAbrirVerId = async function (id, irAFacturar) {
        resetForm();
        getModal().show();
        try {
            const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la orden.');
            const o = data.data;
            const yaFacturado = !!o.id_documento || o.estado === 'facturado' || o.estado === 'anulado';
            const editable = window.CW_PERM.actualizar && !yaFacturado;

            document.getElementById('cw_id').value = o.id;
            document.getElementById('cw_numero_orden').value = o.numero_orden || '';
            document.getElementById('cwTitulo').innerHTML = '<i class="bi bi-droplet-half me-1 text-info"></i> Orden ' + (o.numero_orden || '');
            pintarBadge(o.estado, o.estado);
            CW_CUR = { id: o.id, id_documento: o.id_documento || 0, tipo_documento: o.tipo_documento || '', estado: o.estado || '' };

            // Serie / secuencial (se conserva la numeración de la orden; el selector queda bloqueado).
            const selSerie = document.getElementById('cw_select_serie');
            if (o.id_punto_emision && selSerie.querySelector(`option[value="${o.id_punto_emision}"]`)) selSerie.value = o.id_punto_emision;
            selSerie.disabled = true;
            document.getElementById('cw_serie').value = (o.establecimiento || '') + '-' + (o.punto_emision || '');
            document.getElementById('cw_id_punto_emision').value = o.id_punto_emision || '';
            document.getElementById('cw_id_establecimiento').value = o.id_establecimiento || '';
            document.getElementById('cw_secuencial').value = o.secuencial || '';
            document.getElementById('cw_secuencial').dataset.sec = o.secuencial || '';

            if (o.fecha_ingreso) document.getElementById('cw_fecha_ingreso').value = String(o.fecha_ingreso).replace(' ', 'T').slice(0, 16);
            document.getElementById('cw_id_vehiculo').value = o.id_vehiculo || '';
            document.getElementById('cw_vehiculo_busqueda').value = (o.placa || '') + (o.marca ? ' — ' + o.marca : '');
            document.getElementById('cw_id_cliente').value = o.id_cliente || '';
            document.getElementById('cw_cliente_busqueda').value = o.id_cliente ? ((o.cliente_identificacion || '') + ' — ' + (o.cliente_nombre || '')) : '';
            cwPintarInfoCliente(o);
            document.getElementById('cw_kilometraje').value = o.kilometraje || '';
            document.getElementById('cw_nivel_combustible').value = o.nivel_combustible || '';
            document.getElementById('cw_id_bodega').value = o.id_bodega || '';
            document.getElementById('cw_proxima_cita').value = o.proxima_cita ? String(o.proxima_cita).slice(0, 10) : '';

            (o.detalles || []).forEach(d => cwCargarLineaGuardada(d));
            (o.info_adicional || []).forEach(ia => cwAgregarInfo(ia));
            // Novedades antiguas (órdenes previas) se muestran como líneas de Info. Adicional.
            (o.novedades || []).forEach(n => cwAgregarInfo({ nombre: 'Novedad', valor: n.descripcion }));
            if (!(o.detalles || []).length) cwAgregarLinea();
            cwCalcTotales();

            setEditable(editable);
            document.getElementById('cw_btn_eliminar').classList.toggle('d-none', !(window.CW_PERM.eliminar && !o.id_documento));

            // Botones de documento: generar si es borrador sin documento; PDF/correo/wa si ya hay documento.
            cwToggleDocBtns(true, o);
            if (irAFacturar && !o.id_documento) {
                document.getElementById('cw_btn_factura').classList.add('shadow');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    function pintarBadge(estado, label) {
        estado = estado || 'borrador';
        const nombres = { borrador: 'Borrador', facturado: 'Facturado', anulado: 'Anulado' };
        const b = document.getElementById('cw_estado_badge');
        b.classList.remove('d-none');
        b.textContent = nombres[estado] || label || estado;
        let cls = 'bg-warning bg-opacity-10 text-warning'; // borrador
        if (estado === 'facturado') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'anulado') cls = 'bg-danger bg-opacity-10 text-danger';
        b.className = 'badge ms-2 ' + cls;
    }

    function cwPintarInfoCliente(o) {
        const parts = [];
        if (o.cliente_direccion) parts.push('<i class="bi bi-geo-alt"></i> ' + esc(o.cliente_direccion));
        if (o.cliente_email) parts.push('<i class="bi bi-envelope"></i> ' + esc(o.cliente_email));
        if (o.cliente_telefono) parts.push('<i class="bi bi-telephone"></i> ' + esc(o.cliente_telefono));
        document.getElementById('cw_info_cliente').innerHTML = parts.length
            ? '<span class="text-muted">' + parts.join(' &nbsp; ') + '</span>' : '';
    }

    // Habilita/deshabilita los botones de documento según el estado de la orden.
    function cwToggleDocBtns(mostrar, o) {
        const hayDoc = mostrar && o && !!o.id_documento;
        const puedeFacturar = mostrar && o && !o.id_documento && (o.estado || 'borrador') === 'borrador' && window.CW_PERM.crear;
        const esFactura = hayDoc && (o.tipo_documento === 'FACTURA');
        const ordenGuardada = !!(document.getElementById('cw_id').value);
        ['cw_btn_factura','cw_btn_recibo'].forEach(id => { const b = document.getElementById(id); if (b) b.disabled = !puedeFacturar; });
        const bp = document.getElementById('cw_btn_pdf'); if (bp) bp.disabled = !ordenGuardada; // PDF de la orden
        const bc = document.getElementById('cw_btn_correo'); if (bc) bc.disabled = !ordenGuardada; // Correo de la orden
        const bw = document.getElementById('cw_btn_whatsapp'); if (bw) bw.disabled = !esFactura;
    }

    // ─── Serie / secuencial (mismas reglas que recibo de venta) ───────────────
    window.cwSerieChange = async function () {
        const sel = document.getElementById('cw_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        if (!idPunto || !opt) {
            document.getElementById('cw_serie').value = '';
            document.getElementById('cw_id_punto_emision').value = '';
            document.getElementById('cw_id_establecimiento').value = '';
            document.getElementById('cw_secuencial').value = '';
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('cw_serie').value = est + '-' + punto;
        document.getElementById('cw_id_punto_emision').value = idPunto;
        document.getElementById('cw_id_establecimiento').value = opt.dataset.idEst || '';
        await cwCargarSecuencial(idPunto);
    };
    async function cwCargarSecuencial(idPunto) {
        try {
            const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
            const data = await res.json();
            if (!data.ok) { document.getElementById('cw_secuencial').value = ''; Swal.fire('Atención', data.msg || 'No hay secuencial disponible.', 'warning'); return; }
            const sec = data.formateado || String(data.secuencial || '').padStart(9, '0');
            document.getElementById('cw_secuencial').value = sec;
            document.getElementById('cw_secuencial').dataset.sec = data.secuencial || '';
        } catch (e) { document.getElementById('cw_secuencial').value = ''; }
    }

    // ─── Vehículo ─────────────────────────────────────────────────────────────
    window.cwBuscarVehiculos = function (q) {
        clearTimeout(vehTimer);
        const dd = document.getElementById('cw_veh_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        vehTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarVehiculosAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(v => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="fw-bold text-primary small">${esc(v.placa || '')}</span>
                               <span class="small ms-2">${esc(v.marca || '')}</span>
                               <span class="small text-muted ms-1">${v.propietario ? '· ' + esc(v.propietario) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); cwSeleccionarVehiculo(v); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados. Use "Vehículo" para crear uno nuevo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    function cwSeleccionarVehiculo(v) {
        document.getElementById('cw_id_vehiculo').value = v.id;
        document.getElementById('cw_vehiculo_busqueda').value = (v.placa || '') + (v.marca ? ' — ' + v.marca : '');
        document.getElementById('cw_veh_dropdown').classList.add('d-none');
        // snapshot en dataset para el guardado
        const inp = document.getElementById('cw_vehiculo_busqueda');
        inp.dataset.placa = v.placa || '';
        inp.dataset.marca = v.marca || '';
        inp.dataset.modelo = v.modelo || '';
    }

    // ─── Cliente ──────────────────────────────────────────────────────────────
    window.cwBuscarClientes = function (q) {
        clearTimeout(cliTimer);
        const dd = document.getElementById('cw_cli_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        cliTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small fw-semibold">${esc(c.nombre || '')}</span>
                               <span class="small text-muted ms-1">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); cwSeleccionarCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados. Use "Cliente" para crear uno nuevo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    function cwSeleccionarCliente(c) {
        document.getElementById('cw_id_cliente').value = c.id;
        document.getElementById('cw_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        document.getElementById('cw_cli_dropdown').classList.add('d-none');
        cwPintarInfoCliente({ cliente_direccion: c.direccion, cliente_email: c.correo, cliente_telefono: c.telefono });
        // Agrega/actualiza el correo del cliente en Info. Adicional (igual que factura).
        cwActualizarInfoCorreoCliente(c.correo || '');
    }

    // Fila fija con el correo del cliente en Info. Adicional (se actualiza al cambiar de cliente).
    window.cwActualizarInfoCorreoCliente = function (email) {
        const tbody = document.getElementById('cw_info_body');
        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');
        if (!fila) {
            // Reutiliza una línea de correo ya guardada (evita duplicados al reseleccionar cliente).
            fila = Array.from(tbody.querySelectorAll('tr.row-info-adicional')).find(r => (r.querySelector('.input-info-concepto')?.value || '').trim().toLowerCase() === 'correo del cliente');
            if (fila) fila.dataset.tipo = 'correo-cliente';
        }
        email = (email || '').trim();
        if (!email) { if (fila) fila.remove(); return; }
        if (fila) { fila.querySelector('.input-info-detalle').value = email; return; }
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.dataset.tipo = 'correo-cliente';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Correo del cliente" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${esc(email)}"></td>
            <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
        tbody.appendChild(tr);
    };

    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#cw_vehiculo_busqueda') && !e.target.closest('#cw_veh_dropdown'))
            document.getElementById('cw_veh_dropdown')?.classList.add('d-none');
        if (!e.target.closest('#cw_cliente_busqueda') && !e.target.closest('#cw_cli_dropdown'))
            document.getElementById('cw_cli_dropdown')?.classList.add('d-none');
    });

    // ─── Grilla de ítems (portada de Factura de Venta) ────────────────────────
    const EMPRESA_CONFIG = window.EMPRESA_CONFIG || {};
    const TARIFAS_IVA = window.TARIFAS_IVA || [];
    const UNIDADES = window.UNIDADES || [];
    const DEC_PRECIO = EMPRESA_CONFIG.decimales_precio ?? 2;
    const r2 = v => Math.round(v * 100) / 100;
    function cwDebounce(fn, wait) { let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), wait); }; }

    // Opciones de bodega para cada línea (por defecto, la bodega de la cabecera).
    function cwOpcionesBodega(idSel) {
        const bods = window.CW_BODEGAS || [];
        const def = idSel || (document.getElementById('cw_id_bodega') ? document.getElementById('cw_id_bodega').value : '');
        let html = '<option value="">— Bodega —</option>';
        bods.forEach(b => { html += `<option value="${b.id}" ${String(b.id) === String(def) ? 'selected' : ''}>${esc(b.nombre)}</option>`; });
        return html;
    }

    // Al cambiar la bodega de una línea se recalcula su saldo disponible.
    window.cwBodegaLineaChange = function (el) { cwActualizarSaldoFila(el.closest('tr')); };

    // Muestra el saldo del producto de la fila en la bodega elegida.
    async function cwActualizarSaldoFila(tr) {
        if (!tr) return;
        const cont = tr.querySelector('.span-saldo-info');
        const lbl  = tr.querySelector('.lbl-saldo-valor');
        if (!cont || !lbl) return;
        const idProd = tr.querySelector('.input-id-producto') ? tr.querySelector('.input-id-producto').value : '';
        const idBod  = tr.querySelector('.input-bodega') ? tr.querySelector('.input-bodega').value : '';
        if (!idProd || !idBod || tr.dataset.controlaStock !== '1') { cont.classList.add('d-none'); return; }
        try {
            const idOrd = document.getElementById('cw_id').value || 0;
            const res = await fetch(`${RUTA}/getStockAjax?id_producto=${idProd}&id_bodega=${idBod}&id_orden=${idOrd}`);
            const data = await res.json();
            if (!data.ok) { cont.classList.add('d-none'); return; }
            const st = parseFloat(data.stock || 0);
            lbl.textContent = st.toFixed(2);
            cont.classList.remove('d-none');
            cont.classList.toggle('text-danger', st <= 0);
            cont.classList.toggle('text-muted', st > 0);
        } catch (e) { cont.classList.add('d-none'); }
    }

    // Crea una fila vacía de la grilla y cablea su búsqueda de producto.
    window.cwAgregarLinea = function () {
        const tbody = document.getElementById('cw_tbodyDetalle');
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.innerHTML = `
            <td class="ps-3 position-relative">
                <input type="text" class="form-control form-control-sm input-detalle input-descripcion" placeholder="${EMPRESA_CONFIG.facturacion_libre ? 'Escribe o busca un servicio/producto...' : 'Buscar servicio o producto...'}">
                <input type="hidden" class="input-id-producto">
                <input type="hidden" class="input-codigo">
                <input type="hidden" class="input-es-libre" value="0">
                <input type="hidden" class="input-ice-pct" value="0">
                <input type="hidden" class="input-ice-val" value="0">
                <input type="hidden" class="input-precio-base-original" value="0">
                <input type="hidden" class="input-factor-original" value="1">
                <div class="mt-1 container-variante d-none">
                    <select class="form-select form-select-sm input-detalle input-variante" style="font-size:0.7rem; height:24px; padding:0 5px;"><option value="">Variantes...</option></select>
                </div>
                <div class="mt-1 small fw-bold text-muted span-saldo-info d-none" style="font-size:0.68rem;">
                    <i class="bi bi-box-seam me-1 text-primary"></i>Saldo: <span class="lbl-saldo-valor">0.00</span>
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm input-detalle input-adicional text-muted fst-italic" placeholder="Info adicional"></td>
            <td class="${EMPRESA_CONFIG.mostrar_unidad_medida ? '' : 'd-none'}">
                <select class="form-select form-select-sm input-detalle input-medida d-none"><option value="">Medida</option></select>
            </td>
            <td><select class="form-select form-select-sm input-detalle input-bodega" onchange="cwBodegaLineaChange(this)">${cwOpcionesBodega()}</select></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="1" step="any" oninput="cwCalcFila(this)"></td>
            <td><select class="form-select form-select-sm input-detalle input-lista-precios"><option value="">P. Base</option></select></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="cwCalcSinImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio-iva" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="cwCalcConImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="0.00" step="any" oninput="cwCalcFila(this)" ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'readonly'}></td>
            <td>
                <select class="form-select form-select-sm input-detalle text-center input-iva" onchange="cwSyncPrecioIva(this)" ${EMPRESA_CONFIG.editar_iva_factura ? '' : 'disabled'}>
                    ${TARIFAS_IVA.map(t => `<option value="${t.porcentaje_iva}" data-codigo="${t.codigo}" data-id="${t.id}">${t.tarifa}</option>`).join('')}
                </select>
            </td>
            ${EMPRESA_CONFIG.obligatorio_lotes ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-lote d-none" style="font-size:0.75rem;"><option value="">Seleccionar Lote</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_caducidad ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-caducidad d-none" style="font-size:0.75rem;"><option value="">Seleccionar Vencimiento</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_nup ? `<td class="align-middle" style="min-width:100px;"><input type="text" class="form-control form-control-sm input-detalle input-nup d-none" placeholder="NUP/Serial" style="font-size:0.75rem;"></td>` : ''}
            <td class="text-end pe-4 align-middle"><span class="subtotal-line">0.00</span></td>
            <td class="text-center p-0 align-middle" style="width:40px;">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); cwCalcTotales();" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button>
            </td>`;
        tbody.appendChild(tr);

        const inputDesc = tr.querySelector('.input-descripcion');
        const dropdownGlobal = document.getElementById('cw-dropdown-productos-global');

        const buscarProducto = async (q, sourceInput) => {
            q = (q || '').trim();
            if (q.length < 2) { dropdownGlobal.classList.add('d-none'); return; }
            const rect = sourceInput.getBoundingClientRect();
            dropdownGlobal.style.top = `${rect.bottom + 2}px`;
            dropdownGlobal.style.left = `${rect.left}px`;
            dropdownGlobal.style.width = `${Math.max(rect.width, 350)}px`;
            dropdownGlobal.classList.remove('d-none');
            dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
            try {
                // Stock según la bodega de ESTA línea (si no tiene, la de la cabecera).
                const idBod = (tr.querySelector('.input-bodega') && tr.querySelector('.input-bodega').value)
                    || document.getElementById('cw_id_bodega').value || 0;
                const idOrd = document.getElementById('cw_id').value || 0;
                const resp = await fetch(`${RUTA}/getProductosAjax?q=${encodeURIComponent(q)}&id_bodega=${idBod}&id_orden=${idOrd}`);
                const json = await resp.json();
                dropdownGlobal.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(p => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                        // Stock disponible (solo productos que controlan inventario)
                        let stockBadge = '';
                        if (p.controla_stock) {
                            const st = parseFloat(p.stock_actual || 0);
                            const cls = st > 0 ? 'success' : 'danger';
                            stockBadge = `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 me-1">Stock: ${st.toFixed(2)}</span>`;
                        }
                        b.innerHTML = `<div class="d-flex justify-content-between align-items-center text-start">
                                <div class="pe-3"><div class="fw-bold text-dark">${esc(p.nombre)}</div>
                                <div class="x-small text-muted">${esc(p.codigo || '')} ${p.codigo_barras ? '| ' + esc(p.codigo_barras) : ''}</div></div>
                                <div class="text-nowrap">${stockBadge}<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">$${parseFloat(p.precio_base || 0).toFixed(2)}</span></div></div>`;
                        b.onmousedown = (evt) => {
                            evt.preventDefault();
                            if (p.controla_stock && parseFloat(p.stock_actual || 0) <= 0) {
                                Swal.fire({ icon: 'warning', title: 'Sin stock', text: `"${p.nombre}" no tiene stock disponible en la bodega seleccionada.`, timer: 2600, showConfirmButton: false, target: document.getElementById('modalOrdenCW') });
                            }
                            cwSeleccionarProductoEnFila(p, tr);
                            dropdownGlobal.classList.add('d-none');
                        };
                        dropdownGlobal.appendChild(b);
                    });
                    if (EMPRESA_CONFIG.facturacion_libre) cwAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                } else {
                    if (EMPRESA_CONFIG.facturacion_libre) cwAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                    else dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catálogo</div>';
                }
            } catch (err) { console.error('Error productos', err); }
        };

        inputDesc.addEventListener('input', cwDebounce((e) => buscarProducto(e.target.value, inputDesc), 400));
        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) { e.preventDefault(); firstBtn.onmousedown(new MouseEvent('mousedown')); }
            }
        });
        inputDesc.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));
        inputDesc.addEventListener('blur', () => {
            if (!EMPRESA_CONFIG.facturacion_libre) return;
            const idProd = tr.querySelector('.input-id-producto').value;
            const desc = inputDesc.value.trim();
            if (!idProd && desc.length > 0) cwSeleccionarItemLibre(desc, tr);
        });

        cwCalcTotales();
        return tr;
    };

    window.cwSeleccionarProductoEnFila = function (p, row) {
        row.querySelector('.input-codigo').value = p.codigo || '';
        row.querySelector('.input-descripcion').value = p.nombre || '';
        row.querySelector('.input-precio').value = parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO);
        row.querySelector('.input-id-producto').value = p.id;
        row.dataset.idProducto = p.id;
        row.dataset.tipoProduccion = p.tipo_produccion || '01';
        row.dataset.inventariable = p.inventariable;
        row.dataset.controlaStock = p.controla_stock ? '1' : '0';
        row.querySelector('.input-es-libre').value = '0';
        row.querySelector('.input-precio-base-original').value = p.precio_base || 0;
        row.querySelector('.input-ice-pct').value = p.valor_ice || 0;
        row.querySelector('.input-ice-val').value = 0;
        row.classList.remove('table-warning');

        // Variantes
        const selVar = row.querySelector('.input-variante');
        const contVar = row.querySelector('.container-variante');
        if (p.variantes && p.variantes.length > 0) {
            if (contVar) contVar.classList.remove('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
            p.variantes.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.precio_adicional || 0;
                opt.textContent = `${v.nombre}: ${v.valor} (+${parseFloat(v.precio_adicional || 0).toFixed(2)})`;
                opt.dataset.nombre = v.nombre; opt.dataset.valor = v.valor;
                selVar.appendChild(opt);
            });
            selVar.onchange = () => {
                const base = parseFloat(row.querySelector('.input-precio-base-original').value) || 0;
                const add = parseFloat(selVar.value) || 0;
                row.querySelector('.input-precio').value = (base + add).toFixed(DEC_PRECIO);
                const opt = selVar.options[selVar.selectedIndex];
                row.querySelector('.input-adicional').value = opt.value ? `${opt.dataset.nombre}: ${opt.dataset.valor}` : '';
                cwSyncPrecioIva(row.querySelector('.input-precio'));
            };
        } else {
            if (contVar) contVar.classList.add('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
        }

        // IVA: por id de tarifa o por porcentaje
        let pctFinal = null;
        if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) pctFinal = parseFloat(p.porcentaje_iva);
        else if (p.tarifa_iva) { const tf = TARIFAS_IVA.find(t => t.id == p.tarifa_iva); if (tf) pctFinal = parseFloat(tf.porcentaje_iva); }
        const selIva = row.querySelector('.input-iva');
        if (selIva) {
            let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;
            if (!opt && pctFinal !== null) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
            if (opt) selIva.selectedIndex = opt.index;
            else if (pctFinal === 0) selIva.value = '0';
        }

        // Medidas
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) {
            if (p.id_tipo_medida || p.id_medida) {
                selMedida.classList.remove('d-none');
                selMedida.innerHTML = '';
                let compatibles = [];
                if (p.id_tipo_medida) compatibles = UNIDADES.filter(u => u.id_tipo == p.id_tipo_medida);
                if (compatibles.length === 0 && p.id_medida) { const ub = UNIDADES.find(u => u.id == p.id_medida); if (ub) compatibles = [ub]; }
                if (compatibles.length > 0) {
                    compatibles.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id; opt.textContent = u.nombre; opt.dataset.factor = u.factor_base || 1;
                        if (u.id == p.id_medida) { opt.selected = true; row.querySelector('.input-factor-original').value = u.factor_base || 1; }
                        selMedida.appendChild(opt);
                    });
                } else { selMedida.classList.add('d-none'); }
            } else { selMedida.classList.add('d-none'); selMedida.innerHTML = '<option value="">...</option>'; }
        }

        // Lista de precios
        const selPrecios = row.querySelector('.input-lista-precios');
        selPrecios.innerHTML = '';
        const optBase = document.createElement('option');
        optBase.value = p.precio_base; optBase.textContent = `P. Base ($${parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO)})`;
        selPrecios.appendChild(optBase);
        if (p.precios_lista && p.precios_lista.length > 0) {
            p.precios_lista.forEach(pl => {
                const opt = document.createElement('option');
                opt.value = pl.precio; opt.textContent = `${pl.nombre_precio} ($${parseFloat(pl.precio || 0).toFixed(DEC_PRECIO)})`;
                selPrecios.appendChild(opt);
            });
        }
        selPrecios.onchange = () => { row.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(DEC_PRECIO); cwSyncPrecioIva(row.querySelector('.input-precio')); };

        cwSyncPrecioIva(row.querySelector('.input-precio'));
        cwCalcFila(row.querySelector('.input-cantidad'));
        cwActualizarSaldoFila(row);
        const inCant = row.querySelector('.input-cantidad'); inCant.focus(); inCant.select();
    };

    window.cwAgregarOpcionServicioLibre = function (texto, tr, dropdown) {
        const sep = document.createElement('div');
        sep.className = 'list-group-item py-1 text-muted x-small border-top bg-light';
        sep.textContent = 'ó facturar como servicio libre:';
        dropdown.appendChild(sep);
        const bLibre = document.createElement('button');
        bLibre.type = 'button';
        bLibre.className = 'list-group-item list-group-item-action py-2 border-0 bg-warning bg-opacity-10';
        bLibre.innerHTML = `<div class="d-flex align-items-center gap-2 text-start"><i class="bi bi-lightning-charge-fill text-warning fs-6"></i>
            <div><div class="fw-bold small text-dark">"${esc(texto)}"</div><div class="x-small text-muted">Registrar como servicio libre (se creará al guardar)</div></div></div>`;
        bLibre.onmousedown = (evt) => { evt.preventDefault(); cwSeleccionarItemLibre(texto, tr); dropdown.classList.add('d-none'); };
        dropdown.appendChild(bLibre);
    };

    window.cwSeleccionarItemLibre = function (descripcion, row) {
        row.querySelector('.input-descripcion').value = descripcion;
        row.querySelector('.input-id-producto').value = '';
        row.querySelector('.input-codigo').value = '__LIBRE__';
        row.querySelector('.input-es-libre').value = '1';
        row.dataset.tipoProduccion = '02';
        row.dataset.inventariable = 'false';
        const selIva = row.querySelector('.input-iva');
        if (selIva && selIva.options.length > 0) selIva.selectedIndex = 0;
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) selMedida.classList.add('d-none');
        row.classList.add('table-warning');
        row.title = 'Servicio libre - se creará en el catálogo al guardar';
        const inputPrecio = row.querySelector('.input-precio');
        if (inputPrecio) setTimeout(() => { inputPrecio.focus(); inputPrecio.select(); }, 50);
        cwCalcFila(row.querySelector('.input-cantidad'));
    };

    window.cwSyncPrecioIva = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(tr.querySelector('.input-cantidad'));
    };
    window.cwCalcSinImp = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(el);
    };
    window.cwCalcConImp = function (el) {
        const tr = el.closest('tr');
        const pCon = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio').value = (pCon / (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        cwCalcFila(el);
    };
    window.cwCalcFila = function (el) {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
        const subtotalNeto = r2(r2(cant * prec) - desc);
        tr.querySelector('.subtotal-line').textContent = subtotalNeto.toFixed(2);
        cwCalcTotales();
    };
    window.cwCalcTotales = function () {
        const modoIva = EMPRESA_CONFIG.calculo_iva || 'linea_linea';
        let subtotalGeneral = 0, descuentoTotal = 0;
        const grupos = {};
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva.options[selIva.selectedIndex];
            const ivaPct = parseFloat(optIva ? optIva.value : 0) || 0;
            const key = optIva ? (optIva.dataset.id || ivaPct) : ivaPct;
            const label = optIva ? optIva.text : '0%';
            const bruto = r2(cant * prec);
            const neto = r2(bruto - desc);
            subtotalGeneral = r2(subtotalGeneral + bruto);
            descuentoTotal = r2(descuentoTotal + desc);
            if (!grupos[key]) grupos[key] = { pct: ivaPct, label: label, base: 0, iva: 0 };
            grupos[key].base = r2(grupos[key].base + neto);
            if (modoIva === 'linea_linea') grupos[key].iva = r2(grupos[key].iva + r2(neto * ivaPct / 100));
        });
        if (modoIva === 'subtotal') Object.values(grupos).forEach(g => { g.iva = r2(g.base * g.pct / 100); });
        let ivaTotal = 0; Object.values(grupos).forEach(g => { ivaTotal = r2(ivaTotal + g.iva); });
        const total = r2((subtotalGeneral - descuentoTotal) + ivaTotal);

        const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        set('cw-lbl-subtotal', subtotalGeneral.toFixed(2));
        const contSub = document.getElementById('cw-lbl-subtotales-iva');
        if (contSub) { contSub.innerHTML = ''; Object.values(grupos).forEach(g => { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1 text-muted'; d.innerHTML = `<span>Subtotal ${g.label}</span><span>${g.base.toFixed(2)}</span>`; contSub.appendChild(d); }); }
        set('cw-lbl-descuento', descuentoTotal.toFixed(2));
        const contIva = document.getElementById('cw-lbl-ivas-grupo');
        if (contIva) { contIva.innerHTML = ''; Object.values(grupos).forEach(g => { if (g.pct > 0 && g.iva > 0) { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1'; d.innerHTML = `<span class="text-muted">(+) IVA ${g.pct}%</span><span>${g.iva.toFixed(2)}</span>`; contIva.appendChild(d); } }); }
        set('cw-lbl-total', total.toFixed(2));
        const cnt = document.getElementById('cw-count-items'); if (cnt) cnt.textContent = document.querySelectorAll('#cw_tbodyDetalle .row-detalle').length;
    };
    // Alias para llamadas previas.
    window.cwRecalcularGrilla = window.cwCalcTotales;

    // Carga un detalle guardado en una fila de la grilla.
    window.cwCargarLineaGuardada = function (d) {
        const tr = cwAgregarLinea();
        tr.dataset.idProducto = d.id_producto || '';
        tr.dataset.tipoProduccion = (d.tipo_linea === 'servicio') ? '02' : '01';
        tr.dataset.controlaStock = (d.id_producto && d.tipo_linea === 'producto') ? '1' : '0';
        const selBod = tr.querySelector('.input-bodega');
        if (selBod && d.id_bodega) selBod.value = d.id_bodega;
        tr.querySelector('.input-descripcion').value = d.descripcion || '';
        tr.querySelector('.input-id-producto').value = d.id_producto || '';
        tr.querySelector('.input-es-libre').value = (d.es_libre === true || d.es_libre === 't' || d.es_libre === 'true' || d.es_libre === 1) ? '1' : '0';
        tr.querySelector('.input-cantidad').value = d.cantidad != null ? parseFloat(d.cantidad) : 1;
        tr.querySelector('.input-precio').value = parseFloat(d.precio_unitario || 0).toFixed(DEC_PRECIO);
        tr.querySelector('.input-desc').value = parseFloat(d.descuento || 0).toFixed(2);
        const selIva = tr.querySelector('.input-iva');
        if (selIva) {
            let opt = d.id_tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == d.id_tarifa_iva) : null;
            if (!opt) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - parseFloat(d.porcentaje_iva || 0)) < 0.001);
            if (!opt && d.porcentaje_iva != null && d.porcentaje_iva !== '' && !isNaN(parseFloat(d.porcentaje_iva))) {
                // Tarifa histórica inactiva: se agrega la opción solo para este documento.
                const pctH = parseFloat(d.porcentaje_iva);
                opt = document.createElement('option');
                opt.value = pctH;
                opt.textContent = pctH + '%';
                selIva.appendChild(opt);
            }
            if (opt) selIva.selectedIndex = opt.index;
        }
        cwSyncPrecioIva(tr.querySelector('.input-precio'));
        cwCalcFila(tr.querySelector('.input-cantidad'));
        cwActualizarSaldoFila(tr);
    };


    // ─── Info. Adicional (igual que factura de venta) ─────────────────────────
    window.cwAgregarInfo = function (ia) {
        ia = ia || {};
        const tbody = document.getElementById('cw_info_body');
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Concepto..." value="${esc(ia.nombre || '')}"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Detalle..." value="${esc(ia.valor || '')}"></td>
            <td class="p-0 text-center pe-1"><button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();"><i class="bi bi-x-circle-fill"></i></button></td>`;
        const primeraFija = tbody.querySelector('tr[data-tipo]');
        if (primeraFija) tbody.insertBefore(tr, primeraFija);
        else tbody.appendChild(tr);
        if (!ia.nombre) tr.querySelector('.input-info-concepto').focus();
    };

    // ─── Totales (usa la grilla portada de factura) ───────────────────────────
    window.cwRecalcular = function () { cwCalcTotales(); };

    // ─── Guardar ──────────────────────────────────────────────────────────────
    window.cwGuardar = async function () {
        const idVeh = document.getElementById('cw_id_vehiculo').value;
        const idCli = document.getElementById('cw_id_cliente').value;
        // 1º Vehículo (obligatorio)
        if (!idVeh) { await Swal.fire('Atención', 'Seleccione un vehículo.', 'warning'); cwFocus('cw_vehiculo_busqueda'); return; }
        // El cliente es opcional al registrar la orden (obligatorio al facturar).
        // 2º Serie / secuencial (obligatorio)
        if (!document.getElementById('cw_id_punto_emision').value || !document.getElementById('cw_secuencial').value) {
            await Swal.fire('Atención', 'Seleccione la serie (punto de emisión).', 'warning'); cwFocus('cw_select_serie'); return;
        }

        const detalles = [];
        document.querySelectorAll('#cw_tbodyDetalle .row-detalle').forEach(tr => {
            const desc = (tr.querySelector('.input-descripcion')?.value || '').trim();
            const cant = num(tr.querySelector('.input-cantidad')?.value);
            if (!desc || cant <= 0) return;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva ? selIva.options[selIva.selectedIndex] : null;
            const idProd = tr.querySelector('.input-id-producto')?.value || '';
            const esLibre = (tr.querySelector('.input-es-libre')?.value === '1') || !idProd;
            const tipoProd = tr.dataset.tipoProduccion || '';
            detalles.push({
                id_producto: idProd || null,
                tipo_linea: (tipoProd === '02' || esLibre) ? 'servicio' : (idProd ? 'producto' : 'servicio'),
                es_libre: esLibre,
                descripcion: desc,
                id_bodega: (tr.querySelector('.input-bodega') && tr.querySelector('.input-bodega').value) || null,
                id_tarifa_iva: optIva ? (optIva.dataset.id || null) : null,
                cantidad: cant,
                precio_unitario: num(tr.querySelector('.input-precio')?.value),
                descuento: num(tr.querySelector('.input-desc')?.value),
                porcentaje_iva: optIva ? (parseFloat(optIva.value) || 0) : 0,
            });
        });
        // 3º Al menos un servicio/producto
        if (!detalles.length) {
            await Swal.fire('Atención', 'Agregue al menos un servicio o producto.', 'warning');
            let fila = document.querySelector('#cw_tbodyDetalle .row-detalle .input-descripcion');
            if (!fila) { cwAgregarLinea(); fila = document.querySelector('#cw_tbodyDetalle .row-detalle .input-descripcion'); }
            if (fila) fila.focus();
            return;
        }

        const info_adicional = [];
        document.querySelectorAll('#cw_info_body .row-info-adicional').forEach(row => {
            const nom = (row.querySelector('.input-info-concepto')?.value || '').trim();
            const val = (row.querySelector('.input-info-detalle')?.value || '').trim();
            if (nom && val) info_adicional.push({ nombre: nom, valor: val });
        });

        const vehInp = document.getElementById('cw_vehiculo_busqueda');
        const serie = document.getElementById('cw_serie').value;
        const payload = {
            id: document.getElementById('cw_id').value || null,
            id_establecimiento: document.getElementById('cw_id_establecimiento').value || null,
            id_punto_emision: document.getElementById('cw_id_punto_emision').value || null,
            establecimiento: (serie.split('-')[0] || ''),
            punto_emision: (serie.split('-')[1] || ''),
            secuencial: document.getElementById('cw_secuencial').dataset.sec || document.getElementById('cw_secuencial').value || '',
            id_vehiculo: parseInt(idVeh, 10),
            id_cliente: idCli ? parseInt(idCli, 10) : null,
            placa: vehInp.dataset.placa || (vehInp.value.split(' — ')[0] || ''),
            marca: vehInp.dataset.marca || '',
            modelo: vehInp.dataset.modelo || '',
            kilometraje: document.getElementById('cw_kilometraje').value,
            nivel_combustible: document.getElementById('cw_nivel_combustible').value,
            id_bodega: document.getElementById('cw_id_bodega').value || null,
            fecha_ingreso: (document.getElementById('cw_fecha_ingreso').value || '').replace('T', ' '),
            novedades_texto: '',
            observaciones: '',
            proxima_cita: document.getElementById('cw_proxima_cita').value,
            detalles, novedades: [], info_adicional
        };

        const btn = document.getElementById('cw_btn_guardar');
        const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Listo', text: data.msg, timer: 1400, showConfirmButton: false });
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            btn.disabled = false; btn.innerHTML = orig;
        }
    };

    // ─── Eliminar ─────────────────────────────────────────────────────────────
    window.cwEliminar = async function () {
        const id = document.getElementById('cw_id').value;
        if (!id) return;
        const c = await Swal.fire({ title: '¿Eliminar orden?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1200, showConfirmButton: false });
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Documento de venta (Factura / Recibo) ────────────────────────────────
    const CW_BASE = RUTA.replace(/\/modulos\/car-wash\/?$/, '');

    window.cwGenerarDocumento = async function (tipo) {
        const idOrden = document.getElementById('cw_id').value;
        if (!idOrden) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        if (CW_CUR.id_documento) { Swal.fire('Atención', 'Esta orden ya generó un documento.', 'warning'); return; }

        const formas = window.CW_FORMAS_PAGO || [];
        const optForma = formas.map(f => `<option value="${esc(f.codigo)}">${esc(f.nombre)}</option>`).join('') || '<option value="01">Efectivo</option>';
        const etq = tipo === 'FACTURA' ? 'Factura electrónica' : 'Recibo de venta';

        const { value: form } = await Swal.fire({
            title: 'Generar ' + etq,
            target: document.getElementById('modalOrdenCW'),
            html: `<div class="text-start">
                    <label class="form-label small fw-semibold mb-1">Forma de pago</label>
                    <select id="cwEmForma" class="form-select form-select-sm">${optForma}</select>
                    <div class="form-text">El inventario se descarga de la bodega seleccionada en la orden.</div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-receipt me-1"></i> Generar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => ({ forma_pago: document.getElementById('cwEmForma').value || '01', id_bodega: 0 })
        });
        if (!form) return;

        Swal.fire({ title: 'Generando ' + etq + '...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id_orden', idOrden);
            fd.append('tipo', tipo);
            fd.append('forma_pago', form.forma_pago);
            fd.append('id_bodega', form.id_bodega);
            const res = await fetch(`${RUTA}/generarDocumentoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo generar el documento.');

            CW_CUR.id_documento = data.id_documento;
            CW_CUR.tipo_documento = data.tipo_documento;
            CW_CUR.estado = 'facturado';
            pintarBadge('facturado', 'Facturado');
            setEditable(false);
            document.getElementById('cw_btn_eliminar').classList.add('d-none');
            cwToggleDocBtns(true, { id_documento: data.id_documento, tipo_documento: data.tipo_documento, estado: 'facturado' });
            if (typeof cwRecargarTablero === 'function') cwRecargarTablero();
            if (typeof cargarGrid === 'function') cargarGrid();

            const r = await Swal.fire({ icon: 'success', title: '¡Listo!', text: data.msg, showCancelButton: true, confirmButtonText: 'Ver PDF', cancelButtonText: 'Cerrar' });
            if (r.isConfirmed) cwPdfDocumento();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    // PDF de la orden (orden de servicio car-wash) — descarga directa.
    window.cwPdf = function () {
        const id = document.getElementById('cw_id').value || CW_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        const a = document.createElement('a');
        a.href = `${RUTA}/exportarPdfAjax?id=${id}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
    };
    // PDF del documento generado (factura/recibo).
    window.cwPdfDocumento = function () {
        if (!CW_CUR.id_documento) return;
        const ruta = CW_CUR.tipo_documento === 'FACTURA' ? 'factura-venta' : 'recibo-venta';
        window.open(`${CW_BASE}/modulos/${ruta}/exportarPdfAjax?id=${CW_CUR.id_documento}`, '_blank');
    };

    // Enviar el PDF de la orden por correo (mismo patrón que consignaciones).
    window.cwCorreo = async function () {
        const id = document.getElementById('cw_id').value || CW_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        const correoActual = (document.getElementById('cw_info_cliente').textContent.match(/[\w.+-]+@[\w-]+\.[\w.-]+/) || [''])[0];
        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correo(s) destino, separados por coma.',
            inputValue: correoActual,
            inputPlaceholder: 'cliente@correo.com',
            target: document.getElementById('modalOrdenCW'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            else Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
        } catch (e) { Swal.fire('Error', 'No se pudo enviar el correo.', 'error'); }
    };

    window.cwWhatsapp = async function () {
        if (!CW_CUR.id_documento || CW_CUR.tipo_documento !== 'FACTURA') return;
        try {
            const res = await fetch(`${CW_BASE}/modulos/factura-venta/getPlantillasWhatsappAjax?id_factura=${CW_CUR.id_documento}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudieron cargar las plantillas.');
            if (data.configurado === false) { Swal.fire('WhatsApp', 'Aún no tiene configurada la API de WhatsApp. Actívela en su módulo de WhatsApp.', 'info'); return; }
            const opts = (data.plantillas || []).map(p => `<option value="${p.id}">${esc(p.nombre)} (${esc(p.idioma)})</option>`).join('');
            const { value: form, isConfirmed } = await Swal.fire({
                title: 'Enviar por WhatsApp', target: document.getElementById('modalOrdenCW'),
                html: `<div class="text-start">
                        <label class="form-label small fw-semibold mb-1">Plantilla</label>
                        <select id="cwWaTpl" class="form-select form-select-sm mb-2">${opts || '<option value="">Sin plantillas</option>'}</select>
                        <label class="form-label small fw-semibold mb-1">Teléfono</label>
                        <input id="cwWaTel" class="form-control form-control-sm" value="${esc(data.telefono_cliente || '593')}">
                       </div>`,
                showCancelButton: true, confirmButtonText: 'Enviar',
                preConfirm: () => ({ id_plantilla: document.getElementById('cwWaTpl').value, telefono: document.getElementById('cwWaTel').value })
            });
            if (!isConfirmed || !form) return;
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, target: document.getElementById('modalOrdenCW'), didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('id_factura', CW_CUR.id_documento);
            fd.append('id_plantilla', form.id_plantilla);
            fd.append('telefono', form.telefono);
            const r2 = await fetch(`${CW_BASE}/modulos/factura-venta/enviarWhatsappAjax`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d2 = await r2.json();
            if (!d2.ok) throw new Error(d2.error || 'No se pudo enviar.');
            Swal.fire({ icon: 'success', title: '¡Enviado!', text: d2.mensaje || 'Mensaje enviado.', timer: 2200, showConfirmButton: false });
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Crear entidades al vuelo (reutiliza modales existentes) ───────────────
    window.cwCrearVehiculo = function () {
        if (typeof window.abrirModalVehiculoCrear === 'function') window.abrirModalVehiculoCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de vehículo.', 'warning');
    };
    window.cwCrearCliente = function () {
        if (typeof window.abrirModalClienteCrear === 'function') window.abrirModalClienteCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de cliente.', 'warning');
    };
    window.cwCrearProducto = function () {
        if (typeof window.abrirModalProductoCrear === 'function') window.abrirModalProductoCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de producto.', 'warning');
    };

    // Autoseleccionar la entidad recién creada (best-effort según el payload del evento).
    window.addEventListener('vehiculoGuardado', (e) => {
        const j = e.detail || {}; const v = j.data || j;
        if (v && v.id) cwSeleccionarVehiculo({ id: v.id, placa: v.placa, marca: v.marca, modelo: v.modelo });
        else if (v && v.placa) { document.getElementById('cw_vehiculo_busqueda').value = v.placa; cwBuscarVehiculos(v.placa); }
    });
    document.addEventListener('clienteGuardado', (e) => {
        const j = e.detail || {}; const c = j.data || j;
        if (c && c.id) cwSeleccionarCliente({ id: c.id, nombre: c.nombre || j.nombre, identificacion: c.identificacion, direccion: c.direccion, correo: c.correo || c.email, telefono: c.telefono });
    });
    document.addEventListener('productoGuardado', () => {
        // El nuevo producto queda disponible en el buscador de líneas; nada que autoseleccionar aquí.
    });
})();
</script>
