<?php
/**
 * Página pública: registro de tarjeta (Add Card) vía Nuvei, para habilitar
 * el cobro automático recurrente (ej. Suscripciones).
 * Variables: $estado (string|null), $token, $env_mode, $app_code, $app_key,
 *            $id_cliente, $email, $url_resultado
 */
$estado       = $estado ?? null; // null (mostrar formulario) | 'completado' | 'cancelado' | 'error'
$token        = $token ?? '';
$envMode      = $env_mode ?? 'stg';
$appCode      = $app_code ?? '';
$appKey       = $app_key  ?? '';
$idCliente    = $id_cliente ?? 0;
$email        = $email ?? '';
$urlResultado = $url_resultado ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de tarjeta</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  html { font-size: 16px !important; }
  body { background: #f0f4f8; min-height: 100dvh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
  .card-tarjeta { max-width: 460px; width: 100%; margin: 2rem auto; }
  .result-icon { font-size: 3.5rem; }
</style>
</head>
<body>
<div class="container card-tarjeta">

  <?php if ($estado): ?>
    <?php
    $cfgs = [
      'completado' => ['icon' => 'bi-check-circle-fill', 'color' => '#198754', 'bg' => '#d1e7dd', 'titulo' => 'Tarjeta registrada', 'texto' => 'Tu tarjeta quedó registrada correctamente para el cobro automático.'],
      'cancelado'  => ['icon' => 'bi-x-circle-fill', 'color' => '#6c757d', 'bg' => '#e2e3e5', 'titulo' => 'Enlace cancelado', 'texto' => 'Este enlace de registro ya no está disponible.'],
      'error'      => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#dc3545', 'bg' => '#f8d7da', 'titulo' => 'Enlace no válido', 'texto' => 'Este enlace no existe o ya fue usado.'],
    ];
    $cfg = $cfgs[$estado] ?? $cfgs['error'];
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="py-4 text-center" style="background:<?= $cfg['bg'] ?>;">
        <i class="bi <?= $cfg['icon'] ?> result-icon" style="color:<?= $cfg['color'] ?>;"></i>
        <h5 class="fw-bold mt-2 mb-0" style="color:<?= $cfg['color'] ?>;"><?= $cfg['titulo'] ?></h5>
      </div>
      <div class="card-body text-center p-4">
        <p class="text-muted mb-0"><?= $cfg['texto'] ?></p>
      </div>
    </div>

  <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-header bg-primary text-white py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-credit-card-2-front fs-5"></i>
          <div class="fw-bold" style="font-size:.95rem;">Registrar tarjeta para cobro automático</div>
        </div>
      </div>
      <div class="card-body p-4">
        <p class="text-muted small mb-3">Registra tu tarjeta de forma segura. No se realizará ningún cobro ahora — solo se guarda para los cobros periódicos futuros.</p>

        <div id="tokenize_container"></div>

        <button type="button" id="nvBtnGuardarTarjeta" class="btn btn-primary w-100 py-2 mt-2">
          <i class="bi bi-lock-fill me-1"></i>Guardar tarjeta
        </button>
        <div id="nvTarjetaError" class="alert alert-danger py-2 small mt-3 d-none"></div>

        <div class="d-flex align-items-center justify-content-center gap-1 mt-3 small text-muted">
          <i class="bi bi-shield-lock text-success"></i>
          Procesado de forma segura por <strong class="ms-1">Nuvei</strong>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php if (!$estado): ?>
<script src="https://cdn.paymentez.com/ccapi/sdk/payment_sdk_stable.min.js"></script>
<script>
(function() {
    const token         = <?= json_encode($token) ?>;
    const urlResultado  = <?= json_encode($urlResultado) ?>;
    const environment   = <?= json_encode($envMode) ?>;
    const applicationCode = <?= json_encode($appCode) ?>;
    const applicationKey  = <?= json_encode($appKey) ?>;
    const idCliente     = <?= json_encode((string) $idCliente) ?>;
    const email         = <?= json_encode($email) ?>;

    const btn = document.getElementById('nvBtnGuardarTarjeta');
    const msg = document.getElementById('nvTarjetaError');

    function mostrarError(texto) {
        msg.textContent = texto;
        msg.classList.remove('d-none');
    }

    function getTokenizeData() {
        return {
            locale: 'es',
            user: { id: idCliente, email: email },
            configuration: { default_country: 'ECU' }
        };
    }

    function responseCallback(response) {
        const card = response && response.card ? response.card : null;
        if (!card || !card.token) {
            mostrarError('No se pudo tokenizar la tarjeta. Verifica los datos e intenta de nuevo.');
            return;
        }

        const fd = new FormData();
        fd.append('token', token);
        fd.append('card', JSON.stringify(card));

        fetch(urlResultado, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) {
                    mostrarError(data.mensaje || 'No se pudo guardar la tarjeta.');
                    return;
                }
                window.location.reload();
            })
            .catch(() => mostrarError('No se pudo conectar con el servidor.'));
    }

    function notCompletedFormCallback(err) {
        mostrarError((err && err.error && err.error.description) || 'Completa los datos de la tarjeta.');
    }

    let pgSdk = null;

    function initTokenize() {
        if (typeof PaymentGateway === 'undefined') { setTimeout(initTokenize, 100); return; }
        pgSdk = new PaymentGateway(environment, applicationCode, applicationKey);
        pgSdk.generate_tokenize(getTokenizeData(), '#tokenize_container', responseCallback, notCompletedFormCallback);
    }

    btn.addEventListener('click', function() {
        msg.classList.add('d-none');
        if (pgSdk) pgSdk.tokenize();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTokenize);
    } else {
        initTokenize();
    }
})();
</script>
<?php endif; ?>
</body>
</html>
