<div class="modal fade" id="modalPago" data-bs-backdrop="static" tabindex="-1" style="z-index:1070;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <form id="frmPago">
                <input type="hidden" name="id" id="pag-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cash-coin text-primary me-2"></i>
                        <span id="modalPagoTitulo">Nuevo Pago</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">

                        <!-- Selector de cita -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Cita <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-pagos', 'pag-cita-sel', 'id_cita') ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="pag-cita-buscar" class="form-control form-control-sm"
                                       placeholder="Buscar por cliente, tipo de cita o ID..." autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarCitaSel()">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <input type="hidden" name="id_cita" id="pag-cita-id" required>
                            <div id="pag-cita-resultados" class="list-group position-absolute w-100 shadow-sm" style="z-index:2000;max-height:220px;overflow-y:auto;display:none;"></div>
                            <!-- Info de la cita seleccionada -->
                            <div id="pag-cita-info" class="mt-2 d-none">
                                <div class="p-2 bg-light border rounded-2 small">
                                    <div class="d-flex flex-wrap gap-3">
                                        <span><i class="bi bi-calendar3 me-1 text-primary"></i><span id="pag-cita-fecha">—</span></span>
                                        <span><i class="bi bi-person me-1 text-primary"></i><span id="pag-cita-cliente">—</span></span>
                                        <span><i class="bi bi-tags me-1 text-primary"></i><span id="pag-cita-tipo">—</span></span>
                                        <span><i class="bi bi-currency-dollar me-1 text-success"></i>Precio base: <strong id="pag-cita-precio">—</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo de pago + monto -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de pago <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-pagos', 'pag-tipo-pago', 'tipo_pago') ?></label>
                            <select name="tipo_pago" id="pag-tipo-pago" class="form-select form-select-sm" required onchange="recalcularMonto()">
                                <option value="total">Pago total</option>
                                <option value="anticipo">Anticipo parcial</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monto <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" id="pag-monto" class="form-control form-control-sm"
                                       step="0.01" min="0.01" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-pagos', 'pag-estado', 'estado') ?></label>
                            <select name="estado" id="pag-estado" class="form-select form-select-sm">
                                <option value="pendiente">Pendiente</option>
                                <option value="completado">Completado</option>
                                <option value="fallido">Fallido</option>
                                <option value="reembolsado">Reembolsado</option>
                            </select>
                        </div>

                        <!-- Método de pago + referencia -->
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Método de pago <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-pagos', 'pag-gateway', 'gateway') ?></label>
                            <select name="gateway" id="pag-gateway" class="form-select form-select-sm">
                                <option value="sitio">En sitio</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="stripe">Stripe</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Referencia / Comprobante</label>
                            <input type="text" name="referencia_externa" id="pag-referencia" class="form-control form-control-sm"
                                   placeholder="Nro. de transacción, recibo, etc." maxlength="200" autocomplete="off">
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarPago" onclick="eliminarPago()">
                            <i class="bi bi-trash me-1"></i> Eliminar
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4">
                            <i class="bi bi-check-circle me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let _modalPago  = null;
let _citaSel    = null;   // Objeto cita actualmente seleccionada en el modal
let _buscTimer  = null;

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('modalPago');
    if (el) _modalPago = new bootstrap.Modal(el);

    // Autocomplete de citas
    const inputBuscar = document.getElementById('pag-cita-buscar');
    inputBuscar.addEventListener('input', () => {
        clearTimeout(_buscTimer);
        const q = inputBuscar.value.trim();
        if (q.length < 2) { ocultarResultados(); return; }
        _buscTimer = setTimeout(() => buscarCitasModal(q), 300);
    });

    // Cerrar resultados al perder foco
    document.addEventListener('click', e => {
        if (!e.target.closest('#pag-cita-buscar') && !e.target.closest('#pag-cita-resultados')) {
            ocultarResultados();
        }
    });

    // Submit
    document.getElementById('frmPago').addEventListener('submit', e => {
        e.preventDefault();
        const idCita = document.getElementById('pag-cita-id').value;
        if (!idCita) {
            Swal.fire('Atención', 'Debe seleccionar una cita.', 'warning');
            return;
        }
        const btn = e.target.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...'; }

        fetch(`${URL_PAGOS}/guardar`, { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json())
            .then(res => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar'; }
                if (res.ok) {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: res.mensaje, timer: 1500, showConfirmButton: false })
                        .then(() => cargarPagos());
                    bootstrap.Modal.getInstance(document.getElementById('modalPago'))?.hide();
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            })
            .catch(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar'; }
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            });
    });
});

// ── Abrir modal ──────────────────────────────────────────────────────────────

