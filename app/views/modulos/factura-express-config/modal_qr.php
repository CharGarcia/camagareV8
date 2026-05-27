<style>
@media print {
    html, body { margin: 0; padding: 0; }
    body * { visibility: hidden !important; }
    #fexqrHojaImpresion,
    #fexqrHojaImpresion * { visibility: visible !important; }
    #fexqrHojaImpresion {
        position: fixed !important;
        inset: 0 !important;
        display: flex !important;
        margin: 0 !important;
    }
}

#fexqrHojaImpresion {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 40px 40px;
    width: 100%;
    min-height: 100vh;
    font-family: Arial, sans-serif;
    text-align: center;
    background: #fff;
}
#fexqrHojaImpresion .qr-nombre      { font-size: 1.6rem; font-weight: 700; color: #111; margin-bottom: 12px; }
#fexqrHojaImpresion .qr-descripcion { font-size: 1rem; color: #444; margin-bottom: 36px; max-width: 340px; line-height: 1.5; }
#fexqrHojaImpresion .qr-imagen      { width: 280px; height: 280px; }
#fexqrHojaImpresion .qr-firma       { font-size: 0.75rem; color: #aaa; margin-top: 24px; letter-spacing: 0.02em; }
</style>

<!-- Hoja oculta, visible solo al imprimir -->
<div id="fexqrHojaImpresion">
    <div class="qr-nombre"      id="fexqrPrintNombre"></div>
    <div class="qr-descripcion" id="fexqrPrintDescripcion"></div>
    <img class="qr-imagen" id="fexqrPrintImg" src="" alt="QR">
    <div class="qr-firma">Generado por CaMaGaRe.com.ec</div>
</div>

<div class="modal fade" id="modalFexqrQr" tabindex="-1" style="z-index:1070">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-qr-code text-primary me-2"></i>Código QR</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="fw-bold mb-1" id="fexqrQrNombre"></p>
                <p class="text-muted small mb-3" id="fexqrQrDescripcion"></p>

                <div id="fexqrQrSpinner" class="py-4">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                    <div class="small text-muted mt-2">Generando QR...</div>
                </div>

                <img id="fexqrQrImg" src="" alt="Código QR" class="img-fluid rounded-3 border shadow-sm" style="max-width:280px;"
                     onerror="this.parentElement.querySelector('#fexqrQrSpinner').classList.remove('d-none');this.style.display='none'">

                <div class="mt-3 p-2 bg-light rounded-3 border">
                    <small class="text-muted d-block mb-1">URL del formulario público:</small>
                    <a id="fexqrQrUrlLink" href="#" target="_blank" class="small text-primary text-break fw-medium">
                        <span id="fexqrQrUrl"></span>
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-2 justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopiarUrl" onclick="fexqrCopiarUrl()">
                    <i class="bi bi-clipboard me-1"></i>Copiar URL
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fexqrImprimirQr()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.fexqrImprimirQr = function() {
    window.print();
};
</script>
