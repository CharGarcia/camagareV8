<?php
/** @var string $titulo */
/** @var array $permisos */
/** @var array|null $config */

$base = BASE_URL;
$urlModulo  = rtrim($base, '/') . '/modulos/configuracion-whatsapp';
$webhookUrl = 'https://www.camagare.com.ec/whatsapp-webhook';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-cogs text-secondary"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<div class="card w-100 border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form id="formWaConfig">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">
                        Identificador del número de teléfono
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                              title="En Meta for Developers → WhatsApp → Configuración de API, sección 'Número de teléfono'. Corresponde al campo 'Identificador del número de teléfono'."
                              class="text-primary ms-1" style="cursor:pointer;">
                            <i class="bi bi-question-circle-fill"></i>
                        </span>
                    </label>
                    <input type="text" class="form-control" name="phone_number_id"
                           value="<?= htmlspecialchars($config['phone_number_id'] ?? '') ?>"
                           placeholder="Ej: 123456789012345" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">
                        Identificador de la cuenta de WhatsApp Business
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                              title="En Meta for Developers → WhatsApp → Configuración de API. Corresponde al campo 'Identificador de la cuenta de WhatsApp Business'."
                              class="text-primary ms-1" style="cursor:pointer;">
                            <i class="bi bi-question-circle-fill"></i>
                        </span>
                    </label>
                    <input type="text" class="form-control" name="waba_id"
                           value="<?= htmlspecialchars($config['waba_id'] ?? '') ?>"
                           placeholder="Ej: 987654321098765" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">
                        App ID
                        <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                              title="ID de tu aplicación de Meta. Lo encuentras en Meta for Developers → tu App → Configuración → Básica → 'ID de la aplicación'."
                              class="text-primary ms-1" style="cursor:pointer;">
                            <i class="bi bi-question-circle-fill"></i>
                        </span>
                    </label>
                    <input type="text" class="form-control" name="app_id"
                           value="<?= htmlspecialchars($config['app_id'] ?? '') ?>"
                           placeholder="Ej: 1122334455667788" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium small text-muted">
                    Identificador de acceso
                    <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                          title="En Meta for Developers → WhatsApp → Configuración de API. Corresponde al campo 'Identificador de acceso'. NO uses el token temporal de 24 horas, genera uno permanente desde el System User en Meta Business Suite."
                          class="text-primary ms-1" style="cursor:pointer;">
                        <i class="bi bi-question-circle-fill"></i>
                    </span>
                </label>
                <textarea class="form-control" name="access_token" rows="3"
                          placeholder="EAAxxxxxxxxxxxxxxxx..." required><?= htmlspecialchars($config['access_token'] ?? '') ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium small text-muted">
                    Identificador de verificación
                    <span tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                          title="Palabra o frase secreta que tú defines. En Meta for Developers → WhatsApp → Configuración de API, al configurar el webhook, corresponde al campo 'Identificador de verificación'. Debe coincidir exactamente."
                          class="text-primary ms-1" style="cursor:pointer;">
                        <i class="bi bi-question-circle-fill"></i>
                    </span>
                </label>
                <input type="text" class="form-control" name="webhook_verify_token"
                       value="<?= htmlspecialchars($config['webhook_verify_token'] ?? '') ?>"
                       placeholder="Ej: mitoken2024seguro" required>
                <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    URL de devolución de llamada:
                    <code id="webhookUrl" class="text-break"><?= htmlspecialchars($webhookUrl) ?></code>
                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="WACFG_copiarUrl()" title="Copiar URL">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
                    <button type="button" class="btn btn-primary" onclick="WACFG_guardarConfig()">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary" onclick="WACFG_probarConexion()">
                    <i class="fas fa-plug"></i> Probar Conexión
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.WACFG_URL_BASE = '<?= $urlModulo ?>';

    // Inicializar tooltips de Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function (el) {
            new bootstrap.Tooltip(el, { trigger: 'hover focus' });
        });
    });

    // Copiar URL del webhook al portapapeles
    window.WACFG_copiarUrl = function () {
        var url = document.getElementById('webhookUrl').textContent.trim();
        navigator.clipboard.writeText(url).then(function () {
            if (window.Toast) {
                Toast.fire({ icon: 'success', title: 'URL copiada al portapapeles' });
            }
        });
    };
</script>
<script src="<?= $base ?>/js/modulos/configuracion_whatsapp.js?v=<?= time() ?>"></script>
