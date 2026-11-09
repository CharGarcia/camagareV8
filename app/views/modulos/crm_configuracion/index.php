<?php
/**
 * Configuración del CRM (modulos/crm_configuracion): canales (Instagram),
 * embudos y etapas, orígenes y motivos de pérdida. Todo por AJAX
 * (CrmConfiguracionController); lógica en public/js/modulos/crm_configuracion.js.
 * Diseño: docs/diseno/crm-redes-fase1.md §9.3.
 *
 * @var array       $perm
 * @var string      $rutaModulo
 * @var bool        $disponible
 * @var array|null  $appMeta   app de Meta de la empresa (sin App Secret)
 * @var array       $urlsMeta  URLs que se registran en esa app
 * @var array|null  $flash
 */
$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$claseFlash = ['success' => 'alert-success', 'warning' => 'alert-warning', 'error' => 'alert-danger'];
?>
<style>
    #modulo-crm-config .crmc-etapa { display:flex; gap:6px; align-items:center; padding:6px 8px; border:1px solid #e3e6ea; border-radius:.5rem; background:#fff; }
    #modulo-crm-config .crmc-etapa .crmc-asa { cursor:grab; color:#adb5bd; }
    #modulo-crm-config .crmc-etapa input[type=color] { width:32px; height:28px; padding:2px; }
    #modulo-crm-config .crmc-embudo.active { background:#e7f1ff; border-color:#b6d4fe; }
    #modulo-crm-config .form-control-sm, #modulo-crm-config .form-select-sm { font-size:.8rem; }
</style>

<div class="container-fluid pt-2 pb-3 px-0 px-md-3" id="modulo-crm-config">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0 fw-bold"><i class="bi bi-gear me-2 text-primary"></i>Configuración del CRM</h5>
        <a href="<?= $base ?>/modulos/crm" class="btn btn-outline-secondary btn-sm"><i class="bi bi-kanban me-1"></i>Ir al CRM</a>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning"><i class="bi bi-database-exclamation me-1"></i>
        El CRM todavía no está instalado en la base de datos. Falta aplicar <code>database/2026-11-08_crm_fase1.sql</code>.</div>
