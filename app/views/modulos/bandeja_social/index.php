<?php
/**
 * Bandeja social (modulos/bandeja_social): tres columnas — conversaciones,
 * chat y panel del contacto. Todo por AJAX (BandejaSocialController); lógica en
 * public/js/modulos/bandeja_social.js. Diseño: docs/diseno/crm-redes-fase1.md §9.2.
 *
 * @var array  $perm
 * @var string $rutaModulo
 * @var bool   $disponible
 * @var array  $usuarios
 * @var array  $embudos
 * @var int    $idUsuario
 * @var bool   $puedeOportunidad
 */
$base    = rtrim(BASE_URL, '/');
$urlBase = $base . '/' . ltrim($rutaModulo, '/');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>
<style>
    /* La bandeja ocupa justo el alto visible (sin scroll de página): el JS de abajo fija
       la altura exacta desde donde empieza; esto es el respaldo si el JS no corre. */
    #bandeja { display:grid; grid-template-columns: 320px 1fr 290px; gap:10px; height: calc(100dvh - var(--cmg-sticky-h, 80px) - 46px); min-height:420px; }
    #bandeja .bs-col { background:#fff; border-radius:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.06); display:flex; flex-direction:column; min-height:0; }
    #bandeja .bs-lista { overflow-y:auto; flex:1; }
    #bandeja .bs-item { padding:10px 12px; border-bottom:1px solid #f1f3f5; cursor:pointer; }
    #bandeja .bs-item:hover { background:#f8f9fa; }
    #bandeja .bs-item.activa { background:#e7f1ff; }
    #bandeja .bs-item .bs-nombre { font-weight:600; font-size:.85rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #bandeja .bs-item .bs-ultimo { font-size:.76rem; color:#6c757d; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #bandeja .bs-item.no-leida .bs-ultimo { color:#212529; font-weight:600; }
    #bandeja .bs-mensajes { flex:1; overflow-y:auto; padding:12px; background:#f6f7f9; }
    #bandeja .bs-msg { max-width:75%; margin-bottom:8px; padding:7px 10px; border-radius:.75rem; font-size:.85rem; white-space:pre-wrap; word-break:break-word; }
    #bandeja .bs-msg.in { background:#fff; border:1px solid #e3e6ea; margin-right:auto; border-bottom-left-radius:.2rem; }
    #bandeja .bs-msg.out { background:#dbeafe; margin-left:auto; border-bottom-right-radius:.2rem; }
    #bandeja .bs-msg.fallido { background:#fde2e1; }
    #bandeja .bs-msg .bs-meta { font-size:.68rem; color:#6c757d; margin-top:2px; text-align:right; }
    #bandeja .bs-msg img, #bandeja .bs-msg video { max-width:240px; max-height:240px; border-radius:.5rem; display:block; }
    #bandeja .bs-composer textarea { resize:none; font-size:.85rem; }
    #bandeja .bs-panel { overflow-y:auto; padding:12px; }
    #bandeja .bs-vacio { color:#adb5bd; text-align:center; margin:auto; }
    /* Celular y tablet: una columna a la vez. */
    @media (max-width: 991.98px) {
        #bandeja { grid-template-columns: 1fr; height: calc(100dvh - var(--cmg-sticky-h, 80px) - 40px); }
        #bandeja.ver-chat .bs-col-lista, #bandeja:not(.ver-chat) .bs-col-chat, #bandeja .bs-col-panel { display:none; }
        #bandeja.ver-panel .bs-col-chat { display:none; }
        #bandeja.ver-panel .bs-col-panel { display:flex; }
    }
</style>

<div class="container-fluid pt-1 pb-0 px-0 px-md-3" id="modulo-bandeja-social">
    <div class="d-flex align-items-center mb-1">
        <h5 class="mb-0 fw-bold"><i class="bi bi-chat-square-heart me-2 text-danger"></i>Bandeja social
            <span class="badge bg-danger ms-1 d-none" id="bs-total-sin-leer">0</span></h5>
    </div>

<?php if (!$disponible): ?>
    <div class="alert alert-warning"><i class="bi bi-database-exclamation me-1"></i>
        El CRM todavía no está instalado en la base de datos. Falta aplicar <code>database/2026-11-08_crm_fase1.sql</code>.</div>
