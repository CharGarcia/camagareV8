<?php
/** @var array $perm */
use App\Helpers\CatalogoRdep;

$sel = function (array $opciones, string $id, string $name): string {
    $h = '<select class="form-select form-select-sm" id="' . $id . '" name="' . $name . '">';
    foreach ($opciones as $v => $l) $h .= '<option value="' . htmlspecialchars((string) $v) . '">' . htmlspecialchars((string) $l) . '</option>';
    return $h . '</select>';
};
$monto = fn(string $id, string $name, string $label, string $col = 'col-md-3', bool $ro = false) =>
    '<div class="' . $col . '"><label class="form-label small fw-bold d-block">' . $label . '</label>'
    . '<div class="input-group input-group-sm"><span class="input-group-text">$</span>'
    . '<input type="number" step="0.01" min="0" class="form-control text-end' . ($ro ? ' bg-light' : '') . '" id="' . $id . '" name="' . $name . '"' . ($ro ? ' readonly tabindex="-1"' : '') . '></div></div>';
?>
<style>
    #tabsRdepTrab .nav-link { padding: 6px 9px; font-size: 0.8rem; white-space: nowrap; }
</style>
<!-- Modal Trabajador del anexo (anidado sobre el modal del anexo) -->
<div class="modal fade" id="modalRdepTrab" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1070;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formRdepTrab" novalidate onsubmit="return false;">
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard text-primary me-2"></i><span id="rdep_t_titulo">Trabajador</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <input type="hidden" name="id" id="rdep_t_id" value="">
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsRdepTrab" role="tablist">
                            <li class="nav-item" role="presentation"><a class="nav-link active" id="rdept-tab-datos-btn" data-bs-toggle="tab" href="#rdept-tab-datos" role="tab"><i class="bi bi-person me-1"></i> Datos</a></li>
                            <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#rdept-tab-ingresos" role="tab"><i class="bi bi-cash-stack me-1"></i> Ingresos</a></li>
                            <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#rdept-tab-gastos" role="tab"><i class="bi bi-receipt me-1"></i> Gastos y exoneraciones</a></li>
                            <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#rdept-tab-resumen" role="tab"><i class="bi bi-calculator me-1"></i> Resumen impositivo</a></li>
                        </ul>
                    </div>
                    <div class="border-bottom bg-light mb-0"></div>
                    <div class="tab-content border-top px-3 py-3">
                        <!-- Datos -->
                        <div class="tab-pane fade show active" id="rdept-tab-datos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Tipo de identificación *</label>
                                    <?= $sel(CatalogoRdep::TIPO_ID, 'rdep_t_tip_id_ret', 'tip_id_ret') ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Identificación *</label>
                                    <input type="text" class="form-control form-control-sm" name="id_ret" id="rdep_t_id_ret" maxlength="13">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Apellidos *</label>
                                    <input type="text" class="form-control form-control-sm" name="apellidos" id="rdep_t_apellidos" maxlength="100">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Nombres *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombres" id="rdep_t_nombres" maxlength="100">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Establecimiento *</label>
                                    <input type="text" class="form-control form-control-sm" name="estab" id="rdep_t_estab" maxlength="3" list="rdep_estab_lista">
                                    <datalist id="rdep_estab_lista"></datalist>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Residencia *</label>
                                    <?= $sel(CatalogoRdep::RESIDENCIA, 'rdep_t_residencia', 'residencia') ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">País de residencia *</label>
                                    <select class="form-select form-select-sm" name="pais_residencia" id="rdep_t_pais"></select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Convenio doble imposición *</label>
                                    <?= $sel(CatalogoRdep::CONVENIO, 'rdep_t_convenio', 'aplica_convenio') ?>
                                </div>
                                <div class="col-12"><hr class="my-1"><div class="small fw-bold text-muted">Discapacidad y beneficios</div></div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold d-block">Condición respecto a discapacidades *</label>
                                    <?= $sel(CatalogoRdep::TIPO_DISCAP, 'rdep_t_tipo_discap', 'tipo_discap') ?>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold d-block">% discapacidad</label>
                                    <input type="number" min="0" max="100" step="1" class="form-control form-control-sm text-end" name="porcentaje_discap" id="rdep_t_pct_discap">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Tipo id. persona sustituida</label>
                                    <?= $sel(CatalogoRdep::TIPO_ID_DISCAP, 'rdep_t_tip_id_discap', 'tip_id_discap') ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Identificación persona sustituida</label>
                                    <input type="text" class="form-control form-control-sm" name="id_discap" id="rdep_t_id_discap" maxlength="13">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Beneficio Galápagos</label>
                                    <?= $sel(CatalogoRdep::SI_NO, 'rdep_t_galapagos', 'ben_galapagos') ?>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold d-block">Con o a cargo de discapacidad / enfermedad catastrófica</label>
                                    <?= $sel(CatalogoRdep::SI_NO, 'rdep_t_enf', 'enf_catastro') ?>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold d-block">Cargas familiares</label>
                                    <input type="number" min="0" max="5" step="1" class="form-control form-control-sm text-end" name="num_cargas" id="rdep_t_cargas">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold d-block">Tercera edad</label>
                                    <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="tercera_edad" id="rdep_t_tercera" value="1"><label class="form-check-label small" for="rdep_t_tercera">65 años o más</label></div>
                                </div>
                            </div>
                        </div>

                        <!-- Ingresos -->
                        <div class="tab-pane fade" id="rdept-tab-ingresos" role="tabpanel">
                            <div class="row g-3">
                                <?= $monto('rdep_t_suel_sal', 'suel_sal', 'Sueldos y salarios (con IESS)') ?>
                                <?= $monto('rdep_t_sob_suel', 'sob_suel', 'Otros ingresos gravados (sin IESS)') ?>
                                <?= $monto('rdep_t_part_util', 'part_util', 'Participación de utilidades') ?>
                                <?= $monto('rdep_t_imp_rent_empl', 'imp_rent_empl', 'IR asumido por este empleador') ?>
                                <?= $monto('rdep_t_decim_ter', 'decim_ter', 'Décimo tercer sueldo') ?>
                                <?= $monto('rdep_t_decim_cuar', 'decim_cuar', 'Décimo cuarto sueldo') ?>
                                <?= $monto('rdep_t_fondo_reserva', 'fondo_reserva', 'Fondo de reserva') ?>
                                <?= $monto('rdep_t_salario_digno', 'salario_digno', 'Compensación salario digno') ?>
                                <?= $monto('rdep_t_otros_no_grav', 'otros_ing_no_grav', 'Otros ingresos no gravados') ?>
                                <?= $monto('rdep_t_ing_grav', 'ing_grav_este_empl', 'Ingresos gravados con este empleador', 'col-md-3', true) ?>
                                <div class="col-12"><hr class="my-1"><div class="small fw-bold text-muted">Con otros empleadores (formulario 107 que entrega el trabajador)</div></div>
                                <?= $monto('rdep_t_int_grab_gen', 'int_grab_gen', 'Ingresos gravados con otros empleadores') ?>
                                <?= $monto('rdep_t_apor_otros', 'apor_per_iess_otros', 'Aporte personal con otros empleadores') ?>
                                <?= $monto('rdep_t_val_ret_otros', 'val_ret_otros', 'Impuesto retenido/asumido por otros') ?>
                            </div>
                        </div>

                        <!-- Gastos -->
                        <div class="tab-pane fade" id="rdept-tab-gastos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Sistema de salario neto</label>
                                    <?= $sel(CatalogoRdep::SISTEMA_SALARIO_NETO, 'rdep_t_sis', 'sis_sal_net') ?>
                                </div>
                                <?= $monto('rdep_t_apo_per_iess', 'apo_per_iess', 'Aporte personal con este empleador') ?>
                                <div class="col-12"><hr class="my-1"><div class="small fw-bold text-muted">Gastos personales (último formulario de proyección del trabajador)</div></div>
                                <?= $monto('rdep_t_vivienda', 'deduc_vivienda', 'Vivienda', 'col-md-2') ?>
                                <?= $monto('rdep_t_salud', 'deduc_salud', 'Salud', 'col-md-2') ?>
                                <?= $monto('rdep_t_educ', 'deduc_educ', 'Educación, arte y cultura', 'col-md-2') ?>
                                <?= $monto('rdep_t_aliment', 'deduc_aliment', 'Alimentación', 'col-md-2') ?>
                                <?= $monto('rdep_t_vestim', 'deduc_vestim', 'Vestimenta', 'col-md-2') ?>
                                <?= $monto('rdep_t_turismo', 'deduc_turismo', 'Turismo', 'col-md-2') ?>
                                <div class="col-12"><hr class="my-1"><div class="small fw-bold text-muted">Exoneraciones (calculadas: tercera edad hasta 1 fracción básica; discapacidad 60/70/80/100% de 2 fracciones según el grado; solo una)</div></div>
                                <?= $monto('rdep_t_exo_discap', 'exo_discap', 'Exoneración por discapacidad', 'col-md-3', true) ?>
                                <?= $monto('rdep_t_exo_ter', 'exo_ter_ed', 'Exoneración por tercera edad', 'col-md-3', true) ?>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div class="tab-pane fade" id="rdept-tab-resumen" role="tabpanel">
                            <div class="row g-3">
                                <?= $monto('rdep_t_bas_imp', 'bas_imp', 'Base imponible gravada', 'col-md-3', true) ?>
                                <?= $monto('rdep_t_causado', 'imp_rent_caus', 'Impuesto a la renta causado', 'col-md-3', true) ?>
                                <?= $monto('rdep_t_rebaja', 'rebaja_gastos', 'Rebaja por gastos personales', 'col-md-3', true) ?>
                                <?= $monto('rdep_t_imp_rebaja', 'imp_rent_rebaja', 'Impuesto después de la rebaja', 'col-md-3', true) ?>
                                <?= $monto('rdep_t_val_ret', 'val_ret', 'Retenido al trabajador por este empleador') ?>
                                <?= $monto('rdep_t_val_imp_asu', 'val_imp_asu_este', 'Asumido por este empleador') ?>
                                <?= $monto('rdep_t_val_ret_otros2', 'val_ret_otros_ro', 'Retenido/asumido por otros', 'col-md-3', true) ?>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold d-block">Diferencia (retenido + asumido − impuesto)</label>
                                    <div class="form-control form-control-sm bg-light text-end fw-bold" id="rdep_t_diferencia">0.00</div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-primary bg-primary bg-opacity-10 py-2 px-3 small border-primary border-opacity-25 mb-2">
                                        <i class="bi bi-info-circle-fill me-1"></i>
                                        El SRI espera que retenido + asumido por este empleador + retenido por otros sea igual al impuesto después de la rebaja.
                                        Si retuvo de menos durante el año, la diferencia se regulariza en la última nómina o la declara el trabajador.
                                    </div>
                                    <label class="form-label small fw-bold d-block">Observaciones internas</label>
                                    <input type="text" class="form-control form-control-sm" name="observaciones" id="rdep_t_obs" maxlength="500">
                                </div>
                                <div class="col-12" id="rdep_t_validaciones"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if (!empty($perm['actualizar'])): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3" onclick="RDEP_quitarTrabajador()"><i class="bi bi-trash3 me-1"></i> Quitar del anexo</button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                        <?php if (!empty($perm['actualizar'])): ?>
                            <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarRdepTrab" onclick="RDEP_guardarTrabajador()"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
