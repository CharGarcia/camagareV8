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

        vehPrepararPestanas(0);
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
        vehPrepararPestanas(data.id);
        document.getElementById('modalAlert').classList.add('d-none');
        document.getElementById('btnEliminar')?.classList.remove('d-none');
        
        const btnSave = document.getElementById('btnGuardar');
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }

        getModalVeh()?.show();
    };

    // ─── Pestañas Transacciones / Recordatorios ───────────────────────────────
    const vehEsc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const vehFmt = v => (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const vehFecha = f => { const m = String(f || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/); return m ? `${m[3]}-${m[2]}-${m[1]}` + (m[4] ? ` ${m[4]}:${m[5]}:${m[6] || '00'}` : '') : ''; };
    let vehIdActual = 0;
    const vehCargado = { trx: false, rec: false };

    // Reinicia las pestañas: siempre se abre en General; las otras solo con un vehículo guardado.
    function vehPrepararPestanas(id) {
        vehIdActual = parseInt(id, 10) || 0;
        vehCargado.trx = false; vehCargado.rec = false;
        document.querySelectorAll('#vehTabs .veh-tab-requiere-id').forEach(a => {
            a.classList.toggle('disabled', !vehIdActual);
            a.title = vehIdActual ? '' : 'Guarde el vehículo para ver su información';
        });
        const gen = document.getElementById('veh-tab-general');
        if (gen && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(gen).show();
        const badge = document.getElementById('veh-badge-trx'); if (badge) badge.classList.add('d-none');
        const tb = document.getElementById('veh_trx_body'); if (tb) tb.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Sin transacciones.</td></tr>';
        const rs = document.getElementById('veh_trx_resumen'); if (rs) rs.innerHTML = '';
    }

    document.getElementById('veh-tab-transacciones')?.addEventListener('shown.bs.tab', () => { if (!vehCargado.trx) vehCargarTransacciones(); });
    document.getElementById('veh-tab-recordatorios')?.addEventListener('shown.bs.tab', () => { if (!vehCargado.rec) vehCargarRecordatorios(); });

    async function vehCargarTransacciones() {
        const tbody = document.getElementById('veh_trx_body');
        if (!vehIdActual || !tbody) return;
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const json = await (await fetch(`${urlBaseVeh}/transaccionesAjax?id=${vehIdActual}`)).json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar.');
            vehCargado.trx = true;
            const { ordenes, resumen } = json.data;
            const badge = document.getElementById('veh-badge-trx');
            if (badge) { badge.textContent = ordenes.length; badge.classList.toggle('d-none', !ordenes.length); }
            document.getElementById('veh_trx_resumen').innerHTML = `
                <span><i class="bi bi-list-check text-primary me-1"></i><b>${resumen.visitas}</b> ${resumen.visitas === 1 ? 'visita' : 'visitas'}</span>
                <span><i class="bi bi-cash-stack text-success me-1"></i>Total: <b>${vehFmt(resumen.total)}</b></span>
                <span><i class="bi bi-calendar-check text-info me-1"></i>Última visita: <b>${vehEsc(vehFecha(resumen.ultima_visita) || '—')}</b></span>
                <span><i class="bi bi-calendar-event text-warning me-1"></i>Próxima cita: <b>${vehEsc(vehFecha(resumen.proxima_cita) || '—')}</b></span>`;
            if (!ordenes.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Este vehículo aún no tiene órdenes de Car-Wash.</td></tr>';
                return;
            }
            const estados = { borrador: ['warning', 'Borrador'], facturado: ['success', 'Facturado'], anulado: ['danger', 'Anulado'] };
            tbody.innerHTML = ordenes.map(o => {
                const e = estados[o.estado] || ['secondary', o.estado || ''];
                const doc = o.numero_documento ? `${o.tipo_documento === 'FACTURA' ? 'Fact.' : 'Rec.'} ${vehEsc(o.numero_documento)}` : '<span class="text-muted">—</span>';
                const lineas = (o.lineas || []).map(l => `<tr>
                        <td class="ps-4 text-muted">${l.tipo_linea === 'producto' ? '<i class="bi bi-box-seam"></i>' : '<i class="bi bi-tools"></i>'}</td>
                        <td colspan="4">${vehEsc(l.producto_codigo ? l.producto_codigo + ' - ' : '')}${vehEsc(l.descripcion)}</td>
                        <td class="text-end">${(parseFloat(l.cantidad) || 0).toLocaleString('en-US', { maximumFractionDigits: 6 })} × ${vehFmt(l.precio_unitario)}${parseFloat(l.descuento) > 0 ? ' − ' + vehFmt(l.descuento) : ''}</td>
                        <td class="text-end">${vehFmt(l.total_linea)}</td><td colspan="2"></td></tr>`).join('');
                return `<tr class="veh-trx-orden" role="button" data-id="${o.id}">
                        <td class="text-center"><i class="bi bi-chevron-right small"></i></td>
                        <td>${vehEsc(o.fecha || '')}</td>
                        <td class="fw-semibold text-primary">${vehEsc(o.numero_orden || '')}</td>
                        <td class="text-truncate" style="max-width:200px">${vehEsc(o.cliente_nombre || '')}</td>
                        <td class="text-end">${o.kilometraje ? vehEsc(Number(o.kilometraje).toLocaleString('es-EC')) : ''}</td>
                        <td class="text-truncate" style="max-width:300px" title="${vehEsc(o.servicios || '')}">${vehEsc(o.servicios || '')}</td>
                        <td class="text-end">${vehFmt(o.total)}</td>
                        <td>${doc}</td>
                        <td class="text-center"><span class="badge bg-${e[0]} bg-opacity-10 text-${e[0]}">${vehEsc(e[1])}</span></td>
                    </tr>
                    <tr class="veh-trx-detalle d-none bg-light" data-det="${o.id}"><td colspan="9" class="p-0">
                        <table class="table table-sm mb-0 bg-light" style="font-size:.75rem;"><tbody>${lineas || '<tr><td class="ps-4 text-muted">Sin líneas.</td></tr>'}</tbody></table>
                        ${typeof window.cwAbrirVerId === 'function' ? `<div class="ps-4 pb-1"><button type="button" class="btn btn-link btn-sm p-0" onclick="event.stopPropagation(); vehAbrirOrden(${o.id})"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir la orden</button></div>` : ''}
                    </td></tr>`;
            }).join('');
            tbody.querySelectorAll('.veh-trx-orden').forEach(tr => tr.addEventListener('click', () => {
                const det = tbody.querySelector(`tr[data-det="${tr.dataset.id}"]`);
                const abierto = det.classList.toggle('d-none') === false;
                tr.querySelector('i.bi').className = 'bi small ' + (abierto ? 'bi-chevron-down' : 'bi-chevron-right');
            }));
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${vehEsc(e.message)}</td></tr>`;
        }
    }

    // Desde la página de Car-Wash, la orden se abre en su modal (se cierra el del vehículo).
    window.vehAbrirOrden = function (idOrden) {
        getModalVeh()?.hide();
        setTimeout(() => window.cwAbrirVerId(idOrden, false), 300);
    };

    async function vehCargarRecordatorios() {
        const tCitas = document.getElementById('veh_rec_citas');
        const tHist  = document.getElementById('veh_rec_historial');
        const aviso  = document.getElementById('veh_rec_aviso');
        if (!vehIdActual || !tCitas) return;
        tCitas.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const json = await (await fetch(`${urlBaseVeh}/recordatoriosAjax?id=${vehIdActual}`)).json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar.');
            vehCargado.rec = true;
            const { citas, historial, disponible } = json.data;
            aviso.innerHTML = disponible ? '' : '<div class="alert alert-warning py-1 small mb-2">Falta aplicar el SQL de recordatorios (20260929_carwash_recordatorios.sql): se pueden ver las citas, pero aún no enviar recordatorios.</div>';
            window.VEH_CITAS = {};
            const hoy = new Date().toISOString().slice(0, 10);
            tCitas.innerHTML = citas.length ? citas.map(c => {
                window.VEH_CITAS[c.id_orden] = c;
                const f = String(c.proxima_cita).slice(0, 10);
                const badge = c.vencida ? '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">pasada</span>'
                    : (f === hoy ? '<span class="badge bg-warning bg-opacity-10 text-warning ms-1">hoy</span>' : '<span class="badge bg-success bg-opacity-10 text-success ms-1">próxima</span>');
                const puede = disponible && window.VEH_PERM_ACTUALIZAR;
                return `<tr>
                    <td class="fw-semibold">${vehEsc(vehFecha(f))}${badge}</td>
                    <td>${vehEsc(c.numero_orden || '')}</td>
                    <td class="text-truncate" style="max-width:180px">${vehEsc(c.nombre_destino || '')}</td>
                    <td>${c.correo_destino ? vehEsc(c.correo_destino) : '<span class="text-muted">—</span>'}</td>
                    <td>${c.telefono_destino ? vehEsc(c.telefono_destino) : '<span class="text-muted">—</span>'}</td>
                    <td>${c.ultimo_envio ? vehEsc(vehFecha(c.ultimo_envio)) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" title="Enviar por correo" ${puede ? '' : 'disabled'} onclick="vehRecordatorioCorreo(${c.id_orden})"><i class="bi bi-envelope"></i></button>
                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" title="Enviar por WhatsApp" ${puede ? '' : 'disabled'} onclick="vehRecordatorioWhatsapp(${c.id_orden})"><i class="bi bi-whatsapp"></i></button>
                    </td></tr>`;
            }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Este vehículo no tiene citas registradas. La próxima cita se fija en la orden de Car-Wash.</td></tr>';

            const canal = { correo: '<i class="bi bi-envelope text-info me-1"></i>Correo', whatsapp: '<i class="bi bi-whatsapp text-success me-1"></i>WhatsApp' };
            tHist.innerHTML = historial.length ? historial.map(h => `<tr>
                    <td>${vehEsc(vehFecha(h.created_at))}</td>
                    <td>${vehEsc(vehFecha(h.fecha_cita))}</td>
                    <td>${vehEsc(h.numero_orden || '')}</td>
                    <td>${canal[h.canal] || vehEsc(h.canal)}</td>
                    <td class="text-truncate" style="max-width:200px">${vehEsc(h.destinatario || '')}</td>
                    <td class="text-center">${h.estado === 'enviado' ? '<span class="badge bg-success bg-opacity-10 text-success">Enviado</span>' : `<span class="badge bg-danger bg-opacity-10 text-danger" title="${vehEsc(h.detalle || '')}">Error</span>`}</td>
                    <td>${h.origen === 'automatico' ? '<i class="bi bi-robot me-1"></i>Automático' : 'Manual'}</td>
                    <td class="text-muted">${vehEsc(h.usuario || '')}</td></tr>`).join('')
                : '<tr><td colspan="8" class="text-center text-muted py-3">Aún no se han enviado recordatorios.</td></tr>';
        } catch (e) {
            tCitas.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-3">${vehEsc(e.message)}</td></tr>`;
        }
    }

    window.vehRecordatorioCorreo = async function (idOrden) {
        const c = (window.VEH_CITAS || {})[idOrden]; if (!c) return;
        const modalEl = document.getElementById('modalVehiculo');
        const { value: form } = await Swal.fire({
            title: 'Recordatorio por correo', width: 600, target: modalEl, heightAuto: false,
            html: `<div class="text-start small">
                <label class="fw-semibold mb-1">Para (separe varios con coma)</label>
                <input id="vehRecPara" class="form-control form-control-sm mb-2" value="${vehEsc(c.correo_destino || '')}" placeholder="cliente@correo.com">
                <label class="fw-semibold mb-1">Asunto</label>
                <input id="vehRecAsunto" class="form-control form-control-sm mb-2" value="${vehEsc(c.asunto_defecto || '')}">
                <label class="fw-semibold mb-1">Mensaje</label>
                <textarea id="vehRecMsg" class="form-control form-control-sm" rows="6">${vehEsc(c.mensaje_defecto || '')}</textarea></div>`,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar', cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const para = document.getElementById('vehRecPara').value.trim();
                if (!para) { Swal.showValidationMessage('Escriba al menos un correo.'); return false; }
                return { para, asunto: document.getElementById('vehRecAsunto').value, mensaje: document.getElementById('vehRecMsg').value };
            }
        });
        if (!form) return;
        Swal.fire({ title: 'Enviando...', allowOutsideClick: false, target: modalEl, heightAuto: false, didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id_orden', idOrden); fd.append('destinatarios', form.para); fd.append('asunto', form.asunto); fd.append('mensaje', form.mensaje);
            const json = await (await fetch(`${urlBaseVeh}/enviarRecordatorioCorreoAjax`, { method: 'POST', body: fd })).json();
            if (json.ok) Swal.fire({ icon: 'success', title: 'Enviado', text: json.msg, target: modalEl, heightAuto: false, timer: 2200, showConfirmButton: false });
            else Swal.fire({ icon: 'error', title: 'No se envió', text: json.error || 'Error al enviar.', target: modalEl, heightAuto: false });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', target: modalEl, heightAuto: false });
        }
        vehCargarRecordatorios();
    };

    // WhatsApp: abre WhatsApp (web o app) con el mensaje listo; el sistema deja constancia del envío.
    window.vehRecordatorioWhatsapp = async function (idOrden) {
        const c = (window.VEH_CITAS || {})[idOrden]; if (!c) return;
        const modalEl = document.getElementById('modalVehiculo');
        const { value: form } = await Swal.fire({
            title: 'Recordatorio por WhatsApp', width: 560, target: modalEl, heightAuto: false,
            html: `<div class="text-start small">
                <label class="fw-semibold mb-1">Teléfono</label>
                <input id="vehRecTel" class="form-control form-control-sm mb-2" value="${vehEsc(c.telefono_destino || '')}" placeholder="0987654321">
                <label class="fw-semibold mb-1">Mensaje</label>
                <textarea id="vehRecWaMsg" class="form-control form-control-sm" rows="6">${vehEsc(c.mensaje_defecto || '')}</textarea>
                <div class="form-text">Se abrirá WhatsApp con el mensaje listo para enviarlo.</div></div>`,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-whatsapp me-1"></i> Abrir WhatsApp', cancelButtonText: 'Cancelar',
            preConfirm: () => {
                let tel = document.getElementById('vehRecTel').value.replace(/\D/g, '');
                if (tel.length < 9) { Swal.showValidationMessage('Escriba un teléfono válido.'); return false; }
                if (!tel.startsWith('593')) tel = '593' + (tel.startsWith('0') ? tel.slice(1) : tel);
                return { tel, mensaje: document.getElementById('vehRecWaMsg').value };
            }
        });
        if (!form) return;
        window.open(`https://wa.me/${form.tel}?text=${encodeURIComponent(form.mensaje)}`, '_blank');
        try {
            const fd = new FormData();
            fd.append('id_orden', idOrden); fd.append('telefono', form.tel); fd.append('mensaje', form.mensaje);
            const json = await (await fetch(`${urlBaseVeh}/registrarRecordatorioWhatsappAjax`, { method: 'POST', body: fd })).json();
            if (!json.ok) Swal.fire({ icon: 'warning', title: 'Atención', text: json.error || 'No se pudo registrar el envío.', target: modalEl, heightAuto: false });
        } catch (e) { /* el mensaje ya se abrió en WhatsApp; solo no quedó registrado */ }
        vehCargarRecordatorios();
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
            const correo = document.getElementById('vehiculo_correo').value.trim();
            const telefono = document.getElementById('vehiculo_telefono').value.trim();

            if (!marca) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La marca es obligatoria.' });
            if (!placa) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La placa es obligatoria.' });

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
