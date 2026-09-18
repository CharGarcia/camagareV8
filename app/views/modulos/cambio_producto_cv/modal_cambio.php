<?php
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalCambio" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="tituloModalCambio">
                    <i class="bi bi-arrow-left-right me-1"></i> Nuevo Cambio de productos
                </h5>
                <span id="cam_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body position-relative">
                <div id="cam-modal-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Cargando información del cambio...</div>
                </div>
                <!-- Barra de acciones superior (PDF / Excel / Correo / WhatsApp) y, a la derecha, el Estado -->
                <div class="d-flex gap-1 align-items-center flex-wrap mb-3 pb-2 border-bottom">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="camPdf()" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="camExcel()" title="Exportar Excel"><i class="bi bi-file-earmark-excel"></i></button>
                    <button type="button" class="btn btn-outline-info btn-sm px-2" onclick="camEmail()" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="camWhatsapp()" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>

                    <!-- Estado (solo al abrir un cambio ya guardado) -->
                    <div class="ms-auto d-flex align-items-center gap-2 d-none" id="cam_estado_wrapper">
                        <label for="cam_estado_selector" class="form-label small fw-bold mb-0 text-muted">Estado:</label>
                        <select id="cam_estado_selector" class="form-select form-select-sm fw-bold" style="width:auto;" onchange="camCambiarEstado(this.value)">
                            <option value="Borrador">Borrador</option>
                            <option value="Emitida">Emitida</option>
                            <option value="Anulada">Anulada</option>
                        </select>
                    </div>
                </div>

                <ul class="nav nav-tabs mb-3" id="tabsCambio" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="cam-tab-general-btn" data-bs-toggle="tab" href="#cam-tab-general" role="tab"><i class="bi bi-info-circle me-1"></i> General</a>
                    </li>
                    <?php if (\App\Helpers\AsientoPestana::puedeVer()): // solo con acceso a Contabilidad → Asientos Contables ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="cam-tab-asiento-btn" data-bs-toggle="tab" href="#cam-tab-asiento" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="tab-content" id="tabsCambioContent">
                <div class="tab-pane fade show active" id="cam-tab-general" role="tabpanel">
                <form id="formCambio" autocomplete="off">
                    <input type="hidden" id="cam_id">

                    <!-- Numeración + cliente -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Fecha</label>
                            <input type="date" id="cam_fecha_cambio" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">
                                Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo ?? 'modulos/cambio-producto-cv', 'cam_select_serie', 'id_punto_emision') ?>
                            </label>
                            <select id="cam_select_serie" class="form-select form-select-sm" onchange="camSerieChange()">
                                <?php if (empty($puntos)): ?>
                                    <option value="">— Sin secuencial configurado —</option>
                                <?php else: ?>
                                    <?php foreach ($puntos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                            <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" id="cam_serie">
                            <input type="hidden" id="cam_id_punto_emision">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Secuencial</label>
                            <input type="text" id="cam_secuencial" class="form-control form-control-sm bg-light text-center" readonly placeholder="000000000">
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label small mb-1">Cliente</label>
                            <input type="text" id="cam_cliente_busqueda" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o identificación..." oninput="camBuscarClientes(this.value)" autocomplete="off">
                            <input type="hidden" id="cam_id_cliente">
                            <input type="hidden" id="cam_cliente_email">
                            <div id="cam_clientes_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:240px; overflow:auto;"></div>
                        </div>
                    </div>

                    <!-- Motivo y Observaciones (el Estado va en la barra de acciones) -->
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Motivo</label>
                            <input type="text" id="cam_motivo" class="form-control form-control-sm" placeholder="Motivo del cambio (opcional)">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Observaciones</label>
                            <input type="text" id="cam_observaciones" class="form-control form-control-sm" placeholder="Observaciones (opcional)">
                        </div>
                    </div>

                    <!-- ── Productos que devuelve ─────────────────────────────── -->
                    <div class="border rounded-3 p-2 mb-2 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold text-success"><i class="bi bi-box-arrow-in-down me-1"></i> Productos que devuelve</h6>
                            <span id="cam_dev_info" class="small text-muted"></span>
                        </div>
                        <div class="position-relative mb-2" id="cam_dev_search_wrap">
                            <input type="text" id="cam_dev_busqueda" class="form-control form-control-sm" placeholder="NUP, lote, N° de factura de venta o de cambio, código o nombre del producto… (sin cliente busca en todos y lo fija con el ítem elegido)" oninput="camBuscarLineas(this.value)" onfocus="camBuscarLineas(this.value, true)" autocomplete="off">
                            <div id="cam_dev_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1075; max-height:320px; overflow:auto;"></div>
                        </div>
                        <div class="table-responsive border rounded-3 bg-white" style="max-height:26vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="tablaCamDev">
                                <thead class="table-light">
                                    <tr class="small">
                                        <th>Origen</th>
                                        <th>Producto</th>
                                        <th style="width:140px">Bodega</th>
                                        <th style="width:120px">Lote</th>
                                        <th style="width:150px">NUP</th>
                                        <th class="text-end" style="width:110px">Cantidad</th>
                                        <th style="width:34px"></th>
                                    </tr>
                                </thead>
                                <tbody id="cam_dev_body">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Seleccione un cliente y busque el producto a devolver.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Productos que entrega a cambio ─────────────────────── -->
                    <div class="border rounded-3 p-2 mb-2 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-box-arrow-up me-1"></i> Productos que entrega a cambio</h6>
                            <span id="cam_ent_info" class="small text-muted"></span>
                        </div>
                        <div class="position-relative mb-2" id="cam_ent_search_wrap">
                            <input type="text" id="cam_ent_busqueda" class="form-control form-control-sm" placeholder="N° de consignación: completo (001-001-000000012) o solo el secuencial (12)…" oninput="camBuscarEntregas(this.value)" onfocus="camBuscarEntregas(this.value, true)" autocomplete="off">
                            <div id="cam_ent_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1075; max-height:320px; overflow:auto;"></div>
                        </div>
                        <div class="table-responsive border rounded-3 bg-white" style="max-height:26vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="tablaCamEnt">
                                <thead class="table-light">
                                    <tr class="small">
                                        <th style="width:200px">Origen</th>
                                        <th>Producto</th>
                                        <th style="width:140px">Bodega</th>
                                        <th style="width:120px">Lote</th>
                                        <th style="width:150px">NUP</th>
                                        <th class="text-end" style="width:90px">Cantidad</th>
                                        <th style="width:34px"></th>
                                    </tr>
                                </thead>
                                <tbody id="cam_ent_body">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Busque por N° de consignación lo que se entrega a cambio.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
                </div><!-- /cam-tab-general -->

                <!-- Pestaña Asiento Contable (a costo) -->
                <?php if (\App\Helpers\AsientoPestana::puedeVer()): ?>
                <div class="tab-pane fade" id="cam-tab-asiento" role="tabpanel">
                    <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Asiento <strong>a costo</strong>: neto entre el reingreso de lo devuelto y la salida de lo entregado (<em>Inventario</em> contra <em>Costo de ventas</em>).</span>
                    </div>
                    <?php $prefijo = 'cam'; require MVC_APP . '/views/partials/asiento_tab.php'; ?>
                </div><!-- /cam-tab-asiento -->
                <?php endif; ?>
                </div><!-- /tabsCambioContent -->
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarCambio" onclick="camEliminar()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnGuardarCambio" onclick="camGuardar()">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = window.RUTA_MODULO_CAMBIO;
    const DEC_C = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_cantidad) || 2;
    let modal;
    let camClientesTimer = null, camDevTimer = null, camEntTimer = null;
    let camBodegas = [];   // cache de bodegas [{id,nombre}]
    let camBloquearSecuencial = false;   // en un cambio ya guardado el número no se recalcula
    let camSecuencialConfigurado = true; // ¿la serie activa tiene secuencial configurado?

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('modalCambio'));
        return modal;
    }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function esc(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

    // ─── Reset / modo ─────────────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('formCambio').reset();
        document.getElementById('cam_id').value = '';
        document.getElementById('cam_id_cliente').value = '';
        document.getElementById('cam_cliente_email').value = '';
        document.getElementById('cam_serie').value = '';
        document.getElementById('cam_id_punto_emision').value = '';
        vaciarDev(); vaciarEnt();
        document.getElementById('cam_dev_busqueda').value = '';
        document.getElementById('cam_ent_busqueda').value = '';
        camRecalcular();
    }
    function vaciarDev() {
        document.getElementById('cam_dev_body').innerHTML =
            '<tr class="cam-dev-empty"><td colspan="7" class="text-center text-muted py-3">Seleccione un cliente y busque el producto a devolver.</td></tr>';
        document.getElementById('cam_dev_info').textContent = '';
    }
    function vaciarEnt() {
        document.getElementById('cam_ent_body').innerHTML =
            '<tr class="cam-ent-empty"><td colspan="7" class="text-center text-muted py-3">Busque por N° de consignación lo que se entrega a cambio.</td></tr>';
        document.getElementById('cam_ent_info').textContent = '';
    }

    function setCamposEditables(editable) {
        // Los dos buscadores funcionan también SIN cliente: lo que se devuelve se localiza por
        // NUP, lote o número de documento, y lo que se entrega por el número de la consignación;
        // el cliente del cambio se fija con el ítem elegido.
        ['cam_select_serie','cam_fecha_cambio','cam_cliente_busqueda','cam_motivo','cam_observaciones',
         'cam_dev_busqueda','cam_ent_busqueda'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !editable;
        });
        document.getElementById('btnGuardarCambio').classList.toggle('d-none', !editable);
    }

    async function cargarBodegas() {
        if (camBodegas.length) return;
        try {
            const res = await fetch(`${RUTA}/getBodegasAjax`);
            const data = await res.json();
            camBodegas = (data.ok && data.data) ? data.data : [];
        } catch (e) { camBodegas = []; }
    }
    function bodegaOptions(sel) {
        return camBodegas.map(b => `<option value="${b.id}" ${String(b.id) === String(sel) ? 'selected' : ''}>${esc(b.nombre)}</option>`).join('');
    }

    window.abrirModalCambioNuevo = async function () {
        resetForm();
        camResetTabs();
        await cargarBodegas();
        setCamposEditables(true);
        document.getElementById('tituloModalCambio').innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Nuevo Cambio de productos';
        document.getElementById('cam_estado_badge').textContent = 'Nuevo';
        document.getElementById('cam_estado_badge').className = 'badge bg-secondary bg-opacity-10 text-secondary ms-2';
        document.getElementById('btnEliminarCambio').classList.add('d-none');
        document.getElementById('cam_estado_wrapper').classList.add('d-none');
        document.getElementById('cam_fecha_cambio').value = CMG_fechaLocal();

        document.getElementById('btnGuardarCambio').innerHTML = '<i class="bi bi-save me-1"></i> Guardar';

        const selSerie = document.getElementById('cam_select_serie');
        selSerie.disabled = false;
        camBloquearSecuencial = false;

        // Serie favorita del usuario (estrella), igual que en Facturas de Venta: se aplica
        // ANTES de pedir el secuencial para que el número corresponda a esa serie.
        if (typeof aplicarFavoritosModal === 'function') aplicarFavoritosModal('#modalCambio');
        if (!selSerie.value && selSerie.options.length) selSerie.selectedIndex = 0;
        await camSerieChange();

        getModal().show();
    };

    window.abrirModalCambioVer = async function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        const puedeActualizar = (<?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        const editable = (row.estado === 'Borrador') && puedeActualizar;

        resetForm();
        camResetTabs();
        await cargarBodegas();
        setCamposEditables(editable);
        document.getElementById('cam_id').value = row.id;
        document.getElementById('tituloModalCambio').innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Cambio ' + (row.serie || '') + '-' + (row.secuencial || '');
        camPintarBadge(row.estado);

        camBloquearSecuencial = true;   // documento ya numerado: no se recalcula la numeración
        const btnG = document.getElementById('btnGuardarCambio');
        btnG.innerHTML = '<i class="bi bi-save me-1"></i> Actualizar';

        const wrap = document.getElementById('cam_estado_wrapper');
        const selEstado = document.getElementById('cam_estado_selector');
        wrap.classList.remove('d-none');
        selEstado.value = row.estado;
        selEstado.dataset.prev = row.estado;
        selEstado.disabled = !puedeActualizar;

        const puedeEliminar = (<?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        document.getElementById('btnEliminarCambio').classList.toggle('d-none', !puedeEliminar);

        getModal().show();

        // Mostrar loader: la carga completa vía AJAX puede tardar y sin esto el
        // usuario ve el modal "vacío" y piensa que el cambio no tiene datos.
        document.getElementById('cam-modal-loader')?.classList.remove('d-none');

        await camCargarDetalle(row.id, editable);
    };

    function camPintarBadge(estado) {
        const badge = document.getElementById('cam_estado_badge');
        badge.textContent = estado;
        let cls = 'bg-secondary bg-opacity-10 text-secondary';
        if (estado === 'Emitida') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'Anulada') cls = 'bg-danger bg-opacity-10 text-danger';
        else if (estado === 'Borrador') cls = 'bg-warning bg-opacity-10 text-warning';
        badge.className = 'badge ms-2 ' + cls;
    }

    window.camCambiarEstado = async function (nuevo) {
        const sel = document.getElementById('cam_estado_selector');
        const id  = document.getElementById('cam_id').value;
        const prev = sel.dataset.prev || 'Emitida';
        if (!id || nuevo === prev) return;

        if (nuevo === 'Anulada' || (prev === 'Emitida' && nuevo === 'Borrador')) {
            const c = await Swal.fire({
                title: nuevo === 'Anulada' ? '¿Anular cambio?' : '¿Pasar a Borrador?',
                text: 'Se reversarán los movimientos de inventario de este cambio y se liberará el saldo.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar'
            });
            if (!c.isConfirmed) { sel.value = prev; return; }
        }

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('estado', nuevo);
            const res = await fetch(`${RUTA}/cambiarEstadoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cambiar el estado.');
            sel.dataset.prev = nuevo;
            camPintarBadge(nuevo);
            if (typeof cargarGrid === 'function') cargarGrid();
            Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1200, showConfirmButton: false });
        } catch (e) {
            sel.value = prev;
            Swal.fire('Error', e.message, 'error');
        }
    };

    // ─── Numeración ───────────────────────────────────────────────────────────
    window.camSerieChange = async function () {
        const sel = document.getElementById('cam_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        if (!idPunto || !opt) {
            document.getElementById('cam_serie').value = '';
            document.getElementById('cam_id_punto_emision').value = '';
            const inp = document.getElementById('cam_secuencial');
            inp.value = ''; inp.dataset.sec = '';
            inp.classList.remove('border-warning', 'border-danger');
            // Sin punto de emisión no hay numeración posible: avisar como en Facturas de Venta.
            if (!camBloquearSecuencial) {
                camSecuencialConfigurado = false;
                inp.placeholder = 'Sin serie';
                camAvisarSecuencialNoConfigurado('serie');
            }
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('cam_serie').value = est + '-' + punto;
        document.getElementById('cam_id_punto_emision').value = idPunto;
        await camCargarSecuencial(idPunto);
    };
    /** Aviso de serie/secuencial no configurados (mismo texto que Facturas de Venta). */
    function camAvisarSecuencialNoConfigurado(tipo) {
        const html = (tipo === 'serie')
            ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Puntos de emisión</strong> antes de emitir el cambio.'
            : 'No están configurados los secuenciales para esta serie.<br>Configúrelos en <strong>Empresa → Puntos de emisión</strong> antes de emitir el cambio.';
        Swal.fire({
            icon: 'warning', title: 'Secuencial no configurado', html,
            confirmButtonText: 'Entendido',
            target: document.getElementById('modalCambio'),
        });
    }

    async function camCargarSecuencial(idPunto) {
        // En un cambio ya guardado el número no se recalcula.
        if (camBloquearSecuencial) return;

        const inp = document.getElementById('cam_secuencial');

        if (!idPunto) {
            camSecuencialConfigurado = false;
            inp.value = ''; inp.dataset.sec = ''; inp.placeholder = 'Sin serie';
            inp.classList.remove('border-warning', 'border-danger');
            camAvisarSecuencialNoConfigurado('serie');
            return;
        }

        inp.placeholder = 'Cargando...';
        try {
            const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}&fecha=${encodeURIComponent(document.getElementById('cam_fecha_cambio')?.value || '')}`);
            const data = await res.json();
            if (!data.ok) {
                camSecuencialConfigurado = false;
                inp.value = ''; inp.dataset.sec = ''; inp.placeholder = '000000001';
                inp.classList.add('border-danger');
                camAvisarSecuencialNoConfigurado('secuencial');
                return;
            }

            inp.value = data.formateado || String(data.secuencial || '').padStart(9, '0');
            inp.dataset.sec = data.secuencial || '';
            inp.placeholder = '000000001';

            // Aviso visual cuando el número recuperado es un hueco de la numeración.
            if (data.es_gap) {
                inp.classList.add('border-warning');
                inp.title = data.detalle || 'Número faltante recuperado';
            } else {
                inp.classList.remove('border-warning');
                inp.title = data.detalle || 'Siguiente consecutivo';
            }

            camSecuencialConfigurado = (data.configurado !== false);
            if (!camSecuencialConfigurado) {
                inp.classList.add('border-danger');
                camAvisarSecuencialNoConfigurado('secuencial');
            } else {
                inp.classList.remove('border-danger');
            }
        } catch (e) {
            console.error('Error cargando secuencial', e);
            inp.placeholder = '000000001';
        }
    }

    // ─── Cliente ──────────────────────────────────────────────────────────────
    window.camBuscarClientes = function (q) {
        clearTimeout(camClientesTimer);
        const dd = document.getElementById('cam_clientes_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        camClientesTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small text-dark">${esc(c.nombre)}</span>
                               <span class="small text-muted ms-1">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); camSeleccionarCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados.</span>';
            }
            dd.classList.remove('d-none');
        }, 300);
    };
    /**
     * Limpia por completo la selección de cliente (input visible + ocultos + dropdown) y
     * las devoluciones, que dependen del cliente. Lo que se entrega a cambio se conserva.
     */
    function camLimpiarCliente() {
        document.getElementById('cam_cliente_busqueda').value = '';
        document.getElementById('cam_id_cliente').value = '';
        document.getElementById('cam_cliente_email').value = '';
        document.getElementById('cam_clientes_dropdown').classList.add('d-none');
        clearTimeout(camClientesTimer);
        camQuitarLineasDelCliente();
        document.getElementById('cam_dev_busqueda').value = '';
        document.getElementById('cam_dev_dropdown').classList.add('d-none');
        camRecalcular();
    }
    window.camLimpiarCliente = camLimpiarCliente;

    /**
     * Quita las líneas que dependen del cliente: todas las devoluciones (vienen de SUS
     * facturas de consignación/cambios). Lo que se entrega a cambio se conserva: puede salir de
     * la consignación de cualquier cliente (18-09-2026), así que no depende del cliente del cambio.
     */
    function camQuitarLineasDelCliente() {
        vaciarDev();
    }

    // Con un cliente ya fijado el input muestra una etiqueta ("identificación — nombre"):
    // Backspace/Delete limpian TODA la selección de una vez, no letra por letra
    // (CLAUDE.md §9, inputs de búsqueda con selección tipo "chip").
    document.getElementById('cam_cliente_busqueda').addEventListener('keydown', (e) => {
        if (e.key !== 'Backspace' && e.key !== 'Delete') return;
        if (!document.getElementById('cam_id_cliente').value) return;
        e.preventDefault();
        camLimpiarCliente();
    });

    function camSeleccionarCliente(c) {
        const prev = document.getElementById('cam_id_cliente').value;
        document.getElementById('cam_id_cliente').value = c.id;
        document.getElementById('cam_cliente_email').value = c.email || '';
        document.getElementById('cam_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        document.getElementById('cam_clientes_dropdown').classList.add('d-none');
        // Al CAMBIAR de cliente se limpian las líneas que dependen de él (las devoluciones).
        // Si el cliente se fija por primera vez a partir de un ítem buscado por NUP / número,
        // no hay nada que limpiar.
        if (prev && String(prev) !== String(c.id)) camQuitarLineasDelCliente();
        camRecalcular();
    }

    document.addEventListener('click', (e) => {
        [['cam_cliente_busqueda','cam_clientes_dropdown'],
         ['cam_dev_busqueda','cam_dev_dropdown'],
         ['cam_ent_busqueda','cam_ent_dropdown']].forEach(([inp, dd]) => {
            const el = document.getElementById(dd);
            if (el && !e.target.closest('#' + inp) && !e.target.closest('#' + dd)) el.classList.add('d-none');
        });
    });

    // ─── Helpers compartidos por los dos buscadores ───────────────────────────
    function camSwal(opts) {
        return Swal.fire(Object.assign({ target: document.getElementById('modalCambio') }, opts));
    }
    function camFechaCorta(f) {
        return f ? String(f).slice(0, 10).split('-').reverse().join('-') : '';
    }
    // 'FACTURA' = factura de consignación, pero el número que la acompaña es el de la
    // factura de venta que generó (CambioProductoCvRepository::sqlNumeroFacturaVenta).
    function camLabelOrigen(t) {
        return t === 'CAMBIO' ? 'Cambio' : (t === 'CONSIGNACION' ? 'Consignación' : (t === 'FACTURA' ? 'Factura' : 'Bodega'));
    }
    function camBadgeOrigen(t, texto, titulo) {
        const cls = t === 'CAMBIO' ? 'bg-info' : (t === 'CONSIGNACION' ? 'bg-warning' : (t === 'FACTURA' ? 'bg-secondary' : 'bg-primary'));
        return `<span class="badge ${cls} bg-opacity-25 text-dark"${titulo ? ` title="${esc(titulo)}"` : ''}>${esc(texto != null ? texto : camLabelOrigen(t))}</span>`;
    }
    /**
     * Origen de lo que ENTRA: el número de la factura de venta afectada. Si la unidad llegó en un
     * cambio anterior, el servidor resuelve esa factura (factura_afectada) y el cambio queda en el
     * título; si no la encuentra, se muestra el cambio. "Sin factura" si no hay factura de venta que
     * mostrar (igual que CambioProductoCvPdfService::etiquetaOrigen).
     */
    function camBadgeOrigenDev(tipo, numeroOrigen, facturaAfectada) {
        if (facturaAfectada) {
            return camBadgeOrigen(tipo, 'Factura ' + facturaAfectada, tipo === 'CAMBIO' ? 'Entregada en el cambio ' + (numeroOrigen || '') : '');
        }
        // Sin factura de venta que mostrar: devolución migrada que la migración no pudo enlazar a su
        // factura o de una factura de consignación que no tiene factura de venta enlazada.
        if (!tipo || (tipo === 'FACTURA' && !numeroOrigen)) {
            const motivo = !tipo
                ? 'Cambio migrado sin factura enlazada'
                : 'La factura de consignación de esta unidad no tiene factura de venta enlazada';
            return `<span class="badge bg-secondary bg-opacity-10 text-secondary" title="${motivo}">Sin factura</span>`;
        }
        return camBadgeOrigen(tipo, camLabelOrigen(tipo) + (numeroOrigen ? ' ' + numeroOrigen : ''));
    }
    /** Origen de lo que SALE desde una consignación: el número de esa consignación. */
    function camBadgeOrigenConsignacion(numero) {
        return camBadgeOrigen('CONSIGNACION', 'Consignación ' + (numero || ''));
    }
    function camLoteNup(l) {
        return [l.lote, l.nup].filter(Boolean).join(' / ') || '—';
    }

    /**
     * Fija el cliente del cambio a partir de una línea que se DEVUELVE (factura de consignación o
     * cambio previo) cuando todavía no hay cliente: así se puede empezar por el NUP o por el
     * número del documento. Devuelve false si la línea es de OTRO cliente. Lo que se entrega a
     * cambio no pasa por aquí: puede salir de la consignación de cualquier cliente.
     */
    function camAsegurarClienteDeLinea(l) {
        if (!l.id_cliente) return true; // existencias / catálogo: no dependen del cliente
        const actual = document.getElementById('cam_id_cliente').value;
        if (!actual) {
            camSeleccionarCliente({ id: l.id_cliente, nombre: l.cliente_nombre, identificacion: l.cliente_identificacion, email: l.cliente_email });
            return true;
        }
        if (String(actual) !== String(l.id_cliente)) {
            camSwal({
                icon: 'warning', title: 'Es de otro cliente',
                html: `El documento <strong>${esc(l.doc_numero || l.doc_numero_consignacion || '')}</strong> pertenece a <strong>${esc(l.cliente_nombre || 'otro cliente')}</strong>.<br>Lo que se devuelve en un cambio es de un solo cliente: quite el cliente actual (Backspace en el campo Cliente) o registre otro cambio.`
            });
            return false;
        }
        return true;
    }

    /**
     * Pinta un dropdown AGRUPADO POR DOCUMENTO (factura / cambio / consignación): una
     * cabecera por documento con "Agregar todos" y, debajo, cada ítem del documento por
     * separado (el cambio se hace por unidad / NUP, así que cada uno se agrega solo).
     * opts: { mostrarCliente, idClienteCambio, existe(l) → bool, onAdd(l, silencioso) }
     * Con `idClienteCambio` (solo lo pasa la búsqueda de lo que se entrega), los documentos de
     * OTRO cliente llevan el nombre de su cliente y la marca "Otro cliente", para que se note de
     * qué consignación sale lo entregado; se agregan igual que los demás.
     */
    function camRenderGrupos(dd, rows, opts) {
        const grupos = new Map();
        rows.forEach(l => {
            const g = l.origen_tipo + '-' + l.id_origen;
            if (!grupos.has(g)) grupos.set(g, { tipo: l.origen_tipo, numero: l.doc_numero, numeroConsig: l.doc_numero_consignacion, fecha: l.doc_fecha, cliente: l.cliente_nombre, idCliente: l.id_cliente, items: [] });
            grupos.get(g).items.push(l);
        });
        grupos.forEach(g => {
            const otroCliente = !!opts.idClienteCambio && !!g.idCliente && String(g.idCliente) !== String(opts.idClienteCambio);
            const head = document.createElement('div');
            head.className = 'list-group-item py-1 bg-light d-flex align-items-center gap-2 flex-wrap';
            // Factura de consignación sin factura de venta enlazada: se identifica por su número propio.
            const numero = g.numero
                ? `<span class="small fw-semibold">${esc(g.numero)}</span>`
                : (g.numeroConsig ? `<span class="small text-muted" title="Esta factura de consignación no tiene factura de venta enlazada">Sin factura · F. consig. ${esc(g.numeroConsig)}</span>` : '');
            head.innerHTML = `${camBadgeOrigen(g.tipo)}
                ${numero}
                <span class="small text-muted">${camFechaCorta(g.fecha)}</span>
                ${(opts.mostrarCliente || otroCliente) && g.cliente ? `<span class="small ${otroCliente ? 'fw-semibold text-dark' : 'text-muted'}">· ${esc(g.cliente)}</span>` : ''}
                ${otroCliente ? '<span class="badge bg-warning bg-opacity-25 text-dark border border-warning" title="Esta consignación es de otro cliente: lo que se entregue sale de su saldo y va al cliente del cambio">Otro cliente</span>' : ''}
                <span class="small text-muted ms-auto">${g.items.length} ítem(s)</span>`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-primary btn-sm py-0 px-1';
            btn.style.fontSize = '.7rem';
            btn.innerHTML = '<i class="bi bi-plus-lg"></i> Agregar todos';
            btn.onclick = (ev) => { ev.preventDefault(); ev.stopPropagation(); g.items.forEach(l => opts.onAdd(l, true)); dd.classList.add('d-none'); };
            head.appendChild(btn);
            dd.appendChild(head);

            g.items.forEach(l => {
                const ya = opts.existe(l);
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action py-1 ps-4' + (ya ? ' text-muted' : '');
                a.innerHTML = `<i class="bi bi-plus-circle text-primary me-1"></i>
                    <span class="small fw-semibold">${esc(l.producto_codigo ? l.producto_codigo + ' · ' : '')}${esc(l.producto_nombre)}</span>
                    <span class="small text-muted ms-1">Lote/NUP: ${esc(camLoteNup(l))}</span>
                    <span class="small text-success ms-1">Saldo: ${fmt(l.saldo_pendiente, DEC_C)}</span>
                    ${l.bodega_nombre ? `<span class="small text-muted ms-1">· ${esc(l.bodega_nombre)}</span>` : ''}
                    ${ya ? '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">ya agregada</span>' : ''}`;
                a.onclick = (ev) => { ev.preventDefault(); opts.onAdd(l, false); dd.classList.add('d-none'); };
                dd.appendChild(a);
            });
        });
    }

    // ─── Devoluciones (buscar líneas de origen y agregar) ─────────────────────
    // Busca por NUP, lote, número de factura de consignación / cambio (completo o solo el secuencial) o
    // producto. Con cliente fijado acota a ese cliente (y con el campo vacío lista todo lo
    // pendiente del cliente); sin cliente busca en todos y el ítem elegido fija el cliente.
    window.camBuscarLineas = function (q, desdeFocus) {
        clearTimeout(camDevTimer);
        const dd = document.getElementById('cam_dev_dropdown');
        const idCliente = document.getElementById('cam_id_cliente').value;
        q = (q || '').trim();
        if (!q && !idCliente) { dd.classList.add('d-none'); return; }
        if (q && q.length < 2 && !idCliente) { dd.classList.add('d-none'); return; }
        camDevTimer = setTimeout(async () => {
            const excl = document.getElementById('cam_id').value || 0;
            const res = await fetch(`${RUTA}/buscarLineasOrigenAjax?id_cliente=${idCliente || 0}&excluir=${excl}&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            if (!data.ok) {
                dd.innerHTML = `<span class="list-group-item small text-danger">${esc(data.error || 'No se pudo buscar.')}</span>`;
                dd.classList.remove('d-none');
                return;
            }
            const rows = data.data || [];
            if (!rows.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin ítems con saldo pendiente para esa búsqueda.</span>';
            } else {
                camRenderGrupos(dd, rows, {
                    mostrarCliente: !idCliente,
                    existe: (l) => !!document.querySelector(`#cam_dev_body tr[data-key="${l.origen_tipo}-${l.id_origen_detalle}"]`),
                    onAdd: (l, silencioso) => { camAgregarDevolucion(l, silencioso); document.getElementById('cam_dev_busqueda').value = ''; }
                });
            }
            dd.classList.remove('d-none');
        }, desdeFocus ? 0 : 300);
    };

    function camAgregarDevolucion(l, silencioso) {
        if (!camAsegurarClienteDeLinea(l)) return;
        const key = l.origen_tipo + '-' + l.id_origen_detalle;
        if (document.querySelector(`#cam_dev_body tr[data-key="${key}"]`)) {
            if (!silencioso) camSwal({ icon: 'info', title: 'Ya agregada', text: 'Esa línea ya está en la lista.', timer: 1200, showConfirmButton: false });
            return;
        }
        const empty = document.querySelector('#cam_dev_body .cam-dev-empty');
        if (empty) empty.parentElement.removeChild(empty);

        const saldo = num(l.saldo_pendiente);
        const tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.dataset.origenTipo = l.origen_tipo;
        tr.dataset.idOrigenDetalle = l.id_origen_detalle;
        tr.dataset.saldo = saldo;
        // Bodega de la que salió la unidad (la de la línea de origen): ahí vuelve a entrar. El saldo no
        // tiene columna: es el valor propuesto y el tope de la cantidad (camOnCantDev).
        tr.innerHTML = `
            <td class="small">${camBadgeOrigenDev(l.origen_tipo, l.doc_numero, l.factura_afectada)}</td>
            <td class="small">${esc(l.producto_codigo ? l.producto_codigo + ' · ' : '')}${esc(l.producto_nombre)}</td>
            <td class="small">${esc(l.bodega_nombre || '—')}</td>
            <td class="small">${esc(l.lote || '—')}</td>
            <td class="small">${esc(l.nup || '—')}</td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-dev-cant" min="0" max="${saldo}" step="any" value="${saldo}" oninput="camOnCantDev(this)" title="Máximo: ${fmt(saldo, DEC_C)}" style="height:26px;font-size:.8rem;"></td>
            <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'dev')" title="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        document.getElementById('cam_dev_body').appendChild(tr);
        camOnCantDev(tr.querySelector('.cam-dev-cant'));
    }

    window.camOnCantDev = function (inp) {
        const tr = inp.closest('tr');
        const saldo = num(tr.dataset.saldo);
        if (num(inp.value) > saldo) inp.value = saldo;
        camRecalcular();
    };

    // ─── Entregas (desde consignaciones de cualquier cliente, por su número) ──────
    // Se busca SOLO por el número de la consignación: completo (001-001-000000012) o solo el
    // secuencial, con o sin ceros. Salen las líneas de esa consignación ENTREGADA con saldo en
    // poder del cliente, cada ítem por separado (o "Agregar todos"). Desde el 18-09-2026 ya no
    // se ofrecen existencias de bodega ni el catálogo, ni se busca por NUP, lote o producto.
    // La búsqueda NO se acota al cliente del cambio: se puede entregar desde la consignación de
    // otro cliente (decisión del usuario, 18-09-2026; el servidor también lo admite). Esas
    // consignaciones salen con el nombre de su cliente y la marca "Otro cliente".
    window.camBuscarEntregas = function (q, desdeFocus) {
        clearTimeout(camEntTimer);
        const dd = document.getElementById('cam_ent_dropdown');
        const idCliente = document.getElementById('cam_id_cliente').value;
        q = (q || '').trim();
        // Sin un número no hay nada que buscar (con el campo vacío ya no se listan todas las
        // consignaciones del cliente).
        if (!/\d/.test(q)) { dd.classList.add('d-none'); return; }
        camEntTimer = setTimeout(async () => {
            const excl = document.getElementById('cam_id').value || 0;
            const res = await fetch(`${RUTA}/buscarEntregasAjax?id_cliente=0&excluir=${excl}&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            if (!data.ok) {
                dd.innerHTML = `<span class="list-group-item small text-danger">${esc(data.error || 'No se pudo buscar.')}</span>`;
                dd.classList.remove('d-none');
                return;
            }
            const consig = data.consignaciones || [];
            if (!consig.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">No hay una consignación entregada con ese número y saldo pendiente.</span>';
            } else {
                camRenderGrupos(dd, consig, {
                    mostrarCliente: !idCliente,
                    idClienteCambio: idCliente,
                    existe: (l) => !!document.querySelector(`#cam_ent_body tr[data-key="CONSIGNACION-${l.id_origen_detalle}"]`),
                    onAdd: (l, silencioso) => { camAgregarEntregaFila(l, silencioso); document.getElementById('cam_ent_busqueda').value = ''; }
                });
            }
            dd.classList.remove('d-none');
        }, desdeFocus ? 0 : 300);
    };

    /**
     * Agrega una fila de entrega desde una línea de consignación: bodega, lote y NUP son los de
     * la consignación (fijos) y la cantidad propone su saldo pendiente, que es también su
     * máximo. La consignación puede ser de cualquier cliente, así que no fija ni exige el
     * cliente del cambio: ese lo fijan las devoluciones.
     */
    function camAgregarEntregaFila(o, silencioso) {
        const key = `CONSIGNACION-${o.id_origen_detalle}`;
        if (document.querySelector(`#cam_ent_body tr[data-key="${key}"]`)) {
            if (!silencioso) camSwal({ icon: 'info', title: 'Ya agregado', text: 'Ese ítem ya está en la lista.', timer: 1200, showConfirmButton: false });
            return;
        }
        const empty = document.querySelector('#cam_ent_body .cam-ent-empty');
        if (empty) empty.parentElement.removeChild(empty);

        // Precio: el de la consignación. No se muestra en pantalla: viaja oculto en la fila
        // para que el servidor calcule la diferencia informativa y el registro en Facturación
        // de consignaciones. El IVA no viaja: lo pone el servidor con la tarifa vigente del producto.
        const saldo = num(o.saldo_pendiente);
        const tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.setAttribute('data-prod', o.id_producto);
        tr.dataset.origenTipo = 'CONSIGNACION';
        tr.dataset.idOrigenDetalle = o.id_origen_detalle;
        tr.dataset.saldo = saldo;
        tr.dataset.caducidad = o.fecha_caducidad ? String(o.fecha_caducidad).slice(0, 10) : '';
        tr.dataset.precio = num(o.precio_unitario);
        tr.innerHTML = `
            <td class="small">${camBadgeOrigenConsignacion(o.doc_numero)}</td>
            <td class="small">${esc(o.producto_codigo ? o.producto_codigo + ' · ' : '')}${esc(o.producto_nombre)}</td>
            <td class="p-0"><select class="form-select form-select-sm cam-ent-bodega" disabled title="La bodega es la de la consignación" style="height:26px;font-size:.78rem;">${bodegaOptions(o.id_bodega || '')}</select></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm cam-ent-lote" placeholder="Lote" value="${esc(o.lote || '')}" readonly style="height:26px;font-size:.75rem;"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm cam-ent-nup" placeholder="NUP" value="${esc(o.nup || '')}" readonly style="height:26px;font-size:.75rem;"></td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-cant" min="0" max="${saldo}" step="any" value="${saldo}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
            <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'ent')" title="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        document.getElementById('cam_ent_body').appendChild(tr);
        camOnEnt(tr.querySelector('.cam-ent-cant'));
    }

    window.camOnEnt = function (inp) {
        const tr = inp.closest('tr');
        const cantInp = tr.querySelector('.cam-ent-cant');
        // Desde consignación no se puede entregar más que el saldo en poder del cliente.
        const saldo = num(tr.dataset.saldo);
        if (tr.dataset.saldo !== '' && saldo > 0 && num(cantInp.value) > saldo) cantInp.value = saldo;
        camRecalcular();
    };

    window.camQuitarFila = function (btn, tipo) {
        const tr = btn.closest('tr');
        tr.parentElement.removeChild(tr);
        if (tipo === 'dev' && !document.querySelector('#cam_dev_body tr')) vaciarDev();
        if (tipo === 'ent' && !document.querySelector('#cam_ent_body tr')) vaciarEnt();
        camRecalcular();
    };

    function camRecalcular() {
        // Solo el conteo de líneas de cada tabla: el modal ya no muestra precios, IVA,
        // totales ni la diferencia (siguen calculándose en el servidor para el listado).
        const nDev = document.querySelectorAll('#cam_dev_body tr[data-key]').length;
        const nEnt = document.querySelectorAll('#cam_ent_body tr[data-prod]').length;
        document.getElementById('cam_dev_info').textContent = nDev ? nDev + ' línea(s)' : '';
        document.getElementById('cam_ent_info').textContent = nEnt ? nEnt + ' línea(s)' : '';
    }

    // ─── Ver / editar detalle existente ───────────────────────────────────────
    async function camCargarDetalle(id, editable) {
        try {
            const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
            const data = await res.json();
            if (!data.ok) { Swal.fire('Error', data.error || 'No se pudo cargar.', 'error'); return; }
            const r = data.data;

            document.getElementById('cam_serie').value = r.serie || '';
            const selSerie = document.getElementById('cam_select_serie');
            if (r.id_punto_emision && selSerie.querySelector(`option[value="${r.id_punto_emision}"]`)) selSerie.value = r.id_punto_emision;
            selSerie.disabled = true;
            document.getElementById('cam_id_punto_emision').value = r.id_punto_emision || '';
            document.getElementById('cam_secuencial').value = r.secuencial || '';
            document.getElementById('cam_secuencial').dataset.sec = r.secuencial || '';
            if (r.fecha_cambio) document.getElementById('cam_fecha_cambio').value = String(r.fecha_cambio).slice(0, 10);

            document.getElementById('cam_id_cliente').value = r.id_cliente || '';
            document.getElementById('cam_cliente_email').value = r.cliente_email || '';
            document.getElementById('cam_cliente_busqueda').value = (r.cliente_identificacion || '') + ' — ' + (r.cliente_nombre || '');
            document.getElementById('cam_motivo').value = r.motivo || '';
            document.getElementById('cam_observaciones').value = r.observaciones || '';

            vaciarDev(); vaciarEnt();
            const dets = r.detalles || [];
            const devs = dets.filter(d => d.tipo_linea === 'devolucion');
            const ents = dets.filter(d => d.tipo_linea === 'entrega');

            if (devs.length) {
                document.querySelector('#cam_dev_body .cam-dev-empty')?.remove();
                devs.forEach(d => camPintarDevExistente(d, editable));
            }
            if (ents.length) {
                document.querySelector('#cam_ent_body .cam-ent-empty')?.remove();
                ents.forEach(d => camPintarEntExistente(d, editable));
            }
            camRecalcular();
        } catch (err) {
            console.error('Error cargando detalle del cambio:', err);
            Swal.fire({ icon: 'error', title: 'No se pudo cargar el cambio', text: 'Ocurrió un error de conexión. Intenta cerrar y volver a abrir este registro.' });
        } finally {
            document.getElementById('cam-modal-loader')?.classList.add('d-none');
        }
    }

    function camPintarDevExistente(d, editable) {
        const saldoRef = num(d.cantidad); // en edición el máximo real se revalida en el server
        const tr = document.createElement('tr');
        tr.setAttribute('data-key', d.origen_tipo + '-' + d.id_origen_detalle);
        tr.dataset.origenTipo = d.origen_tipo;
        tr.dataset.idOrigenDetalle = d.id_origen_detalle;
        tr.dataset.saldo = editable ? 1e12 : saldoRef;
        const cantCell = editable
            ? `<input type="number" class="form-control form-control-sm text-end cam-dev-cant" min="0" step="any" value="${num(d.cantidad)}" oninput="camOnCantDev(this)" style="height:26px;font-size:.8rem;">`
            : `<span class="cam-dev-cant-ro">${fmt(d.cantidad, DEC_C)}</span>`;
        tr.innerHTML = `
            <td class="small">${camBadgeOrigenDev(d.origen_tipo, d.origen_numero, d.factura_afectada)}</td>
            <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
            <td class="small">${esc(d.bodega_nombre || '—')}</td>
            <td class="small">${esc(d.lote || '—')}</td>
            <td class="small">${esc(d.nup || '—')}</td>
            <td class="p-0 text-end">${cantCell}</td>
            <td class="text-center p-0">${editable ? `<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'dev')"><i class="bi bi-x-lg"></i></button>` : ''}</td>`;
        document.getElementById('cam_dev_body').appendChild(tr);
    }

    function camPintarEntExistente(d, editable) {
        const esConsig = (d.origen_tipo === 'CONSIGNACION');
        const tr = document.createElement('tr');
        tr.setAttribute('data-prod', d.id_producto);
        tr.setAttribute('data-key', esConsig
            ? `CONSIGNACION-${d.id_origen_detalle}`
            : `GUARDADA-${d.id || ''}-${d.id_producto}-${d.id_bodega || 0}-${d.lote || ''}-${d.nup || ''}`);
        tr.dataset.origenTipo = esConsig ? 'CONSIGNACION' : '';
        tr.dataset.idOrigenDetalle = esConsig ? (d.id_origen_detalle || '') : '';
        // Entrega desde bodega o catálogo de un borrador anterior al 18-09-2026: viaja con el id de
        // su línea para que el servidor la reconozca (las nuevas se rechazan) y su cantidad no
        // puede subir (camOnEnt la topa en el saldo). Desde consignación, el máximo real se
        // revalida en el server.
        tr.dataset.idDetalle = esConsig ? '' : (d.id || '');
        tr.dataset.saldo = esConsig ? '' : num(d.cantidad);
        tr.dataset.caducidad = d.fecha_caducidad ? String(d.fecha_caducidad).slice(0, 10) : '';
        // Precio guardado: no se muestra, pero al editar un borrador se reenvía tal cual (el IVA
        // lo vuelve a poner el servidor).
        tr.dataset.precio = num(d.precio_unitario);
        const origenCell = esConsig
            ? camBadgeOrigenConsignacion(d.origen_numero)
            : camBadgeOrigen('BODEGA', null, 'Entrega desde bodega o catálogo registrada antes del 18-09-2026: se conserva, pero ya no se agregan nuevas');
        if (editable) {
            const topeCant = esConsig ? '' : `max="${num(d.cantidad)}" title="Máximo ${fmt(d.cantidad, DEC_C)}: se puede reducir, no aumentar (lo que se agregue debe salir de una consignación)"`;
            tr.innerHTML = `
                <td class="small">${origenCell}</td>
                <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
                <td class="p-0"><select class="form-select form-select-sm cam-ent-bodega" ${esConsig ? 'disabled title="La bodega es la de la consignación"' : ''} style="height:26px;font-size:.78rem;">${bodegaOptions(d.id_bodega)}</select></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm cam-ent-lote" placeholder="Lote" value="${esc(d.lote || '')}" ${esConsig ? 'readonly' : ''} style="height:26px;font-size:.75rem;"></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm cam-ent-nup" placeholder="NUP" value="${esc(d.nup || '')}" ${esConsig ? 'readonly' : ''} style="height:26px;font-size:.75rem;"></td>
                <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-cant" min="0" ${topeCant} step="any" value="${num(d.cantidad)}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
                <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'ent')"><i class="bi bi-x-lg"></i></button></td>`;
            document.getElementById('cam_ent_body').appendChild(tr);
            camOnEnt(tr.querySelector('.cam-ent-cant'));
        } else {
            const bod = (camBodegas.find(b => String(b.id) === String(d.id_bodega)) || {}).nombre || (d.bodega_nombre || '—');
            tr.innerHTML = `
                <td class="small">${origenCell}</td>
                <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
                <td class="small">${esc(bod)}</td>
                <td class="small">${esc(d.lote || '—')}</td>
                <td class="small">${esc(d.nup || '—')}</td>
                <td class="text-end small">${fmt(d.cantidad, DEC_C)}</td>
                <td></td>`;
            document.getElementById('cam_ent_body').appendChild(tr);
        }
    }

    // ─── Guardar ──────────────────────────────────────────────────────────────
    window.camGuardar = async function () {
        const idCliente = document.getElementById('cam_id_cliente').value;
        if (!idCliente) { Swal.fire('Atención', 'Seleccione un cliente.', 'warning'); return; }
        // La numeración solo se exige al EMITIR: un documento ya guardado conserva la suya.
        if (!document.getElementById('cam_id').value) {
            if (!document.getElementById('cam_id_punto_emision').value) { camAvisarSecuencialNoConfigurado('serie'); return; }
            if (!document.getElementById('cam_secuencial').value || !camSecuencialConfigurado) { camAvisarSecuencialNoConfigurado('secuencial'); return; }
        }

        const devoluciones = [];
        document.querySelectorAll('#cam_dev_body tr[data-key]').forEach(tr => {
            const inp = tr.querySelector('.cam-dev-cant');
            const c = inp ? num(inp.value) : num(tr.querySelector('.cam-dev-cant-ro')?.textContent);
            if (c > 0) devoluciones.push({
                origen_tipo: tr.dataset.origenTipo,
                id_origen_detalle: parseInt(tr.dataset.idOrigenDetalle, 10),
                cantidad: c
            });
        });
        if (!devoluciones.length) { Swal.fire('Atención', 'Agregue al menos un producto a devolver.', 'warning'); return; }

        const entregas = [];
        document.querySelectorAll('#cam_ent_body tr[data-prod]').forEach(tr => {
            const cInp = tr.querySelector('.cam-ent-cant');
            if (!cInp) return; // fila de solo lectura (modo ver)
            const c = num(cInp.value);
            if (c > 0) entregas.push({
                id_producto: parseInt(tr.dataset.prod, 10),
                cantidad: c,
                // Oculto en la fila (ver camAgregarEntregaFila); el IVA lo pone el servidor.
                precio_unitario: num(tr.dataset.precio),
                id_bodega: parseInt(tr.querySelector('.cam-ent-bodega').value || 0, 10),
                // Desde consignación: el servidor toma producto/bodega/lote/NUP de esa línea.
                origen_tipo: tr.dataset.origenTipo || '',
                id_origen_detalle: tr.dataset.idOrigenDetalle ? parseInt(tr.dataset.idOrigenDetalle, 10) : null,
                // Desde bodega o catálogo: solo la línea que el borrador ya tenía (ver camPintarEntExistente).
                id_detalle: tr.dataset.idDetalle ? parseInt(tr.dataset.idDetalle, 10) : null,
                lote: (tr.querySelector('.cam-ent-lote')?.value || '').trim(),
                nup: (tr.querySelector('.cam-ent-nup')?.value || '').trim(),
                fecha_caducidad: tr.dataset.caducidad || ''
            });
        });

        const idCambio = document.getElementById('cam_id').value;
        const payload = {
            id: idCambio || null,
            id_cliente: parseInt(idCliente, 10),
            fecha_cambio: document.getElementById('cam_fecha_cambio').value,
            serie: document.getElementById('cam_serie').value,
            secuencial: document.getElementById('cam_secuencial').dataset.sec || '',
            id_punto_emision: document.getElementById('cam_id_punto_emision').value,
            establecimiento: (document.getElementById('cam_serie').value.split('-')[0] || ''),
            punto_emision: (document.getElementById('cam_serie').value.split('-')[1] || ''),
            motivo: document.getElementById('cam_motivo').value,
            observaciones: document.getElementById('cam_observaciones').value,
            devoluciones,
            entregas,
            // Líneas del asiento completadas en la pestaña (vista previa). CambioProductoCvService
            // las usa solo si vienen completas; si no, arma el asiento con la sugerencia.
            asiento_detalles: (typeof window.camCapturarDetallesAsiento === 'function')
                ? window.camCapturarDetallesAsiento() : []
        };

        const btn = document.getElementById('btnGuardarCambio');
        const labelOrig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            getModal().hide();
            await Swal.fire('Listo', data.msg, 'success');
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        } finally {
            btn.disabled = false; btn.innerHTML = labelOrig;
        }
    };

    // ─── Eliminar ─────────────────────────────────────────────────────────────
    window.camEliminar = async function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return;
        const c = await Swal.fire({
            title: '¿Eliminar cambio?', text: 'Se reversarán los movimientos de inventario de este cambio.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            getModal().hide();
            await Swal.fire('Eliminado', data.msg, 'success');
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    };

    // ─── Acciones rápidas ─────────────────────────────────────────────────────
    window.camPdf = function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        const a = document.createElement('a');
        a.href = `${RUTA}/pdf?id=${id}`; a.download = '';
        document.body.appendChild(a); a.click(); a.remove();
    };
    window.camExcel = function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        window.open(`${RUTA}/excel?id=${id}`, '_blank');
    };
    window.camEmail = async function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo', input: 'text',
            inputLabel: 'Correo(s) destino, separados por coma.',
            inputValue: document.getElementById('cam_cliente_email').value || '',
            inputPlaceholder: 'cliente@correo.com',
            target: document.getElementById('modalCambio'),
            showCancelButton: true, confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar', cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalCambio'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            else Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
        } catch (e) {
            Swal.fire('Error', 'No se pudo enviar el correo.', 'error');
        }
    };
    window.camWhatsapp = function () {
        if (!document.getElementById('cam_id').value) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        Swal.fire('Info', 'Enviando por WhatsApp...', 'info');
    };

    // ─── Pestaña Asiento ──────────────────────────────────────────────────────
    // Componente compartido: public/js/modulos/asiento_contable_tab.js.
    //  - Sin asiento todavía → vista previa EDITABLE: las cuentas que falten se completan aquí
    //    y viajan como `asiento_detalles` al guardar el cambio (CambioProductoCvService ya las
    //    usa; si quedan incompletas cae a la sugerencia automática, como antes).
    //  - Con asiento registrado → se corrige y se guarda desde la pestaña contra el módulo de
    //    Asientos Contables, y queda marcado para que el documento no lo regenere.
    let _camAsientoTab = null;
    function camAsientoTab() {
        // Sin permiso sobre Asientos Contables la pestaña no se renderiza: nada que inicializar.
        if (!document.getElementById('cam-asiento-tbody')) return null;
        if (!_camAsientoTab && typeof window.crearAsientoTab === 'function') {
            _camAsientoTab = window.crearAsientoTab({
                prefijo: 'cam',
                moduloOrigen: 'cambio_producto_cv',
                previewEditable: true,
                previewUrl: `${RUTA}/getAsientoSugeridoAjax`,
                cuentasUrl: `${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
                asientosUrl: `${window.BASE_URL}/modulos/asientos-contables`
            });
        }
        return _camAsientoTab;
    }

    function camResetTabs() {
        try { new bootstrap.Tab(document.getElementById('cam-tab-general-btn')).show(); } catch (e) {}
        if (_camAsientoTab) _camAsientoTab.limpiar();
    }

    function camCargarAsiento() {
        const tab = camAsientoTab();
        if (tab) tab.cargar(document.getElementById('cam_id').value || 0);
    }

    /** Líneas del asiento que viajan con el cambio al guardarlo (vista previa completada). */
    window.camCapturarDetallesAsiento = function () {
        return _camAsientoTab ? _camAsientoTab.capturar() : [];
    };

    // La pestaña solo existe con acceso a Contabilidad → Asientos Contables.
    document.getElementById('cam-tab-asiento-btn')?.addEventListener('shown.bs.tab', camCargarAsiento);
    window.__camResetTabs = camResetTabs;
})();
    // Cambiar la fecha del documento puede cambiar su número: con numeración por fecha
    // de emisión (Empresa → Secuenciales), cada periodo lleva su propio correlativo.
    // Se vuelve a pedir la vista previa disparando el 'change' del selector de serie.
    (function _recalcularSecuencialPorFecha() {
        const enganchar = () => {
            const inputFecha = document.getElementById('cam_fecha_cambio');
            const selSerie   = document.getElementById('cam_select_serie');
            if (!inputFecha || !selSerie) return;
            inputFecha.addEventListener('change', () => {
                if (selSerie.value) selSerie.dispatchEvent(new Event('change'));
            });
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', enganchar);
        } else {
            enganchar();
        }
    })();
</script>
