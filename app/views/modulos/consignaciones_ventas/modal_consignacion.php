<?php
/** @var array $perm */
/** @var string $rutaModulo */
$moduloBase = basename($rutaModulo ?? 'consignaciones-ventas');
$vistaConfigConsignaciones = \App\Helpers\PreferenciasHelper::getPreferenciasVista($moduloBase);
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigConsignaciones, 'estiloVistaPestanasConsignaciones');
?>
<div class="modal fade" id="modalConsignacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formConsignacion" onsubmit="event.preventDefault(); guardarConsignacion();" novalidate>
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam text-primary"></i>
                        <span id="tituloModalConsignacion">Nueva Consignación</span>
                        <span id="cons_estado_badge" class="badge bg-primary bg-opacity-10 text-primary ms-2 d-none" style="font-size: 0.8rem;">Emitida</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <input type="hidden" id="cons_id" name="id" value="">
                    
                    <!-- Pestañas -->
                    <style>
                        #tabsCons .nav-link {
                            padding: 6px 9px;
                            font-size: 0.8rem;
                            white-space: nowrap;
                        }
                        #tabsCons .nav-link i {
                            font-size: 0.85rem;
                        }
                        #consTablaDetalles th {
                            font-size: 0.7rem !important;
                            padding: 4px 8px !important;
                        }
                        #consTablaDetalles td {
                            padding: 0 !important;
                            vertical-align: middle;
                        }
                        .input-detalle {
                            border: none;
                            background: transparent;
                            font-size: 0.82rem !important;
                            padding: 2px 8px !important;
                            height: 30px !important;
                            border-radius: 0;
                        }
                        .input-detalle:focus {
                            background: #fff;
                            box-shadow: inset 0 0 0 1px #0d6efd;
                            outline: none;
                        }
                        .row-detalle-cons:hover {
                            background-color: rgba(13, 110, 253, 0.03);
                        }
                    </style>
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsCons" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" id="cons-tab-general-btn" data-bs-toggle="tab" href="#cons-tab-general" role="tab" title="General"><i class="bi bi-info-circle me-1"></i> General</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="cons-tab-retornos-btn" data-bs-toggle="tab" href="#cons-tab-retornos" role="tab" title="Retornos"><i class="bi bi-arrow-return-left me-1"></i> Retornos</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="cons-tab-facturacion-btn" data-bs-toggle="tab" href="#cons-tab-facturacion" role="tab" title="Facturación"><i class="bi bi-receipt me-1"></i> Facturación</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="cons-tab-guias-btn" data-bs-toggle="tab" href="#cons-tab-guias" role="tab" title="Guías Remisión"><i class="bi bi-truck me-1"></i> Guías Remisión</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="cons-tab-pedidos-btn" data-bs-toggle="tab" href="#cons-tab-pedidos" role="tab" title="Pedidos"><i class="bi bi-cart3 me-1"></i> Pedidos</a>
                            </li>
                        </ul>
                        <div class="pb-1 flex-shrink-0">
                            <?php
                            $pestanasConfigCons = [
                                'cons-tab-retornos'    => 'Retornos',
                                'cons-tab-facturacion' => 'Facturación',
                                'cons-tab-guias'       => 'Guías Remisión',
                                'cons-tab-pedidos'     => 'Pedidos'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigCons, $vistaConfigConsignaciones ?? [], $moduloBase);
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>
                    
                    <div class="tab-content border-top px-3 py-3" id="tabsConsContent" style="overflow: visible !important;">
                        <div class="tab-pane fade show active" id="cons-tab-general" role="tabpanel">
                            
                            <!-- Botones de Acción Rápida -->
                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn btn-sm btn-outline-info px-2" onclick="llamarPedido()" title="Cargar desde Pedido">
                                    <i class="bi bi-cart"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2" onclick="crearVendedorRapido()" title="Nuevo Vendedor">
                                    <i class="bi bi-person-plus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success px-2 py-1" onclick="abrirModalResponsableCrear()" title="Crear responsable de traslado rápido">
                                    <i class="bi bi-truck"></i>
                                </button>

                                <div class="vr mx-1"></div>

                                <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1" onclick="pdfConsignacion()" title="Generar PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1" onclick="emailConsignacion()" title="Enviar por Correo">
                                    <i class="bi bi-envelope"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success px-2 py-1" onclick="whatsappConsignacion()" title="Enviar por WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </button>
                            </div>
                            <hr class="text-muted my-3 opacity-25">

                            <div class="row g-3">
                                <!-- Fila 1: 2 + 2 + 2 + 3 + 3 = 12 -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Fecha Emisión *</label>
                                    <input type="date" class="form-control form-control-sm" id="cons_fecha_emision" name="fecha_emision" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="cons_id_punto_emision" class="form-label small fw-bold">Serie <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'cons_id_punto_emision', 'id_punto_emision') ?></label>
                                    <select class="form-select form-select-sm border-primary border-opacity-25" name="id_punto_emision" id="cons_id_punto_emision" onchange="syncSerieConsignacion(this.value)" required>
                                        <option value="">Seleccione...</option>
                                    </select>
                                    <input type="hidden" name="id_establecimiento" id="cons_id_establecimiento">
                                    <input type="hidden" name="establecimiento" id="cons_establecimiento">
                                    <input type="hidden" name="punto_emision" id="cons_punto_emision">
                                    <input type="hidden" name="serie" id="cons_serie_hidden">
                                </div>
                                <div class="col-md-2">
                                    <label for="cons_secuencial" class="form-label small fw-bold">Secuencial <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm text-end bg-light" id="cons_secuencial" name="secuencial" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Bodega * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'cons_id_bodega', 'id_bodega') ?></label>
                                    <select class="form-select form-select-sm" id="cons_id_bodega" name="id_bodega" required>
                                        <option value="">Seleccione...</option>
                                        <?php if(isset($bodegas)): ?>
                                            <?php foreach ($bodegas as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= !empty($b['es_default']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Asesor <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'cons_id_vendedor', 'id_vendedor') ?></label>
                                    <select class="form-select form-select-sm" id="cons_id_vendedor" name="id_vendedor" onchange="document.getElementById('cons_cliente_busqueda').focus()">
                                        <option value="">Seleccione...</option>
                                        <?php if(isset($vendedores)): ?>
                                            <?php foreach ($vendedores as $v): ?>
                                                <option value="<?= $v['id'] ?>">
                                                    <?= htmlspecialchars($v['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Fila 2: 12 -->
                                <div class="col-md-12 position-relative">
                                    <div class="p-2 border rounded-3 bg-light bg-opacity-10">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-8 position-relative">
                                                <div class="input-group input-group-sm rounded-pill overflow-hidden border bg-white pe-2 align-items-center">
                                                    <span class="input-group-text bg-white border-0 text-primary"><i class="bi bi-search"></i></span>
                                                    <input type="text" class="form-control border-0 px-1 shadow-none" id="cons_cliente_busqueda" placeholder="Buscar cliente por nombre o identificación..." autocomplete="off" onkeydown="manejarTecladoClienteCons(event)" oninput="buscarClienteCons(this)" onblur="setTimeout(() => document.getElementById('cons_cliente_results').classList.add('d-none'), 200)" required>
                                                    <button class="btn btn-link text-muted p-0" type="button" onclick="limpiarCliente()"><i class="bi bi-x-circle-fill"></i></button>
                                                </div>
                                                <div id="cons_cliente_results" class="list-group shadow position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; top: calc(100% + 5px);"></div>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-center gap-1">
                                                <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'cons_id_responsable_traslado', 'id_responsable_traslado') ?>
                                                <select class="form-select form-select-sm rounded-pill border-0 shadow-sm" id="cons_id_responsable_traslado" name="id_responsable_traslado">
                                                    <option value="">Responsable Traslado...</option>
                                                    <?php if(isset($responsables)): ?>
                                                        <?php foreach ($responsables as $t): ?>
                                                            <option value="<?= $t['id'] ?>">
                                                                <?= htmlspecialchars($t['nombre']) ?> <?= $t['identificacion'] ? '('.htmlspecialchars($t['identificacion']).')' : '' ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="id_cliente" id="cons_id_cliente">
                                </div>

                                <!-- Fila 3: 6 + 6 = 12 -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Punto Partida</label>
                                    <input type="text" class="form-control form-control-sm" id="cons_punto_partida" name="punto_partida">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Punto Llegada</label>
                                    <input type="text" class="form-control form-control-sm" id="cons_punto_llegada" name="punto_llegada">
                                </div>

                                <!-- Fila 4: 2 + 2 + 2 + 6 = 12 -->
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Fecha Entrega</label>
                                    <input type="date" class="form-control form-control-sm" id="cons_fecha_entrega" name="fecha_entrega">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Hora Desde</label>
                                    <input type="time" class="form-control form-control-sm" id="cons_hora_entrega_desde" name="hora_entrega_desde" onchange="validarHoraEntrega()">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Hora Hasta</label>
                                    <input type="time" class="form-control form-control-sm" id="cons_hora_entrega_hasta" name="hora_entrega_hasta" onchange="validarHoraEntrega()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Observaciones</label>
                                    <input type="text" class="form-control form-control-sm" id="cons_observaciones" name="observaciones">
                                </div>
                            </div>

                            <hr>

                            <!-- Detalle Productos -->
                            <div class="p-0 mt-3 border rounded-3 overflow-hidden bg-white shadow-sm">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap" id="consTablaDetalles">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted">Producto</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:120px;">Precios</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:120px;">Precio</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:100px;">Cantidad</th>
                                                <th class="py-2 small fw-bold text-muted d-none th-lote" style="width:120px;">Lote</th>
                                                <th class="py-2 small fw-bold text-muted d-none th-caducidad" style="width:120px;">Caducidad</th>
                                                <th class="py-2 small fw-bold text-muted d-none th-nup" style="width:120px;">NUP</th>
                                                <th class="py-2 small fw-bold text-muted text-end d-none" style="width:120px;">Subtotal</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cons_detalles_body">
                                            <!-- Filas dinámicas -->
                                        </tbody>
                                    </table>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFilaConsignacion()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Items: <span id="cons_count_items">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña Retornos -->
                        <div class="tab-pane fade" id="cons-tab-retornos" role="tabpanel">
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-arrow-return-left fs-1 text-secondary mb-3 opacity-50"></i>
                                <h5>Módulo de Retornos</h5>
                                <p>El historial y registro de productos devueltos o mermas aparecerá aquí.</p>
                            </div>
                        </div>

                        <!-- Pestaña Facturación -->
                        <div class="tab-pane fade" id="cons-tab-facturacion" role="tabpanel">
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-receipt fs-1 text-secondary mb-3 opacity-50"></i>
                                <h5>Historial de Facturación</h5>
                                <p>Las facturas emitidas a partir de los cortes de esta consignación se listarán aquí.</p>
                            </div>
                        </div>

                        <!-- Pestaña Guías Remisión -->
                        <div class="tab-pane fade" id="cons-tab-guias" role="tabpanel">
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-truck fs-1 text-secondary mb-3 opacity-50"></i>
                                <h5>Guías de Remisión</h5>
                                <p>Historial de guías de remisión electrónicas emitidas por los traslados de esta consignación.</p>
                            </div>
                        </div>

                        <!-- Pestaña Pedidos -->
                        <div class="tab-pane fade" id="cons-tab-pedidos" role="tabpanel">
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-cart3 fs-1 text-secondary mb-3 opacity-50"></i>
                                <h5>Pedidos Asociados</h5>
                                <p>Detalle de los pedidos que fueron generados y despachados desde esta consignación.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarConsignacion" onclick="eliminarConsignacionBackend()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i> Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarConsignacion"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    if (typeof window.RUTA_MODULO_CONSIGNACION === 'undefined') {
        window.RUTA_MODULO_CONSIGNACION = '<?= url($rutaModulo ?? "modulos/consignaciones_ventas") ?>';
    }
    let CONS_BLOQUEAR_SECUENCIAL = false;

    // Clave de borrador local por empresa + usuario (igual que en Facturas de Venta).
    const CONS_STORAGE_KEY = 'cons_borrador_<?= (int)($_SESSION['id_empresa'] ?? 0) ?>_<?= (int)($_SESSION['id_usuario'] ?? 0) ?>';

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function abrirModalConsignacionNueva() {
        // ── Borrador local: si hay una consignación sin guardar, avisar antes de abrir ──
        if (!window._CONS_SKIP_BORRADOR) {
            let borrador = null;
            try {
                const raw = localStorage.getItem(CONS_STORAGE_KEY);
                if (raw) borrador = JSON.parse(raw);
            } catch (e) {}
            if (borrador && (borrador.id_cliente || (borrador.detalles && borrador.detalles.length))) {
                consMostrarAvisoBorrador(borrador);
                return;
            }
        }
        window._CONS_SKIP_BORRADOR = false;

        CONS_BLOQUEAR_SECUENCIAL = false;
        document.getElementById('formConsignacion').reset();
        document.getElementById('cons_id').value = '';
        document.getElementById('cons_id_cliente').value = '';
        document.getElementById('tituloModalConsignacion').textContent = 'Nueva Consignación en Ventas';
        document.getElementById('cons_estado_badge').textContent = 'Nueva';
        document.getElementById('cons_estado_badge').className = 'badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none';
        
        const btnGuardar = document.getElementById('btnGuardarConsignacion');
        btnGuardar.classList.remove('d-none');
        btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i> Guardar';
        btnGuardar.disabled = false;
        
        document.getElementById('btnEliminarConsignacion').classList.add('d-none');
        
        document.getElementById('cons_detalles_body').innerHTML = '';
        agregarFilaConsignacion(); // Empieza con una línea vacía
        
        // Asignar fechas y horas actuales
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        const hoy = `${yyyy}-${mm}-${dd}`;
        
        document.getElementById('cons_fecha_emision').value = hoy;
        document.getElementById('cons_fecha_entrega').value = hoy;

        const padZero = num => num.toString().padStart(2, '0');
        document.getElementById('cons_hora_entrega_desde').value = padZero(now.getHours()) + ':' + padZero(now.getMinutes());

        now.setHours(now.getHours() + 1);
        document.getElementById('cons_hora_entrega_hasta').value = padZero(now.getHours()) + ':' + padZero(now.getMinutes());

        cargarCatalogosConsignacion();
        
        if (typeof EMPRESA_CONFIG !== 'undefined') {
            if (EMPRESA_CONFIG.obligatorio_lotes) {
                document.querySelectorAll('.th-lote').forEach(e => e.classList.remove('d-none'));
            }
            if (EMPRESA_CONFIG.obligatorio_caducidad) {
                document.querySelectorAll('.th-caducidad').forEach(e => e.classList.remove('d-none'));
            }
            if (EMPRESA_CONFIG.obligatorio_nup) {
                document.querySelectorAll('.th-nup').forEach(e => e.classList.remove('d-none'));
            }
        }
        
        const modalEl = document.getElementById('modalConsignacion');
        const modal = new bootstrap.Modal(modalEl);
        
        modalEl.addEventListener('shown.bs.modal', function onModalShown() {
            if (window._CONS_BORRADOR_PENDIENTE) {
                const b = window._CONS_BORRADOR_PENDIENTE;
                window._CONS_BORRADOR_PENDIENTE = null;
                consRestaurar(b);
            } else {
                document.getElementById('cons_cliente_busqueda').focus();
            }
            modalEl.removeEventListener('shown.bs.modal', onModalShown);
        });
        
        modal.show();
    }

    async function abrirModalConsignacionVer(el) {
        const row = JSON.parse(el.getAttribute('data-row'));
        CONS_BLOQUEAR_SECUENCIAL = true;
        document.getElementById('formConsignacion').reset();
        document.getElementById('cons_id').value = row.id;
        
        document.getElementById('tituloModalConsignacion').textContent = 'Consignación en Ventas: ' + row.serie + '-' + row.secuencial;
        document.getElementById('cons_estado_badge').classList.add('d-none');
        
        if (row.estado === 'Emitida' || row.estado === 'Nueva') {
            document.getElementById('btnEliminarConsignacion').classList.remove('d-none');
        } else {
            document.getElementById('btnEliminarConsignacion').classList.add('d-none');
        }

        // Cargar campos básicos
        if(row.fecha_emision) {
            // Convertir dd-mm-yyyy a yyyy-mm-dd
            const partes = row.fecha_emision.split('-');
            if(partes.length === 3) {
                document.getElementById('cons_fecha_emision').value = `${partes[2]}-${partes[1]}-${partes[0]}`;
            } else {
                document.getElementById('cons_fecha_emision').value = row.fecha_emision;
            }
        }
        
        document.getElementById('cons_secuencial').value = row.serie + '-' + row.secuencial;
        document.getElementById('cons_serie_hidden').value = row.serie;
        document.getElementById('cons_establecimiento').value = row.establecimiento;
        document.getElementById('cons_punto_emision').value = row.punto_emision;
        
        document.getElementById('cons_id_cliente').value = row.id_cliente;
        document.getElementById('cons_cliente_busqueda').value = (row.cliente_identificacion || '') + ' - ' + (row.cliente_nombre || '');
        
        document.getElementById('cons_punto_partida').value = row.punto_partida || '';
        document.getElementById('cons_punto_llegada').value = row.punto_llegada || '';
        document.getElementById('cons_fecha_entrega').value = row.fecha_entrega || '';
        document.getElementById('cons_hora_entrega_desde').value = row.hora_entrega_desde ? row.hora_entrega_desde.substring(0, 5) : '';
        document.getElementById('cons_hora_entrega_hasta').value = row.hora_entrega_hasta ? row.hora_entrega_hasta.substring(0, 5) : '';
        document.getElementById('cons_observaciones').value = row.observaciones || '';

        // Selects estáticos
        if (row.id_vendedor) document.getElementById('cons_id_vendedor').value = row.id_vendedor;
        if (row.id_bodega) document.getElementById('cons_id_bodega').value = row.id_bodega;
        if (row.id_responsable_traslado) document.getElementById('cons_id_responsable_traslado').value = row.id_responsable_traslado;

        // Controlar si se puede editar (solo si está Emitida)
        const btnGuardar = document.getElementById('btnGuardarConsignacion');
        if (row.estado === 'Emitida') {
            btnGuardar.classList.remove('d-none');
            btnGuardar.innerHTML = '<i class="bi bi-save me-1"></i> Actualizar';
        } else {
            btnGuardar.classList.add('d-none');
        }
        
        await cargarDetallesCons(row.id);

        await cargarCatalogosConsignacion();
        
        // Asignar punto de emision (que es dinámico)
        if (row.id_punto_emision) {
            document.getElementById('cons_id_punto_emision').value = row.id_punto_emision;
        }

        if (typeof EMPRESA_CONFIG !== 'undefined') {
            if (EMPRESA_CONFIG.obligatorio_lotes) {
                document.querySelectorAll('.th-lote').forEach(e => e.classList.remove('d-none'));
            }
            if (EMPRESA_CONFIG.obligatorio_caducidad) {
                document.querySelectorAll('.th-caducidad').forEach(e => e.classList.remove('d-none'));
            }
            if (EMPRESA_CONFIG.obligatorio_nup) {
                document.querySelectorAll('.th-nup').forEach(e => e.classList.remove('d-none'));
            }
        }

        new bootstrap.Modal(document.getElementById('modalConsignacion')).show();
    }

    async function cargarCatalogosConsignacion() {
        try {
            const resp = await fetch(`${RUTA_MODULO_CONSIGNACION}/getEstablecimientosAjax`);
            const data = await resp.json();
            
            const selBodega = document.getElementById('cons_id_bodega');
            const selVendedor = document.getElementById('cons_id_vendedor');
            
            if (data.ok && data.data.length > 0) {
                const idEst = data.data[0].id;
                document.getElementById('cons_id_establecimiento').value = idEst;
                
                const pResp = await fetch(`${RUTA_MODULO_CONSIGNACION}/getPuntosEmisionAjax?id_establecimiento=${idEst}`);
                const pData = await pResp.json();
                
                const sel = document.getElementById('cons_id_punto_emision');
                sel.innerHTML = '<option value="">Seleccione...</option>';
                
                if (pData.ok) {
                    pData.data.forEach(p => {
                        sel.innerHTML += `<option value="${p.id}" data-est="${p.id_establecimiento}" data-cod-est="${p.cod_establecimiento}" data-cod-punto="${p.codigo_punto}" data-direccion="${p.direccion_establecimiento || ''}">${p.cod_establecimiento}-${p.codigo_punto}</option>`;
                    });
                    
                    if (pData.data.length > 0) {
                        sel.value = pData.data[0].id;
                        await syncSerieConsignacion(sel.value);
                    }
                }
            }

            const currentPunto = document.getElementById('cons_id_punto_emision');
            if(currentPunto && currentPunto.options.length > 0) {
                const opt = currentPunto.options[currentPunto.selectedIndex];
                const codEst = opt.dataset.codEst;
                const codPunto = opt.dataset.codPunto;
                
                document.getElementById('cons_establecimiento').value = codEst;
                document.getElementById('cons_punto_emision').value = codPunto;
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function syncSerieConsignacion(idPunto) {
        if (!idPunto || CONS_BLOQUEAR_SECUENCIAL) {
            if (!CONS_BLOQUEAR_SECUENCIAL) {
                document.getElementById('cons_secuencial').value = '';
                document.getElementById('cons_serie_hidden').value = '';
            }
            return;
        }
        
        try {
            const resp = await fetch(`${RUTA_MODULO_CONSIGNACION}/getSecuencialAjax?id_punto_emision=${idPunto}`);
            const data = await resp.json();
            
            if (data.ok) {
                document.getElementById('cons_secuencial').value = data.formateado;
                
                const sel = document.getElementById('cons_id_punto_emision');
                const opt = sel.options[sel.selectedIndex];
                
                const codEst = opt.dataset.codEst;
                const codPunto = opt.dataset.codPunto;
                
                document.getElementById('cons_establecimiento').value = codEst;
                document.getElementById('cons_punto_emision').value = codPunto;
                document.getElementById('cons_serie_hidden').value = `${codEst}-${codPunto}`;
                
                if (opt.dataset.direccion) {
                    document.getElementById('cons_punto_partida').value = opt.dataset.direccion;
                }
            } else {
                document.getElementById('cons_secuencial').value = '';
                document.getElementById('cons_serie_hidden').value = '';
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Debe seleccionar un establecimiento para obtener el secuencial.'
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

        async function cargarDetallesCons(id) {
            try {
                const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/getDetalleAjax?id=${id}`);
                const data = await res.json();
                if(data.ok && data.data && data.data.detalles) {
                    const tbody = document.getElementById('cons_detalles_body');
                    tbody.innerHTML = '';
                    
                    const bodegaId = document.getElementById('cons_id_bodega').value || 0;

                    for (const d of data.data.detalles) {
                        const tr = agregarFilaConsignacion();
                        tr.dataset.idBodega = d.id_bodega || '';
                        const inputDesc = tr.querySelector('.input-descripcion');
                        const inputCant = tr.querySelector('.input-cantidad');
                        const inputPrecio = tr.querySelector('.input-precio');
                        const selLista = tr.querySelector('.input-lista-precios');
                        
                        tr.querySelector('.input-id-producto').value = d.id_producto;
                        tr.querySelector('.input-codigo').value = d.producto_codigo || '';
                        inputDesc.value = d.producto_nombre || ''; 
                        tr.querySelector('.input-id-pedido-detalle').value = d.id_pedido_detalle || '';
                        
                        tr.dataset.idProducto = d.id_producto;
                        tr.dataset.tipoProduccion = d.tipo_produccion || '01';
                        tr.dataset.inventariable = d.inventariable;
                        const pBase = parseFloat(d.precio_base) || 0;
                        tr.querySelector('.input-precio-base-original').value = pBase;

                        inputCant.value = parseFloat(d.cantidad) || 0;
                        inputPrecio.value = parseFloat(d.precio_unitario).toFixed(2);
                        
                        selLista.innerHTML = `<option value="${pBase}">P. Base ($${pBase.toFixed(2)})</option>`;
                        if (d.precios_lista && d.precios_lista.length > 0) {
                            d.precios_lista.forEach(pl => {
                                selLista.innerHTML += `<option value="${pl.precio}">${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(2)})</option>`;
                            });
                        }
                        
                        const precioGuardado = parseFloat(d.precio_unitario);
                        const matchPrice = Array.from(selLista.options).some(opt => parseFloat(opt.value).toFixed(2) === precioGuardado.toFixed(2));
                        if (!matchPrice) {
                            selLista.innerHTML = `<option value="${precioGuardado}" selected>Precio Guardado ($${precioGuardado.toFixed(2)})</option>` + selLista.innerHTML;
                        } else {
                            for (let i = 0; i < selLista.options.length; i++) {
                                if (parseFloat(selLista.options[i].value).toFixed(2) === precioGuardado.toFixed(2)) {
                                    selLista.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        selLista.onchange = () => {
                            tr.querySelector('.input-precio').value = parseFloat(selLista.value).toFixed(2);
                            consCalcFila(selLista);
                        };
                        
                        let esInv = (d.inventariable == true || d.inventariable == 'true' || d.inventariable == 1) && d.tipo_produccion !== '02';
                        if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                            try {
                                const rowBodegaId = d.id_bodega || bodegaId;
                                const resLote = await fetch(`${RUTA_MODULO_CONSIGNACION}/getLotesDisponiblesAjax?id_producto=${d.id_producto}&id_bodega=${rowBodegaId}`);
                                const dataLote = await resLote.json();
                                const fLote = tr.querySelector('.input-lote');
                                const fCad = tr.querySelector('.input-caducidad');
                                const fNup = tr.querySelector('.input-nup');
                                
                                if (fLote) fLote.classList.remove('d-none');
                                if (fCad) fCad.classList.remove('d-none');
                                if (fNup) {
                                    fNup.classList.remove('d-none');
                                    fNup.value = d.nup || '';
                                }

                                if (dataLote.ok && dataLote.data.length > 0) {
                                    let opts = dataLote.data;
                                    if (d.lote && !opts.some(l => l.numero_lote === d.lote)) {
                                        opts.push({numero_lote: d.lote, fecha_caducidad: d.fecha_caducidad});
                                    }
                                    
                                    if (fLote) {
                                        fLote.innerHTML = '<option value="">Lote...</option>';
                                        opts.forEach(l => {
                                            const lv = l.numero_lote === 'sin_lote' ? '' : l.numero_lote;
                                            fLote.innerHTML += `<option value="${lv}" ${lv == d.lote ? 'selected' : ''}>${lv || 'Sin Lote'}</option>`;
                                        });
                                    }
                                    if (fCad) {
                                        fCad.innerHTML = '<option value="">Vencimiento...</option>';
                                        opts.forEach(l => {
                                            const c = l.fecha_caducidad || '';
                                            fCad.innerHTML += `<option value="${c}" ${c == d.fecha_caducidad ? 'selected' : ''}>${c || 'Sin Fecha'}</option>`;
                                        });
                                    }
                                } else {
                                    if (fLote) fLote.innerHTML = `<option value="${d.lote || ''}">${d.lote || 'Sin Lote'}</option>`;
                                    if (fCad) fCad.innerHTML = `<option value="${d.fecha_caducidad || ''}">${d.fecha_caducidad || 'Sin Fecha'}</option>`;
                                }
                            } catch(e) {}
                        }

                        consCalcFila(inputCant);
                    }
                    consCalcTotales();
                }
            } catch(e) {
                console.error(e);
            }
        }

    window.consCalcFila = function(el) {
        const tr = el.closest('tr');
        if (!tr) return;
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const subtotal = cant * prec;
        tr.querySelector('.subtotal-line').textContent = subtotal.toFixed(2);
        window.consCalcTotales();
    };

    window.consCalcTotales = function() {
        const rows = document.querySelectorAll('#cons_detalles_body tr.row-detalle-cons');
        const countSpan = document.getElementById('cons_count_items');
        if (countSpan) {
            countSpan.textContent = rows.length;
        }
    };

    function agregarFilaConsignacion() {
            const tbody = document.getElementById('cons_detalles_body');
            const tr = document.createElement('tr');
            tr.className = 'row-detalle-cons';
            tr.innerHTML = `
                <td class="ps-3 position-relative">
                    <input type="text" class="form-control form-control-sm input-detalle input-descripcion" placeholder="Buscar producto..." autocomplete="off">
                    <input type="hidden" class="input-id-producto">
                    <input type="hidden" class="input-codigo">
                    <input type="hidden" class="input-precio-base-original" value="0">
                    <input type="hidden" class="input-id-pedido-detalle" value="">
                </td>
                <td>
                    <select class="form-select form-select-sm input-detalle input-lista-precios">
                        <option value="0">Precios...</option>
                    </select>
                </td>
                <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="0.00" step="any" oninput="consCalcFila(this)" onblur="this.value=parseFloat(this.value||0).toFixed(2)"></td>
                <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="1" step="any" oninput="consCalcFila(this)"></td>
                <td class="${typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.obligatorio_lotes ? '' : 'd-none'} th-lote">
                    <select class="form-select form-select-sm input-detalle input-lote d-none"></select>
                </td>
                <td class="${typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.obligatorio_caducidad ? '' : 'd-none'} th-caducidad">
                    <select class="form-select form-select-sm input-detalle input-caducidad d-none"></select>
                </td>
                <td class="${typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.obligatorio_nup ? '' : 'd-none'} th-nup">
                    <input type="text" class="form-control form-control-sm input-detalle input-nup d-none" placeholder="NUP">
                </td>
                <td class="text-end pe-4 align-middle d-none"><span class="subtotal-line">0.00</span></td>
                <td class="text-center p-0 align-middle">
                    <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); consCalcTotales();"><i class="bi bi-trash3 fs-6"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            tr.dataset.idBodega = document.getElementById('cons_id_bodega').value || '';

            const inputDesc = tr.querySelector('.input-descripcion');
            setTimeout(() => inputDesc.focus(), 50);

            const dropdownGlobal = document.getElementById('cons_res_prod_global') || crearDropdownConsignacion();

            const seleccionarProducto = (p) => {
                tr.querySelector('.input-id-producto').value = p.id;
                tr.querySelector('.input-codigo').value = p.codigo || '';
                inputDesc.value = p.nombre;
                tr.dataset.idProducto = p.id;
                tr.dataset.inventariable = p.inventariable;
                tr.dataset.tipoProduccion = p.tipo_produccion || '01';
                
                const pBase = parseFloat(p.precio_base) || 0;
                tr.querySelector('.input-precio-base-original').value = pBase;
                tr.querySelector('.input-precio').value = pBase.toFixed(2);

                const selPrecios = tr.querySelector('.input-lista-precios');
                selPrecios.innerHTML = `<option value="${pBase}">P. Base ($${pBase.toFixed(2)})</option>`;
                if (p.precios_lista && p.precios_lista.length > 0) {
                    p.precios_lista.forEach(pl => {
                        selPrecios.innerHTML += `<option value="${pl.precio}">${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(2)})</option>`;
                    });
                }
                selPrecios.onchange = () => {
                    tr.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(2);
                    consCalcFila(selPrecios);
                };

                const esInv = (p.inventariable == true || p.inventariable == 'true' || p.inventariable == 1) && p.tipo_produccion !== '02';
                if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                    consCargarLotesFila(tr);
                }

                consCalcFila(selPrecios);
                
                inputDesc.blur();
                
                const inputCant = tr.querySelector('.input-cantidad');
                if (inputCant) {
                    inputCant.focus();
                    inputCant.select();
                    setTimeout(() => {
                        inputCant.focus();
                        inputCant.select();
                    }, 150);
                }
            };

            let timeout = null;
            
            // Permitir borrar el producto seleccionado con Backspace o Delete
            inputDesc.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    const idProd = tr.querySelector('.input-id-producto').value;
                    if (idProd) {
                        e.preventDefault();
                        
                        // Limpiar campos de producto
                        tr.querySelector('.input-id-producto').value = '';
                        tr.querySelector('.input-codigo').value = '';
                        inputDesc.value = '';
                        inputDesc.readOnly = false;
                        tr.removeAttribute('data-id-producto');
                        tr.removeAttribute('data-inventariable');
                        tr.removeAttribute('data-tipo-produccion');
                        tr.querySelector('.input-precio-base-original').value = '0';
                        tr.querySelector('.input-precio').value = '0.00';
                        
                        const selPrecios = tr.querySelector('.input-lista-precios');
                        selPrecios.innerHTML = '<option value="0">Precios...</option>';
                        selPrecios.value = '0';
                        
                        const fLote = tr.querySelector('.input-lote');
                        if (fLote) {
                            fLote.innerHTML = '';
                            fLote.classList.add('d-none');
                        }
                        const fCad = tr.querySelector('.input-caducidad');
                        if (fCad) {
                            fCad.innerHTML = '';
                            fCad.classList.add('d-none');
                        }
                        const fNup = tr.querySelector('.input-nup');
                        if (fNup) {
                            fNup.value = '';
                            fNup.classList.add('d-none');
                        }
                        
                        consCalcFila(inputDesc);
                        setTimeout(() => inputDesc.focus(), 50);
                    }
                }
                if (e.key === 'Enter') {
                    const firstBtn = dropdownGlobal.querySelector('button');
                    if (firstBtn && !dropdownGlobal.classList.contains('d-none')) {
                        e.preventDefault();
                        if (firstBtn.onmousedown) firstBtn.onmousedown(new MouseEvent('mousedown'));
                    }
                }
            });

            inputDesc.addEventListener('blur', () => {
                setTimeout(() => {
                    dropdownGlobal.classList.add('d-none');
                }, 200);
            });

            inputDesc.addEventListener('input', () => {
                const q = inputDesc.value.trim();
                if (q.length < 2) {
                    dropdownGlobal.classList.add('d-none');
                    return;
                }

                clearTimeout(timeout);
                timeout = setTimeout(async () => {
                    try {
                        const idBodega = document.getElementById('cons_id_bodega').value || 0;
                        const consId = document.getElementById('cons_id').value || 0;
                        const url = `${RUTA_MODULO_CONSIGNACION}/getProductosAjax?q=${encodeURIComponent(q)}&id_bodega=${idBodega}&id_consignacion=${consId}`;
                        const resp = await fetch(url);
                        const res = await resp.json();

                        dropdownGlobal.innerHTML = '';
                        if (res.ok && res.data && res.data.length > 0) {
                            res.data.forEach(p => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action py-2';
                                btn.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center text-start w-100">
                                        <div class="pe-2">
                                            <span class="fw-bold small text-primary d-block">${p.nombre}</span>
                                            <span class="x-small text-muted">${p.codigo || 'S/C'}</span>
                                        </div>
                                        <div class="text-end flex-shrink-0">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 d-block mb-1">$${parseFloat(p.precio_base || 0).toFixed(2)}</span>
                                            ${(p.inventariable == true || p.inventariable == 'true' || p.inventariable == 1) && p.tipo_produccion !== '02' ? `
                                                <span class="badge ${parseFloat(p.stock_actual || 0) > 0 ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-danger bg-opacity-10 text-danger border-danger'} border x-small">
                                                    Saldo: ${parseFloat(p.stock_actual || 0).toFixed(2)}
                                                </span>
                                            ` : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 x-small">Servicio</span>'}
                                        </div>
                                    </div>
                                `;
                                btn.onmousedown = (evt) => {
                                    evt.preventDefault();
                                    
                                    const esInv = (p.inventariable == true || p.inventariable == 'true' || p.inventariable == 1) && p.tipo_produccion !== '02';
                                    if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                                        const stock = parseFloat(p.stock_actual || 0);
                                        if (stock <= 0) {
                                            Swal.fire({
                                                icon: 'warning',
                                                title: 'Stock Insuficiente',
                                                text: `El producto '${p.nombre}' no tiene stock disponible en la bodega seleccionada.`,
                                                confirmButtonColor: '#0d6efd'
                                            });
                                            return;
                                        }
                                    }
                                    
                                    seleccionarProducto(p);
                                    dropdownGlobal.classList.add('d-none');
                                    setTimeout(() => {
                                        dropdownGlobal.innerHTML = '';
                                    }, 150);
                                };
                                dropdownGlobal.appendChild(btn);
                            });

                            const rect = inputDesc.getBoundingClientRect();
                            dropdownGlobal.style.top = `${rect.bottom + window.scrollY}px`;
                            dropdownGlobal.style.left = `${rect.left + window.scrollX}px`;
                            dropdownGlobal.style.width = `${rect.width}px`;
                            dropdownGlobal.classList.remove('d-none');
                        } else {
                            dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">No se encontraron resultados</div>';
                            const rect = inputDesc.getBoundingClientRect();
                            dropdownGlobal.style.top = `${rect.bottom + window.scrollY}px`;
                            dropdownGlobal.style.left = `${rect.left + window.scrollX}px`;
                            dropdownGlobal.style.width = `${rect.width}px`;
                            dropdownGlobal.classList.remove('d-none');
                        }
                    } catch (e) {
                        console.error('Error al buscar productos:', e);
                    }
                }, 300);
            });

            return tr;
        }


    function crearDropdownConsignacion() {
        let div = document.createElement('div');
        div.id = 'cons_res_prod_global';
        div.className = 'list-group shadow d-none bg-white';
        div.style.position = 'fixed';
        div.style.zIndex = '9999';
        div.style.maxHeight = '250px';
        div.style.overflowY = 'auto';
        document.body.appendChild(div);
        return div;
    }

    async function consCargarLotesFila(row) {
        const idProd = row.dataset.idProducto;
        const idBod = row.dataset.idBodega || document.getElementById('cons_id_bodega').value;
        const selLote = row.querySelector('.input-lote');
        const selCad = row.querySelector('.input-caducidad');
        const fNup = row.querySelector('.input-nup');
        const idConsignacion = document.getElementById('cons_id').value || 0;

        if (!idProd || !idBod) return;

        try {
            if (selLote) {
                selLote.innerHTML = '<option value="">Cargando...</option>';
                selLote.disabled = true;
                selLote.classList.remove('d-none');
            }
            if (selCad) {
                selCad.innerHTML = '<option value="">Cargando...</option>';
                selCad.disabled = true;
                selCad.classList.remove('d-none');
            }
            if (fNup) {
                fNup.classList.remove('d-none');
            }

            // Mostrar las columnas de cabecera si hay producto inventariable
            document.querySelectorAll('.th-lote, .th-caducidad, .th-nup').forEach(el => el.classList.remove('d-none'));

            const resp = await fetch(`${RUTA_MODULO_CONSIGNACION}/getLotesDisponiblesAjax?id_producto=${idProd}&id_bodega=${idBod}&id_consignacion=${idConsignacion}`);
            const json = await resp.json();

            if (selLote) {
                selLote.innerHTML = '<option value="">Lote...</option>';
                selLote.disabled = false;
            }
            if (selCad) {
                selCad.innerHTML = '<option value="">Vencimiento...</option>';
                selCad.disabled = false;
            }

            if (json.ok && json.data && json.data.length > 0) {
                json.data.forEach(l => {
                    const loteVal = l.numero_lote === 'sin_lote' ? '' : l.numero_lote;
                    const cadVal = l.fecha_caducidad || '';
                    
                    if (selLote) {
                        const optL = document.createElement('option');
                        optL.value = loteVal;
                        optL.textContent = loteVal || 'Sin Lote';
                        selLote.appendChild(optL);
                    }
                    if (selCad) {
                        const optC = document.createElement('option');
                        optC.value = cadVal;
                        optC.textContent = cadVal || 'Sin Fecha';
                        selCad.appendChild(optC);
                    }
                });

                if (selLote && selCad) {
                    selLote.onchange = () => {
                        const index = selLote.selectedIndex;
                        if (index >= 0) selCad.selectedIndex = index;
                    };
                    selCad.onchange = () => {
                        const index = selCad.selectedIndex;
                        if (index >= 0) selLote.selectedIndex = index;
                    };
                }

                // Seleccionar por defecto el lote y vencimiento más antiguo (el primero de la lista)
                if (selLote && selLote.options.length > 1) {
                    selLote.selectedIndex = 1;
                }
                if (selCad && selCad.options.length > 1) {
                    selCad.selectedIndex = 1;
                }
            }
        } catch (e) {
            console.error('Error al cargar lotes:', e);
        }
    }

    // Escuchar el cambio de bodega para actualizar los lotes de las filas
    document.addEventListener('DOMContentLoaded', () => {
        const selectBodega = document.getElementById('cons_id_bodega');
        if (selectBodega) {
            selectBodega.addEventListener('change', () => {
                document.querySelectorAll('.row-detalle-cons').forEach(tr => {
                    const isManual = !tr.querySelector('.input-id-pedido-detalle') || !tr.querySelector('.input-id-pedido-detalle').value;
                    if (isManual) {
                        tr.dataset.idBodega = selectBodega.value;
                    }
                    const esInv = (tr.dataset.inventariable == true || tr.dataset.inventariable == 'true' || tr.dataset.inventariable == 1) && tr.dataset.tipoProduccion !== '02';
                    if (esInv && tr.dataset.idProducto) {
                        consCargarLotesFila(tr);
                    }
                });
            });
        }
    });

    function limpiarCliente() {
        document.getElementById('cons_id_cliente').value = '';
        document.getElementById('cons_cliente_busqueda').value = '';
        const resultsDiv = document.getElementById('cons_cliente_results');
        if(resultsDiv) resultsDiv.classList.add('d-none');
    }

    let consBuscadorClienteTimeout;

    function manejarTecladoClienteCons(e) {
        if (e.key === 'Backspace' || e.key === 'Delete') {
            const idInput = document.getElementById('cons_id_cliente');
            if (idInput && idInput.value) {
                e.preventDefault();
                limpiarCliente();
            }
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            const resultsDiv = document.getElementById('cons_cliente_results');
            if (resultsDiv && !resultsDiv.classList.contains('d-none')) {
                const firstBtn = resultsDiv.querySelector('button');
                if (firstBtn) {
                    firstBtn.click();
                }
            }
        }
    }

    async function buscarClienteCons(input) {
        const q = input.value.trim();
        const resultsDiv = document.getElementById('cons_cliente_results');
        
        if (q.length < 2) {
            resultsDiv.classList.add('d-none');
            return;
        }

        clearTimeout(consBuscadorClienteTimeout);

        consBuscadorClienteTimeout = setTimeout(async () => {
            try {
                const url = '<?= defined("BASE_URL") ? BASE_URL : "" ?>/modulos/factura-venta/getClientesAjax?q=' + encodeURIComponent(q);
                
                const resp = await fetch(url);
                const data = await resp.json();
                
                resultsDiv.innerHTML = '';
                if (data.ok && data.data.length > 0) {
                    if (data.data.length === 1) {
                        const c = data.data[0];
                        if (c.identificacion === q) {
                            seleccionarClienteCons(c);
                            resultsDiv.classList.add('d-none');
                            return;
                        }
                    }

                    data.data.forEach(c => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-2 border-start-0 border-end-0';
                        btn.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-primary">${c.nombre}</span>
                                <span class="badge bg-light text-dark border x-small">${c.identificacion}</span>
                            </div>
                            <div class="x-small text-muted text-truncate"><i class="bi bi-geo-alt me-1"></i>${c.direccion || 'Sin dirección'}</div>
                        `;
                        btn.onclick = () => {
                            seleccionarClienteCons(c);
                            resultsDiv.classList.add('d-none');
                        };
                        resultsDiv.appendChild(btn);
                    });
                    resultsDiv.classList.remove('d-none');
                } else {
                    resultsDiv.innerHTML = '<div class="list-group-item small text-muted">No se encontraron resultados</div>';
                    resultsDiv.classList.remove('d-none');
                }
            } catch (e) {
                console.error(e);
            }
        }, 300);
    }

    function seleccionarClienteCons(c) {
        document.getElementById('cons_id_cliente').value = c.id;
        document.getElementById('cons_cliente_busqueda').value = c.identificacion + ' - ' + c.nombre;
        
        // Autocompletar Punto de Llegada (dirección del cliente)
        document.getElementById('cons_punto_llegada').value = c.direccion || '';
        
        // Autocompletar Punto de Partida (dirección del establecimiento del punto de emisión seleccionado)
        const selPunto = document.getElementById('cons_id_punto_emision');
        if (selPunto && selPunto.selectedIndex >= 0) {
            const opt = selPunto.options[selPunto.selectedIndex];
            if (opt && opt.dataset.direccion) {
                document.getElementById('cons_punto_partida').value = opt.dataset.direccion;
            }
        }
        
        // Mover foco a la búsqueda de productos en la grilla
        const firstProdInput = document.querySelector('.row-detalle-cons .input-descripcion');
        if (firstProdInput) {
            firstProdInput.focus();
        }
    }

    function validarHoraEntrega() {
        const desde = document.getElementById('cons_hora_entrega_desde').value;
        const hasta = document.getElementById('cons_hora_entrega_hasta').value;
        
        if (desde && hasta) {
            if (hasta <= desde) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Hora inválida',
                    text: 'La hora "Hasta" debe ser mayor a la hora "Desde".',
                    timer: 2000,
                    showConfirmButton: false
                });
                document.getElementById('cons_hora_entrega_hasta').value = '';
            }
        }
    }

    async function guardarConsignacion(e) {
        if (e) e.preventDefault();
        
        if (!document.getElementById('cons_id_cliente').value) {
            Swal.fire('Atención', 'Debe seleccionar un cliente.', 'warning').then(() => {
                const searchInput = document.getElementById('cons_cliente_busqueda');
                if (searchInput) searchInput.focus();
            });
            return;
        }
        if (!document.getElementById('cons_fecha_emision').value) {
            Swal.fire('Atención', 'La fecha de emisión es requerida.', 'warning').then(() => {
                const fEmision = document.getElementById('cons_fecha_emision');
                if (fEmision) fEmision.focus();
            });
            return;
        }
        if (!document.getElementById('cons_id_bodega').value) {
            Swal.fire('Atención', 'Debe seleccionar una bodega.', 'warning').then(() => {
                const selectBodega = document.getElementById('cons_id_bodega');
                if (selectBodega) selectBodega.focus();
            });
            return;
        }
        
        const rowElements = document.querySelectorAll('#cons_detalles_body tr.row-detalle-cons');
        if (rowElements.length === 0) {
            Swal.fire('Atención', 'Agregue al menos un producto.', 'warning').then(() => {
                agregarFilaConsignacion();
            });
            return;
        }
        
        for (let i = 0; i < rowElements.length; i++) {
            const tr = rowElements[i];
            const idProd = tr.querySelector('.input-id-producto').value;
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const precio = parseFloat(tr.querySelector('.input-precio').value) || 0;
            
            if (!idProd) {
                Swal.fire('Atención', `Hay un producto sin identificar en la fila ${i + 1}. Seleccione un producto del catálogo.`, 'warning').then(() => {
                    const descInput = tr.querySelector('.input-descripcion');
                    if (descInput) {
                        descInput.focus();
                        descInput.select();
                    }
                });
                return;
            }
            if (cant <= 0) {
                Swal.fire('Atención', `La cantidad debe ser mayor a 0 en la fila ${i + 1}.`, 'warning').then(() => {
                    const cantInput = tr.querySelector('.input-cantidad');
                    if (cantInput) {
                        cantInput.focus();
                        cantInput.select();
                    }
                });
                return;
            }
            if (precio < 0) {
                Swal.fire('Atención', `El precio no puede ser negativo en la fila ${i + 1}.`, 'warning').then(() => {
                    const precioInput = tr.querySelector('.input-precio');
                    if (precioInput) {
                        precioInput.focus();
                        precioInput.select();
                    }
                });
                return;
            }
        }
        
        if (!document.getElementById('cons_secuencial').value) {
            Swal.fire('Atención', 'No hay secuencial válido configurado. Por favor configurar en el módulo Empresa / Secuenciales antes de guardar.', 'warning').then(() => {
                const secInput = document.getElementById('cons_secuencial');
                if (secInput) secInput.focus();
            });
            return;
        }

        const btn = document.getElementById('btnGuardarConsignacion');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        const payload = {
            id: document.getElementById('cons_id').value || null,
            fecha_emision: document.getElementById('cons_fecha_emision').value,
            id_punto_emision: document.getElementById('cons_id_punto_emision').value,
            establecimiento: document.getElementById('cons_establecimiento').value,
            punto_emision: document.getElementById('cons_punto_emision').value,
            serie: document.getElementById('cons_serie_hidden').value,
            secuencial: document.getElementById('cons_secuencial').value,
            id_bodega: document.getElementById('cons_id_bodega').value,
            id_vendedor: document.getElementById('cons_id_vendedor').value,
            id_cliente: document.getElementById('cons_id_cliente').value,
            id_responsable_traslado: document.getElementById('cons_id_responsable_traslado').value,
            punto_partida: document.getElementById('cons_punto_partida').value,
            punto_llegada: document.getElementById('cons_punto_llegada').value,
            fecha_entrega: document.getElementById('cons_fecha_entrega').value,
            hora_entrega_desde: document.getElementById('cons_hora_entrega_desde').value,
            hora_entrega_hasta: document.getElementById('cons_hora_entrega_hasta').value,
            observaciones: document.getElementById('cons_observaciones').value,
            detalles: Array.from(document.querySelectorAll('#cons_detalles_body tr.row-detalle-cons')).map(tr => ({
                id_producto: tr.querySelector('.input-id-producto').value,
                cantidad: parseFloat(tr.querySelector('.input-cantidad').value) || 0,
                precio_unitario: parseFloat(tr.querySelector('.input-precio').value) || 0,
                lote: (tr.querySelector('.input-lote') && !tr.querySelector('.input-lote').classList.contains('d-none')) ? tr.querySelector('.input-lote').value : '',
                fecha_caducidad: (tr.querySelector('.input-caducidad') && !tr.querySelector('.input-caducidad').classList.contains('d-none')) ? tr.querySelector('.input-caducidad').value : '',
                nup: (tr.querySelector('.input-nup') && !tr.querySelector('.input-nup').classList.contains('d-none')) ? tr.querySelector('.input-nup').value : '',
                subtotal: parseFloat(tr.querySelector('.subtotal-line').textContent) || 0,
                id_pedido_detalle: tr.querySelector('.input-id-pedido-detalle') ? tr.querySelector('.input-id-pedido-detalle').value : '',
                id_bodega: tr.dataset.idBodega || document.getElementById('cons_id_bodega').value || ''
            }))
        };

        try {
            const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/store`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if(data.ok) {
                consLimpiarBorrador();
                Swal.fire('Éxito', data.msg, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalConsignacion')).hide();
                cargarGrid();
            } else {
                Swal.fire('Error', data.error, 'error');
            }
        } catch(e) {
            Swal.fire('Error', 'Error al guardar.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    }

    async function eliminarConsignacionBackend() {
        const id = document.getElementById('cons_id').value;
        if(!id) return;

        const confirm = await Swal.fire({
            title: '¿Eliminar consignación?',
            text: "Se devolverá el stock al inventario. Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        });

        if (confirm.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);

            try {
                const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/eliminar`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.ok) {
                    Swal.fire('Éxito', data.msg, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalConsignacion')).hide();
                    cargarGrid();
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Error al eliminar.', 'error');
            }
        }
    }

    let consBuscarPedidoModal = null;

    window.llamarPedido = function() {
        if (!consBuscarPedidoModal) {
            const modalEl = document.getElementById('modalBuscarPedido');
            consBuscarPedidoModal = new bootstrap.Modal(modalEl);
            modalEl.addEventListener('shown.bs.modal', function () {
                document.getElementById('buscar_pedido_input').focus();
            });
        }
        
        // Sync bodega options and selection
        const mainBodega = document.getElementById('cons_id_bodega');
        const modalBodega = document.getElementById('buscar_pedido_bodega');
        if (mainBodega && modalBodega) {
            modalBodega.innerHTML = mainBodega.innerHTML;
            modalBodega.value = mainBodega.value;
        }

        limpiarSeleccionPedido();
        consBuscarPedidoModal.show();
    };

    window.limpiarSeleccionPedido = function() {
        document.getElementById('buscar_pedido_input').value = '';
        const divSug = document.getElementById('buscar_pedido_sugerencias');
        if (divSug) {
            divSug.style.display = 'none';
            divSug.innerHTML = '';
        }
        document.getElementById('bp_items_container').classList.add('d-none');
        document.getElementById('btn_agregar_items_seleccionados').classList.add('d-none');
        document.getElementById('bp_empty_state').classList.remove('d-none');
        window.ACTUAL_PEDIDO_ID = null;
    };

    window.buscarPedidosAutocompletar = async function() {
        const q = document.getElementById('buscar_pedido_input').value.trim();
        const divSug = document.getElementById('buscar_pedido_sugerencias');
        
        if (q.length < 1) {
            divSug.style.display = 'none';
            divSug.innerHTML = '';
            return;
        }

        try {
            const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/getPedidosPendientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (data.ok && data.data.length > 0) {
                divSug.innerHTML = '';
                data.data.forEach(p => {
                    const item = document.createElement('a');
                    item.className = 'dropdown-item py-2 px-3 small border-bottom';
                    item.style.cursor = 'pointer';
                    let fecha = p.fecha_pedido || '';
                    if (fecha.length >= 10) fecha = fecha.substring(0, 10);
                    
                    item.innerHTML = `
                        <div class="d-flex justify-content-between pointer-events-none">
                            <span class="fw-bold text-primary">${p.numero_pedido}</span>
                            <span class="text-muted small">${fecha}</span>
                        </div>
                        <div class="text-truncate text-secondary pointer-events-none" style="font-size: 0.75rem;">${p.cliente_nombre}</div>
                    `;
                    item.onclick = function() {
                        divSug.style.display = 'none';
                        document.getElementById('buscar_pedido_input').value = `${p.numero_pedido} - ${p.cliente_nombre}`;
                        cargarPedidoParaPaso2(p.id, p.numero_pedido, p.cliente_nombre);
                    };
                    divSug.appendChild(item);
                });
                divSug.style.display = 'block';
            } else {
                divSug.innerHTML = '<div class="dropdown-item py-2 px-3 small text-muted">No se encontraron pedidos pendientes</div>';
                divSug.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    };

    // Close suggestions dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const divSug = document.getElementById('buscar_pedido_sugerencias');
        const input = document.getElementById('buscar_pedido_input');
        if (divSug && e.target !== divSug && e.target !== input && !divSug.contains(e.target)) {
            divSug.style.display = 'none';
        }
    });

    window.recargarStockLotesPedidoSeleccionado = function() {
        if (window.ACTUAL_PEDIDO_ID) {
            const num = document.getElementById('bp_pedido_numero').textContent;
            const cli = document.getElementById('bp_pedido_cliente').textContent;
            window.cargarPedidoParaPaso2(window.ACTUAL_PEDIDO_ID, num, cli);
        }
    };

    window.cargarPedidoParaPaso2 = async function(id, numero_pedido, cliente_nombre) {
        try {
            Swal.fire({
                title: 'Cargando detalles del pedido...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const res = await fetch(`${RUTA_MODULO_CONSIGNACION}/cargarPedidoDetalleAjax?id=${id}`);
            const data = await res.json();
            Swal.close();

            if (data.ok && data.data) {
                const c = data.data.cabecera;
                const d = data.data.detalles;
                
                // Calculate quantities already in the main grid for this order
                const mainGridQtyMap = {};
                document.querySelectorAll('#cons_detalles_body tr.row-detalle-cons').forEach(tr => {
                    const inpDetId = tr.querySelector('.input-id-pedido-detalle');
                    const inpCant = tr.querySelector('.input-cantidad');
                    if (inpDetId && inpDetId.value && inpCant) {
                        const detId = parseInt(inpDetId.value);
                        const cant = parseFloat(inpCant.value) || 0;
                        mainGridQtyMap[detId] = (mainGridQtyMap[detId] || 0) + cant;
                    }
                });

                const pendingItems = d.map(item => {
                    const alreadyInGrid = mainGridQtyMap[item.id] || 0;
                    const realPending = Math.max(0, (parseFloat(item.cantidad_pendiente) || 0) - alreadyInGrid);
                    return { ...item, cantidad_pendiente: realPending };
                }).filter(item => item.cantidad_pendiente > 0);

                if (pendingItems.length === 0) {
                    Swal.fire('Info', 'Este pedido ya no cuenta con ítems pendientes (o ya fueron agregados a la grilla actual).', 'info');
                    return;
                }

                if (!window.PEDIDO_DETALLES_LOADED) {
                    window.PEDIDO_DETALLES_LOADED = {};
                }
                window.PEDIDO_DETALLES_LOADED[id] = { cabecera: c, detalles: d };
                window.ACTUAL_PEDIDO_ID = id;

                document.getElementById('bp_pedido_numero').textContent = numero_pedido;
                document.getElementById('bp_pedido_cliente').textContent = cliente_nombre;

                const tbody = document.getElementById('bp_items_tbody');
                tbody.innerHTML = '';
                const bodegaId = document.getElementById('buscar_pedido_bodega').value || document.getElementById('cons_id_bodega').value || 0;

                for (const item of pendingItems) {
                    const cantPendiente = parseFloat(item.cantidad_pendiente) || 0;
                    const pBase = parseFloat(item.precio_base) || 0;
                    
                    let listaOptions = `<option value="${pBase}">P. Base ($${pBase.toFixed(2)})</option>`;
                    if (item.precios_lista && item.precios_lista.length > 0) {
                        item.precios_lista.forEach(pl => {
                            listaOptions += `<option value="${pl.precio}">${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(2)})</option>`;
                        });
                    }

                    let esInv = (item.inventariable == true || item.inventariable == 'true' || item.inventariable == 1) && item.tipo_produccion !== '02';
                    let loteHtml = '';
                    let vencHtml = '';
                    let nupHtml = '';

                    if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                        let lotesOptions = '<option value="">Lote...</option>';
                        let vencOptions = '<option value="">Vencimiento...</option>';
                        
                        try {
                            const resL = await fetch(`${RUTA_MODULO_CONSIGNACION}/getLotesDisponiblesAjax?id_producto=${item.id_producto}&id_bodega=${bodegaId}`);
                            const dataL = await resL.json();
                            if (dataL.ok && dataL.data.length > 0) {
                                dataL.data.forEach(l => {
                                    const lv = l.numero_lote === 'sin_lote' ? '' : l.numero_lote;
                                    const c = l.fecha_caducidad || '';
                                    lotesOptions += `<option value="${lv}">${lv || 'Sin Lote'}</option>`;
                                    vencOptions += `<option value="${c}">${c || 'Sin Fecha'}</option>`;
                                });
                            } else {
                                lotesOptions = '<option value="">Sin Lote</option>';
                                vencOptions = '<option value="">Sin Fecha</option>';
                            }
                        } catch(e) {
                            lotesOptions = '<option value="">Sin Lote</option>';
                            vencOptions = '<option value="">Sin Fecha</option>';
                        }

                        loteHtml = `<select class="form-select form-select-sm item-lote py-0 px-1" style="font-size: 0.8rem; height: auto;">${lotesOptions}</select>`;
                        vencHtml = `<select class="form-select form-select-sm item-caducidad py-0 px-1" style="font-size: 0.8rem; height: auto;">${vencOptions}</select>`;
                        nupHtml = `<input type="text" class="form-control form-control-sm item-nup py-0 px-1" style="font-size: 0.8rem; height: auto;" placeholder="NUP">`;
                    } else {
                        loteHtml = '<span class="text-muted small">—</span>';
                        vencHtml = '<span class="text-muted small">—</span>';
                        nupHtml = '<span class="text-muted small">—</span>';
                    }

                    const tr = document.createElement('tr');
                    tr.dataset.itemId = item.id;
                    tr.dataset.productoId = item.id_producto;
                    tr.innerHTML = `
                        <td class="align-middle">
                            <div class="fw-bold small text-dark">${item.producto_nombre}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">${item.producto_codigo}</div>
                        </td>
                        <td class="text-end align-middle small">${parseFloat(item.cantidad)}</td>
                        <td class="text-end align-middle small">${parseFloat(item.cantidad_consignada)}</td>
                        <td class="text-end align-middle small fw-bold text-danger">${cantPendiente}</td>
                        <td class="align-middle">
                            <input type="number" class="form-control form-control-sm item-cantidad text-end py-0 px-1" style="font-size: 0.8rem; height: auto;" min="0" step="any" max="${cantPendiente}" value="${cantPendiente}">
                        </td>
                        <td class="align-middle">
                            <select class="form-select form-select-sm item-lista-precios py-0 px-1" style="font-size: 0.8rem; height: auto;" onchange="const tr = this.closest('tr'); tr.querySelector('.item-precio').value = parseFloat(this.value).toFixed(2);">
                                    ${listaOptions}
                            </select>
                        </td>
                        <td class="align-middle">
                            <input type="number" class="form-control form-control-sm item-precio text-end py-0 px-1" style="font-size: 0.8rem; height: auto;" min="0.00" step="0.01" value="${parseFloat(item.precio_unitario).toFixed(2)}">
                        </td>
                        <td class="align-middle">${loteHtml}</td>
                        <td class="align-middle">${vencHtml}</td>
                        <td class="align-middle">${nupHtml}</td>
                    `;
                    tbody.appendChild(tr);
                }

                document.getElementById('bp_empty_state').classList.add('d-none');
                document.getElementById('bp_items_container').classList.remove('d-none');
                document.getElementById('btn_agregar_items_seleccionados').classList.remove('d-none');
            } else {
                Swal.fire('Error', data.error || 'No se pudieron cargar los ítems del pedido.', 'error');
            }
        } catch(e) {
            console.error(e);
            Swal.fire('Error', 'Ocurrió un error al cargar el detalle.', 'error');
        }
    };

    window.agregarItemsSeleccionadosPaso2 = async function() {
        const id = window.ACTUAL_PEDIDO_ID;
        const loaded = window.PEDIDO_DETALLES_LOADED ? window.PEDIDO_DETALLES_LOADED[id] : null;
        if (!loaded) return;
        
        const c = loaded.cabecera;
        const tbodyItems = document.getElementById('bp_items_tbody');
        const checkedRows = tbodyItems.querySelectorAll('tr');
        
        let selectedItems = [];
        
        let validationError = null;

        checkedRows.forEach(tr => {
            if (validationError) return;

            const qty = parseFloat(tr.querySelector('.item-cantidad').value) || 0;
            if (qty > 0) {
                const itemDetailId = parseInt(tr.dataset.itemId);
                const idProducto = parseInt(tr.dataset.productoId);
                const price = parseFloat(tr.querySelector('.item-precio').value) || 0;
                const selLista = tr.querySelector('.item-lista-precios');
                const priceBase = selLista ? parseFloat(selLista.options[0].value) : price;
                
                const selLote = tr.querySelector('.item-lote');
                const selCad = tr.querySelector('.item-caducidad');
                const inpNup = tr.querySelector('.item-nup');
                
                const lote = selLote ? selLote.value : '';
                const caducidad = selCad ? selCad.value : '';
                const nup = inpNup ? inpNup.value.trim() : '';
                
                const detailObj = loaded.detalles.find(d => d.id === itemDetailId);
                const maxQty = parseFloat(tr.querySelector('.item-cantidad').max) || 0;
                if (qty > maxQty) {
                    validationError = `La cantidad a despachar (${qty}) no puede superar el saldo pendiente (${maxQty}) para el producto: ${detailObj.producto_nombre}`;
                    return;
                }

                let esInv = (detailObj.inventariable == true || detailObj.inventariable == 'true' || detailObj.inventariable == 1) && detailObj.tipo_produccion !== '02';

                if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                    if (EMPRESA_CONFIG.obligatorio_lotes && !lote) {
                        validationError = `El lote es obligatorio para el producto: ${detailObj.producto_nombre}`;
                        if (selLote) selLote.focus();
                        return;
                    }
                    if (EMPRESA_CONFIG.obligatorio_caducidad && !caducidad) {
                        validationError = `La fecha de vencimiento es obligatoria para el producto: ${detailObj.producto_nombre}`;
                        if (selCad) selCad.focus();
                        return;
                    }
                    if (EMPRESA_CONFIG.obligatorio_nup && !nup) {
                        validationError = `El NUP es obligatorio para el producto: ${detailObj.producto_nombre}`;
                        if (inpNup) inpNup.focus();
                        return;
                    }
                }
                
                selectedItems.push({
                    id_pedido_detalle: itemDetailId,
                    id_producto: idProducto,
                    cantidad: qty,
                    precio_unitario: price,
                    precio_base: priceBase,
                    lote: lote,
                    caducidad: caducidad,
                    nup: nup,
                    original: detailObj
                });
            }
        });
        
        if (validationError) {
            Swal.fire('Atención', validationError, 'warning');
            return;
        }
        
        if (selectedItems.length === 0) {
            Swal.fire('Atención', 'Debe configurar una cantidad mayor a cero en al menos un ítem.', 'warning');
            return;
        }

        const isNewCons = !document.getElementById('cons_id').value;
        if (isNewCons && c.cliente_direccion) {
            document.getElementById('cons_punto_llegada').value = c.cliente_direccion;
        }

        const selectedBodegaId = document.getElementById('buscar_pedido_bodega').value || document.getElementById('cons_id_bodega').value || '';

        const currentClientVal = document.getElementById('cons_id_cliente').value;
        if (!currentClientVal) {
            document.getElementById('cons_id_cliente').value = c.id_cliente;
            document.getElementById('cons_cliente_busqueda').value = (c.cliente_identificacion || '') + ' - ' + (c.cliente_nombre || '');
            if (c.id_vendedor) {
                document.getElementById('cons_id_vendedor').value = c.id_vendedor;
            }
        }

        for (const item of selectedItems) {
            const tr = agregarFilaConsignacion();
            tr.dataset.idBodega = selectedBodegaId;
            
            const inputDesc = tr.querySelector('.input-descripcion');
            const inputCant = tr.querySelector('.input-cantidad');
            const inputPrecio = tr.querySelector('.input-precio');
            const selLista = tr.querySelector('.input-lista-precios');
            
            tr.querySelector('.input-id-producto').value = item.id_producto;
            tr.querySelector('.input-codigo').value = item.original.producto_codigo || '';
            inputDesc.value = item.original.producto_nombre || ''; 
            
            tr.dataset.idProducto = item.id_producto;
            tr.dataset.tipoProduccion = item.original.tipo_produccion || '01';
            tr.dataset.inventariable = item.original.inventariable;

            const inputPedidoDet = tr.querySelector('.input-id-pedido-detalle');
            if (inputPedidoDet) {
                inputPedidoDet.value = item.id_pedido_detalle;
            }

            const pBase = parseFloat(item.precio_base) || 0;
            tr.querySelector('.input-precio-base-original').value = pBase;

            inputCant.value = item.cantidad;
            
            selLista.innerHTML = `<option value="${pBase}">P. Base ($${pBase.toFixed(2)})</option>`;
            if (item.original.precios_lista && item.original.precios_lista.length > 0) {
                item.original.precios_lista.forEach(pl => {
                    selLista.innerHTML += `<option value="${pl.precio}">${pl.nombre_precio} ($${parseFloat(pl.precio).toFixed(2)})</option>`;
                });
            }
            
            const matchPrice = Array.from(selLista.options).some(opt => parseFloat(opt.value).toFixed(2) === item.precio_unitario.toFixed(2));
            if (!matchPrice) {
                selLista.innerHTML = `<option value="${item.precio_unitario}" selected>Precio Pedido ($${item.precio_unitario.toFixed(2)})</option>` + selLista.innerHTML;
            } else {
                for (let i = 0; i < selLista.options.length; i++) {
                    if (parseFloat(selLista.options[i].value).toFixed(2) === item.precio_unitario.toFixed(2)) {
                        selLista.selectedIndex = i;
                        break;
                    }
                }
            }

            // Set price and call calculation AFTER select list is completely built
            inputPrecio.value = item.precio_unitario.toFixed(2);

            selLista.onchange = () => {
                tr.querySelector('.input-precio').value = parseFloat(selLista.value).toFixed(2);
                consCalcFila(selLista);
            };

            let esInv = (item.original.inventariable == true || item.original.inventariable == 'true' || item.original.inventariable == 1) && item.original.tipo_produccion !== '02';
            if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                const fLote = tr.querySelector('.input-lote');
                const fCad = tr.querySelector('.input-caducidad');
                const fNup = tr.querySelector('.input-nup');
                
                if (fLote) fLote.classList.remove('d-none');
                if (fCad) fCad.classList.remove('d-none');
                if (fNup) fNup.classList.remove('d-none');

                const bodegaId = selectedBodegaId || 0;
                try {
                    const resLote = await fetch(`${RUTA_MODULO_CONSIGNACION}/getLotesDisponiblesAjax?id_producto=${item.id_producto}&id_bodega=${bodegaId}`);
                    const dataLote = await resLote.json();
                    
                    if (dataLote.ok && dataLote.data.length > 0) {
                        if (fLote) {
                            fLote.innerHTML = '<option value="">Lote...</option>';
                            dataLote.data.forEach(l => {
                                const lv = l.numero_lote === 'sin_lote' ? '' : l.numero_lote;
                                fLote.innerHTML += `<option value="${lv}">${lv || 'Sin Lote'}</option>`;
                            });
                            fLote.value = item.lote;
                        }
                        if (fCad) {
                            fCad.innerHTML = '<option value="">Vencimiento...</option>';
                            dataLote.data.forEach(l => {
                                const c = l.fecha_caducidad || '';
                                fCad.innerHTML += `<option value="${c}">${c || 'Sin Fecha'}</option>`;
                            });
                            fCad.value = item.caducidad;
                        }
                    } else {
                        if (fLote) fLote.innerHTML = `<option value="${item.lote}">${item.lote || 'Sin Lote'}</option>`;
                        if (fCad) fCad.innerHTML = `<option value="${item.caducidad}">${item.caducidad || 'Sin Fecha'}</option>`;
                    }
                } catch(e) {
                    if (fLote) fLote.innerHTML = `<option value="${item.lote}">${item.lote || 'Sin Lote'}</option>`;
                    if (fCad) fCad.innerHTML = `<option value="${item.caducidad}">${item.caducidad || 'Sin Fecha'}</option>`;
                }
                
                if (fNup) {
                    fNup.value = item.nup;
                }
            }

            consCalcFila(inputPrecio);
        }

        consCalcTotales();

        if (consBuscarPedidoModal) {
            consBuscarPedidoModal.hide();
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Ítems Agregados',
            text: 'Se han agregado los ítems seleccionados correctamente.',
            timer: 1500,
            showConfirmButton: false
        });
    };

    function crearVendedorRapido() { Swal.fire('Info', 'Abre modal de vendedor', 'info'); }

    function pdfConsignacion() {
        const id = document.getElementById('cons_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar la consignación primero', 'warning');
        Swal.fire('Info', 'Generando PDF...', 'info');
    }
    function emailConsignacion() {
        const id = document.getElementById('cons_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar la consignación primero', 'warning');
        Swal.fire('Info', 'Enviando por correo...', 'info');
    }
    function whatsappConsignacion() {
        const id = document.getElementById('cons_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar la consignación primero', 'warning');
        Swal.fire('Info', 'Enviando por WhatsApp...', 'info');
    }

    let modalRespInst = null;

    function abrirModalResponsableCrear() {
        document.getElementById('form-responsable-traslado').reset();
        if (!modalRespInst) {
            modalRespInst = new bootstrap.Modal(document.getElementById('modalResponsableTraslado'));
        }
        modalRespInst.show();
    }

    function cerrarModalResponsable() {
        if (modalRespInst) {
            modalRespInst.hide();
        }
    }

    async function guardarResponsableTraslado() {
        const nombre = document.getElementById('resp_nombre').value.trim();
        const identificacion = document.getElementById('resp_identificacion').value.trim();
        const telefono = document.getElementById('resp_telefono').value.trim();
        const email = document.getElementById('resp_email').value.trim();

        if (!nombre) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'El nombre completo es obligatorio.'
            });
            return;
        }

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'El formato del correo electrónico no es válido.'
            });
            return;
        }

        try {
            const formData = new FormData();
            formData.append('nombre', nombre);
            formData.append('identificacion', identificacion);
            formData.append('telefono', telefono);
            formData.append('email', email);

            const resp = await fetch(`${RUTA_MODULO_CONSIGNACION}/guardarResponsableAjax`, {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();

            if (res.status && res.data) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: res.message || 'Responsable creado con éxito.',
                    timer: 1500,
                    showConfirmButton: false
                });

                const selectResp = document.getElementById('cons_id_responsable_traslado');
                if (selectResp) {
                    const option = document.createElement('option');
                    option.value = res.data.id;
                    option.textContent = res.data.nombre;
                    selectResp.appendChild(option);
                    selectResp.value = res.data.id;
                }

                cerrarModalResponsable();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message || 'No se pudo guardar la información.'
                });
            }
        } catch (err) {
            console.error('Error al guardar responsable:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo conectar con el servidor.'
            });
        }
    }

    // =====================================================================
    // BORRADOR LOCAL - Auto-guardado de consignación sin guardar
    // (mismo patrón que Facturas de Venta: 100% localStorage del navegador)
    // =====================================================================

    /** Serializa el estado actual del modal a un objeto plano (sin secuencial). */
    function consCapturarEstado() {
        const e = {};
        e.fecha_emision           = document.getElementById('cons_fecha_emision')?.value || '';
        e.id_punto_emision        = document.getElementById('cons_id_punto_emision')?.value || '';
        e.id_bodega               = document.getElementById('cons_id_bodega')?.value || '';
        e.id_vendedor             = document.getElementById('cons_id_vendedor')?.value || '';
        e.id_responsable_traslado = document.getElementById('cons_id_responsable_traslado')?.value || '';
        e.id_cliente              = document.getElementById('cons_id_cliente')?.value || '';
        e.cliente_busqueda        = document.getElementById('cons_cliente_busqueda')?.value || '';
        e.punto_partida           = document.getElementById('cons_punto_partida')?.value || '';
        e.punto_llegada           = document.getElementById('cons_punto_llegada')?.value || '';
        e.fecha_entrega           = document.getElementById('cons_fecha_entrega')?.value || '';
        e.hora_entrega_desde      = document.getElementById('cons_hora_entrega_desde')?.value || '';
        e.hora_entrega_hasta      = document.getElementById('cons_hora_entrega_hasta')?.value || '';
        e.observaciones           = document.getElementById('cons_observaciones')?.value || '';

        e.detalles = [];
        document.querySelectorAll('#cons_detalles_body tr.row-detalle-cons').forEach(tr => {
            const idProd = tr.querySelector('.input-id-producto')?.value || '';
            const nombre = tr.querySelector('.input-descripcion')?.value || '';
            if (!idProd && !nombre.trim()) return; // ignorar filas vacías
            const fLote = tr.querySelector('.input-lote');
            const fCad  = tr.querySelector('.input-caducidad');
            const fNup  = tr.querySelector('.input-nup');
            e.detalles.push({
                id_producto:      idProd,
                codigo:           tr.querySelector('.input-codigo')?.value || '',
                nombre:           nombre,
                cantidad:         tr.querySelector('.input-cantidad')?.value || '',
                precio:           tr.querySelector('.input-precio')?.value || '',
                precio_base:      tr.querySelector('.input-precio-base-original')?.value || '0',
                id_pedido_detalle: tr.querySelector('.input-id-pedido-detalle')?.value || '',
                id_bodega:        tr.dataset.idBodega || '',
                inventariable:    tr.dataset.inventariable || '',
                tipo_produccion:  tr.dataset.tipoProduccion || '01',
                lote:             (fLote && !fLote.classList.contains('d-none')) ? fLote.value : '',
                fecha_caducidad:  (fCad  && !fCad.classList.contains('d-none'))  ? fCad.value  : '',
                nup:              (fNup  && !fNup.classList.contains('d-none'))  ? fNup.value  : ''
            });
        });
        return e;
    }

    /** Guarda el estado actual en localStorage (solo al crear, no al editar). */
    function consAutoGuardar() {
        try {
            // Solo cuando se está creando una NUEVA (sin id) y el modal está abierto.
            if (document.getElementById('cons_id')?.value) return;
            const modalEl = document.getElementById('modalConsignacion');
            if (!modalEl || !modalEl.classList.contains('show')) return;

            const estado = consCapturarEstado();
            if (!estado.id_cliente && !estado.detalles.length) {
                localStorage.removeItem(CONS_STORAGE_KEY);
                return;
            }
            localStorage.setItem(CONS_STORAGE_KEY, JSON.stringify(estado));
        } catch (e) {
            /* localStorage puede estar lleno o deshabilitado */
        }
    }

    /** Elimina el borrador guardado. */
    function consLimpiarBorrador() {
        try { localStorage.removeItem(CONS_STORAGE_KEY); } catch (e) {}
    }

    /** Aviso (overlay) cuando hay una consignación sin guardar. */
    function consMostrarAvisoBorrador(borrador) {
        const previo = document.getElementById('cons-borrador-aviso');
        if (previo) previo.remove();

        const divAviso = document.createElement('div');
        divAviso.id = 'cons-borrador-aviso';
        divAviso.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
        const clienteName = borrador.cliente_busqueda || 'desconocido';
        divAviso.innerHTML = `
            <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                    <h6 class="fw-bold mb-0">Consignación sin guardar</h6>
                </div>
                <p class="small text-muted mb-4">Hay una consignación en borrador del cliente <strong>${clienteName}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary" id="cons-aviso-nueva">
                        <i class="bi bi-file-earmark-plus me-1"></i> Nueva consignación
                    </button>
                    <button class="btn btn-sm btn-primary" id="cons-aviso-restaurar">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                    </button>
                </div>
            </div>`;
        document.body.appendChild(divAviso);

        document.getElementById('cons-aviso-restaurar').onclick = () => {
            divAviso.remove();
            window._CONS_SKIP_BORRADOR = true;
            window._CONS_BORRADOR_PENDIENTE = borrador;
            abrirModalConsignacionNueva();
        };
        document.getElementById('cons-aviso-nueva').onclick = () => {
            consLimpiarBorrador();
            divAviso.remove();
            window._CONS_SKIP_BORRADOR = true;
            abrirModalConsignacionNueva();
        };
    }

    /** Restaura el estado guardado en el modal (se llama tras shown.bs.modal). */
    async function consRestaurar(b) {
        // Esperar a que el catálogo de puntos de emisión termine de cargarse (carga asíncrona).
        const selPunto = document.getElementById('cons_id_punto_emision');
        for (let i = 0; i < 40 && selPunto.options.length <= 1; i++) {
            await new Promise(r => setTimeout(r, 50));
        }

        // Cabecera (la bodega debe fijarse antes de reconstruir los detalles para cargar lotes correctos)
        if (b.fecha_emision)           document.getElementById('cons_fecha_emision').value = b.fecha_emision;
        if (b.id_bodega)               document.getElementById('cons_id_bodega').value = b.id_bodega;
        if (b.id_vendedor)             document.getElementById('cons_id_vendedor').value = b.id_vendedor;
        if (b.id_responsable_traslado) document.getElementById('cons_id_responsable_traslado').value = b.id_responsable_traslado;
        if (b.fecha_entrega)           document.getElementById('cons_fecha_entrega').value = b.fecha_entrega;
        if (b.hora_entrega_desde)      document.getElementById('cons_hora_entrega_desde').value = b.hora_entrega_desde;
        if (b.hora_entrega_hasta)      document.getElementById('cons_hora_entrega_hasta').value = b.hora_entrega_hasta;
        document.getElementById('cons_observaciones').value = b.observaciones || '';

        // Serie / punto de emisión → recalcula un secuencial FRESCO (no se guarda el viejo)
        if (b.id_punto_emision && selPunto.querySelector(`option[value="${b.id_punto_emision}"]`)) {
            selPunto.value = b.id_punto_emision;
            await syncSerieConsignacion(b.id_punto_emision);
        }

        // Puntos de partida/llegada: fijar DESPUÉS del sync (que puede sobreescribir partida)
        document.getElementById('cons_punto_partida').value = b.punto_partida || '';
        document.getElementById('cons_punto_llegada').value = b.punto_llegada || '';

        // Cliente
        if (b.id_cliente) {
            document.getElementById('cons_id_cliente').value = b.id_cliente;
            document.getElementById('cons_cliente_busqueda').value = b.cliente_busqueda || '';
        }

        // Detalles
        const tbody = document.getElementById('cons_detalles_body');
        tbody.innerHTML = '';
        for (const d of (b.detalles || [])) {
            const tr = agregarFilaConsignacion();
            tr.querySelector('.input-id-producto').value = d.id_producto || '';
            tr.querySelector('.input-codigo').value = d.codigo || '';
            tr.querySelector('.input-descripcion').value = d.nombre || '';
            const inpPedDet = tr.querySelector('.input-id-pedido-detalle');
            if (inpPedDet) inpPedDet.value = d.id_pedido_detalle || '';

            tr.dataset.idProducto     = d.id_producto || '';
            tr.dataset.inventariable  = d.inventariable;
            tr.dataset.tipoProduccion = d.tipo_produccion || '01';
            tr.dataset.idBodega       = d.id_bodega || document.getElementById('cons_id_bodega').value || '';

            const pBase = parseFloat(d.precio_base) || 0;
            tr.querySelector('.input-precio-base-original').value = pBase;
            tr.querySelector('.input-cantidad').value = d.cantidad || 1;
            const precioGuardado = parseFloat(d.precio) || 0;
            tr.querySelector('.input-precio').value = precioGuardado.toFixed(2);

            const selLista = tr.querySelector('.input-lista-precios');
            selLista.innerHTML = `<option value="${pBase}">P. Base ($${pBase.toFixed(2)})</option>`;
            if (Math.abs(precioGuardado - pBase) > 0.001) {
                selLista.innerHTML = `<option value="${precioGuardado}" selected>Precio Guardado ($${precioGuardado.toFixed(2)})</option>` + selLista.innerHTML;
            }
            selLista.onchange = () => {
                tr.querySelector('.input-precio').value = parseFloat(selLista.value).toFixed(2);
                consCalcFila(selLista);
            };

            const esInv = (d.inventariable == true || d.inventariable == 'true' || d.inventariable == 1) && d.tipo_produccion !== '02';
            if (esInv && typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.facturacion_inventario) {
                await consCargarLotesFila(tr);
                const fLote = tr.querySelector('.input-lote');
                const fCad  = tr.querySelector('.input-caducidad');
                const fNup  = tr.querySelector('.input-nup');
                if (fLote && d.lote) {
                    if (!Array.from(fLote.options).some(o => o.value === d.lote)) fLote.appendChild(new Option(d.lote, d.lote));
                    fLote.value = d.lote;
                }
                if (fCad && d.fecha_caducidad) {
                    if (!Array.from(fCad.options).some(o => o.value === d.fecha_caducidad)) fCad.appendChild(new Option(d.fecha_caducidad, d.fecha_caducidad));
                    fCad.value = d.fecha_caducidad;
                }
                if (fNup) fNup.value = d.nup || '';
            }

            consCalcFila(tr.querySelector('.input-cantidad'));
        }
        if (!(b.detalles && b.detalles.length)) agregarFilaConsignacion();
        consCalcTotales();
    }

    // Registrar el auto-guardado del borrador sobre el formulario de consignación.
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('formConsignacion');
        if (form) {
            const debouncedGuardar = debounce(consAutoGuardar, 800);
            form.addEventListener('input', debouncedGuardar);
            form.addEventListener('change', debouncedGuardar);
        }
    });
