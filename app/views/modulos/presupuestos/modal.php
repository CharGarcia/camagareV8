<?php
/** @var array $perm @var array $vistaConfig @var string $rutaModulo @var array $centrosCosto @var array $proyectos @var bool $puedeAprobar */
$anioActual = (int) date('Y');
$pestanasPre = [
    'pane-pre-datos'     => 'Datos',
    'pane-pre-grilla'    => 'Presupuesto',
    'pane-pre-ejecucion' => 'Ejecución',
    'pane-pre-versiones' => 'Versiones',
];
?>
<style>
    .modal-pre label { font-size: .83rem; font-weight: 600; color: #495057; margin-bottom: 3px !important; }
    .modal-pre .nav-tabs .nav-link { font-size: .875rem; }
    /* Grilla: filas compactas, inputs sin borde, totales en negrita */
    .pre-grilla-wrap { overflow: auto; max-height: calc(100vh - 360px); }
    .pre-grilla { font-size: .78rem; min-width: 900px; }
    .pre-grilla thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; white-space: nowrap; box-shadow: 0 1px 0 #dee2e6; }
    .pre-grilla td { padding: 0 !important; vertical-align: middle; }
    .pre-grilla td.pre-fija { padding: 0 6px !important; white-space: nowrap; }
    .pre-grilla td.pre-cuenta { position: sticky; left: 0; background: #fff; z-index: 1; max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
    .pre-grilla thead th.pre-cuenta { left: 0; z-index: 3; }
    .pre-grilla input.pre-monto { width: 92px; height: 22px; font-size: .78rem; padding: 0 4px; text-align: right; border: 0; background: transparent; }
    .pre-grilla input.pre-monto:focus { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    .pre-grilla tr.pre-ingreso td.pre-cuenta { border-left: 3px solid #198754; }
    .pre-grilla tr.pre-gasto td.pre-cuenta { border-left: 3px solid #dc3545; }
    .pre-grilla tfoot td { font-weight: 700; background: #f8f9fa; padding: 2px 6px !important; white-space: nowrap; }
    .pre-grilla .pre-total-fila { font-weight: 700; padding: 0 6px !important; white-space: nowrap; }
    .pre-grilla .btn-quitar { opacity: 0; color: #dc3545; }
    .pre-grilla tr:hover .btn-quitar { opacity: 1; }
    .pre-grilla select.pre-rubro { height: 22px; font-size: .74rem; padding: 0 4px; border: 0; background: transparent; width: 130px; }
    /* Ejecución */
    .pre-ejec { font-size: .78rem; }
    .pre-ejec td, .pre-ejec th { white-space: nowrap; }
    .pre-ejec td.pre-click { cursor: pointer; text-decoration: underline dotted; }
    .pre-ejec tr.pre-rubro-fila td { background: #f1f3f5; font-weight: 700; }
    .pre-ejec .pre-pct { display: inline-block; min-width: 56px; text-align: right; border-radius: 3px; padding: 0 4px; }
    .pre-pct.verde { background: #d1e7dd; color: #0f5132; } .pre-pct.amarillo { background: #fff3cd; color: #664d03; }
    .pre-pct.rojo { background: #f8d7da; color: #842029; } .pre-pct.gris, .pre-pct.futuro { background: #e9ecef; color: #6c757d; }
    .pre-dropdown { z-index: 1090; max-height: 240px; overflow-y: auto; }
    .pre-sem { display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; margin-right:4px; }
    .pre-sem-verde { background:#198754; } .pre-sem-amarillo { background:#ffc107; } .pre-sem-rojo { background:#dc3545; } .pre-sem-gris, .pre-sem-futuro { background:#ced4da; }
</style>

<div class="modal fade modal-pre" id="modalPre" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formPre" novalidate>
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold fs-6">
                        <i class="bi bi-calculator text-primary me-2"></i>
                        <span id="pre-titulo">Nuevo presupuesto</span>
                        <span id="pre-badge-estado" class="badge ms-2 d-none"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <input type="hidden" id="pre_id" name="id" value="">

                    <!-- Barra de acciones de documento -->
                    <div class="px-3 py-2 bg-light border-bottom d-flex gap-1 align-items-center flex-wrap" id="pre-acciones">
                        <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="PRE.pdf()" title="PDF del presupuesto (versión mostrada)"><i class="bi bi-file-earmark-pdf fs-6"></i></button>
                        <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="PRE.excel()" title="Excel del presupuesto (versión mostrada)"><i class="bi bi-file-earmark-spreadsheet fs-6"></i></button>
                        <div class="vr mx-1"></div>
                        <span class="small text-muted" id="pre-version-label"></span>
                        <div class="ms-auto d-flex gap-1 flex-wrap" id="pre-acciones-version">
                            <?php if ($puedeAprobar): ?>
                                <button type="button" class="btn btn-success btn-sm px-2 d-none" id="pre-btn-aprobar" onclick="PRE.abrirAprobar()" title="Aprobar la versión en borrador (requiere Acceso total)">
                                    <i class="bi bi-check2-circle me-1"></i>Aprobar
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($perm['actualizar'])): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm px-2 d-none" id="pre-btn-reforma" onclick="PRE.abrirReforma()" title="Nueva versión en borrador copiando la vigente">
                                    <i class="bi bi-pencil-square me-1"></i>Reforma
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm px-2 d-none" id="pre-btn-cerrar" onclick="PRE.cambiarEstado('cerrado')" title="Cerrar el presupuesto (ya no se reforma)">
                                    <i class="bi bi-lock me-1"></i>Cerrar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm px-2 d-none" id="pre-btn-reabrir" onclick="PRE.cambiarEstado('aprobado')" title="Reabrir el presupuesto">
                                    <i class="bi bi-unlock me-1"></i>Reabrir
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($perm['eliminar'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm px-2 d-none" id="pre-btn-descartar-reforma" onclick="PRE.descartarReforma()" title="Descartar la reforma en borrador">
                                    <i class="bi bi-x-circle me-1"></i>Descartar reforma
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" id="pre-tab-datos-btn" data-bs-toggle="tab" href="#pane-pre-datos" role="tab"><i class="bi bi-card-text me-1"></i>Datos</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="pre-tab-grilla-btn" data-bs-toggle="tab" href="#pane-pre-grilla" role="tab"><i class="bi bi-table me-1"></i>Presupuesto</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="pre-tab-ejecucion-btn" data-bs-toggle="tab" href="#pane-pre-ejecucion" role="tab"><i class="bi bi-speedometer2 me-1"></i>Ejecución</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" id="pre-tab-versiones-btn" data-bs-toggle="tab" href="#pane-pre-versiones" role="tab"><i class="bi bi-clock-history me-1"></i>Versiones</a></li>
                        </ul>
                        <div class="pb-1 flex-shrink-0">
                            <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasPre, $vistaConfig ?? [], basename($rutaModulo)) ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light"></div>

                    <div class="tab-content border-top px-3 py-3">

                        <!-- ══ Datos ══ -->
                        <div class="tab-pane fade show active" id="pane-pre-datos" role="tabpanel">
                            <!-- Fila 1: nombre + período (el tipo y sus fechas siempre caben en la misma fila: 5+2+2+2 = 11 con Desde/Hasta) -->
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label for="pre_nombre">Nombre *</label>
                                    <input type="text" class="form-control form-control-sm" id="pre_nombre" name="nombre" maxlength="150" required placeholder="Presupuesto 2027">
                                </div>
                                <div class="col-md-2">
                                    <label for="pre_tipo_periodo">Período *</label>
                                    <select class="form-select form-select-sm" id="pre_tipo_periodo" name="tipo_periodo" onchange="PRE.onTipoPeriodo()">
                                        <option value="anual">Anual</option>
                                        <option value="mensual">Un mes</option>
                                        <option value="personalizado">Personalizado</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="pre-wrap-anio">
                                    <label for="pre_anio">Año *</label>
                                    <input type="number" class="form-control form-control-sm" id="pre_anio" name="anio" min="2000" max="2100" value="<?= $anioActual + 1 ?>">
                                </div>
                                <div class="col-md-2 d-none" id="pre-wrap-mes">
                                    <label for="pre_mes">Mes *</label>
                                    <input type="month" class="form-control form-control-sm" id="pre_mes" name="mes">
                                </div>
                                <div class="col-md-2 d-none" id="pre-wrap-desde">
                                    <label for="pre_desde">Desde *</label>
                                    <input type="month" class="form-control form-control-sm" id="pre_desde" name="desde">
                                </div>
                                <div class="col-md-2 d-none" id="pre-wrap-hasta">
                                    <label for="pre_hasta">Hasta *</label>
                                    <input type="month" class="form-control form-control-sm" id="pre_hasta" name="hasta">
                                </div>
                            </div>

                            <!-- Fila 2: alcance + semáforo (2+4+2+2 = 10) -->
                            <div class="row g-2 mt-0">
                                <div class="col-md-2">
                                    <label for="pre_alcance">Alcance *</label>
                                    <select class="form-select form-select-sm" id="pre_alcance" name="alcance" onchange="PRE.onAlcance()">
                                        <option value="empresa">Toda la empresa</option>
                                        <option value="centro_costo">Centro de costo</option>
                                        <option value="proyecto">Proyecto</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-none" id="pre-wrap-cc">
                                    <label for="pre_id_centro_costo">Centro de costo *</label>
                                    <select class="form-select form-select-sm" id="pre_id_centro_costo" name="id_centro_costo">
                                        <option value="">- Seleccione -</option>
                                        <?php foreach ($centrosCosto as $c): ?>
                                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars(trim(($c['codigo'] ? $c['codigo'] . ' - ' : '') . $c['nombre'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-none" id="pre-wrap-pr">
                                    <label for="pre_id_proyecto">Proyecto *</label>
                                    <select class="form-select form-select-sm" id="pre_id_proyecto" name="id_proyecto">
                                        <option value="">- Seleccione -</option>
                                        <?php foreach ($proyectos as $p): ?>
                                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars(trim(($p['codigo'] ? $p['codigo'] . ' - ' : '') . $p['nombre'])) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="pre_umbral_amarillo" title="% de ejecución desde el que el semáforo se pone amarillo">Aviso desde %</label>
                                    <input type="number" class="form-control form-control-sm" id="pre_umbral_amarillo" name="umbral_amarillo" value="90" min="1" max="200" step="1">
                                </div>
                                <div class="col-md-2">
                                    <label for="pre_umbral_rojo" title="% de ejecución desde el que el semáforo se pone rojo">Exceso desde %</label>
                                    <input type="number" class="form-control form-control-sm" id="pre_umbral_rojo" name="umbral_rojo" value="100" min="1" max="300" step="1">
                                </div>
                            </div>

                            <!-- Fila 3: observaciones -->
                            <div class="row g-2 mt-0">
                                <div class="col-12">
                                    <label for="pre_observaciones">Observaciones</label>
                                    <textarea class="form-control form-control-sm" id="pre_observaciones" name="observaciones" rows="2"></textarea>
                                </div>
                                <div class="col-12 small text-muted" id="pre-aviso-aprobado" style="display:none">
                                    <i class="bi bi-info-circle me-1"></i>Con una versión aprobada, el período y el alcance quedan fijos; se pueden cambiar el nombre, los umbrales y las observaciones.
                                </div>
                            </div>
                        </div>

                        <!-- ══ Presupuesto (grilla) ══ -->
                        <div class="tab-pane fade" id="pane-pre-grilla" role="tabpanel">
                            <div class="text-center text-muted small py-5" id="pre-grilla-vacio">
                                <i class="bi bi-table fs-3 d-block mb-2"></i>Guarde los datos del presupuesto para armar la grilla.
                            </div>
                            <div id="pre-grilla-wrap" class="d-none">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="pre-grilla-herramientas">
                                    <div class="position-relative" style="width: 360px; max-width: 100%;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" id="pre-buscar-cuenta" placeholder="Agregar cuenta: código o nombre…" autocomplete="off">
                                        </div>
                                        <div id="pre-dropdown-cuenta" class="list-group shadow position-absolute w-100 d-none pre-dropdown"></div>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" onclick="PRE.abrirCopiar()" title="Traer las cuentas y montos de otro presupuesto o versión"><i class="bi bi-files"></i> Copiar de…</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="PRE.abrirDesdeEjecutado()" title="Partir de lo realmente gastado en un período anterior"><i class="bi bi-graph-up"></i> Desde lo ejecutado…</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="PRE.plantilla()" title="Descargar la plantilla de Excel de este presupuesto"><i class="bi bi-download"></i> Plantilla</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('pre-archivo').click()" title="Cargar la plantilla llena"><i class="bi bi-upload"></i> Cargar Excel</button>
                                        <input type="file" id="pre-archivo" accept=".xlsx,.xls" class="d-none" onchange="PRE.importar(this)">
                                        <button type="button" class="btn btn-outline-secondary" onclick="PRE.gestionarRubros()" title="Crear, renombrar o eliminar rubros"><i class="bi bi-tags"></i> Rubros</button>
                                    </div>
                                    <div class="ms-auto small text-muted" id="pre-grilla-nota"></div>
                                </div>
                                <div class="alert alert-warning small py-2 d-none" id="pre-grilla-aviso"></div>
                                <div class="border rounded-3 bg-white pre-grilla-wrap">
                                    <table class="table table-sm table-hover mb-0 pre-grilla">
                                        <thead><tr id="pre-grilla-head"></tr></thead>
                                        <tbody id="pre-grilla-body"></tbody>
                                        <tfoot id="pre-grilla-foot"></tfoot>
                                    </table>
                                </div>
                                <div class="small text-muted mt-2">
                                    <i class="bi bi-lightbulb me-1"></i>Escriba el total anual en la columna <b>Total</b> y se reparte en partes iguales entre los meses. Las cuentas de grupo suman todas sus subcuentas al ejecutar.
                                </div>
                            </div>
                        </div>

                        <!-- ══ Ejecución ══ -->
                        <div class="tab-pane fade" id="pane-pre-ejecucion" role="tabpanel">
                            <div class="text-center text-muted small py-5" id="pre-ejec-vacio">
                                <i class="bi bi-speedometer2 fs-3 d-block mb-2"></i>La ejecución se compara con la última versión aprobada.
                            </div>
                            <div id="pre-ejec-wrap" class="d-none">
                                <div class="row g-2 mb-2" id="pre-ejec-kpis"></div>
                                <div class="d-flex align-items-center gap-2 mb-2 small">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-primary" id="pre-ejec-btn-cuentas" onclick="PRE.ejecVista('cuentas')">Por cuenta y mes</button>
                                        <button type="button" class="btn btn-outline-primary" id="pre-ejec-btn-rubros" onclick="PRE.ejecVista('rubros')">Por rubro</button>
                                    </div>
                                    <span class="text-muted ms-auto" id="pre-ejec-leyenda"></span>
                                </div>
                                <div class="border rounded-3 bg-white" style="overflow:auto; max-height: calc(100vh - 380px);">
                                    <table class="table table-sm table-hover mb-0 pre-ejec" id="pre-ejec-tabla"></table>
                                </div>
                                <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Haga clic en una cifra ejecutada para ver los asientos que la forman.</div>
                            </div>
                        </div>

                        <!-- ══ Versiones ══ -->
                        <div class="tab-pane fade" id="pane-pre-versiones" role="tabpanel">
                            <div class="border rounded-3 bg-white">
                                <table class="table table-sm table-hover mb-0 small">
                                    <thead class="table-light"><tr>
                                        <th class="ps-2">Versión</th><th>Estado</th><th>Motivo</th><th>Aprobada</th><th>Acta</th>
                                        <th class="text-end">Ingresos</th><th class="text-end">Gastos</th><th class="pe-2"></th>
                                    </tr></thead>
                                    <tbody id="pre-versiones-body"><tr><td colspan="8" class="text-center text-muted py-3">—</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['eliminar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="pre-btn-eliminar" onclick="PRE.eliminar()">
                                <i class="bi bi-trash3 me-1"></i>Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                        <?php if (!empty($perm['crear']) || !empty($perm['actualizar'])): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 d-none" id="pre-btn-guardar-grilla" onclick="PRE.guardarGrilla()">
                                <i class="bi bi-table me-1"></i>Guardar presupuesto
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4" id="pre-btn-guardar">
                                <i class="bi bi-check2-circle me-1"></i>Guardar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ Modal: aprobar versión ══ -->
<div class="modal fade" id="modalPreAprobar" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-2"><h6 class="modal-title fw-bold"><i class="bi bi-check2-circle me-2"></i>Aprobar versión</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-2">La versión queda congelada y pasa a ser la vigente. Las aprobadas anteriores quedan como reemplazadas.</p>
                <label for="pre_acta" class="small fw-bold">Acta o documento de aprobación *</label>
                <input type="text" class="form-control form-control-sm mb-2" id="pre_acta" maxlength="120" placeholder="Acta de asamblea N.º 12 / Resolución gerencia 2027-01">
                <label for="pre_obs_aprobacion" class="small fw-bold">Observación</label>
                <textarea class="form-control form-control-sm" id="pre_obs_aprobacion" rows="2"></textarea>
            </div>
            <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-success btn-sm px-3" onclick="PRE.aprobar()">Aprobar</button></div>
        </div>
    </div>
</div>

<!-- ══ Modal: reforma ══ -->
<div class="modal fade" id="modalPreReforma" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2"><h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Nueva reforma</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-2">Se crea una versión en borrador copiando la vigente. Edítela y apruébela; la vigente sigue igual hasta entonces.</p>
                <label for="pre_motivo" class="small fw-bold">Motivo de la reforma *</label>
                <textarea class="form-control form-control-sm" id="pre_motivo" rows="3" placeholder="Qué cambia y por qué"></textarea>
            </div>
            <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm px-3" onclick="PRE.crearReforma()">Crear reforma</button></div>
        </div>
    </div>
</div>

<!-- ══ Modal: copiar de / desde lo ejecutado ══ -->
<div class="modal fade" id="modalPreOrigen" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2"><h6 class="modal-title fw-bold" id="pre-origen-titulo">Copiar de otro presupuesto</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-3">
                <div id="pre-origen-copiar">
                    <label for="pre_origen_version" class="small fw-bold">Presupuesto y versión *</label>
                    <select class="form-select form-select-sm mb-2" id="pre_origen_version"></select>
                </div>
                <div id="pre-origen-ejecutado" class="d-none">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label for="pre_ref_desde" class="small fw-bold">Desde (mes) *</label><input type="month" class="form-control form-control-sm" id="pre_ref_desde"></div>
                        <div class="col-6"><label for="pre_ref_hasta" class="small fw-bold">Hasta (mes) *</label><input type="month" class="form-control form-control-sm" id="pre_ref_hasta"></div>
                    </div>
                    <p class="small text-muted mb-2">Se toman las cuentas con movimiento real en ese período (según la contabilidad) y se alinean mes a mes con este presupuesto.</p>
                </div>
                <label for="pre_origen_pct" class="small fw-bold">Ajuste %</label>
                <input type="number" class="form-control form-control-sm" id="pre_origen_pct" value="0" step="0.5" placeholder="Ej.: 5 = +5 %, -10 = -10 %">
                <p class="small text-muted mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Reemplaza las líneas de la grilla (no se graba hasta pulsar Guardar presupuesto).</p>
            </div>
            <div class="modal-footer py-2"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary btn-sm px-3" onclick="PRE.aplicarOrigen()">Traer a la grilla</button></div>
        </div>
    </div>
</div>

<!-- ══ Modal: asientos de una cifra ejecutada ══ -->
<div class="modal fade" id="modalPreAsientos" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2"><h6 class="modal-title fw-bold" id="pre-asientos-titulo">Asientos</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light"><tr><th class="ps-2">Fecha</th><th>Comprobante</th><th>Concepto</th><th>Origen</th><th>Cuenta</th><th class="text-end pe-2">Monto</th></tr></thead>
                    <tbody id="pre-asientos-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
