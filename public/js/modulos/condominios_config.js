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

    document.addEventListener('DOMContentLoaded', () => {
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

    return { onFondo, onIntereses, multaLimpiar, multaEditar, multaGuardar, multaEliminar };
})();
