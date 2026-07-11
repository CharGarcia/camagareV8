<?php
/** @var array $perm */
/** @var string $rutaModulo */
/** @var array $vistaConfig */
/** @var array $bancos */

// Asegurar consistencia si el modal se incluye desde otro módulo
$idUsuarioAct = (int)($_SESSION['id_usuario'] ?? 0);
$idEmpresaAct = (int)($_SESSION['id_empresa'] ?? 0);
$nivelAct = (int)($_SESSION['nivel'] ?? 1);

// 1. Forzar vistaConfig de empleados
$vistaConfigEmp = \App\Helpers\PreferenciasHelper::getPreferenciasVista('empleados');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigEmp, 'estiloVistaPestanasEmp');

// 2. Forzar permisos de empleados
$permEmp = $perm ?? [];
if (($rutaModulo ?? '') !== 'modulos/empleados') {
    $modelPerm = new \App\models\PermisoSubmodulo();
    $idSubEmp = $modelPerm->getIdSubmoduloPorRutaMvc('modulos/empleados');
    if ($idSubEmp) {
        $mapPerm = $modelPerm->getPermisosDeUsuario($idUsuarioAct, $idEmpresaAct);
        if (isset($mapPerm[$idSubEmp])) {
            $p = $mapPerm[$idSubEmp];
            $permEmp = [
                'ver' => !empty($p['ver']),
                'crear' => !empty($p['crear']),
                'actualizar' => !empty($p['actualizar']),
                'eliminar' => !empty($p['eliminar']),
                'todo' => !empty($p['t']),
            ];
        } else if ($nivelAct >= 3) {
            $permEmp = ['ver' => true, 'crear' => true, 'actualizar' => true, 'eliminar' => true, 'todo' => true];
        }
    }
}

$urlBaseEmpShared = BASE_URL . '/modulos/empleados';
?>
<style>
    /* Ancho del modal: un poco más angosto que modal-xl, pero suficiente para las pestañas */
    #modalEmpleado .modal-dialog { max-width: 1000px; }
    /* Grilla estilo "detalle de factura" para Periodos y Rubros Fijos */
    #modalEmpleado .emp-grid th {
        font-size: 0.7rem !important;
        text-transform: uppercase;
        background-color: #f8f9fa;
        padding: 4px 8px !important;
    }
    #modalEmpleado .emp-grid td {
        padding: 0 !important;
        vertical-align: middle;
    }
    #modalEmpleado .input-emp {
        border: none;
        background: transparent;
        height: 30px !important;
        font-size: 0.82rem !important;
        padding: 2px 8px !important;
        border-radius: 0;
        box-shadow: none;
    }
    #modalEmpleado .input-emp:focus {
        background: #fff;
        box-shadow: inset 0 0 0 1px #0d6efd;
        outline: none;
    }
    #modalEmpleado .row-emp:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
    #modalEmpleado .emp-grid .remove-row {
        color: #dc3545;
        opacity: 0;
        transition: opacity 0.2s;
    }
    #modalEmpleado .emp-grid .row-emp:hover .remove-row {
        opacity: 1;
    }
