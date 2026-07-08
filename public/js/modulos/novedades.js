/**
 * Lógica del Modal de Novedades (Nómina)
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/novedades';
    let modalInst = null;
    const form = document.getElementById('formNovedad');

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalNovedad');
            if (el) modalInst = new bootstrap.Modal(el);
        }
        return modalInst;
    }

    // ─── Buscador de empleado (autocompletar) ───────────────────────────────
    const empBuscar = document.getElementById('nov_empleado_buscar');
    const empHidden = document.getElementById('nov_id_empleado');
    const empResultados = document.getElementById('nov_empleado_resultados');
    let empTimer = null;

    function ocultarResultadosEmp() { if (empResultados) empResultados.classList.add('d-none'); }

    function setEmpleado(id, texto) {
        if (empHidden) empHidden.value = id || '';
        if (empBuscar) empBuscar.value = texto || '';
        ocultarResultadosEmp();
    }

    async function buscarEmpleados(q) {
        try {
            const resp = await fetch(`${urlModulo}/buscarEmpleadosAjax?q=${encodeURIComponent(q)}`);
            const json = await resp.json();
            if (!json.ok || !empResultados) return;
            if (!json.data.length) {
                empResultados.innerHTML = '<div class="list-group-item small text-muted">Sin resultados</div>';
                empResultados.classList.remove('d-none');
                return;
            }
            empResultados.innerHTML = json.data.map(e => {
                const texto = `${e.nombres_apellidos} (${e.identificacion})`.replace(/"/g, '&quot;');
                return `<button type="button" class="list-group-item list-group-item-action py-1 small" data-id="${e.id}" data-texto="${texto}">
                            <span class="fw-medium">${e.nombres_apellidos}</span> <span class="text-muted">${e.identificacion}</span>
                        </button>`;
            }).join('');
            empResultados.classList.remove('d-none');
        } catch (e) { ocultarResultadosEmp(); }
    }

    if (empBuscar) {
        empBuscar.addEventListener('input', () => {
            if (empHidden) empHidden.value = ''; // obliga a re-seleccionar del listado
            const q = empBuscar.value.trim();
            clearTimeout(empTimer);
            if (q.length < 2) { ocultarResultadosEmp(); return; }
            empTimer = setTimeout(() => buscarEmpleados(q), 300);
        });
        empBuscar.addEventListener('blur', () => setTimeout(ocultarResultadosEmp, 200));
    }
    if (empResultados) {
        // mousedown (antes del blur del input) para poder seleccionar.
        empResultados.addEventListener('mousedown', (ev) => {
            const btn = ev.target.closest('[data-id]');
            if (!btn) return;
            ev.preventDefault();
            setEmpleado(btn.dataset.id, btn.dataset.texto);
        });
    }

    // ─── Suprimir/Retroceso limpian todo el input de una vez (reescribir rápido) ─
    function habilitarLimpiezaRapida(el) {
        if (!el) return;
        el.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && el.value !== '') {
                e.preventDefault();
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }
    document.querySelectorAll('#modalNovedad input[type="text"], #modalNovedad input[type="number"], #modalNovedad textarea')
        .forEach(habilitarLimpiezaRapida);

    // ─── Autocompletar Observación con "Tipo - Mes Año" ──────────────────────
    let obsAuto = '';

    function textoObsAuto() {
        const tipoSel = document.getElementById('nov_tipo_codigo');
        const mesSel = document.getElementById('nov_periodo_mes');
        const anio = (document.getElementById('nov_periodo_anio')?.value || '').trim();
        if (!tipoSel || !tipoSel.value) return '';
        const tipo = tipoSel.options[tipoSel.selectedIndex].text;
        const mes = (mesSel && mesSel.value) ? mesSel.options[mesSel.selectedIndex].text : '';
        return `${tipo} - ${mes} ${anio}`.replace(/\s+/g, ' ').trim();
    }

    // Solo autocompleta si el campo está vacío o si aún contiene el último
    // valor autogenerado (para no pisar un texto que el usuario editó a mano).
    function actualizarObservacion() {
        const obs = document.getElementById('nov_observacion');
        if (!obs) return;
        const actual = obs.value.trim();
        if (actual === '' || actual === obsAuto) {
            obsAuto = textoObsAuto();
            obs.value = obsAuto;
        }
    }

    ['nov_tipo_codigo', 'nov_periodo_mes', 'nov_periodo_anio'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', actualizarObservacion);
    });
    document.getElementById('nov_periodo_anio')?.addEventListener('input', actualizarObservacion);

    // Muestra/oculta Valor y Motivo según el tipo, y ajusta la etiqueta del valor.
    window.novToggleCampos = function () {
        const cod = document.getElementById('nov_tipo_codigo').value;
        const cat = window.NOVEDAD_CATALOGO || {};
        const esAviso = cod !== '' && cod === cat.cod_aviso_salida;

        const contValor = document.getElementById('nov_container_valor');
        const contMotivo = document.getElementById('nov_container_motivo');
        const lblValor = document.getElementById('nov_label_valor');
        const inpValor = document.getElementById('nov_valor');
        const selMotivo = document.getElementById('nov_motivo_codigo');

        if (esAviso) {
            contValor.classList.add('d-none');
            if (inpValor) inpValor.value = '0';
            contMotivo.classList.remove('d-none');
        } else {
            contValor.classList.remove('d-none');
            contMotivo.classList.add('d-none');
            if (selMotivo) selMotivo.value = '';
            if (lblValor) lblValor.textContent = (cat.labels && cat.labels[cod]) ? cat.labels[cod] : 'Monto ($)';
        }
    };

    window.abrirModalCrear = function () {
        if (!form) return;
        form.reset();
        document.getElementById('nov_id').value = '';
        document.getElementById('tituloModalNov').textContent = 'Nueva Novedad';
        document.getElementById('btnEliminarNov')?.classList.add('d-none');
        setEmpleado('', '');
        obsAuto = '';
        // Toda novedad nace activa: el selector se muestra pero en solo lectura.
        const selEstadoNuevo = document.getElementById('nov_estado');
        selEstadoNuevo.value = 'activo';
        selEstadoNuevo.disabled = true;
        // Precargar valores favoritos (tipo, afecta a) y sincronizar dependientes.
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalNovedad');
        window.novToggleCampos();
        actualizarObservacion();
        getModal()?.show();
    };

    window.abrirModalEditar = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!form || !id) return;

        form.reset();
        document.getElementById('nov_id').value = id;
        document.getElementById('tituloModalNov').textContent = 'Editar Novedad';
        document.getElementById('btnEliminarNov')?.classList.remove('d-none');
        // En edición sí se puede cambiar el estado (activar / anular).
        document.getElementById('nov_estado').disabled = false;
        getModal()?.show();

        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const d = res.data;

            setEmpleado(d.id_empleado || '', d.empleado_nombre ? `${d.empleado_nombre} (${d.empleado_identificacion || ''})` : '');
            document.getElementById('nov_estado').value = d.estado || 'activo';
            document.getElementById('nov_tipo_codigo').value = d.tipo_codigo || '';
            document.getElementById('nov_fecha').value = d.fecha || '';
            document.getElementById('nov_periodo_mes').value = String(parseInt(d.periodo_mes, 10) || '');
            document.getElementById('nov_periodo_anio').value = d.periodo_anio || '';
            document.getElementById('nov_aplica_en').value = d.aplica_en || 'rol';
            document.getElementById('nov_observacion').value = d.observacion || '';

            window.novToggleCampos();

            // Valores dependientes del tipo, tras el toggle.
            document.getElementById('nov_valor').value = d.valor != null ? d.valor : '0';
            if (d.motivo_codigo) document.getElementById('nov_motivo_codigo').value = d.motivo_codigo;

            // Sincroniza el "auto" con lo cargado: si la observación coincide con el
            // texto autogenerado, seguirá sincronizándose; si es manual, se respeta.
            obsAuto = textoObsAuto();
        } catch (e) {}
    };

    if (form) {
        form.addEventListener('submit', async () => {
            const id = document.getElementById('nov_id').value;
            const btn = document.getElementById('btnGuardarNov');
            const url = id ? `${urlModulo}/update` : `${urlModulo}/store`;
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; };

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            try {
                const resp = await fetch(url, { method: 'POST', body: new FormData(form) });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: id ? 'Actualizada' : 'Guardada', text: json.msg || 'Novedad guardada.', timer: 1500, showConfirmButton: false });
                    setTimeout(() => {
                        restaurar();
                        getModal()?.hide();
                        window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
                    }, 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar.' });
                    restaurar();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                restaurar();
            }
        });
    }

    async function eliminarConSwal(id, cerrarModal) {
        if (!id) return;
        const result = await Swal.fire({
            title: '¿Está seguro?',
            text: 'No podrá revertir esta acción.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;

        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModulo}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', text: json.msg || 'Novedad eliminada.', timer: 1500, showConfirmButton: false });
                if (cerrarModal) getModal()?.hide();
                window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo eliminar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
    }

    window.eliminarRegistro = function (id) { eliminarConSwal(id, false); };
    window.eliminarNovedadModal = function () { eliminarConSwal(document.getElementById('nov_id').value, true); };

})(window, document);
