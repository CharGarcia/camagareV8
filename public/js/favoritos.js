/**
 * FAVORITOS.JS - Sistema de Preferencias y Vistas
 */
document.addEventListener('DOMContentLoaded', () => {
    initFavoritosEstrellas();
    initConfiguracionVistas();
    initResizableColumns();
});

/**
 * Permite redimensionar columnas de tablas que tengan data-col en sus th.
 */
function initResizableColumns() {
    const tables = document.querySelectorAll('.cmg-table-card table');
    
    tables.forEach(table => {
        const headerRow = table.querySelector('thead tr');
        if (!headerRow) return;
        
        const cols = headerRow.querySelectorAll('th[data-col]');
        const modulo = table.closest('.card')?.querySelector('.dropdown-menu')?.dataset.modulo 
                    || window.location.pathname.split('/').pop().replace(/-/g, '_');

        cols.forEach(th => {
            // No añadir duplicados
            if (th.querySelector('.cmg-resizer')) return;

            const resizer = document.createElement('div');
            resizer.classList.add('cmg-resizer');
            th.appendChild(resizer);

            let x = 0;
            let w = 0;

            const mouseDownHandler = (e) => {
                x = e.clientX;
                w = parseInt(window.getComputedStyle(th).width, 10);
                resizer.classList.add('resizing');

                document.addEventListener('mousemove', mouseMoveHandler);
                document.addEventListener('mouseup', mouseUpHandler);
            };

            const mouseMoveHandler = (e) => {
                const dx = e.clientX - x;
                const newWidth = `${w + dx}px`;
                th.style.setProperty('width', newWidth, 'important');
                th.style.setProperty('min-width', newWidth, 'important');
                th.style.setProperty('max-width', newWidth, 'important');
            };

            const mouseUpHandler = () => {
                resizer.classList.remove('resizing');
                document.removeEventListener('mousemove', mouseMoveHandler);
                document.removeEventListener('mouseup', mouseUpHandler);

                // Guardar preferencias de anchos
                guardarAnchosColumnas(table, modulo);
            };

            resizer.addEventListener('mousedown', mouseDownHandler);
        });
    });
}

function guardarAnchosColumnas(table, modulo) {
    const anchos = {};
    table.querySelectorAll('thead th[data-col]').forEach(th => {
        anchos[th.dataset.col] = parseInt(th.style.width || th.offsetWidth);
    });
    guardarPreferenciaVista(modulo, '__columnas_anchos__', anchos, 'Anchos de columna guardados');
}

// 1. Lógica de Estrellas Favoritos
function initFavoritosEstrellas() {
    if (typeof APP_FAVORITOS === 'undefined') return;
    
    document.querySelectorAll('.btn-favorito').forEach(estrella => {
        if (estrella.dataset.iniciado === '1') return;
        estrella.dataset.iniciado = '1';
        
        const campo = estrella.dataset.campo;
        const targetId = estrella.dataset.target;
        const selectEl = document.querySelector(targetId);

        if (APP_FAVORITOS[campo] && selectEl) {
            if (selectEl.value === APP_FAVORITOS[campo]) marcarEstrella(estrella, true);
            selectEl.addEventListener('change', () => {
                marcarEstrella(estrella, selectEl.value === APP_FAVORITOS[campo]);
            });
        }

        estrella.addEventListener('click', async () => {
            if (!selectEl || (!selectEl.value && !APP_FAVORITOS[campo])) return alert('Seleccione un valor');

            const valorActual = selectEl.value;
            const esEliminar = (APP_FAVORITOS[campo] && APP_FAVORITOS[campo] == valorActual);
            const valorFinal = esEliminar ? '' : valorActual;

            try {
                const fd = new FormData();
                fd.append('modulo', estrella.dataset.modulo);
                fd.append('campo', campo);
                fd.append('valor', valorFinal);
                const resp = await fetch(typeof APP_FAVORITOS_URL !== 'undefined' ? APP_FAVORITOS_URL : '/Preferencias/guardarAjax', {
                    method: 'POST',
                    body: fd
                });
                const res = await resp.json();
                if (res.ok) {
                    if (esEliminar) {
                        delete APP_FAVORITOS[campo];
                        marcarEstrella(estrella, false);
                        showToast('Favorito eliminado', 'info');
                    } else {
                        APP_FAVORITOS[campo] = valorFinal;
                        marcarEstrella(estrella, true);
                        showToast('Favorito guardado', 'success');
                    }
                }
            } catch (e) {
                console.error(e);
            }
        });
    });
}

