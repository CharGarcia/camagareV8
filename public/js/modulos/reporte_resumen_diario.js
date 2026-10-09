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

        try {
            const params = new URLSearchParams({ fecha, borradores: $('rrd-borradores').value, saldos: $('rrd-saldos').value });
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
        mostrar();
    });

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

    mostrar(); // primera carga: hoy
})();
