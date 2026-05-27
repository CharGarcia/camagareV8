/**
 * Lógica compartida para el Modal de Vendedores
 */

(function (window, document) {
    'use strict';

    const urlBaseVendedores = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/vendedores') : (window.location.origin + '/sistema/public/modulos/vendedores');
    const formV = document.getElementById('formVendedor');
    let modalInstV = null;

    function getModalV() {
        if (!modalInstV) {
            const el = document.getElementById('modalVendedor');
            if (el) modalInstV = new bootstrap.Modal(el);
        }
        return modalInstV;
    }

    window.abrirModalVendedorCrear = function() {
        if (!formV) return;
        formV.reset();
        const timelineV = document.getElementById('auditoriaTimelineV');
        if (timelineV) timelineV.innerHTML = '<div class="text-center py-5 text-muted small">Aún no existe historial.</div>';
        
        const vid = document.getElementById('vendedor_id');
        if (vid) vid.value = '';
        
        const title = document.getElementById('tituloModalVendedorLabel');
        if (title) title.textContent = 'Nuevo Vendedor';
        
        const btnElim = document.getElementById('btnEliminarVendedorActual');
        if (btnElim) btnElim.classList.add('d-none');

        if (typeof bootstrap !== 'undefined') {
            const tabEl = document.getElementById('tab-general-vendedor-btn');
            if (tabEl) (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
        resetearInfoExtraV();
        const tabInfo = document.getElementById('tab-info-vendedor-btn');
        if (tabInfo) tabInfo.classList.add('disabled');

        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalVendedor');
        }

        const alertEl = document.getElementById('modalAlertVendedor');
        if (alertEl) alertEl.classList.add('d-none');

        getModalV()?.show();
    };

    window.abrirModalVendedorEditar = function(rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = JSON.parse(rowOrData.dataset.row || rowOrData.dataset.vendedor);
        } else {
            data = rowOrData;
        }

        if (!formV || !data) return;
        formV.reset();
        
        const vid = document.getElementById('vendedor_id');
        if (vid) vid.value = data.id;
        
        const vnom = document.getElementById('vendedor_nombre');
        if (vnom) vnom.value = data.nombre || '';
        
        const viden = document.getElementById('vendedor_identificacion');
        if (viden) viden.value = data.identificacion || '';
        
        const vcor = document.getElementById('vendedor_correo');
        if (vcor) vcor.value = data.correo || '';
        
        const vtel = document.getElementById('vendedor_telefono');
        if (vtel) vtel.value = data.telefono || '';
        
        const vdir = document.getElementById('vendedor_direccion');
        if (vdir) vdir.value = data.direccion || '';
        
        const vstat = document.getElementById('vendedor_status');
        if (vstat) vstat.value = data.status ?? 1;

        const title = document.getElementById('tituloModalVendedorLabel');
        if (title) title.textContent = 'Editar Vendedor';
        
        const btnElim = document.getElementById('btnEliminarVendedorActual');
        if (btnElim) btnElim.classList.remove('d-none');

        if (typeof bootstrap !== 'undefined') {
            const tabEl = document.getElementById('tab-general-vendedor-btn');
            if (tabEl) (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
        
        const tabInfo = document.getElementById('tab-info-vendedor-btn');
        if (tabInfo) tabInfo.classList.remove('disabled');
        
        fetchInformacionExtraV(data.id);
        fetchHistorialV(data.id);

        const alertEl = document.getElementById('modalAlertVendedor');
        if (alertEl) alertEl.classList.add('d-none');

        getModalV()?.show();
    };

    async function fetchInformacionExtraV(id) {
        resetearInfoExtraV('Cargando...');
        try {
            const resp = await fetch(`${urlBaseVendedores}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const elCount = document.getElementById('info_clientes_count_v');
                if (elCount) elCount.textContent = `${d.clientes_count} clientes`;
                fetchHistorialV(id);
            } else {
                resetearInfoExtraV('Error al cargar');
            }
        } catch (e) {
            resetearInfoExtraV('Error de red');
        }
    }

    function resetearInfoExtraV(msg = '—') {
        const elCount = document.getElementById('info_clientes_count_v');
        if (elCount) elCount.textContent = msg === '—' ? '0 clientes' : msg;
        
        const timeline = document.getElementById('auditoriaTimelineV');
        if (timeline && msg === '—') {
            timeline.innerHTML = '<div class="text-center py-5 text-muted small">Aún no existe historial.</div>';
        }
    }

    async function fetchHistorialV(id) {
        const container = document.getElementById('auditoriaTimelineV');
        if (!container || !id) return;

        try {
            const resp = await fetch(`${urlBaseVendedores}/getHistorialAjax?id=${id}&tabla=vendedores`);
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
                                    ${window.renderDetalleHistorialV(log.detalles)}
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

    window.renderDetalleHistorialV = function(detalle) {
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

    window.eliminarVendedor = async function() {
        const id = document.getElementById('vendedor_id')?.value;
        if (!id || !confirm('¿Está seguro de eliminar este vendedor?')) return;

        const fd = new FormData();
        fd.append('id_eliminar', id);

        try {
            const resp = await fetch(urlBaseVendedores + '/delete', {
                method: 'POST',
                body: fd
            });
            const json = await resp.json();
            if (json.ok) {
                getModalV()?.hide();
                if (typeof window.cargarListado === 'function' && window.location.href.includes('vendedores')) {
                    window.cargarListado();
                }
            } else {
                alert(json.error);
            }
        } catch (e) {
            alert('Error al eliminar vendedor');
        }
    };

    function initVendedorModalEvents() {
        if (!formV) return;

        formV.addEventListener('submit', async (e) => {
            e.preventDefault();
            const vid = document.getElementById('vendedor_id')?.value;
            const action = vid ? '/update' : '/store';
            const fd = new FormData(formV);
            const btn = document.getElementById('btnGuardarVendedorActual');
            const alertEl = document.getElementById('modalAlertVendedor');

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
            }
            if (alertEl) alertEl.classList.add('d-none');

            try {
                const resp = await fetch(urlBaseVendedores + action, {
                    method: 'POST',
                    body: fd
                });
                const json = await resp.json();

                if (alertEl) {
                    alertEl.textContent = json.msg || json.error || 'Error';
                    alertEl.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                    alertEl.classList.remove('d-none');
                }

                if (json.ok) {
                    // Si estamos en Clientes, podríamos necesitar actualizar el select (si es que no recarga catálogos)
                    // Nota: En clientes_modal.js ya forzamos la recarga de catálogos si se usa guardarVendedorRapido.
                    // Pero si se usa este modal estándar, podemos intentar lo mismo.
                    
                    const selClientes = document.getElementById('cliente_vendedor');
                    if (selClientes && !vid) {
                        const opt = new Option(fd.get('nombre'), json.id);
                        selClientes.add(opt);
                        selClientes.value = json.id;
                    }

                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                        }
                        getModalV()?.hide();
                        if (typeof window.cargarListado === 'function' && window.location.href.includes('vendedores')) {
                            window.cargarListado();
                        }
                    }, 800);
                } else {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                    }
                }
            } catch (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVendedorModalEvents);
    } else {
        initVendedorModalEvents();
    }

})(window, document);
