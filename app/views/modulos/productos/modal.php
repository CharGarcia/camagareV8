<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de productos para ocultar pestañas correctamente
$vistaConfigProd = \App\Helpers\PreferenciasHelper::getPreferenciasVista('productos');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigProd, 'estiloVistaPestanasProd');

// 2. Forzar permisos de productos
$permProd = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/productos') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubProd = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/productos');
    if ($idSubProd) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubProd])) {
            $p = $mapPerm[$idSubProd];
            $permProd = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permProd = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}
?>

<!-- Modal Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form id="formProducto" novalidate onsubmit="return false;">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-box me-2 text-primary"></i> <span id="tituloModalProducto">Nuevo Producto</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none m-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="prod_id">

                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="modalProductoTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#pane-general" role="tab">
                                    <i class="bi bi-card-text me-1"></i> General
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-inventario-btn" data-bs-toggle="tab" href="#pane-inventario" role="tab">
                                    <i class="bi bi-box-seam me-1"></i> Inventario
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-precios-btn" data-bs-toggle="tab" href="#pane-precios" role="tab">
                                    <i class="bi bi-tags me-1"></i> Precios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-clasificacion-btn" data-bs-toggle="tab" href="#pane-clasificacion" role="tab">
                                    <i class="bi bi-diagram-3 me-1"></i> Clasificación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-componentes-btn" data-bs-toggle="tab" href="#pane-componentes" role="tab">
                                    <i class="bi bi-puzzle me-1"></i> Componentes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-variantes-btn" data-bs-toggle="tab" href="#pane-variantes" role="tab">
                                    <i class="bi bi-list-stars me-1"></i> Variantes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-homologaciones-btn" data-bs-toggle="tab" href="#pane-homologaciones" role="tab">
                                    <i class="bi bi-arrow-repeat me-1"></i> Homologaciones
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-info-btn" data-bs-toggle="tab" href="#pane-info" role="tab">
                                    <i class="bi bi-info-circle me-1"></i> Información
                                </a>
                            </li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfig = [
                                'tab-inventario-btn' => 'Inventario',
                                'tab-precios-btn' => 'Precios',
                                'tab-clasificacion-btn' => 'Clasificación',
                                'tab-componentes-btn' => 'Componentes',
                                'tab-variantes-btn' => 'Variantes',
                                'tab-homologaciones-btn' => 'Homologaciones',
                                'tab-info-btn' => 'Información'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfig, $vistaConfigProd ?? [], 'productos');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3">
                        <!-- TAB GENERAL -->
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <div class="row g-3">
                                <!-- Fila 0: Opciones de uso (Compra / Venta) - extensible a futuro -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-4 flex-wrap px-2 py-2 border rounded-3 bg-light bg-opacity-75">
                                        <span class="small fw-bold text-muted"><i class="bi bi-toggles me-1"></i>Usar en:</span>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="prod_opc_compra" name="opc_compra" value="1" checked>
                                            <label class="form-check-label small fw-semibold" for="prod_opc_compra">
                                                <i class="bi bi-cart-plus text-success me-1"></i>Compra
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="prod_opc_venta" name="opc_venta" value="1" checked>
                                            <label class="form-check-label small fw-semibold" for="prod_opc_venta">
                                                <i class="bi bi-bag-check text-primary me-1"></i>Venta
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 1: Tipo Producción, Código Principal  codigo auxiliar Código de Barras-->
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Tipo Producción <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tipo_produccion', 'tipo_produccion') ?></label>
                                    <select name="tipo_produccion" id="prod_tipo_produccion" class="form-select form-select-sm shadow-none border-secondary-subtle" onchange="actualizarCodigoSiguiente(); toggleBienesFields();">
                                        <option value="01">Producto</option>
                                        <option value="02">Servicio</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código Principal *</label>
                                    <input type="text" name="codigo" id="prod_codigo" class="form-control form-control-sm shadow-none border-secondary-subtle fw-bold" required maxlength="50" style="text-transform:uppercase;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código Auxiliar</label>
                                    <input type="text" name="codigo_auxiliar" id="prod_codigo_auxiliar" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="50">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código de Barras</label>
                                    <input type="text" name="codigo_barras" id="prod_codigo_barras" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="50">
                                </div>

                                <!-- Fila 2: Descripción-->
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted text-primary">Descripción / Nombre *</label>
                                    <input type="text" name="nombre" id="prod_nombre" class="form-control form-control-sm shadow-none border-secondary-subtle" required maxlength="200" placeholder="Nombre del producto o servicio">
                                </div>

                                <!-- Fila 3:  Tipo Medida, Unidad de Medida -->
                                <div class="col-md-3 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Tipo medida <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tipo_medida', 'id_tipo_medida') ?></label>
                                    <select name="id_tipo_medida" id="prod_tipo_medida" class="form-select form-select-sm shadow-none border-secondary-subtle" onchange="actualizarUnidadesMedida()"></select>
                                </div>
                                <div class="col-md-3 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Unidad de Medida</label>
                                    <select name="id_medida" id="prod_id_medida" class="form-select form-select-sm shadow-none border-secondary-subtle">
                                        <option value="">Seleccione medida</option>
                                    </select>
                                </div>

                                <!-- Fila 4: Precios y Tributación -->
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Precio Base</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="precio_base" id="prod_precio_base" class="form-control shadow-none border-secondary-subtle fw-bold" step="0.0001" min="0" value="0.00" oninput="calcularPreciosTotales()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Tarifa IVA <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tarifa_iva', 'tarifa_iva') ?></label>
                                    <select name="tarifa_iva" id="prod_tarifa_iva" class="form-select form-select-sm shadow-none border-secondary-subtle" onchange="calcularPreciosTotales()"></select>
                                </div>
                                <div class="col-md-2 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Concepto ICE</label>
                                    <select name="id_ice" id="prod_id_ice" class="form-select form-select-sm shadow-none border-secondary-subtle" onchange="actualizarValorICE(); calcularPreciosTotales();">
                                        <option value="">Sin ICE</option>
                                    </select>
                                </div>
                                <div class="col-md-2 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Valor ICE (%)</label>
                                    <input type="number" name="valor_ice" id="prod_valor_ice" class="form-control form-control-sm bg-light shadow-none" readonly value="0.00">
                                </div>

                                <!-- Fila 5: PVP Final -->
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-primary">PVP Final (Inc. Imp.)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-primary bg-opacity-10 text-primary border-primary border-opacity-25">$</span>
                                        <input type="number" id="prod_pvp_total" class="form-control shadow-none border-primary border-opacity-25 fw-bold text-primary" step="0.01" min="0" value="0.00" oninput="calcularPrecioInverso()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB INVENTARIO -->
                        <div class="tab-pane fade" id="pane-inventario" role="tabpanel">
                            <div class="row g-3">
                                <!-- Switch Inventariable -->
                                <div class="col-md-12">
                                    <div class="p-3 border rounded bg-light bg-opacity-50">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="inventariable" id="prod_inventariable" value="1" checked onchange="toggleInventariableTabs()">
                                            <label class="form-check-label fw-bold text-muted" for="prod_inventariable">Este producto maneja inventario (Stock)</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stocks -->
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Saldo Total (Stock)</label>
                                    <input type="text" id="prod_stock_actual" class="form-control form-control-sm bg-light fw-bold text-primary shadow-none" readonly value="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Stock Mínimo</label>
                                    <input type="number" name="stock_minimo" id="prod_stock_minimo" class="form-control form-control-sm shadow-none border-warning-subtle" step="any" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Stock Máximo</label>
                                    <input type="number" name="stock_maximo" id="prod_stock_maximo" class="form-control form-control-sm shadow-none border-info-subtle" step="any" placeholder="0.00">
                                </div>

                                <div class="col-md-12 mt-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Costo Actual</label>
                                    <div class="input-group input-group-sm" style="max-width: 200px;">
                                        <span class="input-group-text">$</span>
                                        <input type="number" name="costo_producto" id="prod_costo_producto" class="form-control shadow-none border-secondary-subtle fw-bold" step="0.000001" min="0" value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB PRECIOS -->
                        <div class="tab-pane fade" id="pane-precios" role="tabpanel">
                            <div class="card border-0 bg-light bg-opacity-50 mb-3">
                                <div class="card-body p-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Nombre Precio</label>
                                            <input type="text" id="pre_nombre" class="form-control form-control-sm shadow-none" placeholder="Ej: Mayorista">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted mb-1">Precio</label>
                                            <input type="number" id="pre_valor" step="0.01" class="form-control form-control-sm shadow-none text-end" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                                            <input type="date" id="pre_desde" class="form-control form-control-sm shadow-none">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                                            <input type="date" id="pre_hasta" class="form-control form-control-sm shadow-none">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="agregarPrecio()">
                                                <i class="bi bi-plus-lg"></i> Añadir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered mb-0 small text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Nombre / Detalle</th>
                                            <th>Precio</th>
                                            <th>Desde</th>
                                            <th>Hasta</th>
                                            <th style="width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-precios">
                                        <tr>
                                            <td colspan="5" class="text-muted py-3">No hay precios adicionales</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB CLASIFICACIÓN -->
                        <div class="tab-pane fade" id="pane-clasificacion" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Categoría</label>
                                    <div class="input-group input-group-sm">
                                        <select name="id_categoria" id="prod_id_categoria" class="form-select shadow-none"></select>
                                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalCategoriaCrear()"><i class="bi bi-plus-circle"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Marca</label>
                                    <div class="input-group input-group-sm">
                                        <select name="id_marca" id="prod_id_marca" class="form-select shadow-none"></select>
                                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalMarcaCrear()"><i class="bi bi-plus-circle"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <!-- TAB COMPONENTES -->
                        <div class="tab-pane fade" id="pane-componentes" role="tabpanel">
                            <div class="card border-0 bg-light bg-opacity-50 mb-3">
                                <div class="card-body p-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted mb-1">Buscar Componente</label>
                                            <div class="position-relative">
                                                <input type="text" id="comp_search" class="form-control form-control-sm shadow-none" placeholder="Código o nombre..." oninput="searchComponente()">
                                                <div id="comp_results" class="list-group position-absolute w-100 shadow-lg d-none" style="z-index: 1070; max-height: 200px; overflow-y: auto;"></div>
                                            </div>
                                            <input type="hidden" id="comp_id">
                                            <input type="hidden" id="comp_codigo">
                                            <input type="hidden" id="comp_nombre">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-bold text-muted mb-1">Cant.</label>
                                            <input type="number" id="comp_cantidad" step="any" class="form-control form-control-sm shadow-none text-end" value="1">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold text-muted mb-1">Medida</label>
                                            <select id="comp_medida" class="form-select form-select-sm shadow-none"></select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="agregarComponente()">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered mb-0 small text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Cód.</th>
                                            <th class="text-start">Nombre</th>
                                            <th>Cant.</th>
                                            <th>Medida</th>
                                            <th style="width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-componentes">
                                        <tr>
                                            <td colspan="5" class="text-muted py-3">No hay componentes</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB VARIANTES -->
                        <div class="tab-pane fade" id="pane-variantes" role="tabpanel">
                            <div class="row g-2 mb-3 bg-light p-2 rounded border">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Variante</label>
                                    <input type="text" id="var_nombre" class="form-control form-control-sm shadow-none" placeholder="Color">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Valor</label>
                                    <input type="text" id="var_valor" class="form-control form-control-sm shadow-none" placeholder="Rojo">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Precio +</label>
                                    <input type="number" id="var_precio" class="form-control form-control-sm shadow-none" step="any" value="0.00">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="agregarVariante()">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered mb-0 small text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Variante</th>
                                            <th class="text-start">Valor</th>
                                            <th>P. Adicional</th>
                                            <th style="width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-variantes">
                                        <tr>
                                            <td colspan="4" class="text-muted py-3">No hay variantes</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB HOMOLOGACIONES -->
                        <div class="tab-pane fade" id="pane-homologaciones" role="tabpanel">
                            <div class="bg-light p-2 rounded border mb-3">
                                <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Estos son los códigos con los que tus proveedores identifican este producto en sus documentos electrónicos.</p>
                            </div>
                            <div class="table-responsive" style="max-height: 300px;">
                                <table class="table table-sm table-hover border mb-0 small">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th class="ps-3">Proveedor</th>
                                            <th>Producto del proveedor</th>
                                            <th>Código de producto</th>
                                            <th class="text-center" style="width: 80px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-homologaciones">
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No hay homologaciones registradas</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB INFO -->
                        <div class="tab-pane fade" id="pane-info" role="tabpanel">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Estado del Registro</label>
                                    <select name="status" id="prod_status" class="form-select form-select-sm shadow-none border-secondary-subtle">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label mb-2 small fw-bold text-muted">Imagen del Producto</label>
                                <div class="d-flex flex-column align-items-start gap-2">
                                    <div id="imagePreview" class="rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden"
                                         style="width:200px;height:200px;cursor:pointer" onclick="document.getElementById('inputImage').click()" title="Clic para cambiar imagen">
                                        <i class="bi bi-image text-muted" style="font-size:3rem;opacity:0.25"></i>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('inputImage').click()">
                                            <i class="bi bi-upload me-1"></i> Subir imagen
                                        </button>
                                        <button type="button" id="btnRemoveImage" class="btn btn-outline-danger btn-sm d-none" onclick="removerImagen()">
                                            <i class="bi bi-trash me-1"></i> Quitar
                                        </button>
                                    </div>
                                    <input type="hidden" name="imagen" id="prod_imagen">
                                    <input type="file" id="inputImage" class="d-none" accept="image/*" onchange="uploadProductImage(this)">
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($permProd['eliminar']): ?>
                            <button type="button" id="btnEliminarProducto" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarProducto()">
                                <i class="bi bi-trash3 me-1"></i> Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="button" class="btn btn-primary px-4 btn-sm" id="btnGuardarProducto" onclick="guardarProducto()">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../categorias/modal_categoria.php';
include __DIR__ . '/../marcas/modal_marca.php';
?>