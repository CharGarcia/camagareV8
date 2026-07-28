/**
 * Motor WebRTC de la sala de videollamada.
 *
 * ── Cómo funciona ───────────────────────────────────────────────────────────
 * El servidor solo hace de buzón: los navegadores intercambian por él su
 * descripción de sesión (SDP) y sus direcciones candidatas (ICE) hasta que
 * consiguen hablarse directo. A partir de ese momento el audio y el video van
 * de máquina a máquina y el servidor deja de participar.
 *
 * ── Malla (mesh) ────────────────────────────────────────────────────────────
 * Se abre una conexión por CADA par de participantes. Con N personas cada
 * navegador sostiene N-1 conexiones y sube N-1 copias de su video: por eso el
 * módulo limita el cupo. El servidor no sufre; la conexión de subida de cada
 * participante, sí.
 *
 * ── Colisión de ofertas (glare) ─────────────────────────────────────────────
 * Si dos navegadores se ofertan a la vez, la negociación se rompe. Se evita con
 * una regla simple y determinista: de cada par, SOLO el que tiene el
 * identificador menor envía la oferta. El otro espera y responde.
 */
(function () {
    'use strict';

    const BASE    = window.VCS_BASE;
    const ID_SALA = window.VCS_ID_SALA;

    /** Cada cuánto se consulta el buzón mientras alguna conexión negocia. */
    const POLL_NEGOCIANDO = 1000;
    /** Cada cuánto cuando ya está todo conectado (solo se vigila quién entra o sale). */
    const POLL_ESTABLE = 3000;
    /** Tiempo sin conectar tras el cual se vuelve a intentar la negociación. */
    const REINTENTO_MS = 8000;
    /** Cuántas veces se reintenta antes de darlo por imposible y avisar. */
    const MAX_REINTENTOS = 3;

    let peerId = null;
    let cursor = 0;
    let iceServers = [];
    let localStream = null;
    let pantallaStream = null;
    let pollTimer = null;
    let saliendo = false;

    /** peerId → { pc, nombre, stream, negociando } */
    const peers = new Map();

    // ── Utilidades de interfaz ───────────────────────────────────────────────

    const $ = (id) => document.getElementById(id);

    function estado(texto, clase) {
        const el = $('vcEstado');
        if (!el) return;
        el.textContent = texto;
        el.className = 'small ' + (clase || 'text-secondary');
    }

    function aviso(html, tipo) {
        const el = $('vcAvisos');
        if (!el) return;
        el.innerHTML = html
            ? `<div class="alert alert-${tipo || 'warning'} py-2 px-3 mb-0 small">${html}</div>`
            : '';
    }

    // ── Arranque ─────────────────────────────────────────────────────────────

    async function iniciar() {
        estado('Pidiendo cámara y micrófono...', 'text-info');

        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        } catch (e) {
            // Sin cámara todavía se puede participar escuchando, así que se
            // intenta solo con audio antes de rendirse.
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                aviso('No se pudo usar la cámara. Entrará solo con audio.', 'warning');
            } catch (e2) {
                estado('Sin cámara ni micrófono', 'text-danger');
                aviso(mensajeMedios(e2), 'danger');
                return;
            }
        }

        pintarVideoLocal();

        estado('Entrando a la sala...', 'text-info');

        const fd = new FormData();
        fd.append('id', ID_SALA);

        let datos;
        try {
            datos = await (await fetch(`${BASE}/entrarAjax`, { method: 'POST', body: fd })).json();
        } catch (e) {
            estado('Sin conexión con el servidor', 'text-danger');
            return;
        }

        if (!datos.ok) {
            estado('No se pudo entrar', 'text-danger');
            aviso(datos.mensaje || 'No se pudo entrar a la reunión.', 'danger');
            return;
        }

        peerId = datos.peer_id;
        cursor = datos.cursor || 0;
        iceServers = (datos.credenciales && datos.credenciales.ice_servers) || [];

        if (datos.credenciales && !datos.credenciales.turn_configurado) {
            aviso('No hay servidor TURN configurado. Si alguien no logra conectarse, es por esto: ' +
                  'las redes de oficina y el internet móvil suelen necesitar un relay.', 'warning');
        }

        // Quienes ya estaban en la sala: se abre una conexión con cada uno.
        (datos.presentes || []).forEach(p => conectarCon(p.peer_id, p.nombre));

        estado(peers.size > 0 ? 'Conectando...' : 'Esperando a los demás...', 'text-info');
        agendarPoll(POLL_NEGOCIANDO);
    }

    function mensajeMedios(e) {
        if (!e) return 'No se pudo acceder a la cámara ni al micrófono.';
        if (e.name === 'NotAllowedError') {
            return 'El navegador bloqueó el acceso. Permita cámara y micrófono para este sitio ' +
                   'desde el candado de la barra de direcciones y recargue.';
        }
        if (e.name === 'NotFoundError') return 'No se encontró cámara ni micrófono en este equipo.';
        if (e.name === 'NotReadableError') return 'La cámara está siendo usada por otro programa.';
        return 'No se pudo acceder a la cámara ni al micrófono: ' + e.name;
    }

    // ── Buzón de señalización ────────────────────────────────────────────────

    function agendarPoll(ms) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, ms);
    }

    async function poll() {
        if (saliendo) return;

        try {
            const r = await fetch(`${BASE}/senalesAjax?id=${ID_SALA}&cursor=${cursor}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const j = await r.json();

            if (!j.ok) {
                if (j.reentrar) { estado('Sesión perdida, recargue la ventana', 'text-danger'); return; }
                agendarPoll(POLL_ESTABLE);
                return;
            }

            cursor = j.cursor || cursor;

            if (j.estado === 'finalizada' || j.estado === 'cancelada') {
                aviso('El anfitrión finalizó la reunión.', 'info');
                cerrarTodo();
                return;
            }

            (j.mensajes || []).forEach(procesarMensaje);
            sincronizarPresentes(j.presentes || []);
        } catch (e) {
            // Un fallo de red puntual no debe tumbar la sala: se reintenta.
        }

        reintentarAtascadas();

        const negociando = [...peers.values()].some(p => p.negociando);
        agendarPoll(negociando ? POLL_NEGOCIANDO : POLL_ESTABLE);
    }

    /**
     * Salvaguarda contra una negociación que se quedó a medias.
     *
     * El buzón es de mejor esfuerzo: un mensaje puede perderse (expiró antes de
     * leerse, o dos procesos escribieron a la vez). En vez de dejar al usuario
     * mirando una pantalla negra, quien tenía que ofertar vuelve a intentarlo.
     */
    function reintentarAtascadas() {
        const ahora = Date.now();
        peers.forEach((entrada, otroPeerId) => {
            if (!entrada.negociando) return;
            if (ahora - entrada.desde < REINTENTO_MS) return;
            if (!peerId || peerId >= otroPeerId) return;  // solo reintenta el ofertante

            entrada.desde = ahora;
            entrada.intentos = (entrada.intentos || 0) + 1;

            if (entrada.intentos > MAX_REINTENTOS) {
                entrada.negociando = false;
                aviso('No se pudo conectar con ' + escapar(entrada.nombre) +
                      '. Lo más probable es que falte configurar el servidor TURN.', 'danger');
                return;
            }
            negociar(otroPeerId, entrada);
        });
    }

    /** Abre conexión con los que aparecieron y cierra la de los que se fueron. */
    function sincronizarPresentes(presentes) {
        const vivos = new Set();

        presentes.forEach(p => {
            vivos.add(p.peer_id);
            if (!peers.has(p.peer_id)) {
                conectarCon(p.peer_id, p.nombre);
            }
        });

        [...peers.keys()].forEach(id => {
            if (!vivos.has(id)) cerrarPeer(id);
        });

        actualizarContador();
    }

    async function enviarSenal(para, tipo, payload) {
        const fd = new FormData();
        fd.append('id', ID_SALA);
        fd.append('para', para || '');
        fd.append('tipo', tipo);
        fd.append('payload', JSON.stringify(payload || {}));
        try {
            await fetch(`${BASE}/enviarSenalAjax`, { method: 'POST', body: fd });
        } catch (e) { /* el navegador reintentará en la siguiente negociación */ }
    }

    async function procesarMensaje(m) {
        if (m.tipo === 'bye') { cerrarPeer(m.de); actualizarContador(); return; }

        const entrada = peers.get(m.de) || conectarCon(m.de, m.payload?.nombre || '');
        if (!entrada) return;

        try {
            if (m.tipo === 'offer') {
                await entrada.pc.setRemoteDescription(new RTCSessionDescription(m.payload));
                const respuesta = await entrada.pc.createAnswer();
                await entrada.pc.setLocalDescription(respuesta);
                await enviarSenal(m.de, 'answer', { type: respuesta.type, sdp: respuesta.sdp });
            } else if (m.tipo === 'answer') {
                await entrada.pc.setRemoteDescription(new RTCSessionDescription(m.payload));
            } else if (m.tipo === 'ice' && m.payload && m.payload.candidate) {
                await entrada.pc.addIceCandidate(new RTCIceCandidate(m.payload));
            }
        } catch (e) {
            console.error('Señal ' + m.tipo + ' de ' + m.de, e);
        }
    }

    // ── Conexiones ───────────────────────────────────────────────────────────

    function conectarCon(otroPeerId, nombre) {
        if (!otroPeerId || otroPeerId === peerId || peers.has(otroPeerId)) {
            return peers.get(otroPeerId) || null;
        }

        const pc = new RTCPeerConnection({ iceServers: iceServers });
        const entrada = {
            pc: pc,
            nombre: nombre || 'Participante',
            stream: null,
            negociando: true,
            desde: Date.now(),
            intentos: 0,
        };
        peers.set(otroPeerId, entrada);

        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

        pc.onicecandidate = (ev) => {
            if (ev.candidate) enviarSenal(otroPeerId, 'ice', ev.candidate.toJSON());
        };

        pc.ontrack = (ev) => {
            entrada.stream = ev.streams[0];
            pintarVideoRemoto(otroPeerId, entrada);
        };

        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'connected') {
                entrada.negociando = false;
                estado('En llamada', 'text-success');
                informarRuta(pc);
            } else if (pc.connectionState === 'failed') {
                entrada.negociando = false;
                aviso('No se pudo establecer la conexión con ' + entrada.nombre +
                      '. Suele ser falta de servidor TURN.', 'danger');
            } else if (pc.connectionState === 'disconnected') {
                estado('Reconectando...', 'text-warning');
            }
        };

        // Regla anti-colisión: de cada par, solo el del identificador menor oferta.
        if (peerId && peerId < otroPeerId) {
            negociar(otroPeerId, entrada);
        }

        actualizarContador();
        return entrada;
    }

    async function negociar(otroPeerId, entrada) {
        try {
            entrada.negociando = true;
            const oferta = await entrada.pc.createOffer();
            await entrada.pc.setLocalDescription(oferta);
            await enviarSenal(otroPeerId, 'offer', { type: oferta.type, sdp: oferta.sdp });
            agendarPoll(POLL_NEGOCIANDO);
        } catch (e) {
            console.error('Error creando la oferta', e);
        }
    }

    /** Diagnóstico: informa si la conexión salió directa o tuvo que usar el relay. */
    async function informarRuta(pc) {
        try {
            const stats = await pc.getStats();
            let tipo = null;
            stats.forEach(r => {
                if (r.type === 'candidate-pair' && r.state === 'succeeded' && r.localCandidateId) {
                    const local = stats.get(r.localCandidateId);
                    if (local) tipo = local.candidateType;
                }
            });
            if (tipo === 'relay') {
                estado('En llamada (por relay TURN)', 'text-success');
            }
        } catch (e) { /* el diagnóstico es opcional */ }
    }

    function cerrarPeer(id) {
        const entrada = peers.get(id);
        if (!entrada) return;
        try { entrada.pc.close(); } catch (e) {}
        peers.delete(id);
        document.getElementById('vcTile-' + cssId(id))?.remove();
        if (peers.size === 0) estado('Esperando a los demás...', 'text-secondary');
    }

    function cerrarTodo() {
        clearTimeout(pollTimer);
        [...peers.keys()].forEach(cerrarPeer);
        if (localStream) localStream.getTracks().forEach(t => t.stop());
        if (pantallaStream) pantallaStream.getTracks().forEach(t => t.stop());
        estado('Reunión finalizada', 'text-secondary');
    }

    // ── Rejilla de video ─────────────────────────────────────────────────────

    const cssId = (s) => String(s).replace(/[^a-zA-Z0-9_-]/g, '');

    function pintarVideoLocal() {
        const v = $('vcVideoLocal');
        if (v) { v.srcObject = localStream; v.muted = true; }
    }

    function pintarVideoRemoto(id, entrada) {
        const grid = $('vcGrid');
        if (!grid) return;

        let tile = document.getElementById('vcTile-' + cssId(id));
        if (!tile) {
            tile = document.createElement('div');
            tile.id = 'vcTile-' + cssId(id);
            tile.className = 'vc-tile';
            tile.innerHTML =
                '<video autoplay playsinline></video>' +
                '<div class="vc-tile-nombre">' + escapar(entrada.nombre) + '</div>';
            grid.appendChild(tile);
        }
        tile.querySelector('video').srcObject = entrada.stream;
        ajustarRejilla();
    }

    /** La rejilla se reacomoda según cuántos haya, para aprovechar la ventana. */
    function ajustarRejilla() {
        const grid = $('vcGrid');
        if (!grid) return;
        const n = grid.querySelectorAll('.vc-tile').length;
        const columnas = n <= 1 ? 1 : (n <= 4 ? 2 : 3);
        grid.style.gridTemplateColumns = `repeat(${columnas}, minmax(0, 1fr))`;
    }

    function actualizarContador() {
        const el = $('vcContador');
        if (el) el.textContent = String(peers.size + 1);
    }

    const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    // ── Controles ────────────────────────────────────────────────────────────

    window.VCS_toggleMic = function () {
        if (!localStream) return;
        const pista = localStream.getAudioTracks()[0];
        if (!pista) return;
        pista.enabled = !pista.enabled;
        const btn = $('vcBtnMic');
        btn.innerHTML = pista.enabled ? '<i class="bi bi-mic-fill"></i>' : '<i class="bi bi-mic-mute-fill"></i>';
        btn.classList.toggle('btn-danger', !pista.enabled);
        btn.classList.toggle('btn-outline-light', pista.enabled);
    };

    window.VCS_toggleCam = function () {
        if (!localStream) return;
        const pista = localStream.getVideoTracks()[0];
        if (!pista) return;
        pista.enabled = !pista.enabled;
        const btn = $('vcBtnCam');
        btn.innerHTML = pista.enabled ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-camera-video-off-fill"></i>';
        btn.classList.toggle('btn-danger', !pista.enabled);
        btn.classList.toggle('btn-outline-light', pista.enabled);
    };

    /**
     * Compartir pantalla.
     *
     * Se sustituye la pista de video en las conexiones ya abiertas con
     * replaceTrack, que NO obliga a renegociar: el cambio es instantáneo y no
     * genera tráfico de señalización.
     */
    window.VCS_togglePantalla = async function () {
        if (pantallaStream) { detenerPantalla(); return; }

        try {
            pantallaStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        } catch (e) {
            return; // el usuario canceló el diálogo
        }

        const pista = pantallaStream.getVideoTracks()[0];
        reemplazarVideoEnPares(pista);

        const v = $('vcVideoLocal');
        if (v) { v.srcObject = pantallaStream; v.classList.add('vc-sin-espejo'); }

        // El navegador ofrece su propio botón de "dejar de compartir".
        pista.onended = detenerPantalla;

        const btn = $('vcBtnPantalla');
        btn.classList.add('btn-primary');
        btn.classList.remove('btn-outline-light');
    };

    function detenerPantalla() {
        if (!pantallaStream) return;
        pantallaStream.getTracks().forEach(t => t.stop());
        pantallaStream = null;

        const pistaCam = localStream ? localStream.getVideoTracks()[0] : null;
        if (pistaCam) reemplazarVideoEnPares(pistaCam);

        const v = $('vcVideoLocal');
        if (v) { v.srcObject = localStream; v.classList.remove('vc-sin-espejo'); }

        const btn = $('vcBtnPantalla');
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-light');
    }

    function reemplazarVideoEnPares(pista) {
        peers.forEach(entrada => {
            const sender = entrada.pc.getSenders().find(s => s.track && s.track.kind === 'video');
            if (sender && pista) sender.replaceTrack(pista);
        });
    }

    /**
     * Datos de la salida.
     * El token CSRF va explícito porque sendBeacon NO pasa por el interceptor de
     * csrf.js (que solo envuelve fetch, XHR y formularios): sin esto, la salida
     * se rechazaría con 419 y el participante quedaría "fantasma" hasta que
     * expirara su presencia.
     */
    function datosSalida() {
        const fd = new FormData();
        fd.append('id', ID_SALA);
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        return fd;
    }

    window.VCS_salir = function () {
        saliendo = true;
        cerrarTodo();

        // sendBeacon sobrevive al cierre de la ventana; fetch no siempre.
        if (navigator.sendBeacon) {
            navigator.sendBeacon(`${BASE}/salirAjax`, datosSalida());
        } else {
            fetch(`${BASE}/salirAjax`, { method: 'POST', body: datosSalida(), keepalive: true });
        }
        window.close();
    };

    window.addEventListener('beforeunload', function () {
        if (saliendo) return;
        saliendo = true;
        if (navigator.sendBeacon) navigator.sendBeacon(`${BASE}/salirAjax`, datosSalida());
    });

    iniciar();
})();
