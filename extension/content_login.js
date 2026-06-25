'use strict';

/**
 * CaMaGaRe — Login automático en el portal del SRI (comportamiento mínimo y seguro).
 * Si el usuario pulsó "Generar descarga del SRI" (hay una descarga marcada), y aparece el
 * formulario de login, escribe el RUC + clave UNA vez y envía. Nada más: NO fuerza navegación
 * (eso lo hace el propio SRI con su redirect), para que nunca pueda entrar en bucle.
 * El captcha y la consulta los hace el humano.
 */

(function () {
    const path = location.pathname;
    const enComprobantes = path.includes('comprobantes-electronicos-internet');
    const urlEsLogin = /\/auth\/|\/realms\/|\/openid|\/protocol\//i.test(path);

    // En comprobantes (o cualquier página que no sea login) no hacemos nada.
    if (enComprobantes || !urlEsLogin) return;

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

    // Llena un login a lo sumo UNA vez por carga de página (anti-bucle).
    let yaLleno = false;
    function hacerLlenado(u, p) {
        if (yaLleno) return;
        yaLleno = true;
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            if (chrome.runtime.lastError) return;
            if (!resp || !resp.ok) {
                // Sin descarga marcada = uso normal del SRI, no interferir. Otros errores sí se avisan.
                if (resp && resp.error && !/pendiente/i.test(resp.error)) {
                    banner('CaMaGaRe: ' + resp.error, '#dc3545');
                }
                return;
            }
            banner('CaMaGaRe: ingresando al SRI…', '#198754');
            escribir(u, resp.ruc);
            escribir(p, resp.clave);
            const btn = document.querySelector('#kc-login');
            if (btn) btn.click();
            else { const f = u.closest('form'); if (f) f.submit(); }
        });
    }

    // Espera el formulario de login (hasta ~6s) y lo llena. Si no aparece, no hace nada.
    let intentos = 0;
    const iv = setInterval(() => {
        const u = document.querySelector('#usuario');
        const p = document.querySelector('#password');
        if (u && p) { clearInterval(iv); hacerLlenado(u, p); return; }
        if (++intentos > 20) clearInterval(iv);
    }, 300);
})();
