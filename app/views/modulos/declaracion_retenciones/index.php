<style>
    .sri-container { background: #fff; border: 1px solid #ccc; padding: 10px; font-family: 'Arial', sans-serif; overflow-y: auto; overflow-x: auto; max-height: 55vh; margin-bottom: 10px; }
    .sri-section-title { background: #0d6efd; color: #fff; padding: 3px 8px; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; border: 1px solid #0a58ca; border-radius: 3px; }
    .sri-table td { padding: 2px 4px !important; vertical-align: middle; border: 1px solid #ccc; font-size: 0.75rem; }
    .sri-table .row-bold { background-color: #f2f2f2; font-weight: 700; }
    .nav-tabs .nav-link { font-weight: 700; font-size: 0.8rem; color: #555; }
    .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; }
</style>

<div class="container-fluid py-2">
    <div class="row mb-1 print-none">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h5 mb-0 text-dark fw-bold">Declaración de Retenciones en la Fuente (Formulario 103 SRI)</h1>
                <p class="text-muted mb-0 small" style="font-size: 0.7rem;">Retenciones de Impuesto a la Renta efectuadas en compras</p>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3 mt-1 cmg-table-card print-none">
        <div class="card-body p-2 text-center">
            <form id="formDeclaracion" class="row g-2 align-items-end justify-content-center">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Año</label>
                    <select name="anio" class="form-select form-select-sm border-0 bg-light fw-bold" id="anio">
                        <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Mes</label>
                    <select name="mes" class="form-select form-select-sm border-0 bg-light fw-bold" id="mes"></select>
                </div>
                <div class="col-md-2 text-start pb-1">
                    <div class="form-check form-switch mb-0 ms-2" title="Mostrar solo las filas que tengan algún valor">
                        <input class="form-check-input" type="checkbox" id="checkSoloValores">
                        <label class="form-check-label fw-bold small text-muted" for="checkSoloValores" style="font-size: 0.7rem;">Solo valores</label>
                    </div>
                </div>
                <div class="col-md-5 d-flex gap-2 align-items-end flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold py-1">SINCRONIZAR</button>
                    <button type="button" id="btnPdf" class="btn btn-outline-danger btn-sm flex-grow-1 fw-bold py-1 d-none" onclick="exportarPdf()">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <button type="button" id="btnExportarExcel" class="btn btn-success btn-sm flex-grow-1 fw-bold py-1 d-none" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> EXCEL
                    </button>
                    <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                    <button type="button" id="btnGuardarDeclaracion" class="btn btn-outline-primary btn-sm fw-bold py-1 d-none">
                        <i class="bi bi-save"></i> GUARDAR DECLARACIÓN
                    </button>
                    <button type="button" id="btnGenerarAsiento" class="btn btn-outline-dark btn-sm fw-bold py-1 d-none">
                        <i class="bi bi-journal-text"></i> GENERAR ASIENTO
                    </button>
                    <button type="button" id="btnGenerarEgreso" class="btn btn-outline-danger btn-sm fw-bold py-1 d-none">
                        <i class="bi bi-cash-coin"></i> GENERAR EGRESO
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            <div id="avisoDeclarado" class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none text-start small"></div>
        </div>
    </div>

    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña d-none print-none" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Formulario 103</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="detalle-tab" data-bs-toggle="tab" data-bs-target="#detalle" type="button" role="tab">Detalle de Documentos</button>
        </li>
    </ul>

    <div class="tab-content border-top bg-white p-3 d-none" id="myTabContent">
        <div class="tab-pane fade show active" id="resumen" role="tabpanel">
            <div id="formSRI" class="sri-container"></div>
        </div>
        <div class="tab-pane fade" id="detalle" role="tabpanel">
            <div id="accordionDetalle" class="accordion accordion-flush" style="max-height: 55vh; overflow-y: auto;"></div>
        </div>
    </div>
</div>

<!-- Modal: Generar Egreso -->
<div class="modal fade" id="modalGenerarEgreso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-cash-coin text-danger me-1"></i> Generar Egreso — Pago Declaración de Retenciones</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">El egreso se registra a nombre del proveedor/tercero que realizará el pago de las retenciones al SRI.</p>

                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label small fw-bold mb-1">Fecha</label>
                        <input type="date" id="egresoFecha" class="form-control form-control-sm">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold mb-1">Serie</label>
                        <select id="egresoPuntoEmision" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold mb-1">Secuencial</label>
                        <input type="text" id="egresoSecuencial" class="form-control form-control-sm bg-light" readonly>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Proveedor / Tercero</label>
                        <div class="position-relative">
                            <input type="text" id="egresoProveedorTexto" class="form-control form-control-sm" placeholder="Buscar proveedor..." autocomplete="off">
                            <input type="hidden" id="egresoProveedorId">
                            <div id="egresoProveedorDropdown" class="list-group position-absolute w-100 shadow-sm" style="z-index: 2000; display:none; max-height:200px; overflow-y:auto;"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1 d-flex align-items-center">Concepto de Egreso <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('declaracion_retenciones', 'egresoConcepto', 'id_egreso_concepto_default') ?></label>
                        <select id="egresoConcepto" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1 d-flex align-items-center">Forma de Pago <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('declaracion_retenciones', 'egresoFormaPago', 'id_forma_pago_default') ?></label>
                        <select id="egresoFormaPago" class="form-select form-select-sm"></select>
                    </div>
                </div>

                <!-- Campos condicionales cuando la forma de pago es tipo BANCO -->
                <div class="row g-2 mt-1 d-none" id="egresoBancoExtra">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Operación Bancaria</label>
                        <select id="egresoTipoOperacion" class="form-select form-select-sm bg-warning bg-opacity-10">
                            <option value="TRANSFERENCIA" selected>Transferencia</option>
                            <option value="DEPOSITO">Depósito</option>
                            <option value="DEBITO">Débito</option>
                            <option value="CHEQUE">Cheque</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-none" id="egresoChequeNumWrap">
                        <label class="form-label small fw-bold mb-1 text-primary"><i class="bi bi-card-checklist me-1"></i>N° Cheque</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="egresoNumeroCheque" class="form-control border-primary" placeholder="Autogenerado...">
                            <button type="button" class="btn btn-outline-primary btn-sm" title="Recargar secuencia" onclick="recargarSecuenciaChequeDecl()">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="egresoChequeFechaWrap">
                        <label class="form-label small fw-bold mb-1 text-primary"><i class="bi bi-calendar-date me-1"></i>Fecha Cobro</label>
                        <input type="date" id="egresoFechaCheque" class="form-control form-control-sm border-primary">
                    </div>
                </div>

                <div class="alert alert-secondary py-2 px-3 small mb-0 mt-2" id="egresoMontoInfo"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarEgreso" class="btn btn-danger btn-sm fw-bold">Generar Egreso</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDeclaracion');
        const selMes = document.getElementById('mes');
        const formSRI = document.getElementById('formSRI');
        const tabsContainer = document.getElementById('myTab');
        const tabContent = document.getElementById('myTabContent');

        // Map (no objeto literal): en los objetos JS las claves '10'-'12' se ordenan
        // numéricamente antes que '01'-'09' y los meses salían desordenados
        const meses = new Map([
            ['01', 'Enero'], ['02', 'Febrero'], ['03', 'Marzo'], ['04', 'Abril'],
            ['05', 'Mayo'], ['06', 'Junio'], ['07', 'Julio'], ['08', 'Agosto'],
            ['09', 'Septiembre'], ['10', 'Octubre'], ['11', 'Noviembre'], ['12', 'Diciembre']
        ]);
        const mesDefault = '<?= htmlspecialchars((string) $mes) ?>';
        for (const [v, m] of meses) selMes.insertAdjacentHTML('beforeend', `<option value="${v}">${m}</option>`);
        if (meses.has(mesDefault)) selMes.value = mesDefault;

        document.getElementById('checkSoloValores').addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.sri-row-data').forEach(row => {
                if (row.getAttribute('data-has-values') === '0') {
                    row.classList.toggle('d-none', isChecked);
                }
            });
        });

        form.addEventListener('submit', e => {
            e.preventDefault();
            generar();
        });

        function generar() {
            const params = new URLSearchParams(new FormData(form)).toString() + '&sincronizar=1';
            tabsContainer.classList.remove('d-none');
            tabContent.classList.remove('d-none');
            formSRI.innerHTML = '<div class="text-center py-5 small text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Sincronizando y generando el formulario...</div>';
            document.getElementById('accordionDetalle').innerHTML = '<div class="text-center py-3">Cargando detalles...</div>';

            fetch(`<?= $base ?>/<?= $rutaModulo ?>/sincronizar-ajax?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.json()).then(data => {
                if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                renderFormulario(data.resumen_completo);
                renderDetalle(data.detalle_documentos);
                document.getElementById('btnExportarExcel').classList.remove('d-none');
                document.getElementById('btnPdf').classList.remove('d-none');
                yaGenerado = true;
                actualizarBotonesDeclaracion();
            });
        }

        window.exportarExcel = function() {
            const params = new URLSearchParams(new FormData(form)).toString();
            window.open(`<?= $base ?>/<?= $rutaModulo ?>/excel?${params}`, '_blank');
        };
        window.exportarPdf = function() {
            const params = new URLSearchParams(new FormData(form)).toString();
            window.open(`<?= $base ?>/<?= $rutaModulo ?>/pdf?${params}`, '_blank');
        };

        const seccionTitulos = {
            'NACIONAL': 'Por pagos efectuados a residentes y establecimientos permanentes',
            'EXT_CONVENIO': 'Pagos al exterior — con convenio de doble tributación',
            'EXT_SINCONVENIO': 'Pagos al exterior — sin convenio de doble tributación',
            'EXT_PARAISO': 'Pagos al exterior — paraísos fiscales o regímenes fiscales preferentes',
            'INFORMATIVO': 'Valores a pagar y forma de pago (informativo)'
        };

        function renderFormulario(resumenData) {
            const layout = resumenData.layout;
            const valores = resumenData.valores;
            const isChecked = document.getElementById('checkSoloValores').checked;

            layout.forEach(r => {
                if (r.tipo === 'informativo') { r.hasValues = false; return; }
                const vb = r.casillero_base ? (parseFloat(valores[r.casillero_base]) || 0) : null;
                const vv = r.casillero_valor ? (parseFloat(valores[r.casillero_valor]) || 0) : null;
                r.hasValues = (vb !== null && vb !== 0) || (vv !== null && vv !== 0);
            });

            const seccionHasValues = {};
            layout.forEach(r => { if (r.hasValues) seccionHasValues[r.seccion] = true; });

            let currentSeccion = '';
            let html = '';

            layout.forEach(r => {
                if (r.seccion !== currentSeccion) {
                    if (currentSeccion !== '') html += '</tbody></table></div></div>';
                    const sHasValues = seccionHasValues[r.seccion] ? '1' : '0';
                    const dNoneSec = (isChecked && !seccionHasValues[r.seccion]) ? 'd-none' : '';
                    html += `<div class="sri-section-container sri-row-data ${dNoneSec}" data-has-values="${sHasValues}">`;
                    html += `<div class="sri-section-title mt-3">${seccionTitulos[r.seccion] || r.seccion}</div>`;
                    html += `<div class="table-responsive"><table class="table table-bordered table-sm sri-table align-middle w-100 mb-0" style="font-size: 0.8rem;">`;
                    html += `<thead class="table-light text-center">
                                <tr>
                                    <th style="width:55%;">Concepto</th>
                                    <th style="width:8%;">Casillero</th><th style="width:18%;">Base Imponible</th>
                                    <th style="width:8%;">Casillero</th><th style="width:18%;">Valor Retenido</th>
                                </tr>
                             </thead><tbody>`;
                    currentSeccion = r.seccion;
                }

                const marginLeft = (r.indent > 0 ? (r.indent * 15) : 0) + 'px';
                const rowClass = r.bold ? 'fw-bold text-dark bg-light' : '';
                const dNoneRow = (isChecked && !r.hasValues) ? 'd-none' : '';
                const fmt = v => v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                if (r.tipo === 'informativo') {
                    html += `<tr class="sri-row-data" data-has-values="1">
                        <td colspan="3" class="ps-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${r.descripcion}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${r.casillero_valor ? '<b>'+r.casillero_valor+'</b>' : ''}</td>
                        <td></td>
                    </tr>`;
                } else {
                    const vb = r.casillero_base ? (parseFloat(valores[r.casillero_base]) || 0) : null;
                    const vv = r.casillero_valor ? (parseFloat(valores[r.casillero_valor]) || 0) : null;
                    html += `<tr class="${rowClass} sri-row-data ${dNoneRow}" data-has-values="${r.hasValues ? '1' : '0'}">
                        <td class="ps-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${r.descripcion}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${r.casillero_base ? '<b>'+r.casillero_base+'</b>' : ''}</td>
                        <td class="text-end">${vb !== null ? fmt(vb) : ''}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${r.casillero_valor ? '<b>'+r.casillero_valor+'</b>' : ''}</td>
                        <td class="text-end">${vv !== null ? fmt(vv) : ''}</td>
                    </tr>`;
                }
            });
            if (currentSeccion !== '') html += '</tbody></table></div></div>';
            formSRI.innerHTML = html;
        }

        function renderDetalle(detalle) {
            const accordionDetalle = document.getElementById('accordionDetalle');
            if (detalle.length === 0) {
                accordionDetalle.innerHTML = '<div class="text-center text-muted py-3">No hay documentos sincronizados en este período.</div>';
                return;
            }

            const grupos = {};
            detalle.forEach(d => {
                const docNum = d.establecimiento ? `${d.establecimiento}-${d.punto_emision}-${d.secuencial}` : `ID: ${d.id_origen}`;
                const key = `${d.origen}_${docNum}`;
                if (!grupos[key]) {
                    grupos[key] = { origen: d.origen, docNum: docNum, fecha: d.fecha, entidad: d.proveedor_nombre || '', items: [] };
                }
                grupos[key].items.push(d);
            });

            let html = '';
            let i = 0;
            for (const key in grupos) {
                const g = grupos[key];
                const headerId = 'heading' + i;
                const collapseId = 'collapse' + i;

                const conceptosMap = {};
                g.items.forEach(d => {
                    const concepto = d.concepto || 'Sin concepto';
                    if (!conceptosMap[concepto]) conceptosMap[concepto] = [];
                    conceptosMap[concepto].push(d);
                });

                let filasHtml = '';
                for (const concepto in conceptosMap) {
                    const items = conceptosMap[concepto].sort((a, b) => parseInt(a.casillero) - parseInt(b.casillero));
                    let badgesHtml = '';
                    items.forEach(d => {
                        const val = parseFloat(d.valor) || 0;
                        const modBadge = d.editado_manualmente ? `<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Editado manualmente" style="font-size: 0.75rem;"></i>` : '';
                        badgesHtml += `
                        <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1 me-2 mb-1"
                             style="cursor: pointer;" onmouseover="this.classList.add('shadow-sm','border-primary')" onmouseout="this.classList.remove('shadow-sm','border-primary')"
                             onclick="editarCasillero(${d.id}, '${d.casillero}')" title="Clic para cambiar a qué casillero pertenece este valor">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 me-2">${d.casillero}</span>
                            <span class="fw-bold text-dark" style="font-size: 0.85rem;">${val.toLocaleString('en-US',{minimumFractionDigits:2})}</span>
                            ${modBadge}
                        </div>`;
                    });
                    filasHtml += `<tr>
                        <td class="text-start ps-3 fw-medium text-dark align-middle">${concepto}</td>
                        <td class="text-start pe-3 align-middle py-2">${badgesHtml}</td>
                    </tr>`;
                }

                html += `
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header" id="${headerId}">
                        <button class="accordion-button collapsed py-3 rounded" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 text-uppercase" style="font-size: 0.7rem;">Retención compra</span>
                                    <span class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;">#${g.docNum}</span>
                                    <span class="fw-medium text-secondary" style="font-size: 0.85rem;"><i class="bi bi-person-fill text-muted me-1"></i>${g.entidad || 'Sin proveedor'}</span>
                                    <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>${new Date(g.fecha).toLocaleDateString('es-ES', {day:'2-digit', month:'short', year:'numeric'})}</span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#accordionDetalle">
                        <div class="accordion-body p-3 bg-white border border-top-0 rounded-bottom">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                                    <thead class="table-light text-start border-bottom">
                                        <tr>
                                            <th class="text-start ps-3 text-muted fw-semibold" style="width: 40%;">Concepto</th>
                                            <th class="text-muted fw-semibold">Casilleros <small class="text-primary fw-normal ms-2">(clic para editar)</small></th>
                                        </tr>
                                    </thead>
                                    <tbody>${filasHtml}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>`;
                i++;
            }
            accordionDetalle.innerHTML = html;
        }

        window.editarCasillero = function(id, casilleroActual) {
            Swal.fire({
                title: 'Editar Casillero',
                input: 'text',
                inputLabel: 'Ingresa el nuevo código de casillero para este valor',
                inputValue: casilleroActual,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value) return 'El casillero no puede estar vacío';
                    if (!/^[0-9]{3}$/.test(value)) return 'El casillero debe ser de 3 dígitos numéricos';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('id', id);
                    fd.append('casillero', result.value);
                    fetch(`<?= $base ?>/<?= $rutaModulo ?>/actualizar-casillero-ajax`, {
                        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => r.json()).then(data => {
                        if (data.ok) {
                            Swal.fire({ title: 'Éxito', text: 'Casillero actualizado.', icon: 'success', timer: 1500, showConfirmButton: false });
                            generar();
                        } else {
                            Swal.fire('Error', data.mensaje, 'error');
                        }
                    });
                }
            });
        };

        // ==========================================================================
        // Declaración guardada: aviso de duplicado, guardar, asiento, egreso
        // (mismo patrón que declaracion_iva/index.php, sin tipo_periodo/semestral)
        // ==========================================================================
        const avisoDeclarado = document.getElementById('avisoDeclarado');
        const btnGuardar = document.getElementById('btnGuardarDeclaracion');
        const btnAsiento = document.getElementById('btnGenerarAsiento');
        const btnEgreso = document.getElementById('btnGenerarEgreso');
        let declaracionActual = null;
        let yaGenerado = false; // el botón Guardar solo aparece después de presionar SINCRONIZAR

        function periodoParams() {
            return { anio: document.getElementById('anio').value, mes: selMes.value };
        }

        function actualizarBotonesDeclaracion() {
            if (!btnGuardar) return; // sin permisos de crear/actualizar
            btnGuardar.classList.toggle('d-none', !yaGenerado);
            if (declaracionActual) {
                btnAsiento.classList.remove('d-none');
                const aPagar = parseFloat(declaracionActual.total_retenido) || 0;
                const yaTieneEgreso = !!declaracionActual.id_egreso;
                btnEgreso.classList.toggle('d-none', !(aPagar > 0 && !yaTieneEgreso));
                btnGuardar.innerHTML = '<i class="bi bi-save"></i> ACTUALIZAR DECLARACIÓN';
            } else {
                btnAsiento.classList.add('d-none');
                btnEgreso.classList.add('d-none');
                btnGuardar.innerHTML = '<i class="bi bi-save"></i> GUARDAR DECLARACIÓN';
            }
        }

        function fetchJsonDecl(url, opts) {
            return fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opts || {})).then(r => r.json());
        }

        function verificarDeclarado() {
            const p = periodoParams();
            if (!p.anio || !p.mes) return;
            const params = new URLSearchParams(p).toString();
            fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/verificar-declarado-ajax?${params}`).then(data => {
                if (!data.ok) return;
                declaracionActual = data.declaracion || null;
                if (declaracionActual) {
                    const fecha = declaracionActual.updated_at || declaracionActual.created_at || '';
                    const quien = declaracionActual.usuario_nombre ? ` por ${declaracionActual.usuario_nombre}` : '';
                    avisoDeclarado.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Este período ya fue declarado y guardado${quien}${fecha ? ' (' + fecha + ')' : ''}. Si vuelve a guardar, se actualizará la declaración existente.`;
                    avisoDeclarado.classList.remove('d-none');
                } else {
                    avisoDeclarado.classList.add('d-none');
                }
                actualizarBotonesDeclaracion();
            }).catch(() => {});
        }

        function onCambioPeriodo() {
            yaGenerado = false; // hay que volver a presionar SINCRONIZAR para este período
            verificarDeclarado();
        }
        document.getElementById('anio').addEventListener('change', onCambioPeriodo);
        selMes.addEventListener('change', onCambioPeriodo);
        verificarDeclarado();

        if (btnGuardar) {
            btnGuardar.addEventListener('click', () => {
                const p = periodoParams();
                const fd = new FormData();
                fd.append('anio', p.anio);
                fd.append('mes', p.mes);
                btnGuardar.disabled = true;
                fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/guardar-ajax`, { method: 'POST', body: fd }).then(data => {
                    btnGuardar.disabled = false;
                    if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                    declaracionActual = data.declaracion;
                    actualizarBotonesDeclaracion();
                    Swal.fire({ title: 'Guardado', text: 'Declaración guardada correctamente.', icon: 'success', timer: 1800, showConfirmButton: false });
                }).catch(() => { btnGuardar.disabled = false; });
            });
        }

        if (btnAsiento) {
            btnAsiento.addEventListener('click', () => {
                if (!declaracionActual) return;
                btnAsiento.disabled = true;
                const fd = new FormData();
                fd.append('id_declaracion', declaracionActual.id);
                fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/generar-asiento-ajax`, { method: 'POST', body: fd }).then(data => {
                    btnAsiento.disabled = false;
                    if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                    Swal.fire({ title: 'Asiento generado', text: 'El asiento contable #' + data.id_asiento + ' fue generado.', icon: 'success' });
                    verificarDeclarado();
                }).catch(() => { btnAsiento.disabled = false; });
            });
        }

        // ---- Modal Generar Egreso ----
        let modalEgreso = null;

        function actualizarSecuencialDecl() {
            const idPunto = document.getElementById('egresoPuntoEmision').value;
            const inp = document.getElementById('egresoSecuencial');
            if (!idPunto) { inp.value = ''; return; }
            fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/get-secuencial-egreso-ajax?id_punto_emision=${idPunto}`).then(res => {
                if (res.ok) inp.value = String(res.secuencial).padStart(9, '0');
            });
        }

        function manejarCambioTipoOperacionDecl(val) {
            const numWrap = document.getElementById('egresoChequeNumWrap');
            const fecWrap = document.getElementById('egresoChequeFechaWrap');
            if (val === 'CHEQUE') {
                numWrap.classList.remove('d-none');
                fecWrap.classList.remove('d-none');
                recargarSecuenciaChequeDecl();
            } else {
                numWrap.classList.add('d-none');
                fecWrap.classList.add('d-none');
            }
        }

        function manejarCambioFormaPagoDecl() {
            const sel = document.getElementById('egresoFormaPago');
            const opt = sel.options[sel.selectedIndex];
            const tipo = opt ? (opt.dataset.tipo || '') : '';
            const wrapper = document.getElementById('egresoBancoExtra');
            if (tipo.toUpperCase() === 'BANCO') {
                wrapper.classList.remove('d-none');
                document.getElementById('egresoTipoOperacion').value = 'TRANSFERENCIA';
                manejarCambioTipoOperacionDecl('TRANSFERENCIA');
            } else {
                wrapper.classList.add('d-none');
                manejarCambioTipoOperacionDecl('');
            }
        }

        window.recargarSecuenciaChequeDecl = function() {
            const fp = document.getElementById('egresoFormaPago').value;
            if (!fp) return;
            const input = document.getElementById('egresoNumeroCheque');
            input.placeholder = 'Buscando...';
            fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/get-ultimo-cheque-ajax?id_forma_pago=${fp}`).then(res => {
                if (res.ok && res.siguiente) { input.value = res.siguiente; } else { input.value = ''; input.placeholder = 'Manual Nº'; }
            }).catch(() => { input.placeholder = 'Manual Nº'; });
        };

        document.getElementById('egresoPuntoEmision').addEventListener('change', actualizarSecuencialDecl);
        document.getElementById('egresoFormaPago').addEventListener('change', manejarCambioFormaPagoDecl);
        document.getElementById('egresoTipoOperacion').addEventListener('change', (e) => manejarCambioTipoOperacionDecl(e.target.value));

        if (btnEgreso) {
            btnEgreso.addEventListener('click', () => {
                if (!declaracionActual) return;
                document.getElementById('egresoMontoInfo').innerHTML = `Retenciones a pagar: <b>$${(parseFloat(declaracionActual.total_retenido) || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</b>`;
                document.getElementById('egresoFecha').value = new Date().toISOString().slice(0, 10);
                document.getElementById('egresoProveedorTexto').value = '';
                document.getElementById('egresoProveedorId').value = '';
                document.getElementById('egresoNumeroCheque').value = '';
                document.getElementById('egresoFechaCheque').value = '';
                document.getElementById('egresoBancoExtra').classList.add('d-none');

                fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/datos-egreso-ajax`).then(data => {
                    if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                    document.getElementById('egresoConcepto').innerHTML = data.conceptos.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
                    document.getElementById('egresoFormaPago').innerHTML = data.formas_pago.map(f => `<option value="${f.id}" data-tipo="${f.tipo || ''}">${f.nombre}</option>`).join('');
                    document.getElementById('egresoPuntoEmision').innerHTML = data.puntos_emision.map(p => `<option value="${p.id}">${p.cod_establecimiento}-${p.codigo_punto}</option>`).join('');

                    if (typeof aplicarFavoritosModal === 'function') aplicarFavoritosModal('#modalGenerarEgreso');

                    actualizarSecuencialDecl();
                    manejarCambioFormaPagoDecl();

                    modalEgreso = modalEgreso || new bootstrap.Modal(document.getElementById('modalGenerarEgreso'));
                    modalEgreso.show();
                });
            });
        }

        function setupTypeaheadDecl(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
            let debounceTimer;
            inputEl.addEventListener('keydown', (e) => {
                if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                    e.preventDefault();
                    hiddenEl.value = '';
                    inputEl.value = '';
                    dropdownEl.style.display = 'none';
                    dropdownEl.innerHTML = '';
                }
            });
            inputEl.addEventListener('input', () => {
                hiddenEl.value = '';
                clearTimeout(debounceTimer);
                const q = inputEl.value.trim();
                if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                debounceTimer = setTimeout(async () => {
                    let items = [];
                    try { items = await fetchFn(q); } catch (e) { return; }
                    if (!items || !items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                    dropdownEl.innerHTML = items.map(it => {
                        const label = renderLabel(it);
                        return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${label.replace(/"/g, '&quot;')}">${label}</a>`;
                    }).join('');
                    dropdownEl.style.display = 'block';
                }, 300);
            });
            dropdownEl.addEventListener('click', (e) => {
                const a = e.target.closest('a[data-id]');
                if (!a) return;
                e.preventDefault();
                hiddenEl.value = a.dataset.id;
                inputEl.value = a.dataset.label;
                dropdownEl.style.display = 'none';
            });
            document.addEventListener('click', (e) => {
                if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
            });
        }

        const egresoProveedorTexto = document.getElementById('egresoProveedorTexto');
        if (egresoProveedorTexto) {
            setupTypeaheadDecl(
                egresoProveedorTexto,
                document.getElementById('egresoProveedorDropdown'),
                document.getElementById('egresoProveedorId'),
                async (q) => {
                    const json = await fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/get-proveedores-ajax?q=${encodeURIComponent(q)}`);
                    return json.ok ? json.data : [];
                },
                (it) => it.identificacion ? `${it.razon_social} (${it.identificacion})` : it.razon_social
            );
        }

        const btnConfirmarEgreso = document.getElementById('btnConfirmarEgreso');
        if (btnConfirmarEgreso) {
            btnConfirmarEgreso.addEventListener('click', () => {
                if (!declaracionActual) return;
                const idProveedor = document.getElementById('egresoProveedorId').value;
                if (!idProveedor) return Swal.fire('Atención', 'Seleccione un proveedor de la lista.', 'warning');

                const fd = new FormData();
                fd.append('id_declaracion', declaracionActual.id);
                fd.append('id_proveedor', idProveedor);
                fd.append('id_egreso_concepto', document.getElementById('egresoConcepto').value);
                fd.append('id_forma_pago', document.getElementById('egresoFormaPago').value);
                fd.append('id_punto_emision', document.getElementById('egresoPuntoEmision').value);
                fd.append('fecha', document.getElementById('egresoFecha').value);

                if (!document.getElementById('egresoBancoExtra').classList.contains('d-none')) {
                    const tipoOp = document.getElementById('egresoTipoOperacion').value;
                    fd.append('tipo_operacion_bancaria', tipoOp);
                    if (tipoOp === 'CHEQUE') {
                        fd.append('numero_cheque', document.getElementById('egresoNumeroCheque').value);
                        fd.append('fecha_cobro', document.getElementById('egresoFechaCheque').value);
                    }
                }

                btnConfirmarEgreso.disabled = true;
                fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/generar-egreso-ajax`, { method: 'POST', body: fd }).then(data => {
                    btnConfirmarEgreso.disabled = false;
                    if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                    modalEgreso.hide();
                    Swal.fire({ title: 'Egreso generado', text: 'El egreso #' + data.id_egreso + ' fue registrado.', icon: 'success' });
                    verificarDeclarado();
                }).catch(() => { btnConfirmarEgreso.disabled = false; });
            });
        }
    });
</script>
