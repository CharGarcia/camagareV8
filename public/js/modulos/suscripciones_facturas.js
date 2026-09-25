/**
 * Pestaña «Facturas» del modal de Suscripciones (app/views/modulos/suscripciones/modal_suscripcion.php):
 * las facturas y recibos de venta emitidos al cliente elegido en el formulario —o solo los
 * que generó esta suscripción— con su estado de cobro; un clic en la fila despliega el
 * detalle de productos y servicios del documento. Solo lectura.
 *
 * Se carga al abrir la pestaña (no al abrir el modal) y se vuelve a pedir si cambió la
 * suscripción o el cliente; se limpia al abrir y al cerrar el modal. Los controles no
 * llevan "name" y Enter no envía el formulario: viven dentro del <form> de la suscripción.
 *
 * SuscFacturas.iniciar({
 *   url:       '/…/modulos/suscripciones/facturasClienteAjax',
 *   urlPdf:    { FACTURA: '/…/modulos/factura-venta/exportarPdfAjax', RECIBO: '/…/modulos/recibo-venta/exportarPdfAjax' },
 *   panel:     'pane-susc-facturas',   // id del tab-pane
 *   boton:     'susc-tab-facturas-btn',
 *   modalId:   'modalSusc',
 *   idSusc:    'susc_id',              // input con el id de la suscripción (vacío si es nueva)
 *   idCliente: 'susc_id_cliente',      // input con el cliente elegido en el formulario
 * });
 * Cada control del panel se ubica por su atributo data-sf.
 */
