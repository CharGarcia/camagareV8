<?php

/** @var string $titulo */
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $asientosTipo */

$base = BASE_URL;
?>

<style>
    .config-scroll {
        max-height: calc(100dvh - 300px);
        overflow-y: auto;
    }

    .config-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #f8f9fa;
    }

    .table-interactiva td {
        vertical-align: middle !important;
    }

    /* Forzar que el acordeón y la tabla no corten el autocompletado flotante */
    .table-responsive,
    .accordion,
    .accordion-item,
    .accordion-collapse,
    .accordion-body {
        overflow: visible !important;
    }

    /* Sugerencias flotantes de autocompletado en celdas de tablas */
    .autocomplete-celda {
        position: relative;
    }

    .sugerencias-flotantes {
        position: absolute;
        width: 100%;
        max-height: 180px;
        overflow-y: auto;
        z-index: 2200 !important;
        /* Estar por encima de todo */
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background-color: #ffffff !important;
    }

    .sugerencias-flotantes button {
        text-align: left;
        font-size: 0.85rem;
    }

    /* Estilo premium de acordeón */
    .accordion-button:not(.collapsed) {
        background-color: rgba(13, 110, 253, 0.05);
        color: var(--bs-primary);
        box-shadow: none;
    }

    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(13, 110, 253, 0.25);
    }
</style>

<!-- Cabecera de Página -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<!-- Selector de Concepto y Botón de Acción -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-4">
                <label for="tipoAsientoSelector" class="form-label small fw-bold text-secondary mb-1">Seleccionar Tipo de Asiento</label>
                <select class="form-select form-select-sm border shadow-none" id="tipoAsientoSelector">
                    <option value="" disabled selected>-- Elija un Tipo de Asiento --</option>
                    <option value="ventas_factura">Ventas con Factura (Facturas y notas de crédito)</option>
                    <option value="recibos_venta">Recibos de Venta (Recibos y su IVA)</option>
                    <option value="consignacion_venta">Consignaciones en Ventas (Reclasificación de inventario a costo)</option>
                    <option value="adquisiciones_compras">Adquisiciones de Compras/Servicios (Documentos recibidos)</option>
                    <option value="retenciones_venta">Retenciones en Venta</option>
                    <option value="retenciones_compra">Retenciones en Compra</option>
                    <option value="ingresos_egresos">Ingresos y Egresos (Transacciones directas)</option>
                    <option value="cobros_pagos">Cobros y Pagos</option>
                    <option value="nomina">Nómina</option>
                    <option value="cierre_ejercicio">Cierre del Ejercicio (Saldo de resultados a patrimonio)</option>
                    <option value="declaracion_iva">Declaración de IVA (Liquidación del período)</option>
                    <option value="declaracion_retenciones">Declaración de Retenciones (Reclasificación al pago del SRI)</option>
                    <option value="activos_fijos_alta">Activos Fijos - Alta (Contrapartida)</option>
                    <option value="activos_fijos_depreciacion">Activos Fijos - Depreciación (Ajuste por redondeo)</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <button type="button" id="btnConfigurarAsientos" class="btn btn-primary btn-sm px-4 shadow-sm w-100 py-2 fw-medium" onclick="ASIENTOPROG_configurar()">
                    <i class="bi bi-sliders me-1"></i> Configurar Asientos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Panel de Configuración en Acordeones (Oculto por defecto) -->
