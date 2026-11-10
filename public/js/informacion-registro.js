/**
 * Pestaña «Información» de los modales (solo lectura): quién creó y modificó el registro, la
 * línea de tiempo de sus acciones y los movimientos de inventario que generó.
 *
 * Automático: no hay que llamar nada desde el módulo. La vista dibuja el panel con
 * \App\Helpers\InformacionRegistro::panel(), que lleva `data-info-registro`, `data-url`,
 * `data-id-input` (id del <input> con el id del registro abierto) y `data-nombre`. Cada vez que
 * se muestra esa pestaña se consulta `{ruta}/getInformacionRegistroAjax` (BaseModuloController).
 *
 * Para repintarla a mano (p. ej. tras guardar con la pestaña abierta): CMG_infoRegistro.cargar(panel).
 */
(function () {
    'use strict';
    if (window.CMG_infoRegistro) return; // protección contra doble carga

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function aviso(panel, texto, clase) {
        panel.innerHTML = `<div class="p-4 text-center small ${clase || 'text-muted'}">${texto}</div>`;
    }

    function etiquetaAccion(accion) {
        const a = String(accion || '');
        if (a.includes('CAMBIAR_ESTADO')) return 'Cambio de estado';
        if (a.includes('ENTREGA'))        return 'Entrega registrada';
        if (a.includes('ANULAR'))         return 'Registro anulado';
        if (a.includes('CREAR'))          return 'Registro creado';
        // "ACTUALIZAR_CONSIGNACION" es una edición del registro; "ACTUALIZAR_VENDEDOR" es una acción
        // puntual y se muestra con su propio nombre (cae al texto humanizado de abajo).
        if (/^(ACTUALIZAR|EDITAR|MODIFICAR)(_[A-Z]+)?$/.test(a) && !/_(VENDEDOR|ESTADO)$/.test(a)) return 'Registro editado';
        if (a.includes('ELIMINAR'))       return 'Registro eliminado';
        const t = a.replace(/_/g, ' ').toLowerCase();
        return t.charAt(0).toUpperCase() + t.slice(1);
    }

    function iconoAccion(accion) {
        const a = String(accion || '');
        return a.includes('CAMBIAR_ESTADO') ? 'bi-arrow-repeat text-info' :
               a.includes('ENTREGA')        ? 'bi-geo-alt-fill text-success' :
               a.includes('ANULAR')         ? 'bi-x-circle-fill text-danger' :
               a.includes('CREAR')          ? 'bi-plus-circle-fill text-success' :
               /ACTUALIZAR|EDITAR|MODIFICAR/.test(a) ? 'bi-pencil-fill text-primary' :
               a.includes('ELIMINAR')       ? 'bi-trash-fill text-danger' :
                                              'bi-clock-history text-secondary';
    }

    function renderCambios(detalles) {
        if (!Array.isArray(detalles) || !detalles.length) return '<span class="text-muted">Sin cambios registrados</span>';
        return `<ul class="list-unstyled mb-0">${detalles.map(d => {
            if (!d || typeof d !== 'object') return `<li><i class="bi bi-dot"></i> ${esc(d)}</li>`;
            const antes = (d.antes !== null && d.antes !== undefined)
                ? `<span class="text-decoration-line-through text-muted">${esc(d.antes)}</span> ` : '';
            return `<li><i class="bi bi-dot"></i> <span class="fw-bold">${esc(d.campo)}:</span> ${antes}<i class="bi bi-arrow-right mx-1"></i> ${esc(d.despues)}</li>`;
        }).join('')}</ul>`;
    }

    // "DD-MM-YYYY HH:MM:SS" → milisegundos, para cuadrar la última edición con su evento.
    function ts(valor) {
        const m = String(valor || '').match(/^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2}):(\d{2})/);
        return m ? new Date(+m[3], +m[2] - 1, +m[1], +m[4], +m[5], +m[6]).getTime() : null;
    }

    function render(panel, data) {
        const nombre = panel.dataset.nombre || 'el registro';
        const Nombre = nombre.charAt(0).toUpperCase() + nombre.slice(1);
        const registro = data.registro || {};
        const historial = data.historial || [];
        const inventario = data.inventario || [];

        // Última edición: quién y cuándo salen de la cabecera; el "qué hizo", del último evento
        // del historial, solo si su fecha cuadra con updated_at. Si no cuadra, ese cambio no
        // quedó detallado (registro migrado o anterior al registro de cambios) y se avisa.
        const hayEdicion = !!(registro.modificado_por || registro.updated_at);
        const ultimo = historial.find(h => !String(h.accion || '').includes('CREAR'));
        const tsCab = ts(registro.updated_at);
        const tsEv = ultimo ? ts(ultimo.fecha) : null;
        let ultimaAccion;
        if (!hayEdicion) {
            ultimaAccion = `<span class="text-muted">${esc(Nombre)} no se ha modificado desde que se creó.</span>`;
        } else if (ultimo && tsCab !== null && tsEv !== null && Math.abs(tsCab - tsEv) <= 60000) {
            ultimaAccion = `<span class="fw-semibold">${esc(etiquetaAccion(ultimo.accion))}</span><div class="mt-1">${renderCambios(ultimo.detalles)}</div>`;
        } else {
            ultimaAccion = '<span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>'
                + 'Este último cambio no quedó detallado en el historial (registro anterior al registro de cambios, o tocado por una migración).</span>';
        }

        const timeline = historial.length
            ? '<div class="position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left: 10px; top: 0;"></div>'
              + historial.map(h => `
                <div class="position-relative mb-3 ps-4">
                    <div class="position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border"
                         style="left: 0; top: 0; width: 22px; height: 22px; z-index: 2;">
                        <i class="bi ${iconoAccion(h.accion)}" style="font-size: 0.7rem;"></i>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-0 gap-2">
                            <span class="fw-bold" style="font-size: 0.75rem;">${esc(etiquetaAccion(h.accion))}</span>
                            <span class="text-muted text-nowrap" style="font-size: 0.65rem;">${esc(h.fecha || '')}</span>
                        </div>
                        <div class="text-muted mb-1" style="font-size: 0.7rem;"><i class="bi bi-person me-1"></i> ${esc(h.usuario || 'SISTEMA')}</div>
                        <div class="bg-light rounded p-1 border border-light-subtle" style="font-size: 0.65rem;">${renderCambios(h.detalles)}</div>
                    </div>
                </div>`).join('')
            : `<div class="text-center py-4 text-muted small">No hay acciones registradas para ${esc(nombre)}.</div>`;

        let bloqueInventario = '';
        if (data.con_inventario) {
            const th = 'py-2 small fw-bold text-muted';
            const filas = inventario.map(m => {
                const cant = parseFloat(m.cantidad || 0);
                const estado = m.eliminado
                    ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>
                       <div class="small text-muted">${esc(m.anulado_por || '—')} · ${esc(m.deleted_at || '')}</div>`
                    : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>';
                const titulo = (m.producto_codigo ? m.producto_codigo + ' - ' : '') + (m.producto_nombre || '');
                return `<tr>
                    <td class="ps-3 text-nowrap">${esc(m.created_at)}</td>
                    <td class="text-nowrap">${esc(m.usuario_nombre || '—')}</td>
                    <td class="text-nowrap">${esc(m.tipo_movimiento || '')}</td>
                    <td><span class="text-truncate d-inline-block" style="max-width:260px" title="${esc(titulo)}">${m.producto_codigo ? `<code class="text-secondary me-1">${esc(m.producto_codigo)}</code>` : ''}${esc(m.producto_nombre || '')}</span></td>
                    <td class="text-nowrap">${esc(m.bodega_nombre || '—')}</td>
                    <td class="text-nowrap">${esc(m.numero_lote || '—')}</td>
                    <td class="text-nowrap">${esc(m.nup || '—')}</td>
                    <td class="text-end text-nowrap fw-bold ${cant < 0 ? 'text-danger' : 'text-success'}">${cant.toFixed(2)}</td>
                    <td class="pe-3">${estado}</td>
                </tr>`;
            }).join('');
            bloqueInventario = `
                <h6 class="fw-bold small text-muted text-uppercase mb-2 mt-3"><i class="bi bi-box-seam me-1"></i> Movimientos de inventario generados</h6>
                <div class="border rounded-3 overflow-hidden bg-white">
                    <div class="table-responsive" style="max-height: 260px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr class="table-light border-bottom">
                                <th class="ps-3 ${th}">Fecha y hora</th><th class="${th}">Usuario</th><th class="${th}">Tipo</th><th class="${th}">Producto</th>
                                <th class="${th}">Bodega</th><th class="${th}">Lote</th><th class="${th}">NUP</th><th class="${th} text-end">Cantidad</th><th class="${th} pe-3">Estado</th>
                            </tr></thead>
                            <tbody>${filas || `<tr><td colspan="9" class="text-center py-3 text-muted">${esc(Nombre)} no generó movimientos de inventario.</td></tr>`}</tbody>
                        </table>
                    </div>
                </div>`;
        }

        panel.innerHTML = `
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="border rounded-3 p-2 bg-white h-100">
                        <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;"><i class="bi bi-person-check me-1"></i> Creado</div>
                        <div class="fw-bold text-truncate" style="font-size: 0.85rem;" title="${esc(registro.creado_por || '')}">${esc(registro.creado_por || '—')}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">${esc(registro.created_at || '—')}</div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="border rounded-3 p-2 bg-white h-100">
                        <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.65rem;"><i class="bi bi-pencil-square me-1"></i> Última edición</div>
                        <div class="d-flex flex-wrap align-items-baseline gap-2">
                            <span class="fw-bold text-truncate" style="font-size: 0.85rem;">${esc(hayEdicion ? (registro.modificado_por || 'Usuario no identificado') : 'Sin ediciones')}</span>
                            <span class="text-muted" style="font-size: 0.72rem;">${hayEdicion && registro.updated_at ? '· ' + esc(registro.updated_at) : ''}</span>
                        </div>
                        <div class="mt-1" style="font-size: 0.72rem;">${ultimaAccion}</div>
                    </div>
                </div>
            </div>
            <h6 class="fw-bold small text-muted text-uppercase mb-2"><i class="bi bi-list-ul me-1"></i> Acciones registradas</h6>
            <div class="border rounded-3 bg-white p-3" style="max-height: 320px; overflow-y: auto;">
                <div class="position-relative">${timeline}</div>
            </div>
            ${bloqueInventario}`;
    }

    async function cargar(panel) {
        if (!panel || !panel.dataset.url) return;
        const nombre = panel.dataset.nombre || 'el registro';
        // El id del registro abierto sale de un <input> (data-id-input) o, si el modal lo guarda
        // en una variable, de una función global (data-id-fn).
        const leerId = () => {
            const fn = panel.dataset.idFn ? window[panel.dataset.idFn] : null;
            if (typeof fn === 'function') return String(fn() ?? '').trim();
            const el = document.getElementById(panel.dataset.idInput || '');
            return el ? String(el.value || '').trim() : '';
        };
        const id = leerId();
        if (!id || id === '0') {
            aviso(panel, `<i class="bi bi-clock-history d-block mb-2" style="font-size:1.6rem;"></i>Guarde ${esc(nombre)} para ver quién lo registró y los cambios que reciba.`);
            return;
        }
        aviso(panel, '<span class="spinner-border spinner-border-sm me-2"></span> Cargando información...');
        try {
            const resp = await fetch(`${panel.dataset.url}?id=${encodeURIComponent(id)}`);
            const json = await resp.json();
            // Si mientras tanto se abrió otro registro en el modal, esta respuesta ya no corresponde.
            if (leerId() !== id) return;
            if (!json.ok || !json.data) { aviso(panel, esc(json.error || 'No hay información disponible.')); return; }
            render(panel, json.data);
        } catch (e) {
            aviso(panel, 'No se pudo cargar la información.', 'text-danger');
        }
    }

    // `shown.bs.tab` burbujea: un solo listener sirve a todos los modales, presentes y futuros.
    document.addEventListener('shown.bs.tab', ev => {
        const selector = ev.target && (ev.target.getAttribute('data-bs-target') || ev.target.getAttribute('href'));
        if (!selector || selector.charAt(0) !== '#') return;
        const panel = document.getElementById(selector.slice(1));
        if (panel && panel.hasAttribute('data-info-registro')) cargar(panel);
    });

    window.CMG_infoRegistro = { cargar };
})();
