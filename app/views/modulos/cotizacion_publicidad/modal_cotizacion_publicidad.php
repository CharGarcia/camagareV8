<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $vendedores */
/** @var array  $tarifasIva */
/** @var array  $categorias */
/** @var array  $permClientes */
/** @var array  $permProductos */
/** @var array  $puntos */

$vistaConfigCP = \App\Helpers\PreferenciasHelper::getPreferenciasVista('modulos/cotizacion-publicidad');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigCP, 'estiloVistaPestanasCP');
?>

<style>
    .modal-cotpub .modal-header { padding: 10px 16px; border-bottom: 1px solid #dee2e6; }
    .modal-cotpub .modal-body   { padding: 0; }
    .modal-cotpub .nav-tabs .nav-link { font-size: 0.8rem; white-space: nowrap; }
    .modal-cotpub label, .modal-cotpub .x-small { font-size: 0.72rem; }
    .modal-cotpub .table-detalle th { font-size: 0.7rem; padding: 4px 6px; background: #f8f9fa; }
    .modal-cotpub .table-detalle td { padding: 0 !important; vertical-align: middle; }
    .modal-cotpub .input-detalle {
        border: none !important;
        background-color: transparent;
        font-size: 0.78rem; padding: 0 4px; height: 20px; width: 100%;
        box-shadow: none !important;
    }
    .modal-cotpub .input-detalle:focus {
        background-color: #fff !important;
        box-shadow: inset 0 0 0 1px #0d6efd !important;
        outline: none; border-radius: 2px;
    }
    .modal-cotpub .input-detalle[readonly] { background-color: #f8f9fa !important; color: #6c757d; }
    .modal-cotpub select.input-detalle     { padding-right: 18px; }
    .modal-cotpub .bg-light.p-3,
    .modal-cotpub .p-3 { padding: 10px !important; }
    .modal-cotpub hr { margin: 6px 0; }
    .cp-dd-clientes, .cp-dd-proveedores {
        position: absolute; z-index: 1055;
        width: 100%; max-height: 240px; overflow-y: auto;
        background: #fff; border: 1px solid #dee2e6;
        border-radius: 0 0 6px 6px;
        box-shadow: 0 4px 16px rgba(0,0,0,.12);
        top: 100%; left: 0;
    }
</style>

<!-- Modal Cotización de Publicidad -->
<div class="modal fade modal-cotpub" id="modalCotizacionPublicidad" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">

            <!-- ── HEADER ─────────────────────────────────── -->
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-megaphone me-2"></i>
                    <span id="cp_tituloModal">Nueva Cotización</span>
                </h5>
                <span id="cp_estadoBadge" class="badge d-none ms-2" style="font-size:0.72rem;vertical-align:middle;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- ── BODY ───────────────────────────────────── -->
            <div class="modal-body p-0">
                <input type="hidden" id="cp_id">

                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <button id="cp-btn-categorias" type="button" class="btn btn-outline-secondary btn-sm px-2"
                        onclick="CP.abrirCategorias()" title="Gestionar categorías">
                        <i class="bi bi-tags"></i>
                    </button>
                    <?php if (!empty($permClientes['crear'])): ?>
                    <button id="cp-btn-cliente-nuevo" type="button" class="btn btn-outline-secondary btn-sm px-2"
                        onclick="abrirModalClienteCrear()" title="Registrar nuevo cliente">
                        <i class="bi bi-person-plus"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($permProductos['crear'])): ?>
                    <button id="cp-btn-producto-nuevo" type="button" class="btn btn-outline-secondary btn-sm px-2"
                        onclick="abrirModalProductoCrear()" title="Registrar nuevo producto">
                        <i class="bi bi-box-seam"></i>
                    </button>
                    <?php endif; ?>
                    <div class="vr mx-1"></div>
                    <?php if (!empty($perm['crear'])): ?>
                    <button id="cp-btn-factura" type="button" class="btn btn-outline-primary btn-sm px-2 d-none"
                        onclick="CP.abrirGenerarFactura()" title="Generar factura de venta">
                        <i class="bi bi-receipt"></i>
                    </button>
                    <button id="cp-btn-version" type="button" class="btn btn-outline-secondary btn-sm px-2 d-none"
                        onclick="CP.nuevaVersion()" title="Nueva versión">
                        <i class="bi bi-copy"></i>
                    </button>
                    <div class="vr mx-1" id="cp-vr1"></div>
                    <?php endif; ?>
                    <button id="cp-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2 d-none"
                        onclick="CP.exportarPdf()" title="Exportar PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </button>
                    <button id="cp-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2 d-none"
                        onclick="CP.enviarCorreo()" title="Enviar por correo">
                        <i class="bi bi-envelope"></i>
                    </button>
                    <?php if (!empty($perm['actualizar'])): ?>
                    <div class="vr mx-1" id="cp-vr2"></div>
                    <button id="cp-btn-aprobar" type="button" class="btn btn-outline-success btn-sm px-2 d-none"
                        onclick="CP.cambiarEstado('aprobada')" title="Aprobar">
                        <i class="bi bi-check-circle"></i>
                    </button>
                    <button id="cp-btn-rechazar" type="button" class="btn btn-outline-warning btn-sm px-2 d-none"
                        onclick="CP.cambiarEstado('rechazada')" title="Rechazar">
                        <i class="bi bi-x-circle"></i>
                    </button>
                    <button id="cp-btn-anular" type="button" class="btn btn-outline-warning btn-sm px-2 d-none"
                        onclick="CP.cambiarEstado('anulada')" title="Anular">
                        <i class="bi bi-slash-circle"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsCotizacion" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-2 small" id="cp-tab-cotizacion-btn"
                               data-bs-toggle="tab" href="#cp-tab-cotizacion" role="tab" style="white-space:nowrap;">
                                <i class="bi bi-megaphone me-1"></i>Cotización
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 small" id="cp-tab-costos-btn"
                               data-bs-toggle="tab" href="#cp-tab-costos" role="tab" style="white-space:nowrap;">
                                <i class="bi bi-cash-coin me-1"></i>Costos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 small" id="cp-tab-facturas-btn"
                               data-bs-toggle="tab" href="#cp-tab-facturas" role="tab" style="white-space:nowrap;">
                                <i class="bi bi-receipt me-1"></i>Facturas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 small" id="cp-tab-resumen-btn"
                               data-bs-toggle="tab" href="#cp-tab-resumen" role="tab" style="white-space:nowrap;">
                                <i class="bi bi-graph-up-arrow me-1"></i>Resumen
                            </a>
                        </li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas(
                            ['cp-tab-costos' => 'Costos', 'cp-tab-facturas' => 'Facturas', 'cp-tab-resumen' => 'Resumen'],
                            $vistaConfigCP,
                            'modulos/cotizacion-publicidad'
                        );
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top">

                    <!-- ── TAB COTIZACIÓN ─────────────────────── -->
                    <div class="tab-pane fade show active" id="cp-tab-cotizacion" role="tabpanel">

                        <!-- Cabecera -->
                        <div class="p-3 bg-white border-bottom">
                            <div class="row g-2">

                                <div class="col-12">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">Fecha</label>
                                            <input type="date" class="form-control form-control-sm border-primary border-opacity-10 py-0"
                                                style="height:31px;" id="cp_fecha">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">N° Cotización</label>
                                            <input type="text" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light"
                                                style="height:31px;" id="cp_numero" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="x-small fw-bold text-muted mb-1">Proyecto</label>
                                            <input type="text" class="form-control form-control-sm border-primary border-opacity-10"
                                                style="height:31px;" id="cp_proyecto" placeholder="Nombre del proyecto">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">Presupuesto</label>
                                            <input type="number" class="form-control form-control-sm border-primary border-opacity-10 text-end"
                                                style="height:31px;" id="cp_presupuesto" min="0" step="0.01" placeholder="0.00">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="x-small fw-bold text-muted mb-1">
                                                Ejecutivo
                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/cotizacion-publicidad', 'cp_vendedor', 'id_vendedor') ?>
                                            </label>
                                            <select class="form-select form-select-sm border-primary border-opacity-10"
                                                id="cp_vendedor" style="height:31px;">
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($vendedores as $v): ?>
                                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre'] ?? '') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila cliente -->
                                <div class="col-12 mt-2">
                                    <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                        <div class="row g-2 align-items-center">

                                            <div class="col-md-8 position-relative">
                                                <label class="x-small fw-bold text-muted mb-1">Cliente</label>
                                                <div class="input-group input-group-sm flex-grow-1 rounded-pill overflow-hidden border">
                                                    <span class="input-group-text bg-white border-0 text-primary">
                                                        <i class="bi bi-search"></i>
                                                    </span>
                                                    <input type="text" class="form-control border-0 px-1"
                                                        id="cp_clienteBuscar"
                                                        placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                    <input type="hidden" id="cp_idCliente">
                                                </div>
                                                <div id="cp_ddClientes" class="cp-dd-clientes list-group shadow d-none"></div>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="x-small fw-bold text-muted mb-1">Contacto</label>
                                                <input type="text" class="form-control form-control-sm border-primary border-opacity-10"
                                                    style="height:31px;" id="cp_contacto">
                                            </div>

                                            <div class="col-12 mt-1" id="cp_infoCliente" style="display:none;">
                                                <div class="d-flex flex-wrap align-items-center px-1"
                                                    style="font-size:0.72rem;text-transform:lowercase;color:#6c757d;gap:6px;">
                                                    <span class="border-end pe-2 me-1 fw-bold text-dark" id="cp_clienteRuc"></span>
                                                    <span id="cp_clienteNombre"></span>
                                                </div>
                                            </div>

                                            <div class="col-12 mt-2">
                                                <div class="row g-2">
                                                    <div class="col" style="flex:0 0 60%;max-width:60%;">
                                                        <label class="x-small fw-bold text-muted mb-1">Observaciones</label>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-white border-primary border-opacity-10 text-muted"
                                                                style="font-size:0.7rem;">
                                                                <i class="bi bi-sticky"></i>
                                                            </span>
                                                            <textarea class="form-control border-primary border-opacity-10"
                                                                id="cp_observaciones" rows="1"
                                                                placeholder="Notas internas..."
                                                                style="font-size:0.75rem;min-height:31px;"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col" style="flex:0 0 20%;max-width:20%;">
                                                        <label class="x-small fw-bold text-muted mb-1">
                                                            Tarifa IVA
                                                            <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/cotizacion-publicidad', 'cp_tarifaIva', 'id_tarifa_iva') ?>
                                                        </label>
                                                        <select class="form-select form-select-sm border-primary border-opacity-10"
                                                            id="cp_tarifaIva" style="height:31px;" onchange="CP.recalcularTotales()">
                                                            <?php foreach ($tarifasIva as $t): ?>
                                                            <option value="<?= $t['id'] ?>" data-pct="<?= $t['porcentaje_iva'] ?>"><?= htmlspecialchars($t['tarifa'] ?? '') ?> (<?= $t['porcentaje_iva'] ?>%)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col" style="flex:0 0 20%;max-width:20%;">
                                                        <label class="x-small fw-bold text-muted mb-1">Comisión (%)</label>
                                                        <input type="number" class="form-control form-control-sm border-primary border-opacity-10"
                                                            style="height:31px;" id="cp_comision" min="0" max="100" step="0.01" value="17" oninput="CP.recalcularTotales()">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Tabla de Detalle -->
                        <div class="p-3">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:300px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="cp_tablaDetalle">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:16%;">Categoría</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:28%;">Descripción</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:10%;">Precio</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Ciudades</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Días</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:8%;">Cant.</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-4" style="width:14%;">Subtotal</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cp_tbodyDetalle"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold"
                                        onclick="CP.agregarFila()">
                                        <i class="bi bi-plus-circle me-1"></i>Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Ítems: <span id="cp_countItems">0</span>
                                    </div>
                                </div>
                            </div>
                            <p class="x-small text-muted mt-1 mb-0"><i class="bi bi-info-circle me-1"></i>Ciudades y días son informativos, no afectan el cálculo del subtotal (Precio × Cantidad).</p>
                        </div>

                        <!-- Totales -->
                        <div class="p-3 border-top bg-light d-flex justify-content-end">
                            <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;min-width:280px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Subtotal</span>
                                    <span id="cp_subtotal">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">Comisión de agencia</span>
                                    <span id="cp_totalComision">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted">IVA</span>
                                    <span id="cp_totalIva">0.00</span>
                                </div>
                                <hr class="my-1 opacity-25">
                                <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                    <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                    <span class="fw-bold text-dark" style="font-size:1rem;" id="cp_importeTotal">0.00</span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /cp-tab-cotizacion -->

                    <!-- ── TAB COSTOS ─────────────────────────── -->
                    <div class="tab-pane fade" id="cp-tab-costos" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-cash-coin me-2 text-primary"></i>
                                <h6 class="mb-0 fw-bold text-secondary small">Costos reales por proveedor</h6>
                                <span class="ms-2 text-muted small">Compara el costo real contra lo cotizado — una línea puede tener costos de varios proveedores</span>
                            </div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:320px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="cp_tablaCostos">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:26%;">Proveedor</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:15%;">N° Factura</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:14%;">Costo</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:35%;">Observación</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cp_tbodyCostos"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-primary btn-sm px-3" id="cp_btnGuardarCostos" onclick="CP.guardarCostos()">
                                        <i class="bi bi-check2-circle me-1"></i>Guardar costos
                                    </button>
                                    <div class="small fw-bold" id="cp_resumenUtilidad">Utilidad: $0.00</div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /cp-tab-costos -->

                    <!-- ── TAB FACTURAS ───────────────────────── -->
                    <div class="tab-pane fade" id="cp-tab-facturas" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-receipt me-2 text-primary"></i>
                                    <h6 class="mb-0 fw-bold text-secondary small">Facturas generadas</h6>
                                    <span class="ms-2 text-muted small">Facturas de venta creadas a partir de esta cotización</span>
                                </div>
                                <?php if (!empty($perm['crear'])): ?>
                                <button id="cp-btn-generar-factura-tab" type="button" class="btn btn-primary btn-sm px-3 d-none"
                                    onclick="CP.abrirGenerarFactura()">
                                    <i class="bi bi-receipt me-1"></i>Generar Factura
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="cp_facturasMensaje" class="alert alert-warning py-2 px-3 small d-none"></div>
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:320px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:20%;">Fecha</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:35%;">N° Factura</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:20%;">Valor Total</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:25%;">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cp_tbodyFacturas">
                                            <tr><td colspan="4" class="text-center text-muted small py-3">Sin facturas asociadas</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div><!-- /cp-tab-facturas -->

                    <!-- ── TAB RESUMEN ────────────────────────── -->
                    <div class="tab-pane fade" id="cp-tab-resumen" role="tabpanel">
                        <div class="p-3" id="cp_resumenContenido">
                            <div class="text-center text-muted small py-5">Guarde la cotización para ver el resumen.</div>
                        </div>
                    </div><!-- /cp-tab-resumen -->
                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <!-- ── FOOTER ─────────────────────────────────── -->
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                        id="cp_btnEliminar" onclick="CP.eliminar()">
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                    </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 d-none"
                        id="cp_btnGuardar" onclick="CP.guardar()">
                        <i class="bi bi-check2-circle me-1"></i>Guardar
                    </button>
                </div>
            </div>

        </div><!-- /modal-content -->
    </div><!-- /modal-dialog -->
</div><!-- /modal -->

<!-- Modal Categorías (gestión de categorías de servicio publicitario) -->
<div class="modal fade" id="cp_modalCategorias" tabindex="-1" aria-hidden="true" style="z-index:1070;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fs-6 fw-bold"><i class="bi bi-tags me-2"></i>Categorías</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <?php if (!empty($perm['crear'])): ?>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control" id="cp_nuevaCategoria" placeholder="Nombre de la categoría" maxlength="150">
                    <button type="button" class="btn btn-primary" onclick="CP.agregarCategoria()">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <?php endif; ?>
                <div id="cp_listaCategorias" class="list-group list-group-flush" style="max-height:260px;overflow-y:auto;">
                    <div class="text-center text-muted small py-3">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Generar Factura (facturación manual desde la cotización) -->
<div class="modal fade" id="cp_modalGenerarFactura" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index:1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header py-2 px-3">
                <h6 class="modal-title fs-6 fw-bold"><i class="bi bi-receipt me-2"></i>Generar Factura de Venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="cpf_idCotizacion">

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-3">
                        <label class="x-small fw-bold text-muted mb-1">Fecha</label>
                        <input type="date" class="form-control form-control-sm" id="cpf_fecha" style="height:31px;">
                    </div>
                    <div class="col-md-4">
                        <label class="x-small fw-bold text-muted mb-1">Serie</label>
                        <select class="form-select form-select-sm" id="cpf_punto" style="height:31px;">
                            <?php foreach (($puntos ?? []) as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                data-est="<?= $p['id_establecimiento'] ?? '' ?>"
                                data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" id="cpf_establecimiento">
                    </div>
                    <div class="col-md-3">
                        <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                        <input type="text" class="form-control form-control-sm text-center bg-light"
                            id="cpf_secuencial" style="height:31px;" readonly placeholder="000000001">
                    </div>
                </div>

                <div class="mb-2 position-relative">
                    <label class="x-small fw-bold text-muted mb-1">Cliente</label>
                    <div class="input-group input-group-sm flex-grow-1 rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-0 px-1" id="cpf_clienteBuscar"
                            placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                        <input type="hidden" id="cpf_idCliente">
                    </div>
                    <div id="cpf_ddClientes" class="cp-dd-clientes list-group shadow d-none"></div>
                </div>

                <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-2">
                    <div class="table-responsive" style="max-height:280px;">
                        <table class="table table-sm table-detalle mb-0 text-nowrap">
                            <thead>
                                <tr class="table-light border-bottom">
                                    <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Producto</th>
                                    <th class="py-2 small fw-bold text-muted text-center" style="width:12%;">Cantidad</th>
                                    <th class="py-2 small fw-bold text-muted text-end" style="width:16%;">Precio</th>
                                    <th class="py-2 small fw-bold text-muted text-end pe-4" style="width:17%;">Subtotal</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cpf_tbodyDetalle"></tbody>
                        </table>
                    </div>
                    <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="CP.agregarFilaFactura()">
                            <i class="bi bi-plus-circle me-1"></i>Agregar línea
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;min-width:260px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">Subtotal</span>
                            <span id="cpf_subtotal">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">IVA</span>
                            <span id="cpf_iva">0.00</span>
                        </div>
                        <hr class="my-1 opacity-25">
                        <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                            <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                            <span class="fw-bold text-dark" style="font-size:1rem;" id="cpf_total">0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-primary btn-sm px-4" id="cpf_btnGenerar" onclick="CP.generarFactura()">
                    <i class="bi bi-check2-circle me-1"></i>Generar Factura
                </button>
            </div>
        </div>
    </div>
</div>
