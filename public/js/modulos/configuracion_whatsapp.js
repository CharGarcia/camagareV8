/**
 * JS para Módulo Configuración WhatsApp
 */
const WACFG_URL = window.WACFG_URL_BASE || '';

// ── Caché de números en memoria ───────────────────────────────────────────────
let WACFG_numerosCache = [];

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

// =============================================================================
//  AVISOS DE MENSAJES NO LEÍDOS
// =============================================================================

/** Carga la configuración de avisos y llena el formulario */
async function WACFG_cargarAvisoConfig() {
    try {
        const resp = await fetch(`${WACFG_URL}/getAvisoConfigAjax`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (!data.ok) return;

        // Poblar select de plantillas
        const selPlantilla = document.getElementById('avisoPlantillaNombre');
        if (selPlantilla) {
            // Limpiar opciones previas (mantener la opción vacía)
            selPlantilla.innerHTML = '<option value="">— Sin plantilla (texto libre) —</option>';
            (data.plantillas || []).forEach(pl => {
                const opt = document.createElement('option');
                opt.value       = pl.nombre;
                opt.textContent = pl.nombre + ' (' + pl.idioma + ')';
                selPlantilla.appendChild(opt);
            });
        }

        // Llenar formulario de config
        const cfg = data.config;
        if (cfg) {
            const sw = document.getElementById('avisoActivo');
            sw.checked = cfg.activo == true || cfg.activo === 't';
            document.getElementById('lblAvisoActivo').textContent = sw.checked ? 'Activado' : 'Desactivado';
            document.getElementById('avisoUmbral').value = cfg.umbral_minutos || 30;
            if (selPlantilla) selPlantilla.value = cfg.plantilla_nombre || '';
        }

        // Último aviso enviado
        const divUltimo = document.getElementById('divUltimoAviso');
        const txtUltimo = document.getElementById('txtUltimoAviso');
        if (data.ultimo_aviso && divUltimo) {
            const ua = data.ultimo_aviso;
            const fecha = new Date(ua.fecha_envio);
            const fechaStr = fecha.toLocaleString('es-EC', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
            txtUltimo.innerHTML = `Último aviso enviado: <strong>${fechaStr}</strong> — ${ua.chats_pendientes} chat(s) pendiente(s), ${ua.numeros_notificados} número(s) notificado(s).`;
            divUltimo.classList.remove('d-none');
        }

        // Renderizar tabla de números
        WACFG_numerosCache = data.numeros || [];
        WACFG_renderNumeros();
    } catch (e) {
        console.error('Error cargando config de avisos:', e);
    }
}

/** Renderiza la tabla de números */
function WACFG_renderNumeros() {
    const tbody = document.getElementById('tbodyAvisoNumeros');
    const trVacio = document.getElementById('trAvisoSinNumeros');
    if (!tbody) return;

    // Eliminar filas previas (excepto el trVacio)
    Array.from(tbody.querySelectorAll('tr:not(#trAvisoSinNumeros)')).forEach(r => r.remove());

    if (WACFG_numerosCache.length === 0) {
        if (trVacio) trVacio.classList.remove('d-none');
        return;
    }

    if (trVacio) trVacio.classList.add('d-none');

    WACFG_numerosCache.forEach(num => {
        const activo  = num.activo == true || num.activo === 't';
        const badgeCls = activo
            ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25'
            : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
        const badgeTxt = activo ? 'Activo' : 'Inactivo';
        const iconSwitch = activo ? 'bi-toggle-on text-success' : 'bi-toggle-off text-secondary';

        const tr = document.createElement('tr');
        tr.id = `trNumero_${num.id}`;
        tr.innerHTML = `
            <td class="ps-3 fw-medium">${WACFG_esc(num.telefono)}</td>
            <td class="text-muted">${WACFG_esc(num.nombre || '—')}</td>
            <td class="text-center">
                <span class="badge ${badgeCls}">${badgeTxt}</span>
            </td>
            <td class="text-center pe-3">
                <button class="btn btn-sm btn-outline-secondary me-1" title="${activo ? 'Desactivar' : 'Activar'}"
                        onclick="WACFG_toggleNumero(${num.id})">
                    <i class="bi ${iconSwitch}"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar"
                        onclick="WACFG_eliminarNumero(${num.id}, '${WACFG_esc(num.telefono)}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
    });
}

/** Guarda la configuración de avisos */
async function WACFG_guardarAvisoConfig() {
    const form = document.getElementById('formAvisoConfig');
    if (!form) return;

    const activo    = document.getElementById('avisoActivo').checked ? '1' : '0';
    const umbral    = document.getElementById('avisoUmbral').value.trim();
    const plantilla = document.getElementById('avisoPlantillaNombre').value;

    if (!umbral) {
        Swal.fire('Error', 'El tiempo de aviso es obligatorio.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('activo',           activo);
    fd.append('umbral_minutos',   umbral);
    fd.append('plantilla_nombre', plantilla);

    try {
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const resp = await fetch(`${WACFG_URL}/guardarAvisoConfigAjax`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.ok) {
            Swal.fire('¡Guardado!', data.mensaje, 'success');
        } else {
            Swal.fire('Error', data.error || 'No se pudo guardar.', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de red.', 'error');
    }
}

/** Muestra el formulario inline para agregar número */
function WACFG_abrirAgregarNumero() {
    document.getElementById('divFormNumero').classList.remove('d-none');
    document.getElementById('inputNuevoTelefono').value = '';
    document.getElementById('inputNuevoNombre').value   = '';
    document.getElementById('inputNuevoTelefono').focus();
}

function WACFG_cancelarAgregarNumero() {
    document.getElementById('divFormNumero').classList.add('d-none');
}

/** Confirma y envía el nuevo número al servidor */
async function WACFG_confirmarAgregarNumero() {
    const telefono = document.getElementById('inputNuevoTelefono').value.trim();
    const nombre   = document.getElementById('inputNuevoNombre').value.trim();

    if (!telefono) {
        document.getElementById('inputNuevoTelefono').focus();
        return;
    }

    const fd = new FormData();
    fd.append('telefono', telefono);
    fd.append('nombre',   nombre);

    try {
        const resp = await fetch(`${WACFG_URL}/agregarNumeroAjax`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.ok) {
            WACFG_numerosCache.push(data.numero);
            WACFG_renderNumeros();
            WACFG_cancelarAgregarNumero();
            if (window.Toast) {
                Toast.fire({ icon: 'success', title: data.mensaje });
            }
        } else {
            Swal.fire('Error', data.error || 'No se pudo agregar.', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de red.', 'error');
    }
}

/** Activa / desactiva un número */
async function WACFG_toggleNumero(id) {
    const fd = new FormData();
    fd.append('id', id);

    try {
        const resp = await fetch(`${WACFG_URL}/toggleNumeroAjax`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.ok) {
            const num = WACFG_numerosCache.find(n => n.id == id);
            if (num) num.activo = data.activo;
            WACFG_renderNumeros();
        } else {
            Swal.fire('Error', data.error || 'No se pudo cambiar el estado.', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de red.', 'error');
    }
}

/** Elimina un número con confirmación */
async function WACFG_eliminarNumero(id, telefono) {
    const result = await Swal.fire({
        title: '¿Eliminar número?',
        html: `Se eliminará <strong>${WACFG_esc(telefono)}</strong> de la lista de avisos.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Sí, eliminar',
    });

    if (!result.isConfirmed) return;

    const fd = new FormData();
    fd.append('id', id);

    try {
        const resp = await fetch(`${WACFG_URL}/eliminarNumeroAjax`, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.ok) {
            WACFG_numerosCache = WACFG_numerosCache.filter(n => n.id != id);
            WACFG_renderNumeros();
            if (window.Toast) {
                Toast.fire({ icon: 'success', title: data.mensaje });
            }
        } else {
            Swal.fire('Error', data.error || 'No se pudo eliminar.', 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Error de red.', 'error');
    }
}

/** Escapa HTML para prevenir XSS */
function WACFG_esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// =============================================================================
//  CONFIGURACIÓN API
// =============================================================================

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