function marcarEstrella(el, activa) {
    if (activa) {
        el.classList.remove('bi-star', 'text-muted');
        el.classList.add('bi-star-fill', 'text-warning');
    } else {
        el.classList.remove('bi-star-fill', 'text-warning');
        el.classList.add('bi-star', 'text-muted');
    }
}

// 2. Lógica de Columnas y Pestañas
function initConfiguracionVistas() {
    // Manejar Columnas
    document.querySelectorAll('.dropdown-vista-columnas').forEach(div => {
        const menu = div.querySelector('.dropdown-menu');
        const modulo = menu.dataset.modulo;
        menu.addEventListener('click', e => e.stopPropagation());
        
        div.querySelectorAll('.toggle-columna-vista').forEach(chk => {
            chk.addEventListener('change', () => {
                const ocultas = Array.from(div.querySelectorAll('.toggle-columna-vista:not(:checked)')).map(c => c.value);
                const style = document.getElementById('estiloVistaColumnas');
                if (style) {
                    style.innerHTML = ocultas.map(oc => `th[data-col="${oc}"], td[data-col="${oc}"] { display: none !important; }`).join('\n');
                }
                guardarPreferenciaVista(modulo, '__columnas_ocultas__', ocultas, 'Columnas actualizadas');
            });
        });
    });

    // Manejar Pestañas (Modales)
    document.querySelectorAll('.dropdown-vista-pestanas').forEach(div => {
        const menu = div.querySelector('.dropdown-menu');
        const modulo = menu.dataset.modulo;
        // Los modales compartidos (cliente, producto, proveedor…) escriben su CSS
        // en un <style> propio para no pisar el de la página que los incluye.
        // Sin esto se aplicaba siempre sobre 'estiloVistaPestanas', que en esas
        // páginas es el de OTRO módulo: al ocultar una pestaña del modal se
        // borraban las reglas del listado de fondo.
        const styleId = menu.dataset.styleId || 'estiloVistaPestanas';
        menu.addEventListener('click', e => e.stopPropagation());

        div.querySelectorAll('.toggle-pestana-vista').forEach(chk => {
            chk.addEventListener('change', () => {
                const ocultas = Array.from(div.querySelectorAll('.toggle-pestana-vista:not(:checked)')).map(c => c.value);
                const style = document.getElementById(styleId);
                if (style) {
                    style.innerHTML = ocultas.map(oc => `.nav-link[data-bs-target="#${oc}"], #${oc} { display: none !important; }`).join('\n');
                }
                guardarPreferenciaVista(modulo, '__pestanas_ocultas__', ocultas, 'Pestañas actualizadas');
            });
        });
    });
}

/**
 * Recarga la página actual tras guardar una preferencia de VISTA (columnas,
 * anchos, pestañas u ordenamiento) para que el listado se re-renderice ya
 * aplicando la preferencia en el servidor. Se protege contra recargas
 * múltiples y aplica un pequeño retraso para que el toast alcance a mostrarse.
 * No se usa para favoritos de campo (esos se aplican dentro del modal).
 */
let _cmgReloadTimer = null;
function _cmgReloadPagina() {
    if (_cmgReloadTimer) return;
    _cmgReloadTimer = setTimeout(() => { window.location.reload(); }, 250);
}

let _timerVistaAjax = {};
function guardarPreferenciaVista(modulo, key, valor, msg) {
    const moduloLimpio = modulo.split('/').pop().replace(/-/g, '_');
    const timerKey = moduloLimpio + key;
    clearTimeout(_timerVistaAjax[timerKey]);
    _timerVistaAjax[timerKey] = setTimeout(async () => {
        try {
            const fd = new FormData();
            fd.append('modulo', moduloLimpio);
            const payload = {}; payload[key] = valor;
            fd.append('vistaPayload', JSON.stringify(payload));
            
            const url = typeof APP_VISTAS_URL !== 'undefined' ? APP_VISTAS_URL : '/Preferencias/guardarVistaAjax';
            const resp = await fetch(url, {method:'POST', body:fd});
            const res = await resp.json();
            
            if (res.ok) {
                if (msg) {
                    showToast(msg, 'success');
                }
                _cmgReloadPagina();
            } else {
                alert("Error al guardar: " + res.error);
            }
        } catch(e) {
            console.error(e);
            alert("Error de conexión al guardar preferencias");
        }
    }, 500);
}

