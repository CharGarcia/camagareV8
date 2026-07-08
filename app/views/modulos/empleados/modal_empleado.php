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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Tipo ID * <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_tipo_id', 'tipo_id') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="tipo_id" id="emp_tipo_id" required>
                                        <option value="cedula">Cédula</option>
                                        <option value="pasaporte">Pasaporte</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center gap-1">
                                        Identificación *
                                        <span id="empSriBadge" class="badge d-none" style="font-size:0.6rem;"></span>
                                        <span id="empSriSpinner" class="spinner-border spinner-border-sm text-secondary d-none" style="width:0.75rem;height:0.75rem;" role="status" aria-hidden="true"></span>
                                    </label>
                                    <input type="text" class="form-control form-control-sm shadow-none fw-bold" name="identificacion" id="emp_identificacion" required autocomplete="off">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_estado', 'estado') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="estado" id="emp_estado">
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-10">
                                    <label class="form-label mb-1 small fw-bold text-muted">Nombres y Apellidos *</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="nombres_apellidos" id="emp_nombres_apellidos" required placeholder="JUAN PEREZ">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-1 small fw-bold text-muted d-flex align-items-center">Sexo <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_sexo', 'sexo') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="sexo" id="emp_sexo">
                                        <option value="M">M</option>
                                        <option value="F">F</option>
                                        <option value="O">O</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Correo Electrónico</label>
                                    <input type="email" class="form-control form-control-sm shadow-none" name="email" id="emp_email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Fecha Nacimiento</label>
                                    <input type="date" class="form-control form-control-sm shadow-none" name="fecha_nacimiento" id="emp_fecha_nacimiento">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small fw-bold text-muted">Teléfono</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="telefono" id="emp_telefono">
                                </div>
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
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Fondos de Reserva <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_fondos_reserva', 'fondos_reserva') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="fondos_reserva" id="emp_fondos_reserva">
                                        <option value="no_se_paga">No se paga</option>
                                        <option value="rol">En Rol Mensual</option>
                                        <option value="planilla">Planilla IESS</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Aporta al IESS <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_aporta_iess', 'aporta_iess') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="aporta_iess" id="emp_aporta_iess" onchange="window.toggleIessFields()">
                                        <option value="si">Sí</option>
                                        <option value="no">No</option>
                                    </select>
                                </div>
                                <div class="col-12" id="container-aportes-iess">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label mb-1 small fw-bold text-muted">Aporte Personal (%)</label>
                                            <input type="number" step="0.0001" class="form-control form-control-sm shadow-none iess-field" name="aporte_personal" id="emp_aporte_personal">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label mb-1 small fw-bold text-muted">Aporte Patronal (%)</label>
                                            <input type="number" step="0.0001" class="form-control form-control-sm shadow-none iess-field" name="aporte_patronal" id="emp_aporte_patronal">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Décimo Tercero <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_decimo_tercero', 'decimo_tercero') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="decimo_tercero" id="emp_decimo_tercero">
                                        <option value="acumula">Acumula</option>
                                        <option value="mensualiza">Mensualiza</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Décimo Cuarto <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_decimo_cuarto', 'decimo_cuarto') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="decimo_cuarto" id="emp_decimo_cuarto">
                                        <option value="acumula">Acumula</option>
                                        <option value="mensualiza">Mensualiza</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">Sueldo Base ($)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none fw-bold" name="sueldo_base" id="emp_sueldo_base">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">V. Semanal</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none" name="valor_semanal" id="emp_valor_semanal">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 small fw-bold text-muted">V. Quincena</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm shadow-none" name="valor_quincena" id="emp_valor_quincena">
                                </div>
                            </div>
                        </div>

                        <!-- Panel Puesto -->
                        <div class="tab-pane fade" id="tab-puesto" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Región <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('empleados', 'emp_region', 'region') ?></label>
                                    <select class="form-select form-select-sm shadow-none" name="region" id="emp_region">
                                        <option value="costa">Costa</option>
                                        <option value="sierra">Sierra</option>
                                        <option value="oriente">Oriente</option>
                                        <option value="insular">Insular</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Departamento</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="departamento" id="emp_departamento">
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-1 small fw-bold text-muted">Cargo</label>
                                    <input type="text" class="form-control form-control-sm shadow-none text-uppercase" name="cargo" id="emp_cargo">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Lugar de Trabajo</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="lugar_trabajo" id="emp_lugar_trabajo">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 small fw-bold text-muted">Horario de Trabajo</label>
                                    <input type="text" class="form-control form-control-sm shadow-none" name="horario_trabajo" id="emp_horario_trabajo" placeholder="Ej: 08:00-17:00">
                                </div>
                                <div class="col-12">
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
