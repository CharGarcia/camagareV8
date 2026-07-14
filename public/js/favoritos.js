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
        menu.addEventListener('click', e => e.stopPropagation());
        
        div.querySelectorAll('.toggle-pestana-vista').forEach(chk => {
            chk.addEventListener('change', () => {
                const ocultas = Array.from(div.querySelectorAll('.toggle-pestana-vista:not(:checked)')).map(c => c.value);
                const style = document.getElementById('estiloVistaPestanas');
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

/**
 * Motor global de ordenamiento de tablas.
 *
 * Engancha todos los `.sortable-header[data-sort]` de un contenedor, alterna la
 * dirección al hacer clic, actualiza los íconos, PERSISTE la preferencia
 * (`__ordenCol__`/`__ordenDir__` vía guardarOrdenacionVista) y llama al callback
 * de recarga del módulo. Reemplaza la lógica inline duplicada en cada vista.
 *
 * @param {string} modulo Nombre del módulo para persistir (ej: 'marcas').
 * @param {function(string,string)} onSort Callback (col, dir) que recarga la tabla.
 * @param {object} [opts] { col, dir, container }.
 *        - col/dir: estado inicial (para pintar el ícono activo al cargar).
 *        - container: selector o elemento donde buscar los encabezados (def: document).
 * @returns {{getSort:function, getDir:function, refreshIcons:function}|null}
 */
window.CMG_initSort = function(modulo, onSort, opts) {
    opts = opts || {};
    const scope = opts.container
        ? (typeof opts.container === 'string' ? document.querySelector(opts.container) : opts.container)
        : document;
    if (!scope) return null;

    const state = {
        col: opts.col || '',
        dir: (opts.dir || 'ASC').toString().toUpperCase()
    };

    function refreshIcons() {
        scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
            const icon = th.querySelector('i');
            if (!icon) return;
            if (th.dataset.sort === state.col) {
                icon.className = (state.dir === 'ASC')
                    ? 'bi bi-sort-alpha-down text-primary ms-1'
                    : 'bi bi-sort-alpha-up text-primary ms-1';
            } else {
                icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
            }
        });
    }

    scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
        if (th.dataset.sortBound === '1') return; // evitar doble binding
        th.dataset.sortBound = '1';
        if (!th.getAttribute('role')) th.setAttribute('role', 'button');

        th.addEventListener('click', () => {
            const f = th.dataset.sort;
            if (state.col === f) {
                state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.col = f;
                state.dir = 'ASC';
            }
            refreshIcons();
            if (typeof window.guardarOrdenacionVista === 'function') {
                window.guardarOrdenacionVista(modulo, state.col, state.dir);
            }
            try {
                onSort(state.col, state.dir);
            } catch (e) {
                console.error('Error en callback de ordenamiento:', e);
            }
        });
    });

    refreshIcons();

    return {
        getSort: () => state.col,
        getDir: () => state.dir,
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

/**
 * Guarda el ordenamiento de columnas de una vista.
 * @param {string} modulo Nombre del módulo (ej: factura-venta)
 * @param {string} col Nombre de la columna (data-sort)
 * @param {string} dir Dirección (ASC/DESC)
 */
window.guardarOrdenacionVista = function(modulo, col, dir) {
    // Normalizar nombre del módulo
    const moduloLimpio = modulo.split('/').pop().replace(/-/g, '_');

    if (typeof guardarPreferenciaVista === 'function') {
        const payload = {
            '__ordenCol__': col,
            '__ordenDir__': dir
        };
        // Usamos una versión simplificada o llamamos directamente a guardarPreferenciaVista
        // para persistir ambos valores en una sola llamada si fuera posible, 
        // pero guardarPreferenciaVista está diseñada para llaves individuales por ahora.
        // Optamos por dos llamadas o una personalizada.
        
        // Mejoramos guardarPreferenciaVista para que acepte un objeto completo si es necesario, 
        // pero para mantener compatibilidad, llamamos dos veces o implementamos el fetch aquí.
        
        // Versión optimizada para ordenación:
        const fd = new FormData();
        fd.append('modulo', moduloLimpio);
        fd.append('vistaPayload', JSON.stringify(payload));
        const url = typeof APP_VISTAS_URL !== 'undefined' ? APP_VISTAS_URL : '/Preferencias/guardarVistaAjax';
        
        fetch(url, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(json => {
                if (json.ok) {
                    console.log(`Ordenación guardada para ${modulo}: ${col} ${dir}`);
                    _cmgReloadPagina();
                }
            })
            .catch(err => console.error('Error guardando ordenación:', err));
    }
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
            marcarEstrella(estrella, true);
        }
    });
}