</script>

<!-- Modal Crear Responsable de Traslado -->
<div class="modal fade" id="modalResponsableTraslado" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3 border-bottom">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-truck text-success me-2"></i> Nuevo Responsable de Traslado
                </h5>
                <button type="button" class="btn-close" onclick="cerrarModalResponsable()" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="form-responsable-traslado">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" id="resp_nombre" class="form-control form-control-sm" placeholder="Ej: Juan Pérez" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Identificación (Cédula/RUC)</label>
                        <input type="text" id="resp_identificacion" class="form-control form-control-sm" placeholder="Ej: 1700000000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Teléfono</label>
                        <input type="text" id="resp_telefono" class="form-control form-control-sm" placeholder="Ej: 0999999999">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Correo electrónico</label>
                        <input type="email" id="resp_email" class="form-control form-control-sm" placeholder="Ej: responsable@correo.com">
                    </div>
                </form>
            </div>
            <div class="modal-footer justify-content-end bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" onclick="cerrarModalResponsable()">
                    <i class="bi bi-x-lg me-1"></i> Cerrar
                </button>
                <button type="button" class="btn btn-success btn-sm px-4 shadow-sm" onclick="guardarResponsableTraslado()">
                    <i class="bi bi-check2-circle me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Buscar Pedidos Pendientes -->
