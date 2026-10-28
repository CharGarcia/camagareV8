<?php
/**
 * Cuerpo del modal de cuenta contable, con pestañas. Lo incluyen los dos modales de cuenta
 * (plan_cuentas/index.php y el reutilizable plan_cuentas/modal.php), que conservan su
 * propio encabezado, pie y JS de guardado.
 *
 *   General           Cuentas existentes del nivel + datos de la cuenta (siempre).
 *   Centro de costos  Periodos de centro de costo (nivel 5 y si la empresa tiene centros activos).
 *   Proyectos         Periodos de proyecto (nivel 5 y si la empresa tiene proyectos activos).
 *   SRI               Código SRI (nivel 5).
 *   Supercias         Códigos ESF / ERI / ECP (nivel 5).
 *
 * $pcIds: ids propios de cada modal (alerta, info del padre, hermanos) y la clase que
 * muestra/oculta lo exclusivo del nivel 5. Al abrir, el modal llama
 * window.PCFORM_abrir(idCuenta, nivel, soloLectura).
 */
use App\Helpers\PreferenciasHelper;

$pcIds = $pcIds ?? [];
$pcIds += [
    'alert'        => 'modalAlert',
    'parent'       => 'wrapper-parent-info',
    'parentName'   => 'parent-account-name',
    'existing'     => 'wrapper-existing-accounts',
    'existingList' => 'existing-accounts-list',
    'nivel5'       => 'wrapper-nivel-5',
];
$pcCentros   = $centros ?? $centrosCosto ?? [];
$pcProyectos = $proyectos ?? [];

// Pestañas que cada usuario puede ocultar (General siempre se ve). Clave = id del panel.
$pcPestanas = [];
if (!empty($pcCentros))   $pcPestanas['pc-pane-centros']   = 'Centro de costos';
if (!empty($pcProyectos)) $pcPestanas['pc-pane-proyectos'] = 'Proyectos';
$pcPestanas['pc-pane-sri']       = 'SRI';
$pcPestanas['pc-pane-supercias'] = 'Supercias';
$pcVista = PreferenciasHelper::getPreferenciasVista('modulos/plan-cuentas');
echo PreferenciasHelper::renderEstilosPestanasOcultas($pcVista, 'estiloVistaPestanasPC');

$pcTab = static function (string $pane, string $icono, string $texto, bool $activa = false, string $clase = ''): string {
    return '<li class="nav-item ' . $clase . '" role="presentation">'
         . '<a class="nav-link py-1 px-3 small' . ($activa ? ' active' : '') . '" data-bs-toggle="tab" href="#' . $pane . '" data-bs-target="#' . $pane . '" role="tab">'
         . '<i class="bi ' . $icono . ' me-1"></i>' . $texto . '</a></li>';
};
?>
<ul class="nav nav-tabs px-3 pt-2 align-items-center" id="pc-tabs" role="tablist">
    <?= $pcTab('pc-pane-general', 'bi-card-text', 'General', true) ?>
    <?php if (!empty($pcCentros)): ?><?= $pcTab('pc-pane-centros', 'bi-diagram-3', 'Centro de costos', false, $pcIds['nivel5'] . ' d-none') ?><?php endif; ?>
    <?php if (!empty($pcProyectos)): ?><?= $pcTab('pc-pane-proyectos', 'bi-kanban', 'Proyectos', false, $pcIds['nivel5'] . ' d-none') ?><?php endif; ?>
    <?= $pcTab('pc-pane-sri', 'bi-bank', 'SRI', false, $pcIds['nivel5'] . ' d-none') ?>
    <?= $pcTab('pc-pane-supercias', 'bi-building', 'Supercias', false, $pcIds['nivel5'] . ' d-none') ?>
    <li class="nav-item ms-auto <?= $pcIds['nivel5'] ?> d-none">
        <?= PreferenciasHelper::renderDropdownPestanas($pcPestanas, $pcVista, 'modulos/plan-cuentas', '__pestanas_ocultas__', 'estiloVistaPestanasPC') ?>
    </li>
</ul>