/** Tope de columnas simultáneas en el orden múltiple (igual que OrdenListado::MAX_CRITERIOS). */
const CMG_SORT_MAX = 3;

/** Deja la lista de criterios en forma canónica: sin vacíos, sin repetidas, con tope. */
function _cmgNormalizarSorts(lista, max) {
    const out = [];
    const vistas = {};
    (lista || []).forEach(s => {
        if (!s) return;
        const col = (typeof s === 'string' ? s : (s.col || '')).toString().trim();
        if (!col || vistas[col]) return;
        vistas[col] = true;
        const dir = ((s.dir || 'ASC').toString().toUpperCase() === 'DESC') ? 'DESC' : 'ASC';
        if (out.length < Math.max(1, max || CMG_SORT_MAX)) out.push({ col: col, dir: dir });
    });
    return out;
}

/** Serializa los criterios al formato que entiende OrdenListado::parsear() en PHP. */
window.CMG_ordenParam = function(sorts) {
    return (sorts || []).map(s => s.col + ':' + s.dir).join(',');
};

/**
 * Motor global de ordenamiento de tablas.
 *
 * Engancha todos los `.sortable-header[data-sort]` de un contenedor, alterna la
 * dirección al hacer clic, actualiza los íconos, PERSISTE la preferencia
 * (`__ordenCol__`/`__ordenDir__` y, en modo múltiple, `__ordenMulti__`) y llama
 * al callback de recarga del módulo. Reemplaza la lógica inline duplicada en
 * cada vista.
 *
 * ORDEN MÚLTIPLE (`multi: true`, opcional): con Shift+clic el usuario encadena
 * columnas — ordenar por ciudad y, dentro de cada ciudad, por nombre. El ícono
 * de cada columna activa lleva un superíndice con su prioridad.
 *   - clic normal   → deja SOLO esa columna (alterna ASC/DESC si ya era la única).
 *   - Shift+clic    → ASC → DESC → la saca del orden (ciclo de tres estados).
 * Es opt-in a propósito: sin el backend preparado (OrdenListado::clausula en el
 * repositorio) la UI mostraría dos criterios y el listado aplicaría uno solo.
 *
 * @param {string} modulo Nombre del módulo para persistir (ej: 'marcas').
 * @param {function(string,string,Array)} onSort Callback (col, dir, sorts) que recarga
 *        la tabla. Los dos primeros argumentos son el criterio principal, así que
 *        los callbacks de una sola columna siguen funcionando sin cambios.
 * @param {object} [opts] { col, dir, sorts, multi, maxCols, container, reload }.
 *        - col/dir: estado inicial de una columna (para pintar el ícono al cargar).
 *        - sorts: estado inicial múltiple [{col,dir},…]; tiene prioridad sobre col/dir.
 *        - multi: true habilita Shift+clic. Por defecto false (comportamiento clásico).
 *        - maxCols: tope de columnas encadenadas (def. 3).
 *        - container: selector o elemento donde buscar los encabezados (def: document).
 *        - reload: false cuando el callback ya repinta TODO lo que depende del
 *          orden (filas, paginación, contador, enlaces de exportación). Por
 *          defecto guardar la preferencia recarga la página entera, lo que en un
 *          módulo que recarga por AJAX significa listar dos veces lo mismo.
 *          En modo múltiple el defecto es NO recargar: encadenar columnas con
 *          Shift dispararía una recarga por clic.
 * @returns {{getSort:function, getDir:function, getSorts:function, getOrdenParam:function, refreshIcons:function}|null}
 */
