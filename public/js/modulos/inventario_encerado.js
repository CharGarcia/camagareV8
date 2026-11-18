/**
 * «Encerar existencias» de Movimientos de Inventario (solo niveles 2 y 3).
 * Vista: app/views/modulos/inventario/_modal_encerado.php.
 * Endpoints: InventarioController::enceradoPreviaAjax / encerarAjax / enceradosListarAjax / enceradoRevertirAjax.
 */
(function () {
    'use strict';
    if (window.__CMG_inventarioEncerado) return;
    window.__CMG_inventarioEncerado = true;

    const url = () => window.INV_ENCERADO_URL;
    const $ = (id) => document.getElementById(id);

    let tokenGuardado = '';
    let previaVigente = false;   // la vista previa corresponde a lo que hay en el formulario
    let previaConDatos = false;  // la vista previa encontró algo que encerar
    let enCurso = false;         // bandera anti doble clic (§8)

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (v, d = 2) => Number(v || 0).toLocaleString('es-EC', { minimumFractionDigits: d, maximumFractionDigits: d });
    const cant = (v) => Number(v || 0).toLocaleString('es-EC', { maximumFractionDigits: 4 });

    function fechaHora(s) {
        if (!s) return '';
        const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);
        if (!m) return s;
        return `${m[3]}-${m[2]}-${m[1]}` + (m[4] ? ` ${m[4]}:${m[5]}:${m[6]}` : '');
    }

    function datosFormulario() {
        const fd = new FormData();
        fd.append('fecha_corte', $('encFecha').value);
        fd.append('observacion', $('encObs').value.trim());
        const todas = $('encTodas').checked;
        fd.append('todas_bodegas', todas ? '1' : '0');
        if (!todas) {
            document.querySelectorAll('#encListaBodegas .enc-bodega:checked').forEach((c) => fd.append('ids_bodegas[]', c.value));
        }
        return fd;
    }

    function invalidarPrevia() {
        previaVigente = false;
        previaConDatos = false;
        $('encPrevia').classList.add('d-none');
        $('encConfirmar').value = '';
        actualizarBotonAplicar();
    }

    function actualizarBotonAplicar() {
        $('btnEncAplicar').disabled = enCurso || !previaVigente || !previaConDatos
            || $('encConfirmar').value.trim().toUpperCase() !== 'ENCERAR';
    }

    function toggleTodas() {
        const todas = $('encTodas').checked;
        document.querySelectorAll('#encListaBodegas .enc-bodega').forEach((c) => { c.disabled = todas; if (todas) c.checked = false; });
    }

    function kpi(icono, color, valor, etiqueta) {
        return `<div class="enc-kpi border rounded px-2 py-1 d-flex align-items-center gap-2 bg-white">
                    <span class="badge bg-${color} bg-opacity-10 text-${color} p-2"><i class="bi ${icono}"></i></span>
                    <div><div class="fw-bold small">${valor}</div><div class="text-muted" style="font-size:.7rem">${etiqueta}</div></div>
                </div>`;
    }

    async function previsualizar() {
        if (enCurso) return;
        enCurso = true;
        const btn = $('btnEncPrevia');
        btn.disabled = true;
        try {
            const resp = await fetch(`${url()}/enceradoPreviaAjax`, { method: 'POST', body: datosFormulario() });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'warning', title: 'Revise los datos', text: json.mensaje || 'No se pudo previsualizar.' });
                return;
            }
            const d = json.data;
            $('encKpis').innerHTML =
                kpi('bi-box-seam', 'primary', d.total_productos, 'Productos / bodega') +
                kpi('bi-list-ol', 'secondary', d.total_movimientos, 'Movimientos') +
                kpi('bi-box-arrow-right', 'danger', cant(d.unidades_salida), 'Unidades que salen') +
                kpi('bi-box-arrow-in-left', 'success', cant(d.unidades_entrada), 'Unidades que entran (saldos negativos)') +
                kpi('bi-cash-coin', 'warning', '$ ' + num(d.valor_salida), 'Valor de las salidas');

            $('encDetalle').innerHTML = d.detalle.length
                ? d.detalle.map((r) => `<tr>
                        <td class="ps-2">${esc(r.bodega)}</td>
                        <td>${esc(r.producto)}</td>
                        <td>${esc(r.lote || '')}</td>
                        <td>${esc(r.nup || '')}</td>
                        <td class="text-end ${r.saldo < 0 ? 'text-danger' : ''}">${cant(r.saldo)}</td>
                        <td class="text-end">${num(r.costo, 4)}</td>
                        <td class="pe-2">${r.saldo > 0
                            ? '<span class="badge bg-danger bg-opacity-10 text-danger">Salida ' + cant(r.saldo) + '</span>'
                            : '<span class="badge bg-success bg-opacity-10 text-success">Entrada ' + cant(-r.saldo) + '</span>'}</td>
                    </tr>`).join('')
                : '<tr><td colspan="7" class="text-center text-muted py-3">No hay existencias distintas de cero a esa fecha: no hay nada que encerar.</td></tr>';

            let aviso = d.detalle_parcial ? `Se muestran los primeros ${d.detalle.length} de ${d.total_movimientos} saldos; se encerarán todos.` : '';
            if (d.excede_tope) aviso += ` Son más de ${d.max_pares} productos/bodega: encere menos bodegas a la vez.`;
            $('encAvisoDetalle').textContent = aviso.trim();

            previaVigente = true;
            previaConDatos = d.total_movimientos > 0 && !d.excede_tope;
            $('encPrevia').classList.remove('d-none');
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
        } finally {
            enCurso = false;
            btn.disabled = false;
            actualizarBotonAplicar();
        }
    }

    async function aplicar() {
        if (enCurso || !previaVigente || !previaConDatos) return;
        enCurso = true;
        actualizarBotonAplicar();
        let creado = false;
        try {
            const fd = datosFormulario();
            fd.append('token_guardado', tokenGuardado);
            const resp = await fetch(`${url()}/encerarAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se enceró', text: json.mensaje || 'No se pudo encerar.' });
                return;
            }
            creado = true;
            await Swal.fire({ icon: 'success', title: 'Encerado aplicado', text: json.mensaje });
            // Formulario listo para otro encerado, con su propia clave de guardado.
            nuevoFormulario();
            bootstrap.Tab.getOrCreateInstance($('enc-tab-historial')).show();
            await cargarHistorial();
            if (typeof window.fetchSearch === 'function') window.fetchSearch(1);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor. Revise el Historial antes de reintentar: el encerado pudo haberse registrado.' });
        } finally {
            enCurso = false;
            if (!creado) actualizarBotonAplicar();
        }
    }

    function filaHistorial(r) {
        const revertido = r.estado === 'revertido';
        const bodegas = r.todas_bodegas === true || r.todas_bodegas === 't'
            ? '<span class="badge bg-secondary bg-opacity-10 text-secondary">Todas</span> ' + esc(r.bodegas_nombres || '')
            : esc(r.bodegas_nombres || '');
        const estado = revertido
            ? `<span class="badge bg-secondary bg-opacity-10 text-secondary" title="${esc((r.revertido_por || '') + ' · ' + fechaHora(r.revertido_at) + ' · ' + (r.motivo_reversion || ''))}">Revertido</span>`
            : '<span class="badge bg-success bg-opacity-10 text-success">Aplicado</span>';
        const accion = revertido ? ''
            : `<button type="button" class="btn btn-outline-danger btn-sm py-0" data-revertir="${r.id}" title="Anular todos los movimientos de este encerado">
                   <i class="bi bi-arrow-counterclockwise"></i> Revertir</button>`;
        return `<tr class="${revertido ? 'text-muted' : ''}">
                    <td class="ps-2">${r.id}</td>
                    <td class="text-nowrap">${fechaHora(r.fecha_corte)}</td>
                    <td style="max-width:220px">${bodegas}</td>
                    <td class="text-end">${r.total_movimientos}</td>
                    <td class="text-end">${cant(r.unidades_salida)}</td>
                    <td class="text-end">${cant(r.unidades_entrada)}</td>
                    <td style="max-width:240px">${esc(r.observacion)}</td>
                    <td class="text-nowrap">${esc(r.creado_por || '')}<br><span class="text-muted">${fechaHora(r.created_at)}</span></td>
                    <td>${estado}</td>
                    <td class="pe-2 text-nowrap">${accion}</td>
                </tr>`;
    }

    async function cargarHistorial() {
        const tbody = $('encHistorial');
        try {
            const resp = await fetch(`${url()}/enceradosListarAjax`);
            const json = await resp.json();
            if (!json.ok) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-3">${esc(json.mensaje || 'No se pudo cargar.')}</td></tr>`;
                return;
            }
            tbody.innerHTML = json.rows.length
                ? json.rows.map(filaHistorial).join('')
                : '<tr><td colspan="10" class="text-center text-muted py-3">Todavía no hay encerados.</td></tr>';
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Error de conexión con el servidor.</td></tr>';
        }
    }

    let revirtiendo = false;
    async function revertir(id, boton) {
        if (revirtiendo) return;
        const r = await Swal.fire({
            icon: 'warning',
            title: `¿Revertir el encerado #${id}?`,
            html: 'Se anulan todos sus movimientos y el stock vuelve a como estaba antes del encerado.',
            input: 'textarea',
            inputLabel: 'Motivo de la reversión',
            inputAttributes: { maxlength: 300 },
            showCancelButton: true,
            confirmButtonText: 'Revertir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            inputValidator: (v) => (!v || !v.trim()) ? 'Escriba el motivo.' : undefined,
        });
        if (!r.isConfirmed) return;

        revirtiendo = true;
        if (boton) boton.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('motivo', r.value.trim());
            const resp = await fetch(`${url()}/enceradoRevertirAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se revirtió', text: json.mensaje || 'No se pudo revertir.' });
                return;
            }
            Swal.fire({ icon: 'success', title: 'Encerado revertido', text: json.mensaje, timer: 1800, showConfirmButton: false });
            await cargarHistorial();
            if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
        } finally {
            revirtiendo = false;
            if (boton) boton.disabled = false;
        }
    }

    function nuevoFormulario() {
        tokenGuardado = window.CMG_nuevoTokenGuardado ? window.CMG_nuevoTokenGuardado() : String(Date.now());
        $('encObs').value = '';
        $('encTodas').checked = false;
        document.querySelectorAll('#encListaBodegas .enc-bodega').forEach((c) => { c.checked = false; c.disabled = false; });
        invalidarPrevia();
    }

    window.INV_abrirEncerado = function () {
        nuevoFormulario();
        bootstrap.Tab.getOrCreateInstance($('enc-tab-nuevo')).show();
        bootstrap.Modal.getOrCreateInstance($('modalEncerado')).show();
        cargarHistorial();
    };

    function iniciar() {
        const modal = $('modalEncerado');
        if (!modal) return;
        $('encTodas').addEventListener('change', () => { toggleTodas(); invalidarPrevia(); });
        $('encFecha').addEventListener('change', invalidarPrevia);
        $('encListaBodegas').addEventListener('change', invalidarPrevia);
        $('encConfirmar').addEventListener('input', actualizarBotonAplicar);
        $('btnEncPrevia').addEventListener('click', previsualizar);
        $('btnEncAplicar').addEventListener('click', aplicar);
        $('enc-tab-historial').addEventListener('shown.bs.tab', cargarHistorial);
        $('encHistorial').addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-revertir]');
            if (b) revertir(parseInt(b.dataset.revertir, 10), b);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
