<?php

/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */

// Módulo de preferencias: el helper lo normaliza a 'pedidos' (basename + guiones a _).
$pedRutaModulo  = $rutaModulo ?? 'modulos/pedidos';
$pedVistaConfig = $vistaConfig ?? [];
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

            <div class="modal-body p-0">
                <div class="px-3 pt-3">
                <!-- Aviso: otro usuario tiene este pedido en uso (edición o consumo desde Consignaciones) -->
                <div id="aviso-bloqueo-pedido" class="alert alert-warning d-flex align-items-center gap-2 mb-3 d-none" role="alert">
                    <i class="bi bi-lock-fill"></i>
                    <span id="aviso-bloqueo-pedido-texto"></span>
                </div>

                <!-- Aviso: todas las líneas ya están registradas (Consignación/Factura); el pedido queda de solo lectura -->
                <div id="aviso-pedido-procesado" class="alert alert-info d-flex align-items-center gap-2 mb-3 d-none" role="alert">
                    <i class="bi bi-check2-circle"></i>
                    <span>Este pedido ya está completamente registrado en una consignación o factura. No se puede editar.</span>
                </div>

                <!-- Barra de acciones del documento: va ANTES de las pestañas (CLAUDE.md §9),
                     porque aplica al pedido entero y no a una pestaña en particular. -->
                <div class="d-flex justify-content-start gap-1 mb-3">
                    <?php if (\App\Helpers\Permisos::puedeCrear('modulos/clientes')): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1" onclick="abrirModalClienteCrear()" title="Crear nuevo cliente rápido">
                        <i class="bi bi-person-plus"></i>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-success btn-sm px-2 py-1" onclick="abrirModalResponsableCrear()" title="Crear responsable de traslado rápido">
                        <i class="bi bi-truck"></i>
                    </button>

                    <div class="vr mx-1"></div>

                    <button type="button" class="btn btn-outline-danger btn-sm px-2 py-1" onclick="pdfPedido()" title="Generar PDF">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2 py-1" onclick="excelPedido()" title="Exportar Excel">
                        <i class="bi bi-file-earmark-excel"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1" onclick="emailPedido()" title="Enviar por correo">
                        <i class="bi bi-envelope"></i>
                    </button>

                    <?php if (\App\Helpers\Permisos::puedeCrear('modulos/factura-venta')): ?>
                    <div class="vr mx-1"></div>

                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1" id="btn-facturar-pedido" onclick="facturarPedido()" title="Generar factura de venta desde este pedido">
                        <i class="bi bi-receipt"></i>
                    </button>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Pestañas del modal -->
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsPedido" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="ped-tab-general-btn" data-bs-toggle="tab"
                               data-bs-target="#ped-pane-general" href="#ped-pane-general" role="tab" title="General">
                                <i class="bi bi-info-circle me-1"></i> General
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="ped-tab-detalle-btn" data-bs-toggle="tab"
                               data-bs-target="#ped-pane-detalle" href="#ped-pane-detalle" role="tab" title="Detalle del registro">
                                <i class="bi bi-clock-history me-1"></i> Detalle
                            </a>
                        </li>
                    </ul>
                    <div class="pb-1 flex-shrink-0">
                        <?php
                        // La clave debe ser el id del .tab-pane (no el del <a>): es lo que
                        // oculta renderEstilosPestanasOcultas(). "General" no se lista a
                        // propósito — es el formulario, no se puede esconder.
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas(
                            ['ped-pane-detalle' => 'Detalle'],
                            $pedVistaConfig,
                            $pedRutaModulo
                        );
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top px-3 py-3" id="tabsPedidoContent" style="overflow: visible !important;">
                <div class="tab-pane fade show active" id="ped-pane-general" role="tabpanel">
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
                                    <!-- El vendedor va en la MISMA línea del label, pegado al filo derecho:
                                         `min-width:0` es lo que permite que text-truncate recorte el nombre
                                         largo en vez de empujar el label "Cliente" fuera de la columna. -->
                                    <div class="d-flex align-items-end justify-content-between gap-2">
                                        <label class="form-label small fw-bold mb-1 flex-shrink-0">Cliente <span class="text-danger">*</span></label>
                                        <!-- Vendedor (asesor) del cliente seleccionado. Pedidos no guarda vendedor
                                             propio: es el que tiene asignado el cliente (clientes.id_vendedor), solo
                                             informativo. Lo llena pedMostrarVendedorCliente() en pedidos.js. -->
                                        <div id="ped-cliente-vendedor" class="small text-muted text-truncate text-end mb-1 d-none" style="font-size:.72rem;min-width:0;"></div>
                                    </div>
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
                                    <!-- type="text" (no "time") a propósito: se escribe libremente con la máscara
                                         00:00 y sin el selector de horas del navegador. Ver pedMascaraHora(). -->
                                    <input type="text" id="hora_inicial_entrega" class="form-control form-control-sm ped-hora"
                                           placeholder="00:00" maxlength="5" inputmode="numeric" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Hora Máxima <span class="text-danger">*</span></label>
                                    <input type="text" id="hora_maxima_entrega" class="form-control form-control-sm ped-hora"
                                           placeholder="00:00" maxlength="5" inputmode="numeric" autocomplete="off">
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
                                <!-- overflow: auto (antes "visible"): con max-height y overflow visible las
                                     filas que pasaban de 350px se dibujaban FUERA del contenedor, encima del
                                     pie, y tapaban el botón "Agregar línea" en los pedidos con muchos ítems.
                                     Con auto, la tabla scrollea dentro y el pie queda siempre visible. El
                                     dropdown de productos no se recorta: vive fuera, con position: fixed. -->
                                <div class="table-responsive" style="max-height: 350px; overflow: auto;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap align-middle">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted text-center" style="width: 150px;">Código</th>
                                                <th class="py-2 small fw-bold text-muted" style="width: 60%;">Descripción <span class="text-danger">*</span></th>
                                                <!-- Columna Estado: se oculta en un pedido nuevo (no hay nada que informar
                                                     todavía) y aparece al abrir un pedido existente. Ver pedActualizarColumnaEstado(). -->
                                                <th class="py-2 small fw-bold text-muted text-center ped-col-estado d-none" style="width: 100px;">Estado</th>
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
                                    <button type="button" id="btn-agregar-linea" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="agregarFilaProducto()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3 d-flex flex-wrap gap-3">
                                        <span>Items: <span id="m-count-items">0</span></span>
                                        <span>Total cantidades: <span id="m-total-cantidades">0</span></span>
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
                </div><!-- /ped-pane-general -->

                <!-- Pestaña Detalle: quién registró el pedido y qué se le ha cambiado.
                     Va FUERA del <form> a propósito: PED_bloquearControles() deshabilita
                     todos los input/select/button del formulario cuando el pedido está
                     bloqueado, y el historial se debe poder seguir consultando. -->
                <div class="tab-pane fade" id="ped-pane-detalle" role="tabpanel">
                    <div id="ped-detalle-vacio" class="text-center text-muted py-5 d-none">
                        <i class="bi bi-clock-history d-block mb-2" style="font-size: 1.6rem;"></i>
                        <div class="small">El pedido todavía no se ha guardado: aquí aparecerán quién lo creó y los cambios que reciba.</div>
                    </div>

                    <div id="ped-detalle-contenido" class="d-none">
                        <!-- Ficha de registro: quién lo creó y en qué consistió la última edición.
                             "Quién" sale de created_by / updated_by de la cabecera; el "qué hizo"
                             se completa con el último evento del historial, porque updated_by por
                             sí solo no dice nada de lo que esa persona cambió. -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded-3 p-2 bg-white h-100">
                                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">
                                        <i class="bi bi-person-check me-1"></i> Creado
                                    </div>
                                    <div class="fw-bold text-truncate" style="font-size: 0.85rem;" id="ped-info-creado-por" title="">—</div>
                                    <div class="text-muted" style="font-size: 0.72rem;" id="ped-info-creado-en">—</div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="border rounded-3 p-2 bg-white h-100">
                                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;">
                                        <i class="bi bi-pencil-square me-1"></i> Última edición
                                    </div>
                                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                                        <span class="fw-bold text-truncate" style="font-size: 0.85rem;" id="ped-info-modificado-por" title="">—</span>
                                        <span class="text-muted" style="font-size: 0.72rem;" id="ped-info-modificado-en"></span>
                                    </div>
                                    <!-- Qué hizo: etiqueta de la acción + campos que cambió. -->
                                    <div class="mt-1" style="font-size: 0.72rem;" id="ped-info-ultima-accion">
                                        <span class="text-muted">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Historial de ediciones (log_sistema / pedidos_cabecera) -->
                        <h6 class="fw-bold small text-muted text-uppercase mb-2">
                            <i class="bi bi-list-ul me-1"></i> Ediciones del pedido
                        </h6>
                        <div class="border rounded-3 bg-white p-3" style="max-height: 320px; overflow-y: auto;">
                            <div id="ped-historial-timeline" class="position-relative">
                                <div class="text-center py-4 text-muted small">
                                    <span class="spinner-border spinner-border-sm me-2"></span> Cargando historial...
                                </div>
                            </div>
                        </div>
                        <div class="text-muted mt-2" style="font-size: 0.7rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            El historial registra los cambios de la cabecera del pedido (cliente, fechas, horas,
                            responsable, estado y observaciones) y los cambios de estado que provoca guardar o
                            eliminar una consignación. Lo que pasó con cada línea —si ya se entregó en consignación
                            o se facturó— se consulta con el ícono de historial de la propia línea, en General.
                        </div>
                    </div>
                </div><!-- /ped-pane-detalle -->
                </div><!-- /tab-content -->
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
                        <button type="button" id="btn-guardar-pedido" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="guardarPedido()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dropdown de autocompletado global flotante como en factura de venta -->
<div id="m-dropdown-productos-global" class="list-group shadow position-fixed d-none" style="z-index: 9999; min-width: 400px; max-height: 250px; overflow-y: auto; overscroll-behavior: contain; background-color: white;"></div>

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