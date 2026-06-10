<?php
/** @var string $titulo */
/** @var array $permisos */
/** @var array|null $config */

$base = BASE_URL;
$urlModulo  = rtrim($base, '/') . '/modulos/configuracion-whatsapp';
$webhookUrl = 'https://erp.camagare.com.ec/whatsapp-webhook';
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

<!-- ================================================================
     SECCIÓN: Avisos de mensajes no leídos (acordeón)
     ================================================================ -->
<div class="accordion mt-4" id="accordionAvisos">
    <div class="accordion-item border-0 shadow-sm rounded-3 overflow-hidden">

        <h2 class="accordion-header" id="headingAvisos">
            <button class="accordion-button collapsed fw-bold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#collapseAvisos"
                    aria-expanded="false" aria-controls="collapseAvisos">
                <i class="bi bi-bell-fill text-warning me-2"></i> Avisos de mensajes no leídos
            </button>
        </h2>

        <div id="collapseAvisos" class="accordion-collapse collapse"
             aria-labelledby="headingAvisos" data-bs-parent="#accordionAvisos">
            <div class="accordion-body p-4">

                <div class="alert alert-light border small mb-4">
                    <div class="mb-1">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        <strong>¿Cómo funciona?</strong> Si hay chats con mensajes sin leer durante más del tiempo configurado,
                        el sistema envía automáticamente un aviso a los números registrados. El aviso se repite cada vez que
                        transcurra ese mismo tiempo mientras los mensajes sigan sin atenderse.
                    </div>
                    <div class="mb-1">
                        <i class="bi bi-gear text-secondary me-1"></i>
                        <strong>Frecuencia:</strong> Depende de la automatización que configures en
                        <a href="<?= rtrim($base, '/') ?>/modulos/automatizaciones" class="text-decoration-none fw-medium">Automatizaciones</a>
                        (módulo <em>WhatsApp</em> → acción <em>Avisar mensajes no leídos</em>). Se recomienda cada 5 minutos.
                    </div>
                    <div class="mb-0">
                        <i class="bi bi-whatsapp text-success me-1"></i>
                        <strong>Plantilla requerida:</strong> Debes tener una plantilla aprobada por Meta para garantizar la entrega del aviso.
                        Créala en <a href="<?= rtrim($base, '/') ?>/modulos/plantillas-whatsapp" class="text-decoration-none fw-medium">Plantillas WhatsApp</a>
                        con dos variables: <code>{{1}}</code> = cantidad de chats pendientes, <code>{{2}}</code> = umbral en minutos.
                    </div>
                </div>

                <!-- Estado del último aviso -->
                <div id="divUltimoAviso" class="alert alert-light border mb-4 d-none" style="font-size:.85rem;">
                    <i class="bi bi-clock-history me-1 text-muted"></i>
                    <span id="txtUltimoAviso"></span>
                </div>

                <form id="formAvisoConfig">
                    <div class="row g-3 align-items-end mb-4">
                        <!-- Plantilla -->
                        <div class="col-md-4">
                            <label class="form-label fw-medium small text-muted">
                                Plantilla de aviso
                                <span tabindex="0" data-bs-toggle="tooltip"
                                      title="Plantilla aprobada por Meta. Debe tener dos variables: {{1}} = cantidad de chats pendientes, {{2}} = umbral en minutos."
                                      class="text-primary ms-1" style="cursor:pointer;">
                                    <i class="bi bi-question-circle-fill"></i>
                                </span>
                            </label>
                            <select class="form-select" id="avisoPlantillaNombre" name="plantilla_nombre">
                                <option value="">— Seleccionar plantilla —</option>
                            </select>
                        </div>

                        <!-- Avisar después de -->
                        <div class="col-md-3">
                            <label class="form-label fw-medium small text-muted">
                                Avisar después de
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="umbral_minutos"
                                       id="avisoUmbral" min="1" max="1440" value="30" placeholder="30">
                                <span class="input-group-text text-muted small">minutos sin atender</span>
                            </div>
                        </div>

                        <!-- Estado + Guardar -->
                        <div class="col-md-3 d-flex align-items-end gap-3">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="avisoActivo" name="activo" value="1" style="width:2.5em;height:1.3em;">
                                <label class="form-check-label fw-semibold" for="avisoActivo" id="lblAvisoActivo">Activado</label>
                            </div>
                            <?php if ($permisos['crear'] || $permisos['actualizar']): ?>
                            <button type="button" class="btn btn-primary btn-sm px-3" onclick="WACFG_guardarAvisoConfig()">
                                <i class="bi bi-save me-1"></i> Guardar
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <hr class="my-4">

                <!-- Números de teléfono -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-telephone-fill text-success me-1"></i> Números que recibirán los avisos</h6>
                    <?php if ($permisos['crear']): ?>
                    <button type="button" class="btn btn-success btn-sm px-3" onclick="WACFG_abrirAgregarNumero()">
                        <i class="bi bi-plus-lg me-1"></i> Agregar número
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Formulario inline agregar número -->
                <div id="divFormNumero" class="card border-0 bg-light rounded-3 p-2 mb-3 d-none">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div style="flex:1;min-width:160px;">
                            <label class="form-label small fw-medium text-muted mb-1">
                                Teléfono <span class="text-danger">*</span>
                                <span class="text-muted fw-normal">(con código de país, sin +)</span>
                            </label>
                            <input type="text" class="form-control form-control-sm" id="inputNuevoTelefono"
                                   placeholder="Ej: 593981234567" maxlength="20">
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label class="form-label small fw-medium text-muted mb-1">Etiqueta (opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="inputNuevoNombre"
                                   placeholder="Ej: Gerente, Soporte..." maxlength="100">
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-success btn-sm" onclick="WACFG_confirmarAgregarNumero()">
                                <i class="bi bi-check-lg me-1"></i> Agregar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="WACFG_cancelarAgregarNumero()">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de números -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Teléfono</th>
                                <th>Etiqueta</th>
                                <th class="text-center">Estado</th>
                                <?php if ($permisos['actualizar'] || $permisos['eliminar']): ?>
                                <th class="text-center pe-3">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyAvisoNumeros">
                            <tr id="trAvisoSinNumeros">
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-telephone-x fs-4 d-block mb-1 opacity-50"></i>
                                    No hay números configurados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    window.WACFG_URL_BASE = '<?= $urlModulo ?>';

    document.addEventListener('DOMContentLoaded', function () {
        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el, { trigger: 'hover focus' });
        });

        // Cargar config de avisos solo la primera vez que se abre el acordeón
        let avisosYaCargados = false;
        document.getElementById('collapseAvisos').addEventListener('show.bs.collapse', function () {
            if (!avisosYaCargados) {
                avisosYaCargados = true;
                WACFG_cargarAvisoConfig();
            }
        });

        // Label dinámico del switch
        document.getElementById('avisoActivo').addEventListener('change', function () {
            document.getElementById('lblAvisoActivo').textContent = this.checked ? 'Activado' : 'Desactivado';
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
