<?php
/**
 * Vista: Métricas del sistema (solo lectura, nivel 3). Datos globales de la plataforma.
 * Fase A: todo en vivo; las pestañas se cargan por AJAX al abrirlas (MET_cargar).
 *
 * @var string $titulo
 * @var string $desde
 * @var string $hasta
 */
$base = BASE_URL;
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    #form-filtros-metricas .form-select,
    #form-filtros-metricas .form-control,
    #form-filtros-metricas .btn { height:28px; font-size:.75rem; }
    .metricas-scroll { overflow-x:auto; }
    .metricas-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; font-size:.72rem; text-transform:uppercase; color:#6c757d; }
    .metricas-scroll td { font-size:.8rem; white-space:nowrap; }
    .metricas-scroll td.met-wrap { white-space:normal; min-width:260px; }
    .met-kpi { border:1px solid #e9ecef; border-radius:.5rem; padding:.6rem .8rem; background:#fff; height:100%; }
    .met-kpi__valor { font-size:1.25rem; font-weight:700; line-height:1.2; }
    .met-kpi__etq { font-size:.72rem; color:#6c757d; text-transform:uppercase; letter-spacing:.02em; }
    .met-kpi__sub { font-size:.72rem; color:#6c757d; }
    .met-chart { position:relative; height:260px; }
    .met-card-titulo { font-size:.8rem; font-weight:700; text-transform:uppercase; color:#495057; }
    #met-tabs .nav-link { font-size:.82rem; }
    @media (max-width: 767.98px) {
        #modulo-metricas-sistema .metricas-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-metricas-sistema">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2 text-primary"></i><?= htmlspecialchars($titulo) ?></h5>
            <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm px-2" style="font-size:.75rem;">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-metricas" onsubmit="event.preventDefault(); MET_mostrar(false);" class="d-flex flex-wrap align-items-start gap-2">
                <div style="width:150px;">
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Periodo</label>
                    <select id="met-periodo" class="form-select form-select-sm shadow-none border" onchange="MET_aplicarPeriodo(this.value)">
                        <option value="hoy">Hoy</option>
                        <option value="7d">Últimos 7 días</option>
                        <option value="30d">Últimos 30 días</option>
                        <option value="mes" selected>Este mes</option>
                        <option value="mes_ant">Mes anterior</option>
                        <option value="anio">Este año</option>
                        <option value="12m">Últimos 12 meses</option>
                        <option value="24m">Últimos 2 años (Tendencias)</option>
                        <option value="5a">Últimos 5 años (Tendencias)</option>
                        <option value="otro">Personalizado</option>
                    </select>
                </div>
                <div style="width:125px;">
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Desde</label>
                    <input type="date" id="met-desde" class="form-control form-control-sm shadow-none border" value="<?= htmlspecialchars($desde) ?>" onchange="document.getElementById('met-periodo').value='otro'">
                </div>
                <div style="width:125px;">
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Hasta</label>
                    <input type="date" id="met-hasta" class="form-control form-control-sm shadow-none border" value="<?= htmlspecialchars($hasta) ?>" onchange="document.getElementById('met-periodo').value='otro'">
                </div>
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="form-label small mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="MET_limpiar()"><i class="bi bi-eraser me-1"></i>Limpiar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-search me-1"></i>Mostrar</button>
                            <button type="button" class="btn btn-outline-primary btn-sm px-2" title="Recalcular sin usar la caché" onclick="MET_mostrar(true)"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                    </div>
                </div>
                <div class="ms-auto align-self-end small text-muted" style="font-size:.7rem;">
                    Solo documentos de producción · <span id="met-generado">—</span>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-broadcast bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="met-st-conectados">0</div>
                        <div class="cmg-control-card__stat-label">Conectados ahora</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-person-check bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="met-st-usuarios-hoy">0</div>
                        <div class="cmg-control-card__stat-label">Usuarios hoy · <span id="met-st-usuarios">0</span> activos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-building bg-info bg-opacity-10 text-info"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="met-st-empresas">0</div>
                        <div class="cmg-control-card__stat-label">Empresas activas</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat" id="met-st-conectadas-wrap">
                    <i class="bi bi-plug bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success" id="met-st-conectadas">—</div>
                        <div class="cmg-control-card__stat-label">Empresas conectadas · <span id="met-st-conectadas-hoy">—</span> hoy</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-activity bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="met-st-empresas-mov">0</div>
                        <div class="cmg-control-card__stat-label">Con movimiento</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-file-earmark-check bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="met-st-docs">0</div>
                        <div class="cmg-control-card__stat-label">Documentos SRI</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="met-st-ventas">0.00</span></div>
                        <div class="cmg-control-card__stat-label"><span id="met-st-facturas">0</span> facturas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pestañas ── -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom pb-0 px-3 pt-2">
            <ul class="nav nav-tabs border-0" id="met-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#met-pane-usuarios" data-seccion="usuarios" type="button"><i class="bi bi-people me-1"></i>Usuarios</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-empresas" data-seccion="empresas" type="button"><i class="bi bi-building me-1"></i>Empresas</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-documentos" data-seccion="documentos" type="button"><i class="bi bi-files me-1"></i>Documentos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-productos" data-seccion="productos" type="button"><i class="bi bi-box-seam me-1"></i>Productos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-sri" data-seccion="sri" type="button"><i class="bi bi-cloud-check me-1"></i>SRI</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-errores" data-seccion="errores" type="button"><i class="bi bi-bug me-1"></i>Errores</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-tendencias" data-seccion="tendencias" type="button"><i class="bi bi-graph-up me-1"></i>Tendencias</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-modulos" data-seccion="modulos" type="button"><i class="bi bi-grid-3x3-gap me-1"></i>Uso de módulos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-comercial" data-seccion="comercial" type="button"><i class="bi bi-cash-coin me-1"></i>Comercial</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#met-pane-salud" data-seccion="salud" type="button"><i class="bi bi-heart-pulse me-1"></i>Salud técnica</button></li>
            </ul>
        </div>
        <div class="card-body p-3 tab-content">
            <?php foreach (['usuarios', 'empresas', 'documentos', 'productos', 'sri', 'errores', 'tendencias', 'modulos', 'comercial', 'salud'] as $i => $sec): ?>
            <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="met-pane-<?= $sec ?>" role="tabpanel">
                <div class="text-center text-muted py-5 small"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>window.BASE_URL = '<?= $base ?>';</script>
<script src="<?= $base ?>/js/config/metricas_sistema.js?v=<?= asset_ver('/js/config/metricas_sistema.js') ?>"></script>
