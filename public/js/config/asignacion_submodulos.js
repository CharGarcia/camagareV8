window.addEventListener('load', function () {
    'use strict';

    const API = `${window.BASE_URL || ''}/config/asignacion-submodulos`;
    const MAX_FILAS_RENDER = 500;

    const selSubmodulo = document.getElementById('asigsub-submodulo');
    const bloqueUsuarios = document.getElementById('bloque-usuarios');
    const bloqueEmpresaFiltro = document.getElementById('bloque-empresa-filtro');
    const bloqueEmpresa = document.getElementById('bloque-empresa');
    const selEmpresaFiltro = document.getElementById('asigsub-empresa-filtro');
    const selEmpresa = document.getElementById('asigsub-empresa');
    const errorEl = document.getElementById('asigsub-error');
    const resultadoEl = document.getElementById('asigsub-resultado');
    const totalesEl = document.getElementById('asigsub-totales');
    const tbody = document.getElementById('asigsub-tbody');
    const btnPrevisualizar = document.getElementById('btn-previsualizar');
    const btnAplicar = document.getElementById('btn-aplicar');

    let tsSubmodulo = null;
    let tsUsuarios = null;
    let tsEmpresaFiltro = null;
    let tsEmpresa = null;

    if (typeof TomSelect !== 'undefined') {
        tsSubmodulo = new TomSelect(selSubmodulo, {
            create: false,
            placeholder: 'Buscar submódulo...',
            searchField: ['text', 'search'],
            render: {
                option: function (data, escape) {
                    var grupo = data.optgroup ? this.optgroups[data.optgroup] : null;
                    var modulo = grupo ? escape(grupo.label) : '';
                    return '<div class="py-1">' + escape(data.text)
                        + (modulo ? ' <small class="text-muted">(' + modulo + ')</small>' : '')
                        + '</div>';
                },
            },
        });
        tsUsuarios = new TomSelect('#asigsub-usuarios', {
            create: false,
            placeholder: 'Buscar usuarios...',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            load: function (query, callback) {
                fetch(`${API}?action=usuarios&q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { callback(Array.isArray(data) ? data : []); })
                    .catch(function () { callback(); });
            },
        });
        tsEmpresaFiltro = new TomSelect(selEmpresaFiltro, { create: false, placeholder: 'Todas las empresas del usuario' });
        tsEmpresa = new TomSelect(selEmpresa, { create: false, placeholder: 'Seleccione empresa...' });
    }

    function modoSeleccionado() {
        const r = document.querySelector('input[name="asigsub-modo"]:checked');
        return r ? r.value : 'usuarios';
    }

    function actualizarBloquesVisibles() {
        const modo = modoSeleccionado();
        bloqueUsuarios.classList.toggle('d-none', modo !== 'usuarios');
        bloqueEmpresaFiltro.classList.toggle('d-none', modo === 'empresa');
        bloqueEmpresa.classList.toggle('d-none', modo !== 'empresa');
    }

    document.querySelectorAll('input[name="asigsub-modo"]').forEach(function (r) {
        r.addEventListener('change', function () {
            actualizarBloquesVisibles();
            invalidarPreview();
        });
    });
    actualizarBloquesVisibles();

    function setError(msg) {
        if (!msg) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
            return;
        }
        errorEl.textContent = msg;
        errorEl.classList.remove('d-none');
    }

    function invalidarPreview() {
        btnAplicar.disabled = true;
        resultadoEl.classList.add('d-none');
    }

    // Cualquier cambio en los criterios invalida la previsualización vigente.
    ['asigsub-submodulo', 'perm-ver', 'perm-crear', 'perm-actualizar', 'perm-eliminar', 'perm-t', 'asigsub-sobrescribir', 'asigsub-empresa-filtro', 'asigsub-empresa'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', invalidarPreview);
    });
    if (tsUsuarios) tsUsuarios.on('change', invalidarPreview);

    function idModuloDeSubmodulo(idSub) {
        const opt = selSubmodulo.querySelector('option[value="' + idSub + '"]');
        return opt ? (opt.getAttribute('data-id-modulo') || '') : '';
    }

    function nombreSubmodulo(idSub) {
        const opt = selSubmodulo.querySelector('option[value="' + idSub + '"]');
        return opt ? opt.textContent.trim() : '';
    }

    function construirFormData() {
        const idSub = tsSubmodulo ? tsSubmodulo.getValue() : selSubmodulo.value;
        const fd = new FormData();
        fd.append('id_submodulo', idSub || '');
        fd.append('id_modulo', idModuloDeSubmodulo(idSub));
        fd.append('nombre_submodulo', nombreSubmodulo(idSub));

        ['ver', 'crear', 'actualizar', 'eliminar', 't'].forEach(function (p) {
            const chk = document.getElementById('perm-' + p);
            if (chk && chk.checked) fd.append(p, '1');
        });

        const modo = modoSeleccionado();
        if (modo === 'usuarios') {
            fd.append('modo', 'usuarios');
            const ids = tsUsuarios ? tsUsuarios.getValue() : [];
            (Array.isArray(ids) ? ids : [ids]).forEach(function (id) { if (id) fd.append('ids_usuario[]', id); });
            fd.append('id_empresa_filtro', (tsEmpresaFiltro ? tsEmpresaFiltro.getValue() : selEmpresaFiltro.value) || '');
        } else if (modo === 'empresa') {
            fd.append('modo', 'empresa');
            fd.append('id_empresa', (tsEmpresa ? tsEmpresa.getValue() : selEmpresa.value) || '');
        } else {
            // admin | usuario | todos
            const nivelMap = { admin: '2', usuario: '1', todos: 'todos' };
            fd.append('modo', 'nivel');
            fd.append('nivel', nivelMap[modo] || '');
            fd.append('id_empresa_filtro', (tsEmpresaFiltro ? tsEmpresaFiltro.getValue() : selEmpresaFiltro.value) || '');
        }

        if (document.getElementById('asigsub-sobrescribir').checked) fd.append('sobrescribir', '1');
        return fd;
    }

    function renderPreview(res) {
        const filas = res.filas || [];
        const mostrar = filas.slice(0, MAX_FILAS_RENDER);
        tbody.innerHTML = mostrar.map(function (f) {
            const badge = f.ya_asignado
                ? '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Ya asignado</span>'
                : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Nuevo</span>';
            return '<tr><td>' + escapeHtml(f.nombre_usuario) + '</td><td>' + escapeHtml(f.nombre_empresa) + '</td><td class="text-center">' + badge + '</td></tr>';
        }).join('');

        let totalesTxt = 'Total: <strong>' + res.total + '</strong> &middot; Nuevos: <strong class="text-success">' + res.nuevos + '</strong> &middot; Ya asignados: <strong class="text-secondary">' + res.ya_asignados + '</strong>';
        if (filas.length > MAX_FILAS_RENDER) {
            totalesTxt += '<br><span class="text-muted">Mostrando los primeros ' + MAX_FILAS_RENDER + ' de ' + filas.length + ' en la vista previa; la asignación se aplicará sobre todos.</span>';
        }
        totalesEl.innerHTML = totalesTxt;
        resultadoEl.classList.remove('d-none');
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    btnPrevisualizar.addEventListener('click', function () {
        setError('');
        invalidarPreview();
        const fd = construirFormData();
        btnPrevisualizar.disabled = true;
        fetch(`${API}?action=previsualizar`, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btnPrevisualizar.disabled = false;
                if (!res.ok) {
                    setError(res.error || 'Error al previsualizar.');
                    return;
                }
                renderPreview(res);
                btnAplicar.disabled = false;
                btnAplicar.dataset.totales = JSON.stringify({ total: res.total, nuevos: res.nuevos, yaAsignados: res.ya_asignados });
            })
            .catch(function () {
                btnPrevisualizar.disabled = false;
                setError('Error de conexión al previsualizar.');
            });
    });

    btnAplicar.addEventListener('click', function () {
        setError('');
        let resumen = {};
        try { resumen = JSON.parse(btnAplicar.dataset.totales || '{}'); } catch (e) {}
        const sobrescribir = document.getElementById('asigsub-sobrescribir').checked;
        const confirmMsg = 'Se asignará el submódulo a ' + (resumen.total || 0) + ' combinación(es) usuario/empresa: '
            + (resumen.nuevos || 0) + ' nueva(s)'
            + (sobrescribir ? ' y ' + (resumen.yaAsignados || 0) + ' se sobrescribirán' : ' (' + (resumen.yaAsignados || 0) + ' ya asignadas se omitirán)')
            + '.\n\n¿Confirma aplicar la asignación?';
        if (!window.confirm(confirmMsg)) return;

        const fd = construirFormData();
        btnAplicar.disabled = true;
        fetch(`${API}?action=aplicar`, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) {
                    setError(res.error || 'Error al aplicar la asignación.');
                    btnAplicar.disabled = false;
                    return;
                }
                totalesEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Aplicado: '
                    + res.insertados + ' insertado(s), ' + res.actualizados + ' actualizado(s), ' + res.omitidos + ' omitido(s).';
                tbody.innerHTML = '';
                resultadoEl.classList.remove('d-none');
            })
            .catch(function () {
                setError('Error de conexión al aplicar.');
                btnAplicar.disabled = false;
            });
    });
});