</style>
<!-- Modal Empleado -->
<div class="modal fade" id="modalEmpleado" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <form id="formEmpleado" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-badge-fill me-2 text-primary"></i>
                        <span id="tituloModal">Nuevo Empleado</span>
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modalAlert" class="alert d-none mx-3 mt-3 mb-0 py-2 small shadow-sm border-0"></div>
                    <input type="hidden" name="id" id="emp_id" value="">

                    <!-- Barra de Acciones Superior -->
                    <div class="d-flex gap-1 align-items-center flex-wrap px-3 py-2 border-bottom bg-white">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnEmpPdf" onclick="window.imprimirEmpleadoPdf()" title="Imprimir ficha del empleado (PDF)">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="tabsEmpleado" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-2 small" id="tab-general-btn" data-bs-toggle="tab" href="#tab-general" role="tab"><i class="bi bi-person me-1"></i>General</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-laboral-btn" data-bs-toggle="tab" href="#tab-laboral" role="tab"><i class="bi bi-briefcase me-1"></i>Laboral</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-puesto-btn" data-bs-toggle="tab" href="#tab-puesto" role="tab"><i class="bi bi-person-workspace me-1"></i>Puesto</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-financiera-btn" data-bs-toggle="tab" href="#tab-financiera" role="tab"><i class="bi bi-bank me-1"></i>Banco</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-periodos-btn" data-bs-toggle="tab" href="#tab-periodos" role="tab"><i class="bi bi-clock-history me-1"></i>Periodos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-rubros-btn" data-bs-toggle="tab" href="#tab-rubros" role="tab"><i class="bi bi-calculator me-1"></i>Rubros Fijos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-horario-btn" data-bs-toggle="tab" href="#tab-horario" role="tab"><i class="bi bi-clock-history me-1"></i>Horario</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-credenciales-btn" data-bs-toggle="tab" href="#tab-credenciales" role="tab" onclick="window.empCredCargar && window.empCredCargar()"><i class="bi bi-person-vcard me-1"></i>Credenciales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 small" id="tab-atrasos-btn" data-bs-toggle="tab" href="#tab-atrasos" role="tab"><i class="bi bi-clock-history me-1"></i>Atrasos</a>
                            </li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasConfigEmp = [
                                'tab-laboral-btn' => 'Laboral',
                                'tab-puesto-btn' => 'Puesto',
                                'tab-financiera-btn' => 'Bancarios',
                                'tab-periodos-btn' => 'Historial',
                                'tab-rubros-btn' => 'Rubros Fijos'
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigEmp, $vistaConfigEmp ?? [], 'empleados');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>

                    <div class="tab-content border-top px-3 py-3" id="tabsEmpleadoContent" style="overflow: visible !important;">
                        <!-- Panel General -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                            <div class="row g-3">
                                <!-- Fila 1: Tipo ID · Identificación · Sexo · Fecha Nacimiento · Estado -->
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Tipo ID * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_tipo_id', 'tipo_id') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_id" id="emp_tipo_id" required>
                                        <option value="cedula">Cédula</option>
                                        <option value="pasaporte">Pasaporte</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center gap-1">
                                        Identificación *
                                        <span id="empSriBadge" class="badge d-none" style="font-size:0.6rem;"></span>
                                        <span id="empSriSpinner" class="spinner-border spinner-border-sm text-secondary d-none" style="width:0.75rem;height:0.75rem;" role="status" aria-hidden="true"></span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm shadow-none fw-bold" name="identificacion" id="emp_identificacion" required autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Sexo <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_sexo', 'sexo') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="sexo" id="emp_sexo">
                                        <option value="M">M</option>
                                        <option value="F">F</option>
                                        <option value="O">O</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Fecha Nacimiento</label>
                                    <input type="date" class="form-control form-control-sm shadow-none" name="fecha_nacimiento" id="emp_fecha_nacimiento">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_estado', 'estado') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="estado" id="emp_estado">
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                                <!-- Fila 2: Nombres y Apellidos · Correo · Teléfono -->
                                <div class="col-md-5">
                                    <label class="form-label mb-1 small fw-bold text-muted">Nombres y Apellidos *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="nombres_apellidos" id="emp_nombres_apellidos" required placeholder="JUAN PEREZ">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Correo Electrónico</label>
                                    <input type="email" class="form-control form-control-sm shadow-none" name="email" id="emp_email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="telefono" id="emp_telefono">
                                </div>
                                <!-- Fila 3: Dirección · Contacto de Emergencia -->
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Dirección</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="direccion" id="emp_direccion">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Contacto de Emergencia</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="contacto_emergencia" id="emp_contacto_emergencia" placeholder="Nombre y teléfono">
                                </div>
                            </div>
                        </div>

                        <!-- Panel Laboral -->
                        <div class="tab-pane fade" id="tab-laboral" role="tabpanel">
                            <div class="row g-3">
                                <!-- Fila 1: Fondos de Reserva · Décimo Tercero · Décimo Cuarto · Aporta al IESS -->
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Fondos de Reserva <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_fondos_reserva', 'fondos_reserva') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="fondos_reserva" id="emp_fondos_reserva">
                                        <option value="no_se_paga">No se paga</option>
                                        <option value="rol">En Rol Mensual</option>
                                        <option value="planilla">Planilla IESS</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Décimo Tercero <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_decimo_tercero', 'decimo_tercero') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="decimo_tercero" id="emp_decimo_tercero">
                                        <option value="acumula">Acumula</option>
                                        <option value="mensualiza">Mensualiza</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Décimo Cuarto <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_decimo_cuarto', 'decimo_cuarto') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="decimo_cuarto" id="emp_decimo_cuarto">
                                        <option value="acumula">Acumula</option>
                                        <option value="mensualiza">Mensualiza</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Aporta al IESS <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_aporta_iess', 'aporta_iess') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="aporta_iess" id="emp_aporta_iess" onchange="window.toggleIessFields()">
                                        <option value="si">Sí</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <!-- Fila 2: Aporte Personal · Aporte Patronal (condicionales) · Sueldo Base · V. Semanal · V. Quincena -->
                                <div class="col-md-3 col-aporte-iess">
                                    <label class="form-label mb-1 small fw-bold text-muted">Aporte Personal (%)</label>
                                    <input type="number" step="0.0001" class="form-control form-control-sm shadow-none iess-field" name="aporte_personal" id="emp_aporte_personal">
                                </div>
                                <div class="col-md-3 col-aporte-iess">
                                    <label class="form-label mb-1 small fw-bold text-muted">Aporte Patronal (%)</label>
                                    <input type="number" step="0.0001" class="form-control form-control-sm shadow-none iess-field" name="aporte_patronal" id="emp_aporte_patronal">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Sueldo Base ($)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none fw-bold" name="sueldo_base" id="emp_sueldo_base">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">V. Semanal</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none" name="valor_semanal" id="emp_valor_semanal">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">V. Quincena</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none" name="valor_quincena" id="emp_valor_quincena">
                                </div>
                            </div>
                        </div>

                        <!-- Panel Puesto -->
                        <div class="tab-pane fade" id="tab-puesto" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Región <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_region', 'region') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="region" id="emp_region">
                                        <option value="costa">Costa</option>
                                        <option value="sierra">Sierra</option>
                                        <option value="oriente">Oriente</option>
                                        <option value="insular">Insular</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Departamento</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="departamento" id="emp_departamento">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Cargo</label>
                                    <input type="text" class="form-control form-control-sm shadow-none text-uppercase" name="cargo" id="emp_cargo">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Lugar de Trabajo</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="lugar_trabajo" id="emp_lugar_trabajo">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted">Cód. Sectorial IESS</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="codigo_sectorial_iess" id="emp_codigo_sectorial_iess">
                                </div>
                            </div>
                        </div>

                        <!-- Panel Bancario -->
                        <div class="tab-pane fade" id="tab-financiera" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label mb-1 small fw-bold text-muted">Banco <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_id_banco_ecuador', 'id_banco_ecuador') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="id_banco_ecuador" id="emp_id_banco_ecuador">
                                        <option value="0">-- Seleccionar --</option>
                                        <?php foreach ($bancos ?? [] as $b): ?>
                                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nombre_banco']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Tipo Cuenta <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_tipo_cuenta', 'tipo_cuenta') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_cuenta" id="emp_tipo_cuenta">
                                        <option value="ahorros">Ahorros</option>
                                        <option value="corriente">Corriente</option>
                                        <option value="virtual">Virtual</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Número Cuenta</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="numero_cuenta" id="emp_numero_cuenta">
                                </div>
                            </div>
                        </div>

                        <!-- Tablas Dinámicas -->
                        <div class="tab-pane fade" id="tab-periodos" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm emp-grid mb-0 text-nowrap" id="tablaPeriodos">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:30%;">Ingreso</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:30%;">Salida</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:40%;">Motivo</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.agregarFilaPeriodo()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar periodo
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="periodos_json" id="periodos_json">
                        </div>

                        <div class="tab-pane fade" id="tab-rubros" role="tabpanel">
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm emp-grid mb-0 text-nowrap" id="tablaRubros">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:22%;">Tipo</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:40%;">Nombre</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:18%;">Valor</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:20%;">IESS</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.agregarFilaRubro()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar rubro
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="rubros_json" id="rubros_json">
                        </div>

                        <!-- Panel Horario: asignación de turno + punto por vigencia -->
                        <div class="tab-pane fade" id="tab-horario" role="tabpanel">
                            <p class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Asigna a este empleado su turno y (opcional) su punto de servicio, con vigencia. El motor de Jornadas usa el turno vigente para calcular atrasos, extras y faltas.</p>
                            <div class="border rounded overflow-hidden">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm emp-grid mb-0 text-nowrap" id="tablaAsignaciones">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:32%;">Turno</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:28%;">Punto de servicio</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:18%;">Vigente desde</th>
                                                <th class="py-2 small fw-bold text-muted" style="width:18%;">Vigente hasta</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="p-2 border-top bg-light">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="window.agregarFilaAsignacion()">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar asignación
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="asignaciones_horario_json" id="asignaciones_horario_json" value="[]">
                        </div>

                        <!-- Panel Credenciales: credencial QR personal + rostro -->
                        <div class="tab-pane fade" id="tab-credenciales" role="tabpanel">
                            <div id="empCredNoGuardado" class="alert alert-secondary small py-2 mb-0 d-none">
                                <i class="bi bi-info-circle me-1"></i> Guarda el empleado primero para generar su credencial de asistencia.
                            </div>
                            <div id="empCredContenido" class="row g-3 d-none">
                                <!-- Credencial QR -->
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-bold small"><i class="bi bi-qr-code me-1 text-primary"></i>Credencial personal (QR)</span>
                                            <span id="empCredEstado"></span>
                                        </div>
                                        <p class="text-muted small mb-2">El empleado abre este QR una vez en su celular para vincular su credencial. Luego marca escaneando el QR del punto de servicio.</p>
                                        <div class="text-center">
                                            <div id="empCredSpinner" class="py-4 d-none"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                                            <img id="empCredImg" src="" alt="QR" class="img-fluid rounded-3 border shadow-sm d-none" style="max-width:220px;">
                                            <div id="empCredSinQr" class="py-3 text-muted small">Aún no se ha generado la credencial.</div>
                                        </div>
                                        <div id="empCredLinkWrap" class="mt-2 p-2 bg-light rounded-3 border d-none">
                                            <small class="text-muted d-block mb-1">Enlace personal:</small>
                                            <span id="empCredLink" class="small text-primary text-break"></span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 mt-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.empCredGenerar()"><i class="bi bi-qr-code me-1"></i><span id="empCredBtnTxt">Generar QR</span></button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="empCredBtnCopiar" onclick="window.empCredCopiar()"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="empCredBtnImprimir" onclick="window.empCredImprimir()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                                            <button type="button" class="btn btn-outline-warning btn-sm d-none" id="empCredBtnRegen" onclick="window.empCredRegenerar()"><i class="bi bi-arrow-repeat me-1"></i>Regenerar</button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Rostro (opcional) -->
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-bold small"><i class="bi bi-person-badge me-1 text-info"></i>Rostro (opcional)</span>
                                            <span id="empRostroEstado"></span>
                                        </div>
                                        <p class="text-muted small mb-3">Confirma que quien marca es el empleado. Se guarda un vector facial (no una foto). Requiere consentimiento del empleado (LOPDP).</p>
                                        <button type="button" class="btn btn-outline-info btn-sm" id="empRostroBtnAbrir" onclick="window.empAbrirRostro()"><i class="bi bi-camera me-1"></i><span id="empRostroBtnTxt">Registrar rostro</span></button>
                                        <!-- Área de captura inline (sin modal anidado) -->
                                        <div id="empRostroCaptura" class="d-none mt-3">
                                            <div style="position:relative;width:100%;max-width:240px;aspect-ratio:3/4;margin:0 auto;background:#000;border-radius:12px;overflow:hidden;">
                                                <video id="empRostroVideo" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);"></video>
                                            </div>
                                            <div id="empRostroMsg" class="small text-muted mt-2" style="min-height:20px;"></div>
                                            <div class="form-check d-flex align-items-center gap-1 mt-1">
                                                <input class="form-check-input" type="checkbox" id="empRostroConsent">
                                                <label class="form-check-label small" for="empRostroConsent">El empleado autoriza el registro de su rostro (LOPDP).</label>
                                            </div>
                                            <div class="d-flex gap-1 mt-2">
                                                <button type="button" class="btn btn-info btn-sm text-white" id="empRostroBtnCapturar" onclick="window.empCapturarRostro()"><i class="bi bi-camera me-1"></i>Capturar y guardar</button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.empCerrarRostro()">Cancelar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Atrasos: tratamiento de atrasos del empleado -->
                        <div class="tab-pane fade" id="tab-atrasos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label mb-1 small fw-bold text-muted">Tratamiento de atrasos</label>
                                    <select class="form-select form-select-sm shadow-none" name="atraso_modo" id="emp_atraso_modo">
                                        <option value="descuento">Se descuenta según horas</option>
                                        <option value="no_descuenta" selected>No se descuenta</option>
                                        <option value="informativo_reg">Solo informativo</option>
                                    </select>
                                </div>
                            </div>

                            <div class="accordion accordion-flush border rounded mt-3" id="accAtrasoAyuda">
                              <div class="accordion-item">
                                <h2 class="accordion-header">
                                  <button class="accordion-button collapsed small py-2 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAtrasoAyuda" aria-expanded="false" aria-controls="collapseAtrasoAyuda">
                                    <i class="bi bi-question-circle me-2 text-primary"></i>Aquí la explicación con ejemplo
                                  </button>
                                </h2>
                                <div id="collapseAtrasoAyuda" class="accordion-collapse collapse" data-bs-parent="#accAtrasoAyuda">
                                  <div class="accordion-body small">
                                <p class="mb-2"><i class="bi bi-info-circle me-1 text-primary"></i><b>¿Qué es?</b> Si el empleado marca su asistencia, el sistema calcula sus jornadas y acumula los <b>minutos de atraso</b> del mes. Al usar «Generar Novedades» (módulo Jornadas), esos atrasos se convierten (o no) en una novedad para el rol según esta regla:</p>
                                <ul class="mb-2 ps-3">
                                    <li><b>Se descuenta según horas:</b> crea una novedad de <b>Descuento</b> = horas de atraso × (sueldo base ÷ 240). El 240 = horas laborables al mes (8 h × 30 días).</li>
                                    <li><b>No se descuenta:</b> no genera ninguna novedad. El atraso queda visible solo en Jornadas.</li>
                                    <li><b>Solo informativo:</b> genera una novedad de <b>registro con valor $0</b> (no descuenta), para que quede constancia del atraso en el rol.</li>
                                </ul>
                                <div class="p-2 bg-white rounded border">
                                    <b class="d-block mb-1"><i class="bi bi-lightbulb me-1 text-warning"></i>Ejemplo</b>
                                    Empleado con sueldo base <b>$480</b> que acumula <b>3 horas</b> de atraso en el mes:
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                <tr><td style="width:190px;"><b>Se descuenta según horas</b></td><td>3 h × ($480 ÷ 240) = 3 × $2.00 = <b>$6.00</b> → novedad Descuento de $6.00.</td></tr>
                                                <tr><td><b>No se descuenta</b></td><td>Nada. El atraso solo se ve en Jornadas.</td></tr>
                                                <tr><td><b>Solo informativo</b></td><td>Novedad de registro por las <b>3 h</b> con valor <b>$0.00</b> (no afecta el pago).</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="mt-2 text-muted"><i class="bi bi-exclamation-circle me-1"></i>Solo aplica si el empleado usa el módulo de asistencia (marca y se generan jornadas).</div>
                                  </div>
                                </div>
                              </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="btnEliminarModal" onclick="window.eliminarRegistroModal()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardar">
                            <i class="bi bi-check2-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $urlEmpMod = rtrim(BASE_URL, '/') . '/modulos/empleados'; $baseEmp = rtrim(BASE_URL, '/'); ?>

