/**
 * Pestañas de consulta de las fichas de proveedor y cliente: «Transacciones» y
 * «Estado de cuenta». Un solo código para ambos modales: cada uno llama a
 * FichaConsultas.iniciar(config) con sus URLs, ids y textos. El HTML lo pinta
 * app/views/partials/ficha_consultas.php (que también carga este script) y cada
 * control se ubica por su atributo data-fc dentro de su panel.
 *
 * Solo lectura. Cada pestaña se carga al abrirse (no al abrir la ficha), se vuelve a
 * pedir si cambia el registro, se limpia al cerrar el modal y se recarga tras guardar
 * una ficha nueva. Sus controles no llevan "name" y Enter no envía el formulario:
 * viven dentro del <form> de la ficha.
 *
 * config = {
 *   urlBase:        '/…/modulos/proveedores',  // expone transaccionesAjax, estadoCuentaAjax,
 *                                              // anticiposAjax, estadoCuentaExcel y consultasDisponiblesAjax
 *   idInput:        'prov_id',                 // input con el id de la ficha
 *   modalId:        'modalProveedor',
 *   eventoGuardado: 'proveedorGuardado',       // CustomEvent que emite la ficha al guardar
 *   transacciones:  { panel, boton, textos: { documentos, ultimaFecha, vacio } },
 *   estadoCuenta:   { panel, boton, textos: { sinPagos }, origenes: { ORIGEN: 'Etiqueta' }, pago: {…} },
 *   anticipos:      { panel, boton, textos: { vacio } },
 * }
 * Cada pestaña se pinta solo si ese registro tiene datos: al abrir la ficha se consulta
 * `consultasDisponiblesAjax` y se ocultan las que no aplican (ver crearDisponibilidad).
 * estadoCuenta.pago describe el movimiento de caja que se despliega con un clic (egreso
 * del proveedor / ingreso del cliente): origen, titulo, tituloFila, icono,
 * urlDetalle(id), urlPdf(id), numero(e), sujeto(e), etiquetaSujeto, montoLinea,
 * formaNombre, tituloDocs, tituloFormas, tiposDoc.
 */
