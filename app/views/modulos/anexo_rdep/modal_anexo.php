<?php
/** @var array $perm */
$vistaConfigRdep = \App\Helpers\PreferenciasHelper::getPreferenciasVista('anexo_rdep');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigRdep, 'estiloVistaPestanasRdep');
$sel = function (array $opciones, string $id, string $name): string {
    $h = '<select class="form-select form-select-sm" id="' . $id . '" name="' . $name . '">';
    foreach ($opciones as $v => $l) $h .= '<option value="' . htmlspecialchars((string) $v) . '">' . htmlspecialchars($l) . '</option>';
    return $h . '</select>';
};
?>
<style>
    #tabsRdep .nav-link { padding: 6px 9px; font-size: 0.8rem; white-space: nowrap; }
    #tabsRdep .nav-link i { font-size: 0.85rem; }
    #rdep_tbody_trab td { font-size: .78rem; white-space: nowrap; }
    #rdep_tbody_trab tr.rdep-grave td { background: rgba(220, 53, 69, .06); }
    .rdep-trab-scroll { max-height: 55vh; overflow: auto; }
    .rdep-trab-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; font-size: .75rem; white-space: nowrap; }
</style>
<!-- Modal Anexo RDEP -->
<div class="modal fade" id="modalRdep" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-code text-primary me-2"></i><span id="rdep_titulo">Anexo RDEP</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 position-relative">
                <div id="rdep-loader" class="d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1055;">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted" id="rdep-loader-texto">Cargando...</div>
                </div>
                <input type="hidden" id="rdep_id" value="">

                <!-- Barra de acciones del documento -->
                <div class="d-flex gap-1 align-items-center flex-wrap px-3 py-2 border-bottom bg-white">
                    <?php if (!empty($perm['actualizar'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="RDEP_importar()" title="Volver a tomar la nómina del ejercicio (roles mensuales, décimos, utilidades y gastos personales). Lo editado a mano se conserva."><i class="bi bi-download me-1"></i> Importar nómina</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="RDEP_recalcular()" title="Recalcular el resumen impositivo y validar"><i class="bi bi-arrow-repeat me-1"></i> Recalcular</button>
                        <div class="vr mx-1"></div>
                        <button type="button" class="btn btn-sm btn-success" onclick="RDEP_generar()" title="Generar el XML y el ZIP para SRI en Línea"><i class="bi bi-file-earmark-zip me-1"></i> Generar XML</button>
                    <?php endif; ?>
                    <span id="rdep_links" class="d-none ms-1">
                        <a href="#" id="rdep_link_zip" class="btn btn-sm btn-outline-success" title="Descargar ZIP"><i class="bi bi-file-earmark-zip"></i> ZIP</a>
                        <a href="#" id="rdep_link_xml" class="btn btn-sm btn-outline-secondary" title="Descargar XML"><i class="bi bi-filetype-xml"></i> XML</a>
                    </span>
                    <span class="ms-auto small" id="rdep_resumen_obs"></span>
                </div>

                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsRdep" role="tablist">
                        <li class="nav-item" role="presentation"><a class="nav-link active" id="rdep-tab-informante-btn" data-bs-toggle="tab" href="#rdep-tab-informante" role="tab"><i class="bi bi-building me-1"></i> Informante</a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link" id="rdep-tab-trab-btn" data-bs-toggle="tab" href="#rdep-tab-trab" role="tab"><i class="bi bi-people me-1"></i> Trabajadores <span class="badge bg-secondary ms-1" id="rdep_badge_trab">0</span></a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link" id="rdep-tab-obs-btn" data-bs-toggle="tab" href="#rdep-tab-obs" role="tab"><i class="bi bi-exclamation-triangle me-1"></i> Observaciones <span class="badge bg-danger ms-1 d-none" id="rdep_badge_graves">0</span><span class="badge bg-warning text-dark ms-1 d-none" id="rdep_badge_leves">0</span></a></li>
                    </ul>
                    <div class="pb-1 flex-shrink-0">
                        <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(['rdep-tab-obs' => 'Observaciones'], $vistaConfigRdep, 'anexo_rdep', '__pestanas_ocultas__', 'estiloVistaPestanasRdep') ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top px-3 py-3" id="tabsRdepContent">
                    <!-- Informante -->
                    <div class="tab-pane fade show active" id="rdep-tab-informante" role="tabpanel">
                        <form id="formRdepCabecera" novalidate onsubmit="return false;">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold d-block">Ejercicio</label>
                                    <div class="form-control form-control-sm bg-light" id="rdep_c_anio">-</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">RUC del empleador *</label>
                                    <input type="text" class="form-control form-control-sm" name="num_ruc" id="rdep_c_ruc" maxlength="13">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-bold d-block">Razón social</label>
                                    <input type="text" class="form-control form-control-sm" name="razon_social" id="rdep_c_rs" maxlength="300">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Tipo de empleador *</label>
                                    <?= $sel(\App\Helpers\CatalogoRdep::TIPO_EMPLEADOR, 'rdep_c_tipo', 'tipo_empleador') ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Ente de seguridad social *</label>
                                    <?= $sel(\App\Helpers\CatalogoRdep::ENTE_SEG_SOCIAL, 'rdep_c_ente', 'ente_seg_social') ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Estado</label>
                                    <div class="form-control form-control-sm bg-light" id="rdep_c_estado">-</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Última importación</label>
                                    <div class="form-control form-control-sm bg-light" id="rdep_c_importado">-</div>
                                </div>
                                <div class="col-12"><hr class="my-1"><div class="small fw-bold text-muted">Parámetros del ejercicio (resumen impositivo)</div></div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Fracción básica desgravada</label>
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" class="form-control text-end" name="fraccion_basica" id="rdep_c_fb"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Canasta familiar básica</label>
                                    <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" class="form-control text-end" name="canasta_basica" id="rdep_c_cfb"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">% rebaja gastos personales</label>
                                    <div class="input-group input-group-sm"><input type="number" step="0.01" min="0" class="form-control text-end" name="porcentaje_rebaja" id="rdep_c_pr"><span class="input-group-text">%</span></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">IPCEG (Galápagos)</label>
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm text-end" name="ipceg" id="rdep_c_ipceg">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold d-block">Observaciones</label>
                                    <input type="text" class="form-control form-control-sm" name="observaciones" id="rdep_c_obs" maxlength="500">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-warning py-2 px-3 small mb-0 d-none" id="rdep_aviso_tramos">
                                        <i class="bi bi-exclamation-triangle me-1"></i> No hay tabla de impuesto a la renta cargada para este ejercicio: el impuesto causado sale en cero. Cárguela en Configuración → Impuesto a la renta.
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <?php if (!empty($perm['actualizar'])): ?>
                                        <button type="button" class="btn btn-primary btn-sm px-4" onclick="RDEP_guardarCabecera()"><i class="bi bi-check2-circle me-1"></i> Guardar y recalcular</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Trabajadores -->
                    <div class="tab-pane fade" id="rdep-tab-trab" role="tabpanel">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <input type="text" class="form-control form-control-sm" id="rdep_filtro_trab" style="width:260px" placeholder="Filtrar por nombre o identificación...">
                            <div class="form-check form-check-inline small ms-1">
                                <input class="form-check-input" type="checkbox" id="rdep_solo_obs"><label class="form-check-label" for="rdep_solo_obs">Solo con observaciones</label>
                            </div>
                            <?php if (!empty($perm['actualizar'])): ?>
                                <div class="ms-auto position-relative" style="width:320px">
                                    <input type="text" class="form-control form-control-sm" id="rdep_buscar_emp" placeholder="Agregar trabajador: busque por nombre o cédula" autocomplete="off">
                                    <div class="list-group position-absolute w-100 shadow d-none" id="rdep_buscar_emp_lista" style="z-index:1070; max-height:220px; overflow:auto;"></div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="RDEP_agregarEnBlanco()" title="Agregar un trabajador sin ficha de empleado"><i class="bi bi-person-plus"></i></button>
                            <?php endif; ?>
                        </div>
                        <div class="rdep-trab-scroll border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Identificación</th>
                                        <th>Apellidos</th>
                                        <th>Nombres</th>
                                        <th class="text-center">Estab.</th>
                                        <th class="text-end">Sueldos</th>
                                        <th class="text-end">Otros grav.</th>
                                        <th class="text-end">Utilidades</th>
                                        <th class="text-end">13.º</th>
                                        <th class="text-end">14.º</th>
                                        <th class="text-end">F. reserva</th>
                                        <th class="text-end">Aporte IESS</th>
                                        <th class="text-end">Gastos pers.</th>
                                        <th class="text-end">Base imp.</th>
                                        <th class="text-end">Causado</th>
                                        <th class="text-end">Rebaja</th>
                                        <th class="text-end">Impuesto</th>
                                        <th class="text-end">Retenido</th>
                                        <th class="text-center">Obs.</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="rdep_tbody_trab"></tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr id="rdep_tfoot_trab"></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            Haga clic en un trabajador para completar lo que la nómina no sabe: discapacidad, residencia en el exterior,
                            Galápagos, ingresos y retenciones con otros empleadores, impuesto asumido. Lo editado a mano no se pisa al volver a importar.
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="tab-pane fade" id="rdep-tab-obs" role="tabpanel">
                        <div class="alert alert-light border small py-2 mb-2">
                            <b>Graves</b>: el SRI rechaza el archivo; hay que corregirlas antes de generar. <b>Leves</b>: el SRI solo avisa; conviene revisarlas.
                        </div>
                        <div id="rdep_lista_obs" class="small"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" id="btnEliminarRdep" onclick="RDEP_eliminar()"><i class="bi bi-trash3 me-1"></i> Eliminar</button>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