<!-- Hoja de impresión del QR personal -->
<style>
@media print {
    body.emp-print-cred * { visibility: hidden !important; }
    #empCredHoja, #empCredHoja * { visibility: visible !important; }
    #empCredHoja { position: fixed !important; inset: 0 !important; display: flex !important; margin: 0 !important; }
}
#empCredHoja { display: none; flex-direction: column; align-items: center; justify-content: center; padding: 80px 40px; width: 100%; min-height: 100dvh; font-family: Arial, sans-serif; text-align: center; background: #fff; }
#empCredHoja .n { font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; }
#empCredHoja .s { color: #444; margin-bottom: 28px; }
#empCredHoja img { width: 300px; height: 300px; }
</style>
<div id="empCredHoja">
    <div class="n" id="empCredPrintNombre"></div>
    <div class="s">Abre este QR una sola vez en tu celular para vincular tu credencial</div>
    <img id="empCredPrintImg" src="" alt="QR">
</div>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
<script>window.CASIS_FACE_MODELS = window.CASIS_FACE_MODELS || null;</script>
<script src="<?= $baseEmp ?>/js/modulos/face_asistencia.js?v=<?= time() ?>"></script>
<script>
(function () {
    'use strict';
    const urlBase = '<?= $urlEmpMod ?>';
    const $ = (id) => document.getElementById(id);
    const swalErr = (m) => window.Swal ? Swal.fire({ icon: 'error', title: 'Error', text: m }) : alert(m);
    let linkActual = null, nombreActual = '';
    let stream = null, modelsReady = false;

    const empId = () => ($('emp_id') ? $('emp_id').value : '');
    const empNombre = () => ($('emp_nombres_apellidos') ? $('emp_nombres_apellidos').value : ($('emp_identificacion') ? $('emp_identificacion').value : 'Empleado'));

    // Carga el estado de la credencial al abrir la pestaña.
    window.empCredCargar = function () {
        const id = empId();
        const noG = $('empCredNoGuardado'), cont = $('empCredContenido');
        if (!id) { noG.classList.remove('d-none'); cont.classList.add('d-none'); return; }
        noG.classList.add('d-none'); cont.classList.remove('d-none');
        nombreActual = empNombre();
        fetch(`${urlBase}/credencialAjax?id=${id}`).then(r => r.json()).then(j => {
            if (!j.ok) { swalErr(j.error); return; }
            if (j.tiene_credencial) empCredPintar(j.link); else empCredSinCred();
            empRostroPintar(j.tiene_rostro);
        }).catch(() => swalErr('Error de red.'));
    };

    function empCredSinCred() {
        linkActual = null;
        $('empCredEstado').innerHTML = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Sin credencial</span>';
        $('empCredImg').classList.add('d-none'); $('empCredSinQr').classList.remove('d-none');
        $('empCredLinkWrap').classList.add('d-none');
        $('empCredBtnTxt').textContent = 'Generar QR';
        ['empCredBtnCopiar','empCredBtnImprimir','empCredBtnRegen'].forEach(i => $(i).classList.add('d-none'));
    }

    function empCredPintar(link) {
        linkActual = link;
        $('empCredEstado').innerHTML = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle me-1"></i>Vinculada</span>';
        const img = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(link)}&size=260x260&margin=10`;
        const el = $('empCredImg'), sp = $('empCredSpinner');
        $('empCredSinQr').classList.add('d-none');
        sp.classList.remove('d-none'); el.classList.add('d-none');
        el.onload = () => { sp.classList.add('d-none'); el.classList.remove('d-none'); };
        el.src = img;
        $('empCredPrintImg').src = img;
        $('empCredPrintNombre').textContent = nombreActual;
        $('empCredLink').textContent = link;
        $('empCredLinkWrap').classList.remove('d-none');
        $('empCredBtnTxt').textContent = 'Ver QR';
        ['empCredBtnCopiar','empCredBtnImprimir','empCredBtnRegen'].forEach(i => $(i).classList.remove('d-none'));
    }

    function empRostroPintar(tiene) {
        $('empRostroEstado').innerHTML = tiene
            ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><i class="bi bi-person-badge me-1"></i>Enrolado</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">—</span>';
        $('empRostroBtnTxt').textContent = tiene ? 'Actualizar rostro' : 'Registrar rostro';
    }

    window.empCredGenerar = function () {
        const id = empId(); if (!id) return;
        const fd = new FormData(); fd.append('id_empleado', id);
        fetch(`${urlBase}/generarCredencialAjax`, { method: 'POST', body: fd })
            .then(r => r.json()).then(j => { if (!j.ok) { swalErr(j.error); return; } empCredPintar(j.link); })
            .catch(() => swalErr('Error de red.'));
    };

    window.empCredRegenerar = function () {
        const id = empId(); if (!id) return;
        const run = () => {
            const fd = new FormData(); fd.append('id_empleado', id);
            fetch(`${urlBase}/regenerarCredencialAjax`, { method: 'POST', body: fd })
                .then(r => r.json()).then(j => { if (!j.ok) { swalErr(j.error); return; } empCredPintar(j.link); window.Swal && Swal.fire({ icon: 'success', title: 'QR regenerado', timer: 1200, showConfirmButton: false }); });
        };
        if (window.Swal) Swal.fire({ icon: 'warning', title: '¿Regenerar credencial?', text: 'El QR anterior dejará de funcionar en el celular del empleado.', showCancelButton: true, confirmButtonText: 'Regenerar', cancelButtonText: 'Cancelar', reverseButtons: true }).then(r => { if (r.isConfirmed) run(); });
        else if (confirm('¿Regenerar credencial?')) run();
    };

    window.empCredCopiar = function () {
        if (linkActual && navigator.clipboard) navigator.clipboard.writeText(linkActual)
            .then(() => window.Swal && Swal.fire({ icon: 'success', title: 'Copiado', timer: 1000, showConfirmButton: false }));
    };

    window.empCredImprimir = function () {
        document.body.classList.add('emp-print-cred');
        const cleanup = () => { document.body.classList.remove('emp-print-cred'); window.removeEventListener('afterprint', cleanup); };
        window.addEventListener('afterprint', cleanup);
        window.print();
    };

    // ── Rostro (inline) ──
    window.empAbrirRostro = async function () {
        const id = empId(); if (!id) { swalErr('Guarda el empleado primero.'); return; }
        $('empRostroCaptura').classList.remove('d-none');
        $('empRostroConsent').checked = false;
        $('empRostroMsg').textContent = 'Cargando modelos faciales...';
        try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }); $('empRostroVideo').srcObject = stream; }
        catch (e) { $('empRostroMsg').textContent = 'No se pudo abrir la cámara.'; return; }
        try { await window.CASIS_FACE.loadModels(); modelsReady = true; $('empRostroMsg').textContent = 'Listo. Presiona «Capturar y guardar».'; }
        catch (e) { modelsReady = false; $('empRostroMsg').textContent = 'No se pudieron cargar los modelos faciales (revisa la conexión).'; }
    };

    window.empCerrarRostro = function () {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        $('empRostroCaptura').classList.add('d-none');
        $('empRostroMsg').textContent = '';
    };

    window.empCapturarRostro = async function () {
        const id = empId(); if (!id) return;
        if (!$('empRostroConsent').checked) { swalErr('Marca el consentimiento del empleado.'); return; }
        if (!modelsReady) { swalErr('Los modelos faciales no están listos.'); return; }
        const btn = $('empRostroBtnCapturar'); btn.disabled = true;
        const video = $('empRostroVideo'); const muestras = [];
        try {
            for (let i = 0; i < 3; i++) {
                $('empRostroMsg').textContent = `Capturando muestra ${i + 1} de 3...`;
                const d = await window.CASIS_FACE.descriptor(video);
                if (d) muestras.push(d);
                await new Promise(r => setTimeout(r, 400));
            }
        } catch (e) {}
        if (muestras.length === 0) { $('empRostroMsg').textContent = ''; btn.disabled = false; swalErr('No se detectó ningún rostro. Acércate a la cámara con buena luz.'); return; }
        const desc = window.CASIS_FACE.promediar(muestras);
        const fd = new FormData();
        fd.append('id_empleado', id); fd.append('descriptor', JSON.stringify(desc)); fd.append('consentimiento', '1');
        try {
            const r = await fetch(`${urlBase}/enrolarRostroAjax`, { method: 'POST', body: fd });
            const j = await r.json(); btn.disabled = false;
            if (j.ok) { window.empCerrarRostro(); empRostroPintar(true); window.Swal && Swal.fire({ icon: 'success', title: j.msg, timer: 1400, showConfirmButton: false }); }
            else swalErr(j.error);
        } catch (e) { btn.disabled = false; swalErr('Error de red al guardar.'); }
    };

    // Al cerrar el modal, apagar la cámara si quedó encendida.
    const modalEl = document.getElementById('modalEmpleado');
    if (modalEl) modalEl.addEventListener('hidden.bs.modal', () => window.empCerrarRostro());
})();
</script>
