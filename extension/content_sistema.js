'use strict';

/**
 * CaMaGaRe — auto-configuración del token.
 * Corre dentro del sistema CaMaGaRe. La página publica (postMessage, same-origin) el token del
 * agente del usuario logueado; aquí lo guardamos para que el usuario NO tenga que pegarlo a mano.
 * Si varios usuarios usan el mismo navegador, el token siempre queda el del usuario activo.
 */

window.addEventListener('message', (e) => {
    if (e.source !== window) return;
    const d = e.data;
    if (!d || d.source !== 'cmg-sistema') return;

    // El usuario pulsó "Generar descarga del SRI": marca de flujo activo, para que la extensión
    // solo actúe en el login del SRI durante esta ventana (y no cuando el usuario entra por su cuenta).
    if (d.accion === 'iniciar-descarga') {
        chrome.storage.local.set({ cmg_flujo_activo: Date.now() });
        return;
    }

    // Auto-configuración del token entregado por la página del sistema.
    if (d.token) {
        chrome.storage.local.set({
            agenteToken: d.token,
            servidorUrl: d.servidorUrl || location.origin,
        });
    }
});
