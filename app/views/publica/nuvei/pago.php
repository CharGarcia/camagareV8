<?php
/**
 * Página pública: pago con tarjeta vía Checkout de Nuvei.
 * Variables: $estado (string|null), $reference, $dev_reference, $env_mode,
 *            $descripcion, $monto, $empresa_nombre, $url_resultado
 */
$estado        = $estado ?? null; // null (mostrar checkout) | 'aprobado' | 'rechazado' | 'cancelado' | 'error' | 'pendiente'
$reference     = $reference      ?? '';
$devReference  = $dev_reference  ?? '';
$envMode       = $env_mode       ?? 'stg';
$descripcion   = htmlspecialchars($descripcion ?? '');
$monto         = number_format((float)($monto ?? 0), 2);
$empresaNombre = htmlspecialchars($empresa_nombre ?? '');
$urlResultado  = $url_resultado  ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pago con tarjeta<?= $empresaNombre ? ' · ' . $empresaNombre : '' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html { font-size: 16px !important; }
  body { background: #f0f4f8; min-height: 100dvh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
  .pay-card { max-width: 460px; width: 100%; margin: 2rem auto; }
  .result-icon { font-size: 3.5rem; }
</style>
</head>
<body>
<div class="container pay-card" id="nvContenedor">

  <?php if ($estado): ?>
    <?php
    $cfgs = [
      'aprobado'  => ['icon' => 'bi-check-circle-fill', 'color' => '#198754', 'bg' => '#d1e7dd', 'titulo' => 'Pago aprobado',   'texto' => '¡Tu pago fue procesado correctamente!'],
      'rechazado' => ['icon' => 'bi-exclamation-circle-fill', 'color' => '#dc3545', 'bg' => '#f8d7da', 'titulo' => 'Pago rechazado', 'texto' => 'Tu pago fue rechazado. Intenta con otro método.'],
      'cancelado' => ['icon' => 'bi-x-circle-fill', 'color' => '#6c757d', 'bg' => '#e2e3e5', 'titulo' => 'Pago cancelado', 'texto' => 'Cancelaste el proceso de pago.'],
      'error'     => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#dc3545', 'bg' => '#f8d7da', 'titulo' => 'Error', 'texto' => 'Hubo un problema al procesar el pago.'],
    ];
    $cfg = $cfgs[$estado] ?? $cfgs['error'];
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="py-4 text-center" style="background:<?= $cfg['bg'] ?>;">
        <i class="bi <?= $cfg['icon'] ?> result-icon" style="color:<?= $cfg['color'] ?>;"></i>
        <h5 class="fw-bold mt-2 mb-0" style="color:<?= $cfg['color'] ?>;"><?= $cfg['titulo'] ?></h5>
      </div>
      <div class="card-body text-center p-4">
        <p class="text-muted"><?= $cfg['texto'] ?></p>
        <?php if ($descripcion || $monto): ?>
        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-3 text-start">
          <span class="text-muted small"><?= $descripcion ?: 'Pago' ?></span>
          <span class="fw-bold fs-5">$ <?= $monto ?></span>
        </div>
        <?php endif; ?>
        <?php if ($estado !== 'aprobado'): ?>
        <button onclick="history.back()" class="btn btn-outline-secondary btn-sm px-4">
          <i class="bi bi-arrow-left me-1"></i>Volver e intentar de nuevo
        </button>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden" id="nvCardPago">
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

        <button type="button" id="nvBtnPagar" class="btn btn-primary w-100 py-2">
          <i class="bi bi-lock-fill me-1"></i>Pagar ahora
        </button>
        <div id="nvMensajeError" class="alert alert-danger py-2 small mt-3 d-none"></div>

        <div class="d-flex align-items-center justify-content-center gap-1 mt-3 small text-muted">
          <i class="bi bi-shield-lock text-success"></i>
          Pago seguro procesado por <strong class="ms-1">Nuvei</strong>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php if (!$estado): ?>
<script src="https://cdn.paymentez.com/ccapi/sdk/payment_checkout_3.0.0.min.js"></script>
<script>
(function() {
    var reference    = <?= json_encode($reference) ?>;
    var devReference = <?= json_encode($devReference) ?>;
    var urlResultado = <?= json_encode($urlResultado) ?>;
    var envMode      = <?= json_encode($envMode) ?>;

    var btn = document.getElementById('nvBtnPagar');
    var msg = document.getElementById('nvMensajeError');

    function mostrarError(texto) {
        msg.textContent = texto;
        msg.classList.remove('d-none');
    }

    function confirmarEnServidor(transaction) {
        var fd = new FormData();
        fd.append('dev_reference', devReference);
        fd.append('transaction', JSON.stringify(transaction));

        fetch(urlResultado, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) {
                    mostrarError(data.mensaje || 'No se pudo confirmar el pago.');
                    return;
                }
                if (data.url_exito) {
                    window.location.href = data.url_exito;
                    return;
                }
                // Recargar para que el servidor muestre la pantalla de resultado correspondiente
                window.location.reload();
            })
            .catch(function() {
                mostrarError('No se pudo confirmar el pago con el servidor. Si tu tarjeta fue cobrada, contacta al comercio.');
            });
    }

    function initCheckout() {
        if (typeof PaymentCheckout === 'undefined') { setTimeout(initCheckout, 100); return; }

        var paymentCheckout = new PaymentCheckout.modal({
            env_mode: envMode,
            onOpen: function() {},
            onClose: function() {},
            onResponse: function(response) {
                if (response && response.transaction) {
                    confirmarEnServidor(response.transaction);
                } else {
                    mostrarError((response && response.error && response.error.description) || 'El pago no se completó.');
                }
            }
        });

        btn.addEventListener('click', function() {
            msg.classList.add('d-none');
            paymentCheckout.open({ reference: reference });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCheckout);
    } else {
        initCheckout();
    }
})();
</script>
<?php endif; ?>
</body>
</html>
