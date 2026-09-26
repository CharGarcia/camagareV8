/**
 * Tabla de detalle de los documentos de venta (Factura de Venta, Recibos de
 * Venta): que el Código y la Descripción de cada línea se vean completos.
 *
 *  1. La descripción (textarea) crece en alto con su texto. El alto fijo de 30px
 *     de `.modal-factura .input-detalle` la dejaba en poco más de una línea y el
 *     resto quedaba escondido tras una barra de scroll. Pasadas ~10 líneas, el
 *     campo muestra su propia barra.
 *  2. Las columnas cuyo <th> lleva `data-det-col` ("codigo" / "descripcion") se
 *     ensanchan arrastrando el borde derecho del encabezado, o con doble clic en
 *     ese borde para ajustarlas al texto más largo. El ancho se guarda por
 *     usuario en las preferencias del módulo.
 *
 * Uso (una vez al cargar la vista, antes de crear filas):
 *     const DET = CMG_detalleColumnas({
 *         tabla:   '#m-tabla-detalle',
 *         tbody:   '#m-tbodyDetalle',
 *         modal:   '#modalNuevaFactura',
 *         anchos:  <?= json_encode((object) PreferenciasHelper::getAnchosDetalle($vistaConfig)) ?>,
 *         modulo:  RUTA_MODULO,          // p. ej. 'modulos/factura-venta'
 *         urlBase: B_URL,
 *     });
 *     // en la función que crea cada fila:
 *     DET.engancharDescripcion(tr.querySelector('.input-descripcion'));
 *
 * Por qué se guarda así y no con guardarPreferenciaVista() de favoritos.js:
 *  - guardarPreferenciaVista() recarga la página, y en el modal puede haber un
 *    documento a medio editar.
 *  - Va en una clave propia de __vista__ (__detalle_anchos__): __columnas_anchos__
 *    es la del listado y el servidor reemplaza la clave entera, así que
 *    compartirla haría que el listado y el modal se pisaran los anchos.
 *
 * El CSS vive en public/css/app.css (th[data-det-col], .cmg-det-anchos-usuario);
 * el asa es la misma .cmg-resizer de los listados.
 */
