<?php
/** @var string     $titulo  */
/** @var array|null $config  */
/** @var array      $permisos */
/** @var string     $urlBase */

$ambiente          = $config['ambiente'] ?? 'sandbox';
$appCodeServer     = htmlspecialchars($config['app_code_server']    ?? '', ENT_QUOTES);
$appKeyServer      = htmlspecialchars($config['app_key_server']     ?? '', ENT_QUOTES);
$appCodeLinkToPay  = htmlspecialchars($config['app_code_linktopay'] ?? '', ENT_QUOTES);
$appKeyLinkToPay   = htmlspecialchars($config['app_key_linktopay']  ?? '', ENT_QUOTES);
$activoCheckout    = isset($config['activo_checkout'])  ? (bool) $config['activo_checkout']  : true;
$activoAddCard     = isset($config['activo_addcard'])   ? (bool) $config['activo_addcard']   : true;
$activoLinkToPay   = isset($config['activo_linktopay']) ? (bool) $config['activo_linktopay'] : false;
$tieneConfig       = !empty($config);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-credit-card-2-front text-primary me-2"></i><?= htmlspecialchars($titulo) ?>
    </h5>
    <?php if ($tieneConfig): ?>
    <span class="badge <?= ($activoCheckout || $activoAddCard || $activoLinkToPay) ? 'bg-success' : 'bg-secondary' ?> px-3 py-2">
        <i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>
        <?= ($activoCheckout || $activoAddCard || $activoLinkToPay) ? 'Activo' : 'Inactivo' ?>
    </span>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- ── Formulario de credenciales ─────────────────────────────────── -->
    <div class="col-lg-7">
        <form id="frmNuvei">
        <input type="hidden" name="id" value="<?= (int)($config['id'] ?? 0) ?>">

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-globe text-secondary me-2"></i>Ambiente</h6>
            </div>
            <div class="card-body p-4">
                <select name="ambiente" id="nv-ambiente" class="form-select form-select-sm" style="max-width:260px;">
                    <option value="sandbox"    <?= $ambiente === 'sandbox'    ? 'selected' : '' ?>>Sandbox (pruebas)</option>
                    <option value="production" <?= $ambiente === 'production' ? 'selected' : '' ?>>Producción</option>
                </select>
                <div class="form-text">Aplica a todas las credenciales de abajo.</div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-key text-warning me-2"></i>Checkout + Add Card</h6>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="activo_checkout"
                           id="nv-activo-checkout" value="1" <?= $activoCheckout ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-bold" for="nv-activo-checkout">Activo</label>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Application Code <span class="text-danger">*</span></label>
                    <input type="text" name="app_code_server" id="nv-code-server"
                           class="form-control form-control-sm font-monospace"
                           value="<?= $appCodeServer ?>"
                           placeholder="Ej. NUVEISTG-EC-SERVER" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Application Key <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="app_key_server" id="nv-key-server"
                               class="form-control form-control-sm font-monospace"
                               value="<?= $appKeyServer ?>"
                               placeholder="<?= $appKeyServer !== '' ? '(sin cambios — dejar en blanco para conservar)' : 'Application Key' ?>"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggle('nv-key-server','ic-key-server')" title="Mostrar/ocultar">
                            <i class="bi bi-eye" id="ic-key-server"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="activo_addcard"
                           id="nv-activo-addcard" value="1" <?= $activoAddCard ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-bold" for="nv-activo-addcard">Habilitar Add Card / recurrencia con estas credenciales</label>
                </div>
                <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="probarConexion()">
                    <i class="bi bi-plug me-1"></i>Probar conexión
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="spinPrueba"></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-link-45deg text-secondary me-2"></i>Link to Pay</h6>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="activo_linktopay"
                           id="nv-activo-linktopay" value="1" <?= $activoLinkToPay ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-bold" for="nv-activo-linktopay">Activo</label>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Application Code</label>
                    <input type="text" name="app_code_linktopay" id="nv-code-linktopay"
                           class="form-control form-control-sm font-monospace"
                           value="<?= $appCodeLinkToPay ?>"
                           placeholder="Ej. LINKTOPAY01-EC-SERVER" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Application Key</label>
                    <div class="input-group">
                        <input type="password" name="app_key_linktopay" id="nv-key-linktopay"
                               class="form-control form-control-sm font-monospace"
                               value="<?= $appKeyLinkToPay ?>"
                               placeholder="<?= $appKeyLinkToPay !== '' ? '(sin cambios — dejar en blanco para conservar)' : 'Application Key' ?>"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggle('nv-key-linktopay','ic-key-linktopay')" title="Mostrar/ocultar">
                            <i class="bi bi-eye" id="ic-key-linktopay"></i>
                        </button>
                    </div>
                    <div class="form-text">Producto independiente de Nuvei, con credenciales propias.</div>
                </div>
                <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="probarConexionLinktopay()">
                    <i class="bi bi-plug me-1"></i>Probar conexión
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="spinPruebaLtp"></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
        <button type="submit" class="btn btn-primary btn-sm px-4">
            <i class="bi bi-save me-1"></i>Guardar configuración
        </button>
        <?php endif; ?>
        </form>
    </div>

    <!-- ── Panel informativo ───────────────────────────────────────────── -->
    <div class="col-lg-5">

        <!-- Estado de conexión -->
        <div class="card border-0 shadow-sm rounded-3 mb-3" id="cardEstado" style="display:none!important;">
            <div class="card-body p-3" id="cuerpoEstado"></div>
        </div>

        <!-- Ayuda -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-info me-2"></i>¿De dónde salen las credenciales?</h6>
            </div>
            <div class="card-body p-3 small text-muted">
                <p class="mb-2">Nuvei entrega <strong>dos pares</strong> de credenciales por comercio:</p>
                <ul class="ps-3 mb-2">
                    <li><strong>Checkout + Add Card</strong>: una sola aplicación tipo <em>Server</em> sirve para ambos métodos.</li>
                    <li><strong>Link to Pay</strong>: producto aparte, con su propia aplicación y credenciales.</li>
                </ul>
                <p class="mb-0">Solicítalas a tu ejecutivo de Nuvei o en <code>integrations@paymentez.com</code>, indicando la URL del webhook de abajo.</p>
            </div>
        </div>

        <!-- URL del webhook -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-broadcast text-secondary me-2"></i>URL de notificación (webhook)</h6>
            </div>
            <div class="card-body p-3 small">
                <p class="text-muted mb-2">
                    Entrégale esta URL al equipo de Nuvei para que envíen aquí la notificación de cada
                    transacción (aprobada o rechazada). Es la <strong>misma URL para todas las empresas</strong>
                    del sistema.
                </p>
                <div class="input-group input-group-sm">
                    <input type="text" id="urlWebhook" class="form-control font-monospace"
                           value="<?= rtrim(BASE_URL, '/') ?>/nuvei/webhook" readonly>
                    <button class="btn btn-outline-secondary" onclick="copiar('urlWebhook',this)" title="Copiar">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="alert alert-warning p-2 mt-2 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Aún no está confirmado con Nuvei el formato exacto ni la firma del webhook; por ahora el
                    sistema solo registra el payload recibido para depuración.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const URL_NV = '<?= $urlBase ?>';

