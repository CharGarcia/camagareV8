<?php
/** @var string     $titulo   */
/** @var array|null $config   */
/** @var array      $permisos */
/** @var string     $urlBase  */

$publicKey  = htmlspecialchars($config['public_key']  ?? '', ENT_QUOTES);
$privateKey = htmlspecialchars($config['private_key'] ?? '', ENT_QUOTES);
$ambiente   = $config['ambiente'] ?? 'uat';
$moneda     = $config['moneda']   ?? 'USD';
$activo     = isset($config['activo']) ? (bool) $config['activo'] : true;
$tieneConfig = !empty($config);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-credit-card-2-front text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($tieneConfig): ?>
    <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
        <i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>
        <?= $activo ? 'Activo' : 'Inactivo' ?>
        &nbsp;·&nbsp;<?= strtoupper(htmlspecialchars($ambiente)) ?>
    </span>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- ── Formulario de credenciales ─────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-key text-warning me-2"></i>Credenciales de la API</h6>
            </div>
            <div class="card-body p-4">
                <form id="frmKushki">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">
                            Clave pública (Public Key) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="public_key" id="kushki-public"
                                   class="form-control form-control-sm font-monospace"
                                   value="<?= $publicKey ?>"
                                   placeholder="Clave pública de Kushki (usada en Kushki.js)"
                                   autocomplete="off" required>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="toggleCampo('kushki-public','icon-pub')" title="Mostrar/ocultar">
                                <i class="bi bi-eye" id="icon-pub"></i>
                            </button>
                        </div>
                        <div class="form-text">Se usa en el <strong>frontend</strong> para tokenizar la tarjeta del cliente de forma segura.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">
                            Clave privada (Private Key) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="private_key" id="kushki-private"
                                   class="form-control form-control-sm font-monospace"
                                   value="<?= $privateKey ?>"
                                   placeholder="Clave privada de Kushki (usada en el servidor)"
                                   autocomplete="off" required>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="toggleCampo('kushki-private','icon-priv')" title="Mostrar/ocultar">
                                <i class="bi bi-eye" id="icon-priv"></i>
                            </button>
                        </div>
                        <div class="form-text">Se usa en el <strong>servidor</strong> para crear tokens de suscripción y ejecutar cobros. <strong>Nunca la expongas en el frontend.</strong></div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Ambiente</label>
                            <select name="ambiente" id="kushki-ambiente" class="form-select form-select-sm">
                                <option value="uat"        <?= $ambiente === 'uat'        ? 'selected' : '' ?>>UAT (pruebas)</option>
                                <option value="production" <?= $ambiente === 'production' ? 'selected' : '' ?>>Producción</option>
                            </select>
                            <div class="form-text">Use <strong>UAT</strong> para pruebas con tarjetas de test. Cambie a <strong>Producción</strong> cuando esté listo.</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-bold">Moneda</label>
                            <select name="moneda" class="form-select form-select-sm">
                                <option value="USD" <?= $moneda === 'USD' ? 'selected' : '' ?>>USD</option>
                            </select>
                        </div>
                        <div class="col-sm-3 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo"
                                       id="kushki-activo" value="1" <?= $activo ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="kushki-activo">
                                    Activo
                                </label>
                            </div>
                        </div>
                    </div>

                    <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            <i class="bi bi-save me-1"></i>Guardar configuración
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="probarConexion()">
                            <i class="bi bi-plug me-1"></i>Probar conexión
                            <span class="spinner-border spinner-border-sm ms-1 d-none" id="spinPrueba"></span>
                        </button>
                    </div>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>

    <!-- ── Panel derecho ──────────────────────────────────────────────── -->
    <div class="col-lg-5">

        <!-- Estado de conexión (se muestra tras probar) -->
        <div class="card border-0 shadow-sm rounded-3 mb-3 d-none" id="cardEstado">
            <div class="card-body p-3" id="cuerpoEstado"></div>
        </div>

        <!-- ¿Cómo obtener las claves? -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-info me-2"></i>¿Cómo obtener las claves?</h6>
            </div>
            <div class="card-body p-3 small text-muted">
                <p class="fw-semibold text-dark mb-1">Pasos:</p>
                <ol class="ps-3 mb-2">
                    <li class="mb-1">Ingresa al <a href="https://dashboard.kushkipagos.com" target="_blank" rel="noopener">Dashboard de Kushki</a></li>
                    <li class="mb-1">Ve a <strong>Configuración → Credenciales</strong></li>
                    <li class="mb-1">Selecciona el ambiente: <strong>UAT</strong> (pruebas) o <strong>Producción</strong></li>
                    <li class="mb-1">Copia la <strong>Clave pública</strong> y la <strong>Clave privada</strong></li>
                    <li>Pégalas en los campos de la izquierda y guarda</li>
                </ol>
                <div class="alert alert-warning p-2 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Las claves de <strong>UAT</strong> y <strong>Producción</strong> son diferentes. Asegúrate de usar las correctas según el ambiente.
                </div>
            </div>
        </div>

        <!-- Tarjetas de prueba (UAT) -->
        <div class="card border-0 shadow-sm rounded-3" id="cardTarjetasPrueba">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-credit-card text-secondary me-2"></i>Tarjetas de prueba (UAT)</h6>
            </div>
            <div class="card-body p-3 small">
                <p class="text-muted mb-2">Usa estas tarjetas en ambiente UAT para probar el formulario de registro:</p>
                <table class="table table-sm mb-0" style="font-size:.8rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Marca</th>
                            <th>Número</th>
                            <th>CVV</th>
                            <th>Venc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="bi bi-credit-card text-primary me-1"></i>Visa</td>
                            <td><code>4242424242424242</code></td>
                            <td>123</td>
                            <td>12/28</td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-credit-card text-danger me-1"></i>Mastercard</td>
                            <td><code>5555555555554444</code></td>
                            <td>123</td>
                            <td>12/28</td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-credit-card text-warning me-1"></i>Amex</td>
                            <td><code>378282246310005</code></td>
                            <td>1234</td>
                            <td>12/28</td>
                        </tr>
                    </tbody>
                </table>
                <div class="text-muted mt-2" style="font-size:.75rem;">
                    <i class="bi bi-info-circle me-1"></i>Nombre del titular: cualquier texto. En UAT no se realiza ningún cobro real.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const URL_KUSHKI = '<?= $urlBase ?>';

