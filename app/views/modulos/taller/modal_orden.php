<?php
/**
 * Modal de la Orden de Trabajo del taller.
 *
 * Pestañas: Recepción · Presupuesto · Departamentos · Bitácora · Entrega ·
 * Info. adicional. La barra de acciones de documento (PDF, informe técnico,
 * correo, WhatsApp, factura y recibo) va arriba, antes de las pestañas, como
 * exige el estándar de modales del sistema.
 *
 * @var array $perm
 * @var array $puntos
 * @var array $bodegas
 * @var array $departamentos
 * @var array $empleados
 * @var array $vistaConfig
 */
$pestanas = [
    'tll-tab-recepcion'     => 'Recepción',
    'tll-tab-presupuesto'   => 'Presupuesto',
    'tll-tab-departamentos' => 'Departamentos',
    'tll-tab-bitacora'      => 'Bitácora',
    'tll-tab-entrega'       => 'Entrega',
    'tll-tab-info'          => 'Info. adicional',
];

/**
 * Acciones de la orden que en realidad pertenecen a otros módulos: crear
 * departamentos, emitir una factura o emitir un recibo. Se resuelve el permiso
 * real de cada módulo destino con el helper central (misma fuente de verdad que
 * usan los controladores) y se ocultan los botones que el usuario no puede usar.
 *
 * El backend vuelve a comprobarlo: ocultar un botón no es una medida de
 * seguridad, solo evita ofrecer algo que va a fallar.
 */
$puedeCrear = static function (string $ruta): bool {
    $p = \App\Helpers\Permisos::porRuta($ruta);
    return !empty($p['crear']) || !empty($p['todo']);
};

