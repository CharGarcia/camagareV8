<?php
$vistaConfigInv = \App\Helpers\PreferenciasHelper::getPreferenciasVista('inventario');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigInv, 'estiloVistaPestanasInv');
?>
<!-- Modal para Nuevo Movimiento de Inventario -->
<div class="modal fade" id="modalAjuste" tabindex="-1" aria-labelledby="modalAjusteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalAjusteLabel">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>Registrar Movimiento
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="formAjuste">
                    <input type="hidden" name="id" id="ajuste_id_mov">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsInventario" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active py-2 small" id="tab-inv-general-btn" data-bs-toggle="tab" href="#pane-inv-general" role="tab">
                                    <i class="bi bi-box-seam me-1"></i> General
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-4 py-4" id="tabsInventarioContent">
                        <div class="tab-pane fade show active" id="pane-inv-general" role="tabpanel">
                            <div class="row g-3">
                                <!-- Producto -->
                                <div class="col-md-8 position-relative">
                                    <label class="form-label small fw-bold text-muted">Producto <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                        <input type="text" id="ajuste_busqueda_prod" class="form-control" placeholder="Buscar por código o nombre..." autocomplete="off" required>
                                        <input type="hidden" name="id_producto" id="ajuste_id_producto" required>
                                    </div>
                                    <div id="ajuste_resultados_prod" class="dropdown-predictivo border-0 shadow-sm p-0 d-none"></div>
                                </div>

                                <!-- Bodega -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Bodega <span class="text-danger">*</span></label>
                                    <select name="id_bodega" id="ajuste_id_bodega" class="form-select form-select-sm" required>
                                        <option value="">Seleccione bodega...</option>
                                        <?php foreach($bodegas as $b): ?>
                                            <option value="<?= $b['id'] ?>"><?= $b['nombre'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12"><hr class="my-1 opacity-25"></div>

                                <!-- Movimiento -->
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Tipo Movimiento <span class="text-danger">*</span></label>
                                    <select name="tipo_movimiento" id="ajuste_tipo_mov" class="form-select form-select-sm fw-bold" required>
                                        <option value="entrada">ENTRADA (+)</option>
                                        <option value="salida">SALIDA (-)</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Cantidad <span class="text-danger">*</span> 
                                        <span id="ajuste_stock_info" class="badge bg-secondary bg-opacity-10 text-secondary border d-none ms-1">Stock: 0</span>
                                    </label>
                                    <input type="number" name="cantidad" id="ajuste_cantidad" class="form-control form-control-sm text-end fw-bold" step="0.01" min="0.01" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">Costo Unitario</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" name="costo_unitario" id="ajuste_costo" class="form-control text-end" step="0.000001" min="0">
                                    </div>
                                </div>

                                <!-- Unidad de Medida -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Unidad de Medida <span class="text-danger">*</span></label>
                                    <select name="id_medida" id="ajuste_id_medida" class="form-select form-select-sm" required>
                                        <option value="">Seleccione medida...</option>
                                    </select>
                                </div>

                                <!-- Lote y Caducidad -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Número de Lote</label>
                                    <div id="ajuste_lote_container">
                                        <input type="text" name="numero_lote" id="ajuste_lote_input" class="form-control form-control-sm" placeholder="Ej: LOT-2024-001">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Fecha de Caducidad</label>
                                    <input type="date" name="fecha_caducidad" class="form-control form-control-sm">
                                </div>

                                <!-- Traceabilidad Unitaria -->
                                <div class="col-12">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="ajuste_check_individual" name="individual_check">
                                        <label class="form-check-label small fw-bold text-primary" for="ajuste_check_individual">
                                            <i class="bi bi-tag-fill me-1"></i> Registrar seriales individuales (NUP)
                                        </label>
                                    </div>
                                    <div id="ajuste_div_individual" class="d-none animate__animated animate__fadeIn">
                                        <label class="form-label small fw-bold text-muted">Ingrese seriales (uno por línea)</label>
                                        <textarea name="seriales" id="ajuste_seriales" class="form-control form-control-sm font-monospace" rows="4" placeholder="SERIAL001&#10;SERIAL002"></textarea>
                                        <small class="text-muted">Si ingresa seriales, el sistema creará un movimiento por cada uno.</small>
                                    </div>
                                </div>

                                <!-- Observaciones -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted">Observaciones / Motivo</label>
                                    <textarea name="observaciones" class="form-control form-control-sm" rows="2" placeholder="Describa el motivo del ajuste..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="ajuste_mensaje" class="alert mx-3 mb-3 py-2 small d-none shadow-sm border-0"></div>

                    <div class="modal-footer justify-content-between bg-light border-top p-2">
                        <div>
                            <?php if ($perm['eliminar']): ?>
                                <button type="button" id="ajuste_btn_eliminar" class="btn btn-outline-danger btn-sm px-3 d-none">
                                    <i class="bi bi-trash3 me-1"></i> Eliminar
                                </button>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                                <i class="fa-solid fa-xmark me-1"></i>Cerrar
                            </button>
                            <button type="submit" id="ajuste_btn_guardar" class="btn btn-primary btn-sm px-4">
                                <i class="bi bi-check2-circle me-1"></i> Confirmar Registro
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const form = document.getElementById('formAjuste');
        const inputBusqueda = document.getElementById('ajuste_busqueda_prod');
        const inputIdProd = document.getElementById('ajuste_id_producto');
        const resDiv = document.getElementById('ajuste_resultados_prod');
        const checkIndiv = document.getElementById('ajuste_check_individual');
        const divIndiv = document.getElementById('ajuste_div_individual');
        const resMsg = document.getElementById('ajuste_mensaje');
        const selectTipoMov = document.getElementById('ajuste_tipo_mov');
        const selectBodega = document.getElementById('ajuste_id_bodega');
        const selectMedida = document.getElementById('ajuste_id_medida');
        const inputLote = document.getElementById('ajuste_lote_input');
        const inputCaducidad = document.querySelector('input[name="fecha_caducidad"]');
        const loteContainer = document.getElementById('ajuste_lote_container');

        const btnGuardar = document.getElementById('ajuste_btn_guardar');
        const btnEliminar = document.getElementById('ajuste_btn_eliminar');
        let timerBusqueda;
        let modalObj = null;
        const spanStock = document.getElementById('ajuste_stock_info');
        const inputCant = document.getElementById('ajuste_cantidad');

        let stockActualGlobal = 0;
        let stockActualLote = null; // null significa que no hay lote seleccionado o no aplica
        let listaLotes = [];
        let originalId = null;
        let originalQty = 0;
        let originalTipo = '';

        const inputIdMov = document.getElementById('ajuste_id_mov');
        const modalLabel = document.getElementById('modalAjusteLabel');

        window.abrirModalAjuste = async function(id = null) {
            if (!modalObj) {
                const modalEl = document.getElementById('modalAjuste');
                if (modalEl) modalObj = new bootstrap.Modal(modalEl);
            }
            
            if (modalObj) {
                form.reset();
                inputIdMov.value = '';
                inputIdProd.value = '';
                resMsg.classList.add('d-none');
                divIndiv.classList.add('d-none');
                spanStock.classList.add('d-none');
                btnGuardar.disabled = false;
                stockActualGlobal = 0;
                restablecerCampoLote();
                if (btnEliminar) btnEliminar.classList.add('d-none');
                
                if (id) {
                    modalLabel.innerHTML = `<i class="bi bi-pencil-square me-2"></i>Editar Movimiento`;
                    btnGuardar.innerHTML = `<i class="bi bi-save me-1"></i>Guardar Cambios`;
                    try {
                        const resp = await fetch(`<?= BASE_URL ?>/modulos/inventario/getByIdAjax?id=${id}`);
                        const json = await resp.json();
                        if (json.ok) {
                            const d = json.data;
                            originalId = d.id;
                            originalQty = Math.abs(parseFloat(d.cantidad));
                            originalTipo = d.tipo_movimiento;

                            inputIdMov.value = d.id;
                            inputIdProd.value = d.id_producto;
                            inputBusqueda.value = `[${d.producto_codigo}] ${d.producto_nombre}`;
                            selectBodega.value = d.id_bodega;
                            selectTipoMov.value = d.tipo_movimiento;
                            inputCant.value = originalQty;
                            document.querySelector('input[name="costo_unitario"]').value = d.costo_unitario;
                            document.querySelector('textarea[name="observaciones"]').value = d.observaciones;
                            
                            if (d.id_medida) {
                                await cargarMedidasProducto(d.id_producto, d.id_medida);
                            }
                                
                            if (d.numero_lote) {
                                await verificarCargaDatos(); 
                                const selectLote = document.getElementById('ajuste_lote_select');
                                if (selectLote) {
                                    selectLote.value = d.numero_lote;
                                    seleccionarLote(d.numero_lote);
                                } else {
                                    const inputLoteText = document.getElementById('ajuste_lote_input');
                                    if (inputLoteText) inputLoteText.value = d.numero_lote;
                                }
                            }
                            if (d.fecha_caducidad) {
                                inputCaducidad.value = d.fecha_caducidad.split(' ')[0];
                            }
                            if (d.nup) {
                                checkIndiv.checked = true;
                                divIndiv.classList.remove('d-none');
                                document.getElementById('ajuste_seriales').value = d.nup;
                            }

                            if (btnEliminar) btnEliminar.classList.remove('d-none');
                        }
                    } catch (e) {
                        console.error('Error al cargar datos:', e);
                    }
                } else {
                    originalId = null;
                    originalQty = 0;
                    originalTipo = '';
                    modalLabel.innerHTML = `<i class="bi bi-plus-lg me-2"></i>Registrar Movimiento`;
                    btnGuardar.innerHTML = `<i class="bi bi-check-lg me-1"></i>Confirmar Registro`;
                }

                modalObj.show();
            } else {
                console.error('No se pudo inicializar el modal: elemento #modalAjuste no encontrado.');
            }
        };

        // Mantener compatibilidad con el botón "Nuevo"
        window.abrirModalNuevoAjuste = () => window.abrirModalAjuste();

        function restablecerCampoLote() {
            loteContainer.innerHTML = `<input type="text" name="numero_lote" id="ajuste_lote_input" class="form-control form-control-sm" placeholder="Ej: LOT-2024-001">`;
        }

        // Detectar cambios para cargar lotes y stock
        async function verificarCargaDatos() {
            const idProd = inputIdProd.value;
            const idBod = selectBodega.value;
            const tipo = selectTipoMov.value;

            if (idProd && idBod) {
                // Cargar Stock General
                try {
                    const respStock = await fetch(`<?= BASE_URL ?>/modulos/inventario/getStockAjax?id_producto=${idProd}&id_bodega=${idBod}`);
                    const jsonStock = await respStock.json();
                    if (jsonStock.ok) {
                        stockActualGlobal = parseFloat(jsonStock.stock);
                        actualizarBadgeStock();
                    }
                } catch (e) {
                    console.error('Error al cargar stock:', e);
                }

                // Cargar Lotes si es salida
                if (tipo === 'salida') {
                    try {
                        const respLotes = await fetch(`<?= BASE_URL ?>/modulos/inventario/getLotesAjax?id_producto=${idProd}&id_bodega=${idBod}`);
                        const jsonLotes = await respLotes.json();
                        if (jsonLotes.ok && jsonLotes.lotes.length > 0) {
                            listaLotes = jsonLotes.lotes;
                            renderSelectLotes(jsonLotes.lotes);
                        } else {
                            restablecerCampoLote();
                            listaLotes = [];
                        }
                    } catch (e) {
                        console.error('Error al cargar lotes:', e);
                    }
                } else {
                    restablecerCampoLote();
                }
            } else {
                spanStock.classList.add('d-none');
                restablecerCampoLote();
            }
        }

        function renderSelectLotes(lotes) {
            let html = `<select name="numero_lote" id="ajuste_lote_select" class="form-select form-select-sm" required onchange="seleccionarLote(this.value)">`;
            html += `<option value="">Seleccione un lote...</option>`;
            lotes.forEach(l => {
                html += `<option value="${l.numero_lote}">${l.numero_lote} (Disp: ${parseFloat(l.stock_lote)})</option>`;
            });
            html += `</select>`;
            loteContainer.innerHTML = html;
        }

        function actualizarBadgeStock() {
            if (!inputIdProd.value || !selectBodega.value) {
                spanStock.classList.add('d-none');
                return;
            }

            let displayGlobal = stockActualGlobal;
            let displayLote = stockActualLote;

            // Si estamos editando una SALIDA, el stock disponible para el usuario 
            // debe incluir la cantidad que ese movimiento ya está ocupando.
            if (originalId && originalTipo === 'salida' && originalId == inputIdMov.value) {
                displayGlobal += originalQty;
                if (displayLote !== null) displayLote += originalQty;
            }

            // El stock de referencia es el del lote si hay uno seleccionado, si no el global.
            let stockReferencia = (displayLote !== null) ? displayLote : displayGlobal;

            spanStock.textContent = `Disponible: ${stockReferencia}`;
            spanStock.classList.remove('d-none', 'bg-danger', 'text-danger', 'bg-success', 'text-success', 'bg-warning', 'text-warning');
            
            if (stockReferencia <= 0) {
                spanStock.classList.add('bg-danger', 'bg-opacity-10', 'text-danger');
            } else {
                spanStock.classList.add('bg-success', 'bg-opacity-10', 'text-success');
            }
        }

        window.seleccionarLote = function(numLote) {
            const lote = listaLotes.find(l => l.numero_lote === numLote);
            if (lote) {
                if (lote.fecha_caducidad) inputCaducidad.value = lote.fecha_caducidad.split(' ')[0];
                stockActualLote = parseFloat(lote.stock_lote);
            } else {
                inputCaducidad.value = '';
                stockActualLote = null;
            }
            actualizarBadgeStock();
            validarCantidad();
        };

        async function cargarMedidasProducto(idProd, idSeleccionada = null) {
            if (!idProd) return;
            try {
                const resp = await fetch(`<?= BASE_URL ?>/modulos/inventario/getMedidasProductoAjax?id_producto=${idProd}`);
                const json = await resp.json();
                if (json.ok) {
                    let html = '<option value="">Seleccione medida...</option>';
                    json.medidas.forEach(m => {
                        const selected = (idSeleccionada && m.id == idSeleccionada) || (!idSeleccionada && m.id == json.id_medida_base) ? 'selected' : '';
                        html += `<option value="${m.id}" ${selected}>${m.nombre} (${m.abreviatura})</option>`;
                    });
                    selectMedida.innerHTML = html;
                }
            } catch (e) {
                console.error('Error al cargar medidas:', e);
            }
        }

        // Validación de cantidad máxima
        function validarCantidad() {
            const cant = parseFloat(inputCant.value) || 0;
            const tipo = selectTipoMov.value;
            
            if (tipo === 'salida') {
                // Stock base para validación (global o lote si aplica)
                let base = (stockActualLote !== null) ? stockActualLote : stockActualGlobal;
                
                // Si estamos editando el MISMO movimiento de salida, sumamos su cantidad al disponible
                if (originalId && originalTipo === 'salida' && originalId == inputIdMov.value) {
                    base += originalQty;
                }

                if (cant > base) {
                    inputCant.classList.add('is-invalid');
                    btnGuardar.disabled = true;
                    return;
                }
            }
            
            inputCant.classList.remove('is-invalid');
            btnGuardar.disabled = false;
        }

        inputCant.addEventListener('input', validarCantidad);

        selectTipoMov.addEventListener('change', () => {
            verificarCargaDatos().then(validarCantidad);
        });
        selectBodega.addEventListener('change', () => {
            verificarCargaDatos().then(validarCantidad);
        });

        // Búsqueda de productos
        inputBusqueda.addEventListener('input', () => {
            const q = inputBusqueda.value.trim();
            resDiv.classList.add('d-none');
            resDiv.classList.remove('show');
            inputIdProd.value = '';
            
            if (q.length < 2) return;

            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(async () => {
                try {
                    const resp = await fetch('<?= BASE_URL ?>/modulos/productos/searchAjaxSimple?q=' + encodeURIComponent(q));
                    const json = await resp.json();
                    if (json.ok && json.data.length > 0) {
                        renderResultados(json.data);
                    }
                } catch (e) {
                    console.error('Error en búsqueda de productos:', e);
                }
            }, 400);
        });

        function renderResultados(items) {
            resDiv.innerHTML = items.map(item => {
                const nomEscaped = item.nombre.replace(/'/g, "\\'");
                const codEscaped = item.codigo.replace(/'/g, "\\'");
                return `
                    <div class="predictivo-item" onclick="seleccionarProducto(${item.id}, '${codEscaped}', '${nomEscaped}')">
                        <span class="item-codigo">[${item.codigo}]</span>
                        <span class="item-nombre">${item.nombre}</span>
                    </div>
                `;
            }).join('');
            resDiv.classList.remove('d-none');
            resDiv.classList.add('show');
        }

        window.seleccionarProducto = (id, cod, nom) => {
            inputIdProd.value = id;
            inputBusqueda.value = `[${cod}] ${nom}`;
            resDiv.classList.add('d-none');
            resDiv.classList.remove('show');
            cargarMedidasProducto(id);
            verificarCargaDatos();
        };

        // Toggle individual NUP
        checkIndiv.addEventListener('change', () => {
            const areaSeriales = document.getElementById('ajuste_seriales');
            if (checkIndiv.checked) {
                divIndiv.classList.remove('d-none');
                areaSeriales.required = true;
            } else {
                divIndiv.classList.add('d-none');
                areaSeriales.required = false;
            }
        });

        // Guardar Ajuste
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!inputIdProd.value) {
                alert('Debe seleccionar un producto válido');
                return;
            }

            btnGuardar.disabled = true;
            resMsg.classList.remove('d-none');
            resMsg.className = 'alert mt-3 py-2 small shadow-sm border-0 alert-info';
            resMsg.textContent = 'Procesando movimiento...';

            const fd = new FormData(form);
            try {
                const resp = await fetch('<?= BASE_URL ?>/modulos/inventario/ajusteAjax', {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();
                
                resMsg.className = `alert mt-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                resMsg.textContent = json.mensaje;

                if (json.ok) {
                    setTimeout(() => {
                        modalObj.hide();
                        if (typeof window.cargarListado === 'function') window.cargarListado();
                        else location.reload();
                    }, 1200);
                } else {
                    btnGuardar.disabled = false;
                }
            } catch (e) {
                btnGuardar.disabled = false;
                resMsg.className = 'alert mt-3 py-2 small shadow-sm border-0 alert-danger';
                resMsg.textContent = 'Error de conexión con el servidor.';
            }
        });

        if (btnEliminar) {
            btnEliminar.addEventListener('click', async () => {
                const id = inputIdMov.value;
                if (!id) return;
                
                if (typeof window.eliminarMovimiento === 'function') {
                    await window.eliminarMovimiento(id);
                    modalObj.hide();
                }
            });
        }

        // Cerrar dropdown si se hace clic fuera
        document.addEventListener('click', (e) => {
            if (!resDiv.contains(e.target) && e.target !== inputBusqueda) {
                resDiv.classList.add('d-none');
            }
        });
    })();
</script>
