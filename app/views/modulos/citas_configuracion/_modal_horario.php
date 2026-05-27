<?php
$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>

<div class="modal fade" id="modalHorario" data-bs-backdrop="static" tabindex="-1" style="z-index:1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmHorario">
                <input type="hidden" name="id" id="hor-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clock text-primary me-2"></i>
                        <span id="modalHorarioTitulo">Nuevo Bloque Horario</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Recurso <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-configuracion', 'hor-recurso', 'id_recurso') ?></label>
                            <select name="id_recurso" id="hor-recurso" class="form-select form-select-sm">
                                <option value="">— General (aplica a toda la empresa) —</option>
                                <?php foreach ($recursosActivos as $rec): ?>
                                    <option value="<?= $rec['id'] ?>">
                                        <?= htmlspecialchars($rec['nombre']) ?>
                                        (<?= htmlspecialchars($rec['tipo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" style="font-size:.7rem;">Deja en blanco para que aplique a todos los recursos.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Día de la semana <span class="text-danger">*</span> <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-configuracion', 'hor-dia', 'dia_semana') ?></label>
                            <select name="dia_semana" id="hor-dia" class="form-select form-select-sm" required>
                                <?php foreach ($diasSemana as $num => $nombre): ?>
                                    <option value="<?= $num ?>"><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Hora inicio <span class="text-danger">*</span></label>
                            <input type="time" name="hora_inicio" id="hor-inicio" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Hora fin <span class="text-danger">*</span></label>
                            <input type="time" name="hora_fin" id="hor-fin" class="form-control form-control-sm" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarHorario" onclick="eliminarHorario()">
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
let _modalHorario = null;

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('modalHorario');
    if (el) _modalHorario = new bootstrap.Modal(el);

    document.getElementById('frmHorario').addEventListener('submit', e => {
        e.preventDefault();
        fetch(`${URL_CITAS_CFG}/guardar-horario`, { method: 'POST', body: new FormData(e.target) })
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

function abrirModalHorario(data = null) {
    document.getElementById('frmHorario').reset();
    document.getElementById('hor-id').value = '';
    document.getElementById('btnEliminarHorario').classList.add('d-none');

    if (data) {
        document.getElementById('modalHorarioTitulo').innerText = 'Editar Bloque Horario';
        document.getElementById('hor-id').value      = data.id;
        document.getElementById('hor-recurso').value = data.id_recurso ?? '';
        document.getElementById('hor-dia').value     = data.dia_semana ?? '1';
        document.getElementById('hor-inicio').value  = data.hora_inicio ? data.hora_inicio.substring(0, 5) : '';
        document.getElementById('hor-fin').value     = data.hora_fin ? data.hora_fin.substring(0, 5) : '';
        document.getElementById('btnEliminarHorario').classList.remove('d-none');
    } else {
        document.getElementById('modalHorarioTitulo').innerText = 'Nuevo Bloque Horario';
    }

    if (_modalHorario) _modalHorario.show();

    // Aplicar favoritos solo al crear nuevo
    if (!data && typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalHorario');
    }
}

function eliminarHorario() {
    const id = document.getElementById('hor-id').value;
    if (!id) return;
    Swal.fire({
        title: '¿Eliminar bloque horario?',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('id', id);
        fetch(`${URL_CITAS_CFG}/eliminar-horario`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1500, showConfirmButton: false }).then(() => location.reload());
                else Swal.fire('Error', res.mensaje, 'error');
            });
    });
}
</script>
