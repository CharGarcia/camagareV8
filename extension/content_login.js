'use strict';

/**
 * CaMaGaRe — Login automático + salto a comprobantes en el portal del SRI.
 * Si el usuario pulsó "Generar descarga del SRI" (hay descarga marcada):
 *  - En el LOGIN: escribe RUC+clave una vez y entra; deja una marca de un solo uso.
 *  - En la PRIMERA página logueada (p.ej. el perfil): consume la marca y va a comprobantes UNA vez.
 * La marca se borra ANTES de navegar, por lo que es imposible que entre en bucle.
 */

(function () {
    const COMP_URL = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
    const path = location.pathname;
    const enComprobantes = path.includes('comprobantes-electronicos-internet');
    const urlEsLogin = /\/auth\/|\/realms\/|\/openid|\/protocol\//i.test(path);

    if (enComprobantes) { chrome.storage.local.remove('cmg_ir'); return; }

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
            // Marca de UN SOLO USO para saltar a comprobantes desde la primera página logueada.
            chrome.storage.local.set({ cmg_ir: Date.now() }, () => {
                const btn = document.querySelector('#kc-login');
                if (btn) btn.click();
                else { const f = u.closest('form'); if (f) f.submit(); }
            });
        });
    }

    async function irAComprobantes() {
        const { cmg_ir } = await chrome.storage.local.get('cmg_ir');
        // ANTIBUCLE: borrar la marca SIEMPRE antes de decidir/navegar. Así nunca se repite.
        chrome.storage.local.remove('cmg_ir');
        if (!cmg_ir || (Date.now() - cmg_ir > 120000)) return;
        banner('CaMaGaRe: abriendo Comprobantes recibidos…', '#0d6efd');
        location.href = COMP_URL;
    }

    if (urlEsLogin) {
        // Pantalla de login: esperar el formulario (~6s) y llenarlo una vez.
        let intentos = 0;
        const iv = setInterval(() => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) { clearInterval(iv); hacerLlenado(u, p); return; }
            if (++intentos > 20) clearInterval(iv);
        }, 300);
    } else {
        // Página logueada que no es login ni comprobantes (p.ej. el perfil): saltar a comprobantes UNA vez.
        irAComprobantes();
    }
})();