document.getElementById('frmNuvei').addEventListener('submit', e => {
    e.preventDefault();
    const btn  = e.target.querySelector('[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    const fd = new FormData(e.target);
    ['activo_checkout', 'activo_addcard', 'activo_linktopay'].forEach(name => {
        if (!document.getElementById('nv-' + name.replace('activo_', 'activo-')).checked) fd.set(name, '0');
    });

    fetch(`${URL_NV}/guardar`, { method: 'POST', body: fd })
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

function mostrarEstado(res) {
    const card   = document.getElementById('cardEstado');
    const cuerpo = document.getElementById('cuerpoEstado');
    card.style.removeProperty('display');
    if (res.ok) {
        card.className  = 'card border-0 shadow-sm rounded-3 mb-3 border-success';
        cuerpo.innerHTML = `<div class="d-flex align-items-center gap-2 text-success">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span class="fw-semibold small">${res.mensaje}</span>
        </div>`;
    } else {
        card.className  = 'card border-0 shadow-sm rounded-3 mb-3 border-danger';
        cuerpo.innerHTML = `<div class="d-flex align-items-center gap-2 text-danger">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <span class="fw-semibold small">${res.mensaje}</span>
        </div>`;
    }
}

function probarConexion() {
    const spin = document.getElementById('spinPrueba');
    spin.classList.remove('d-none');
    fetch(`${URL_NV}/probar-conexion`)
        .then(r => r.json())
        .then(res => { spin.classList.add('d-none'); mostrarEstado(res); })
        .catch(() => { spin.classList.add('d-none'); Swal.fire('Error', 'No se pudo verificar la conexión.', 'error'); });
}

function probarConexionLinktopay() {
    const spin = document.getElementById('spinPruebaLtp');
    spin.classList.remove('d-none');
    fetch(`${URL_NV}/probar-conexion-linktopay`)
        .then(r => r.json())
        .then(res => { spin.classList.add('d-none'); mostrarEstado(res); })
        .catch(() => { spin.classList.add('d-none'); Swal.fire('Error', 'No se pudo verificar la conexión.', 'error'); });
}

function toggle(inputId, iconId) {
    const inp   = document.getElementById(inputId);
    const icono = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icono.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icono.className = 'bi bi-eye';
    }
}

function copiar(inputId, btn) {
    const val = document.getElementById(inputId).value;
    navigator.clipboard.writeText(val).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check text-success"></i>';
        setTimeout(() => btn.innerHTML = orig, 1500);
    });
}
</script>
