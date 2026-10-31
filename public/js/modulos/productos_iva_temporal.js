/**
 * Cambio temporal de IVA en Productos (empresas de turismo — IVA 8% en feriados).
 * Modal: app/views/modulos/productos/modal_iva_temporal.php
 * Servidor: ProductosController::ivaTemporal*Ajax → IvaCambioTemporalService.
 */
(function () {
    'use strict';
    if (window.__CMG_ivaTemporal) return;
    window.__CMG_ivaTemporal = true;

    const urlBase = (window.BASE_URL || '').replace(/\/$/, '') + '/modulos/productos';
    const ESTADOS = {
        programado: ['Programado', 'bg-info text-info'],
        aplicado:   ['Aplicado', 'bg-success text-success'],
        restaurado: ['Restaurado', 'bg-secondary text-secondary'],
        cancelado:  ['Cancelado', 'bg-secondary text-secondary'],
        vencido:    ['Vencido sin aplicar', 'bg-warning text-warning'],
    };
    const APLICA = { todos: 'Todos', servicios: 'Servicios', bienes: 'Bienes' };

    let tarifas = [];
    let programando = false;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fecha = (ymd) => { const p = String(ymd || '').split('-'); return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : ''; };
    const error = (msg) => (typeof window.swalError === 'function'
        ? window.swalError(msg)
        : Swal.fire({ icon: 'error', title: 'Error', text: msg }));

    window.abrirModalIvaTemporal = async function () {
        bootstrap.Modal.getOrCreateInstance($('modalIvaTemporal')).show();
        await cargar(true);
    };

    async function cargar(reiniciarForm) {
        try {
            const resp = await fetch(`${urlBase}/ivaTemporalDatosAjax`);
            const json = await resp.json();
            if (!json.ok) { error(json.error || 'No se pudo cargar el cambio temporal de IVA.'); return; }
            tarifas = json.tarifas || [];
            if (reiniciarForm) llenarFormulario(json);
            pintarHistorial(json.historial || []);
            actualizarPreview();
        } catch (e) {
            error('Error de conexión al cargar el cambio temporal de IVA.');
        }
    }

    function llenarFormulario(json) {
        const opt = (t, conConteo) => `<option value="${t.id}">${esc(t.tarifa)}${conConteo ? ` (${t.total} producto${t.total === 1 ? '' : 's'})` : ''}</option>`;
        $('ivaTmpActual').innerHTML = tarifas.map((t) => opt(t, true)).join('');
        $('ivaTmpNueva').innerHTML = tarifas.map((t) => opt(t, false)).join('');
        // Por defecto: 15% → 8% (códigos SRI 4 y 8).
        const t15 = tarifas.find((t) => t.codigo === '4');
        const t8 = tarifas.find((t) => t.codigo === '8');
        if (t15) $('ivaTmpActual').value = t15.id;
        if (t8) $('ivaTmpNueva').value = t8.id;
        $('ivaTmpAplica').value = 'todos';
        $('ivaTmpDesde').value = json.hoy;
        $('ivaTmpHasta').value = json.hoy;
        $('ivaTmpDesde').min = json.hoy;
        $('ivaTmpHasta').min = json.hoy;
        $('ivaTmpObs').value = '';
    }

    function actualizarPreview() {
        const t = tarifas.find((x) => String(x.id) === $('ivaTmpActual').value);
        const aplica = $('ivaTmpAplica').value;
        const n = !t ? 0 : (aplica === 'servicios' ? t.servicios : (aplica === 'bienes' ? t.bienes : t.total));
        const nueva = tarifas.find((x) => String(x.id) === $('ivaTmpNueva').value);
        $('ivaTmpPreview').innerHTML = t && nueva
            ? `Se cambiarán <b>${n}</b> producto${n === 1 ? '' : 's'} de <b>${esc(t.tarifa)}</b> a <b>${esc(nueva.tarifa)}</b> (según los que tengan esa tarifa al llegar la fecha desde).`
            : '';
    }

    function pintarHistorial(rows) {
        const tbody = $('ivaTmpHistorial');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Todavía no hay cambios de IVA programados.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map((r) => {
            const [txt, cls] = ESTADOS[r.estado] || [r.estado, 'bg-secondary text-secondary'];
            let productos = '';
            if (r.estado === 'aplicado') productos = r.total_productos;
            else if (r.estado === 'restaurado') productos = `${r.total_restaurados}${r.total_omitidos > 0 ? ` <span class="text-warning" title="No se restauraron porque su tarifa se cambió a mano durante el período">(+${r.total_omitidos} omitidos)</span>` : ''}`;
            const fechaEstado = r.estado === 'aplicado' ? r.aplicado_at : (r.estado === 'restaurado' ? r.restaurado_at : '');
            let acciones = '';
            if (r.estado === 'aplicado' || r.estado === 'restaurado') {
                acciones += `<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Ver productos" data-iva-ver="${r.id}"><i class="bi bi-list-ul"></i></button> `;
            }
            if (r.estado === 'programado') {
                acciones += `<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" title="Cancelar" data-iva-cancelar="${r.id}"><i class="bi bi-x-lg"></i></button>`;
            }
            if (r.estado === 'aplicado') {
                acciones += `<button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" title="Restaurar ahora la tarifa anterior" data-iva-restaurar="${r.id}"><i class="bi bi-arrow-counterclockwise"></i></button>`;
            }
            return `<tr>
                <td class="text-nowrap">${fecha(r.fecha_desde)} al ${fecha(r.fecha_hasta)}</td>
                <td class="text-nowrap">${esc(r.tarifa_actual)} → ${esc(r.tarifa_nueva)}</td>
                <td>${esc(APLICA[r.aplica_a] || r.aplica_a)}</td>
                <td class="text-center"><span class="badge ${cls} bg-opacity-10" title="${esc(fechaEstado)}">${esc(txt)}</span></td>
                <td class="text-end">${productos}</td>
                <td class="text-truncate" style="max-width:180px;" title="${esc(r.observaciones)}">${esc(r.observaciones)}</td>
                <td class="text-nowrap" title="${esc(r.created_at)}">${esc(r.creado_por)}</td>
                <td class="text-end text-nowrap">${acciones}</td>
            </tr>`;
        }).join('');
    }

    async function programar() {
        if (programando) return;
        programando = true;
        const btn = $('btnIvaTmpProgramar');
        const btnHtml = btn.innerHTML;
        btn.disabled = true;
        try {
            const actual = tarifas.find((x) => String(x.id) === $('ivaTmpActual').value);
            const nueva = tarifas.find((x) => String(x.id) === $('ivaTmpNueva').value);
            const desde = $('ivaTmpDesde').value;
            const hasta = $('ivaTmpHasta').value;
            const hoyInicia = desde === $('ivaTmpDesde').min;
            const ok = await Swal.fire({
                title: '¿Programar el cambio de IVA?',
                html: `${esc(actual?.tarifa)} → <b>${esc(nueva?.tarifa)}</b> del <b>${fecha(desde)}</b> al <b>${fecha(hasta)}</b>.<br>`
                    + (hoyInicia ? 'Empieza hoy: los productos cambian <b>ahora mismo</b>.' : 'Se aplicará automáticamente en la fecha desde.')
                    + '<br>Al terminar, cada producto vuelve a su tarifa anterior.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, programar',
                cancelButtonText: 'Cancelar',
            });
            if (!ok.isConfirmed) return;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
            const resp = await fetch(`${urlBase}/ivaTemporalProgramarAjax`, { method: 'POST', body: new FormData($('formIvaTemporal')) });
            const json = await resp.json();
            if (!json.ok) { error(json.error || 'No se pudo programar el cambio.'); return; }
            await cargar(true);
            if (json.estado === 'aplicado' && typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            Swal.fire({ icon: 'success', title: 'Listo', text: json.msg, confirmButtonColor: '#0d6efd' });
        } catch (e) {
            error('Error de conexión al programar el cambio. Revise el historial antes de volver a intentarlo.');
            cargar(false);
        } finally {
            btn.innerHTML = btnHtml;
            btn.disabled = false;
            programando = false;
        }
    }

    async function accion(endpoint, id, pregunta, textoBoton, color) {
        const ok = await Swal.fire({
            title: pregunta, icon: 'warning', showCancelButton: true,
            confirmButtonColor: color, cancelButtonColor: '#6c757d',
            confirmButtonText: textoBoton, cancelButtonText: 'Volver',
        });
        if (!ok.isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        try {
            const resp = await fetch(`${urlBase}/${endpoint}`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) { error(json.error || 'No se pudo completar la acción.'); return; }
            await cargar(false);
            if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            Swal.fire({ icon: 'success', title: 'Listo', text: json.msg, confirmButtonColor: '#0d6efd' });
        } catch (e) {
            error('Error de conexión.');
        }
    }

    async function verProductos(id) {
        try {
            const resp = await fetch(`${urlBase}/ivaTemporalDetalleAjax?id=${encodeURIComponent(id)}`);
            const json = await resp.json();
            if (!json.ok) { error(json.error || 'No se pudo cargar el detalle.'); return; }
            const filas = (json.rows || []).map((r) => {
                const est = r.omitido ? '<span class="text-warning">Omitido (cambiado a mano)</span>'
                    : (r.restaurado ? '<span class="text-secondary">Restaurado</span>' : '<span class="text-success">Con tarifa nueva</span>');
                return `<tr><td>${esc(r.codigo)}</td><td class="text-start">${esc(r.nombre)}</td><td>${esc(r.tarifa_anterior)}</td><td>${esc(r.tarifa_aplicada)}</td><td>${esc(r.tarifa_hoy)}</td><td>${est}</td></tr>`;
            }).join('') || '<tr><td colspan="6" class="text-muted">Ningún producto tenía esa tarifa al aplicarse.</td></tr>';
            Swal.fire({
                title: 'Productos del cambio',
                width: 900,
                html: `<div style="max-height:60vh;overflow:auto;"><table class="table table-sm small mb-0"><thead class="table-light"><tr><th>Código</th><th class="text-start">Producto</th><th>Antes</th><th>Aplicada</th><th>Hoy</th><th>Estado</th></tr></thead><tbody>${filas}</tbody></table></div>`,
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'Cerrar',
            });
        } catch (e) {
            error('Error de conexión.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modal = $('modalIvaTemporal');
        if (!modal) return;
        ['ivaTmpActual', 'ivaTmpNueva', 'ivaTmpAplica'].forEach((id) => $(id).addEventListener('change', actualizarPreview));
        $('ivaTmpDesde').addEventListener('change', () => {
            if ($('ivaTmpHasta').value < $('ivaTmpDesde').value) $('ivaTmpHasta').value = $('ivaTmpDesde').value;
            $('ivaTmpHasta').min = $('ivaTmpDesde').value;
        });
        $('btnIvaTmpProgramar').addEventListener('click', programar);
        $('ivaTmpHistorial').addEventListener('click', (e) => {
            const b = e.target.closest('button');
            if (!b) return;
            if (b.dataset.ivaVer) verProductos(b.dataset.ivaVer);
            else if (b.dataset.ivaCancelar) accion('ivaTemporalCancelarAjax', b.dataset.ivaCancelar, '¿Cancelar este cambio programado?', 'Sí, cancelar', '#dc3545');
            else if (b.dataset.ivaRestaurar) accion('ivaTemporalRestaurarAjax', b.dataset.ivaRestaurar, '¿Restaurar ahora la tarifa anterior de los productos?', 'Sí, restaurar', '#0d6efd');
        });
    });
})();
