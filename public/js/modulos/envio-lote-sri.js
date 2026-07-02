/* ============================================================================
 * Envío en lote de comprobantes electrónicos al SRI
 * Filtros + selección múltiple + creación de lote + polling de progreso.
 * ========================================================================== */
(function () {
    'use strict';

    const app   = document.getElementById('els-app');
    const BASE  = app.dataset.base || '';
    const RUTA  = app.dataset.ruta || 'modulos/envio-lote-sri';
    const AMBIENTE = app.dataset.ambiente || '1';   // definido por la empresa
    const URL   = (accion) => `${BASE}/${RUTA}/${accion}`;

    const TIPO_LABEL = {
        factura_venta:      ['Factura', 'primary'],
        nota_credito:       ['Nota crédito', 'info'],
        retencion_compra:   ['Retención', 'warning'],
        liquidacion_compra: ['Liquidación', 'secondary'],
    };

    let datos = [];            // filas enviables actuales
    let pollTimer = null;
    let loteActual = 0;
    let modalProgreso = null;
    let modalHistorial = null;

    // ── Utilidades ──────────────────────────────────────────────────────────
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function money(n) {
        return (Number(n) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fechaFmt(f) {
        if (!f) return '';
        const d = String(f).substring(0, 10).split('-');
        return d.length === 3 ? `${d[2]}-${d[1]}-${d[0]}` : f;
    }
    function ambienteBadge(a) {
        return a === '2'
            ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 els-estado-badge">PRODUCCIÓN</span>'
            : '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 els-estado-badge">PRUEBAS</span>';
    }
    function estadoItemBadge(e) {
        const map = {
            autorizado:   ['success', 'Autorizado'],
            devuelto:     ['danger', 'Devuelto'],
            no_autorizado:['danger', 'No autorizado'],
            error:        ['danger', 'Error'],
            procesando:   ['info', 'Procesando'],
            pendiente:    ['secondary', 'Pendiente'],
        };
        const [c, l] = map[e] || ['secondary', e || '-'];
        return `<span class="badge bg-${c} bg-opacity-10 text-${c} border border-${c} border-opacity-25 els-estado-badge">${esc(l)}</span>`;
    }
    function tiposSeleccionados() {
        return Array.from(document.querySelectorAll('.els-tipo:checked')).map(c => c.value);
    }

    // ── Buscar comprobantes enviables ───────────────────────────────────────
    async function buscar() {
        const params = new URLSearchParams({
            desde:    document.getElementById('els-desde').value,
            hasta:    document.getElementById('els-hasta').value,
            b:        document.getElementById('els-buscar').value,
            tipos:    tiposSeleccionados().join(','),
        });
        const tbody = document.getElementById('els-tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</td></tr>';

        try {
            const r = await fetch(`${URL('buscarAjax')}?${params.toString()}`);
            const j = await r.json();
            if (!j.ok) throw new Error(j.mensaje || 'Error al consultar.');
            datos = j.data || [];
            render();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(e.message)}</td></tr>`;
        }
    }

    function render() {
        const tbody = document.getElementById('els-tbody');
        document.getElementById('els-contador-total').textContent = datos.length;
        document.getElementById('els-check-all').checked = false;

        if (!datos.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-4 d-block mb-2"></i>No hay comprobantes enviables con esos filtros.</td></tr>';
            actualizarContador();
            return;
        }

        tbody.innerHTML = datos.map((d, i) => {
            const [lbl, color] = TIPO_LABEL[d.tipo] || [d.tipo, 'secondary'];
            return `<tr>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input els-row" data-idx="${i}" onchange="ELS.actualizarContador()">
                </td>
                <td><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25 els-badge-tipo">${esc(lbl)}</span></td>
                <td class="text-nowrap fw-semibold">${esc(d.numero)}</td>
                <td class="text-nowrap">${fechaFmt(d.fecha_emision)}</td>
                <td class="text-truncate" style="max-width:260px;">${esc(d.contraparte)}</td>
                <td class="text-end text-nowrap">$${money(d.total)}</td>
                <td>${esc(d.estado)}</td>
                <td>${ambienteBadge(String(d.tipo_ambiente))}</td>
            </tr>`;
        }).join('');
        actualizarContador();
    }

    // ── Selección ───────────────────────────────────────────────────────────
    function filas() { return Array.from(document.querySelectorAll('.els-row')); }
    function seleccionadas() { return filas().filter(c => c.checked); }

    function actualizarContador() {
        document.getElementById('els-contador-sel').textContent = seleccionadas().length;
    }
    function toggleTodos(v) { filas().forEach(c => c.checked = v); actualizarContador(); }
    function seleccionarTodos() { toggleTodos(true); document.getElementById('els-check-all').checked = true; }
    function limpiarSeleccion() { toggleTodos(false); document.getElementById('els-check-all').checked = false; }
    function seleccionarN(n) {
        const cs = filas();
        cs.forEach((c, i) => c.checked = i < n);
        document.getElementById('els-check-all').checked = (n >= cs.length && cs.length > 0);
        actualizarContador();
    }

    // ── Crear lote y lanzar procesamiento ───────────────────────────────────
    async function enviar() {
        const sel = seleccionadas();
        if (!sel.length) {
            Swal.fire({ icon: 'info', title: 'Sin selección', text: 'Marca al menos un comprobante para enviar.' });
            return;
        }
        const items = sel.map(c => {
            const d = datos[Number(c.dataset.idx)];
            return { tipo: d.tipo, id: d.id, numero: d.numero, fecha_emision: d.fecha_emision };
        });

        const conf = await Swal.fire({
            icon: 'question',
            title: `Enviar ${items.length} comprobante(s) al SRI`,
            html: 'El proceso corre en <b>segundo plano</b>. Puedes cerrar la ventana y continuará.',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-cloud-upload me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
        });
        if (!conf.isConfirmed) return;

        const fd = new FormData();
        fd.append('items', JSON.stringify(items));
        fd.append('desde', document.getElementById('els-desde').value);
        fd.append('hasta', document.getElementById('els-hasta').value);
        fd.append('tipos', tiposSeleccionados().join(','));

        try {
            const r = await fetch(URL('crearLoteAjax'), { method: 'POST', body: fd });
            const j = await r.json();
            if (!j.ok) throw new Error(j.mensaje || 'No se pudo crear el lote.');

            if (!j.lanzado) {
                Swal.fire({ icon: 'warning', title: 'Lote creado', text: j.mensaje });
            }
            loteActual = j.id_lote;
            abrirProgreso(j.id_lote);
            iniciarPolling(j.id_lote);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        }
    }

    // ── Progreso (polling) ──────────────────────────────────────────────────
    function abrirProgreso(idLote) {
        document.getElementById('els-lote-id').textContent = '#' + idLote;
        document.getElementById('els-progress-items').innerHTML = '';
        setBar(0, 0, 0, 0, 'pendiente');
        if (!modalProgreso) {
            const el = document.getElementById('els-modal-progreso');
            modalProgreso = new bootstrap.Modal(el);
            // Al cerrar el modal: detener el polling y refrescar la pantalla a su
            // estado inicial (recarga el listado; los ya autorizados desaparecen).
            el.addEventListener('hidden.bs.modal', () => {
                detenerPolling();
                loteActual = 0;
                limpiarSeleccion();
                buscar();
            });
        }
        modalProgreso.show();
    }
    function setBar(proc, total, ok, err, estado) {
        const pct = total > 0 ? Math.round((proc / total) * 100) : 0;
        const bar = document.getElementById('els-progress-bar');
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        document.getElementById('els-p-proc').textContent = proc;
        document.getElementById('els-p-total').textContent = total;
        document.getElementById('els-p-ok').textContent = ok;
        document.getElementById('els-p-err').textContent = err;
        const badge = document.getElementById('els-p-estado');
        badge.textContent = estado;
        badge.className = 'badge ' + (
            estado === 'completado' ? 'bg-success' :
            estado === 'completado_con_errores' ? 'bg-warning text-dark' :
            estado === 'cancelado' ? 'bg-secondary' : 'bg-info'
        );
    }
    function iniciarPolling(idLote) {
        detenerPolling();
        const tick = async () => {
            try {
                const r = await fetch(`${URL('estadoLoteAjax')}?id=${idLote}`);
                const j = await r.json();
                if (!j.ok) return;
                const d = j.data;
                setBar(+d.procesados, +d.total, +d.exitosos, +d.fallidos, d.estado);
                pintarItems(d.items || []);
                if (['completado', 'completado_con_errores', 'cancelado'].includes(d.estado)) {
                    detenerPolling();
                    document.getElementById('els-progress-bar').classList.remove('progress-bar-animated');
                }
            } catch (e) { /* reintentar en el próximo tick */ }
        };
        tick();
        pollTimer = setInterval(tick, 2500);
    }
    function detenerPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
    function pintarItems(items) {
        document.getElementById('els-progress-items').innerHTML = items.map(it => {
            const [lbl] = TIPO_LABEL[it.tipo_comprobante] || [it.tipo_comprobante];
            return `<tr>
                <td class="text-nowrap">${esc(it.numero || '')}</td>
                <td>${esc(lbl)}</td>
                <td>${estadoItemBadge(it.estado)}</td>
                <td class="small text-muted">${esc(it.mensaje || '')}</td>
            </tr>`;
        }).join('');
    }

    async function cancelar() {
        if (!loteActual) return;
        const conf = await Swal.fire({
            icon: 'warning', title: '¿Cancelar lote?',
            text: 'Se detendrá tras el comprobante en curso.',
            showCancelButton: true, confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No',
        });
        if (!conf.isConfirmed) return;
        const fd = new FormData(); fd.append('id', loteActual);
        try {
            const r = await fetch(URL('cancelarLoteAjax'), { method: 'POST', body: fd });
            const j = await r.json();
            Swal.fire({ icon: j.ok ? 'success' : 'error', title: j.ok ? 'Solicitado' : 'Error', text: j.mensaje });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        }
    }

    // ── Historial ───────────────────────────────────────────────────────────
    async function abrirHistorial() {
        modalHistorial = modalHistorial || new bootstrap.Modal(document.getElementById('els-modal-historial'));
        modalHistorial.show();
        const body = document.getElementById('els-historial-body');
        body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Cargando…</td></tr>';
        try {
            const r = await fetch(URL('historialLotesAjax'));
            const j = await r.json();
            const rows = (j.data || []);
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Aún no hay lotes.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(l => {
                const estCls = l.estado === 'completado' ? 'success' :
                    l.estado === 'completado_con_errores' ? 'warning' :
                    l.estado === 'cancelado' ? 'secondary' :
                    l.estado === 'procesando' ? 'info' : 'secondary';
                return `<tr>
                    <td>#${esc(l.id)}</td>
                    <td class="text-nowrap">${esc(l.created_at_fmt || '')}</td>
                    <td>${esc(l.usuario_nombre || '')}</td>
                    <td>${ambienteBadge(String(l.tipo_ambiente))}</td>
                    <td>${esc(l.total)}</td>
                    <td class="text-success">${esc(l.exitosos)}</td>
                    <td class="text-danger">${esc(l.fallidos)}</td>
                    <td><span class="badge bg-${estCls} bg-opacity-10 text-${estCls} border border-${estCls} border-opacity-25 els-estado-badge">${esc(l.estado)}</span></td>
                    <td><button class="btn btn-sm btn-outline-primary py-0" onclick="ELS.verLote(${l.id})"><i class="bi bi-eye"></i></button></td>
                </tr>`;
            }).join('');
        } catch (e) {
            body.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${esc(e.message)}</td></tr>`;
        }
    }
    function verLote(idLote) {
        loteActual = idLote;
        if (modalHistorial) modalHistorial.hide();
        abrirProgreso(idLote);
        iniciarPolling(idLote);
    }

    // ── API pública ─────────────────────────────────────────────────────────
    window.ELS = {
        buscar, render, actualizarContador, toggleTodos, seleccionarTodos,
        limpiarSeleccion, seleccionarN, enviar, cancelar, abrirHistorial, verLote,
    };

    // Clic en cualquier parte de la fila → marca/desmarca su checkbox.
    // (Delegación en el tbody; se ignora el clic directo sobre controles nativos.)
    document.getElementById('els-tbody').addEventListener('click', (e) => {
        if (e.target.closest('input, button, a, label')) return;
        const tr = e.target.closest('tr');
        const cb = tr && tr.querySelector('.els-row');
        if (!cb) return;
        cb.checked = !cb.checked;
        actualizarContador();
    });

    // Buscar al cargar (con los filtros por defecto = hoy)
    document.addEventListener('DOMContentLoaded', buscar);
    if (document.readyState !== 'loading') buscar();
})();
