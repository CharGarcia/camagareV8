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
        const btn = document.createElement('button');
        btn.id = 'cmg-sri-btn';
        btn.textContent = '⬇ Enviar comprobantes al sistema';
        Object.assign(btn.style, {
            position: 'fixed', bottom: '20px', right: '20px', zIndex: 2147483647,
            background: '#0d6efd', color: '#fff', border: 'none', borderRadius: '8px',
            padding: '12px 18px', fontSize: '14px', fontWeight: 'bold', cursor: 'pointer',
            boxShadow: '0 4px 12px rgba(0,0,0,.3)', fontFamily: 'Arial, sans-serif',
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
                position: 'fixed', bottom: '76px', right: '20px', zIndex: 2147483647,
                color: '#fff', borderRadius: '8px', padding: '12px 18px', fontSize: '14px',
                maxWidth: '340px', boxShadow: '0 4px 12px rgba(0,0,0,.3)',
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

    // Mostrar/ocultar el botón según haya resultados en la tabla.
    setInterval(() => { hayResultados() ? crearBoton() : quitarBoton(); }, 1500);
})();
