(function () {
    'use strict';

    const API = `${window.BASE_URL || ''}/config/log-sistema`;

    // ── Auditoría ────────────────────────────────────────────────────────────
    let currentPage = 1;
    let sortCol = 'created_at';
    let sortDir = 'DESC';
    let timerBusqueda = null;
    let modalDetalle = null;
    let detalleId = null; // id del evento cargado en el modal (para el PDF)

    function getBuscar() {
        const el = document.getElementById('logInputBuscar');
        return el ? el.value.trim() : '';
    }

    // Lee la barra de filtros y devuelve solo los que tienen valor.
    function getFiltros() {
        const map = { fu: 'fltUsuario', fe: 'fltEmpresa', fa: 'fltAccion', ft: 'fltTabla', fc: 'fltContenido', fr: 'fltReferencia', fd: 'fltDesde', fh: 'fltHasta' };
        const out = {};
        Object.keys(map).forEach(function (param) {
            const el = document.getElementById(map[param]);
            if (el && el.value.trim() !== '') out[param] = el.value.trim();
        });
        return out;
    }

    function construirParams(extra) {
        const params = new URLSearchParams(extra);
        const f = getFiltros();
        Object.keys(f).forEach(function (k) { params.set(k, f[k]); });
        return params;
    }

    window.LOGSIS_cargarListado = async function (page = 1) {
        currentPage = page;
        const tbody = document.getElementById('tbodyLogSistema');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        const params = construirParams({
            action: 'listar',
            b: getBuscar(),
            page: String(page),
            sort: sortCol,
            dir: sortDir,
        });

        try {
            const resp = await fetch(`${API}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.ok) {
                if (tbody) tbody.innerHTML = res.rows;
                const info = document.getElementById('logPaginationInfo');
                if (info) info.textContent = res.info;
                const pag = document.getElementById('logWrapperPagination');
                if (pag) pag.innerHTML = res.pagination;
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">${res.error || 'Error al cargar'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error de conexión con el servidor.</td></tr>';
        }
    };

    window.LOGSIS_cambiarPagina = function (page) {
        if (page < 1) return;
        LOGSIS_cargarListado(page);
    };

    window.LOGSIS_verDetalle = async function (id) {
        if (!id) return;
        if (!modalDetalle) {
            const el = document.getElementById('modalLogDetalle');
            if (el) modalDetalle = new bootstrap.Modal(el);
        }
        const body = document.getElementById('logDetalleBody');
        const btnPdf = document.getElementById('btnLogDetallePdf');
        detalleId = null;
        if (btnPdf) btnPdf.disabled = true;
        if (body) body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</div>';
        if (modalDetalle) modalDetalle.show();

        try {
            const resp = await fetch(`${API}?action=detalle&id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.ok) {
                if (body) body.innerHTML = res.html;
                detalleId = id;
                if (btnPdf) btnPdf.disabled = false;
            } else {
                if (body) body.innerHTML = `<div class="alert alert-warning mb-0">${res.error || 'No se pudo cargar el detalle.'}</div>`;
            }
        } catch (e) {
            console.error(e);
            if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión.</div>';
        }
    };

    // PDF del detalle abierto: mismo flujo que todo PDF de un registro desde un modal
    // (CMG_pdfDocumento pregunta Imprimir / Descargar / Ver).
    window.LOGSIS_pdfDetalle = function () {
        if (!detalleId) return;
        const url = `${API}?action=pdfDetalle&id=${encodeURIComponent(detalleId)}`;
        if (typeof window.CMG_pdfDocumento === 'function') {
            window.CMG_pdfDocumento(url);
        } else {
            window.open(url, '_blank');
        }
    };

    window.LOGSIS_exportar = function (tipo) {
        const action = tipo === 'pdf' ? 'exportarPdf' : 'exportarExcel';
        const params = construirParams({ action: action, b: getBuscar(), sort: sortCol, dir: sortDir });
        window.open(`${API}?${params.toString()}`, '_blank');
    };

    function actualizarIndicadoresOrden(selector, col, dir) {
        document.querySelectorAll(selector).forEach(function (th) {
            const icon = th.querySelector('i');
            if (icon) icon.remove();
            if (th.getAttribute('data-col') === col) {
                const i = document.createElement('i');
                i.className = 'bi ' + (dir === 'ASC' ? 'bi-arrow-up-short' : 'bi-arrow-down-short');
                th.appendChild(i);
            }
        });
    }

    // ── Intentos de login (nivel 3) ──────────────────────────────────────────
    let loginPage = 1;
    let loginSortCol = 'created_at';
    let loginSortDir = 'DESC';
    let loginTimerBusqueda = null;
    let loginCargado = false;

    function getLoginBuscar() {
        const el = document.getElementById('loginInputBuscar');
        return el ? el.value.trim() : '';
    }

    function getLoginFiltros() {
        const map = { fr: 'fltIntResultado', fd: 'fltIntDesde', fh: 'fltIntHasta' };
        const out = {};
        Object.keys(map).forEach(function (param) {
            const el = document.getElementById(map[param]);
            if (el && el.value.trim() !== '') out[param] = el.value.trim();
        });
        return out;
    }

    function construirParamsLogin(extra) {
        const params = new URLSearchParams(extra);
        const f = getLoginFiltros();
        Object.keys(f).forEach(function (k) { params.set(k, f[k]); });
        return params;
    }

    window.LOGIN_cargarListado = async function (page = 1) {
        loginPage = page;
        const tbody = document.getElementById('tbodyLoginIntentos');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        const params = construirParamsLogin({
            action: 'intentos',
            b: getLoginBuscar(),
            page: String(page),
            sort: loginSortCol,
            dir: loginSortDir,
        });

        try {
            const resp = await fetch(`${API}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.ok) {
                if (tbody) tbody.innerHTML = res.rows;
                const info = document.getElementById('loginPaginationInfo');
                if (info) info.textContent = res.info;
                const pag = document.getElementById('loginWrapperPagination');
                if (pag) pag.innerHTML = res.pagination;
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${res.error || 'Error al cargar'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error de conexión con el servidor.</td></tr>';
        }
    };

    window.LOGIN_cambiarPagina = function (page) {
        if (page < 1) return;
        LOGIN_cargarListado(page);
    };

    // ── Pestañas ─────────────────────────────────────────────────────────────
    function activarTab(tab) {
        document.querySelectorAll('.log-tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tab);
        });

        const esAuditoria = tab === 'auditoria';
        const headerAud = document.getElementById('logHeaderAuditoria');
        const bodyAud = document.getElementById('logBodyAuditoria');
        const headerInt = document.getElementById('logHeaderIntentos');
        const bodyInt = document.getElementById('logBodyIntentos');

        if (headerAud) headerAud.classList.toggle('d-none', !esAuditoria);
        if (bodyAud) bodyAud.classList.toggle('d-none', !esAuditoria);
        if (headerInt) headerInt.classList.toggle('d-none', esAuditoria);
        if (bodyInt) bodyInt.classList.toggle('d-none', esAuditoria);

        if (!esAuditoria && !loginCargado) {
            loginCargado = true;
            LOGIN_cargarListado(1);
        }
    }

    // Filtros con buscador: los <select> de Usuario, Empresa, Acción y Módulo se
    // convierten en TomSelect (ya cargado en el layout) para poder escribir y filtrar
    // la lista. El <select> original sigue siendo la fuente del valor: getFiltros() y
    // el evento 'change' no cambian. Si TomSelect no cargó, quedan como selects normales.
    const filtrosBuscables = {};
    const IDS_BUSCABLES = ['fltUsuario', 'fltEmpresa', 'fltAccion', 'fltTabla'];

    function initFiltrosBuscables() {
        if (typeof window.TomSelect !== 'function') return;
        IDS_BUSCABLES.forEach(function (id) {
            const el = document.getElementById(id);
            if (!el || el.tomselect) return;
            try {
                filtrosBuscables[id] = new window.TomSelect(el, {
                    create: false,
                    maxOptions: null,
                    allowEmptyOption: false, // la opción vacía ("Todos") pasa a ser el placeholder
                    placeholder: (el.options[0] ? el.options[0].text : 'Todos') + ' — buscar…',
                    dropdownParent: 'body',
                    copyClassesToDropdown: false, // .form-select deformaría el desplegable
                    dropdownClass: 'ts-dropdown log-ts-dropdown',
                    selectOnTab: true,
                    render: {
                        no_results: function () {
                            return '<div class="no-results px-2 py-1 text-muted">Sin coincidencias</div>';
                        },
                    },
                    onItemAdd: function () { this.blur(); },
                });
            } catch (e) {
                // Se queda como <select> normal.
            }
        });
    }

    function limpiarFiltroSelect(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (filtrosBuscables[id]) filtrosBuscables[id].clear(true); // silencioso: no recarga por cada uno
        else el.value = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        initFiltrosBuscables();

        // Auditoría: búsqueda con debounce.
        const input = document.getElementById('logInputBuscar');
        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timerBusqueda);
                timerBusqueda = setTimeout(() => LOGSIS_cargarListado(1), 400);
            });
        }

        // Contenido del mensaje: busca dentro del JSON del evento. Es una consulta
        // pesada (sin índice), así que se espera un poco más que en el buscador
        // normal y no se dispara con 1-2 letras; con Enter se fuerza igual.
        // Referencia (cliente/proveedor/empleado/producto): mismo comportamiento, porque
        // resuelve el nombre con una lectura por fila.
        ['fltContenido', 'fltReferencia'].forEach(function (idCampo) {
            const inputContenido = document.getElementById(idCampo);
            if (!inputContenido) return;
            let timerContenido = null;
            inputContenido.addEventListener('input', function () {
                clearTimeout(timerContenido);
                const valor = inputContenido.value.trim();
                if (valor !== '' && valor.length < 3) return;
                timerContenido = setTimeout(() => LOGSIS_cargarListado(1), 600);
            });
            inputContenido.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    clearTimeout(timerContenido);
                    LOGSIS_cargarListado(1);
                }
            });
        });

        document.querySelectorAll('.log-sort').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = th.getAttribute('data-col');
                if (!col) return;
                if (sortCol === col) {
                    sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    sortCol = col;
                    sortDir = 'DESC';
                }
                actualizarIndicadoresOrden('.log-sort', sortCol, sortDir);
                LOGSIS_cargarListado(1);
            });
        });

        ['fltUsuario', 'fltEmpresa', 'fltAccion', 'fltTabla', 'fltDesde', 'fltHasta'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', function () { LOGSIS_cargarListado(1); });
        });

        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function () {
                ['fltUsuario', 'fltEmpresa', 'fltAccion', 'fltTabla'].forEach(limpiarFiltroSelect);
                ['fltContenido', 'fltReferencia'].forEach(function (id) {
                    const inputTxt = document.getElementById(id);
                    if (inputTxt) inputTxt.value = '';
                });
                ['fltDesde', 'fltHasta'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = el.defaultValue;
                });
                const inputB = document.getElementById('logInputBuscar');
                if (inputB) inputB.value = '';
                LOGSIS_cargarListado(1);
            });
        }

        // Intentos de login: búsqueda con debounce.
        const loginInput = document.getElementById('loginInputBuscar');
        if (loginInput) {
            loginInput.addEventListener('input', function () {
                clearTimeout(loginTimerBusqueda);
                loginTimerBusqueda = setTimeout(() => LOGIN_cargarListado(1), 400);
            });
        }

        document.querySelectorAll('.login-sort').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = th.getAttribute('data-col');
                if (!col) return;
                if (loginSortCol === col) {
                    loginSortDir = loginSortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    loginSortCol = col;
                    loginSortDir = 'DESC';
                }
                actualizarIndicadoresOrden('.login-sort', loginSortCol, loginSortDir);
                LOGIN_cargarListado(1);
            });
        });

        ['fltIntResultado', 'fltIntDesde', 'fltIntHasta'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', function () { LOGIN_cargarListado(1); });
        });

        const btnLimpiarIntentos = document.getElementById('btnLimpiarFiltrosIntentos');
        if (btnLimpiarIntentos) {
            btnLimpiarIntentos.addEventListener('click', function () {
                const sel = document.getElementById('fltIntResultado');
                if (sel) sel.value = '';
                ['fltIntDesde', 'fltIntHasta'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = el.defaultValue;
                });
                const inputB = document.getElementById('loginInputBuscar');
                if (inputB) inputB.value = '';
                LOGIN_cargarListado(1);
            });
        }

        // Pestañas
        document.querySelectorAll('.log-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { activarTab(btn.getAttribute('data-tab')); });
        });
    });
})();
