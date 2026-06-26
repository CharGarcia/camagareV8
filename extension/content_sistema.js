'use strict';

/**
 * CaMaGaRe — auto-configuración del token.
 * Corre dentro del sistema CaMaGaRe. Toma el token del agente del usuario logueado y lo guarda,
 * para que el usuario NO tenga que pegarlo a mano. Lo obtiene de dos formas (la 1ª es robusta):
 *   1) leyendo el elemento #cmg-config que la página deja en el HTML (no depende del timing);
 *   2) escuchando un postMessage (compatibilidad).
 * Si varios usuarios usan el mismo navegador, el token queda el del usuario activo.
 */

(function () {
    function guardar(token, servidorUrl) {
        if (!token) return;
        chrome.storage.local.set({ agenteToken: token, servidorUrl: servidorUrl || location.origin });
    }

    // 1) Elemento en el HTML (robusto). Lo reintenta un par de veces por si carga tarde.
    let intentos = 0;
    const iv = setInterval(() => {
        const el = document.getElementById('cmg-config');
        if (el && el.dataset && el.dataset.token) {
            guardar(el.dataset.token, location.origin);
            clearInterval(iv);
            return;
        }
        if (++intentos > 20) clearInterval(iv); // ~6s
    }, 300);

    // 2) postMessage (compatibilidad).
    window.addEventListener('message', (e) => {
        if (e.source !== window) return;
        const d = e.data;
        if (!d || d.source !== 'cmg-sistema' || !d.token) return;
        guardar(d.token, d.servidorUrl);
    });
})();
