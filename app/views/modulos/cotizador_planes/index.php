<?php
/**
 * Cotizador de Planes — listado, modal de cotización y modal de configuración.
 * Lógica de la pantalla: public/js/modulos/cotizador_planes.js
 *
 * @var string $titulo
 * @var array  $perm
 * @var string $rutaModulo
 * @var bool   $disponible
 * @var bool   $esAdmin
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $perPage
 * @var int    $totalPages
 * @var string $buscar
 * @var string $ordenJson
 * @var string $ordenParam
 * @var array  $vistaConfig
 * @var array  $vendedores
 * @var bool   $puedeProforma
 */
$base   = BASE_URL;
$urlCP  = rtrim($base, '/') . '/' . ltrim($rutaModulo, '/');
$from   = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to     = $total > 0 ? min($page * $perPage, $total) : 0;
$e      = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<style>
    .cotizador-scroll { max-height: calc(100dvh - 240px); overflow: auto; }
    .cotizador-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; white-space: nowrap; }
    .cp-row { cursor: pointer; }
    .cp-celda { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cp-total-box { border: 1px solid #dee2e6; border-radius: .5rem; padding: .35rem .75rem; background: #fff; min-width: 150px; }
    .cp-total-box .valor { font-size: 1.05rem; font-weight: 700; }
    .cp-total-box .etiqueta { font-size: .7rem; color: #6c757d; text-transform: uppercase; letter-spacing: .02em; }
    .cp-est { border: 1px solid #dee2e6; border-radius: .5rem; margin-bottom: .75rem; background: #fff; }
    .cp-est-head { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: .4rem .6rem; border-radius: .5rem .5rem 0 0; }
    .cp-items td { padding: 2px 6px !important; font-size: .8rem; vertical-align: middle; }
    .cp-items .cp-dep td { color: #6c757d; }
    #modalCP .form-control-sm, #modalCP .form-select-sm { font-size: .8rem; }
    #cfgItems td, #cfgItems th { font-size: .78rem; padding: 3px 6px; vertical-align: middle; }
</style>
<?= \App\Helpers\PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-calculator text-primary me-1"></i> <?= $e($titulo) ?></h5>
    <div class="d-flex gap-2">
        <?php if ($disponible && $perm['crear']): ?>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="CP_nueva()"><i class="bi bi-plus-lg"></i> Nueva</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        El módulo aún no está instalado: falta ejecutar <code>database/2026-11-13_cotizador_planes.sql</code>.
    </div>
<?php else: ?>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php
            $tCP = 'Cotización';
            $filtrosCP = [
                ['tab' => $tCP, 'key' => 'numero',   'label' => 'N.º',          'icon' => 'bi-hash',     'type' => 'number_range', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCP, 'key' => 'ruc',      'label' => 'RUC / cédula', 'icon' => 'bi-person-vcard', 'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCP, 'key' => 'cliente',  'label' => 'Razón social', 'icon' => 'bi-building', 'type' => 'text', 'grupo' => 'Datos', 'col' => 4],
                ['tab' => $tCP, 'key' => 'estado',   'label' => 'Estado',       'icon' => 'bi-flag',     'type' => 'select', 'grupo' => 'Estado', 'col' => 6, 'options' => [
                    ['v' => 'borrador', 'l' => 'Borrador'], ['v' => 'proforma', 'l' => 'Proforma generada'], ['v' => 'anulada', 'l' => 'Anulada'],
                ]],
                ['tab' => $tCP, 'key' => 'modalidad','label' => 'Modalidad',    'icon' => 'bi-calendar3', 'type' => 'select', 'grupo' => 'Estado', 'col' => 6, 'options' => [
                    ['v' => 'anual', 'l' => 'Anual'], ['v' => 'mensual', 'l' => 'Mensual'],
                ]],
                ['tab' => $tCP, 'key' => 'total_anual', 'label' => 'Total anual', 'icon' => 'bi-cash-stack', 'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tCP, 'key' => 'usuarios',    'label' => 'Usuarios',    'icon' => 'bi-people',     'type' => 'number_range', 'grupo' => 'Valores', 'col' => 6],
                ['tab' => $tCP, 'key' => 'fecha',    'label' => 'Fecha',        'icon' => 'bi-calendar-event', 'type' => 'date_range', 'grupo' => 'Fechas', 'col' => 6, 'atajos' => true],
                ['tab' => $tCP, 'key' => 'vendedor', 'label' => 'Vendedor',     'icon' => 'bi-person-badge', 'type' => 'text', 'grupo' => 'Fechas', 'col' => 6],
            ];
            ?>
            <link rel="stylesheet" href="<?= rtrim($base, '/') ?>/css/components/filtros_modal.css?v=<?= asset_ver('/css/components/filtros_modal.css') ?>">
            <script src="<?= rtrim($base, '/') ?>/js/components/filtros_modal.js?v=<?= asset_ver('/js/components/filtros_modal.js') ?>"></script>
            <div id="fmBuscadorCP"></div>
            <input type="hidden" id="buscarCP" value="<?= $e($buscar) ?>">
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.FiltrosModal) return;
                    new FiltrosModal({
                        containerId: 'fmBuscadorCP', hiddenInputId: 'buscarCP',
                        placeholder: 'Buscar por número, RUC, razón social…', titulo: 'Filtros de cotizaciones',
                        inputWidth: 380, extraId: 'fmExtraCP',
                        fields: <?= json_encode($filtrosCP, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
                        loadingTarget: '#tbodyCP',
                        onApply: () => window.CP_buscar && window.CP_buscar(1),
                    }).init();
                });
            </script>
            <div id="fmExtraCP" class="btn-group btn-group-sm">
                <?= \App\Helpers\PreferenciasHelper::renderDropdownColumnas([
                    'numero' => 'N.º', 'fecha' => 'Fecha', 'identificacion' => 'RUC / cédula', 'razon_social' => 'Razón social',
                    'establecimientos' => 'Establec.', 'usuarios' => 'Usuarios', 'modalidad' => 'Modalidad',
                    'total_anual' => 'Anual', 'total_mensual' => 'Mensual', 'total_unico' => 'Pago único',
                    'vendedor' => 'Vendedor', 'estado' => 'Estado',
                ], $vistaConfig ?? [], $rutaModulo) ?>
                <a id="btnPdfCP" href="<?= $urlCP ?>/export-pdf?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-danger" title="Descargar PDF">
                    <i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span>
                </a>
                <a id="btnExcelCP" href="<?= $urlCP ?>/export-excel?b=<?= urlencode($buscar) ?>&orden=<?= urlencode($ordenParam) ?>" class="btn btn-outline-success" title="Descargar Excel">
                    <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span>
                </a>
                <?php if ($esAdmin): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="CP_abrirConfig()" title="Configuración: parámetros y catálogo de precios">
                        <i class="bi bi-gear"></i><span class="d-none d-md-inline"> Configuración</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span id="paginacionInfoCP" class="text-muted small fw-medium"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
            <div id="paginacionCP" class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" <?= $page <= 1 ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page - 1 ?>)"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" <?= $page >= $totalPages ? 'disabled' : '' ?> onclick="cambiarPaginaAjax(<?= $page + 1 ?>)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="cotizador-scroll w-100">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        $cols = [
                            ['numero', 'N.º', 'ps-3'], ['fecha', 'Fecha', ''], ['identificacion', 'RUC / cédula', ''],
                            ['razon_social', 'Razón social', ''], ['establecimientos', 'Establec.', 'text-center'],
                            ['usuarios', 'Usuarios', 'text-center'], ['modalidad', 'Modalidad', ''],
                            ['total_anual', 'Anual', 'text-end'], ['total_mensual', 'Mensual', 'text-end'],
                            ['total_unico', 'Pago único', 'text-end'], ['vendedor', 'Vendedor', ''], ['estado', 'Estado', 'text-center pe-3'],
                        ];
                        foreach ($cols as [$k, $t, $cls]): ?>
                            <th class="sortable-header <?= $cls ?>" role="button" data-sort="<?= $k ?>" data-col="<?= $k ?>"><?= $t ?> <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="tbodyCP"><?= \App\controllers\modulos\CotizadorPlanesController::filasHtml($rows) ?></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════ Modal de cotización ═══════════════ -->
<div class="modal fade" id="modalCP" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-calculator text-primary me-2"></i><span id="cpTitulo">Nueva cotización</span></h5>
                <span id="cpEstado" class="badge ms-2"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <input type="hidden" id="cp_id" value="">
                <div id="cpAviso" class="alert d-none mx-3 mt-2 mb-0 py-2 small"></div>

                <!-- Totales en vivo: visibles en todas las pestañas -->
                <div class="d-flex flex-wrap gap-2 px-3 pt-2 align-items-stretch">
                    <div class="cp-total-box"><div class="etiqueta">Plan anual</div><div class="valor text-primary" id="cpTotAnual">$0.00</div><div class="small text-muted" id="cpTotAnualNota"></div></div>
                    <div class="cp-total-box"><div class="etiqueta">Plan mensual</div><div class="valor" id="cpTotMensual">$0.00</div><div class="small text-muted" id="cpTotMensualNota"></div></div>
                    <div class="cp-total-box"><div class="etiqueta">Pago único</div><div class="valor" id="cpTotUnico">$0.00</div><div class="small text-muted">implementación y servicios</div></div>
                    <div class="cp-total-box"><div class="etiqueta">Usuarios</div><div class="valor" id="cpTotUsuarios">0</div><div class="small text-muted" id="cpTotUsuariosNota"></div></div>
                    <div class="ms-auto align-self-center small text-muted" id="cpCalculando"></div>
                </div>

                <div class="d-flex align-items-center bg-light px-3 pt-2 mt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1" role="tablist">
                        <li class="nav-item"><a class="nav-link active py-2 small" id="cp-tab-cliente-btn" data-bs-toggle="tab" href="#cp-tab-cliente" role="tab"><i class="bi bi-building me-1"></i>Cliente</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#cp-tab-establecimientos" role="tab"><i class="bi bi-shop me-1"></i>Establecimientos y módulos</a></li>
                        <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#cp-tab-resumen" role="tab"><i class="bi bi-receipt me-1"></i>Resumen</a></li>
                        <?= \App\Helpers\InformacionRegistro::boton('cp', 'py-2 small') ?>
                    </ul>
                    <?= \App\Helpers\PreferenciasHelper::renderDropdownPestanas(\App\Helpers\InformacionRegistro::pestanaConfigurable('cp'), $vistaConfig ?? [], $rutaModulo) ?>
                </div>
                <div class="border-bottom mx-3 mb-3"></div>

                <div class="tab-content px-3 pb-3">
                    <!-- Cliente -->
                    <div class="tab-pane fade show active" id="cp-tab-cliente" role="tabpanel">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">RUC o cédula *</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control cp-editable" id="cp_identificacion" maxlength="13" inputmode="numeric" placeholder="13 dígitos (RUC) o 10 (cédula)">
                                    <button type="button" class="btn btn-outline-primary cp-editable" id="cpBtnSri" title="Traer datos y establecimientos del SRI"><i class="bi bi-search"></i> Consultar SRI</button>
                                </div>
                                <div class="small mt-1" id="cpSriInfo"></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold mb-1">Razón social *</label>
                                <input type="text" class="form-control form-control-sm cp-editable" id="cp_razon_social" maxlength="300">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Nombre comercial</label>
                                <input type="text" class="form-control form-control-sm cp-editable" id="cp_nombre_comercial" maxlength="300">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Tipo de contribuyente</label>
                                <select class="form-select form-select-sm cp-editable" id="cp_tipo_contribuyente">
                                    <option value="">—</option>
                                    <option value="persona_natural">Persona natural</option>
                                    <option value="sociedad">Sociedad</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Obligado a llevar contabilidad</label>
                                <select class="form-select form-select-sm cp-editable" id="cp_obligado_contabilidad">
                                    <option value="">—</option>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Persona de contacto</label>
                                <input type="text" class="form-control form-control-sm cp-editable" id="cp_contacto_nombre" maxlength="200" placeholder="Quién decide la compra">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Correo <span class="text-muted fw-normal">(obligatorio para la proforma)</span></label>
                                <input type="email" class="form-control form-control-sm cp-editable" id="cp_correo" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Teléfono</label>
                                <input type="text" class="form-control form-control-sm cp-editable" id="cp_telefono" maxlength="50">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-bold mb-1">Dirección</label>
                                <input type="text" class="form-control form-control-sm cp-editable" id="cp_direccion" maxlength="500">
                                <input type="hidden" id="cp_cod_prov"><input type="hidden" id="cp_cod_ciudad">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Vendedor</label>
                                <select class="form-select form-select-sm cp-editable" id="cp_id_vendedor">
                                    <option value="">—</option>
                                    <?php foreach ($vendedores as $v): ?>
                                        <option value="<?= (int) $v['id'] ?>"><?= $e($v['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold mb-1">Observaciones <span class="text-muted fw-normal">(van a la proforma)</span></label>
                                <textarea class="form-control form-control-sm cp-editable" id="cp_observaciones" rows="2" maxlength="600"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Establecimientos -->
                    <div class="tab-pane fade" id="cp-tab-establecimientos" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="small text-muted">Cada establecimiento contrata sus módulos. Al agregar un módulo entran solos los que necesita para funcionar.</div>
                            <button type="button" class="btn btn-outline-primary btn-sm cp-editable" id="cpBtnAgregarEst"><i class="bi bi-plus-lg"></i> Agregar establecimiento</button>
                        </div>
                        <div id="cpEstablecimientos"></div>
                    </div>

                    <!-- Resumen -->
                    <div class="tab-pane fade" id="cp-tab-resumen" role="tabpanel">
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Usuarios del RUC *</label>
                                <input type="number" min="1" step="1" class="form-control form-control-sm cp-editable" id="cp_usuarios" value="3">
                                <div class="small text-muted" id="cpUsuariosAyuda"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Modalidad para la proforma</label>
                                <select class="form-select form-select-sm cp-editable" id="cp_modalidad">
                                    <option value="anual">Anual</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="cpDescuentoWrap">
                                <label class="form-label small fw-bold mb-1">Descuento comercial (%)</label>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm cp-editable" id="cp_descuento_vendedor_pct" value="0">
                                <div class="small text-muted" id="cpDescuentoAyuda"></div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered small mb-1">
                                <thead class="table-light">
                                    <tr><th>Establecimiento</th><th class="text-end">Base</th><th class="text-end">Adicionales</th><th class="text-end">Desc. establ.</th><th class="text-end">Anual</th><th class="text-end">Mensual</th><th class="text-end">Pago único</th></tr>
                                </thead>
                                <tbody id="cpResumenFilas"></tbody>
                                <tfoot id="cpResumenPie" class="fw-semibold"></tfoot>
                            </table>
                        </div>
                        <div class="small text-muted" id="cpResumenNota"></div>
                    </div>

                    <?= \App\Helpers\InformacionRegistro::panel('cp', $rutaModulo, 'cp_id') ?>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" id="cpBtnEliminar"><i class="bi bi-trash3 me-1"></i> Eliminar</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-success btn-sm d-none" id="cpBtnVerProforma" href="#"><i class="bi bi-file-earmark-text me-1"></i> Ver proforma</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <?php if ($perm['crear'] || $perm['actualizar']): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3" id="cpBtnGuardar"><i class="bi bi-check2-circle me-1"></i> Guardar</button>
                    <?php endif; ?>
                    <?php if ($puedeProforma && ($perm['crear'] || $perm['actualizar'])): ?>
                        <button type="button" class="btn btn-success btn-sm px-3" id="cpBtnProforma"><i class="bi bi-file-earmark-plus me-1"></i> Generar proforma</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($esAdmin): ?>
<!-- ═══════════════ Modal de configuración (niveles 2 y 3) ═══════════════ -->
<div class="modal fade" id="modalCPConfig" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-gear text-secondary me-2"></i>Configuración del cotizador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active small" data-bs-toggle="tab" href="#cfg-tab-catalogo">Catálogo de precios</a></li>
                    <li class="nav-item"><a class="nav-link small" data-bs-toggle="tab" href="#cfg-tab-parametros">Parámetros</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="cfg-tab-catalogo">
                        <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
                            <div style="width:420px;max-width:100%;">
                                <label class="form-label small fw-bold mb-1">Agregar un módulo del menú</label>
                                <select class="form-select form-select-sm" id="cfgSubmodulo"></select>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="cfgBtnAgregarModulo"><i class="bi bi-plus-lg"></i> Agregar módulo</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cfgBtnAgregarServicio"><i class="bi bi-plus-lg"></i> Agregar servicio</button>
                            <div class="ms-auto" style="width:240px;">
                                <input type="search" class="form-control form-control-sm" id="cfgFiltro" placeholder="Filtrar el catálogo…">
                            </div>
                        </div>

                        <!-- Editor de ítem -->
                        <div class="card border-primary border-opacity-25 mb-2 d-none" id="cfgEditor">
                            <div class="card-body py-2">
                                <input type="hidden" id="it_id"><input type="hidden" id="it_tipo"><input type="hidden" id="it_id_submodulo"><input type="hidden" id="it_ruta">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold mb-1">Nombre *</label>
                                        <input type="text" class="form-control form-control-sm" id="it_nombre" maxlength="150">
                                        <div class="small text-muted" id="it_origen"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold mb-1">Grupo</label>
                                        <input type="text" class="form-control form-control-sm" id="it_grupo" maxlength="100" placeholder="Ventas, Compras…">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold mb-1">Precio anual ($) *</label>
                                        <input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" id="it_precio_anual" value="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold mb-1">Se cobra</label>
                                        <select class="form-select form-select-sm" id="it_cobro">
                                            <option value="recurrente">Cada periodo (anual / mensual)</option>
                                            <option value="unico">Una sola vez</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch small mt-1">
                                            <input class="form-check-input" type="checkbox" id="it_aplica_descuento_establecimiento" checked>
                                            <label class="form-check-label" for="it_aplica_descuento_establecimiento" title="Módulo base: en los establecimientos adicionales lleva el descuento (solo plan anual). Apagado: módulo adicional a precio completo en cada establecimiento.">Entra en el descuento por establecimiento adicional</label>
                                        </div>
                                        <div class="form-check form-switch small">
                                            <input class="form-check-input" type="checkbox" id="it_por_defecto">
                                            <label class="form-check-label" for="it_por_defecto">Por defecto en cada establecimiento nuevo</label>
                                        </div>
                                        <div class="form-check form-switch small">
                                            <input class="form-check-input" type="checkbox" id="it_activo" checked>
                                            <label class="form-check-label" for="it_activo">Activo (se ofrece al cotizar)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold mb-1">Requiere (entran solos al agregarlo)</label>
                                        <select class="form-select form-select-sm" id="it_requiere" multiple size="5"></select>
                                        <div class="small text-muted">Ctrl/Cmd + clic para elegir varios.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold mb-1">Orden</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="it_orden" value="0">
                                        <div id="it_producto_wrap" class="mt-2">
                                            <label class="form-label small fw-bold mb-1">Producto en la proforma</label>
                                            <input type="text" class="form-control form-control-sm" id="it_producto_txt" list="cfgProductosLista" placeholder="Opcional: buscar producto">
                                            <input type="hidden" id="it_id_producto">
                                            <div class="small text-muted">Solo pagos únicos. Sin producto, la línea toma el IVA del plan.</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold mb-1">Descripción</label>
                                        <input type="text" class="form-control form-control-sm" id="it_descripcion" maxlength="500">
                                    </div>
                                    <div class="col-12 d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cfgBtnEliminarItem"><i class="bi bi-trash3"></i> Eliminar del catálogo</button>
                                        <div class="ms-auto d-flex gap-2">
                                            <button type="button" class="btn btn-secondary btn-sm" id="cfgBtnCancelarItem">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm" id="cfgBtnGuardarItem"><i class="bi bi-check2-circle"></i> Guardar ítem</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height:52vh;">
                            <table class="table table-sm table-hover mb-0" id="cfgItems">
                                <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                                    <tr><th>Grupo</th><th>Nombre</th><th>Tipo</th><th>Se cobra</th><th class="text-end">Precio anual</th><th class="text-center">Desc. establ.</th><th class="text-center">Por defecto</th><th>Requiere</th><th class="text-center">Estado</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cfg-tab-parametros">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Recargo del plan mensual (%)</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" id="cfg_recargo_mensual_pct">
                                <div class="small text-muted">Mensual = anual ÷ 12 × (1 + recargo).</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Descuento por establecimiento adicional (%)</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" id="cfg_descuento_establecimiento_pct">
                                <div class="small text-muted">Solo plan anual y solo módulos base.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Usuarios incluidos por establecimiento</label>
                                <input type="number" min="0" step="1" class="form-control form-control-sm" id="cfg_usuarios_por_establecimiento">
                                <div class="small text-muted">Se suman y se comparten a nivel de RUC.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Usuario adicional ($ al año)</label>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm" id="cfg_precio_usuario_adicional_anual">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check form-switch small mt-4">
                                    <input class="form-check-input" type="checkbox" id="cfg_permitir_descuento_vendedor">
                                    <label class="form-check-label" for="cfg_permitir_descuento_vendedor">El vendedor puede dar descuento</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Descuento máximo del vendedor (%)</label>
                                <input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" id="cfg_descuento_vendedor_max_pct">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Días de vigencia de la proforma</label>
                                <input type="number" min="1" max="3650" step="1" class="form-control form-control-sm" id="cfg_dias_vigencia">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Producto de la línea «plan anual» *</label>
                                <input type="text" class="form-control form-control-sm" id="cfg_producto_anual_txt" list="cfgProductosLista" placeholder="Ej.: Plan anual CaMaGaRe">
                                <input type="hidden" id="cfg_id_producto_resumen_anual">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Producto de la línea «plan mensual» *</label>
                                <input type="text" class="form-control form-control-sm" id="cfg_producto_mensual_txt" list="cfgProductosLista" placeholder="Ej.: Plan mensual CaMaGaRe">
                                <input type="hidden" id="cfg_id_producto_resumen_mensual">
                            </div>
                            <div class="col-12 small text-muted">
                                La proforma lleva una sola línea con ese producto (su IVA es el del producto) y el detalle del plan en el anexo de condiciones.
                                Los precios del catálogo son <strong>sin IVA</strong>.
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-primary btn-sm" id="cfgBtnGuardarParametros"><i class="bi bi-check2-circle"></i> Guardar parámetros</button>
                            </div>
                        </div>
                    </div>
                </div>
                <datalist id="cfgProductosLista"></datalist>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.CP_CFG = {
        url: <?= json_encode($urlCP) ?>,
        perm: <?= json_encode(['crear' => !empty($perm['crear']), 'actualizar' => !empty($perm['actualizar']), 'eliminar' => !empty($perm['eliminar'])]) ?>,
        esAdmin: <?= json_encode($esAdmin) ?>,
        puedeProforma: <?= json_encode($puedeProforma) ?>,
        pagina: <?= (int) $page ?>,
    };
    window.currentSorts = <?= $ordenJson ?: '[]' ?>;
</script>
<script src="<?= $base ?>/js/modulos/cotizador_planes.js?v=<?= asset_ver('/js/modulos/cotizador_planes.js') ?>"></script>
<?php endif; ?>
