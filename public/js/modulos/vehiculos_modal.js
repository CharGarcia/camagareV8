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
        vehSriInfo(''); vehSriUltimaPlaca = '';
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
        setTimeout(() => { document.getElementById('vehiculo_placa')?.focus(); }, 500);
    };

    window.abrirModalVehiculoEditar = function(rowOrData) {
        let data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        if (!formVeh || !data) return;
        formVeh.reset();
        vehSriInfo(''); vehSriUltimaPlaca = '';
        
        document.getElementById('vehiculo_id').value = data.id;
        document.getElementById('vehiculo_marca').value = data.marca || '';
        document.getElementById('vehiculo_placa').value = data.placa || '';
        document.getElementById('vehiculo_chasis').value = data.chasis || '';
        const inpMotor = document.getElementById('vehiculo_motor'); if (inpMotor) inpMotor.value = data.motor || '';
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
    const vehCargado = { trx: false, rec: false, mant: false };

    // Reinicia las pestañas: siempre se abre en General; las otras solo con un vehículo guardado.
    function vehPrepararPestanas(id) {
        vehIdActual = parseInt(id, 10) || 0;
        vehCargado.trx = false; vehCargado.rec = false; vehCargado.mant = false;
        const bm = document.getElementById('veh-badge-mant'); if (bm) bm.classList.add('d-none');
        const mb = document.getElementById('veh_mant_body'); if (mb) mb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Sin datos.</td></tr>';
        const mh = document.getElementById('veh_mant_hist'); if (mh) mh.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Aún no hay cambios registrados.</td></tr>';
        const mr = document.getElementById('veh_mant_registrar'); if (mr) mr.disabled = true;
        const ma = document.getElementById('veh_mant_avisos'); if (ma) ma.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Aún no se han enviado avisos.</td></tr>';
        const me = document.getElementById('veh_mant_est'); if (me) me.innerHTML = '';
        ['veh_mant_aviso_correo', 'veh_mant_aviso_wa'].forEach(id => { const b = document.getElementById(id); if (b) b.disabled = true; });
        document.querySelectorAll('#vehTabs .veh-tab-requiere-id').forEach(a => {
            a.classList.toggle('disabled', !vehIdActual);
            a.title = vehIdActual ? '' : 'Guarde el vehículo para ver su información';
        });
        const gen = document.getElementById('veh-tab-general');
        if (gen && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(gen).show();
        const badge = document.getElementById('veh-badge-trx'); if (badge) badge.classList.add('d-none');
        const tb = document.getElementById('veh_trx_body'); if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin transacciones.</td></tr>';
        const rs = document.getElementById('veh_trx_resumen'); if (rs) rs.innerHTML = '';
        const bq = document.getElementById('veh_trx_buscar'); if (bq) bq.value = '';
        vehLlenarFiltroTrx('veh_trx_categoria', 'Todas las categorías', []);
        vehLlenarFiltroTrx('veh_trx_marca', 'Todas las marcas', []);
        document.getElementById('veh_trx_limpiar')?.classList.add('d-none');
        const cn = document.getElementById('veh_trx_conteo'); if (cn) cn.textContent = '';
        const ag = document.getElementById('veh_rec_agregar'); if (ag) ag.disabled = true; // se habilita al cargar la pestaña
    }

    // Opciones de Categoría / Marca: solo las que aparecen en los ítems de este vehículo.
    function vehLlenarFiltroTrx(id, etiquetaTodas, valores) {
        const sel = document.getElementById(id);
        if (!sel) return;
        const unicos = [...new Set(valores.map(v => String(v || '').trim()).filter(Boolean))]
            .sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));
        sel.innerHTML = `<option value="">${vehEsc(etiquetaTodas)}</option>`
            + unicos.map(v => `<option value="${vehEsc(vehNorm(v))}">${vehEsc(v)}</option>`).join('');
        sel.disabled = !unicos.length;
    }

    // ─── Buscar ítems dentro de las órdenes (Transacciones) ───────────────────
    // Filtra en el navegador (las líneas ya vienen con las órdenes). Varias palabras en
    // cualquier orden, sin importar mayúsculas ni tildes, sobre código + descripción +
    // categoría + marca del ítem; además, los selectores de Categoría y Marca. Una orden queda
    // si alguno de sus ítems cumple TODO lo pedido (texto, categoría y marca); con solo texto,
    // también si las palabras están en su número de orden o de documento. Mientras hay un
    // filtro, las órdenes que coinciden se muestran desplegadas solo con los ítems encontrados
    // (resaltados); al limpiar vuelven a encogerse con todos sus ítems.
    const vehNorm = s => String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    let vehTrxTimer = null;
    function vehFiltrarTransacciones() {
        const tbody = document.getElementById('veh_trx_body');
        const inp = document.getElementById('veh_trx_buscar');
        if (!tbody || !inp) return;
        const palabras = vehNorm(inp.value).split(/\s+/).filter(Boolean);
        const cat = document.getElementById('veh_trx_categoria')?.value || '';
        const mar = document.getElementById('veh_trx_marca')?.value || '';
        const hayFiltro = palabras.length > 0 || cat !== '' || mar !== '';
        const tiene = txt => palabras.every(p => txt.includes(p));
        document.getElementById('veh_trx_limpiar')?.classList.toggle('d-none', !hayFiltro);
        let total = 0, visibles = 0;
        tbody.querySelectorAll('tr.veh-trx-orden').forEach(tr => {
            total++;
            const det = tbody.querySelector(`tr[data-det="${tr.dataset.id}"]`);
            const lineas = det ? det.querySelectorAll('tr[data-txt]') : [];
            let coinciden = 0;
            lineas.forEach(l => {
                const si = hayFiltro && tiene(l.dataset.txt)
                    && (cat === '' || l.dataset.cat === cat) && (mar === '' || l.dataset.mar === mar);
                l.classList.toggle('veh-trx-coincide', si);
                if (si) coinciden++;
            });
            // El número de orden/documento solo cuenta cuando no se eligió categoría ni marca.
            const porNumero = palabras.length > 0 && cat === '' && mar === '' && tiene(tr.dataset.txt || '');
            const muestra = !hayFiltro || coinciden > 0 || porNumero;
            // Con un filtro solo se ven los ítems que coinciden; si la orden se encontró por su
            // número, se ven todos sus ítems.
            lineas.forEach(l => l.classList.toggle('d-none',
                hayFiltro && !porNumero && !l.classList.contains('veh-trx-coincide')));
            tr.classList.toggle('d-none', !muestra);
            if (muestra) visibles++;
            if (det) {
                const abrir = muestra && hayFiltro;
                det.classList.toggle('d-none', !abrir);
                tr.querySelector('i.bi').className = 'bi small ' + (abrir ? 'bi-chevron-down' : 'bi-chevron-right');
            }
        });
        const vacio = tbody.querySelector('tr.veh-trx-sin-resultados');
        if (hayFiltro && total && !visibles) {
            if (!vacio) tbody.insertAdjacentHTML('beforeend', '<tr class="veh-trx-sin-resultados"><td colspan="7" class="text-center text-muted py-4">Ningún ítem coincide con la búsqueda.</td></tr>');
        } else if (vacio) {
            vacio.remove();
        }
        const cn = document.getElementById('veh_trx_conteo');
        if (cn) cn.textContent = hayFiltro ? `${visibles} de ${total} ${total === 1 ? 'orden' : 'órdenes'}` : '';
    }
    document.getElementById('veh_trx_buscar')?.addEventListener('input', () => {
        clearTimeout(vehTrxTimer);
        vehTrxTimer = setTimeout(vehFiltrarTransacciones, 200);
    });
    ['veh_trx_categoria', 'veh_trx_marca'].forEach(id =>
        document.getElementById(id)?.addEventListener('change', vehFiltrarTransacciones));
    document.getElementById('veh_trx_limpiar')?.addEventListener('click', () => {
        const inp = document.getElementById('veh_trx_buscar');
        inp.value = '';
        ['veh_trx_categoria', 'veh_trx_marca'].forEach(id => { const s = document.getElementById(id); if (s) s.value = ''; });
        vehFiltrarTransacciones(); inp.focus();
    });

    document.getElementById('veh-tab-transacciones')?.addEventListener('shown.bs.tab', () => { if (!vehCargado.trx) vehCargarTransacciones(); });
    document.getElementById('veh-tab-recordatorios')?.addEventListener('shown.bs.tab', () => { if (!vehCargado.rec) vehCargarRecordatorios(); });
    document.getElementById('veh-tab-mantenimiento')?.addEventListener('shown.bs.tab', () => { if (!vehCargado.mant) vehCargarMantenimiento(); });

    // ─── Pestaña Mantenimiento (próximos cambios por kilometraje) ─────────────
    let vehMant = null;      // última respuesta de mantEstadoAjax
    let vehMantOcupado = false;
    const vehModalEl = () => document.getElementById('modalVehiculo');

    async function vehCargarMantenimiento() {
        const body = document.getElementById('veh_mant_body');
        const hist = document.getElementById('veh_mant_hist');
        if (!vehIdActual || !body || !window.MANT_UI) return;
        const U = window.MANT_UI;
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const json = await (await fetch(`${urlBaseVeh}/mantEstadoAjax?id_vehiculo=${vehIdActual}&historial=1`)).json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar.');
            vehCargado.mant = true;
            vehMant = json.data;
            const d = vehMant;
            document.getElementById('veh_mant_aviso').innerHTML = d.disponible ? ''
                : '<div class="alert alert-warning py-1 small mb-2">Falta aplicar el SQL <code>database/migrations/20261030_vehiculos_mantenimientos.sql</code>.</div>';
            document.getElementById('veh_mant_km').textContent = d.km_actual != null ? U.km(d.km_actual) : '—';
            const es = d.estimacion;
            document.getElementById('veh_mant_est').innerHTML = es
                ? `· Estimado hoy: <b>${U.esc(U.km(es.km_est))}</b> <span title="${es.dia_defecto ? 'Con una sola lectura de km se supone 1.000 km al mes' : 'Según el km de sus órdenes'}">(≈ ${U.esc(String(Math.round(es.km_dia)))} km/día${es.dia_defecto ? ', supuesto' : ''})</span>`
                : '';
            const puedeMod = !!d.disponible && !!window.VEH_PERM_ACTUALIZAR;
            document.getElementById('veh_mant_registrar').disabled = !puedeMod;
            const hayProximos = (d.servicios || []).some(s => s.proximo_vigente != null);
            ['veh_mant_aviso_correo', 'veh_mant_aviso_wa'].forEach(id => {
                const b = document.getElementById(id);
                b.disabled = !(puedeMod && d.avisos_disponible && hayProximos);
                b.title = !d.avisos_disponible ? 'Falta aplicar el SQL de avisos (20261031_mantenimientos_avisos.sql)'
                    : (!hayProximos ? 'El vehículo no tiene próximos cambios registrados' : (id.endsWith('wa') ? 'Avisar al cliente por WhatsApp' : 'Avisar al cliente por correo'));
            });
            const canal = { correo: '<i class="bi bi-envelope text-info me-1"></i>Correo', whatsapp: '<i class="bi bi-whatsapp text-success me-1"></i>WhatsApp' };
            document.getElementById('veh_mant_avisos').innerHTML = (d.avisos || []).map(a => `<tr>
                    <td>${U.esc(a.creado_txt)}</td>
                    <td>${U.esc(a.servicio || '')}</td>
                    <td class="text-end">${U.esc(U.km(a.proximo_km))}</td>
                    <td>${canal[a.canal] || U.esc(a.canal)}</td>
                    <td class="text-truncate" style="max-width:180px">${U.esc(a.destinatario || '')}</td>
                    <td class="text-center">${a.estado === 'enviado' ? '<span class="badge bg-success bg-opacity-10 text-success">Enviado</span>' : `<span class="badge bg-danger bg-opacity-10 text-danger" title="${U.esc(a.detalle || '')}">Error</span>`}</td>
                    <td>${a.origen === 'automatico' ? `<i class="bi bi-robot me-1"></i>Automático${a.aviso_n > 1 ? ` <span class="text-muted">(recordatorio ${a.aviso_n - 1})</span>` : ''}` : 'Manual'}</td>
                    <td class="text-muted">${U.esc(a.usuario || '')}</td></tr>`).join('')
                || '<tr><td colspan="8" class="text-center text-muted py-3">Aún no se han enviado avisos.</td></tr>';
            document.getElementById('veh_mant_servicios').disabled = !d.disponible;

            let vencidos = 0;
            body.innerHTML = (d.servicios || []).map(s => {
                const e = { estado: s.estado, faltan: s.faltan_km };
                if (s.estado === 'vencido') vencidos++;
                return `<tr data-servicio="${s.id_servicio}">
                    <td class="fw-semibold">${U.esc(s.nombre)}</td>
                    <td><div class="d-flex align-items-center gap-1">
                        <input type="number" min="1" step="500" class="form-control form-control-sm py-0 veh-mant-int" style="width:90px" value="${s.intervalo_km}" data-propio="${s.intervalo_propio ?? ''}" ${puedeMod ? '' : 'disabled'} title="${U.esc(s.intervalo_detalle)}">
                        ${U.origen(s)}
                        ${puedeMod ? `<button type="button" class="btn btn-link btn-sm p-0 veh-mant-marca" title="Aplicar este intervalo a otros vehículos de la misma marca"><i class="bi bi-diagram-3"></i></button>` : ''}
                    </div></td>
                    <td>${vehMantUltimo(s) || '<span class="text-muted">—</span>'}</td>
                    <td class="fw-semibold">${s.proximo_vigente != null ? U.esc(U.km(s.proximo_vigente)) : '<span class="text-muted">—</span>'}</td>
                    <td>${U.badge(e)}</td></tr>`;
            }).join('') || '<tr><td colspan="5" class="text-center text-muted py-3">No hay servicios activos. Use <b>Servicios</b> para agregarlos.</td></tr>';
            const bm = document.getElementById('veh-badge-mant');
            if (bm) { bm.textContent = vencidos; bm.classList.toggle('d-none', !vencidos); bm.title = 'Mantenimientos vencidos'; }

            const puedeElim = !!d.disponible && !!window.VEH_PERM_ELIMINAR;
            hist.innerHTML = (d.historial || []).map(h => `<tr>
                    <td>${U.esc(h.fecha_txt)}</td>
                    <td>${U.esc(h.servicio)}</td>
                    <td class="text-end">${U.esc(U.km(h.km))}</td>
                    <td class="text-end">${U.esc(U.km(h.proximo_km))}</td>
                    <td>${h.origen === 'orden'
                        ? `<i class="bi bi-droplet-half text-info me-1"></i>Orden ${U.esc(h.numero_orden || '')}`
                        : `<i class="bi bi-pencil-square text-secondary me-1"></i>A mano${h.observacion ? ` <span class="text-muted d-inline-block text-truncate align-bottom" style="max-width:160px" title="${U.esc(h.observacion)}">· ${U.esc(h.observacion)}</span>` : ''}`}</td>
                    <td class="text-muted">${U.esc(h.usuario || '')}</td>
                    <td class="text-center">${h.origen === 'manual'
                        ? `<button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar este cambio" ${puedeElim ? '' : 'disabled'} onclick="vehMantEliminarCambio(${h.id})"><i class="bi bi-trash3"></i></button>` : ''}</td>
                </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Aún no hay cambios registrados.</td></tr>';
        } catch (e) {
            body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${vehEsc(e.message)}</td></tr>`;
        }
    }

    // "Último cambio" vigente (con el de la última orden): km · fecha.
    function vehMantUltimo(s) {
        const h = (vehMant?.historial || []).find(x => String(x.id_servicio) === String(s.id_servicio));
        if (!h) return '';
        return `${vehEsc(window.MANT_UI.km(h.km))} <span class="text-muted">· ${vehEsc(h.fecha_txt)}</span>`;
    }

    // Cambiar "Cada (km)": queda como intervalo propio del vehículo (vacío = volver al sugerido).
    document.getElementById('veh_mant_body')?.addEventListener('change', async (ev) => {
        const inp = ev.target.closest('.veh-mant-int');
        if (!inp || vehMantOcupado) return;
        const sid = inp.closest('tr').dataset.servicio;
        const valor = inp.value.trim();
        if (valor !== '' && !(parseInt(valor, 10) > 0)) { inp.value = inp.defaultValue; return; }
        vehMantOcupado = true; inp.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_vehiculo', vehIdActual); fd.append('id_servicio', sid); fd.append('intervalo_km', valor);
            const j = await (await fetch(`${urlBaseVeh}/mantGuardarIntervaloAjax`, { method: 'POST', body: fd })).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo guardar.');
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: j.msg, timer: 1400, showConfirmButton: false });
            await vehCargarMantenimiento();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: vehModalEl(), heightAuto: false });
            inp.value = inp.defaultValue;
        } finally { vehMantOcupado = false; inp.disabled = false; }
    });
    document.getElementById('veh_mant_body')?.addEventListener('click', async (ev) => {
        const b = ev.target.closest('.veh-mant-marca');
        if (!b) return;
        const tr = b.closest('tr');
        const s = (vehMant?.servicios || []).find(x => String(x.id_servicio) === tr.dataset.servicio);
        if (s && await window.MANT_UI.aplicarMismaMarca(urlBaseVeh, vehIdActual, s.id_servicio, s.nombre, tr.querySelector('.veh-mant-int').value, vehModalEl())) {
            await vehCargarMantenimiento();
        }
    });
    document.getElementById('veh_mant_servicios')?.addEventListener('click', () => window.mantAbrirServicios?.(urlBaseVeh));
    document.addEventListener('mantenimientoServicioGuardado', () => {
        if (vehModalEl()?.classList.contains('show') && vehCargado.mant) vehCargarMantenimiento();
    });

    // Registrar a mano un cambio hecho fuera (o sin orden): servicio, km, fecha y próximo cambio.
    document.getElementById('veh_mant_registrar')?.addEventListener('click', async () => {
        if (vehMantOcupado || !vehIdActual || !vehMant) return;
        const U = window.MANT_UI;
        const servs = (vehMant.servicios || []).filter(s => s.activo);
        if (!servs.length) { Swal.fire({ icon: 'info', title: 'No hay servicios activos', target: vehModalEl(), heightAuto: false }); return; }
        const hoy = new Date(); const hoyIso = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
        const { value: form } = await Swal.fire({
            title: 'Registrar cambio', width: 520, target: vehModalEl(), heightAuto: false,
            html: `<div class="text-start small">
                <label class="fw-semibold mb-1 d-block">Servicio</label>
                <select id="vehMantSrv" class="form-select form-select-sm mb-2">${servs.map(s => `<option value="${s.id_servicio}" data-int="${s.intervalo_km}">${U.esc(s.nombre)}</option>`).join('')}</select>
                <div class="row g-2 mb-2">
                    <div class="col-4"><label class="fw-semibold mb-1 d-block">Km del cambio</label><input type="number" min="0" id="vehMantKm" class="form-control form-control-sm" value="${vehMant.km_actual ?? ''}"></div>
                    <div class="col-4"><label class="fw-semibold mb-1 d-block">Fecha</label><input type="date" id="vehMantFecha" class="form-control form-control-sm" max="${hoyIso}" value="${hoyIso}"></div>
                    <div class="col-4"><label class="fw-semibold mb-1 d-block">Próximo (km)</label><input type="number" min="1" id="vehMantProx" class="form-control form-control-sm"></div>
                </div>
                <label class="fw-semibold mb-1 d-block">Observación <span class="text-muted fw-normal">(opcional, p. ej. dónde se hizo)</span></label>
                <input id="vehMantObs" class="form-control form-control-sm" maxlength="300">
                <div class="form-text">El próximo cambio se calcula con el intervalo del vehículo; puede corregirlo.</div></div>`,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Registrar', cancelButtonText: 'Cancelar',
            didOpen: () => {
                const sel = document.getElementById('vehMantSrv'), km = document.getElementById('vehMantKm'), px = document.getElementById('vehMantProx');
                const calc = () => { if (px.dataset.manual) return; const k = parseInt(km.value, 10); px.value = k >= 0 ? k + (parseInt(sel.selectedOptions[0].dataset.int, 10) || 0) : ''; };
                px.addEventListener('input', () => { px.dataset.manual = px.value ? '1' : ''; });
                sel.addEventListener('change', calc); km.addEventListener('input', calc); calc();
                km.focus();
            },
            preConfirm: () => {
                const km = document.getElementById('vehMantKm').value, px = document.getElementById('vehMantProx').value;
                if (km === '' || parseInt(km, 10) < 0) { Swal.showValidationMessage('Ingrese el km del cambio.'); return false; }
                if (!(parseInt(px, 10) > parseInt(km, 10))) { Swal.showValidationMessage('El próximo cambio debe ser mayor que el km del cambio.'); return false; }
                return { id_servicio: document.getElementById('vehMantSrv').value, km, fecha: document.getElementById('vehMantFecha').value, proximo_km: px, observacion: document.getElementById('vehMantObs').value.trim() };
            }
        });
        if (!form || vehMantOcupado) return;
        vehMantOcupado = true;
        try {
            const fd = new FormData(); fd.append('id_vehiculo', vehIdActual);
            Object.entries(form).forEach(([k, v]) => fd.append(k, v));
            const j = await (await fetch(`${urlBaseVeh}/mantRegistrarCambioAjax`, { method: 'POST', body: fd })).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo registrar.');
            Swal.fire({ icon: 'success', title: j.msg, target: vehModalEl(), heightAuto: false, timer: 1400, showConfirmButton: false });
            await vehCargarMantenimiento(); // recargar antes de liberar: un segundo clic ya ve el cambio creado
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'No se registró', text: e.message, target: vehModalEl(), heightAuto: false });
        } finally { vehMantOcupado = false; }
    });

    // Aviso manual al cliente del próximo mantenimiento (correo o WhatsApp), con el mensaje armado.
    async function vehMantAviso(canal) {
        if (vehMantOcupado || !vehIdActual) return;
        const modalEl = vehModalEl();
        let g;
        try {
            const j = await (await fetch(`${urlBaseVeh}/mantAvisoPreviewAjax?id_vehiculo=${vehIdActual}`)).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo preparar el aviso.');
            g = j.data;
        } catch (e) { Swal.fire({ icon: 'warning', title: 'Atención', text: e.message, target: modalEl, heightAuto: false }); return; }
        const esCorreo = canal === 'correo';
        const { value: form } = await Swal.fire({
            title: esCorreo ? 'Aviso de mantenimiento por correo' : 'Aviso de mantenimiento por WhatsApp', width: 600, target: modalEl, heightAuto: false,
            html: `<div class="text-start small">
                <label class="fw-semibold mb-1">${esCorreo ? 'Para (separe varios con coma)' : 'Teléfono'}</label>
                <input id="vehMantAvDest" class="form-control form-control-sm mb-2" value="${vehEsc(esCorreo ? g.correo_destino : g.telefono_destino)}" placeholder="${esCorreo ? 'correo@ejemplo.com' : '0987654321'}">
                ${esCorreo ? `<label class="fw-semibold mb-1">Asunto</label><input id="vehMantAvAsunto" class="form-control form-control-sm mb-2" value="${vehEsc(g.asunto_defecto)}">` : ''}
                <label class="fw-semibold mb-1">Mensaje</label>
                <textarea id="vehMantAvMsg" class="form-control form-control-sm" rows="8">${vehEsc(g.mensaje_defecto)}</textarea>
                <div class="form-text">${esCorreo ? 'El sistema envía el correo y lo deja en el historial de avisos.' : 'Se abrirá WhatsApp con el mensaje listo para enviarlo; el sistema deja constancia.'}</div></div>`,
            showCancelButton: true, cancelButtonText: 'Cancelar',
            confirmButtonText: esCorreo ? '<i class="bi bi-send me-1"></i> Enviar' : '<i class="bi bi-whatsapp me-1"></i> Abrir WhatsApp',
            preConfirm: () => {
                const dest = document.getElementById('vehMantAvDest').value.trim();
                if (!dest) { Swal.showValidationMessage(esCorreo ? 'Escriba el correo de destino.' : 'Escriba el teléfono.'); return false; }
                let tel = '';
                if (!esCorreo) {
                    tel = dest.replace(/\D/g, '');
                    if (tel.length < 9) { Swal.showValidationMessage('Escriba un teléfono válido.'); return false; }
                    if (!tel.startsWith('593')) tel = '593' + (tel.startsWith('0') ? tel.slice(1) : tel);
                }
                return { dest: esCorreo ? dest : tel, asunto: document.getElementById('vehMantAvAsunto')?.value || '', mensaje: document.getElementById('vehMantAvMsg').value };
            }
        });
        if (!form || vehMantOcupado) return;
        vehMantOcupado = true;
        try {
            const fd = new FormData(); fd.append('id_vehiculo', vehIdActual); fd.append('mensaje', form.mensaje);
            if (esCorreo) {
                fd.append('destinatarios', form.dest); fd.append('asunto', form.asunto);
                Swal.fire({ title: 'Enviando...', target: modalEl, heightAuto: false, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const j = await (await fetch(`${urlBaseVeh}/mantAvisoCorreoAjax`, { method: 'POST', body: fd })).json();
                if (!j.ok) throw new Error(j.error || 'No se pudo enviar.');
                Swal.fire({ icon: 'success', title: 'Aviso enviado', text: j.msg, target: modalEl, heightAuto: false, timer: 1800, showConfirmButton: false });
            } else {
                window.open(`https://wa.me/${form.dest}?text=${encodeURIComponent(form.mensaje)}`, '_blank');
                fd.append('telefono', form.dest);
                const j = await (await fetch(`${urlBaseVeh}/mantAvisoWhatsappAjax`, { method: 'POST', body: fd })).json();
                if (!j.ok) throw new Error(j.error || 'No se pudo registrar el aviso.');
            }
            await vehCargarMantenimiento();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: modalEl, heightAuto: false });
            vehCargarMantenimiento(); // un correo fallido también queda en el historial
        } finally { vehMantOcupado = false; }
    }
    document.getElementById('veh_mant_aviso_correo')?.addEventListener('click', () => vehMantAviso('correo'));
    document.getElementById('veh_mant_aviso_wa')?.addEventListener('click', () => vehMantAviso('whatsapp'));

    window.vehMantEliminarCambio = async function (id) {
        if (vehMantOcupado) return;
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar este cambio?', text: 'El próximo cambio vuelve a calcularse con el cambio anterior.', target: vehModalEl(), heightAuto: false,
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!c.isConfirmed) return;
        vehMantOcupado = true;
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('id_vehiculo', vehIdActual);
            const j = await (await fetch(`${urlBaseVeh}/mantEliminarCambioAjax`, { method: 'POST', body: fd })).json();
            if (!j.ok) throw new Error(j.error || 'No se pudo eliminar.');
            await vehCargarMantenimiento();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message, target: vehModalEl(), heightAuto: false });
        } finally { vehMantOcupado = false; }
    };

    async function vehCargarTransacciones() {
        const tbody = document.getElementById('veh_trx_body');
        if (!vehIdActual || !tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const json = await (await fetch(`${urlBaseVeh}/transaccionesAjax?id=${vehIdActual}`)).json();
            if (!json.ok) throw new Error(json.error || 'No se pudo cargar.');
            vehCargado.trx = true;
            const { ordenes, resumen } = json.data;
            const todasLineas = ordenes.flatMap(o => o.lineas || []);
            vehLlenarFiltroTrx('veh_trx_categoria', 'Todas las categorías', todasLineas.map(l => l.categoria));
            vehLlenarFiltroTrx('veh_trx_marca', 'Todas las marcas', todasLineas.map(l => l.marca));
            const badge = document.getElementById('veh-badge-trx');
            if (badge) { badge.textContent = ordenes.length; badge.classList.toggle('d-none', !ordenes.length); }
            document.getElementById('veh_trx_resumen').innerHTML = `
                <span><i class="bi bi-list-check text-primary me-1"></i><b>${resumen.visitas}</b> ${resumen.visitas === 1 ? 'visita' : 'visitas'}</span>
                <span><i class="bi bi-cash-stack text-success me-1"></i>Total: <b>${vehFmt(resumen.total)}</b></span>
                <span><i class="bi bi-calendar-check text-info me-1"></i>Última visita: <b>${vehEsc(vehFecha(resumen.ultima_visita) || '—')}</b></span>
                <span><i class="bi bi-calendar-event text-warning me-1"></i>Próxima cita: <b>${vehEsc(vehFecha(resumen.proxima_cita) || '—')}</b></span>`;
            if (!ordenes.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Este vehículo aún no tiene órdenes de Car-Wash.</td></tr>';
                return;
            }
            const estados = { borrador: ['warning', 'Borrador'], facturado: ['success', 'Facturado'], anulado: ['danger', 'Anulado'] };
            tbody.innerHTML = ordenes.map(o => {
                const e = estados[o.estado] || ['secondary', o.estado || ''];
                const doc = o.numero_documento ? `${o.tipo_documento === 'FACTURA' ? 'Fact.' : 'Rec.'} ${vehEsc(o.numero_documento)}` : '<span class="text-muted">—</span>';
                const lineas = (o.lineas || []).map(l => `<tr data-txt="${vehEsc(vehNorm([l.producto_codigo, l.descripcion, l.categoria, l.marca].filter(Boolean).join(' ')))}"
                        data-cat="${vehEsc(vehNorm(String(l.categoria || '').trim()))}" data-mar="${vehEsc(vehNorm(String(l.marca || '').trim()))}">
                        <td class="ps-4 text-muted">${l.tipo_linea === 'producto' ? '<i class="bi bi-box-seam"></i>' : '<i class="bi bi-tools"></i>'}</td>
                        <td colspan="4">${vehEsc(l.producto_codigo ? l.producto_codigo + ' - ' : '')}${vehEsc(l.descripcion)}${(l.categoria || l.marca)
                            ? ` <span class="text-muted ms-1">· ${vehEsc([l.categoria, l.marca].filter(Boolean).join(' · '))}</span>` : ''}</td>
                        <td class="text-end">${(parseFloat(l.cantidad) || 0).toLocaleString('en-US', { maximumFractionDigits: 6 })} × ${vehFmt(l.precio_unitario)}${parseFloat(l.descuento) > 0 ? ' − ' + vehFmt(l.descuento) : ''}</td>
                        <td class="text-end">${vehFmt(l.total_linea)}</td><td colspan="2"></td></tr>`).join('');
                return `<tr class="veh-trx-orden" role="button" data-id="${o.id}" data-txt="${vehEsc(vehNorm((o.numero_orden || '') + ' ' + (o.numero_documento || '')))}">
                        <td class="text-center"><i class="bi bi-chevron-right small"></i></td>
                        <td>${vehEsc(o.fecha || '')}</td>
                        <td class="fw-semibold text-primary">${vehEsc(o.numero_orden || '')}</td>
                        <td class="text-truncate" style="max-width:260px">${vehEsc(o.cliente_nombre || '')}</td>
                        <td class="text-end">${vehFmt(o.total)}</td>
                        <td>${doc}</td>
                        <td class="text-center"><span class="badge bg-${e[0]} bg-opacity-10 text-${e[0]}">${vehEsc(e[1])}</span></td>
                    </tr>
                    <tr class="veh-trx-detalle d-none bg-light" data-det="${o.id}"><td colspan="7" class="p-0">
                        <table class="table table-sm mb-0 bg-light" style="font-size:.75rem;"><tbody>${lineas || '<tr><td class="ps-4 text-muted">Sin líneas.</td></tr>'}</tbody></table>
                        ${(() => {
                            // El kilometraje ya no es columna: va al pie del detalle desplegado.
                            const pie = [
                                o.kilometraje ? `Km: <b>${vehEsc(Number(o.kilometraje).toLocaleString('es-EC'))}</b>` : '',
                                typeof window.cwAbrirVerId === 'function' ? `<button type="button" class="btn btn-link btn-sm p-0" onclick="event.stopPropagation(); vehAbrirOrden(${o.id})"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir la orden</button>` : '',
                            ].filter(Boolean).join('<span class="text-muted mx-2">·</span>');
                            return pie ? `<div class="ps-4 py-1 small">${pie}</div>` : '';
                        })()}
                    </td></tr>`;
            }).join('');
            tbody.querySelectorAll('.veh-trx-orden').forEach(tr => tr.addEventListener('click', () => {
                const det = tbody.querySelector(`tr[data-det="${tr.dataset.id}"]`);
                const abierto = det.classList.toggle('d-none') === false;
                tr.querySelector('i.bi').className = 'bi small ' + (abierto ? 'bi-chevron-down' : 'bi-chevron-right');
            }));
            // Si ya había algo escrito en el buscador, se aplica a lo recién cargado.
            vehFiltrarTransacciones();
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${vehEsc(e.message)}</td></tr>`;
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
            const { citas, historial, disponible, manual } = json.data;
            aviso.innerHTML = !disponible
                ? '<div class="alert alert-warning py-1 small mb-2">Falta aplicar el SQL de recordatorios (20260929_carwash_recordatorios.sql): se pueden ver las citas, pero aún no enviar recordatorios.</div>'
                : (!manual ? '<div class="alert alert-warning py-1 small mb-2">Falta aplicar el SQL 20261030_vehiculos_recordatorios.sql para poder agregar recordatorios.</div>' : '');
            const btnAgregar = document.getElementById('veh_rec_agregar');
            if (btnAgregar) {
                btnAgregar.disabled = !(manual && window.VEH_PERM_ACTUALIZAR);
                btnAgregar.title = !manual ? 'Falta aplicar el SQL de recordatorios del vehículo' : (window.VEH_PERM_ACTUALIZAR ? '' : 'Requiere permiso de modificar');
            }
            // Clave de cada cita: 'o<id>' (cita de una orden) o 'r<id>' (recordatorio agregado).
            window.VEH_CITAS = {};
            const hoy = new Date().toISOString().slice(0, 10);
            tCitas.innerHTML = citas.length ? citas.map(c => {
                const clave = c.id_recordatorio ? `r${c.id_recordatorio}` : `o${c.id_orden}`;
                window.VEH_CITAS[clave] = c;
                const f = String(c.proxima_cita).slice(0, 10);
                const badge = c.vencida ? '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">pasada</span>'
                    : (f === hoy ? '<span class="badge bg-warning bg-opacity-10 text-warning ms-1">hoy</span>' : '<span class="badge bg-success bg-opacity-10 text-success ms-1">próxima</span>');
                const puede = disponible && window.VEH_PERM_ACTUALIZAR;
                const origen = c.id_recordatorio
                    ? `<i class="bi bi-bookmark-star text-primary me-1" title="Recordatorio agregado"></i><span class="d-inline-block text-truncate align-bottom" style="max-width:200px" title="${vehEsc(c.motivo + (c.observacion ? ' — ' + c.observacion : ''))}">${vehEsc(c.motivo || '')}</span>`
                    : vehEsc(c.numero_orden || '');
                const editar = c.id_recordatorio
                    ? `<span class="vr mx-1 align-middle"></span>
                       <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Editar recordatorio" ${window.VEH_PERM_ACTUALIZAR ? '' : 'disabled'} onclick="vehRecordatorioEditar(${c.id_recordatorio})"><i class="bi bi-pencil"></i></button>
                       <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar recordatorio" ${window.VEH_PERM_ELIMINAR ? '' : 'disabled'} onclick="vehRecordatorioEliminar(${c.id_recordatorio})"><i class="bi bi-trash3"></i></button>`
                    : '';
                return `<tr>
                    <td class="fw-semibold">${vehEsc(vehFecha(f))}${badge}</td>
                    <td>${origen}</td>
                    <td class="text-truncate" style="max-width:180px">${vehEsc(c.nombre_destino || '')}</td>
                    <td>${c.correo_destino ? vehEsc(c.correo_destino) : '<span class="text-muted">—</span>'}</td>
                    <td>${c.telefono_destino ? vehEsc(c.telefono_destino) : '<span class="text-muted">—</span>'}</td>
                    <td>${c.ultimo_envio ? vehEsc(vehFecha(c.ultimo_envio)) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-info btn-sm py-0 px-2" title="Enviar por correo" ${puede ? '' : 'disabled'} onclick="vehRecordatorioCorreo('${clave}')"><i class="bi bi-envelope"></i></button>
                        <button type="button" class="btn btn-outline-success btn-sm py-0 px-2" title="Enviar por WhatsApp" ${puede ? '' : 'disabled'} onclick="vehRecordatorioWhatsapp('${clave}')"><i class="bi bi-whatsapp"></i></button>
                        ${editar}
                    </td></tr>`;
            }).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Este vehículo no tiene citas ni recordatorios. Use <b>Agregar recordatorio</b> o fije la próxima cita en la orden de Car-Wash.</td></tr>';

            const canal = { correo: '<i class="bi bi-envelope text-info me-1"></i>Correo', whatsapp: '<i class="bi bi-whatsapp text-success me-1"></i>WhatsApp' };
            tHist.innerHTML = historial.length ? historial.map(h => `<tr>
                    <td>${vehEsc(vehFecha(h.created_at))}</td>
                    <td>${vehEsc(vehFecha(h.fecha_cita))}</td>
                    <td class="text-truncate" style="max-width:200px">${vehEsc(h.numero_orden || h.motivo || '')}</td>
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

    // Agregar (id = 0) o editar un recordatorio propio del vehículo: fecha + motivo.
    let vehRecGuardando = false;
    window.vehRecordatorioEditar = async function (id) {
        if (vehRecGuardando || !vehIdActual) return;
        const c = id ? (window.VEH_CITAS || {})[`r${id}`] : null;
        if (id && !c) return;
        const modalEl = document.getElementById('modalVehiculo');
        const hoy = new Date();
        const hoyIso = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
        const { value: form } = await Swal.fire({
            title: id ? 'Editar recordatorio' : 'Agregar recordatorio', width: 520, target: modalEl, heightAuto: false,
            html: `<div class="text-start small">
                <label class="fw-semibold mb-1 d-block">Fecha</label>
                <input type="date" id="vehRecFecha" class="form-control form-control-sm mb-2" min="${c ? '' : hoyIso}" value="${vehEsc(c ? String(c.proxima_cita).slice(0, 10) : '')}">
                <label class="fw-semibold mb-1 d-block">Motivo</label>
                <input id="vehRecMotivo" class="form-control form-control-sm mb-2" maxlength="200" value="${vehEsc(c ? c.motivo : '')}" placeholder="Ej.: Cambio de aceite, revisión de frenos, lavado de tapicería...">
                <label class="fw-semibold mb-1 d-block">Observación <span class="text-muted fw-normal">(opcional, uso interno)</span></label>
                <textarea id="vehRecObs" class="form-control form-control-sm" rows="3">${vehEsc(c ? (c.observacion || '') : '')}</textarea>
                <div class="form-text">Se avisa al cliente por correo o WhatsApp, a mano o con la automatización de recordatorios.</div></div>`,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Guardar', cancelButtonText: 'Cancelar',
            didOpen: () => document.getElementById(id ? 'vehRecMotivo' : 'vehRecFecha')?.focus(),
            preConfirm: () => {
                const fecha = document.getElementById('vehRecFecha').value;
                const motivo = document.getElementById('vehRecMotivo').value.trim();
                if (!fecha) { Swal.showValidationMessage('Elija la fecha.'); return false; }
                if (!motivo) { Swal.showValidationMessage('Escriba el motivo.'); return false; }
                return { fecha, motivo, observacion: document.getElementById('vehRecObs').value.trim() };
            }
        });
        if (!form || vehRecGuardando) return;
        vehRecGuardando = true;
        try {
            const fd = new FormData();
            fd.append('id_vehiculo', vehIdActual); fd.append('id_recordatorio', id || 0);
            fd.append('fecha', form.fecha); fd.append('motivo', form.motivo); fd.append('observacion', form.observacion);
            const json = await (await fetch(`${urlBaseVeh}/guardarRecordatorioAjax`, { method: 'POST', body: fd })).json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se guardó', text: json.error || 'Error al guardar.', target: modalEl, heightAuto: false });
                return;
            }
            Swal.fire({ icon: 'success', title: json.msg || 'Guardado', target: modalEl, heightAuto: false, timer: 1500, showConfirmButton: false });
            // Recargar antes de liberar la bandera: así un segundo clic ya ve el recordatorio creado.
            await vehCargarRecordatorios();
            vehCargado.trx = false; // el resumen de Transacciones muestra la próxima cita
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', target: modalEl, heightAuto: false });
        } finally {
            vehRecGuardando = false;
        }
    };

    window.vehRecordatorioEliminar = async function (id) {
        const c = (window.VEH_CITAS || {})[`r${id}`]; if (!c) return;
        const modalEl = document.getElementById('modalVehiculo');
        const conf = await Swal.fire({
            icon: 'warning', title: '¿Eliminar recordatorio?', target: modalEl, heightAuto: false,
            html: `<b>${vehEsc(c.motivo || '')}</b> del ${vehEsc(vehFecha(c.proxima_cita))}.<br><span class="small text-muted">Los avisos ya enviados quedan en el historial.</span>`,
            showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        });
        if (!conf.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id_recordatorio', id);
            const json = await (await fetch(`${urlBaseVeh}/eliminarRecordatorioAjax`, { method: 'POST', body: fd })).json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se eliminó', text: json.error || 'Error al eliminar.', target: modalEl, heightAuto: false });
                return;
            }
            Swal.fire({ icon: 'success', title: json.msg || 'Eliminado', target: modalEl, heightAuto: false, timer: 1500, showConfirmButton: false });
            vehCargado.trx = false;
            vehCargarRecordatorios();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', target: modalEl, heightAuto: false });
        }
    };

    // Datos que identifican la cita al enviar: la orden o el recordatorio agregado.
    const vehRecIds = (fd, c) => {
        fd.append('id_orden', c.id_orden || 0);
        fd.append('id_recordatorio', c.id_recordatorio || 0);
    };

    window.vehRecordatorioCorreo = async function (clave) {
        const c = (window.VEH_CITAS || {})[clave]; if (!c) return;
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
            vehRecIds(fd, c); fd.append('destinatarios', form.para); fd.append('asunto', form.asunto); fd.append('mensaje', form.mensaje);
            const json = await (await fetch(`${urlBaseVeh}/enviarRecordatorioCorreoAjax`, { method: 'POST', body: fd })).json();
            if (json.ok) Swal.fire({ icon: 'success', title: 'Enviado', text: json.msg, target: modalEl, heightAuto: false, timer: 2200, showConfirmButton: false });
            else Swal.fire({ icon: 'error', title: 'No se envió', text: json.error || 'Error al enviar.', target: modalEl, heightAuto: false });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.', target: modalEl, heightAuto: false });
        }
        vehCargarRecordatorios();
    };

    // WhatsApp: abre WhatsApp (web o app) con el mensaje listo; el sistema deja constancia del envío.
    window.vehRecordatorioWhatsapp = async function (clave) {
        const c = (window.VEH_CITAS || {})[clave]; if (!c) return;
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
            vehRecIds(fd, c); fd.append('telefono', form.tel); fd.append('mensaje', form.mensaje);
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

            if (!placa) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La placa es obligatoria.' });
            // Vehículo nuevo: placa completa AAA-1111. Al editar no se exige, para no
            // bloquear placas ya guardadas en otro formato (migradas, motos, etc.).
            if (!document.getElementById('vehiculo_id').value && !/^[A-Z]{3}-[0-9]{4}$/.test(placa)) {
                return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La placa debe tener el formato AAA-1111 (3 letras y 4 números).' });
            }
            if (!marca) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'La marca y modelo es obligatoria.' });

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
                        window.dispatchEvent(new CustomEvent('vehiculoGuardado', { detail: { ...json, nuevo: !id } }));
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

    // ─── Consulta de marca/año por placa en el SRI ───────────────────────────
    // Botón "SRI": consulta y reemplaza marca y año. Al salir del campo placa en un
    // vehículo nuevo con marca vacía, consulta sola y solo llena los campos vacíos.
    // Si el SRI falla, el usuario sigue escribiendo a mano.
    let vehSriUltimaPlaca = '';

    function vehSriInfo(html, clase) {
        const el = document.getElementById('vehiculo_sri_info');
        if (!el) return;
        el.className = 'form-text small ' + (html ? (clase || 'text-muted') : 'd-none');
        el.innerHTML = html;
    }

    async function vehConsultarSri(automatico) {
        const inpPlaca = document.getElementById('vehiculo_placa');
        const btn = document.getElementById('btnVehConsultarSri');
        const placa = (inpPlaca?.value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (placa.length < 5) {
            if (!automatico) Swal.fire({ icon: 'warning', title: 'Atención', text: 'Escriba la placa a consultar.' });
            return;
        }
        if (automatico && placa === vehSriUltimaPlaca) return;
        vehSriUltimaPlaca = placa;

        const btnHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
        vehSriInfo('<i class="bi bi-hourglass-split me-1"></i>Consultando el SRI…');
        try {
            const json = await (await fetch(`${urlBaseVeh}/consultarPlacaSriAjax?placa=${encodeURIComponent(placa)}`)).json();
            if (!json.ok) {
                vehSriInfo('<i class="bi bi-exclamation-circle me-1"></i>' + vehEsc(json.error || 'No se encontró el vehículo en el SRI.'), 'text-warning');
                return;
            }
            const d = json.data || {};
            const inpMarca = document.getElementById('vehiculo_marca');
            const inpAnio = document.getElementById('vehiculo_anio');
            // "Marca y modelo" en un solo campo: GREAT WALL M4 MT AC 1.5 5P 4X2 TM
            const marcaModelo = [d.marca, d.modelo].filter(Boolean).join(' ').slice(0, 100);
            if (marcaModelo && (!automatico || !inpMarca.value.trim())) inpMarca.value = marcaModelo;
            if (d.anio && (!automatico || !inpAnio.value)) inpAnio.value = d.anio;

            const partes = [d.marca, d.modelo, d.anio, d.pais].filter(Boolean).map(vehEsc);
            vehSriInfo('<i class="bi bi-check-circle me-1"></i>SRI: ' + partes.join(' · '), 'text-success');
        } catch (e) {
            vehSriInfo('<i class="bi bi-exclamation-circle me-1"></i>No se pudo consultar el SRI. Escriba los datos a mano.', 'text-warning');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnHtml; }
        }
    }

    /**
     * Máscara de placa AAA-1111: 3 letras (mayúsculas), guion y hasta 4 números;
     * cualquier otro carácter se descarta al teclear. El guion se agrega solo al
     * escribir el primer número, para que borrar hacia atrás no se trabe en él.
     */
    function vehMaskPlaca(el) {
        const cursorAlFinal = el.selectionStart === el.value.length;
        let letras = '', numeros = '';
        for (const ch of el.value.toUpperCase()) {
            if (letras.length < 3 && /[A-Z]/.test(ch)) letras += ch;
            else if (letras.length === 3 && numeros.length < 4 && /[0-9]/.test(ch)) numeros += ch;
        }
        el.value = numeros ? `${letras}-${numeros}` : letras;
        if (cursorAlFinal) el.setSelectionRange(el.value.length, el.value.length);
    }

    const inpPlacaVeh = document.getElementById('vehiculo_placa');
    // Solo en vehículo nuevo: al editar, una placa guardada en otro formato (moto,
    // migrada) se destrozaría con la primera tecla.
    inpPlacaVeh?.addEventListener('input', () => {
        if (!document.getElementById('vehiculo_id')?.value) vehMaskPlaca(inpPlacaVeh);
    });
    inpPlacaVeh?.addEventListener('change', () => {
        // Placas antiguas de 3 dígitos: se completan con un 0 (ABC-123 → ABC-0123), como en el SRI.
        const m = inpPlacaVeh.value.match(/^([A-Z]{3})-([0-9]{3})$/);
        if (m) inpPlacaVeh.value = `${m[1]}-0${m[2]}`;

        const esNuevo = !document.getElementById('vehiculo_id')?.value;
        const marcaVacia = !document.getElementById('vehiculo_marca')?.value.trim();
        if (esNuevo && marcaVacia && /^[A-Z]{3}-[0-9]{4}$/.test(inpPlacaVeh.value)) vehConsultarSri(true);
    });
    document.getElementById('btnVehConsultarSri')?.addEventListener('click', () => vehConsultarSri(false));

})(window, document);
