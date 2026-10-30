/**
 * Mantenimientos por kilometraje: utilidades compartidas (MANT_UI) y el modal del
 * catálogo de servicios (mantAbrirServicios). Lo usan la orden de Car-Wash y la ficha
 * del vehículo; cada una pasa la URL de su módulo, así se aplican sus permisos.
 *
 * Al guardar o eliminar un servicio emite 'mantenimientoServicioGuardado' para que la
 * pantalla de abajo vuelva a cargar su tabla de mantenimientos.
 */
(function (window, document) {
    'use strict';
    if (window.__CMG_mantServicios) return; // el partial puede incluirse más de una vez
    window.__CMG_mantServicios = true;

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ─── Utilidades de estado (mismo cálculo que VehiculoMantenimientoService::calcularEstado) ───
    window.MANT_UI = {
        esc,
        km(n) {
            if (n === null || n === undefined || n === '' || isNaN(parseInt(n, 10))) return '';
            return parseInt(n, 10).toLocaleString('es-EC').replace(/,/g, '.') + ' km';
        },
        fecha(f) {
            const m = String(f || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}-${m[2]}-${m[1]}` : '';
        },
        estado(kmActual, proximo, aviso) {
            if (proximo === null || proximo === undefined || proximo === '' || isNaN(parseInt(proximo, 10))) return { estado: 'sin_datos', faltan: null };
            if (kmActual === null || kmActual === undefined || kmActual === '' || isNaN(parseInt(kmActual, 10))) return { estado: 'al_dia', faltan: null };
            const faltan = parseInt(proximo, 10) - parseInt(kmActual, 10);
            return { estado: faltan <= 0 ? 'vencido' : (faltan <= (parseInt(aviso, 10) || 0) ? 'proximo' : 'al_dia'), faltan };
        },
        /** Texto corto del estado: "faltan 300 km", "vencido hace 800 km". */
        texto(e) {
            if (e.estado === 'sin_datos') return 'Sin registro';
            if (e.faltan === null) return 'Al día';
            if (e.estado === 'vencido') return e.faltan === 0 ? 'Le toca ahora' : 'Vencido hace ' + MANT_UI.km(-e.faltan);
            return (e.estado === 'proximo' ? 'Próximo: faltan ' : 'Al día: faltan ') + MANT_UI.km(e.faltan);
        },
        badge(e) {
            const cls = { al_dia: 'success', proximo: 'warning', vencido: 'danger', sin_datos: 'secondary' }[e.estado] || 'secondary';
            const ico = { al_dia: 'bi-check-circle', proximo: 'bi-exclamation-circle', vencido: 'bi-exclamation-triangle-fill', sin_datos: 'bi-dash-circle' }[e.estado];
            return `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25"><i class="bi ${ico} me-1"></i>${esc(MANT_UI.texto(e))}</span>`;
        },
        /** Ícono del origen del intervalo (propio / sugerido por la marca / catálogo). */
        origen(s) {
            const ico = { vehiculo: 'bi-person-check text-primary', sugerido: 'bi-stars text-info', catalogo: 'bi-journal-text text-secondary' }[s.intervalo_origen] || 'bi-journal-text text-secondary';
            return `<i class="bi ${ico}" title="${esc(s.intervalo_detalle || '')}"></i>`;
        },
        /**
         * "Aplicar a los demás de la misma marca/modelo": pregunta el alcance (con cuántos
         * vehículos cambian) y aplica. Devuelve true si se aplicó.
         */
        async aplicarMismaMarca(urlModulo, idVehiculo, idServicio, nombre, intervalo, target) {
            intervalo = parseInt(intervalo, 10) || 0;
            if (!idVehiculo || intervalo <= 0) return false;
            let alc;
            try {
                const j = await (await fetch(`${urlModulo}/mantAlcanceMarcaAjax?id_vehiculo=${idVehiculo}`)).json();
                if (!j.ok) throw new Error(j.error || 'No se pudo consultar.');
                alc = j.data;
            } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: e.message, target, heightAuto: false }); return false; }
            const opciones = Object.entries(alc).map(([k, a], i) => `
                <label class="d-flex align-items-center gap-2 border rounded-2 p-2 mb-1" style="cursor:pointer">
                    <input type="radio" name="mantAlc" value="${k}" ${i === 0 ? 'checked' : ''}>
                    <span>${k === 'modelo' ? 'Misma marca y modelo' : 'Toda la marca'} <b>${esc(a.texto)}</b>
                    <span class="text-muted">(${a.n} ${a.n === 1 ? 'vehículo más' : 'vehículos más'})</span></span>
                </label>`).join('');
            const r = await Swal.fire({
                title: 'Aplicar a otros vehículos', width: 520, target, heightAuto: false,
                html: `<div class="text-start small">
                    <p class="mb-2"><b>${esc(nombre)}</b> cada <b>${esc(MANT_UI.km(intervalo))}</b> en este vehículo y en:</p>${opciones}
                    <div class="form-text">Los próximos cambios ya registrados no cambian: el nuevo intervalo se usa desde el próximo cambio que se registre.</div></div>`,
                showCancelButton: true, confirmButtonText: 'Aplicar', cancelButtonText: 'Cancelar',
                preConfirm: () => document.querySelector('input[name="mantAlc"]:checked')?.value || 'modelo',
            });
            if (!r.isConfirmed) return false;
            try {
                const fd = new FormData();
                fd.append('id_vehiculo', idVehiculo); fd.append('id_servicio', idServicio);
                fd.append('alcance', r.value); fd.append('intervalo_km', intervalo);
                const j = await (await fetch(`${urlModulo}/mantAplicarMarcaAjax`, { method: 'POST', body: fd })).json();
                if (!j.ok) throw new Error(j.error || 'No se pudo aplicar.');
                Swal.fire({ icon: 'success', title: j.msg, target, heightAuto: false, timer: 1800, showConfirmButton: false });
                return true;
            } catch (e) { Swal.fire({ icon: 'error', title: 'Error', text: e.message, target, heightAuto: false }); return false; }
        },
    };

    // ─── Modal del catálogo de servicios ──────────────────────────────────────
    let urlActual = '';
    let puede = false;
    let guardando = false;
    const modalEl = () => document.getElementById('modalMantServicios');

    // Sobre otro modal (orden o vehículo): por encima de él y de su fondo (app.css fija .modal en 5060).
    document.addEventListener('show.bs.modal', (e) => {
        if (e.target !== modalEl()) return;
        e.target.style.setProperty('z-index', '5090', 'important');
        setTimeout(() => {
            const bds = document.querySelectorAll('.modal-backdrop');
            bds[bds.length - 1]?.style.setProperty('z-index', '5085', 'important');
        }, 0);
    });

    function fila(s) {
        const dis = puede ? '' : 'disabled';
        const inp = 'form-control form-control-sm py-0';
        return `<tr data-id="${s.id || 0}">
            <td class="ps-2"><input class="${inp} mant-srv-nombre" maxlength="100" value="${esc(s.nombre || '')}" placeholder="Ej. Filtro de aire" ${dis}></td>
            <td><input type="number" min="1" step="500" class="${inp} mant-srv-int" value="${s.intervalo_km || ''}" ${dis}></td>
            <td><input type="number" min="0" step="100" class="${inp} mant-srv-aviso" value="${s.aviso_km ?? ''}" ${dis}></td>
            <td><input type="number" class="${inp} mant-srv-orden" value="${s.orden ?? 0}" ${dis}></td>
            <td class="text-center"><input type="checkbox" class="form-check-input mant-srv-activo" ${s.activo !== false ? 'checked' : ''} ${dis}></td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 mant-srv-guardar" title="Guardar" ${dis}><i class="bi bi-check2"></i></button>
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 mant-srv-eliminar" title="Eliminar" ${dis}><i class="bi bi-trash3"></i></button>
            </td></tr>`;
    }

    async function cargar() {
        const body = document.getElementById('mant_srv_body');
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const j = await (await fetch(`${urlActual}/mantServiciosAjax`)).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo cargar.');
            document.getElementById('mant_srv_aviso').innerHTML = !j.disponible
                ? '<div class="alert alert-warning py-1 small">Falta aplicar el SQL <code>database/migrations/20261030_vehiculos_mantenimientos.sql</code>.</div>'
                : (!j.puede_configurar ? '<div class="alert alert-light border py-1 small"><i class="bi bi-lock me-1"></i>Solo lectura: cambiar los servicios requiere permiso de modificar.</div>' : '');
            puede = !!j.disponible && !!j.puede_configurar;
            document.getElementById('mant_srv_agregar').classList.toggle('d-none', !puede);
            body.innerHTML = (j.data || []).map(fila).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">No hay servicios.</td></tr>';
        } catch (e) {
            body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${esc(e.message)}</td></tr>`;
        }
    }

    window.mantAbrirServicios = function (urlModulo) {
        urlActual = urlModulo;
        const el = modalEl();
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).show();
        cargar();
    };

    document.addEventListener('click', async (ev) => {
        const el = modalEl();
        if (!el || !el.contains(ev.target)) return;
        if (ev.target.closest('#mant_srv_agregar')) {
            const body = document.getElementById('mant_srv_body');
            if (!body.querySelector('tr[data-id]')) body.innerHTML = '';
            body.insertAdjacentHTML('beforeend', fila({ id: 0, nombre: '', intervalo_km: '', aviso_km: 500, orden: body.querySelectorAll('tr[data-id]').length + 1, activo: true }));
            body.lastElementChild.querySelector('.mant-srv-nombre').focus();
            return;
        }
        const btnG = ev.target.closest('.mant-srv-guardar');
        const btnE = ev.target.closest('.mant-srv-eliminar');
        if (!btnG && !btnE) return;
        const tr = (btnG || btnE).closest('tr');
        const id = parseInt(tr.dataset.id, 10) || 0;
        if (guardando) return;

        if (btnE) {
            if (!id) { tr.remove(); return; }
            let n = 0;
            try { n = (await (await fetch(`${urlActual}/mantUsoServicioAjax?id=${id}`)).json()).n || 0; } catch (e) {}
            const c = await Swal.fire({
                icon: 'warning', title: '¿Eliminar servicio?', target: el, heightAuto: false,
                html: (n ? `<b>${n}</b> ${n === 1 ? 'vehículo tiene' : 'vehículos tienen'} datos de este servicio. Dejarán de mostrarse y no se avisará más (el historial se conserva).<br>` : '')
                    + 'Si solo quiere dejar de usarlo por un tiempo, desmarque <b>Activo</b>.',
                showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            });
            if (!c.isConfirmed) return;
        }
        guardando = true;
        (btnG || btnE).disabled = true;
        try {
            const fd = new FormData();
            fd.append('id', id);
            if (btnG) {
                fd.append('nombre', tr.querySelector('.mant-srv-nombre').value.trim());
                fd.append('intervalo_km', tr.querySelector('.mant-srv-int').value);
                fd.append('aviso_km', tr.querySelector('.mant-srv-aviso').value || 0);
                fd.append('orden', tr.querySelector('.mant-srv-orden').value || 0);
                fd.append('activo', tr.querySelector('.mant-srv-activo').checked ? '1' : '0');
            }
            const j = await (await fetch(`${urlActual}/${btnG ? 'mantGuardarServicioAjax' : 'mantEliminarServicioAjax'}`, { method: 'POST', body: fd })).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo guardar.');
            // El id queda puesto antes de liberar el botón: un segundo clic ya edita, no crea otro.
            if (btnG && j.data) tr.dataset.id = j.data.id;
            if (btnE) tr.remove();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: j.msg, timer: 1500, showConfirmButton: false });
            document.dispatchEvent(new CustomEvent('mantenimientoServicioGuardado', { detail: { ...(j.data || { id }), nuevo: !!j.nuevo, eliminado: !!btnE } }));
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: el, heightAuto: false });
        } finally {
            guardando = false;
            (btnG || btnE).disabled = false;
        }
    });
})(window, document);
