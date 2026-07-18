let TR_loteActual = null; // null = armando un lote nuevo (aún no guardado); objeto = lote ya guardado
let TR_huboCambios = false; // se puso true si algo cambió mientras el modal estuvo abierto

// Al cerrar el modal, si algo cambió, se refresca el listado de fondo (que quedó desactualizado
// porque las acciones ahora recargan el contenido del modal en lugar de recargar la página).
document.getElementById('tr-modal-lote').addEventListener('hidden.bs.modal', function () {
    if (TR_huboCambios) {
        TR_huboCambios = false;
        window.location.reload();
    }
});

function TR_buscar(p = 1) {
    const b = document.getElementById('tr-buscar').value;
    window.location.href = `${TR_URL}/index?b=${encodeURIComponent(b)}&page=${p}&sort=${TR_currentSort}&dir=${TR_currentDir}`;
}

function TR_esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ─── SweetAlert helpers ─────────────────────────────────────────────────────

/**
 * Bootstrap "atrapa" el foco dentro de su modal (#tr-modal-lote) mientras está
 * abierto: si un SweetAlert2 se dibuja fuera de ese elemento (su comportamiento
 * por defecto), Bootstrap le devuelve el foco al modal apenas el usuario intenta
 * escribir, y el textarea del Swal queda inutilizable. Se soluciona indicándole
 * a SweetAlert2 que renderice DENTRO del modal (target) cuando está abierto.
 */
function TR_swalTarget() {
    const modalEl = document.getElementById('tr-modal-lote');
    return (modalEl && modalEl.classList.contains('show')) ? modalEl : document.body;
}

function TR_toast(icon, title) {
    if (window.Swal) Swal.fire({ toast: true, position: 'top-end', timer: 2500, showConfirmButton: false, icon, title, target: TR_swalTarget() });
}

function TR_swalError(msg) {
    if (window.Swal) Swal.fire({ title: 'Error', text: msg || 'Ocurrió un error', icon: 'error', target: TR_swalTarget() });
    else alert(msg || 'Ocurrió un error');
}

async function TR_confirmar(title, text, confirmButtonText = 'Sí, continuar') {
    if (!window.Swal) return confirm(text || title);
    const res = await Swal.fire({
        title, text, icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#0d6efd', cancelButtonColor: '#6c757d',
        confirmButtonText, cancelButtonText: 'Cancelar',
        target: TR_swalTarget(),
    });
    return res.isConfirmed;
}

async function TR_pedirMotivo(title, inputLabel) {
    if (!window.Swal) return prompt(inputLabel);
    const { value, isConfirmed } = await Swal.fire({
        title, input: 'textarea', inputLabel,
        inputPlaceholder: 'Escriba el motivo…',
        showCancelButton: true,
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar',
        inputValidator: (v) => (!v || !v.trim()) ? 'Debe indicar el motivo.' : undefined,
        target: TR_swalTarget(),
    });
    return isConfirmed ? value.trim() : null;
}

// ─── Modal: campos del formulario (cabecera) ───────────────────────────────

const TR_CAMPOS_FORM = ['tr-f-tipo', 'tr-f-fecha', 'tr-f-forma', 'tr-f-banco', 'tr-f-obs'];

function TR_resetForm() {
    document.getElementById('tr-f-tipo').value = 'AMBOS';
    document.getElementById('tr-f-fecha').value = new Date().toISOString().slice(0, 10);
    document.getElementById('tr-f-forma').value = '';
    document.getElementById('tr-f-banco').value = '';
    document.getElementById('tr-f-obs').value = '';
}

function TR_poblarForm(l) {
    document.getElementById('tr-f-tipo').value = l.tipo_lote || 'AMBOS';
    document.getElementById('tr-f-fecha').value = l.fecha_pago ? l.fecha_pago.substring(0, 10) : '';
    document.getElementById('tr-f-forma').value = l.id_forma_pago_origen || '';
    document.getElementById('tr-f-banco').value = l.id_banco_formato || '';
    document.getElementById('tr-f-obs').value = l.observaciones || '';
}

function TR_setFormEditable(editable) {
    TR_CAMPOS_FORM.forEach(id => { document.getElementById(id).disabled = !editable; });
}