<div class="modal fade" id="modalBuscarPedido" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-secondary text-white py-3 border-bottom border-secondary">
                <div>
                    <h5 class="modal-title fw-bold text-white mb-0">
                        <i class="bi bi-file-earmark-arrow-down text-info me-2"></i> Importar Ítems de Pedidos Pendientes
                    </h5>
                    <span class="text-white-50 d-block mt-1" style="font-size: 0.7rem;">Selecciona y configura productos de un pedido para importarlos a la consignación actual sin perder la información cargada.</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Buscador con Sugerencias y Selector de Bodega -->
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-7 position-relative">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.75rem; font-weight: 600;">Buscar Pedido (Número o Cliente)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white px-2 py-0"><i class="bi bi-search text-muted" style="font-size: 0.75rem;"></i></span>
                            <input type="text" id="buscar_pedido_input" class="form-control form-control-sm py-0 px-2" style="font-size: 0.75rem; height: 28px;" placeholder="Escriba el número de pedido o nombre del cliente..." oninput="buscarPedidosAutocompletar()" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" style="font-size: 0.75rem; height: 28px;" onclick="limpiarSeleccionPedido()">
                                <i class="bi bi-x-lg" style="font-size: 0.7rem;"></i> Limpiar
                            </button>
                        </div>
                        <!-- Contenedor de Sugerencias flotante -->
                        <div id="buscar_pedido_sugerencias" class="dropdown-menu shadow-lg w-100 p-0" style="display: none; max-height: 200px; overflow-y: auto; z-index: 1100; position: absolute; top: 100%;">
                            <!-- Sugerencias dinámicas -->
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-1 text-muted" style="font-size: 0.75rem; font-weight: 600;">Bodega de Despacho * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'buscar_pedido_bodega', 'id_bodega') ?></label>
                        <select class="form-select form-select-sm" id="buscar_pedido_bodega" style="font-size: 0.75rem; height: 28px;" onchange="recargarStockLotesPedidoSeleccionado()"></select>
                    </div>
                </div>

                <!-- Detalle del Pedido Seleccionado e Ítems -->
                <div id="bp_items_container" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2 p-1 px-2 bg-light rounded border" style="font-size: 0.75rem; min-height: auto;">
                        <div>
                            <span class="fw-bold text-muted me-1">Pedido:</span>
                            <span id="bp_pedido_numero" class="text-primary fw-bold me-3"></span>
                            <span class="fw-bold text-muted me-1">Cliente:</span>
                            <span id="bp_pedido_cliente" class="text-secondary fw-bold"></span>
                        </div>
                    </div>

                    <div class="card shadow-sm border border-secondary border-opacity-25 rounded-3 bg-white">
                        <div class="card-header bg-light py-2 px-3">
                            <span class="small fw-bold text-dark"><i class="bi bi-box-seam me-1 text-primary"></i> Ítems Pendientes para Cargar</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover table-bordered mb-0 align-middle" style="font-size: 0.8rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-end" style="width: 60px;">Ped.</th>
                                            <th class="text-end" style="width: 60px;">Cons.</th>
                                            <th class="text-end" style="width: 60px;">Pend.</th>
                                            <th class="text-center" style="width: 85px;">Cant. desp.</th>
                                            <th style="width: 140px;">Lista Precios</th>
                                            <th style="width: 80px;">Precio</th>
                                            <th style="width: 100px;">Lote</th>
                                            <th style="width: 100px;">Venc.</th>
                                            <th style="width: 80px;">NUP</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bp_items_tbody">
                                        <!-- Filas dinámicas de ítems del pedido -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado Inicial o Vacío -->
                <div id="bp_empty_state" class="text-center py-5 text-muted">
                    <i class="bi bi-cart3 fs-1 d-block mb-2 text-secondary text-opacity-50"></i>
                    <span>Busque y seleccione un pedido pendiente para configurar e importar sus productos.</span>
                </div>
            </div>
            <div class="modal-footer justify-content-end bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm px-3 me-2" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cerrar
                </button>
                <button type="button" id="btn_agregar_items_seleccionados" class="btn btn-success btn-sm px-3 d-none" onclick="agregarItemsSeleccionadosPaso2()">
                    <i class="bi bi-plus-lg me-1"></i> Agregar a Consignación
                </button>
            </div>
        </div>
    </div>
</div>
