let cmp_clientes = [];
let cmp_plantillas = [];
let cmp_seleccionados = new Set();
let isCampaingRunning = false;

document.addEventListener('DOMContentLoaded', () => {
    CMP_CargarPlantillas();
    CMP_CargarClientes();

    // Filtro de clientes
    const inputBuscador = document.getElementById('buscadorClientes');
    if (inputBuscador) {
        inputBuscador.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase();
            const filas = document.querySelectorAll('#tbodyClientes tr.cliente-row');
            filas.forEach(fila => {
                const texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(val) ? '' : 'none';
            });
        });
    }

    // Checkbox "Seleccionar Todos"
    const checkAll = document.getElementById('checkAllClientes');
    if (checkAll) {
        checkAll.addEventListener('change', (e) => {
            const checks = document.querySelectorAll('.check-cliente');
            checks.forEach(c => {
                // Solo checkear si la fila est visible (filtrada)
                const fila = c.closest('tr');
                if (fila.style.display !== 'none') {
                    c.checked = e.target.checked;
                    if (c.checked) {
                        cmp_seleccionados.add(c.value);
                    } else {
                        cmp_seleccionados.delete(c.value);
                    }
                }
            });
            CMP_ActualizarContador();
        });
    }

    // Seleccion de plantilla
    const selectPlantilla = document.getElementById('campanaPlantilla');
    if (selectPlantilla) {
        selectPlantilla.addEventListener('change', (e) => {
            CMP_RenderizarVariables(e.target.value);
        });
    }

    // Boton Iniciar
    const btnIniciar = document.getElementById('btnIniciarCampana');
    if (btnIniciar) {
        btnIniciar.addEventListener('click', CMP_IniciarCampana);
    }
});

function CMP_CargarPlantillas() {
    fetch(`${B_URL}/modulos/whatsapp-campanas/getPlantillasAjax`)
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                cmp_plantillas = data.plantillas;
                const select = document.getElementById('campanaPlantilla');
                select.innerHTML = '<option value="">-- Seleccione una plantilla --</option>';
                cmp_plantillas.forEach(p => {
                    select.innerHTML += `<option value="${p.nombre}">${p.nombre} (${p.idioma})</option>`;
                });
            } else {
                Swal.fire('Error', data.error || 'No se pudieron cargar las plantillas', 'error');
            }
        })
        .catch(err => console.error('Error cargando plantillas', err));
}

function CMP_CargarClientes() {
    fetch(`${B_URL}/modulos/whatsapp-campanas/getClientesAjax`)
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                cmp_clientes = data.clientes;
                CMP_RenderizarClientes();
            } else {
                Swal.fire('Error', data.error || 'No se pudieron cargar los clientes', 'error');
            }
        })
        .catch(err => console.error('Error cargando clientes', err));
}

