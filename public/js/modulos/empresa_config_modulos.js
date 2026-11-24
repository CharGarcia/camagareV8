/**
 * Configuración de Empresa → pestañas de personalización por módulo
 * (catálogo App\Helpers\ConfigModulo; vista modulos/empresa/config_modulo_pane.php).
 *
 * - Opciones de la empresa: bool / select / texto / columnas.
 * - Opciones 'por_cliente' de tipo columnas: tabla de clientes con columnas propias,
 *   con buscador para agregarlos y ✕ para quitarlos.
 * Se guarda todo de una vez como JSON (EmpresaController::save, section=config_modulo).
 */
(function () {
    'use strict';
    if (window.__CMG_cfgModulos) return;
    window.__CMG_cfgModulos = true;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function avisoOk(msg) {
        if (typeof window.swalToastOk === 'function') return window.swalToastOk(msg);
        if (window.Swal) return Swal.fire({ icon: 'success', title: msg, timer: 1800, showConfirmButton: false });
        alert(msg);
    }
    function avisoError(msg) {
        if (typeof window.swalError === 'function') return window.swalError(msg);
        if (window.Swal) return Swal.fire({ icon: 'error', title: 'Error', text: msg });
        alert(msg);
    }

    /** Columnas marcadas como "por defecto" en el bloque de la empresa. */
    function columnasEmpresa(opcionEl) {
        return Array.from(opcionEl.querySelectorAll('.cfgmod-col-empresa:checked')).map((i) => i.value);
    }

    function actualizarVacio(cont) {
        const filas = cont.querySelectorAll('tr[data-id-cliente]').length;
        const vacio = cont.querySelector('.cfgmod-vacio');
        if (vacio) vacio.classList.toggle('d-none', filas > 0);
    }

    /** Agrega un cliente a la tabla con las columnas por defecto ya marcadas. */
    function agregarCliente(opcionEl, cont, cli) {
        const tbody = cont.querySelector('.cfgmod-tbody');
        const existente = tbody.querySelector(`tr[data-id-cliente="${cli.id}"]`);
        if (existente) {
            existente.classList.add('table-warning');
            existente.scrollIntoView({ block: 'nearest' });
            setTimeout(() => existente.classList.remove('table-warning'), 1500);
            return;
        }
        let cols = [];
        try { cols = JSON.parse(cont.dataset.columnas || '[]'); } catch (e) { cols = []; }
        const marcadas = columnasEmpresa(opcionEl);

        const tr = document.createElement('tr');
        tr.dataset.idCliente = String(cli.id);
        tr.innerHTML =
            `<td><div class="fw-semibold">${esc(cli.nombre)}</div><div class="text-muted" style="font-size:.7rem">${esc(cli.identificacion)}</div></td>` +
            cols.map((c) => `<td class="text-center"><input class="form-check-input cfgmod-col-cli" type="checkbox" value="${esc(c.clave)}"` +
                `${(c.fija || marcadas.includes(c.clave)) ? ' checked' : ''}${c.fija ? ' disabled' : ''}></td>`).join('') +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 cfgmod-quitar" title="Quitar cliente"><i class="bi bi-x-lg"></i></button></td>';
        tbody.insertBefore(tr, tbody.querySelector('.cfgmod-vacio'));
        actualizarVacio(cont);
    }

    function iniciarBuscador(form, opcionEl, cont) {
        const input = cont.querySelector('.cfgmod-buscar-cliente');
        const lista = cont.querySelector('.cfgmod-resultados');
        if (!input || !lista) return;
        let timer = null;
        let ultimo = 0;

        const cerrar = () => { lista.classList.add('d-none'); lista.innerHTML = ''; };

        const buscar = async () => {
            const q = input.value.trim();
            const n = ++ultimo;
            try {
                const r = await fetch(form.dataset.urlClientes + '?q=' + encodeURIComponent(q));
                const j = await r.json();
                if (n !== ultimo) return; // llegó una respuesta vieja
                const rows = (j && j.data) || [];
                if (!rows.length) {
                    lista.innerHTML = '<div class="list-group-item small text-muted">Sin resultados</div>';
                } else {
                    lista.innerHTML = rows.map((c) =>
                        `<button type="button" class="list-group-item list-group-item-action small py-1" data-id="${c.id}" data-nombre="${esc(c.nombre)}" data-identificacion="${esc(c.identificacion)}">` +
                        `<span class="fw-semibold">${esc(c.nombre)}</span> <span class="text-muted">${esc(c.identificacion)}</span></button>`).join('');
                }
                lista.classList.remove('d-none');
            } catch (e) {
                cerrar();
            }
        };

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(buscar, 250); });
        input.addEventListener('focus', () => { if (input.value.trim() !== '') buscar(); });
        input.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); if (e.key === 'Enter') e.preventDefault(); });
        lista.addEventListener('mousedown', (e) => {
            const b = e.target.closest('button[data-id]');
            if (!b) return;
            e.preventDefault();
            agregarCliente(opcionEl, cont, { id: parseInt(b.dataset.id, 10), nombre: b.dataset.nombre, identificacion: b.dataset.identificacion });
            input.value = '';
            cerrar();
        });
        input.addEventListener('blur', () => setTimeout(cerrar, 150));
    }

    /** Arma {modulo, valores, clientes} a partir del formulario. */
    function recolectar(form) {
        const cfg = { modulo: form.dataset.modulo, valores: {}, clientes: {} };
        form.querySelectorAll('.cfgmod-opcion').forEach((op) => {
            const clave = op.dataset.clave;
            const tipo = op.dataset.tipo;
            if (tipo === 'bool') {
                cfg.valores[clave] = !!op.querySelector('.cfgmod-valor')?.checked;
            } else if (tipo === 'select' || tipo === 'texto') {
                cfg.valores[clave] = op.querySelector('.cfgmod-valor')?.value ?? '';
            } else if (tipo === 'columnas') {
                cfg.valores[clave] = columnasEmpresa(op);
                const cont = op.querySelector('.cfgmod-clientes');
                if (cont) {
                    const mapa = {};
                    cont.querySelectorAll('tr[data-id-cliente]').forEach((tr) => {
                        mapa[tr.dataset.idCliente] = Array.from(tr.querySelectorAll('.cfgmod-col-cli:checked')).map((i) => i.value);
                    });
                    cfg.clientes[clave] = mapa;
                }
            }
        });
        return cfg;
    }

    function iniciarForm(form) {
        form.querySelectorAll('.cfgmod-opcion').forEach((op) => {
            const cont = op.querySelector('.cfgmod-clientes');
            if (!cont) return;
            iniciarBuscador(form, op, cont);
            cont.addEventListener('click', (e) => {
                const b = e.target.closest('.cfgmod-quitar');
                if (!b) return;
                b.closest('tr')?.remove();
                actualizarVacio(cont);
            });
        });

        let guardando = false;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (guardando) return;
            guardando = true;
            const btn = form.querySelector('.cfgmod-guardar');
            if (btn) btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('section', 'config_modulo');
                fd.append('config', JSON.stringify(recolectar(form)));
                const r = await fetch(form.dataset.urlGuardar, { method: 'POST', body: fd });
                const j = await r.json();
                if (j && j.ok) {
                    avisoOk('Configuración guardada');
                } else {
                    avisoError((j && (j.error || j.msg)) || 'No se pudo guardar la configuración.');
                }
            } catch (err) {
                avisoError('Error de conexión con el servidor');
            } finally {
                guardando = false;
                if (btn) btn.disabled = false;
            }
        });
    }

    function iniciar() {
        document.querySelectorAll('form.cfgmod-form').forEach(iniciarForm);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
