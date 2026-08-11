<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $permCampus */
/** @var array $permNiveles */
/** @var array $permClientes */
$permCampus   = $permCampus   ?? [];
$permNiveles  = $permNiveles  ?? [];
$permClientes = $permClientes ?? [];
?>
<style>
    .alu-grid th, .alu-grid td { vertical-align: middle; padding: 4px 6px; font-size: 0.8rem; }
    .alu-grid thead th { background: #f8f9fa; }
    .input-alu { padding: 0 4px; height: 26px; font-size: 0.78rem; }
    .row-alu .remove-row { opacity: 0.25; transition: opacity .15s; }
    .row-alu:hover .remove-row { opacity: 1; }
    .alu-typeahead-dropdown { position: absolute; z-index: 4000; max-height: 220px; overflow-y: auto; display: none; width: 260px; }
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="formAlumnoModal" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-mortarboard-fill me-2 text-primary"></i>
                        <span id="tituloModalAlumno">Nuevo Alumno</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <?php if (!empty($permCampus['crear']) || !empty($permNiveles['crear']) || !empty($permClientes['crear'])): ?>
                <!-- Barra de Acciones Superior -->
                <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap">
                    <?php if (!empty($permClientes['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="abrirModalClienteCrear()" title="Registrar nuevo cliente"><i class="bi bi-person-plus fs-6"></i> Nuevo cliente</button>
                    <?php endif; ?>
                    <?php if (!empty($permCampus['crear']) || !empty($permNiveles['crear'])): ?>
                        <div class="vr mx-1"></div>
                    <?php endif; ?>
                    <?php if (!empty($permCampus['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalCampusCrear()" title="Registrar nuevo campus"><i class="bi bi-geo-alt fs-6"></i> Nuevo campus</button>
                    <?php endif; ?>
                    <?php if (!empty($permNiveles['crear'])): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm px-2" onclick="window.abrirModalNivelCrear()" title="Registrar nuevo nivel/curso"><i class="bi bi-mortarboard fs-6"></i> Nuevo nivel/curso</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="px-3 pt-2 bg-light border-bottom">
                    <div id="modalAlertAlumno" class="alert d-none mb-2 py-2 small shadow-sm border-0"></div>
                    <ul class="nav nav-tabs border-bottom-0 flex-nowrap overflow-auto" id="tabsAlumno" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#tab-general" role="tab"><i class="bi bi-person me-1"></i>General</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" id="tab-representante-btn" data-bs-toggle="tab" href="#tab-representante" role="tab"><i class="bi bi-person-badge me-1"></i>Representante</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" id="tab-matricula-btn" data-bs-toggle="tab" href="#tab-matricula" role="tab"><i class="bi bi-journal-bookmark me-1"></i>Matrícula</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" id="tab-horario-btn" data-bs-toggle="tab" href="#tab-horario" role="tab"><i class="bi bi-clock-history me-1"></i>Horario</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" id="tab-salud-btn" data-bs-toggle="tab" href="#tab-salud" role="tab"><i class="bi bi-heart-pulse me-1"></i>Salud</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" id="tab-servicios-btn" data-bs-toggle="tab" href="#tab-servicios" role="tab"><i class="bi bi-cart-check me-1"></i>Servicios</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small disabled" id="tab-documentos-btn" data-bs-toggle="tab" href="#tab-documentos" role="tab"><i class="bi bi-paperclip me-1"></i>Documentos</a></li>
                    </ul>
                </div>

                <div class="modal-body pt-3" style="max-height: 65vh; overflow-y: auto;">
                    <input type="hidden" name="id" id="alu_id" value="">

                    <div class="tab-content">
                        <!-- ============ TAB: DATOS GENERALES ============ -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3 text-center">
                                    <label class="form-label small fw-bold text-muted mb-1 d-block">Foto</label>
                                    <img id="alu_foto_preview" src="<?= BASE_URL ?>/img/no-image.png" class="rounded-circle border mb-2" style="width:90px;height:90px;object-fit:cover;" onerror="this.style.display='none'">
                                    <input type="file" class="form-control form-control-sm shadow-none" id="alu_foto_input" accept="image/*">
                                    <input type="hidden" name="foto_ruta" id="alu_foto_ruta">
                                </div>
                                <div class="col-md-9">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Código de alumno</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="codigo_alumno" id="alu_codigo" maxlength="30" placeholder="Autogenerado si se deja vacío">
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
                                            <label class="form-label small fw-bold text-muted mb-1">Tipo Identificación</label>
                                            <select class="form-select form-select-sm shadow-none" name="tipo_identificacion" id="alu_tipo_id">
                                                <option value="">-- Seleccione --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Número Identificación</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="numero_identificacion" id="alu_identificacion" maxlength="20">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Fecha de nacimiento</label>
                                            <input type="date" class="form-control form-control-sm shadow-none" name="fecha_nacimiento" id="alu_fecha_nacimiento">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Sexo</label>
                                            <select class="form-select form-select-sm shadow-none" name="sexo" id="alu_sexo">
                                                <option value="">-- Seleccione --</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Femenino</option>
                                                <option value="O">Otro</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Nacionalidad</label>
                                            <input type="text" class="form-control form-control-sm shadow-none" name="nacionalidad" id="alu_nacionalidad" maxlength="80">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Estado académico</label>
                                            <select class="form-select form-select-sm shadow-none" name="estado_academico" id="alu_estado_academico">
                                                <option value="activo">Activo</option>
                                                <option value="retirado">Retirado</option>
                                                <option value="egresado">Egresado</option>
                                                <option value="suspendido">Suspendido</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted mb-1">Observaciones</label>
                                    <textarea class="form-control form-control-sm shadow-none" name="observaciones" id="alu_observaciones" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ============ TAB: REPRESENTANTE Y FACTURACIÓN ============ -->
                        <div class="tab-pane fade" id="tab-representante" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-8 position-relative">
                                    <label class="form-label small fw-bold text-muted mb-1">Cliente / Representante que factura *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" id="alu_cliente_texto" placeholder="Buscar cliente por nombre o identificación..." autocomplete="off" required>
                                    <input type="hidden" name="id_cliente" id="alu_id_cliente">
                                    <div id="alu_cliente_dropdown" class="list-group shadow-sm alu-typeahead-dropdown"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted mb-1">Relación con el alumno</label>
                                    <select class="form-select form-select-sm shadow-none" name="relacion_representante" id="alu_relacion">
                                        <option value="">-- Seleccione --</option>
                                        <option value="padre">Padre</option>
                                        <option value="madre">Madre</option>
                                        <option value="tutor">Tutor</option>
                                        <option value="abuelo">Abuelo/a</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Serie / Punto de emisión preferido</label>
                                    <select class="form-select form-select-sm shadow-none" name="id_punto_emision" id="alu_punto_emision">
                                        <option value="">-- Usar el predeterminado de la empresa --</option>
                                    </select>
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
                                                <th style="width:16%;">Campus</th>
                                                <th style="width:16%;">Nivel/Curso</th>
                                                <th style="width:10%;">Año lectivo</th>
                                                <th style="width:12%;">Ingreso</th>
                                                <th style="width:12%;">Salida</th>
                                                <th style="width:14%;">Motivo salida</th>
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
                                    <span class="text-muted small ms-2">Solo puede existir un período sin fecha de salida (matrícula vigente).</span>
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

                        <!-- ============ TAB: SERVICIOS Y PRODUCTOS ============ -->
                        <div class="tab-pane fade" id="tab-servicios" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 320px;">
                                    <table class="table table-sm alu-grid mb-0 text-nowrap" id="tablaServicios">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th style="width:34%;">Producto / Servicio</th>
                                                <th style="width:12%;">Cantidad</th>
                                                <th style="width:16%;">Frecuencia</th>
                                                <th style="width:16%;">Precio (opcional)</th>
                                                <th style="width:10%;" class="text-center">Activo</th>
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
                        </div>

                        <!-- ============ TAB: DOCUMENTOS ============ -->
                        <div class="tab-pane fade" id="tab-documentos" role="tabpanel">
                            <div class="row g-2 align-items-end mb-3">
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
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="window.aluSubirDocumento()">
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

<?php include MVC_APP . '/views/modulos/alumnos_campus/modal_campus.php'; ?>
<?php include MVC_APP . '/views/modulos/alumnos_niveles/modal_nivel.php'; ?>
<?php include MVC_APP . '/views/modulos/clientes/modal_cliente.php'; ?>

<script src="<?= BASE_URL ?>/js/modulos/alumnos_modal.js?v=<?= time() ?>"></script>
