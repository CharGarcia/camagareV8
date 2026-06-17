/**
 * JS para Módulo Plantillas WhatsApp
 */
const WA_URL = window.WA_URL_BASE || '';

// Sobrescribimos el fetchSearch que pusimos en la vista
window.fetchSearch = async (page = 1) => {
    const term = document.getElementById('buscarPlantilla') ? document.getElementById('buscarPlantilla').value.trim() : '';
    window.currentPage = page;
    
    // Lógica de flechas para el ordenamiento visual
    document.querySelectorAll('.sortable-header').forEach(th => {
        const icon = th.querySelector('i');
        const field = th.dataset.sort;
        if (field === window.currentSort) {
            icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
        } else {
            icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
        }
    });

    try {
        const url = new URL(WA_URL + '/searchAjax', window.location.origin);
        url.searchParams.append('page', page);
        url.searchParams.append('q', term);
        url.searchParams.append('sort', window.currentSort);
        url.searchParams.append('dir', window.currentDir);

        const response = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.ok) {
            document.getElementById('tbodyPlantillas').innerHTML = data.rows;
            document.getElementById('paginationContainer').innerHTML = data.pagination;
            document.getElementById('paginationInfo').innerText = data.info;
        } else {
            console.error(data.error);
        }
    } catch (e) {
        console.error("Error cargando plantillas:", e);
    }
};

function WA_sincronizarPlantillas() {
    Swal.fire({
        title: 'Sincronizando',
        text: 'Conectando con Meta para obtener plantillas...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(WA_URL + '/sincronizarAjax', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            Swal.fire('¡Éxito!', data.mensaje, 'success').then(() => {
                fetchSearch(1);
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo sincronizar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Error de red al sincronizar.', 'error');
    });
}

