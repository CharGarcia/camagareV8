/**
 * Lógica del Modal de Alumno: pestañas, catálogos, filas dinámicas
 * (Matrícula / Horario / Servicios), alta rápida de Campus/Nivel y
 * documentos adjuntos.
 */
(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/alumnos') : (window.location.origin + '/sistema/public/modulos/alumnos');
    const modalEl = document.getElementById('modalAlumno');
    let modalInst = null;
    let catalogosCargados = false;
    let idAlumnoActual = null;

    const datosCatalogos = { tipos_id: [], puntos_emision: [], campus: [], niveles: [] };

    // Select de campus/nivel apuntado por el último botón "+" pulsado, para
    // saber a cuál select aplicar la autoselección cuando llega el evento.
    let selCampusObjetivo = null;
    let selNivelObjetivo = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    async function fetchJson(url, opts) {
        const resp = await fetch(url, opts);
        return resp.json();
    }

    // ── Catálogos (tipos de identificación, puntos de emisión, campus, niveles) ──
    async function cargarCatalogos(forzar = false) {
        if (catalogosCargados && !forzar) return;
        try {
            const json = await fetchJson(`${urlBase}/catalogosAjax`);
            if (!json.ok) return;
            datosCatalogos.tipos_id = json.tipos_id || [];
            datosCatalogos.puntos_emision = json.puntos_emision || [];
            datosCatalogos.campus = json.campus || [];
            datosCatalogos.niveles = json.niveles || [];
            catalogosCargados = true;

            const selTipo = document.getElementById('alu_tipo_id');
            if (selTipo) {
                selTipo.innerHTML = '<option value="">-- Seleccione --</option>' +
                    datosCatalogos.tipos_id.map(t => `<option value="${t.codigo}">${t.nombre}</option>`).join('');
            }
            const selPunto = document.getElementById('alu_punto_emision');
            if (selPunto) {
                selPunto.innerHTML = '<option value="">-- Usar el predeterminado de la empresa --</option>' +
                    datosCatalogos.puntos_emision.map(p => `<option value="${p.id}">${p.nombre}${p.establecimiento_nombre ? ' (' + p.establecimiento_nombre + ')' : ''}</option>`).join('');
            }
            document.querySelectorAll('.sel-campus').forEach(sel => llenarSelectCampus(sel));
            document.querySelectorAll('.sel-nivel').forEach(sel => llenarSelectNivel(sel));
        } catch (e) {}
    }

    function llenarSelectCampus(select, seleccionado = '') {
        select.innerHTML = '<option value="">-- Campus --</option>' +
            datosCatalogos.campus.map(c => `<option value="${c.id}" ${String(c.id) === String(seleccionado) ? 'selected' : ''}>${c.nombre}</option>`).join('');
    }

    function llenarSelectNivel(select, seleccionado = '') {
        select.innerHTML = '<option value="">-- Nivel/Curso --</option>' +
            datosCatalogos.niveles.map(n => `<option value="${n.id}" ${String(n.id) === String(seleccionado) ? 'selected' : ''}>${n.nombre}</option>`).join('');
    }

    window.addEventListener('campusGuardado', (e) => {
        const c = e.detail;
        if (!datosCatalogos.campus.some(x => String(x.id) === String(c.id))) datosCatalogos.campus.push({ id: c.id, nombre: c.nombre });
        document.querySelectorAll('.sel-campus').forEach(sel => {
            const val = (sel === selCampusObjetivo) ? c.id : sel.value;
            llenarSelectCampus(sel, val);
        });
        selCampusObjetivo = null;
    });

    window.addEventListener('nivelGuardado', (e) => {
        const n = e.detail;
        if (!datosCatalogos.niveles.some(x => String(x.id) === String(n.id))) datosCatalogos.niveles.push({ id: n.id, nombre: n.nombre });
        document.querySelectorAll('.sel-nivel').forEach(sel => {
            const val = (sel === selNivelObjetivo) ? n.id : sel.value;
            llenarSelectNivel(sel, val);
        });
        selNivelObjetivo = null;
    });

    // El modal de Cliente (incluido desde clientes/modal_cliente.php) dispara
    // 'clienteGuardado' en `document` con { ok, data: {id, nombre, identificacion, ...} }.
    // Solo se autoselecciona si el modal de Alumno está abierto, para no
    // interferir si el usuario crea un cliente desde otra pantalla.
    document.addEventListener('clienteGuardado', (e) => {
        if (!modalEl || !modalEl.classList.contains('show')) return;
        const res = e.detail;
        if (!res || !res.ok || !res.data) return;
        const input = document.getElementById('alu_cliente_texto');
        const hidden = document.getElementById('alu_id_cliente');
        if (!input || !hidden) return;
        hidden.value = res.data.id;
        input.value = res.data.identificacion ? `${res.data.nombre} (${res.data.identificacion})` : res.data.nombre;
    });

    // ── Typeahead genérico (mismo patrón que app/views/modulos/mayores/index.php) ──
    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
        let debounceTimer;
        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = '';
                inputEl.value = '';
                dropdownEl.style.display = 'none';
                dropdownEl.innerHTML = '';
            }
        });
        inputEl.addEventListener('input', () => {
            hiddenEl.value = '';
            clearTimeout(debounceTimer);
            const q = inputEl.value.trim();
            if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
            debounceTimer = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { return; }
                if (!items || !items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                dropdownEl.innerHTML = items.map(it => {
                    const label = renderLabel(it);
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${label.replace(/"/g, '&quot;')}">${label}</a>`;
                }).join('');
                dropdownEl.style.display = 'block';
            }, 300);
        });
        dropdownEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            dropdownEl.style.display = 'none';
        });
        document.addEventListener('click', (e) => {
            if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
        });
    }

    function initClienteTypeahead() {
        const input = document.getElementById('alu_cliente_texto');
        const dropdown = document.getElementById('alu_cliente_dropdown');
        const hidden = document.getElementById('alu_id_cliente');
        if (!input || input.dataset.typeaheadReady) return;
        input.dataset.typeaheadReady = '1';
        setupTypeahead(input, dropdown, hidden,
            async (q) => {
                const json = await fetchJson(`${urlBase}/getClientesAjax?q=${encodeURIComponent(q)}`);
                return json.ok ? json.data : [];
            },
            (it) => it.identificacion ? `${it.nombre} (${it.identificacion})` : it.nombre
        );
    }

    function initProductoTypeahead(row) {
        const input = row.querySelector('.txt-producto');
        const dropdown = row.querySelector('.producto-dropdown');
        const hidden = row.querySelector('.hid-producto');
        const precioInput = row.querySelector('.txt-precio');
        let ultimosResultados = [];
        setupTypeahead(input, dropdown, hidden,
            async (q) => {
                const json = await fetchJson(`${urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`);
                ultimosResultados = json.ok ? json.data : [];
                return ultimosResultados;
            },
            (it) => it.nombre
        );
        dropdown.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a || !precioInput || precioInput.value !== '') return;
            const prod = ultimosResultados.find(p => String(p.id) === a.dataset.id);
            if (prod) precioInput.placeholder = `Base: ${prod.precio_base}`;
        });
    }

    // ── Filas dinámicas: Matrícula (Períodos) ───────────────────────────────
    window.aluAgregarFilaPeriodo = function (data = {}) {
        const tbody = document.querySelector('#tablaPeriodos tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.innerHTML = `
            <td><div class="d-flex gap-1">
                <select class="form-select form-select-sm input-alu sel-campus"></select>
                <button type="button" class="btn btn-sm btn-outline-primary px-1" title="Nuevo campus"><i class="bi bi-plus-lg"></i></button>
            </div></td>
            <td><div class="d-flex gap-1">
                <select class="form-select form-select-sm input-alu sel-nivel"></select>
                <button type="button" class="btn btn-sm btn-outline-primary px-1" title="Nuevo nivel/curso"><i class="bi bi-plus-lg"></i></button>
            </div></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-anio" placeholder="2025-2026" value="${data.anio_lectivo || ''}"></td>
            <td><input type="date" class="form-control form-control-sm input-alu dt-ingreso" value="${data.fecha_ingreso || ''}"></td>
            <td><input type="date" class="form-control form-control-sm input-alu dt-salida" value="${data.fecha_salida || ''}"></td>
            <td><select class="form-select form-select-sm input-alu sel-motivo">
                    <option value="">--</option>
                    <option value="retiro_voluntario">Retiro voluntario</option>
                    <option value="cambio_institucion">Cambio de institución</option>
                    <option value="no_pago">No pago</option>
                    <option value="graduacion">Graduación</option>
                    <option value="otro">Otro</option>
                </select></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-obs" value="${data.observacion || ''}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);

        const selCampus = row.querySelector('.sel-campus');
        const selNivel = row.querySelector('.sel-nivel');
        llenarSelectCampus(selCampus, data.id_campus || '');
        llenarSelectNivel(selNivel, data.id_nivel || '');
        if (data.motivo_salida) row.querySelector('.sel-motivo').value = data.motivo_salida;

        row.querySelectorAll('button')[0].addEventListener('click', () => {
            selCampusObjetivo = selCampus;
            window.abrirModalCampusCrear();
        });
        row.querySelectorAll('button')[1].addEventListener('click', () => {
            selNivelObjetivo = selNivel;
            window.abrirModalNivelCrear();
        });
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Filas dinámicas: Horario ────────────────────────────────────────────
    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    window.aluAgregarFilaHorario = function (data = {}) {
        const tbody = document.querySelector('#tablaHorarios tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.innerHTML = `
            <td><select class="form-select form-select-sm input-alu sel-dia">
                    ${DIAS.slice(1).map((d, i) => `<option value="${i + 1}" ${Number(data.dia_semana) === i + 1 ? 'selected' : ''}>${d}</option>`).join('')}
                </select></td>
            <td><input type="time" class="form-control form-control-sm input-alu tm-inicio" value="${data.hora_inicio ? data.hora_inicio.slice(0, 5) : ''}"></td>
            <td><input type="time" class="form-control form-control-sm input-alu tm-fin" value="${data.hora_fin ? data.hora_fin.slice(0, 5) : ''}"></td>
            <td><select class="form-select form-select-sm input-alu sel-jornada">
                    <option value="">--</option>
                    <option value="matutina" ${data.jornada === 'matutina' ? 'selected' : ''}>Matutina</option>
                    <option value="vespertina" ${data.jornada === 'vespertina' ? 'selected' : ''}>Vespertina</option>
                    <option value="nocturna" ${data.jornada === 'nocturna' ? 'selected' : ''}>Nocturna</option>
                </select></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-obs" value="${data.observacion || ''}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Filas dinámicas: Servicios y Productos ──────────────────────────────
    window.aluAgregarFilaServicio = function (data = {}) {
        const tbody = document.querySelector('#tablaServicios tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.innerHTML = `
            <td class="position-relative">
                <input type="text" class="form-control form-control-sm input-alu txt-producto" placeholder="Buscar producto/servicio..." autocomplete="off" value="${data.producto_nombre || ''}">
                <input type="hidden" class="hid-producto" value="${data.id_producto || ''}">
                <div class="list-group shadow-sm alu-typeahead-dropdown producto-dropdown"></div>
            </td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm input-alu txt-cantidad" value="${data.cantidad_default ?? 1}"></td>
            <td><select class="form-select form-select-sm input-alu sel-frecuencia">
                    <option value="mensual" ${(data.frecuencia || 'mensual') === 'mensual' ? 'selected' : ''}>Mensual</option>
                    <option value="unica_vez" ${data.frecuencia === 'unica_vez' ? 'selected' : ''}>Única vez</option>
                    <option value="periodo" ${data.frecuencia === 'periodo' ? 'selected' : ''}>Por período</option>
                </select></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm input-alu txt-precio" placeholder="Precio base" value="${data.precio_override ?? ''}"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input chk-activo" ${(data.activo === undefined || data.activo === true || data.activo === 't') ? 'checked' : ''}></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        initProductoTypeahead(row);
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Serialización de las 3 sub-tablas a los inputs *_json ───────────────
    function serializarTablas() {
        const periodos = [];
        document.querySelectorAll('#tablaPeriodos tbody tr').forEach(tr => {
            const fi = tr.querySelector('.dt-ingreso')?.value;
            if (!fi) return;
            periodos.push({
                id_campus: tr.querySelector('.sel-campus')?.value || '',
                id_nivel: tr.querySelector('.sel-nivel')?.value || '',
                anio_lectivo: tr.querySelector('.txt-anio')?.value || '',
                fecha_ingreso: fi,
                fecha_salida: tr.querySelector('.dt-salida')?.value || '',
                motivo_salida: tr.querySelector('.sel-motivo')?.value || '',
                observacion: tr.querySelector('.txt-obs')?.value || '',
            });
        });
        document.getElementById('periodos_json').value = JSON.stringify(periodos);

        const horarios = [];
        document.querySelectorAll('#tablaHorarios tbody tr').forEach(tr => {
            const hi = tr.querySelector('.tm-inicio')?.value;
            const hf = tr.querySelector('.tm-fin')?.value;
            if (!hi || !hf) return;
            horarios.push({
                dia_semana: tr.querySelector('.sel-dia')?.value || '',
                hora_inicio: hi,
                hora_fin: hf,
                jornada: tr.querySelector('.sel-jornada')?.value || '',
                observacion: tr.querySelector('.txt-obs')?.value || '',
            });
        });
        document.getElementById('horarios_json').value = JSON.stringify(horarios);

        const servicios = [];
        document.querySelectorAll('#tablaServicios tbody tr').forEach(tr => {
            const idProd = tr.querySelector('.hid-producto')?.value;
            if (!idProd) return;
            servicios.push({
                id_producto: idProd,
                cantidad_default: tr.querySelector('.txt-cantidad')?.value || 1,
                frecuencia: tr.querySelector('.sel-frecuencia')?.value || 'mensual',
                precio_override: tr.querySelector('.txt-precio')?.value || '',
                activo: tr.querySelector('.chk-activo')?.checked ?? true,
            });
        });
        document.getElementById('servicios_json').value = JSON.stringify(servicios);
    }

    function limpiarTablas() {
        ['tablaPeriodos', 'tablaHorarios', 'tablaServicios'].forEach(id => {
            const tbody = document.querySelector(`#${id} tbody`);
            if (tbody) tbody.innerHTML = '';
        });
    }

    // ── Documentos ───────────────────────────────────────────────────────────
    function renderDocumentos(documentos) {
        const tbody = document.getElementById('tbodyDocumentosAlumno');
        if (!tbody) return;
        if (!documentos || !documentos.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Sin documentos adjuntos.</td></tr>';
            return;
        }
        const etiquetas = {
            partida_nacimiento: 'Partida de nacimiento', cedula: 'Cédula/Identificación',
            foto_carnet: 'Foto carnet', certificado_medico: 'Certificado médico',
            contrato: 'Contrato', otro: 'Otro',
        };
        tbody.innerHTML = documentos.map(d => `
            <tr>
                <td>${etiquetas[d.tipo_documento] || d.tipo_documento}</td>
                <td><a href="${window.BASE_URL}/${d.ruta_archivo}" target="_blank">${d.nombre_archivo || 'Ver archivo'}</a></td>
                <td class="small text-muted">${d.fecha_carga || ''}</td>
                <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0" title="Eliminar" onclick="window.aluEliminarDocumento(${d.id})"><i class="bi bi-trash text-danger"></i></button></td>
            </tr>
        `).join('');
    }

    window.aluSubirDocumento = async function () {
        if (!idAlumnoActual) { alert('Guarde el alumno antes de adjuntar documentos.'); return; }
        const fileInput = document.getElementById('alu_doc_archivo');
        const tipo = document.getElementById('alu_doc_tipo').value;
        if (!fileInput.files.length) { alert('Seleccione un archivo.'); return; }

        const fd = new FormData();
        fd.append('id_alumno', idAlumnoActual);
        fd.append('tipo_documento', tipo);
        fd.append('archivo', fileInput.files[0]);

        try {
            const resp = await fetch(`${urlBase}/subirDocumentoAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                renderDocumentos(json.documentos);
                fileInput.value = '';
            } else {
                alert(json.error || 'No se pudo adjuntar el documento.');
            }
        } catch (e) {
            alert('Error de conexión al subir el documento.');
        }
    };

    window.aluEliminarDocumento = async function (idDocumento) {
        if (!confirm('¿Eliminar este documento adjunto?')) return;
        const fd = new FormData();
        fd.append('id_documento', idDocumento);
        fd.append('id_alumno', idAlumnoActual);
        try {
            const resp = await fetch(`${urlBase}/eliminarDocumentoAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) renderDocumentos(json.documentos);
            else alert(json.error || 'No se pudo eliminar.');
        } catch (e) {}
    };

    // ── Foto del alumno ──────────────────────────────────────────────────────
    function initFotoUpload() {
        const input = document.getElementById('alu_foto_input');
        if (!input || input.dataset.ready) return;
        input.dataset.ready = '1';
        input.addEventListener('change', async () => {
            if (!input.files.length) return;
            const fd = new FormData();
            fd.append('foto', input.files[0]);
            try {
                const resp = await fetch(`${urlBase}/uploadFotoAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('alu_foto_ruta').value = json.path;
                    const preview = document.getElementById('alu_foto_preview');
                    preview.src = `${window.BASE_URL}/${json.path}`;
                    preview.style.display = '';
                } else {
                    alert(json.error || 'No se pudo subir la imagen.');
                }
            } catch (e) {
                alert('Error de conexión al subir la imagen.');
            }
        });
    }

    // ── Abrir modal: Crear / Editar ─────────────────────────────────────────
    function irATabGeneral() {
        const btn = document.getElementById('tab-general-btn');
        if (btn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
        }
    }

    window.abrirModalAlumnoCrear = async function () {
        await cargarCatalogos();
        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        form.reset();
        limpiarTablas();
        idAlumnoActual = null;

        document.getElementById('alu_id').value = '';
        document.getElementById('alu_id_cliente').value = '';
        document.getElementById('alu_cliente_texto').value = '';
        document.getElementById('alu_foto_ruta').value = '';
        const previewCrear = document.getElementById('alu_foto_preview');
        previewCrear.style.display = '';
        previewCrear.src = `${window.BASE_URL}/img/no-image.png`;
        document.getElementById('alu_estado_academico').value = 'activo';

        document.getElementById('tituloModalAlumno').textContent = 'Nuevo Alumno';
        document.getElementById('modalAlertAlumno').classList.add('d-none');
        document.getElementById('btnEliminarAlumnoModal').classList.add('d-none');

        const tabDocBtn = document.getElementById('tab-documentos-btn');
        if (tabDocBtn) tabDocBtn.classList.add('disabled');
        renderDocumentos([]);

        initClienteTypeahead();
        initFotoUpload();
        irATabGeneral();
        getModal()?.show();
    };

    window.abrirModalAlumnoEditar = async function (rowOrData) {
        await cargarCatalogos();
        let base;
        if (rowOrData instanceof HTMLElement) {
            base = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            base = rowOrData;
        }
        if (!base || !base.id) return;

        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        form.reset();
        limpiarTablas();

        try {
            const json = await fetchJson(`${urlBase}/getDetalleAjax?id=${base.id}`);
            if (!json.ok) { alert(json.error || 'No se pudo cargar el alumno.'); return; }
            const d = json.data;
            idAlumnoActual = d.id;

            document.getElementById('alu_id').value = d.id;
            document.getElementById('alu_codigo').value = d.codigo_alumno || '';
            document.getElementById('alu_nombres').value = d.nombres || '';
            document.getElementById('alu_apellidos').value = d.apellidos || '';
            document.getElementById('alu_tipo_id').value = d.tipo_identificacion || '';
            document.getElementById('alu_identificacion').value = d.numero_identificacion || '';
            document.getElementById('alu_fecha_nacimiento').value = d.fecha_nacimiento || '';
            document.getElementById('alu_sexo').value = d.sexo || '';
            document.getElementById('alu_nacionalidad').value = d.nacionalidad || '';
            document.getElementById('alu_estado_academico').value = d.estado_academico || 'activo';
            document.getElementById('alu_observaciones').value = d.observaciones || '';

            document.getElementById('alu_id_cliente').value = d.id_cliente || '';
            document.getElementById('alu_cliente_texto').value = d.representante_nombre
                ? `${d.representante_nombre}${d.representante_identificacion ? ' (' + d.representante_identificacion + ')' : ''}`
                : '';
            document.getElementById('alu_relacion').value = d.relacion_representante || '';
            document.getElementById('alu_punto_emision').value = d.id_punto_emision || '';

            document.getElementById('alu_tipo_sangre').value = d.tipo_sangre || '';
            document.getElementById('alu_alergias').value = d.alergias_condiciones || '';
            document.getElementById('alu_emerg_nombre').value = d.contacto_emergencia_nombre || '';
            document.getElementById('alu_emerg_telefono').value = d.contacto_emergencia_telefono || '';

            document.getElementById('alu_foto_ruta').value = d.foto_ruta || '';
            const preview = document.getElementById('alu_foto_preview');
            preview.style.display = '';
            preview.src = d.foto_ruta ? `${window.BASE_URL}/${d.foto_ruta}` : `${window.BASE_URL}/img/no-image.png`;

            (d.periodos || []).forEach(p => window.aluAgregarFilaPeriodo(p));
            (d.horarios || []).forEach(h => window.aluAgregarFilaHorario(h));
            (d.servicios || []).forEach(s => window.aluAgregarFilaServicio(s));
            renderDocumentos(d.documentos || []);

            document.getElementById('tituloModalAlumno').textContent = 'Editar Alumno';
            document.getElementById('modalAlertAlumno').classList.add('d-none');
            document.getElementById('btnEliminarAlumnoModal').classList.remove('d-none');
            const tabDocBtn = document.getElementById('tab-documentos-btn');
            if (tabDocBtn) tabDocBtn.classList.remove('disabled');

            initClienteTypeahead();
            initFotoUpload();
            irATabGeneral();
            getModal()?.show();
        } catch (e) {
            alert('Error de conexión al cargar el alumno.');
        }
    };

    // ── Guardar / Eliminar ───────────────────────────────────────────────────
    window.guardarAlumnoModal = async function () {
        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        if (!document.getElementById('alu_nombres').value.trim() || !document.getElementById('alu_apellidos').value.trim()) {
            mostrarAlertaAlumno('Nombres y apellidos son obligatorios.', false);
            irATabGeneral();
            return;
        }
        if (!document.getElementById('alu_id_cliente').value) {
            mostrarAlertaAlumno('Debe seleccionar el representante (cliente) que factura al alumno.', false);
            const btn = document.getElementById('tab-representante-btn');
            if (btn && typeof bootstrap !== 'undefined') (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
            return;
        }

        serializarTablas();

        const id = document.getElementById('alu_id').value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarAlumnoModal');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(actionUrl, { method: 'POST', body: fd });
            const json = await resp.json();
            mostrarAlertaAlumno(json.msg || json.error, json.ok);

            if (json.ok) {
                if (!id && json.id) {
                    idAlumnoActual = json.id;
                    document.getElementById('alu_id').value = json.id;
                    document.getElementById('btnEliminarAlumnoModal').classList.remove('d-none');
                    const tabDocBtn = document.getElementById('tab-documentos-btn');
                    if (tabDocBtn) tabDocBtn.classList.remove('disabled');
                    renderDocumentos([]);
                }
                setTimeout(() => {
                    getModal()?.hide();
                    if (window.fetchSearchAlumnos) window.fetchSearchAlumnos();
                }, 900);
            }
        } catch (e) {
            mostrarAlertaAlumno('Error de conexión con el servidor.', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    window.eliminarAlumnoModal = async function () {
        const id = document.getElementById('alu_id')?.value;
        if (!id || !confirm('¿Seguro que desea eliminar este alumno?')) return;
        const btn = document.getElementById('btnEliminarAlumnoModal');

        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchAlumnos) window.fetchSearchAlumnos();
            } else {
                alert(json.error || 'No se pudo eliminar.');
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

    function mostrarAlertaAlumno(msg, ok) {
        const alertEl = document.getElementById('modalAlertAlumno');
        if (!alertEl) return;
        alertEl.textContent = msg || (ok ? 'Guardado correctamente.' : 'Ocurrió un error.');
        alertEl.className = `alert mb-2 py-2 small shadow-sm border-0 ${ok ? 'alert-success' : 'alert-danger'}`;
        alertEl.classList.remove('d-none');
    }

})(window, document);