window.CMG_initSort = function(modulo, onSort, opts) {
    opts = opts || {};
    const scope = opts.container
        ? (typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container)
        : document;
    if (!scope) return null;

    const multi = opts.multi === true;
    const maxCols = Math.max(1, opts.maxCols || CMG_SORT_MAX);
    // En modo múltiple no se recarga salvo que el módulo lo pida explícitamente.
    const reload = (opts.reload === undefined) ? !multi : (opts.reload !== false);

    let sorts = _cmgNormalizarSorts(
        (opts.sorts && opts.sorts.length) ? opts.sorts : (opts.col ? [{ col: opts.col, dir: opts.dir }] : []),
        maxCols
    );

    const indiceDe = (col) => sorts.findIndex(s => s.col === col);

    /** Superíndice con la prioridad (1, 2, 3). Solo se pinta si hay más de un criterio. */
    function pintarPrioridad(th, n) {
        let sup = th.querySelector('.cmg-sort-prio');
        if (!n) {
            if (sup) sup.remove();
            return;
        }
        if (!sup) {
            sup = document.createElement('sup');
            sup.className = 'cmg-sort-prio text-primary';
            const icon = th.querySelector('i');
            if (icon) icon.insertAdjacentElement('afterend', sup);
            else th.appendChild(sup);
        }
        sup.textContent = String(n);
    }

    function refreshIcons() {
        scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
            const icon = th.querySelector('i');
            if (!icon) return;
            const i = indiceDe(th.dataset.sort);
            if (i === -1) {
                icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                pintarPrioridad(th, 0);
            } else {
                icon.className = (sorts[i].dir === 'ASC')
                    ? 'bi bi-sort-alpha-down text-primary ms-1'
                    : 'bi bi-sort-alpha-up text-primary ms-1';
                pintarPrioridad(th, sorts.length > 1 ? (i + 1) : 0);
            }
        });
    }

    scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
        if (th.dataset.sortBound === '1') return; // evitar doble binding
        th.dataset.sortBound = '1';
        if (!th.getAttribute('role')) th.setAttribute('role', 'button');
        // Sin pista nadie descubre el Shift+clic. Si el encabezado ya tiene su propio
        // title (p. ej. "Estado" en Pedidos, que explica su orden por flujo), la pista
        // se le AÑADE: omitirla dejaba justo a esas columnas sin ninguna indicación.
        if (multi) {
            const titulo = th.getAttribute('title');
            th.setAttribute('title', titulo
                ? titulo + ' — Shift+clic para ordenar por varias columnas'
                : 'Clic para ordenar · Shift+clic para ordenar por varias columnas');
        }

        th.addEventListener('click', (ev) => {
            const f = th.dataset.sort;
            const i = indiceDe(f);

            if (multi && ev && ev.shiftKey) {
                if (i === -1) {
                    if (sorts.length >= maxCols) {
                        showToast('Máximo ' + maxCols + ' columnas de ordenamiento', 'info');
                        return;
                    }
                    sorts.push({ col: f, dir: 'ASC' });
                } else if (sorts[i].dir === 'ASC') {
                    sorts[i].dir = 'DESC';
                } else {
                    sorts.splice(i, 1); // tercer Shift+clic: la saca del orden
                }
            } else if (i === 0 && sorts.length === 1) {
                sorts = [{ col: f, dir: sorts[0].dir === 'ASC' ? 'DESC' : 'ASC' }];
            } else {
                sorts = [{ col: f, dir: 'ASC' }];
            }

            refreshIcons();
            if (typeof window.CMG_guardarOrden === 'function') {
                window.CMG_guardarOrden(modulo, sorts, { reload: reload });
            }
            try {
                const principal = sorts[0] || { col: '', dir: 'ASC' };
                onSort(principal.col, principal.dir, sorts.slice());
            } catch (e) {
                console.error('Error en callback de ordenamiento:', e);
            }
        });
    });

    refreshIcons();

    return {
        getSort: () => (sorts[0] ? sorts[0].col : ''),
        getDir: () => (sorts[0] ? sorts[0].dir : 'ASC'),
        getSorts: () => sorts.slice(),
        getOrdenParam: () => window.CMG_ordenParam(sorts),
        refreshIcons: refreshIcons
    };
};

/**
 * Persiste un objeto de claves arbitrarias en la vista de un módulo (mezcla
 * incremental en `__vista__`). Útil para vistas con más de una tabla que
 * necesitan claves de orden propias (p. ej. __ordenColTipos__).
 * @param {string} modulo Nombre del módulo.
 * @param {object} payload Objeto { clave: valor, ... } a guardar.
 * @param {object} [opts] { reload }. Por defecto recarga la página (listados).
 *        Pasar { reload: false } en vistas que ya se re-renderizan solas
 *        (p. ej. el dashboard), para no interrumpir al usuario.
 */