function WA_abrirModalCrear() {
    const modal = new bootstrap.Modal(document.getElementById('modalCrearPlantilla'));
    document.getElementById('formCrearPlantilla').reset();
    document.getElementById('divPdfEjemplo').classList.add('d-none');
    document.getElementById('inputPdfEjemplo').removeAttribute('required');
    modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    // Escuchar el cambio en el select del tipo de cabecera
    const selectTipoCabecera = document.getElementById('selectTipoCabecera');
    if (selectTipoCabecera) {
        selectTipoCabecera.addEventListener('change', (e) => {
            const divPdf = document.getElementById('divPdfEjemplo');
            const inputPdf = document.getElementById('inputPdfEjemplo');
            if (e.target.value === 'DOCUMENT') {
                divPdf.classList.remove('d-none');
                inputPdf.setAttribute('required', 'required');
            } else {
                divPdf.classList.add('d-none');
                inputPdf.removeAttribute('required');
                inputPdf.value = '';
            }
        });
    }

    // Submit del formulario
    const formCrear = document.getElementById('formCrearPlantilla');
    if (formCrear) {
        formCrear.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(formCrear);
            const tipoCreacion = document.querySelector('input[name="tipo_creacion"]:checked')?.value;
            const cuerpo = formData.get('cuerpo') || '';

            if (!tipoCreacion) {
                Swal.fire('Advertencia', 'Debe seleccionar el tipo de plantilla (Rápida o Libre).', 'warning');
                return;
            }

            if (tipoCreacion === 'libre') {
                if (/\{\{\d+\}\}/.test(cuerpo)) {
                    Swal.fire('Error', 'Las plantillas libres no pueden contener variables automáticas ({{1}}, {{2}}, etc.).', 'error');
                    return;
                }
            } else if (tipoCreacion === 'rapida') {
                const tipoRapida = document.getElementById('selectPlantillaRapida').value;
                if (!tipoRapida || !PLANTILLAS_RAPIDAS[tipoRapida]) {
                    Swal.fire('Error', 'Debe seleccionar una plantilla rápida válida.', 'error');
                    return;
                }
                const permitidas = (PLANTILLAS_RAPIDAS[tipoRapida].variables || []).map(v => v.id);
                const encontradas = cuerpo.match(/\{\{\d+\}\}/g) || [];
                for (let v of encontradas) {
                    if (!permitidas.includes(v)) {
                        Swal.fire('Error', `La variable ${v} no está permitida en esta plantilla rápida. Solo puedes usar: ${permitidas.length ? permitidas.join(', ') : 'Ninguna'}`, 'error');
                        return;
                    }
                }
                formData.append('plantilla_rapida', tipoRapida);
            }

            const btn = document.getElementById('btnGuardarPlantilla');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
            btn.disabled = true;

            fetch(WA_URL + '/store', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modalCrearPlantilla')).hide();
                    Swal.fire('¡Éxito!', data.mensaje, 'success').then(() => {
                        fetchSearch(1);
                    });
                } else {
                    Swal.fire('Error', data.error || 'No se pudo crear la plantilla.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de red al enviar.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });
    }

    // Carga inicial
    fetchSearch(1);

    // Radio buttons de tipo de creación
    const radiosTipo = document.querySelectorAll('input[name="tipo_creacion"]');
    const contenedorRapidas = document.getElementById('contenedorPlantillasRapidas');
    const contenedorResto = document.getElementById('contenedorRestoFormulario');
    const selectRapida = document.getElementById('selectPlantillaRapida');
    const cuerpoPlantilla = document.getElementById('cuerpoPlantilla');
    const helpCuerpo = document.getElementById('helpCuerpoPlantilla');
    const divBotonesVariables = document.getElementById('botonesVariablesRapidas');
    
    // Configuración de plantillas rápidas centralizada
    const PLANTILLAS_RAPIDAS = {
        'aviso_mensajes_pendientes': {
            nombre: 'aviso_mensajes_pendientes',
            categoria: 'UTILITY',
            cabecera: 'NONE',
            descripcion: 'Sirve para avisar a un número de WhatsApp externo sobre los chats que están pendientes de revisar dentro de la bandeja de entrada del sistema.',
            texto: 'Hola, tienes {{1}} mensajes pendientes desde hace {{2}} minutos.',
            variables: [
                { id: '{{1}}', label: 'Número de Mensajes' },
                { id: '{{2}}', label: 'Tiempo Transcurrido (min)' }
            ]
        },
        'factura_por_cobrar': {
            nombre: 'factura_por_cobrar',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar el saldo pendiente de pago de una factura de venta, adjuntando obligatoriamente el documento (PDF).',
            texto: 'Hola {{1}}, le recordamos que tiene un valor pendiente de pago de {{2}} correspondiente a la factura {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Valor Pendiente de Pago' },
                { id: '{{3}}', label: 'Número de Factura' }
            ]
        },
        'factura_venta': {
            nombre: 'factura_venta',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar al cliente su factura de venta, requiriendo adjuntar el PDF de la factura original.',
            texto: 'Hola {{1}}, adjunto enviamos su factura número {{2}} por un valor total de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Número de Factura' },
                { id: '{{3}}', label: 'Valor Total de la Factura' }
            ]
        },
        'cuenta_por_cobrar': {
            nombre: 'cuenta_por_cobrar',
            categoria: 'UTILITY',
            cabecera: 'NONE',
            descripcion: 'Sirve para enviar a los clientes su saldo pendiente de pago consolidado hasta el momento.',
            texto: 'Hola {{1}}, le informamos que su saldo pendiente de pago hasta el momento es de {{2}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Total por Cobrar' }
            ]
        },
        'renovacion_suscripcion': {
            nombre: 'renovacion_suscripcion',
            categoria: 'UTILITY',
            cabecera: 'NONE',
            descripcion: 'Sirve para enviar avisos a los clientes sobre las próximas renovaciones de sus suscripciones.',
            texto: 'Hola {{1}}, le recordamos que la fecha de renovación de su suscripción es el {{2}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Fecha de Renovación' }
            ]
        },
        'renovacion_firma_electronica': {
            nombre: 'renovacion_firma_electronica',
            categoria: 'UTILITY',
            cabecera: 'NONE',
            descripcion: 'Sirve para avisar a los clientes que han sacado firmas electrónicas sobre la próxima caducidad de su firma.',
            texto: 'Hola {{1}}, le recordamos que su firma electrónica caduca el {{2}}. Comuníquese con nosotros para renovarla.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Fecha de Vencimiento' }
            ]
        },
        'retencion_compra': {
            nombre: 'retencion_compra',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar al proveedor el comprobante de retención, adjuntando el PDF.',
            texto: 'Hola {{1}}, adjunto enviamos su comprobante de retención número {{2}} por un valor de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Proveedor' },
                { id: '{{2}}', label: 'Número de Retención' },
                { id: '{{3}}', label: 'Valor Retenido' }
            ]
        },
        'nota_credito': {
            nombre: 'nota_credito',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar al cliente su nota de crédito, adjuntando el PDF.',
            texto: 'Hola {{1}}, adjunto enviamos su nota de crédito número {{2}} por un valor de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Número de Nota de Crédito' },
                { id: '{{3}}', label: 'Valor Total' }
            ]
        },
        'nota_debito': {
            nombre: 'nota_debito',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar al cliente su nota de débito, adjuntando el PDF.',
            texto: 'Hola {{1}}, adjunto enviamos su nota de débito número {{2}} por un valor de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Cliente' },
                { id: '{{2}}', label: 'Número de Nota de Débito' },
                { id: '{{3}}', label: 'Valor Total' }
            ]
        },
        'guia_remision': {
            nombre: 'guia_remision',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar la guía de remisión, adjuntando el PDF.',
            texto: 'Hola {{1}}, adjunto enviamos la guía de remisión número {{2}} correspondiente a {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Destinatario' },
                { id: '{{2}}', label: 'Número de Guía' },
                { id: '{{3}}', label: 'Motivo / Referencia' }
            ]
        },
        'rol_pagos': {
            nombre: 'rol_pagos',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para enviar al empleado su rol de pagos, adjuntando el PDF.',
            texto: 'Hola {{1}}, adjunto enviamos tu rol de pagos correspondiente al periodo {{2}} por un valor a recibir de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Empleado' },
                { id: '{{2}}', label: 'Periodo / Mes' },
                { id: '{{3}}', label: 'Valor a Recibir' }
            ]
        },
        'descuento_empleado': {
            nombre: 'descuento_empleado',
            categoria: 'UTILITY',
            cabecera: 'DOCUMENT',
            descripcion: 'Sirve para notificar al empleado sobre un descuento, adjuntando el soporte en PDF.',
            texto: 'Hola {{1}}, adjunto enviamos el detalle del descuento por concepto de {{2}} por un valor de {{3}}.',
            variables: [
                { id: '{{1}}', label: 'Nombre del Empleado' },
                { id: '{{2}}', label: 'Concepto del Descuento' },
                { id: '{{3}}', label: 'Valor a Descontar' }
            ]
        }
    };

    window.insertarVariable = function(variableStr) {
        if (!cuerpoPlantilla) return;
        const startPos = cuerpoPlantilla.selectionStart;
        const endPos = cuerpoPlantilla.selectionEnd;
        cuerpoPlantilla.value = cuerpoPlantilla.value.substring(0, startPos)
            + variableStr
            + cuerpoPlantilla.value.substring(endPos, cuerpoPlantilla.value.length);
        cuerpoPlantilla.focus();
        cuerpoPlantilla.selectionStart = startPos + variableStr.length;
        cuerpoPlantilla.selectionEnd = startPos + variableStr.length;
    };

    radiosTipo.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const form = document.getElementById('formCrearPlantilla');
            
            form.nombre.value = '';
            form.cuerpo.value = '';
            if (selectRapida) selectRapida.value = '';
            if (divBotonesVariables) divBotonesVariables.innerHTML = '';
            
            if (e.target.value === 'rapida') {
                contenedorRapidas.style.display = 'block';
                contenedorResto.style.display = 'none';
                helpCuerpo.innerHTML = 'Puedes modificar el texto, pero solo usar las variables mostradas arriba.';
            } else {
                contenedorRapidas.style.display = 'none';
                contenedorResto.style.display = 'block';
                form.nombre.readOnly = false;
                helpCuerpo.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> IMPORTANTE:</span> Las plantillas libres no admiten variables del sistema ({{1}}, {{2}}, etc.). Si las incluyes, el sistema rechazará la plantilla.';
            }
        });
    });

    if (selectRapida) {
        selectRapida.addEventListener('change', (e) => {
            const val = e.target.value;
            const form = document.getElementById('formCrearPlantilla');
            const selectCabecera = document.getElementById('selectTipoCabecera');
            const helpPlantillaRapida = document.getElementById('helpPlantillaRapida');
            divBotonesVariables.innerHTML = '';
            
            if (PLANTILLAS_RAPIDAS[val]) {
                const conf = PLANTILLAS_RAPIDAS[val];
                form.nombre.value = conf.nombre;
                form.nombre.readOnly = true; 
                
                form.categoria.value = conf.categoria;
                selectCabecera.value = conf.cabecera;
                form.cuerpo.value = conf.texto;
                
                if (conf.descripcion && helpPlantillaRapida) {
                    helpPlantillaRapida.innerHTML = `<i class="fas fa-info-circle me-1"></i> <strong>Explicación:</strong> ${conf.descripcion}`;
                }

                contenedorResto.style.display = 'block';
                
                if (conf.variables && conf.variables.length > 0) {
                    let htmlBotones = '<div class="d-flex flex-wrap gap-2 mb-2">';
                    conf.variables.forEach(v => {
                        htmlBotones += `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="insertarVariable('${v.id}')"><i class="fas fa-plus me-1"></i>${v.id} ${v.label}</button>`;
                    });
                    htmlBotones += '</div>';
                    divBotonesVariables.innerHTML = htmlBotones;
                }

                selectCabecera.dispatchEvent(new Event('change'));
            } else {
                contenedorResto.style.display = 'none';
                if (helpPlantillaRapida) {
                    helpPlantillaRapida.innerHTML = '<i class="fas fa-info-circle me-1"></i> Al seleccionar, se pre-llenará el formulario con el nombre y parámetros permitidos.';
                }
            }
        });
    }
});

