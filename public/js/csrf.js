/**
 * csrf.js — adjunta el token CSRF a toda petición que modifique datos.
 *
 * Se carga en <head> (partials/head.php) ANTES que cualquier otro script, para
 * que ningún fetch se escape sin token. Envuelve fetch y XMLHttpRequest y añade
 * un campo oculto a los formularios: los ~80 módulos no necesitan cambio alguno.
 *
 * REGLA CRÍTICA: el token solo viaja al propio origen. Mandarlo a un dominio
 * externo (SRI, Payphone, CDNs, api.qrserver.com) lo entregaría a un tercero y
 * anularía la protección.
 */
(function () {
    'use strict';

    var TOKEN = window.CSRF_TOKEN || '';
    var CABECERA = 'X-CSRF-Token';
    var CAMPO = 'csrf_token';
    var METODOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    if (!TOKEN) { return; }   // páginas públicas sin sesión: nada que proteger

    /** ¿La URL apunta a este mismo sitio? Las relativas siempre sí. */
    function esMismoOrigen(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;   // ante la duda, no se envía el token
        }
    }

    function esMetodoProtegido(metodo) {
        return METODOS.indexOf(String(metodo || 'GET').toUpperCase()) !== -1;
    }

    // ── fetch ────────────────────────────────────────────────────────────────
    var fetchOriginal = window.fetch;
    if (typeof fetchOriginal === 'function') {
        window.fetch = function (entrada, opciones) {
            try {
                opciones = opciones || {};
                var url = (entrada && entrada.url) ? entrada.url : entrada;
                var metodo = opciones.method || (entrada && entrada.method) || 'GET';

                if (esMetodoProtegido(metodo) && esMismoOrigen(url)) {
                    // Headers puede llegar como objeto, array o instancia Headers.
                    if (opciones.headers instanceof Headers) {
                        opciones.headers.set(CABECERA, TOKEN);
                    } else if (Array.isArray(opciones.headers)) {
                        opciones.headers.push([CABECERA, TOKEN]);
                    } else {
                        opciones.headers = Object.assign({}, opciones.headers || {});
                        opciones.headers[CABECERA] = TOKEN;
                    }
                }
            } catch (e) {
                /* Si algo falla, la petición sigue su curso sin token: se
                   preferirá un rechazo visible antes que romper la página. */
            }
            return fetchOriginal.apply(this, [entrada, opciones]).then(function (respuesta) {
                if (respuesta && respuesta.status === 419) { avisarSesionExpirada(); }
                return respuesta;
            });
        };
    }

    // ── XMLHttpRequest ───────────────────────────────────────────────────────
    var openOriginal = XMLHttpRequest.prototype.open;
    var sendOriginal = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (metodo, url) {
        this.__csrfProtegido = esMetodoProtegido(metodo) && esMismoOrigen(url);
        return openOriginal.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
        try {
            if (this.__csrfProtegido) {
                this.setRequestHeader(CABECERA, TOKEN);
            }
            this.addEventListener('load', function () {
                if (this.status === 419) { avisarSesionExpirada(); }
            });
        } catch (e) { /* no bloquear el envío */ }
        return sendOriginal.apply(this, arguments);
    };

    // ── Formularios ──────────────────────────────────────────────────────────
    /** Añade (una sola vez) el campo oculto a un formulario del propio sitio. */
    function prepararFormulario(form) {
        try {
            if (!form || form.__csrfListo) { return; }
            if (!esMetodoProtegido(form.method)) { return; }

            // form.action resuelto: si apunta fuera, no se toca.
            if (!esMismoOrigen(form.action || window.location.href)) { return; }

            var existente = form.querySelector('input[name="' + CAMPO + '"]');
            if (existente) {
                existente.value = TOKEN;      // el de factura-express venía vacío
            } else {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = CAMPO;
                input.value = TOKEN;
                form.appendChild(input);
            }
            form.__csrfListo = true;
        } catch (e) { /* no impedir el envío */ }
    }

    function prepararTodos() {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) { prepararFormulario(forms[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararTodos);
    } else {
        prepararTodos();
    }

    // Formularios creados por JS después de cargar (modales, plantillas). Se usa
    // la fase de captura para llegar antes que cualquier handler del módulo.
    document.addEventListener('submit', function (ev) {
        if (ev.target && ev.target.tagName === 'FORM') { prepararFormulario(ev.target); }
    }, true);

    // ── Aviso al usuario ─────────────────────────────────────────────────────
    var avisoMostrado = false;
    function avisarSesionExpirada() {
        if (avisoMostrado) { return; }
        avisoMostrado = true;

        var barra = document.createElement('div');
        barra.setAttribute('role', 'alert');
        barra.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;'
            + 'background:#dc3545;color:#fff;padding:12px 16px;text-align:center;'
            + 'font-family:system-ui,sans-serif;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.2)';
        barra.innerHTML = 'Su sesión expiró o la página estuvo abierta demasiado tiempo. '
            + '<a href="javascript:location.reload()" style="color:#fff;text-decoration:underline">'
            + 'Recargar la página</a> para continuar.';
        document.body.appendChild(barra);
    }
})();
