/**
 * Posicionamiento de listas flotantes de autocompletado (productos, clientes, …).
 *
 * Estas listas viven colgadas del <body> con `position: fixed` y se pegan al input
 * que las abrió. Tres cosas que hay que respetar y que cada módulo resolvía a mano:
 *
 *  1. Con `position: fixed` las coordenadas son las del viewport, así que a
 *     `getBoundingClientRect()` NO se le suma `window.scrollY` / `scrollX`.
 *     Sumarlo empuja la lista hacia abajo tanto como se haya scrolleado: en el
 *     celular terminaba fuera de la pantalla, debajo del teclado.
 *  2. El alto visible con el teclado abierto lo da `window.visualViewport`, no
 *     `window.innerHeight` (que no descuenta el teclado). Si abajo no cabe, la
 *     lista se abre hacia arriba del input.
 *  3. En el celular el input MISMO suele quedar tapado por el teclado. Los modales
 *     de detalle (Pedidos, Órdenes de Compra, Consignaciones) ponen la tabla de
 *     productos en el tercio inferior: cuando el `modal-body` ya no puede subir
 *     más el campo, este queda al ras del teclado o directamente debajo. Antes, en
 *     ese caso la lista se escondía (`d-none`) y el usuario escribía el código o la
 *     descripción sin ver nunca un resultado. Ahora, cuando no hay hueco ni arriba
 *     ni abajo del input, la lista se ancla al borde inferior de lo que SÍ se ve
 *     —justo encima del teclado— y ocupa el ancho de la pantalla. Solo en
 *     escritorio se cierra al perder de vista el input, porque ahí siempre hay
 *     sitio para mostrarla a su lado.
 *
 * Uso:
 *     CMG_anclarDropdown(dropdown, input, { anchoMinimo: 350 });  // abre y posiciona
 *     CMG_reanclarDropdown();                                     // tras repintar su contenido
 *
 * El reposicionamiento por teclado, scroll del modal o rotación es automático
 * mientras la lista esté visible (sin la clase `d-none`).
 */
