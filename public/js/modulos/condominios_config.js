/**
 * condominios_config.js — Configuración de condominios (modulos/condominios-config).
 *
 * Formulario por pestañas (Condominio / Emisión / Alícuota y fondo / Mora y multas / Descuentos)
 * que se guarda completo con AJAX, más el catálogo de multas (se guarda por fila). Los productos
 * de cada concepto se eligen con buscadores tipo chip sobre los servicios de Productos.
 * Configuración en window.CONDCFG_CFG.
 */
window.CONDCFG = (function () {
    'use strict';

    const CFG = window.CONDCFG_CFG || {};
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = n => { const v = parseFloat(n) || 0; return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    const esTrue = v => v === true || v === 't' || v === 'true' || v === 1 || v === '1';

    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opciones || {}));
        return r.json();
    }
    function post(accion, datos) {
        const fd = datos instanceof FormData ? datos : new FormData();
        if (!(datos instanceof FormData)) Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
        return pedir(`${CFG.url}/${accion}`, { method: 'POST', body: fd });
    }
    function aviso(icon, title, text) { return Swal.fire({ icon, title, text }); }
    /** Mensajes del servidor «texto|#id»: enfoca el control y muestra su pestaña. */
    function errorForm(mensaje) {
        const [txt, s] = String(mensaje || '').split('|');
        aviso('error', 'Revise', txt);
        if (!s) return;
        const el = document.querySelector(s); if (!el) return;
        const pane = el.closest('.tab-pane');
        if (pane) { const btn = document.querySelector(`a[href="#${pane.id}"]`); if (btn) bootstrap.Tab.getOrCreateInstance(btn).show(); }
        el.focus(); el.classList.add('is-invalid'); setTimeout(() => el.classList.remove('is-invalid'), 2500);
    }

    // ── Buscador tipo chip (Backspace/Delete limpian toda la selección) ──
    function chip(inputId, hiddenId, ddId, fetchFn, label) {
        const input = $(inputId), hidden = $(hiddenId), dd = $(ddId);
        if (!input || !hidden || !dd) return;
        let t;
        input.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hidden.value !== '') { e.preventDefault(); hidden.value = ''; input.value = ''; dd.style.display = 'none'; dd.innerHTML = ''; }
        });
        input.addEventListener('input', () => {
            hidden.value = ''; clearTimeout(t);
            const q = input.value.trim();
            if (q.length < 1) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
            t = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { items = []; }
                if (!items.length) { dd.innerHTML = '<span class="list-group-item text-muted small py-1 px-2">Sin resultados. Cree el servicio en Productos.</span>'; dd.style.display = 'block'; return; }
                dd.innerHTML = items.map(it => `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${esc(label(it))}">${esc(label(it))}</a>`).join('');
                dd.style.display = 'block';
            }, 300);
        });
        dd.addEventListener('click', e => { const a = e.target.closest('a[data-id]'); if (!a) return; e.preventDefault(); hidden.value = a.dataset.id; input.value = a.dataset.label; dd.style.display = 'none'; });
        document.addEventListener('click', e => { if (e.target !== input && !dd.contains(e.target)) dd.style.display = 'none'; });
    }
    const buscarServicios = async q => (await pedir(`${CFG.url}/buscarServiciosAjax?q=${encodeURIComponent(q)}`)).rows || [];
    const lblServicio = p => `${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}`;
    function setChip(inputId, hiddenId, id, label) { $(hiddenId).value = id || ''; $(inputId).value = id ? (label || '') : ''; }

    // ── Configuración ──
    let guardando = false;
    function cargar(c) {
        const f = $('formCondConfig');
        f.querySelectorAll('input[name], select[name], textarea[name]').forEach(el => {
            if (el.type === 'hidden' && el.id.startsWith('cfg_prod_')) return;
            if (el.type === 'checkbox') { el.checked = esTrue(c[el.name] ?? false); return; }
            // Nombre y dirección vienen precargados desde el servidor (empresa / establecimiento)
            // cuando la configuración guardada los tiene vacíos: no pisarlos con ''.
            if (c[el.name] !== undefined && c[el.name] !== null && !(c[el.name] === '' && ['nombre_condominio', 'direccion', 'administrador_nombre', 'administrador_cedula'].includes(el.name))) el.value = c[el.name];
        });
        setChip('cfg_prod_ordinaria_txt', 'cfg_prod_ordinaria', c.id_producto_ordinaria, c.producto_ordinaria_nombre);
        setChip('cfg_prod_fondo_txt', 'cfg_prod_fondo', c.id_producto_fondo, c.producto_fondo_nombre);
        setChip('cfg_prod_interes_txt', 'cfg_prod_interes', c.id_producto_interes, c.producto_interes_nombre);
        onFondo(); onIntereses();
    }
    function onFondo() {
        const t = $('cfg_fondo_reserva_tipo').value;
        $('cfg_fondo_reserva_valor').disabled = t === 'no';
        $('cfg_fondo_reserva_valor_lbl').textContent = t === 'porcentaje' ? '% sobre la alícuota' : (t === 'fijo' ? 'Monto fijo' : 'Valor');
        $('cfg-wrap-prod-fondo').classList.toggle('opacity-50', t === 'no');
    }
    function onIntereses() {
        const on = $('cfg_cobra_intereses').checked;
        ['cfg_interes_tipo', 'cfg_interes_destino'].forEach(id => { $(id).disabled = !on; });
        $('cfg_interes_tasa_mensual').disabled = !on || $('cfg_interes_tipo').value !== 'fijo';
        $('cfg-wrap-prod-interes').classList.toggle('opacity-50', !on);
    }
    async function guardar(e) {
        e?.preventDefault();
        if (guardando || !CFG.perm.actualizar) return;
        guardando = true; const btn = $('cfg-btn-guardar'); if (btn) btn.disabled = true;
        try {
            const fd = new FormData($('formCondConfig'));
            // Los disabled no viajan: se completan desde los controles.
            ['fondo_reserva_valor', 'interes_tipo', 'interes_tasa_mensual', 'interes_destino'].forEach(k => fd.set(k, $('cfg_' + k).value));
            const res = await post('guardarAjax', fd);
            if (!res.ok) return errorForm(res.mensaje);
            const eraNueva = !CFG.config;
            CFG.config = res.config;
            await Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje + (res.pendientes && res.pendientes.length ? ' Pendiente: ' + res.pendientes.join(' ') : ''), timer: 2600, showConfirmButton: false });
            if (eraNueva || (res.pendientes || []).length !== (CFG.pendientesIni || 0)) location.reload(); // los avisos y el catálogo de multas dependen de la configuración
        } catch (err) { aviso('error', 'No se pudo guardar', 'Error de comunicación con el servidor.'); }
        finally { guardando = false; if (btn) btn.disabled = false; }
    }

    // ── Multas ──
    function pintarMultas(multas) {
        const b = $('multas-body');
        b.innerHTML = (multas || []).length ? multas.map(m => `<tr>
            <td class="ps-2 fw-medium">${esc(m.nombre)}</td><td class="text-muted">${esc(m.descripcion || '')}</td><td class="text-end">${money(m.valor)}</td><td>${esc(m.producto_nombre || '—')}</td>
            <td><span class="badge bg-${m.estado === 'activo' ? 'success' : 'secondary'} bg-opacity-10 text-${m.estado === 'activo' ? 'success' : 'secondary'} border border-opacity-25">${m.estado === 'activo' ? 'Activa' : 'Inactiva'}</span></td>
            <td class="text-end pe-2 text-nowrap">${CFG.perm.actualizar ? `<button type="button" class="btn btn-link btn-sm p-0 me-2" title="Editar" onclick='CONDCFG.multaEditar(${JSON.stringify(m).replace(/'/g, '&#39;')})'><i class="bi bi-pencil"></i></button>` : ''}
            ${CFG.perm.eliminar ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" title="Eliminar" onclick="CONDCFG.multaEliminar(${m.id})"><i class="bi bi-trash3"></i></button>` : ''}</td></tr>`).join('')
            : '<tr><td colspan="6" class="text-center text-muted py-3">Sin multas registradas.</td></tr>';
    }
    function multaLimpiar() {
        if (!$('multa_id')) return;
        $('multa_id').value = ''; $('multa_nombre').value = ''; $('multa_valor').value = '0'; $('multa_descripcion').value = ''; $('multa_estado').value = 'activo';
        setChip('multa_prod_txt', 'multa_id_producto', '', '');
    }
    function multaEditar(m) {
        $('multa_id').value = m.id; $('multa_nombre').value = m.nombre; $('multa_valor').value = m.valor; $('multa_descripcion').value = m.descripcion || ''; $('multa_estado').value = m.estado;
        setChip('multa_prod_txt', 'multa_id_producto', m.id_producto, m.producto_nombre);
        $('multa_nombre').focus();
    }
    async function multaGuardar() {
        const res = await post('multasAjax', { accion: 'guardar', id: $('multa_id').value, nombre: $('multa_nombre').value, valor: $('multa_valor').value,
            descripcion: $('multa_descripcion').value, id_producto: $('multa_id_producto').value, estado: $('multa_estado').value });
        if (!res.ok) return errorForm(res.mensaje);
        multaLimpiar(); pintarMultas(res.multas);
    }
    async function multaEliminar(id) {
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar la multa del catálogo?', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const res = await post('multasAjax', { accion: 'eliminar', id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
        pintarMultas(res.multas);
    }

    // ── Valores que rigen ──
    const V = { presupuestos: [], previewOk: false };
    const fechaMes = iso => { const m = /^(\d{4})-(\d{2})/.exec(iso || ''); if (!m) return iso || ''; const M = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']; return `${M[+m[2] - 1]}-${m[1]}`; };
    const num = (n, d) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    const modalV = () => bootstrap.Modal.getOrCreateInstance($('modalCondValor'));

    async function cargarValores() {
        if (!$('valores-body')) return;
        const res = await pedir(`${CFG.url}/valoresAjax`);
        if (!res.ok) { $('valores-body').innerHTML = `<tr><td colspan="8" class="text-danger text-center py-3">${esc(res.mensaje)}</td></tr>`; return; }
        V.presupuestos = res.presupuestos || [];
        pintarValores(res.valores || []);
    }
    function pintarValores(valores) {
        $('valores-body').innerHTML = valores.length ? valores.map(v => `<tr class="${v.vigente ? 'table-success' : ''}">
            <td class="ps-2 fw-medium">${fechaMes(v.vigente_desde)}${v.vigente ? ' <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">vigente</span>' : ''}</td>
            <td class="text-end">${+v.tarifa_m2 ? num(v.tarifa_m2, 4) : '<span class="text-muted">—</span>'}</td>
            <td class="text-end">${+v.monto_a_repartir ? money(v.monto_a_repartir) : '<span class="text-muted">—</span>'}</td>
            <td>${v.id_presupuesto ? `<i class="bi bi-calculator me-1"></i>${esc(v.presupuesto_nombre || 'Presupuesto')} · ${esc(v.version_nombre || '')}` : '<span class="text-muted">Manual</span>'}</td>
            <td>${esc(v.acta || '')}</td><td class="text-muted">${esc(v.observacion || '')}</td><td class="text-muted">${esc(v.usuario_nombre || '')}</td>
            <td class="text-end pe-2">${CFG.perm.eliminar ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" title="Eliminar" onclick="CONDCFG.valorEliminar(${v.id})"><i class="bi bi-trash3"></i></button>` : ''}</td></tr>`).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">Aún no hay valores. Sin un valor vigente, los inmuebles por % y por m² no tienen cuota (los manuales sí).</td></tr>';
    }
    function nuevoValor() {
        ['val_tarifa_m2', 'val_monto_a_repartir', 'val_acta', 'val_observacion'].forEach(id => { $(id).value = ''; });
        const d = new Date(); d.setMonth(d.getMonth() + 1);
        $('val_vigente_desde').value = d.toISOString().slice(0, 7);
        $('val_id_presupuesto').innerHTML = '<option value="">— Monto manual —</option>' + V.presupuestos.map(p => `<option value="${p.id}">${esc(p.nombre)} · ${esc(p.version_nombre)}${(p.base_alicuotas === true || p.base_alicuotas === 't') ? ' ★' : ''}</option>`).join('');
        $('val-preview').classList.add('d-none'); $('val-btn-guardar').classList.add('d-none'); V.previewOk = false;
        $('val_monto_a_repartir').disabled = false;
        modalV().show();
    }
    function valorPresupuesto() {
        const id = $('val_id_presupuesto').value;
        const p = V.presupuestos.find(x => String(x.id) === id);
        $('val_monto_a_repartir').disabled = !!p;
        if (p) {
            const ym = $('val_vigente_desde').value;
            $('val_monto_a_repartir').value = p.gastos_mes && p.gastos_mes[ym] !== undefined ? (+p.gastos_mes[ym]).toFixed(2) : '';
        }
        $('val-preview').classList.add('d-none'); $('val-btn-guardar').classList.add('d-none'); V.previewOk = false;
    }
    function datosValor() {
        return { vigente_desde: $('val_vigente_desde').value, tarifa_m2: $('val_tarifa_m2').value, monto_a_repartir: $('val_monto_a_repartir').value,
                 id_presupuesto: $('val_id_presupuesto').value, acta: $('val_acta').value, observacion: $('val_observacion').value };
    }
    async function valorPreview() {
        const res = await post('valorPreviewAjax', datosValor());
        if (!res.ok) return errorForm(res.mensaje);
        const t = res.totales, ant = res.anterior;
        const kpi = (lbl, val, sub) => `<div class="col-6 col-md-3"><div class="border rounded-3 p-2 bg-light"><div class="text-muted" style="font-size:.7rem">${lbl}</div><div class="fw-bold">${val}</div>${sub ? `<div class="text-muted" style="font-size:.7rem">${sub}</div>` : ''}</div></div>`;
        $('val-kpis').innerHTML = kpi('Inmuebles', `${t.inmuebles}${t.sin_cuota ? ` <span class="text-danger">(${t.sin_cuota} sin cuota)</span>` : ''}`)
            + kpi('Σ cuotas ordinarias', money(t.cuotas), ant ? `antes ${money(ant.cuotas)}` : '')
            + kpi('Σ fondo de reserva', money(t.fondo), ant ? `antes ${money(ant.fondo)}` : '')
            + kpi(t.monto_a_repartir ? 'Frente al monto a repartir' : 'Σ total a cobrar', t.monto_a_repartir ? (t.diferencia === 0 ? '<span class="text-success">Cuadra</span>' : `<span class="${t.diferencia > 0 ? 'text-success' : 'text-danger'}">${t.diferencia > 0 ? '+' : ''}${money(t.diferencia)}</span>`) : money(t.total),
                  t.repartir_resto ? `Resto repartido: ${money(t.monto_efectivo)} (manuales ${money(t.manuales)})` : (t.monto_a_repartir ? `Σ cuotas ${money(t.cuotas)} vs ${money(t.monto_a_repartir)}` : ''));
        $('val-body').innerHTML = res.filas.map(f => `<tr class="${f.cuota === null ? 'table-warning' : ''}">
            <td class="ps-2"><code>${esc(f.codigo)}</code> ${esc(f.nombre)}</td><td class="text-truncate" style="max-width:200px">${esc(f.propietario)}</td>
            <td>${esc(({ porcentaje: 'Por %', m2: 'Por m²', manual: 'Manual' })[f.metodo] || f.metodo)}</td>
            <td class="text-end">${num(f.area_m2, 2)}</td><td class="text-end">${num(f.alicuota_pct, 4)}</td>
            <td class="text-end">${f.cuota === null ? '<span class="text-danger" title="Sin valor para su método">—</span>' : money(f.cuota)}</td>
            <td class="text-end">${f.fondo ? money(f.fondo) : '<span class="text-muted">—</span>'}</td><td class="text-end pe-2 fw-medium">${f.total === null ? '—' : money(f.total)}</td></tr>`).join('');
        $('val-nota').textContent = t.sin_cuota ? 'Las filas en amarillo no tienen cuota con este valor: su método necesita la tarifa por m² o el monto a repartir.' : '';
        $('val-preview').classList.remove('d-none'); $('val-btn-guardar').classList.remove('d-none'); V.previewOk = true;
    }
    async function valorGuardar() {
        if (!V.previewOk) return aviso('info', 'Vea la vista previa', 'Revise primero la cuota de cada inmueble.');
        const c = await Swal.fire({ icon: 'question', title: '¿Guardar este valor?', text: `Regirá desde ${fechaMes($('val_vigente_desde').value)}. Los recibos de ese mes en adelante saldrán con la cuota nueva.`, showCancelButton: true, confirmButtonText: 'Sí, guardar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const res = await post('guardarValorAjax', datosValor());
        if (!res.ok) return errorForm(res.mensaje);
        modalV().hide(); pintarValores(res.valores || []);
        aviso('success', 'Listo', res.mensaje);
    }
    async function valorEliminar(id) {
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar este valor?', text: 'Volverá a regir el valor anterior para ese período.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const res = await post('eliminarValorAjax', { id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
        pintarValores(res.valores || []);
    }

    // ── Reajuste masivo de cuotas ──
    const R = { filas: [], excluir: new Set(), previewOk: false };
    const fechaD = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };
    const FORMA_LBL = { fijo: 'Monto fijo', porcentaje: 'Aumento %', inmueble: 'Según inmueble' };
    const ESTADO_REAJ = { pendiente: ['warning', 'Programado'], aplicado: ['success', 'Aplicado'], cancelado: ['secondary', 'Cancelado'], error: ['danger', 'Error'] };

    async function cargarReajuste() {
        if (!$('reaj_id_producto')) return;
        const res = await pedir(`${CFG.url}/reajusteOpcionesAjax`);
        if (!res.ok) return;
        $('reaj_id_producto').innerHTML = '<option value="">— Elija el concepto —</option>' + (res.productos || []).map(p => `<option value="${p.id}">${esc(p.nombre)} (${p.suscripciones} suscr.)</option>`).join('');
        if (res.instalado === false) $('reaj-hist-body').innerHTML = '<tr><td colspan="10" class="text-center text-warning py-3">Falta aplicar database/migrations/20261006_condominios_reajustes.sql.</td></tr>';
        else pintarReajustes(res.reajustes || []);
        reajusteForma();
    }
    function reajusteForma() {
        const f = $('reaj_forma').value;
        $('reaj_parametro_lbl').textContent = f === 'fijo' ? 'Monto *' : (f === 'porcentaje' ? '% aumento *' : 'Parámetro');
        $('reaj_parametro').disabled = f === 'inmueble';
        $('reaj_parametro').placeholder = f === 'fijo' ? '0.00' : (f === 'porcentaje' ? 'p. ej. 8 (o -5)' : 'Usa el valor que rige');
        ocultarReajPreview();
    }
    function ocultarReajPreview() { $('reaj-preview').classList.add('d-none'); R.previewOk = false; }
    function datosReajuste() {
        return { id_producto: $('reaj_id_producto').value, forma: $('reaj_forma').value, parametro: $('reaj_parametro').value, fecha_aplicar: $('reaj_fecha_aplicar').value,
                 descripcion: $('reaj_descripcion').value, incluir_sin_inmueble: $('reaj_incluir_sin_inmueble').checked ? '1' : '0', excluir: Array.from(R.excluir).join(',') };
    }
    async function reajustePreview() {
        const res = await post('reajustePreviewAjax', datosReajuste());
        if (!res.ok) return errorForm(res.mensaje);
        R.filas = res.filas || [];
        pintarReajPreview(res.totales);
    }
    function pintarReajPreview(t) {
        const kpi = (lbl, val, sub) => `<div class="col-6 col-md-3"><div class="border rounded-3 p-2 bg-light"><div class="text-muted" style="font-size:.7rem">${lbl}</div><div class="fw-bold">${val}</div>${sub ? `<div class="text-muted" style="font-size:.7rem">${sub}</div>` : ''}</div></div>`;
        const aplicables = R.filas.filter(f => f.nuevo !== null && !R.excluir.has(f.id_suscripcion));
        const sumA = aplicables.reduce((s, f) => s + (parseFloat(f.actual) || 0), 0), sumN = aplicables.reduce((s, f) => s + (parseFloat(f.nuevo) || 0), 0);
        $('reaj-kpis').innerHTML = kpi('Suscripciones', `${aplicables.length} de ${R.filas.length}`, `${t.omitidas} sin valor posible · ${R.excluir.size} destildadas`)
            + kpi('Σ actual', money(sumA)) + kpi('Σ nuevo', money(sumN), `${sumN - sumA >= 0 ? '+' : ''}${money(sumN - sumA)} por período`)
            + kpi('Cuándo', t.programado ? `<span class="text-warning">Programado</span>` : 'En el acto', fechaD($('reaj_fecha_aplicar').value));
        $('reaj-body').innerHTML = R.filas.map(f => `<tr class="${f.nuevo === null ? 'table-warning' : (R.excluir.has(f.id_suscripcion) ? 'text-muted' : '')}">
            <td class="ps-2">${f.nuevo === null ? '' : `<input type="checkbox" class="form-check-input" ${R.excluir.has(f.id_suscripcion) ? '' : 'checked'} onchange="CONDCFG.reajusteToggle(${f.id_suscripcion}, this.checked)">`}</td>
            <td>${esc(f.cliente)} <small class="text-muted">${esc(f.identificacion || '')}</small> <small class="text-muted">#${f.id_suscripcion}</small></td>
            <td>${esc(f.inmueble || '—')}</td>
            <td class="text-end">${f.actual === null ? '<span class="text-muted" title="Sin línea del concepto">—</span>' : money(f.actual)}</td>
            <td class="text-end fw-medium">${f.nuevo === null ? '—' : money(f.nuevo)}${f.actual === null && f.nuevo !== null ? ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">nueva línea</span>' : ''}</td>
            <td class="pe-2 small text-muted">${esc(f.motivo || (f.cambia ? '' : (f.nuevo === null ? '' : 'Sin cambio')))}</td></tr>`).join('')
            || '<tr><td colspan="6" class="text-center text-muted py-3">No hay suscripciones para este concepto.</td></tr>';
        $('reaj-todas').checked = R.excluir.size === 0;
        $('reaj-preview').classList.remove('d-none'); R.previewOk = true;
    }
    function reajusteToggle(id, on) { if (on) R.excluir.delete(id); else R.excluir.add(id); pintarReajPreview({ omitidas: R.filas.filter(f => f.nuevo === null).length, programado: $('reaj_fecha_aplicar').value > new Date().toISOString().slice(0, 10) }); }
    function reajusteTodas(on) { R.excluir = new Set(on ? [] : R.filas.filter(f => f.nuevo !== null).map(f => f.id_suscripcion)); reajusteToggle(-1, true); }
    async function reajusteAplicar() {
        if (!R.previewOk) return aviso('info', 'Vea la vista previa', 'Revise primero qué suscripciones cambian.');
        const n = R.filas.filter(f => f.nuevo !== null && !R.excluir.has(f.id_suscripcion)).length;
        const prog = $('reaj_fecha_aplicar').value > new Date().toISOString().slice(0, 10);
        const c = await Swal.fire({ icon: 'warning', title: prog ? `¿Programar el reajuste de ${n} suscripción(es)?` : `¿Aplicar el reajuste a ${n} suscripción(es)?`,
            text: prog ? `Se aplicará automáticamente el ${fechaD($('reaj_fecha_aplicar').value)}. Hasta entonces se sigue cobrando el valor actual.` : 'Los valores nuevos rigen desde el próximo documento que se genere.',
            showCancelButton: true, confirmButtonText: prog ? 'Sí, programar' : 'Sí, aplicar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        const btn = $('reaj-btn-aplicar'); btn.disabled = true;
        try {
            const res = await post('reajusteAplicarAjax', datosReajuste());
            if (!res.ok) return errorForm(res.mensaje);
            ocultarReajPreview(); R.excluir = new Set(); $('reaj_descripcion').value = '';
            pintarReajustes(res.reajustes || []);
            aviso('success', 'Listo', res.mensaje);
        } finally { btn.disabled = false; }
    }
    function pintarReajustes(rs) {
        $('reaj-hist-body').innerHTML = rs.length ? rs.map(r => { const [cls, lbl] = ESTADO_REAJ[r.estado] || ['secondary', r.estado]; return `<tr>
            <td class="ps-2">${fechaD(r.fecha_aplicar)}</td><td class="fw-medium">${esc(r.descripcion)}</td><td>${esc(r.producto_nombre || '')}</td>
            <td>${FORMA_LBL[r.forma] || r.forma}${r.forma !== 'inmueble' ? ` <span class="text-muted">${r.forma === 'porcentaje' ? (+r.parametro) + ' %' : money(r.parametro)}</span>` : ''}</td>
            <td class="text-end">${r.total_filas}</td><td class="text-end">${money(r.suma_actual)}</td><td class="text-end">${money(r.suma_nueva)}</td>
            <td><span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25" title="${esc(r.resultado || '')}">${lbl}</span></td>
            <td class="text-muted">${esc(r.usuario_nombre || '')}</td>
            <td class="text-end pe-2">${r.estado === 'pendiente' && CFG.perm.actualizar ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" title="Cancelar" onclick="CONDCFG.reajusteCancelar(${r.id})"><i class="bi bi-x-circle"></i></button>` : ''}</td></tr>`; }).join('')
            : '<tr><td colspan="10" class="text-center text-muted py-3">Sin reajustes.</td></tr>';
    }
    async function reajusteCancelar(id) {
        const c = await Swal.fire({ icon: 'warning', title: '¿Cancelar este reajuste programado?', showCancelButton: true, confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No' });
        if (!c.isConfirmed) return;
        const res = await post('reajusteCancelarAjax', { id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
        pintarReajustes(res.reajustes || []);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (CFG.config) cargarReajuste();
        ['reaj_id_producto', 'reaj_parametro', 'reaj_fecha_aplicar', 'reaj_incluir_sin_inmueble'].forEach(id => $(id)?.addEventListener('change', ocultarReajPreview));
        if (CFG.config) cargarValores();
        $('val_vigente_desde')?.addEventListener('change', valorPresupuesto);
        $('formCondConfig').addEventListener('submit', guardar);
        chip('cfg_prod_ordinaria_txt', 'cfg_prod_ordinaria', 'cfg_prod_ordinaria_dd', buscarServicios, lblServicio);
        chip('cfg_prod_fondo_txt', 'cfg_prod_fondo', 'cfg_prod_fondo_dd', buscarServicios, lblServicio);
        chip('cfg_prod_interes_txt', 'cfg_prod_interes', 'cfg_prod_interes_dd', buscarServicios, lblServicio);
        chip('multa_prod_txt', 'multa_id_producto', 'multa_prod_dd', buscarServicios, lblServicio);
        if (CFG.config) cargar(CFG.config); else { onFondo(); onIntereses(); }
        CFG.pendientesIni = document.querySelector('#cfg-aviso-pendientes') ? 1 : 0;
        pintarMultas(CFG.multas || []);
        if (typeof window.aplicarFavoritosModal === 'function' && !CFG.config) window.aplicarFavoritosModal('#formCondConfig');
    });

    return { onFondo, onIntereses, multaLimpiar, multaEditar, multaGuardar, multaEliminar,
             nuevoValor, valorPresupuesto, valorPreview, valorGuardar, valorEliminar,
             reajusteForma, reajustePreview, reajusteToggle, reajusteTodas, reajusteAplicar, reajusteCancelar };
})();
