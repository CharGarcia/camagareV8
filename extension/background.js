'use strict';

/**
 * CaMaGaRe — Descarga SRI (background service worker)
 * Recibe las claves del content script y las envía al sistema (endpoint del agente,
 * autenticado por token). El fetch sale del service worker, que con host_permissions
 * no tiene restricción CORS.
 */

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
    if (msg && msg.tipo === 'registrar') {
        registrar(msg.claves).then(sendResponse);
        return true; // respuesta asíncrona
    }
    if (msg && msg.tipo === 'login_pendiente') {
        pedirLoginPendiente().then(sendResponse);
        return true;
    }
});

// Pide al sistema las credenciales de la empresa marcada como "descarga pendiente".
async function pedirLoginPendiente() {
    try {
        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) return { ok: false };
        const base = (cfg.servidorUrl || 'https://erp.camagare.com.ec').replace(/\/+$/, '');

        const body = new URLSearchParams();
        body.set('agente_token', cfg.agenteToken);

        const resp = await fetch(`${base}/modulos/DescargasSri/agenteLoginPendienteAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
        const data = await resp.json().catch(() => null);
        if (data && data.ok) return { ok: true, ruc: data.ruc, clave: data.clave };
        return { ok: false, error: (data && data.error) || ('Respuesta del servidor HTTP ' + resp.status) };
    } catch (e) {
        return { ok: false, error: 'No se pudo contactar al servidor: ' + e.message };
    }
}

async function registrar(claves) {
    try {
        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) return { ok: false, error: 'Falta configurar el token en la extensión.' };
        const base = (cfg.servidorUrl || 'https://erp.camagare.com.ec').replace(/\/+$/, '');

        const body = new URLSearchParams();
        body.set('agente_token', cfg.agenteToken);
        body.set('claves', JSON.stringify(claves));

        const resp = await fetch(`${base}/modulos/DescargasSri/agenteRegistrarClavesAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });

        const data = await resp.json().catch(() => null);
        if (!data) return { ok: false, error: 'Respuesta inválida del servidor (HTTP ' + resp.status + ').' };
        if (!data.ok) return { ok: false, error: data.error || 'El servidor rechazó la solicitud.' };
        return { ok: true, data };
    } catch (e) {
        return { ok: false, error: e.message };
    }
}
