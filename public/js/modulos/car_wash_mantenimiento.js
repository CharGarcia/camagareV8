/**
 * Car-Wash → orden → pestaña Mantenimiento (aceite de motor, aceite de caja y los demás
 * servicios por kilometraje) y la franja de aviso de la pestaña General.
 *
 * modal_orden.php llama a window.CW_MANT:
 *   reset()                  al limpiar el formulario
 *   cargar(idVeh, idOrden)   al elegir el vehículo o abrir una orden
 *   setEditable(ed, aparte)  orden editable / facturada (se guarda aparte con su botón)
 *   validar(), confirmarKm() antes de guardar; items(idVeh) va en el payload de store
 * El cálculo del estado es el mismo que el del servidor (MANT_UI.estado).
 */
(function (window, document) {
    'use strict';

    const RUTA = window.RUTA_MODULO_CW || '';
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const U = () => window.MANT_UI;
    const $ = id => document.getElementById(id);
    const modal = () => $('modalOrdenCW');

    let cur = null;          // { idVehiculo, idOrden, data }
    let editable = true;     // orden en borrador y con permiso
    let aparte = false;      // orden facturada: se guarda con "Guardar mantenimientos"
    let seq = 0;             // descarta respuestas viejas si se cambia de vehículo rápido
    let guardando = false;
    let verOcultos = false;  // mostrar los servicios marcados "no aplica"

    const kmOrden = () => {
        const v = ($('cw_kilometraje')?.value || '').trim();
        return v === '' || isNaN(parseInt(v, 10)) ? null : parseInt(v, 10);
    };
    const puedeConfigurar = () => !!cur?.data?.puede_configurar && (editable || aparte);
    const habilitado = () => !!cur?.data?.disponible && (editable || aparte);

    function placeholder(texto) {
        const b = $('cw_mant_body');
        if (b) b.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">${texto}</td></tr>`;
    }

    function reset() {
        cur = null; seq++; verOcultos = false;
        $('cw_mant_ocultos')?.classList.add('d-none');
        placeholder('Elija el vehículo de la orden.');
        const f = $('cw_mant_franja'); if (f) f.innerHTML = '';
        $('cw-badge-mant')?.classList.add('d-none');
        $('cw_mant_btn_servicios')?.classList.add('d-none');
        $('cw_mant_btn_guardar')?.classList.add('d-none');
        if ($('cw_mant_aviso')) $('cw_mant_aviso').innerHTML = '';
        if ($('cw_mant_km_ant')) $('cw_mant_km_ant').textContent = '—';
        pintarKm();
    }

    /** Lo que el usuario tiene marcado/escrito, por servicio (para no perderlo al recargar). */
    function capturar() {
        const out = {};
        document.querySelectorAll('#cw_mant_body tr[data-servicio]').forEach(tr => {
            out[tr.dataset.servicio] = {
                cambiado: tr.querySelector('.cw-mant-chk').checked,
                intervalo: tr.querySelector('.cw-mant-int').value,
                intervalo_editado: tr.dataset.intEditado === '1',
                proximo: tr.querySelector('.cw-mant-prox').value,
                manual: tr.querySelector('.cw-mant-prox').dataset.manual || '',
            };
        });
        return out;
    }

    async function cargar(idVehiculo, idOrden) {
        idVehiculo = parseInt(idVehiculo, 10) || 0;
        idOrden = parseInt(idOrden, 10) || 0;
        if (!idVehiculo || !RUTA) { reset(); return; }
        const preservar = cur && cur.idVehiculo === idVehiculo ? capturar() : null;
        const mio = ++seq;
        if (!preservar) placeholder('<span class="spinner-border spinner-border-sm me-1"></span>Cargando...');
        try {
            const j = await (await fetch(`${RUTA}/mantEstadoAjax?id_vehiculo=${idVehiculo}&id_orden=${idOrden}`)).json();
            if (mio !== seq) return;
            if (!j.ok) throw new Error(j.error || 'No se pudieron cargar los mantenimientos.');
            cur = { idVehiculo, idOrden, data: j.data };
            render(preservar);
        } catch (e) {
            if (mio !== seq) return;
            cur = null;
            placeholder(`<span class="text-danger">${esc(e.message)}</span>`);
        }
    }

    function render(preservar) {
        const d = cur.data;
        $('cw_mant_aviso').innerHTML = d.disponible ? ''
            : '<div class="alert alert-warning py-1 px-2 small mb-2">Falta aplicar el SQL <code>database/migrations/20261030_vehiculos_mantenimientos.sql</code> para controlar los mantenimientos.</div>';
        $('cw_mant_km_ant').textContent = d.km_actual != null ? U().km(d.km_actual) : '—';
        $('cw_mant_btn_servicios').classList.toggle('d-none', !(d.disponible && d.puede_configurar));
        $('cw_mant_btn_guardar').classList.toggle('d-none', !(d.disponible && aparte));
        if (!d.disponible) { placeholder('—'); pintarFranja(); return; }

        const dis = habilitado() ? '' : 'disabled';
        const disInt = puedeConfigurar() ? '' : 'disabled';
        const inp = 'form-control form-control-sm py-0';
        const puedeNA = puedeConfigurar() && !!d.no_aplica_disponible;
        // "No aplica a este vehículo": oculto (salvo que esta orden ya registró su cambio); se ve con el enlace.
        const ocultos = (d.servicios || []).filter(s => s.no_aplica && !s.en_orden);
        $('cw_mant_body').innerHTML = (d.servicios || []).map(s => {
            if (s.no_aplica && !s.en_orden) {
                if (!verOcultos) return '';
                return `<tr data-servicio-oculto="${s.id_servicio}" class="text-muted">
                    <td class="ps-2 fw-semibold"><s>${esc(s.nombre)}</s></td>
                    <td colspan="5"><i class="bi bi-eye-slash me-1"></i>No aplica a este vehículo: no se muestra ni se avisa al cliente.</td>
                    <td class="text-center"><button type="button" class="btn btn-outline-success btn-sm py-0 px-2 cw-mant-na" data-no-aplica="0" ${puedeNA ? '' : 'disabled'} title="Vuelve a aplicar a este vehículo"><i class="bi bi-eye"></i></button></td></tr>`;
            }
            const p = preservar?.[s.id_servicio];
            const eo = s.en_orden;
            const intervalo = p ? p.intervalo : (eo?.intervalo_km ?? s.intervalo_km);
            const cambiado = p ? p.cambiado : !!eo;
            const proximo = p ? p.proximo : (eo ? eo.proximo_km : '');
            // Un próximo guardado distinto de km + intervalo lo escribió el usuario: no se recalcula solo.
            const manual = p ? p.manual : (eo && eo.proximo_km !== eo.km + (eo.intervalo_km || 0) ? '1' : '');
            const ultimo = s.ultimo_km != null
                ? `${esc(U().km(s.ultimo_km))} <span class="text-muted">· ${esc(U().fecha(s.ultima_fecha))}${s.ultimo_orden ? ' · ' + esc(s.ultimo_orden) : ''}</span>`
                : '<span class="text-muted">—</span>';
            return `<tr data-servicio="${s.id_servicio}" data-aviso="${s.aviso_km}" data-prox-previo="${s.proximo_km ?? ''}" data-int-editado="${p?.intervalo_editado ? '1' : ''}">
                <td class="ps-2 fw-semibold">${esc(s.nombre)}${s.activo ? '' : ' <span class="badge bg-secondary bg-opacity-10 text-secondary">inactivo</span>'}</td>
                <td>${ultimo}</td>
                <td><div class="d-flex align-items-center gap-1">
                    <input type="number" min="1" step="500" class="${inp} cw-mant-int" style="width:85px" value="${esc(intervalo)}" ${disInt} title="${esc(s.intervalo_detalle)}">
                    ${U().origen(s)}
                    ${puedeConfigurar() ? '<button type="button" class="btn btn-link btn-sm p-0 cw-mant-marca" title="Aplicar este intervalo a otros vehículos de la misma marca"><i class="bi bi-diagram-3"></i></button>' : ''}
                </div></td>
                <td class="text-center"><input type="checkbox" class="form-check-input cw-mant-chk" ${cambiado ? 'checked' : ''} ${dis}></td>
                <td><input type="number" min="1" class="${inp} cw-mant-prox" style="width:110px" value="${esc(proximo)}" data-manual="${manual}" ${cambiado && habilitado() ? '' : 'disabled'}
                        placeholder="${s.proximo_km != null ? esc(U().km(s.proximo_km)) : ''}"></td>
                <td class="cw-mant-estado"></td>
                <td class="text-center">${puedeNA && !cambiado ? `<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 cw-mant-na" data-no-aplica="1" title="No aplica a este vehículo (deja de mostrarse y de avisarse)"><i class="bi bi-eye-slash"></i></button>` : ''}</td>
            </tr>`;
        }).join('') || `<tr><td colspan="7" class="text-center text-muted py-4">${ocultos.length ? 'Todos los servicios están marcados como «no aplica» en este vehículo.' : 'No hay servicios activos. Use <b>Servicios</b> para agregarlos.'}</td></tr>`;
        const lnk = $('cw_mant_ocultos');
        if (lnk) {
            lnk.classList.toggle('d-none', !ocultos.length);
            lnk.innerHTML = verOcultos
                ? '<i class="bi bi-eye-slash me-1"></i>Esconder los servicios que no aplican'
                : `<i class="bi bi-eye me-1"></i>Mostrar ${ocultos.length} ${ocultos.length === 1 ? 'servicio que no aplica' : 'servicios que no aplican'} a este vehículo`;
        }
        recalcular();
    }

    /** Próximos automáticos (km + intervalo), estados, franja y contador de la pestaña. */
    function recalcular() {
        pintarKm();
        if (!cur?.data?.disponible) { pintarFranja(); return; }
        const km = kmOrden();
        const ref = km ?? cur.data.km_actual;
        document.querySelectorAll('#cw_mant_body tr[data-servicio]').forEach(tr => {
            const chk = tr.querySelector('.cw-mant-chk');
            const prox = tr.querySelector('.cw-mant-prox');
            const intervalo = parseInt(tr.querySelector('.cw-mant-int').value, 10) || 0;
            if (chk.checked && !prox.dataset.manual) prox.value = km !== null && intervalo > 0 ? km + intervalo : '';
            const proximo = chk.checked ? prox.value : tr.dataset.proxPrevio;
            const e = U().estado(ref, proximo, tr.dataset.aviso);
            tr.dataset.estado = e.estado;
            tr.querySelector('.cw-mant-estado').innerHTML = chk.checked
                ? `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check2-circle me-1"></i>Cambiado en esta orden</span>`
                : U().badge(e);
        });
        pintarFranja();
    }

    function pintarKm() {
        const el = $('cw_mant_km'); if (!el) return;
        const km = kmOrden();
        el.textContent = km !== null ? U()?.km(km) || km : '—';
    }

    function pintarFranja() {
        const f = $('cw_mant_franja'); if (!f) return;
        const badge = $('cw-badge-mant');
        if (!cur?.data?.disponible) { f.innerHTML = ''; badge?.classList.add('d-none'); return; }
        const partes = []; let vencidos = 0;
        const km = kmOrden();
        if (km !== null && cur.data.km_actual != null && km < cur.data.km_actual) {
            partes.push(`<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25"><i class="bi bi-exclamation-triangle me-1"></i>El km es menor que el último registrado (${esc(U().km(cur.data.km_actual))})</span>`);
        }
        document.querySelectorAll('#cw_mant_body tr[data-servicio]').forEach(tr => {
            const s = (cur.data.servicios || []).find(x => String(x.id_servicio) === tr.dataset.servicio);
            if (!s) return;
            if (tr.querySelector('.cw-mant-chk').checked) {
                const p = tr.querySelector('.cw-mant-prox').value;
                partes.push(`<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check2-circle me-1"></i>${esc(s.nombre)}: cambiado${p ? ' · próximo ' + esc(U().km(p)) : ''}</span>`);
                return;
            }
            const est = tr.dataset.estado;
            if (est === 'vencido') vencidos++;
            if (est !== 'vencido' && est !== 'proximo') return;
            const e = U().estado(km ?? cur.data.km_actual, tr.dataset.proxPrevio, tr.dataset.aviso);
            const cls = est === 'vencido' ? 'danger' : 'warning';
            partes.push(`<a href="#" class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 text-decoration-none cw-mant-ir"><i class="bi bi-speedometer2 me-1"></i>${esc(s.nombre)}: ${esc(U().texto(e).toLowerCase())}</a>`);
        });
        f.innerHTML = partes.join('');
        if (badge) { badge.textContent = vencidos; badge.title = 'Mantenimientos vencidos'; badge.classList.toggle('d-none', !vencidos); }
    }

    function mostrar() {
        const t = $('cw-tab-mantenimiento');
        if (t && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(t).show();
    }

    /** Payload para store / guardarMantenimientosAjax. undefined = no tocar los mantenimientos. */
    function items(idVeh) {
        if (!cur?.data?.disponible || (idVeh !== undefined && String(cur.idVehiculo) !== String(idVeh))) return undefined;
        return [...document.querySelectorAll('#cw_mant_body tr[data-servicio]')].map(tr => ({
            id_servicio: parseInt(tr.dataset.servicio, 10),
            cambiado: tr.querySelector('.cw-mant-chk').checked,
            intervalo_km: parseInt(tr.querySelector('.cw-mant-int').value, 10) || 0,
            intervalo_editado: tr.dataset.intEditado === '1',
            proximo_km: parseInt(tr.querySelector('.cw-mant-prox').value, 10) || 0,
        }));
    }

    function validar() {
        if (!cur?.data?.disponible) return '';
        const km = kmOrden();
        const marcados = [...document.querySelectorAll('#cw_mant_body tr[data-servicio]')].filter(tr => tr.querySelector('.cw-mant-chk').checked);
        const nombre = tr => tr.querySelector('td').textContent.replace('inactivo', '').trim();
        if (marcados.length && km === null) {
            return 'Ingrese el kilometraje de la orden para registrar el cambio de: ' + marcados.map(nombre).join(', ') + '.';
        }
        for (const tr of marcados) {
            const p = parseInt(tr.querySelector('.cw-mant-prox').value, 10) || 0;
            if (p <= km) return `El próximo cambio de «${nombre(tr)}» debe ser mayor que el km de la orden (${U().km(km)}).`;
        }
        return '';
    }

    /** Km de la orden menor que el último conocido: se pide confirmación (puede ser un error de tipeo). */
    async function confirmarKm() {
        const km = kmOrden();
        if (!cur?.data?.disponible || km === null || cur.data.km_actual == null || km >= cur.data.km_actual) return true;
        const r = await Swal.fire({
            icon: 'warning', title: 'Revise el kilometraje', target: modal(), heightAuto: false,
            html: `El km de la orden (<b>${esc(U().km(km))}</b>) es menor que el último registrado del vehículo (<b>${esc(U().km(cur.data.km_actual))}</b>).<br>¿Guardar de todas formas?`,
            showCancelButton: true, confirmButtonText: 'Sí, guardar', cancelButtonText: 'Corregir',
        });
        if (!r.isConfirmed) setTimeout(() => $('cw_kilometraje')?.focus(), 200);
        return r.isConfirmed;
    }

    function setEditable(ed, guardarAparte) {
        editable = !!ed;
        aparte = !ed && !!guardarAparte;
        if (cur?.data) render(capturar());
        else $('cw_mant_btn_guardar')?.classList.add('d-none');
    }

    // ─── Eventos ──────────────────────────────────────────────────────────────
    $('cw_kilometraje')?.addEventListener('input', recalcular);
    $('cw_mant_body')?.addEventListener('input', (ev) => {
        const tr = ev.target.closest('tr[data-servicio]'); if (!tr) return;
        if (ev.target.classList.contains('cw-mant-int')) tr.dataset.intEditado = '1';
        if (ev.target.classList.contains('cw-mant-prox')) ev.target.dataset.manual = ev.target.value ? '1' : '';
        recalcular();
    });
    $('cw_mant_body')?.addEventListener('change', (ev) => {
        if (!ev.target.classList.contains('cw-mant-chk')) return;
        const tr = ev.target.closest('tr');
        const prox = tr.querySelector('.cw-mant-prox');
        prox.disabled = !ev.target.checked || !habilitado();
        if (ev.target.checked) prox.dataset.manual = '';
        else prox.value = '';
        recalcular();
        if (ev.target.checked && kmOrden() === null) {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Escriba el kilometraje de la orden (pestaña General).', timer: 2500, showConfirmButton: false });
        }
    });
    $('cw_mant_body')?.addEventListener('click', async (ev) => {
        const b = ev.target.closest('.cw-mant-marca'); if (!b || !cur) return;
        const tr = b.closest('tr');
        const s = (cur.data.servicios || []).find(x => String(x.id_servicio) === tr.dataset.servicio);
        if (s && await U().aplicarMismaMarca(RUTA, cur.idVehiculo, s.id_servicio, s.nombre, tr.querySelector('.cw-mant-int').value, modal())) {
            tr.dataset.intEditado = ''; // ya quedó guardado como intervalo del vehículo
            await cargar(cur.idVehiculo, cur.idOrden);
        }
    });
    // "No aplica a este vehículo" / "vuelve a aplicar" (permiso de modificar de Car-Wash).
    $('cw_mant_body')?.addEventListener('click', async (ev) => {
        const b = ev.target.closest('.cw-mant-na'); if (!b || !cur || guardando) return;
        const tr = b.closest('tr');
        const sid = tr.dataset.servicio || tr.dataset.servicioOculto;
        const s = (cur.data.servicios || []).find(x => String(x.id_servicio) === String(sid));
        const no = b.dataset.noAplica === '1';
        if (!s) return;
        if (no) {
            const c = await Swal.fire({ icon: 'question', title: `¿${esc(s.nombre)} no aplica a este vehículo?`, target: modal(), heightAuto: false,
                html: 'Deja de mostrarse en el vehículo y en sus órdenes, y no se le avisará al cliente. El historial se conserva y se puede volver a activar.',
                showCancelButton: true, confirmButtonText: 'Sí, no aplica', cancelButtonText: 'Cancelar' });
            if (!c.isConfirmed) return;
        }
        guardando = true;
        try {
            const fd = new FormData();
            fd.append('id_vehiculo', cur.idVehiculo); fd.append('id_servicio', sid); fd.append('no_aplica', no ? '1' : '0');
            const j = await (await fetch(`${RUTA}/mantNoAplicaAjax`, { method: 'POST', body: fd })).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo guardar.');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: j.msg, timer: 1600, showConfirmButton: false });
            await cargar(cur.idVehiculo, cur.idOrden);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: modal(), heightAuto: false });
        } finally { guardando = false; }
    });
    $('cw_mant_ocultos')?.addEventListener('click', (ev) => {
        ev.preventDefault();
        verOcultos = !verOcultos;
        if (cur?.data) render(capturar());
    });
    $('cw_mant_franja')?.addEventListener('click', (ev) => {
        if (!ev.target.closest('.cw-mant-ir')) return;
        ev.preventDefault(); mostrar();
    });
    $('cw_mant_btn_servicios')?.addEventListener('click', () => window.mantAbrirServicios?.(RUTA));
    document.addEventListener('mantenimientoServicioGuardado', () => {
        if (cur && modal()?.classList.contains('show')) cargar(cur.idVehiculo, cur.idOrden);
    });

    // Orden facturada: se guarda solo la pestaña (como el Reporte de salida).
    $('cw_mant_btn_guardar')?.addEventListener('click', async () => {
        if (guardando || !cur?.idOrden) return;
        const err = validar();
        if (err) { Swal.fire({ icon: 'warning', title: 'Atención', text: err, target: modal(), heightAuto: false }); return; }
        guardando = true;
        const btn = $('cw_mant_btn_guardar');
        const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/guardarMantenimientosAjax`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cur.idOrden, mantenimientos: items() || [] }),
            });
            const j = await res.json();
            if (!j.ok) throw new Error(j.error || 'No se pudo guardar.');
            // Recargar desde lo guardado (sin conservar lo escrito) antes de liberar el botón.
            const { idVehiculo, idOrden } = cur;
            cur = null;
            await cargar(idVehiculo, idOrden);
            Swal.fire({ icon: 'success', title: 'Listo', text: j.msg, target: modal(), heightAuto: false, timer: 1400, showConfirmButton: false });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: modal(), heightAuto: false });
        } finally {
            guardando = false; btn.disabled = false; btn.innerHTML = orig;
        }
    });

    window.CW_MANT = { reset, cargar, setEditable, items, validar, confirmarKm, mostrar };
})(window, document);