<div id="seccionAcordeones" style="display: none;" class="mb-4">

    <div class="mb-3 bg-light p-3 rounded-3 shadow-sm border">
        <h6 class="fw-bold mb-0 text-dark" id="conceptoSeleccionadoTitulo">
            <i class="bi bi-gear-fill text-primary me-1"></i> Configuración del Concepto
        </h6>
        <div class="text-muted mt-2 pt-2 border-top" style="font-size:0.72rem; line-height:1.55;">
            <span class="fw-bold text-dark"><i class="bi bi-info-circle me-1"></i>Cómo funciona:</span>
            La pestaña <b>General</b> es la base obligatoria: una cuenta por cada concepto (ventas/compras, IVA, cuentas por cobrar/pagar, costo, etc.). Sobre esa base puedes crear <b>reglas específicas</b> por Cliente/Proveedor, Producto, Categoría o Marca.
            <div class="fw-bold text-dark mt-1">Cómo decide el sistema la cuenta de cada documento (cascada):</div>
            <ul class="mb-1 mt-1 ps-3">
                <li><b>1. Cliente / Proveedor</b> — si la entidad del documento tiene reglas, <b>todo el documento se contabiliza con sus cuentas</b>; los conceptos que no le configuraste pasan directo a <b>General</b> (no se reparten por producto).</li>
                <li><b>2. Producto → Categoría → Marca</b> — solo cuando el documento <b>no</b> tiene cliente/proveedor con reglas: cada línea usa la cuenta de su producto; si no, la de su categoría; si no, la de su marca.</li>
                <li><b>3. General</b> — todo lo que no haya resuelto un nivel anterior.</li>
            </ul>
            Siempre se toman de su propia fuente (no se personalizan por entidad): <b>IVA por tarifa</b>, <b>Costo de venta</b> e <b>Inventario</b>. Las columnas <b>Debe / Haber</b> indican la naturaleza de la cuenta, y <b>“Copiar cuentas de General”</b> precarga las cuentas base.
        </div>
    </div>

    <div class="accordion shadow-sm rounded-3 overflow-hidden" id="acordeonConfiguracion">

        <!-- ACORDEÓN 1: CONFIGURACIÓN GENERAL -->
        <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingGeneral">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral" aria-expanded="false" aria-controls="collapseGeneral">
                    <i class="bi bi-globe me-2 text-primary"></i> 1. Configuración General (Por Defecto)
                </button>
            </h2>
            <div id="collapseGeneral" class="accordion-collapse collapse" aria-labelledby="headingGeneral" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body p-0 border-top bg-white">
                    <!-- Vista de dos columnas (Debe | Haber): ventas, compras y demás conceptos con naturaleza fija -->
                    <div id="dosColumnasGeneral" class="p-3" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded-3 h-100">
                                    <div class="fw-bold small px-3 py-2 d-flex align-items-center gap-2" style="background:#E6F1FB; color:#0C447C; border-top-left-radius:0.45rem; border-top-right-radius:0.45rem;"><i class="bi bi-arrow-down-right"></i> Debe</div>
                                    <div id="colDebeGeneral"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 h-100">
                                    <div class="fw-bold small px-3 py-2 d-flex align-items-center gap-2" style="background:#FAEEDA; color:#633806; border-top-left-radius:0.45rem; border-top-right-radius:0.45rem;"><i class="bi bi-arrow-up-right"></i> Haber</div>
                                    <div id="colHaberGeneral"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Tabla clásica (retenciones en venta y otros conceptos de doble cuenta) -->
                    <div class="table-responsive" id="tablaGeneralWrap">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light" id="theadConfiguracionGeneral">
                                <tr>
                                    <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                    <th class="py-2" style="width: 25%">Detalle</th>
                                    <th class="py-2" style="width: 15%">Tipo Cuenta</th>
                                    <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                    <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                    <th class="text-center py-2" style="width: 10%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyConfiguracionGeneral">
                                <!-- Filas dinámicas cargadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== DIMENSIONES (FASE A): Cliente (ventas) / Proveedor (compras) / Tarifa IVA (ambos). Visibilidad controlada por JS según el tipo de asiento. ===== -->

        <!-- POR CLIENTE (ventas) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemCliente" style="display:none;">
            <h2 class="accordion-header" id="headingClientes">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClientes" aria-expanded="false" aria-controls="collapseClientes" onclick="ASIENTOPROG_cargarDim('cliente')">
                    <i class="bi bi-people me-2 text-primary"></i> Reglas por Clientes
                </button>
            </h2>
            <div id="collapseClientes" class="accordion-collapse collapse" aria-labelledby="headingClientes" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'cliente')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-person-plus-fill me-1"></i> Nueva Asociaci&oacute;n por Cliente</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Cliente</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_cliente" placeholder="Escriba, o use &quot;Clientes con ventas&quot;..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_cliente" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_cliente" style="display: none;"></div>
                                </div>
                                <select id="dim_anio_cliente" class="form-select form-select-sm" style="width:auto;" title="A&ntilde;o de ventas">
                                    <option value="">Todos los a&ntilde;os</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('cliente')">
                                    <i class="bi bi-people me-1"></i> Clientes con ventas
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalItems('cliente')">
                                    <i class="bi bi-box-seam me-1"></i> Informaci&oacute;n de adquisiciones
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_cliente"></div></div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Cliente</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_cliente"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para clientes.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- POR EMPLEADO (nómina) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemEmpleado" style="display:none;">
            <h2 class="accordion-header" id="headingEmpleados">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEmpleados" aria-expanded="false" aria-controls="collapseEmpleados" onclick="ASIENTOPROG_cargarDim('empleado')">
                    <i class="bi bi-person-badge me-2 text-primary"></i> Reglas por Empleado
                </button>
            </h2>
            <div id="collapseEmpleados" class="accordion-collapse collapse" aria-labelledby="headingEmpleados" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'empleado')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-person-plus-fill me-1"></i> Cuentas espec&iacute;ficas por Empleado</h6>
                            <small class="text-muted">Las cuentas asignadas aqu&iacute; sobreescriben las cuentas generales de n&oacute;mina para ese empleado.</small>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Empleado</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_empleado" placeholder="Escriba nombre o c&eacute;dula..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_empleado" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_empleado" style="display: none;"></div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('empleado')">
                                    <i class="bi bi-people me-1"></i> Empleados activos
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_empleado"></div></div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Empleado</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_empleado"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado cuentas por empleado.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- POR PROVEEDOR (compras) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemProveedor" style="display:none;">
            <h2 class="accordion-header" id="headingProveedores">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProveedores" aria-expanded="false" aria-controls="collapseProveedores" onclick="ASIENTOPROG_cargarDim('proveedor')">
                    <i class="bi bi-truck me-2 text-primary"></i> Reglas por Proveedores
                </button>
            </h2>
            <div id="collapseProveedores" class="accordion-collapse collapse" aria-labelledby="headingProveedores" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'proveedor')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-building-add me-1"></i> Nueva Asociaci&oacute;n por Proveedor</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Proveedor</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_proveedor" placeholder="Escriba, o use &quot;Proveedores con compras&quot;..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_proveedor" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_proveedor" style="display: none;"></div>
                                </div>
                                <select id="dim_anio_proveedor" class="form-select form-select-sm" style="width:auto;" title="A&ntilde;o de compras">
                                    <option value="">Todos los a&ntilde;os</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('proveedor')">
                                    <i class="bi bi-truck me-1"></i> Proveedores con compras
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalItems('proveedor')">
                                    <i class="bi bi-box-seam me-1"></i> Informaci&oacute;n de adquisiciones
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_proveedor"></div></div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Proveedor</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_proveedor"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para proveedores.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- POR PRODUCTO / SERVICIO (ventas y compras) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemProducto" style="display:none;">
            <h2 class="accordion-header" id="headingProductos">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProductos" aria-expanded="false" aria-controls="collapseProductos" onclick="ASIENTOPROG_cargarDim('producto')">
                    <i class="bi bi-box-seam me-2 text-primary"></i> Reglas por Productos / Servicios
                </button>
            </h2>
            <div id="collapseProductos" class="accordion-collapse collapse" aria-labelledby="headingProductos" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'producto')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2"><h6 class="text-primary mb-0 fw-bold"><i class="bi bi-box-seam me-1"></i> Nueva Asociaci&oacute;n por Producto/Servicio</h6></div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Producto / Servicio</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_producto" placeholder="Escriba, o use &quot;Productos con movimientos&quot;..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_producto" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_producto" style="display: none;"></div>
                                </div>
                                <select id="dim_anio_producto" class="form-select form-select-sm" style="width:auto;" title="A&ntilde;o de movimientos">
                                    <option value="">Todos los a&ntilde;os</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('producto')">
                                    <i class="bi bi-box-seam me-1"></i> Productos con movimientos
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_producto"></div></div>
                        <div class="col-12 mt-4 text-end"><button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Producto / Servicio</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_producto"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para productos.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- POR CATEGORÍA (ventas y compras) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemCategoria" style="display:none;">
            <h2 class="accordion-header" id="headingCategorias">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategorias" aria-expanded="false" aria-controls="collapseCategorias" onclick="ASIENTOPROG_cargarDim('categoria')">
                    <i class="bi bi-tags me-2 text-primary"></i> Reglas por Categor&iacute;as
                </button>
            </h2>
            <div id="collapseCategorias" class="accordion-collapse collapse" aria-labelledby="headingCategorias" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'categoria')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2"><h6 class="text-primary mb-0 fw-bold"><i class="bi bi-tags me-1"></i> Nueva Asociaci&oacute;n por Categor&iacute;a</h6></div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Categor&iacute;a</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_categoria" placeholder="Escriba, o use &quot;Categor&iacute;as&quot;..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_categoria" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_categoria" style="display: none;"></div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('categoria')">
                                    <i class="bi bi-tags me-1"></i> Categor&iacute;as
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_categoria"></div></div>
                        <div class="col-12 mt-4 text-end"><button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Categor&iacute;a</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_categoria"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para categor&iacute;as.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- POR MARCA (ventas y compras) -->
        <div class="accordion-item border-0 border-top dim-accordion-fase-a" id="accItemMarca" style="display:none;">
            <h2 class="accordion-header" id="headingMarcas">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcas" aria-expanded="false" aria-controls="collapseMarcas" onclick="ASIENTOPROG_cargarDim('marca')">
                    <i class="bi bi-bookmark-star me-2 text-primary"></i> Reglas por Marcas
                </button>
            </h2>
            <div id="collapseMarcas" class="accordion-collapse collapse" aria-labelledby="headingMarcas" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'marca')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2"><h6 class="text-primary mb-0 fw-bold"><i class="bi bi-bookmark-star me-1"></i> Nueva Asociaci&oacute;n por Marca</h6></div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Marca</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="position-relative" style="flex:1 1 240px; min-width:220px; max-width:420px;">
                                    <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_marca" placeholder="Escriba, o use &quot;Marcas&quot;..." autocomplete="off" required>
                                    <input type="hidden" id="dim_id_marca" required>
                                    <div class="list-group sugerencias-flotantes" id="dim_sug_marca" style="display: none;"></div>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" onclick="ASIENTOPROG_abrirModalEntidades('marca')">
                                    <i class="bi bi-bookmark-star me-1"></i> Marcas
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12"><div class="row g-3" id="inputsDinamicos_marca"></div></div>
                        <div class="col-12 mt-4 text-end"><button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociaci&oacute;n</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light"><tr><th class="ps-4 py-2">Marca</th><th class="py-2">Concepto / Referencia</th><th class="py-2">Cuenta Contable Asignada</th><th class="text-center py-2" style="width: 15%">Acci&oacute;n</th></tr></thead>
                            <tbody id="tbodyDim_marca"><tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para marcas.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!--
        ========================================================================
        ACORDEONES DE DIMENSIONES ESPECÍFICAS (DESACTIVADOS TEMPORALMENTE)
        ========================================================================
        ACORDEÓN 2: POR CLIENTES
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingClientes">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseClientes" aria-expanded="false" aria-controls="collapseClientes" onclick="ASIENTOPROG_cargarDim('cliente')">
                    <i class="bi bi-people me-2 text-primary"></i> 2. Reglas por Clientes
                </button>
            </h2>
            <div id="collapseClientes" class="accordion-collapse collapse" aria-labelledby="headingClientes" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'cliente')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-person-plus-fill me-1"></i> Nueva Asociación por Cliente</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Buscar Cliente</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_cliente" placeholder="Escriba nombre o RUC del cliente..." autocomplete="off" required>
                                <input type="hidden" id="dim_id_cliente" required>
                                <div class="list-group sugerencias-flotantes" id="dim_sug_cliente" style="display: none;"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-3" id="inputsDinamicos_cliente">
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociación</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2">Cliente</th>
                                    <th class="py-2">Concepto / Referencia</th>
                                    <th class="py-2">Cuenta Contable Asignada</th>
                                    <th class="text-center py-2" style="width: 15%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDim_cliente">
                                <tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para clientes.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        ACORDEÓN 3: POR PRODUCTOS
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingProductos">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProductos" aria-expanded="false" aria-controls="collapseProductos" onclick="ASIENTOPROG_cargarDim('producto')">
                    <i class="bi bi-box-seam me-2 text-primary"></i> 3. Reglas por Productos
                </button>
            </h2>
            <div id="collapseProductos" class="accordion-collapse collapse" aria-labelledby="headingProductos" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'producto')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-plus-circle-fill me-1"></i> Nueva Asociación por Producto</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Buscar Producto</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_producto" placeholder="Escriba código o nombre del producto..." autocomplete="off" required>
                                <input type="hidden" id="dim_id_producto" required>
                                <div class="list-group sugerencias-flotantes" id="dim_sug_producto" style="display: none;"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-3" id="inputsDinamicos_producto">
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociación</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2">Producto</th>
                                    <th class="py-2">Concepto / Referencia</th>
                                    <th class="py-2">Cuenta Contable Asignada</th>
                                    <th class="text-center py-2" style="width: 15%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDim_producto">
                                <tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para productos.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        ACORDEÓN 4: POR CATEGORÍAS
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingCategorias">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCategorias" aria-expanded="false" aria-controls="collapseCategorias" onclick="ASIENTOPROG_cargarDim('categoria')">
                    <i class="bi bi-tags me-2 text-primary"></i> 4. Reglas por Categorías
                </button>
            </h2>
            <div id="collapseCategorias" class="accordion-collapse collapse" aria-labelledby="headingCategorias" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'categoria')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-tag-fill me-1"></i> Nueva Asociación por Categoría</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Buscar Categoría</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_categoria" placeholder="Escriba nombre de la categoría..." autocomplete="off" required>
                                <input type="hidden" id="dim_id_categoria" required>
                                <div class="list-group sugerencias-flotantes" id="dim_sug_categoria" style="display: none;"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-3" id="inputsDinamicos_categoria">
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociación</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2">Categoría</th>
                                    <th class="py-2">Concepto / Referencia</th>
                                    <th class="py-2">Cuenta Contable Asignada</th>
                                    <th class="text-center py-2" style="width: 15%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDim_categoria">
                                <tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para categorías.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        ACORDEÓN 5: POR MARCAS
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingMarcas">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcas" aria-expanded="false" aria-controls="collapseMarcas" onclick="ASIENTOPROG_cargarDim('marca')">
                    <i class="bi bi-bookmark-star me-2 text-primary"></i> 5. Reglas por Marcas
                </button>
            </h2>
            <div id="collapseMarcas" class="accordion-collapse collapse" aria-labelledby="headingMarcas" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'marca')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-bookmark-star-fill me-1"></i> Nueva Asociación por Marca</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Buscar Marca</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_marca" placeholder="Escriba nombre de la marca..." autocomplete="off" required>
                                <input type="hidden" id="dim_id_marca" required>
                                <div class="list-group sugerencias-flotantes" id="dim_sug_marca" style="display: none;"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-3" id="inputsDinamicos_marca">
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociación</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2">Marca</th>
                                    <th class="py-2">Concepto / Referencia</th>
                                    <th class="py-2">Cuenta Contable Asignada</th>
                                    <th class="text-center py-2" style="width: 15%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDim_marca">
                                <tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para marcas.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        ACORDEÓN 6: POR TARIFA DE IVA
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingIvas">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIvas" aria-expanded="false" aria-controls="collapseIvas" onclick="ASIENTOPROG_cargarDim('iva')">
                    <i class="bi bi-percent me-2 text-primary"></i> 6. Reglas por Tarifas de IVA
                </button>
            </h2>
            <div id="collapseIvas" class="accordion-collapse collapse" aria-labelledby="headingIvas" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body bg-white p-4">
                    <form onsubmit="ASIENTOPROG_agregarDim(event, 'iva')" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border shadow-sm">
                        <div class="col-12 mb-2 border-bottom pb-2">
                            <h6 class="text-primary mb-0 fw-bold"><i class="bi bi-percent me-1"></i> Nueva Asociación por Tarifa IVA</h6>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-search me-1"></i> Buscar Tarifa IVA</label>
                            <div class="position-relative">
                                <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_search_iva" placeholder="Escriba nombre de tarifa o porcentaje..." autocomplete="off" required>
                                <input type="hidden" id="dim_id_iva" required>
                                <div class="list-group sugerencias-flotantes" id="dim_sug_iva" style="display: none;"></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-3" id="inputsDinamicos_iva">
                            </div>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm"><i class="bi bi-save me-1"></i> Guardar Asociación</button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2">Tarifa IVA</th>
                                    <th class="py-2">Concepto / Referencia</th>
                                    <th class="py-2">Cuenta Contable Asignada</th>
                                    <th class="text-center py-2" style="width: 15%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDim_iva">
                                <tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para tarifas de IVA.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        -->

    </div>

    <!--
    ========================================================================
    ACORDEONES EXCLUSIVOS DE INGRESOS Y EGRESOS
    Se arman desde el módulo "Opciones de Ingreso/Egreso". Solo visibles cuando
    el tipo de asiento seleccionado es "Ingresos y Egresos".
    ========================================================================
    -->
    <div id="acordeonIngresoEgreso" class="accordion shadow-sm rounded-3 overflow-hidden" style="display: none;">

        <!-- ACORDEÓN: INGRESOS -->
        <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingOpcIngresos">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOpcIngresos" aria-expanded="false" aria-controls="collapseOpcIngresos">
                    <i class="bi bi-arrow-down-left-circle me-2 text-success"></i> Ingresos <span class="badge ms-2" style="background:#FAEEDA; color:#633806; font-weight:500;">Haber</span>
                </button>
            </h2>
            <div id="collapseOpcIngresos" class="accordion-collapse collapse" aria-labelledby="headingOpcIngresos" data-bs-parent="#acordeonIngresoEgreso">
                <div class="accordion-body p-0 border-top bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                    <th class="py-2" style="width: 25%">Detalle</th>
                                    <th class="py-2" style="width: 20%">Tipo Cuenta</th>
                                    <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                    <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                    <th class="text-center py-2" style="width: 5%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyOpcIngresos">
                                <!-- Filas dinámicas cargadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACORDEÓN: EGRESOS -->
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingOpcEgresos">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOpcEgresos" aria-expanded="false" aria-controls="collapseOpcEgresos">
                    <i class="bi bi-arrow-up-right-circle me-2 text-danger"></i> Egresos <span class="badge ms-2" style="background:#E6F1FB; color:#0C447C; font-weight:500;">Debe</span>
                </button>
            </h2>
            <div id="collapseOpcEgresos" class="accordion-collapse collapse" aria-labelledby="headingOpcEgresos" data-bs-parent="#acordeonIngresoEgreso">
                <div class="accordion-body p-0 border-top bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                    <th class="py-2" style="width: 25%">Detalle</th>
                                    <th class="py-2" style="width: 20%">Tipo Cuenta</th>
                                    <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                    <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                    <th class="text-center py-2" style="width: 5%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyOpcEgresos">
                                <!-- Filas dinámicas cargadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!--
    ========================================================================
    ACORDEONES EXCLUSIVOS DE COBROS Y PAGOS
    Se arman desde el módulo "Formas de Cobros y Pagos". Solo visibles cuando
    el tipo de asiento seleccionado es "Cobros y Pagos".
    ========================================================================
    -->
    <div id="acordeonCobroPago" class="accordion shadow-sm rounded-3 overflow-hidden" style="display: none;">

        <!-- ACORDEÓN: COBROS -->
        <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingFormaCobros">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFormaCobros" aria-expanded="false" aria-controls="collapseFormaCobros">
                    <i class="bi bi-cash-coin me-2 text-success"></i> Cobros <span class="badge ms-2" style="background:#E6F1FB; color:#0C447C; font-weight:500;">Debe</span>
                </button>
            </h2>
            <div id="collapseFormaCobros" class="accordion-collapse collapse" aria-labelledby="headingFormaCobros" data-bs-parent="#acordeonCobroPago">
                <div class="accordion-body p-0 border-top bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                    <th class="py-2" style="width: 25%">Detalle</th>
                                    <th class="py-2" style="width: 20%">Tipo Cuenta</th>
                                    <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                    <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                    <th class="text-center py-2" style="width: 5%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyFormaCobros">
                                <!-- Filas dinámicas cargadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACORDEÓN: PAGOS -->
        <div class="accordion-item border-0 border-top">
            <h2 class="accordion-header" id="headingFormaPagos">
                <button class="accordion-button collapsed fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFormaPagos" aria-expanded="false" aria-controls="collapseFormaPagos">
                    <i class="bi bi-credit-card-2-back me-2 text-danger"></i> Pagos <span class="badge ms-2" style="background:#FAEEDA; color:#633806; font-weight:500;">Haber</span>
                </button>
            </h2>
            <div id="collapseFormaPagos" class="accordion-collapse collapse" aria-labelledby="headingFormaPagos" data-bs-parent="#acordeonCobroPago">
                <div class="accordion-body p-0 border-top bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                    <th class="py-2" style="width: 25%">Detalle</th>
                                    <th class="py-2" style="width: 20%">Tipo Cuenta</th>
                                    <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                    <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                    <th class="text-center py-2" style="width: 5%">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyFormaPagos">
                                <!-- Filas dinámicas cargadas por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Proveedores con compras -->
<div class="modal fade" id="modalProveedoresCompras" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:540px;">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="modalEntidadesTitulo"><i class="bi bi-card-list me-1 text-primary"></i> Entidades</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="modalProvSearch" class="form-control form-control-sm mb-2" placeholder="Filtrar..." autocomplete="off">
                <div class="small text-muted mb-2"><i class="bi bi-check-circle-fill text-success"></i> = ya tiene cuentas asignadas en esta regla.</div>
                <div id="modalProvLista" class="list-group list-group-flush small"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Información de adquisiciones del proveedor -->
<div class="modal fade" id="modalItemsProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:560px;">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-box-seam me-1 text-primary"></i> Informaci&oacute;n de adquisiciones <span id="modalItemsProvNombre" class="text-muted fw-normal ms-1"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modalItemsBody"></div>
        </div>
    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>

<script src="<?= $base ?>/js/modulos/configuracion_contable_modal.js?v=<?= time() ?>"></script>