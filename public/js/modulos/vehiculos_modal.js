/**
 * Lógica compartida para el Modal de Vehículos
 */

(function (window, document) {
    'use strict';

    const urlBaseVeh = BASE_URL + '/modulos/vehiculos';
    const formVeh = document.getElementById('formVehiculo');
    let modalInstVeh = null;

    function getModalVeh() {
        if (!modalInstVeh && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalVehiculo');
            if (el) modalInstVeh = new bootstrap.Modal(el);
        }
        return modalInstVeh;
    }

    window.abrirModalVehiculoCrear = function() {
        if (!formVeh) return;
        formVeh.reset();
        document.getElementById('vehiculo_id').value = '';
        document.getElementById('tituloModal').textContent = 'Nuevo Vehículo';
        document.getElementById('modalAlert').classList.add('d-none');
        document.getElementById('btnEliminar')?.classList.add('d-none');
        
        const btnSave = document.getElementById('btnGuardar');
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }

        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalVehiculo');
        }

        getModalVeh()?.show();
        setTimeout(() => { document.getElementById('vehiculo_marca')?.focus(); }, 500);
    };

    window.abrirModalVehiculoEditar = function(rowOrData) {
        let data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        if (!formVeh || !data) return;
        formVeh.reset();
        
        document.getElementById('vehiculo_id').value = data.id;
        document.getElementById('vehiculo_marca').value = data.marca || '';
        document.getElementById('vehiculo_placa').value = data.placa || '';
        document.getElementById('vehiculo_chasis').value = data.chasis || '';
        document.getElementById('vehiculo_anio').value = data.anio > 0 ? data.anio : '';
        document.getElementById('vehiculo_propietario').value = data.propietario || '';
        document.getElementById('vehiculo_estado').value = data.estado || 'activo';
        document.getElementById('vehiculo_correo').value = data.correo || '';
        document.getElementById('vehiculo_telefono').value = data.telefono || '';
        
        document.getElementById('tituloModal').textContent = 'Editar Vehículo';
        document.getElementById('modalAlert').classList.add('d-none');
        document.getElementById('btnEliminar')?.classList.remove('d-none');
        
        const btnSave = document.getElementById('btnGuardar');
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }

        getModalVeh()?.show();
    };

    async function fetchHistorialVeh(id) {
        const container = document.getElementById('auditoriaTimelineVeh');
        if (!container || !id) return;

        try {
            const resp = await fetch(`${urlBaseVeh}/getHistorialAjax?id=${id}&tabla=vehiculos`);
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
                            <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border" style="left: 0; top: 0; width: 22px; height: 22px; z-index: 2;">
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
                                    ${window.renderDetalleHistorialVeh(log.detalles)}
                                </div>
                            </div>
                        </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
            }
        } catch (e) { container.innerHTML = '<div class="text-center py-3 text-danger small">Error de carga.</div>'; }
    }

    window.renderDetalleHistorialVeh = function(detalle) {
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

    if (formVeh) {
        formVeh.addEventListener('submit', async (e) => {
            e.preventDefault();

            const marca = document.getElementById('vehiculo_marca').value.trim();
            const placa = document.getElementById('vehiculo_placa').value.trim();
            const propietario = document.getElementById('vehiculo_propietario').value.trim();
            const correo = document.getElementById('vehiculo_correo').value.trim();
            const telefono = document.getElementById('vehiculo_telefono').value.trim();

            if (!marca) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La marca es obligatoria.' });
            if (!placa) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La placa es obligatoria.' });
            if (!propietario) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El propietario es obligatorio.' });

            if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El correo electrónico no tiene un formato válido.' });
            }

            if (telefono && !/^[0-9]{10}$/.test(telefono)) {
                return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El teléfono debe contener exactamente 10 dígitos numéricos.' });
            }

            const id = document.getElementById('vehiculo_id').value;
            const btn = document.getElementById('btnGuardar');
            const url = id ? `${urlBaseVeh}/update` : `${urlBaseVeh}/store`;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            try {
                const fd = new FormData(formVeh);
                const resp = await fetch(url, { method: 'POST', body: fd });
                const json = await resp.json();

                if (json.ok) {
                    Swal.fire({ icon: 'success', title: '¡Guardado!', text: json.msg || 'Guardado correctamente.', timer: 2000, showConfirmButton: false });
                    setTimeout(() => { 
                        getModalVeh()?.hide(); 
                        if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                        window.dispatchEvent(new CustomEvent('vehiculoGuardado', { detail: json }));
                    }, 800);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Ocurrió un error al guardar.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            }
        });
    }

    window.eliminarVehiculo = async function() {
        const id = document.getElementById('vehiculo_id').value;
        const marca = document.getElementById('vehiculo_marca').value;
        const placa = document.getElementById('vehiculo_placa').value;
        if (!id) return;

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar vehículo?',
            html: `¿Está seguro de que desea eliminar el vehículo <strong>${marca} (${placa})</strong>?`,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (!conf.isConfirmed) return;

        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBaseVeh}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModalVeh()?.hide();
                Swal.fire({ icon: 'success', title: '¡Eliminado!', text: json.msg || 'Vehículo eliminado correctamente.', timer: 2000, showConfirmButton: false });
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'Ocurrió un error al eliminar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
        }
    };

})(window, document);