function WA_verDetalles(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetallesPlantilla'));
    modal.show();

    const contenedor = document.getElementById('divDetallesPlantilla');
    contenedor.innerHTML = `
        <div class="text-center text-muted py-4">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <p class="mb-0">Cargando detalles...</p>
        </div>
    `;

    fetch(WA_URL + '/getDetallesAjax?id=' + id, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            contenedor.innerHTML = data.html;
        } else {
            contenedor.innerHTML = `<div class="alert alert-danger">${data.error || 'No se pudo cargar la información'}</div>`;
        }
    })
    .catch(err => {
        console.error(err);
        contenedor.innerHTML = `<div class="alert alert-danger">Error de red al cargar los detalles.</div>`;
    });
}

function WA_abrirModalProbar(id) {
    document.getElementById('testIdPlantilla').value = id;
    const form = document.getElementById('formProbarEnvio');
    form.reset();
    
    const divVariables = document.getElementById('divFormVariables');
    divVariables.classList.remove('d-none');
    divVariables.innerHTML = '<div class="text-center text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Cargando requerimientos...</div>';

    const modal = new bootstrap.Modal(document.getElementById('modalProbarEnvio'));
    modal.show();

    fetch(WA_URL + '/getFormularioPruebaAjax?id=' + id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            divVariables.innerHTML = data.html;
            if (data.html.trim() === '') {
                divVariables.classList.add('d-none');
            }
        } else {
            divVariables.innerHTML = `<div class="alert alert-danger mb-0">${data.error}</div>`;
        }
    })
    .catch(err => {
        console.error(err);
        divVariables.innerHTML = `<div class="alert alert-danger mb-0">Error cargando requerimientos.</div>`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const formProbar = document.getElementById('formProbarEnvio');
    if (formProbar) {
        formProbar.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnEnviarPrueba');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
            btn.disabled = true;

            fetch(WA_URL + '/enviarPruebaAjax', {
                method: 'POST',
                body: new FormData(formProbar),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire('¡Enviado!', data.mensaje || 'El mensaje ha sido enviado.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalProbarEnvio')).hide();
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al enviar.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de red al enviar.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });
    }

    // Modal Editar Plantilla - select cabecera
    const selectEditarTipoCabecera = document.getElementById('editarTipoCabecera');
    if (selectEditarTipoCabecera) {
        selectEditarTipoCabecera.addEventListener('change', (e) => {
            const divPdf = document.getElementById('divEditarPdfEjemplo');
            if (e.target.value === 'DOCUMENT') {
                divPdf.classList.remove('d-none');
            } else {
                divPdf.classList.add('d-none');
            }
        });
    }

    // Submit formEditarPlantilla
    const formEditar = document.getElementById('formEditarPlantilla');
    if (formEditar) {
        formEditar.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnActualizarPlantilla');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
            btn.disabled = true;

            const formData = new FormData(formEditar);

            fetch(WA_URL + '/update', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    Swal.fire('¡Éxito!', data.mensaje, 'success').then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarPlantilla')).hide();
                        fetchSearch(window.currentPage);
                    });
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al actualizar.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Error de red al actualizar.', 'error');
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });
    }
});

