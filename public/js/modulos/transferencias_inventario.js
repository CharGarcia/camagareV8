/**
 * Transferencias de Inventario — listado + modal del documento.
 *
 * El documento es de un solo paso: al registrarlo el servidor mueve el stock
 * (salida en origen + entrada en destino). No hay edición: una transferencia
 * registrada solo se puede anular.
 */
(function () {
    'use strict';

    const URL      = window.TRI_URL_BASE;
    const PERM     = window.TRI_PERM || {};
    const BODEGAS  = window.TRI_BODEGAS || [];

    let ordenCol = window.TRI_ORDEN_COL || 'fecha_transferencia';
    let ordenDir = window.TRI_ORDEN_DIR || 'DESC';
    let pagina   = 1;
    let modalRef = null;
    let lineas   = [];          // líneas en edición
    let seqLinea = 0;
    let soloLectura = false;
    let timerBusquedaProducto = null;

    // ── Utilidades ───────────────────────────────────────────────────────────

    function esc(t) {
        return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function num(v) {
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function money(v) {
        return '$' + num(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function aviso(icono, titulo, texto) {
        if (typeof Swal === 'undefined') { alert(titulo + '\n' + (texto || '')); return; }
        return Swal.fire({ icon: icono, title: titulo, text: texto || '', confirmButtonColor: '#0d6efd' });
    }

    /** Guardar / Actualizar según el documento tenga id o no (mismo criterio que el resto de módulos). */
    function textoBotonGuardar() {
        const hayId = !!(el('tri-id') && el('tri-id').value);
        return hayId
            ? '<i class="bi bi-check-lg me-1"></i> Actualizar'
            : '<i class="bi bi-check-lg me-1"></i> Guardar';
    }

    function getModal() {
        if (!modalRef) modalRef = new bootstrap.Modal(document.getElementById('modalTransferencia'));
        return modalRef;
    }

    function el(id) { return document.getElementById(id); }

    // ── Listado ──────────────────────────────────────────────────────────────

    function paramsFiltros() {
        return new URLSearchParams({
            b:         el('tri-buscar')?.value || '',
            desde:     el('tri-desde')?.value || '',
            hasta:     el('tri-hasta')?.value || '',
            id_bodega: el('tri-bodega')?.value || '',
            estado:    el('tri-estado')?.value || '',
            sort:      ordenCol,
            dir:       ordenDir,
        });
    }

    window.TRI_buscar = function (p) {
        pagina = p || 1;
        const params = paramsFiltros();
        params.set('page', String(pagina));

        const tbody = el('tri-tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';

        fetch(`${URL}/search-ajax?${params.toString()}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                if (tbody) tbody.innerHTML = res.rows;
                if (el('tri-paginacion')) el('tri-paginacion').innerHTML = res.pagination;
                if (el('tri-info')) el('tri-info').textContent = res.info;

                if (res.resumen) {
                    el('tri-stat-documentos').textContent = res.resumen.documentos;
                    el('tri-stat-unidades').textContent   = num(res.resumen.unidades).toFixed(2);
                    el('tri-stat-costo').textContent      = num(res.resumen.costo).toFixed(2);
                    el('tri-stat-inter').textContent      = res.resumen.interestablecimiento;
                }

                document.querySelectorAll('.sortable-header').forEach(th => {
                    const icono = th.querySelector('i');
                    if (!icono) return;
                    icono.className = (th.dataset.col === ordenCol)
                        ? (ordenDir.toUpperCase() === 'ASC' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1')
                        : 'bi bi-arrow-down-up small text-muted ms-1';
                });
            })
            .catch(e => console.error('TRI_buscar', e));
    };

    window.TRI_cambiarPagina = function (p) {
        if (p < 1) return;
        window.TRI_buscar(p);
    };

    window.TRI_ordenar = function (col) {
        if (ordenCol === col) {
            ordenDir = ordenDir.toUpperCase() === 'ASC' ? 'DESC' : 'ASC';
        } else {
            ordenCol = col;
            ordenDir = 'ASC';
        }
        if (navigator.sendBeacon && typeof APP_VISTAS_URL !== 'undefined') {
            const fd = new FormData();
            fd.append('modulo', window.TRI_MODULO);
            fd.append('vistaPayload', JSON.stringify({ __ordenCol__: ordenCol, __ordenDir__: ordenDir }));
            navigator.sendBeacon(APP_VISTAS_URL, fd);
        }
        window.TRI_buscar(1);
    };

    window.TRI_limpiarFiltros = function () {
        ['tri-buscar', 'tri-desde', 'tri-hasta'].forEach(id => { if (el(id)) el(id).value = ''; });
        if (el('tri-bodega')) el('tri-bodega').value = '';
        if (el('tri-estado')) el('tri-estado').value = '';
        window.TRI_buscar(1);
    };

    window.TRI_exportar = function (tipo) {
        const params = paramsFiltros();
        window.open(`${URL}/${tipo === 'pdf' ? 'export-pdf' : 'export-excel'}?${params.toString()}`, '_blank');
    };

    el('tri-buscar')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); window.TRI_buscar(1); }
    });

    // ── Modal: alta ──────────────────────────────────────────────────────────

    function resetModal() {
        soloLectura = false;
        lineas = [];
        seqLinea = 0;

        el('tri-id').value = '';
        el('tri-fecha').value = (typeof CMG_fechaLocal === 'function') ? CMG_fechaLocal() : new Date().toISOString().slice(0, 10);
        el('tri-bodega-origen').value = '';
        el('tri-bodega-destino').value = '';
        el('tri-resp-envia').value = '';
        el('tri-resp-recibe').value = '';
        el('tri-observaciones').value = '';
        el('tri-buscar-producto').value = '';
        el('tri-dropdown-producto').classList.add('d-none');

        el('tri-modal-titulo').textContent = 'Nueva Transferencia';
        el('tri-modal-badge').classList.add('d-none');
        el('tri-acciones').classList.add('d-none');
        el('tri-btn-guia').classList.add('d-none');
        el('tri-guia-emitida').classList.add('d-none');
        el('tri-btn-anular').classList.add('d-none');
        el('tri-btn-eliminar').classList.add('d-none');
        el('tri-btn-guardar').classList.remove('d-none');
        el('tri-btn-guardar').innerHTML = textoBotonGuardar();
        el('tri-zona-agregar').classList.remove('d-none');
        el('tri-info-auditoria').innerHTML = '';
        el('tri-info-auditoria').classList.add('d-none');

        setCamposHabilitados(true);
        actualizarAvisoEstablecimiento();
        render();
    }

    function setCamposHabilitados(habilitado) {
        ['tri-fecha', 'tri-bodega-origen', 'tri-bodega-destino', 'tri-resp-envia', 'tri-resp-recibe', 'tri-observaciones']
            .forEach(id => { if (el(id)) el(id).disabled = !habilitado; });
        el('tri-buscar-producto').disabled = !habilitado || !el('tri-bodega-origen').value;
    }

    window.TRI_nueva = function () {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear transferencias.'); return; }
        resetModal();
        getModal().show();
    };

    function bodegaSeleccionada(id) {
        return BODEGAS.find(b => parseInt(b.id, 10) === parseInt(id, 10)) || null;
    }

    function actualizarAvisoEstablecimiento() {
        const o = bodegaSeleccionada(el('tri-bodega-origen').value);
        const d = bodegaSeleccionada(el('tri-bodega-destino').value);
        const cruza = o && d && o.id_establecimiento && d.id_establecimiento
                   && parseInt(o.id_establecimiento, 10) !== parseInt(d.id_establecimiento, 10);
        el('tri-aviso-establecimiento').classList.toggle('d-none', !cruza);
        return cruza;
    }

    window.TRI_cambioBodega = function (cual) {
        const origen  = el('tri-bodega-origen').value;
        const destino = el('tri-bodega-destino').value;

        if (origen && destino && origen === destino) {
            aviso('warning', 'Bodegas iguales', 'La bodega de origen y la de destino deben ser distintas.');
            if (cual === 'destino') el('tri-bodega-destino').value = '';
            else el('tri-bodega-origen').value = '';
        }

        if (cual === 'origen' && lineas.length) {
            // El stock y los lotes dependen de la bodega de origen: al cambiarla,
            // las líneas ya cargadas dejan de ser válidas.
            lineas = [];
            render();
            aviso('info', 'Productos descartados', 'Al cambiar la bodega de origen se limpian los productos agregados.');
        }

        el('tri-buscar-producto').disabled = !el('tri-bodega-origen').value;
        el('tri-hint-producto').textContent = el('tri-bodega-origen').value
            ? 'Se listan los productos inventariables con su stock en la bodega de origen.'
            : 'Seleccione primero la bodega de origen.';

        actualizarAvisoEstablecimiento();
    };

    // ── Buscador de productos ────────────────────────────────────────────────

    el('tri-buscar-producto')?.addEventListener('input', function () {
        const q = this.value.trim();
        const dd = el('tri-dropdown-producto');
        clearTimeout(timerBusquedaProducto);

        if (q.length < 2) { dd.classList.add('d-none'); return; }

        timerBusquedaProducto = setTimeout(() => {
            const idBodega = el('tri-bodega-origen').value;
            fetch(`${URL}/buscar-productos-ajax?id_bodega=${idBodega}&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(res => {
                    if (!res.ok || !res.data.length) {
                        dd.innerHTML = '<div class="list-group-item small text-muted">Sin resultados.</div>';
                        dd.classList.remove('d-none');
                        return;
                    }
                    dd.innerHTML = res.data.map(p => `
                        <button type="button" class="list-group-item list-group-item-action py-1 small"
                                onclick="window.TRI_agregarProducto(${p.id}, '${esc(p.codigo)}', '${esc(p.nombre)}', ${p.id_medida_base || 'null'}, ${num(p.stock)})">
                            <span class="fw-bold">${esc(p.codigo)}</span> — ${esc(p.nombre)}
                            <span class="badge ${num(p.stock) > 0 ? 'bg-success' : 'bg-secondary'} bg-opacity-10 ${num(p.stock) > 0 ? 'text-success' : 'text-secondary'} border float-end">
                                Stock: ${num(p.stock).toFixed(2)}
                            </span>
                        </button>`).join('');
                    dd.classList.remove('d-none');
                })
                .catch(e => console.error('buscarProductos', e));
        }, 300);
    });

    document.addEventListener('click', e => {
        const dd = el('tri-dropdown-producto');
        if (dd && !dd.contains(e.target) && e.target !== el('tri-buscar-producto')) {
            dd.classList.add('d-none');
        }
    });

    window.TRI_agregarProducto = function (id, codigo, nombre, idMedida, stock) {
        if (num(stock) <= 0) {
            aviso('warning', 'Sin stock', `«${nombre}» no tiene existencias en la bodega de origen.`);
            return;
        }

        const linea = {
            uid: ++seqLinea,
            id_producto: id,
            codigo: codigo,
            nombre: nombre,
            id_medida: idMedida || null,
            cantidad: 1,
            stock: num(stock),
            disponible: num(stock),
            numero_lote: '',
            fecha_caducidad: '',
            nup: '',
            costo_unitario: 0,
            lotes: [],
            series: [],
        };
        lineas.push(linea);

        el('tri-buscar-producto').value = '';
        el('tri-dropdown-producto').classList.add('d-none');
        render();
        cargarLotes(linea.uid);
    };

    function buscarLinea(uid) {
        return lineas.find(l => l.uid === parseInt(uid, 10));
    }

    function cargarLotes(uid) {
        const l = buscarLinea(uid);
        if (!l) return;
        const idBodega = el('tri-bodega-origen').value;

        fetch(`${URL}/get-lotes-ajax?id_producto=${l.id_producto}&id_bodega=${idBodega}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                l.lotes = res.lotes || [];
                l.stock = num(res.stock);
                l.costo_unitario = num(res.costo);

                // Un solo lote disponible: se selecciona solo.
                if (l.lotes.length === 1) {
                    aplicarLote(l, l.lotes[0].numero_lote);
                } else {
                    l.disponible = l.stock;
                }
                render();
                // Series disponibles (solo aparecen si el producto es serializado).
                cargarSeries(l.uid);
            })
            .catch(e => console.error('cargarLotes', e));
    }

    function aplicarLote(l, numeroLote) {
        l.numero_lote = numeroLote || '';
        const lote = (l.lotes || []).find(x => x.numero_lote === numeroLote);
        if (lote) {
            l.disponible = num(lote.stock_lote);
            l.fecha_caducidad = (lote.fecha_caducidad || '').split(' ')[0].split('T')[0] || '';
            if (lote.costo !== undefined) l.costo_unitario = num(lote.costo);
        } else {
            l.disponible = l.stock;
        }
        if (num(l.cantidad) > l.disponible) l.cantidad = l.disponible;
    }

    window.TRI_cambiarLote = function (uid, valor) {
        const l = buscarLinea(uid);
        if (!l) return;
        aplicarLote(l, valor);
        l.nup = '';
        l.series = [];
        render();
        cargarSeries(uid);
    };

    function cargarSeries(uid) {
        const l = buscarLinea(uid);
        if (!l) return;
        const idBodega = el('tri-bodega-origen').value;

        fetch(`${URL}/get-series-ajax?id_producto=${l.id_producto}&id_bodega=${idBodega}&lote=${encodeURIComponent(l.numero_lote || '')}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                l.series = res.series || [];
                render();
            })
            .catch(e => console.error('cargarSeries', e));
    }

    window.TRI_cambiarSerie = function (uid, valor) {
        const l = buscarLinea(uid);
        if (!l) return;
        l.nup = valor || '';
        if (l.nup !== '') {
            // Una serie identifica una unidad concreta.
            l.cantidad = 1;
            const serie = (l.series || []).find(s => s.nup === l.nup);
            if (serie && serie.fecha_caducidad) l.fecha_caducidad = String(serie.fecha_caducidad).split(' ')[0].split('T')[0];
        }
        render();
    };

    window.TRI_cambiarCantidad = function (uid, valor) {
        const l = buscarLinea(uid);
        if (!l) return;
        let cant = num(valor);
        if (cant < 0) cant = 0;
        if (l.nup !== '' && cant !== 1) cant = 1;
        if (cant > l.disponible) {
            cant = l.disponible;
            aviso('warning', 'Cantidad ajustada', `Solo hay ${l.disponible} unidades disponibles de «${l.nombre}» con ese lote.`);
        }
        l.cantidad = cant;
        render();
    };

    window.TRI_cambiarCaducidad = function (uid, valor) {
        const l = buscarLinea(uid);
        if (l) l.fecha_caducidad = valor || '';
    };

    window.TRI_quitarLinea = function (uid) {
        lineas = lineas.filter(l => l.uid !== parseInt(uid, 10));
        render();
    };

    // ── Render de líneas ─────────────────────────────────────────────────────

    function render() {
        const tbody = el('tri-tbody-detalle');
        if (!tbody) return;

        if (!lineas.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Sin productos agregados.</td></tr>';
            el('tri-total-items').textContent = '0.00';
            el('tri-total-costo').textContent = '$0.00';
            return;
        }

        let totalItems = 0;
        let totalCosto = 0;

        tbody.innerHTML = lineas.map((l, idx) => {
            const costoTotal = num(l.cantidad) * num(l.costo_unitario);
            totalItems += num(l.cantidad);
            totalCosto += costoTotal;

            const lotesHtml = soloLectura
                ? esc(l.numero_lote || '—')
                : (l.lotes && l.lotes.length
                    ? `<select onchange="window.TRI_cambiarLote(${l.uid}, this.value)">
                           <option value="">Seleccione el lote…</option>
                           ${l.lotes.map(x => `<option value="${esc(x.numero_lote)}" ${x.numero_lote === l.numero_lote ? 'selected' : ''}>${esc(x.numero_lote === 'sin_lote' ? 'Sin lote' : x.numero_lote)} (${num(x.stock_lote).toFixed(2)})</option>`).join('')}
                       </select>`
                    : '<span class="text-muted">—</span>');

            const seriesHtml = soloLectura
                ? esc(l.nup || '—')
                : (l.series && l.series.length
                    ? `<select onchange="window.TRI_cambiarSerie(${l.uid}, this.value)">
                           <option value="">Sin serie</option>
                           ${l.series.map(s => `<option value="${esc(s.nup)}" ${s.nup === l.nup ? 'selected' : ''}>${esc(s.nup)}</option>`).join('')}
                       </select>`
                    : '<span class="text-muted">—</span>');

            return `<tr>
                <td class="text-center text-muted">${idx + 1}</td>
                <td><span class="fw-bold">${esc(l.codigo)}</span> ${esc(l.nombre)}</td>
                <td>${lotesHtml}</td>
                <td>${soloLectura
                        ? (l.fecha_caducidad ? esc(l.fecha_caducidad.split('-').reverse().join('-')) : '—')
                        : `<input type="date" value="${esc(l.fecha_caducidad)}" onchange="window.TRI_cambiarCaducidad(${l.uid}, this.value)">`}</td>
                <td>${seriesHtml}</td>
                <td class="text-end">${num(l.disponible).toFixed(2)}</td>
                <td class="text-end">${soloLectura
                        ? num(l.cantidad).toFixed(2)
                        : `<input type="number" step="any" min="0" class="text-end" value="${num(l.cantidad)}" onchange="window.TRI_cambiarCantidad(${l.uid}, this.value)">`}</td>
                <td class="text-end">${num(l.costo_unitario).toFixed(4)}</td>
                <td class="text-end">${money(costoTotal)}</td>
                <td class="text-center">${soloLectura ? '' : `<button type="button" class="btn btn-link p-0 text-danger border-0" onclick="window.TRI_quitarLinea(${l.uid})" title="Quitar"><i class="bi bi-trash3"></i></button>`}</td>
            </tr>`;
        }).join('');

        el('tri-total-items').textContent = totalItems.toFixed(2);
        el('tri-total-costo').textContent = money(totalCosto);
    }

    // ── Guardar ──────────────────────────────────────────────────────────────

    window.TRI_guardar = function () {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear transferencias.'); return; }

        const origen  = el('tri-bodega-origen').value;
        const destino = el('tri-bodega-destino').value;

        if (!origen || !destino) { aviso('warning', 'Faltan datos', 'Seleccione la bodega de origen y la de destino.'); return; }
        if (origen === destino)  { aviso('warning', 'Bodegas iguales', 'La bodega de origen y la de destino deben ser distintas.'); return; }
        if (!lineas.length)      { aviso('warning', 'Sin productos', 'Agregue al menos un producto a la transferencia.'); return; }

        const invalida = lineas.find(l => num(l.cantidad) <= 0);
        if (invalida) { aviso('warning', 'Cantidad inválida', `Indique la cantidad a transferir de «${invalida.nombre}».`); return; }

        // Producto con lotes: hay que decir de cuál sale (el servidor lo exige igual).
        const sinLote = lineas.find(l => !l.numero_lote && (l.lotes || []).some(x => x.numero_lote !== 'sin_lote'));
        if (sinLote) { aviso('warning', 'Falta el lote', `Seleccione el lote del que sale «${sinLote.nombre}».`); return; }

        const detalles = lineas.map(l => ({
            id_producto:     l.id_producto,
            id_medida:       l.id_medida,
            cantidad:        num(l.cantidad),
            numero_lote:     l.numero_lote || '',
            fecha_caducidad: l.fecha_caducidad || '',
            nup:             l.nup || '',
            observaciones:   '',
        }));

        const fd = new FormData();
        fd.append('fecha_transferencia', el('tri-fecha').value);
        fd.append('id_bodega_origen', origen);
        fd.append('id_bodega_destino', destino);
        fd.append('responsable_envia', el('tri-resp-envia').value);
        fd.append('responsable_recibe', el('tri-resp-recibe').value);
        fd.append('observaciones', el('tri-observaciones').value);
        fd.append('detalles', JSON.stringify(detalles));

        const btn = el('tri-btn-guardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';

        fetch(`${URL}/guardar-ajax`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { aviso('error', 'No se registró', res.mensaje || 'Error desconocido.'); return; }
                aviso('success', 'Transferencia registrada', res.mensaje);
                window.TRI_buscar(pagina);
                window.TRI_verTransferencia(res.id);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se pudo registrar la transferencia.'); })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = textoBotonGuardar();
            });
    };

    // ── Ver documento existente ──────────────────────────────────────────────

    window.TRI_verTransferencia = function (id) {
        fetch(`${URL}/get-transferencia-ajax?id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { aviso('error', 'Error', res.mensaje); return; }
                cargarDocumento(res.data);
                getModal().show();
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se pudo abrir la transferencia.'); });
    };

    function cargarDocumento(doc) {
        resetModal();
        soloLectura = true;

        const anulada = doc.estado === 'anulada';

        el('tri-id').value = doc.id;
        el('tri-fecha').value = String(doc.fecha_transferencia || '').slice(0, 10);
        el('tri-bodega-origen').value = doc.id_bodega_origen;
        el('tri-bodega-destino').value = doc.id_bodega_destino;
        el('tri-resp-envia').value = doc.responsable_envia || '';
        el('tri-resp-recibe').value = doc.responsable_recibe || '';
        el('tri-observaciones').value = doc.observaciones || '';

        el('tri-modal-titulo').textContent = `Transferencia ${doc.numero}`;
        const badge = el('tri-modal-badge');
        badge.className = anulada
            ? 'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-2'
            : 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2';
        badge.textContent = anulada ? 'Anulada' : 'Registrada';
        badge.classList.remove('d-none');

        const entreEst = doc.entre_establecimientos === true || doc.entre_establecimientos === 't' || doc.entre_establecimientos === 1;
        el('tri-aviso-establecimiento').classList.toggle('d-none', !entreEst);

        lineas = (doc.detalles || []).map(d => ({
            uid: ++seqLinea,
            id_producto: parseInt(d.id_producto, 10),
            codigo: d.producto_codigo || '',
            nombre: d.producto_nombre || '',
            id_medida: d.id_medida,
            cantidad: num(d.cantidad),
            stock: 0,
            disponible: num(d.cantidad),
            numero_lote: d.numero_lote || '',
            fecha_caducidad: (d.fecha_caducidad || '').split(' ')[0].split('T')[0] || '',
            nup: d.nup || '',
            costo_unitario: num(d.costo_unitario),
            lotes: [],
            series: [],
        }));

        setCamposHabilitados(false);
        el('tri-zona-agregar').classList.add('d-none');
        el('tri-btn-guardar').classList.add('d-none');
        el('tri-acciones').classList.remove('d-none');

        if (!anulada) {
            if (PERM.actualizar) el('tri-btn-anular').classList.remove('d-none');
            if (entreEst) {
                if (doc.id_guia_remision) {
                    el('tri-guia-emitida').classList.remove('d-none');
                } else {
                    el('tri-btn-guia').classList.remove('d-none');
                }
            }
        } else if (PERM.eliminar) {
            el('tri-btn-eliminar').classList.remove('d-none');
        }

        el('tri-info-auditoria').innerHTML = `
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">REGISTRÓ</span>${esc(doc.usuario_nombre || '—')}</div>
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">FECHA DE REGISTRO</span>${esc(fechaLarga(doc.created_at))}</div>
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">ÚLTIMA MODIFICACIÓN</span>${esc(fechaLarga(doc.updated_at))}</div>
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">ESTABLECIMIENTO ORIGEN</span>${esc(doc.establecimiento_origen_codigo || '—')} ${esc(doc.establecimiento_origen_nombre || '')}</div>
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">ESTABLECIMIENTO DESTINO</span>${esc(doc.establecimiento_destino_codigo || '—')} ${esc(doc.establecimiento_destino_nombre || '')}</div>
            <div class="col-md-4"><span class="text-muted d-block" style="font-size:.7rem;">UNIDADES / COSTO</span>${num(doc.total_items).toFixed(2)} · ${money(doc.total_costo)}</div>`;
        el('tri-info-auditoria').classList.remove('d-none');

        render();
    }

    function fechaLarga(valor) {
        if (!valor) return '—';
        const f = new Date(String(valor).replace(' ', 'T'));
        if (isNaN(f.getTime())) return String(valor);
        const p = n => String(n).padStart(2, '0');
        return `${p(f.getDate())}-${p(f.getMonth() + 1)}-${f.getFullYear()} ${p(f.getHours())}:${p(f.getMinutes())}:${p(f.getSeconds())}`;
    }

    // ── Acciones sobre el documento ──────────────────────────────────────────

    window.TRI_anular = function () {
        const id = el('tri-id').value;
        if (!id) return;

        Swal.fire({
            icon: 'warning',
            title: '¿Anular la transferencia?',
            html: 'El stock volverá a la bodega de origen.<br>Esta acción no se puede deshacer.',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${URL}/anular-ajax`, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (!res.ok) { aviso('error', 'No se pudo anular', res.mensaje); return; }
                    aviso('success', 'Transferencia anulada', res.mensaje);
                    getModal().hide();
                    window.TRI_buscar(pagina);
                });
        });
    };

    window.TRI_eliminar = function () {
        const id = el('tri-id').value;
        if (!id) return;

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la transferencia anulada?',
            text: 'Dejará de aparecer en el listado. El kardex conserva el registro.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then(r => {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${URL}/eliminar-ajax`, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (!res.ok) { aviso('error', 'No se pudo eliminar', res.mensaje); return; }
                    aviso('success', 'Transferencia eliminada', res.mensaje);
                    getModal().hide();
                    window.TRI_buscar(1);
                });
        });
    };

    window.TRI_abrirPdf = function () {
        const id = el('tri-id').value;
        if (id) window.open(`${URL}/pdf-documento?id=${id}`, '_blank');
    };

    window.TRI_generarGuia = function () {
        const id = el('tri-id').value;
        if (!id) return;

        Swal.fire({
            icon: 'question',
            title: 'Generar guía de remisión',
            html: 'Se abrirá el módulo de <strong>Guías de Remisión</strong> con los productos, direcciones y motivo ya cargados.<br>'
                + 'Deberá completar el destinatario, el transportista y la placa antes de emitirla.',
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
        }).then(r => {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${URL}/preparar-guia-ajax`, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (!res.ok) { aviso('error', 'Error', res.mensaje); return; }
                    window.location.href = res.url;
                });
        });
    };
})();
