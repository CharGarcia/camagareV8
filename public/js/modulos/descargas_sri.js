let clavesDetectadasTxt = [];

// Escucha cuando se selecciona un archivo TXT
document.getElementById('archivo_txt_input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('archivo_txt', file);

    fetch(`${BASE_URL}/modulos/descargas_sri/procesarTxtSriAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            clavesDetectadasTxt = data.claves;
            document.getElementById('txt_total_claves').textContent = data.total;
            document.getElementById('txt_claves_detectadas').classList.remove('d-none');
            
            Swal.fire({
                icon: 'success',
                title: 'TXT procesado',
                text: data.mensaje,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.error, 'error');
            document.getElementById('archivo_txt_input').value = '';
            document.getElementById('txt_claves_detectadas').classList.add('d-none');
            clavesDetectadasTxt = [];
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Problema al procesar el archivo TXT.', 'error');
    });
});

function procesarClaveAcceso() {
    const input = document.getElementById('clave_acceso_input');
    const claveStr = input.value.trim();
    
    if (claveStr === '' || claveStr.length !== 49) {
        Swal.fire('Atención', 'Ingrese una clave de acceso válida de 49 dígitos.', 'warning');
        return;
    }

    const btn = document.getElementById('btnProcesarClaves');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Procesando...`;

    const formData = new FormData();
    formData.append('claves', claveStr);

    fetch(`${BASE_URL}/modulos/descargas_sri/procesarClavesAccesoAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-cloud-download me-1"></i> Procesar Clave`;
        
        if (data.ok) {
            renderResultados(data.resultados);
            input.value = '';
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-cloud-download me-1"></i> Procesar Clave`;
        Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
    });
}

async function iniciarDescargaClavesTxt() {
    if (clavesDetectadasTxt.length === 0) {
        Swal.fire('Atención', 'No hay claves listas para descargar.', 'warning');
        return;
    }

    const btn = document.getElementById('btnDescargarTxtSRI');
    btn.disabled = true;
    
    let procesadas = 0;
    const total = clavesDetectadasTxt.length;

    Swal.fire({
        title: 'Procesando Claves',
        html: `Iniciando descarga de <b>${total}</b> documentos...<br><br><div class="progress"><div id="progreso-sri" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div></div>`,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    for (const clave of clavesDetectadasTxt) {
        try {
            const formData = new FormData();
            formData.append('claves', clave);

            const res = await fetch(`${BASE_URL}/modulos/descargas_sri/procesarClavesAccesoAjax`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                renderResultados(data.resultados);
            }
        } catch (err) {
            console.error(`Error procesando clave ${clave}:`, err);
        }

        procesadas++;
        const porc = Math.round((procesadas / total) * 100);
        const progressBar = document.getElementById('progreso-sri');
        if (progressBar) {
            progressBar.style.width = porc + '%';
            progressBar.textContent = porc + '%';
        }
        Swal.update({
            html: `Procesando: <b>${procesadas}</b> de <b>${total}</b> documentos...<br><br><div class="progress"><div id="progreso-sri" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: ${porc}%">${porc}%</div></div>`
        });
    }

    btn.disabled = false;
    btn.innerHTML = `<i class="bi bi-play-circle me-1"></i> Iniciar Descarga`;
    
    document.getElementById('archivo_txt_input').value = '';
    document.getElementById('txt_claves_detectadas').classList.add('d-none');
    clavesDetectadasTxt = [];
    
    Swal.fire({
        icon: 'success',
        title: 'Proceso Finalizado',
        text: `Se procesaron las ${total} claves del archivo.`,
        timer: 3000
    });
}

function procesarArchivosXml() {
    const input = document.getElementById('archivos_xml_input');
    if (input.files.length === 0) {
        Swal.fire('Atención', 'Seleccione al menos un archivo o carpeta XML.', 'warning');
        return;
    }

    const btn = document.getElementById('btnProcesarXml');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Procesando XMLs...`;

    const formData = new FormData();
    for (let i = 0; i < input.files.length; i++) {
        formData.append('archivos_xml[]', input.files[i]);
    }

    fetch(`${BASE_URL}/modulos/descargas_sri/procesarArchivosXmlAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-upload me-1"></i> Procesar XMLs`;
        
        if (data.ok) {
            renderResultados(data.resultados);
            input.value = '';
            Swal.fire({
                icon: 'success',
                title: 'Proceso Masivo Finalizado',
                text: `Se procesaron ${data.total} archivos correctamente.`,
                timer: 3000
            });
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-upload me-1"></i> Procesar XMLs`;
        Swal.fire('Error', 'Problema de conexión con el servidor.', 'error');
    });
}

function abrirModalRegistro(clave) {
    const res = resultadosCache[clave];
    if (!res) return;

    Swal.fire({
        title: '¿Registrar documento?',
        text: `Se registrará la ${res.info.tipo_nombre} ${clave} en el sistema.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, registrar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            ejecutarRegistro(clave, res.xml_base64);
        }
    });
}

function ejecutarRegistro(clave, xmlBase64) {
    Swal.fire({
        title: 'Procesando...',
        html: 'Registrando documento en la base de datos',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new FormData();
    formData.append('clave', clave);
    formData.append('xml_base64', xmlBase64);

    fetch(`${BASE_URL}/modulos/descargas_sri/registrarComprobanteAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            Swal.fire({
                icon: 'success',
                title: 'Registrado',
                text: data.mensaje || 'Documento registrado correctamente.',
                timer: 3000
            });
            
            // Actualizar la fila en la tabla de resultados
            const res = resultadosCache[clave];
            if (res) {
                res.info.estado_registro = 'REGISTRADO';
                res.mensaje = data.mensaje;
                renderResultados([res]);
            }
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Problema al conectar con el servidor.', 'error');
    });
}

let resultadosCache = {};

function renderResultados(resultados) {
    const tbody = document.getElementById('tbodyResultados');
    
    if (tbody.querySelector('.fs-2')) {
        tbody.innerHTML = '';
    }

    resultados.forEach(res => {
        resultadosCache[res.clave] = res; 
        
        let tr = document.getElementById(`row-${res.clave}`);
        let isNew = false;
        
        if (!tr) {
            tr = document.createElement('tr');
            tr.id = `row-${res.clave}`;
            isNew = true;
        }
        
        const info = res.info || res;
        const numero = info.numero_documento || '---';
        const tipoDoc = info.tipo_nombre || 'Documento';
        const emisor = info.emisor || '';
        const total = info.total ? parseFloat(info.total).toLocaleString('en-US', { style: 'currency', currency: 'USD' }) : '$0.00';
        
        // Estado (Registrado / No registrado / Ignorado)
        let regClass = 'bg-danger';
        let regText = 'No registrado';
        
        if (info.estado_registro === 'REGISTRADO') {
            regClass = 'bg-success';
            regText = 'Registrado';
        } else if (info.estado_registro === 'IGNORADO') {
            regClass = 'bg-dark';
            regText = 'Ignorado';
        }

        const badgeEstado = `<span class="badge ${regClass} bg-opacity-10 border border-${regClass.split('-')[1]} border-opacity-25" style="color: var(--bs-${regClass.split('-')[1]}) !important; width: 110px;">${regText}</span>`;

        // Botón descarga XML
        let btnDownload = '';
        if (res.xml_base64) {
            btnDownload = `<a href="data:application/xml;base64,${res.xml_base64}" download="${res.clave}.xml" class="btn btn-outline-success btn-xs p-1 py-0 ms-2" title="Descargar XML"><i class="bi bi-download"></i></a>`;
        }

        tr.innerHTML = `
            <td class="ps-4 py-2">
                <div class="d-flex flex-column">
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 x-small" style="width: fit-content;">${tipoDoc.toUpperCase()}</span>
                    <div class="small text-muted mt-1" style="font-family: monospace; font-size: 0.7rem;">${res.clave}</div>
                </div>
            </td>
            <td class="py-2 fw-bold text-dark">${numero}</td>
            <td class="py-2 small text-muted">
                <i class="bi bi-person me-1"></i>${emisor}
            </td>
            <td class="py-2 text-end fw-bold text-dark">${total}</td>
            <td class="py-2 text-center">${badgeEstado}</td>
            <td class="pe-4 small text-muted py-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div style="max-width: 300px;">
                        <div class="fw-bold x-small ${info.estado_registro === 'ERROR' ? 'text-danger' : (info.estado_registro === 'IGNORADO' ? 'text-muted' : 'text-primary')} mb-1">${info.estado_registro || ''}</div>
                        <div class="p-2 rounded-2 border shadow-sm x-small ${info.estado_registro === 'ERROR' ? 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25' : (info.estado_registro === 'IGNORADO' ? 'bg-light text-muted border-secondary border-opacity-10' : 'bg-white text-dark border-light')}" style="font-size: 0.75rem; line-height: 1.2;">
                            <i class="bi ${info.estado_registro === 'ERROR' ? 'bi-exclamation-triangle' : (info.estado_registro === 'IGNORADO' ? 'bi-slash-circle' : 'bi-info-circle')} me-1"></i>
                            ${res.mensaje}
                        </div>
                    </div>
                    <div class="d-flex">
                        ${btnDownload}
                    </div>
                </div>
            </td>
        `;
        if (isNew) {
            tbody.prepend(tr);
        }
    });
}

// --- DOCUMENTOS IGNORADOS (LISTA NEGRA) ---

function listarDocumentosIgnorados() {
    const tbody = document.getElementById('tbodyIgnorados');
    if (!tbody) return;

    fetch(`${BASE_URL}/modulos/descargas_sri/listarDocumentosIgnoradosAjax`)
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay documentos en la lista negra.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(d => `
                <tr>
                    <td class="ps-3 py-2 small">${d.fecha_documento || '---'}</td>
                    <td class="py-2 small font-monospace" style="font-size: 0.7rem;">${d.clave_acceso}</td>
                    <td class="py-2 small fw-bold">${d.nombre_proveedor || '---'}</td>
                    <td class="py-2 small text-muted">${d.observaciones || '---'}</td>
                    <td class="py-2 small">${d.created_at}</td>
                    <td class="py-2 text-center">
                        <button type="button" class="btn btn-link btn-xs text-danger p-0" onclick="eliminarDocumentoIgnorado(${d.id})" title="Eliminar de la lista">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
    })
    .catch(err => {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error al cargar la lista.</td></tr>';
    });
}

// Auto-extracción de datos desde la clave de acceso
document.getElementById('ignorado_clave_input')?.addEventListener('input', function(e) {
    const val = e.target.value.trim();
    if (val.length === 49) {
        // 1. Extraer Fecha (DDMMYYYY) -> YYYY-MM-DD
        const dia = val.substring(0, 2);
        const mes = val.substring(2, 4);
        const anio = val.substring(4, 8);
        const fecha = `${anio}-${mes}-${dia}`;
        
        // Guardar temporalmente en el dataset para usar al guardar
        this.dataset.fechaAuto = fecha;

        // 2. Extraer RUC (dígitos 11-23)
        const ruc = val.substring(10, 23);

        // 3. Extraer Número de Documento (Estab + PtoEmi + Secuencial)
        const estab = val.substring(24, 27);
        const pto   = val.substring(27, 30);
        const secu  = val.substring(30, 39);
        const numDoc = `${estab}-${pto}-${secu}`;
        this.dataset.numDocAuto = numDoc;

        // 4. Intentar buscar proveedor por RUC en el sistema
        fetch(`${BASE_URL}/modulos/proveedores/getProveedoresAjax?q=${ruc}`)
        .then(res => res.json())
        .then(data => {
            const items = data.data || data.rows || [];
            const obsInput = document.getElementById('ignorado_obs_input');
            const provName = items.length > 0 ? (items[0].razon_social || items[0].nombre) : `RUC ${ruc}`;
            
            this.dataset.nombreAuto = provName;
            
            if (obsInput && !obsInput.value) {
                obsInput.value = `${numDoc} - ${provName} (${fecha})`;
            }
        });
    }
});

function agregarDocumentoIgnorado() {
    const inputClave = document.getElementById('ignorado_clave_input');
    const clave = inputClave.value.trim();
    const obs   = document.getElementById('ignorado_obs_input').value.trim();

    if (clave.length !== 49) {
        Swal.fire('Atención', 'La clave de acceso debe tener 49 dígitos.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('clave_acceso', clave);
    formData.append('observaciones', obs);
    formData.append('nombre_proveedor', inputClave.dataset.nombreAuto || '');
    formData.append('fecha_documento', inputClave.dataset.fechaAuto || '');

    fetch(`${BASE_URL}/modulos/descargas_sri/agregarDocumentoIgnoradoAjax`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Agregado', text: data.mensaje, timer: 2000, showConfirmButton: false });
            inputClave.value = '';
            delete inputClave.dataset.nombreAuto;
            delete inputClave.dataset.fechaAuto;
            document.getElementById('ignorado_obs_input').value = '';
            listarDocumentosIgnorados();
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Problema al conectar con el servidor.', 'error');
    });
}

function eliminarDocumentoIgnorado(id) {
    Swal.fire({
        title: '¿Eliminar de la lista negra?',
        text: 'Este documento podrá ser procesado nuevamente en el futuro.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('id', id);

            fetch(`${BASE_URL}/modulos/descargas_sri/eliminarDocumentoIgnoradoAjax`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: data.mensaje, timer: 2000, showConfirmButton: false });
                    listarDocumentosIgnorados();
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Problema al conectar con el servidor.', 'error');
            });
        }
    });
}


// =============================================================================
// DESCARGA AUTOMÁTICA
// =============================================================================

function cargarConfigDescarga() {
    // El historial se carga aparte, NO dentro de la cadena de la configuración:
    // si la empresa no tiene configuración (data.ok = false) o el fetch falla,
    // la cadena se cortaba antes y la tabla quedaba en "Cargando historial...".
    cargarHistorialDescargas();

    fetch(`${BASE_URL}/modulos/descargas_sri/obtenerConfigDescargaAjax`)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const c = data.config;

            // sri_usuario viene prellenado desde PHP (RUC de la empresa activa)

            // Radio estado
            const radioActivo   = document.getElementById('auto_estado_activo');
            const radioInactivo = document.getElementById('auto_estado_inactivo');
            if (radioActivo && radioInactivo) {
                radioActivo.checked   = c.estado === 'activo';
                radioInactivo.checked = c.estado !== 'activo';
            }

            // Badge de estado junto al título del acordeón de configuración
            actualizarBadgeEstado(c.estado);

            // Badge clave guardada
            const badge = document.getElementById('auto_clave_guardada_badge');
            if (badge) badge.classList.toggle('d-none', !c.sri_clave_guardada);

            // Última ejecución
            renderUltimoEstado(c);
        })
        .catch(err => console.error('Error cargando config descarga:', err));
}

function actualizarBadgeEstado(estado) {
    const badge = document.getElementById('auto_estado_badge');
    if (!badge) return;
    const activo = estado === 'activo';
    badge.textContent = activo ? 'Activo' : 'Inactivo';
    badge.className = 'badge ms-2 ' + (activo
        ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25'
        : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25');
    badge.style.fontSize = '0.7rem';
}

function renderUltimoEstado(c) {
    const card = document.getElementById('auto_ultimo_estado_card');
    if (!card) return;

    if (!c.ultima_descarga) {
        card.innerHTML = '<p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Sin ejecuciones registradas todavía.</p>';
        return;
    }

    const estadoClass = c.ultimo_estado === 'completado'
        ? 'bg-success text-success border-success'
        : c.ultimo_estado === 'parcial'
            ? 'bg-warning text-warning border-warning'
            : 'bg-danger text-danger border-danger';

    card.innerHTML = `
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <div class="x-small text-muted">Última ejecución</div>
                <div class="fw-bold small">${c.ultima_descarga || '---'}</div>
            </div>
            <div>
                <span class="badge bg-opacity-10 border border-opacity-25 ${estadoClass}" style="font-size:0.75rem;">
                    ${(c.ultimo_estado || 'desconocido').toUpperCase()}
                </span>
            </div>
        </div>
        ${c.ultimo_mensaje ? `<div class="mt-2 small text-muted border-top pt-2">${c.ultimo_mensaje}</div>` : ''}
    `;
}

function guardarConfigDescarga() {
    const usuario = document.getElementById('auto_sri_usuario').value.trim();
    const clave   = document.getElementById('auto_sri_clave').value;
    const estado  = document.querySelector('input[name="auto_estado"]:checked')?.value || 'inactivo';

    if (!usuario) {
        Swal.fire('Atención', 'Debe ingresar el usuario SRI en Línea.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('sri_usuario', usuario);
    formData.append('sri_clave', clave);
    formData.append('estado', estado);
    // El tipo de documento ya no se configura aquí; se elige por ejecución en la descarga semiautomática.
    formData.append('tipos_documento', 'todos');

    fetch(`${BASE_URL}/modulos/descargas_sri/guardarConfigDescargaAjax`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Guardado', text: data.mensaje, timer: 2500, showConfirmButton: false });
            document.getElementById('auto_sri_clave').value = '';
            cargarConfigDescarga();
        } else {
            Swal.fire('Error', data.error || 'No se pudo guardar.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Problema de conexión.', 'error');
    });
}

function toggleVerClave() {
    const input = document.getElementById('auto_sri_clave');
    const icono = document.getElementById('iconoOjoClave');
    if (!input || !icono) return;
    if (input.type === 'password') {
        input.type = 'text';
        icono.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icono.className = 'bi bi-eye';
    }
}

function cargarHistorialDescargas() {
    const tbody = document.getElementById('tbodyHistorialDescargas');
    if (!tbody) return;

    const limite = document.getElementById('historial_limite')?.value || 20;
    fetch(`${BASE_URL}/modulos/descargas_sri/historialDescargasAjax?limite=${limite}`)
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.data.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Sin registros de descargas todavía.</td></tr>';
                return;
            }

            tbody.innerHTML = data.data.map(row => {
                const estadoClass = row.estado === 'completado'
                    ? 'bg-success text-success border-success'
                    : row.estado === 'error'
                        ? 'bg-danger text-danger border-danger'
                        : 'bg-warning text-warning border-warning';

                const origenIcon = row.origen === 'manual'
                    ? '<i class="bi bi-person-fill text-primary" title="Manual"></i>'
                    : '<i class="bi bi-robot text-muted" title="Automático (cron)"></i>';

                return `<tr>
                    <td class="ps-3 py-1" style="white-space:nowrap;">${row.fecha_proceso}</td>
                    <td class="py-1" style="white-space:nowrap;">${row.fecha_desde} → ${row.fecha_hasta}</td>
                    <td class="py-1 text-center fw-bold text-success">${row.total_nuevos}</td>
                    <td class="py-1 text-center text-muted">${row.total_existentes}</td>
                    <td class="py-1 text-center ${row.total_errores > 0 ? 'text-danger fw-bold' : 'text-muted'}">${row.total_errores}</td>
                    <td class="py-1 text-center">
                        <span class="badge bg-opacity-10 border border-opacity-25 ${estadoClass}" style="font-size:0.7rem;">${row.estado}</span>
                    </td>
                    <td class="py-1 text-center">${origenIcon}</td>
                    <td class="py-1 text-center pe-2">
                        <button class="btn btn-outline-secondary btn-xs py-0 px-1" title="Ver detalle" onclick="verDetalleLog(${row.id})">
                            <i class="bi bi-zoom-in"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-danger">Error al cargar el historial.</td></tr>';
        });
}

function verDetalleLog(idLog) {
    fetch(`${BASE_URL}/modulos/descargas_sri/detalleLogAjax?id=${idLog}`)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.error); return; }

            const log     = data.log;
            const detalle = data.detalle || {};
            const claves  = detalle.claves || [];
            const debug   = detalle.debug  || [];

            const estadoBadge = log.estado === 'completado'
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">completado</span>'
                : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">error</span>';

            const filasClaves = claves.length
                ? claves.map(c => {
                    const est = (c.estado || '').toUpperCase();
                    const estadoColor = est === 'REGISTRADO' ? 'text-success'
                                      : (est === 'YA ESTABA REGISTRADO' || est === 'YA_EXISTE' || est === 'EXISTENTE') ? 'text-muted'
                                      : est === 'IGNORADO' ? 'text-warning'
                                      : 'text-danger';
                    const monto = (c.total !== null && c.total !== undefined && c.total !== '')
                        ? '$' + Number(c.total).toFixed(2) : '';
                    // Los logs antiguos solo guardaban la clave; si no hay número de
                    // documento se muestra la clave para no dejar la celda vacía.
                    const doc = c.numero
                        ? `<div class="fw-semibold">${escHtml(c.numero)}</div>
                           ${c.tipo ? `<div class="text-muted" style="font-size:0.68rem;">${escHtml(c.tipo)}</div>` : ''}`
                        : `<div class="text-break" style="font-size:0.68rem;">${escHtml(c.clave || '—')}</div>`;

                    return `<tr>
                        <td class="py-1 small" style="max-width:150px;">${doc}</td>
                        <td class="py-1 small text-truncate" style="max-width:150px;" title="${escHtml(c.emisor || '')}">${escHtml(c.emisor || '—')}</td>
                        <td class="py-1 small text-end text-nowrap">${monto}</td>
                        <td class="py-1 small fw-bold ${estadoColor}">${escHtml(c.estado || '—')}</td>
                        <td class="py-1 small text-muted">${escHtml(c.msg || '')}</td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="5" class="text-center py-3 text-muted small">Sin documentos procesados.</td></tr>';

            const debugHtml = debug.length
                ? `<div class="mt-3 border-top pt-3">
                    <div class="small fw-bold text-muted mb-1"><i class="bi bi-terminal me-1"></i>Log del scraper</div>
                    <pre class="bg-dark text-light rounded p-2 small" style="max-height:150px;overflow-y:auto;font-size:0.7rem;">${debug.map(l => escHtml(l)).join('\n')}</pre>
                   </div>`
                : '';

            let fotoHtml = '';
            const screenshotLog = debug.find(l => l.includes('📸 Screenshot:'));
            if (screenshotLog) {
                const filename = screenshotLog.split('📸 Screenshot:')[1].trim();
                // Determinar el path publico asumiendo estructura estandar
                const fotoUrl = window.location.href.includes('/public/') 
                    ? `${BASE_URL}/sri_debug/${filename}` 
                    : `${window.location.origin}/sri_debug/${filename}`; // o depender de BASE_URL

                fotoHtml = `
                    <div class="mt-3 border-top pt-3">
                        <div class="small fw-bold text-primary mb-2"><i class="bi bi-camera text-primary me-1"></i>Evidencia fotográfica del portal SRI</div>
                        <a href="${BASE_URL}/sri_debug/${filename}" target="_blank" class="d-block border rounded p-1 bg-light text-center" style="text-decoration:none;">
                            <img src="${BASE_URL}/sri_debug/${filename}" class="img-fluid rounded" style="max-height: 350px; object-fit: contain;" alt="Evidencia">
                            <div class="small text-primary mt-1"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar foto original</div>
                        </a>
                    </div>
                `;
            }

            const errorHtml = detalle.error
                ? `<div class="alert alert-danger py-2 small mt-2"><i class="bi bi-exclamation-triangle me-1"></i>${escHtml(detalle.error)}</div>`
                : '';

            Swal.fire({
                title: `Detalle del log #${idLog}`,
                width: '900px',
                html: `
                    <div class="text-start">
                        <div class="d-flex flex-wrap gap-3 mb-3 small">
                            <div><span class="text-muted">Fecha:</span> <strong>${log.fecha_proceso_fmt || log.fecha_proceso}</strong></div>
                            <div><span class="text-muted">Período:</span> <strong>${log.fecha_desde} → ${log.fecha_hasta}</strong></div>
                            <div><span class="text-muted">Estado:</span> ${estadoBadge}</div>
                            <div><span class="text-muted">Duración:</span> <strong>${log.duracion_seg ?? '—'}s</strong></div>
                            <div><span class="text-muted">Origen:</span> <strong>${log.origen}</strong></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col text-center p-2 bg-success bg-opacity-10 rounded">
                                <div class="fw-bold text-success fs-5">${log.total_nuevos}</div>
                                <div class="small text-muted">Nuevos</div>
                            </div>
                            <div class="col text-center p-2 bg-secondary bg-opacity-10 rounded">
                                <div class="fw-bold text-secondary fs-5">${log.total_existentes}</div>
                                <div class="small text-muted">Ya existían</div>
                            </div>
                            <div class="col text-center p-2 bg-warning bg-opacity-10 rounded">
                                <div class="fw-bold text-warning fs-5">${log.total_ignorados}</div>
                                <div class="small text-muted">Ignorados</div>
                            </div>
                            <div class="col text-center p-2 bg-danger bg-opacity-10 rounded">
                                <div class="fw-bold text-danger fs-5">${log.total_errores}</div>
                                <div class="small text-muted">Errores</div>
                            </div>
                        </div>
                        ${errorHtml}
                        <div class="table-responsive border rounded" style="max-height:220px;overflow-y:auto;">
                            <table class="table table-sm mb-0" style="font-size:0.78rem;">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th class="py-1 ps-2">Documento</th>
                                        <th class="py-1">Emisor</th>
                                        <th class="py-1 text-end">Total</th>
                                        <th class="py-1">Estado</th>
                                        <th class="py-1">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>${filasClaves}</tbody>
                            </table>
                        </div>
                        ${fotoHtml}
                        ${debugHtml}
                    </div>`,
                confirmButtonText: 'Cerrar',
                showClass: { popup: '' },
            });
        })
        .catch(err => { console.error(err); alert('Error al cargar el detalle.'); });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}


// Marca la empresa activa como "pendiente de login" y abre el portal del SRI.
// La extensión detecta el login, escribe RUC+clave y navega a Comprobantes recibidos.
function generarDescargaSri() {
    const btn = document.getElementById('btnGenerarDescargaSri');
    if (btn) btn.disabled = true;
    fetch(`${BASE_URL}/modulos/descargas_sri/marcarLoginPendienteAjax`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (!data.ok) {
                Swal.fire('Atención', data.error || 'No se pudo iniciar la descarga.', 'warning');
                return;
            }
            window.open('https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf', '_blank');
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            console.error(err);
            Swal.fire('Error', 'Problema de conexión con el sistema.', 'error');
        });
}

// "Descarga Automática" es ahora la pestaña activa por defecto. Antes su
// configuración e historial se cargaban con el onclick de la pestaña; al no
// haber clic inicial, hay que dispararlo al abrir la página.
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('tab-auto') && typeof cargarConfigDescarga === 'function') {
        cargarConfigDescarga();
    }
});
