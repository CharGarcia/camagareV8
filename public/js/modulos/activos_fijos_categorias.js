(function () {
    'use strict';

    const AFC_URL = window.AFC_URL_BASE;
    const CTA_URL = window.AFC_CUENTAS_URL;

    async function fetchJson(url) {
        const r = await fetch(url);
        return r.json();
    }

    // Typeahead con selección tipo "chip": Backspace/Delete con selección activa
    // limpia todo de una vez (input visible + oculto + dropdown), no letra por letra.
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
                try {
                    items = await fetchFn(q);
                } catch (e) {
                    console.error('Error buscando cuentas:', e);
                    return;
                }
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

    async function buscarCuentas(q) {
        const json = await fetchJson(`${CTA_URL}/searchAjaxCuentas?q=${encodeURIComponent(q)}`);
        return json.ok ? json.data : [];
    }
    const labelCuenta = (it) => `${it.codigo} - ${it.nombre}`;

    setupTypeahead(document.getElementById('afc-cuenta-activo-txt'), document.getElementById('afc-cuenta-activo-dropdown'), document.getElementById('afc-cuenta-activo-id'), buscarCuentas, labelCuenta);
    setupTypeahead(document.getElementById('afc-cuenta-dep-txt'), document.getElementById('afc-cuenta-dep-dropdown'), document.getElementById('afc-cuenta-dep-id'), buscarCuentas, labelCuenta);
    setupTypeahead(document.getElementById('afc-cuenta-gasto-txt'), document.getElementById('afc-cuenta-gasto-dropdown'), document.getElementById('afc-cuenta-gasto-id'), buscarCuentas, labelCuenta);

    window.AFC_buscar = function () {
        const b = document.getElementById('txtBuscarAFC')?.value || '';
        const tbody = document.getElementById('tbodyAFC');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        fetchJson(`${AFC_URL}/searchAjax?b=${encodeURIComponent(b)}`).then(res => {
            if (tbody) tbody.innerHTML = res.rows;
        });
    };

    document.getElementById('txtBuscarAFC')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); window.AFC_buscar(); }
    });

    function resetForm() {
        const f = document.getElementById('formAFC');
        f.reset();
        document.getElementById('afc-id').value = '';
        ['activo', 'dep', 'gasto'].forEach(k => {
            document.getElementById(`afc-cuenta-${k}-txt`).value = '';
            document.getElementById(`afc-cuenta-${k}-id`).value = '';
        });
        document.getElementById('afc-estado').checked = true;
        document.getElementById('afc-btn-eliminar').classList.add('d-none');
    }

    window.AFC_abrirModal = function (id) {
        resetForm();
        document.getElementById('afcModalTitulo').textContent = id ? 'Editar Categoría de Activos Fijos' : 'Nueva Categoría de Activos Fijos';

        if (id) {
            fetchJson(`${AFC_URL}/getCategoriaAjax?id=${id}`).then(res => {
                if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                const c = res.data;
                document.getElementById('afc-id').value = c.id;
                document.getElementById('afc-nombre').value = c.nombre;
                document.getElementById('afc-porcentaje').value = parseFloat(c.porcentaje_depreciacion_anual).toFixed(2);
                document.getElementById('afc-observaciones').value = c.observaciones || '';
                document.getElementById('afc-estado').checked = !!(c.estado === true || c.estado === 't' || c.estado === '1' || c.estado === 1);

                document.getElementById('afc-cuenta-activo-id').value = c.id_cuenta_activo;
                document.getElementById('afc-cuenta-activo-txt').value = c.cuenta_activo_codigo ? `${c.cuenta_activo_codigo} - ${c.cuenta_activo_nombre}` : '';
                document.getElementById('afc-cuenta-dep-id').value = c.id_cuenta_depreciacion_acumulada;
                document.getElementById('afc-cuenta-dep-txt').value = c.cuenta_dep_acum_codigo ? `${c.cuenta_dep_acum_codigo} - ${c.cuenta_dep_acum_nombre}` : '';
                document.getElementById('afc-cuenta-gasto-id').value = c.id_cuenta_gasto_depreciacion;
                document.getElementById('afc-cuenta-gasto-txt').value = c.cuenta_gasto_codigo ? `${c.cuenta_gasto_codigo} - ${c.cuenta_gasto_nombre}` : '';

                if (window.AFC_PERM?.eliminar) document.getElementById('afc-btn-eliminar').classList.remove('d-none');
                document.getElementById('afc-btn-eliminar').dataset.id = c.id;

                new bootstrap.Modal(document.getElementById('modalAFC')).show();
            });
            return;
        }

        new bootstrap.Modal(document.getElementById('modalAFC')).show();
    };

    window.AFC_guardar = function () {
        const form = document.getElementById('formAFC');
        if (!form.reportValidity()) return;

        if (!document.getElementById('afc-cuenta-activo-id').value) { Swal.fire('Atención', 'Seleccione la cuenta de Activo desde la lista.', 'warning'); return; }
        if (!document.getElementById('afc-cuenta-dep-id').value) { Swal.fire('Atención', 'Seleccione la cuenta de Depreciación Acumulada desde la lista.', 'warning'); return; }
        if (!document.getElementById('afc-cuenta-gasto-id').value) { Swal.fire('Atención', 'Seleccione la cuenta de Gasto por Depreciación desde la lista.', 'warning'); return; }

        const fd = new FormData(form);
        fd.set('estado', document.getElementById('afc-estado').checked ? '1' : '');

        const btn = document.getElementById('afc-btn-guardar');
        btn.disabled = true;
        fetch(`${AFC_URL}/guardarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            btn.disabled = false;
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAFC'))?.hide();
                window.AFC_buscar();
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al guardar', res.mensaje, 'error');
            }
        }).catch(() => { btn.disabled = false; Swal.fire('Error de Red', 'No se pudo completar la operación.', 'error'); });
    };

    window.AFC_eliminar = function () {
        const id = document.getElementById('afc-btn-eliminar')?.dataset.id;
        if (!id) return;
        Swal.fire({
            title: '¿Eliminar esta categoría?',
            text: 'Solo es posible si no tiene activos fijos registrados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${AFC_URL}/eliminarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.ok) {
                    Swal.fire('Eliminada', res.mensaje, 'success').then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalAFC'))?.hide();
                        window.AFC_buscar();
                    });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    };
})();
