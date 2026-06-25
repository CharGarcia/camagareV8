'use strict';

document.addEventListener('DOMContentLoaded', async () => {
    const $ = (id) => document.getElementById(id);
    const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);

    if (cfg.agenteToken) {
        $('estado').className = 'estado ok';
        $('estado').textContent = '✓ Conectada a tu sistema CaMaGaRe';
        $('ayuda').innerHTML = 'Ya puedes entrar al portal del SRI, consultar tus comprobantes recibidos y pulsar el botón azul <b>«Enviar comprobantes al sistema»</b>.'
            + (cfg.servidorUrl ? '<div class="mini">Servidor: ' + cfg.servidorUrl + '</div>' : '');
    } else {
        $('estado').className = 'estado pend';
        $('estado').textContent = 'Aún no conectada';
        $('ayuda').innerHTML = 'Abre tu sistema <b>CaMaGaRe</b> e ingresa a <b>Descargas SRI</b>: la extensión se conecta sola, sin que configures nada.';
    }

    $('servidor').value = cfg.servidorUrl || 'https://erp.camagare.com.ec';
    $('token').value    = cfg.agenteToken || '';

    $('advLink').addEventListener('click', () => {
        const a = $('adv');
        a.style.display = (a.style.display === 'block') ? 'none' : 'block';
    });

    $('guardar').addEventListener('click', async () => {
        const servidorUrl = $('servidor').value.trim().replace(/\/+$/, '');
        const agenteToken = $('token').value.trim();
        await chrome.storage.local.set({ servidorUrl, agenteToken });
        $('msg').textContent = 'Guardado ✓';
    });
});
