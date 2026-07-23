/**
 * Lógica compartida para el Modal de Categorías
 */

(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/categorias') : (window.location.origin + '/sistema/public/modulos/categorias');
    const modalEl = document.getElementById('modalCategoria');
    let modalInst = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    window.abrirModalCategoriaCrear = function() {
        const form = document.getElementById('formCategoriaModal');
        if (!form) return;
        form.reset();
        
        const cid = document.getElementById('categoria_id_modal');
        if (cid) cid.value = '';
        
        const title = document.getElementById('tituloModalCat');
        if (title) title.textContent = 'Nueva Categoría';
        
        const alertCat = document.getElementById('modalAlertCat');
        if (alertCat) alertCat.classList.add('d-none');
        
        const btnElim = document.getElementById('btnEliminarCatModal');
        if (btnElim) btnElim.classList.add('d-none');
        
        const tabInfoBtn = document.getElementById('tab-cat-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.add('disabled');
        
        const tabGenBtn = document.getElementById('tab-cat-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }
        
        getModal()?.show();
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalCategoria');
        }
        setTimeout(() => {
            const nomInput = document.getElementById('categoria_nombre_modal');
            if (nomInput) nomInput.focus();
        }, 500);
    };

    window.abrirModalCategoriaEditar = function(rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            data = rowOrData;
        }

        const form = document.getElementById('formCategoriaModal');
        if (!form || !data) return;
        form.reset();
        
        const cid = document.getElementById('categoria_id_modal');
        if (cid) cid.value = data.id;
        
        const cnom = document.getElementById('categoria_nombre_modal');
        if (cnom) cnom.value = data.nombre || '';
        
        const cstat = document.getElementById('categoria_status_modal');
        if (cstat) cstat.value = data.status ?? '1';

        const title = document.getElementById('tituloModalCat');
        if (title) title.textContent = 'Editar Categoría';
        
        const alertCat = document.getElementById('modalAlertCat');
        if (alertCat) alertCat.classList.add('d-none');
        
        const btnElim = document.getElementById('btnEliminarCatModal');
        if (btnElim) btnElim.classList.remove('d-none');
        
        const tabInfoBtn = document.getElementById('tab-cat-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.remove('disabled');

        fetchInfoCat(data.id);
        fetchHistorialCat(data.id);
        
        const tabGenBtn = document.getElementById('tab-cat-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }
        getModal()?.show();
    };

    async function fetchInfoCat(id) {
        try {
            const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const elCount = document.getElementById('info_cat_productos_count');
                if (elCount) elCount.textContent = d.productos_count;
            }
        } catch (e) {}
    }

    async function fetchHistorialCat(id) {
        const container = document.getElementById('auditoriaTimelineCat');
        if (!container || !id) return;

        try {
            const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=categorias`);
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
                                        ${window.renderDetalleHistorialCat(log.detalles)}
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

    window.renderDetalleHistorialCat = function(detalle) {
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

    window.guardarCategoriaModal = async function() {
        const form = document.getElementById('formCategoriaModal');
        const id = document.getElementById('categoria_id_modal')?.value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarCatModal');
        const alertEl = document.getElementById('modalAlertCat');

        if (!form || !btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(actionUrl, { method: 'POST', body: fd });
            const json = await resp.json();

            if (alertEl) {
                alertEl.textContent = json.msg || json.error;
                alertEl.className = `alert mb-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                alertEl.classList.remove('d-none');
            }

            if (json.ok) {
                setTimeout(async () => {
                    getModal()?.hide();
                    // Si estamos en el módulo de categorías, recargar tabla
                    if (window.fetchSearchCat) window.fetchSearchCat();
                    // Si estamos en otros módulos, disparar evento
                    const event = new CustomEvent('categoriaGuardada', { detail: json.data || {id: id || json.id, nombre: fd.get('nombre')} });
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

    window.eliminarCategoriaModal = async function() {
        const id = document.getElementById('categoria_id_modal')?.value;
        if (!id || !confirm('¿Seguro que desea eliminar esta categoría?')) return;
        const btn = document.getElementById('btnEliminarCatModal');
        
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchCat) window.fetchSearchCat();
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

})(window, document);
