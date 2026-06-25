'use strict';

/**
 * CaMaGaRe — Login automático en el portal del SRI.
 * Corre en todo srienlinea.sri.gob.ec. Si detecta la pantalla de login y hay una "descarga
 * pendiente" marcada en el sistema, escribe el RUC y la clave de la empresa activa y entra;
 * tras el login, navega a "Comprobantes recibidos". El captcha lo sigue resolviendo el humano.
 */

(function () {
    const COMP_URL = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';

    const enComprobantes = location.href.includes('comprobantes-electronicos-internet');
    const campoUsuario   = document.querySelector('#usuario');
    const campoClave     = document.querySelector('#password');
    const enLogin        = !!(campoUsuario && campoClave);

    // Escribe en un input como si se tecleara (el SRI bloquea pegar, no escribir por JS).
    function escribir(input, valor) {
        input.focus();
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(input, valor);
        input.dispatchEvent(new Event('input',  { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function hacerLogin() {
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            if (chrome.runtime.lastError || !resp || !resp.ok) return; // sin pendiente: no interferir
            if (!campoUsuario || !campoClave) return;
            escribir(campoUsuario, resp.ruc);
            escribir(campoClave, resp.clave);
            // Tras el login hay que ir a Comprobantes recibidos.
            chrome.storage.local.set({ sri_ir_comprobantes: Date.now() }, () => {
                const btn = document.querySelector('#kc-login');
                if (btn) btn.click();
                else { const f = campoUsuario.closest('form'); if (f) f.submit(); }
            });
        });
    }

    async function navegarSiPendiente() {
        const { sri_ir_comprobantes } = await chrome.storage.local.get('sri_ir_comprobantes');
        if (!sri_ir_comprobantes) return;
        // Caducidad de seguridad: 2 min (evita redirecciones viejas).
        if (Date.now() - sri_ir_comprobantes > 120000) { chrome.storage.local.remove('sri_ir_comprobantes'); return; }
        if (enComprobantes) { chrome.storage.local.remove('sri_ir_comprobantes'); return; }
        chrome.storage.local.remove('sri_ir_comprobantes');
        location.href = COMP_URL;
    }

    if (enLogin) hacerLogin();
    else navegarSiPendiente();
})();
