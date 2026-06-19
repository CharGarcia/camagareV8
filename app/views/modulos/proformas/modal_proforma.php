<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $puntos */
/** @var array  $vendedores */

$vistaConfigPF = \App\Helpers\PreferenciasHelper::getPreferenciasVista('modulos/proformas');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigPF, 'estiloVistaPestanasPF');
?>

<style>
    .modal-proforma .modal-header { padding: 10px 16px; border-bottom: 1px solid #dee2e6; }
    .modal-proforma .modal-body   { padding: 0; }
    .modal-proforma .nav-tabs .nav-link { font-size: 0.8rem; white-space: nowrap; }
    .modal-proforma label, .modal-proforma .x-small { font-size: 0.72rem; }
    .modal-proforma .table-detalle th { font-size: 0.7rem; padding: 4px 6px; background: #f8f9fa; }
    .modal-proforma .table-detalle td { padding: 0 !important; vertical-align: middle; }
    /* inputs compactos en tabla de detalles — igual que factura de venta */
    .modal-proforma .input-detalle {
        border: none !important;
        background-color: transparent;
        font-size: 0.78rem; padding: 0 4px; height: 20px; width: 100%;
        box-shadow: none !important;
    }
    .modal-proforma .input-detalle:focus {
        background-color: #fff !important;
        box-shadow: inset 0 0 0 1px #0d6efd !important;
        outline: none; border-radius: 2px;
    }
    .modal-proforma .input-detalle[readonly]  { background-color: #f8f9fa !important; color: #6c757d; }
    .modal-proforma select.input-detalle      { padding-right: 18px; }
    .modal-proforma .bg-light.p-3,
    .modal-proforma .p-3 { padding: 10px !important; }
    .modal-proforma hr { margin: 6px 0; }
    /* Dropdowns predictivos */
    .pf-dd-clientes, .pf-dd-productos {
        position: absolute; z-index: 1055;
        width: 100%; max-height: 240px; overflow-y: auto;
        background: #fff; border: 1px solid #dee2e6;
        border-radius: 0 0 6px 6px;
        box-shadow: 0 4px 16px rgba(0,0,0,.12);
        top: 100%; left: 0;
    }
</style>

<!-- Modal Proforma -->
<div class="modal fade modal-proforma" id="modalProforma" data-bs-backdrop="static" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg">

            <!-- ── HEADER ─────────────────────────────────── -->
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <span id="pf_tituloModal">Nueva Proforma</span>
                </h5>
                <span id="pf_estadoBadge" class="badge d-none ms-2" style="font-size:0.72rem;vertical-align:middle;"></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- ── BODY ───────────────────────────────────── -->
            <div class="modal-body p-0">
                <input type="hidden" id="pf_id">

                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <?php if (!empty($perm['crear'])): ?>
                    <button id="pf-btn-factura" type="button" class="btn btn-outline-primary btn-sm px-2 d-none"
                        onclick="PF.convertirAFactura()" title="Crear factura">
                        <i class="bi bi-receipt"></i>
                    </button>
                    <button id="pf-btn-pedido" type="button" class="btn btn-outline-primary btn-sm px-2 d-none"
                        onclick="PF.crearPedido()" title="Crear pedido">
                        <i class="bi bi-cart-plus"></i>
                    </button>
                    <button id="pf-btn-recibo" type="button" class="btn btn-outline-primary btn-sm px-2 d-none"
                        onclick="PF.crearReciboVenta()" title="Crear recibo de venta">
                        <i class="bi bi-receipt-cutoff"></i>
                    </button>
                    <button id="pf-btn-duplicar" type="button" class="btn btn-outline-secondary btn-sm px-2 d-none"
                        onclick="PF.duplicar()" title="Duplicar proforma">
                        <i class="bi bi-files"></i>
                    </button>
                    <div class="vr mx-1" id="pf-vr1"></div>
                    <?php endif; ?>
                    <button id="pf-btn-pdf" type="button" class="btn btn-outline-danger btn-sm px-2 d-none"
                        onclick="PF.exportarPdf()" title="Exportar PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </button>
                    <button id="pf-btn-correo" type="button" class="btn btn-outline-info btn-sm px-2 d-none"
                        onclick="PF.enviarCorreo()" title="Enviar por correo">
                        <i class="bi bi-envelope"></i>
                    </button>
                    <button id="pf-btn-whatsapp" type="button" class="btn btn-outline-success btn-sm px-2 d-none"
                        onclick="PF.enviarWhatsapp()" title="Enviar por WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </button>
                    <?php if (!empty($perm['actualizar'])): ?>
                    <div class="vr mx-1" id="pf-vr2"></div>
                    <button id="pf-btn-aprobar" type="button" class="btn btn-outline-success btn-sm px-2 d-none"
                        onclick="PF.cambiarEstado('aprobada')" title="Aprobar">
                        <i class="bi bi-check-circle"></i>
                    </button>
                    <button id="pf-btn-rechazar" type="button" class="btn btn-outline-warning btn-sm px-2 d-none"
                        onclick="PF.cambiarEstado('rechazada')" title="Rechazar">
                        <i class="bi bi-x-circle"></i>
                    </button>
                    <button id="pf-btn-anular" type="button" class="btn btn-outline-warning btn-sm px-2 d-none"
                        onclick="PF.cambiarEstado('anulada')" title="Anular">
                        <i class="bi bi-slash-circle"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Pestañas -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsProforma" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active py-2 small" id="pf-tab-proforma-btn"
                               data-bs-toggle="tab" href="#pf-tab-proforma" role="tab" style="white-space:nowrap;">
                                <i class="bi bi-file-earmark-text me-1"></i>Proforma
                            </a>
                        </li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas([], $vistaConfigPF, 'modulos/proformas');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top">

                    <!-- ── TAB PROFORMA ───────────────────────── -->
                    <div class="tab-pane fade show active" id="pf-tab-proforma" role="tabpanel">

                        <!-- Cabecera -->
                        <div class="p-3 bg-white border-bottom">
                            <div class="row g-2">

                                <!-- Fila 1: campos documento -->
                                <div class="col-12">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">Fecha</label>
                                            <input type="date" class="form-control form-control-sm border-primary border-opacity-10 py-0"
                                                style="height:31px;" id="pf_fecha">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">
                                                Serie
                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/proformas', 'pf_punto', 'id_punto_emision') ?>
                                            </label>
                                            <select class="form-select form-select-sm border-primary border-opacity-25"
                                                id="pf_punto" style="height:31px;">
                                                <?php foreach ($puntos as $p): ?>
                                                <option value="<?= $p['id'] ?>"
                                                    data-est="<?= $p['id_establecimiento'] ?? '' ?>"
                                                    data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                                    data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                                    <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" id="pf_establecimiento">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                            <input type="text" class="form-control form-control-sm border-primary border-opacity-25 text-center text-dark py-0 bg-light"
                                                style="height:31px;" id="pf_secuencial" placeholder="000000001" maxlength="9" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="x-small fw-bold text-muted mb-1">
                                                Vendedor
                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/proformas', 'pf_vendedor', 'id_vendedor') ?>
                                            </label>
                                            <select class="form-select form-select-sm border-primary border-opacity-10"
                                                id="pf_vendedor" style="height:31px;">
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($vendedores as $v): ?>
                                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre'] ?? '') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 2: Cliente con Diseño Compacto (igual a factura de venta) -->
                                <div class="col-12 mt-2">
                                    <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                        <div class="row g-2 align-items-center">

                                            <div class="col-12 position-relative">
                                                <div class="input-group input-group-sm flex-grow-1 rounded-pill overflow-hidden border">
                                                    <span class="input-group-text bg-white border-0 text-primary">
                                                        <i class="bi bi-search"></i>
                                                    </span>
                                                    <input type="text" class="form-control border-0 px-1"
                                                        id="pf_clienteBuscar"
                                                        placeholder="Buscar cliente por RUC o Razón Social..." autocomplete="off">
                                                    <input type="hidden" id="pf_idCliente">
                                                </div>
                                                <div id="pf_ddClientes" class="pf-dd-clientes list-group shadow d-none"></div>
                                            </div>

                                            <div class="col-12 px-3 mt-1 d-none" id="pf_infoCliente">
                                                <div class="d-flex flex-wrap align-items-center"
                                                    style="font-size:0.72rem;text-transform:lowercase;color:#6c757d;gap:6px;">
                                                    <span class="border-end pe-2 me-1 fw-bold text-dark" id="pf_clienteRuc"></span>
                                                    <span id="pf_clienteNombre"></span>
                                                    <div class="d-flex align-items-center gap-1 border-start ps-2">
                                                        <i class="bi bi-envelope"></i>
                                                        <span id="pf_clienteEmail"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 mt-2">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-white border-primary border-opacity-10 text-muted"
                                                        style="font-size:0.7rem;">
                                                        <i class="bi bi-sticky me-1"></i>Observaciones
                                                    </span>
                                                    <textarea class="form-control border-primary border-opacity-10"
                                                        id="pf_observaciones" rows="1"
                                                        placeholder="Notas internas..."
                                                        style="font-size:0.75rem;min-height:31px;"></textarea>
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
                                <div class="table-responsive" style="max-height:350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="pf_tablaDetalle">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:20%;">Descripción</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:10%;">Adicional</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:6%;">Cant.</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:12%;">Precios</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:8%;">P. Sin Imp.</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:8%;">P. Con Imp.</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:7%;">Desc.</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:7%;">Iva</th>
                                                <th class="py-2 small fw-bold text-muted text-end pe-4" style="width:11%;">Subtotal</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="pf_tbodyDetalle"></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold"
                                        onclick="PF.agregarFila()">
                                        <i class="bi bi-plus-circle me-1"></i>Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Ítems: <span id="pf_countItems">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pie: Info Adicional + Totales -->
                        <div class="p-3 border-top bg-light">
                            <div class="row g-3">

                                <!-- Izquierda: sub-pestaña Info Adicional -->
                                <div class="col-md-8">
                                    <ul class="nav nav-tabs nav-tabs-sm mb-2" role="tablist">
                                        <li class="nav-item">
                                            <button class="nav-link active py-1 small"
                                                data-bs-toggle="tab" data-bs-target="#pf-subtab-info" type="button">
                                                Info. Adicional
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link py-1 small"
                                                data-bs-toggle="tab" data-bs-target="#pf-subtab-vigencia" type="button">
                                                Vigencia
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content bg-white border p-2 rounded-bottom" style="min-height:120px;">
                                        <div class="tab-pane fade show active" id="pf-subtab-info" role="tabpanel">
                                            <div class="border rounded-2 overflow-hidden bg-white mt-1">
                                                <div class="table-responsive" style="max-height:200px;">
                                                    <table class="table table-sm mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th class="ps-2 py-0 small fw-bold text-muted" style="width:40%;">Concepto</th>
                                                                <th class="py-0 small fw-bold text-muted" style="width:50%;">Detalle</th>
                                                                <th class="py-0" style="width:10%;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="pf_tbodyAdicional"></tbody>
                                                    </table>
                                                </div>
                                                <div class="p-1 border-top bg-light">
                                                    <button type="button"
                                                        class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2"
                                                        onclick="PF.agregarAdicional()">
                                                        <i class="bi bi-plus-circle me-1"></i>Agregar línea
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Pestaña Vigencia -->
                                        <div class="tab-pane fade" id="pf-subtab-vigencia" role="tabpanel">
                                            <div class="p-2">
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label class="x-small text-muted mb-1">Días de vigencia</label>
                                                        <input type="number" class="form-control form-control-sm" id="pf_diasVigencia" min="0" value="15">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="x-small text-muted mb-1">Unidad</label>
                                                        <select class="form-select form-select-sm" id="pf_vigenciaUnidad">
                                                            <option value="dias">Días</option>
                                                            <option value="meses">Meses</option>
                                                            <option value="anios">Años</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Derecha: Totales Verticales -->
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                        <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                            <span class="text-muted">Subtotal</span>
                                            <span id="pf_subtotalGeneral">0.00</span>
                                        </div>
                                        <div id="pf_subtotalesIva" class="mb-1"></div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted">(-) Descuento</span>
                                            <span class="fw-bold text-dark" id="pf_totalDescuento">0.00</span>
                                        </div>
                                        <div id="pf_ivasGrupo" class="mb-1"></div>
                                        <hr class="my-1 opacity-25">
                                        <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                            <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                            <span class="fw-bold text-dark" style="font-size:1rem;" id="pf_importeTotal">0.00</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div><!-- /pf-tab-proforma -->
                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <!-- ── FOOTER ─────────────────────────────────── -->
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                        id="pf_btnEliminar" onclick="PF.eliminar()">
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                    </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 d-none"
                        id="pf_btnGuardar" onclick="PF.guardar()">
                        <i class="bi bi-check2-circle me-1"></i>Guardar
                    </button>
                </div>
            </div>

        </div><!-- /modal-content -->
    </div><!-- /modal-dialog -->
</div><!-- /modal -->

<!-- Modal descuento rápido (proformas) -->
<div class="modal fade" id="pf_modalDescuento" tabindex="-1" aria-hidden="true" style="z-index:2070;">
    <div class="modal-dialog modal-sm modal-dialog-centered" style="max-width:320px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-1 px-3 border-bottom-0">
                <h6 class="modal-title small text-primary" style="font-size:0.85rem;">
                    <i class="bi bi-percent me-1"></i>Aplicar Descuento
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size:0.5rem;padding:0.8rem;"></button>
            </div>
            <div class="modal-body py-2 px-3">
                <div class="mb-2 text-center">
                    <label class="small text-muted mb-1 d-block text-start" style="font-size:0.75rem;">Modo</label>
                    <div class="btn-group w-100 btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="pfTipoDesc" id="pfDescPorc" value="P" checked onchange="PF._validarCalcDesc()">
                        <label class="btn btn-outline-primary py-1" for="pfDescPorc" style="font-size:0.75rem;">Porcentaje (%)</label>
                        <input type="radio" class="btn-check" name="pfTipoDesc" id="pfDescVal" value="V" onchange="PF._validarCalcDesc()">
                        <label class="btn btn-outline-primary py-1" for="pfDescVal" style="font-size:0.75rem;">Valor ($)</label>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="small text-muted mb-1 d-block" style="font-size:0.75rem;">Ingreso</label>
                        <input type="number" id="pf_inputDescVal" class="form-control form-control-sm text-center shadow-none border-secondary-subtle"
                            value="0" step="any" oninput="PF._validarCalcDesc()" style="font-size:0.9rem;">
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-1 d-block" style="font-size:0.75rem;">Calculado ($)</label>
                        <input type="number" id="pf_inputDescCalc" class="form-control form-control-sm text-center shadow-none border-0 bg-light text-primary"
                            value="0" readonly style="font-size:0.9rem;">
                    </div>
                </div>
                <div class="form-check form-switch mb-1" style="min-height:auto;">
                    <input class="form-check-input" type="checkbox" id="pf_checkDescTodo" style="height:1rem;width:1.8rem;margin-top:0.2rem;">
                    <label class="form-check-label text-muted ms-1" for="pf_checkDescTodo" style="font-size:0.7rem;vertical-align:middle;">Aplicar a todos los ítems</label>
                </div>
            </div>
            <div class="modal-footer bg-light p-2 border-top-0 justify-content-center">
                <button type="button" class="btn btn-primary btn-sm w-100 py-1" onclick="PF._confirmarDescuento()" style="font-size:0.8rem;">Confirmar</button>
            </div>
        </div>
    </div>
</div>