<?php else: ?>
    <div id="bandeja">
        <!-- 1. Conversaciones -->
        <div class="bs-col bs-col-lista">
            <div class="p-2 border-bottom">
                <div class="btn-group btn-group-sm w-100 mb-2" role="group" id="bs-alcance">
                    <button type="button" class="btn btn-outline-primary active" data-alcance="todas">Todas</button>
                    <button type="button" class="btn btn-outline-primary" data-alcance="mias">Mías</button>
                    <button type="button" class="btn btn-outline-primary" data-alcance="sin_asignar">Sin asignar</button>
                </div>
                <div class="d-flex gap-1 mb-2">
                    <select class="form-select form-select-sm" id="bs-f-tipo">
                        <option value="">Todo</option><option value="dm">Mensajes</option><option value="comentario">Comentarios</option>
                    </select>
                    <select class="form-select form-select-sm" id="bs-f-estado">
                        <option value="abierta" selected>Abiertas</option><option value="cerrada">Cerradas</option><option value="">Todas</option>
                    </select>
                    <select class="form-select form-select-sm" id="bs-f-bot" title="Asistente virtual">
                        <option value="">Cualquiera</option><option value="traspasada">Pasadas por el asistente</option>
                    </select>
                </div>
                <input type="search" class="form-control form-control-sm" id="bs-f-buscar" placeholder="Buscar nombre, @usuario o texto…">
            </div>
            <div class="bs-lista" id="bs-lista"></div>
        </div>

        <!-- 2. Chat -->
        <div class="bs-col bs-col-chat">
            <div class="p-2 border-bottom d-flex align-items-center gap-2" id="bs-chat-cabecera">
                <button type="button" class="btn btn-sm btn-light d-lg-none" onclick="BS.volverALista()" title="Volver"><i class="bi bi-arrow-left"></i></button>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate" id="bs-chat-nombre">Elija una conversación</div>
                    <div class="small text-muted text-truncate" id="bs-chat-sub"></div>
                </div>
                <button type="button" class="btn btn-sm btn-light d-lg-none" onclick="BS.verPanel()" title="Contacto"><i class="bi bi-person-lines-fill"></i></button>
            </div>
            <div class="bs-mensajes" id="bs-mensajes"><div class="bs-vacio mt-5"><i class="bi bi-chat-dots fs-1 d-block"></i>Sin conversación abierta</div></div>
            <div class="bs-composer border-top p-2 d-none" id="bs-composer">
                <div class="small mb-1" id="bs-ventana"></div>
                <div class="btn-group btn-group-sm mb-1 d-none" role="group" id="bs-modo-comentario">
                    <input type="radio" class="btn-check" name="bs-modo" id="bs-modo-publica" value="publica" checked>
                    <label class="btn btn-outline-secondary" for="bs-modo-publica"><i class="bi bi-chat-square-text me-1"></i>Pública</label>
                    <input type="radio" class="btn-check" name="bs-modo" id="bs-modo-privada" value="privada">
                    <label class="btn btn-outline-secondary" for="bs-modo-privada" title="Mensaje directo a quien comentó (una sola vez, dentro de 7 días)"><i class="bi bi-envelope me-1"></i>Privada</label>
                </div>
                <div class="d-flex gap-1 align-items-end">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" title="Respuestas rápidas" onclick="BS.cargarRespuestas()"><i class="bi bi-lightning"></i></button>
                        <ul class="dropdown-menu shadow" id="bs-respuestas" style="max-height:300px;overflow:auto;max-width:360px;"></ul>
                    </div>
                    <textarea class="form-control" id="bs-texto" rows="2" maxlength="1000" placeholder="Escriba su respuesta… (Enter envía, Shift+Enter salto de línea)"></textarea>
                    <?php if (!empty($perm['crear'])): ?>
                        <input type="file" id="bs-imagen" accept="image/jpeg,image/png,image/gif" class="d-none" onchange="BS.enviarImagen(this)">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="bs-btn-imagen" onclick="document.getElementById('bs-imagen').click()" title="Enviar una imagen (JPG, PNG o GIF, hasta 8 MB)"><i class="bi bi-image"></i></button>
                        <button type="button" class="btn btn-primary btn-sm px-3" id="bs-btn-enviar" onclick="BS.enviar()" title="Enviar"><i class="bi bi-send"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. Contacto -->
        <div class="bs-col bs-col-panel">
            <div class="p-2 border-bottom d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light d-lg-none" onclick="BS.verChat()" title="Volver al chat"><i class="bi bi-arrow-left"></i></button>
                <span class="fw-bold small">Contacto</span>
            </div>
            <div class="bs-panel" id="bs-panel"><div class="text-muted small">Sin conversación abierta.</div></div>
        </div>
    </div>

    <!-- Crear oportunidad -->
    <div class="modal fade" id="bsModalOportunidad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold fs-6"><i class="bi bi-briefcase text-primary me-2"></i>Nueva oportunidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bs-op-token">
                    <label class="form-label small fw-bold mb-1">Oportunidad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm mb-2" id="bs-op-titulo" maxlength="200">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Valor estimado</label>
                            <input type="number" class="form-control form-control-sm text-end" id="bs-op-valor" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Embudo</label>
                            <select class="form-select form-select-sm" id="bs-op-embudo">
                                <?php foreach ($embudos as $e): ?>
                                    <option value="<?= (int) $e['id'] ?>" <?= !empty($e['es_defecto']) ? 'selected' : '' ?>><?= $h($e['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Queda con este contacto y enlazada a la conversación; se sigue en el CRM.</div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" id="bs-op-btn" onclick="BS.crearOportunidad()"><i class="bi bi-check2 me-1"></i>Crear</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enlazar a contacto existente -->
    <div class="modal fade" id="bsModalEnlazar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title fw-bold fs-6"><i class="bi bi-link-45deg text-primary me-2"></i>Enlazar a contacto existente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Si esta persona ya estaba registrada en el CRM (por ejemplo, la agregó a mano), una la conversación a ese contacto.</p>
                    <div class="position-relative">
                        <input type="hidden" id="bs-enl-id">
                        <input type="text" class="form-control form-control-sm" id="bs-enl-buscar" autocomplete="off" placeholder="Buscar contacto…">
                        <div class="list-group shadow position-absolute d-none" id="bs-enl-dd" style="left:0;right:0;z-index:1080;max-height:220px;overflow:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm px-3" onclick="BS.enlazar()"><i class="bi bi-link-45deg me-1"></i>Enlazar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.BS_CONFIG = {
            urlBase:   <?= json_encode($urlBase) ?>,
            urlCrm:    <?= json_encode($base . '/modulos/crm') ?>,
            idUsuario: <?= (int) $idUsuario ?>,
            usuarios:  <?= json_encode(array_map(fn($u) => ['id' => (int) $u['id'], 'nombre' => $u['nombre']], $usuarios), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            puedeOportunidad: <?= json_encode(!empty($puedeOportunidad)) ?>,
            perm: {
                crear:      <?= json_encode(!empty($perm['crear'])) ?>,
                actualizar: <?= json_encode(!empty($perm['actualizar'])) ?>,
            },
        };
    </script>
    <script src="<?= $base ?>/js/modulos/bandeja_social.js?v=<?= asset_ver('/js/modulos/bandeja_social.js') ?>"></script>
    <script>
        // La bandeja llena el alto visible desde donde empieza hasta el borde inferior
        // (sin scroll de la página): cada columna hace su propio scroll.
        (function () {
            const caja = document.getElementById('bandeja');
            if (!caja) return;
            const ajustar = () => {
                const arriba = caja.getBoundingClientRect().top + window.scrollY;
                const alto = Math.max(420, Math.floor(window.innerHeight - arriba - 8));
                caja.style.height = alto + 'px';
            };
            ajustar();
            window.addEventListener('resize', ajustar);
            window.addEventListener('orientationchange', ajustar);
            // El encabezado del sistema termina de medirse después de cargar.
            window.addEventListener('load', ajustar);
            setTimeout(ajustar, 300);
        })();
    </script>
<?php endif; ?>
</div>
