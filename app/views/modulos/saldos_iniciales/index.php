<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */

$base    = BASE_URL;
$urlBase = $base . '/' . $rutaModulo;
?>

<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>

<?php /* Página con pestañas sobre la tabla: desactivar app-shell para que las tablas tengan su propio scroll vertical */ ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .si-header { flex-shrink: 0; }

    .si-scroll {
        max-height: calc(100dvh - 290px);
        overflow-y: auto;
    }
    .si-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
    }

    /* scroll bancos dentro de tab */
    .si-bancos-scroll {
        max-height: calc(100dvh - 320px);
        overflow-y: auto;
    }

    .si-row { cursor: default; }
    .si-row:hover { background-color: rgba(0,0,0,.03); }

    .badge-tipo {
        font-size: .6rem;
        padding: 2px 6px;
        background: rgba(13,110,253,.1);
        color: #0d6efd;
        border: 1px solid rgba(13,110,253,.2);
        border-radius: 4px;
    }
</style>

<!-- ── Cabecera ── -->
<div class="si-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-database-add me-2 text-warning"></i>Saldos Iniciales
        </h5>
        <small class="text-muted">Carga de saldos previos al inicio de operaciones en este sistema</small>
    </div>
</div>

<!-- ── Tabs Bootstrap ── -->
<ul class="nav nav-tabs mb-0 border-bottom-0" id="siTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-cxc-btn"
                data-bs-toggle="tab" data-bs-target="#tab-cxc" type="button" role="tab"
                onclick="SI_onTabCxc()">
            <i class="bi bi-wallet2 me-1 text-success"></i>Cuentas por Cobrar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-cxp-btn"
                data-bs-toggle="tab" data-bs-target="#tab-cxp" type="button" role="tab"
                onclick="SI_onTabCxp()">
            <i class="bi bi-receipt me-1 text-primary"></i>Cuentas por Pagar
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-bancos-btn"
                data-bs-toggle="tab" data-bs-target="#tab-bancos" type="button" role="tab"
                onclick="SI_onTabBancos()">
            <i class="bi bi-bank me-1 text-warning"></i>Bancos y Tarjetas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-efectivo-btn"
                data-bs-toggle="tab" data-bs-target="#tab-efectivo" type="button" role="tab"
                onclick="SI_onTabEfectivo()">
            <i class="bi bi-cash-stack me-1 text-success"></i>Efectivo
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-anticipos-btn"
                data-bs-toggle="tab" data-bs-target="#tab-anticipos" type="button" role="tab"
                onclick="SI_onTabAnticipos()">
            <i class="bi bi-cash-coin me-1 text-purple" style="color:#6f42c1;"></i>Anticipos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-inventario-btn"
                data-bs-toggle="tab" data-bs-target="#tab-inventario" type="button" role="tab"
                onclick="SI_onTabInventario()">
            <i class="bi bi-box-seam me-1 text-info"></i>Inventario
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-consig-btn"
                data-bs-toggle="tab" data-bs-target="#tab-consig" type="button" role="tab"
                onclick="SI_onTabConsig()">
            <i class="bi bi-arrow-left-right me-1 text-secondary"></i>Consignaciones Ventas
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ══════════ TAB CXC ══════════ -->
    <div class="tab-pane fade show active" id="tab-cxc" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

                <!-- Izquierda: acciones -->
                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-success btn-sm px-3 shadow-sm" onclick="SI_abrirModalCxc()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-success btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-cxc" accept=".xlsx,.xls" class="d-none" onchange="SI_importarCxc(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateCxc()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>
                </div>

                <!-- Derecha: filtros + buscador + contador -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="si-cxc-estado" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="SI_cargarCxc()">
                        <option value="TODOS">Todos los estados</option>
                        <option value="PENDIENTE" selected>Pendiente</option>
                        <option value="PARCIAL">Parcial</option>
                        <option value="PAGADO">Pagado</option>
                    </select>
                    <span class="text-muted small fw-medium" id="si-cxc-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-cxc-buscar" placeholder="Filtrar…" oninput="SI_filtrarCxc(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:fixed;min-width:910px;">
                        <colgroup>
                            <col style="width:150px;">
                            <col>
                            <col style="width:105px;">
                            <col style="width:115px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:90px;">
                            <col style="width:100px;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Documento</th>
                                <th>Cliente / RUC</th>
                                <th class="text-center">F.Emisión</th>
                                <th class="text-center">F.Vencimiento</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th class="text-end">Cobrado</th>
                                <th class="text-end pe-2">Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-cxc-tbody">
                            <tr><td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-wallet2 fs-3 d-block mb-2 opacity-25 text-success"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB CXP ══════════ -->
    <div class="tab-pane fade" id="tab-cxp" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-primary btn-sm px-3 shadow-sm" onclick="SI_abrirModalCxp()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-primary btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-cxp" accept=".xlsx,.xls" class="d-none" onchange="SI_importarCxp(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateCxp()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="si-cxp-tipo" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="SI_cargarCxp()">
                        <option value="">Todos los tipos</option>
                        <option value="FACTURA_COMPRA">Facturas</option>
                        <option value="LIQUIDACION">Liquidaciones</option>
                        <option value="NOTA_CREDITO">Notas de Crédito</option>
                        <option value="NOTA_DEBITO">Notas de Débito</option>
                    </select>
                    <select id="si-cxp-estado" class="form-select form-select-sm shadow-none border" style="width:155px;" onchange="SI_cargarCxp()">
                        <option value="TODOS">Todos los estados</option>
                        <option value="PENDIENTE" selected>Pendiente</option>
                        <option value="PARCIAL">Parcial</option>
                        <option value="PAGADO">Pagado</option>
                    </select>
                    <span class="text-muted small fw-medium" id="si-cxp-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-cxp-buscar" placeholder="Filtrar…" oninput="SI_filtrarCxp(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:fixed;min-width:960px;">
                        <colgroup>
                            <col style="width:100px;">
                            <col style="width:140px;">
                            <col>
                            <col style="width:105px;">
                            <col style="width:115px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:105px;">
                            <col style="width:90px;">
                            <col style="width:100px;">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tipo</th>
                                <th>Documento</th>
                                <th>Proveedor / RUC</th>
                                <th class="text-center">F.Emisión</th>
                                <th class="text-center">F.Vencimiento</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end pe-2">Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-cxp-tbody">
                            <tr><td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt fs-3 d-block mb-2 opacity-25 text-primary"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB BANCOS ══════════ -->
    <div class="tab-pane fade" id="tab-bancos" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold"><i class="bi bi-bank me-2 text-warning"></i>Saldos de Cuentas Bancarias y Tarjetas</span>
                    <small class="text-muted d-block" style="font-size:.72rem;">
                        Ingrese el saldo y fecha de corte para cada cuenta activa de tipo <strong>Banco</strong> o <strong>Tarjeta</strong>.
                    </small>
                </div>
                <?php if ($perm['crear'] ?? false): ?>
                <button class="btn btn-warning btn-sm px-3 text-dark shadow-sm" onclick="SI_guardarBancos()">
                    <i class="bi bi-floppy me-1"></i>Guardar Saldos
                </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="si-bancos-scroll">
                    <div id="bancos-container" class="p-3">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2 text-warning"></div>Cargando cuentas…
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB EFECTIVO ══════════ -->
    <div class="tab-pane fade" id="tab-efectivo" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold"><i class="bi bi-cash-stack me-2 text-success"></i>Saldos de Efectivo / Caja</span>
                    <small class="text-muted d-block" style="font-size:.72rem;">
                        Ingrese el saldo y fecha de corte para cada forma de cobro/pago de tipo <strong>Efectivo</strong>.
                    </small>
                </div>
                <?php if ($perm['crear'] ?? false): ?>
                <button class="btn btn-success btn-sm px-3 shadow-sm" onclick="SI_guardarEfectivo()">
                    <i class="bi bi-floppy me-1"></i>Guardar Saldos
                </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <div class="si-bancos-scroll">
                    <div id="efectivo-container" class="p-3">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2 text-success"></div>Cargando…
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB ANTICIPOS ══════════ -->
    <div class="tab-pane fade" id="tab-anticipos" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-sm px-3 shadow-sm text-white" style="background:#6f42c1;" onclick="SI_abrirModalAnticipo()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <?php endif; ?>
                    <small class="text-muted" style="font-size:.72rem;">Cada anticipo va atado a un <strong>cliente</strong> (Ingreso) o <strong>proveedor</strong> (Egreso).</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-medium" id="si-anti-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-anti-buscar" placeholder="Filtrar…" oninput="SI_filtrarAnticipos(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="min-width:900px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tipo</th>
                                <th>Cliente / Proveedor</th>
                                <th>Forma de Anticipo</th>
                                <th class="text-center">Fecha</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th>Observaciones</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-anti-tbody">
                            <tr><td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-cash-coin fs-3 d-block mb-2 opacity-25" style="color:#6f42c1;"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB INVENTARIO ══════════ -->
    <div class="tab-pane fade" id="tab-inventario" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-info btn-sm px-3 shadow-sm text-white" onclick="SI_abrirModalInventario()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-info btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-inv" accept=".xlsx,.xls" class="d-none" onchange="SI_importarInventario(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateInventario()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>
                    <small class="text-muted" style="font-size:.72rem;">Cada fila genera una <strong>entrada de apertura</strong> en el kardex (stock real).</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-medium" id="si-inv-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-inv-buscar" placeholder="Filtrar…" oninput="SI_filtrarInventario(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="min-width:980px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th>Bodega</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Costo Unit.</th>
                                <th class="text-end">Costo Total</th>
                                <th>Lote</th>
                                <th class="text-center">Caducidad</th>
                                <th>NUP</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-inv-tbody">
                            <tr><td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-3 d-block mb-2 opacity-25 text-info"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ TAB CONSIGNACIONES ══════════ -->
    <div class="tab-pane fade" id="tab-consig" role="tabpanel">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-top-0 rounded-bottom-3">

            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <?php if ($perm['crear'] ?? false): ?>
                    <button class="btn btn-secondary btn-sm px-3 shadow-sm" onclick="SI_abrirModalConsig()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo
                    </button>
                    <label class="btn btn-outline-secondary btn-sm mb-0" title="Importar Excel">
                        <i class="bi bi-file-earmark-excel me-1"></i>Importar
                        <input type="file" id="input-import-consig" accept=".xlsx,.xls" class="d-none" onchange="SI_importarConsig(this)">
                    </label>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary btn-sm" onclick="SI_descargarTemplateConsig()" title="Descargar plantilla Excel">
                        <i class="bi bi-download me-1"></i>Plantilla
                    </button>
                    <small class="text-muted" style="font-size:.72rem;">Registro de mercadería consignada a clientes. <strong>No afecta inventario.</strong></small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-medium" id="si-consig-count">0 registros</span>
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 shadow-none ps-0"
                               id="si-consig-buscar" placeholder="Filtrar…" oninput="SI_filtrarConsig(this.value)">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="si-scroll">
                    <table class="table table-hover table-sm mb-0 align-middle" style="min-width:1100px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Documento</th>
                                <th class="text-center">Fecha</th>
                                <th>Cliente</th>
                                <th>Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Total</th>
                                <th>Vendedor</th>
                                <th>Bodega</th>
                                <th class="text-center pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-consig-tbody">
                            <tr><td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-right fs-3 d-block mb-2 opacity-25 text-secondary"></i>Cargando…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div><!-- /tab-content -->


<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial CXC
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSICxc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-wallet2 me-2"></i>Saldo Inicial — Cuenta por Cobrar
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-cxc-id">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Nº Documento <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxc-nro" class="form-control form-control-sm shadow-none"
                               placeholder="000-000-000000000" maxlength="17">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                        <input type="date" id="si-cxc-femision" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Vencimiento</label>
                        <input type="date" id="si-cxc-fvenc" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-cxc-id-cliente">
                        <div class="position-relative">
                            <input type="text" id="si-cxc-cliente-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por identificación o nombre…" autocomplete="off"
                                   oninput="SI_buscarTercero('cxc', this.value)"
                                   onfocus="SI_buscarTercero('cxc', this.value)">
                            <div id="si-cxc-cliente-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:220px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-cxc-cliente-sel" class="mt-1 d-none">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-wrap text-start"
                                  id="si-cxc-cliente-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;"
                                    onclick="SI_limpiarTercero('cxc')">cambiar</button>
                        </div>
                        <div class="form-text" style="font-size:.72rem;">
                            ¿No existe? <a href="<?= $base ?>/modulos/clientes" target="_blank">Crear cliente</a> primero.
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1 text-success">Saldo Pendiente ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold text-success border-success border-opacity-50">$</span>
                            <input type="number" id="si-cxc-saldo"
                                   class="form-control shadow-none fw-bold text-success border-success border-opacity-50"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-cxc-obs" class="form-control form-control-sm shadow-none"
                               maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-cxc"
                        onclick="SI_eliminarCxc()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success btn-sm px-4" onclick="SI_guardarCxc()">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial CXP
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSICxp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-receipt me-2"></i>Saldo Inicial — Cuenta por Pagar
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-cxp-id">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold mb-1">Tipo Documento <span class="text-danger">*</span></label>
                        <select id="si-cxp-tipo" class="form-select form-select-sm shadow-none">
                            <option value="FACTURA_COMPRA">Factura de Compra</option>
                            <option value="LIQUIDACION">Liquidación</option>
                            <option value="NOTA_CREDITO">Nota de Crédito</option>
                            <option value="NOTA_DEBITO">Nota de Débito</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-bold mb-1">Nº Documento <span class="text-danger">*</span></label>
                        <input type="text" id="si-cxp-nro" class="form-control form-control-sm shadow-none"
                               placeholder="000-000-000000000" maxlength="17">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                        <input type="date" id="si-cxp-femision" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Vencimiento</label>
                        <input type="date" id="si-cxp-fvenc" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-cxp-id-proveedor">
                        <div class="position-relative">
                            <input type="text" id="si-cxp-proveedor-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por identificación o razón social…" autocomplete="off"
                                   oninput="SI_buscarTercero('cxp', this.value)"
                                   onfocus="SI_buscarTercero('cxp', this.value)">
                            <div id="si-cxp-proveedor-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:220px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-cxp-proveedor-sel" class="mt-1 d-none">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-wrap text-start"
                                  id="si-cxp-proveedor-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;"
                                    onclick="SI_limpiarTercero('cxp')">cambiar</button>
                        </div>
                        <div class="form-text" style="font-size:.72rem;">
                            ¿No existe? <a href="<?= $base ?>/modulos/proveedores" target="_blank">Crear proveedor</a> primero.
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1 text-primary">Saldo Pendiente ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold text-primary border-primary border-opacity-50">$</span>
                            <input type="number" id="si-cxp-saldo"
                                   class="form-control shadow-none fw-bold text-primary border-primary border-opacity-50"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-cxp-obs" class="form-control form-control-sm shadow-none"
                               maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-cxp"
                        onclick="SI_eliminarCxp()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="SI_guardarCxp()">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Registrar Cobro / Pago
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header py-2 px-3" id="si-mov-header">
                <h6 class="modal-title fw-bold" id="si-mov-titulo">
                    <i class="bi bi-cash-coin me-2"></i>Registrar Movimiento
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-mov-id">
                <input type="hidden" id="si-mov-tipo">

                <!-- Resumen del documento -->
                <div class="p-2 border rounded-3 bg-light mb-3">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Documento</div>
                            <div class="fw-bold small font-monospace" id="si-mov-doc"></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.7rem;">Tercero</div>
                            <div class="fw-semibold small text-truncate" id="si-mov-tercero"></div>
                        </div>
                        <div class="col-6 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Saldo Inicial</div>
                            <div class="fw-semibold small">$<span id="si-mov-saldo-inicial"></span></div>
                        </div>
                        <div class="col-6 mt-1">
                            <div class="text-muted" style="font-size:.7rem;">Saldo Pendiente</div>
                            <div class="fw-bold" id="si-mov-saldo-pend" style="color:#dc3545;font-size:1.1rem;"></div>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label small fw-bold mb-1">Serie <span class="text-danger">*</span></label>
                        <select id="si-mov-punto" class="form-select form-select-sm shadow-none"
                                onchange="SI_cargarSecuencialMov(this.value)">
                            <option value="">— Seleccione —</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-bold mb-1">Nº Secuencial</label>
                        <input type="text" id="si-mov-secuencial" readonly
                               class="form-control form-control-sm shadow-none bg-light text-center fw-bold font-monospace"
                               placeholder="—" style="font-size:.8rem;">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Fecha <span class="text-danger">*</span></label>
                        <input type="date" id="si-mov-fecha" class="form-control form-control-sm shadow-none"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Concepto</label>
                        <select id="si-mov-concepto" class="form-select form-select-sm shadow-none">
                            <option value="">— Sin concepto —</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Monto ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold" id="si-mov-icon-monto">$</span>
                            <input type="number" id="si-mov-monto" class="form-control shadow-none fw-bold"
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Forma de Cobro/Pago <span class="text-danger">*</span></label>
                        <select id="si-mov-forma" class="form-select form-select-sm shadow-none"
                                onchange="SI_toggleBancoMov(this.value)">
                            <option value="">— Seleccione —</option>
                        </select>
                    </div>
                    <!-- Datos bancarios (condicional) -->
                    <div class="col-12 d-none" id="si-mov-div-banco">
                        <div class="border border-warning border-opacity-25 rounded-2 p-2 bg-warning bg-opacity-10 row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Op. Bancaria</label>
                                <select id="si-mov-tipo-op" class="form-select form-select-sm shadow-none">
                                    <option value="TRANSFERENCIA">Transferencia</option>
                                    <option value="DEBITO">Débito</option>
                                    <option value="CHEQUE">Cheque</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Nº Referencia</label>
                                <input type="text" id="si-mov-num-op" class="form-control form-control-sm shadow-none"
                                       placeholder="Nº transf / cheque" maxlength="100">
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-mov-obs" class="form-control form-control-sm shadow-none"
                               placeholder="Opcional…" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm px-4 fw-semibold" id="btn-guardar-movimiento"
                        onclick="SI_guardarMovimiento()">
                    <i class="bi bi-check-lg me-1"></i>Registrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Historial de Movimientos
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-clock-history me-2"></i>Historial de Movimientos
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Nº Comprobante</th>
                                <th>Forma</th>
                                <th>Usuario</th>
                                <th class="text-end pe-3">Monto</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="si-hist-tbody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">Cargando…</td></tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end small ps-3 py-2">Total:</td>
                                <td class="text-end pe-3 text-success">$<span id="si-hist-total">0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial Anticipo
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIAnticipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white py-2 px-3" style="background:#6f42c1;">
                <h6 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Saldo Inicial — Anticipo</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-anti-id">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Forma de Anticipo <span class="text-danger">*</span></label>
                        <select id="si-anti-forma" class="form-select form-select-sm shadow-none" onchange="SI_onFormaAnticipoChange()">
                            <option value="">— Seleccione —</option>
                        </select>
                        <div class="form-text" style="font-size:.72rem;">La dirección define si es anticipo de <strong>cliente</strong> (Ingreso) o de <strong>proveedor</strong> (Egreso).</div>
                    </div>

                    <!-- Selector Cliente (aplica_en = INGRESO) -->
                    <div class="col-12 d-none" id="si-anti-cli-block">
                        <label class="form-label small fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-anti-id-cliente">
                        <div class="position-relative">
                            <input type="text" id="si-anti-cli-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por identificación o nombre…" autocomplete="off"
                                   oninput="SI_buscarTercero('anticli', this.value)" onfocus="SI_buscarTercero('anticli', this.value)">
                            <div id="si-anti-cli-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-anti-cli-sel" class="mt-1 d-none">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-wrap text-start"
                                  id="si-anti-cli-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;" onclick="SI_limpiarTercero('anticli')">cambiar</button>
                        </div>
                    </div>

                    <!-- Selector Proveedor (aplica_en = EGRESO) -->
                    <div class="col-12 d-none" id="si-anti-prov-block">
                        <label class="form-label small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-anti-id-proveedor">
                        <div class="position-relative">
                            <input type="text" id="si-anti-prov-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por identificación o razón social…" autocomplete="off"
                                   oninput="SI_buscarTercero('antiprov', this.value)" onfocus="SI_buscarTercero('antiprov', this.value)">
                            <div id="si-anti-prov-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-anti-prov-sel" class="mt-1 d-none">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-wrap text-start"
                                  id="si-anti-prov-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;" onclick="SI_limpiarTercero('antiprov')">cambiar</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha Saldo <span class="text-danger">*</span></label>
                        <input type="date" id="si-anti-fecha" class="form-control form-control-sm shadow-none" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Saldo Inicial ($) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text fw-bold" style="color:#6f42c1;">$</span>
                            <input type="number" id="si-anti-saldo" class="form-control shadow-none fw-bold" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-anti-obs" class="form-control form-control-sm shadow-none" maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-anti"
                        onclick="SI_eliminarAnticipo()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm px-4 text-white" style="background:#6f42c1;" onclick="SI_guardarAnticipo()">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial Inventario
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:580px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Saldo Inicial — Inventario</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Producto <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-inv-id-producto">
                        <div class="position-relative">
                            <input type="text" id="si-inv-prod-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por código o nombre…" autocomplete="off"
                                   oninput="SI_buscarProducto('inv', this.value)" onfocus="SI_buscarProducto('inv', this.value)">
                            <div id="si-inv-prod-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:220px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-inv-prod-sel" class="mt-1 d-none">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 text-wrap text-start"
                                  id="si-inv-prod-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;" onclick="SI_limpiarProducto('inv')">cambiar</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Bodega <span class="text-danger">*</span></label>
                        <select id="si-inv-bodega" class="form-select form-select-sm shadow-none"><option value="">— Seleccione —</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" id="si-inv-cantidad" class="form-control form-control-sm shadow-none" step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Costo Unit.</label>
                        <input type="number" id="si-inv-costo" class="form-control form-control-sm shadow-none" step="0.000001" min="0" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Lote</label>
                        <input type="text" id="si-inv-lote" class="form-control form-control-sm shadow-none" maxlength="50" placeholder="Opcional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Caducidad</label>
                        <input type="date" id="si-inv-caducidad" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">NUP / Serie</label>
                        <input type="text" id="si-inv-nup" class="form-control form-control-sm shadow-none" maxlength="100" placeholder="Opcional">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-inv-obs" class="form-control form-control-sm shadow-none" maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info btn-sm px-4 text-white" onclick="SI_guardarInventario()">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Saldo Inicial Consignación
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSIConsig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:640px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Saldo Inicial — Consignación</h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="si-consig-id">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Fecha <span class="text-danger">*</span></label>
                        <input type="date" id="si-consig-fecha" class="form-control form-control-sm shadow-none" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Nº Documento</label>
                        <input type="text" id="si-consig-nro" class="form-control form-control-sm shadow-none" placeholder="000-000-000000000" maxlength="17">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-consig-id-cliente">
                        <div class="position-relative">
                            <input type="text" id="si-consig-cli-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por identificación o nombre…" autocomplete="off"
                                   oninput="SI_buscarTercero('consig', this.value)" onfocus="SI_buscarTercero('consig', this.value)">
                            <div id="si-consig-cli-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-consig-cli-sel" class="mt-1 d-none">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 text-wrap text-start"
                                  id="si-consig-cli-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;" onclick="SI_limpiarTercero('consig')">cambiar</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Producto <span class="text-danger">*</span></label>
                        <input type="hidden" id="si-consig-id-producto">
                        <div class="position-relative">
                            <input type="text" id="si-consig-prod-buscar" class="form-control form-control-sm shadow-none"
                                   placeholder="Buscar por código o nombre…" autocomplete="off"
                                   oninput="SI_buscarProducto('consig', this.value)" onfocus="SI_buscarProducto('consig', this.value)">
                            <div id="si-consig-prod-dropdown" class="list-group position-absolute w-100 shadow-sm d-none"
                                 style="z-index:1085;max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div id="si-consig-prod-sel" class="mt-1 d-none">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 text-wrap text-start"
                                  id="si-consig-prod-sel-txt" style="font-size:.72rem;"></span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-decoration-none" style="font-size:.72rem;" onclick="SI_limpiarProducto('consig')">cambiar</button>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" id="si-consig-cantidad" class="form-control form-control-sm shadow-none" step="0.01" min="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Precio Unit.</label>
                        <input type="number" id="si-consig-precio" class="form-control form-control-sm shadow-none" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Bodega</label>
                        <select id="si-consig-bodega" class="form-select form-select-sm shadow-none"><option value="">— Ninguna —</option></select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold mb-1">Vendedor</label>
                        <select id="si-consig-vendedor" class="form-select form-select-sm shadow-none"><option value="">— Ninguno —</option></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Lote</label>
                        <input type="text" id="si-consig-lote" class="form-control form-control-sm shadow-none" maxlength="50" placeholder="Opcional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Caducidad</label>
                        <input type="date" id="si-consig-caducidad" class="form-control form-control-sm shadow-none">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold mb-1">NUP / Serie</label>
                        <input type="text" id="si-consig-nup" class="form-control form-control-sm shadow-none" maxlength="100" placeholder="Opcional">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Observaciones</label>
                        <input type="text" id="si-consig-obs" class="form-control form-control-sm shadow-none" maxlength="255" placeholder="Opcional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-eliminar-consig"
                        onclick="SI_eliminarConsig()" style="display:none!important;">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary btn-sm px-4" onclick="SI_guardarConsig()" style="background:#5a6268;">
                        <i class="bi bi-check-lg me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_SI           = "<?= $rutaModulo ?>";
    const SI_PERM_CREAR     = <?= ($perm['crear']      ?? false) ? 'true' : 'false' ?>;
    const SI_PERM_MODIFICAR = <?= ($perm['actualizar'] ?? false) ? 'true' : 'false' ?>;
    const SI_PERM_ELIMINAR  = <?= ($perm['eliminar']   ?? false) ? 'true' : 'false' ?>;

    /* Lazily cargar cada tab la primera vez */
    let SI_tabCxcCargado        = false;
    let SI_tabCxpCargado        = false;
    let SI_tabBancosCargado     = false;
    let SI_tabEfectivoCargado   = false;
    let SI_tabAnticiposCargado  = false;
    let SI_tabInventarioCargado = false;
    let SI_tabConsigCargado     = false;

    function SI_onTabCxc() {
        if (!SI_tabCxcCargado) { SI_cargarCxc(); SI_tabCxcCargado = true; }
    }
    function SI_onTabCxp() {
        if (!SI_tabCxpCargado) { SI_cargarCxp(); SI_tabCxpCargado = true; }
    }
    function SI_onTabBancos() {
        if (!SI_tabBancosCargado) { SI_cargarBancos(); SI_tabBancosCargado = true; }
    }
    function SI_onTabEfectivo() {
        if (!SI_tabEfectivoCargado) { SI_cargarEfectivo(); SI_tabEfectivoCargado = true; }
    }
    function SI_onTabAnticipos() {
        if (!SI_tabAnticiposCargado) { SI_cargarAnticipos(); SI_tabAnticiposCargado = true; }
    }
    function SI_onTabInventario() {
        if (!SI_tabInventarioCargado) { SI_cargarInventario(); SI_tabInventarioCargado = true; }
    }
    function SI_onTabConsig() {
        if (!SI_tabConsigCargado) { SI_cargarConsig(); SI_tabConsigCargado = true; }
    }

    /* stub compatibilidad — el JS real llama SI_cambiarTab */
    function SI_cambiarTab(tab) { /* no-op, Bootstrap maneja el tab */ }
</script>
<script src="<?= BASE_URL ?>/js/modulos/saldos_iniciales.js?v=<?= time() ?>"></script>
