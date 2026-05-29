<?php
/**
 * Página pública: pago con tarjeta via Cajita de Pagos Payphone.
 * Variables: $widgetConfig (array), $descripcion (string), $monto (float),
 *            $empresa_nombre (string), $estado (string|null)
 */
$widgetJson   = json_encode($widgetConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$descripcion  = htmlspecialchars($descripcion ?? '');
$monto        = number_format((float)($monto ?? 0), 2);
$empresaNombre= htmlspecialchars($empresa_nombre ?? '');
$estado       = $estado ?? null; // 'aprobado' | 'cancelado' | 'rechazado' | 'error' | null
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pago con tarjeta<?= $empresaNombre ? ' · ' . $empresaNombre : '' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css">
<style>
  html { font-size: 16px !important; }
  body { background: #f0f4f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
  .pay-card { max-width: 480px; width: 100%; margin: 2rem auto; }
</style>
</head>
<body>
<div class="container pay-card">

  <?php if ($estado): ?>
    <?php
    $cfgs = [
      'aprobado'  => ['bg-success', 'bi-check-circle-fill', 'Pago aprobado',   '¡Tu pago fue procesado correctamente!'],
      'cancelado' => ['bg-secondary','bi-x-circle-fill',    'Pago cancelado',  'Cancelaste el proceso de pago.'],
      'rechazado' => ['bg-danger',   'bi-exclamation-circle-fill', 'Pago rechazado', 'Tu pago fue rechazado. Intenta con otro método.'],
      'error'     => ['bg-danger',   'bi-exclamation-triangle-fill','Error',    'Hubo un problema al procesar el pago.'],
    ];
    [$bgClass, $icon, $titulo, $texto] = $cfgs[$estado] ?? $cfgs['error'];
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="p-4 text-white text-center <?= $bgClass ?>">
        <i class="bi <?= $icon ?>" style="font-size:3rem;"></i>
        <h5 class="fw-bold mt-2 mb-0"><?= $titulo ?></h5>
      </div>
      <div class="card-body text-center p-4">
        <p class="text-muted"><?= $texto ?></p>
        <?php if ($estado !== 'aprobado'): ?>
        <button onclick="history.back()" class="btn btn-outline-secondary btn-sm px-4">
          <i class="bi bi-arrow-left me-1"></i>Volver e intentar de nuevo
        </button>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-header bg-primary text-white py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-credit-card-2-front fs-5"></i>
          <div>
            <div class="fw-bold" style="font-size:.95rem;">Pago con tarjeta</div>
            <?php if ($empresaNombre): ?>
              <div style="font-size:.75rem;opacity:.85;"><?= $empresaNombre ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="card-body p-4">

        <?php if ($descripcion || $monto): ?>
        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-3">
          <span class="text-muted small"><?= $descripcion ?: 'Pago pendiente' ?></span>
          <span class="fw-bold fs-5 text-primary">$ <?= $monto ?></span>
        </div>
        <?php endif; ?>

        <div id="pp-pago-publico"></div>

        <div class="d-flex align-items-center justify-content-center gap-1 mt-3 small text-muted">
          <i class="bi bi-shield-lock text-success"></i>
          Pago seguro procesado por <strong class="ms-1">Payphone</strong>
          <span class="ms-1" style="font-size:.7rem;">· PCI DSS 4.0</span>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php if (!$estado): ?>
<script src="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js"></script>
<script>
(function() {
    var config = <?= $widgetJson ?>;
    function init() {
        if (typeof PPaymentButtonBox !== 'undefined') {
            try { new PPaymentButtonBox(config).render('pp-pago-publico'); }
            catch(e) {
                document.getElementById('pp-pago-publico').innerHTML =
                    '<div class="alert alert-danger small">Error al cargar el formulario. Recarga la página.</div>';
            }
        } else {
            setTimeout(init, 100);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<?php endif; ?>
</body>
</html>
