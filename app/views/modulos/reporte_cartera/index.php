<?php $idModulo = basename($rutaModulo); ?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<style>
    .rc-header { flex-shrink: 0; }
</style>

<div class="container-fluid py-4 px-0 px-md-3" id="modulo-<?php echo $idModulo; ?>">
    <!-- ── Cabecera ── -->
    <div class="rc-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Reporte de Cartera</h5>
            <small class="text-muted">Estado de cuenta cronológico de clientes o proveedores: facturación, cobros/pagos y saldo adeudado</small>
        </div>
    </div>

    <!-- ── Filtros ── -->
    <div class="accordion mb-3 shadow-sm border-0" id="accordionFiltros">
        <div class="accordion-item border-0 rounded-3">
            <h2 class="accordion-header" id="headingFiltros">
                <button class="accordion-button bg-white text-dark py-2 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFiltros" aria-expanded="true" aria-controls="collapseFiltros">
                    <i class="bi bi-funnel me-2 text-primary"></i> <span class="fw-bold small">Filtros</span>
                </button>
            </h2>
            <div id="collapseFiltros" class="accordion-collapse collapse show" aria-labelledby="headingFiltros" data-bs-parent="#accordionFiltros">
                <div class="accordion-body bg-light bg-opacity-10 p-3 pt-2">
                    <form id="form-filtros-reporte" onsubmit="event.preventDefault(); window.RC_generarReporte();" class="row g-3">

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Tipo</label>
                            <select name="tipo" id="rc_tipo" class="form-select form-select-sm shadow-none border" onchange="window.RC_cambiarTipo()">
                                <option value="CLIENTE" selected>Cliente</option>
                                <option value="PROVEEDOR">Proveedor</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Mes</label>
                            <?php $mesActual = date('m'); ?>
                            <select id="rc-mes" class="form-select form-select-sm shadow-none border">
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
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Año</label>
                            <select id="rc-anio" class="form-select form-select-sm shadow-none border" onchange="window.RC_cambiarMesAnio()">
                                <option value="TODOS">Todos</option>
                                <?php for ($a = (int) date('Y'); $a >= (int) date('Y') - 5; $a--): ?>
                                    <option value="<?= $a ?>" <?= $a == date('Y') ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="rc-fecha-desde" class="form-control form-control-sm shadow-none border" onchange="window.RC_generarReporte()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="rc-fecha-hasta" class="form-control form-control-sm shadow-none border" onchange="window.RC_generarReporte()">
                        </div>

                        <div class="w-100 d-none d-md-block m-0"></div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold mb-1 text-muted text-uppercase" style="font-size: 0.65rem;" id="rc-label-entidad">Cliente(s)</label>

                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="form-check mb-0 text-nowrap">
                                    <input class="form-check-input" type="checkbox" name="todos" value="1" id="rc-todos" onchange="window.RC_toggleTodos()">
                                    <label class="form-check-label small" for="rc-todos" id="rc-label-todos">Todos los clientes con saldo pendiente</label>
                                </div>

                                <span class="text-muted small">ó</span>

                                <div class="position-relative flex-grow-1" style="min-width: 300px;">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control border-start-0 px-1 shadow-none" id="rc-search-entidad" placeholder="Buscar clientes..." autocomplete="off">
                                    </div>
                                    <div id="rc-dropdown-entidad" class="list-group shadow dropdown-predictivo position-absolute d-none" style="z-index: 1050; width: 100%; max-height: 250px; overflow-y: auto; margin-top: 2px;"></div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm shadow-sm text-nowrap" id="btn-generar-reporte" style="font-size: 0.75rem; padding: 0.3rem 0.9rem;">
                                    <i class="bi bi-search me-1"></i> Generar Estado de Cuenta
                                </button>
                            </div>

                            <div id="rc-chips-entidad" class="d-flex flex-column gap-1 mt-2"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tarjetas de Estadísticas ── -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;" id="rc-label-stat-entidades">Clientes</h6>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;" id="stat-entidades">0</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-arrow-up-circle fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Deuda Generada</h6>
                        <h4 class="mb-0 fw-bold text-danger" style="font-family: 'Outfit', sans-serif;">$<span id="stat-cargos">0.00</span></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-arrow-down-circle fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Total Abonos</h6>
                        <h4 class="mb-0 fw-bold text-success" style="font-family: 'Outfit', sans-serif;">$<span id="stat-abonos">0.00</span></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem;">Saldo Total</h6>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif;">$<span id="stat-saldo">0.00</span></h4>
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