<?php else: ?>

    <?php if ($flash): ?>
        <div class="alert <?= $claseFlash[$flash['tipo']] ?? 'alert-info' ?> alert-dismissible fade show">
            <?= $h($flash['texto']) ?>
            <?php foreach (($flash['avisos'] ?? []) as $av): ?>
                <div class="small mt-1"><i class="bi bi-exclamation-triangle me-1"></i><?= $h($av) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#crmc-canales" type="button"><i class="bi bi-instagram me-1"></i>Canales</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#crmc-asistente" type="button" id="crmc-tab-asistente"><i class="bi bi-robot me-1"></i>Asistente virtual</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#crmc-embudos" type="button"><i class="bi bi-funnel me-1"></i>Embudos y etapas</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#crmc-origenes" type="button"><i class="bi bi-signpost-split me-1"></i>Orígenes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#crmc-motivos" type="button"><i class="bi bi-x-octagon me-1"></i>Motivos de pérdida</button></li>
    </ul>

    <div class="tab-content">
        <!-- Canales -->
        <div class="tab-pane fade show active" id="crmc-canales">
            <?php
            $app = ($appMeta ?? []) + ['configurada' => false, 'app_id' => '', 'secret_guardado' => false, 'verify_token' => '', 'config_id' => '', 'unificado' => false];
            $editable = $app['configurada'] ? !empty($perm['actualizar']) : !empty($perm['crear']);
            // Dónde va cada dirección en Meta, según la forma de inicio de sesión de la app.
            $etiquetasUrl = $app['unificado'] ? [
                'oauth'        => ['URL de redirección', 'API setup with Unified login → la configuración → URL de redirección'],
                'webhook'      => ['URL de devolución de llamada (webhook)', 'API setup with Unified login → Configurar webhooks. Token de verificación: el de abajo'],
                'desautorizar' => ['URL para cancelar la autorización', 'Si Meta la pide (Configuración de la app o Inicio de sesión con Facebook para empresas)'],
                'eliminar'     => ['URL de solicitud de eliminación de datos', 'Configuración de la app → Básica → Eliminación de datos'],
            ] : [
                'oauth'        => ['URI de redirección de OAuth válida', 'Inicio de sesión empresarial → URI de redirección de OAuth válidas'],
                'webhook'      => ['URL de devolución de llamada (webhook)', 'Webhooks → URL de devolución de llamada. Token de verificación: el de abajo'],
                'desautorizar' => ['URL para cancelar la autorización', 'Inicio de sesión empresarial → Cancelar autorización'],
                'eliminar'     => ['URL de solicitud de eliminación de datos', 'Inicio de sesión empresarial → Solicitud de eliminación de datos'],
            ];
            ?>
            <!-- App de Meta de ESTA empresa (crm_meta_apps). El App Secret nunca vuelve al navegador. -->
            <div class="card border-0 shadow-sm mb-3" id="crmc-app-meta">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small"><i class="bi bi-meta me-1 text-primary"></i>App de Meta de la empresa</span>
                    <span class="badge <?= $app['configurada'] ? 'bg-success' : 'bg-secondary' ?>" id="crmc-app-estado"><?= $app['configurada'] ? 'Registrada' : 'Sin registrar' ?></span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Cree la app en <strong>developers.facebook.com</strong> (caso de uso «Administrar mensajes y contenido en Instagram»)
                        y copie aquí su <strong>App ID</strong> y <strong>App Secret</strong> (Configuración de la app → Básica) y el
                        <strong>ID de configuración</strong> de inicio de sesión. Luego registre en la app las direcciones de abajo.
                        Guía completa: manual <em>App de Meta para Instagram</em>.</p>
                    <div class="row g-2 align-items-end mb-1" style="max-width:980px;">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">App ID</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="crmc-app-id" inputmode="numeric" maxlength="30"
                                   value="<?= $h($app['app_id']) ?>" <?= $editable ? '' : 'disabled' ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">App Secret</label>
                            <input type="password" class="form-control form-control-sm font-monospace" id="crmc-app-secret" maxlength="64" autocomplete="new-password"
                                   placeholder="<?= $app['secret_guardado'] ? 'Guardado — escriba solo para cambiarlo' : 'Pegue el App Secret' ?>" <?= $editable ? '' : 'disabled' ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">ID de configuración</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="crmc-config-id" inputmode="numeric" maxlength="30"
                                   value="<?= $h($app['config_id']) ?>" placeholder="Apps nuevas (Unified login)" <?= $editable ? '' : 'disabled' ?>>
                        </div>
                        <div class="col-md-3 d-flex gap-1">
                            <?php if ($editable): ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="CRMC.guardarApp()"><i class="bi bi-check2 me-1"></i>Guardar</button>
                            <?php endif; ?>
                            <?php if (!empty($perm['eliminar']) && $app['configurada']): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="CRMC.quitarApp()" title="Quitar la app de Meta"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="small text-muted mb-2" style="max-width:980px;">
                        <?php if ($app['configurada']): ?>
                            <span class="badge bg-light text-dark border me-1"><?= $app['unificado'] ? 'Unified login' : 'Inicio de sesión de Instagram' ?></span>
                        <?php endif; ?>
                        El <strong>ID de configuración</strong> está en la URL de inicio de sesión que da Meta (después de <code>config_id=</code>).
                        Déjelo vacío solo si su app es anterior a noviembre de 2026 y usa «API setup with Instagram login» (ahí van el Instagram App ID y Secret).
                    </div>

                    <div class="<?= $app['configurada'] ? '' : 'd-none' ?>" id="crmc-app-datos">
                        <div class="fw-bold small mt-3 mb-1">Datos para registrar en la app de Meta</div>
                        <div class="d-flex flex-column gap-1" style="max-width:820px;">
                            <?php foreach ($urlsMeta as $clave => $url): ?>
                                <div>
                                    <div class="small text-muted"><?= $h($etiquetasUrl[$clave][0]) ?> <span class="text-body-tertiary">· <?= $h($etiquetasUrl[$clave][1]) ?></span></div>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control font-monospace" readonly value="<?= $h($url) ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="CRMC.copiar(this)" title="Copiar"><i class="bi bi-clipboard"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div>
                                <div class="small text-muted">Token de verificación del webhook</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace" readonly id="crmc-verify-token" value="<?= $h($app['verify_token']) ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="CRMC.copiar(this)" title="Copiar"><i class="bi bi-clipboard"></i></button>
                                    <?php if (!empty($perm['actualizar'])): ?>
                                        <button class="btn btn-outline-secondary" type="button" onclick="CRMC.regenerarToken()" title="Generar otro token"><i class="bi bi-arrow-repeat"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($app['actualizado'])): ?>
                            <div class="small text-muted mt-2">Última modificación: <?= $h(date('d-m-Y H:i:s', strtotime((string) $app['actualizado']))) ?><?= $app['actualizado_por'] ? ' · ' . $h($app['actualizado_por']) : '' ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small text-muted">Cuentas de Instagram de esta empresa. Sus mensajes y comentarios llegan a la Bandeja social.</div>
                <?php if (!empty($perm['crear'])): ?>
                    <button type="button" class="btn btn-primary btn-sm <?= $app['configurada'] ? '' : 'd-none' ?>" id="crmc-btn-conectar" onclick="CRMC.conectarInstagram()"><i class="bi bi-instagram me-1"></i>Conectar Instagram</button>
                <?php endif; ?>
            </div>
            <div id="crmc-lista-canales" class="d-flex flex-column gap-2"></div>

            <!-- LinkedIn (fase 3): app de la empresa y conexión de perfil/páginas; lo pinta crm_configuracion.js -->
            <div class="card border-0 shadow-sm mt-3"><div class="card-body" id="crmc-li-app">
                <div class="small text-muted">Cargando LinkedIn…</div>
            </div></div>
        </div>

        <!-- Embudos y etapas -->
        <div class="tab-pane fade" id="crmc-embudos">
            <div class="row g-3">
                <div class="col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <span class="fw-bold small">Embudos</span>
                            <?php if (!empty($perm['crear'])): ?>
                                <button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="CRMC.nuevoEmbudo()"><i class="bi bi-plus-lg"></i></button>
                            <?php endif; ?>
                        </div>
                        <div class="list-group list-group-flush" id="crmc-lista-embudos"></div>
                    </div>
                </div>
                <div class="col-md-8 col-lg-9">
                    <div class="card border-0 shadow-sm" id="crmc-editor-embudo">
                        <div class="card-body text-muted small">Elija un embudo.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asistente virtual (fase 2): responde solo los mensajes directos de Instagram -->
        <div class="tab-pane fade" id="crmc-asistente">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-2 d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold small"><i class="bi bi-robot me-1 text-primary"></i>Asistente virtual de Instagram</span>
                            <select class="form-select form-select-sm ms-auto" id="bot-canal" style="width:220px;" title="Cuenta de Instagram"></select>
                        </div>
                        <div class="card-body">
                            <div id="bot-aviso"></div>
                            <form id="bot-form" autocomplete="off" onsubmit="event.preventDefault(); CRMBOT.guardar();">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="bot-activo" name="activo" value="1">
                                    <label class="form-check-label fw-bold" for="bot-activo">Responder automáticamente los mensajes directos</label>
                                    <div class="small text-muted">Solo dentro de las 24 h de Instagram. Se identifica como asistente virtual y pasa a una persona cuando hace falta.</div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold mb-1">Nombre del asistente</label>
                                        <input type="text" class="form-control form-control-sm" name="nombre_asistente" id="bot-nombre" maxlength="60">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold mb-1">Tono</label>
                                        <select class="form-select form-select-sm" name="tono" id="bot-tono">
                                            <option value="cercano">Cercano (tutea)</option>
                                            <option value="formal">Formal (de usted)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold mb-1">Presentación (primer mensaje)</label>
                                        <input type="text" class="form-control form-control-sm" name="presentacion" id="bot-presentacion" maxlength="500"
                                               placeholder="Hola, soy … el asistente virtual de … ¿En qué te puedo ayudar?">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold mb-1">Lo que debe saber (instrucciones de la empresa)</label>
                                        <textarea class="form-control form-control-sm" rows="5" name="instrucciones" id="bot-instrucciones" maxlength="4000"
                                                  placeholder="Ej.: Atendemos de lunes a viernes de 9:00 a 18:00. Envíos a todo el país en 48 h. No hacemos cambios en ropa interior."></textarea>
                                        <div class="small text-muted">Además consulta los documentos cargados en <b>IA Soporte</b> y el catálogo de productos.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold mb-1">Temas de los que no habla</label>
                                        <textarea class="form-control form-control-sm" rows="2" name="temas_prohibidos" id="bot-prohibidos" maxlength="1000"
                                                  placeholder="Ej.: política, competencia, reclamos de garantía"></textarea>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold mb-1">Cuándo responde</label>
                                        <select class="form-select form-select-sm" name="horario" id="bot-horario" onchange="CRMBOT.verHorario()">
                                            <option value="siempre">Siempre</option>
                                            <option value="fuera_horario">Solo fuera del horario de atención</option>
                                        </select>
                                    </div>
                                    <div class="col-12 d-none" id="bot-horario-dias">
                                        <label class="form-label small fw-bold mb-1">Horario de atención (en ese horario responde el equipo)</label>
                                        <div class="d-flex flex-column gap-1" id="bot-dias"></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-check mt-sm-4">
                                            <input class="form-check-input" type="checkbox" name="mostrar_stock" id="bot-stock" value="1">
                                            <label class="form-check-label small" for="bot-stock">Decir si hay disponibilidad (nunca la cantidad)</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="crear_oportunidades" id="bot-oportunidades" value="1">
                                            <label class="form-check-label small" for="bot-oportunidades">Crear la oportunidad en el CRM cuando el cliente quiere comprar (y pasarla a una persona)</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold mb-1">Máx. respuestas por conversación</label>
                                        <input type="number" class="form-control form-control-sm" name="max_respuestas_conv" id="bot-max-conv" min="1" max="50">
                                        <div class="small text-muted">Al llegar, pasa a una persona.</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-bold mb-1">Máx. respuestas por día (empresa)</label>
                                        <input type="number" class="form-control form-control-sm" name="max_respuestas_dia" id="bot-max-dia" min="1" max="100000">
                                        <div class="small text-muted">Controla el gasto de su clave de IA.</div>
                                    </div>
                                </div>
                                <?php if (!empty($perm['actualizar'])): ?>
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm px-3" id="bot-btn-guardar"><i class="bi bi-check2-circle me-1"></i>Guardar</button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white py-2"><span class="fw-bold small"><i class="bi bi-cpu me-1 text-primary"></i>IA de la empresa y consumo del mes</span></div>
                        <div class="card-body small" id="bot-consumo"></div>
                    </div>
                    <?php if (!empty($perm['actualizar'])): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-2 d-flex align-items-center">
                            <span class="fw-bold small"><i class="bi bi-chat-dots me-1 text-primary"></i>Probar</span>
                            <button type="button" class="btn btn-link btn-sm ms-auto p-0" onclick="CRMBOT.reiniciarPrueba()">Empezar de nuevo</button>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-2">Escriba como si fuera un cliente. Usa la configuración <b>guardada</b>; no envía nada a Instagram ni guarda nada en el CRM (sí consume su clave de IA).</div>
                            <div id="bot-chat" class="border rounded p-2 mb-2 bg-light" style="height:280px; overflow-y:auto;"></div>
                            <div class="d-flex gap-1">
                                <input type="text" class="form-control form-control-sm" id="bot-prueba-texto" maxlength="1000" placeholder="Hola, ¿qué horario tienen?"
                                       onkeydown="if (event.key === 'Enter') { event.preventDefault(); CRMBOT.probar(); }">
                                <button type="button" class="btn btn-primary btn-sm" id="bot-btn-probar" onclick="CRMBOT.probar()" title="Enviar"><i class="bi bi-send"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Orígenes -->
        <div class="tab-pane fade" id="crmc-origenes">
            <div class="card border-0 shadow-sm" style="max-width:760px;">
                <div class="card-body">
                    <p class="small text-muted mb-2">Por dónde llegan los contactos (Instagram, LinkedIn, referidos…). Se usan para filtrar y medir.</p>
                    <div id="crmc-lista-origenes" class="d-flex flex-column gap-1"></div>
                </div>
            </div>
        </div>

        <!-- Motivos de pérdida -->
        <div class="tab-pane fade" id="crmc-motivos">
            <div class="card border-0 shadow-sm" style="max-width:760px;">
                <div class="card-body">
                    <p class="small text-muted mb-2">Motivos que se eligen al marcar una oportunidad como perdida.</p>
                    <div id="crmc-lista-motivos" class="d-flex flex-column gap-1"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($disponible): ?>
<script>
    window.CRMC_CONFIG = {
        urlBase: <?= json_encode($urlBase) ?>,
        perm: {
            crear:      <?= json_encode(!empty($perm['crear'])) ?>,
            actualizar: <?= json_encode(!empty($perm['actualizar'])) ?>,
            eliminar:   <?= json_encode(!empty($perm['eliminar'])) ?>,
        },
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="<?= $base ?>/js/modulos/crm_configuracion.js?v=<?= asset_ver('/js/modulos/crm_configuracion.js') ?>"></script>
<script src="<?= $base ?>/js/modulos/crm_asistente.js?v=<?= asset_ver('/js/modulos/crm_asistente.js') ?>"></script>
<?php endif; ?>