function TR_ocultarBotones() {
    ['tr-btn-guardar', 'tr-btn-enviar-aprobacion', 'tr-btn-aprobar', 'tr-btn-rechazar', 'tr-btn-generar', 'tr-btn-descargar', 'tr-btn-confirmar', 'tr-btn-anular', 'tr-btn-eliminar', 'tr-btn-agregar-sel']
        .forEach(b => { const el = document.getElementById(b); if (el) el.classList.add('d-none'); });
}

/** Oculta la barra de acciones si ninguno de sus botones quedó visible (evita una barra vacía). */
function TR_sincronizarBarraAcciones() {
    const barra = document.getElementById('tr-barra-acciones');
    const algunaVisible = Array.from(barra.querySelectorAll('button, a')).some(el => !el.classList.contains('d-none'));
    barra.classList.toggle('d-none', !algunaVisible);
}

// ─── Config de aprobación (se refresca cada vez que se abre el modal) ──────

let TR_REQUIERE_APROBACION = false;

async function TR_cargarConfigAprobacion() {
    try {
        const res = await fetch(`${TR_URL}/getConfigAprobacionAjax`);
        const json = await res.json();
        if (json.ok) {
            TR_ES_APROBADOR = !!json.data.esAprobador;
            TR_APROBADORES = json.data.aprobadores || [];
            TR_REQUIERE_APROBACION = !!json.data.requiere;
        }
    } catch (err) {
        // Si falla, se mantienen los valores cargados con la página.
    }
    TR_actualizarBotonEnviar();
}

/** El texto del botón depende de si la empresa realmente exige aprobación o no. */
function TR_actualizarBotonEnviar() {
    const btn = document.getElementById('tr-btn-enviar-aprobacion');
    if (!btn) return;
    btn.innerHTML = TR_REQUIERE_APROBACION
        ? '<i class="bi bi-send-check me-1"></i>Enviar a aprobación'
        : '<i class="bi bi-check2-circle me-1"></i>Aprobar y continuar';
}

// ─── Abrir modal: nuevo lote ────────────────────────────────────────────────

async function TR_abrirNuevo() {
    TR_loteActual = null;
    document.getElementById('tr-modal-titulo').textContent = 'Nuevo lote de pago bancario';
    document.getElementById('tr-detalle-msg').innerHTML = '';
    document.getElementById('tr-detalle-cuerpo').innerHTML = '';
    document.getElementById('tr-selector').classList.add('d-none');
    document.getElementById('tr-bloque-agregar-pagos').classList.remove('d-none');
    document.getElementById('tr-barra-acciones').classList.add('d-none');
    TR_resetForm();
    TR_setFormEditable(true);
    TR_ocultarBotones();
    const btnGuardar = document.getElementById('tr-btn-guardar');
    if (btnGuardar) btnGuardar.classList.remove('d-none');
    new bootstrap.Modal(document.getElementById('tr-modal-lote')).show();
    await TR_cargarConfigAprobacion();
}

// ─── Abrir modal: lote existente ────────────────────────────────────────────

async function TR_abrirExistente(id) {
    TR_loteActual = null;
    document.getElementById('tr-modal-titulo').textContent = 'Cargando…';
    document.getElementById('tr-detalle-msg').innerHTML = '';
    document.getElementById('tr-detalle-cuerpo').innerHTML = 'Cargando…';
    document.getElementById('tr-selector').classList.add('d-none');
    document.getElementById('tr-barra-acciones').classList.add('d-none');
    TR_ocultarBotones();
    new bootstrap.Modal(document.getElementById('tr-modal-lote')).show();
    await TR_cargarConfigAprobacion();
    await TR_cargarLote(id);
}

