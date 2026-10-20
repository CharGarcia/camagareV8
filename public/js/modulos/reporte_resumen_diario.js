/* Resumen Diario: ventas, compras, ingresos, egresos y traslados de un día.
   El servidor arma el HTML (ReporteResumenDiarioController::generarAjax); aquí se piden
   los datos, se pintan los indicadores, se enlazan PDF/Excel y se manejan los modales de
   traslados entre formas de pago y saldos de apertura. */
(function () {
    const $ = id => document.getElementById(id);
    const RUTA = window.RRD_RUTA;
    const BASE = window.RRD_BASE;
    const URL_MOD = `${BASE}/${RUTA}`;
    const money = n => {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const fechaTexto = iso => iso.split('-').reverse().join('-');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function post(accion, datos) {
        const res = await fetch(`${URL_MOD}/${accion}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(datos).toString()
        });
        return res.json();
    }

    let consulta = 0; // descarta respuestas viejas si el usuario cambia de día rápido

    /** Usuario elegido en el selector (solo existe con acceso total); '' = todos. */
    const usuarioElegido = () => ($('rrd-usuario') ? $('rrd-usuario').value : '');

    /** Fecha y usuario de la caja que se ve: los modales abren/cierran exactamente esa. */
    let cajaActual = null;

    /**
     * Estado de la caja (solo en «Cierre de caja»): etiqueta y botones Abrir / Cerrar /
     * Reabrir según esté sin abrir, abierta o cerrada. En los otros modos, todo oculto.
     */
    function pintarEstadoCaja(c, fecha) {
        const badge = $('rrd-caja-estado');
        const ver = (id, si) => { if ($(id)) $(id).classList.toggle('d-none', !si); };
        const fh = s => {
            if (!s) return '';
            const [d, t] = String(s).split(/[ T]/);
            return fechaTexto(d) + ' ' + String(t || '').slice(0, 8);
        };
        const activo = !!(c && c.disponible);
        cajaActual = activo ? { fecha, usuario: usuarioElegido() } : null;
        ver('rrdBtnAbrirCaja', activo && c.estado === 'sin_abrir');
        ver('rrdBtnCerrarCaja', activo && c.estado === 'abierta');
        ver('rrdBtnReabrirCaja', activo && c.estado === 'cerrada');
        ver('rrd-stat-contado', activo && c.estado === 'cerrada');
        ver('rrd-stat-diferencia', activo && c.estado === 'cerrada');
        badge.classList.toggle('d-none', !activo);
        if (!activo) return;

        const est = {
            sin_abrir: ['bg-secondary', 'Caja sin abrir', ''],
            abierta:   ['bg-success', 'Caja abierta', `Abierta por ${c.abierta_por} el ${fh(c.fecha_apertura)}`],
            cerrada:   ['bg-dark', 'Caja cerrada', `Cerrada por ${c.cerrada_por} el ${fh(c.fecha_cierre)}`]
        }[c.estado];
        badge.className = `badge ${est[0]}`;
        badge.textContent = est[1];
        badge.title = est[2];
        if (c.estado === 'cerrada') {
            $('rrd-kpi-contado').textContent = money(c.contado);
            $('rrd-kpi-diferencia').textContent = money(c.diferencia);
            $('rrd-kpi-diferencia').classList.toggle('text-danger', c.diferencia < 0);
            $('rrd-kpi-diferencia').classList.toggle('text-success', c.diferencia > 0);
        }
    }

    /** Texto del alcance para el título de los modales. */
    function tituloCaja() {
        const sel = $('rrd-usuario');
        const quien = sel && sel.value ? ' · ' + sel.options[sel.selectedIndex].text : '';
        return `— ${fechaTexto(cajaActual.fecha)}${quien}`;
    }

    async function formasCaja(tipo) {
        const params = new URLSearchParams({ tipo, fecha: cajaActual.fecha, usuario: cajaActual.usuario });
        const res = await fetch(`${URL_MOD}/cajaFormasAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return res.json();
    }

    async function mostrar() {
        const fecha = $('rrd-fecha').value;
        const cont = $('rrd-contenido');
        if (!fecha) {
            cont.innerHTML = '<div class="alert alert-warning m-3 mb-0">Elija el día del resumen.</div>';
            return;
        }
        const id = ++consulta;
        cont.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Armando el resumen…</div>';
        $('rrdBtnPdf').disabled = true;
        $('rrdBtnExcel').disabled = true;
        pintarEstadoCaja(null, fecha); // sin botones de caja hasta saber el estado del día

        try {
            const params = new URLSearchParams({ fecha, borradores: $('rrd-borradores').value, saldos: $('rrd-saldos').value, usuario: usuarioElegido() });
            const res = await fetch(`${URL_MOD}/generarAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (id !== consulta) return;
            if (!json.ok) {
                cont.innerHTML = `<div class="alert alert-warning m-3 mb-0">${esc(json.mensaje || 'No se pudo generar el resumen.')}</div>`;
                return;
            }
            cont.innerHTML = json.html;
            const k = json.kpis;
            $('rrd-titulo-dia').textContent = 'Día ' + fechaTexto(fecha);
            $('rrd-kpi-ventas').textContent   = money(k.ventas_netas);
            $('rrd-kpi-compras').textContent  = money(k.compras_netas);
            $('rrd-kpi-ingresos').textContent = money(k.ingresos);
            $('rrd-kpi-egresos').textContent  = money(k.egresos);
            $('rrd-kpi-neto').textContent     = money(k.neto_caja);
            $('rrd-kpi-neto').classList.toggle('text-primary', k.neto_caja >= 0);
            $('rrd-kpi-neto').classList.toggle('text-danger', k.neto_caja < 0);
            $('rrd-stat-saldo').classList.toggle('d-none', k.saldo_final === null);
            if (k.saldo_final !== null) {
                $('rrd-kpi-saldo').textContent = money(k.saldo_final);
                $('rrd-kpi-saldo').classList.toggle('text-danger', k.saldo_final < 0);
            }
            $('rrd-kpi-saldo-lbl').textContent = json.cierre ? 'Saldo esperado' : 'Saldo final';
            pintarEstadoCaja(json.cierre, fecha);

            $('rrdBtnPdf').disabled = false;
            $('rrdBtnExcel').disabled = false;
            $('rrdBtnPdf').onclick   = () => CMG_descargar(json.pdf_url);
            $('rrdBtnExcel').onclick = () => CMG_descargar(json.excel_url);
        } catch (e) {
            if (id === consulta) {
                cont.innerHTML = '<div class="alert alert-danger m-3 mb-0">Error de comunicación con el servidor.</div>';
            }
        }
    }

    /** Mueve el día ±1 y vuelve a consultar. */
    function moverDia(delta) {
        const v = $('rrd-fecha').value || window.RRD_HOY;
        const d = new Date(v + 'T12:00:00');
        d.setDate(d.getDate() + delta);
        const p = n => String(n).padStart(2, '0');
        $('rrd-fecha').value = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        mostrar();
    }

    $('rrdBtnMostrar').addEventListener('click', mostrar);
    $('rrdBtnAnterior').addEventListener('click', () => moverDia(-1));
    $('rrdBtnSiguiente').addEventListener('click', () => moverDia(1));
    $('rrdBtnLimpiar').addEventListener('click', () => {
        $('rrd-fecha').value = window.RRD_HOY;
        $('rrd-borradores').value = '';
        $('rrd-saldos').value = '';
        if ($('rrd-usuario')) $('rrd-usuario').value = '';
        mostrar();
    });
    // Cambiar de modo de caja o de usuario vuelve a consultar (los botones de caja dependen de eso).
    $('rrd-saldos').addEventListener('change', mostrar);
    if ($('rrd-usuario')) $('rrd-usuario').addEventListener('change', mostrar);

    // ── Traslados entre formas de pago ─────────────────────────────────────────
    // Un guardado = un registro (§8): bandera "en curso" antes del primer await y una
    // clave de formulario por traslado nuevo, que el servidor usa para no duplicar.
    let guardandoTraslado = false;
    let tokenTraslado = '';

    if ($('rrdBtnTraslado')) {
        $('rrdBtnTraslado').addEventListener('click', () => {
            $('rrd-tr-fecha').value = $('rrd-fecha').value || window.RRD_HOY;
            ['rrd-tr-valor', 'rrd-tr-obs', 'rrd-tr-origen', 'rrd-tr-destino'].forEach(i => { $(i).value = ''; });
            tokenTraslado = CMG_nuevoTokenGuardado();
            $('rrdBtnGuardarTraslado').disabled = false;
            bootstrap.Modal.getOrCreateInstance('#rrdModalTraslado').show();
        });

        $('rrdBtnGuardarTraslado').addEventListener('click', async () => {
            if (guardandoTraslado) return;
            guardandoTraslado = true;
            const btn = $('rrdBtnGuardarTraslado');
            btn.disabled = true;
            let creado = false;
            try {
                const json = await post('guardarTrasladoAjax', {
                    fecha: $('rrd-tr-fecha').value,
                    id_forma_origen: $('rrd-tr-origen').value,
                    id_forma_destino: $('rrd-tr-destino').value,
                    valor: $('rrd-tr-valor').value,
                    observaciones: $('rrd-tr-obs').value,
                    token_guardado: tokenTraslado
                });
                if (!json.ok) {
                    Swal.fire({ icon: 'warning', title: 'No se guardó', text: json.mensaje || 'Error desconocido.' });
                    return;
                }
                creado = true;
                bootstrap.Modal.getOrCreateInstance('#rrdModalTraslado').hide();
                Swal.fire({ icon: 'success', title: json.mensaje, timer: 1800, showConfirmButton: false });
                await mostrar();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de comunicación', text: 'Si el traslado se guardó, al reintentar no se duplica.' });
            } finally {
                guardandoTraslado = false;
                // Tras crear, el botón queda desactivado hasta abrir un traslado nuevo.
                if (!creado) btn.disabled = false;
            }
        });
    }

    // Eliminar traslado (botón dentro de la tabla que llega por AJAX).
    $('rrd-contenido').addEventListener('click', async ev => {
        const btn = ev.target.closest('.rrd-eliminar-traslado');
        if (!btn || !window.RRD_PUEDE_ELIMINAR) return;
        const ok = await Swal.fire({
            icon: 'question', title: '¿Eliminar el traslado?', text: 'Se quita del resumen y del saldo de las formas de pago.',
            showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545'
        });
        if (!ok.isConfirmed) return;
        btn.disabled = true;
        try {
            const json = await post('eliminarTrasladoAjax', { id: btn.dataset.id });
            if (!json.ok) {
                btn.disabled = false;
                Swal.fire({ icon: 'warning', title: 'No se eliminó', text: json.mensaje });
                return;
            }
            mostrar();
        } catch (e) {
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Error de comunicación' });
        }
    });

    // ── Saldos de apertura ─────────────────────────────────────────────────────
    let guardandoApertura = false;

    if ($('rrdBtnApertura')) {
        $('rrdBtnApertura').addEventListener('click', async () => {
            const tbody = $('rrd-ap-tbody');
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Cargando…</td></tr>';
            $('rrdBtnGuardarApertura').disabled = true;
            bootstrap.Modal.getOrCreateInstance('#rrdModalApertura').show();
            try {
                const res = await fetch(`${URL_MOD}/aperturasAjax`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json();
                if (!json.ok) {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-3">${esc(json.mensaje)}</td></tr>`;
                    return;
                }
                if (!json.formas.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">La empresa no tiene formas de pago activas.</td></tr>';
                    return;
                }
                tbody.innerHTML = json.formas.map(f => `
                    <tr data-forma="${f.id_forma_pago}">
                        <td class="py-1">${esc(f.forma)}</td>
                        <td class="p-0"><input type="date" class="form-control form-control-sm rrd-ap-fecha" value="${esc(f.fecha)}"
                                style="padding:0 4px;height:24px;font-size:0.78rem;"></td>
                        <td class="p-0"><input type="number" step="0.01" class="form-control form-control-sm text-end rrd-ap-valor"
                                value="${f.valor === null ? '' : Number(f.valor).toFixed(2)}" placeholder="0.00"
                                style="padding:0 4px;height:24px;font-size:0.78rem;"></td>
                    </tr>`).join('');
                $('rrdBtnGuardarApertura').disabled = false;
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Error de comunicación.</td></tr>';
            }
        });

        $('rrdBtnGuardarApertura').addEventListener('click', async () => {
            if (guardandoApertura) return;
            guardandoApertura = true;
            const btn = $('rrdBtnGuardarApertura');
            btn.disabled = true;
            try {
                const filas = [...document.querySelectorAll('#rrd-ap-tbody tr[data-forma]')].map(tr => ({
                    id_forma_pago: tr.dataset.forma,
                    fecha: tr.querySelector('.rrd-ap-fecha').value,
                    valor: tr.querySelector('.rrd-ap-valor').value || 0
                }));
                const json = await post('guardarAperturasAjax', { aperturas: JSON.stringify(filas) });
                if (!json.ok) {
                    Swal.fire({ icon: 'warning', title: 'No se guardó', text: json.mensaje });
                    return;
                }
                bootstrap.Modal.getOrCreateInstance('#rrdModalApertura').hide();
                Swal.fire({ icon: 'success', title: json.mensaje, timer: 1800, showConfirmButton: false });
                mostrar();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de comunicación' });
            } finally {
                guardandoApertura = false;
                btn.disabled = false;
            }
        });
    }

    // ── Abrir caja ─────────────────────────────────────────────────────────────
    // Un guardado = un registro (§8): bandera "en curso" y una clave de formulario por
    // apertura; un reintento tras un error de conexión no abre otra.
    const inputCelda = (cls, val) => `<input type="number" step="0.01" class="form-control form-control-sm text-end ${cls}"
            value="${val}" placeholder="0.00" style="padding:0 4px;height:24px;font-size:0.78rem;">`;
    let guardandoCaja = false;
    let tokenAbrir = '';

    if ($('rrdBtnAbrirCaja')) {
        $('rrdBtnAbrirCaja').addEventListener('click', async () => {
            if (!cajaActual) return;
            const tbody = $('rrd-ac-tbody');
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Cargando…</td></tr>';
            $('rrd-ac-titulo').textContent = tituloCaja();
            $('rrd-ac-obs').value = '';
            $('rrdBtnGuardarAbrirCaja').disabled = true;
            tokenAbrir = CMG_nuevoTokenGuardado();
            bootstrap.Modal.getOrCreateInstance('#rrdModalAbrirCaja').show();
            try {
                const json = await formasCaja('abrir');
                if (!json.ok) {
                    tbody.innerHTML = `<tr><td colspan="2" class="text-center text-danger py-3">${esc(json.mensaje)}</td></tr>`;
                    return;
                }
                if (!json.formas.length) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">La empresa no tiene formas de pago activas.</td></tr>';
                    return;
                }
                tbody.innerHTML = json.formas.map(f => `
                    <tr data-forma="${f.id_forma_pago}">
                        <td class="py-1">${esc(f.forma)}</td>
                        <td class="p-0">${inputCelda('rrd-ac-saldo', Number(f.saldo) ? Number(f.saldo).toFixed(2) : '')}</td>
                    </tr>`).join('');
                $('rrdBtnGuardarAbrirCaja').disabled = false;
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error de comunicación.</td></tr>';
            }
        });

        $('rrdBtnGuardarAbrirCaja').addEventListener('click', async () => {
            if (guardandoCaja || !cajaActual) return;
            guardandoCaja = true;
            const btn = $('rrdBtnGuardarAbrirCaja');
            btn.disabled = true;
            let abierta = false;
            try {
                const saldos = [...document.querySelectorAll('#rrd-ac-tbody tr[data-forma]')].map(tr => ({
                    id_forma_pago: tr.dataset.forma,
                    saldo: tr.querySelector('.rrd-ac-saldo').value
                }));
                const json = await post('abrirCajaAjax', {
                    fecha: cajaActual.fecha, usuario: cajaActual.usuario, saldos: JSON.stringify(saldos),
                    observaciones: $('rrd-ac-obs').value, token_guardado: tokenAbrir
                });
                if (!json.ok) {
                    Swal.fire({ icon: 'warning', title: 'No se abrió la caja', text: json.mensaje });
                    return;
                }
                abierta = true;
                bootstrap.Modal.getOrCreateInstance('#rrdModalAbrirCaja').hide();
                Swal.fire({ icon: 'success', title: json.mensaje, timer: 1800, showConfirmButton: false });
                await mostrar();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de comunicación', text: 'Si la caja se abrió, al reintentar no se abre otra.' });
            } finally {
                guardandoCaja = false;
                // Tras abrir, el botón queda desactivado hasta volver a abrir el modal.
                if (!abierta) btn.disabled = false;
            }
        });
    }

    // ── Cerrar caja (arqueo) ───────────────────────────────────────────────────
    function totalesCierre() {
        let esp = 0, cont = 0, completo = true;
        document.querySelectorAll('#rrd-cc-tbody tr[data-forma]').forEach(tr => {
            const e = parseFloat(tr.dataset.esperado) || 0;
            const raw = tr.querySelector('.rrd-cc-contado').value;
            const c = raw === '' ? null : (parseFloat(raw) || 0);
            esp += e;
            if (c === null) { completo = false; tr.querySelector('.rrd-cc-dif').textContent = ''; return; }
            cont += c;
            const dif = CMG_r2(c - e);
            const td = tr.querySelector('.rrd-cc-dif');
            td.textContent = money(dif);
            td.className = 'py-1 text-end text-nowrap rrd-cc-dif ' + (dif < 0 ? 'text-danger' : (dif > 0 ? 'text-success' : ''));
        });
        $('rrd-cc-tot-esp').textContent = money(CMG_r2(esp));
        $('rrd-cc-tot-cont').textContent = completo ? money(CMG_r2(cont)) : '';
        $('rrd-cc-tot-dif').textContent = completo ? money(CMG_r2(cont - esp)) : '';
    }

    if ($('rrdBtnCerrarCaja')) {
        $('rrdBtnCerrarCaja').addEventListener('click', async () => {
            if (!cajaActual) return;
            const tbody = $('rrd-cc-tbody');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Cargando…</td></tr>';
            $('rrd-cc-titulo').textContent = tituloCaja();
            $('rrd-cc-obs').value = '';
            ['rrd-cc-tot-esp', 'rrd-cc-tot-cont', 'rrd-cc-tot-dif'].forEach(i => { $(i).textContent = ''; });
            $('rrdBtnGuardarCerrarCaja').disabled = true;
            bootstrap.Modal.getOrCreateInstance('#rrdModalCerrarCaja').show();
            try {
                const json = await formasCaja('cerrar');
                if (!json.ok) {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">${esc(json.mensaje)}</td></tr>`;
                    return;
                }
                if (!json.formas.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">La caja no tiene saldos ni movimientos que cerrar.</td></tr>';
                    return;
                }
                tbody.innerHTML = json.formas.map(f => `
                    <tr data-forma="${f.id_forma}" data-esperado="${f.esperado}">
                        <td class="py-1">${esc(f.forma)}</td>
                        <td class="py-1 text-end text-nowrap">${money(f.esperado)}</td>
                        <td class="p-0">${inputCelda('rrd-cc-contado', '')}</td>
                        <td class="py-1 text-end text-nowrap rrd-cc-dif"></td>
                    </tr>`).join('');
                totalesCierre();
                $('rrdBtnGuardarCerrarCaja').disabled = false;
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Error de comunicación.</td></tr>';
            }
        });

        $('rrd-cc-tbody').addEventListener('input', totalesCierre);
        $('rrdBtnCopiarEsperado').addEventListener('click', () => {
            document.querySelectorAll('#rrd-cc-tbody tr[data-forma]').forEach(tr => {
                tr.querySelector('.rrd-cc-contado').value = (parseFloat(tr.dataset.esperado) || 0).toFixed(2);
            });
            totalesCierre();
        });

        $('rrdBtnGuardarCerrarCaja').addEventListener('click', async () => {
            if (guardandoCaja || !cajaActual) return;
            guardandoCaja = true;
            const btn = $('rrdBtnGuardarCerrarCaja');
            btn.disabled = true;
            let cerrada = false;
            try {
                const contados = [...document.querySelectorAll('#rrd-cc-tbody tr[data-forma]')].map(tr => ({
                    id_forma_pago: tr.dataset.forma,
                    contado: tr.querySelector('.rrd-cc-contado').value
                }));
                const json = await post('cerrarCajaAjax', {
                    fecha: cajaActual.fecha, usuario: cajaActual.usuario,
                    contados: JSON.stringify(contados), observaciones: $('rrd-cc-obs').value
                });
                if (!json.ok) {
                    Swal.fire({ icon: 'warning', title: 'No se cerró la caja', text: json.mensaje });
                    return;
                }
                cerrada = true;
                bootstrap.Modal.getOrCreateInstance('#rrdModalCerrarCaja').hide();
                const t = json.totales;
                Swal.fire({ icon: 'success', title: json.mensaje,
                    text: `Esperado ${money(t.esperado)} · Contado ${money(t.contado)} · Diferencia ${money(t.diferencia)}` });
                await mostrar();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de comunicación', text: 'Vuelva a mostrar el día para ver si la caja quedó cerrada.' });
            } finally {
                guardandoCaja = false;
                if (!cerrada) btn.disabled = false;
            }
        });
    }

    // ── Reabrir caja (acceso total) ────────────────────────────────────────────
    if ($('rrdBtnReabrirCaja')) {
        $('rrdBtnReabrirCaja').addEventListener('click', async () => {
            if (!cajaActual || guardandoCaja) return;
            const r = await Swal.fire({
                icon: 'warning', title: '¿Reabrir la caja?',
                text: 'Se borra el arqueo (lo contado y la diferencia); el saldo inicial se conserva. Queda registrado quién la reabrió.',
                input: 'text', inputPlaceholder: 'Motivo', inputValidator: v => (!v || !v.trim()) ? 'Indique el motivo.' : undefined,
                showCancelButton: true, confirmButtonText: 'Reabrir', cancelButtonText: 'Cancelar'
            });
            if (!r.isConfirmed) return;
            guardandoCaja = true;
            try {
                const json = await post('reabrirCajaAjax', { fecha: cajaActual.fecha, usuario: cajaActual.usuario, motivo: r.value });
                if (!json.ok) {
                    Swal.fire({ icon: 'warning', title: 'No se reabrió la caja', text: json.mensaje });
                    return;
                }
                Swal.fire({ icon: 'success', title: json.mensaje, timer: 1800, showConfirmButton: false });
                await mostrar();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de comunicación' });
            } finally {
                guardandoCaja = false;
            }
        });
    }

    mostrar(); // primera carga: hoy
})();
