<?php
/**
 * Modal «Devengar mes» (NIIF 15): asiento consolidado del mes con lo diferido que pasa al
 * ingreso y la provisión de mes caído. Vista previa antes de generar; revertir si el mes ya
 * tiene asiento. Lógica en public/js/modulos/suscripciones_devengo_mes.js.
 */
$urlSuscDev   = rtrim(BASE_URL, '/') . '/modulos/suscripciones';
$mesAnterior  = (new \DateTimeImmutable('first day of this month'))->modify('-1 month')->format('Y-m');
$mesActual    = date('Y-m');
?>
<div class="modal fade" id="modalDevengoMes" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fs-6 fw-bold text-dark"><i class="bi bi-calendar-check text-primary me-2"></i>Devengar ingresos de suscripciones</h5>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <div style="width: 170px;">
                        <label class="form-label small fw-bold text-muted mb-1 d-block" for="dm_mes">Mes</label>
                        <input type="month" class="form-control form-control-sm" id="dm_mes" value="<?= $mesAnterior ?>" max="<?= $mesActual ?>">
                    </div>
                    <div class="text-muted small flex-grow-1" style="min-width: 220px;">
                        Pasa al ingreso lo diferido del mes (y lo que quedó pendiente de meses anteriores) y
                        provisiona el servicio de mes caído ya prestado. Asiento con fecha del último día del mes.
                        <br><a href="#" class="small" onclick="event.preventDefault(); bootstrap.Modal.getInstance(document.getElementById('modalDevengoMes'))?.hide(); SuscIngresosDiferidos.abrirApertura();">
                            <i class="bi bi-box-arrow-in-right"></i> Apertura: facturas emitidas antes de activar el devengado</a>
                    </div>
                </div>

                <div data-dm="cargando" class="text-center text-muted small py-4 d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span> Calculando…
                </div>
                <div data-dm="error" class="alert alert-danger small py-2 d-none"></div>

                <div data-dm="contenido" class="d-none">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="card bg-light border-0 text-center p-2">
                                <span class="small text-muted d-block" style="font-size: .7rem;">DIFERIDO A DEVENGAR</span>
                                <h6 class="mb-0 fw-bold text-primary" data-dm="diferido">$0.00</h6>
                                <span class="text-muted" style="font-size: .68rem;" data-dm="diferido-filas"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-light border-0 text-center p-2">
                                <span class="small text-muted d-block" style="font-size: .7rem;">DE MESES ANTERIORES</span>
                                <h6 class="mb-0 fw-bold text-warning" data-dm="anteriores">$0.00</h6>
                                <span class="text-muted" style="font-size: .68rem;">incluido en el diferido</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-light border-0 text-center p-2">
                                <span class="small text-muted d-block" style="font-size: .7rem;">PROVISIÓN MES CAÍDO</span>
                                <h6 class="mb-0 fw-bold text-success" data-dm="provision">$0.00</h6>
                                <span class="text-muted" style="font-size: .68rem;" data-dm="provision-susc"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-light border-0 text-center p-2">
                                <span class="small text-muted d-block" style="font-size: .7rem;">ESPERANDO ASIENTO</span>
                                <h6 class="mb-0 fw-bold text-secondary" data-dm="esperando">$0.00</h6>
                                <span class="text-muted" style="font-size: .68rem;">facturas sin asiento aún</span>
                            </div>
                        </div>
                    </div>

                    <div data-dm="aviso" class="alert alert-warning small py-2 d-none"></div>
                    <div data-dm="vacio" class="text-center text-muted small py-3 d-none">
                        <i class="bi bi-check2-circle fs-4 d-block mb-1"></i>
                        No hay nada por devengar ni provisionar en este mes.
                    </div>

                    <div data-dm="asiento-wrap" class="d-none">
                        <div class="small fw-bold text-muted mb-1">Asiento que se generará (<span data-dm="fecha"></span>)</div>
                        <div class="border rounded-3 bg-white">
                            <table class="table table-sm mb-0 small">
                                <thead class="table-light">
                                    <tr><th class="ps-2">Cuenta</th><th>Concepto</th><th class="text-end">Debe</th><th class="text-end pe-2">Haber</th></tr>
                                </thead>
                                <tbody data-dm="asiento"></tbody>
                            </table>
                        </div>
                    </div>

                    <div data-dm="existentes-wrap" class="mt-3 d-none">
                        <div class="small fw-bold text-muted mb-1">Asientos de devengo ya registrados en este mes</div>
                        <ul class="list-group list-group-flush small border rounded-3" data-dm="existentes"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar'] ?? false): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" data-dm="btn-revertir">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Revertir mes
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i>Cerrar
                    </button>
                    <?php if ($perm['crear'] ?? false): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3" data-dm="btn-generar" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Generar asiento
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/suscripciones_devengo_mes.js?v=<?= asset_ver('/js/modulos/suscripciones_devengo_mes.js') ?>"></script>
<script>
    SuscDevengoMes.iniciar({
        modalId:     'modalDevengoMes',
        urlPreview:  <?= json_encode($urlSuscDev . '/devengoMesPreviewAjax') ?>,
        urlGenerar:  <?= json_encode($urlSuscDev . '/devengarMesAjax') ?>,
        urlRevertir: <?= json_encode($urlSuscDev . '/revertirDevengoMesAjax') ?>,
    });
</script>