/** Carga/recarga el lote DENTRO del modal ya abierto (sin cerrarlo ni reabrirlo). */
async function TR_cargarLote(id) {
    document.getElementById('tr-detalle-msg').innerHTML = '';
    document.getElementById('tr-selector').classList.add('d-none');
    try {
        const res = await fetch(`${TR_URL}/getDetalleAjax?id=${id}`);
        const json = await res.json();
        if (!json.ok) { document.getElementById('tr-detalle-cuerpo').innerHTML = `<div class="text-danger">${json.mensaje}</div>`; return; }
        const l = json.data;
        TR_loteActual = l;
        document.getElementById('tr-modal-titulo').textContent = 'Lote de pago bancario #' + l.numero;
        TR_poblarForm(l);
        TR_setFormEditable(l.estado === 'BORRADOR');

        const estadoTxt = {
            BORRADOR: 'Borrador', PENDIENTE_APROBACION: 'Pendiente de aprobación', APROBADO: 'Aprobado',
            RECHAZADO: 'Rechazado', GENERADO: 'Generado', CONFIRMADO: 'Confirmado', ANULADO: 'Anulado'
        }[l.estado] || l.estado;

        let filasDetalle = (l.detalle || []).map(d => `
            <tr>
                <td>${TR_esc(d.nombre_beneficiario)}</td>
                <td>${TR_esc(d.identificacion)}</td>
                <td>${TR_esc(d.tipo_cuenta)} ${TR_esc(d.numero_cuenta)}</td>
                <td class="text-end">$ ${parseFloat(d.monto || 0).toFixed(2)}</td>
                <td class="text-center">
                    ${l.estado === 'BORRADOR' ? `<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" title="Quitar" onclick="TR_quitarLinea(${d.id})"><i class="bi bi-x"></i></button>` : ''}
                </td>
            </tr>`).join('');

        document.getElementById('tr-detalle-cuerpo').innerHTML = `
            <div class="row g-2 small mb-3">
                <div class="col-md-4"><div class="text-muted" style="font-size:.65rem;">Estado</div><div class="fw-bold">${estadoTxt}</div></div>
                <div class="col-md-4"><div class="text-muted" style="font-size:.65rem;">Monto total</div><div class="fw-bold">$ ${parseFloat(l.monto_total || 0).toFixed(2)}</div></div>
                <div class="col-md-4"><div class="text-muted" style="font-size:.65rem;">Pagos</div><div class="fw-bold">${l.cantidad_pagos || 0}</div></div>
                ${l.motivo_rechazo ? `<div class="col-12"><div class="text-muted" style="font-size:.65rem;">Motivo rechazo</div><div class="text-danger">${TR_esc(l.motivo_rechazo)}</div></div>` : ''}
                ${l.motivo_anulacion ? `<div class="col-12"><div class="text-muted" style="font-size:.65rem;">Motivo anulación</div><div class="text-dark">${TR_esc(l.motivo_anulacion)}</div></div>` : ''}
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:.78rem;">
                    <thead class="table-light"><tr><th>Beneficiario</th><th>Identificación</th><th>Cuenta</th><th class="text-end">Monto</th><th></th></tr></thead>
                    <tbody>${filasDetalle || '<tr><td colspan="5" class="text-center text-muted">Sin pagos agregados</td></tr>'}</tbody>
                </table>
            </div>`;

        // Botones según estado + segregación de funciones.
        const esCreador = String(l.created_by) === String(TR_ID_USUARIO);
        const puedeAprobar = TR_ES_APROBADOR && (TR_ES_SUPERADMIN || !esCreador);

        TR_ocultarBotones();
        const show = (id) => { const el = document.getElementById(id); if (el) el.classList.remove('d-none'); };

        const puedeAgregarPagos = l.estado === 'BORRADOR';
        document.getElementById('tr-bloque-agregar-pagos').classList.toggle('d-none', !puedeAgregarPagos);
        if (puedeAgregarPagos) show('tr-btn-agregar-sel');

        if (l.estado === 'BORRADOR') {
            show('tr-btn-guardar');
            show('tr-btn-enviar-aprobacion');
            show('tr-btn-eliminar');
        }
        if (l.estado === 'PENDIENTE_APROBACION') {
            if (puedeAprobar) { show('tr-btn-aprobar'); show('tr-btn-rechazar'); }
            else {
                const quien = TR_APROBADORES.length ? TR_APROBADORES.join(', ') : 'un usuario autorizado (configúrelos en Empresa → Pagos al Banco)';
                document.getElementById('tr-detalle-msg').innerHTML = `<div class="alert alert-info py-2 px-3 small mb-2"><i class="bi bi-hourglass-split me-1"></i>Pendiente de aprobación por: <strong>${quien}</strong>.</div>`;
            }
        }
        if (l.estado === 'APROBADO' || l.estado === 'GENERADO') {
            show('tr-btn-generar');
        }
        if (l.estado === 'GENERADO') {
            const btnDesc = document.getElementById('tr-btn-descargar');
            btnDesc.href = `${TR_URL}/descargarArchivo?id=${l.id}`;
            show('tr-btn-descargar');
            show('tr-btn-confirmar');
        }
        if (['PENDIENTE_APROBACION', 'APROBADO', 'GENERADO', 'CONFIRMADO'].includes(l.estado)) {
            show('tr-btn-anular');
        }
        TR_sincronizarBarraAcciones();
    } catch (err) {
        document.getElementById('tr-detalle-cuerpo').innerHTML = '<div class="text-danger">Error de conexión.</div>';
    }
}

/** Botón "Mostrar pagos pendientes de transferencia": revela y carga la tabla bajo demanda. */
function TR_mostrarPagosPendientes() {
    document.getElementById('tr-selector').classList.remove('d-none');
    TR_cargarSelector();
}

// ─── Guardar cabecera (crea el lote la primera vez, o actualiza el borrador) ─

async function TR_guardarCabecera() {
    const tipo   = document.getElementById('tr-f-tipo').value;
    const fecha  = document.getElementById('tr-f-fecha').value;
    const forma  = document.getElementById('tr-f-forma').value;
    const banco  = document.getElementById('tr-f-banco').value;
    const obs    = document.getElementById('tr-f-obs').value;

    if (!forma) { TR_swalError('Seleccione la cuenta de origen.'); return; }
    if (!fecha) { TR_swalError('Indique la fecha de pago.'); return; }

    const body = new URLSearchParams();
    body.append('tipo_lote', tipo);
    body.append('fecha_pago', fecha);
    body.append('id_forma_pago_origen', forma);
    body.append('id_banco_formato', banco);
    body.append('observaciones', obs);

    const btn = document.getElementById('tr-btn-guardar');
    btn.disabled = true;
    try {
        if (!TR_loteActual) {
            // Pagos que el usuario ya marcó (vía "Mostrar pagos pendientes") antes de guardar.
            const idsSeleccionados = Array.from(document.querySelectorAll('.tr-sel-pago:checked')).map(el => el.value);

            const res = await fetch(`${TR_URL}/crearAjax`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            const json = await res.json();
            if (!json.ok) { TR_swalError(json.mensaje || 'Error al guardar el lote'); return; }

            const nuevoId = json.data.id;
            if (idsSeleccionados.length) {
                const body2 = new URLSearchParams();
                body2.append('id_lote', nuevoId);
                idsSeleccionados.forEach(id => body2.append('ids_egreso_pago[]', id));
                const res2 = await fetch(`${TR_URL}/agregarLineasAjax`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body2 });
                const json2 = await res2.json();
                if (!json2.ok) {
                    TR_swalError('El lote se guardó, pero no se pudieron agregar los pagos seleccionados: ' + json2.mensaje);
                }
            }
            TR_huboCambios = true;
            TR_toast('success', 'Lote guardado');
            await TR_cargarLote(nuevoId);
        } else {
            body.append('id', TR_loteActual.id);
            const res = await fetch(`${TR_URL}/actualizarCabeceraAjax`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            const json = await res.json();
            if (json.ok) {
                TR_huboCambios = true;
                TR_toast('success', 'Lote actualizado');
                await TR_cargarLote(TR_loteActual.id);
            } else {
                TR_swalError(json.mensaje || 'Error al actualizar el lote');
            }
        }
    } catch (err) {
        TR_swalError('Error de conexión con el servidor.');
    } finally {
        btn.disabled = false;
    }
}

// ─── Selector de pagos pendientes ───────────────────────────────────────────

let TR_selectorTimer = null;
document.getElementById('tr-selector-buscar').addEventListener('input', function () {
    clearTimeout(TR_selectorTimer);
    TR_selectorTimer = setTimeout(() => TR_cargarSelector(), 350);
});

async function TR_cargarSelector() {
    const body = document.getElementById('tr-selector-body');
    body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Cargando…</td></tr>';
    const chkTodos = document.getElementById('tr-sel-todos');
    if (chkTodos) { chkTodos.checked = false; chkTodos.indeterminate = false; }
    // Se usa el tipo elegido en el formulario (sirve tanto al crear como al editar un borrador).
    const tipo = document.getElementById('tr-f-tipo').value || 'AMBOS';
    const b = document.getElementById('tr-selector-buscar').value;
    try {
        const res = await fetch(`${TR_URL}/getPagosDisponiblesAjax?tipo=${encodeURIComponent(tipo)}&b=${encodeURIComponent(b)}`);
        const json = await res.json();
        if (!json.ok || !json.data.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay pagos pendientes de transferencia disponibles.</td></tr>';
            return;
        }
        body.innerHTML = json.data.map(p => {
            const sinCuenta = !p.id_banco || !p.numero_cuenta;
            return `<tr class="${sinCuenta ? 'table-danger' : 'tr-sel-fila'}" style="${sinCuenta ? '' : 'cursor:pointer;'}" title="${sinCuenta ? 'Sin banco/cuenta registrada' : ''}" onclick="TR_toggleFila(event, this)">
                <td><input type="checkbox" class="form-check-input tr-sel-pago" value="${p.id_egreso_pago}" ${sinCuenta ? 'disabled' : ''}></td>
                <td>${TR_esc(p.beneficiario)}</td>
                <td>${sinCuenta ? '<span class="text-danger">Sin banco</span>' : TR_esc(p.banco_nombre)}</td>
                <td>${sinCuenta ? '-' : TR_esc(p.tipo_cuenta)}</td>
                <td>${sinCuenta ? '-' : TR_esc(p.numero_cuenta)}</td>
                <td class="text-end">$ ${parseFloat(p.monto || 0).toFixed(2)}</td>
            </tr>`;
        }).join('');
    } catch (err) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error de conexión.</td></tr>';
    }
}

/** Permite seleccionar el pago haciendo clic en cualquier parte de la fila (no solo el checkbox). */
function TR_toggleFila(e, tr) {
    const chk = tr.querySelector('.tr-sel-pago');
    if (!chk || chk.disabled) return;
    if (e.target.tagName !== 'INPUT') chk.checked = !chk.checked;
    tr.classList.toggle('table-primary', chk.checked);
    TR_sincronizarCheckTodos();
}

/** Checkbox del encabezado: marca/desmarca todas las filas seleccionables. */
function TR_marcarTodos(chkHeader) {
    document.querySelectorAll('#tr-selector-body .tr-sel-pago:not(:disabled)').forEach(chk => {
        chk.checked = chkHeader.checked;
        chk.closest('tr').classList.toggle('table-primary', chk.checked);
    });
}

/** Refleja en el checkbox del encabezado si están todas/algunas/ninguna marcadas. */
function TR_sincronizarCheckTodos() {
    const chkTodos = document.getElementById('tr-sel-todos');
    if (!chkTodos) return;
    const filas = Array.from(document.querySelectorAll('#tr-selector-body .tr-sel-pago:not(:disabled)'));
    const marcadas = filas.filter(c => c.checked).length;
    chkTodos.checked = filas.length > 0 && marcadas === filas.length;
    chkTodos.indeterminate = marcadas > 0 && marcadas < filas.length;
}

async function TR_agregarSeleccionados() {
    if (!TR_loteActual) return;
    const ids = Array.from(document.querySelectorAll('.tr-sel-pago:checked')).map(el => el.value);
    if (!ids.length) { TR_toast('warning', 'Seleccione al menos un pago.'); return; }

    const body = new URLSearchParams();
    body.append('id_lote', TR_loteActual.id);
    ids.forEach(id => body.append('ids_egreso_pago[]', id));

    try {
        const res = await fetch(`${TR_URL}/agregarLineasAjax`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        const json = await res.json();
        if (json.ok) {
            TR_huboCambios = true;
            TR_toast('success', 'Pagos agregados al lote');
            await TR_cargarLote(TR_loteActual.id);
        } else {
            TR_swalError(json.mensaje);
        }
    } catch (err) {
        TR_swalError('Error de conexión.');
    }
}

async function TR_quitarLinea(idDetalle) {
    if (!TR_loteActual) return;
    const ok = await TR_confirmar('¿Quitar este pago?', 'Se quitará del lote y quedará disponible nuevamente.', 'Sí, quitar');
    if (!ok) return;
    await TR_accion(`${TR_URL}/quitarLineaAjax`, `id_lote=${TR_loteActual.id}&id_detalle=${idDetalle}`, true);
}

// ─── Acciones (aprobar, rechazar, generar, confirmar, anular, eliminar) ─────

async function TR_accion(url, body, recargarDetalle = false) {
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
        const json = await res.json();
        if (json.ok) {
            TR_huboCambios = true;
            if (recargarDetalle && TR_loteActual) {
                TR_toast('success', json.mensaje || 'Listo');
                await TR_cargarLote(TR_loteActual.id);
            } else {
                await Swal.fire({ icon: 'success', title: 'Listo', text: json.mensaje || 'Operación realizada.', timer: 1800, showConfirmButton: false, target: TR_swalTarget() });
                window.location.reload();
            }
        } else {
            TR_swalError(json.mensaje);
        }
    } catch (err) {
        TR_swalError('Error de conexión.');
    }
}

async function TR_enviarAprobacion() {
    if (!TR_loteActual) return;
    const ok = TR_REQUIERE_APROBACION
        ? await TR_confirmar('¿Enviar a aprobación?', 'El lote pasará a revisión antes de generar el archivo bancario.', 'Sí, enviar')
        : await TR_confirmar('¿Aprobar y continuar?', 'La empresa no exige aprobación, así que el lote quedará aprobado de inmediato y listo para generar el archivo.', 'Sí, continuar');
    if (!ok) return;
    await TR_accion(`${TR_URL}/enviarAprobacionAjax`, `id=${TR_loteActual.id}`, true);
}
async function TR_aprobar() {
    if (!TR_loteActual) return;
    const ok = await TR_confirmar('¿Aprobar este lote?', 'Podrá generarse el archivo bancario a continuación.', 'Sí, aprobar');
    if (!ok) return;
    await TR_accion(`${TR_URL}/aprobarAjax`, `id=${TR_loteActual.id}`, true);
}
async function TR_rechazar() {
    if (!TR_loteActual) return;
    const motivo = await TR_pedirMotivo('Rechazar lote de pago bancario', 'Motivo del rechazo');
    if (!motivo) return;
    await TR_accion(`${TR_URL}/rechazarAjax`, `id=${TR_loteActual.id}&motivo=${encodeURIComponent(motivo)}`, true);
}
async function TR_generarArchivo() {
    if (!TR_loteActual) return;
    await TR_accion(`${TR_URL}/generarArchivoAjax`, `id=${TR_loteActual.id}`, true);
}
async function TR_confirmarEnvio() {
    if (!TR_loteActual) return;
    const ok = await TR_confirmar(
        '¿Confirmar envío al banco?',
        'Indique que el archivo ya fue subido al banco. Los pagos incluidos ya no se podrán volver a transferir.',
        'Sí, confirmar'
    );
    if (!ok) return;
    await TR_accion(`${TR_URL}/confirmarEnvioAjax`, `id=${TR_loteActual.id}`, true);
}
async function TR_anular() {
    if (!TR_loteActual) return;
    const motivo = await TR_pedirMotivo('Anular lote de pago bancario', 'Motivo de la anulación');
    if (!motivo) return;
    await TR_accion(`${TR_URL}/anularAjax`, `id=${TR_loteActual.id}&motivo=${encodeURIComponent(motivo)}`, true);
}
async function TR_eliminar() {
    if (!TR_loteActual) return;
    const ok = await TR_confirmar('¿Eliminar este lote?', 'Esta acción no se puede deshacer.', 'Sí, eliminar');
    if (!ok) return;
    await TR_accion(`${TR_URL}/eliminarAjax`, `id=${TR_loteActual.id}`);
}
