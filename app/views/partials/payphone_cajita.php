<?php
/**
 * Partial reutilizable: Cajita de Pagos de Payphone (widget embebido).
 *
 * Variables requeridas:
 *   $cajita        array   — resultado de PayphoneService::prepararCajita()
 *                            debe tener: ['ok' => true, 'widget' => [...], 'client_transaction_id' => '...']
 *
 * Variables opcionales:
 *   $cajitaTitulo  string  — encabezado del panel (default: 'Pagar con tarjeta')
 *   $cajitaClase   string  — clase CSS adicional para el contenedor (default: '')
 *
 * Uso desde un módulo:
 *   $cajita = $pp->prepararCajita($idEmpresa, [
 *       'monto'          => PayphoneService::dolaresACentavos(25.00),
 *       'descripcion'    => 'Cita #45',
 *       'modulo'         => 'citas',
 *       'id_referencia'  => 45,
 *       'url_retorno'    => BASE_URL . '/payphone/cajita-retorno',
 *       'url_cancelacion'=> BASE_URL . '/payphone/cancelacion',
 *       'url_exito'      => BASE_URL . '/modulos/citas?pago=ok',
 *       'id_usuario'     => $_SESSION['id_usuario'],
 *   ]);
 *   include VIEW_PATH . '/partials/payphone_cajita.php';
 */

if (empty($cajita) || !($cajita['ok'] ?? false)) {
    $errorMsg = $cajita['mensaje'] ?? 'No se pudo inicializar el pago.';
    ?>
    <div class="alert alert-danger py-2 small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= htmlspecialchars($errorMsg) ?>
    </div>
    <?php
    return;
}

$widget      = $cajita['widget'];
$ctid        = $cajita['client_transaction_id'];
$cajitaTitulo = $cajitaTitulo ?? 'Pagar con tarjeta';
$cajitaClase  = $cajitaClase  ?? '';
$widgetJson   = json_encode($widget, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$containerId  = 'pp-cajita-' . substr(md5($ctid), 0, 8);
?>

<!-- Payphone Cajita de Pagos CDN -->
<link rel="stylesheet" href="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css">
<script type="module" src="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js"></script>

<div class="card border-0 shadow-sm rounded-3 <?= htmlspecialchars($cajitaClase) ?>">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-credit-card-2-front text-primary me-2"></i>
            <?= htmlspecialchars($cajitaTitulo) ?>
        </h6>
    </div>
    <div class="card-body p-3">

        <!-- Resumen del cobro -->
        <div class="d-flex justify-content-between align-items-center small mb-3 p-2 bg-light rounded-2">
            <span class="text-muted">Total a pagar</span>
            <span class="fw-bold fs-6">
                $<?= number_format($widget['amount'] / 100, 2) ?> <?= htmlspecialchars($widget['currency'] ?? 'USD') ?>
            </span>
        </div>

        <?php if (!empty($widget['reference'])): ?>
        <div class="small text-muted mb-3">
            <i class="bi bi-tag me-1"></i><?= htmlspecialchars($widget['reference']) ?>
        </div>
        <?php endif; ?>

        <!-- Contenedor del widget de Payphone -->
        <div id="<?= $containerId ?>"></div>

        <!-- Nota de seguridad -->
        <div class="d-flex align-items-center gap-1 mt-3 small text-muted">
            <i class="bi bi-shield-lock text-success"></i>
            Pago seguro procesado por <strong class="ms-1">Payphone</strong>
            <span class="ms-1 text-muted" style="font-size:.7rem;">PCI DSS 4.0</span>
        </div>

    </div>
</div>

<script type="module">
(function () {
    const config = <?= $widgetJson ?>;

    function initCajita() {
        if (typeof PPaymentButtonBox === 'undefined') {
            setTimeout(initCajita, 100);
            return;
        }
        try {
            new PPaymentButtonBox(config).render('<?= $containerId ?>');
        } catch (e) {
            document.getElementById('<?= $containerId ?>').innerHTML =
                '<div class="alert alert-danger small py-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>No se pudo cargar el formulario de pago. Recarga la página.' +
                '</div>';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCajita);
    } else {
        initCajita();
    }
})();
</script>
