<style>
@media print {
    html, body { margin: 0; padding: 0; }
    body * { visibility: hidden !important; }
    #casisQrHoja, #casisQrHoja * { visibility: visible !important; }
    #casisQrHoja { position: fixed !important; inset: 0 !important; display: flex !important; margin: 0 !important; }
}
#casisQrHoja {
    display: none; flex-direction: column; align-items: center; justify-content: center;
    padding: 80px 40px 40px; width: 100%; min-height: 100dvh;
    font-family: Arial, sans-serif; text-align: center; background: #fff;
}
#casisQrHoja .qr-nombre { font-size: 1.6rem; font-weight: 700; color: #111; margin-bottom: 8px; }
#casisQrHoja .qr-sub { font-size: 1rem; color: #444; margin-bottom: 30px; }
#casisQrHoja .qr-imagen { width: 300px; height: 300px; }
#casisQrHoja .qr-firma { font-size: 0.75rem; color: #aaa; margin-top: 24px; }
</style>

<!-- Hoja oculta para impresión -->
<div id="casisQrHoja">
    <div class="qr-nombre" id="casisPrintNombre"></div>
    <div class="qr-sub">Escanee para registrar su asistencia</div>
    <img class="qr-imagen" id="casisPrintImg" src="" alt="QR">
    <div class="qr-firma">Generado por CaMaGaRe.com.ec</div>
</div>

<div class="modal fade" id="modalCasisQr" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-qr-code text-primary me-2"></i>QR del punto</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="fw-bold mb-3" id="casisQrNombre"></p>

                <div id="casisQrSpinner" class="py-4">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                    <div class="small text-muted mt-2">Generando QR...</div>
                </div>

                <img id="casisQrImg" src="" alt="Código QR" class="img-fluid rounded-3 border shadow-sm d-none" style="max-width:280px;">

                <div class="mt-3 p-2 bg-light rounded-3 border">
                    <small class="text-muted d-block mb-1">Contenido del QR:</small>
                    <span id="casisQrTexto" class="small text-primary text-break fw-medium"></span>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 justify-content-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="casisCopiarQr()"><i class="bi bi-clipboard me-1"></i>Copiar</button>
                <?php if (($perm['actualizar'] ?? false)): ?>
                <button type="button" class="btn btn-outline-warning btn-sm" onclick="casisRegenerarQr()"><i class="bi bi-arrow-repeat me-1"></i>Regenerar</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>
