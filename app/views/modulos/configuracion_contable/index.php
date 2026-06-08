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
                    <option value="ventas_recibo">Ventas con Recibo (Recibos no autorizados)</option>
                    <option value="adquisiciones_compras">Adquisiciones de Compras/Servicios (Documentos recibidos)</option>
                    <option value="retenciones_venta">Retenciones en Venta</option>
                    <option value="retenciones_compra">Retenciones en Compra</option>
                    <option value="ingresos_egresos">Ingresos y Egresos (Transacciones directas)</option>
                    <option value="cobros_pagos">Cobros y Pagos</option>
                    <option value="nomina">Nómina</option>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 bg-light p-3 rounded-3 shadow-sm border">
        <h6 class="fw-bold mb-0 text-dark" id="conceptoSeleccionadoTitulo">
            <i class="bi bi-gear-fill text-primary me-1"></i> Configuración del Concepto
        </h6>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <label for="selectorMetodoPreferencia" class="form-label small fw-bold mb-0 text-muted"><i class="bi bi-diagram-3-fill me-1"></i> Contabilizar Por:</label>
            <select class="form-select form-select-sm fw-medium shadow-sm text-dark bg-white" id="selectorMetodoPreferencia" style="width: 250px;" onchange="ASIENTOPROG_guardarMetodoPreferencia(this.value)">
                <option value="general">General (Por Defecto)</option>
                <option value="cliente">Por Clientes</option>
                <option value="producto">Por Productos</option>
                <option value="categoria">Por Categorías</option>
                <option value="marca">Por Marcas</option>
                <option value="iva">Por Tarifas de IVA</option>
                <option value="cascada">Jerarquía Completa (Cascada)</option>
            </select>
        </div>
    </div>

    <div class="accordion shadow-sm rounded-3 overflow-hidden" id="acordeonConfiguracion">

        <!-- ACORDEÓN 1: CONFIGURACIÓN GENERAL -->
        <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingGeneral">
                <button class="accordion-button fw-bold py-3 px-4 text-dark bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral" aria-expanded="true" aria-controls="collapseGeneral">
                    <i class="bi bi-globe me-2 text-primary"></i> 1. Configuración General (Por Defecto)
                </button>
            </h2>
            <div id="collapseGeneral" class="accordion-collapse collapse show" aria-labelledby="headingGeneral" data-bs-parent="#acordeonConfiguracion">
                <div class="accordion-body p-0 border-top bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle table-interactiva">
                            <thead class="table-light">
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

        <!-- ACORDEÓN 2: POR CLIENTES -->
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
                                <!-- Cargado dinámicamente según la Configuración General -->
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

        <!-- ACORDEÓN 3: POR PRODUCTOS -->
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
                                <!-- Cargado dinámicamente según la Configuración General -->
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

        <!-- ACORDEÓN 4: POR CATEGORÍAS -->
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
                                <!-- Cargado dinámicamente según la Configuración General -->
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

        <!-- ACORDEÓN 5: POR MARCAS -->
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
                                <!-- Cargado dinámicamente según la Configuración General -->
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

        <!-- ACORDEÓN 6: POR TARIFA DE IVA -->
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
                                <!-- Cargado dinámicamente según la Configuración General -->
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

    </div>
</div>

<script>
    window.BASE_URL = '<?= $base ?>';
</script>

<script src="<?= $base ?>/js/modulos/configuracion_contable_modal.js?v=<?= time() ?>"></script>