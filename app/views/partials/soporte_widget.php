<?php
/**
 * Burbuja de chat de soporte — se inyecta en el layout principal, así que está
 * disponible en todas las pantallas del sistema.
 *
 * No comprueba permisos: el soporte es para cualquier usuario con sesión. Lo
 * que sí decide si se muestra o no es soporte_config.activo, que el JS consulta
 * al arrancar (si la migración no está desplegada, la burbuja no aparece).
 *
 * El badge de mensajes sin leer NO tiene refresco propio: lleva las clases
 * .soporte-sinleer-icon / .soporte-sinleer-badge y lo actualiza el mismo ciclo
 * de /contadores/navbarAjax que ya corre en el navbar. Cero peticiones extra.
 */
if (empty($_SESSION['id_usuario']) || empty($_SESSION['id_empresa'])) {
    return;
}

// La configuración se resuelve aquí y no con un fetch al cargar cada página:
// serían tantas peticiones como cargas de página × usuarios, y en un servidor de
// 1 vCPU cada petición ocupa un worker de PHP-FPM. El Service la cachea en APCu,
// así que esto no es una consulta por página. Si el módulo no está desplegado o
// está apagado, no se imprime nada: ni HTML, ni JS, ni peticiones.
try {
    $sopConfig = (new \App\Services\modulos\SoporteChatService(
        new \App\repositories\modulos\SoporteChatRepository(),
        new \App\Rules\modulos\SoporteChatRules(),
        new \App\Services\LogSistemaService(),
    ))->getConfig();
} catch (\Throwable $e) {
    return;
}

if (empty($sopConfig['activo'])) {
    return;
}

$base = rtrim(BASE_URL, '/');
$sopEnHorario = !empty($sopConfig['en_horario']);
?>