(function () {
    'use strict';

    const ALTO_MIN = 30;    // el alto de siempre de .input-detalle (una línea)
    const ALTO_MAX = 160;   // ~10 líneas; más allá, scroll dentro del campo
    const LIMITES  = { codigo: [70, 420], descripcion: [150, 700] };   // [mín, máx] px
    const CAMPOS   = { codigo: 'input.input-codigo', descripcion: 'textarea.input-descripcion' };
    const CLASE_ANCHOS_USUARIO = 'cmg-det-anchos-usuario';

    const valueNativo = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');

    // Carga en bloque (abrir un documento de cientos de líneas): medir el alto de
    // cada descripción al asignarla obliga al navegador a recalcular el diseño de
    // toda la tabla —que va creciendo— varias veces por línea (O(n²)). Mientras
    // dura la pausa no se mide nada; al terminar, quien la pidió llama a
    // ajustarDescripciones(), que mide todas de una vez.
    let pausado = false;

    /** Ajusta el alto de un textarea de descripción a su texto. */
    function ajustarAltura(ta) {
        if (pausado) return;
        // Oculto (modal cerrado u otra pestaña): scrollHeight vale 0. Se recalcula
        // al mostrarse (shown.bs.modal / shown.bs.tab).
        if (!ta || ta.offsetParent === null) return;
        // Primero al mínimo: medido desde un alto mayor, scrollHeight no bajaría
        // al borrar texto. En línea y con !important para ganarle a los 30px de
        // .modal-factura .input-detalle.
        ta.style.setProperty('height', ALTO_MIN + 'px', 'important');
        const alto = Math.min(Math.max(ta.scrollHeight, ALTO_MIN), ALTO_MAX);
        ta.style.setProperty('height', alto + 'px', 'important');
        ta.style.overflowY = ta.scrollHeight > alto ? 'auto' : 'hidden';
    }

    function elemento(x) {
        return typeof x === 'string' ? document.querySelector(x) : (x || null);
    }

    window.CMG_detalleColumnas = function (opts) {
        const tabla   = elemento(opts.tabla);
        const selBody = opts.tbody;
        const modal   = elemento(opts.modal);
        const anchos  = opts.anchos || {};
        const limites = Object.assign({}, LIMITES, opts.limites || {});
        const campos  = Object.assign({}, CAMPOS, opts.campos || {});

        /**
         * Ajusta todas las descripciones en tres pasadas (leer, escribir, leer,
         * escribir agrupados) en vez de medir y escribir fila por fila: así el
         * navegador recalcula el diseño un par de veces en total, no una por línea.
         */
        function ajustarDescripciones() {
            if (pausado) return;
            const tas = Array.from(document.querySelectorAll(`${selBody} ${campos.descripcion}`))
                .filter(ta => ta.offsetParent !== null);   // ocultas: se ajustan al mostrarse
            if (!tas.length) return;
            tas.forEach(ta => ta.style.setProperty('height', ALTO_MIN + 'px', 'important'));
            const contenido = tas.map(ta => ta.scrollHeight);
            tas.forEach((ta, i) => {
                const alto = Math.min(Math.max(contenido[i], ALTO_MIN), ALTO_MAX);
                ta.style.setProperty('height', alto + 'px', 'important');
                ta.style.overflowY = contenido[i] > alto ? 'auto' : 'hidden';
            });
        }

        /** Pausa (true) o reanuda (false) el ajuste de alto línea por línea. Al
         *  reanudar se ajustan todas las descripciones de una vez. */
        function pausarAjuste(estado) {
            pausado = !!estado;
            if (!pausado) ajustarDescripciones();
        }

        /**
         * Engancha el ajuste de alto a la descripción de una fila: al escribir, y
         * también cuando el código le asigna el texto (elegir un producto, abrir el
         * documento, duplicar, importar…). Esos caminos hacen `ta.value = …`, que no
         * dispara 'input'; en vez de repetir la llamada en cada uno, se envuelve el
         * setter de `value` de este elemento.
         */
        function engancharDescripcion(ta) {
            if (!ta || ta.dataset.altoAuto === '1') return;
            ta.dataset.altoAuto = '1';
            ta.style.whiteSpace = 'pre-wrap';   // la tabla es .text-nowrap
            Object.defineProperty(ta, 'value', {
                configurable: true,
                get() { return valueNativo.get.call(this); },
                set(v) { valueNativo.set.call(this, v); ajustarAltura(this); },
            });
            ta.addEventListener('input', () => ajustarAltura(ta));
            ajustarAltura(ta);
        }

        /** Fija el ancho (px) de una columna, dentro de sus límites. */
        function fijarAncho(th, px) {
            const [min, max] = limites[th.dataset.detCol] || [60, 600];
            px = Math.round(Math.min(Math.max(px, min), max));
            ['width', 'min-width', 'max-width'].forEach(p => th.style.setProperty(p, px + 'px', 'important'));
            tabla?.classList.add(CLASE_ANCHOS_USUARIO);
            return px;
        }

        /** Ancho que necesita el texto más largo de la columna (doble clic en el borde). */
        function anchoContenido(th) {
            const col = th.dataset.detCol;
            const ctx = (anchoContenido._ctx ||= document.createElement('canvas').getContext('2d'));
            const fuente = cs => `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;

            let necesario = 0;
            if (campos[col]) {
                document.querySelectorAll(`${selBody} ${campos[col]}`).forEach(el => {
                    const cs = getComputedStyle(el);
                    ctx.font = fuente(cs);
                    const texto = ctx.measureText(String(el.value || '')).width;
                    // Relleno del campo + margen para el cursor y, en la descripción,
                    // la barra de scroll si llegara a aparecer.
                    const extra = parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight)
                        + (el.tagName === 'TEXTAREA' ? 12 : 6);
                    necesario = Math.max(necesario, texto + extra);
                });
            }

            // El encabezado también tiene que caber (va en mayúsculas por CSS), más el asa.
            const csTh = getComputedStyle(th);
            ctx.font = fuente(csTh);
            const titulo = ctx.measureText(th.firstChild?.textContent?.trim().toUpperCase() || '').width
                + parseFloat(csTh.paddingLeft) + parseFloat(csTh.paddingRight) + 8;

            return Math.max(necesario, titulo);
        }

        let timerGuardar = null;
        /** Guarda los anchos en las preferencias del usuario, SIN recargar la página. */
        function guardarAnchos() {
            const valores = {};
            tabla?.querySelectorAll('th[data-det-col]').forEach(th => {
                const px = parseInt(th.style.getPropertyValue('width'), 10);
                if (px > 0) valores[th.dataset.detCol] = px;
            });
            clearTimeout(timerGuardar);
            timerGuardar = setTimeout(() => {
                const fd = new FormData();
                fd.append('modulo', opts.modulo);
                fd.append('vistaPayload', JSON.stringify({ __detalle_anchos__: valores }));
                fetch(`${opts.urlBase}/Preferencias/guardarVistaAjax`, { method: 'POST', body: fd })
                    .catch(() => { /* el ancho queda aplicado en esta sesión aunque no se guarde */ });
            }, 400);
        }

        tabla?.querySelectorAll('th[data-det-col]').forEach(th => {
            const col = th.dataset.detCol;
            if (anchos[col]) fijarAncho(th, anchos[col]);

            const asa = document.createElement('div');
            asa.className = 'cmg-resizer';
            asa.title = 'Arrastra para cambiar el ancho · doble clic para ajustarlo al texto';
            th.appendChild(asa);

            asa.addEventListener('pointerdown', e => {
                if (e.button !== 0) return;
                e.preventDefault();
                asa.setPointerCapture(e.pointerId);
                const x0 = e.clientX;
                const w0 = th.getBoundingClientRect().width;
                let movido = false;
                asa.classList.add('resizing');
                const mover = ev => {
                    // Un clic suelto (o el primero de un doble clic) no debe fijar ni
                    // guardar nada: solo cuenta si el puntero se desplazó.
                    if (!movido && Math.abs(ev.clientX - x0) < 3) return;
                    movido = true;
                    fijarAncho(th, w0 + ev.clientX - x0);
                };
                const soltar = () => {
                    asa.classList.remove('resizing');
                    asa.removeEventListener('pointermove', mover);
                    asa.removeEventListener('pointerup', soltar);
                    asa.removeEventListener('pointercancel', soltar);
                    if (movido) {
                        ajustarDescripciones();
                        guardarAnchos();
                    }
                };
                asa.addEventListener('pointermove', mover);
                asa.addEventListener('pointerup', soltar);
                asa.addEventListener('pointercancel', soltar);
            });

            asa.addEventListener('dblclick', e => {
                e.preventDefault();
                fijarAncho(th, anchoContenido(th));
                ajustarDescripciones();
                guardarAnchos();
            });
        });

        // Recalcular los altos cuando la tabla se vuelve visible o cambia de ancho:
        // mientras está oculta no se pueden medir.
        modal?.addEventListener('shown.bs.modal', ajustarDescripciones);
        modal?.addEventListener('shown.bs.tab', ajustarDescripciones);
        let timerResize = null;
        window.addEventListener('resize', () => {
            clearTimeout(timerResize);
            timerResize = setTimeout(ajustarDescripciones, 150);
        });

        return { engancharDescripcion, ajustarDescripciones, pausarAjuste };
    };
})();
