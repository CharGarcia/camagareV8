<?php
/**
 * Conciliación de Tarjetas — pantalla principal.
 *
 * Dos vistas sobre lo mismo:
 *   • Pendientes por depositar: cobros con tarjeta que aún no aparecen en ningún
 *     estado de cuenta de la procesadora (con semáforo de atraso).
 *   • Conciliaciones: las sesiones de cruce ya armadas.
 *
 * Página con filtros y KPIs encima de la tabla → app-shell desactivado y tarjeta
 * de control fija (§9).
 */
$idModulo = basename($rutaModulo);
$puedeCrear      = !empty($perm['crear']);
$puedeActualizar = !empty($perm['actualizar']);
$puedeEliminar   = !empty($perm['eliminar']);
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .ctar-scroll { overflow-x:auto; }
    .ctar-scroll thead th { background:#f8f9fa; box-shadow:0 1px 0 #dee2e6; white-space:nowrap; }

    /* Semáforo de atraso: días que un cobro lleva sin aparecer en el estado de cuenta */
    .ctar-dias-ok      { background:rgba(25,135,84,.12);  color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-dias-alerta  { background:rgba(255,193,7,.15);  color:#856404; border:1px solid rgba(255,193,7,.35); }
    .ctar-dias-tarde   { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }

    .ctar-estado-borrador { background:rgba(108,117,125,.12); color:#6c757d; border:1px solid rgba(108,117,125,.25); }
    .ctar-estado-cerrada  { background:rgba(25,135,84,.12);   color:#198754; border:1px solid rgba(25,135,84,.25); }
    .ctar-estado-anulada   { background:rgba(220,53,69,.12);  color:#dc3545; border:1px solid rgba(220,53,69,.25); }

    /* Altura idéntica y explícita en todos los controles de filtros (§9) */
    #form-filtros-ctar .form-select,
    #form-filtros-ctar .form-control,
    #form-filtros-ctar .input-group-text,
    #form-filtros-ctar .btn { height:28px; font-size:.75rem; }

    /* La tabla se extiende libremente: hace scroll la página, no un contenedor interno */
    @media (max-width: 767.98px) {
        #modulo-<?php echo $idModulo; ?> .ctar-scroll { max-height:none !important; height:auto !important; overflow-y:visible !important; }
    }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Conciliación de Tarjetas</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-ctar" onsubmit="event.preventDefault(); CTAR_cargar();" class="d-flex flex-wrap align-items-start gap-2">

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Procesadora</label>
                    <select id="ctar-procesadora" class="form-select form-select-sm shadow-none border" style="width:160px;" onchange="CTAR_cargar()">
                        <option value="">Todas</option>
                        <?php foreach ($procesadoras as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>"
                                    data-cuenta="<?php echo htmlspecialchars((string) ($p['cuenta_codigo'] ?? '')); ?>"
                                    data-dias="<?php echo (int) ($p['dias_liquidacion'] ?? 2); ?>">
                                <?php echo htmlspecialchars($p['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Estado</label>
                    <select id="ctar-estado" class="form-select form-select-sm shadow-none border" style="width:130px;" onchange="CTAR_cargar()">
                        <option value="">Todos</option>
                        <option value="borrador">En borrador</option>
                        <option value="cerrada">Cerradas</option>
                        <option value="anulada">Anuladas</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                    <input type="date" id="ctar-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                    <input type="date" id="ctar-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;">
                </div>

                <!-- Buscador + botones: agrupados para que nunca se separen al hacer wrap -->
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="position-relative" style="width:440px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="ctar-buscar" class="form-control border-start-0 px-1 shadow-none"
                                   placeholder="Cliente, documento, número o autorización..." autocomplete="off"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();CTAR_cargar();}">
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2" onclick="CTAR_limpiarFiltros()">
                                <i class="bi bi-eraser me-1"></i>Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm">
                                <i class="bi bi-search me-1"></i>Aplicar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats" id="ctar-stats-row">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-hourglass-split bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-warning">$<span id="ctar-stat-pendiente">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Por depositar</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-receipt bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="ctar-stat-cobros">0</div>
                        <div class="cmg-control-card__stat-label">Cobros pendientes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-clock-history bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger"><span id="ctar-stat-dias">0</span></div>
                        <div class="cmg-control-card__stat-label">Días del más antiguo</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-check2-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="ctar-stat-conciliado">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Conciliado (filtro)</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-percent bg-secondary bg-opacity-10 text-secondary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="ctar-stat-comision">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Comisiones</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjeta de listado ── -->
    <div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <ul class="nav nav-pills nav-sm gap-1" id="ctar-tabs">
                    <li class="nav-item">
                        <button class="nav-link active btn-sm py-1 px-3" id="ctar-tab-pendientes"
                                onclick="CTAR_setVista('pendientes')">
                            <i class="bi bi-hourglass-split me-1"></i>Pendientes por depositar
                            <span class="badge bg-white text-dark ms-1" id="ctar-badge-pendientes">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link btn-sm py-1 px-3" id="ctar-tab-conciliaciones"
                                onclick="CTAR_setVista('conciliaciones')">
                            <i class="bi bi-list-check me-1"></i>Conciliaciones
                        </button>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger" onclick="CTAR_exportarPDF()" title="Exportar a PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="CTAR_exportarExcel()" title="Exportar a Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                        </button>
                    </div>
                    <?php if ($puedeActualizar): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="CTAR_abrirConfig()"
                                title="Cuentas contables y valores por defecto de cada procesadora">
                            <i class="bi bi-gear"></i> Configuración
                        </button>
                    <?php endif; ?>
                    <?php if ($puedeCrear): ?>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="CTAR_nueva()">
                            <i class="bi bi-plus-lg me-1"></i>Nueva conciliación
                        </button>
                    <?php endif; ?>
                    <small class="text-muted fw-medium" id="ctar-count-label"></small>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <!-- Vista 1: cobros pendientes por depositar -->
            <div id="ctar-vista-pendientes">
                <div class="ctar-scroll w-100">
                    <table class="table table-hover table-sm mb-0 align-middle" id="tabla-ctar-pendientes" style="min-width:1100px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" data-col="ct_fecha">Fecha cobro</th>
                                <th data-col="ct_documento">Documento</th>
                                <th data-col="ct_cliente">Cliente</th>
                                <th data-col="ct_ingreso">Ingreso</th>
                                <th data-col="ct_autorizacion">Autorización</th>
                                <th class="text-end" data-col="ct_monto">Monto</th>
                                <th class="text-center" data-col="ct_dias">Días</th>
                            </tr>
                        </thead>
                        <tbody id="ctar-tbody-pendientes">
                            <tr><td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-credit-card-2-front fs-3 d-block mb-2 text-primary opacity-50"></i>
                                Seleccione una procesadora para ver sus cobros pendientes de depósito.
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vista 2: conciliaciones registradas -->
            <div id="ctar-vista-conciliaciones" class="d-none">
                <div class="ctar-scroll w-100">
                    <table class="table table-hover table-sm mb-0 align-middle" id="tabla-ctar" style="min-width:1240px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" data-col="c_numero">Número</th>
                                <th data-col="c_fecha">Fecha</th>
                                <th data-col="c_procesadora">Procesadora</th>
                                <th data-col="c_destino">Depositado en</th>
                                <th class="text-center" data-col="c_cobros">Cobros</th>
                                <th class="text-end" data-col="c_bruto">Bruto</th>
                                <th class="text-end" data-col="c_comision">Comisión</th>
                                <th class="text-end" data-col="c_retenciones">Retenciones</th>
                                <th class="text-end" data-col="c_neto">Neto</th>
                                <th class="text-center" data-col="c_estado">Estado</th>
                                <th class="text-center" data-col="c_asiento">Asiento</th>
                                <th class="text-center" data-col="c_acciones">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="ctar-tbody">
                            <tr><td colspan="12" class="text-center py-5 text-muted">
                                <i class="bi bi-list-check fs-3 d-block mb-2 text-primary opacity-50"></i>
                                Cargando conciliaciones…
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/modal_conciliacion.php'; ?>
<?php include __DIR__ . '/modal_config.php'; ?>

<script>
    const CTAR_URL   = '<?php echo BASE_URL; ?>/modulos/conciliacion-tarjetas';
    const CTAR_PERM  = {
        crear:      <?php echo $puedeCrear ? 'true' : 'false'; ?>,
        actualizar: <?php echo $puedeActualizar ? 'true' : 'false'; ?>,
        eliminar:   <?php echo $puedeEliminar ? 'true' : 'false'; ?>
    };
    const CTAR_PROCESADORAS = <?php echo json_encode($procesadoras, JSON_UNESCAPED_UNICODE); ?>;
    const CTAR_DESTINOS     = <?php echo json_encode($destinos, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/conciliacion_tarjetas.js?v=<?php echo time(); ?>"></script>
