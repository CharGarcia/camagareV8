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
    if (msg && msg.tipo === 'registrarXmls') {
        registrarXmls(msg.items).then(sendResponse);
        return true;
    }
    if (msg && msg.tipo === 'clavesPendientes') {
        clavesPendientes(msg.claves).then(sendResponse);
        return true;
    }
    if (msg && msg.tipo === 'login_pendiente') {
        pedirLoginPendiente().then(sendResponse);
        return true;
    }
    if (msg && msg.tipo === 'cerrar_sesion') {
        cerrarSesion().then(sendResponse);
        return true;
    }
});

// Cierra la sesión del SRI eliminando sus cookies (igual que el scraper forzaba un login limpio).
async function cerrarSesion() {
    try {
        const cookies = await chrome.cookies.getAll({ domain: 'sri.gob.ec' });
        for (const c of cookies) {
            const host = c.domain.startsWith('.') ? c.domain.slice(1) : c.domain;
            const url = (c.secure ? 'https://' : 'http://') + host + (c.path || '/');
            try { await chrome.cookies.remove({ url, name: c.name }); } catch (e) {}
        }
        return { ok: true };
    } catch (e) {
        return { ok: false, error: e.message };
    }
}

// Pide al sistema las credenciales de la empresa marcada como "descarga pendiente".
async function pedirLoginPendiente() {
    try {
        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) return { ok: false };
        const base = (cfg.servidorUrl || 'https://erp.camagare.com.ec').replace(/\/+$/, '');

        const body = new URLSearchParams();
        body.set('agente_token', cfg.agenteToken);

        const resp = await fetch(`${base}/modulos/descargas_sri/agenteLoginPendienteAjax`, {
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

/**
 * Pregunta al sistema cuáles de estas claves faltan por registrar, para bajar
 * del portal solo el XML de esas. Si falla, devuelve null y quien llama decide
 * (se descargan todas, como antes) para no perder comprobantes por un error de red.
 */
async function clavesPendientes(claves) {
    try {
        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) return { ok: false, error: 'Falta configurar el token en la extensión.' };
        const base = (cfg.servidorUrl || 'https://erp.camagare.com.ec').replace(/\/+$/, '');

        const body = new URLSearchParams();
        body.set('agente_token', cfg.agenteToken);
        body.set('claves', JSON.stringify(claves));

        const resp = await fetch(`${base}/modulos/descargas_sri/agenteClavesPendientesAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
        const data = await resp.json().catch(() => null);
        if (!data || !data.ok) return { ok: false, error: (data && data.error) || 'Respuesta inválida.' };
        return { ok: true, pendientes: data.pendientes || [] };
    } catch (e) {
        return { ok: false, error: e.message };
    }
}

/**
 * Envía los comprobantes con su XML ya descargado del portal. Va por lotes porque
 * los XML pesan y una sola petición con cientos de comprobantes se pasaría de los
 * límites de POST del servidor. Los totales de cada lote se acumulan.
 */
async function registrarXmls(items) {
    try {
        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) return { ok: false, error: 'Falta configurar el token en la extensión.' };
        const base = (cfg.servidorUrl || 'https://erp.camagare.com.ec').replace(/\/+$/, '');

        // Lotes acotados por cantidad y por tamaño (~3 MB de XML por petición).
        const MAX_ITEMS = 15;
        const MAX_BYTES = 3 * 1024 * 1024;
        const lotes = [];
        let actual = [];
        let bytes = 0;
        for (const it of items) {
            const size = (it.xml || '').length;
            if (actual.length && (actual.length >= MAX_ITEMS || bytes + size > MAX_BYTES)) {
                lotes.push(actual);
                actual = [];
                bytes = 0;
            }
            actual.push(it);
            bytes += size;
        }
        if (actual.length) lotes.push(actual);

        const total = {
            total_encontrados: 0, total_nuevos: 0, total_existentes: 0,
            total_ignorados: 0, total_errores: 0,
        };

        for (const lote of lotes) {
            const body = new URLSearchParams();
            body.set('agente_token', cfg.agenteToken);
            body.set('xmls', JSON.stringify(lote));

            const resp = await fetch(`${base}/modulos/descargas_sri/agenteRegistrarXmlsAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            });

            const data = await resp.json().catch(() => null);
            if (!data) return { ok: false, error: 'Respuesta inválida del servidor (HTTP ' + resp.status + ').' };
            if (!data.ok) return { ok: false, error: data.error || 'El servidor rechazó la solicitud.' };

            for (const k of Object.keys(total)) total[k] += (data[k] || 0);
        }

        return { ok: true, data: total };
    } catch (e) {
        return { ok: false, error: e.message };
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

        const resp = await fetch(`${base}/modulos/descargas_sri/agenteRegistrarClavesAjax`, {
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
