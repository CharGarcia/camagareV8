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
 * módulo limita el cupo y por eso la calidad baja sola cuando entra más gente.
 *
 * ── Colisión de ofertas (glare) ─────────────────────────────────────────────
 * Si dos navegadores se ofertan a la vez, la negociación se rompe. Se evita con
 * una regla simple y determinista: de cada par, SOLO el que tiene el
 * identificador menor envía la oferta. El otro espera y responde.
 *
 * ── Chat y señales de sala ──────────────────────────────────────────────────
 * El chat, la mano levantada y el aviso de silencio viajan por el canal de datos
 * de WebRTC, es decir, directo entre navegadores. NO tocan el servidor.
 */
(function () {
    'use strict';

    const BASE    = window.VCS_BASE;
    const ID_SALA = window.VCS_ID_SALA;
    const MI_NOMBRE = window.VCS_NOMBRE || 'Usted';

    /** Cada cuánto se consulta el buzón mientras alguna conexión negocia. */
    const POLL_NEGOCIANDO = 1000;
    /** Cada cuánto cuando ya está todo conectado (solo se vigila quién entra o sale). */
    const POLL_ESTABLE = 3000;
    /** Cada cuánto pregunta el que está en la antesala. */
    const POLL_ESPERA = 2000;
    /** Tiempo sin conectar tras el cual se vuelve a intentar la negociación. */
    const REINTENTO_MS = 8000;
    /** Cuántas veces se reintenta antes de darlo por imposible y avisar. */
    const MAX_REINTENTOS = 3;

    /**
     * Calidad según cuánta gente haya. En malla, cada participante que entra
     * multiplica lo que TODOS tienen que subir, así que la única forma de que
     * seis personas quepan en una conexión doméstica es bajar el listón.
     */
    const PERFILES = [
        { hasta: 1,        alto: 720, bitrate: 1200000 },
        { hasta: 3,        alto: 540, bitrate:  700000 },
        { hasta: 5,        alto: 360, bitrate:  400000 },
        { hasta: Infinity, alto: 270, bitrate:  250000 },
    ];

    let peerId = null;
    let cursor = 0;
    let iceServers = [];
    let localStream = null;
    let pantallaStream = null;
    let pollTimer = null;
    let saliendo = false;
    let mandaSala = false;
    let manoArriba = false;
    let sinLeer = 0;

    /** peerId → { pc, canal, nombre, stream, negociando, desde, intentos } */
    const peers = new Map();

    // ── Utilidades de interfaz ───────────────────────────────────────────────

    const $ = (id) => document.getElementById(id);

    const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const cssId = (s) => String(s).replace(/[^a-zA-Z0-9_-]/g, '');

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

        const v = $('vcVideoLocal');
        if (v) { v.srcObject = localStream; v.muted = true; }

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

        if (datos.en_espera) {
            mostrarAntesala(true);
            estado('En la sala de espera', 'text-warning');
            agendarPoll(POLL_ESPERA, true);
            return;
        }

        arrancarSala(datos);
    }

    /** Ya dentro: guarda credenciales, conecta con los presentes y empieza a pollear. */
    function arrancarSala(datos) {
        mostrarAntesala(false);

        mandaSala = !!datos.manda_sala || mandaSala;
        iceServers = (datos.credenciales && datos.credenciales.ice_servers) || iceServers;

        if (datos.credenciales && !datos.credenciales.turn_configurado) {
            aviso('No hay servidor TURN configurado. Si alguien no logra conectarse, es por esto: ' +
                  'las redes de oficina y el internet móvil suelen necesitar un relay.', 'warning');
        }

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

    // ── Sala de espera ───────────────────────────────────────────────────────

    function mostrarAntesala(visible) {
        $('vcAntesala')?.classList.toggle('d-none', !visible);
        $('vcEscenario')?.classList.toggle('d-none', visible);
        $('vcControles')?.classList.toggle('d-none', visible);
    }

    /** Poll reducido mientras se aguarda la admisión del anfitrión. */
    async function pollEspera() {
        if (saliendo) return;

        try {
            const j = await (await fetch(`${BASE}/senalesAjax?id=${ID_SALA}&cursor=${cursor}&espera=1`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })).json();

            if (!j.ok) { agendarPoll(POLL_ESPERA, true); return; }

            if (j.rechazado) {
                mostrarAntesala(true);
                $('vcAntesalaTexto').innerHTML =
                    '<i class="bi bi-x-circle text-danger fs-1 d-block mb-3"></i>' +
                    'El anfitrión no autorizó su ingreso.';
                estado('Ingreso no autorizado', 'text-danger');
                return;
            }

            if (j.estado === 'finalizada' || j.estado === 'cancelada') {
                $('vcAntesalaTexto').innerHTML =
                    '<i class="bi bi-clock-history fs-1 d-block mb-3"></i>La reunión terminó.';
                return;
            }

            if (j.admitido) {
                cursor = j.cursor || cursor;
                arrancarSala(j);
                return;
            }
        } catch (e) { /* reintenta en el siguiente ciclo */ }

        agendarPoll(POLL_ESPERA, true);
    }

    /** Cola de gente esperando, solo visible para el anfitrión. */
    function pintarCola(esperando) {
        const caja = $('vcCola');
        const lista = $('vcColaLista');
        if (!caja || !lista) return;

        if (!esperando || esperando.length === 0) {
            caja.classList.add('d-none');
            lista.innerHTML = '';
            return;
        }

        caja.classList.remove('d-none');
        lista.innerHTML = esperando.map(p => `
            <div class="d-flex align-items-center gap-2 py-1">
                <span class="flex-grow-1 text-truncate small">${escapar(p.nombre || 'Invitado')}</span>
                <button class="btn btn-success btn-sm py-0 px-2" onclick="VCS_admitir('${escapar(p.peer_id)}', true)">
                    Admitir
                </button>
                <button class="btn btn-outline-danger btn-sm py-0 px-2" onclick="VCS_admitir('${escapar(p.peer_id)}', false)">
                    No
                </button>
            </div>`).join('');
    }

    window.VCS_admitir = async function (peer, admitir) {
        const fd = new FormData();
        fd.append('id', ID_SALA);
        fd.append('peer_id', peer);
        fd.append('admitir', admitir ? '1' : '0');
        try {
            await fetch(`${BASE}/admitirAjax`, { method: 'POST', body: fd });
        } catch (e) { /* el poll volverá a mostrarlo si no se aplicó */ }
    };

    // ── Buzón de señalización ────────────────────────────────────────────────

    function agendarPoll(ms, esperando) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(esperando ? pollEspera : poll, ms);
    }

    async function poll() {
        if (saliendo) return;

        try {
            const j = await (await fetch(`${BASE}/senalesAjax?id=${ID_SALA}&cursor=${cursor}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })).json();

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
            if (mandaSala) pintarCola(j.esperando || []);
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
            if (!peers.has(p.peer_id)) conectarCon(p.peer_id, p.nombre);
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
            canal: null,
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
                aplicarCalidad();
                informarRuta(pc);
            } else if (pc.connectionState === 'failed') {
                entrada.negociando = false;
                aviso('No se pudo establecer la conexión con ' + escapar(entrada.nombre) +
                      '. Suele ser falta de servidor TURN.', 'danger');
            } else if (pc.connectionState === 'disconnected') {
                estado('Reconectando...', 'text-warning');
            }
        };

        // El canal de datos lo crea el mismo que oferta; el otro lo recibe.
        if (peerId && peerId < otroPeerId) {
            prepararCanal(entrada, pc.createDataChannel('cmg'), otroPeerId);
            negociar(otroPeerId, entrada);
        } else {
            pc.ondatachannel = (ev) => prepararCanal(entrada, ev.channel, otroPeerId);
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
            if (tipo === 'relay') estado('En llamada (por relay TURN)', 'text-success');
        } catch (e) { /* el diagnóstico es opcional */ }
    }

    function cerrarPeer(id) {
        const entrada = peers.get(id);
        if (!entrada) return;
        try { entrada.pc.close(); } catch (e) {}
        peers.delete(id);
        document.getElementById('vcTile-' + cssId(id))?.remove();
        if (peers.size === 0) estado('Esperando a los demás...', 'text-secondary');
        aplicarCalidad();
    }

    function cerrarTodo() {
        clearTimeout(pollTimer);
        [...peers.keys()].forEach(cerrarPeer);
        if (localStream) localStream.getTracks().forEach(t => t.stop());
        if (pantallaStream) pantallaStream.getTracks().forEach(t => t.stop());
        estado('Reunión finalizada', 'text-secondary');
    }

    // ── Canal de datos: chat y señales de sala ───────────────────────────────

    function prepararCanal(entrada, canal, otroPeerId) {
        entrada.canal = canal;

        canal.onmessage = (ev) => {
            let d;
            try { d = JSON.parse(ev.data); } catch (e) { return; }

            if (d.tipo === 'chat') {
                agregarMensaje(d.nombre || entrada.nombre, d.texto, false);
            } else if (d.tipo === 'mano') {
                marcarMano(otroPeerId, !!d.arriba);
            }
        };
    }

    function difundir(objeto) {
        const texto = JSON.stringify(objeto);
        peers.forEach(entrada => {
            if (entrada.canal && entrada.canal.readyState === 'open') {
                try { entrada.canal.send(texto); } catch (e) {}
            }
        });
    }

    window.VCS_enviarChat = function () {
        const input = $('vcChatInput');
        const texto = (input.value || '').trim();
        if (!texto) return;

        difundir({ tipo: 'chat', texto: texto, nombre: MI_NOMBRE });
        agregarMensaje(MI_NOMBRE, texto, true);
        input.value = '';
    };

    function agregarMensaje(nombre, texto, propio) {
        const caja = $('vcChatMensajes');
        if (!caja) return;

        const hora = new Date();
        const p = (n) => String(n).padStart(2, '0');

        const div = document.createElement('div');
        div.className = 'vc-msg' + (propio ? ' vc-msg-propio' : '');
        div.innerHTML =
            `<div class="vc-msg-meta">${escapar(nombre)} · ${p(hora.getHours())}:${p(hora.getMinutes())}</div>` +
            `<div class="vc-msg-texto">${escapar(texto)}</div>`;
        caja.appendChild(div);
        caja.scrollTop = caja.scrollHeight;

        // Si el panel está cerrado, se acumula el contador de no leídos.
        if (!propio && $('vcChat').classList.contains('d-none')) {
            sinLeer++;
            const badge = $('vcChatBadge');
            badge.textContent = String(sinLeer);
            badge.classList.remove('d-none');
        }
    }

    window.VCS_toggleChat = function () {
        const panel = $('vcChat');
        const abierto = !panel.classList.contains('d-none');
        panel.classList.toggle('d-none', abierto);

        if (!abierto) {
            sinLeer = 0;
            $('vcChatBadge').classList.add('d-none');
            $('vcChatInput')?.focus();
        }
    };

    window.VCS_toggleMano = function () {
        manoArriba = !manoArriba;
        difundir({ tipo: 'mano', arriba: manoArriba, nombre: MI_NOMBRE });

        const btn = $('vcBtnMano');
        btn.classList.toggle('btn-warning', manoArriba);
        btn.classList.toggle('btn-outline-light', !manoArriba);
    };

    function marcarMano(id, arriba) {
        const tile = document.getElementById('vcTile-' + cssId(id));
        if (!tile) return;

        let icono = tile.querySelector('.vc-mano');
        if (arriba && !icono) {
            icono = document.createElement('div');
            icono.className = 'vc-mano';
            icono.innerHTML = '<i class="bi bi-hand-index-thumb-fill"></i>';
            tile.appendChild(icono);
        } else if (!arriba && icono) {
            icono.remove();
        }
    }

    // ── Calidad adaptativa ───────────────────────────────────────────────────

    /**
     * Ajusta resolución y bitrate al número de participantes.
     *
     * La resolución se cambia en el track local (una sola vez, afecta a todos
     * los envíos) y el bitrate en cada emisor por separado.
     */
    function aplicarCalidad() {
        const total = peers.size;
        const perfil = PERFILES.find(p => total <= p.hasta) || PERFILES[PERFILES.length - 1];

        const pistaCam = localStream ? localStream.getVideoTracks()[0] : null;
        if (pistaCam && !pantallaStream) {
            pistaCam.applyConstraints({ height: { ideal: perfil.alto } }).catch(() => {});
        }

        peers.forEach(entrada => {
            const sender = entrada.pc.getSenders().find(s => s.track && s.track.kind === 'video');
            if (!sender) return;
            const params = sender.getParameters();
            if (!params.encodings || params.encodings.length === 0) params.encodings = [{}];
            params.encodings[0].maxBitrate = perfil.bitrate;
            sender.setParameters(params).catch(() => {});
        });
    }

    // ── Rejilla de video ─────────────────────────────────────────────────────

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

        aplicarCalidad();
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

    document.getElementById('vcChatInput')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); window.VCS_enviarChat(); }
    });

    iniciar();
})();
