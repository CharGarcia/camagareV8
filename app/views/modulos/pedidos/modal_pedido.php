<?php

/** @var array $perm */
?>
<!-- Modal Pedido -->
<div class="modal fade" id="modalPedido" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 cmg-favoritos-card" data-modulo="pedidos">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="titulo-modal">
                    <i class="bi bi-cart-plus text-primary me-2"></i> Nuevo Pedido
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3">
                <!-- Acciones Rápidas Superior -->
                <div class="d-flex justify-content-start gap-1 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1" onclick="abrirModalClienteCrear()" title="Crear nuevo cliente rápido">
                        <i class="bi bi-person-plus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2 py-1" onclick="abrirModalResponsableCrear()" title="Crear responsable de traslado rápido">
                        <i class="bi bi-truck"></i>
                    </button>
                </div>
                <hr class="text-muted my-3 opacity-25">

                <form id="form-pedido-cabecera">
                    <input type="hidden" id="pedido_id">

                            <!-- Cabecera del Pedido -->
                            <!-- Fila 1 -->
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1"><?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('pedidos', 'id_punto_emision', 'id_punto_emision') ?> Serie</label>
                                    <select class="form-select form-select-sm" name="id_punto_emision" id="id_punto_emision" onchange="syncSerie(this.value)">
                                        <?php if (isset($puntos) && is_array($puntos)): ?>
                                            <?php foreach ($puntos as $p): ?>
                                                <option value="<?= $p['id'] ?>"
                                                    data-est="<?= $p['id_establecimiento'] ?>"
                                                    data-cod-est="<?= htmlspecialchars($p['cod_establecimiento']) ?>"
                                                    data-cod-punto="<?= htmlspecialchars($p['codigo_punto']) ?>">
                                                    <?= $p['cod_establecimiento'] ?>-<?= $p['codigo_punto'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <input type="hidden" name="id_establecimiento" id="id_establecimiento">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold mb-1">Secuencial <span class="text-danger">*</span></label>
                                    <input type="text" id="secuencial" class="form-control form-control-sm bg-light text-center" readonly placeholder="000000001" maxlength="9">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Estado</label>
                                    <select id="estado" class="form-select form-select-sm">
                                        <option value="Pendiente">Pendiente</option>
                                        <option value="Procesado">Procesado</option>
                                        <option value="Anulado">Anulado</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Cliente <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                        <input type="text" id="buscar-cliente" class="form-control form-control-sm" placeholder="Buscar por nombre o identificación..." autocomplete="off">
                                        <input type="hidden" id="id_cliente">
                                    </div>
                                </div>
                            </div>

                            <!-- Fila 2 -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Fecha Pedido <span class="text-danger">*</span></label>
                                    <input type="date" id="fecha_pedido" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Fecha de Entrega <span class="text-danger">*</span></label>
                                    <input type="date" id="fecha_entrega" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Hora Inicial <span class="text-danger">*</span></label>
                                    <input type="time" id="hora_inicial_entrega" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Hora Máxima <span class="text-danger">*</span></label>
                                    <input type="time" id="hora_maxima_entrega" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold"><?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('pedidos', 'id_responsable_entrega', 'id_responsable_entrega') ?> Responsable de Entrega <span class="text-danger">*</span></label>
                                    <select id="id_responsable_entrega" class="form-select form-select-sm">
                                        <option value="">Seleccione un responsable...</option>
                                        <?php if (isset($responsables) && is_array($responsables)): ?>
                                            <?php foreach ($responsables as $resp): ?>
                                                <option value="<?= $resp['id'] ?>"><?= htmlspecialchars($resp['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Fila 3 -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Observaciones Generales</label>
                                    <input type="text" id="observaciones" class="form-control form-control-sm" placeholder="Notas adicionales sobre el pedido...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Observaciones Internas</label>
                                    <input type="text" id="observaciones_internas" class="form-control form-control-sm" placeholder="Notas internas del equipo...">
                                </div>
                            </div>

                            <hr class="text-muted opacity-25">

                            <!-- Fila 4: Productos (Mismo estilo que Factura Venta) -->
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
                                <div class="table-responsive" style="max-height: 350px; overflow: visible;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap align-middle">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted text-center" style="width: 150px;">Código</th>
                                                <th class="py-2 small fw-bold text-muted" style="width: 70%;">Descripción <span class="text-danger">*</span></th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width: 15%;">Cant. <span class="text-danger">*</span></th>
                                                <th style="width: 40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="detalle-productos">
                                            <!-- Filas generadas dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFilaProducto()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Items: <span id="m-count-items">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Totales Ocultos (Para mantener compatibilidad del JS si es necesario) -->
                            <div class="d-none">
                                <span id="txt-subtotal">0</span>
                                <span id="txt-iva">0</span>
                                <span id="txt-total">0</span>
                            </div>
                        </form>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btn-eliminar-modal" onclick="eliminarPedidoActual()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cerrar
                    </button>
                    <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="guardarPedido()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dropdown de autocompletado global flotante como en factura de venta -->
<div id="m-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; background-color: white;"></div>

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