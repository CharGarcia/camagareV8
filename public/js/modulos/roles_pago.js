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
    const PAGO = {
        pagado:    { c: 'success',   t: 'Pagado',         i: 'bi-check-circle-fill' },
        parcial:   { c: 'warning',   t: 'Pago parcial',   i: 'bi-hourglass-split' },
        pendiente: { c: 'secondary', t: 'Pago pendiente', i: 'bi-cash-coin' }
    };
    const pagoBadge = (d) => {
        const p = PAGO[d.estado_pago] || PAGO.pendiente;
        const pagado = money(d.pagado || 0);
        const saldo  = money(d.saldo != null ? d.saldo : d.neto);
        const title  = `Estado de pago del rol · Pagado ${pagado} · Saldo ${saldo}`;
        return `<span class="badge bg-${p.c} bg-opacity-10 text-${p.c} border border-${p.c} border-opacity-25 ms-2" style="font-size:0.62rem;" title="${title}"><i class="bi ${p.i} me-1"></i>${p.t}</span>`;
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
                avisarPendientes(json.data.avisos || []);
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
        renderAvisos(rol.avisos || []);

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

    // Banner de anticipos/préstamos pendientes de desembolso (no se descuentan hasta pagarlos).
    function renderAvisos(avisos) {
        const box = $('rolver_avisos');
        if (!box) return;
        if (!avisos || !avisos.length) { box.innerHTML = ''; return; }
        const li = avisos.map(a =>
            `<li>${esc(a.empleado)} — <b>${esc(a.concepto)}</b>: ${money(a.monto)} <span class="text-muted">(${a.tipo === 'anticipo' ? 'anticipo sin pagar' : 'préstamo sin desembolsar'})</span></li>`
        ).join('');
        box.innerHTML = `<div class="alert alert-warning py-2 px-3 mb-2 small">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <b>Pendientes de desembolso.</b> Estas novedades NO se descuentan en el rol hasta pagarlas en <b>Egresos → Nómina</b>:
            <ul class="mb-0 mt-1">${li}</ul></div>`;
    }

    // Aviso emergente al generar, si hay pendientes de desembolso.
    function avisarPendientes(avisos) {
        if (!avisos || !avisos.length) return;
        const li = avisos.map(a => `<li>${esc(a.empleado)} — ${esc(a.concepto)}: ${money(a.monto)}</li>`).join('');
        Swal.fire({
            icon: 'warning',
            title: 'Hay novedades sin desembolsar',
            html: `<div class="small text-start">Estos anticipos/préstamos <b>no se descontaron</b> en el rol porque aún no se han pagado en <b>Egresos → Nómina</b>:<ul class="mt-2">${li}</ul></div>`,
            confirmButtonText: 'Entendido'
        });
    }

    // ─── Generar egresos de nómina en lote (un egreso por empleado) ──────────
    let mEgLote = null;
    const modalEgLote = () => (mEgLote = mEgLote || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal($('modalEgresoLote')) : null));

    window.abrirEgresoLote = async function () {
        if (!rolActual || !rolActual.id) return;
        if ($('egl_msg')) $('egl_msg').innerHTML = '';
        try {
            const resp = await fetch(`${urlModulo}/datosEgresoLoteAjax?id_rol=${rolActual.id}`);
            const res = await resp.json();
            if (!res.ok) { Swal.fire('Atención', res.error || 'No se pudieron cargar los datos.', 'warning'); return; }
            if (!res.tiene_concepto) {
                Swal.fire('Falta configurar', 'No existe un concepto de egreso de Nómina (comportamiento ROL). Créelo en opciones de ingreso/egreso antes de generar.', 'warning');
                return;
            }
            if (!res.pendientes) {
                Swal.fire('Sin saldos pendientes', 'Todos los empleados de este rol ya tienen su egreso generado. No hay nada que pagar.', 'info');
                return;
            }
            const selP = $('egl_punto'); selP.innerHTML = '';
            (res.puntos || []).forEach(p => {
                const est = String(p.cod_establecimiento || '').padStart(3, '0');
                const pto = String(p.codigo_punto || '').padStart(3, '0');
                const o = document.createElement('option'); o.value = p.id; o.textContent = `${est}-${pto}`;
                selP.appendChild(o);
            });
            const selF = $('egl_forma'); selF.innerHTML = '';
            (res.formas || []).forEach(f => {
                const o = document.createElement('option'); o.value = f.id; o.textContent = f.nombre;
                o.dataset.tipo = (f.tipo || '');
                selF.appendChild(o);
            });
            if (!selP.options.length) { Swal.fire('Atención', 'No hay puntos de emisión configurados.', 'warning'); return; }
            if (!selF.options.length) { Swal.fire('Atención', 'No hay formas de pago para egresos configuradas.', 'warning'); return; }
            eglPintarEmpleados(res.empleados || []);
            window.eglFormaChange();
            modalEgLote()?.show();
        } catch (e) {
            Swal.fire('Error de Red', 'No se pudo conectar con el servidor.', 'error');
        }
    };

    // ── Selección de empleados del lote ─────────────────────────────────────
    // Se listan solo los que tienen saldo pendiente; arrancan todos marcados
    // (es el caso habitual) y el usuario desmarca a quien no va a pagar ahora.
    function eglPintarEmpleados(lista) {
        const tb = $('egl_empleados');
        if (!tb) return;
        tb.innerHTML = '';

        lista.forEach(e => {
            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            tr.style.userSelect = 'none'; // evita seleccionar el texto al hacer clic repetido
            tr.title = 'Clic para marcar o desmarcar';
            // Marcar/desmarcar haciendo clic en cualquier parte de la fila. El clic
            // sobre la casilla se deja pasar tal cual (si no, se alternaría dos veces).
            tr.addEventListener('click', ev => {
                if (ev.target.classList.contains('egl-chk')) return;
                const chk = tr.querySelector('.egl-chk');
                if (!chk) return;
                chk.checked = !chk.checked;
                window.eglActualizarSeleccion();
            });

            const parcial = e.estado_pago === 'parcial'
                ? ` <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-25">abonado ${money(e.pagado)}</span>`
                : '';
            tr.innerHTML =
                `<td class="text-center"><input type="checkbox" class="form-check-input m-0 egl-chk"
                     value="${e.id_detalle}" data-saldo="${e.saldo}" onchange="window.eglActualizarSeleccion()" checked></td>
                 <td>${esc(e.nombres_apellidos)}${parcial}<div class="text-muted" style="font-size:0.7rem;">${esc(e.identificacion || '')}</div></td>
                 <td class="text-end">${money(e.neto)}</td>
                 <td class="text-end fw-bold">${money(e.saldo)}</td>`;
            tb.appendChild(tr);
        });

        window.eglActualizarSeleccion();
    }

    window.eglMarcarTodos = function (marcar) {
        document.querySelectorAll('#egl_empleados .egl-chk').forEach(c => { c.checked = !!marcar; });
        window.eglActualizarSeleccion();
    };

    window.eglActualizarSeleccion = function () {
        const chks = Array.from(document.querySelectorAll('#egl_empleados .egl-chk'));
        const sel = chks.filter(c => c.checked);
        const total = sel.reduce((s, c) => s + (parseFloat(c.dataset.saldo) || 0), 0);

        // Resalta las filas marcadas para que la selección se vea de un vistazo.
        chks.forEach(c => c.closest('tr')?.classList.toggle('table-active', c.checked));

        if ($('egl_sel_conteo')) $('egl_sel_conteo').textContent = sel.length;
        if ($('egl_sel_total')) $('egl_sel_total').textContent = money(total);

        // Checkbox de la cabecera: marcado / vacío / indeterminado.
        const todos = $('egl_chk_todos');
        if (todos) {
            todos.checked = sel.length > 0 && sel.length === chks.length;
            todos.indeterminate = sel.length > 0 && sel.length < chks.length;
        }

        const btn = $('eglBtnConfirmar');
        if (btn) btn.disabled = sel.length === 0;
    };

    // Muestra/oculta el bloque bancario según la forma de pago seleccionada.
    window.eglFormaChange = function () {
        const sel = $('egl_forma'); const opt = sel.options[sel.selectedIndex];
        const esBanco = opt && String(opt.dataset.tipo || '').toUpperCase() === 'BANCO';
        $('egl_banco_wrap').classList.toggle('d-none', !esBanco);
        if (esBanco) { $('egl_tipo_op').value = 'TRANSFERENCIA'; window.eglTipoOpChange(); }
    };
    window.eglTipoOpChange = function () {
        const esCheque = $('egl_tipo_op').value === 'CHEQUE';
        document.querySelectorAll('.egl-cheque-campo').forEach(el => el.classList.toggle('d-none', !esCheque));
        if (!esCheque) { const f = $('egl_cheque_fecha'); if (f) f.value = ''; }
    };

    window.confirmarEgresoLote = async function () {
        if (!rolActual || !rolActual.id) return;
        const btn = $('eglBtnConfirmar');
        const fecha = $('egl_fecha').value, idPunto = $('egl_punto').value, idForma = $('egl_forma').value;
        if (!fecha || !idPunto || !idForma) { Swal.fire('Requerido', 'Complete fecha, punto y forma de pago.', 'warning'); return; }

        const marcados = Array.from(document.querySelectorAll('#egl_empleados .egl-chk:checked'));
        if (!marcados.length) { Swal.fire('Requerido', 'Marque al menos un empleado.', 'warning'); return; }
        const totalSel = marcados.reduce((s, c) => s + (parseFloat(c.dataset.saldo) || 0), 0);
        // Datos bancarios (si aplica).
        const esBanco = !$('egl_banco_wrap').classList.contains('d-none');
        let tipoOp = '', chequeIni = '', chequeFecha = '';
        if (esBanco) {
            tipoOp = $('egl_tipo_op').value;
            if (tipoOp === 'CHEQUE') {
                chequeIni = $('egl_cheque_ini').value;
                if (!chequeIni || parseInt(chequeIni, 10) <= 0) { Swal.fire('Requerido', 'Ingrese el número inicial del cheque.', 'warning'); return; }
                chequeFecha = $('egl_cheque_fecha').value;
                if (!chequeFecha) { Swal.fire('Requerido', 'Indique la fecha de cobro del cheque.', 'warning'); return; }
            }
        }
        const conf = await Swal.fire({
            icon: 'question', title: '¿Generar egresos?',
            html: `Se creará un egreso para <b>${marcados.length}</b> empleado(s) por un total de <b>${money(totalSel)}</b>.`,
            showCancelButton: true, confirmButtonText: 'Sí, generar', cancelButtonText: 'Cancelar'
        });
        if (!conf.isConfirmed) return;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';
        try {
            const fd = new FormData();
            fd.append('id_rol', rolActual.id); fd.append('fecha', fecha); fd.append('id_punto_emision', idPunto); fd.append('id_forma_pago', idForma);
            marcados.forEach(c => fd.append('ids_detalle[]', c.value));
            if (esBanco) { fd.append('tipo_operacion_bancaria', tipoOp); if (tipoOp === 'CHEQUE') { fd.append('numero_cheque_inicial', chequeIni); fd.append('fecha_cobro', chequeFecha); } }
            const resp = await fetch(`${urlModulo}/generarEgresosLoteAjax`, { method: 'POST', body: fd });
            const res = await resp.json();
            if (!res.ok) { Swal.fire('Atención', res.error || 'No se pudo generar.', 'error'); }
            else {
                modalEgLote()?.hide();
                let html = `<div class="small text-start">Se generaron <b>${res.creados}</b> egreso(s) por <b>${money(res.total || 0)}</b>.`;
                if (res.omitidos) html += ` ${res.omitidos} ya estaban pagados.`;
                if (res.no_seleccionados) html += ` ${res.no_seleccionados} quedaron pendientes por no estar marcados.`;
                if (res.errores && res.errores.length) {
                    html += `<div class="alert alert-warning mt-2 mb-0 py-1 px-2"><b>${res.errores.length} con error:</b><ul class="mb-0 mt-1">` +
                        res.errores.map(e => `<li>${esc(e.empleado)}: ${esc(e.error)}</li>`).join('') + `</ul></div>`;
                }
                html += '</div>';
                Swal.fire({ icon: (res.errores && res.errores.length) ? 'warning' : 'success', title: 'Egresos generados', html });
                window.abrirModalVer({ id: rolActual.id }); // refrescar estado de pago
                window.dispatchEvent(new CustomEvent('rolGuardado'));
            }
        } catch (e) {
            Swal.fire('Error de Red', 'No se pudo conectar con el servidor.', 'error');
        }
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Generar egresos';
        window.eglActualizarSeleccion(); // rehabilita el botón solo si sigue habiendo marcados
    };

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
        if ($('rolemp_asist_body')) $('rolemp_asist_body').innerHTML = '';
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
            renderAsistencia(d);
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

    function renderAsistencia(d) {
        const cont = $('rolemp_asist_body');
        if (!cont) return;
        const a = d.asistencia || { dias: [], resumen: null };
        const dias = a.dias || [];
        if (!dias.length) {
            cont.innerHTML = '<div class="text-muted py-4 text-center"><i class="bi bi-clock-history me-1"></i>Sin marcaciones/jornadas registradas para este empleado en el período.</div>';
            return;
        }
        const r = a.resumen || {};
        const hm = (min) => { const m = parseInt(min, 10) || 0; return m > 0 ? (m + ' min') : '—'; };
        const hhmm = (ts) => ts ? String(ts).substring(11, 16) : '—';
        const estadoColor = { completa: 'success', incompleta: 'warning', falta: 'danger', permiso: 'info' };
        const cap = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : '';

        const totFaltas = parseInt(r.dias_falta, 10) || 0;
        const totAtraso = parseInt(r.atraso_min, 10) || 0;
        const totExtra = parseInt(r.extra_min, 10) || 0;
        const totDias = dias.filter(x => (x.estado || '') !== 'falta').length;

        let filas = '';
        dias.forEach(x => {
            const ec = estadoColor[x.estado] || 'secondary';
            const atr = (parseInt(x.atraso_min, 10) || 0);
            const ext = (parseInt(x.extra_min, 10) || 0);
            filas += `<tr>
                <td class="ps-3 small">${esc(String(x.fecha).substring(0, 10).split('-').reverse().join('-'))}</td>
                <td class="text-center small">${hhmm(x.primera_entrada)}</td>
                <td class="text-center small">${hhmm(x.ultima_salida)}</td>
                <td class="text-center small fw-medium">${(parseFloat(x.horas_trabajadas) || 0).toFixed(2)}</td>
                <td class="text-center small ${atr > 0 ? 'text-danger fw-medium' : 'text-muted'}">${atr > 0 ? atr + ' min' : '—'}</td>
                <td class="text-center small ${ext > 0 ? 'text-success fw-medium' : 'text-muted'}">${ext > 0 ? ext + ' min' : '—'}</td>
                <td class="text-center"><span class="badge bg-${ec} bg-opacity-10 text-${ec} border border-${ec} border-opacity-25">${esc(cap(x.estado))}</span></td>
            </tr>`;
        });

        cont.innerHTML = `
            <div class="row g-2 mb-3">
                <div class="col"><div class="border rounded-3 p-2 text-center"><div class="small text-muted">Días trabajados</div><div class="fw-bold">${totDias}</div></div></div>
                <div class="col"><div class="border rounded-3 p-2 text-center"><div class="small text-muted">Faltas</div><div class="fw-bold text-danger">${totFaltas}</div></div></div>
                <div class="col"><div class="border rounded-3 p-2 text-center"><div class="small text-muted">Atraso total</div><div class="fw-bold text-danger">${hm(totAtraso)}</div></div></div>
                <div class="col"><div class="border rounded-3 p-2 text-center"><div class="small text-muted">Extra total</div><div class="fw-bold text-success">${hm(totExtra)}</div></div></div>
            </div>
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                <div class="table-responsive" style="max-height: 320px;">
                    <table class="table table-sm mb-0 text-nowrap align-middle">
                        <thead class="table-light border-bottom">
                            <tr>
                                <th class="ps-3 py-2 small fw-bold text-muted">Fecha</th>
                                <th class="py-2 small fw-bold text-muted text-center">Entrada</th>
                                <th class="py-2 small fw-bold text-muted text-center">Salida</th>
                                <th class="py-2 small fw-bold text-muted text-center">Horas</th>
                                <th class="py-2 small fw-bold text-muted text-center">Atraso</th>
                                <th class="py-2 small fw-bold text-muted text-center">Extra</th>
                                <th class="py-2 small fw-bold text-muted text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                    </table>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Asistencia del mes del rol. Los atrasos se trasladan al rol según el tratamiento de la ficha del empleado.</p>`;
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
