<?php
/**
 * Ventana «QR de registro» del módulo Clientes: QR fijo de la empresa para que los
 * clientes se registren o actualicen sus datos, con el título y mensaje del impreso.
 * JS: public/js/modulos/clientes_qr_registro.js
 */
$baseQr = rtrim(BASE_URL, '/') . '/modulos/clientes';
?>
<div class="modal fade" id="modalClientesQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-qr-code text-primary me-2"></i>QR de registro de clientes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="cliqrCargando" class="text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm me-2"></span>Cargando...
                </div>
                <div id="cliqrError" class="alert alert-warning d-none mb-0"></div>

                <div id="cliqrContenido" class="row g-3 d-none">
                    <div class="col-md-5 text-center">
                        <div class="border rounded-3 p-2 bg-white d-inline-block">
                            <img id="cliqrImg" alt="Código QR de registro" style="width:220px;height:220px;">
                        </div>
                        <div class="mt-2">
                            <span id="cliqrBadge" class="badge"></span>
                        </div>
                        <div class="small text-muted mt-2" id="cliqrResumen"></div>
                    </div>
                    <div class="col-md-7">
                        <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 small py-2">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            Un solo QR para toda la empresa (todos los establecimientos). Quien lo escanea queda
                            registrado como cliente al instante; si su identificación ya existe, se actualizan su
                            nombre, correo, teléfono y dirección.
                        </div>

                        <label class="form-label small fw-bold d-block" for="cliqrUrl">Enlace</label>
                        <div class="input-group input-group-sm mb-3">
                            <input type="text" class="form-control" id="cliqrUrl" readonly>
                            <button type="button" class="btn btn-outline-secondary" id="cliqrCopiar" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
                        </div>

                        <label class="form-label small fw-bold d-block" for="cliqrTitulo">Título del impreso</label>
                        <input type="text" class="form-control form-control-sm mb-2" id="cliqrTitulo" maxlength="150">

                        <label class="form-label small fw-bold d-block" for="cliqrMensaje">Mensaje del impreso</label>
                        <textarea class="form-control form-control-sm mb-1" id="cliqrMensaje" rows="4" maxlength="600"></textarea>
                        <div class="form-text mb-2">El título y el mensaje aparecen en el QR impreso y en el formulario que ve el cliente.</div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="cliqrActivo">
                            <label class="form-check-label small" for="cliqrActivo">QR activo (si lo apaga, el enlace deja de funcionar)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="cliqrImprimir"><i class="bi bi-printer me-1"></i>Imprimir QR</button>
                <button type="button" class="btn btn-primary btn-sm px-3 d-none" id="cliqrGuardar"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>
<script>window.CLIQR_URL = <?= json_encode($baseQr) ?>;</script>
<script src="<?= rtrim(BASE_URL, '/') ?>/js/modulos/clientes_qr_registro.js?v=<?= asset_ver('/js/modulos/clientes_qr_registro.js') ?>"></script>
