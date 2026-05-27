<div class="modal fade" id="modalRecurso" data-bs-backdrop="static" tabindex="-1" style="z-index:1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmRecurso">
                <input type="hidden" name="id" id="rec-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-gear text-primary me-2"></i>
                        <span id="modalRecursoTitulo">Nuevo Recurso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="rec-nombre" class="form-control form-control-sm" placeholder="Ej: Dr. García, Sala 1, Equipo A..." required maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-configuracion', 'rec-tipo', 'tipo') ?></label>
                            <select name="tipo" id="rec-tipo" class="form-select form-select-sm" required>
                                <option value="persona">Persona</option>
                                <option value="sala">Sala</option>
                                <option value="equipo">Equipo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Descripción</label>
                            <textarea name="descripcion" id="rec-descripcion" class="form-control form-control-sm" rows="2" maxlength="300" placeholder="Descripción opcional..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-configuracion', 'rec-status', 'status') ?></label>
                            <select name="status" id="rec-status" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarRecurso" onclick="eliminarRecurso()">
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
let _modalRecurso = null;

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('modalRecurso');
    if (el) _modalRecurso = new bootstrap.Modal(el);

    document.getElementById('frmRecurso').addEventListener('submit', e => {
        e.preventDefault();
        fetch(`${URL_CITAS_CFG}/guardar-recurso`, { method: 'POST', body: new FormData(e.target) })
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

function abrirModalRecurso(data = null) {
    document.getElementById('frmRecurso').reset();
    document.getElementById('rec-id').value = '';
    document.getElementById('rec-status').value = '1';
    document.getElementById('btnEliminarRecurso').classList.add('d-none');

    if (data) {
        document.getElementById('modalRecursoTitulo').innerText = 'Editar Recurso';
        document.getElementById('rec-id').value          = data.id;
        document.getElementById('rec-nombre').value      = data.nombre ?? '';
        document.getElementById('rec-tipo').value        = data.tipo ?? 'persona';
        document.getElementById('rec-descripcion').value = data.descripcion ?? '';
        document.getElementById('rec-status').value      = data.status ?? '1';
        document.getElementById('btnEliminarRecurso').classList.remove('d-none');
    } else {
        document.getElementById('modalRecursoTitulo').innerText = 'Nuevo Recurso';
    }

    if (_modalRecurso) _modalRecurso.show();

    // Aplicar favoritos solo al crear nuevo
    if (!data && typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalRecurso');
    }
}

function eliminarRecurso() {
    const id = document.getElementById('rec-id').value;
    if (!id) return;
    Swal.fire({
        title: '¿Eliminar recurso?',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('id', id);
        fetch(`${URL_CITAS_CFG}/eliminar-recurso`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                else Swal.fire('Error', res.mensaje, 'error');
            });
    });
}
</script>