<div class="tab-content p-4" style="min-height:260px;">
    <div id="<?= $pcIds['alert'] ?>" class="alert d-none mb-3 py-2 small shadow-sm border-0"></div>

    <!-- General -->
    <div class="tab-pane fade show active" id="pc-pane-general" role="tabpanel">
        <div id="<?= $pcIds['parent'] ?>" class="alert alert-primary py-1 mb-2 small border-0 shadow-sm d-none" style="background-color: rgba(13, 110, 253, 0.05); color: #0d6efd;">
            <i class="bi bi-info-circle-fill me-2"></i>
            <span>La cuenta que se va a crear estará dentro de: <strong id="<?= $pcIds['parentName'] ?>"></strong></span>
        </div>

        <!-- Cuentas existentes en el mismo nivel -->
        <div id="<?= $pcIds['existing'] ?>" class="mb-3 d-none p-2 border rounded bg-light bg-opacity-50">
            <h6 class="small fw-bold text-muted mb-1" style="font-size: 0.7rem;"><i class="bi bi-list-ul me-1"></i> CUENTAS EXISTENTES EN ESTE NIVEL</h6>
            <div id="<?= $pcIds['existingList'] ?>" class="list-group list-group-flush" style="max-height: 100px; overflow-y: auto;"></div>
        </div>

        <input type="hidden" name="id" id="pc_id" value="">

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label small fw-bold">Código</label>
                <input type="text" class="form-control form-control-sm bg-light fw-bold" name="codigo" id="pc_codigo_edit" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Nivel</label>
                <input type="text" class="form-control form-control-sm bg-light text-center" name="nivel" id="pc_nivel_edit" readonly>
            </div>
            <div class="col-md-4 text-end">
                <label class="form-label small fw-bold d-block">Estado</label>
                <div class="form-check form-switch d-inline-block mt-1">
                    <input class="form-check-input shadow-none" type="checkbox" role="switch" name="status" id="pc_status" value="1" checked>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">Nombre de la Cuenta *</label>
                <input type="text" class="form-control form-control-sm shadow-none" name="nombre" id="pc_nombre_edit" required placeholder="Ingrese el nombre...">
            </div>
        </div>
    </div>

    <?php if (!empty($pcCentros)): ?>
    <!-- Centro de costos -->
    <div class="tab-pane fade" id="pc-pane-centros" role="tabpanel">
        <?php $pcdimTipo = 'centro'; $pcdimOpciones = $pcCentros; include __DIR__ . '/_dimensiones.php'; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($pcProyectos)): ?>
    <!-- Proyectos -->
    <div class="tab-pane fade" id="pc-pane-proyectos" role="tabpanel">
        <?php $pcdimTipo = 'proyecto'; $pcdimOpciones = $pcProyectos; include __DIR__ . '/_dimensiones.php'; ?>
    </div>
    <?php endif; ?>

    <!-- SRI -->
    <div class="tab-pane fade" id="pc-pane-sri" role="tabpanel">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold">Código SRI</label>
                <input type="text" class="form-control form-control-sm shadow-none" name="codigo_sri" id="pc_codigo_sri">
                <div class="form-text" style="font-size:.65rem;">Casillero del formulario de renta al que suma la cuenta.</div>
            </div>
        </div>
    </div>

    <!-- Supercias -->
    <div class="tab-pane fade" id="pc-pane-supercias" role="tabpanel">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-bold">Supercias ESF</label>
                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_esf" id="pc_supercias_esf">
                <div class="form-text" style="font-size:.65rem;">Casillero del Estado de Situación Financiera.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Supercias ERI</label>
                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_eri" id="pc_supercias_eri">
                <div class="form-text" style="font-size:.65rem;">Casillero del Estado de Resultado Integral.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Supercias ECP Columna</label>
                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_ecp_subcodigo" id="pc_supercias_ecp_subcodigo" placeholder="Ej: 301" title="Componente del patrimonio en el Estado de Cambios en el Patrimonio: 301, 302, 303, 30401, 30402, 30501-30504, 30601-30607, 30701, 30702">
                <div class="form-text" style="font-size:.65rem;">Componente del patrimonio (301 Capital, 30401 Reserva legal, 30601 Ganancias acumuladas…).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Supercias ECP Fila de cambios <span class="fw-normal">(opcional)</span></label>
                <input type="text" class="form-control form-control-sm shadow-none" name="supercias_ecp_codigo" id="pc_supercias_ecp_codigo" placeholder="Ej: 990204" title="Solo si el movimiento del año de esta cuenta no va a la fila por defecto de su columna. Filas: 990102, 990103, 990201 a 990209">
                <div class="form-text" style="font-size:.65rem;">Solo si el movimiento del año no va a la fila por defecto (ej. 990204 Dividendos).</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    /**
     * Prepara las pestañas al abrir una cuenta: vuelve a General, muestra las pestañas del
     * nivel 5 solo en cuentas de movimiento y carga los periodos de centro / proyecto.
     */
    window.PCFORM_abrir = function (idCuenta, nivel, soloLectura) {
        const esNivel5 = parseInt(nivel, 10) === 5;
        document.querySelectorAll('.<?= $pcIds['nivel5'] ?>').forEach(el => el.classList.toggle('d-none', !esNivel5));
        const general = document.querySelector('#pc-tabs .nav-link[data-bs-target="#pc-pane-general"]');
        if (general) bootstrap.Tab.getOrCreateInstance(general).show();
        if (esNivel5 && window.PCDIM_cargar) window.PCDIM_cargar(idCuenta || 0, !!soloLectura);
        else if (window.PCDIM_desactivar) window.PCDIM_desactivar();
    };
})();
</script>
