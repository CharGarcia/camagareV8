<?php
/** @var string $titulo */
/** @var array $permisos */
/** @var array|null $config */

$base = BASE_URL;
$urlModulo = rtrim($base, '/') . '/modulos/configuracion-whatsapp';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-cogs text-secondary"></i> <?= htmlspecialchars($titulo) ?></h5>
</div>

<div class="card cmg-table-card w-100 border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form id="formWaConfig">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">Phone ID</label>
                    <input type="text" class="form-control" name="phone_number_id" value="<?= htmlspecialchars($config['phone_number_id'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">WABA ID</label>
                    <input type="text" class="form-control" name="waba_id" value="<?= htmlspecialchars($config['waba_id'] ?? '') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium small text-muted">App ID</label>
                    <input type="text" class="form-control" name="app_id" value="<?= htmlspecialchars($config['app_id'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-medium small text-muted">Token de Acceso (Access Token)</label>
                <textarea class="form-control" name="access_token" rows="3" required><?= htmlspecialchars($config['access_token'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-medium small text-muted">Token de Verificación para Webhook (Opcional)</label>
                <input type="text" class="form-control" name="webhook_verify_token" value="<?= htmlspecialchars($config['webhook_verify_token'] ?? '') ?>">
                <div class="form-text">Si deseas recibir mensajes entrantes, define un token de verificación aquí y en Meta.</div>
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
</script>
<script src="<?= $base ?>/js/modulos/configuracion_whatsapp.js?v=<?= time() ?>"></script>
