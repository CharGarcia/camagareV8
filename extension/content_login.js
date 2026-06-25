'use strict';

/**
 * CaMaGaRe — Login automático + navegación en el portal del SRI.
 * Solo actúa si el usuario pulsó "Generar descarga del SRI" (hay una descarga marcada en el
 * servidor). Si no hay nada marcado, no interfiere con el uso normal del portal.
 *  - En el LOGIN con formulario: escribe RUC+clave y entra.
 *  - Tras el login (o si ya había sesión): navega solo a "Comprobantes recibidos".
 * El captcha lo sigue resolviendo el humano.
 */

(function () {
    const COMP_URL = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
    const url = location.href;
    const enComprobantes = url.includes('comprobantes-electronicos-internet');
    const urlEsLogin = /\/auth\/|\/realms\/|\/openid|\/protocol\//i.test(url);

    try { console.log('[CaMaGaRe v' + chrome.runtime.getManifest().version + '] url=' + url + ' | esLogin=' + urlEsLogin + ' | enComprobantes=' + enComprobantes); } catch (e) {}

    if (enComprobantes) { chrome.storage.local.remove('sri_ir_comprobantes'); return; }

    function banner(texto, color) {
        let b = document.getElementById('cmg-login-banner');
        if (!b) {
            b = document.createElement('div');
            b.id = 'cmg-login-banner';
            Object.assign(b.style, {
                position: 'fixed', top: '0', left: '0', right: '0', zIndex: '2147483647',
                background: color || '#0d6efd', color: '#fff', padding: '10px 14px',
                fontFamily: 'Arial, sans-serif', fontSize: '15px', fontWeight: 'bold', textAlign: 'center',
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

    // Hay formulario de login: pedir credenciales al servidor. Solo escribe si hay descarga marcada.
    function hacerLlenado(u, p) {
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            if (chrome.runtime.lastError) return;
            if (!resp || !resp.ok) {
                // Sin descarga marcada = uso normal del SRI, no interferir. Otros errores (token,
                // credenciales) sí los avisamos para que el usuario los corrija.
                if (resp && resp.error && !/pendiente/i.test(resp.error)) {
                    banner('CaMaGaRe: ' + resp.error, '#dc3545');
                }
                return;
            }
            banner('CaMaGaRe: ingresando al SRI…', '#198754');
            escribir(u, resp.ruc);
            escribir(p, resp.clave);
            // Persistir la marca ANTES de enviar el login (si no, la navegación corta el guardado).
            chrome.storage.local.set({ sri_ir_comprobantes: Date.now() }, () => {
                const btn = document.querySelector('#kc-login');
                if (btn) btn.click();
                else { const f = u.closest('form'); if (f) f.submit(); }
            });
        });
    }

    // Espera el formulario de login. Si aparece, lo llena; si no en ~6s (ya hay sesión), navega.
    function esperarYLlenar() {
        let intentos = 0;
        const iv = setInterval(() => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) { clearInterval(iv); hacerLlenado(u, p); return; }
            if (++intentos > 20) { clearInterval(iv); navegarSiPendiente(); }
        }, 300);
    }

    // Ir a Comprobantes recibidos SOLO si hay marca local (tras el login) o el servidor confirma
    // que el usuario pulsó "Generar descarga del SRI". Si no, no hace nada (uso normal).
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

    if (urlEsLogin || document.querySelector('#usuario')) {
        esperarYLlenar();
    } else {
        // Quizá es inicio (post-login) o un login que aún no renderiza. Dar margen y decidir.
        let intentos = 0;
        const iv = setInterval(() => {
            if (document.querySelector('#usuario')) { clearInterval(iv); esperarYLlenar(); return; }
            if (++intentos >= 6) { clearInterval(iv); navegarSiPendiente(); }
        }, 300);
    }
})();
