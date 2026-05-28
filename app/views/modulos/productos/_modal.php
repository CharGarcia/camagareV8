<?php
$vistaConfigProdInline = \App\Helpers\PreferenciasHelper::getPreferenciasVista('productos');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigProdInline, 'estiloVistaPestanasProdInline');
?>
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form id="formProducto" novalidate onsubmit="return false;">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-box me-2 text-primary"></i> <span id="tituloModal">Nuevo Producto</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none m-3 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="prod_id">

                    <div class="d-flex align-items-center px-3 pt-2 bg-light">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" id="modalProductoTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-2 small fw-bold" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#pane-general" type="button">
                                <i class="bi bi-card-text me-1"></i> General
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 small fw-bold" id="tab-inventario-btn" data-bs-toggle="tab" data-bs-target="#pane-inventario" type="button">
                                <i class="bi bi-box-seam me-1"></i> Inventario
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 small fw-bold" id="tab-precios-btn" data-bs-toggle="tab" data-bs-target="#pane-precios" type="button">
                                <i class="bi bi-tags me-1"></i> Precios
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 small fw-bold" id="tab-clasificacion-btn" data-bs-toggle="tab" data-bs-target="#pane-clasificacion" type="button">
                                <i class="bi bi-diagram-3 me-1"></i> Clasificación
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 small fw-bold" id="tab-contable-btn" data-bs-toggle="tab" data-bs-target="#pane-contable" type="button">
                                <i class="bi bi-calculator me-1"></i> Contable
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-2 small fw-bold disabled" id="tab-info-btn" data-bs-toggle="tab" data-bs-target="#pane-info" type="button">
                                <i class="bi bi-info-circle me-1"></i> Información
                            </button>
                        </li>
                    </ul>
                    <div class="ms-auto pb-1">
                        <?php
                        $pestanasProdInline = [
                            'pane-inventario'   => 'Inventario',
                            'pane-precios'      => 'Precios',
                            'pane-clasificacion'=> 'Clasificación',
                            'pane-contable'     => 'Contable',
                            'pane-info'         => 'Información',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasProdInline, $vistaConfigProdInline, 'productos');
                        ?>
                    </div>
                    </div>

                    <div class="tab-content p-4">
                        <!-- TAB GENERAL -->
                        <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                            <!-- Grupo 1: Identificación y Estado -->
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Tipo Producción <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tipo_produccion', 'tipo_produccion') ?></label>
                                    <select name="tipo_produccion" id="prod_tipo_produccion" class="form-select form-select-sm shadow-none border-secondary-subtle" onchange="toggleBienesFields()">
                                        <option value="01">Producto / Bien</option>
                                        <option value="02">Servicio</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código Principal *</label>
                                    <input type="text" name="codigo" id="prod_codigo" class="form-control form-control-sm shadow-none border-secondary-subtle fw-bold" required maxlength="50" autocomplete="off" style="text-transform:uppercase;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Estado del Registro <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_status', 'status') ?></label>
                                    <select name="status" id="prod_status" class="form-select form-select-sm shadow-none border-secondary-subtle">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end bienes-field">
                                    <div class="form-check form-switch pb-2 ms-2">
                                        <input class="form-check-input mt-1" type="checkbox" role="switch" name="inventariable" id="prod_inventariable" value="1" checked>
                                        <label class="form-check-label small fw-bold text-muted ms-1" for="prod_inventariable">¿Es Inventariable?</label>
                                    </div>
                                </div>

                                <!-- Grupo 2: Identificadores y Medidas (Subido) -->
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código Auxiliar</label>
                                    <input type="text" name="codigo_auxiliar" id="prod_codigo_auxiliar" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="50" autocomplete="off" placeholder="(Opcional)">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Código de Barras</label>
                                    <input type="text" name="codigo_barras" id="prod_codigo_barras" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="50" autocomplete="off" placeholder="786...">
                                </div>
                                <div class="col-md-3 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Grupo de Medida <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tipo_medida', 'id_tipo_medida') ?></label>
                                    <select name="id_tipo_medida" id="prod_tipo_medida" class="form-select form-select-sm shadow-none border-secondary-subtle option-dynamic" onchange="actualizarUnidadesMedida()">
                                    </select>
                                </div>
                                <div class="col-md-3 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Unidad Específica</label>
                                    <select name="id_medida" id="prod_id_medida" class="form-select form-select-sm shadow-none border-secondary-subtle option-dynamic">
                                        <option value="">Seleccione medida</option>
                                    </select>
                                </div>

                                <!-- Grupo 3: Descripción Principal (Bajado y Separado) -->

                                <div class="col-md-12">
                                    <label class="form-label mb-1 small fw-bold text-muted text-primary">Descripción (Nombre comercial del producto o servicio) *</label>
                                    <input type="text" name="nombre" id="prod_nombre" class="form-control form-control-sm shadow-none border-secondary-subtle" required maxlength="200" autocomplete="off" placeholder="Ej: Jabón Antibacterial 500ml">
                                </div>


                                <!-- Grupo 4: Precios y Tributación -->

                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Precio Base (Venta)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle">$</span>
                                        <input type="number" name="precio_base" id="prod_precio_base" class="form-control shadow-none border-secondary-subtle fw-bold" step="0.0001" min="0" value="0.00" oninput="calcularPreciosTotales()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Tarifa IVA <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_tarifa_iva', 'tarifa_iva') ?></label>
                                    <select name="tarifa_iva" id="prod_tarifa_iva" class="form-select form-select-sm shadow-none border-secondary-subtle option-dynamic" onchange="calcularPreciosTotales()">
                                    </select>
                                </div>
                                <div class="col-md-2 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Valor ICE</label>
                                    <input type="number" name="valor_ice" id="prod_valor_ice" class="form-control form-control-sm shadow-none border-secondary-subtle" step="0.0001" min="0" value="0.00" oninput="calcularPreciosTotales()">
                                </div>
                                <div class="col-md-2 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Cód. ICE</label>
                                    <input type="text" name="codigo_ice" id="prod_codigo_ice" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="20" autocomplete="off" placeholder="(opc)">
                                </div>
                                <div class="col-md-2 bienes-field">
                                    <label class="form-label mb-1 small fw-bold text-muted">Nombre ICE</label>
                                    <input type="text" name="nombre_ice" id="prod_nombre_ice" class="form-control form-control-sm shadow-none border-secondary-subtle" maxlength="50" autocomplete="off" placeholder="(opc)">
                                </div>

                                <!-- Grupo 5: Resultados (Tamaño Estándar - �šltima Fila) -->
                                <div class="row g-2 mt-2 bg-light p-2 rounded border">
                                    <div class="col-md-6">
                                        <label class="form-label mb-1 small fw-bold text-success">IVA Calculado</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-success bg-opacity-10 text-success border-success border-opacity-25">$</span>
                                            <input type="text" id="display_iva" class="form-control shadow-none border-success border-opacity-25 fw-bold text-success" readonly value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label mb-1 small fw-bold text-primary">PVP (Editable)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-primary bg-opacity-10 text-primary border-primary border-opacity-25">$</span>
                                            <input type="number" id="prod_pvp_total" class="form-control shadow-none border-primary border-opacity-25 fw-bold text-primary" step="0.01" min="0" value="0.00" oninput="calcularPrecioInverso()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- TAB INVENTARIO -->
                        <div class="tab-pane fade" id="pane-inventario" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3 border-0 bg-info bg-opacity-10 text-info-emphasis">
                                <i class="bi bi-info-circle-fill me-1"></i> Asigne los saldos iniciales y los topes mínimos y máximos por cada bodega.
                            </div>

                            <div class="row g-2 mb-3 bg-light p-2 rounded border">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Bodega</label>
                                    <select id="inv_bodega" class="form-select form-select-sm shadow-none option-dynamic"></select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Stock Actual</label>
                                    <input type="number" id="inv_stock" step="any" class="form-control form-control-sm shadow-none text-end" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Mínimo</label>
                                    <input type="number" id="inv_min" step="any" class="form-control form-control-sm shadow-none text-end" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Máximo</label>
                                    <input type="number" id="inv_max" step="any" class="form-control form-control-sm shadow-none text-end" placeholder="0">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="agregarInventario()">
                                        <i class="bi bi-plus-lg"></i> Añadir
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered mb-0 small text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Bodega</th>
                                            <th>S. Actual</th>
                                            <th>S. Mínimo</th>
                                            <th>S. Máximo</th>
                                            <th style="width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-inventario">
                                        <tr>
                                            <td colspan="5" class="text-muted py-3">No hay bodegas vinculadas</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB PRECIOS -->
                        <div class="tab-pane fade" id="pane-precios" role="tabpanel">
                            <div class="alert alert-info py-2 small mb-3 border-0 bg-info bg-opacity-10 text-info-emphasis">
                                <i class="bi bi-info-circle-fill me-1"></i> Agregue listas de precios promocionales, por mayor o detalles de venta especiales.
                            </div>

                            <div class="row g-2 mb-3 bg-light p-2 rounded border">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Nombre Precio</label>
                                    <input type="text" id="pre_nombre" class="form-control form-control-sm shadow-none" placeholder="Ej: Promoción">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Precio</label>
                                    <input type="number" id="pre_precio" step="0.01" class="form-control form-control-sm shadow-none text-end" placeholder="0.00">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                                    <input type="date" id="pre_desde" class="form-control form-control-sm shadow-none">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                                    <input type="date" id="pre_hasta" class="form-control form-control-sm shadow-none">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary btn-sm w-100 shadow-sm" onclick="agregarPrecio()">
                                        <i class="bi bi-plus-lg"></i> Añadir
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered mb-0 small text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Nombre / Detalle</th>
                                            <th>Precio</th>
                                            <th>Válido Desde</th>
                                            <th>Válido Hasta</th>
                                            <th style="width:50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb-precios">
                                        <tr>
                                            <td colspan="5" class="text-muted py-3">No hay listados de precios extra</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB CLASIFICACI�“N -->
                        <div class="tab-pane fade" id="pane-clasificacion" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted d-flex align-items-center">Categoría General <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_id_categoria', 'id_categoria') ?></label>
                                    <select name="id_categoria" id="prod_id_categoria" class="form-select form-select-sm shadow-none option-dynamic">
                                        <option value="">Ninguna...</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted d-flex align-items-center">Marca Comercial <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('productos', 'prod_id_marca', 'id_marca') ?></label>
                                    <select name="id_marca" id="prod_id_marca" class="form-select form-select-sm shadow-none option-dynamic">
                                        <option value="">Ninguna...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB CONTABLE -->
                        <div class="tab-pane fade" id="pane-contable" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Cuenta Contable (Inventario / Bienes)</label>
                                    <select name="id_cuenta_inventario" id="prod_id_cuenta_inventario" class="form-select form-select-sm shadow-none option-dynamic">
                                        <option value="">Selecciona cuenta...</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Cuenta Contable (Costo / Gasto)</label>
                                    <select name="id_cuenta_costo_gasto" id="prod_id_cuenta_costo_gasto" class="form-select form-select-sm shadow-none option-dynamic">
                                        <option value="">Selecciona cuenta...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB INFO -->
                        <div class="tab-pane fade" id="pane-info" role="tabpanel">
                            <div class="bg-light rounded-3 p-3 border">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem">Fecha de creación</label>
                                        <span class="small fw-medium d-block" id="info_creado_at">�-</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem">Creado por</label>
                                        <span class="small fw-medium d-block" id="info_creado_por">�-</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem">�šltima modificación</label>
                                        <span class="small fw-medium d-block" id="info_actualizado_at">�-</span>
                                    </div>
                                    <div class="col-6">
                                        <label class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem">Modificado por</label>
                                        <span class="small fw-medium d-block" id="info_actualizado_por">�-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3">
                    <div class="w-100 d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($perm['eliminar']): ?>
                                <button type="button" id="btnEliminar" class="btn btn-outline-danger btn-sm px-3 d-none" onclick="eliminarProducto()">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-link text-decoration-none text-muted btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardar" onclick="guardarProducto()">
                                <i class="bi bi-check-lg"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';

        const urlBase = '<?= BASE_URL ?>/<?= $rutaModulo ?>';
        const form = document.getElementById('formProducto');
        const inputBuscar = document.getElementById('buscarProducto');
        let modalInst = null;
        let page = <?= $page ?>;
        let totalPages = <?= $totalPages ?>;
        let buscarTimer = null;
        let ordenCol = '<?= $ordenCol ?>';
        let ordenDir = '<?= $ordenDir ?>';

        let catalogosCargados = false;
        let datosCatalogos = {
            categorias: [],
            marcas: [],
            medidas: [],
            tipos_medida: [],
            unidades: [], // Cache de unidades por tipo si deseamos o solo vacio
            cuentas: [],
            tarifas_iva: [],
            bodegas: []
        };

        let inventariosObj = [];
        let preciosObj = [];

        function getModal() {
            if (!modalInst) {
                toggleBienesFields();
                calcularPreciosTotales();
                modalInst = new bootstrap.Modal(document.getElementById('modalProducto'));
            }
            return modalInst;
        }

        async function cargarCatalogos() {
            if (catalogosCargados) return;
            try {
                const resp = await fetch(`${urlBase}/catalogos`);
                const json = await resp.json();
                if (json.ok) {
                    datosCatalogos = json.data;
                    poblarSelects();
                    catalogosCargados = true;
                }
            } catch (e) {
                console.error('Error cargando catálogos:', e);
            }
        }

        function poblarSelects() {
            const buildOps = (arr, label, textFn) => `<option value="">${label}</option>` + arr.map(i => `<option value="${i.id}">${textFn(i)}</option>`).join('');

            document.getElementById('prod_id_categoria').innerHTML = buildOps(datosCatalogos.categorias, 'Ninguna...', i => i.nombre);
            document.getElementById('prod_id_marca').innerHTML = buildOps(datosCatalogos.marcas, 'Ninguna...', i => i.nombre);
            document.getElementById('prod_tipo_medida').innerHTML = buildOps(datosCatalogos.tipos_medida, 'Selecciona tipo', i => i.nombre);
            // No llamar actualizar aqui, se llamará al abrir modal o elegir tipo
            document.getElementById('prod_tarifa_iva').innerHTML = datosCatalogos.tarifas_iva.map(i => `<option value="${i.id}" data-porcentaje="${i.porcentaje}">${i.nombre}</option>`).join('');

            const ctaOps = buildOps(datosCatalogos.cuentas, 'Selecciona cuenta...', i => `[${i.codigo}] ${i.nombre}`);
            document.getElementById('prod_id_cuenta_inventario').innerHTML = ctaOps;
            document.getElementById('prod_id_cuenta_costo_gasto').innerHTML = ctaOps;

            document.getElementById('inv_bodega').innerHTML = buildOps(datosCatalogos.bodegas, 'Seleccione...', i => i.nombre);

            calcularPreciosTotales();
        }

        window.toggleBienesFields = function() {
            const val = document.getElementById('prod_tipo_produccion').value;
            const isBien = (val === '01');

            document.querySelectorAll('.bienes-field').forEach(el => {
                if (isBien) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });

            // Pestaña inventario (ocultar li contenedor)
            const tabBtn = document.getElementById('tab-inventario-btn');
            if (tabBtn) {
                const li = tabBtn.closest('.nav-item');
                if (isBien) {
                    li.classList.remove('d-none');
                } else {
                    li.classList.add('d-none');
                    // Si estaba en la pestaña de inventario y se oculta, moverse a general
                    if (tabBtn.classList.contains('active')) {
                        bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
                    }
                }
            }

            if (!isBien) {
                document.getElementById('prod_inventariable').checked = false;
                document.getElementById('prod_id_medida').value = '';
                document.getElementById('prod_tipo_medida').value = '';
                // Limpiar inventarios si no es bien (opcional, según reglas de negocio)
                inventariosObj = [];
                renderInventarios();
            }

            // Sugerir código si es un nuevo producto
            const id = document.getElementById('prod_id').value;
            if (!id) {
                sugerirCodigo();
            }
        };

        window.sugerirCodigo = async function() {
            const tipo = document.getElementById('prod_tipo_produccion').value;
            try {
                const resp = await fetch(`${urlBase}/getSiguienteCodigoAjax?tipo=${tipo}`);
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('prod_codigo').value = json.codigo;
                }
            } catch (e) {
                console.error('Error sugiriendo código:', e);
            }
        };

        window.calcularPreciosTotales = function() {
            const base = parseFloat(document.getElementById('prod_precio_base').value) || 0;
            const ice = parseFloat(document.getElementById('prod_valor_ice').value) || 0;

            const ivaSelect = document.getElementById('prod_tarifa_iva');
            const ivaOpt = ivaSelect.options[ivaSelect.selectedIndex];
            const ivaPorcentaje = ivaOpt ? parseFloat(ivaOpt.dataset.porcentaje || 0) : 0;

            const ivaCalculado = base * (ivaPorcentaje / 100);
            const total = base + ivaCalculado + ice;

            // Actualizar el resumen visual de totales
            const ivaInput = document.getElementById('display_iva');
            const pvpInput = document.getElementById('prod_pvp_total');

            if (ivaInput) ivaInput.value = ivaCalculado.toFixed(2);
            if (pvpInput) pvpInput.value = total.toFixed(2);

            // Si hay ICE, mostrar advertencia o marcar campos requeridos si se desea
            if (ice > 0) {
                document.getElementById('prod_codigo_ice').setAttribute('required', 'required');
                document.getElementById('prod_nombre_ice').setAttribute('required', 'required');
            } else {
                document.getElementById('prod_codigo_ice').removeAttribute('required');
                document.getElementById('prod_nombre_ice').removeAttribute('required');
            }
        };

        window.calcularPrecioInverso = function() {
            const pvp = parseFloat(document.getElementById('prod_pvp_total').value) || 0;
            const ice = parseFloat(document.getElementById('prod_valor_ice').value) || 0;

            const ivaSelect = document.getElementById('prod_tarifa_iva');
            const ivaOpt = ivaSelect.options[ivaSelect.selectedIndex];
            const ivaPorcentaje = ivaOpt ? parseFloat(ivaOpt.dataset.porcentaje || 0) : 0;

            // Base = (PVP - ICE) / (1 + IVA%)
            const baseImponible = (pvp - ice) / (1 + (ivaPorcentaje / 100));
            const baseRefinada = baseImponible > 0 ? baseImponible : 0;

            document.getElementById('prod_precio_base').value = baseRefinada.toFixed(4);

            // Recalcular IVA para el display (reutilizando la lógica normal)
            const ivaCalculado = baseRefinada * (ivaPorcentaje / 100);
            const ivaInput = document.getElementById('display_iva');
            if (ivaInput) ivaInput.value = ivaCalculado.toFixed(2);
        };

        window.actualizarUnidadesMedida = async function(idUnidadPrevia = null) {
            const idTipo = document.getElementById('prod_tipo_medida').value;
            const medS = document.getElementById('prod_id_medida');

            medS.innerHTML = `<option value="">Cargando...</option>`;
            medS.disabled = true;

            if (!idTipo) {
                medS.innerHTML = `<option value="">Unidad base...</option>`;
                medS.disabled = false;
                return;
            }

            try {
                const resp = await fetch(`${urlBase}/getUnidadesPorTipoAjax?id_tipo=${idTipo}`);
                const json = await resp.json();
                if (json.ok) {
                    medS.innerHTML = `<option value="">Unidad base...</option>` +
                        json.data.map(i => `<option value="${i.id}">${i.nombre} (${i.abreviatura})</option>`).join('');
                    if (idUnidadPrevia) medS.value = idUnidadPrevia;
                } else {
                    medS.innerHTML = `<option value="">Error al cargar</option>`;
                }
            } catch (e) {
                console.error(e);
                medS.innerHTML = `<option value="">Error...</option>`;
            } finally {
                medS.disabled = false;
            }
        };

        /* ---------------------------------
           L�“GICA INVENTARIOS Y PRECIOS 
        ---------------------------------- */
        function renderInventarios() {
            const tb = document.getElementById('tb-inventario');
            if (inventariosObj.length === 0) {
                tb.innerHTML = '<tr><td colspan="5" class="text-muted py-3">No hay bodegas vinculadas</td></tr>';
                return;
            }
            tb.innerHTML = inventariosObj.map((inv, idx) => {
                const b = datosCatalogos.bodegas.find(x => x.id == inv.id_bodega);
                const name = b ? b.nombre : (inv.nombre_bodega || 'Desconocida');
                return `<tr>
                    <td class="text-start">${name}</td>
                    <td class="fw-bold">${parseFloat(inv.stock_actual).toFixed(2)}</td>
                    <td>${parseFloat(inv.stock_minimo).toFixed(2)}</td>
                    <td>${parseFloat(inv.stock_maximo).toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm text-danger p-0 m-0 shadow-none border-0" onclick="quitarInventario(${idx})">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        window.agregarInventario = function() {
            const id_bodega = document.getElementById('inv_bodega').value;
            const stock_actual = document.getElementById('inv_stock').value || 0;
            const stock_minimo = document.getElementById('inv_min').value || 0;
            const stock_maximo = document.getElementById('inv_max').value || 0;

            if (!id_bodega) return alert('Seleccione una bodega.');
            if (inventariosObj.some(i => i.id_bodega == id_bodega)) return alert('Esta bodega ya está en la lista.');

            inventariosObj.push({
                id_bodega,
                stock_actual,
                stock_minimo,
                stock_maximo
            });

            document.getElementById('inv_bodega').value = '';
            document.getElementById('inv_stock').value = '';
            document.getElementById('inv_min').value = '';
            document.getElementById('inv_max').value = '';
            renderInventarios();
        }

        window.quitarInventario = function(idx) {
            inventariosObj.splice(idx, 1);
            renderInventarios();
        }

        function renderPrecios() {
            const tb = document.getElementById('tb-precios');
            if (preciosObj.length === 0) {
                tb.innerHTML = '<tr><td colspan="5" class="text-muted py-3">No hay listados de precios extra</td></tr>';
                return;
            }
            tb.innerHTML = preciosObj.map((pre, idx) => {
                return `<tr>
                    <td class="text-start">${pre.nombre_precio}</td>
                    <td class="fw-bold text-success">$${parseFloat(pre.precio).toFixed(2)}</td>
                    <td>${pre.valido_desde || '�-'}</td>
                    <td>${pre.valido_hasta || '�-'}</td>
                    <td>
                        <button type="button" class="btn btn-sm text-danger p-0 m-0 shadow-none border-0" onclick="quitarPrecio(${idx})">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        window.agregarPrecio = function() {
            const nombre_precio = document.getElementById('pre_nombre').value.trim();
            const precio = document.getElementById('pre_precio').value || 0;
            const valido_desde = document.getElementById('pre_desde').value;
            const valido_hasta = document.getElementById('pre_hasta').value;

            if (!nombre_precio || precio <= 0) return alert('Nombre y precio válido son obligatorios.');

            preciosObj.push({
                nombre_precio,
                precio,
                valido_desde,
                valido_hasta
            });

            document.getElementById('pre_nombre').value = '';
            document.getElementById('pre_precio').value = '';
            document.getElementById('pre_desde').value = '';
            document.getElementById('pre_hasta').value = '';
            document.getElementById('pre_nombre').focus();
            renderPrecios();
        }

        window.quitarPrecio = function(idx) {
            preciosObj.splice(idx, 1);
            renderPrecios();
        }

        /* ---------------------------------------------------- */

        window.cambiarPaginaAjax = function(p) {
            if (p < 1 || p > totalPages) return;
            page = p;
            cargarListado();
        };

        window.limpiarBuscar = function() {
            inputBuscar.value = '';
            page = 1;
            cargarListado();
        };

        if (inputBuscar) {
            inputBuscar.addEventListener('input', () => {
                clearTimeout(buscarTimer);
                buscarTimer = setTimeout(() => {
                    page = 1;
                    cargarListado();
                }, 500);
            });
        }

        document.querySelectorAll('.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const newSort = th.dataset.sort;
                if (!newSort) return;
                if (ordenCol === newSort) {
                    ordenDir = (ordenDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
                } else {
                    ordenCol = newSort;
                    ordenDir = 'ASC';
                }
                
                if (typeof window.guardarOrdenacionVista === 'function') {
                    window.guardarOrdenacionVista('productos', ordenCol, ordenDir);
                }

                page = 1;
                cargarListado();
            });
        });

        async function cargarListado() {
            const b = inputBuscar ? inputBuscar.value.trim() : '';
            const url = `${urlBase}/searchAjax?page=${page}&b=${encodeURIComponent(b)}&sort=${ordenCol}&dir=${ordenDir}`;

            try {
                const resp = await fetch(url);
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('tbodyProductos').innerHTML = json.rows;
                    document.getElementById('paginationInfo').textContent = json.info;
                    document.getElementById('paginationContainer').innerHTML = json.pagination;
                    
                    if (json.pdf_url) document.getElementById('btnExportPdf').href = json.pdf_url;
                    if (json.excel_url) document.getElementById('btnExportExcel').href = json.excel_url;
                    
                    if (json.total !== undefined) {
                        totalPages = Math.ceil(json.total / 20) || 1;
                    }

                    // Actualizar iconos de ordenamiento
                    document.querySelectorAll('.sortable-header').forEach(th => {
                        const icon = th.querySelector('i');
                        if (!icon) return;
                        const field = th.dataset.sort;
                        if (field === ordenCol) {
                            icon.className = (ordenDir.toLowerCase() === 'asc') ?
                                'bi bi-sort-alpha-down text-primary ms-1' :
                                'bi bi-sort-alpha-up text-primary ms-1';
                        } else {
                            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                        }
                    });
                }
            } catch (e) {
                console.error('Error cargando listado:', e);
            }
        }

        window.abrirModalProductoCrear = async function() {
            if (!catalogosCargados) await cargarCatalogos();

            form.reset();
            document.getElementById('prod_id').value = '';
            document.getElementById('tituloModal').textContent = 'Nuevo Producto';
            document.getElementById('btnEliminar')?.classList.add('d-none');

            document.getElementById('prod_precio_base').value = '0.00';
            document.getElementById('prod_valor_ice').value = '0.00';
            document.getElementById('prod_codigo_ice').value = '';
            document.getElementById('prod_nombre_ice').value = '';

            document.getElementById('prod_inventariable').checked = true;
            document.getElementById('prod_tipo_produccion').value = '01';
            toggleBienesFields();
            calcularPreciosTotales();

            if (typeof aplicarFavoritosModal === 'function') {
                aplicarFavoritosModal();
            }

            inventariosObj = [];
            preciosObj = [];
            renderInventarios();
            renderPrecios();

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            document.getElementById('tab-info-btn').classList.add('disabled');

            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            getModal().show();
        };

        window.abrirModalProductoEditar = async function(row) {
            if (!catalogosCargados) await cargarCatalogos();

            const data = JSON.parse(row.dataset.row);
            form.reset();
            document.getElementById('prod_id').value = data.id;
            document.getElementById('prod_codigo').value = data.codigo || '';
            document.getElementById('prod_nombre').value = data.nombre || '';
            document.getElementById('prod_codigo_auxiliar').value = data.codigo_auxiliar || '';
            document.getElementById('prod_codigo_barras').value = data.codigo_barras || '';
            document.getElementById('prod_precio_base').value = data.precio_base ? parseFloat(data.precio_base).toFixed(4) : '0.00';
            document.getElementById('prod_valor_ice').value = data.valor_ice ? parseFloat(data.valor_ice).toFixed(4) : '0.00';
            document.getElementById('prod_codigo_ice').value = data.codigo_ice || '';
            document.getElementById('prod_nombre_ice').value = data.nombre_ice || '';

            document.getElementById('prod_tipo_produccion').value = data.tipo_produccion || '01';

            const idT = data.id_tipo_medida || '';
            document.getElementById('prod_tipo_medida').value = idT;
            if (idT) {
                await actualizarUnidadesMedida(data.id_medida);
            } else {
                document.getElementById('prod_id_medida').innerHTML = '<option value="">Unidad base...</option>';
            }

            toggleBienesFields();

            document.getElementById('prod_tarifa_iva').value = data.tarifa_iva || 2;
            document.getElementById('prod_id_categoria').value = data.id_categoria || '';
            document.getElementById('prod_id_marca').value = data.id_marca || '';

            document.getElementById('prod_id_cuenta_inventario').value = data.id_cuenta_inventario || '';
            document.getElementById('prod_id_cuenta_costo_gasto').value = data.id_cuenta_costo_gasto || '';

            document.getElementById('prod_inventariable').checked = (data.inventariable === true || data.inventariable === 't' || data.inventariable === 1 || data.inventariable === '1');

            const isInactive = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
            document.getElementById('prod_status').value = isInactive ? '0' : '1';

            document.getElementById('tituloModal').textContent = 'Editar Producto';
            document.getElementById('btnEliminar')?.classList.remove('d-none');

            inventariosObj = [];
            preciosObj = [];
            renderInventarios();
            renderPrecios();

            calcularPreciosTotales();

            if (typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getInstance(document.getElementById('tab-general-btn'))?.show() || new bootstrap.Tab(document.getElementById('tab-general-btn')).show();
            }
            document.getElementById('tab-info-btn').classList.remove('disabled');

            const mo = document.getElementById('modalAlert');
            if (mo) mo.classList.add('d-none');

            fetchInformacionExtra(data.id);
            getModal().show();
        };

        async function fetchInformacionExtra(id) {
            document.getElementById('info_creado_at').textContent = '�-';
            try {
                const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
                const json = await resp.json();
                if (json.ok) {
                    const d = json.data;
                    document.getElementById('info_creado_at').textContent = d.creado_at || '�-';
                    document.getElementById('info_creado_por').textContent = d.creado_por || '�-';
                    document.getElementById('info_actualizado_at').textContent = d.actualizado_at || '�-';
                    document.getElementById('info_actualizado_por').textContent = d.actualizado_por || '�-';

                    if (d.inventarios) {
                        inventariosObj = d.inventarios;
                        renderInventarios();
                    }
                    if (d.precios) {
                        preciosObj = d.precios;
                        renderPrecios();
                    }
                }
            } catch (e) {}
        }

        window.guardarProducto = async function() {
            const form = document.getElementById('formProducto');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const id = document.getElementById('prod_id').value;
            const action = id ? '/update' : '/store';
            const fd = new FormData(form);

            fd.append('inventarios', JSON.stringify(inventariosObj));
            fd.append('precios', JSON.stringify(preciosObj));

            const btn = document.getElementById('btnGuardar');
            const alertEl = document.getElementById('modalAlert');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            if (alertEl) alertEl.classList.add('d-none');

            try {
                const resp = await fetch(`${urlBase}${action}`, {
                    method: 'POST',
                    body: fd
                });

                const text = await resp.text();
                let json;
                try {
                    json = JSON.parse(text);
                } catch (parseError) {
                    throw new Error('La respuesta del servidor no es un JSON válido');
                }

                if (alertEl) {
                    alertEl.textContent = json.msg || json.error || 'Error desconocido';
                    alertEl.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                    alertEl.classList.remove('d-none');
                }

                if (json.ok) {
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                        getModal().hide();
                        cargarListado();
                    }, 800);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            } catch (err) {
                if (alertEl) {
                    alertEl.textContent = 'Error de red al conectar con el servidor';
                    alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                    alertEl.classList.remove('d-none');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            }
        };

        window.eliminarProducto = async function() {
            const id = document.getElementById('prod_id').value;
            if (!id || !confirm('¿Está seguro de eliminar este producto?')) return;

            const fd = new FormData();
            fd.append('id_eliminar', id);

            try {
                const resp = await fetch(urlBase + '/delete', {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();
                if (json.ok) {
                    getModal().hide();
                    cargarListado();
                } else {
                    alert(json.error);
                }
            } catch (e) {
                alert('Error al eliminar producto');
            }
        };

    })();
</script>