<div id="sopWidget" class="sop-widget">

    <!-- Panel -->
    <div id="sopPanel" class="sop-panel shadow-lg d-none">

        <!-- Cabecera -->
        <div class="sop-panel-head">
            <button type="button" class="btn btn-sm btn-link text-white p-0 me-2 d-none" id="sopBtnVolver" title="Volver">
                <i class="bi bi-arrow-left fs-5"></i>
            </button>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold text-truncate" id="sopTitulo">Soporte</div>
                <small class="opacity-75 text-truncate d-block" id="sopSubtitulo">
                    <?= htmlspecialchars($sopConfig['mensaje_bienvenida'] ?? '¿En qué podemos ayudarte?') ?>
                </small>
            </div>
            <button type="button" class="btn btn-sm btn-link text-white p-0 ms-2" id="sopBtnCerrar" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Aviso fuera de horario -->
        <?php if (!$sopEnHorario && !empty($sopConfig['mensaje_fuera_horario'])): ?>
            <div class="sop-aviso"><?= htmlspecialchars($sopConfig['mensaje_fuera_horario']) ?></div>
        <?php endif; ?>

        <!-- Vista: lista de conversaciones -->
        <div id="sopVistaLista" class="sop-body">
            <div id="sopLista" class="sop-lista">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>

        <!-- Vista: conversación -->
        <div id="sopVistaChat" class="sop-body d-none">
            <div id="sopMensajes" class="sop-mensajes"></div>
        </div>

        <!-- Vista: nueva consulta -->
        <div id="sopVistaNueva" class="sop-body d-none">
            <div class="p-3">
                <label class="form-label small fw-semibold">¿Sobre qué es tu consulta?</label>
                <input type="text" id="sopNuevoAsunto" class="form-control form-control-sm mb-2"
                       placeholder="Asunto (opcional)" maxlength="200">
                <textarea id="sopNuevoMensaje" class="form-control form-control-sm" rows="5"
                          placeholder="Cuéntanos con detalle qué necesitas…" maxlength="4000"></textarea>
                <div class="form-text" id="sopNuevoContexto"></div>
                <button type="button" class="btn btn-primary btn-sm w-100 mt-2" id="sopBtnEnviarNueva">
                    <i class="bi bi-send me-1"></i> Enviar consulta
                </button>
            </div>
        </div>

        <!-- Pie: acciones de lista / caja de escritura -->
        <div class="sop-panel-foot">
            <div id="sopFootLista">
                <button type="button" class="btn btn-primary btn-sm w-100" id="sopBtnNueva">
                    <i class="bi bi-plus-lg me-1"></i> Nueva consulta
                </button>
            </div>
            <form id="sopFootChat" class="d-none d-flex align-items-end gap-2">
                <input type="file" id="sopFile" class="d-none"
                       accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.xml,.txt,.csv,.xlsx,.xls,.docx,.zip">
                <button type="button" class="btn btn-light btn-sm border" id="sopBtnAdjuntar"
                        style="height:31px;" title="Adjuntar archivo (máx. 10 MB)">
                    <i class="bi bi-paperclip"></i>
                </button>
                <textarea id="sopInput" class="form-control form-control-sm" rows="1"
                          placeholder="Escribe un mensaje…" maxlength="4000" style="resize:none;"></textarea>
                <button type="submit" class="btn btn-primary btn-sm sop-btn-send" title="Enviar">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>

            <!-- Calificación: aparece cuando el equipo marca la consulta resuelta -->
            <div id="sopCalificar" class="d-none text-center">
                <div class="small text-muted mb-1">¿Resolvimos tu consulta?</div>
                <div class="d-flex justify-content-center gap-1 mb-2" id="sopEstrellas">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="btn btn-sm btn-link p-0 sop-estrella" data-valor="<?= $i ?>"
                                style="font-size:1.3rem;line-height:1;color:#ffc107;" title="<?= $i ?>">
                            <i class="bi bi-star"></i>
                        </button>
                    <?php endfor; ?>
                </div>
                <input type="text" id="sopComentario" class="form-control form-control-sm mb-2"
                       placeholder="Comentario (opcional)" maxlength="500">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light border flex-fill" id="sopCalifOmitir">Ahora no</button>
                    <button type="button" class="btn btn-sm btn-primary flex-fill" id="sopCalifEnviar">Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón flotante -->
    <button type="button" id="sopLauncher" class="sop-launcher shadow" title="Soporte">
        <i class="bi bi-headset" id="sopLauncherIcon"></i>
        <span class="soporte-sinleer-icon d-none">
            <span class="badge rounded-pill bg-danger soporte-sinleer-badge sop-launcher-badge">0</span>
        </span>
    </button>
</div>

<style>
.sop-widget { position: fixed; right: 18px; bottom: 18px; z-index: 1035; }

