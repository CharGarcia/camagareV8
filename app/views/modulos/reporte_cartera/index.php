<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    /* Altura idéntica y explícita para todos los controles de filtros */
    #form-filtros-reporte .form-select,
    #form-filtros-reporte .form-control,
    #form-filtros-reporte .input-group-text,
    #form-filtros-reporte .btn { height:28px; font-size:.75rem; }
    #rc-chips-entidad:empty { margin-top:0; }
</style>

<div class="container-fluid pt-0 pb-3 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">

    <!-- ── Tarjeta de control fija (título + filtros + KPIs) ── -->
    <div class="card cmg-control-card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white border-bottom py-2 px-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Reporte de Cartera</h5>
        </div>
        <div class="card-body p-3">
            <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.RC_generarReporte();">

                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Tipo</label>
                        <select name="tipo" id="rc_tipo" class="form-select form-select-sm shadow-none border" style="width:130px;" onchange="window.RC_cambiarTipo()">
                            <option value="CLIENTE" selected>Cliente</option>
                            <option value="PROVEEDOR">Proveedor</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Año</label>
                        <select id="rc-anio" class="form-select form-select-sm shadow-none border" style="width:90px;" onchange="window.RC_cambiarMesAnio()">
                            <option value="TODOS">Todos</option>
                            <?php for ($a = (int) date('Y'); $a >= (int) date('Y') - 5; $a--): ?>
                                <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Mes</label>
                        <?php $mesActual = date('m'); ?>
                        <select id="rc-mes" class="form-select form-select-sm shadow-none border" style="width:110px;">
                            <option value="TODOS">Todos</option>
                            <option value="01" <?= $mesActual === '01' ? 'selected' : '' ?>>Enero</option>
                            <option value="02" <?= $mesActual === '02' ? 'selected' : '' ?>>Febrero</option>
                            <option value="03" <?= $mesActual === '03' ? 'selected' : '' ?>>Marzo</option>
                            <option value="04" <?= $mesActual === '04' ? 'selected' : '' ?>>Abril</option>
                            <option value="05" <?= $mesActual === '05' ? 'selected' : '' ?>>Mayo</option>
                            <option value="06" <?= $mesActual === '06' ? 'selected' : '' ?>>Junio</option>
                            <option value="07" <?= $mesActual === '07' ? 'selected' : '' ?>>Julio</option>
                            <option value="08" <?= $mesActual === '08' ? 'selected' : '' ?>>Agosto</option>
                            <option value="09" <?= $mesActual === '09' ? 'selected' : '' ?>>Septiembre</option>
                            <option value="10" <?= $mesActual === '10' ? 'selected' : '' ?>>Octubre</option>
                            <option value="11" <?= $mesActual === '11' ? 'selected' : '' ?>>Noviembre</option>
                            <option value="12" <?= $mesActual === '12' ? 'selected' : '' ?>>Diciembre</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Desde</label>
                        <input type="date" name="fecha_desde" id="rc-fecha-desde" class="form-control form-control-sm shadow-none border" style="width:115px;" onchange="window.RC_generarReporte()">
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="rc-fecha-hasta" class="form-control form-control-sm shadow-none border" style="width:115px;" onchange="window.RC_generarReporte()">
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <div class="form-check mb-0 text-nowrap" style="height:28px;display:flex;align-items:center;">
                            <input class="form-check-input" type="checkbox" name="todos" value="1" id="rc-todos" onchange="window.RC_toggleTodos()">
                            <label class="form-check-label small ms-1" for="rc-todos" id="rc-label-todos">Todos los clientes con saldo pendiente</label>
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <span class="text-muted small" style="height:28px;display:flex;align-items:center;">ó</span>
                    </div>

                    <div class="position-relative" style="flex:1 1 200px;">
                        <label class="form-label small fw-bold mb-1 d-block text-muted text-uppercase" style="font-size:.65rem;" id="rc-label-entidad">Cliente(s)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control border-start-0 px-1 shadow-none" id="rc-search-entidad" placeholder="Buscar clientes..." autocomplete="off">
                        </div>
                        <div id="rc-dropdown-entidad" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index:1050;width:100%;max-height:250px;overflow-y:auto;margin-top:2px;"></div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold mb-1 d-block" style="font-size:.65rem;">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm shadow-sm text-nowrap" id="btn-generar-reporte">
                            <i class="bi bi-search me-1"></i> Generar
                        </button>
                    </div>
                </div>
                <div id="rc-chips-entidad" class="d-flex flex-column gap-1 mt-2"></div>
            </form>
        </div>
        <div class="card-footer bg-white border-top py-2 px-3">
            <div class="cmg-control-card__stats">
                <div class="cmg-control-card__stat">
                    <i class="bi bi-people bg-primary bg-opacity-10 text-primary"></i>
                    <div>
                        <div class="cmg-control-card__stat-value" id="stat-entidades">0</div>
                        <div class="cmg-control-card__stat-label" id="rc-label-stat-entidades">Clientes</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-up-circle bg-danger bg-opacity-10 text-danger"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-danger">$<span id="stat-cargos">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Deuda Generada</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-arrow-down-circle bg-success bg-opacity-10 text-success"></i>
                    <div>
                        <div class="cmg-control-card__stat-value text-success">$<span id="stat-abonos">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Total Abonos</div>
                    </div>
                </div>
                <div class="cmg-control-card__stat">
                    <i class="bi bi-cash-stack bg-warning bg-opacity-10 text-warning"></i>
                    <div>
                        <div class="cmg-control-card__stat-value">$<span id="stat-saldo">0.00</span></div>
                        <div class="cmg-control-card__stat-label">Saldo Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Acciones (PDF / Excel / Correo) ── -->
    <div class="d-flex justify-content-end mb-2">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-danger" onclick="window.RC_exportarPDF()" title="Descargar PDF">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-outline-success" onclick="window.RC_exportarExcel()" title="Descargar Excel">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel
            </button>
            <button type="button" class="btn btn-outline-primary" onclick="window.RC_abrirModalCorreo()" title="Enviar por correo">
                <i class="bi bi-envelope"></i> Correo
            </button>
        </div>
    </div>

    <!-- ── Resultados: un estado de cuenta por entidad seleccionada ── -->
    <div id="rc-resultados">
        <div class="text-center text-muted py-5"><i class="bi bi-filter-circle fs-3 d-block mb-2"></i>Selecciona uno o varios clientes y haz clic en Generar para ver el estado de cuenta.</div>
    </div>
</div>

<!-- ── Modal: Enviar por correo ── -->
<div class="modal fade" id="modalCorreoReporte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Enviar estado de cuenta por correo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Destinatarios</label>
                    <input type="text" id="rc-correo-destinatarios" class="form-control form-control-sm" placeholder="correo1@ejemplo.com, correo2@ejemplo.com">
                    <small class="text-muted">Separe varios correos con coma.</small>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Adjuntar</label>
                    <select id="rc-correo-adjuntar" class="form-select form-select-sm">
                        <option value="pdf" selected>Solo PDF</option>
                        <option value="excel">Solo Excel</option>
                        <option value="ambos">PDF y Excel</option>
                    </select>
                </div>
                <small class="text-muted">Se enviará el estado de cuenta de la selección y período actuales.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-enviar-correo-reporte" onclick="window.RC_enviarCorreo()">
                    <i class="bi bi-send me-1"></i> Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const RUTA_MODULO = "<?php echo $rutaModulo; ?>";
</script>
<script src="<?php echo BASE_URL; ?>/js/modulos/reporte_cartera.js?v=<?php echo time(); ?>"></script>
