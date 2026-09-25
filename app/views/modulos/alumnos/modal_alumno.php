<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $permCampus */
/** @var array $permNiveles */
/** @var array $permClientes */
$permCampus   = $permCampus   ?? [];
$permNiveles  = $permNiveles  ?? [];
$permClientes = $permClientes ?? [];
// Generar factura desde el alumno exige poder CREAR en Facturas de Venta (el
// documento nace allí); el PDF de cada factura, poder VERLAS.
$aluPuedeFacturar  = \App\Helpers\Permisos::puedeCrear('modulos/factura-venta');
$aluPuedeVerFactura = \App\Helpers\Permisos::puedeVer('modulos/factura-venta');

// Pestañas que cada usuario puede ocultar (misma pieza que el modal de Proveedores).
// General y Facturación no entran: llevan los campos obligatorios y, al fallar
// la validación, guardarAlumnoModal() salta a ellas.
$vistaConfigAlu = \App\Helpers\PreferenciasHelper::getPreferenciasVista('alumnos');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigAlu, 'estiloVistaPestanasAlu');
$pestanasConfigAlu = [
    'tab-representante' => 'Representantes',
    'tab-facturas'   => 'Transacciones',
    'tab-matricula'  => 'Matrícula',
    'tab-horario'    => 'Horario',
    'tab-salud'      => 'Salud',
    'tab-documentos' => 'Documentos',
];
?>
<style>
    .alu-grid th, .alu-grid td { vertical-align: middle; padding: 4px 6px; font-size: 0.8rem; }
    .alu-grid thead th { background: #f8f9fa; }
    .input-alu { padding: 0 4px; height: 26px; font-size: 0.78rem; }
    .row-alu .remove-row { opacity: 0.25; transition: opacity .15s; }
    .row-alu:hover .remove-row { opacity: 1; }
    /* Listas de autocompletado: colgadas del <body> (position: fixed) por encima de
       todos los modales (5060 / 6060); las posiciona CMG_anclarDropdown. */
    .alu-typeahead-dropdown { position: fixed; z-index: 7000; max-height: 260px; overflow-y: auto; }
    #modalAlumno .nav-link.disabled { pointer-events: none; opacity: .5; }

    /* ── Apilado de modales sobre el modal de Alumno ──
       app.css fuerza `.modal { z-index: 5060 !important }` a TODOS los modales,
       lo que deja los submodales (nuevo cliente / campus / nivel) por detrás
       del modal de Alumno. Mismo patrón que app/views/modulos/compras/index.php:
       `.modal:not(#modalAlumno)` incluye un ID y gana en especificidad al
       `.modal` global, elevando cualquier modal que se abra encima. */
    .modal:not(#modalAlumno) {
        z-index: 6060 !important;
    }

    .modal-backdrop ~ .modal-backdrop {
        z-index: 6055 !important;
    }
</style>

<div class="modal fade" id="modalAlumno" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0">
            <form id="formAlumnoModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-mortarboard-fill me-2 text-primary"></i>
                        <span id="tituloModalAlumno">Nuevo Alumno</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <?php if (!empty($permCampus['crear']) || !empty($permNiveles['crear']) || !empty($permClientes['crear']) || $aluPuedeFacturar): ?>
                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <?php if (!empty($permClientes['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i></button>
                    <?php endif; ?>
                    <?php if (!empty($permCampus['crear']) || !empty($permNiveles['crear'])): ?>
                        <div class="vr mx-1"></div>
                    <?php endif; ?>
                    <?php if (!empty($permCampus['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalCampusCrear()" title="Registrar nuevo campus"><i class="bi bi-geo-alt fs-6"></i></button>
                    <?php endif; ?>
                    <?php if (!empty($permNiveles['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalNivelCrear()" title="Registrar nuevo nivel/curso"><i class="bi bi-mortarboard fs-6"></i></button>
                    <?php endif; ?>
                    <?php if ($aluPuedeFacturar): ?>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-outline-success btn-sm px-2" id="btnAluGenerarFactura" onclick="window.aluAbrirGenerarFactura()" title="Generar factura de los servicios del alumno"><i class="bi bi-receipt-cutoff fs-6"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="px-3 pt-2 bg-light border-bottom">
                    <div class="d-flex align-items-center">
                        <?php // data-bs-target es obligatorio: el CSS de pestañas ocultas lo usa para esconder el botón. ?>
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap overflow-auto" id="tabsAlumno" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#tab-general" href="#tab-general" role="tab"><i class="bi bi-person me-1"></i>General</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-representante-btn" data-bs-toggle="tab" data-bs-target="#tab-representante" href="#tab-representante" role="tab"><i class="bi bi-people me-1"></i>Representantes</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-facturacion-btn" data-bs-toggle="tab" data-bs-target="#tab-facturacion" href="#tab-facturacion" role="tab"><i class="bi bi-receipt me-1"></i>Facturación</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-facturas-btn" data-bs-toggle="tab" data-bs-target="#tab-facturas" href="#tab-facturas" role="tab"><i class="bi bi-list-check me-1"></i>Transacciones</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-matricula-btn" data-bs-toggle="tab" data-bs-target="#tab-matricula" href="#tab-matricula" role="tab"><i class="bi bi-journal-bookmark me-1"></i>Matrícula</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-horario-btn" data-bs-toggle="tab" data-bs-target="#tab-horario" href="#tab-horario" role="tab"><i class="bi bi-clock-history me-1"></i>Horario</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-salud-btn" data-bs-toggle="tab" data-bs-target="#tab-salud" href="#tab-salud" role="tab"><i class="bi bi-heart-pulse me-1"></i>Salud</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="tab-documentos-btn" data-bs-toggle="tab" data-bs-target="#tab-documentos" href="#tab-documentos" role="tab"><i class="bi bi-paperclip me-1"></i>Documentos</a></li>
                        </ul>
                        <div class="pb-1 ps-2 flex-shrink-0">
                            <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigAlu, $vistaConfigAlu, 'alumnos', '__pestanas_ocultas__', 'estiloVistaPestanasAlu') ?>
                        </div>
                    </div>
                </div>

                <?php // Sin scroll propio: si el contenido no cabe, scrollea el modal completo (igual que Proveedores). ?>
                <div class="modal-body pt-3">
                    <input type="hidden" name="id" id="alu_id" value="">

                    <div class="tab-content">
                        <!-- ============ TAB: DATOS GENERALES ============ -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Tipo Identificación <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('alumnos', 'alu_tipo_id', 'tipo_identificacion') ?></label>
                                            <select class="form-select form-select-sm shadow-none" name="tipo_identificacion" id="alu_tipo_id">
                                                <option value="">-- Seleccione --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between align-items-center">
                                                <span>Número Identificación</span>
                                                <span id="aluSriBadge" class="badge d-none"></span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm shadow-none" name="numero_identificacion" id="alu_identificacion" maxlength="20" autocomplete="off">
                                                <span class="input-group-text bg-white px-2 d-none" id="aluSriSpinner">
                                                    <span class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Consultando...</span></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Estado académico <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('alumnos', 'alu_estado_academico', 'estado_academico') ?></label>
                                            <select class="form-select form-select-sm shadow-none" name="estado_academico" id="alu_estado_academico">
                                                <option value="activo">Activo</option>
                                                <option value="retirado">Retirado</option>
                                                <option value="egresado">Egresado</option>
                                                <option value="suspendido">Suspendido</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Nombres *</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="nombres" id="alu_nombres" required maxlength="150">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Apellidos *</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="apellidos" id="alu_apellidos" required maxlength="150">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Sexo <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('alumnos', 'alu_sexo', 'sexo') ?></label>
                                            <select class="form-select form-select-sm shadow-none" name="sexo" id="alu_sexo">
                                                <option value="">-- Seleccione --</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Femenino</option>
                                                <option value="O">Otro</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Fecha de nacimiento</label>
                                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha_nacimiento" id="alu_fecha_nacimiento">
                                        </div>
                                        <?php // Campus y nivel NO son columnas del alumno: editan el período de
                                              // matrícula vigente (alumnos_periodos). Sin name: los serializa
                                              // serializarTablas() dentro de periodos_json. ?>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Campus</label>
                                            <select class="form-select form-select-sm shadow-none sel-campus" id="alu_campus_actual"></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Nivel / Curso</label>
                                            <select class="form-select form-select-sm shadow-none sel-nivel" id="alu_nivel_actual"></select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Nacionalidad <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('alumnos', 'alu_nacionalidad', 'nacionalidad') ?></label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="nacionalidad" id="alu_nacionalidad" maxlength="80">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-bold text-muted mb-1">Observaciones</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="observaciones" id="alu_observaciones">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============ TAB: REPRESENTANTES Y AUTORIZADOS A RETIRAR ============ -->
                        <div class="tab-pane fade" id="tab-representante" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm alu-grid mb-0 text-nowrap" id="tablaRepresentantes">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th style="width:28%;">Nombres y apellidos</th>
                                                <th style="width:13%;">Identificación</th>
                                                <th style="width:13%;">Teléfono</th>
                                                <th style="width:13%;">Relación</th>
                                                <th style="width:9%;" class="text-center" title="Autorizado a retirar al alumno del centro educativo">Puede retirar</th>
                                                <th>Observación</th>
                                                <th style="width:30px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.aluAgregarFilaRepresentante()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar representante
                                    </button>
                                    <span class="text-muted small ms-2">Marque «Puede retirar» en las personas autorizadas a retirar al alumno del centro educativo.</span>
                                </div>
                            </div>
                            <input type="hidden" name="representantes_json" id="representantes_json">
                        </div>

                        <!-- ============ TAB: FACTURACIÓN (cliente que factura + servicios) ============ -->
                        <div class="tab-pane fade" id="tab-facturacion" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8 position-relative">
                                    <label class="form-label small fw-bold text-muted mb-1">Cliente que factura *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" id="alu_cliente_texto" placeholder="Buscar cliente por nombre o identificación..." autocomplete="off" required>
                                    <input type="hidden" name="id_cliente" id="alu_id_cliente">
                                    <div id="alu_cliente_dropdown" class="list-group shadow alu-typeahead-dropdown d-none"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1 d-flex align-items-center">Serie <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('alumnos', 'alu_punto_emision', 'id_punto_emision') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="id_punto_emision" id="alu_punto_emision"></select>
                                </div>
                            </div>

                            <?php // Servicios y productos que se le facturan (antes, pestaña Servicios aparte). ?>
                            <div class="small fw-bold text-muted mt-3 mb-1"><i class="bi bi-cart-check me-1"></i>Servicios y productos a facturar</div>
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm alu-grid mb-0 text-nowrap" id="tablaServicios">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th style="width:28%;">Producto / Servicio</th>
                                                <th style="width:22%;" title="Texto que sale bajo la descripción del ítem en la factura. Admite marcadores como {alumno} o {MES}">Detalle del ítem</th>
                                                <th style="width:8%;">Cantidad</th>
                                                <th style="width:10%;">Precio</th>
                                                <th style="width:11%;">IVA</th>
                                                <th style="width:9%;" class="text-end">Total</th>
                                                <th style="width:6%;" class="text-center">Activo</th>
                                                <th style="width:30px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.aluAgregarFilaServicio()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar servicio/producto
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="servicios_json" id="servicios_json">

                            <div class="row g-3 mt-1">
                                <!-- Información adicional de la factura (concepto / detalle) -->
                                <div class="col-md-8">
                                    <div class="small fw-bold text-muted mb-1"><i class="bi bi-info-circle me-1"></i>Información adicional de la factura</div>
                                    <div class="border rounded overflow-hidden">
                                        <table class="table table-sm alu-grid mb-0" id="tablaInfoAdicional">
                                            <thead>
                                                <tr class="border-bottom">
                                                    <th style="width:35%;">Concepto</th>
                                                    <th>Detalle</th>
                                                    <th style="width:30px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                        <div class="p-2 border-top bg-light">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.aluAgregarFilaInfo()">
                                                <i class="bi bi-plus-circle me-1"></i> Agregar información
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="info_adicional_json" id="info_adicional_json">
                                    <datalist id="aluConceptosInfo">
                                        <option value="{niño}">Niño o Niña (según el sexo del alumno)</option>
                                        <option value="Alumno"></option>
                                        <option value="Estudiante"></option>
                                        <option value="Cédula alumno"></option>
                                        <option value="Curso"></option>
                                        <option value="Campus"></option>
                                        <option value="Período"></option>
                                    </datalist>
                                    <div class="text-muted mt-1" style="font-size:.72rem;">
                                        Marcadores (en el detalle del ítem y aquí): <code>{niño}</code> (Niño / Niña según el sexo) <code>{alumno}</code> <code>{nombres}</code> <code>{apellidos}</code> <code>{cedula}</code> <code>{campus}</code> <code>{curso}</code> <code>{mes}</code> <code>{MES}</code> <code>{anio}</code> <code>{mes_anio}</code>. El correo del cliente se agrega solo.
                                    </div>
                                </div>
                                <!-- Totales, como en la Factura de Venta (solo servicios activos) -->
                                <div class="col-md-4">
                                    <div class="bg-white border rounded p-2 shadow-sm" style="font-size:0.75rem;">
                                        <div class="d-flex justify-content-between align-items-center mb-1 fw-bold border-bottom pb-1">
                                            <span class="text-muted">Subtotal</span>
                                            <span id="aluLblSubtotal">0.00</span>
                                        </div>
                                        <div id="aluLblSubtotalesIva" class="mb-1"></div>
                                        <div id="aluLblIvas" class="mb-1"></div>
                                        <hr class="my-1 opacity-25">
                                        <div class="d-flex justify-content-between align-items-center bg-light border py-1 px-2 rounded">
                                            <span class="fw-bold text-dark" style="font-size:0.8rem;">TOTAL</span>
                                            <span class="fw-bold text-dark" style="font-size:1rem;" id="aluLblTotal">0.00</span>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size:.7rem;">Solo servicios activos. Es lo que saldría en la factura del mes.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============ TAB: MATRÍCULA ============ -->
                        <div class="tab-pane fade" id="tab-matricula" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm alu-grid mb-0 text-nowrap" id="tablaPeriodos">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th style="width:14%;">Año lectivo</th>
                                                <th style="width:16%;">Ingreso</th>
                                                <th style="width:16%;">Salida</th>
                                                <th style="width:20%;">Motivo salida</th>
                                                <th>Observación</th>
                                                <th style="width:30px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.aluAgregarFilaPeriodo()">
                                        <i class="bi bi-plus-circle me-1"></i> Matricular / agregar período
                                    </button>
                                    <span class="text-muted small ms-2">Solo puede existir un período sin fecha de salida (matrícula vigente). Su campus y nivel se eligen en la pestaña General.</span>
                                </div>
                            </div>
                            <input type="hidden" name="periodos_json" id="periodos_json">
                        </div>

                        <!-- ============ TAB: HORARIO ============ -->
                        <div class="tab-pane fade" id="tab-horario" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm alu-grid mb-0 text-nowrap" id="tablaHorarios">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th style="width:18%;">Día</th>
                                                <th style="width:16%;">Hora inicio</th>
                                                <th style="width:16%;">Hora fin</th>
                                                <th style="width:16%;">Jornada</th>
                                                <th>Observación</th>
                                                <th style="width:30px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.aluAgregarFilaHorario()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar horario
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="horarios_json" id="horarios_json">
                        </div>

                        <!-- ============ TAB: SALUD Y EMERGENCIA ============ -->
                        <div class="tab-pane fade" id="tab-salud" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Tipo de sangre</label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_sangre" id="alu_tipo_sangre">
                                        <option value="">-- N/D --</option>
                                        <option>A+</option><option>A-</option>
                                        <option>B+</option><option>B-</option>
                                        <option>AB+</option><option>AB-</option>
                                        <option>O+</option><option>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label small fw-bold text-muted mb-1">Alergias / condiciones médicas</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="alergias_condiciones" id="alu_alergias" placeholder="Ej. Alergia a la penicilina">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Contacto de emergencia</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="contacto_emergencia_nombre" id="alu_emerg_nombre" placeholder="Nombre">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Teléfono de emergencia</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="contacto_emergencia_telefono" id="alu_emerg_telefono">
                                </div>
                            </div>
                        </div>

                        <!-- ============ TAB: TRANSACCIONES (lo facturado, por ítem, con su factura) ============ -->
                        <?php // El id sigue siendo tab-facturas: así se respeta a quien ya la ocultó desde el engranaje. ?>
                        <div class="tab-pane fade" id="tab-facturas" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm alu-grid mb-0 text-nowrap">
                                    <thead>
                                        <tr class="border-bottom">
                                            <th>Mes</th>
                                            <th>Emisión</th>
                                            <th>Factura</th>
                                            <th>Estado</th>
                                            <th>Código</th>
                                            <th>Producto / Servicio</th>
                                            <th class="text-end">Cant.</th>
                                            <th class="text-end">Precio</th>
                                            <th class="text-end">Desc.</th>
                                            <th class="text-end">Subtotal</th>
                                            <th class="text-end">IVA</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyFacturasAlumno">
                                        <tr><td colspan="12" class="text-center text-muted py-3 small">Guarde el alumno para ver sus transacciones.</td></tr>
                                    </tbody>
                                    <tfoot id="tfootFacturasAlumno" class="d-none"></tfoot>
                                </table>
                            </div>
                            <div class="small text-muted mt-2">Cada fila es un producto o servicio facturado al alumno, con la factura en que salió (clic en el número para su PDF). Las facturas se generan con el botón <i class="bi bi-receipt-cutoff"></i> de la barra superior; nacen en borrador y se envían al SRI desde Facturas de Venta. Las anuladas se ven tachadas y no suman.</div>
                        </div>

                        <!-- ============ TAB: DOCUMENTOS ============ -->
                        <div class="tab-pane fade" id="tab-documentos" role="tabpanel">
                            <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom">
                                <img id="alu_foto_preview" src="<?= BASE_URL ?>/img/no-image.png" class="rounded-circle border" style="width:90px;height:90px;object-fit:cover;" onerror="this.style.display='none'">
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-bold text-muted mb-1 d-block">Foto del alumno</label>
                                    <input type="file" class="form-control form-control-sm shadow-none" id="alu_foto_input" accept="image/*">
                                    <input type="hidden" name="foto_ruta" id="alu_foto_ruta">
                                </div>
                            </div>
                            <div class="row g-2 align-items-end mb-3" id="alu_doc_adjuntar">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Tipo de documento</label>
                                    <select class="form-select form-select-sm shadow-none" id="alu_doc_tipo">
                                        <option value="partida_nacimiento">Partida de nacimiento</option>
                                        <option value="cedula">Cédula / Identificación</option>
                                        <option value="foto_carnet">Foto carnet</option>
                                        <option value="certificado_medico">Certificado médico</option>
                                        <option value="contrato">Contrato</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-muted mb-1">Archivo (PDF, JPG, PNG)</label>
                                    <input type="file" class="form-control form-control-sm shadow-none" id="alu_doc_archivo" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100" id="alu_doc_btn_adjuntar" onclick="window.aluSubirDocumento()">
                                        <i class="bi bi-upload me-1"></i> Adjuntar
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm alu-grid mb-0">
                                    <thead>
                                        <tr class="border-bottom">
                                            <th>Tipo</th>
                                            <th>Archivo</th>
                                            <th>Fecha</th>
                                            <th style="width:30px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyDocumentosAlumno">
                                        <tr><td colspan="4" class="text-center text-muted py-3 small">Guarde el alumno para adjuntar documentos.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarAlumnoModal" onclick="eliminarAlumnoModal()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarAlumnoModal" onclick="guardarAlumnoModal()">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($aluPuedeFacturar): ?>
<!-- Modal: Generar factura del alumno (una por cliente) -->
<div class="modal fade" id="modalAluGenerarFactura" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Generar factura</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted mb-1">Serie *</label>
                        <select class="form-select form-select-sm shadow-none" id="aluGfSerie"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Mes facturado *</label>
                        <input type="month" class="form-control form-control-sm shadow-none" id="aluGfMes">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted mb-1" title="Se usa en los servicios que no tienen «Detalle del ítem» propio">Texto para ítems sin detalle</label>
                        <input type="text" class="form-control form-control-sm shadow-none" id="aluGfTexto" maxlength="300" placeholder="Ej. {MES} {anio}">
                    </div>
                </div>
                <div id="aluGfAvisos"></div>
                <div class="border rounded overflow-hidden">
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-sm alu-grid mb-0 text-nowrap">
                            <thead>
                                <tr class="border-bottom">
                                    <th style="width:30px;" class="text-center"><input type="checkbox" class="form-check-input" id="aluGfTodas" title="Marcar / desmarcar todas"></th>
                                    <th>Producto / Servicio</th>
                                    <th class="text-end">Cant.</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Total</th>
                                    <th>Cliente</th>
                                </tr>
                            </thead>
                            <tbody id="aluGfLineas"></tbody>
                        </table>
                    </div>
                </div>
                <div id="aluGfResumen" class="small mt-2"></div>
                <div class="small text-muted mt-2">Se genera la factura al <strong>cliente que factura</strong>, en borrador, con la fecha de hoy. Se factura lo <strong>guardado</strong> en la pestaña Facturación. En el texto puede usar {mes}, {MES}, {anio}, {mes_anio}.</div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                <button type="button" class="btn btn-success btn-sm px-4" id="aluGfBtnGenerar" onclick="window.aluGenerarFactura()"><i class="bi bi-receipt-cutoff me-1"></i> Generar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>
    window.ALU_FACT_CFG = {
        puedeFacturar: <?= $aluPuedeFacturar ? 'true' : 'false' ?>,
        urlPdfFactura: <?= $aluPuedeVerFactura ? json_encode(BASE_URL . '/modulos/factura-venta/exportarPdfAjax') : 'null' ?>
    };
</script>

<script src="<?= rtrim(BASE_URL, '/') ?>/js/components/dropdown_flotante.js?v=<?= asset_ver('/js/components/dropdown_flotante.js') ?>"></script>

<?php include MVC_APP . '/views/modulos/alumnos_campus/modal_campus.php'; ?>
<?php include MVC_APP . '/views/modulos/alumnos_niveles/modal_nivel.php'; ?>
<?php include MVC_APP . '/views/modulos/clientes/modal_cliente.php'; ?>
<!-- clientes_modal.js NO lo carga modal_cliente.php (es compartido por varios
     módulos): cada vista que incluye el modal debe cargarlo, igual que
     factura_venta/index.php. Sin esto abrirModalClienteCrear() no existe y el
     botón de nuevo cliente de la barra de acciones no hace nada. -->
<script src="<?= BASE_URL ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>

<script src="<?= BASE_URL ?>/js/modulos/alumnos_modal.js?v=<?= asset_ver('/js/modulos/alumnos_modal.js') ?>"></script>
