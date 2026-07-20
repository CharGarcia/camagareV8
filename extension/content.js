'use strict';

/**
 * CaMaGaRe — Descarga SRI (content script)
 * Corre dentro del portal de "Comprobantes electrónicos recibidos" del SRI.
 * Muestra, apilados abajo a la derecha: el aviso de resultado, el botón "Enviar comprobantes"
 * (cuando hay resultados) y el botón "Cerrar sesión SRI". Al pulsar "Consultar" se limpia el aviso.
 */

(function () {
    const SEL_TBODY   = '[id$="tablaCompRecibidos_data"]';
    const SEL_LNK_XML = 'a[id*="lnkXml"]';
    let enviando = false;

    const pausa = (ms) => new Promise((r) => setTimeout(r, ms));

    // Extrae {clave, link} de cada fila visible. La clave vive en su propia celda con
    // 49 dígitos exactos; el enlace del XML es el mismo que usaba el scraper (":lnkXml").
    function filasDePaginaActual() {
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
            if (clave) out.push({ clave, link: row.querySelector(SEL_LNK_XML) });
        });
        return out;
    }

    function hayResultados() {
        return filasDePaginaActual().length > 0;
    }

    // ── Descarga del XML desde el portal ────────────────────────────────────────
    // Es la vía preferida: el XML del portal está disponible aunque el comprobante
    // sea antiguo, mientras que el webservice de autorización solo entrega los
    // recientes. Si falla, se manda solo la clave y el servidor usa el webservice.

    function pareceXmlComprobante(txt) {
        if (!txt) return false;
        const s = txt.slice(0, 500).toLowerCase();
        return s.includes('<?xml')
            || /<(comprobanteretencion|factura|notacredito|notadebito|liquidacioncompra|autorizacion)\b/.test(s);
    }

    async function descargarXml(link) {
        if (!link) return '';
        try {
            // Caso simple: el enlace apunta directo al archivo.
            const href = link.getAttribute('href') || '';
            if (href && !href.startsWith('#') && !href.toLowerCase().startsWith('javascript:')) {
                const r = await fetch(new URL(href, location.href).toString(), { credentials: 'include' });
                if (r.ok) {
                    const t = await r.text();
                    if (pareceXmlComprobante(t)) return t;
                }
            }

            // Caso JSF: replicar el postback del formulario que contiene el enlace.
            const form = link.closest('form');
            if (!form) return '';

            const params = new URLSearchParams();
            form.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
                if (el.type === 'submit' || el.type === 'button') return;
                params.append(el.name, el.value);
            });

            // Parámetros del onclick de Mojarra:
            //   mojarra.jsfcljs(form, {'frmPrincipal:tabla:0:lnkXml':'frmPrincipal:tabla:0:lnkXml'}, '')
            // Los ids de JSF contienen ':', así que hay que separar clave/valor
            // respetando las comillas: cortar por el primer ':' parte el id a la mitad.
            const onclick = link.getAttribute('onclick') || '';
            const m = onclick.match(/\{([^}]*)\}/);
            let usoOnclick = false;
            if (m && m[1].trim()) {
                const re = /(['"])(.*?)\1\s*:\s*(['"])(.*?)\3/g;
                let par;
                while ((par = re.exec(m[1])) !== null) {
                    params.set(par[2], par[4]);
                    usoOnclick = true;
                }
            }
            if (!usoOnclick && link.id) {
                params.set(link.id, link.id);
                params.set('javax.faces.source', link.id);
            }

            const resp = await fetch(form.getAttribute('action') || location.href, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params.toString(),
            });
            if (!resp.ok) return '';
            const txt = await resp.text();
            return pareceXmlComprobante(txt) ? txt : '';
        } catch (e) {
            return '';
        }
    }

    // Recorre todas las páginas y devuelve [{clave, xml}] (xml puede ir vacío).
    // Los XML se bajan ANTES de paginar: los ids de fila del portal solo valen
    // mientras esa página está en pantalla.
    // Pregunta al sistema cuáles de estas claves faltan por registrar.
    // Devuelve un Set, o null si no se pudo consultar (entonces se bajan todas).
    function pedirPendientes(claves) {
        return new Promise((resolve) => {
            try {
                chrome.runtime.sendMessage({ tipo: 'clavesPendientes', claves }, (resp) => {
                    if (chrome.runtime.lastError || !resp || !resp.ok) return resolve(null);
                    resolve(new Set(resp.pendientes || []));
                });
            } catch (e) { resolve(null); }
        });
    }

    async function recolectarTodas(onProgreso) {
        const porClave = new Map();
        let vueltas = 0;
        let fallosSeguidos = 0;
        let bajarXml = true;
        let omitidos = 0;

        while (vueltas < 60) {
            const filas = filasDePaginaActual().filter((f) => !porClave.has(f.clave));

            // Solo se descarga el XML de los pendientes. Los ya registrados se
            // envían igual (solo la clave) para que el resumen siga contándolos,
            // pero sin el costo de bajar su XML del portal.
            const pendientes = filas.length ? await pedirPendientes(filas.map((f) => f.clave)) : null;

            for (const fila of filas) {
                if (porClave.has(fila.clave)) continue;
                const hayQueBajar = bajarXml && (pendientes === null || pendientes.has(fila.clave));

                let xml = '';
                if (hayQueBajar) {
                    xml = await descargarXml(fila.link);
                    if (xml) {
                        fallosSeguidos = 0;
                    } else if (++fallosSeguidos >= 3) {
                        // El portal no está entregando XML por esta vía: dejar de
                        // intentarlo y seguir solo con claves (el servidor hará el resto).
                        bajarXml = false;
                    }
                    await pausa(150);
                } else if (pendientes !== null) {
                    omitidos++;
                }
                porClave.set(fila.clave, xml);
                if (onProgreso) onProgreso(porClave.size, omitidos);
            }

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

        return [...porClave.entries()].map(([clave, xml]) => ({ clave, xml }));
    }

    // ── UI flotante (contenedor apilado abajo a la derecha) ─────────────────────
    function contenedor() {
        let c = document.getElementById('cmg-sri-cont');
        if (!c) {
            c = document.createElement('div');
            c.id = 'cmg-sri-cont';
            Object.assign(c.style, {
                position: 'fixed', bottom: '24px', right: '24px', zIndex: 2147483647,
                display: 'flex', flexDirection: 'column', gap: '10px', alignItems: 'flex-end',
                fontFamily: 'Arial, sans-serif',
            });
            (document.body || document.documentElement).appendChild(c);
        }
        return c;
    }

    function estilos() {
        if (document.getElementById('cmg-sri-style')) return;
        const st = document.createElement('style');
        st.id = 'cmg-sri-style';
        st.textContent =
            '@keyframes cmgPulse {' +
            '  0%   { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 0 rgba(13,110,253,.55); }' +
            '  70%  { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 20px rgba(13,110,253,0); }' +
            '  100% { box-shadow: 0 8px 22px rgba(0,0,0,.35), 0 0 0 0 rgba(13,110,253,0); }' +
            '}' +
            '#cmg-sri-btn:hover { background:#0b5ed7 !important; transform:scale(1.04); }' +
            '#cmg-sri-salir:hover { background:#bb2d3b !important; }';
        (document.head || document.documentElement).appendChild(st);
    }

    // Botón principal: enviar comprobantes (order 2 = en medio).
    function crearBoton() {
        if (document.getElementById('cmg-sri-btn')) return;
        estilos();
        const btn = document.createElement('button');
        btn.id = 'cmg-sri-btn';
        btn.textContent = '⬇ Enviar comprobantes al sistema';
        Object.assign(btn.style, {
            order: '2',
            background: '#0d6efd', color: '#fff', border: '3px solid #fff', borderRadius: '14px',
            padding: '20px 34px', fontSize: '21px', fontWeight: 'bold', cursor: 'pointer',
            fontFamily: 'Arial, sans-serif', letterSpacing: '.3px',
            animation: 'cmgPulse 1.8s infinite', transition: 'transform .15s ease',
        });
        btn.addEventListener('click', enviar);
        contenedor().appendChild(btn);
    }

    function quitarBoton() {
        document.getElementById('cmg-sri-btn')?.remove();
    }

    // Botón de cerrar sesión del SRI (order 3 = debajo del de enviar), en rojo.
    function crearBotonSalir() {
        if (document.getElementById('cmg-sri-salir')) return;
        estilos();
        const btn = document.createElement('button');
        btn.id = 'cmg-sri-salir';
        btn.textContent = '✕ Cerrar sesión SRI';
        Object.assign(btn.style, {
            order: '3',
            background: '#dc3545', color: '#fff', border: '3px solid #fff', borderRadius: '14px',
            padding: '14px 24px', fontSize: '16px', fontWeight: 'bold', cursor: 'pointer',
            fontFamily: 'Arial, sans-serif',
        });
        btn.addEventListener('click', salirSri);
        contenedor().appendChild(btn);
    }

    function salirSri() {
        const btn = document.getElementById('cmg-sri-salir');
        if (btn) { btn.textContent = 'Cerrando sesión…'; btn.disabled = true; }
        // Cierra sesión limpiando las cookies del SRI (vía background) y cierra la pestaña.
        // No se espera la respuesta: al borrar las cookies el portal suele redirigir y
        // destruye este contexto antes de que llegue, lo que provoca el aviso
        // "message channel closed". Se consulta lastError solo para silenciarlo.
        try {
            chrome.runtime.sendMessage({ tipo: 'cerrar_sesion' }, () => { void chrome.runtime.lastError; });
        } catch (e) { /* el contexto ya se estaba descargando */ }

        setTimeout(() => {
            window.close();
            // Si el navegador no permite cerrar la pestaña, volver al inicio (pedirá login).
            setTimeout(() => { try { location.href = 'https://srienlinea.sri.gob.ec/'; } catch (e) {} }, 500);
        }, 400);
    }

    // Aviso de estado/resultado (order 1 = arriba del todo).
    function aviso(html, color) {
        let n = document.getElementById('cmg-sri-aviso');
        if (!n) {
            n = document.createElement('div');
            n.id = 'cmg-sri-aviso';
            Object.assign(n.style, {
                order: '1',
                color: '#fff', borderRadius: '10px', padding: '14px 20px', fontSize: '15px',
                maxWidth: '360px', boxShadow: '0 6px 16px rgba(0,0,0,.3)',
                fontFamily: 'Arial, sans-serif', lineHeight: '1.4',
            });
            contenedor().appendChild(n);
        }
        n.style.background = color || '#333';
        n.innerHTML = html;
    }

    function quitarAviso() {
        document.getElementById('cmg-sri-aviso')?.remove();
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
        if (btn) { btn.disabled = true; btn.textContent = 'Descargando XML…'; }
        aviso('Descargando los XML del portal…', '#0d6efd');

        let items;
        try {
            items = await recolectarTodas((n, omitidos) => {
                if (btn) {
                    btn.textContent = omitidos
                        ? `Revisando… (${n}, ${omitidos} ya registrados)`
                        : `Descargando XML… (${n})`;
                }
            });
        } catch (e) {
            aviso('Error recolectando: ' + e.message, '#dc3545');
            resetBoton(btn);
            return;
        }

        if (!items.length) {
            aviso('No se encontraron comprobantes. Primero haz la consulta en el portal.', '#dc3545');
            resetBoton(btn);
            return;
        }

        const conXml = items.filter((i) => i.xml).length;
        const yaEstaban = items.length - conXml;
        aviso(`Enviando ${items.length} comprobantes (${conXml} XML descargados`
            + (yaEstaban ? `, ${yaEstaban} ya registrados` : '') + ')…', '#0d6efd');
        if (btn) btn.textContent = `Enviando ${items.length}…`;

        chrome.runtime.sendMessage({ tipo: 'registrarXmls', items }, (resp) => {
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

    // Al pulsar "Consultar" en el SRI, limpiar el aviso de la descarga anterior.
    document.addEventListener('click', (e) => {
        const t = e.target.closest('button, a, input[type=submit], input[type=button]');
        if (t && /consultar/i.test((t.textContent || t.value || ''))) quitarAviso();
    }, true);

    // Botón de salir siempre; el de enviar solo cuando hay resultados en la tabla.
    setInterval(() => {
        crearBotonSalir();
        hayResultados() ? crearBoton() : quitarBoton();
    }, 1500);
})();
