'use strict';

/**
 * CaMaGaRe — Descarga SRI (content script)
 * Corre dentro del portal de "Comprobantes electrónicos recibidos" del SRI.
 * Cuando hay resultados, muestra un botón flotante; al pulsarlo recolecta las
 * claves de acceso de TODAS las páginas y las envía al sistema (vía background).
 */

(function () {
    const SEL_TBODY = '[id$="tablaCompRecibidos_data"]';
    let enviando = false;

    const pausa = (ms) => new Promise((r) => setTimeout(r, ms));

    // Extrae las claves de la página visible. La clave vive en su propia celda con
    // 49 dígitos exactos; así no se pegan dígitos de otras columnas.
    function clavesDePaginaActual() {
        const tbody = document.querySelector(SEL_TBODY);
        if (!tbody) return [];
        const out = [];
        tbody.querySelectorAll('tr[data-ri]').forEach((row) => {
            let clave = null;
            for (const td of row.querySelectorAll('td')) {
                const soloDig = (td.textContent || '').replace(/\D/g, '');
                if (soloDig.length === 49) { clave = soloDig; break; }
            }
            if (!clave) {
                const m = (row.textContent || '').match(/(?<!\d)\d{49}(?!\d)/);
                if (m) clave = m[0];
            }
            if (clave) out.push(clave);
        });
        return out;
    }

    function hayResultados() {
        return clavesDePaginaActual().length > 0;
    }

    // Recorre todas las páginas del listado y junta las claves (sin duplicar).
    async function recolectarTodas() {
        const set = new Set();
        let vueltas = 0;
        while (vueltas < 60) {
            clavesDePaginaActual().forEach((c) => set.add(c));
            const next = document.querySelector('.ui-paginator-next');
            if (!next || next.classList.contains('ui-state-disabled')) break;
            const antes = document.querySelector('tr[data-ri]')?.textContent || '';
            next.click();
            let espera = 0;
            while (espera < 25) {
                await pausa(400);
                const ahora = document.querySelector('tr[data-ri]')?.textContent || '';
                if (ahora !== antes) break;
                espera++;
            }
            vueltas++;
        }
        return [...set];
    }

    // ── UI flotante ───────────────────────────────────────────────────────────
    function crearBoton() {
        if (document.getElementById('cmg-sri-btn')) return;
        // Estilos con animación de pulso para que el botón resalte (se inyecta una sola vez).
        if (!document.getElementById('cmg-sri-style')) {
            const st = document.createElement('style');
            st.id = 'cmg-sri-style';
            st.textContent =
                '@keyframes cmgPulse {' +
                '  0%   { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 0 rgba(13,110,253,.55); }' +
                '  70%  { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 20px rgba(13,110,253,0); }' +
                '  100% { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 0 rgba(13,110,253,0); }' +
                '}' +
                '#cmg-sri-btn:hover { background:#0b5ed7 !important; transform:scale(1.04); }';
            (document.head || document.documentElement).appendChild(st);
        }
        const btn = document.createElement('button');
        btn.id = 'cmg-sri-btn';
        btn.textContent = '⬇ Enviar comprobantes al sistema';
        Object.assign(btn.style, {
            position: 'fixed', bottom: '26px', right: '26px', zIndex: 2147483647,
            background: '#0d6efd', color: '#fff', border: '3px solid #fff', borderRadius: '14px',
            padding: '20px 34px', fontSize: '21px', fontWeight: 'bold', cursor: 'pointer',
            fontFamily: 'Arial, sans-serif', letterSpacing: '.3px',
            animation: 'cmgPulse 1.8s infinite', transition: 'transform .15s ease',
        });
        btn.addEventListener('click', enviar);
        document.body.appendChild(btn);
    }

    function quitarBoton() {
        document.getElementById('cmg-sri-btn')?.remove();
    }

    function aviso(html, color) {
        let n = document.getElementById('cmg-sri-aviso');
        if (!n) {
            n = document.createElement('div');
            n.id = 'cmg-sri-aviso';
            Object.assign(n.style, {
                position: 'fixed', bottom: '112px', right: '26px', zIndex: 2147483647,
                color: '#fff', borderRadius: '10px', padding: '14px 20px', fontSize: '15px',
                maxWidth: '360px', boxShadow: '0 6px 16px rgba(0,0,0,.3)',
                fontFamily: 'Arial, sans-serif', lineHeight: '1.4',
            });
            document.body.appendChild(n);
        }
        n.style.background = color || '#333';
        n.innerHTML = html;
    }

    function resetBoton(btn) {
        enviando = false;
        if (btn) { btn.disabled = false; btn.textContent = '⬇ Enviar comprobantes al sistema'; }
    }

    async function enviar() {
        if (enviando) return;

        const cfg = await chrome.storage.local.get(['servidorUrl', 'agenteToken']);
        if (!cfg.agenteToken) {
            aviso('Falta el token. Haz clic en el icono de la extensión (arriba a la derecha) y pega tu token del sistema.', '#dc3545');
            return;
        }

        enviando = true;
        const btn = document.getElementById('cmg-sri-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Recolectando…'; }
        aviso('Recolectando comprobantes de todas las páginas…', '#0d6efd');

        let claves;
        try {
            claves = await recolectarTodas();
        } catch (e) {
            aviso('Error recolectando: ' + e.message, '#dc3545');
            resetBoton(btn);
            return;
        }

        if (!claves.length) {
            aviso('No se encontraron comprobantes. Primero haz la consulta en el portal.', '#dc3545');
            resetBoton(btn);
            return;
        }

        aviso(`Enviando ${claves.length} comprobantes al sistema…`, '#0d6efd');
        if (btn) btn.textContent = `Enviando ${claves.length}…`;

        chrome.runtime.sendMessage({ tipo: 'registrar', claves }, (resp) => {
            resetBoton(btn);
            if (chrome.runtime.lastError) {
                aviso('Error: ' + chrome.runtime.lastError.message, '#dc3545');
                return;
            }
            if (!resp || !resp.ok) {
                aviso('Error: ' + ((resp && resp.error) || 'no se pudo enviar'), '#dc3545');
                return;
            }
            const r = resp.data || {};
            aviso(`✓ Listo. Nuevos: <b>${r.total_nuevos ?? 0}</b> · Ya existían: ${r.total_existentes ?? 0} · Errores: ${r.total_errores ?? 0}`, '#198754');
        });
    }

    // Botón para cerrar sesión del SRI (necesario para cambiar de empresa). Siempre visible.
    function crearBotonSalir() {
        if (document.getElementById('cmg-sri-salir')) return;
        const btn = document.createElement('button');
        btn.id = 'cmg-sri-salir';
        btn.textContent = '✕ Cerrar sesión SRI';
        Object.assign(btn.style, {
            position: 'fixed', bottom: '26px', left: '26px', zIndex: 2147483647,
            background: '#6c757d', color: '#fff', border: '2px solid #fff', borderRadius: '12px',
            padding: '14px 22px', fontSize: '16px', fontWeight: 'bold', cursor: 'pointer',
            fontFamily: 'Arial, sans-serif',
        });
        btn.addEventListener('click', salirSri);
        document.body.appendChild(btn);
    }

    function salirSri() {
        // Buscar el enlace/botón de cerrar sesión del portal del SRI.
        const sels = ['a[href*="logout"]', 'a[href*="cerrarSesion"]', 'a[href*="cerrar-sesion"]', 'a[href*="signoff"]'];
        for (const sel of sels) { const el = document.querySelector(sel); if (el) { el.click(); return; } }
        const els = [...document.querySelectorAll('a, button, span, li')];
        const salir = els.find(e => /cerrar sesi[oó]n|salir del sistema/i.test((e.textContent || '').trim()) && (e.textContent || '').length < 40);
        if (salir) { salir.click(); return; }
        // Último recurso: logout de Keycloak del SRI.
        location.href = 'https://srienlinea.sri.gob.ec/auth/realms/Internet/protocol/openid-connect/logout';
    }

    // Mostrar/ocultar el botón de enviar según haya resultados; el de salir siempre.
    setInterval(() => {
        crearBotonSalir();
        hayResultados() ? crearBoton() : quitarBoton();
    }, 1500);
})();
