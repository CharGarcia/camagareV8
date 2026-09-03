/* ============================================================================
 * Burbuja de chat de soporte (todas las pantallas del sistema).
 *
 * Polling adaptativo, no WebSockets ni SSE: el servidor es 1 vCPU y una
 * conexión persistente por usuario se comería los workers de PHP-FPM.
 *
 * El ciclo barato es "pulsoAjax", que devuelve solo un número de versión
 * servido desde APCu; los mensajes se piden únicamente cuando ese número
 * cambia. Con el panel cerrado NO hay polling: el badge lo actualiza el ciclo
 * de contadores del navbar, que ya está corriendo de todos modos.
 * ========================================================================= */
(function () {
    'use strict';

    var BASE = window.SOP_BASE || '';

    var S = {
        bienvenida: '',          // mensaje configurado, lo renderiza el partial
        abierto: false,
        vista: 'lista',          // lista | chat | nueva
        idConversacion: 0,
        ultimoId: 0,             // id del último mensaje pintado
        version: 0,              // último número de versión conocido
        timer: null,
        ultimaActividad: Date.now(),
        enVuelo: false,
        conversaciones: [],      // último listado recibido
        calificacion: 0,         // estrellas elegidas, aún sin enviar
        califOmitida: false,     // el usuario pulsó "Ahora no"
        ignorarClick: false      // el pointerup vino de un arrastre, no de un clic
    };

    // Posición del botón flotante. Se guarda en el navegador (no en BD): es una
    // comodidad de pantalla y no vale la pena una petición por cada movimiento.
    // Va atada a la huella de la sesión (window.SOP_SESION): con cada inicio de
    // sesión nuevo se descarta y el botón vuelve a su esquina.
    var POS_KEY = 'cmg_sop_widget_pos';
    var OCULTO_KEY = 'cmg_sop_widget_oculto';   // guarda la huella de la sesión en que se quitó
    var POS_SESION = String(window.SOP_SESION || '');
    var POS_MARGEN = 8;      // separación mínima con los bordes de la ventana
    var POS_UMBRAL = 6;      // píxeles a partir de los cuales un clic pasa a ser arrastre

    // ── Utilidades ──────────────────────────────────────────────────────────

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /** Texto con saltos de línea preservados, ya escapado. */
    function escMulti(str) {
        return esc(str).replace(/\n/g, '<br>');
    }

    function fechaCorta(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d)) return '';
        var hoy = new Date();
        var mismoDia = d.toDateString() === hoy.toDateString();
        var hh = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        if (mismoDia) return hh;
        return String(d.getDate()).padStart(2, '0') + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear() + ' ' + hh;
    }

    function $(id) { return document.getElementById(id); }

    function api(ruta, opciones) {
        return fetch(BASE + ruta, Object.assign({
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }, opciones || {})).then(function (r) { return r.json(); });
    }

    function post(ruta, datos) {
        return api(ruta, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(datos)
        });
    }

    function aviso(mensaje) {
        if (window.Toast) {
            window.Toast.fire({ icon: 'error', title: mensaje });
        } else {
            alert(mensaje);
        }
    }

    // ── Cadencia del polling ────────────────────────────────────────────────

    /**
     * Devuelve los milisegundos hasta el próximo ciclo, o 0 para no reagendar.
     * La pestaña oculta no consulta nada: se reanuda con visibilitychange.
     */
    function proximoDelay() {
        if (!S.abierto || document.hidden) return 0;

        var inactivoMs = Date.now() - S.ultimaActividad;
        var base;
        if (inactivoMs > 5 * 60 * 1000)   base = 30000;  // 5 min sin tocar nada
        else if (!document.hasFocus())    base = 10000;  // en segundo plano
        else                              base = 3000;   // mirando la conversación

        // La vista de lista no tiene endpoint de pulso: cada ciclo es una
        // consulta real. Se espacia, porque además no es urgente — para eso
        // está el badge del navbar. El chat abierto sí usa el pulso barato.
        return S.vista === 'lista' ? base * 3 : base;
    }

    function agendar() {
        if (S.timer) { clearTimeout(S.timer); S.timer = null; }
        var delay = proximoDelay();
        if (delay > 0) S.timer = setTimeout(ciclo, delay);
    }

    function ciclo() {
        if (S.enVuelo) { agendar(); return; }

        if (S.vista === 'chat' && S.idConversacion > 0) {
            S.enVuelo = true;
            api('/modulos/soporte-chat/pulsoAjax?id=' + S.idConversacion)
                .then(function (r) {
                    if (r && r.ok && r.v > S.version) {
                        S.version = r.v;
                        return cargarMensajesNuevos();
                    }
                })
                .catch(function () { /* fallo de red: se reintenta al próximo ciclo */ })
                .finally(function () { S.enVuelo = false; agendar(); });
            return;
        }

        if (S.vista === 'lista') {
            S.enVuelo = true;
            cargarLista()
                .catch(function () {})
                .finally(function () { S.enVuelo = false; agendar(); });
            return;
        }

        agendar();
    }

    function marcarActividad() {
        S.ultimaActividad = Date.now();
    }

    // ── Vistas ──────────────────────────────────────────────────────────────

    function mostrarVista(vista) {
        S.vista = vista;

        $('sopVistaLista').classList.toggle('d-none', vista !== 'lista');
        $('sopVistaChat').classList.toggle('d-none', vista !== 'chat');
        $('sopVistaNueva').classList.toggle('d-none', vista !== 'nueva');

        $('sopFootLista').classList.toggle('d-none', vista !== 'lista');
        $('sopFootChat').classList.toggle('d-none', vista !== 'chat');
        $('sopBtnVolver').classList.toggle('d-none', vista === 'lista');

        if (vista === 'lista') {
            $('sopTitulo').textContent = 'Soporte';
            $('sopSubtitulo').textContent = S.bienvenida;
        } else if (vista === 'nueva') {
            $('sopTitulo').textContent = 'Nueva consulta';
            $('sopSubtitulo').textContent = 'Cuéntanos qué necesitas';
        }

        marcarActividad();
        agendar();
    }

    // ── Lista de conversaciones ─────────────────────────────────────────────

    function cargarLista() {
        return api('/modulos/soporte-chat/misConversacionesAjax').then(function (r) {
            if (!r || !r.ok) return;
            pintarLista(r.data || []);
        });
    }

    function pintarLista(items) {
        var cont = $('sopLista');
        S.conversaciones = items;   // para saber estado/calificación al abrir una

        if (!items.length) {
            cont.innerHTML =
                '<div class="text-center text-muted py-5 px-3">' +
                '<i class="bi bi-chat-dots opacity-50" style="font-size:2.5rem;"></i>' +
                '<p class="mt-2 mb-0 small">Todavía no tienes consultas.<br>Abre una y te respondemos.</p>' +
                '</div>';
            return;
        }

        var estados = {
            espera:     ['En espera', 'bg-warning text-dark'],
            atendiendo: ['Atendiendo', 'bg-info text-dark'],
            resuelta:   ['Resuelta', 'bg-success'],
            cerrada:    ['Cerrada', 'bg-secondary']
        };

        cont.innerHTML = items.map(function (c) {
            var e = estados[c.estado] || ['', 'bg-secondary'];
            var sinLeer = parseInt(c.sin_leer_usuario || 0, 10);
            return '' +
                '<div class="sop-item" data-id="' + c.id + '" data-asunto="' + esc(c.asunto || '') + '">' +
                  '<div class="d-flex align-items-center gap-2">' +
                    '<span class="fw-semibold small text-truncate flex-grow-1">' + esc(c.asunto || 'Consulta') + '</span>' +
                    (sinLeer > 0 ? '<span class="badge rounded-pill bg-danger">' + sinLeer + '</span>' : '') +
                  '</div>' +
                  '<div class="sop-item-prev">' + esc(c.ultimo_mensaje || '') + '</div>' +
                  '<div class="d-flex align-items-center gap-2 mt-1">' +
                    '<span class="badge ' + e[1] + '" style="font-size:.62rem;">' + e[0] + '</span>' +
                    '<small class="text-muted" style="font-size:.68rem;">' + fechaCorta(c.ultimo_mensaje_at) + '</small>' +
                  '</div>' +
                '</div>';
        }).join('');

        Array.prototype.forEach.call(cont.querySelectorAll('.sop-item'), function (el) {
            el.addEventListener('click', function () {
                abrirConversacion(parseInt(el.dataset.id, 10), el.dataset.asunto || '');
            });
        });
    }

    // ── Conversación ────────────────────────────────────────────────────────

    function abrirConversacion(id, asunto) {
        S.idConversacion = id;
        S.ultimoId = 0;
        S.version = 0;
        S.calificacion = 0;
        S.califOmitida = false;
        $('sopTitulo').textContent = asunto || 'Consulta';
        $('sopSubtitulo').textContent = 'Te responderemos por aquí';

        // Si el equipo ya la resolvió y no está calificada, el pie pasa a ser
        // la caja de calificación en vez de la de escribir.
        var conv = (S.conversaciones || []).filter(function (c) { return parseInt(c.id, 10) === id; })[0];
        pintarEstrellas(0);
        $('sopComentario').value = '';
        actualizarPieChat(conv);
        $('sopMensajes').innerHTML =
            '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';
        mostrarVista('chat');

        api('/modulos/soporte-chat/mensajesAjax?id=' + id).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo abrir la conversación.'); return; }
            $('sopMensajes').innerHTML = '';
            pintarMensajes(r.data || []);
            // Al leer, el badge del navbar cambia: pedirle que se refresque.
            if (window.CMG_refreshContadores) window.CMG_refreshContadores();
        });
    }

    function cargarMensajesNuevos() {
        return api('/modulos/soporte-chat/mensajesAjax?id=' + S.idConversacion + '&desde=' + S.ultimoId)
            .then(function (r) {
                if (!r || !r.ok) return;
                pintarMensajes(r.data || []);
            });
    }

    function pintarMensajes(mensajes) {
        if (!mensajes.length) return;

        var cont = $('sopMensajes');
        var pegadoAbajo = (cont.scrollHeight - cont.scrollTop - cont.clientHeight) < 60;

        mensajes.forEach(function (m) {
            var id = parseInt(m.id, 10);
            if (id > S.ultimoId) S.ultimoId = id;
            if (id > S.version) S.version = id;

            var div = document.createElement('div');
            div.className = 'sop-burbuja sop-burbuja-' + esc(m.rol);

            if (m.rol === 'sistema') {
                div.innerHTML = escMulti(m.contenido);
            } else {
                var autor = m.rol === 'agente'
                    ? '<div class="fw-semibold" style="font-size:.7rem;color:#0d6efd;">' + esc(m.autor_nombre || 'Soporte') + '</div>'
                    : '';
                div.innerHTML = autor + escMulti(m.contenido) + htmlAdjunto(m) +
                    '<div class="sop-hora">' + fechaCorta(m.created_at) + '</div>';
            }
            cont.appendChild(div);
        });

        if (pegadoAbajo) cont.scrollTop = cont.scrollHeight;
    }

    // ── Adjuntos ────────────────────────────────────────────────────────────

    function htmlAdjunto(m) {
        if (!m.adjunto) return '';

        var url = BASE + '/modulos/soporte-chat/adjuntoVer?id=' + encodeURIComponent(m.id);
        var nombre = esc(m.adjunto_nombre || 'archivo');

        if (String(m.adjunto_mime || '').indexOf('image/') === 0) {
            return '<a href="' + url + '" target="_blank" rel="noopener">' +
                   '<img src="' + url + '" alt="' + nombre + '" ' +
                   'style="max-width:100%;max-height:180px;border-radius:8px;display:block;margin-top:4px;"></a>';
        }
        return '<a href="' + url + '" target="_blank" rel="noopener" ' +
               'class="d-inline-flex align-items-center gap-1 mt-1 text-decoration-none">' +
               '<i class="bi bi-paperclip"></i><span style="font-size:.78rem;">' + nombre + '</span></a>';
    }

    function subirArchivo(file) {
        if (!file || !S.idConversacion) return;

        var fd = new FormData();
        fd.append('id', S.idConversacion);
        fd.append('archivo', file);
        fd.append('texto', $('sopInput').value.trim());
        fd.append('origen', 'widget');
        marcarActividad();

        fetch(BASE + '/modulos/soporte-chat/adjuntarAjax', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (r) { return r.json(); })
        .then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo adjuntar el archivo.'); return; }
            $('sopInput').value = '';
            return cargarMensajesNuevos();
        })
        .catch(function () { aviso('No se pudo adjuntar el archivo.'); });
    }

    // ── Calificación ────────────────────────────────────────────────────────

    /**
     * La caja de calificar sustituye a la de escribir cuando el equipo marca la
     * consulta como resuelta y todavía no hay nota puesta.
     */
    function actualizarPieChat(conversacion) {
        var resuelta = conversacion && (conversacion.estado === 'resuelta' || conversacion.estado === 'cerrada');
        var yaCalificada = conversacion && conversacion.calificacion;
        var mostrarCalif = resuelta && !yaCalificada && !S.califOmitida;

        $('sopFootChat').classList.toggle('d-none', mostrarCalif);
        $('sopCalificar').classList.toggle('d-none', !mostrarCalif);
    }

    function pintarEstrellas(valor) {
        Array.prototype.forEach.call(document.querySelectorAll('.sop-estrella'), function (b) {
            var v = parseInt(b.dataset.valor, 10);
            b.querySelector('i').className = v <= valor ? 'bi bi-star-fill' : 'bi bi-star';
        });
    }

    function enviarCalificacion() {
        if (!S.calificacion) { aviso('Elige de 1 a 5 estrellas.'); return; }

        post('/modulos/soporte-chat/calificarAjax', {
            id: S.idConversacion,
            calificacion: S.calificacion,
            comentario: $('sopComentario').value.trim()
        }).then(function (r) {
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo enviar la calificación.'); return; }
            if (window.Toast) window.Toast.fire({ icon: 'success', title: '¡Gracias por tu opinión!' });
            S.califOmitida = true;
            $('sopCalificar').classList.add('d-none');
            $('sopFootChat').classList.remove('d-none');
            cargarLista();
        });
    }

    function enviarMensaje() {
        var input = $('sopInput');
        var texto = input.value.trim();
        if (!texto) return;

        input.value = '';
        input.style.height = 'auto';
        marcarActividad();

        post('/modulos/soporte-chat/enviarAjax', { id: S.idConversacion, contenido: texto, origen: 'widget' })
            .then(function (r) {
                if (!r || !r.ok) {
                    aviso(r && r.error ? r.error : 'No se pudo enviar el mensaje.');
                    input.value = texto; // no perder lo escrito
                    return;
                }
                return cargarMensajesNuevos();
            })
            .catch(function () {
                aviso('No se pudo enviar el mensaje.');
                input.value = texto;
            });
    }

    // ── Nueva consulta ──────────────────────────────────────────────────────

    function enviarNueva() {
        var mensaje = $('sopNuevoMensaje').value.trim();
        if (!mensaje) { aviso('Escribe tu consulta.'); return; }

        var asunto = $('sopNuevoAsunto').value.trim();
        var btn = $('sopBtnEnviarNueva');
        btn.disabled = true;

        post('/modulos/soporte-chat/abrirAjax', {
            mensaje: mensaje,
            asunto: asunto,
            origen_url: window.location.pathname + window.location.search,
            origen_modulo: window.SOP_MODULO || ''
        })
        .then(function (r) {
            btn.disabled = false;
            if (!r || !r.ok) { aviso(r && r.error ? r.error : 'No se pudo enviar la consulta.'); return; }
            $('sopNuevoMensaje').value = '';
            $('sopNuevoAsunto').value = '';
            abrirConversacion(r.id, asunto || mensaje.substring(0, 60));
        })
        .catch(function () {
            btn.disabled = false;
            aviso('No se pudo enviar la consulta.');
        });
    }

    // ── Apertura / cierre del panel ─────────────────────────────────────────

    function abrirPanel() {
        S.abierto = true;
        $('sopPanel').classList.remove('d-none');
        $('sopLauncherIcon').className = 'bi bi-chevron-down';
        // Detiene el pulso del botón mientras el panel está a la vista (el CSS
        // engancha la animación a .sop-widget:not(.sop-abierto)).
        $('sopWidget').classList.add('sop-abierto');
        marcarActividad();
        mostrarVista('lista');
        cargarLista();
    }

    function cerrarPanel() {
        S.abierto = false;
        $('sopPanel').classList.add('d-none');
        $('sopLauncherIcon').className = 'bi bi-headset';
        $('sopWidget').classList.remove('sop-abierto');
        if (S.timer) { clearTimeout(S.timer); S.timer = null; }

        // Al cerrar puede haber quedado algo sin leer en otra conversación:
        // que el badge se ponga al día sin esperar al ciclo del navbar.
        if (window.CMG_refreshContadores) window.CMG_refreshContadores();
    }

    // ── Arrastre del botón flotante ─────────────────────────────────────────
    // El botón se puede llevar a cualquier punto de la ventana. La posición se
    // guarda como distancia al borde derecho e inferior, igual que el CSS base,
    // para que al cambiar el tamaño de la ventana el botón siga cerca de la
    // esquina donde se dejó y nunca quede fuera de la vista.

    function leerPosicion() {
        try {
            var v = JSON.parse(localStorage.getItem(POS_KEY) || 'null');
            if (v && v.sesion === POS_SESION && isFinite(v.right) && isFinite(v.bottom)) return v;
        } catch (e) { /* almacenamiento bloqueado o dato corrupto: posición por defecto */ }
        return null;
    }

    function guardarPosicion(pos) {
        pos.sesion = POS_SESION;
        try { localStorage.setItem(POS_KEY, JSON.stringify(pos)); } catch (e) { /* sin persistencia */ }
    }

    // ── Quitar la burbuja durante la sesión ─────────────────────────────────
    // Se guarda la huella de la sesión en que se quitó: mientras coincida, la
    // burbuja no se muestra; con el siguiente inicio de sesión vuelve sola.

    function estaOculta() {
        try { return localStorage.getItem(OCULTO_KEY) === POS_SESION; } catch (e) { return false; }
    }

    function ocultarWidget() {
        if (S.abierto) cerrarPanel();
        $('sopWidget').classList.add('d-none');
        try { localStorage.setItem(OCULTO_KEY, POS_SESION); } catch (e) { /* solo en esta página */ }
    }

    // Alto del navbar fijo: el botón no puede subir por encima de esa línea,
    // si no quedaría tapado por el menú. Se mide el header real y, si no está,
    // se usa la variable CSS que mantiene el app-shell.
    function altoNavbar() {
        var h = document.querySelector('.cmg-sticky-header');
        if (h && h.offsetHeight) return h.offsetHeight;
        var v = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--cmg-sticky-h'));
        return isFinite(v) ? v : 0;
    }

    // El panel cuelga del botón: si el botón está a la izquierda, el panel se
    // abre hacia la derecha; si está arriba, se abre hacia abajo. Así nunca
    // queda cortado por el borde de la ventana ni escondido bajo el navbar.
    function ubicarPanel() {
        var widget = $('sopWidget');
        var panel = $('sopPanel');
        var r = widget.getBoundingClientRect();
        var vw = window.innerWidth, vh = window.innerHeight;
        var nav = altoNavbar();
        var panelW = Math.min(370, vw - 36);
        var panelH = Math.min(520, vh - 120) + 68;

        var espIzq = r.right, espDer = vw - r.left;
        widget.classList.toggle('sop-lado-izq', espIzq < panelW + POS_MARGEN && espDer > espIzq);

        var espArriba = r.top - nav, espAbajo = vh - r.bottom;
        var abajo = espArriba < panelH + POS_MARGEN && espAbajo > espArriba;
        widget.classList.toggle('sop-arriba', abajo);

        // Si el lado elegido no da para el alto completo, el panel se acorta en
        // vez de meterse bajo el navbar o salirse por abajo. En móvil el panel
        // ocupa toda la pantalla y no se toca.
        if (vw < 576) {
            panel.style.maxHeight = '';
        } else {
            var disponible = (abajo ? espAbajo : espArriba) - 68 - POS_MARGEN;
            panel.style.maxHeight = Math.max(240, Math.min(vh - 120, disponible)) + 'px';
        }
    }

    function aplicarPosicion(right, bottom) {
        var widget = $('sopWidget');
        var btn = $('sopLauncher');
        var maxRight = Math.max(POS_MARGEN, window.innerWidth - btn.offsetWidth - POS_MARGEN);
        var maxBottom = Math.max(POS_MARGEN, window.innerHeight - btn.offsetHeight - altoNavbar() - POS_MARGEN);
        right = Math.min(Math.max(right, POS_MARGEN), maxRight);
        bottom = Math.min(Math.max(bottom, POS_MARGEN), maxBottom);
        widget.style.right = right + 'px';
        widget.style.bottom = bottom + 'px';
        ubicarPanel();
        return { right: Math.round(right), bottom: Math.round(bottom) };
    }

    function habilitarArrastre() {
        var widget = $('sopWidget');
        var btn = $('sopLauncher');
        var arrastre = null;

        var guardada = leerPosicion();
        if (guardada) aplicarPosicion(guardada.right, guardada.bottom);
        else ubicarPanel();

        btn.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            var r = widget.getBoundingClientRect();
            arrastre = {
                id: e.pointerId,
                x0: e.clientX, y0: e.clientY,
                offX: e.clientX - r.left, offY: e.clientY - r.top,
                movido: false
            };
            try { btn.setPointerCapture(e.pointerId); } catch (err) { /* navegador sin captura */ }
        });

        btn.addEventListener('pointermove', function (e) {
            if (!arrastre || e.pointerId !== arrastre.id) return;
            if (!arrastre.movido) {
                if (Math.abs(e.clientX - arrastre.x0) < POS_UMBRAL && Math.abs(e.clientY - arrastre.y0) < POS_UMBRAL) return;
                arrastre.movido = true;
                widget.classList.add('sop-arrastrando');
            }
            var left = e.clientX - arrastre.offX;
            var top = e.clientY - arrastre.offY;
            aplicarPosicion(window.innerWidth - left - btn.offsetWidth, window.innerHeight - top - btn.offsetHeight);
        });

        function terminar(e) {
            if (!arrastre || e.pointerId !== arrastre.id) return;
            var movido = arrastre.movido;
            arrastre = null;
            widget.classList.remove('sop-arrastrando');
            if (!movido) return;
            // El navegador dispara "click" al soltar: no debe abrir/cerrar el panel.
            S.ignorarClick = true;
            guardarPosicion(aplicarPosicion(parseFloat(widget.style.right), parseFloat(widget.style.bottom)));
        }
        btn.addEventListener('pointerup', terminar);
        btn.addEventListener('pointercancel', terminar);

        // Si la ventana se achica, que el botón no quede fuera de la vista.
        var timerResize = null;
        window.addEventListener('resize', function () {
            clearTimeout(timerResize);
            timerResize = setTimeout(function () {
                var pos = leerPosicion();
                if (pos) aplicarPosicion(pos.right, pos.bottom);
                else ubicarPanel();
            }, 120);
        });
    }

    // ── Arranque ────────────────────────────────────────────────────────────

    function enlazarEventos() {
        $('sopLauncher').addEventListener('click', function () {
            if (S.ignorarClick) { S.ignorarClick = false; return; }
            S.abierto ? cerrarPanel() : abrirPanel();
        });
        $('sopOcultar').addEventListener('click', ocultarWidget);
        $('sopBtnCerrar').addEventListener('click', cerrarPanel);

        $('sopBtnVolver').addEventListener('click', function () {
            S.idConversacion = 0;
            mostrarVista('lista');
            cargarLista();
        });

        $('sopBtnNueva').addEventListener('click', function () {
            // Se avisa de qué se envía además del texto, pero sin la ruta
            // interna del módulo: a quien pregunta no le dice nada.
            $('sopNuevoContexto').textContent = window.SOP_MODULO
                ? 'Junto a tu mensaje enviamos la pantalla desde la que escribes, así no tenemos que preguntártelo.'
                : '';
            mostrarVista('nueva');
            $('sopNuevoMensaje').focus();
        });

        $('sopBtnEnviarNueva').addEventListener('click', enviarNueva);

        $('sopFootChat').addEventListener('submit', function (e) {
            e.preventDefault();
            enviarMensaje();
        });

        // Adjuntos
        $('sopBtnAdjuntar').addEventListener('click', function () { $('sopFile').click(); });
        $('sopFile').addEventListener('change', function () {
            if (this.files && this.files.length) subirArchivo(this.files[0]);
            this.value = '';
        });

        // Calificación
        Array.prototype.forEach.call(document.querySelectorAll('.sop-estrella'), function (b) {
            b.addEventListener('click', function () {
                S.calificacion = parseInt(b.dataset.valor, 10);
                pintarEstrellas(S.calificacion);
            });
        });
        $('sopCalifEnviar').addEventListener('click', enviarCalificacion);
        $('sopCalifOmitir').addEventListener('click', function () {
            S.califOmitida = true;
            $('sopCalificar').classList.add('d-none');
            $('sopFootChat').classList.remove('d-none');
        });

        var input = $('sopInput');
        input.addEventListener('input', function () {
            marcarActividad();
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 90) + 'px';
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                enviarMensaje();
            }
        });

        // Al volver a la pestaña, refrescar de inmediato en vez de esperar turno.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && S.abierto) { marcarActividad(); ciclo(); }
        });
        window.addEventListener('focus', function () {
            if (S.abierto) { marcarActividad(); agendar(); }
        });
    }

    function init() {
        // El partial solo imprime el widget si el chat está activo y desplegado;
        // si no hay nada que enganchar, este script no hace ni una petición.
        if (!$('sopWidget')) return;

        // El usuario la quitó en esta sesión: no se muestra ni se engancha nada.
        if (estaOculta()) { $('sopWidget').classList.add('d-none'); return; }

        S.bienvenida = $('sopSubtitulo').textContent.trim();
        enlazarEventos();
        habilitarArrastre();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