document.getElementById('frmKushki').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn  = e.target.querySelector('[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    const fd = new FormData(e.target);
    if (!document.getElementById('kushki-activo').checked) fd.set('activo', '0');

    fetch(`${URL_KUSHKI}/guardar`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (res.ok) {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: res.mensaje, timer: 1800, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.mensaje, 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = orig;
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        });
});

function probarConexion() {
    const spin = document.getElementById('spinPrueba');
    spin.classList.remove('d-none');

    fetch(`${URL_KUSHKI}/probarConexion`)
        .then(r => r.json())
        .then(res => {
            spin.classList.add('d-none');
            const card   = document.getElementById('cardEstado');
            const cuerpo = document.getElementById('cuerpoEstado');
            card.classList.remove('d-none');
            if (res.ok) {
                card.className = 'card border-0 shadow-sm rounded-3 mb-3 border-success';
                cuerpo.innerHTML = `<div class="d-flex align-items-center gap-2 text-success">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <span class="fw-semibold small">${res.mensaje}</span>
                </div>`;
            } else {
                card.className = 'card border-0 shadow-sm rounded-3 mb-3 border-danger';
                cuerpo.innerHTML = `<div class="d-flex align-items-center gap-2 text-danger">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                    <span class="fw-semibold small">${res.mensaje}</span>
                </div>`;
            }
        })
        .catch(() => {
            spin.classList.add('d-none');
            Swal.fire('Error', 'No se pudo verificar la conexión.', 'error');
        });
}

function toggleCampo(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
