<?php
/**
 * Modal «Apertura de ingresos diferidos» (NIIF 15): facturas/recibos de suscripción emitidos antes
 * de activar el devengado; arma su cronograma para los meses que faltan con un asiento de
 * reclasificación. Se abre desde «Devengar mes». Lógica en public/js/modulos/suscripciones_apertura_devengo.js.
 * El reporte de saldos vive en su propio módulo: modulos/reporte_ingresos_diferidos.
 */
$urlSuscId    = rtrim(BASE_URL, '/') . '/modulos/suscripciones';
$mesAnteriorI = (new \DateTimeImmutable('first day of this month'))->modify('-1 month')->format('Y-m');
$mesActualI   = date('Y-m');
?>
<!-- ══ Apertura del devengado ══════════════════════════════════════════════ -->
<div class="modal fade" id="modalAperturaDevengo" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title fs-6 fw-bold text-dark"><i class="bi bi-box-arrow-in-right text-primary me-2"></i>Apertura de ingresos diferidos</h5>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                    <div style="width: 170px;">
                        <label class="form-label small fw-bold text-muted mb-1 d-block" for="apd_mes">Mes de corte</label>
                        <input type="month" class="form-control form-control-sm" id="apd_mes" value="<?= $mesAnteriorI ?>" max="<?= $mesActualI ?>">
                    </div>
                    <div class="text-muted small flex-grow-1" style="min-width: 220px;">
                        Facturas y recibos de suscripciones que reconocen <b>durante el período</b>, emitidos antes de
                        activar el devengado (su asiento reconoció todo como ingreso). La parte de los meses
                        posteriores al corte pasa a <i>Ingresos diferidos</i> con un asiento al último día del mes de corte.
                    </div>
                </div>

                <div data-apd="cargando" class="text-center text-muted small py-4 d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span> Calculando…
                </div>
                <div data-apd="error" class="alert alert-danger small py-2 d-none"></div>
                <div data-apd="contenido" class="d-none">
                    <div data-apd="aviso" class="alert alert-warning small py-2 d-none"></div>
                    <div data-apd="vacio" class="text-center text-muted small py-3 d-none">
                        <i class="bi bi-check2-circle fs-4 d-block mb-1"></i>
                        No hay documentos con meses por diferir después del mes de corte.
                    </div>
                    <div data-apd="docs-wrap" class="d-none">
                        <div class="small fw-bold text-muted mb-1">Documentos (<span data-apd="total"></span> a diferir)</div>
                        <div class="border rounded-3 bg-white mb-3" style="max-height: 260px; overflow: auto;">
                            <table class="table table-sm mb-0 small">
                                <thead class="table-light"><tr><th class="ps-2">Documento</th><th>Fecha</th><th>Cliente</th><th class="text-end pe-2">A diferir</th></tr></thead>
                                <tbody data-apd="docs"></tbody>
                            </table>
                        </div>
                        <div class="small fw-bold text-muted mb-1">Asiento que se generará (<span data-apd="fecha"></span>)</div>
                        <div class="border rounded-3 bg-white">
                            <table class="table table-sm mb-0 small">
                                <thead class="table-light"><tr><th class="ps-2">Cuenta</th><th>Concepto</th><th class="text-end">Debe</th><th class="text-end pe-2">Haber</th></tr></thead>
                                <tbody data-apd="asiento"></tbody>
                            </table>
                        </div>
                    </div>
                    <div data-apd="existentes-wrap" class="mt-3 d-none">
                        <div class="small fw-bold text-muted mb-1">Aperturas ya registradas con este mes de corte</div>
                        <ul class="list-group list-group-flush small border rounded-3" data-apd="existentes"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar'] ?? false): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none" data-apd="btn-revertir">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Revertir apertura
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
                    <?php if ($perm['crear'] ?? false): ?>
                        <button type="button" class="btn btn-primary btn-sm px-3" data-apd="btn-aplicar" disabled>
                            <i class="bi bi-check2-circle me-1"></i>Registrar apertura
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/suscripciones_apertura_devengo.js?v=<?= asset_ver('/js/modulos/suscripciones_apertura_devengo.js') ?>"></script>
<script>
    SuscAperturaDevengo.iniciar({
        urlApPreview: <?= json_encode($urlSuscId . '/aperturaPreviewAjax') ?>,
        urlApAplicar: <?= json_encode($urlSuscId . '/aplicarAperturaAjax') ?>,
        urlApRevertir:<?= json_encode($urlSuscId . '/revertirAperturaAjax') ?>,
    });
</script>
