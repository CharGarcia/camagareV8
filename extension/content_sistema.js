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
    if (!d || d.source !== 'cmg-sistema' || !d.token) return;
    chrome.storage.local.set({
        agenteToken: d.token,
        servidorUrl: d.servidorUrl || location.origin,
    });
});
