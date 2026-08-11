(function () {
    'use strict';

    const TIPO_LABEL = { ACTIVO: 'Activo', PASIVO: 'Pasivo', PATRIMONIO: 'Patrimonio', INGRESO: 'Ingreso', COSTO: 'Costo', GASTO: 'Gasto' };
    let establecimientosCache = [];

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function cargarLista() {
        const tbody = document.getElementById('bc-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Cargando…</td></tr>';
        try {
            const res = await fetch(`${BC_URL_BASE}/listarAjax`).then(r => r.json());
            if (!res.ok) { tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">${esc(res.mensaje)}</td></tr>`; return; }
            const grupos = res.data || [];
            if (!grupos.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No hay grupos consolidados definidos.</td></tr>';
                return;
            }
            tbody.innerHTML = grupos.map(g => {
                const cuentas = (g.cuentas || []).map(c =>
                    `<span class="badge bg-light text-dark border me-1 mb-1" title="${esc(c.empresa_nombre)}">${esc(c.establecimiento)}: ${esc(c.cuenta_codigo)} - ${esc(c.cuenta_nombre)}</span>`
                ).join(' ') || '<span class="text-muted small">Sin cuentas</span>';
                const acciones = [];
                if (BC_PERM_ACTUALIZAR) acciones.push(`<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="window.BC_editar(${g.id})" title="Editar"><i class="bi bi-pencil"></i></button>`);
                if (BC_PERM_ELIMINAR) acciones.push(`<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="window.BC_eliminar(${g.id})" title="Eliminar"><i class="bi bi-trash"></i></button>`);
                return `<tr>
                    <td class="ps-3 fw-bold">${esc(g.nombre)}</td>
                    <td><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${TIPO_LABEL[g.tipo] || g.tipo}</span></td>
                    <td>${cuentas}</td>
                    <td class="text-center">${acciones.join(' ')}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error de red o servidor.</td></tr>';
        }
    }

    function renderPickerEstablecimientos(seleccionActual) {
        const tbody = document.getElementById('bc-tbody-establecimientos');
        seleccionActual = seleccionActual || {}; // id_empresa -> id_cuenta ya elegido (al editar)

        tbody.innerHTML = establecimientosCache.map(est => {
            const opciones = ['<option value="">— Ninguna —</option>'].concat(
                est.cuentas.map(c => {
                    const yaSeleccionada = seleccionActual[est.id_empresa] === c.id;
                    const bloqueada = c.usada && !yaSeleccionada;
                    const label = `${c.codigo} - ${c.nombre}` + (bloqueada ? ' (ya usada en otro grupo)' : '');
                    return `<option value="${c.id}" ${yaSeleccionada ? 'selected' : ''} ${bloqueada ? 'disabled' : ''}>${esc(label)}</option>`;
                })
            ).join('');
            return `<tr data-id-empresa="${est.id_empresa}">
                <td class="fw-medium">${esc(est.etiqueta)}</td>
                <td><select class="form-select form-select-sm shadow-none bc-select-cuenta">${opciones}</select></td>
            </tr>`;
        }).join('');
    }

    window.BC_toggleAvisoPatrimonio = function () {
        document.getElementById('bc-aviso-patrimonio').style.display =
            document.getElementById('bc-tipo').value === 'PATRIMONIO' ? '' : 'none';
    };

    async function abrirModal(idGrupo, datosExistente) {
        document.getElementById('bc-id-grupo').value = idGrupo || '';
        document.getElementById('bc-modal-titulo').textContent = idGrupo ? 'Editar grupo consolidado' : 'Nuevo grupo consolidado';
        document.getElementById('bc-nombre').value = datosExistente ? datosExistente.nombre : '';
        document.getElementById('bc-tipo').value = datosExistente ? datosExistente.tipo : 'ACTIVO';
        window.BC_toggleAvisoPatrimonio();

        const seleccionActual = {};
        if (datosExistente) {
            (datosExistente.cuentas || []).forEach(c => { seleccionActual[c.id_empresa] = c.id_cuenta; });
        }

        document.getElementById('bc-tbody-establecimientos').innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Cargando cuentas…</td></tr>';
        new bootstrap.Modal(document.getElementById('modalGrupoBC')).show();

        try {
            const params = new URLSearchParams();
            if (idGrupo) params.append('id_grupo', idGrupo);
            const res = await fetch(`${BC_URL_BASE}/establecimientosAjax?${params.toString()}`).then(r => r.json());
            if (!res.ok) { document.getElementById('bc-tbody-establecimientos').innerHTML = `<tr><td colspan="2" class="text-danger">${esc(res.mensaje)}</td></tr>`; return; }
            establecimientosCache = res.data || [];
            renderPickerEstablecimientos(seleccionActual);
        } catch (e) {
            console.error(e);
            document.getElementById('bc-tbody-establecimientos').innerHTML = '<tr><td colspan="2" class="text-danger">Error al cargar establecimientos.</td></tr>';
        }
    }

    document.getElementById('bc-btn-nuevo')?.addEventListener('click', () => abrirModal(null, null));
    document.getElementById('bc-tipo')?.addEventListener('change', window.BC_toggleAvisoPatrimonio);

    window.BC_editar = async function (id) {
        try {
            const res = await fetch(`${BC_URL_BASE}/listarAjax`).then(r => r.json());
            const grupo = (res.data || []).find(g => g.id === id);
            if (!grupo) { Swal.fire('Error', 'Grupo no encontrado.', 'error'); return; }
            abrirModal(id, grupo);
        } catch (e) {
            console.error(e);
        }
    };

    window.BC_guardar = async function () {
        const idGrupo = document.getElementById('bc-id-grupo').value;
        const nombre = document.getElementById('bc-nombre').value.trim();
        const tipo = document.getElementById('bc-tipo').value;

        if (!nombre) { Swal.fire('Atención', 'Ingrese el nombre del concepto.', 'warning'); return; }

        const cuentas = [];
        document.querySelectorAll('#bc-tbody-establecimientos tr').forEach(tr => {
            const idEmpresa = parseInt(tr.dataset.idEmpresa, 10);
            const idCuenta = parseInt(tr.querySelector('.bc-select-cuenta').value, 10);
            if (idCuenta) cuentas.push({ id_empresa: idEmpresa, id_cuenta: idCuenta });
        });

        if (cuentas.length < 2) {
            Swal.fire('Atención', 'Seleccione al menos 2 establecimientos con su cuenta equivalente.', 'warning');
            return;
        }

        try {
            const resp = await fetch(`${BC_URL_BASE}/guardarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: idGrupo || null, nombre, tipo, cuentas }),
            });
            const json = await resp.json();
            if (!json.ok) { Swal.fire('Error', json.mensaje || 'No se pudo guardar.', 'error'); return; }
            bootstrap.Modal.getInstance(document.getElementById('modalGrupoBC')).hide();
            Swal.fire({ icon: 'success', title: 'Guardado', timer: 1500, showConfirmButton: false });
            cargarLista();
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de red o servidor.', 'error');
        }
    };

    window.BC_eliminar = async function (id) {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Eliminar este grupo consolidado?',
            text: 'Los establecimientos dejarán de sumarse para este concepto en los reportes consolidados.',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`${BC_URL_BASE}/eliminarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: `id=${encodeURIComponent(id)}`,
            });
            const json = await resp.json();
            if (!json.ok) { Swal.fire('Error', json.mensaje || 'No se pudo eliminar.', 'error'); return; }
            cargarLista();
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de red o servidor.', 'error');
        }
    };

    document.addEventListener('DOMContentLoaded', cargarLista);
})();
