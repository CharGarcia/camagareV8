<div class="modal fade" id="modalCita" data-bs-backdrop="static" tabindex="-1" style="z-index:1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="frmCita">
                <input type="hidden" name="id" id="cit-id">

                <div class="modal-header bg-light py-3 border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>
                        <span id="modalCitaTitulo">Nueva Cita</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">

                        <!-- Cliente -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Cliente <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-agenda', 'cit-cliente', 'cliente') ?></label>
                            <select id="cit-cliente" name="id_cliente" class="form-select form-select-sm">
                                <option value="">— Sin cliente asignado —</option>
                            </select>
                            <div class="form-text" style="font-size:.7rem;">Busca por nombre, cédula o RUC.</div>
                        </div>

                        <!-- Tipo de cita -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tipo de Cita <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-agenda', 'cit-tipo', 'id_tipo_cita') ?></label>
                            <select name="id_tipo_cita" id="cit-tipo" class="form-select form-select-sm" onchange="citaTipoCambiado(this)">
                                <option value="">— Sin tipo —</option>
                            </select>
                        </div>

                        <!-- Recurso -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Recurso <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-agenda', 'cit-recurso', 'id_recurso') ?></label>
                            <select name="id_recurso" id="cit-recurso" class="form-select form-select-sm">
                                <option value="">— Sin recurso —</option>
                            </select>
                        </div>

                        <!-- Título -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Título / Motivo</label>
                            <input type="text" name="titulo" id="cit-titulo" class="form-control form-control-sm"
                                   placeholder="Ej: Consulta de seguimiento..." maxlength="200" autocomplete="off">
                        </div>

                        <!-- Fecha inicio -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Fecha y hora inicio <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_inicio" id="cit-inicio"
                                   class="form-control form-control-sm" required onchange="citaAutoFin()">
                        </div>

                        <!-- Fecha fin -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Fecha y hora fin <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_fin" id="cit-fin"
                                   class="form-control form-control-sm" required>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estado <?= \App\Helpers\PreferenciasHelper::renderEstrellaFavorito('modulos/citas-agenda', 'cit-estado', 'estado') ?></label>
                            <select name="estado" id="cit-estado" class="form-select form-select-sm">
                                <option value="pendiente">Pendiente</option>
                                <option value="confirmada">Confirmada</option>
                                <option value="en_curso">En curso</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                                <option value="no_asistio">No asistió</option>
                            </select>
                        </div>

                        <!-- Notas -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Notas</label>
                            <textarea name="notas" id="cit-notas" class="form-control form-control-sm"
                                      rows="3" maxlength="2000" placeholder="Observaciones internas..."></textarea>
                        </div>

                        <!-- Info estado (solo edición) -->
                        <div class="col-12 d-none" id="sec-cambiar-estado">
                            <label class="form-label small fw-bold">Cambio rápido de estado</label>
                            <div class="d-flex flex-wrap gap-2" id="btns-estado"></div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button type="button" class="btn btn-outline-danger btn-sm d-none"
                                id="btnEliminarCita" onclick="eliminarCita()">
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
let _modalCita     = null;
let _tomCliente    = null;
let _citaDuracion  = 30;   // minutos del tipo seleccionado
let _catalogoTipos = [];   // [{id, nombre, duracion_minutos, recursos_ids:[]}]
let _todosRecursos = [];   // [{id, nombre, tipo}] — lista completa