(function () {
    'use strict';

    if (window.CMG_anclarDropdown) return; // ya cargado por otra vista

    var activo    = null;   // { dropdown, input, opts } de la última lista abierta
    var MOVIL_MAX = 767.98; // mismo corte que las media queries del sistema

    function posicionar(dropdown, input, opts) {
        var margen      = 8;
        var anchoMinimo = opts.anchoMinimo != null ? opts.anchoMinimo : 350;
        var altoMaximo  = opts.altoMaximo != null ? opts.altoMaximo : 250;
        // Alto por debajo del cual un hueco no sirve para mostrar la lista: con
        // menos que esto solo se vería un renglón cortado, así que conviene
        // anclarla encima del teclado en lugar de embutirla ahí. Antes este valor
        // se usaba como alto MÍNIMO forzado, y en un hueco de casi cero la lista
        // se dibujaba igual: 110px de resultados debajo del teclado.
        var altoUtil    = 120;

        var vv = window.visualViewport;
        // getBoundingClientRect() y position:fixed comparten sistema de coordenadas
        // (layout viewport); visualViewport.offsetTop dice dónde arranca la parte
        // visible dentro de él cuando el teclado desplaza la pantalla.
        var visTop    = vv ? vv.offsetTop : 0;
        var visAlto   = vv ? vv.height : window.innerHeight;
        var visAncho  = vv ? vv.width : window.innerWidth;
        var visBottom = visTop + visAlto;
        var movil     = visAncho <= MOVIL_MAX;

        var rect = input.getBoundingClientRect();
        var inputVisible = rect.bottom > visTop && rect.top < visBottom;

        // Escritorio: el input se fue de la pantalla (scroll del modal) y no tiene
        // sentido mostrar la lista pegada a algo que no se ve. En el celular NO se
        // cierra: que el campo quede tapado por el teclado es lo habitual, y es
        // justamente cuando más falta hace ver los resultados.
        if (!inputVisible && !movil) {
            dropdown.classList.add('d-none');
            return;
        }

        var anchoTope = Math.max(160, visAncho - margen * 2);
        // En el celular la lista ocupa el ancho de la pantalla: los nombres de
        // producto son largos y la columna del input es angosta.
        var ancho = movil ? anchoTope : Math.min(Math.max(rect.width, anchoMinimo), anchoTope);
        dropdown.style.minWidth = (movil ? 0 : Math.min(anchoMinimo, anchoTope)) + 'px';
        dropdown.style.width    = ancho + 'px';

        var espacioAbajo  = visBottom - rect.bottom - margen;
        var espacioArriba = rect.top - visTop - margen;

        if (inputVisible && espacioAbajo >= altoUtil) {
            // Debajo del input: el caso normal en escritorio.
            dropdown.style.maxHeight = Math.min(altoMaximo, espacioAbajo) + 'px';
            dropdown.style.bottom    = 'auto';
            dropdown.style.top       = (rect.bottom + 2) + 'px';
        } else if (inputVisible && espacioArriba >= altoUtil) {
            // Encima del input. Anclada por abajo (no por arriba) para que quede
            // pegada a él aunque la lista traiga pocos resultados.
            dropdown.style.maxHeight = Math.min(altoMaximo, espacioArriba) + 'px';
            dropdown.style.top       = 'auto';
            dropdown.style.bottom    = Math.max(0, window.innerHeight - rect.top + 2) + 'px';
        } else {
            // No hay hueco aprovechable a ningún lado del input, o el input está
            // tapado por el teclado: la lista se pega al borde inferior del área
            // visible. El `bottom` se mide contra el layout viewport (que es lo que
            // usa position:fixed), de ahí la resta entre innerHeight y visBottom.
            dropdown.style.maxHeight = Math.max(altoUtil, Math.min(altoMaximo, visAlto - margen * 2)) + 'px';
            dropdown.style.top       = 'auto';
            dropdown.style.bottom    = Math.max(margen, window.innerHeight - visBottom + margen) + 'px';
        }

        var left = movil ? margen : Math.min(rect.left, visAncho - ancho - margen);
        dropdown.style.left = Math.max(margen, left) + 'px';
    }

    /**
     * El toque sobre la lista no debe robarle el foco al input: si lo hiciera, el
     * teclado se cerraría, el layout saltaría y el `blur` del módulo escondería la
     * lista antes de que el dedo termine el gesto. preventDefault() en mousedown
     * evita ese blur — en táctil el mousedown sintético llega antes del cambio de
     * foco. Es el mismo recurso que ya usaba a mano el buscador de clientes.
     */
    function protegerFoco(dropdown) {
        if (dropdown.dataset.cmgFocoProtegido) return;
        dropdown.dataset.cmgFocoProtegido = '1';
        dropdown.addEventListener('mousedown', function (e) { e.preventDefault(); });
    }

    /** Muestra la lista pegada al input y la deja como la lista activa. */
    window.CMG_anclarDropdown = function (dropdown, input, opts) {
        if (!dropdown || !input) return;
        activo = { dropdown: dropdown, input: input, opts: opts || {} };
        protegerFoco(dropdown);
        dropdown.classList.remove('d-none');
        posicionar(dropdown, input, activo.opts);
    };

    /** Vuelve a pegar la lista activa al input (tras repintar su contenido). */
    window.CMG_reanclarDropdown = function () {
        if (!activo) return;
        var dropdown = activo.dropdown;
        if (!dropdown || dropdown.classList.contains('d-none')) return;
        if (!document.body.contains(activo.input)) return;
        posicionar(dropdown, activo.input, activo.opts);
    };

    // El teclado del celular aparece DESPUÉS de abrirse la lista y cambia el alto
    // visible; el cuerpo del modal puede scrollear por debajo de ella.
    // capture: true porque el scroll de un contenedor no burbujea.
    window.addEventListener('scroll', window.CMG_reanclarDropdown, { passive: true, capture: true });
    window.addEventListener('resize', window.CMG_reanclarDropdown, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', window.CMG_reanclarDropdown, { passive: true });
        window.visualViewport.addEventListener('scroll', window.CMG_reanclarDropdown, { passive: true });
    }
})();
