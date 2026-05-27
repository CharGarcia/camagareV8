/**
 * JS para Módulo Configuración WhatsApp
 */
const WACFG_URL = window.WACFG_URL_BASE || '';

async function WACFG_guardarConfig() {
    const form = document.getElementById('formWaConfig');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    try {
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const resp = await fetch(`${WACFG_URL}/guardarConfiguracion`, {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        if (data.ok) {
            Swal.fire('¡Éxito!', data.mensaje, 'success');
        } else {
            Swal.fire('Error', data.error || 'No se pudo guardar la configuración', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Ocurrió un error en la petición', 'error');
    }
}

async function WACFG_probarConexion() {
    try {
        Swal.fire({
            title: 'Probando conexión...',
            text: 'Conectando con servidores de Meta',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const resp = await fetch(`${WACFG_URL}/probarConexion`);
        const data = await resp.json();

        if (data.ok) {
            Swal.fire('¡Conectado!', data.mensaje, 'success');
        } else {
            Swal.fire('Fallo de conexión', data.error || data.mensaje || 'Error al conectar con WhatsApp', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Ocurrió un error en la petición', 'error');
    }
}
