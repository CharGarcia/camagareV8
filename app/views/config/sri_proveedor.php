<?php
/**
 * Config global (nivel 3): RUC del proveedor del sistema de facturación.
 * Res. NAC-DGERCGC26-00000027 (Art. 5) + Ficha Técnica v2.34, Anexo 26.
 */
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>
<div class="container-fluid px-3 py-3" style="max-width: 720px;">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/config" class="btn btn-sm btn-outline-secondary me-2" title="Volver a configuración">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h5 class="mb-0"><i class="bi bi-patch-check text-danger me-2"></i>RUC del Proveedor del Sistema (SRI)</h5>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg[0]) ?> py-2 small"><?= htmlspecialchars($msg[1]) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <p class="small text-muted mb-3">
                La <strong>Resolución NAC-DGERCGC26-00000027</strong> del SRI (Ficha Técnica v2.34, Anexo 26) exige que
                todo comprobante electrónico incluya en su <em>información adicional</em> el campo
                <code><?= htmlspecialchars($nombreCampo) ?></code> con el número de RUC del proveedor del sistema de
                facturación. El sistema lo agrega <strong>automáticamente</strong> en el XML y el RIDE de facturas,
                notas de crédito, liquidaciones de compra, guías de remisión y retenciones. Los usuarios no pueden
                modificarlo ni eliminarlo desde los documentos.
            </p>

            <form method="POST" action="<?= BASE_URL ?>/config/sri-proveedor" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-bold mb-1" for="ruc_proveedor">RUC del proveedor (13 dígitos)</label>
                    <input type="text" class="form-control" id="ruc_proveedor" name="ruc_proveedor"
                           value="<?= htmlspecialchars($rucActual) ?>" maxlength="13" inputmode="numeric"
                           pattern="\d{13}" placeholder="1792708389001" autocomplete="off"
                           oninput="this.value = this.value.replace(/\D/g, '')">
                    <div class="form-text" style="font-size:0.7rem;">Dejar vacío desactiva el campo (los comprobantes saldrán sin él — no recomendado una vez vigente la norma).</div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-danger w-100"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body py-2">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1"></i>Así viajará en el XML de cada comprobante:
            </div>
            <pre class="bg-light rounded-2 p-2 mt-2 mb-0 small"><code>&lt;infoAdicional&gt;
    &lt;campoAdicional nombre="<?= htmlspecialchars($nombreCampo) ?>"&gt;<?= htmlspecialchars($rucActual ?: '…') ?>&lt;/campoAdicional&gt;
&lt;/infoAdicional&gt;</code></pre>
        </div>
    </div>
</div>
