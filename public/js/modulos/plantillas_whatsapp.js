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
            
            const btn = document.getElementById('btnGuardarPlantilla');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
            btn.disabled = true;

            const formData = new FormData(formCrear);

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

    // Plantillas rápidas
    const selectRapida = document.getElementById('selectPlantillaRapida');
    if (selectRapida) {
        selectRapida.addEventListener('change', (e) => {
            const val = e.target.value;
            const form = document.getElementById('formCrearPlantilla');
            const selectCabecera = document.getElementById('selectTipoCabecera');
            
            if (val === 'factura') {
                form.nombre.value = 'envio_factura';
                form.categoria.value = 'UTILITY';
                selectCabecera.value = 'DOCUMENT';
                form.cuerpo.value = 'Hola {{1}}, adjunto encontrarás tu factura número {{2}} por el monto de {{3}}. Gracias por tu preferencia.';
                // Disparar evento para mostrar el input del PDF
                selectCabecera.dispatchEvent(new Event('change'));
            } else if (val === 'recordatorio') {
                form.nombre.value = 'recordatorio_pago';
                form.categoria.value = 'UTILITY';
                selectCabecera.value = 'NONE';
                form.cuerpo.value = 'Hola {{1}}, te recordamos que tienes un saldo pendiente de {{2}} que vence el {{3}}. Por favor, realiza el pago a la brevedad posible.';
                selectCabecera.dispatchEvent(new Event('change'));
            } else if (val === 'bienvenida') {
                form.nombre.value = 'mensaje_bienvenida';
                form.categoria.value = 'MARKETING';
                selectCabecera.value = 'NONE';
                form.cuerpo.value = '¡Hola {{1}}! Bienvenido a nuestra empresa. Estamos felices de tenerte con nosotros. Si tienes alguna duda, responde este mensaje.';
                selectCabecera.dispatchEvent(new Event('change'));
            } else {
                form.reset();
                selectRapida.value = '';
                selectCabecera.dispatchEvent(new Event('change'));
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
});