window.CMG_guardarVista = function(modulo, payload, opts) {
    opts = opts || {};
    const recargar = opts.reload !== false;
    const moduloLimpio = modulo.split('/').pop().replace(/-/g, '_');
    const url = typeof APP_VISTAS_URL !== 'undefined' ? APP_VISTAS_URL : '/Preferencias/guardarVistaAjax';
    const fd = new FormData();
    fd.append('modulo', moduloLimpio);
    fd.append('vistaPayload', JSON.stringify(payload || {}));
    fetch(url, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(json => { if (json && json.ok && recargar) _cmgReloadPagina(); })
        .catch(err => console.error('Error guardando vista:', err));
};

let _timerOrdenVista = {};
/**
 * Persiste el ordenamiento de una vista (una o varias columnas).
 *
 * Guarda `__ordenMulti__` con la lista completa y, ADEMÁS, `__ordenCol__` /
 * `__ordenDir__` con el criterio principal: así los módulos y controladores que
 * todavía leen solo esas dos claves siguen viendo exactamente lo de siempre.
 *
 * El POST va con debounce porque encadenar columnas con Shift+clic genera varios
 * cambios seguidos y no tiene sentido guardar en cada uno.
 *
 * @param {string} modulo Nombre del módulo (ej: factura-venta)
 * @param {Array} sorts Lista [{col, dir}, …] en orden de prioridad.
 * @param {object} [opts] { reload }. reload:false evita recargar la página al
 *        guardar; es para módulos que ya repintan el listado por AJAX con el
 *        nuevo orden (recargar solo repetiría la consulta). Por omisión recarga.
 */
window.CMG_guardarOrden = function(modulo, sorts, opts) {
    const moduloLimpio = modulo.split('/').pop().replace(/-/g, '_');
    const recargar = !(opts && opts.reload === false);
    const lista = _cmgNormalizarSorts(sorts, CMG_SORT_MAX);
    const principal = lista[0] || null;

    clearTimeout(_timerOrdenVista[moduloLimpio]);
    _timerOrdenVista[moduloLimpio] = setTimeout(() => {
        const payload = {
            '__ordenMulti__': lista,
            // null y no '' : así el `?? 'defecto'` de los controladores sigue funcionando
            // cuando el usuario deja la tabla sin ningún criterio propio.
            '__ordenCol__': principal ? principal.col : null,
            '__ordenDir__': principal ? principal.dir : null
        };
        const fd = new FormData();
        fd.append('modulo', moduloLimpio);
        fd.append('vistaPayload', JSON.stringify(payload));
        const url = typeof APP_VISTAS_URL !== 'undefined' ? APP_VISTAS_URL : '/Preferencias/guardarVistaAjax';

        fetch(url, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(json => {
                if (json && json.ok && recargar) _cmgReloadPagina();
            })
            .catch(err => console.error('Error guardando ordenación:', err));
    }, 350);
};

/**
 * Guarda el ordenamiento de UNA columna. Se mantiene por compatibilidad con los
 * módulos que la llaman directamente; delega en CMG_guardarOrden.
 * @param {string} modulo Nombre del módulo (ej: factura-venta)
 * @param {string} col Nombre de la columna (data-sort)
 * @param {string} dir Dirección (ASC/DESC)
 * @param {object} [opts] { reload }
 */
window.guardarOrdenacionVista = function(modulo, col, dir, opts) {
    window.CMG_guardarOrden(modulo, col ? [{ col: col, dir: dir }] : [], opts);
};

function showToast(msg, icon) {
    if (typeof Toast !== 'undefined') {
        Toast.fire({ icon: icon, title: msg, timer: 1500, position: 'bottom-end', showConfirmButton: false });
    }
}

window.aplicarFavoritosModal = aplicarFavoritosModal;
function aplicarFavoritosModal(containerSelector = 'body') {
    if (typeof APP_FAVORITOS === 'undefined') return;
    const container = document.querySelector(containerSelector) || document.body;
    container.querySelectorAll('.btn-favorito').forEach(estrella => {
        const campo = estrella.dataset.campo;
        const targetId = estrella.dataset.target;
        const selectEl = document.querySelector(targetId);
        if (selectEl && APP_FAVORITOS[campo] !== undefined) {
            selectEl.value = APP_FAVORITOS[campo];
            selectEl.dispatchEvent(new Event('change'));
            marcarEstrella(estrella, true);
        }
    });
}