.sop-launcher {
    width: 56px; height: 56px; border-radius: 50%;
    border: none; background: #0d6efd; color: #fff;
    font-size: 1.4rem; position: relative;
    display: flex; align-items: center; justify-content: center;
    transition: transform .15s ease, background-color .15s ease;
}
.sop-launcher:hover { transform: scale(1.06); background: #0b5ed7; }
.sop-launcher-badge { position: absolute; top: -2px; right: -2px; font-size: .65rem; }

/* ── Aviso de respuesta sin leer ──────────────────────────────────────────
   No necesita JavaScript propio: el ciclo de contadores del navbar quita el
   .d-none del badge cuando hay mensajes pendientes, y el :has() de aquí
   engancha la animación a ese mismo cambio.
   Se detiene con el panel abierto (.sop-abierto): si ya está mirando el chat,
   seguir llamando la atención sobra. */
/* La sombra base va repetida en cada paso: si no, la animación pisaría la
   sombra propia del botón y este se vería plano durante el ciclo. */
@keyframes sop-pulso {
    0%   { box-shadow: 0 .25rem .5rem rgba(0,0,0,.15), 0 0 0 0    rgba(220, 53, 69, .55); }
    70%  { box-shadow: 0 .25rem .5rem rgba(0,0,0,.15), 0 0 0 14px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 .25rem .5rem rgba(0,0,0,.15), 0 0 0 0    rgba(220, 53, 69, 0); }
}

/* Balanceo tipo campana, con pausa larga entre repeticiones para que llame
   la atención sin resultar molesto en una pantalla que se mira todo el día. */
@keyframes sop-campana {
    0%, 62%, 100%      { transform: rotate(0); }
    66%, 74%, 82%      { transform: rotate(14deg); }
    70%, 78%, 86%      { transform: rotate(-14deg); }
}

.sop-widget:not(.sop-abierto) .sop-launcher:has(.soporte-sinleer-icon:not(.d-none)) {
    animation: sop-pulso 1.8s ease-out infinite;
}
.sop-widget:not(.sop-abierto) .sop-launcher:has(.soporte-sinleer-icon:not(.d-none)) > .bi {
    animation: sop-campana 3.2s ease-in-out infinite;
    transform-origin: 50% 10%;
}

/* Quien haya pedido menos movimiento en su sistema conserva el badge, que es
   el aviso de verdad, pero sin animación. */
@media (prefers-reduced-motion: reduce) {
    .sop-widget .sop-launcher,
    .sop-widget .sop-launcher > .bi {
        animation: none !important;
    }
}

.sop-panel {
    position: absolute; right: 0; bottom: 68px;
    width: 370px; max-width: calc(100vw - 36px);
    height: 520px; max-height: calc(100dvh - 120px);
    background: #fff; border-radius: 14px; overflow: hidden;
    display: flex; flex-direction: column;
}
.sop-panel-head {
    background: #0d6efd; color: #fff;
    padding: 10px 14px; display: flex; align-items: center; flex-shrink: 0;
}
.sop-aviso {
    background: #fff3cd; color: #664d03; border-bottom: 1px solid #ffe69c;
    padding: 8px 12px; font-size: .78rem; flex-shrink: 0;
}
.sop-body { flex: 1 1 auto; overflow-y: auto; min-height: 0; }
.sop-panel-foot { border-top: 1px solid #dee2e6; padding: 10px; background: #fff; flex-shrink: 0; }
.sop-btn-send { width: 34px; height: 31px; flex-shrink: 0; }

/* Lista de conversaciones */
.sop-item { padding: 10px 14px; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
.sop-item:hover { background: #f8f9fa; }
.sop-item-prev {
    font-size: .78rem; color: #6c757d;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* Mensajes */
.sop-mensajes { display: flex; flex-direction: column; padding: 12px; gap: 8px; background: #f7f8fa; min-height: 100%; }
.sop-burbuja { max-width: 82%; padding: 7px 11px; border-radius: 12px; font-size: .84rem; box-shadow: 0 1px 1px rgba(0,0,0,.06); }
.sop-burbuja-usuario { background: #d1e7ff; align-self: flex-end; border-bottom-right-radius: 3px; }
.sop-burbuja-agente  { background: #fff;    align-self: flex-start; border-bottom-left-radius: 3px; }
.sop-burbuja-sistema {
    background: transparent; align-self: center; text-align: center;
    color: #6c757d; font-size: .75rem; font-style: italic; box-shadow: none;
}
.sop-hora { font-size: .68rem; color: rgba(0,0,0,.45); margin-top: 3px; text-align: right; }

@media (max-width: 575.98px) {
    .sop-widget { right: 12px; bottom: 12px; }
    .sop-panel {
        position: fixed; right: 0; left: 0; bottom: 0; top: 0;
        width: 100%; max-width: 100%; height: 100dvh; max-height: 100dvh;
        border-radius: 0;
    }
}
</style>

<script>
    window.SOP_BASE = '<?= $base ?>';
    window.SOP_MODULO = '<?= htmlspecialchars($rutaModulo ?? '', ENT_QUOTES) ?>';
</script>
<script src="<?= $base ?>/js/soporte_widget.js?v=<?= @filemtime(MVC_ROOT . '/public/js/soporte_widget.js') ?: time() ?>"></script>