$puedeCrearDepartamento = $puedeCrear('modulos/taller-departamentos');
$puedeCrearChecklist    = $puedeCrear('modulos/taller-checklist');
$puedeFacturar = $puedeCrear(\App\controllers\modulos\TallerController::RUTA_FACTURA);
$puedeRecibo   = $puedeCrear(\App\controllers\modulos\TallerController::RUTA_RECIBO);
?>
<div class="modal fade" id="modalOrdenTaller" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="tllTitulo"><i class="bi bi-wrench-adjustable-circle me-1 text-primary"></i> Nueva orden de trabajo</h5>
                <span id="tll_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nueva</span>
                <span id="tll_aprob_badge" class="badge bg-warning bg-opacity-10 text-warning ms-2 d-none">
                    <i class="bi bi-clock-history"></i> Sin aprobar
                </span>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanas, $vistaConfig ?? [], 'taller') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>

            <!-- Barra de acciones de documento -->
            <div class="px-3 pt-2 d-flex flex-wrap gap-1 align-items-center border-bottom pb-2">
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="tllCrearVehiculo()" title="Registrar un vehículo nuevo">
                    <i class="bi bi-car-front"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="tllCrearCliente()" title="Registrar un cliente nuevo">
                    <i class="bi bi-person-plus"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="tllCrearProducto()" title="Registrar un repuesto o servicio nuevo">
                    <i class="bi bi-box-seam"></i>
                </button>
                <?php if ($puedeCrearDepartamento): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="tllCrearDepartamento()" title="Crear un departamento del taller">
                        <i class="bi bi-diagram-3"></i>
                    </button>
                <?php endif; ?>
                <?php if ($puedeCrearChecklist): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="tllCrearAccesorio()" title="Agregar un punto al checklist de recepción">
                        <i class="bi bi-list-check"></i>
                    </button>
                <?php endif; ?>
                <button type="button" id="tll_btn_pdf" class="btn btn-outline-danger btn-sm px-2" onclick="tllPdf()" title="PDF de la orden de trabajo" disabled>
                    <i class="bi bi-file-earmark-pdf"></i>
                </button>
                <button type="button" id="tll_btn_informe" class="btn btn-outline-danger btn-sm px-2" onclick="tllInforme()" title="Informe técnico del vehículo" disabled>
                    <i class="bi bi-file-earmark-text"></i> <span class="d-none d-md-inline">Informe</span>
                </button>
                <button type="button" id="tll_btn_precuenta" class="btn btn-outline-dark btn-sm px-2" onclick="tllPrecuenta()" title="Precuenta: valores a pagar" disabled>
                    <i class="bi bi-cash-coin"></i> <span class="d-none d-md-inline">Precuenta</span>
                </button>
                <button type="button" id="tll_btn_correo" class="btn btn-outline-info btn-sm px-2" onclick="tllCorreo()" title="Enviar por correo" disabled>
                    <i class="bi bi-envelope"></i>
                </button>
                <button type="button" id="tll_btn_whatsapp" class="btn btn-outline-success btn-sm px-2" onclick="tllWhatsapp()" title="Avisar por WhatsApp" disabled>
                    <i class="bi bi-whatsapp"></i>
                </button>
                <?php if ($puedeFacturar): ?>
                    <button type="button" id="tll_btn_factura" class="btn btn-outline-success btn-sm px-2" onclick="tllGenerarDocumento('FACTURA')" title="Generar factura electrónica">
                        <i class="bi bi-receipt"></i> <span class="d-none d-md-inline">Factura</span>
                    </button>
                <?php endif; ?>
                <?php if ($puedeRecibo): ?>
                    <button type="button" id="tll_btn_recibo" class="btn btn-outline-success btn-sm px-2" onclick="tllGenerarDocumento('RECIBO')" title="Generar recibo de venta">
                        <i class="bi bi-receipt-cutoff"></i> <span class="d-none d-md-inline">Recibo</span>
                    </button>
                <?php endif; ?>
                <span id="tll_doc_info" class="ms-auto small text-muted"></span>
            </div>

            <div class="modal-body">
                <form id="formOrdenTaller" autocomplete="off">
                    <input type="hidden" id="tll_id">
                    <input type="hidden" id="tll_id_vehiculo">
                    <input type="hidden" id="tll_id_cliente">
                    <input type="hidden" id="tll_id_punto_emision">
                    <input type="hidden" id="tll_id_establecimiento">
                    <input type="hidden" id="tll_numero_orden">

                    <!-- Cabecera fija (siempre visible) -->
                    <div class="p-2 bg-white border rounded-3 mb-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Fecha ingreso <span class="text-danger">*</span></label>
                                <input type="datetime-local" id="tll_fecha_ingreso" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/taller', 'tll_select_serie', 'id_punto_emision') ?></label>
                                <select id="tll_select_serie" name="id_punto_emision" class="form-select form-select-sm border-primary border-opacity-25" onchange="tllSerieChange()" style="height:31px;">
                                    <?php if (empty($puntos)): ?>
                                        <option value="">— Sin puntos —</option>
                                    <?php else: foreach ($puntos as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"
                                            data-id-est="<?= (int) ($p['id_establecimiento'] ?? 0) ?>"
                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                            <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Secuencial</label>
                                <input type="text" id="tll_secuencial" class="form-control form-control-sm border-primary border-opacity-25 text-center bg-light py-0" style="height:31px;" readonly placeholder="000000001" maxlength="9">
                            </div>
                            <div class="col-12 col-md-6 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Cliente</label>
                                <input type="text" id="tll_cliente_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Buscar cliente..." oninput="tllBuscarClientes(this.value)">
                                <div id="tll_cli_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                        </div>

                        <div class="row g-2 align-items-end mt-1">
                            <div class="col-12 col-md-4 position-relative">
                                <label class="x-small fw-bold text-muted mb-1">Vehículo <span class="text-danger">*</span></label>
                                <input type="text" id="tll_vehiculo_busqueda" class="form-control form-control-sm border-primary border-opacity-10" placeholder="Placa, marca, modelo o propietario..." oninput="tllBuscarVehiculos(this.value)">
                                <div id="tll_veh_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:240px; overflow:auto;"></div>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Kilometraje</label>
                                <input type="number" id="tll_kilometraje" class="form-control form-control-sm border-primary border-opacity-10 py-0" style="height:31px;" min="0" placeholder="Km">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Combustible</label>
                                <select id="tll_nivel_combustible" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;">
                                    <option value="">—</option>
                                    <option value="E">E - Vacío</option>
                                    <option value="1/4">1/4</option>
                                    <option value="1/2">1/2</option>
                                    <option value="3/4">3/4</option>
                                    <option value="F">F - Lleno</option>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Prioridad</label>
                                <select id="tll_prioridad" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;">
                                    <option value="baja">Baja</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="x-small fw-bold text-muted mb-1">Bodega <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/taller', 'tll_id_bodega', 'id_bodega') ?></label>
                                <select id="tll_id_bodega" name="id_bodega" class="form-select form-select-sm border-primary border-opacity-10" style="height:31px;" title="Bodega de la que salen los repuestos">
                                    <option value="">Seleccione...</option>
                                    <?php foreach (($bodegas ?? []) as $b): ?>
                                        <option value="<?= (int) $b['id'] ?>" <?= \App\Helpers\Booleano::es($b['es_default'] ?? false) ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-1 d-flex flex-wrap gap-3" id="tll_info_vehiculo" style="font-size:.78rem"></div>
                    </div>

                    <!-- Pestañas -->
                    <ul class="nav nav-tabs nav-tabs-sm" role="tablist">
                        <li class="nav-item" id="tll-tab-recepcion"><button class="nav-link active py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-recepcion" type="button"><i class="bi bi-clipboard-check me-1"></i>Recepción</button></li>
                        <li class="nav-item" id="tll-tab-presupuesto"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-presupuesto" type="button"><i class="bi bi-cash-coin me-1"></i>Presupuesto <span id="tll_badge_lineas" class="badge bg-secondary ms-1">0</span></button></li>
                        <li class="nav-item" id="tll-tab-departamentos"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-departamentos" type="button"><i class="bi bi-diagram-3 me-1"></i>Departamentos</button></li>
                        <li class="nav-item" id="tll-tab-bitacora"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-bitacora" type="button"><i class="bi bi-clock-history me-1"></i>Bitácora</button></li>
                        <li class="nav-item" id="tll-tab-entrega"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-entrega" type="button"><i class="bi bi-box-arrow-right me-1"></i>Entrega</button></li>
                        <li class="nav-item" id="tll-tab-info"><button class="nav-link py-1 small" data-bs-toggle="tab" data-bs-target="#tll-pane-info" type="button"><i class="bi bi-info-circle me-1"></i>Info. adicional</button></li>
                    </ul>

                    <div class="tab-content border border-top-0 rounded-bottom p-2">

                        <!-- ═══ RECEPCIÓN ═══ -->
                        <div class="tab-pane fade show active" id="tll-pane-recepcion" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-12 col-md-8">
                                    <label class="x-small fw-bold text-muted mb-1">Motivo de ingreso / qué reporta el cliente <span class="text-danger">*</span></label>
                                    <textarea id="tll_motivo_ingreso" class="form-control form-control-sm" rows="2" placeholder="Ej. Ruido al frenar en la rueda delantera derecha y golpe en la puerta trasera izquierda."></textarea>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Tipo servicio</label>
                                    <select id="tll_tipo_servicio" class="form-select form-select-sm">
                                        <option value="correctivo">Correctivo</option>
                                        <option value="mantenimiento">Mantenimiento</option>
                                        <option value="colision">Colisión</option>
                                        <option value="garantia">Garantía</option>
                                        <option value="revision">Revisión</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Entrega estimada</label>
                                    <input type="datetime-local" id="tll_fecha_estimada_entrega" class="form-control form-control-sm">
                                </div>
                            </div>

                            <div class="row g-2 mt-1">
                                <div class="col-6 col-md-3">
                                    <label class="x-small fw-bold text-muted mb-1">Usuario del vehículo</label>
                                    <input type="text" id="tll_nombre_usuario" class="form-control form-control-sm" maxlength="200" placeholder="Quién lo maneja">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Teléfono</label>
                                    <input type="text" id="tll_telefono_contacto" class="form-control form-control-sm" maxlength="50">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="x-small fw-bold text-muted mb-1">Correo de contacto</label>
                                    <input type="email" id="tll_correo_contacto" class="form-control form-control-sm" maxlength="150">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Asesor</label>
                                    <select id="tll_id_empleado_asesor" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        <?php foreach (($empleados ?? []) as $e): ?>
                                            <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombres_apellidos'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Jefe de taller</label>
                                    <select id="tll_id_empleado_jefe" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        <?php foreach (($empleados ?? []) as $e): ?>
                                            <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombres_apellidos'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Siniestro / aseguradora -->
                            <div class="mt-2 p-2 border rounded bg-light">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="tll_es_siniestro" onchange="tllToggleSiniestro()">
                                    <label class="form-check-label x-small fw-bold text-muted" for="tll_es_siniestro">El ingreso es por un siniestro de aseguradora</label>
                                </div>
                                <div class="row g-2 mt-1 d-none" id="tll_bloque_siniestro">
                                    <div class="col-6 col-md-4">
                                        <label class="x-small fw-bold text-muted mb-1">Aseguradora</label>
                                        <input type="text" id="tll_aseguradora" class="form-control form-control-sm" maxlength="150">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="x-small fw-bold text-muted mb-1">N.° siniestro</label>
                                        <input type="text" id="tll_numero_siniestro" class="form-control form-control-sm" maxlength="60">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Deducible</label>
                                        <input type="number" step="0.01" min="0" id="tll_deducible" class="form-control form-control-sm text-end" value="0">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="x-small fw-bold text-muted mb-1">Ajustador</label>
                                        <input type="text" id="tll_ajustador" class="form-control form-control-sm" maxlength="150">
                                    </div>
                                </div>
                            </div>

                            <!-- Checklist de recepción -->
                            <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                                <h6 class="mb-0 small fw-bold text-muted"><i class="bi bi-list-check me-1"></i>Inventario y estado del vehículo</h6>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="tllCargarChecklistBase()" title="Cargar la plantilla configurada">
                                    <i class="bi bi-arrow-clockwise"></i> Cargar plantilla
                                </button>
                            </div>
                            <div id="tll_checklist" class="row g-1"></div>

                            <!-- Fotos -->
                            <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                                <h6 class="mb-0 small fw-bold text-muted"><i class="bi bi-camera me-1"></i>Fotos de evidencia</h6>
                                <div class="d-flex gap-1 align-items-center">
                                    <select id="tll_foto_momento" class="form-select form-select-sm py-0" style="width:auto;height:26px;font-size:.75rem;">
                                        <option value="ingreso">Ingreso</option>
                                        <option value="proceso">Proceso</option>
                                        <option value="entrega">Entrega</option>
                                    </select>
                                    <input type="file" id="tll_foto_input" accept="image/*" class="d-none" onchange="tllSubirFoto(this)">
                                    <button type="button" id="tll_btn_foto" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="document.getElementById('tll_foto_input').click()" disabled>
                                        <i class="bi bi-upload"></i> Subir
                                    </button>
                                </div>
                            </div>
                            <div id="tll_fotos" class="d-flex flex-wrap gap-2"></div>
                            <div class="x-small text-muted mt-1" id="tll_fotos_ayuda">Guarde la orden para poder adjuntar fotos.</div>

                            <div class="mt-2">
                                <label class="x-small fw-bold text-muted mb-1">Observaciones de recepción</label>
                                <textarea id="tll_observaciones" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- ═══ PRESUPUESTO ═══ -->
                        <div class="tab-pane fade" id="tll-pane-presupuesto" role="tabpanel">
                            <div class="mb-2">
                                <label class="x-small fw-bold text-muted mb-1">Diagnóstico del taller</label>
                                <div class="input-group input-group-sm">
                                    <textarea id="tll_diagnostico" class="form-control form-control-sm" rows="2" placeholder="Qué encontró el técnico y cuál es la causa."></textarea>
                                    <button type="button" class="btn btn-outline-primary" onclick="tllGuardarDiagnostico()" title="Guardar el diagnóstico"><i class="bi bi-save"></i></button>
                                </div>
                            </div>

                            <!-- Alta rápida de línea -->
                            <div class="p-2 border rounded bg-light mb-2">
                                <div class="row g-1 align-items-end">
                                    <div class="col-6 col-md-2">
                                        <label class="x-small fw-bold text-muted mb-1">Tipo</label>
                                        <select id="tll_l_tipo" class="form-select form-select-sm" onchange="tllTipoLineaChange()">
                                            <option value="repuesto">Repuesto</option>
                                            <option value="mano_obra">Mano de obra</option>
                                            <option value="insumo">Insumo</option>
                                            <option value="tercero">Terceros</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 position-relative">
                                        <label class="x-small fw-bold text-muted mb-1">Descripción <span class="text-danger">*</span></label>
                                        <input type="text" id="tll_l_descripcion" class="form-control form-control-sm" placeholder="Buscar repuesto o servicio del catálogo, o escribir libre..." oninput="tllBuscarProductos(this.value)" onfocus="tllBuscarProductos(this.value)" autocomplete="off">
                                        <input type="hidden" id="tll_l_id_producto">
                                        <!-- Resultados del catálogo. Va aquí dentro (posición absoluta sobre la
                                             columna) igual que los buscadores de cliente y vehículo. -->
                                        <div id="tll_prod_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1085; max-height:260px; overflow:auto;"></div>
                                    </div>
                                    <div class="col-4 col-md-1">
                                        <label class="x-small fw-bold text-muted mb-1">Cant.</label>
                                        <input type="number" step="0.01" min="0" id="tll_l_cantidad" class="form-control form-control-sm text-end" value="1">
                                    </div>
                                    <div class="col-4 col-md-1">
                                        <label class="x-small fw-bold text-muted mb-1">Horas</label>
                                        <input type="number" step="0.25" min="0" id="tll_l_horas" class="form-control form-control-sm text-end" value="0" disabled>
                                    </div>
                                    <div class="col-4 col-md-1">
                                        <label class="x-small fw-bold text-muted mb-1">P. Unit</label>
                                        <input type="number" step="0.01" min="0" id="tll_l_precio" class="form-control form-control-sm text-end" value="0">
                                    </div>
                                    <div class="col-4 col-md-1">
                                        <label class="x-small fw-bold text-muted mb-1">Dscto</label>
                                        <input type="number" step="0.01" min="0" id="tll_l_descuento" class="form-control form-control-sm text-end" value="0">
                                    </div>
                                    <div class="col-4 col-md-1">
                                        <label class="x-small fw-bold text-muted mb-1">Depto.</label>
                                        <select id="tll_l_departamento" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <?php foreach (($departamentos ?? []) as $d): ?>
                                                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['nombre'] ?? '') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4 col-md-1 d-grid">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="tllAgregarLinea()" id="tll_btn_agregar_linea" disabled>
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row g-1 mt-1">
                                    <div class="col-6 col-md-3">
                                        <label class="x-small fw-bold text-muted mb-1">Técnico que ejecuta</label>
                                        <select id="tll_l_tecnico" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <?php foreach (($empleados ?? []) as $e): ?>
                                                <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombres_apellidos'] ?? '') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3 d-flex align-items-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tll_l_provisto_cliente">
                                            <label class="form-check-label x-small text-muted" for="tll_l_provisto_cliente">El repuesto lo trae el cliente (no se factura)</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="x-small fw-bold text-muted mb-1">Observación de la línea</label>
                                        <input type="text" id="tll_l_observacion" class="form-control form-control-sm" maxlength="300">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive" style="max-height:280px;overflow:auto;">
                                <table class="table table-sm table-detalle mb-0 align-middle">
                                    <thead class="sticky-top">
                                        <tr>
                                            <th style="width:24%">Descripción</th>
                                            <th style="width:10%">Tipo</th>
                                            <th style="width:12%">Departamento</th>
                                            <th style="width:11%">Técnico</th>
                                            <th class="text-end" style="width:7%">Cant.</th>
                                            <th class="text-end" style="width:8%">P. Unit</th>
                                            <th class="text-end" style="width:9%">Total</th>
                                            <th class="text-center" style="width:9%">Estado</th>
                                            <th class="text-center" style="width:10%">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tll_tbody_lineas">
                                        <tr><td colspan="9" class="text-center text-muted py-3 small">Sin repuestos ni trabajos registrados.</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-start mt-2 flex-wrap gap-2">
                                <!-- Aprobación del cliente -->
                                <div class="p-2 border rounded flex-grow-1" style="min-width:320px;max-width:520px;" id="tll_bloque_aprobacion">
                                    <h6 class="small fw-bold text-muted mb-2"><i class="bi bi-hand-thumbs-up me-1"></i>Aprobación del cliente <span class="text-danger">*</span></h6>
                                    <div id="tll_aprobacion_hecha" class="alert alert-success py-2 px-2 mb-0 small d-none"></div>
                                    <div id="tll_aprobacion_form">
                                        <div class="row g-1">
                                            <div class="col-12 col-md-6">
                                                <label class="x-small fw-bold text-muted mb-1">Quién aprobó</label>
                                                <input type="text" id="tll_aprobado_por" class="form-control form-control-sm" maxlength="200" placeholder="Nombre del cliente">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="x-small fw-bold text-muted mb-1">Medio</label>
                                                <select id="tll_aprobado_medio" class="form-select form-select-sm">
                                                    <option value="presencial">Presencial</option>
                                                    <option value="telefono">Teléfono</option>
                                                    <option value="whatsapp">WhatsApp</option>
                                                    <option value="correo">Correo</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="x-small fw-bold text-muted mb-1">Observación</label>
                                                <input type="text" id="tll_aprobado_observacion" class="form-control form-control-sm" maxlength="300">
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm w-100 mt-2" onclick="tllAprobar()" id="tll_btn_aprobar" disabled>
                                            <i class="bi bi-check2-circle me-1"></i> Registrar aprobación
                                        </button>
                                        <div class="x-small text-muted mt-1">Sin esta aprobación ningún departamento puede ejecutar trabajos.</div>
                                    </div>
                                </div>

                                <!-- Totales -->
                                <div class="border rounded p-2" style="min-width:250px;">
                                    <div class="d-flex justify-content-between small"><span class="text-muted">Repuestos</span><span id="tll_t_repuestos">0.00</span></div>
                                    <div class="d-flex justify-content-between small"><span class="text-muted">Mano de obra</span><span id="tll_t_mano_obra">0.00</span></div>
                                    <div class="d-flex justify-content-between small"><span class="text-muted">Descuento</span><span id="tll_t_descuento">0.00</span></div>
                                    <div class="d-flex justify-content-between small"><span class="text-muted">IVA</span><span id="tll_t_iva">0.00</span></div>
                                    <hr class="my-1">
                                    <div class="d-flex justify-content-between fw-bold"><span>TOTAL</span><span id="tll_t_total">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ DEPARTAMENTOS ═══ -->
                        <div class="tab-pane fade" id="tll-pane-departamentos" role="tabpanel">
                            <div class="d-flex gap-1 align-items-end flex-wrap mb-2 p-2 border rounded bg-light">
                                <div>
                                    <label class="x-small fw-bold text-muted mb-1">Enviar el vehículo a</label>
                                    <select id="tll_dep_destino" class="form-select form-select-sm" style="min-width:200px;">
                                        <option value="">Seleccione departamento...</option>
                                        <?php foreach (($departamentos ?? []) as $d): ?>
                                            <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['nombre'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" onclick="tllEnviarDepartamento()" id="tll_btn_enviar_dep" disabled>
                                    <i class="bi bi-arrow-right-circle me-1"></i> Enviar
                                </button>
                                <span class="x-small text-muted ms-2" id="tll_dep_actual_txt"></span>
                            </div>

                            <div id="tll_etapas" class="small"></div>
                        </div>

                        <!-- ═══ BITÁCORA ═══ -->
                        <div class="tab-pane fade" id="tll-pane-bitacora" role="tabpanel">
                            <div class="row g-1 align-items-end mb-2 p-2 border rounded bg-light">
                                <div class="col-12 col-md-4">
                                    <label class="x-small fw-bold text-muted mb-1">Título de la nota</label>
                                    <input type="text" id="tll_nota_concepto" class="form-control form-control-sm" maxlength="150">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="x-small fw-bold text-muted mb-1">Detalle</label>
                                    <input type="text" id="tll_nota_detalle" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-2 d-grid">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="tllAgregarNota()" id="tll_btn_nota" disabled>
                                        <i class="bi bi-plus-lg"></i> Agregar
                                    </button>
                                </div>
                            </div>
                            <div id="tll_bitacora" class="tll-timeline"></div>
                        </div>

                        <!-- ═══ ENTREGA ═══ -->
                        <div class="tab-pane fade" id="tll-pane-entrega" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6 col-md-4">
                                    <label class="x-small fw-bold text-muted mb-1">Entregado a</label>
                                    <input type="text" id="tll_entregado_a" class="form-control form-control-sm" maxlength="200">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Km de salida</label>
                                    <input type="number" min="0" id="tll_kilometraje_salida" class="form-control form-control-sm text-end">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Garantía (días)</label>
                                    <input type="number" min="0" id="tll_garantia_dias" class="form-control form-control-sm text-end" value="0">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Garantía (km)</label>
                                    <input type="number" min="0" id="tll_garantia_km" class="form-control form-control-sm text-end" value="0">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1">Próx. mant. (km)</label>
                                    <input type="number" min="0" id="tll_proximo_mantenimiento_km" class="form-control form-control-sm text-end">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="x-small fw-bold text-muted mb-1"><i class="bi bi-calendar-event"></i> Próxima cita</label>
                                    <input type="date" id="tll_proxima_cita" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-10">
                                    <label class="x-small fw-bold text-muted mb-1">Recomendaciones para el cliente</label>
                                    <textarea id="tll_recomendaciones" class="form-control form-control-sm" rows="2" placeholder="Lo que queda pendiente o se debe vigilar."></textarea>
                                </div>
                            </div>
                            <button type="button" class="btn btn-success btn-sm mt-2" onclick="tllEntregar()" id="tll_btn_entregar" disabled>
                                <i class="bi bi-box-arrow-right me-1"></i> Registrar entrega del vehículo
                            </button>
                            <div class="x-small text-muted mt-1">Se exige que todos los departamentos hayan cerrado su trabajo.</div>
                        </div>

                        <!--
                            ═══ INFO ADICIONAL ═══
                            Mismo diseño que la pestaña Info. Adicional de Factura de Venta: tabla
                            de concepto/detalle con filas compactas y una fila fija con el correo
                            del cliente, que viaja al documento al facturar la orden.
                        -->
                        <div class="tab-pane fade" id="tll-pane-info" role="tabpanel">
                            <div class="border rounded-2 overflow-hidden bg-white mt-1">
                                <div class="table-responsive" style="max-height: 200px;">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-2 py-0 small fw-bold text-muted" style="width: 40%;">Concepto</th>
                                                <th class="py-0 small fw-bold text-muted" style="width: 50%;">Detalle</th>
                                                <th class="py-0" style="width: 10%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="tll_tbody_info"></tbody>
                                    </table>
                                </div>
                                <div class="p-1 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold ms-2" onclick="tllAgregarInfo()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                </div>
                            </div>
                            <div class="x-small text-muted mt-1">
                                <i class="bi bi-info-circle me-1"></i>
                                Estos campos se copian a la factura o al recibo que se genere desde la orden.
                                El correo del cliente se mantiene solo y es el que usa el envío del comprobante.
                            </div>

                            <div class="mt-3" id="tll_historial_vehiculo_box">
                                <h6 class="small fw-bold text-muted mb-1"><i class="bi bi-clock-history me-1"></i>Historial de este vehículo</h6>
                                <div id="tll_historial_vehiculo" class="small text-muted">Seleccione un vehículo para ver su historial.</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="tll_btn_eliminar" onclick="tllEliminar()">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" id="tll_btn_guardar" onclick="tllGuardar()">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!--
    Alta rápida de departamento. Se abre desde la barra de acciones del modal,
    igual que los modales de cliente, vehículo y producto. Al guardar, el nuevo
    departamento queda seleccionado en la orden sin salir de la pantalla.
-->
<div class="modal fade" id="modalTallerDepRapido" tabindex="-1" data-bs-backdrop="static" style="z-index:1070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-1 text-primary"></i>Nuevo departamento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="x-small fw-bold text-muted mb-1">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="tll_dep_nuevo_nombre" class="form-control form-control-sm" maxlength="100" placeholder="Ej. Pintura">
                    </div>
                    <div class="col-6">
                        <label class="x-small fw-bold text-muted mb-1">Código</label>
                        <input type="text" id="tll_dep_nuevo_codigo" class="form-control form-control-sm" maxlength="20" placeholder="PIN">
                    </div>
                    <div class="col-6">
                        <label class="x-small fw-bold text-muted mb-1">Orden</label>
                        <input type="number" id="tll_dep_nuevo_orden" class="form-control form-control-sm" value="0" min="0">
                    </div>
                    <div class="col-6">
                        <label class="x-small fw-bold text-muted mb-1">Color</label>
                        <input type="color" id="tll_dep_nuevo_color" class="form-control form-control-sm form-control-color" value="#0d6efd">
                    </div>
                    <div class="col-6">
                        <label class="x-small fw-bold text-muted mb-1">Ícono</label>
                        <select id="tll_dep_nuevo_icono" class="form-select form-select-sm">
                            <option value="bi-tools">Herramientas</option>
                            <option value="bi-clipboard-pulse">Diagnóstico</option>
                            <option value="bi-gear">Motor</option>
                            <option value="bi-cone-striped">Suspensión</option>
                            <option value="bi-record-circle">Frenos</option>
                            <option value="bi-lightning-charge">Electricidad</option>
                            <option value="bi-snow">Aire acondicionado</option>
                            <option value="bi-hammer">Enderezada</option>
                            <option value="bi-brush">Preparación</option>
                            <option value="bi-palette">Pintura</option>
                            <option value="bi-stars">Pulido</option>
                            <option value="bi-nut">Armado</option>
                            <option value="bi-droplet-half">Lavado</option>
                            <option value="bi-patch-check">Control de calidad</option>
                            <option value="bi-box-seam">Desarme</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="tll_dep_nuevo_diagnostico">
                            <label class="form-check-label x-small text-muted" for="tll_dep_nuevo_diagnostico">
                                Es el departamento de diagnóstico (puede trabajar sin la aprobación del cliente)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="tllGuardarDepartamentoRapido()">
                    <i class="bi bi-check2-circle me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!--
    Alta rápida de un punto del checklist de recepción (accesorios, carrocería,
    documentos o niveles). Queda en la plantilla para las próximas órdenes y se
    agrega de una vez al checklist de la orden abierta.
-->
<div class="modal fade" id="modalTallerAccesorioRapido" tabindex="-1" data-bs-backdrop="static" style="z-index:1070;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-list-check me-1 text-primary"></i>Nuevo ítem de recepción</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="x-small fw-bold text-muted mb-1">Grupo</label>
                        <select id="tll_acc_nuevo_grupo" class="form-select form-select-sm">
                            <option value="accesorios">Accesorios</option>
                            <option value="carroceria">Carrocería</option>
                            <option value="documentos">Documentos</option>
                            <option value="niveles">Niveles</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="x-small fw-bold text-muted mb-1">Ítem <span class="text-danger">*</span></label>
                        <input type="text" id="tll_acc_nuevo_item" class="form-control form-control-sm" maxlength="150" placeholder="Ej. Llanta de emergencia">
                    </div>
                    <div class="col-12">
                        <div class="x-small text-muted">
                            Queda en la plantilla del taller para las próximas órdenes y se suma al
                            checklist de esta, que se persiste al guardar la orden.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-3" onclick="tllGuardarAccesorioRapido()">
                    <i class="bi bi-check2-circle me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>
