<?php
// Esta página tiene título, filtros y pestañas ENCIMA del formulario, así que aplica la
// excepción de CLAUDE.md §9: el app-shell (que bloquea el scroll del body y asume "título +
// una sola tabla que llena el alto") queda desactivado y la página scrollea normal. Sin esto,
// el Resumen 104 tenía que vivir encerrado en una caja de 50vh con scroll propio.
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    /* El formulario NO se encierra: se extiende hasta donde llegue y quien scrollea es la
       página. La barra de control de arriba (título + filtros + pestañas) queda fija. */
    .sri-container { font-family: 'Arial', sans-serif; margin-bottom: 10px; }
    .sri-section-title { background: #0d6efd; color: #fff; padding: 3px 8px; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; border: 1px solid #0a58ca; border-radius: 3px; }
    .casillero-tag { background: #eee; color: #000; border: 1px solid #999; padding: 0px 4px; font-weight: 700; font-size: 0.65rem; min-width: 30px; display: inline-block; text-align: center; border-radius: 1px; margin-right: 3px; }
    .val-cell { background: #fff; border: 1px solid #bbb; padding: 1px 4px; text-align: right; font-family: 'Courier New', monospace; font-weight: 700; font-size: 0.78rem; flex-grow: 1; min-height: 22px; }
    .sri-table td { padding: 2px 4px !important; vertical-align: middle; border: 1px solid #ccc; font-size: 0.7rem; }
    .sri-table .row-bold { background-color: #f2f2f2; font-weight: 700; }
    .nav-tabs .nav-link { font-weight: 700; font-size: 0.8rem; color: #555; }
    .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; }

    /* Barra de control: controles compactos y de la misma altura (los -sm de Bootstrap no
       rinden igual entre select, input y botón). */
    #formDeclaracion .form-select,
    #formDeclaracion .btn { height: 28px; }
    .cmg-control-card .card-footer .nav-tabs { margin-bottom: -1px; }
    /* Mientras no se ha generado nada las pestañas están ocultas: el pie de la tarjeta se
       esconde con ellas para no dejar una franja vacía. */
    .cmg-control-card .card-footer:has(> .nav-tabs.d-none) { display: none; }
    /* El panel de las pestañas arranca pegado a la barra de control. */
    #myTabContent { border: 1px solid #dee2e6; border-top: 0; }

    /* Las secciones del 104 se separan entre sí, ya sin el recuadro que las encerraba. */
    .sri-section-container + .sri-section-container { margin-top: 1rem; }
    .sri-table thead th { position: static; background: #f8f9fa; }

    /* Filtros y totales de la pestaña "Detalle de Casilleros": altura de controles forzada
       (los -sm de Bootstrap no rinden igual entre select, input e input-group). */
    #form-filtros-detalle .form-select,
    #form-filtros-detalle .form-control,
    #form-filtros-detalle .input-group-text,
    #form-filtros-detalle .btn { height: 28px; font-size: 0.75rem; }
    .dc-stat { display: flex; align-items: center; gap: 6px; }
    .dc-stat i { width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; }
    .dc-stat-value { font-weight: 700; font-size: 0.85rem; line-height: 1; }
    .dc-stat-label { font-size: 0.62rem; text-transform: uppercase; color: #6c757d; letter-spacing: .3px; }
    .dc-cas-chip { cursor: pointer; font-size: 0.7rem; }
    .dc-cas-chip:hover { border-color: #0d6efd !important; }
    .dc-cas-chip.activo { border-color: #0d6efd !important; background: #cfe2ff !important; }
</style>

<?php if (empty($esMatriz)): ?>
    <div class="container-fluid py-2">
        <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-start gap-3 p-4">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">Este no es el establecimiento matriz</h5>
                <?php if (!empty($matrizGrupo)): ?>
                    <p class="mb-0">La Declaración de IVA se presenta por RUC completo y se debe generar desde el
                        establecimiento matriz: <strong><?= htmlspecialchars((string)($matrizGrupo['establecimiento'] ?? '')) ?> - <?= htmlspecialchars((string)($matrizGrupo['nombre'] ?? '')) ?></strong>.
                        Cambie a esa empresa para declarar este período.</p>
                <?php else: ?>
                    <p class="mb-0">La Declaración de IVA se presenta por RUC completo y se debe generar desde el
                        establecimiento matriz, pero este grupo (RUC <?= htmlspecialchars((string)($empresaActual['ruc'] ?? '')) ?>)
                        todavía no tiene ninguno configurado. Configúrelo en
                        <a href="<?= $base ?>/modulos/empresa#establecimientos">Empresa → Establecimientos</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="container-fluid py-2">
    <!-- Barra de control fija: título, filtros/acciones y pestañas (CLAUDE.md §9). Se queda
         pegada bajo el navbar mientras el formulario de abajo scrollea con la página. -->
    <div class="card shadow-sm border cmg-control-card mb-0 print-none">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Declaración de IVA (form 104 SRI)</h5>
        </div>
        <div class="card-body p-2 text-start">
            <?php // Flexbox puro con anchos fijos por campo (CLAUDE.md §9): con el grid .row el
                  // punto de quiebre variaba y los botones saltaban de línea de forma errática. ?>
            <form id="formDeclaracion" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label d-block fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Período</label>
                    <div class="btn-group btn-group-sm">
                        <input type="radio" class="btn-check" name="tipo_periodo" id="tipo_mensual" value="mensual" checked>
                        <label class="btn btn-outline-primary fw-bold" for="tipo_mensual">Mensual</label>
                        <input type="radio" class="btn-check" name="tipo_periodo" id="tipo_semestral" value="semestral">
                        <label class="btn btn-outline-primary fw-bold" for="tipo_semestral">Semestral</label>
                    </div>
                </div>
                <div style="width: 85px;">
                    <label class="form-label d-block fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">Año</label>
                    <select name="anio" class="form-select form-select-sm border-0 bg-light fw-bold" id="anio">
                        <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="width: 170px;">
                    <label class="form-label d-block fw-bold small text-uppercase text-muted mb-1" id="labelPeriodo" style="font-size: 0.6rem;">Mes</label>
                    <select name="periodo" class="form-select form-select-sm border-0 bg-light fw-bold" id="periodo"></select>
                </div>
                <div>
                    <label class="form-label d-block fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">&nbsp;</label>
                    <div class="form-check form-switch mb-0 ms-2 text-nowrap d-flex align-items-center" style="height:28px;" title="Mostrar solo las filas que tengan algún valor">
                        <input class="form-check-input mt-0" type="checkbox" id="checkSoloValores">
                        <label class="form-check-label fw-bold small text-muted ms-2" for="checkSoloValores" style="font-size: 0.7rem;">Solo valores</label>
                    </div>
                </div>
                <div>
                    <?php // Label invisible con d-block: iguala la altura de las etiquetas para que
                          // los botones arranquen a la misma altura que los selects (CLAUDE.md §9). ?>
                    <label class="form-label d-block fw-bold small text-uppercase text-muted mb-1" style="font-size: 0.6rem;">&nbsp;</label>
                    <div class="d-flex flex-wrap align-items-start gap-2">
                    <button type="submit" class="btn btn-primary btn-sm fw-bold py-1 px-3">GENERAR</button>
                    <button type="button" id="btnExportarExcel" class="btn btn-success btn-sm fw-bold py-1 px-3 d-none" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> EXCEL
                    </button>
                    <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                    <button type="button" id="btnGuardarDeclaracion" class="btn btn-outline-primary btn-sm fw-bold py-1 px-3 d-none">
                        <i class="bi bi-save"></i> GUARDAR DECLARACIÓN
                    </button>
                    <button type="button" id="btnGenerarAsiento" class="btn btn-outline-dark btn-sm fw-bold py-1 px-3 d-none">
                        <i class="bi bi-journal-text"></i> GENERAR ASIENTO
                    </button>
                    <button type="button" id="btnGenerarEgreso" class="btn btn-outline-danger btn-sm fw-bold py-1 px-3 d-none">
                        <i class="bi bi-cash-coin"></i> GENERAR EGRESO
                    </button>
                    <?php endif; ?>
                    </div>
                </div>
            </form>

            <div id="avisoDeclarado" class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none text-start small"></div>
        </div>

        <!-- Las pestañas viven en el pie de la barra de control para que sigan visibles
             mientras se recorre el formulario. -->
        <div class="card-footer bg-white border-top p-0">
            <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña d-none px-2 pt-1" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen 104</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="detalle-tab" data-bs-toggle="tab" data-bs-target="#detalle" type="button" role="tab">Detalle de Casilleros</button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content bg-white p-3 d-none" id="myTabContent">
        <!-- Pestaña 1 -->
        <div class="tab-pane fade show active" id="resumen" role="tabpanel">
            <div id="avisoFormulas" class="alert alert-warning py-2 px-3 mb-2 d-none small"></div>
            <div id="formSRI" class="sri-container"></div>
        </div>
        
        <!-- Pestaña 2 -->
        <div class="tab-pane fade" id="detalle" role="tabpanel">

            <!-- Filtros del detalle -->
            <div class="card border shadow-sm mb-2">
                <div class="card-body p-2">
                    <form id="form-filtros-detalle" class="d-flex flex-wrap align-items-start gap-2" onsubmit="return false;">
                        <div style="width:170px;">
                            <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">Tipo de documento</label>
                            <select id="detalleOrigen" class="form-select form-select-sm"><option value="">Todos</option></select>
                        </div>
                        <div style="width:150px;">
                            <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">Casillero</label>
                            <select id="detalleCasillero" class="form-select form-select-sm"><option value="">Todos</option></select>
                        </div>
                        <div style="width:150px;">
                            <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">Documento</label>
                            <input type="text" id="detalleDocumento" class="form-control form-control-sm" placeholder="001-101-0000…" autocomplete="off">
                        </div>
                        <div style="width:300px;">
                            <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">Cliente / Proveedor</label>
                            <input type="text" id="detalleEntidad" class="form-control form-control-sm" list="detalleEntidadesLista" placeholder="Nombre o identificación" autocomplete="off">
                            <datalist id="detalleEntidadesLista"></datalist>
                        </div>
                        <div class="d-flex flex-wrap align-items-start gap-2">
                            <div style="width:240px;">
                                <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">Buscar (todo)</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="detalleBuscar" class="form-control form-control-sm" placeholder="Número, entidad, concepto…" autocomplete="off">
                                </div>
                            </div>
                            <div>
                                <label class="form-label d-block fw-bold text-uppercase text-muted mb-1" style="font-size:0.6rem;">&nbsp;</label>
                                <button type="button" id="detalleLimpiar" class="btn btn-outline-secondary btn-sm fw-bold"><i class="bi bi-eraser"></i> Limpiar</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Totales de lo filtrado -->
                <div class="card-footer bg-white border-top py-2 px-3">
                    <div id="detalleTotales" class="d-flex flex-wrap align-items-center gap-3"></div>
                    <div id="detalleTotalesCasilleros" class="mt-2"></div>
                </div>
            </div>

            <div id="accordionDetalle" class="accordion accordion-flush"></div>
        </div>
    </div>
</div>

<!-- Modal: Generar Egreso -->
<div class="modal fade" id="modalGenerarEgreso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-cash-coin text-danger me-1"></i> Generar Egreso — Pago Declaración de IVA</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">El egreso se registra a nombre del proveedor/tercero que realizará el pago del IVA al SRI.</p>

                <!-- Fila 1: Fecha, Serie, Secuencial -->
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

                <!-- Fila 2: Proveedor, Concepto de Egreso, Forma de Pago -->
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
                        <label class="form-label small fw-bold mb-1 d-flex align-items-center">Concepto de Egreso <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('declaracion_iva', 'egresoConcepto', 'id_egreso_concepto_default') ?></label>
                        <select id="egresoConcepto" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1 d-flex align-items-center">Forma de Pago <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('declaracion_iva', 'egresoFormaPago', 'id_forma_pago_default') ?></label>
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

                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Valor a pagar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" id="egresoMonto" class="form-control fw-bold text-danger" onfocus="this.select()">
                        </div>
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

<?php // Modal de Asiento Contable estándar (mismo del módulo Libro Diario / Asientos):
      // permite ver, modificar y agregar cuentas al asiento generado, sin cerrarse solo. ?>
<?php require MVC_APP . '/views/modulos/asientos_contables/modal_asiento.php'; ?>
<script src="<?= $base ?>/js/modulos/asientos_contables_modal.js?v=<?= time() ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formDeclaracion');
        const selPeriodo = document.getElementById('periodo');
        const labelPeriodo = document.getElementById('labelPeriodo');
        const formSRI = document.getElementById('formSRI');
        const tabsContainer = document.getElementById('myTab');
        const tabContent = document.getElementById('myTabContent');

        // Estado del último resumen renderizado, para el recálculo de fórmulas en vivo
        // cuando el usuario edita un casillero editable (sin volver a pedir el servidor).
        let ultimoLayout = [];
        let ultimosValores = {};
        let ultimoTotal480481 = 0;
        let ultimoDetalle = []; // último detalle_documentos recibido, para filtrar sin volver a pedir el servidor

        // Map (no objeto literal): en los objetos JS las claves '10'-'12' se ordenan
        // numéricamente antes que '01'-'09' y los meses salían desordenados
        const meses = new Map([
            ['01', 'Enero'], ['02', 'Febrero'], ['03', 'Marzo'], ['04', 'Abril'],
            ['05', 'Mayo'], ['06', 'Junio'], ['07', 'Julio'], ['08', 'Agosto'],
            ['09', 'Septiembre'], ['10', 'Octubre'], ['11', 'Noviembre'], ['12', 'Diciembre']
        ]);
        const semestres = new Map([['1', 'Primer Semestre'], ['2', 'Segundo Semestre']]);

        const mesDefault = '<?= htmlspecialchars((string) $mes) ?>';

        function actualizarPeriodos() {
            const tipo = document.querySelector('input[name="tipo_periodo"]:checked').value;
            selPeriodo.innerHTML = '';
            if (tipo === 'mensual') {
                labelPeriodo.innerText = 'Mes';
                for (const [v, m] of meses) selPeriodo.insertAdjacentHTML('beforeend', `<option value="${v}">${m}</option>`);
                if (meses.has(mesDefault)) selPeriodo.value = mesDefault;
            } else {
                labelPeriodo.innerText = 'Semestre';
                for (const [v, s] of semestres) selPeriodo.insertAdjacentHTML('beforeend', `<option value="${v}">${s}</option>`);
            }
        }
        document.querySelectorAll('input[name="tipo_periodo"]').forEach(el => el.addEventListener('change', actualizarPeriodos));
        actualizarPeriodos();

        document.getElementById('checkSoloValores').addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.sri-row-data').forEach(row => {
                if (row.getAttribute('data-has-values') === '0') {
                    if (isChecked) {
                        row.classList.add('d-none');
                    } else {
                        row.classList.remove('d-none');
                    }
                }
            });
        });

        form.addEventListener('submit', e => {
            e.preventDefault();
            if (declaracionActual && declaracionActual.estado === 'pagado') {
                Swal.fire({
                    title: 'Declaración cerrada',
                    html: 'Esta declaración ya tiene un <b>asiento</b> y un <b>egreso</b> generados.<br>Para recalcularla hay que <b>anular</b> el asiento y el egreso actuales. ¿Desea continuar?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reabrir y regenerar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then(r => {
                    if (!r.isConfirmed) return;
                    const fd = new FormData();
                    fd.append('id_declaracion', declaracionActual.id);
                    fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/reabrir-ajax`, { method: 'POST', body: fd }).then(data => {
                        if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                        declaracionActual = data.declaracion || null;
                        actualizarBotonesDeclaracion();
                        generar();
                    });
                });
                return;
            }
            generar();
        });

        function generar() {
            const params = new URLSearchParams(new FormData(form)).toString() + '&sincronizar=1';
            tabsContainer.classList.remove('d-none');
            tabContent.classList.remove('d-none');
            formSRI.innerHTML = '<div class="text-center py-5 small text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Sincronizando y generando reporte...</div>';
            document.getElementById('accordionDetalle').innerHTML = '<div class="text-center py-3">Cargando detalles...</div>';

            fetch(`<?= $base ?>/<?= $rutaModulo ?>/generar-ajax?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.json()).then(data => {
                if (!data.ok) return Swal.fire('Error', data.mensaje, 'error');
                renderVentas(data.resumen_completo);
                ultimoDetalle = data.detalle_documentos || [];
                poblarFiltrosDetalle(ultimoDetalle);
                limpiarFiltrosDetalle();
                aplicarFiltrosDetalle();
                document.getElementById('btnExportarExcel').classList.remove('d-none');
                yaGenerado = true;
                actualizarBotonesDeclaracion();
            });
        }

        window.exportarExcel = function() {
            const params = new URLSearchParams(new FormData(form));
            // Los casilleros editables van con el valor que tienen AHORA en pantalla: si no se
            // envían, el Excel exporta el valor por defecto del servidor y no coincide con lo
            // que el usuario está viendo (ajustes de 615/617/481/484/486/902 sin guardar).
            ['615', '617', '481', '484', '486', '902'].forEach(codigo => {
                const inp = formSRI.querySelector('input[data-casillero-editable="' + codigo + '"]');
                if (inp && inp.value !== '') params.append('ajuste_' + codigo, inp.value);
            });
            window.open(`<?= $base ?>/<?= $rutaModulo ?>/exportar-excel?${params.toString()}`, '_blank');
        };

        // Fórmulas configuradas en /config/sri-casilleros-etiquetas que no llegan a verse en el
        // formulario (fórmula en una columna sin casillero, fila de tipo "título", códigos
        // inexistentes o sintaxis que no se puede evaluar). Antes fallaban en silencio y el
        // casillero salía en blanco sin explicación.
        function renderAvisosFormulas(avisos) {
            const cont = document.getElementById('avisoFormulas');
            if (!avisos || !avisos.length) { cont.classList.add('d-none'); cont.innerHTML = ''; return; }
            const esc = t => String(t == null ? '' : t).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
            cont.innerHTML =
                `<div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>` +
                `${avisos.length} fórmula${avisos.length === 1 ? '' : 's'} configurada${avisos.length === 1 ? '' : 's'} que no se está${avisos.length === 1 ? '' : 'n'} aplicando</div>` +
                '<ul class="mb-1 ps-3">' +
                avisos.map(a => `<li><b>${a.casillero ? 'Casillero ' + esc(a.casillero) : 'Fila sin casillero'}</b>` +
                    `${a.descripcion ? ' — ' + esc(a.descripcion) : ''}: <code>${esc(a.formula)}</code><br>${esc(a.motivo)}</li>`).join('') +
                '</ul>' +
                '<div class="text-muted" style="font-size:0.7rem;">Se corrige en Configuración → Casilleros SRI.</div>';
            cont.classList.remove('d-none');
        }

        function renderVentas(resumenData) {
            const layout = resumenData.layout;
            const valores = resumenData.valores;
            const isChecked = document.getElementById('checkSoloValores').checked;

            renderAvisosFormulas(resumenData.avisos_formulas);

            ultimoLayout = layout;
            ultimosValores = valores;
            ultimoTotal480481 = parseFloat(resumenData.total_480_481) || 0;

            // Pre-calcular valores
            layout.forEach(r => {
                if (r.tipo !== 'titulo') {
                    const cBruto = r.casillero_bruto || '';
                    const vBruto = cBruto ? (parseFloat(valores[cBruto]) || 0) : null;
                    const cNeto = r.casillero_neto || '';
                    const vNeto = cNeto ? (parseFloat(valores[cNeto]) || 0) : null;
                    const cImp = r.casillero_impuesto || '';
                    const vImp = cImp ? (parseFloat(valores[cImp]) || 0) : null;
                    // Un casillero editable debe verse siempre, aunque esté en 0: es precisamente
                    // cuando está vacío que el usuario necesita encontrarlo para llenarlo.
                    r.hasValues = r.editable || (vBruto !== null && vBruto !== 0) || (vNeto !== null && vNeto !== 0) || (vImp !== null && vImp !== 0);
                } else {
                    r.hasValues = false;
                }
            });

            // Propagar a los títulos
            for (let i = layout.length - 1; i >= 0; i--) {
                const r = layout[i];
                if (r.tipo === 'titulo') {
                    let j = i + 1;
                    while (j < layout.length && layout[j].seccion === r.seccion) {
                        const next = layout[j];
                        if (next.tipo === 'titulo' && next.indent <= r.indent) break;
                        if (next.hasValues) {
                            r.hasValues = true;
                            break;
                        }
                        j++;
                    }
                }
            }

            const seccionHasValues = {};
            layout.forEach(r => {
                if (r.hasValues) seccionHasValues[r.seccion] = true;
            });

            let currentSeccion = '';
            let html = '';

            layout.forEach(r => {
                if (r.seccion !== currentSeccion) {
                    if (currentSeccion !== '') html += '</tbody></table></div></div>';
                    const sHasValues = seccionHasValues[r.seccion] ? '1' : '0';
                    const dNoneSec = (isChecked && !seccionHasValues[r.seccion]) ? 'd-none' : '';
                    html += `<div class="sri-section-container sri-row-data ${dNoneSec}" data-has-values="${sHasValues}">`;
                    html += `<div class="sri-section-title mt-3">SECCIÓN: ${r.seccion}</div>`;
                    html += `<div class="table-responsive"><table class="table table-bordered table-sm sri-table align-middle w-100 mb-0" style="font-size: 0.8rem;">`;
                    html += `<thead class="table-light text-center">
                                <tr>
                                    <th style="width:40%;">Concepto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Valor Bruto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Valor Neto</th>
                                    <th style="width:5%;">Cas.</th><th style="width:15%;">Impuesto Gen.</th>
                                </tr>
                             </thead><tbody>`;
                    currentSeccion = r.seccion;
                }

                const marginLeft = r.indent > 0 ? (r.indent * 15) + 'px' : '0px';
                const rowClass = r.bold ? 'fw-bold text-dark bg-light' : '';
                const descFormatted = r.descripcion;
                const dNoneRow = (isChecked && !r.hasValues) ? 'd-none' : '';
                
                if (r.tipo === 'titulo') {
                    html += `<tr class="${rowClass} sri-row-data ${dNoneRow}" data-has-values="${r.hasValues ? '1' : '0'}"><td colspan="7" class="ps-2 py-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${descFormatted}</td></tr>`;
                } else {
                    const cBruto = r.casillero_bruto || '';
                    const vBruto = cBruto ? (parseFloat(valores[cBruto]) || 0) : null;
                    const cNeto = r.casillero_neto || '';
                    const vNeto = cNeto ? (parseFloat(valores[cNeto]) || 0) : null;
                    const cImp = r.casillero_impuesto || '';
                    const vImp = cImp ? (parseFloat(valores[cImp]) || 0) : null;

                    // Las filas de conteo muestran cantidades (enteros), no montos. El arrastre
                    // de crédito tributario y la liquidación diferida siempre son montos (2 decimales).
                    const esArrastre = (r.fuente_valor || '').startsWith('arrastre_');
                    const esConteo = r.fuente_valor && r.fuente_valor !== 'documentos' && !esArrastre;
                    const dec = esConteo ? 0 : 2;
                    const fmt = v => v.toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec});

                    // Editable: propiedad genérica configurada en /config/sri-casilleros-etiquetas.
                    // Un casillero puede vivir en la columna Bruto, Neto o Impuesto según la fila
                    // (la mayoría de las filas de una sola columna usan Impuesto o Bruto indistintamente);
                    // por eso esto se resuelve por columna, no asumiendo que siempre es "Bruto".
                    // 605/606 (arrastre "entrante") no son editables pero muestran un candado
                    // informativo: vienen automáticamente del período anterior.
                    const esArrastreEntrante = r.fuente_valor === 'arrastre_entrante_compras' || r.fuente_valor === 'arrastre_entrante_retenciones';
                    const celdaValor = (codigo, valor) => {
                        if (!codigo) return valor !== null ? fmt(valor) : '';
                        // Ícono de copiar: solo en casilleros con valor distinto de cero.
                        const tieneValor = valor !== null && valor !== 0;
                        const icono = tieneValor ? `<i class="bi bi-clipboard copy-casillero-icon text-muted ms-1" data-copy-codigo="${codigo}" title="Copiar valor" role="button" style="cursor:pointer;font-size:0.7rem;"></i>` : '';
                        let contenido;
                        if (r.editable) {
                            contenido = `<input type="number" step="0.01" class="form-control form-control-sm text-end p-0 border-0 bg-warning bg-opacity-10 fw-bold" style="height:22px;font-size:0.78rem;" data-casillero-editable="${codigo}" data-decimales="${dec}" value="${(valor ?? 0).toFixed(dec)}">`;
                        } else {
                            const candado = esArrastreEntrante ? ' <i class="bi bi-lock-fill text-muted" style="font-size:0.65rem;" title="Se completa automáticamente con el arrastre del período anterior"></i>' : '';
                            contenido = `<span data-casillero-display="${codigo}" data-decimales="${dec}">${valor !== null ? fmt(valor) : ''}</span>${candado}`;
                        }
                        return `<div class="d-flex align-items-center justify-content-end">${contenido}${icono}</div>`;
                    };
                    const tdBruto = celdaValor(cBruto, vBruto);
                    const tdNetoContenido = celdaValor(cNeto, vNeto);
                    const tdImpContenido = celdaValor(cImp, vImp);

                    html += `<tr class="${rowClass} sri-row-data ${dNoneRow}" data-has-values="${r.hasValues ? '1' : '0'}">
                        <td class="ps-2" style="padding-left: calc(0.5rem + ${marginLeft}) !important;">${descFormatted}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cBruto ? '<b>'+cBruto+'</b>' : ''}</td>
                        <td class="text-end">${tdBruto}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cNeto ? '<b>'+cNeto+'</b>' : ''}</td>
                        <td class="text-end">${tdNetoContenido}</td>
                        <td class="text-center text-muted" style="font-size:0.7rem;">${cImp ? '<b>'+cImp+'</b>' : ''}</td>
                        <td class="text-end">${tdImpContenido}</td>
                    </tr>`;
                }
            });
            if (currentSeccion !== '') html += '</tbody></table></div></div>';
            formSRI.innerHTML = html;
        }

        // ==========================================================================
        // Motor de fórmulas en el navegador — puerto de DeclaracionIvaService::
        // evaluarMatematica() + el loop de sustitución de getResumenCompleto(). Recalcula
        // los casilleros con fórmula cuando el usuario edita un casillero "editable"
        // (configurado en /config/sri-casilleros-etiquetas), sin volver a pedir al servidor.
        // ==========================================================================
        // Evaluador aritmético (+ - * / y paréntesis). Calca DeclaracionIvaService::
        // evaluarMatematica(): sin eval, y DIVIDIR POR CERO DA 0 en esa operación — en el 104
        // los cocientes son factores de proporcionalidad (563) o interruptores del tipo
        // (615/615)*609, y con denominador cero el resultado correcto es cero.
        function evaluarMatematicaJS(expr) {
            const limpio = String(expr).replace(/[^0-9+\-*/.()\s]/g, ' ');
            if (!limpio.trim()) return null;

            // Tokenizar. El espacio no es token pero sí corta números: "401 402" son dos valores.
            const tokens = [];
            for (let i = 0; i < limpio.length;) {
                const c = limpio[i];
                if (/\s/.test(c)) { i++; continue; }
                if (/[0-9.]/.test(c)) {
                    let num = '';
                    while (i < limpio.length && /[0-9.]/.test(limpio[i])) { num += limpio[i]; i++; }
                    const v = parseFloat(num);
                    if (!isFinite(v)) return null;
                    tokens.push(v);
                    continue;
                }
                tokens.push(c);
                i++;
            }

            let pos = 0;
            const factor = () => {
                if (pos >= tokens.length) return null;
                const t = tokens[pos];
                if (t === '+' || t === '-') {
                    pos++;
                    const v = factor();
                    return v === null ? null : (t === '-' ? -v : v);
                }
                if (t === '(') {
                    pos++;
                    const v = suma();
                    if (v === null || tokens[pos] !== ')') return null;
                    pos++;
                    return v;
                }
                if (typeof t === 'number') { pos++; return t; }
                return null;
            };
            const producto = () => {
                let v = factor();
                if (v === null) return null;
                while (pos < tokens.length && (tokens[pos] === '*' || tokens[pos] === '/')) {
                    const op = tokens[pos]; pos++;
                    const d = factor();
                    if (d === null) return null;
                    v = (op === '*') ? v * d : (Math.abs(d) < 1e-12 ? 0 : v / d);
                }
                return v;
            };
            const suma = () => {
                let v = producto();
                if (v === null) return null;
                while (pos < tokens.length && (tokens[pos] === '+' || tokens[pos] === '-')) {
                    const op = tokens[pos]; pos++;
                    const d = producto();
                    if (d === null) return null;
                    v = (op === '+') ? v + d : v - d;
                }
                return v;
            };

            const valor = suma();
            if (valor === null || pos !== tokens.length || !isFinite(valor)) return null;
            return valor;
        }

        // Resuelve una fórmula de casilleros. Calca DeclaracionIvaService::resolverFormula():
        // los separadores de lista valen como suma y la expresión se limpia ANTES de sustituir
        // los códigos, para que un código pegado a una letra ("C401") no acabe evaluándose como
        // el número literal 401.
        function resolverFormulaJS(formula, valores) {
            let expr = String(formula).replace(/[,;]/g, '+').replace(/[^0-9+\-*/.()\s]/g, ' ');
            if (!expr.trim()) return null;
            expr = expr.replace(/\b(\d{3})\b/g, (m, cod) => {
                const v = valores[cod];
                return '(' + (v === undefined || v === null ? 0 : parseFloat(v) || 0) + ')';
            });
            return evaluarMatematicaJS(expr);
        }

        function recalcularFormulasJS(valores, layout) {
            const formulas = {};
            layout.forEach(r => {
                if (r.casillero_bruto && r.formula_bruto) formulas[r.casillero_bruto] = r.formula_bruto;
                if (r.casillero_neto && r.formula_neto) formulas[r.casillero_neto] = r.formula_neto;
                if (r.casillero_impuesto && r.formula_impuesto) formulas[r.casillero_impuesto] = r.formula_impuesto;
            });

            for (let pasada = 0; pasada < 5; pasada++) {
                let cambio = false;
                for (const casillero in formulas) {
                    const calculado = resolverFormulaJS(formulas[casillero], valores);
                    if (calculado === null) continue; // fórmula inválida: se avisa desde el servidor
                    const resultado = Math.max(0, calculado);
                    const actual = parseFloat(valores[casillero]) || 0;
                    if (Math.abs(actual - resultado) > 0.001) {
                        valores[casillero] = resultado;
                        cambio = true;
                    }
                }
                if (!cambio) break;
            }
            return valores;
        }

        function actualizarCeldaCasillero(codigo, valor) {
            document.querySelectorAll('[data-casillero-display="' + codigo + '"]').forEach(el => {
                const dec = parseInt(el.getAttribute('data-decimales') || '2', 10);
                el.textContent = Number(valor).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
            });
        }

        // Delegado: cualquier input editable del "Resumen 104" dispara el recálculo en vivo.
        formSRI.addEventListener('input', (e) => {
            const input = e.target.closest('[data-casillero-editable]');
            if (!input) return;
            const codigo = input.getAttribute('data-casillero-editable');
            const nuevoValor = parseFloat(input.value) || 0;
            ultimosValores[codigo] = nuevoValor;

            // Caso especial 480/481: son complementarios, siempre suman el total gravado del período.
            if (codigo === '481') {
                const nuevoValor480 = Math.round((ultimoTotal480481 - nuevoValor) * 100) / 100;
                ultimosValores['480'] = nuevoValor480;
                const input480 = formSRI.querySelector('[data-casillero-editable="480"]');
                if (input480) input480.value = nuevoValor480.toFixed(2);
                else actualizarCeldaCasillero('480', nuevoValor480);
            }

            recalcularFormulasJS(ultimosValores, ultimoLayout);
            Object.keys(ultimosValores).forEach(cod => actualizarCeldaCasillero(cod, ultimosValores[cod]));
        });

        // Ícono de copiar: copia el valor ACTUAL del casillero (lee el input si es editable,
        // o el texto mostrado si es de solo lectura, para no copiar un valor desactualizado
        // tras un recálculo en vivo).
        formSRI.addEventListener('click', (e) => {
            const icono = e.target.closest('.copy-casillero-icon');
            if (!icono) return;
            const codigo = icono.getAttribute('data-copy-codigo');
            const input = formSRI.querySelector(`[data-casillero-editable="${codigo}"]`);
            const texto = input ? input.value : (formSRI.querySelector(`[data-casillero-display="${codigo}"]`)?.textContent.trim() || '');
            if (!texto) return;

            const marcarCopiado = () => {
                icono.classList.remove('bi-clipboard', 'text-muted');
                icono.classList.add('bi-clipboard-check', 'text-success');
                setTimeout(() => {
                    icono.classList.remove('bi-clipboard-check', 'text-success');
                    icono.classList.add('bi-clipboard', 'text-muted');
                }, 1200);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(marcarCopiado).catch(() => {});
            } else {
                // Respaldo para navegadores/contextos sin acceso al portapapeles moderno.
                const tmp = document.createElement('textarea');
                tmp.value = texto;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); marcarCopiado(); } catch (err) {}
                document.body.removeChild(tmp);
            }
        });

        function renderDetalle(detalle) {
            let html = '';
            const accordionDetalle = document.getElementById('accordionDetalle');
            if (detalle.length === 0) {
                const hayFiltro = ['detalleBuscar', 'detalleDocumento', 'detalleEntidad'].some(id => document.getElementById(id).value.trim() !== '')
                    || document.getElementById('detalleOrigen').value !== ''
                    || document.getElementById('detalleCasillero').value !== '';
                accordionDetalle.innerHTML = '<div class="text-center text-muted py-3">' +
                    (hayFiltro ? 'Sin resultados para los filtros aplicados.' : 'No hay documentos sincronizados.') + '</div>';
                return;
            }

            // Agrupar por docNum
            const grupos = {};
            detalle.forEach(d => {
                const docNum = d.establecimiento ? `${d.establecimiento}-${d.punto_emision}-${d.secuencial}` : `ID: ${d.id_origen}`;
                const key = `${d.origen}_${docNum}`;
                if (!grupos[key]) {
                    grupos[key] = {
                        origen: d.origen,
                        docNum: docNum,
                        fecha: d.fecha,
                        entidad: d.entidad || '',
                        identificacion: d.identificacion || '',
                        items: [],
                        total: 0
                    };
                }
                grupos[key].items.push(d);
                grupos[key].total += parseFloat(d.valor) || 0;
            });

            // Orden de los grupos: primero por tipo de documento (Compras, Facturas de Venta,
            // Retenciones, Importaciones...), y dentro de cada tipo por fecha. Los orígenes no
            // listados aquí caen al final, en el orden en que llegaron.
            const ordenOrigen = ['compras', 'liquidaciones_compras', 'facturas de venta', 'notas_credito', 'notas de debito', 'retenciones_ventas', 'retenciones_compras', 'importaciones'];
            const listaGrupos = Object.values(grupos).sort((a, b) => {
                const pa = ordenOrigen.indexOf(a.origen); const pb = ordenOrigen.indexOf(b.origen);
                const ra = pa === -1 ? ordenOrigen.length : pa; const rb = pb === -1 ? ordenOrigen.length : pb;
                if (ra !== rb) return ra - rb;
                return new Date(a.fecha) - new Date(b.fecha);
            });

            let i = 0;
            for (const g of listaGrupos) {
                const headerId = 'heading' + i;
                const collapseId = 'collapse' + i;
                
                // Agrupar items por concepto unificando Base e IVA
                const conceptosMap = {};
                g.items.forEach(d => {
                    let concepto = d.concepto || 'Sin concepto';
                    concepto = concepto.replace(/\s\((Base|IVA)\)$/i, '');
                    if (!conceptosMap[concepto]) conceptosMap[concepto] = [];
                    conceptosMap[concepto].push(d);
                });

                let filasHtml = '';
                for (const concepto in conceptosMap) {
                    const casillerosItems = conceptosMap[concepto];
                    
                    // Ordenar casilleros de menor a mayor
                    casillerosItems.sort((a, b) => parseInt(a.casillero) - parseInt(b.casillero));
                    
                    let badgesHtml = '';
                    casillerosItems.forEach(d => {
                        const val = parseFloat(d.valor) || 0;
                        const modBadge = d.editado_manualmente ? `<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Editado Manualmente" style="font-size: 0.75rem;"></i>` : '';
                        
                        badgesHtml += `
                        <div class="d-inline-flex align-items-center bg-light border rounded px-2 py-1 me-2 mb-1" 
                             style="cursor: pointer; transition: all 0.2s ease;"
                             onmouseover="this.classList.add('shadow-sm', 'border-primary')"
                             onmouseout="this.classList.remove('shadow-sm', 'border-primary')"
                             onclick="editarCasillero(${d.id}, '${d.casillero}')" 
                             title="Clic para cambiar a qué casillero pertenece este valor">
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

                // Determinar color por origen
                let colorOrigen = 'secondary';
                if (g.origen === 'facturas de venta') colorOrigen = 'primary';
                else if (g.origen === 'compras') colorOrigen = 'success';
                else if (g.origen === 'notas_credito') colorOrigen = 'warning';
                else if (g.origen === 'liquidaciones_compras') colorOrigen = 'info';

                html += `
                <div class="accordion-item border-0 mb-2 shadow-sm rounded">
                    <h2 class="accordion-header" id="${headerId}">
                        <button class="accordion-button collapsed py-3 rounded" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-${colorOrigen} bg-opacity-10 text-${colorOrigen} border border-${colorOrigen} border-opacity-25 px-2 py-1 text-uppercase" style="font-size: 0.7rem;">${g.origen.replace(/_/g, ' ')}</span>
                                    <span class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;">#${g.docNum}</span>
                                    <span class="fw-medium text-secondary" style="font-size: 0.85rem;"><i class="bi bi-person-fill text-muted me-1"></i>${g.entidad ? g.entidad : 'Sin entidad asignada'}${g.identificacion ? ' <span class="text-muted">(' + g.identificacion + ')</span>' : ''}</span>
                                    <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>${new Date(g.fecha).toLocaleDateString('es-ES', {day: '2-digit', month: 'short', year: 'numeric'})}</span>
                                </div>
                                <span class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;" title="Suma de los casilleros visibles de este documento">${fmtMoneda(g.total)}</span>
                            </div>
                        </button>
                    </h2>
                    <div id="${collapseId}" class="accordion-collapse collapse" data-bs-parent="#accordionDetalle">
                        <div class="accordion-body p-3 bg-white border border-top-0 rounded-bottom">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                                    <thead class="table-light text-start border-bottom">
                                        <tr>
                                            <th class="text-start ps-3 text-muted fw-semibold" style="width: 40%;">Concepto del Valor</th>
                                            <th class="text-muted fw-semibold">Casilleros Reportados <small class="text-primary fw-normal ms-2">(Clic en un casillero para editarlo)</small></th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">${filasHtml}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>`;
                i++;
            }
            accordionDetalle.innerHTML = html;
        }

        // ==========================================================================
        // Filtros y totales de la pestaña "Detalle de Casilleros"
        // Todo se resuelve sobre el detalle ya cargado (ultimoDetalle), sin volver a
        // pedir al servidor: filtrar es instantáneo y los totales de abajo siempre
        // corresponden EXACTAMENTE a lo que se está viendo.
        // ==========================================================================
        <?php
        // Descripción oficial de cada casillero (para rotular los filtros y los chips de suma).
        $mapaCasilleroDesc = [];
        foreach (($estructura ?? []) as $e) {
            foreach (['casillero_bruto', 'casillero_neto', 'casillero_impuesto'] as $k) {
                if (!empty($e[$k]) && !isset($mapaCasilleroDesc[$e[$k]])) {
                    $mapaCasilleroDesc[$e[$k]] = trim((string) ($e['descripcion'] ?? ''));
                }
            }
        }
        ?>
        const CASILLERO_DESC = <?= json_encode($mapaCasilleroDesc, JSON_UNESCAPED_UNICODE) ?>;

        const filtroOrigen    = document.getElementById('detalleOrigen');
        const filtroCasillero = document.getElementById('detalleCasillero');
        const filtroDocumento = document.getElementById('detalleDocumento');
        const filtroEntidad   = document.getElementById('detalleEntidad');
        const filtroBuscar    = document.getElementById('detalleBuscar');

        function numeroDocumento(d) {
            return d.establecimiento ? `${d.establecimiento}-${d.punto_emision}-${d.secuencial}` : `ID: ${d.id_origen}`;
        }

        function fmtMoneda(v) {
            return (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Rellena los selectores con lo que realmente trae el período cargado.
        function poblarFiltrosDetalle(detalle) {
            const origenes = [...new Set(detalle.map(d => d.origen || '').filter(Boolean))].sort();
            filtroOrigen.innerHTML = '<option value="">Todos</option>' +
                origenes.map(o => `<option value="${o}">${o.replace(/_/g, ' ')}</option>`).join('');

            const casilleros = [...new Set(detalle.map(d => d.casillero || '').filter(Boolean))]
                .sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true }));
            filtroCasillero.innerHTML = '<option value="">Todos</option>' +
                casilleros.map(c => {
                    const desc = CASILLERO_DESC[c] ? ' — ' + CASILLERO_DESC[c].substring(0, 40) : '';
                    return `<option value="${c}">${c}${desc}</option>`;
                }).join('');

            const entidades = [...new Set(detalle.map(d => d.entidad || '').filter(Boolean))].sort();
            document.getElementById('detalleEntidadesLista').innerHTML =
                entidades.map(e => `<option value="${e.replace(/"/g, '&quot;')}"></option>`).join('');
        }

        function limpiarFiltrosDetalle() {
            filtroOrigen.value = '';
            filtroCasillero.value = '';
            filtroDocumento.value = '';
            filtroEntidad.value = '';
            filtroBuscar.value = '';
        }

        function filtrarDetalle() {
            const fOrigen = filtroOrigen.value;
            const fCas    = filtroCasillero.value;
            const fDoc    = filtroDocumento.value.trim().toLowerCase();
            const fEnt    = filtroEntidad.value.trim().toLowerCase();
            const q       = filtroBuscar.value.trim().toLowerCase();

            return ultimoDetalle.filter(d => {
                if (fOrigen && (d.origen || '') !== fOrigen) return false;
                if (fCas && String(d.casillero || '') !== fCas) return false;
                if (fDoc && !numeroDocumento(d).toLowerCase().includes(fDoc)) return false;
                if (fEnt) {
                    const ent = (d.entidad || '').toLowerCase();
                    const ide = (d.identificacion || '').toLowerCase();
                    if (!ent.includes(fEnt) && !ide.includes(fEnt)) return false;
                }
                if (q) {
                    const texto = [
                        numeroDocumento(d), d.entidad || '', d.identificacion || '',
                        d.concepto || '', d.casillero || '', d.origen || ''
                    ].join(' ').toLowerCase();
                    if (!texto.includes(q)) return false;
                }
                return true;
            });
        }

        // Sumas de lo filtrado: total general, número de documentos y desglose por casillero.
        function renderTotalesDetalle(detalle) {
            const cont = document.getElementById('detalleTotales');
            const contCas = document.getElementById('detalleTotalesCasilleros');

            const total = detalle.reduce((a, d) => a + (parseFloat(d.valor) || 0), 0);
            const documentos = new Set(detalle.map(d => `${d.origen}_${numeroDocumento(d)}`)).size;
            const hayFiltro = !!(filtroOrigen.value || filtroCasillero.value || filtroDocumento.value.trim() ||
                                 filtroEntidad.value.trim() || filtroBuscar.value.trim());

            cont.innerHTML = `
                <div class="dc-stat"><i class="bi bi-files bg-primary bg-opacity-10 text-primary"></i>
                    <div><div class="dc-stat-value">${documentos.toLocaleString('en-US')}</div><div class="dc-stat-label">Documentos</div></div></div>
                <div class="dc-stat"><i class="bi bi-list-ol bg-secondary bg-opacity-10 text-secondary"></i>
                    <div><div class="dc-stat-value">${detalle.length.toLocaleString('en-US')}</div><div class="dc-stat-label">Registros</div></div></div>
                <div class="dc-stat"><i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div><div class="dc-stat-value">${fmtMoneda(total)}</div><div class="dc-stat-label">Suma ${hayFiltro ? 'filtrada' : 'total'}</div></div></div>
                ${hayFiltro ? `<span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-25"><i class="bi bi-funnel-fill me-1"></i>Filtro activo — de ${ultimoDetalle.length} registros</span>` : ''}
            `;

            // Desglose por casillero (siempre sobre lo filtrado)
            const porCas = {};
            detalle.forEach(d => {
                const c = String(d.casillero || '');
                if (!c) return;
                if (!porCas[c]) porCas[c] = { suma: 0, n: 0 };
                porCas[c].suma += parseFloat(d.valor) || 0;
                porCas[c].n++;
            });
            const codigos = Object.keys(porCas).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
            if (!codigos.length) { contCas.innerHTML = ''; return; }

            contCas.innerHTML =
                '<div class="dc-stat-label mb-1">Suma por casillero</div>' +
                '<div class="d-flex flex-wrap gap-1">' +
                codigos.map(c => {
                    const activo = filtroCasillero.value === c ? ' activo' : '';
                    const desc = CASILLERO_DESC[c] ? CASILLERO_DESC[c] : 'Sin fila en la estructura del formulario';
                    return `<div class="dc-cas-chip d-inline-flex align-items-center bg-light border rounded px-2 py-1${activo}"
                                 data-filtro-casillero="${c}" title="${desc.replace(/"/g, '&quot;')} — clic para filtrar por este casillero">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 me-2">${c}</span>
                        <span class="fw-bold text-dark">${fmtMoneda(porCas[c].suma)}</span>
                        <span class="text-muted ms-2" style="font-size:0.65rem;">(${porCas[c].n})</span>
                    </div>`;
                }).join('') +
                '</div>';
        }

        function aplicarFiltrosDetalle() {
            const filtrado = filtrarDetalle();
            renderDetalle(filtrado);
            renderTotalesDetalle(filtrado);
        }
        window.aplicarFiltrosDetalle = aplicarFiltrosDetalle;

        [filtroOrigen, filtroCasillero].forEach(el => el.addEventListener('change', aplicarFiltrosDetalle));
        [filtroDocumento, filtroEntidad, filtroBuscar].forEach(el => el.addEventListener('input', aplicarFiltrosDetalle));
        document.getElementById('detalleLimpiar').addEventListener('click', () => {
            limpiarFiltrosDetalle();
            aplicarFiltrosDetalle();
        });

        // Los chips del desglose filtran por ese casillero (segundo clic lo quita).
        document.getElementById('detalleTotalesCasilleros').addEventListener('click', function (e) {
            const chip = e.target.closest('[data-filtro-casillero]');
            if (!chip) return;
            const codigo = chip.getAttribute('data-filtro-casillero');
            filtroCasillero.value = (filtroCasillero.value === codigo) ? '' : codigo;
            aplicarFiltrosDetalle();
        });

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
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok) {
                            Swal.fire({ title: 'Éxito', text: 'Casillero actualizado.', icon: 'success', timer: 1500, showConfirmButton: false });
                            generar(); // Recargar datos
                        } else {
                            Swal.fire('Error', data.mensaje, 'error');
                        }
                    });
                }
            });
        };

        // ==========================================================================
        // Declaración guardada: aviso de duplicado, guardar, asiento, egreso
        // ==========================================================================
        const avisoDeclarado = document.getElementById('avisoDeclarado');
        const btnGuardar = document.getElementById('btnGuardarDeclaracion');
        const btnAsiento = document.getElementById('btnGenerarAsiento');
        const btnEgreso = document.getElementById('btnGenerarEgreso');
        let declaracionActual = null;
        let yaGenerado = false; // el botón Guardar solo aparece después de presionar GENERAR

        function periodoParams() {
            const tipo = document.querySelector('input[name="tipo_periodo"]:checked').value;
            return { anio: document.getElementById('anio').value, periodo: selPeriodo.value, tipo_periodo: tipo };
        }

        function actualizarBotonesDeclaracion() {
            if (!btnGuardar) return; // sin permisos de crear/actualizar
            const cerrada = !!declaracionActual && declaracionActual.estado === 'pagado';
            btnGuardar.classList.toggle('d-none', !yaGenerado || cerrada);
            if (declaracionActual) {
                btnAsiento.classList.remove('d-none');
                const aPagar = parseFloat(declaracionActual.iva_a_pagar) || 0;
                const yaTieneEgreso = !!declaracionActual.id_egreso;
                btnEgreso.classList.toggle('d-none', !(aPagar > 0 && !yaTieneEgreso));
                btnGuardar.innerHTML = '<i class="bi bi-save"></i> ACTUALIZAR DECLARACIÓN';
            } else {
                btnAsiento.classList.add('d-none');
                btnEgreso.classList.add('d-none');
                btnGuardar.innerHTML = '<i class="bi bi-save"></i> GUARDAR DECLARACIÓN';
            }
            let avisoCerrada = document.getElementById('avisoDeclaracionCerrada');
            if (cerrada) {
                if (!avisoCerrada) {
                    avisoCerrada = document.createElement('div');
                    avisoCerrada.id = 'avisoDeclaracionCerrada';
                    avisoCerrada.className = 'alert alert-dark py-2 px-3 small mb-2';
                    avisoDeclarado.insertAdjacentElement('afterend', avisoCerrada);
                }
                avisoCerrada.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Esta declaración está <b>cerrada</b> (tiene asiento y egreso generados) y no se puede editar. Presione GENERAR si necesita corregirla: se anulará el asiento y el egreso actuales.';
                avisoCerrada.classList.remove('d-none');
            } else if (avisoCerrada) {
                avisoCerrada.classList.add('d-none');
            }
        }

        function fetchJsonDecl(url, opts) {
            return fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opts || {})).then(r => r.json());
        }

        // Pinta el Resumen 104 directo desde el snapshot guardado (valores_casilleros),
        // sin recalcular desde los documentos — lo realmente declarado en su momento.
        function cargarDeclaracionGuardada(data, declaracion) {
            tabsContainer.classList.remove('d-none');
            tabContent.classList.remove('d-none');
            renderVentas({ layout: data.layout, valores: declaracion.valores_casilleros || {}, total_480_481: data.total_480_481 });
            ultimoDetalle = []; // no hay detalle de documentos al cargar el snapshot guardado
            poblarFiltrosDetalle([]);
            limpiarFiltrosDetalle();
            renderTotalesDetalle([]);
            document.getElementById('accordionDetalle').innerHTML = '<div class="text-center text-muted py-3">Este es el detalle guardado al declarar. Presione GENERAR para ver el detalle de documentos actual.</div>';
            document.getElementById('btnExportarExcel').classList.remove('d-none');
            yaGenerado = true;
            actualizarBotonesDeclaracion();
        }

        function verificarDeclarado(preguntar) {
            const p = periodoParams();
            if (!p.anio || !p.periodo) return Promise.resolve();
            const params = new URLSearchParams(p).toString();
            return fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/verificar-declarado-ajax?${params}`).then(data => {
                if (!data.ok) return;
                declaracionActual = data.declaracion || null;
                if (declaracionActual) {
                    const fecha = declaracionActual.updated_at || declaracionActual.created_at || '';
                    const quien = declaracionActual.usuario_nombre ? ` por ${declaracionActual.usuario_nombre}` : '';
                    avisoDeclarado.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Este período ya fue declarado y guardado${quien}${fecha ? ' (' + fecha + ')' : ''}. Si vuelve a guardar, se actualizará la declaración existente.`;
                    avisoDeclarado.classList.remove('d-none');
                    if (preguntar && !yaGenerado) {
                        Swal.fire({
                            title: 'Período ya declarado',
                            text: 'Este período ya tiene una declaración guardada. ¿Qué desea hacer?',
                            icon: 'question',
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonText: 'Cargar la guardada',
                            denyButtonText: 'Recalcular desde documentos',
                            cancelButtonText: 'Cancelar'
                        }).then(res => {
                            if (res.isConfirmed) cargarDeclaracionGuardada(data, declaracionActual);
                            else if (res.isDenied) generar();
                        });
                    }
                } else {
                    avisoDeclarado.classList.add('d-none');
                }
                actualizarBotonesDeclaracion();
            }).catch(() => {});
        }

        function onCambioPeriodo() {
            yaGenerado = false; // hay que volver a presionar GENERAR para este período
            formSRI.innerHTML = '';
            tabsContainer.classList.add('d-none');
            tabContent.classList.add('d-none');
            document.getElementById('btnExportarExcel').classList.add('d-none');
            verificarDeclarado(true);
        }
        document.getElementById('anio').addEventListener('change', onCambioPeriodo);
        selPeriodo.addEventListener('change', onCambioPeriodo);
        document.querySelectorAll('input[name="tipo_periodo"]').forEach(el => el.addEventListener('change', onCambioPeriodo));
        verificarDeclarado(true);

        if (btnGuardar) {
            btnGuardar.addEventListener('click', () => {
                const p = periodoParams();
                const fd = new FormData();
                fd.append('anio', p.anio);
                fd.append('periodo', p.periodo);
                fd.append('tipo_periodo', p.tipo_periodo);

                // Casilleros editables (615/617 arrastre, 481/484/486 liquidación diferida,
                // 902 total a pagar): si la tabla ya se generó, se envía el valor actual de cada
                // input (autocalculado o ajustado a mano por el usuario).
                const mapaAjustes = { '615': 'ajuste_615', '617': 'ajuste_617', '481': 'ajuste_481', '484': 'ajuste_484', '486': 'ajuste_486', '902': 'ajuste_902' };
                Object.keys(mapaAjustes).forEach(codigo => {
                    const inp = formSRI.querySelector('input[data-casillero-editable="' + codigo + '"]');
                    if (inp) fd.append(mapaAjustes[codigo], inp.value);
                });

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
                // Formulario en blanco (o el borrador/asiento ya guardado, si existe) — sin
                // cuentas ni valores sugeridos: el usuario arma las líneas a mano y puede
                // guardar aunque no cuadre todavía (queda como borrador temporal).
                if (typeof window.ASIENTO_abrirModalDesdeOrigen === 'function') {
                    window.ASIENTO_abrirModalDesdeOrigen('declaracion_iva', declaracionActual.id);
                }
            });
        }

        // El asiento se guarda desde el modal compartido (asientos_contables_modal.js), que no
        // sabe nada de "declaraciones" — este listener es el que, tras guardarlo, lo vincula a
        // la declaración. Si quedó como borrador (sin cuadrar todavía) solo se vincula el id,
        // sin marcar la declaración como contabilizada ni ofrecer el egreso.
        document.addEventListener('asiento:guardado', (e) => {
            if (!declaracionActual || e.detail.modulo_origen !== 'declaracion_iva'
                || String(e.detail.id_referencia_origen) !== String(declaracionActual.id)) {
                return;
            }

            const fdV = new FormData();
            fdV.append('id_declaracion', declaracionActual.id);
            fetchJsonDecl(`<?= $base ?>/<?= $rutaModulo ?>/vincular-asiento-ajax`, { method: 'POST', body: fdV }).then(res => {
                verificarDeclarado().then(() => {
                    if (res.estado_asiento !== 'contabilizado') return; // sigue en borrador
                    const aPagar = parseFloat(declaracionActual && declaracionActual.iva_a_pagar) || 0;
                    const yaTieneEgreso = !!(declaracionActual && declaracionActual.id_egreso);
                    if (aPagar > 0 && !yaTieneEgreso) {
                        Swal.fire({
                            title: 'Asiento registrado',
                            text: '¿Desea generar también el egreso del pago ahora?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, generar egreso',
                            cancelButtonText: 'Ahora no'
                        }).then(r => { if (r.isConfirmed) btnEgreso.click(); });
                    }
                });
            });
        });

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
                const sugerido = parseFloat(declaracionActual.total_a_pagar ?? declaracionActual.iva_a_pagar) || 0;
                document.getElementById('egresoMonto').value = sugerido.toFixed(2);
                document.getElementById('egresoMontoInfo').innerHTML = `Sugerido según la declaración (casillero 902): <b>$${sugerido.toLocaleString('en-US', {minimumFractionDigits:2})}</b>. Puede editarlo arriba.`;
                document.getElementById('egresoFecha').value = CMG_fechaLocal();
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

                    // Proveedor sugerido (recordado del último egreso de este tipo de declaración).
                    if (data.proveedor_sugerido) {
                        const p = data.proveedor_sugerido;
                        document.getElementById('egresoProveedorId').value = p.id;
                        document.getElementById('egresoProveedorTexto').value = p.identificacion ? `${p.razon_social} (${p.identificacion})` : p.razon_social;
                    }

                    // Los selects se llenaron recién ahora (AJAX): aplicar los favoritos guardados
                    // (estrella) antes de calcular secuencial/campos bancarios dependientes.
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
            // Con una selección activa (hiddenEl con valor), Backspace/Delete limpia toda la
            // selección de una vez en vez de borrar la etiqueta letra por letra (§9 CLAUDE.md).
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
                const monto = parseFloat(document.getElementById('egresoMonto').value) || 0;
                if (monto <= 0) return Swal.fire('Atención', 'Ingrese el valor a pagar.', 'warning');

                const fd = new FormData();
                fd.append('id_declaracion', declaracionActual.id);
                fd.append('id_proveedor', idProveedor);
                fd.append('monto', monto.toFixed(2));
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
