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

    // Ventana para considerar "reciente" un intento de login previo (igual a la del servidor).
    const TTL_INTENTO_LOGIN = 5 * 60 * 1000;

    // Busca un mensaje de error visible en la pantalla de login (Keycloak/SRI).
    function textoErrorLogin() {
        const selectores = ['#kc-feedback-text', '#input-error', '#input-error-username',
            '#input-error-password', '.alert-error', '.pf-c-alert__title', '[role="alert"]'];
        for (const sel of selectores) {
            const el = document.querySelector(sel);
            const t = el && el.textContent && el.textContent.trim();
            if (t) return t;
        }
        return '';
    }

    // Si ya se intentó un login automático hace poco y volvimos a caer en esta misma
    // pantalla con los campos otra vez, es porque el SRI rechazó el RUC/clave.
    async function yaFalloLoginReciente() {
        const { cmg_login_intento } = await chrome.storage.local.get('cmg_login_intento');
        if (!cmg_login_intento) return false;
        if (Date.now() - cmg_login_intento > TTL_INTENTO_LOGIN) {
            chrome.storage.local.remove('cmg_login_intento');
            return false;
        }
        return true;
    }

    let yaLleno = false;
    function hacerLlenado(u, p) {
        if (yaLleno) return;
        yaLleno = true;
        chrome.runtime.sendMessage({ tipo: 'login_pendiente' }, (resp) => {
            try { console.log('[CaMaGaRe] login_pendiente →', JSON.stringify(resp)); } catch (e) {}
            if (chrome.runtime.lastError) return;
            // Sin descarga marcada (o error): no interferir con el uso normal del SRI.
            if (!resp || !resp.ok) return;
            banner('CaMaGaRe: ingresando al SRI…', '#198754');
            escribir(u, resp.ruc);
            escribir(p, resp.clave);
            // Marca de UN SOLO USO para saltar a comprobantes desde la primera página logueada,
            // y marca de "ya se intentó" para detectar en la próxima carga si el login falló.
            chrome.storage.local.set({ cmg_ir: Date.now(), cmg_login_intento: Date.now() }, () => {
                const btn = document.querySelector('#kc-login');
                if (btn) btn.click();
                else { const f = u.closest('form'); if (f) f.submit(); }
            });
        });
    }

    async function irAComprobantes() {
        const { cmg_ir } = await chrome.storage.local.get('cmg_ir');
        // ANTIBUCLE: borrar la marca SIEMPRE antes de actuar. Así nunca se repite.
        chrome.storage.local.remove('cmg_ir');
        if (!cmg_ir || (Date.now() - cmg_ir > 120000)) return;
        banner('CaMaGaRe: abriendo Comprobantes recibidos…', '#0d6efd');
        // El acceso directo a la URL rebota al perfil; hay que entrar POR EL MENÚ:
        // expandir "Facturación Electrónica" y pulsar "Comprobantes recibidos" (redireccion=57).
        let intentos = 0;
        let expandido = false;
        const iv = setInterval(() => {
            intentos++;
            const link = document.querySelector('a[href*="redireccion=57"]');
            if (link) { clearInterval(iv); link.click(); return; }
            if (!expandido) {
                const icono = document.querySelector('.sri-menu-icon-facturacion-electronica');
                const header = icono && icono.closest('a.ui-panelmenu-header-link');
                if (header) { header.click(); expandido = true; }
            }
            if (intentos > 40) clearInterval(iv); // ~12s sin menú: parar (sin bucle)
        }, 300);
    }

    if (urlEsLogin) {
        // Pantalla de login: esperar el formulario (~6s).
        let intentos = 0;
        const iv = setInterval(async () => {
            const u = document.querySelector('#usuario');
            const p = document.querySelector('#password');
            if (u && p) {
                clearInterval(iv);
                // Si ya hubo un intento automático reciente y volvimos a la pantalla de login,
                // el RUC/clave fue rechazado: avisar y NO reintentar (ni volver a llenar el formulario).
                if (await yaFalloLoginReciente()) {
                    chrome.storage.local.remove(['cmg_login_intento', 'cmg_ir']);
                    const detalle = textoErrorLogin();
                    banner('CaMaGaRe: el RUC o la clave del SRI son incorrectos. Corrígela en el sistema (Descargas SRI) y vuelve a pulsar "Generar descarga".'
                        + (detalle ? ' — ' + detalle : ''), '#dc3545');
                    return;
                }
                hacerLlenado(u, p);
                return;
            }
            if (++intentos > 20) clearInterval(iv);
        }, 300);
    } else {
        // Llegamos a una página logueada (no login): el intento previo, si lo hubo, funcionó.
        chrome.storage.local.remove('cmg_login_intento');
        // Página logueada que no es comprobantes (p.ej. el perfil): saltar a comprobantes UNA vez.
        irAComprobantes();
    }
})();
