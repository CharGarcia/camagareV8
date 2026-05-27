<div class="modal fade" id="modalTipoCita" data-bs-backdrop="static" tabindex="-1" style="z-index:1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmTipoCita">
                <input type="hidden" name="id" id="tc-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-tags text-primary me-2"></i>
                        <span id="modalTipoTitulo">Nuevo Tipo de Cita</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="tc-nombre" class="form-control form-control-sm" placeholder="Ej: Consulta general, Revisión, etc." required maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Color en calendario</label>
                            <div class="input-group input-group-sm">
                                <input type="color" name="color" id="tc-color" class="form-control form-control-color form-control-sm border" value="#0d6efd" style="max-width:50px; padding:2px;">
                                <input type="text" id="tc-color-hex" class="form-control form-control-sm font-monospace" value="#0d6efd" maxlength="7" style="max-width:90px;">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Duración (minutos) <span class="text-danger">*</span></label>
                            <input type="number" name="duracion_minutos" id="tc-duracion" class="form-control form-control-sm" value="30" min="5" max="480" step="5" required>
                            <div class="form-text" style="font-size:.7rem;">Entre 5 y 480 min.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Precio (USD)</label>
                            <input type="number" name="precio" id="tc-precio" class="form-control form-control-sm" value="0.00" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-configuracion', 'tc-status', 'status') ?></label>
                            <select name="status" id="tc-status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Tipo de Pago</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_pago" id="tp-sin" value="sin_pago" checked onchange="togglePago(this.value)">
                                    <label class="form-check-label small" for="tp-sin">Sin pago</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_pago" id="tp-total" value="total" onchange="togglePago(this.value)">
                                    <label class="form-check-label small" for="tp-total">Pago total al reservar</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_pago" id="tp-anticipo" value="anticipo" onchange="togglePago(this.value)">
                                    <label class="form-check-label small" for="tp-anticipo">Anticipo parcial</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 d-none" id="sec-anticipo">
                            <label class="form-label small fw-bold">Porcentaje de anticipo (%) <span class="text-danger">*</span></label>
                            <input type="number" name="anticipo_porcentaje" id="tc-anticipo" class="form-control form-control-sm" min="1" max="99" step="1" placeholder="Ej: 30">
                        </div>

                        <!-- Recursos asociados -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Recursos asociados</label>
                            <div id="tc-recursos-wrap" class="d-flex flex-wrap gap-2 p-2 border rounded-2 bg-light" style="min-height:38px;">
                                <span class="text-muted small fst-italic" id="tc-recursos-vacio">Sin recursos activos configurados.</span>
                            </div>
                            <div class="form-text" style="font-size:.7rem;">
                                Marca los recursos disponibles para este tipo de cita. Si no marcas ninguno, estará disponible en todos.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Descripción</label>
                            <textarea name="descripcion" id="tc-descripcion" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Descripción opcional que verán los clientes al reservar..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarTipo" onclick="eliminarTipo()">
                            <i class="bi bi-trash me-1"></i> Eliminar
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-circle me-1"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let _modalTipo   = null;
let _recursosAll = []; // [{id, nombre, tipo}] cargados una sola vez

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('modalTipoCita');
    if (el) _modalTipo = new bootstrap.Modal(el);

    // Cargar recursos activos una sola vez para los checkboxes
    fetch(`${URL_CITAS_CFG}/recursos-activos-ajax`)
        .then(r => r.json())
        .then(res => { if (res.ok) _recursosAll = res.data || []; })
        .catch(() => {});

    // Sincronizar color picker <-> texto hex
    document.getElementById('tc-color').addEventListener('input', e => {
        document.getElementById('tc-color-hex').value = e.target.value;
    });
    document.getElementById('tc-color-hex').addEventListener('input', e => {
        const val = e.target.value;
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            document.getElementById('tc-color').value = val;
        }
    });

    document.getElementById('frmTipoCita').addEventListener('submit', e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        // Aseguramos que el hex del input texto prevalezca
        fd.set('color', document.getElementById('tc-color-hex').value);

        fetch(`${URL_CITAS_CFG}/guardar-tipo`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: res.mensaje, timer: 1500, showConfirmButton: false })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
    });
});

