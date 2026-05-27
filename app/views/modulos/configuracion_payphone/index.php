<?php
/** @var string     $titulo  */
/** @var array|null $config  */
/** @var array      $permisos */
/** @var string     $urlBase */

$token    = htmlspecialchars($config['token']    ?? '', ENT_QUOTES);
$storeId  = htmlspecialchars($config['store_id'] ?? '', ENT_QUOTES);
$ambiente = $config['ambiente'] ?? 'production';
$activo   = isset($config['activo']) ? (bool) $config['activo'] : true;
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
                <form id="frmPayphone">
                    <input type="hidden" name="id" value="<?= (int)($config['id'] ?? 0) ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">
                            Token de acceso <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" name="token" id="pp-token"
                                   class="form-control form-control-sm font-monospace"
                                   value="<?= $token ?>"
                                   placeholder="Bearer token proporcionado por Payphone"
                                   autocomplete="off" required>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="toggleToken()" title="Mostrar/ocultar">
                                <i class="bi bi-eye" id="iconoToken"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Encuéntralo en <strong>pay.payphonetodoesunred.com</strong> →
                            Integración → Token de acceso.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Store ID <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" name="store_id" id="pp-store"
                               class="form-control form-control-sm"
                               value="<?= $storeId ?>"
                               placeholder="ID de tu tienda en Payphone (si aplica)"
                               autocomplete="off">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label small fw-bold">Ambiente</label>
                            <select name="ambiente" id="pp-ambiente" class="form-select form-select-sm">
                                <option value="production" <?= $ambiente === 'production' ? 'selected' : '' ?>>Producción</option>
                                <option value="sandbox"    <?= $ambiente === 'sandbox'    ? 'selected' : '' ?>>Sandbox (pruebas)</option>
                            </select>
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo"
                                       id="pp-activo" value="1" <?= $activo ? 'checked' : '' ?>>
                                <label class="form-check-label small fw-bold" for="pp-activo">
                                    Servicio activo
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

    <!-- ── Panel informativo ───────────────────────────────────────────── -->
    <div class="col-lg-5">

        <!-- Estado de conexión -->
        <div class="card border-0 shadow-sm rounded-3 mb-3" id="cardEstado" style="display:none!important;">
            <div class="card-body p-3" id="cuerpoEstado"></div>
        </div>

        <!-- Ayuda -->
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-info me-2"></i>¿Cómo obtener el token?</h6>
            </div>
            <div class="card-body p-3 small text-muted">
                <ol class="ps-3 mb-0">
                    <li class="mb-1">Ingresa a <a href="https://pay.payphonetodoesunred.com" target="_blank" rel="noopener">pay.payphonetodoesunred.com</a></li>
                    <li class="mb-1">Ve a <strong>Mi cuenta → Integración</strong></li>
                    <li class="mb-1">Copia el <strong>Token de acceso</strong></li>
                    <li>Pégalo en el campo de arriba y guarda</li>
                </ol>
            </div>
        </div>

        <!-- URLs de retorno -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-link-45deg text-secondary me-2"></i>URLs de retorno</h6>
            </div>
            <div class="card-body p-3 small">
                <p class="text-muted mb-2">Estas URLs son generadas automáticamente por el sistema al procesar cada pago:</p>

                <label class="fw-bold text-muted mb-1">Retorno exitoso / fallido</label>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="urlRetorno" class="form-control font-monospace"
                           value="<?= rtrim(BASE_URL, '/') ?>/payphone/retorno" readonly>
                    <button class="btn btn-outline-secondary" onclick="copiar('urlRetorno',this)" title="Copiar">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>

                <label class="fw-bold text-muted mb-1">Cancelación</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="urlCancelacion" class="form-control font-monospace"
                           value="<?= rtrim(BASE_URL, '/') ?>/payphone/cancelacion" readonly>
                    <button class="btn btn-outline-secondary" onclick="copiar('urlCancelacion',this)" title="Copiar">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const URL_PP = '<?= $urlBase ?>';

document.getElementById('frmPayphone').addEventListener('submit', e => {
    e.preventDefault();
    const btn  = e.target.querySelector('[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    const fd = new FormData(e.target);
    if (!document.getElementById('pp-activo').checked) fd.set('activo', '0');

    fetch(`${URL_PP}/guardar`, { method: 'POST', body: fd })
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

    fetch(`${URL_PP}/probar-conexion`)
        .then(r => r.json())
        .then(res => {
            spin.classList.add('d-none');
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
        })
        .catch(() => {
            spin.classList.add('d-none');
            Swal.fire('Error', 'No se pudo verificar la conexión.', 'error');
        });
}

function toggleToken() {
    const inp   = document.getElementById('pp-token');
    const icono = document.getElementById('iconoToken');
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
