'use strict';

/**
 * CaMaGaRe — Login automático + navegación en el portal del SRI.
 * Corre en todo srienlinea.sri.gob.ec.
 *  - Si está la pantalla de login y hay una "descarga pendiente", escribe RUC+clave y entra.
 *  - Tras el login (ya logueado, fuera de comprobantes), navega solo a "Comprobantes recibidos".
 * El captcha lo sigue resolviendo el humano. Muestra un aviso visible del estado.
 */

(function () {
    const COMP_URL = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
    const enComprobantes = location.href.includes('comprobantes-electronicos-internet');

    // Ya estamos en comprobantes: nada que hacer (limpiar la marca).
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

    // Escribe en el input como si se tecleara (el SRI bloquea pegar, no escribir por JS).
    function escribir(input, valor) {
        input.focus();
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(input, valor);
        input.dispatchEvent(new Event('input',  { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function llenarLogin(u, p) {
        banner('CaMaGaRe: autocompletando el ingreso al SRI…', '#0d6efd');
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            if (chrome.runtime.lastError) {
                banner('CaMaGaRe: recarga la extensión e intenta de nuevo.', '#dc3545');
                return;
            }
            if (!resp || !resp.ok) {
                banner('CaMaGaRe: no hay datos para autocompletar. Verifica el token y pulsa "Generar descarga del SRI".', '#dc3545');
                return;
            }
            escribir(u, resp.ruc);
            escribir(p, resp.clave);
            // Marca para, tras el login, ir solo a Comprobantes recibidos.
            chrome.storage.local.set({ sri_ir_comprobantes: Date.now() });
            banner('CaMaGaRe: ingresando…', '#198754');
            const btn = document.querySelector('#kc-login');
            if (btn) btn.click();
            else { const f = u.closest('form'); if (f) f.submit(); }
        });
    }

    // Flujo principal: detectar login y llenarlo, o (si ya logueado y venimos del flujo) navegar.
    chrome.storage.local.get('sri_ir_comprobantes', ({ sri_ir_comprobantes }) => {
        const hayPendiente = sri_ir_comprobantes && (Date.now() - sri_ir_comprobantes < 300000);
        let intentos = 0;
        const iv = setInterval(() => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) { clearInterval(iv); llenarLogin(u, p); return; }

            intentos++;
            // No es la pantalla de login y veníamos del flujo (ya logueados) → ir a comprobantes.
            if (hayPendiente && intentos >= 3) {
                clearInterval(iv);
                chrome.storage.local.remove('sri_ir_comprobantes');
                banner('CaMaGaRe: abriendo Comprobantes recibidos…', '#0d6efd');
                location.href = COMP_URL;
                return;
            }
            if (intentos > 40) clearInterval(iv); // ~12s: ni login ni pendiente, no hacer nada
        }, 300);
    });
})();