(function (window, document) {
    'use strict';

    if (window.SuscFacturas) return; // script incluido dos veces en la misma página

    /** o = ordenable: la clave debe existir en SuscripcionesRepository::ORDEN_FACTURAS_CLIENTE. */
    const COLUMNAS = [
        { k: 'fecha',     t: 'Fecha',     o: true, cls: 'ps-2' },
        { k: 'documento', t: 'Documento', o: true },
        { k: 'detalle',   t: 'Detalle' },
        { k: 'estado',    t: 'Estado',    cls: 'text-center' },
        { k: 'total',     t: 'Total',     o: true, cls: 'text-end' },
        { k: 'cobrado',   t: 'Cobrado',   o: true, cls: 'text-end', ayuda: 'Cobros, retenciones y notas de crédito aplicados' },
        { k: 'saldo',     t: 'Saldo',     o: true, cls: 'text-end' },
        { k: 'pago',      t: 'Pago',      cls: 'text-center' },
        { k: 'pdf',       t: '',          cls: 'text-center pe-2' },
    ];
    const NCOLS = COLUMNAS.length;

    /** Color del estado del documento: el mismo del listado de Facturas de Venta. */
    const COLOR_ESTADO = { autorizado: 'success', aprobado: 'success', anulado: 'danger', anulada: 'danger', borrador: 'secondary' };

    /** Estado de pago (lo calcula el servidor): color y etiqueta por tipo de documento. */
    const PAGO = {
        pagado:    { color: 'success', FACTURA: 'Pagada',    RECIBO: 'Pagado' },
        abonado:   { color: 'warning', FACTURA: 'Abonada',   RECIBO: 'Abonado' },
        pendiente: { color: 'danger',  FACTURA: 'Pendiente', RECIBO: 'Pendiente' },
    };

    const CARGANDO = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando…';

    // ── Utilidades ──────────────────────────────────────────────────────────

    /** Escapa para texto Y para atributos (title="…"): también las comillas. */
    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtFecha(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || '');
    }

    function fmtDinero(n) {
        // en-US: punto decimal y coma de miles (1,234.56), igual que el resto del sistema
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtMoneda(n) {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + fmtDinero(Math.abs(v));
    }

    function fmtCantidad(n) {
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    }

    function fmtPrecio(n) {
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    }

    function badge(color, texto, titulo) {
        const txt = color === 'warning' ? 'dark' : color;
        return `<span class="badge bg-${color} bg-opacity-10 text-${txt} border border-${color} border-opacity-25"${titulo ? ` title="${esc(titulo)}"` : ''}>${esc(texto)}</span>`;
    }

    function capitalizar(s) {
        s = String(s || '');
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    function filaMensaje(html, cls = 'text-muted') {
        return `<tr><td colspan="${NCOLS}" class="text-center ${cls} py-4">${html}</td></tr>`;
    }

    async function pedirJson(url) {
        try {
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return await resp.json();
        } catch (e) {
            return { ok: false, mensaje: 'No se pudo comunicar con el servidor.' };
        }
    }

    const claveFila = r => `${r.origen}:${r.id}`;

    // ── Componente ──────────────────────────────────────────────────────────

    function iniciar(cfg) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(cfg));
            return;
        }
        const panel = document.getElementById(cfg.panel);
        if (!panel) return; // sin permiso: la vista no pintó la pestaña

        const el    = clave => panel.querySelector(`[data-sf="${clave}"]`);
        const valor = id => (document.getElementById(id)?.value || '').trim();
        const st    = {
            clave: null,        // "idSuscripcion|idCliente" ya cargado
            alcance: 'cliente', // cliente | suscripcion
            page: 1,
            sort: 'fecha',
            dir: 'desc',
            timer: null,
            peticion: 0,
            filas: [],
            abiertas: new Set(),
            todas: false,       // botón "Ver detalle de todos"
        };

        const claveActual = () => `${valor(cfg.idSusc)}|${valor(cfg.idCliente)}`;

        function pintarCabecera() {
            const tr = el('thead');
            if (!tr) return;
            tr.innerHTML = COLUMNAS.map(c => {
                const ayuda = c.ayuda ? ` title="${esc(c.ayuda)}"` : '';
                if (!c.o) return `<th class="${c.cls || ''}"${ayuda}>${esc(c.t)}</th>`;
                const icono = st.sort === c.k
                    ? (st.dir === 'asc' ? 'bi-sort-up text-primary' : 'bi-sort-down text-primary')
                    : 'bi-arrow-down-up text-muted';
                const titulo = c.ayuda || `Ordenar por ${c.t.toLowerCase()}`;
                return `<th class="${c.cls || ''}" data-orden="${c.k}" title="${esc(titulo)}">${esc(c.t)} <i class="bi ${icono} small ms-1"></i></th>`;
            }).join('');
        }

        function pintarAlcance() {
            const hayId = valor(cfg.idSusc) !== '';
            panel.querySelectorAll('[data-sf-alcance]').forEach(b => {
                b.classList.toggle('active', b.dataset.sfAlcance === st.alcance);
                if (b.dataset.sfAlcance === 'suscripcion') {
                    b.disabled = !hayId;
                    b.title = hayId
                        ? 'Solo los documentos que generó esta suscripción'
                        : 'Guarde la suscripción para ver solo los documentos que genera';
                }
            });
        }

        function pintarPaginacion(total, page, totalPages, perPage) {
            const desde = total > 0 ? ((page - 1) * perPage) + 1 : 0;
            const hasta = total > 0 ? Math.min(page * perPage, total) : 0;
            const info  = el('info');
            if (info) info.textContent = `${desde}-${hasta}/${total}`;
            const prev = el('prev');
            const next = el('next');
            if (prev) prev.disabled = page <= 1;
            if (next) next.disabled = page >= totalPages;
        }

        function pintarResumen(r) {
            const docs = el('res-docs');
            if (docs) docs.textContent = r ? String(r.documentos || 0) : '0';
            const set = (clave, v) => { const x = el(clave); if (x) x.textContent = fmtMoneda(v); };
            set('res-total', r ? r.total : 0);
            set('res-cobrado', r ? r.cobrado : 0);
            set('res-saldo', r ? r.saldo : 0);
            const conSaldo = el('res-con-saldo');
            if (conSaldo) {
                const n = r ? (parseInt(r.con_saldo, 10) || 0) : 0;
                conSaldo.textContent = n ? `(${n})` : '';
                conSaldo.title = n ? `${n} documento${n === 1 ? '' : 's'} con saldo` : '';
            }
        }

        /** Nota al pie: qué documentos entran y de cuáles el usuario solo ve los suyos. */
        function pintarNota(json) {
            const nota = el('nota');
            if (!nota) return;
            const fuentes = json && Array.isArray(json.fuentes) ? json.fuentes : [];
            const propios = json && Array.isArray(json.propios) ? json.propios : [];
            const nombre  = { FACTURA: 'facturas', RECIBO: 'recibos' };
            const conArt  = { FACTURA: 'las facturas', RECIBO: 'los recibos' };
            const tipos   = fuentes.map(f => nombre[f]).filter(Boolean);
            let txt = tipos.length === 2
                ? 'Facturas y recibos de venta.'
                : (tipos.length === 1 ? `Solo ${tipos[0]} de venta.` : '');
            txt += ' Los anulados y los recibos ya facturados no suman al total ni al saldo.';
            const suyos = propios.map(p => conArt[p]).filter(Boolean);
            if (suyos.length) {
                txt += ` Solo ve ${suyos.join(' y ')} que usted registró (no tiene acceso total en ${suyos.length > 1 ? 'esos módulos' : 'ese módulo'}).`;
            }
            nota.textContent = txt.trim();
        }

        function filaHtml(r) {
            const abierta = st.abiertas.has(claveFila(r));
            const color   = COLOR_ESTADO[r.estado] || 'primary';
            const pago    = PAGO[r.estado_pago];
            const pagoCel = pago ? badge(pago.color, pago[r.origen] || capitalizar(r.estado_pago)) : '<span class="text-muted">—</span>';
            const saldo   = parseFloat(r.saldo) || 0;
            const urlPdf  = cfg.urlPdf && cfg.urlPdf[r.origen] ? `${cfg.urlPdf[r.origen]}?id=${encodeURIComponent(r.id)}` : '';
            const marca   = r.de_suscripcion
                ? '<i class="bi bi-arrow-repeat text-primary ms-1" title="Generado por esta suscripción"></i>'
                : '';
            const tachado = r.con_efecto ? '' : ' text-decoration-line-through text-muted';

            return `<tr class="susc-fact-fila" data-clave="${esc(claveFila(r))}" title="Clic para ver el detalle de productos y servicios">
                <td class="ps-2"><i class="bi ${abierta ? 'bi-chevron-down' : 'bi-chevron-right'} small text-primary me-1" data-sf="chevron"></i>${fmtFecha(r.fecha)}</td>
                <td><span class="text-muted me-1">${esc(r.tipo_documento)}</span><code class="text-secondary">${esc(r.numero)}</code>${marca}</td>
                <td class="susc-fact-desc" title="${esc(r.items)}">${esc(r.items) || '<span class="text-muted">—</span>'}</td>
                <td class="text-center">${badge(color, capitalizar(r.estado) || '—')}</td>
                <td class="text-end fw-medium${tachado}">${fmtMoneda(r.total)}</td>
                <td class="text-end${tachado}">${fmtMoneda(r.abonos)}</td>
                <td class="text-end fw-bold ${!r.con_efecto ? 'text-muted' : (Math.round(saldo * 100) > 0 ? 'text-danger' : 'text-success')}">${fmtMoneda(saldo)}</td>
                <td class="text-center">${pagoCel}</td>
                <td class="text-center pe-2">${urlPdf
                    ? `<a class="btn btn-sm btn-outline-danger py-0 px-1" href="${esc(urlPdf)}" data-pdf-documento title="Descargar el PDF del documento"><i class="bi bi-file-earmark-pdf"></i></a>`
                    : ''}</td>
            </tr>${abierta ? detalleHtml(r) : ''}`;
        }

        /** Fila desplegada: las líneas del documento y sus totales y abonos. */
        function detalleHtml(r) {
            const lineas = (r.lineas || []).map(l => `<tr>
                    <td class="ps-2"><code class="text-secondary">${esc(l.codigo) || '—'}</code></td>
                    <td class="susc-fact-linea-desc">${esc(l.descripcion)}</td>
                    <td class="text-end">${fmtCantidad(l.cantidad)}</td>
                    <td class="text-end">$${fmtPrecio(l.precio_unitario)}</td>
                    <td class="text-end">${parseFloat(l.descuento) ? '$' + fmtDinero(l.descuento) : '—'}</td>
                    <td class="text-end">$${fmtDinero(l.subtotal)}</td>
                    <td class="text-end pe-2"${parseFloat(l.tarifa_iva) ? ` title="Tarifa ${fmtCantidad(l.tarifa_iva)}%"` : ''}>${parseFloat(l.iva) ? '$' + fmtDinero(l.iva) : '—'}</td>
                </tr>`).join('');

            const abonos = [];
            if (parseFloat(r.cobrado))      abonos.push(`Cobros <b>${fmtMoneda(r.cobrado)}</b>`);
            if (parseFloat(r.retencion))    abonos.push(`Retenciones <b>${fmtMoneda(r.retencion)}</b>`);
            if (parseFloat(r.nota_credito)) abonos.push(`Notas de crédito <b>${fmtMoneda(r.nota_credito)}</b>`);

            return `<tr class="susc-fact-det"><td colspan="${NCOLS}" class="px-3 py-2">
                <table class="table table-sm table-bordered bg-white mb-1">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">Código</th>
                            <th>Descripción</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">P. unitario</th>
                            <th class="text-end">Descuento</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end pe-2">IVA</th>
                        </tr>
                    </thead>
                    <tbody>${lineas || `<tr><td colspan="7" class="text-muted text-center py-2">El documento no tiene líneas.</td></tr>`}</tbody>
                </table>
                <div class="d-flex flex-wrap gap-3 small">
                    <span>Subtotal <b>${fmtMoneda(r.subtotal)}</b></span>
                    <span>IVA <b>${fmtMoneda(r.iva)}</b></span>
                    <span>Total <b>${fmtMoneda(r.total)}</b></span>
                    ${abonos.length ? `<span class="text-muted">·</span>${abonos.map(a => `<span>${a}</span>`).join('')}` : ''}
                    ${r.de_suscripcion ? '<span class="ms-auto text-primary"><i class="bi bi-arrow-repeat me-1"></i>Generado por esta suscripción</span>' : ''}
                </div>
            </td></tr>`;
        }

        function pintarFilas(buscando) {
            const tbody = el('tbody');
            if (!tbody) return;
            if (!st.filas.length) {
                const vacio = buscando
                    ? 'Ningún documento coincide con la búsqueda.'
                    : (st.alcance === 'suscripcion'
                        ? 'Esta suscripción todavía no ha generado documentos.'
                        : 'Este cliente todavía no tiene facturas ni recibos de venta.');
                tbody.innerHTML = filaMensaje('<i class="bi bi-inbox fs-4 d-block mb-1"></i>' + esc(vacio));
                return;
            }
            tbody.innerHTML = st.filas.map(filaHtml).join('');
        }

        function pintarBotonTodas() {
            const b = el('todas');
            if (!b) return;
            b.classList.toggle('active', st.todas);
            b.innerHTML = st.todas
                ? '<i class="bi bi-arrows-collapse me-1"></i>Ocultar detalle'
                : '<i class="bi bi-arrows-expand me-1"></i>Ver detalle de todos';
        }

        async function cargar() {
            const idSusc    = valor(cfg.idSusc);
            const idCliente = valor(cfg.idCliente);
            if (!idSusc) st.alcance = 'cliente'; // suscripción nueva: todavía no generó nada
            pintarAlcance();
            st.clave = claveActual();

            const sinCliente = st.alcance === 'cliente' && !idCliente;
            el('sin-cliente')?.classList.toggle('d-none', !sinCliente);
            el('con-datos')?.classList.toggle('d-none', sinCliente);
            if (sinCliente) return;

            const tbody  = el('tbody');
            const buscar = (el('buscar')?.value || '').trim();
            pintarCabecera();
            if (tbody) tbody.innerHTML = filaMensaje(CARGANDO);

            const q = new URLSearchParams({
                id: idSusc, id_cliente: idCliente, alcance: st.alcance,
                page: st.page, sort: st.sort, dir: st.dir, b: buscar,
            });
            const nro  = ++st.peticion;
            const json = await pedirJson(`${cfg.url}?${q}`);
            if (nro !== st.peticion) return; // ya hay una consulta más reciente (o se cerró el modal)

            if (!json.ok) {
                st.filas = [];
                if (tbody) tbody.innerHTML = filaMensaje(esc(json.mensaje || json.error || 'No se pudieron cargar los documentos.'), 'text-danger');
                pintarPaginacion(0, 1, 1, 1);
                pintarResumen(null);
                return;
            }

            st.filas    = json.rows || [];
            st.abiertas = new Set(st.todas ? st.filas.map(claveFila) : []);
            pintarFilas(buscar !== '');
            pintarPaginacion(json.total, json.page, json.total_pages, json.per_page);
            pintarResumen(json.resumen);
            pintarNota(json);
        }

        function reset() {
            Object.assign(st, { clave: null, alcance: 'cliente', page: 1, sort: 'fecha', dir: 'desc', filas: [], abiertas: new Set(), todas: false });
            st.peticion++; // descarta cualquier respuesta en vuelo
            clearTimeout(st.timer);
            const inp = el('buscar');
            if (inp) inp.value = '';
            const tbody = el('tbody');
            if (tbody) tbody.innerHTML = '';
            pintarPaginacion(0, 1, 1, 1);
            pintarResumen(null);
            pintarBotonTodas();
            pintarAlcance();
        }

        /** Al mostrar la pestaña: carga solo si cambió la suscripción o el cliente. */
        function asegurar() {
            if (st.clave !== null && st.clave === claveActual()) return;
            st.page = 1;
            st.abiertas = new Set();
            cargar();
        }

        /** Despliega o pliega el detalle de una fila sin volver a consultar. */
        function alternar(filaEl) {
            const clave = filaEl.dataset.clave;
            const r = st.filas.find(f => claveFila(f) === clave);
            if (!r) return;
            const chevron   = filaEl.querySelector('[data-sf="chevron"]');
            const siguiente = filaEl.nextElementSibling;
            if (siguiente && siguiente.classList.contains('susc-fact-det')) {
                siguiente.remove();
                st.abiertas.delete(clave);
                chevron?.classList.replace('bi-chevron-down', 'bi-chevron-right');
                return;
            }
            filaEl.insertAdjacentHTML('afterend', detalleHtml(r));
            st.abiertas.add(clave);
            chevron?.classList.replace('bi-chevron-right', 'bi-chevron-down');
        }

        // ── Eventos ──

        document.getElementById(cfg.boton)?.addEventListener('shown.bs.tab', asegurar);

        const modalEl = document.getElementById(cfg.modalId);
        // Cada apertura del modal empieza limpia (otra suscripción, otro cliente).
        modalEl?.addEventListener('show.bs.modal', (e) => { if (e.target === modalEl) reset(); });
        modalEl?.addEventListener('hidden.bs.modal', (e) => { if (e.target === modalEl) reset(); });

        const inpBuscar = el('buscar');
        inpBuscar?.addEventListener('input', () => {
            clearTimeout(st.timer);
            st.timer = setTimeout(() => { st.page = 1; cargar(); }, 350);
        });
        inpBuscar?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault(); // Enter no debe enviar el formulario de la suscripción
            clearTimeout(st.timer);
            st.page = 1;
            cargar();
        });

        panel.querySelectorAll('[data-sf-alcance]').forEach(btn => btn.addEventListener('click', () => {
            if (btn.disabled || btn.dataset.sfAlcance === st.alcance) return;
            st.alcance = btn.dataset.sfAlcance;
            st.page = 1;
            cargar();
        }));

        el('todas')?.addEventListener('click', () => {
            st.todas = !st.todas;
            st.abiertas = new Set(st.todas ? st.filas.map(claveFila) : []);
            pintarBotonTodas();
            pintarFilas((el('buscar')?.value || '').trim() !== '');
        });

        el('thead')?.addEventListener('click', (e) => {
            const th = e.target.closest('th[data-orden]');
            if (!th) return;
            const col = th.dataset.orden;
            if (st.sort === col) {
                st.dir = st.dir === 'asc' ? 'desc' : 'asc';
            } else {
                st.sort = col;
                st.dir  = col === 'documento' ? 'asc' : 'desc';
            }
            st.page = 1;
            cargar();
        });

        el('prev')?.addEventListener('click', () => {
            if (st.page > 1) { st.page--; cargar(); }
        });
        el('next')?.addEventListener('click', () => {
            st.page++;
            cargar();
        });

        el('tbody')?.addEventListener('click', (e) => {
            if (e.target.closest('a, button')) return; // p. ej. el PDF: no despliega la fila
            const filaEl = e.target.closest('tr.susc-fact-fila');
            if (filaEl) alternar(filaEl);
        });

        pintarCabecera();
        pintarBotonTodas();
    }

    window.SuscFacturas = { iniciar };
})(window, document);
