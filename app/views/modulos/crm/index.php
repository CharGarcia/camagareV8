<?php
/**
 * CRM (modulos/crm): tarjeta de control fija (filtros + KPIs) y cuatro vistas:
 * Embudo (Kanban), Lista, Contactos y Mis actividades. Todo se carga por AJAX
 * desde CrmController; la lógica de pantalla vive en public/js/modulos/crm.js.
 * Diseño: docs/diseno/crm-redes-fase1.md §9.1.
 *
 * @var array  $perm
 * @var string $rutaModulo
 * @var array  $vistaConfig
 * @var string $ordenJson
 * @var int    $perPage
 * @var bool   $disponible
 * @var array  $columnasLista
 * @var bool   $puedeProforma
 * @var bool   $puedeCliente
 */
use App\Helpers\PreferenciasHelper;

$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    #form-filtros-crm .form-select,
    #form-filtros-crm .form-control,
    #form-filtros-crm .input-group-text,
    #form-filtros-crm .btn { height:28px; font-size:.75rem; }

    /* Kanban: columnas de ancho fijo con scroll horizontal; la página hace el scroll vertical. */
    .crm-kanban-scroll { overflow-x:auto; padding-bottom:6px; }
    .crm-kanban { display:flex; gap:12px; align-items:flex-start; min-height:200px; }
    .crm-col { flex:0 0 272px; background:#f4f6f8; border-radius:.6rem; display:flex; flex-direction:column; max-width:272px; }
    .crm-col-head { padding:8px 10px 6px; border-top:4px solid var(--crm-col, #6c757d); border-radius:.6rem .6rem 0 0; }
    .crm-col-body { padding:0 8px 8px; min-height:80px; display:flex; flex-direction:column; gap:8px; }
    .crm-card { background:#fff; border:1px solid #e3e6ea; border-radius:.5rem; padding:8px 10px; cursor:grab; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .crm-card:hover { border-color:#b6c2cf; }
    .crm-card.sortable-ghost { opacity:.4; }
    .crm-card .crm-card-titulo { font-weight:600; font-size:.82rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .crm-card .crm-card-sub { font-size:.72rem; color:#6c757d; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .crm-card .crm-dot { width:8px; height:8px; border-radius:50%; background:#dc3545; display:inline-block; }
    .crm-avatar { width:22px; height:22px; border-radius:50%; background:#e7f1ff; color:#0d6efd; font-size:.62rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
    .crm-col-foot { font-size:.7rem; color:#6c757d; padding:0 10px 8px; }

    /* Lista y Contactos: la tabla se extiende hacia abajo (scrollea la página). */
    .crm-scroll { overflow-x:auto; }
    .crm-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }
    .crm-scroll thead th.sortable-header { cursor:pointer; user-select:none; }
    .crm-scroll td { white-space:nowrap; }
    .crm-row { cursor:pointer; }
    @media (max-width: 767.98px) {
        #modulo-crm .crm-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
    /* Reportes: barra de magnitud de una sola serie (un tono), con el valor escrito al lado. */
    .crm-barra { height:10px; background:#e9ecef; border-radius:4px; overflow:hidden; min-width:60px; }
    .crm-barra > span { display:block; height:100%; background:#3b82f6; border-radius:0 4px 4px 0; }
    .crm-r-tabla td, .crm-r-tabla th { font-size:.8rem; white-space:nowrap; }
    /* Selector tipo chip (cliente / contacto): limpiar de una vez con Backspace/Delete. */
    .crm-dd { z-index:1080; max-height:240px; overflow-y:auto; }
</style>

<?= PreferenciasHelper::renderEstilosColumnasOcultas($vistaConfig ?? []) ?>
<?= PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfig ?? []) ?>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-crm">

<?php if (!$disponible): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mt-2">
        <i class="bi bi-database-exclamation fs-4"></i>
        <div>El CRM todavía no está instalado en la base de datos. Falta aplicar
            <code>database/2026-11-08_crm_fase1.sql</code>.</div>
    </div>
<?php else: ?>

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-funnel me-2 text-primary"></i>CRM</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-crm" onsubmit="event.preventDefault(); CRM.recargar();" class="d-flex flex-wrap align-items-start gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Embudo <?= PreferenciasHelper::renderEstrellaFavorito($rutaModulo, 'crm-f-embudo', 'id_embudo') ?></label>
                    <select id="crm-f-embudo" class="form-select form-select-sm shadow-none border" style="width:140px;" onchange="CRM.cambiarEmbudo()"></select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="crm-f-estado" class="form-select form-select-sm shadow-none border" style="width:120px;" onchange="CRM.recargar()"
                            title="El Embudo (Kanban) muestra siempre las abiertas">
                        <option value="">Todas</option>
                        <option value="abierta" selected>Abiertas</option>
                        <option value="ganada">Ganadas</option>
                        <option value="perdida">Perdidas</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Responsable</label>
                    <select id="crm-f-responsable" class="form-select form-select-sm shadow-none border" style="width:140px;" onchange="CRM.recargar()">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Origen</label>
                    <select id="crm-f-origen" class="form-select form-select-sm shadow-none border" style="width:140px;" onchange="CRM.recargar()">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cierre desde</label>
                    <input type="date" id="crm-f-cierre-desde" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Cierre hasta</label>
                    <input type="date" id="crm-f-cierre-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>
                <!-- Buscador + botones: un solo ítem, para que salten juntos de línea. -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div style="width:440px;max-width:100%;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="search" id="crm-f-buscar" class="form-control border-start-0 px-1 shadow-none" autocomplete="off"
                                   placeholder="Título, contacto, teléfono… o etapa:propuesta, valor:>=1000"
                                   title="Texto libre o filtros clave:valor — titulo, contacto, etapa, embudo, origen, responsable, vendedor, estado, numero, cierre, creado, cerrada, valor, probabilidad">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="CRM.limpiarFiltros()" title="Limpiar filtros">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-2 shadow-sm" title="Mostrar">
                                <i class="bi bi-search me-1"></i>Mostrar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-kanban bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="crm-k-abiertas">0</div>
                        <div class="cmg-control-card__stat-label">Abiertas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-info">$<span id="crm-k-valor">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Valor del embudo</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-percent bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="crm-k-ponderado">0.00</span></div>
                        <div class="cmg-control-card__stat-label" title="Valor × probabilidad de la etapa">Valor ponderado</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-trophy bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success"><span id="crm-k-ganadas">0</span> · $<span id="crm-k-ganado">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Ganadas este mes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-x-octagon bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger" id="crm-k-perdidas">0</div>
                        <div class="cmg-control-card__stat-label">Perdidas este mes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Vistas ── -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <ul class="nav nav-pills nav-sm gap-1" id="crm-vistas" role="tablist">
            <li class="nav-item"><button class="nav-link py-1 px-3 active" data-crm-vista="kanban" type="button"><i class="bi bi-kanban me-1"></i>Embudo</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-crm-vista="lista" type="button"><i class="bi bi-list-ul me-1"></i>Lista</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-crm-vista="contactos" type="button"><i class="bi bi-person-lines-fill me-1"></i>Contactos</button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-crm-vista="actividades" type="button"><i class="bi bi-check2-square me-1"></i>Mis actividades <span class="badge bg-danger ms-1 d-none" id="crm-badge-vencidas">0</span></button></li>
            <li class="nav-item"><button class="nav-link py-1 px-3" data-crm-vista="reportes" type="button"><i class="bi bi-bar-chart me-1"></i>Reportes</button></li>
        </ul>
        <div class="d-flex gap-1">
            <?php if (!empty($perm['crear'])): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="CRM.nuevoContacto()"><i class="bi bi-person-plus me-1"></i>Nuevo contacto</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="CRM.nuevaOportunidad()"><i class="bi bi-plus-lg me-1"></i>Nueva oportunidad</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Embudo (Kanban) -->
    <section data-crm-panel="kanban">
        <div class="crm-kanban-scroll"><div class="crm-kanban" id="crm-kanban"></div></div>
        <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>Arrastre las tarjetas para cambiarlas de etapa. Para ganar o perder una oportunidad, ábrala.</div>
    </section>

    <!-- Lista -->
    <section data-crm-panel="lista" class="d-none">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold fs-6">Oportunidades</h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="CRM.exportar('Pdf')" title="Exportar a PDF"><i class="bi bi-file-earmark-pdf"></i><span class="d-none d-md-inline"> PDF</span></button>
                        <button type="button" class="btn btn-outline-success" onclick="CRM.exportar('Excel')" title="Exportar a Excel"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> Excel</span></button>
                    </div>
                    <?= PreferenciasHelper::renderDropdownColumnas($columnasLista, $vistaConfig ?? [], $rutaModulo) ?>
                    <small class="text-muted fw-medium" id="crm-lista-info"></small>
                    <div class="btn-group btn-group-sm" id="crm-lista-paginacion"></div>
                    <?= PreferenciasHelper::renderSelectorPorPagina($perPage, 'crm-por-pagina', 'CRM.cambiarPorPagina(this.value)') ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="crm-scroll w-100">
                    <table class="table table-hover table-sm mb-0 align-middle" style="table-layout:auto;min-width:100%;">
                        <thead>
                            <tr>
                                <?php
                                $alineado = ['valor_estimado' => 'text-end', 'probabilidad' => 'text-end', 'estado' => 'text-center'];
                                foreach ($columnasLista as $col => $etq): ?>
                                    <th class="sortable-header <?= $alineado[$col] ?? '' ?> <?= $col === 'numero' ? 'ps-3' : '' ?>" role="button"
                                        data-sort="<?= $h($col) ?>" data-col="<?= $h($col) ?>"><?= $h($etq) ?> <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody id="crm-lista-tbody">
                            <tr><td colspan="<?= count($columnasLista) ?>" class="text-center text-muted py-4">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Contactos -->
    <section data-crm-panel="contactos" class="d-none">
        <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold fs-6">Contactos</h5>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted fw-medium" id="crm-contactos-info"></small>
                    <div class="btn-group btn-group-sm" id="crm-contactos-paginacion"></div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="crm-scroll w-100">
                    <table class="table table-hover table-sm mb-0 align-middle" style="min-width:100%;">
                        <thead>
                            <tr>
                                <th class="ps-3">Nombre</th><th>Empresa</th><th>Teléfono</th><th>Correo</th>
                                <th>Instagram</th><th>Origen</th><th>Responsable</th><th>Cliente</th>
                                <th class="text-center">Oport. abiertas</th>
                            </tr>
                        </thead>
                        <tbody id="crm-contactos-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Mis actividades -->
    <section data-crm-panel="actividades" class="d-none">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-2 px-3 border-bottom">
                <h5 class="mb-0 fw-bold fs-6">Mis actividades pendientes</h5>
            </div>
            <div class="list-group list-group-flush" id="crm-actividades-lista"></div>
        </div>
    </section>

    <!-- Reportes del embudo: período propio + embudo, responsable y origen de la tarjeta de control -->
    <section data-crm-panel="reportes" class="d-none">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <div>
                <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                <input type="date" id="crm-r-desde" class="form-control form-control-sm" style="width:130px;">
            </div>
            <div>
                <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                <input type="date" id="crm-r-hasta" class="form-control form-control-sm" style="width:130px;">
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="CRM.recargar()"><i class="bi bi-search me-1"></i>Mostrar</button>
            <small class="text-muted ms-1">Embudo, responsable y origen se toman de la tarjeta de arriba. Creadas = entraron en el período; ganadas/perdidas = se cerraron en el período.</small>
        </div>
        <div class="row g-2 mb-3" id="crm-r-kpis"></div>
        <div class="row g-3">
            <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white py-2 fw-bold small">Embudo hoy, por etapa</div><div class="card-body p-0" id="crm-r-etapas"></div></div></div>
            <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white py-2 fw-bold small">Días promedio en cada etapa</div><div class="card-body p-0" id="crm-r-tiempo"></div></div></div>
            <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white py-2 fw-bold small">Por mes</div><div class="card-body p-0" id="crm-r-meses"></div></div></div>
            <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white py-2 fw-bold small">Por origen</div><div class="card-body p-0" id="crm-r-origen"></div></div></div>
            <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white py-2 fw-bold small">Por responsable</div><div class="card-body p-0" id="crm-r-responsable"></div></div></div>
            <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white py-2 fw-bold small">Motivos de pérdida</div><div class="card-body p-0" id="crm-r-motivos"></div></div></div>
        </div>
    </section>

<?php endif; ?>
</div>

<?php if ($disponible): ?>
    <?php include __DIR__ . '/modal_oportunidad.php'; ?>
    <?php include __DIR__ . '/modal_contacto.php'; ?>
    <?php include __DIR__ . '/modales_acciones.php'; ?>
    <?php
    // Modal de Clientes para «crear al vuelo» desde Ganar / Crear proforma. Resuelve sus
    // propios permisos cuando $rutaModulo no es modulos/clientes: se respaldan $perm y
    // $rutaModulo por si los pisa (mismo patrón que guias_remision/index.php).
    if (!empty($puedeCliente)) {
        $crmPermRespaldo = $perm;
        $crmRutaRespaldo = $rutaModulo;
        include_once dirname(__DIR__) . '/clientes/modal_cliente.php';
        $perm       = $crmPermRespaldo;
        $rutaModulo = $crmRutaRespaldo;
    }
    ?>
    <script>
        window.CRM_CONFIG = {
            urlBase:        <?= json_encode($urlBase) ?>,
            rutaModulo:     <?= json_encode($rutaModulo) ?>,
            sorts:          <?= $ordenJson ?: '[]' ?>,
            perPage:        <?= (int) $perPage ?>,
            idUsuario:      <?= (int) $idUsuario ?>,
            puedeProforma:  <?= json_encode(!empty($puedeProforma)) ?>,
            puedeCliente:   <?= json_encode(!empty($puedeCliente)) ?>,
            perm: {
                crear:      <?= json_encode(!empty($perm['crear'])) ?>,
                actualizar: <?= json_encode(!empty($perm['actualizar'])) ?>,
                eliminar:   <?= json_encode(!empty($perm['eliminar'])) ?>,
            },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <?php if (!empty($puedeCliente)): ?>
        <script src="<?= $base ?>/js/modulos/clientes_modal.js?v=<?= asset_ver('/js/modulos/clientes_modal.js') ?>"></script>
    <?php endif; ?>
    <script src="<?= $base ?>/js/modulos/crm.js?v=<?= asset_ver('/js/modulos/crm.js') ?>"></script>
<?php endif; ?>
