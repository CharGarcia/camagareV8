<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $modulos */

$urlBaseAuto = BASE_URL . '/modulos/automatizaciones';

$vistaConfigAuto = \App\Helpers\PreferenciasHelper::getPreferenciasVista('automatizaciones');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigAuto, 'estiloVistaPestanasAuto');
?>

<!-- Modal Automatización -->
<div class="modal fade" id="modalAutomatizacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-robot text-primary me-2"></i>
                    <span id="auto_tituloModal">Nueva Automatización</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <input type="hidden" id="auto_id">

                <!-- Pestañas -->
                <style>
                    #tabsAutomatizacion .nav-link { padding:6px 9px; font-size:0.8rem; white-space:nowrap; }
                    #tabsAutomatizacion .nav-link i { font-size:0.85rem; }
                </style>
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 flex-nowrap tab-pestaña" id="tabsAutomatizacion" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="auto-tab-general-btn" data-bs-toggle="tab" href="#auto-tab-general" role="tab" title="General">
                                <i class="fas fa-sliders-h me-1"></i> General
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="auto-tab-programacion-btn" data-bs-toggle="tab" href="#auto-tab-programacion" role="tab" title="Programación">
                                <i class="fas fa-clock me-1"></i> Programación
                            </a>
                        </li>
                        <li class="nav-item" role="presentation" id="auto-tab-historial-li" style="display:none;">
                            <a class="nav-link" id="auto-tab-historial-btn" data-bs-toggle="tab" href="#auto-tab-historial" role="tab" title="Historial"
                               onclick="AUTO_cargarHistorial()">
                                <i class="fas fa-history me-1"></i> Historial
                            </a>
                        </li>
                    </ul>
                    <div class="pb-1 flex-shrink-0">
                        <?php
                        $pestanasConfigAuto = [
                            'auto-tab-programacion' => 'Programación',
                            'auto-tab-historial'    => 'Historial',
                        ];
                        echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasConfigAuto, $vistaConfigAuto ?? [], 'automatizaciones');
                        ?>
                    </div>
                </div>
                <div class="border-bottom bg-light mb-0"></div>

                <div class="tab-content border-top px-3 py-3" id="tabsAutomatizacionContent">

                    <!-- ── Pestaña General ──────────────────────────────────── -->
                    <div class="tab-pane fade show active" id="auto-tab-general" role="tabpanel">
                        <div class="row g-3">

                            <div class="col-md-9">
                                <label class="form-label small fw-bold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" id="auto_nombre" class="form-control form-control-sm"
                                       placeholder="Ej: Envío facturas vencidas — Noche" maxlength="150">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Estado</label>
                                <select id="auto_estado" class="form-select form-select-sm">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold">Descripción</label>
                                <textarea id="auto_descripcion" class="form-control form-control-sm" rows="2"
                                          placeholder="Descripción opcional de la tarea..."></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Módulo <span class="text-danger">*</span></label>
                                <select id="auto_modulo" class="form-select form-select-sm" onchange="AUTO_cargarAcciones()">
                                    <option value="">— Seleccione módulo —</option>
                                    <?php foreach ($modulos ?? [] as $m): ?>
                                        <option value="<?= htmlspecialchars($m['key']) ?>">
                                            <?= htmlspecialchars($m['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Acción <span class="text-danger">*</span></label>
                                <select id="auto_accion" class="form-select form-select-sm" onchange="AUTO_cargarParametros()" disabled>
                                    <option value="">— Seleccione acción —</option>
                                </select>
                                <div id="auto_accion_desc" class="form-text text-muted mt-1" style="font-size:0.78rem;"></div>
                            </div>

                            <!-- Parámetros dinámicos -->
                            <div class="col-12" id="auto_contenedor_params" style="display:none;">
                                <label class="form-label small fw-bold">Parámetros de la acción</label>
                                <div id="auto_campos_params" class="row g-2 p-2 border rounded-2 bg-light"></div>
                            </div>

                        </div>
                    </div>

                    <!-- ── Pestaña Programación ─────────────────────────────── -->
                    <div class="tab-pane fade" id="auto-tab-programacion" role="tabpanel">
                        <div class="row g-3">

                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Tipo de frecuencia <span class="text-danger">*</span></label>
                                <select id="auto_frecuencia_tipo" class="form-select form-select-sm" onchange="AUTO_actualizarFrecuencia()">
                                    <option value="diario">Diario (hora fija)</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                    <option value="horas">Cada N horas</option>
                                    <option value="minutos">Cada N minutos</option>
                                    <option value="cron_personalizado">Cron personalizado</option>
                                </select>
                            </div>

                            <div class="col-md-4" id="auto_col_frecuencia_valor">
                                <label class="form-label small fw-bold" id="auto_label_frecuencia_valor">Hora (HH:MM)</label>
                                <input type="text" id="auto_frecuencia_valor" class="form-control form-control-sm" placeholder="00:00">
                            </div>

                            <div class="col-md-4" id="auto_col_cron_expression" style="display:none;">
                                <label class="form-label small fw-bold">Expresión cron</label>
                                <input type="text" id="auto_cron_expression" class="form-control form-control-sm" placeholder="* * * * *">
                                <div class="form-text" style="font-size:0.75rem;">min hora dom mes dow</div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info bg-opacity-10 border-0 py-2 small mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Configure el cron del servidor para activar la ejecución automática:<br>
                                    <code>* * * * * php /ruta/sistema/app/cron/cron_runner.php</code>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ── Pestaña Historial ───────────────────────────────── -->
                    <div class="tab-pane fade" id="auto-tab-historial" role="tabpanel">
                        <div id="auto_log_cargando" class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando historial...
                        </div>
                        <div id="auto_log_contenido" style="display:none;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Iniciado</th>
                                        <th>Finalizado</th>
                                        <th>Duración</th>
                                        <th class="text-center">Resultado</th>
                                        <th class="text-center">Registros</th>
                                        <th>Ejecutado por</th>
                                        <th>Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody id="auto_log_tbody"></tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /tab-content -->
            </div><!-- /modal-body -->

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <!-- Izquierda: eliminar -->
                <div>
                    <?php if ($perm['eliminar'] ?? false): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                                id="auto_btn_eliminar" onclick="AUTO_eliminar()">
                            <i class="fas fa-trash me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <!-- Derecha: acciones -->
                <div class="d-flex gap-2">
                    <?php if ($perm['actualizar'] ?? false): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none"
                                id="auto_btn_ejecutar" onclick="AUTO_ejecutarAhora()">
                            <i class="fas fa-play me-1"></i> Ejecutar ahora
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm px-4" id="auto_btn_guardar" onclick="AUTO_guardar()">
                        <i class="fas fa-check-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const XHRH  = { 'X-Requested-With': 'XMLHttpRequest' };
    const UBASE = '<?= $urlBaseAuto ?>';
    let   _autoId = null;

    // ── Abrir crear ───────────────────────────────────────────────────────────
    window.AUTO_abrirModalCrear = function () {
        _autoId = null;
        _resetForm();
        document.getElementById('auto_tituloModal').textContent = 'Nueva Automatización';
        document.getElementById('auto-tab-historial-li').style.display = 'none';
        _showBtn('auto_btn_eliminar', false);
        _showBtn('auto_btn_ejecutar', false);
        _activarPrimerPestana();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAutomatizacion')).show();
    };

    // ── Abrir editar ──────────────────────────────────────────────────────────
    window.AUTO_abrirModalEditar = function (trEl) {
        <?php if ($perm['actualizar'] ?? false): ?>
        const d = JSON.parse(trEl.dataset.row);
        _autoId = d.id;
        _resetForm();

        document.getElementById('auto_id').value             = d.id;
        document.getElementById('auto_nombre').value         = d.nombre       ?? '';
        document.getElementById('auto_descripcion').value    = d.descripcion  ?? '';
        document.getElementById('auto_estado').value         = d.estado       ?? 'activo';
        document.getElementById('auto_frecuencia_tipo').value  = d.frecuencia_tipo  ?? 'diario';
        document.getElementById('auto_frecuencia_valor').value = d.frecuencia_valor ?? '';
        document.getElementById('auto_cron_expression').value  = d.cron_expression  ?? '';

        AUTO_actualizarFrecuencia();

        const params = typeof d.parametros === 'string'
            ? JSON.parse(d.parametros || '{}')
            : (d.parametros || {});
        document.getElementById('auto_modulo').value = d.modulo ?? '';
        AUTO_cargarAcciones(d.accion ?? '', params);

        document.getElementById('auto_tituloModal').textContent = 'Editar Automatización';
        document.getElementById('auto-tab-historial-li').style.display = 'inline';
        _showBtn('auto_btn_eliminar', true);
        _showBtn('auto_btn_ejecutar', true);
        _activarPrimerPestana();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAutomatizacion')).show();
        <?php else: ?>
        AUTO_abrirModalVerDetalle(trEl);
        <?php endif; ?>
    };

    // Solo lectura si no tiene permiso de edición
    window.AUTO_abrirModalVerDetalle = function (trEl) {
        const d = JSON.parse(trEl.dataset.row);
        _autoId = d.id;
        _resetForm();
        document.getElementById('auto_nombre').value          = d.nombre       ?? '';
        document.getElementById('auto_descripcion').value     = d.descripcion  ?? '';
        document.getElementById('auto_estado').value          = d.estado       ?? 'activo';
        document.getElementById('auto_frecuencia_tipo').value = d.frecuencia_tipo  ?? 'diario';
        document.getElementById('auto_frecuencia_valor').value= d.frecuencia_valor ?? '';
        document.getElementById('auto_cron_expression').value = d.cron_expression  ?? '';
        AUTO_actualizarFrecuencia();
        document.getElementById('auto_modulo').value = d.modulo ?? '';
        AUTO_cargarAcciones(d.accion ?? '', {});
        document.getElementById('auto_tituloModal').textContent = 'Detalle de Automatización';
        document.getElementById('auto-tab-historial-li').style.display = 'inline';
        document.getElementById('auto_btn_guardar').style.display = 'none';
        _activarPrimerPestana();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAutomatizacion')).show();
    };

    // ── Cargar acciones del módulo ─────────────────────────────────────────────
    window.AUTO_cargarAcciones = function (accionSel = '', paramsRestore = {}) {
        const modulo = document.getElementById('auto_modulo').value;
        const sel    = document.getElementById('auto_accion');
        sel.disabled = true;
        sel.innerHTML = '<option value="">— Cargando... —</option>';
        document.getElementById('auto_accion_desc').textContent = '';
        document.getElementById('auto_campos_params').innerHTML = '';
        document.getElementById('auto_contenedor_params').style.display = 'none';

        if (!modulo) { sel.innerHTML = '<option value="">— Seleccione acción —</option>'; return; }

        fetch(`${UBASE}/getAcciones?modulo=${encodeURIComponent(modulo)}`, { headers: XHRH })
            .then(r => r.json())
            .then(res => {
                sel.innerHTML = '<option value="">— Seleccione acción —</option>';
                (res.acciones ?? []).forEach(a => {
                    const opt       = document.createElement('option');
                    opt.value       = a.key;
                    opt.textContent = a.label;
                    opt.dataset.desc= a.descripcion;
                    sel.appendChild(opt);
                });
                sel.disabled = false;
                if (accionSel) {
                    sel.value = accionSel;
                    const desc = sel.selectedOptions[0]?.dataset?.desc ?? '';
                    document.getElementById('auto_accion_desc').textContent = desc;
                    AUTO_cargarParametros(paramsRestore);
                }
            });
    };

    // ── Cargar parámetros dinámicos ────────────────────────────────────────────
    window.AUTO_cargarParametros = function (restore = {}) {
        const modulo = document.getElementById('auto_modulo').value;
        const accion = document.getElementById('auto_accion').value;
        const opt    = document.getElementById('auto_accion').selectedOptions[0];
        document.getElementById('auto_accion_desc').textContent = opt?.dataset?.desc ?? '';

        if (!modulo || !accion) {
            document.getElementById('auto_contenedor_params').style.display = 'none';
            return;
        }

        fetch(`${UBASE}/getParametros?modulo=${encodeURIComponent(modulo)}&accion=${encodeURIComponent(accion)}`, { headers: XHRH })
            .then(r => r.json())
            .then(res => {
                const campos = res.parametros ?? [];
                const cont   = document.getElementById('auto_campos_params');
                cont.innerHTML = '';
                if (!campos.length) {
                    document.getElementById('auto_contenedor_params').style.display = 'none';
                    return;
                }
                campos.forEach(c => {
                    const col = document.createElement('div');
                    col.className = 'col-md-6';
                    col.innerHTML = _renderCampo(c, restore);
                    cont.appendChild(col);
                });
                document.getElementById('auto_contenedor_params').style.display = 'block';
            });
    };

    function _renderCampo(c, restore) {
        const val = c.key in restore ? restore[c.key] : (c.default ?? '');
        if (c.tipo === 'select') {
            const opts = Object.entries(c.opciones ?? {}).map(([k, v]) =>
                `<option value="${k}"${String(k) === String(val) ? ' selected' : ''}>${v}</option>`
            ).join('');
            return `<label class="form-label small fw-bold">${c.label}</label>
                    <select class="form-select form-select-sm" data-param="${c.key}">${opts}</select>`;
        }
        if (c.tipo === 'checkbox') {
            return `<div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="param_${c.key}" data-param="${c.key}"${val ? ' checked' : ''}>
                        <label class="form-check-label small fw-bold" for="param_${c.key}">${c.label}</label>
                    </div>`;
        }
        return `<label class="form-label small fw-bold">${c.label}</label>
                <input type="${c.tipo}" class="form-control form-control-sm" data-param="${c.key}" value="${val}" placeholder="${c.label}">`;
    }

    // ── Frecuencia ─────────────────────────────────────────────────────────────
    window.AUTO_actualizarFrecuencia = function () {
        const tipo = document.getElementById('auto_frecuencia_tipo').value;
        const lblMap = {
            diario:             'Hora (HH:MM)',
            semanal:            'Día y hora (ej: lunes 08:00)',
            mensual:            'Día y hora (ej: 15 20:00)',
            horas:              'Cada N horas',
            minutos:            'Cada N minutos',
            cron_personalizado: '',
        };
        document.getElementById('auto_label_frecuencia_valor').textContent = lblMap[tipo] ?? 'Valor';
        const esCron = tipo === 'cron_personalizado';
        document.getElementById('auto_col_frecuencia_valor').style.display  = esCron ? 'none' : 'block';
        document.getElementById('auto_col_cron_expression').style.display   = esCron ? 'block' : 'none';
    };

    // ── Guardar ────────────────────────────────────────────────────────────────
    window.AUTO_guardar = function () {
        const id     = document.getElementById('auto_id').value;
        const params = {};
        document.querySelectorAll('#auto_campos_params [data-param]').forEach(el => {
            params[el.dataset.param] = el.type === 'checkbox' ? el.checked : el.value;
        });

        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre',           document.getElementById('auto_nombre').value.trim());
        fd.append('descripcion',      document.getElementById('auto_descripcion').value.trim());
        fd.append('modulo',           document.getElementById('auto_modulo').value);
        fd.append('accion',           document.getElementById('auto_accion').value);
        fd.append('frecuencia_tipo',  document.getElementById('auto_frecuencia_tipo').value);
        fd.append('frecuencia_valor', document.getElementById('auto_frecuencia_valor').value.trim());
        fd.append('cron_expression',  document.getElementById('auto_cron_expression').value.trim());
        fd.append('estado',           document.getElementById('auto_estado').value);
        fd.append('parametros',       JSON.stringify(params));

        const btn = document.getElementById('auto_btn_guardar');
        btn.disabled = true;

        fetch(id ? `${UBASE}/update` : `${UBASE}/store`, {
            method: 'POST', body: fd, headers: XHRH
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAutomatizacion')).hide();
                window.fetchSearch ? window.fetchSearch(window.currentPage ?? 1) : location.reload();
            } else {
                alert(res.error ?? 'Error al guardar.');
            }
        })
        .catch(() => alert('Error de conexión al guardar.'))
        .finally(() => { btn.disabled = false; });
    };

    // ── Eliminar ───────────────────────────────────────────────────────────────
    window.AUTO_eliminar = function () {
        const id = document.getElementById('auto_id').value;
        if (!id || !confirm('¿Eliminar esta automatización? Esta acción no se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('id', id);
        fetch(`${UBASE}/delete`, { method: 'POST', body: fd, headers: XHRH })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modalAutomatizacion')).hide();
                    window.fetchSearch ? window.fetchSearch(1) : location.reload();
                } else { alert(res.error ?? 'Error al eliminar.'); }
            });
    };

    // ── Ejecutar ahora ─────────────────────────────────────────────────────────
    window.AUTO_ejecutarAhora = function () {
        const id     = document.getElementById('auto_id').value;
        const nombre = document.getElementById('auto_nombre').value;
        if (!confirm(`¿Ejecutar ahora la automatización "${nombre}"?`)) return;
        const btn = document.getElementById('auto_btn_ejecutar');
        btn.disabled = true;
        const fd = new FormData();
        fd.append('id', id);
        fetch(`${UBASE}/ejecutar`, { method: 'POST', body: fd, headers: XHRH })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    const r = res.resultado;
                    alert(`✔ ${r.mensaje ?? 'Ejecutado correctamente.'}\nRegistros afectados: ${r.registros ?? 0}`);
                    AUTO_cargarHistorial();
                    window.fetchSearch && window.fetchSearch(window.currentPage ?? 1);
                } else { alert('Error: ' + (res.error ?? 'Error desconocido.')); }
            })
            .finally(() => { btn.disabled = false; });
    };

    // ── Historial ──────────────────────────────────────────────────────────────
    window.AUTO_cargarHistorial = function () {
        if (!_autoId) return;
        document.getElementById('auto_log_cargando').style.display  = 'block';
        document.getElementById('auto_log_contenido').style.display = 'none';

        fetch(`${UBASE}/log?id=${_autoId}&page=1`, { headers: XHRH })
            .then(r => r.json())
            .then(res => {
                document.getElementById('auto_log_cargando').style.display  = 'none';
                document.getElementById('auto_log_contenido').style.display = 'block';
                const tbody = document.getElementById('auto_log_tbody');
                tbody.innerHTML = '';
                if (!res.rows?.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Sin ejecuciones registradas.</td></tr>';
                    return;
                }
                const cls = { exitoso: 'success', error: 'danger', pendiente: 'secondary' };
                res.rows.forEach(r => {
                    const c = cls[r.resultado] ?? 'secondary';
                    tbody.innerHTML += `<tr>
                        <td>${r.iniciado_en ?? '—'}</td>
                        <td>${r.finalizado_en ?? '—'}</td>
                        <td>${r.duracion_ms ? r.duracion_ms + ' ms' : '—'}</td>
                        <td class="text-center">
                            <span class="badge bg-${c} bg-opacity-10 text-${c} border border-${c} border-opacity-25">${r.resultado}</span>
                        </td>
                        <td class="text-center">${r.registros_afectados ?? 0}</td>
                        <td>${r.ejecutado_por ?? '—'}</td>
                        <td title="${r.detalle_error ?? ''}">${r.mensaje ?? '—'}</td>
                    </tr>`;
                });
            });
    };

    // ── Helpers privados ───────────────────────────────────────────────────────
    function _resetForm() {
        document.getElementById('auto_id').value              = '';
        document.getElementById('auto_nombre').value          = '';
        document.getElementById('auto_descripcion').value     = '';
        document.getElementById('auto_estado').value          = 'activo';
        document.getElementById('auto_modulo').value          = '';
        document.getElementById('auto_accion').innerHTML      = '<option value="">— Seleccione acción —</option>';
        document.getElementById('auto_accion').disabled       = true;
        document.getElementById('auto_accion_desc').textContent = '';
        document.getElementById('auto_campos_params').innerHTML = '';
        document.getElementById('auto_contenedor_params').style.display = 'none';
        document.getElementById('auto_frecuencia_tipo').value  = 'diario';
        document.getElementById('auto_frecuencia_valor').value = '00:00';
        document.getElementById('auto_cron_expression').value  = '';
        document.getElementById('auto_log_tbody').innerHTML    = '';
        document.getElementById('auto_log_cargando').style.display  = 'block';
        document.getElementById('auto_log_contenido').style.display = 'none';
        document.getElementById('auto_btn_guardar').style.display   = 'inline-block';
        AUTO_actualizarFrecuencia();
    }

    function _showBtn(id, show) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('d-none', !show);
    }

    function _activarPrimerPestana() {
        const first = document.querySelector('#tabsAutomatizacion .nav-link:not([style*="display:none"])');
        if (first) bootstrap.Tab.getOrCreateInstance(first).show();
    }
})();
</script>
