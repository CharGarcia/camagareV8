'use strict';

document.addEventListener('DOMContentLoaded', async () => {
    const $ = (id) => document.getElementById(id);
    const mostrar = (txt, cls) => { const e = $('estado'); e.textContent = txt; e.className = 'estado ' + cls; };

    const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
    $('servidor').value = cfg.servidorUrl || 'https://erp.camagare.com.ec';
    $('token').value    = cfg.agenteToken || '';
    if (cfg.agenteToken) mostrar('Configurado ✓', 'ok');

    $('guardar').addEventListener('click', async () => {
        const servidorUrl = $('servidor').value.trim().replace(/\/+$/, '');
        const agenteToken = $('token').value.trim();
        if (!servidorUrl || !agenteToken) { mostrar('Completa la dirección y el token.', 'err'); return; }
        await chrome.storage.local.set({ servidorUrl, agenteToken });
        mostrar('Guardado ✓', 'ok');
    });
});
