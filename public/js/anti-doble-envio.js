/**
 * anti-doble-envio.js — un doble clic no debe guardar dos registros.
 *
 * Se carga en partials/csrf.php (layout estándar Y vistas standalone: POS, comandas, KDS,
 * mesas), así que cubre todos los módulos sin cambiar ninguno:
 *
 *  1. fetch: si llega una petición que modifica datos (POST/PUT/PATCH/DELETE) al propio sitio
 *     y ya hay en curso otra IDÉNTICA (mismo método, URL y cuerpo), no se envía de nuevo: el
 *     segundo llamador recibe la MISMA respuesta que el primero (cada uno su copia). Eso es lo
 *     que produce un doble clic o un Enter repetido sobre un botón que el módulo no alcanzó a
 *     desactivar. Una petición con cuerpo distinto (el usuario cambió algo) sí se envía.
 *  2. Formularios con envío nativo (recargan la página): el segundo submit mientras el primero
 *     sigue en camino se ignora.
 *
 * EXCEPCIÓN: cuando repetir es intencional (p. ej. tocar dos veces un producto en Comandas =
 * dos unidades), el llamador agrega la cabecera `X-Permitir-Repetido: 1` y la petición se
 * envía siempre.
 *
 * NO cubre el reintento después de que se perdió la respuesta (el primer envío ya terminó):
 * eso solo lo resuelve el servidor con una clave de formulario (ver token_guardado en
 * Consignaciones de venta).
 */
(function () {
    'use strict';

    /**
     * Clave de un formulario de documento NUEVO para el guardado único del servidor
     * (App\Services\GuardadoUnicoService, CLAUDE.md §8): se genera al abrir el formulario,
     * viaja como `token_guardado` en todos sus intentos de guardado y se renueva solo al abrir
     * otro documento nuevo.
     */
    window.CMG_nuevoTokenGuardado = function () {
        try { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); } catch (e) {}
        return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    };

    var METODOS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    var CABECERA_EXCEPCION = 'x-permitir-repetido';

    /** ¿El llamador pidió explícitamente que se envíe aunque sea idéntica a otra en curso? */
    function permiteRepetido(headers) {
        if (!headers) return false;
        try {
            if (typeof Headers !== 'undefined' && headers instanceof Headers) return headers.has(CABECERA_EXCEPCION);
            if (Array.isArray(headers)) {
                return headers.some(function (h) { return String(h[0]).toLowerCase() === CABECERA_EXCEPCION; });
            }
            return Object.keys(headers).some(function (k) { return k.toLowerCase() === CABECERA_EXCEPCION; });
        } catch (e) { return false; }
    }

    function esMismoOrigen(url) {
        try { return new URL(url, window.location.href).origin === window.location.origin; }
        catch (e) { return false; }
    }

    /** Huella del cuerpo; null si no se puede calcular de forma fiable (entonces no se deduplica). */
    function huellaCuerpo(cuerpo) {
        if (cuerpo === undefined || cuerpo === null) return '';
        if (typeof cuerpo === 'string') return cuerpo;
        if (typeof URLSearchParams !== 'undefined' && cuerpo instanceof URLSearchParams) return cuerpo.toString();
        if (typeof FormData !== 'undefined' && cuerpo instanceof FormData) {
            var partes = [];
            cuerpo.forEach(function (valor, clave) {
                if (typeof File !== 'undefined' && valor instanceof File) {
                    partes.push(clave + '=[archivo:' + valor.name + ':' + valor.size + ':' + valor.lastModified + ']');
                } else {
                    partes.push(clave + '=' + String(valor));
                }
            });
            return partes.join('&');
        }
        return null; // Blob, ArrayBuffer, streams: no se comparan
    }

    // ── 1. fetch ─────────────────────────────────────────────────────────────
    var fetchPrevio = window.fetch;
    if (typeof fetchPrevio === 'function') {
        var enCurso = {}; // huella → Promise<Response> original (nunca se lee su cuerpo)

        window.fetch = function (entrada, opciones) {
            var clave = null;
            try {
                var opts = opciones || {};
                var esRequest = (typeof Request !== 'undefined') && (entrada instanceof Request);
                var url = esRequest ? entrada.url : String(entrada);
                var metodo = String(opts.method || (esRequest ? entrada.method : 'GET')).toUpperCase();
                // Con Request como entrada el cuerpo no es legible sin consumirlo; con signal, abortar
                // la primera abortaría también la segunda. En esos casos no se toca nada.
                if (!esRequest && !opts.signal && !permiteRepetido(opts.headers)
                    && METODOS.indexOf(metodo) !== -1 && esMismoOrigen(url)) {
                    var huella = huellaCuerpo(opts.body);
                    if (huella !== null) {
                        clave = metodo + ' ' + new URL(url, window.location.href).href + '\n' + huella;
                    }
                }
            } catch (e) { clave = null; }

            if (clave === null) {
                return fetchPrevio.apply(this, arguments);
            }

            if (!enCurso[clave]) {
                var original = fetchPrevio.apply(this, arguments);
                enCurso[clave] = original;
                var limpiar = function () { if (enCurso[clave] === original) delete enCurso[clave]; };
                original.then(limpiar, limpiar);
            } else if (window.console && console.info) {
                console.info('[anti-doble-envio] Petición repetida mientras la primera seguía en curso; se reutiliza su respuesta.');
            }
            // Cada llamador recibe su propia copia: el cuerpo de una Response solo se lee una vez.
            return enCurso[clave].then(function (r) { return r.clone(); });
        };
    }

    // ── 2. Formularios con envío nativo ──────────────────────────────────────
    // Fase de burbuja en window: corre DESPUÉS de los handlers del módulo. Si alguno hizo
    // preventDefault (envío por fetch), no es un envío nativo y no se marca nada.
    var ESPERA_MS = 10000; // descargas (PDF/Excel por POST) no recargan: se libera solo
    window.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM' || ev.defaultPrevented) return;
        if (METODOS.indexOf(String(form.method || 'GET').toUpperCase()) === -1) return;
        var destino = (ev.submitter && ev.submitter.getAttribute('formtarget')) || form.target || '';
        if (destino && destino !== '_self') return; // abre en otra pestaña: no bloquear

        if (form.__envioNativoEnCurso) {
            ev.preventDefault();
            return;
        }
        form.__envioNativoEnCurso = true;
        setTimeout(function () { form.__envioNativoEnCurso = false; }, ESPERA_MS);
    }, false);

    // Al volver con "Atrás" (página restaurada de caché) los formularios quedan libres.
    window.addEventListener('pageshow', function () {
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) { forms[i].__envioNativoEnCurso = false; }
    });
})();
