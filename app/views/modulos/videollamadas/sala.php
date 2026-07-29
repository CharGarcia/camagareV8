<?php

/**
 * Sala de videollamada — página STANDALONE (se abre en ventana aparte, igual
 * que el visor de videos de ayuda). No usa el layout principal.
 *
 * El motor WebRTC vive en public/js/videollamada-sala.js. Esta vista solo aporta
 * la estructura que ese motor rellena: la antesala, la rejilla de videos, el
 * panel de chat, la cola de admisión y los controles.
 *
 * @var string $titulo
 * @var array  $sala
 * @var int    $idUsuario
 * @var string $nombreUsuario
 * @var string $rutaModulo
 * @var bool   $esAnfitrion
 */

$base = rtrim(BASE_URL ?? '', '/');
$urlModulo = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <?php
    // Vista standalone: sin esto sus peticiones POST responden 419 (§6 de CLAUDE.md).
    require MVC_APP . "/views/partials/csrf.php";
    ?>
    <style>
        html, body { height: 100%; }
        body { background: #12151a; color: #e9ecef; overflow: hidden; }

        .vc-wrap { display: flex; flex-direction: column; height: 100vh; }
        .vc-header { flex: 0 0 auto; background: #1b1f27; border-bottom: 1px solid #2b303b; }
        .vc-body { flex: 1 1 auto; min-height: 0; display: flex; }
        .vc-pie { flex: 0 0 auto; background: #1b1f27; border-top: 1px solid #2b303b; }

        .vc-escenario { flex: 1 1 auto; min-width: 0; position: relative; padding: 1rem; overflow: hidden; }

        /* Rejilla de participantes remotos. El JS ajusta las columnas según cuántos haya. */
        .vc-grid { display: grid; grid-template-columns: 1fr; gap: .75rem; height: 100%; align-content: center; }
        .vc-tile {
            position: relative; background: #000; border: 1px solid #2b303b;
            border-radius: 12px; overflow: hidden; aspect-ratio: 16 / 9;
        }
        .vc-tile video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .vc-tile-nombre {
            position: absolute; left: 8px; bottom: 8px;
            background: rgba(0, 0, 0, .6); color: #fff;
            padding: 2px 8px; border-radius: 6px; font-size: .75rem;
        }
        .vc-mano {
            position: absolute; right: 8px; top: 8px;
            background: #ffc107; color: #000; width: 30px; height: 30px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            animation: vc-saludo 1.2s ease-in-out infinite;
        }
        @keyframes vc-saludo {
            0%, 100% { transform: rotate(-8deg); }
            50%      { transform: rotate(8deg); }
        }

        /* Vista propia: pequeña y flotante, como en las apps de videollamada. */
        .vc-local {
            position: absolute; right: 1.25rem; bottom: 1.25rem;
            width: 220px; max-width: 32vw;
            background: #000; border: 1px solid #3a4150; border-radius: 12px;
            overflow: hidden; box-shadow: 0 8px 24px rgba(0, 0, 0, .5); z-index: 5;
        }
        .vc-local video {
            width: 100%; display: block; background: #000;
            transform: scaleX(-1);   /* espejo: uno se ve como en un espejo, no invertido */
        }
        .vc-local video.vc-sin-espejo { transform: none; }  /* al compartir pantalla no se refleja */
        .vc-local-etiqueta {
            position: absolute; left: 6px; bottom: 6px;
            background: rgba(0, 0, 0, .6); padding: 1px 6px; border-radius: 5px; font-size: .68rem;
        }

        .vc-espera-cartel { height: 100%; display: flex; flex-direction: column;
                            align-items: center; justify-content: center; color: #6c757d; gap: .75rem; }
        .vc-chip { background: #2b303b; border: 1px solid #3a4150; color: #adb5bd; }
        .vc-avisos { position: absolute; top: 1rem; left: 50%; transform: translateX(-50%);
                     z-index: 10; max-width: 640px; width: calc(100% - 2rem); }
        .vc-control { width: 46px; height: 46px; border-radius: 50%;
                      display: inline-flex; align-items: center; justify-content: center; position: relative; }

        /* Antesala: lo único que se ve mientras el anfitrión no autorice el ingreso. */
        .vc-antesala { flex: 1 1 auto; display: flex; flex-direction: column;
                       align-items: center; justify-content: center; text-align: center; padding: 2rem; }

        /* Cola de admisión, solo para el anfitrión. */
        .vc-cola {
            position: absolute; top: 1rem; right: 1rem; z-index: 20; width: 280px;
            background: #1b1f27; border: 1px solid #ffc107; border-radius: 10px; padding: .75rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .5);
        }

        /* Panel de chat: los mensajes viajan por WebRTC, no por el servidor. */
        .vc-chat { width: 300px; flex: 0 0 auto; background: #1b1f27;
                   border-left: 1px solid #2b303b; display: flex; flex-direction: column; }
        .vc-chat-mensajes { flex: 1 1 auto; overflow-y: auto; padding: .75rem; }
        .vc-msg { margin-bottom: .6rem; }
        .vc-msg-meta { font-size: .68rem; color: #7d8695; margin-bottom: 2px; }
        .vc-msg-texto { background: #2b303b; padding: .35rem .6rem; border-radius: 8px;
                        font-size: .82rem; word-wrap: break-word; }
        .vc-msg-propio .vc-msg-texto { background: #0d6efd; color: #fff; }
        .vc-msg-propio .vc-msg-meta { text-align: right; }
        .vc-chat-pie { flex: 0 0 auto; border-top: 1px solid #2b303b; padding: .5rem; }
        .vc-badge-chat { position: absolute; top: -4px; right: -4px; font-size: .6rem; }

        @media (max-width: 820px) {
            .vc-local { width: 130px; right: .75rem; bottom: .75rem; }
            .vc-escenario { padding: .5rem; }
            .vc-body { flex-direction: column; }
            .vc-chat { width: 100%; height: 45vh; border-left: 0; border-top: 1px solid #2b303b; }
            .vc-cola { width: calc(100% - 2rem); }
        }
    </style>
</head>
<body>
<div class="vc-wrap">

    <div class="vc-header px-3 py-2 d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2" style="min-width:0;">
            <i class="bi bi-camera-video-fill text-primary"></i>
            <span class="fw-bold text-truncate"><?= htmlspecialchars((string) $sala['titulo']) ?></span>
        </div>
        <span class="badge vc-chip"><code class="text-info"><?= htmlspecialchars((string) $sala['codigo']) ?></code></span>
        <?php if ($esAnfitrion): ?>
            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25">Anfitrión</span>
        <?php endif; ?>
        <span class="badge vc-chip"><i class="bi bi-people me-1"></i><span id="vcContador">1</span></span>
        <span id="vcEstado" class="small text-secondary">Iniciando...</span>
        <div class="ms-auto">
            <button class="btn btn-sm btn-outline-light" onclick="VCS_copiarEnlace()" title="Copiar enlace de la reunión">
                <i class="bi bi-link-45deg"></i>
            </button>
        </div>
    </div>

    <div class="vc-body">

        <!-- ANTESALA: se muestra solo si la sala exige admisión -->
        <div class="vc-antesala d-none" id="vcAntesala">
            <div id="vcAntesalaTexto">
                <div class="spinner-border text-primary mb-3"></div>
                <h5 class="fw-bold">Esperando autorización del anfitrión</h5>
                <p class="text-secondary small mb-0">
                    Esta reunión tiene sala de espera. En cuanto lo admitan, entrará automáticamente.
                </p>
            </div>
        </div>

        <div class="vc-escenario" id="vcEscenario">
            <div class="vc-avisos" id="vcAvisos"></div>

            <!-- Cola de admisión (solo la ve el anfitrión) -->
            <div class="vc-cola d-none" id="vcCola">
                <div class="small fw-bold text-warning mb-2">
                    <i class="bi bi-person-raised-hand me-1"></i> Esperando para entrar
                </div>
                <div id="vcColaLista"></div>
            </div>

            <div class="vc-grid" id="vcGrid"></div>

            <!-- Mientras no haya nadie más conectado -->
            <div class="vc-espera-cartel" id="vcEspera">
                <i class="bi bi-people fs-1"></i>
                <div>Esperando a los demás participantes</div>
                <div class="small">Comparta el código <code class="text-info"><?= htmlspecialchars((string) $sala['codigo']) ?></code> o el enlace de la reunión.</div>
            </div>

            <div class="vc-local">
                <video id="vcVideoLocal" autoplay playsinline muted></video>
                <span class="vc-local-etiqueta">Usted</span>
            </div>
        </div>

        <!-- CHAT: viaja por el canal de datos de WebRTC, sin pasar por el servidor -->
        <div class="vc-chat d-none" id="vcChat">
            <div class="px-3 py-2 border-bottom border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                <span class="small fw-bold text-uppercase text-secondary" style="font-size:.7rem;">Chat de la reunión</span>
                <button class="btn btn-sm btn-link text-secondary p-0" onclick="VCS_toggleChat()" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="vc-chat-mensajes" id="vcChatMensajes"></div>
            <div class="vc-chat-pie">
                <div class="input-group input-group-sm">
                    <input type="text" id="vcChatInput" class="form-control form-control-sm bg-dark text-light border-secondary"
                           placeholder="Escriba un mensaje..." maxlength="500" autocomplete="off">
                    <button class="btn btn-primary" onclick="VCS_enviarChat()"><i class="bi bi-send"></i></button>
                </div>
                <div class="text-secondary mt-1" style="font-size:.65rem;">
                    Los mensajes no se guardan: se pierden al cerrar la sala.
                </div>
            </div>
        </div>
    </div>

    <div class="vc-pie px-3 py-2 d-flex align-items-center justify-content-center gap-2 flex-wrap" id="vcControles">
        <button class="btn btn-outline-light vc-control" id="vcBtnMic" onclick="VCS_toggleMic()" title="Micrófono">
            <i class="bi bi-mic-fill"></i>
        </button>
        <button class="btn btn-outline-light vc-control" id="vcBtnCam" onclick="VCS_toggleCam()" title="Cámara">
            <i class="bi bi-camera-video-fill"></i>
        </button>
        <button class="btn btn-outline-light vc-control" id="vcBtnPantalla" onclick="VCS_togglePantalla()" title="Compartir pantalla">
            <i class="bi bi-display"></i>
        </button>
        <button class="btn btn-outline-light vc-control" id="vcBtnMano" onclick="VCS_toggleMano()" title="Levantar la mano">
            <i class="bi bi-hand-index-thumb"></i>
        </button>
        <button class="btn btn-outline-light vc-control" id="vcBtnChat" onclick="VCS_toggleChat()" title="Chat">
            <i class="bi bi-chat-dots"></i>
            <span class="badge bg-danger rounded-pill vc-badge-chat d-none" id="vcChatBadge">0</span>
        </button>
        <button class="btn btn-danger vc-control" onclick="VCS_salir()" title="Salir de la reunión">
            <i class="bi bi-telephone-x-fill"></i>
        </button>
    </div>
</div>

<script>
    window.VCS_BASE    = '<?= $urlModulo ?>';
    window.VCS_ID_SALA = <?= (int) $sala['id'] ?>;
    window.VCS_CODIGO  = '<?= htmlspecialchars((string) $sala['codigo'], ENT_QUOTES) ?>';
    window.VCS_NOMBRE  = '<?= htmlspecialchars($nombreUsuario, ENT_QUOTES) ?>';

    function VCS_copiarEnlace() {
        // Con dominio y con el código: BASE_URL es relativo, así que sin
        // location.origin el enlace copiado no serviría fuera del sistema.
        var ruta = window.VCS_BASE + '/sala/' + encodeURIComponent(window.VCS_CODIGO);
        var url  = /^https?:\/\//i.test(ruta) ? ruta : window.location.origin + ruta;
        navigator.clipboard.writeText(url).catch(function () {});
    }

    /* El cartel de espera se oculta en cuanto entra alguien más a la rejilla. */
    new MutationObserver(function () {
        var hay = document.querySelectorAll('#vcGrid .vc-tile').length > 0;
        document.getElementById('vcEspera').style.display = hay ? 'none' : '';
    }).observe(document.getElementById('vcGrid'), { childList: true });
</script>
<script src="<?= $base ?>/js/videollamada-sala.js?v=<?= time() ?>"></script>
</body>
</html>