const ESTADO_CFG = {
    pendiente:  { label: 'Pendiente',  cls: 'warning' },
    confirmada: { label: 'Confirmada', cls: 'primary' },
    en_curso:   { label: 'En curso',   cls: 'info'    },
    completada: { label: 'Completada', cls: 'success'  },
    cancelada:  { label: 'Cancelada',  cls: 'secondary'},
    no_asistio: { label: 'No asistió', cls: 'danger'   },
};

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('modalCita');
    if (el) _modalCita = new bootstrap.Modal(el);

    // TomSelect para cliente
    _tomCliente = new TomSelect('#cit-cliente', {
        valueField: 'id',
        labelField: 'label',
        searchField: ['label'],
        placeholder: 'Buscar cliente...',
        load(q, cb) {
            if (!q || q.length < 2) return cb();
            fetch(`${URL_AGENDA}/buscar-clientes?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(rows => cb(rows.map(r => ({
                    id: r.id,
                    label: `${r.nombre} (${r.identificacion})`
                }))));
        },
        onChange(val) {},
        create: false,
        plugins: ['clear_button'],
    });

    // Cargar catálogos en selects del modal
    fetch(`${URL_AGENDA}/catalogos-ajax`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) return;
            _catalogoTipos = res.data.tipos   || [];
            _todosRecursos = res.data.recursos || [];

            const selTipo = document.getElementById('cit-tipo');
            _catalogoTipos.forEach(t => {
                const o = new Option(`${t.nombre} (${t.duracion_minutos} min)`, t.id);
                o.dataset.duracion    = t.duracion_minutos;
                o.dataset.recursosIds = JSON.stringify(t.recursos_ids || []);
                selTipo.add(o);
            });
            // Poblar recursos inicial (todos)
            filtrarRecursosPorTipo(null);
        });

    document.getElementById('frmCita').addEventListener('submit', e => {
        e.preventDefault();

        // Validar que la fecha/hora de inicio no sea anterior al momento actual
        const inicioVal = document.getElementById('cit-inicio').value;
        if (inicioVal) {
            const inicioDate = new Date(inicioVal);
            const ahora = new Date();
            ahora.setSeconds(0, 0); // tolerancia al minuto actual
            if (inicioDate < ahora) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Fecha no válida',
                    text: 'La fecha y hora de inicio no puede ser anterior a la fecha y hora actual.',
                });
                return;
            }
        }

        fetch(`${URL_AGENDA}/guardar`, { method: 'POST', body: new FormData(e.target) })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: res.mensaje, timer: 1500, showConfirmButton: false })
                        .then(() => { _modalCita.hide(); citaRecargaVista(); });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
    });
});

function filtrarRecursosPorTipo(idTipo) {
    const sel     = document.getElementById('cit-recurso');
    const valPrev = sel.value;

    // Limpiar opciones excepto la primera (— Sin recurso —)
    while (sel.options.length > 1) sel.remove(1);

    let permitidos = _todosRecursos;
    if (idTipo) {
        const tipo = _catalogoTipos.find(t => String(t.id) === String(idTipo));
        if (tipo && tipo.recursos_ids && tipo.recursos_ids.length > 0) {
            permitidos = _todosRecursos.filter(r => tipo.recursos_ids.includes(parseInt(r.id)));
        }
    }

    permitidos.forEach(r => {
        sel.add(new Option(`${r.nombre} (${r.tipo})`, r.id));
    });

    // Restaurar selección previa si sigue disponible
    sel.value = valPrev;
    if (!sel.value) sel.value = '';
}

function citaTipoCambiado(sel) {
    const opt = sel.options[sel.selectedIndex];
    _citaDuracion = opt ? (parseInt(opt.dataset.duracion) || 30) : 30;
    filtrarRecursosPorTipo(sel.value || null);
    citaAutoFin();
}

function citaAutoFin() {
    const inicio = document.getElementById('cit-inicio').value;
    if (!inicio) return;
    const d = new Date(inicio);
    d.setMinutes(d.getMinutes() + _citaDuracion);
    // formato datetime-local: YYYY-MM-DDTHH:MM
    const pad = n => String(n).padStart(2, '0');
    const finStr = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    document.getElementById('cit-fin').value = finStr;
}

function abrirModalCita(data = null, fechaInicio = null) {
    document.getElementById('frmCita').reset();
    document.getElementById('cit-id').value = '';
    document.getElementById('btnEliminarCita').classList.add('d-none');
    document.getElementById('sec-cambiar-estado').classList.add('d-none');
    _citaDuracion = 30;

    // Establecer el mínimo del campo fecha_inicio al momento actual (minuto exacto)
    const ahora = new Date();
    ahora.setSeconds(0, 0);
    const pad = n => String(n).padStart(2, '0');
    const minVal = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())}T${pad(ahora.getHours())}:${pad(ahora.getMinutes())}`;
    document.getElementById('cit-inicio').min = minVal;

    if (_tomCliente) {
        _tomCliente.clear(true);
        _tomCliente.clearOptions();
    }

    if (data) {
        document.getElementById('modalCitaTitulo').innerText = 'Editar Cita';
        document.getElementById('cit-id').value    = data.id;
        document.getElementById('cit-tipo').value  = data.id_tipo_cita  ?? '';
        document.getElementById('cit-recurso').value = data.id_recurso  ?? '';
        document.getElementById('cit-titulo').value  = data.titulo      ?? '';
        document.getElementById('cit-inicio').value  = data.fecha_inicio ? data.fecha_inicio.substring(0,16) : '';
        document.getElementById('cit-fin').value     = data.fecha_fin    ? data.fecha_fin.substring(0,16)    : '';
        document.getElementById('cit-estado').value  = data.estado       ?? 'pendiente';
        document.getElementById('cit-notas').value   = data.notas        ?? '';
        document.getElementById('btnEliminarCita').classList.remove('d-none');

        // Botones de cambio rápido de estado
        const secEstado = document.getElementById('sec-cambiar-estado');
        const btns      = document.getElementById('btns-estado');
        btns.innerHTML  = '';
        secEstado.classList.remove('d-none');
        Object.entries(ESTADO_CFG).forEach(([est, cfg]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-${cfg.cls} btn-sm`;
            btn.style.opacity = (est === data.estado) ? '1' : '0.4';
            btn.innerHTML = cfg.label;
            btn.onclick = () => cambiarEstadoRapido(data.id, est);
            btns.appendChild(btn);
        });

        // Cargar cliente en TomSelect
        if (data.id_cliente && data.nombre_cliente) {
            const lbl = data.nombre_cliente + (data.cliente_identificacion ? ` (${data.cliente_identificacion})` : '');
            _tomCliente.addOption({ id: data.id_cliente, label: lbl });
            _tomCliente.setValue(data.id_cliente, true);
        }

        // Recuperar duración del tipo seleccionado
        const selTipo = document.getElementById('cit-tipo');
        const opt = selTipo.options[selTipo.selectedIndex];
        if (opt) _citaDuracion = parseInt(opt.dataset.duracion) || 30;

    } else {
        document.getElementById('modalCitaTitulo').innerText = 'Nueva Cita';
        // Si viene fecha preseleccionada (click en slot del calendario)
        if (fechaInicio) {
            document.getElementById('cit-inicio').value = fechaInicio.substring(0, 16);
            citaAutoFin();
        }
    }

    if (_modalCita) _modalCita.show();

    // Aplicar favoritos solo al crear nuevo (no al editar, para no sobreescribir datos)
    if (!data && typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalCita');
    }
}

function eliminarCita() {
    const id = document.getElementById('cit-id').value;
    if (!id) return;
    Swal.fire({
        title: '¿Eliminar esta cita?',
        text: 'Esta acción es irreversible.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const fd = new FormData(); fd.append('id', id);
        fetch(`${URL_AGENDA}/eliminar`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    Swal.fire({ icon: 'success', title: 'Eliminada', text: res.mensaje, timer: 1500, showConfirmButton: false })
                        .then(() => { _modalCita.hide(); citaRecargaVista(); });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
    });
}

function cambiarEstadoRapido(id, estado) {
    const fd = new FormData(); fd.append('id', id); fd.append('estado', estado);
    fetch(`${URL_AGENDA}/cambiar-estado`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                document.getElementById('cit-estado').value = estado;
                // actualizar opacidad botones
                document.querySelectorAll('#btns-estado button').forEach(btn => {
                    btn.style.opacity = (btn.innerText.trim().toLowerCase().replace(' ', '_') === estado ||
                        Object.entries(ESTADO_CFG).find(([k,v]) => v.label === btn.innerText.trim())?.[0] === estado)
                        ? '1' : '0.4';
                });
                citaRecargaVista();
            } else {
                Swal.fire('Error', res.mensaje, 'error');
            }
        });
}

function citaRecargaVista() {
    // Refresca calendario o tabla según la vista activa
    if (typeof _calendarioCitas !== 'undefined' && _calendarioCitas) {
        _calendarioCitas.refetchEvents();
    }
    if (typeof citasListaCargar === 'function') {
        citasListaCargar();
    }
}
</script>