window.WA_eliminarPlantilla = function(id) {
    Swal.fire({
        title: '¿Eliminar plantilla?',
        html: `<p class="mb-3">Selecciona cómo deseas eliminar esta plantilla:</p>
               <div class="form-check text-start mb-2">
                   <input class="form-check-input" type="radio" name="opcionEliminar" id="opcionSolo" value="0" checked>
                   <label class="form-check-label" for="opcionSolo">
                       <strong>Solo quitar del sistema</strong><br>
                       <small class="text-muted">La plantilla seguirá existiendo en Meta (WhatsApp Business).</small>
                   </label>
               </div>
               <div class="form-check text-start">
                   <input class="form-check-input" type="radio" name="opcionEliminar" id="opcionMeta" value="1">
                   <label class="form-check-label" for="opcionMeta">
                       <strong>Eliminar también en Meta</strong><br>
                       <small class="text-danger">Esto eliminará la plantilla permanentemente de WhatsApp Business. Esta acción no se puede deshacer.</small>
                   </label>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const opcion = document.querySelector('input[name="opcionEliminar"]:checked');
            return opcion ? opcion.value : '0';
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        const eliminarMeta = result.value === '1';

        const formData = new FormData();
        formData.append('id', id);
        formData.append('eliminar_meta', eliminarMeta ? '1' : '0');

        Swal.fire({
            title: 'Eliminando...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(WA_URL + '/destroy', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                Swal.fire('¡Eliminada!', data.mensaje, 'success').then(() => {
                    fetchSearch(window.currentPage);
                });
            } else {
                Swal.fire('Error', data.error || 'No se pudo eliminar la plantilla.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error de red al eliminar.', 'error');
        });
    });
};

window.WA_abrirModalEditar = function(id) {
    document.getElementById('editarIdPlantilla').value = id;
    const form = document.getElementById('formEditarPlantilla');
    form.reset();

    const btn = document.getElementById('btnActualizarPlantilla');
    btn.disabled = true;

    Swal.fire({
        title: 'Cargando...',
        text: 'Obteniendo detalles de la plantilla',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(WA_URL + '/getParaEditarAjax?id=' + id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        Swal.close();
        if (data.ok) {
            const p = data.plantilla;
            document.getElementById('editarNombre').value = p.nombre;
            document.getElementById('editarCategoria').value = p.categoria;
            document.getElementById('editarIdioma').value = p.idioma;
            document.getElementById('editarCuerpo').value = p.cuerpo;
            
            const selectCab = document.getElementById('editarTipoCabecera');
            selectCab.value = p.tipo_cabecera;
            selectCab.dispatchEvent(new Event('change'));

            btn.disabled = false;
            
            const modal = new bootstrap.Modal(document.getElementById('modalEditarPlantilla'));
            modal.show();
        } else {
            Swal.fire('Error', data.error || 'No se pudo cargar la información.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.close();
        Swal.fire('Error', 'Error de red al cargar.', 'error');
    });
};

