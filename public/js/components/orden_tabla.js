/* ═══════════════════════════════════════════════════════════════════════════
   OrdenTabla — espejo en JavaScript de App\Helpers\OrdenFilas + enganche de
   las cabeceras clicables de una tabla.

   Para listados que ya tienen TODAS sus filas en el navegador (Cuentas por
   Cobrar, Cuentas por Pagar): al hacer clic en una cabecera se reordena en el
   acto, sin volver a consultar al servidor —esos listados no son paginados y
   su consulta es cara—, y la columna elegida viaja además a los exports para
   que el Excel y el PDF salgan en el mismo orden que la pantalla.

   No se usa CMG_initSort (public/js/favoritos.js) a propósito: ese motor
   persiste con guardarOrdenacionVista, que RECARGA la página, y aquí los
   filtros viven solo en el formulario (se perderían). La preferencia se guarda
   igual, con CMG_guardarVista({reload:false}).

   Las reglas de comparación son las mismas del helper PHP (texto en mayúsculas
   y sin acentos, vacíos siempre al final, desempates en el mismo orden): así
   reordenar con un clic da exactamente la misma lista que traerla ordenada del
   servidor. Si se cambia una regla aquí, cambiarla también allá.
   ═══════════════════════════════════════════════════════════════════════════ */
(function (global) {
    'use strict';

    /** Texto comparable: sin espacios al borde, en mayúsculas y sin acentos. */
    function normalizar(texto) {
        return String(texto ?? '')
            .trim()
            .toUpperCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '');
    }

    /** Valor de la fila para un criterio: `valor` (función), `campo`, o la propia clave. */
    function valorDe(fila, def, clave) {
        if (def && typeof def.valor === 'function') return def.valor(fila);
        return fila[(def && def.campo) || clave];
    }

    /** Compara dos filas por un criterio. Los vacíos van al final en ambas direcciones. */
    function comparar(a, b, def, clave, dir) {
        const x = valorDe(a, def, clave);
        const y = valorDe(b, def, clave);

        const vacioX = (x === null || x === undefined || x === '');
        const vacioY = (y === null || y === undefined || y === '');
        if (vacioX || vacioY) {
            if (vacioX && vacioY) return 0;
            return vacioX ? 1 : -1;
        }

        let cmp;
        if ((def && def.tipo) === 'numero') {
            const nx = parseFloat(x) || 0;
            const ny = parseFloat(y) || 0;
            cmp = nx < ny ? -1 : (nx > ny ? 1 : 0);
        } else {
            const sx = normalizar(x);
            const sy = normalizar(y);
            cmp = sx < sy ? -1 : (sx > sy ? 1 : 0);
        }
        return dir === 'ASC' ? cmp : -cmp;
    }

    /**
     * Columna y dirección efectivas: valida contra la lista blanca y cae al orden por
     * defecto cuando la columna no existe (p. ej. una preferencia de otro módulo).
     * @returns {[string,string]}
     */
    function resolver(mapa, defecto, col, dir) {
        const c = String(col || '').trim();
        if (c === '' || !mapa[c]) return [defecto[0], defecto[1]];
        return [c, String(dir || '').toUpperCase() === 'DESC' ? 'DESC' : 'ASC'];
    }

    /**
     * Devuelve una copia de `filas` ordenada por la columna pedida y, a igualdad,
     * por los desempates ({ columna: 'ASC'|'DESC' }, en orden de prioridad).
     */
    function ordenar(filas, mapa, defecto, col, dir, desempates) {
        const [c, d] = resolver(mapa, defecto, col, dir);

        const criterios = [[c, d]];
        Object.entries(desempates || {}).forEach(([k, v]) => {
            if (k !== c && mapa[k]) criterios.push([k, String(v).toUpperCase() === 'DESC' ? 'DESC' : 'ASC']);
        });

        return [...filas].sort((a, b) => {
            for (const [clave, dirc] of criterios) {
                const cmp = comparar(a, b, mapa[clave], clave, dirc);
                if (cmp !== 0) return cmp;
            }
            return 0;
        });
    }

    /**
     * Engancha las cabeceras `.sortable-header[data-sort]` de un contenedor: alterna
     * la dirección al hacer clic, pinta la flecha en la columna activa, persiste la
     * preferencia del usuario (sin recargar) y avisa al módulo para que repinte.
     *
     * @param {object} opts
     *   - modulo:    ruta del módulo para guardar la preferencia (ej. 'modulos/cuentas_por_cobrar').
     *   - contenedor: selector o elemento donde están los `<th>` (por defecto, document).
     *   - getOrden:  () => [col, dir] con el orden actual.
     *   - setOrden:  (col, dir) => void para guardarlo donde lo lea el módulo.
     *   - onSort:    () => void que repinta la tabla con el nuevo orden.
     */
    function engancharCabeceras(opts) {
        const scope = !opts.contenedor
            ? document
            : (typeof opts.contenedor === 'string' ? document.querySelector(opts.contenedor) : opts.contenedor);
        if (!scope) return;

        const pintarIconos = () => {
            const [col, dir] = opts.getOrden();
            scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
                const icono = th.querySelector('i');
                if (!icono) return;
                icono.className = (th.dataset.sort === col)
                    ? (dir === 'ASC' ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1')
                    : 'bi bi-arrow-down-up small text-muted ms-1';
            });
        };

        scope.querySelectorAll('.sortable-header[data-sort]').forEach(th => {
            if (th.dataset.sortBound === '1') return; // evitar doble enganche
            th.dataset.sortBound = '1';
            if (!th.getAttribute('role')) th.setAttribute('role', 'button');

            th.addEventListener('click', () => {
                const [colActual, dirActual] = opts.getOrden();
                // Primer clic en una columna: ascendente (A-Z). Repetir el clic invierte.
                const nuevaDir = (th.dataset.sort === colActual && dirActual === 'ASC') ? 'DESC' : 'ASC';
                opts.setOrden(th.dataset.sort, nuevaDir);
                pintarIconos();
                if (typeof global.CMG_guardarVista === 'function') {
                    global.CMG_guardarVista(opts.modulo, {
                        '__ordenCol__': th.dataset.sort,
                        '__ordenDir__': nuevaDir
                    }, { reload: false });
                }
                if (typeof opts.onSort === 'function') opts.onSort();
            });
        });

        pintarIconos();
        return { pintarIconos };
    }

    global.CMG_OrdenTabla = {
        normalizar,
        resolver,
        ordenar,
        engancharCabeceras
    };
})(window);
