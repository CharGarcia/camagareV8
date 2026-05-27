/**
 * Lógica compartida para el Modal de Marcas
 */

(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/marcas') : (window.location.origin + '/sistema/public/modulos/marcas');
    const modalEl = document.getElementById('modalMarca');
    let modalInst = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    window.abrirModalMarcaCrear = function() {
        const form = document.getElementById('formMarcaModal');
        if (!form) return;
        form.reset();
        
        const mid = document.getElementById('marca_id_modal');
        if (mid) mid.value = '';
        
        const title = document.getElementById('tituloModalMar');
        if (title) title.textContent = 'Nueva Marca';
        
        const alertMar = document.getElementById('modalAlertMar');
        if (alertMar) alertMar.classList.add('d-none');
        
        const btnElim = document.getElementById('btnEliminarMarModal');
        if (btnElim) btnElim.classList.add('d-none');
        
        const tabInfoBtn = document.getElementById('tab-mar-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.add('disabled');

        const tabGenBtn = document.getElementById('tab-mar-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }
        
        getModal()?.show();
        setTimeout(() => {
            const nomInput = document.getElementById('marca_nombre_modal');
            if (nomInput) nomInput.focus();
        }, 500);
    };

    window.abrirModalMarcaEditar = function(rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            data = rowOrData;
        }

        const form = document.getElementById('formMarcaModal');
        if (!form || !data) return;
        form.reset();

        const mid = document.getElementById('marca_id_modal');
        if (mid) mid.value = data.id;
        
        const mnom = document.getElementById('marca_nombre_modal');
        if (mnom) mnom.value = data.nombre || '';
        
        const mstat = document.getElementById('marca_status_modal');
        if (mstat) mstat.value = data.status ?? '1';

        const title = document.getElementById('tituloModalMar');
        if (title) title.textContent = 'Editar Marca';
        
        const alertMar = document.getElementById('modalAlertMar');
        if (alertMar) alertMar.classList.add('d-none');
        
        const btnElim = document.getElementById('btnEliminarMarModal');
        if (btnElim) btnElim.classList.remove('d-none');
        
        const tabInfoBtn = document.getElementById('tab-mar-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.remove('disabled');

        fetchInfoMar(data.id);
        fetchHistorialMar(data.id);
        
        const tabGenBtn = document.getElementById('tab-mar-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }
        getModal()?.show();
    };

    async function fetchInfoMar(id) {
        try {
            const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const elCount = document.getElementById('info_mar_productos_count');
                if (elCount) elCount.textContent = d.productos_count;
            }
        } catch (e) {}
    }

    async function fetchHistorialMar(id) {
        const container = document.getElementById('auditoriaTimelineMar');
        if (!container || !id) return;

        try {
            const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=marcas`);
            const json = await resp.json();

            if (json.ok && json.data.length > 0) {
                let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left: 10px; top: 0;"></div>';

                json.data.forEach(log => {
                    const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success' :
                               log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary' :
                               log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger' :
                               'bi-clock-history text-secondary';

                    html += `
                        <div class="timeline-item position-relative mb-3 ps-4">
                            <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border" 
                                 style="left: 0; top: 0; width: 22px; height: 22px; z-index: 2;">
                                <i class="bi ${icon}" style="font-size: 0.7rem;"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center mb-0">
                                    <span class="fw-bold" style="font-size: 0.75rem;">${log.accion}</span>
                                    <span class="text-muted" style="font-size: 0.65rem;">${log.created_at}</span>
                                </div>
                                <div class="text-muted mb-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-person me-1"></i> ${log.usuario_nombre || 'SISTEMA'}
                                </div>
                                <div class="bg-light rounded p-1 border border-light-subtle shadow-sm" style="font-size: 0.65rem;">
                                    ${window.renderDetalleHistorialMar(log.detalles)}
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="text-center py-3 text-danger small">Error de carga.</div>';
        }
    }

    window.renderDetalleHistorialMar = function(detalle) {
        if (!detalle || detalle.length === 0) return '<span class="text-muted small">Sin detalles.</span>';
        if (typeof detalle === 'string') return detalle;
        if (Array.isArray(detalle)) {
            return `<ul class="list-unstyled mb-0">
                ${detalle.map(d => {
                    if (typeof d === 'object') {
                        const antes = d.antes !== null ? `<span class="text-decoration-line-through text-muted">${d.antes}</span> ` : '';
                        return `<li><i class="bi bi-dot"></i> <span class="fw-bold">${d.campo}:</span> ${antes}<i class="bi bi-arrow-right mx-1"></i> ${d.despues}</li>`;
                    }
                    return `<li><i class="bi bi-dot"></i> ${d}</li>`;
                }).join('')}
            </ul>`;
        }
        return '<span class="text-muted">Acción registrada</span>';
    };

    window.guardarMarcaModal = async function() {
        const form = document.getElementById('formMarcaModal');
        const id = document.getElementById('marca_id_modal')?.value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarMarModal');
        const alertEl = document.getElementById('modalAlertMar');

        if (!form || !btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(actionUrl, {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();

            if (alertEl) {
                alertEl.textContent = json.msg || json.error;
                alertEl.className = `alert mb-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                alertEl.classList.remove('d-none');
            }

            if (json.ok) {
                setTimeout(async () => {
                    getModal()?.hide();
                    if (window.fetchSearchMar) window.fetchSearchMar();
                    const event = new CustomEvent('marcaGuardada', {
                        detail: json.data || {
                            id: id || json.id,
                            nombre: fd.get('nombre')
                        }
                    });
                    window.dispatchEvent(event);
                }, 800);
            }
        } catch (e) {
            if (alertEl) {
                alertEl.textContent = 'Error de conexión';
                alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                alertEl.classList.remove('d-none');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }
    };

    window.eliminarMarcaModal = async function() {
        const id = document.getElementById('marca_id_modal')?.value;
        if (!id || !confirm('¿Seguro que desea eliminar esta marca?')) return;
        const btn = document.getElementById('btnEliminarMarModal');

        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchMar) window.fetchSearchMar();
            }
        } catch (e) {} finally {
            if (btn) btn.disabled = false;
        }
    };

})(window, document);