(function (window, document) {
    'use strict';

    if (window.FichaConsultas) return; // script incluido dos veces en la misma página

    /** Columnas de Transacciones. o = ordenable (clave de LineasDocumentoTrait::ordenLineasDocumento()). */
    function columnasTransacciones(textos) {
        return {
            detalle: [
                { k: 'fecha',       t: 'Fecha',       o: true, cls: 'ps-2' },
                { k: 'tipo',        t: 'Tipo' },
                { k: 'documento',   t: 'Documento',   o: true },
                { k: 'codigo',      t: 'Código',      o: true },
                { k: 'descripcion', t: 'Descripción', o: true },
                { k: 'cantidad',    t: 'Cantidad',    o: true, cls: 'text-end' },
                { k: 'precio',      t: 'P. unitario', o: true, cls: 'text-end' },
                { k: 'descuento',   t: 'Descuento',   cls: 'text-end' },
                { k: 'subtotal',    t: 'Subtotal',    o: true, cls: 'text-end' },
                { k: 'iva',         t: 'IVA',         o: true, cls: 'text-end pe-2' },
            ],
            producto: [
                { k: 'codigo',        t: 'Código',              o: true, cls: 'ps-2' },
                { k: 'descripcion',   t: 'Producto / servicio', o: true },
                { k: 'documentos',    t: textos.documentos,     o: true, cls: 'text-center' },
                { k: 'cantidad',      t: 'Cantidad',            o: true, cls: 'text-end' },
                { k: 'ultimo_precio', t: 'Último precio',       o: true, cls: 'text-end' },
                { k: 'ultima_fecha',  t: textos.ultimaFecha,    o: true, cls: 'text-center' },
                { k: 'total',         t: 'Total',               o: true, cls: 'text-end pe-2' },
            ],
        };
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    function esc(valor) {
        const div = document.createElement('div');
        div.textContent = valor == null ? '' : String(valor);
        return div.innerHTML;
    }

    function fmtFecha(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || '');
    }

    function fmtCantidad(n) {
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
    }

    function fmtPrecio(n) {
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    }

    function fmtDinero(n) {
        // en-US: punto decimal y coma de miles (1,234.56), igual que el resto de la ficha
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /** Monto con el signo delante del símbolo: -$50.00 (saldo a favor), no $-50.00. */
    function fmtMoneda(n) {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + fmtDinero(Math.abs(v));
    }

    function filaMensaje(colspan, html, cls = 'text-muted') {
        return `<tr><td colspan="${colspan}" class="text-center ${cls} py-4">${html}</td></tr>`;
    }

    async function pedirJson(url) {
        try {
            const resp = await fetch(url);
            return await resp.json();
        } catch (e) {
            return { ok: false, error: 'No se pudo comunicar con el servidor.' };
        }
    }

    function idFicha(cfg) {
        return (document.getElementById(cfg.idInput)?.value || '').trim();
    }

    function mostrarSinGuardar(panel, sinGuardar) {
        panel.querySelector('[data-fc="sin-guardar"]')?.classList.toggle('d-none', !sinGuardar);
        panel.querySelector('[data-fc="con-datos"]')?.classList.toggle('d-none', sinGuardar);
    }

    const CARGANDO = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando…';

    /**
     * Filas por página del estado de cuenta. Se pagina en el navegador porque el saldo
     * corriendo necesita todos los movimientos del período; Transacciones, en cambio,
     * pagina en el servidor (el tamaño lo fija el controlador).
     */
    const POR_PAGINA_EC = 20;

    // ── Transacciones ───────────────────────────────────────────────────────

    function crearTransacciones(cfg) {
        const conf  = cfg.transacciones;
        const panel = document.getElementById(conf.panel);
        if (!panel) return null; // sin permiso: la vista no pintó el panel

        const textos = Object.assign({ documentos: 'Veces', ultimaFecha: 'Última fecha', vacio: 'Todavía no hay transacciones.' }, conf.textos || {});
        const cols   = columnasTransacciones(textos);
        const el     = clave => panel.querySelector(`[data-fc="${clave}"]`);
        const st     = { idCargado: null, page: 1, vista: 'detalle', sort: 'fecha', dir: 'desc', timer: null, peticion: 0 };

        function pintarCabecera() {
            const tr = el('thead');
            if (!tr) return;
            tr.innerHTML = cols[st.vista].map(c => {
                if (!c.o) return `<th class="${c.cls || ''}">${esc(c.t)}</th>`;
                const icono = st.sort === c.k
                    ? (st.dir === 'asc' ? 'bi-sort-up text-primary' : 'bi-sort-down text-primary')
                    : 'bi-arrow-down-up text-muted';
                return `<th class="${c.cls || ''}" data-orden="${c.k}" title="Ordenar por ${esc(c.t.toLowerCase())}">${esc(c.t)} <i class="bi ${icono} small ms-1"></i></th>`;
            }).join('');
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

        function fila(r) {
            const desc = esc(r.descripcion);
            if (st.vista === 'producto') {
                return `<tr>
                    <td class="ps-2"><code class="text-secondary">${esc(r.codigo) || '—'}</code></td>
                    <td class="fc-col-desc" title="${desc}">${desc || '—'}</td>
                    <td class="text-center">${parseInt(r.documentos, 10) || 0}</td>
                    <td class="text-end">${fmtCantidad(r.cantidad)}</td>
                    <td class="text-end">${r.ultimo_precio !== null ? '$' + fmtPrecio(r.ultimo_precio) : '—'}</td>
                    <td class="text-center">${r.ultima_fecha ? fmtFecha(r.ultima_fecha) : '—'}</td>
                    <td class="text-end pe-2 fw-medium">${fmtMoneda(r.total)}</td>
                </tr>`;
            }
            // Nota de crédito: devolución, se ve en rojo y resta
            const nc    = parseInt(r.signo, 10) < 0;
            const rojo  = nc ? ' text-danger' : '';
            const signo = nc ? '−' : '';
            return `<tr${nc ? ' title="Nota de crédito: resta del total"' : ''}>
                <td class="ps-2">${fmtFecha(r.fecha)}</td>
                <td class="${rojo}">${esc(r.tipo_documento)}</td>
                <td><code class="text-secondary">${esc(r.numero_documento)}</code></td>
                <td><code class="text-secondary">${esc(r.codigo) || '—'}</code></td>
                <td class="fc-col-desc" title="${desc}">${desc}</td>
                <td class="text-end${rojo}">${signo}${fmtCantidad(r.cantidad)}</td>
                <td class="text-end">$${fmtPrecio(r.precio_unitario)}</td>
                <td class="text-end">${parseFloat(r.descuento) ? '$' + fmtDinero(r.descuento) : '—'}</td>
                <td class="text-end fw-medium${rojo}">${signo}$${fmtDinero(r.subtotal)}</td>
                <td class="text-end pe-2${rojo}"${parseFloat(r.tarifa_iva) ? ` title="Tarifa ${fmtCantidad(r.tarifa_iva)}%"` : ''}>${parseFloat(r.iva) ? `${signo}$${fmtDinero(r.iva)}` : '—'}</td>
            </tr>`;
        }

        async function cargar() {
            const id = idFicha(cfg);
            mostrarSinGuardar(panel, !id);
            if (!id) return;
            st.idCargado = id;

            const tbody  = el('tbody');
            const ncols  = cols[st.vista].length;
            const buscar = (el('buscar')?.value || '').trim();
            pintarCabecera();
            tbody.innerHTML = filaMensaje(ncols, CARGANDO);

            const q   = new URLSearchParams({ id, vista: st.vista, page: st.page, sort: st.sort, dir: st.dir, b: buscar });
            const nro = ++st.peticion;
            const json = await pedirJson(`${cfg.urlBase}/transaccionesAjax?${q}`);
            if (nro !== st.peticion) return; // ya hay una consulta más reciente (o se cambió de ficha)

            const total    = el('total');
            const totalIva = el('total-iva');
            if (!json.ok) {
                tbody.innerHTML = filaMensaje(ncols, esc(json.error || 'No se pudieron cargar las transacciones.'), 'text-danger');
                pintarPaginacion(0, 1, 1, 1);
                if (total) total.textContent = '$0.00';
                if (totalIva) totalIva.textContent = '$0.00';
                return;
            }

            const rows = json.rows || [];
            tbody.innerHTML = rows.length
                ? rows.map(fila).join('')
                : filaMensaje(ncols, '<i class="bi bi-inbox fs-4 d-block mb-1"></i>'
                    + esc(buscar ? 'Ninguna transacción coincide con la búsqueda.' : textos.vacio));
            pintarPaginacion(json.total, json.page, json.total_pages, json.per_page);
            if (total) total.textContent = fmtMoneda(json.total_neto);
            if (totalIva) totalIva.textContent = fmtMoneda(json.total_iva);
        }

        function reset() {
            Object.assign(st, { idCargado: null, page: 1, vista: 'detalle', sort: 'fecha', dir: 'desc' });
            st.peticion++;
            clearTimeout(st.timer);
            const inp = el('buscar');
            if (inp) inp.value = '';
            panel.querySelectorAll('[data-fc-vista]').forEach(b => b.classList.toggle('active', b.dataset.fcVista === 'detalle'));
            const tbody = el('tbody');
            if (tbody) tbody.innerHTML = '';
            const total = el('total');
            if (total) total.textContent = '$0.00';
            const totalIva = el('total-iva');
            if (totalIva) totalIva.textContent = '$0.00';
            pintarPaginacion(0, 1, 1, 1);
        }

        function asegurar() {
            const id = idFicha(cfg);
            if (id && st.idCargado === id) return;
            if (st.idCargado !== null) reset();
            cargar();
        }

        // Eventos
        document.getElementById(conf.boton)?.addEventListener('shown.bs.tab', asegurar);

        const inpBuscar = el('buscar');
        inpBuscar?.addEventListener('input', () => {
            clearTimeout(st.timer);
            st.timer = setTimeout(() => { st.page = 1; cargar(); }, 350);
        });
        inpBuscar?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault(); // Enter no debe enviar el formulario de la ficha
            clearTimeout(st.timer);
            st.page = 1;
            cargar();
        });

        panel.querySelectorAll('[data-fc-vista]').forEach(btn => btn.addEventListener('click', () => {
            const vista = btn.dataset.fcVista;
            if (vista === st.vista) return;
            Object.assign(st, { vista, page: 1, sort: vista === 'producto' ? 'ultima_fecha' : 'fecha', dir: 'desc' });
            panel.querySelectorAll('[data-fc-vista]').forEach(b => b.classList.toggle('active', b === btn));
            cargar();
        }));

        el('thead')?.addEventListener('click', (e) => {
            const th = e.target.closest('th[data-orden]');
            if (!th) return;
            const col = th.dataset.orden;
            if (st.sort === col) {
                st.dir = st.dir === 'asc' ? 'desc' : 'asc';
            } else {
                st.sort = col;
                st.dir  = ['codigo', 'descripcion', 'documento'].includes(col) ? 'asc' : 'desc';
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

        return {
            reset,
            refrescarSiVisible() { if (panel.classList.contains('active')) asegurar(); },
        };
    }

    // ── Estado de cuenta ────────────────────────────────────────────────────

    function crearEstadoCuenta(cfg) {
        const conf  = cfg.estadoCuenta;
        const panel = document.getElementById(conf.panel);
        if (!panel) return null;

        const pago     = conf.pago || {};
        const origenes = conf.origenes || {};
        const textos   = Object.assign({ sinPagos: 'No hay pagos en el período.' }, conf.textos || {});
        const el       = clave => panel.querySelector(`[data-fc="${clave}"]`);
        const st       = { idCargado: null, filtro: 'todos', data: null, page: 1, peticion: 0, detalles: new Map() };

        function pintarResumen(d) {
            const set = (clave, v) => { const x = el(clave); if (x) x.textContent = fmtMoneda(v); };
            set('saldo-anterior', d ? d.saldo_anterior : 0);
            set('cargos', d ? d.total_cargos : 0);
            set('pagos', d ? d.total_pagos : 0);
            set('otros', d ? d.total_otros_abonos : 0);
            set('saldo', d ? d.saldo_final : 0);
            el('card-anterior')?.classList.toggle('d-none', !(d && el('desde')?.value));
            el('ayuda-pago')?.classList.toggle('d-none', !(d && d.puede_ver_pago && d.total_pagos));
        }

        function fila(m, puedeAbrir) {
            const esCargo = m.tipo_movimiento === 'CARGO';
            const esPago  = m.origen === pago.origen;
            const abrible = esPago && puedeAbrir;
            const color   = esCargo ? 'primary' : (esPago ? 'success' : 'warning');
            const badge   = `<span class="badge bg-${color} bg-opacity-10 text-${color === 'warning' ? 'dark' : color} border border-${color} border-opacity-25">${esc(origenes[m.origen] || m.origen)}</span>`;
            const attrs   = abrible ? ` class="fc-fila-abrible" data-id-pago="${parseInt(m.id_origen, 10)}" title="${esc(pago.tituloFila || 'Ver detalle')}"` : '';
            const flecha  = abrible ? '<i class="bi bi-chevron-right small text-primary me-1" data-fc="chevron"></i>' : '';
            return `<tr${attrs}>
                <td class="ps-2">${flecha}${fmtFecha(m.fecha)}</td>
                <td>${badge}</td>
                <td><code class="text-secondary">${esc(m.numero_documento)}</code></td>
                <td class="fc-col-desc" title="${esc(m.detalle)}">${esc(m.detalle)}</td>
                <td class="text-end">${esCargo ? fmtMoneda(m.monto) : ''}</td>
                <td class="text-end">${esCargo ? '' : fmtMoneda(m.monto)}</td>
                <td class="text-end pe-2 fw-medium">${fmtMoneda(m.saldo)}</td>
            </tr>`;
        }

        function pintarPaginacion(total, totalPaginas) {
            const desde = total > 0 ? ((st.page - 1) * POR_PAGINA_EC) + 1 : 0;
            const hasta = total > 0 ? Math.min(st.page * POR_PAGINA_EC, total) : 0;
            const info  = el('info');
            if (info) info.textContent = `${desde}-${hasta}/${total}`;
            const prev = el('prev');
            const next = el('next');
            if (prev) prev.disabled = st.page <= 1;
            if (next) next.disabled = st.page >= totalPaginas;
        }

        function pintarMovimientos() {
            const tbody = el('tbody');
            const d = st.data;
            if (!tbody || !d) return;

            const soloPagos = st.filtro === 'pagos';
            const movs  = (d.movimientos || []).filter(m => !soloPagos || m.origen === pago.origen);
            const desde = el('desde')?.value || '';

            const totalPaginas = Math.max(1, Math.ceil(movs.length / POR_PAGINA_EC));
            st.page = Math.min(Math.max(1, st.page), totalPaginas);
            const pagina = movs.slice((st.page - 1) * POR_PAGINA_EC, st.page * POR_PAGINA_EC);

            let html = '';
            // El saldo anterior encabeza el estado de cuenta: solo en la primera página
            if (desde && !soloPagos && st.page === 1) {
                html += `<tr class="table-light">
                    <td class="ps-2">${fmtFecha(desde)}</td>
                    <td colspan="5" class="fst-italic text-muted">Saldo anterior</td>
                    <td class="text-end pe-2 fw-medium">${fmtMoneda(d.saldo_anterior)}</td>
                </tr>`;
            }
            html += pagina.length
                ? pagina.map(m => fila(m, !!d.puede_ver_pago)).join('')
                : filaMensaje(7, '<i class="bi bi-inbox fs-4 d-block mb-1"></i>'
                    + esc(soloPagos ? textos.sinPagos : 'No hay movimientos en el período.'));
            tbody.innerHTML = html;
            pintarPaginacion(movs.length, totalPaginas);
        }

        async function cargar() {
            const id = idFicha(cfg);
            mostrarSinGuardar(panel, !id);
            if (!id) return;
            st.idCargado = id;
            st.page = 1;

            const tbody = el('tbody');
            tbody.innerHTML = filaMensaje(7, CARGANDO);

            const q = new URLSearchParams({ id, desde: el('desde')?.value || '', hasta: el('hasta')?.value || '' });
            const nro  = ++st.peticion;
            const json = await pedirJson(`${cfg.urlBase}/estadoCuentaAjax?${q}`);
            if (nro !== st.peticion) return;

            if (!json.ok) {
                st.data = null;
                tbody.innerHTML = filaMensaje(7, esc(json.error || 'No se pudo cargar el estado de cuenta.'), 'text-danger');
                pintarResumen(null);
                return;
            }
            st.data = json;
            pintarResumen(json);
            pintarMovimientos();
        }

        function reset() {
            Object.assign(st, { idCargado: null, filtro: 'todos', data: null, page: 1 });
            st.peticion++;
            st.detalles.clear();
            ['desde', 'hasta'].forEach(clave => { const x = el(clave); if (x) x.value = ''; });
            panel.querySelectorAll('[data-fc-filtro]').forEach(b => b.classList.toggle('active', b.dataset.fcFiltro === 'todos'));
            const tbody = el('tbody');
            if (tbody) tbody.innerHTML = '';
            pintarResumen(null);
            pintarPaginacion(0, 1);
        }

        function asegurar() {
            const id = idFicha(cfg);
            if (id && st.idCargado === id) return;
            if (st.idCargado !== null) reset();
            cargar();
        }

        /** Detalle del egreso/ingreso (respuesta de su getXxxAjax) para la fila desplegable. */
        function htmlPago(e) {
            const tipos = pago.tiposDoc || {};
            const docs = (e.detalles || []).map(d => {
                const etiqueta = tipos[d.tipo_documento];
                const concepto = etiqueta
                    ? `${esc(etiqueta)} <code class="text-secondary">${esc(d.numero_documento || '')}</code>`
                    : esc(d.descripcion || d.numero_documento || 'Otro concepto');
                return `<tr><td>${concepto}</td><td class="text-end text-nowrap">$${fmtDinero(d[pago.montoLinea])}</td></tr>`;
            }).join('');

            const formas = (e.pagos || []).filter(x => x.estado_cheque !== 'anulado').map(x => {
                const extra = [];
                if (x.tipo_operacion_bancaria) extra.push(esc(x.tipo_operacion_bancaria));
                if (x.numero_cheque) extra.push('Cheque N° ' + esc(x.numero_cheque));
                if (x.referencia) extra.push('Ref. ' + esc(x.referencia));
                if (x.fecha_cobro) extra.push('Cobro ' + fmtFecha(x.fecha_cobro));
                return `<tr>
                    <td>${esc(x[pago.formaNombre] || '')}${x.banco_nombre ? ' — ' + esc(x.banco_nombre) : ''}
                        ${extra.length ? `<div class="text-muted">${extra.join(' · ')}</div>` : ''}</td>
                    <td class="text-end text-nowrap">$${fmtDinero(x.monto)}</td>
                </tr>`;
            }).join('');

            const sinFilas = '<tr><td class="text-muted">—</td></tr>';
            const titulo   = pago.titulo || 'Documento';
            const sujeto   = pago.sujeto ? pago.sujeto(e) : '';
            const urlPdf   = pago.urlPdf ? pago.urlPdf(e.id) : '';

            return `
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="fw-bold"><i class="bi ${pago.icono || 'bi-cash-coin'} text-success me-1"></i>${esc(titulo)} <code>${esc(pago.numero ? pago.numero(e) : '')}</code></span>
                    <span class="text-muted">${fmtFecha(e.fecha_emision)}</span>
                    ${sujeto ? `<span class="text-muted">· ${esc(pago.etiquetaSujeto || '')} ${esc(sujeto)}</span>` : ''}
                    ${e.concepto_nombre ? `<span class="badge bg-secondary bg-opacity-10 text-secondary border">${esc(e.concepto_nombre)}</span>` : ''}
                    <span class="ms-auto fw-bold">Total: $${fmtDinero(e.monto_total)}</span>
                    ${urlPdf ? `<a class="btn btn-sm btn-outline-danger py-0 px-2" href="${esc(urlPdf)}" download title="Descargar el comprobante de ${esc(titulo.toLowerCase())} (PDF)"><i class="bi bi-file-earmark-pdf"></i></a>` : ''}
                </div>
                ${e.observaciones ? `<div class="text-muted mb-2"><i class="bi bi-chat-left-text me-1"></i>${esc(e.observaciones)}</div>` : ''}
                <div class="row g-2">
                    <div class="col-md-7">
                        <div class="fw-bold text-muted mb-1">${esc(pago.tituloDocs || 'Documentos')}</div>
                        <table class="table table-sm table-bordered bg-white mb-0"><tbody>${docs || sinFilas}</tbody></table>
                    </div>
                    <div class="col-md-5">
                        <div class="fw-bold text-muted mb-1">${esc(pago.tituloFormas || 'Formas de pago')}</div>
                        <table class="table table-sm table-bordered bg-white mb-0"><tbody>${formas || sinFilas}</tbody></table>
                    </div>
                </div>
                ${e.usuario_nombre ? `<div class="text-muted mt-1" style="font-size: 0.7rem;">Registrado por ${esc(e.usuario_nombre)}</div>` : ''}`;
        }

        /** Clic en un pago/cobro: despliega (o pliega) su egreso/ingreso debajo de la fila. */
        async function alternarPago(filaEl) {
            const chevron   = filaEl.querySelector('[data-fc="chevron"]');
            const siguiente = filaEl.nextElementSibling;
            if (siguiente && siguiente.classList.contains('fc-detalle-pago')) {
                siguiente.remove();
                chevron?.classList.replace('bi-chevron-down', 'bi-chevron-right');
                return;
            }
            chevron?.classList.replace('bi-chevron-right', 'bi-chevron-down');

            const det = document.createElement('tr');
            det.className = 'fc-detalle-pago';
            det.innerHTML = `<td colspan="7" class="px-3 py-2">${CARGANDO}</td>`;
            filaEl.after(det);

            const idPago = filaEl.dataset.idPago;
            let json = st.detalles.get(idPago);
            if (!json) {
                json = await pedirJson(pago.urlDetalle(idPago));
                if (json.ok) st.detalles.set(idPago, json);
            }
            if (!det.isConnected) return; // se plegó o se recargó la tabla mientras llegaba

            det.firstElementChild.innerHTML = json.ok
                ? htmlPago(json.data || {})
                : `<span class="text-danger">${esc(json.mensaje || json.error || 'No se pudo cargar el detalle.')}</span>`;
        }

        // Eventos
        document.getElementById(conf.boton)?.addEventListener('shown.bs.tab', asegurar);

        ['desde', 'hasta'].forEach(clave => {
            const x = el(clave);
            x?.addEventListener('change', cargar);
            x?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); cargar(); } // Enter no envía la ficha
            });
        });

        el('limpiar')?.addEventListener('click', () => {
            ['desde', 'hasta'].forEach(clave => { const x = el(clave); if (x) x.value = ''; });
            cargar();
        });

        // Excel: lo arma el servidor con los mismos movimientos y el mismo período que se
        // están viendo, para que el archivo coincida con la pantalla.
        el('excel')?.addEventListener('click', () => {
            const id = idFicha(cfg);
            if (!id) return;
            const p = new URLSearchParams({
                id,
                desde: el('desde')?.value || '',
                hasta: el('hasta')?.value || '',
            });
            window.open(`${cfg.urlBase}/estadoCuentaExcel?${p.toString()}`, '_blank');
        });

        panel.querySelectorAll('[data-fc-filtro]').forEach(btn => btn.addEventListener('click', () => {
            st.filtro = btn.dataset.fcFiltro;
            st.page   = 1;
            panel.querySelectorAll('[data-fc-filtro]').forEach(b => b.classList.toggle('active', b === btn));
            pintarMovimientos();
        }));

        el('prev')?.addEventListener('click', () => {
            if (st.page > 1) { st.page--; pintarMovimientos(); }
        });
        el('next')?.addEventListener('click', () => {
            st.page++;
            pintarMovimientos();
        });

        el('tbody')?.addEventListener('click', (e) => {
            if (e.target.closest('a, button')) return; // p. ej. el PDF dentro del detalle
            const filaEl = e.target.closest('tr.fc-fila-abrible');
            if (filaEl) alternarPago(filaEl);
        });

        return {
            reset,
            refrescarSiVisible() { if (panel.classList.contains('active')) asegurar(); },
        };
    }

    // ── Anticipos ───────────────────────────────────────────────────────────

    /**
     * Pestaña "Anticipos": el saldo a favor del tercero y los movimientos que lo forman
     * (el saldo inicial y los anticipos recibidos/entregados suman; lo aplicado a un cobro
     * o pago resta). Solo lectura y sin filtros: lo que importa es el saldo. La pestaña
     * solo se pinta si hay movimientos (ver crearDisponibilidad).
     */
    function crearAnticipos(cfg) {
        const conf   = cfg.anticipos;
        const panel  = document.getElementById(conf.panel);
        if (!panel) return null;
        const textos = conf.textos || {};
        const st     = { idCargado: null, peticion: 0 };
        const el     = clave => panel.querySelector(`[data-fc="${clave}"]`);

        function filaHtml(m) {
            const resta = Number(m.signo) < 0;
            return `<tr>
                <td class="ps-2">${fmtFecha(m.fecha)}</td>
                <td>${esc(m.movimiento)}</td>
                <td>${esc(m.forma)}</td>
                <td>${esc(m.numero_documento)}</td>
                <td class="fc-col-desc" title="${esc(m.detalle)}">${esc(m.detalle)}</td>
                <td class="text-end ${resta ? 'text-success' : 'text-primary'}">${resta ? '−' : ''}${fmtMoneda(m.monto)}</td>
                <td class="text-end pe-2 fw-medium">${fmtMoneda(m.saldo)}</td>
            </tr>`;
        }

        async function cargar() {
            const id = idFicha(cfg);
            mostrarSinGuardar(panel, !id);
            if (!id) return;
            st.idCargado = id;

            const tbody = el('tbody');
            const mio   = ++st.peticion;
            if (tbody) tbody.innerHTML = filaMensaje(7, CARGANDO);

            const json = await pedirJson(`${cfg.urlBase}/anticiposAjax?id=${encodeURIComponent(id)}`);
            if (mio !== st.peticion || !tbody) return;
            if (!json.ok) {
                tbody.innerHTML = filaMensaje(7, esc(json.error || 'No se pudo cargar.'), 'text-danger');
                return;
            }

            const movs = json.movimientos || [];
            tbody.innerHTML = movs.length
                ? movs.map(filaHtml).join('')
                : filaMensaje(7, '<i class="bi bi-inbox fs-4 d-block mb-1"></i>'
                    + esc(textos.vacio || 'No hay anticipos registrados.'));

            const pintar = (clave, valor) => { const x = el(clave); if (x) x.textContent = fmtMoneda(valor); };
            pintar('generado', json.total_generado);
            pintar('aplicado', json.total_aplicado);
            pintar('saldo', json.saldo);
        }

        function reset() {
            st.idCargado = null;
            st.peticion++;
            const tbody = el('tbody');
            if (tbody) tbody.innerHTML = '';
            ['generado', 'aplicado', 'saldo'].forEach(clave => {
                const x = el(clave);
                if (x) x.textContent = '$0.00';
            });
        }

        function asegurar() {
            const id = idFicha(cfg);
            if (id && st.idCargado === id) return;
            if (st.idCargado !== null) reset();
            cargar();
        }

        document.getElementById(conf.boton)?.addEventListener('shown.bs.tab', asegurar);

        return {
            reset,
            refrescarSiVisible() { if (panel.classList.contains('active')) asegurar(); },
        };
    }

    // ── Qué pestañas tiene sentido mostrar ──────────────────────────────────

    /**
     * Las dos pestañas se muestran solo si ESE cliente o proveedor tiene datos; el estado
     * de cuenta, además, solo si el usuario puede ver el Reporte de Cartera. Ambas cosas
     * las decide el servidor en `consultasDisponiblesAjax`, que se pregunta una vez por
     * registro (consulta barata: EXISTS sobre las mismas fuentes de cada pestaña).
     *
     * El id de la ficha se vigila mientras el modal está abierto porque cada ficha lo fija
     * en un momento distinto: la de clientes carga los datos y después abre el modal, y la
     * de proveedores abre el modal primero y lo completa luego. Vigilándolo se cubren por
     * igual el alta, la edición y el duplicado, sin que cada ficha tenga que avisar.
     */
    function crearDisponibilidad(cfg) {
        const partes = [
            { conf: cfg.transacciones, clave: 'transacciones' },
            { conf: cfg.estadoCuenta,  clave: 'estado_cuenta' },
            { conf: cfg.anticipos,     clave: 'anticipos' },
        ].filter(p => p.conf && p.conf.boton);
        if (!partes.length) return null;

        let idConsultado = null;
        let vigilante    = null;
        let peticion     = 0;

        /** Oculta o muestra la pestaña. Solo toca su clase: si el usuario la escondió desde
         *  el menú de pestañas configurables, ese CSS sigue mandando. */
        function mostrar(conf, visible) {
            const boton = document.getElementById(conf.boton);
            (boton?.closest('.nav-item') || boton)?.classList.toggle('d-none', !visible);
        }

        /** Si la pestaña que se oculta era la activa, pasar a la primera visible. */
        function reubicarSiActiva(conf) {
            const boton = document.getElementById(conf.boton);
            if (!boton || !boton.classList.contains('active')) return;
            const lista = boton.closest('.nav-tabs');
            const otro  = lista && Array.from(lista.querySelectorAll('.nav-link'))
                .find(b => b !== boton && b.offsetParent !== null);
            if (otro && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(otro).show();
            }
        }

        function ocultarTodas() {
            partes.forEach(p => { mostrar(p.conf, false); reubicarSiActiva(p.conf); });
        }

        async function revisar(id) {
            const mio = ++peticion;
            if (!id) { ocultarTodas(); return; }

            const json = await pedirJson(`${cfg.urlBase}/consultasDisponiblesAjax?id=${encodeURIComponent(id)}`);
            if (mio !== peticion) return; // llegó tarde: mientras tanto se abrió otra ficha

            partes.forEach(p => {
                // Si la consulta falla se dejan visibles: cada pestaña ya avisa si está vacía.
                const visible = json.ok ? !!json[p.clave] : true;
                mostrar(p.conf, visible);
                if (!visible) reubicarSiActiva(p.conf);
            });
        }

        function comprobar(forzar) {
            const id = idFicha(cfg);
            if (!forzar && id === idConsultado) return;
            idConsultado = id;
            revisar(id);
        }

        return {
            empezar() {
                idConsultado = null;
                ocultarTodas();
                comprobar(true);
                clearInterval(vigilante);
                vigilante = setInterval(() => comprobar(false), 250);
            },
            parar() {
                clearInterval(vigilante);
                vigilante    = null;
                idConsultado = null;
                peticion++;  // descarta cualquier respuesta en vuelo
            },
            comprobar,
        };
    }

    // ── Arranque ────────────────────────────────────────────────────────────

    function iniciar(cfg) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => iniciar(cfg));
            return;
        }
        const trx = cfg.transacciones ? crearTransacciones(cfg) : null;
        const ec  = cfg.estadoCuenta ? crearEstadoCuenta(cfg) : null;
        const ant = cfg.anticipos ? crearAnticipos(cfg) : null;
        if (!trx && !ec && !ant) return;

        const disp    = crearDisponibilidad(cfg);
        const modalEl = document.getElementById(cfg.modalId);

        // Abrir la ficha: las pestañas arrancan ocultas y se muestran según lo que haya.
        modalEl?.addEventListener('shown.bs.modal', () => disp?.empezar());

        // Cerrar la ficha limpia las pestañas: la próxima que se abra no muestra datos de otro registro
        modalEl?.addEventListener('hidden.bs.modal', () => {
            disp?.parar();
            trx?.reset();
            ec?.reset();
            ant?.reset();
        });

        // Al guardar una ficha nueva estando en una de estas pestañas, cargarla ya con su id.
        // La ficha fija el id justo después de emitir el evento, de ahí el setTimeout.
        if (cfg.eventoGuardado) {
            document.addEventListener(cfg.eventoGuardado, () => setTimeout(() => {
                disp?.comprobar(true);
                trx?.refrescarSiVisible();
                ec?.refrescarSiVisible();
                ant?.refrescarSiVisible();
            }, 0));
        }
    }

    window.FichaConsultas = { iniciar };
})(window, document);