function CMP_RenderizarClientes() {
    const tbody = document.getElementById('tbodyClientes');
    if (!tbody) return;

    if (cmp_clientes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay clientes con telfono registrado.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    cmp_clientes.forEach(c => {
        const tr = document.createElement('tr');
        tr.className = 'cliente-row';
        tr.innerHTML = `
            <td class="ps-4">
                <div class="form-check">
                    <input class="form-check-input check-cliente" type="checkbox" value="${c.id}">
                </div>
            </td>
            <td class="fw-bold">${c.nombre}</td>
            <td>${c.identificacion || '-'}</td>
            <td>${c.telefono}</td>
        `;
        
        // Al hacer click en la fila (excepto en el checkbox directamente), togglear
        tr.addEventListener('click', (e) => {
            if (e.target.tagName !== 'INPUT') {
                const chk = tr.querySelector('.check-cliente');
                chk.checked = !chk.checked;
                // Disparar evento change
                chk.dispatchEvent(new Event('change'));
            }
        });

        const chk = tr.querySelector('.check-cliente');
        chk.addEventListener('change', (e) => {
            if (e.target.checked) {
                cmp_seleccionados.add(c.id.toString());
            } else {
                cmp_seleccionados.delete(c.id.toString());
            }
            CMP_ActualizarContador();
        });

        tbody.appendChild(tr);
    });
}

function CMP_ActualizarContador() {
    const contador = document.getElementById('contadorSeleccionados');
    if (contador) {
        contador.textContent = cmp_seleccionados.size;
    }
}

function CMP_RenderizarVariables(nombrePlantilla) {
    const container = document.getElementById('campanaVariablesContainer');
    const inputsDiv = document.getElementById('campanaVariablesInputs');
    if (!container || !inputsDiv) return;

    if (!nombrePlantilla) {
        container.classList.add('d-none');
        return;
    }

    const plantilla = cmp_plantillas.find(p => p.nombre === nombrePlantilla);
    if (!plantilla || plantilla.variables === 0) {
        container.classList.add('d-none');
        return;
    }

    container.classList.remove('d-none');
    inputsDiv.innerHTML = '';

    const columnasDisponibles = [
        { val: 'nombre', text: 'Nombre del Cliente' },
        { val: 'identificacion', text: 'Identificacin (RUC/CI)' },
        { val: 'telefono', text: 'Telfono' }
    ];

    for (let i = 1; i <= plantilla.variables; i++) {
        let options = '';
        columnasDisponibles.forEach(col => {
            options += `<option value="${col.val}">${col.text}</option>`;
        });

        inputsDiv.innerHTML += `
            <div class="mb-2">
                <label class="form-label small">Variable {{${i}}}</label>
                <select class="form-select form-select-sm var-mapping" data-index="${i}">
                    ${options}
                </select>
            </div>
        `;
    }
}

async function CMP_IniciarCampana() {
    if (isCampaingRunning) return;

    const nombrePlantilla = document.getElementById('campanaPlantilla').value;
    if (!nombrePlantilla) {
        Swal.fire('Atencin', 'Debes seleccionar una plantilla', 'warning');
        return;
    }

    if (cmp_seleccionados.size === 0) {
        Swal.fire('Atencin', 'Debes seleccionar al menos un cliente', 'warning');
        return;
    }

    const plantilla = cmp_plantillas.find(p => p.nombre === nombrePlantilla);
    
    // Obtener mapeo de variables
    const mappings = [];
    if (plantilla && plantilla.variables > 0) {
        document.querySelectorAll('.var-mapping').forEach(select => {
            mappings.push(select.value);
        });
    }

    const result = await Swal.fire({
        title: 'Iniciar Campaa',
        text: `Se enviarn mensajes a ${cmp_seleccionados.size} clientes. El proceso no debe ser interrumpido. Confirmas?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'S, iniciar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    // Preparar UI para envio
    isCampaingRunning = true;
    document.getElementById('btnIniciarCampana').disabled = true;
    const progContainer = document.getElementById('progresoContainer');
    progContainer.classList.remove('d-none');
    
    const progTexto = document.getElementById('progresoTexto');
    const progPorc = document.getElementById('progresoPorcentaje');
    const progBarra = document.getElementById('progresoBarra');
    const progLog = document.getElementById('progresoLog');
    
    progLog.innerHTML = '';
    
    const total = cmp_seleccionados.size;
    let enviados = 0;
    let exitosos = 0;
    let fallidos = 0;

    const listaIds = Array.from(cmp_seleccionados);

    // Iterar uno por uno
    for (let i = 0; i < listaIds.length; i++) {
        const idCliente = parseInt(listaIds[i]);
        const cliente = cmp_clientes.find(c => c.id === idCliente);
        
        if (!cliente) continue;

        // Construir variables para este cliente especifico
        const varsParaCliente = mappings.map(campo => {
            return cliente[campo] || '';
        });

        // Log en UI
        progLog.innerHTML += `<div class="mb-1 text-dark">Enviando a ${cliente.nombre} (${cliente.telefono})... `;
        progLog.scrollTop = progLog.scrollHeight;

        try {
            const res = await fetch(`${B_URL}/modulos/whatsapp-campanas/sendCampanaMessageAjax`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    telefono: cliente.telefono,
                    nombreCliente: cliente.nombre,
                    plantilla: plantilla.nombre,
                    idioma: plantilla.idioma,
                    variables: varsParaCliente
                })
            });

            const data = await res.json();
            
            if (data.ok) {
                exitosos++;
                progLog.innerHTML += `<span class="text-success fw-bold">OK</span></div>`;
            } else {
                fallidos++;
                progLog.innerHTML += `<span class="text-danger fw-bold">ERROR</span> <span class="text-muted">(${data.error})</span></div>`;
            }
        } catch (error) {
            fallidos++;
            progLog.innerHTML += `<span class="text-danger fw-bold">ERROR RED</span></div>`;
        }

        enviados++;
        
        // Actualizar barra
        const porc = Math.round((enviados / total) * 100);
        progTexto.textContent = `Enviando ${enviados} de ${total}`;
        progPorc.textContent = `${porc}%`;
        progBarra.style.width = `${porc}%`;
        
        // Pequea pausa para no saturar al navegador ni al backend
        await new Promise(r => setTimeout(r, 200));
    }

    isCampaingRunning = false;
    document.getElementById('btnIniciarCampana').disabled = false;
    progBarra.classList.remove('progress-bar-animated');
    progBarra.classList.replace('bg-success', 'bg-secondary');
    progTexto.textContent = 'Proceso Finalizado';
    
    progLog.innerHTML += `<div class="mt-3 text-success fw-bold">Campaña finalizada. Exitosos: ${exitosos}, Fallidos: ${fallidos}</div>`;
    progLog.scrollTop = progLog.scrollHeight;
    
    Swal.fire('Campaña Finalizada', `Se enviaron ${exitosos} exitosamente. Fallaron ${fallidos}.`, 'success').then(() => {
        window.location.reload();
    });
}
