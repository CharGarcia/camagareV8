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

    console.log('[CaMaGaRe] content_login cargado:', { url, enComprobantes, urlEsLogin });

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
            // Persistir la marca ANTES de enviar el login: si no, la navegación corta el guardado
            // y luego la extensión no sabe que debe ir a Comprobantes recibidos.
            chrome.storage.local.set({ sri_ir_comprobantes: Date.now() }, () => {
                banner('CaMaGaRe: ingresando…', '#198754');
                const btn = document.querySelector('#kc-login');
                if (btn) btn.click();
                else { const f = u.closest('form'); if (f) f.submit(); }
            });
        });
    }

    // Espera el formulario de login y lo llena. Si no aparece en ~6s, probablemente ya hay
    // sesión activa (login sin formulario): entonces intenta seguir a Comprobantes recibidos.
    function esperarYLlenar() {
        let intentos = 0;
        const iv = setInterval(() => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) { clearInterval(iv); hacerLlenado(u, p); return; }
            if (++intentos > 20) { clearInterval(iv); navegarSiPendiente(); }
        }, 300);
    }

    // Ir a Comprobantes recibidos si hay marca local (tras el login) o si el servidor confirma
    // que el usuario pulsó "Generar descarga del SRI" (caso de sesión ya activa, sin pasar el login).
    async function navegarSiPendiente() {
        const { sri_ir_comprobantes } = await chrome.storage.local.get('sri_ir_comprobantes');
        let ir = sri_ir_comprobantes && (Date.now() - sri_ir_comprobantes < 300000);
        if (!ir) {
            const resp = await new Promise((r) => {
                try { chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, r); } catch (e) { r(null); }
            });
            ir = !!(resp && resp.ok);
        }
        chrome.storage.local.remove('sri_ir_comprobantes');
        if (!ir) return;
        banner('CaMaGaRe: abriendo Comprobantes recibidos…', '#0d6efd');
        location.href = COMP_URL;
    }

    // Aviso incondicional: si ves esta franja, el script SÍ corre en esta página.
    banner('CaMaGaRe activo: detectando el formulario de ingreso…', '#6c757d');

    if (urlEsLogin || document.querySelector('#usuario')) {
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