function abrirModalPago(data = null) {
    document.getElementById('frmPago').reset();
    document.getElementById('pag-id').value        = '';
    document.getElementById('pag-cita-id').value   = '';
    document.getElementById('pag-cita-buscar').value = '';
    document.getElementById('pag-cita-info').classList.add('d-none');
    document.getElementById('btnEliminarPago').classList.add('d-none');
    _citaSel = null;
    ocultarResultados();

    // Defaults
    document.getElementById('pag-tipo-pago').value = 'total';
    document.getElementById('pag-estado').value    = 'pendiente';
    document.getElementById('pag-gateway').value   = 'sitio';
    document.getElementById('pag-monto').value     = '0.00';

    if (data) {
        document.getElementById('modalPagoTitulo').innerText = 'Editar Pago';
        document.getElementById('pag-id').value              = data.id;
        document.getElementById('pag-tipo-pago').value       = data.tipo_pago    ?? 'total';
        document.getElementById('pag-monto').value           = parseFloat(data.monto ?? 0).toFixed(2);
        document.getElementById('pag-estado').value          = data.estado       ?? 'pendiente';
        document.getElementById('pag-gateway').value         = data.gateway      ?? 'sitio';
        document.getElementById('pag-referencia').value      = data.referencia_externa ?? '';
        document.getElementById('pag-cita-id').value         = data.id_cita      ?? '';
        document.getElementById('btnEliminarPago').classList.remove('d-none');

        // Mostrar info de la cita ya asociada
        if (data.id_cita) {
            const lbl = [
                data.fecha_cita ? new Date(data.fecha_cita).toLocaleDateString('es-EC', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—',
                data.nombre_cliente || data.cita_titulo || '',
                data.nombre_tipo || '',
            ].filter(Boolean).join(' | ');
            document.getElementById('pag-cita-buscar').value = lbl;
            mostrarInfoCita(data);
        }
    } else {
        document.getElementById('modalPagoTitulo').innerText = 'Nuevo Pago';
    }

    if (_modalPago) _modalPago.show();

    // Aplicar favoritos solo al crear nuevo
    if (!data && typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalPago');
    }
}

// ── Buscar citas (autocomplete) ───────────────────────────────────────────────

function buscarCitasModal(q) {
    const wrap = document.getElementById('pag-cita-resultados');
    wrap.innerHTML = '<div class="list-group-item text-muted small py-2"><span class="spinner-border spinner-border-sm me-1"></span> Buscando...</div>';
    wrap.style.display = 'block';

    fetch(`${URL_PAGOS}/buscar-citas-ajax?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok || !res.data.length) {
                wrap.innerHTML = '<div class="list-group-item text-muted small py-2">Sin resultados</div>';
                return;
            }
            wrap.innerHTML = '';
            res.data.forEach(c => {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'list-group-item list-group-item-action py-2 px-3 small';
                const cliente = c.nombre_cliente || '(Sin cliente)';
                const tipo    = c.nombre_tipo    || '(Sin tipo)';
                btn.innerHTML = `
                    <div class="fw-semibold">${escHtml(c.fecha_fmt)} — ${escHtml(cliente)}</div>
                    <div class="text-muted">${escHtml(tipo)} · ID #${c.id}</div>
                `;
                btn.onclick = () => seleccionarCita(c);
                wrap.appendChild(btn);
            });
        })
        .catch(() => {
            wrap.innerHTML = '<div class="list-group-item text-danger small py-2">Error al buscar.</div>';
        });
}

function seleccionarCita(c) {
    _citaSel = c;
    document.getElementById('pag-cita-id').value      = c.id;
    document.getElementById('pag-cita-buscar').value  = `${c.fecha_fmt} — ${c.nombre_cliente || c.nombre_tipo || 'ID #' + c.id}`;
    ocultarResultados();
    mostrarInfoCita(c);
    recalcularMonto();
}

function mostrarInfoCita(c) {
    const info = document.getElementById('pag-cita-info');
    info.classList.remove('d-none');
    document.getElementById('pag-cita-fecha').textContent   = c.fecha_fmt || c.fecha_cita || '—';
    document.getElementById('pag-cita-cliente').textContent = c.nombre_cliente || '(Sin cliente)';
    document.getElementById('pag-cita-tipo').textContent    = c.nombre_tipo    || '—';
    const precio = parseFloat(c.precio ?? 0);
    document.getElementById('pag-cita-precio').textContent  = precio > 0 ? '$' + precio.toFixed(2) : 'Sin precio';
}

function limpiarCitaSel() {
    _citaSel = null;
    document.getElementById('pag-cita-id').value     = '';
    document.getElementById('pag-cita-buscar').value = '';
    document.getElementById('pag-cita-info').classList.add('d-none');
    document.getElementById('pag-monto').value       = '0.00';
    ocultarResultados();
}

function ocultarResultados() {
    const wrap = document.getElementById('pag-cita-resultados');
    if (wrap) { wrap.style.display = 'none'; wrap.innerHTML = ''; }
}

// ── Recalcular monto sugerido ─────────────────────────────────────────────────

function recalcularMonto() {
    if (!_citaSel) return;
    const precio   = parseFloat(_citaSel.precio ?? 0);
    const tipoPago = document.getElementById('pag-tipo-pago').value;
    if (precio <= 0) return;

    if (tipoPago === 'anticipo') {
        const pct = parseFloat(_citaSel.anticipo_porcentaje ?? 30);
        document.getElementById('pag-monto').value = ((precio * pct) / 100).toFixed(2);
    } else {
        document.getElementById('pag-monto').value = precio.toFixed(2);
    }
}

// ── Eliminar pago ─────────────────────────────────────────────────────────────

function eliminarPago() {
    const id = document.getElementById('pag-id').value;
    if (!id) return;
    Swal.fire({
        title: '¿Eliminar este pago?',
        text: 'Esta acción es irreversible.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('id', id);
        fetch(`${URL_PAGOS}/eliminar`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1500, showConfirmButton: false })
                        .then(() => cargarPagos());
                    bootstrap.Modal.getInstance(document.getElementById('modalPago'))?.hide();
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
    });
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
