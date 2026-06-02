<?php
/** @var array  $perm */
/** @var string $rutaModulo */
/** @var array  $vistaConfig */
/** @var array  $modulos */

$urlBaseAuto = BASE_URL . '/modulos/automatizaciones';
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

            <div class="modal-body px-3 py-3">
                <input type="hidden" id="auto_id">

                <div class="row g-3">

                    <!-- ── Datos generales ──────────────────────────────────── -->
                    <div class="col-md-9">
                        <label class="form-label small fw-bold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="auto_nombre" class="form-control form-control-sm"
                               placeholder="Ej: Enviar facturas al SRI — Noche" maxlength="150">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Estado</label>
                        <select id="auto_estado" class="form-select form-select-sm">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>


                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Módulo <span class="text-danger">*</span></label>
                        <select id="auto_modulo" class="form-select form-select-sm" onchange="AUTO_cargarAcciones()">
                            <option value="">— Seleccione módulo —</option>
                            <?php foreach ($modulos ?? [] as $m): ?>
                                <option value="<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" style="font-size:0.75rem;">Sobre qué tipo de documento o proceso actúa la tarea.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Acción <span class="text-danger">*</span></label>
                        <select id="auto_accion" class="form-select form-select-sm" onchange="AUTO_cargarParametros()" disabled>
                            <option value="">— Seleccione acción —</option>
                        </select>
                        <div id="auto_accion_desc" class="form-text text-primary mt-1" style="font-size:0.78rem;"></div>
                    </div>

                    <!-- ── Aviso: correo automático ya activo ───────────────── -->
                    <div class="col-12" id="auto_aviso_correo_auto" style="display:none;">
                        <div class="alert alert-warning border-0 py-2 px-3 mb-0 small">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            <strong>Atención:</strong> tu empresa tiene activada la opción
                            <em>"Enviar correos de forma automática después de autorizar en el SRI"</em>.
                            Si creas esta automatización de correo, el cliente podría recibir
                            <strong>dos correos de la misma factura</strong>.
                            Desactiva esa opción en <em>Empresa → Configuración → Correo</em> antes de continuar.
                        </div>
                    </div>

                    <!-- ── Parámetros de la acción ──────────────────────────── -->
                    <div class="col-12" id="auto_contenedor_params" style="display:none;">
                        <hr class="my-1">
                        <div id="auto_campos_params" class="row g-3 p-2 border rounded-2 bg-light"></div>
                    </div>

                    <!-- ── Programación (al final) ──────────────────────────── -->
                    <div class="col-12">
                        <hr class="my-1">
                        <label class="form-label small fw-bold mb-1">
                            <i class="fas fa-clock text-secondary me-1"></i> Programación
                        </label>
                        <div class="alert alert-light border py-2 px-2 small mb-2" style="font-size:0.78rem;">
                            <i class="fas fa-info-circle text-primary me-1"></i>
                            Defina cada cuánto se ejecuta la tarea automáticamente.
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold">¿Cada cuánto se ejecuta? <span class="text-danger">*</span></label>
                        <select id="auto_frecuencia_tipo" class="form-select form-select-sm" onchange="AUTO_actualizarFrecuencia()">
                            <option value="diario">Todos los días a una hora</option>
                            <option value="semanal">Una vez por semana</option>
                            <option value="mensual">Una vez al mes</option>
                            <option value="horas">Cada cierto número de horas</option>
                            <option value="minutos">Cada cierto número de minutos</option>
                        </select>
                    </div>

                    <!-- Día de la semana (solo semanal) -->
                    <div class="col-md-4" id="auto_grp_dia_semana" style="display:none;">
                        <label class="form-label small fw-bold">Día de la semana</label>
                        <select id="auto_dia_semana" class="form-select form-select-sm" onchange="AUTO_actualizarResumen()">
                            <option value="lunes">Lunes</option>
                            <option value="martes">Martes</option>
                            <option value="miercoles">Miércoles</option>
                            <option value="jueves">Jueves</option>
                            <option value="viernes">Viernes</option>
                            <option value="sabado">Sábado</option>
                            <option value="domingo">Domingo</option>
                        </select>
                    </div>

                    <!-- Día del mes (solo mensual) -->
                    <div class="col-md-4" id="auto_grp_dia_mes" style="display:none;">
                        <label class="form-label small fw-bold">Día del mes</label>
                        <select id="auto_dia_mes" class="form-select form-select-sm" onchange="AUTO_actualizarResumen()">
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?= $d ?>"><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Hora (diario, semanal, mensual) -->
                    <div class="col-md-3" id="auto_grp_hora">
                        <label class="form-label small fw-bold">Hora</label>
                        <input type="time" id="auto_hora" class="form-control form-control-sm" value="20:00" onchange="AUTO_actualizarResumen()">
                    </div>

                    <!-- Cada N horas -->
                    <div class="col-md-7" id="auto_grp_cada_horas" style="display:none;">
                        <label class="form-label small fw-bold">Repetir cada</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="auto_cada_horas" class="form-control form-control-sm" min="1" max="24" value="6" oninput="AUTO_actualizarResumen()">
                            <span class="input-group-text">horas</span>
                        </div>
                        <div class="form-text" style="font-size:0.75rem;">Ejemplo: 6 = se ejecuta cada 6 horas.</div>
                    </div>

                    <!-- Cada N minutos -->
                    <div class="col-md-7" id="auto_grp_cada_minutos" style="display:none;">
                        <label class="form-label small fw-bold">Repetir cada</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="auto_cada_minutos" class="form-control form-control-sm" min="1" max="59" value="30" oninput="AUTO_actualizarResumen()">
                            <span class="input-group-text">minutos</span>
                        </div>
                        <div class="form-text" style="font-size:0.75rem;">Ejemplo: 30 = se ejecuta cada 30 minutos.</div>
                    </div>

                    <!-- Campo oculto: el backend aún acepta cron, pero no se expone en la UI -->
                    <input type="hidden" id="auto_cron_expression" value="">

                    <!-- Resumen legible -->
                    <div class="col-12">
                        <div class="small text-success" id="auto_resumen_frecuencia" style="font-size:0.8rem;"></div>
                    </div>

                    <!-- ── Historial (solo edición) ─────────────────────────── -->
                    <div class="col-12" id="auto_seccion_historial" style="display:none;">
                        <hr class="my-1">
                        <label class="form-label small fw-bold mb-2">
                            <i class="fas fa-history text-secondary me-1"></i> Historial de ejecuciones
                        </label>
                        <div id="auto_log_cargando" class="text-center py-3 text-muted small">
                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando historial...
                        </div>
                        <div id="auto_log_contenido" style="display:none;">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size:.8rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Iniciado</th>
                                            <th>Finalizado</th>
                                            <th>Duración</th>
                                            <th class="text-center">Resultado</th>
                                            <th class="text-center">Registros</th>
                                            <th>Por</th>
                                            <th>Mensaje</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auto_log_tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div><!-- /modal-body -->

            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if ($perm['eliminar'] ?? false): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                                id="auto_btn_eliminar" onclick="AUTO_eliminar()">
                            <i class="fas fa-trash me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
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
        document.getElementById('auto_seccion_historial').style.display = 'none';
        _showBtn('auto_btn_eliminar', false);
        _showBtn('auto_btn_ejecutar', false);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAutomatizacion')).show();
    };

    // ── Abrir editar ──────────────────────────────────────────────────────────
    window.AUTO_abrirModalEditar = function (trEl) {
        <?php if ($perm['actualizar'] ?? false): ?>
        const d = JSON.parse(trEl.dataset.row);
        _autoId = d.id;
        _resetForm();

        document.getElementById('auto_id').value               = d.id;
        document.getElementById('auto_nombre').value           = d.nombre       ?? '';
        document.getElementById('auto_estado').value           = d.estado       ?? 'activo';
        document.getElementById('auto_frecuencia_tipo').value  = d.frecuencia_tipo  ?? 'diario';
        document.getElementById('auto_cron_expression').value  = d.cron_expression  ?? '';

        AUTO_actualizarFrecuencia();
        _parseFrecuenciaValor(d.frecuencia_tipo ?? 'diario', d.frecuencia_valor);
        AUTO_actualizarResumen();

        const params = typeof d.parametros === 'string'
            ? JSON.parse(d.parametros || '{}')
            : (d.parametros || {});
        document.getElementById('auto_modulo').value = d.modulo ?? '';
        AUTO_cargarAcciones(d.accion ?? '', params);

        document.getElementById('auto_tituloModal').textContent = 'Editar Automatización';
        _showBtn('auto_btn_eliminar', true);
        _showBtn('auto_btn_ejecutar', true);

        // Historial visible y cargado
        document.getElementById('auto_seccion_historial').style.display = 'block';
        AUTO_cargarHistorial();

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAutomatizacion')).show();
        <?php else: ?>
        AUTO_abrirModalVerDetalle(trEl);
        <?php endif; ?>
    };

    // Solo lectura
    window.AUTO_abrirModalVerDetalle = function (trEl) {
        const d = JSON.parse(trEl.dataset.row);
        _autoId = d.id;
        _resetForm();
        document.getElementById('auto_nombre').value           = d.nombre       ?? '';
        document.getElementById('auto_estado').value           = d.estado       ?? 'activo';
        document.getElementById('auto_frecuencia_tipo').value  = d.frecuencia_tipo  ?? 'diario';
        document.getElementById('auto_cron_expression').value  = d.cron_expression  ?? '';
        AUTO_actualizarFrecuencia();
        _parseFrecuenciaValor(d.frecuencia_tipo ?? 'diario', d.frecuencia_valor);
        AUTO_actualizarResumen();
        document.getElementById('auto_modulo').value = d.modulo ?? '';
        AUTO_cargarAcciones(d.accion ?? '', {});
        document.getElementById('auto_tituloModal').textContent = 'Detalle de Automatización';
        document.getElementById('auto_btn_guardar').style.display = 'none';
        document.getElementById('auto_seccion_historial').style.display = 'block';
        AUTO_cargarHistorial();
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
                    const opt        = document.createElement('option');
                    opt.value        = a.key;
                    opt.textContent  = a.label;
                    opt.dataset.desc = a.descripcion;
                    sel.appendChild(opt);
                });
                sel.disabled = false;
                if (accionSel) {
                    sel.value = accionSel;
                    document.getElementById('auto_accion_desc').textContent = sel.selectedOptions[0]?.dataset?.desc ?? '';
                    AUTO_cargarParametros(paramsRestore);
                }
            });
    };

    // Detecta la combinación que provocaría correos duplicados
    function _esCorreoFacturaDuplicado() {
        return document.getElementById('auto_modulo').value === 'facturas_venta'
            && document.getElementById('auto_accion').value === 'enviar_correo'
            && window.AUTO_ENVIO_AUTOMATICO_CORREO === true;
    }
    function _toggleAvisoCorreo() {
        document.getElementById('auto_aviso_correo_auto').style.display =
            _esCorreoFacturaDuplicado() ? 'block' : 'none';
    }

    // ── Cargar parámetros dinámicos ────────────────────────────────────────────
    window.AUTO_cargarParametros = function (restore = {}) {
        const modulo = document.getElementById('auto_modulo').value;
        const accion = document.getElementById('auto_accion').value;
        const opt    = document.getElementById('auto_accion').selectedOptions[0];
        document.getElementById('auto_accion_desc').textContent = opt?.dataset?.desc ?? '';

        _toggleAvisoCorreo();

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
                    col.className = 'col-12';
                    col.innerHTML = _renderCampo(c, restore);
                    cont.appendChild(col);
                });
                document.getElementById('auto_contenedor_params').style.display = 'block';
            });
    };

    function _renderCampo(c, restore) {
        const val   = c.key in restore ? restore[c.key] : (c.default ?? '');
        const ayuda = c.ayuda
            ? `<div class="form-text text-muted mt-1" style="font-size:0.74rem;"><i class="fas fa-circle-info me-1"></i>${c.ayuda}</div>`
            : '';

        if (c.tipo === 'select') {
            const opts = Object.entries(c.opciones ?? {}).map(([k, v]) =>
                `<option value="${k}"${String(k) === String(val) ? ' selected' : ''}>${v}</option>`
            ).join('');
            return `<label class="form-label small fw-bold mb-1">${c.label}</label>
                    <select class="form-select form-select-sm" data-param="${c.key}">${opts}</select>${ayuda}`;
        }
        if (c.tipo === 'checkbox') {
            return `<div class="form-check">
                        <input class="form-check-input" type="checkbox" id="param_${c.key}" data-param="${c.key}"${val ? ' checked' : ''}>
                        <label class="form-check-label small fw-bold" for="param_${c.key}">${c.label}</label>
                    </div>${ayuda}`;
        }
        return `<label class="form-label small fw-bold mb-1">${c.label}</label>
                <input type="${c.tipo}" class="form-control form-control-sm" data-param="${c.key}" value="${val}" placeholder="${c.label}">${ayuda}`;
    }

    // ── Frecuencia ─────────────────────────────────────────────────────────────
    const _DIAS_SEMANA = { lunes:'lunes', martes:'martes', miercoles:'miércoles',
                           jueves:'jueves', viernes:'viernes', sabado:'sábado', domingo:'domingo' };

    window.AUTO_actualizarFrecuencia = function () {
        const tipo = document.getElementById('auto_frecuencia_tipo').value;

        const show = (id, v) => { document.getElementById(id).style.display = v ? 'block' : 'none'; };
        show('auto_grp_dia_semana',   tipo === 'semanal');
        show('auto_grp_dia_mes',      tipo === 'mensual');
        show('auto_grp_hora',         ['diario', 'semanal', 'mensual'].includes(tipo));
        show('auto_grp_cada_horas',   tipo === 'horas');
        show('auto_grp_cada_minutos', tipo === 'minutos');

        AUTO_actualizarResumen();
    };

    // Resumen legible de cuándo se ejecuta
    window.AUTO_actualizarResumen = function () {
        const tipo = document.getElementById('auto_frecuencia_tipo').value;
        const hora = document.getElementById('auto_hora').value || '00:00';
        let txt = '';
        switch (tipo) {
            case 'diario':
                txt = `Se ejecutará todos los días a las ${hora}.`; break;
            case 'semanal':
                txt = `Se ejecutará cada ${_DIAS_SEMANA[document.getElementById('auto_dia_semana').value]} a las ${hora}.`; break;
            case 'mensual':
                txt = `Se ejecutará el día ${document.getElementById('auto_dia_mes').value} de cada mes a las ${hora}.`; break;
            case 'horas':
                txt = `Se ejecutará cada ${document.getElementById('auto_cada_horas').value} hora(s).`; break;
            case 'minutos':
                txt = `Se ejecutará cada ${document.getElementById('auto_cada_minutos').value} minuto(s).`; break;
        }
        const el = document.getElementById('auto_resumen_frecuencia');
        el.innerHTML = txt ? `<i class="fas fa-circle-check me-1"></i>${txt}` : '';
    };

    // Genera la descripción automática: "Módulo — Acción. Frecuencia."
    function _buildDescripcion() {
        const modSel = document.getElementById('auto_modulo');
        const accSel = document.getElementById('auto_accion');
        const modulo = modSel.selectedOptions[0]?.textContent.trim() ?? '';
        const accion = accSel.selectedOptions[0]?.textContent.trim() ?? '';

        // Texto de frecuencia (sin el ícono del resumen)
        const resumen = (document.getElementById('auto_resumen_frecuencia').textContent || '').trim();

        let txt = '';
        if (modulo) txt += modulo;
        if (accion) txt += (txt ? ' — ' : '') + accion;
        if (resumen) txt += (txt ? '. ' : '') + resumen;
        return txt;
    }

    // Construye frecuencia_valor en el formato que espera el backend
    function _buildFrecuenciaValor() {
        const tipo = document.getElementById('auto_frecuencia_tipo').value;
        const hora = document.getElementById('auto_hora').value || '00:00';
        switch (tipo) {
            case 'diario':   return hora;
            case 'semanal':  return document.getElementById('auto_dia_semana').value + ' ' + hora;
            case 'mensual':  return document.getElementById('auto_dia_mes').value + ' ' + hora;
            case 'horas':    return document.getElementById('auto_cada_horas').value;
            case 'minutos':  return document.getElementById('auto_cada_minutos').value;
            default:         return '';
        }
    }

    // Descompone frecuencia_valor guardado en los campos visuales
    function _parseFrecuenciaValor(tipo, valor) {
        valor = (valor || '').trim();
        switch (tipo) {
            case 'diario':
                document.getElementById('auto_hora').value = valor || '20:00';
                break;
            case 'semanal': {
                const [dia, hora] = valor.split(' ');
                if (dia) document.getElementById('auto_dia_semana').value = dia;
                document.getElementById('auto_hora').value = hora || '20:00';
                break;
            }
            case 'mensual': {
                const [dia, hora] = valor.split(' ');
                if (dia) document.getElementById('auto_dia_mes').value = dia;
                document.getElementById('auto_hora').value = hora || '20:00';
                break;
            }
            case 'horas':
                document.getElementById('auto_cada_horas').value = valor || '6';
                break;
            case 'minutos':
                document.getElementById('auto_cada_minutos').value = valor || '30';
                break;
        }
    }

    // ── Guardar ────────────────────────────────────────────────────────────────
    window.AUTO_guardar = function () {
        // Bloquear si provocaría correos duplicados
        if (_esCorreoFacturaDuplicado()) {
            Swal.fire({
                icon: 'warning',
                title: 'No se puede guardar',
                html: 'Tu empresa tiene activado el <strong>envío automático de correo</strong> tras la autorización del SRI.<br><br>'
                    + 'Esta automatización enviaría un <strong>segundo correo</strong> de la misma factura.<br><br>'
                    + 'Desactiva esa opción en <em>Empresa → Configuración → Correo</em> antes de crear esta automatización.',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const id     = document.getElementById('auto_id').value;
        const params = {};
        document.querySelectorAll('#auto_campos_params [data-param]').forEach(el => {
            params[el.dataset.param] = el.type === 'checkbox' ? el.checked : el.value;
        });

        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre',           document.getElementById('auto_nombre').value.trim());
        fd.append('descripcion',      _buildDescripcion());
        fd.append('modulo',           document.getElementById('auto_modulo').value);
        fd.append('accion',           document.getElementById('auto_accion').value);
        fd.append('frecuencia_tipo',  document.getElementById('auto_frecuencia_tipo').value);
        fd.append('frecuencia_valor', _buildFrecuenciaValor());
        fd.append('cron_expression',  document.getElementById('auto_cron_expression').value.trim());
        fd.append('estado',           document.getElementById('auto_estado').value);
        fd.append('parametros',       JSON.stringify(params));

        const btn = document.getElementById('auto_btn_guardar');
        btn.disabled = true;

        fetch(id ? `${UBASE}/update` : `${UBASE}/store`, { method: 'POST', body: fd, headers: XHRH })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAutomatizacion')).hide();
                _toastOk(res.mensaje ?? 'Guardado correctamente.');
                window.fetchSearch ? window.fetchSearch(window.currentPage ?? 1) : location.reload();
            } else {
                _alertError(res.error ?? 'Error al guardar.');
            }
        })
        .catch(() => _alertError('No se pudo comunicar con el servidor.'))
        .finally(() => { btn.disabled = false; });
    };

    // ── Eliminar ───────────────────────────────────────────────────────────────
    window.AUTO_eliminar = function () {
        const id = document.getElementById('auto_id').value;
        if (!id) return;
        Swal.fire({
            title: '¿Está seguro?',
            text: 'Se eliminará esta automatización. Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${UBASE}/delete`, { method: 'POST', body: fd, headers: XHRH })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        bootstrap.Modal.getInstance(document.getElementById('modalAutomatizacion')).hide();
                        _toastOk(res.mensaje ?? 'Eliminado correctamente.');
                        window.fetchSearch ? window.fetchSearch(1) : location.reload();
                    } else { _alertError(res.error ?? 'No se pudo eliminar.'); }
                })
                .catch(() => _alertError('No se pudo comunicar con el servidor.'));
        });
    };

    // ── Ejecutar ahora ─────────────────────────────────────────────────────────
    window.AUTO_ejecutarAhora = function () {
        const id     = document.getElementById('auto_id').value;
        const nombre = document.getElementById('auto_nombre').value;
        Swal.fire({
            title: '¿Ejecutar ahora?',
            text: `Se ejecutará la automatización "${nombre}" inmediatamente.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, ejecutar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            const btn = document.getElementById('auto_btn_ejecutar');
            btn.disabled = true;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${UBASE}/ejecutar`, { method: 'POST', body: fd, headers: XHRH })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        const r = res.resultado;
                        Swal.fire({
                            icon: 'success',
                            title: 'Ejecutado',
                            html: `${r.mensaje ?? 'Ejecutado correctamente.'}<br><small class="text-muted">Registros afectados: ${r.registros ?? 0}</small>`
                        });
                        AUTO_cargarHistorial();
                        window.fetchSearch && window.fetchSearch(window.currentPage ?? 1);
                    } else { _alertError(res.error ?? 'Error desconocido.'); }
                })
                .catch(() => _alertError('No se pudo comunicar con el servidor.'))
                .finally(() => { btn.disabled = false; });
        });
    };

    // ── Helpers de notificación ────────────────────────────────────────────────
    function _toastOk(msg) {
        Swal.fire({ icon: 'success', title: 'Éxito', text: msg, timer: 1600, showConfirmButton: false });
    }
    function _alertError(msg) {
        Swal.fire({ icon: 'error', title: 'Atención', text: msg });
    }

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

    // ── Helpers ────────────────────────────────────────────────────────────────
    function _resetForm() {
        document.getElementById('auto_id').value              = '';
        document.getElementById('auto_nombre').value          = '';
        document.getElementById('auto_estado').value          = 'activo';
        document.getElementById('auto_modulo').value          = '';
        document.getElementById('auto_accion').innerHTML      = '<option value="">— Seleccione acción —</option>';
        document.getElementById('auto_accion').disabled       = true;
        document.getElementById('auto_accion_desc').textContent = '';
        document.getElementById('auto_campos_params').innerHTML = '';
        document.getElementById('auto_contenedor_params').style.display = 'none';
        document.getElementById('auto_aviso_correo_auto').style.display = 'none';
        document.getElementById('auto_frecuencia_tipo').value  = 'diario';
        document.getElementById('auto_hora').value             = '20:00';
        document.getElementById('auto_dia_semana').value       = 'lunes';
        document.getElementById('auto_dia_mes').value          = '1';
        document.getElementById('auto_cada_horas').value       = '6';
        document.getElementById('auto_cada_minutos').value     = '30';
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
})();
</script>
