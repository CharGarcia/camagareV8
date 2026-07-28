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
        califOmitida: false      // el usuario pulsó "Ahora no"
    };

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

    // ── Arranque ────────────────────────────────────────────────────────────

    function enlazarEventos() {
        $('sopLauncher').addEventListener('click', function () {
            S.abierto ? cerrarPanel() : abrirPanel();
        });
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

        S.bienvenida = $('sopSubtitulo').textContent.trim();
        enlazarEventos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