function togglePago(val) {
    document.getElementById('sec-anticipo').classList.toggle('d-none', val !== 'anticipo');
    document.getElementById('tc-anticipo').required = (val === 'anticipo');
}

function renderRecursosCheckboxes(seleccionados = []) {
    const wrap  = document.getElementById('tc-recursos-wrap');
    const vacio = document.getElementById('tc-recursos-vacio');

    // Limpiar checkboxes previos (no el span vacío)
    wrap.querySelectorAll('label.rec-check').forEach(l => l.remove());

    if (_recursosAll.length === 0) {
        vacio.style.display = '';
        return;
    }
    vacio.style.display = 'none';

    _recursosAll.forEach(r => {
        const chk  = document.createElement('input');
        chk.type   = 'checkbox';
        chk.name   = 'id_recursos[]';
        chk.value  = r.id;
        chk.id     = `rec-chk-${r.id}`;
        chk.className = 'form-check-input me-1';
        chk.checked = seleccionados.includes(parseInt(r.id));

        const lbl  = document.createElement('label');
        lbl.htmlFor   = `rec-chk-${r.id}`;
        lbl.className = 'form-check-label rec-check d-flex align-items-center gap-1 border rounded-2 px-2 py-1 small bg-white';
        lbl.style.cursor = 'pointer';
        lbl.appendChild(chk);
        lbl.append(r.nombre);

        wrap.appendChild(lbl);
    });
}

function abrirModalTipo(data = null) {
    const frm = document.getElementById('frmTipoCita');
    frm.reset();
    document.getElementById('tc-id').value = '';
    document.getElementById('tc-color').value = '#0d6efd';
    document.getElementById('tc-color-hex').value = '#0d6efd';
    document.getElementById('tc-duracion').value = 30;
    document.getElementById('tc-precio').value = '0.00';
    document.getElementById('tc-status').value = '1';
    document.getElementById('sec-anticipo').classList.add('d-none');
    document.getElementById('btnEliminarTipo').classList.add('d-none');
    document.querySelector('input[name="tipo_pago"][value="sin_pago"]').checked = true;

    if (data) {
        document.getElementById('modalTipoTitulo').innerText = 'Editar Tipo de Cita';
        document.getElementById('tc-id').value       = data.id;
        document.getElementById('tc-nombre').value   = data.nombre ?? '';
        document.getElementById('tc-descripcion').value = data.descripcion ?? '';
        document.getElementById('tc-duracion').value = data.duracion_minutos ?? 30;
        document.getElementById('tc-precio').value   = data.precio ?? '0.00';
        document.getElementById('tc-status').value   = data.status ?? '1';
        const color = data.color ?? '#0d6efd';
        document.getElementById('tc-color').value    = color;
        document.getElementById('tc-color-hex').value = color;
        const tp = data.tipo_pago ?? 'sin_pago';
        const radio = document.querySelector(`input[name="tipo_pago"][value="${tp}"]`);
        if (radio) radio.checked = true;
        togglePago(tp);
        if (tp === 'anticipo' && data.anticipo_porcentaje) {
            document.getElementById('tc-anticipo').value = data.anticipo_porcentaje;
        }
        document.getElementById('btnEliminarTipo').classList.remove('d-none');
        renderRecursosCheckboxes(data.recursos_ids || []);
    } else {
        document.getElementById('modalTipoTitulo').innerText = 'Nuevo Tipo de Cita';
        renderRecursosCheckboxes([]);
    }

    if (_modalTipo) _modalTipo.show();

    // Aplicar favoritos solo al crear nuevo
    if (!data && typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalTipoCita');
    }
}

function eliminarTipo() {
    const id = document.getElementById('tc-id').value;
    if (!id) return;
    Swal.fire({
        title: '¿Eliminar tipo de cita?',
        text: 'Esta acción es irreversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData();
        fd.append('id', id);
        fetch(`${URL_CITAS_CFG}/eliminar-tipo`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                else Swal.fire('Error', res.mensaje, 'error');
            });
    });
}
</script>
