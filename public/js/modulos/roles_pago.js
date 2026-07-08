/**
 * Roles de Pago (Nómina):
 *  - modalRol: Nuevo (tipo/mes/año + N° condicional) + Generar.
 *  - modalRolVer: listado de empleados del rol con buscador.
 *  - modalRolEmp: detalle de un empleado (General / Provisiones / Asiento contable).
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/roles-pago';
    let mNuevo = null, mVer = null, mEmp = null;
    let rolActual = null; // { id, estado } del rol abierto en modalRolVer
    const form = document.getElementById('formRol');

    const TIPOS = { MENSUAL: 'Rol Mensual', QUINCENA: 'Quincena', SEMANAL: 'Semanal' };
    const ESTADOS = { borrador: 'Borrador', generado: 'Generado', pagado: 'Pagado', contabilizado: 'Contabilizado', anulado: 'Anulado' };
    const COLOR = { borrador: 'secondary', generado: 'info', pagado: 'success', contabilizado: 'primary', anulado: 'danger' };
    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const PAGO = { pagado: { c: 'success', t: 'Pagado' }, parcial: { c: 'warning', t: 'Parcial' }, pendiente: { c: 'secondary', t: 'Pendiente' } };
    const pagoBadge = (d) => {
        const p = PAGO[d.estado_pago] || PAGO.pendiente;
        return `<span class="badge bg-${p.c} bg-opacity-10 text-${p.c} border border-${p.c} border-opacity-25 ms-2" style="font-size:0.62rem;">${p.t}</span>`;
    };

    const $ = (id) => document.getElementById(id);
    const money = (v) => '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => (s == null ? '' : String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])));

    const modalNuevo = () => (mNuevo = mNuevo || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal($('modalRol')) : null));
    const modalVer = () => (mVer = mVer || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal($('modalRolVer')) : null));
    const modalEmp = () => (mEmp = mEmp || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal($('modalRolEmp')) : null));

    // ─── Nuevo ───────────────────────────────────────────────────────────────
    window.rolToggleNumero = function () {
        const tipo = $('rol_tipo_rol').value;
        const cont = $('rol_container_numero'), sel = $('rol_numero_periodo'), lbl = $('rol_label_numero');
        if (tipo === 'MENSUAL') { sel.innerHTML = '<option value="0">-</option>'; cont.classList.add('d-none'); return; }
        const max = tipo === 'QUINCENA' ? 2 : 5;
        lbl.textContent = tipo === 'QUINCENA' ? 'Quincena' : 'Semana';
        let opts = '';
        for (let i = 1; i <= max; i++) opts += `<option value="${i}">${i}</option>`;
        sel.innerHTML = opts;
        cont.classList.remove('d-none');
    };

    window.abrirModalCrear = function () {
        if (!form) return;
        form.reset();
        $('rol_id').value = '';
        window.rolToggleNumero();
        modalNuevo()?.show();
    };

    window.generarRol = async function () {
        const btn = $('btnGenerarRol');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
        try {
            const respC = await fetch(`${urlModulo}/store`, { method: 'POST', body: new FormData(form) });
            const jsonC = await respC.json();
            if (!jsonC.ok) { Swal.fire({ icon: 'error', title: 'Atención', text: jsonC.error || 'No se pudo crear.' }); btn.disabled = false; btn.innerHTML = '<i class="bi bi-gear-fill me-1"></i> Generar'; return; }
            const fd = new FormData(); fd.append('id', jsonC.id);
            const resp = await fetch(`${urlModulo}/generar`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                modalNuevo()?.hide();
                renderVer(json.data);
                modalVer()?.show();
                window.dispatchEvent(new CustomEvent('rolGuardado'));
            } else {
                Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo generar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-gear-fill me-1"></i> Generar';
    };

    // ─── Ver rol: listado de empleados con buscador ──────────────────────────
    window.abrirModalVer = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!id) return;
        $('rolver_lista').innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">Cargando…</td></tr>';
        $('rolver_buscar').value = '';
        modalVer()?.show();
        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (res.ok) renderVer(res.data);
            else $('rolver_lista').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">No se pudo cargar.</td></tr>';
        } catch (e) {
            $('rolver_lista').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error de red.</td></tr>';
        }
    };

    function renderVer(rol) {
        const mes = MESES[parseInt(rol.periodo_mes, 10)] || rol.periodo_mes;
        const num = parseInt(rol.numero_periodo, 10) > 0 ? ' #' + rol.numero_periodo : '';
        $('rolver_titulo').textContent = (TIPOS[rol.tipo_rol] || rol.tipo_rol);
        const badge = $('rolver_estado');
        badge.textContent = ESTADOS[rol.estado] || rol.estado;
        badge.className = 'badge ms-2 bg-' + (COLOR[rol.estado] || 'secondary');
        badge.classList.remove('d-none');
        rolActual = { id: rol.id, estado: rol.estado };
        $('rolver_periodo').textContent = `${mes} ${rol.periodo_anio}${num}`;
        $('rolver_totales').innerHTML = `Ingresos <b>${money(rol.total_ingresos)}</b> · Egresos <b>${money(rol.total_egresos)}</b> · Neto <b>${money(rol.total_neto)}</b>`;

        const det = rol.detalle || [];
        if (!det.length) { $('rolver_lista').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin empleados con conceptos.</td></tr>'; $('rolver_conteo').textContent = ''; return; }

        let rows = '';
        det.forEach(d => {
            rows += `<tr class="rolver-row" role="button" data-nombre="${esc((d.nombres_apellidos || '').toLowerCase())}" data-ident="${esc(d.identificacion || '')}" onclick="window.rolEmpDetalle(${d.id})">
                <td class="ps-3"><b>${esc(d.nombres_apellidos)}</b> <span class="text-muted small">${esc(d.identificacion)}</span>${pagoBadge(d)}</td>
                <td class="text-end text-success">${money(d.total_ingresos)}</td>
                <td class="text-end text-danger">${money(d.total_egresos)}</td>
                <td class="text-end pe-3 fw-bold">${money(d.neto)}</td></tr>`;
        });
        $('rolver_lista').innerHTML = rows;
        $('rolver_conteo').textContent = det.length + ' empleados';
    }

    window.rolEliminarModal = async function () {
        if (!rolActual) return;
        const r = await Swal.fire({ title: '¿Eliminar la corrida?', text: 'No podrá revertir esta acción.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id_eliminar', rolActual.id);
            const resp = await fetch(`${urlModulo}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1300, showConfirmButton: false });
                modalVer()?.hide();
                window.dispatchEvent(new CustomEvent('rolGuardado'));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
    };

    window.rolVerFiltrar = function () {
        const q = $('rolver_buscar').value.toLowerCase().trim();
        let vis = 0;
        $('rolver_lista').querySelectorAll('.rolver-row').forEach(tr => {
            const match = !q || (tr.dataset.nombre || '').includes(q) || (tr.dataset.ident || '').includes(q);
            tr.classList.toggle('d-none', !match);
            if (match) vis++;
        });
        $('rolver_conteo').textContent = vis + ' empleados';
    };

    // ─── Detalle de un empleado (pestañas) ───────────────────────────────────
    window.rolEmpDetalle = async function (det) {
        $('rolemp_general_body').innerHTML = '<div class="text-center py-4 text-muted">Cargando…</div>';
        $('rolemp_prov_body').innerHTML = '';
        $('rolemp_asiento_body').innerHTML = '';
        modalEmp()?.show();
        try {
            const resp = await fetch(`${urlModulo}/getEmpleadoAjax?det=${det}`);
            const res = await resp.json();
            if (!res.ok) { $('rolemp_general_body').innerHTML = '<div class="text-danger">No se pudo cargar.</div>'; return; }
            const d = res.data;
            $('rolemp_nombre').textContent = d.nombres_apellidos;
            $('rolemp_ident').textContent = d.identificacion;
            $('rolemp_pdf').onclick = () => {
                const a = document.createElement('a');
                a.href = `${urlModulo}/pdfEmpleado?det=${det}`;
                document.body.appendChild(a); a.click(); a.remove();
            };
            const btnMail = $('rolemp_email');
            btnMail.disabled = !d.email;
            btnMail.title = d.email ? 'Enviar por correo' : 'Sin correo en la ficha';
            btnMail.onclick = () => window.rolEmailEmpleado(det);
            renderGeneral(d);
            renderProvisiones(d);
            renderAsiento(d);
        } catch (e) {
            $('rolemp_general_body').innerHTML = '<div class="text-danger">Error de red.</div>';
        }
    };

    function renderGeneral(d) {
        const ing = (d.rubros || []).filter(r => r.tipo === 'ingreso');
        const egr = (d.rubros || []).filter(r => r.tipo === 'egreso');
        const filas = Math.max(ing.length, egr.length);
        let body = '';
        for (let k = 0; k < filas; k++) {
            const a = ing[k], b = egr[k];
            body += `<tr>
                <td class="ps-3 small text-success">${a ? esc(a.concepto) : ''}</td>
                <td class="small text-success text-end">${a ? money(a.valor) : ''}</td>
                <td class="small text-danger">${b ? esc(b.concepto) : ''}</td>
                <td class="small text-danger text-end pe-3">${b ? money(b.valor) : ''}</td></tr>`;
        }
        $('rolemp_general_body').innerHTML = `
            <table class="table table-sm table-bordered align-middle mb-2">
                <thead class="table-light"><tr>
                    <th class="ps-3 small">Ingreso</th><th class="small text-end">Valor</th>
                    <th class="small">Egreso</th><th class="small text-end pe-3">Valor</th>
                </tr></thead>
                <tbody>${body}
                    <tr class="table-light fw-bold">
                        <td class="ps-3 small">Total Ingresos</td><td class="small text-end">${money(d.total_ingresos)}</td>
                        <td class="small">Total Egresos</td><td class="small text-end pe-3">${money(d.total_egresos)}</td>
                    </tr>
                </tbody>
            </table>
            <div class="d-flex justify-content-end"><span class="fs-6">Neto a recibir: <b class="text-primary">${money(d.neto)}</b></span></div>`;
    }

    function renderProvisiones(d) {
        const prov = d.provisiones || [];
        if (!prov.length) { $('rolemp_prov_body').innerHTML = '<div class="text-muted py-3 text-center">Las provisiones solo aplican al rol mensual.</div>'; return; }
        let rows = '', tot = 0;
        prov.forEach(p => {
            if (p.incluir) tot += parseFloat(p.valor) || 0;
            rows += `<tr class="${p.incluir ? '' : 'text-muted'}">
                <td class="ps-3">${esc(p.concepto)}</td>
                <td class="text-end">${money(p.valor)}</td>
                <td class="small">${p.incluir ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Provisionado</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">' + esc(p.nota) + '</span>'}</td></tr>`;
        });
        $('rolemp_prov_body').innerHTML = `
            <p class="small text-muted">Beneficios sociales que la empresa acumula este mes por el empleado.</p>
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light"><tr><th class="ps-3">Concepto</th><th class="text-end">Valor mensual</th><th>Estado</th></tr></thead>
                <tbody>${rows}
                    <tr class="table-light fw-bold"><td class="ps-3">Total provisionado</td><td class="text-end">${money(tot)}</td><td></td></tr>
                </tbody>
            </table>`;
    }

    function renderAsiento(d) {
        const a = d.asiento;
        if (!a) { $('rolemp_asiento_body').innerHTML = '<div class="text-muted py-3 text-center">El asiento contable solo aplica al rol mensual.</div>'; return; }

        const cuenta = (x) => x.cuenta_codigo
            ? `<span class="text-muted">${esc(x.cuenta_codigo)}</span> ${esc(x.cuenta_nombre)}`
            : `<span class="text-danger">${esc(x.cuenta_nombre)}</span>`;
        const fila = (x, lado) => `<tr>
            <td class="ps-3 small">${cuenta(x)}</td>
            <td class="text-end pe-3 small">${lado === 'debe' ? money(x.valor) : ''}</td>
            <td class="text-end pe-3 small">${lado === 'haber' ? money(x.valor) : ''}</td>
            <td class="small text-muted">${esc(x.concepto)}</td></tr>`;
        const rows = a.debe.map(x => fila(x, 'debe')).join('') + a.haber.map(x => fila(x, 'haber')).join('');
        const badge = a.cuadrado
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2"><i class="bi bi-check2-circle me-1"></i>Cuadrado</span>'
            : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2">Descuadrado (Dif: ' + money(a.total_debe - a.total_haber) + ')</span>';

        $('rolemp_asiento_body').innerHTML = `
            <p class="small text-muted">Asiento del empleado con sus cuentas resueltas (específica del empleado o general de nómina). Configúrelas en Configuración Contable → Nómina.</p>
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                <div class="table-responsive" style="max-height: 340px;">
                    <table class="table table-sm mb-0 text-nowrap align-middle">
                        <thead class="table-light border-bottom">
                            <tr>
                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Débito / Debe</th>
                                <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Crédito / Haber</th>
                                <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                        <tfoot class="bg-light fw-bold border-top">
                            <tr>
                                <td class="text-end py-2">Totales:</td>
                                <td class="text-end pe-3 py-2 text-primary">${money(a.total_debe)}</td>
                                <td class="text-end pe-3 py-2 text-primary">${money(a.total_haber)}</td>
                                <td class="py-2">${badge}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>`;
    }

    window.rolEmailEmpleado = async function (det) {
        const c = await Swal.fire({ title: 'Enviar por correo', text: 'Se enviará el rol al correo del empleado.', icon: 'question', showCancelButton: true, confirmButtonText: 'Enviar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        Swal.fire({ title: 'Enviando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('det', det);
            const resp = await fetch(`${urlModulo}/enviarCorreoEmpleado`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) Swal.fire({ icon: 'success', title: 'Enviado', text: json.msg, timer: 1800, showConfirmButton: false });
            else Swal.fire({ icon: 'error', title: 'Atención', text: json.error });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
    };

    // ─── Eliminar (fila del listado) ─────────────────────────────────────────
    window.eliminarRegistro = async function (id) {
        if (!id) return;
        const r = await Swal.fire({ title: '¿Está seguro?', text: 'No podrá revertir esta acción.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModulo}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1300, showConfirmButton: false });
                window.dispatchEvent(new CustomEvent('rolGuardado'));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) {}
    };

})(window, document);
