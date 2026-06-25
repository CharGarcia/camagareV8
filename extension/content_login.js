'use strict';

/**
 * CaMaGaRe — Login automático + navegación en el portal del SRI.
 *  - En la pantalla de LOGIN (URL de Keycloak /auth/realms o con campos #usuario/#password):
 *    si hay una "descarga pendiente", escribe RUC+clave y entra. Aquí NUNCA navega.
 *  - En otra página ya logueado (no login, no comprobantes): si venimos del flujo, va solo a
 *    "Comprobantes recibidos".
 * El captcha lo sigue resolviendo el humano. Muestra un aviso visible del estado.
 */

(function () {
    const COMP_URL = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
    const url = location.href;
    const enComprobantes = url.includes('comprobantes-electronicos-internet');
    const urlEsLogin = /\/auth\/|\/realms\/|\/openid|\/protocol\//i.test(url);

    if (enComprobantes) { chrome.storage.local.remove('sri_ir_comprobantes'); return; }

    function banner(texto, color) {
        let b = document.getElementById('cmg-login-banner');
        if (!b) {
            b = document.createElement('div');
            b.id = 'cmg-login-banner';
            Object.assign(b.style, {
                position: 'fixed', top: '0', left: '0', right: '0', zIndex: '2147483647',
                background: color || '#0d6efd', color: '#fff', padding: '8px 14px',
                fontFamily: 'Arial, sans-serif', fontSize: '14px', textAlign: 'center',
            });
            (document.body || document.documentElement).appendChild(b);
        }
        b.style.background = color || '#0d6efd';
        b.textContent = texto;
    }

    // Escribe tecleando carácter por carácter (el SRI bloquea pegar; algunos campos validan el tecleo).
    function escribir(input, valor) {
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        input.focus();
        setter.call(input, '');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        for (const ch of String(valor)) {
            input.dispatchEvent(new KeyboardEvent('keydown', { key: ch, bubbles: true }));
            setter.call(input, input.value + ch);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keyup', { key: ch, bubbles: true }));
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.blur();
    }

    function hacerLlenado(u, p) {
        banner('CaMaGaRe: autocompletando el ingreso al SRI…', '#0d6efd');
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            if (chrome.runtime.lastError) {
                banner('CaMaGaRe: recarga la extensión e intenta de nuevo.', '#dc3545');
                return;
            }
            if (!resp || !resp.ok) {
                banner('CaMaGaRe: ' + ((resp && resp.error) || 'no se pudo autocompletar') + '  ·  (revisa el token o pulsa "Generar descarga del SRI")', '#dc3545');
                return;
            }
            escribir(u, resp.ruc);
            escribir(p, resp.clave);
            chrome.storage.local.set({ sri_ir_comprobantes: Date.now() }); // tras el login, ir a comprobantes
            banner('CaMaGaRe: ingresando…', '#198754');
            const btn = document.querySelector('#kc-login');
            if (btn) btn.click();
            else { const f = u.closest('form'); if (f) f.submit(); }
        });
    }

    // Espera a que aparezca el formulario de login y lo llena. NUNCA navega.
    function esperarYLlenar() {
        let intentos = 0;
        const iv = setInterval(() => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) { clearInterval(iv); hacerLlenado(u, p); return; }
            if (++intentos > 40) {
                clearInterval(iv);
                banner('CaMaGaRe: no encontré los campos de usuario/clave en esta página.', '#dc3545');
            }
        }, 300);
    }

    // Ya logueado, fuera de comprobantes: si venimos del flujo, ir a comprobantes.
    async function navegarSiPendiente() {
        const { sri_ir_comprobantes } = await chrome.storage.local.get('sri_ir_comprobantes');
        if (!sri_ir_comprobantes) return;
        if (Date.now() - sri_ir_comprobantes > 300000) { chrome.storage.local.remove('sri_ir_comprobantes'); return; }
        chrome.storage.local.remove('sri_ir_comprobantes');
        banner('CaMaGaRe: abriendo Comprobantes recibidos…', '#0d6efd');
        location.href = COMP_URL;
    }

    if (urlEsLogin || document.querySelector('#usuario')) {
        banner('CaMaGaRe activo: detectando el formulario de ingreso…', '#6c757d');
        esperarYLlenar();
    } else {
        // Quizá es inicio (post-login) o un login que aún no renderiza. Dar margen y decidir.
        let intentos = 0;
        const iv = setInterval(() => {
            if (document.querySelector('#usuario')) { clearInterval(iv); esperarYLlenar(); return; }
            if (++intentos >= 6) { clearInterval(iv); navegarSiPendiente(); } // ~1.8s sin form → no es login
        }, 300);
    }
})();
