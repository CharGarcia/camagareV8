/**
 * Posicionamiento de listas flotantes de autocompletado (productos, clientes, …).
 *
 * Estas listas viven colgadas del <body> con `position: fixed` y se pegan al input
 * que las abrió. Dos cosas que hay que respetar y que cada módulo resolvía a mano:
 *
 *  1. Con `position: fixed` las coordenadas son las del viewport, así que a
 *     `getBoundingClientRect()` NO se le suma `window.scrollY` / `scrollX`.
 *     Sumarlo empuja la lista hacia abajo tanto como se haya scrolleado: en el
 *     celular terminaba fuera de la pantalla, debajo del teclado.
 *  2. El alto visible con el teclado abierto lo da `window.visualViewport`, no
 *     `window.innerHeight` (que no descuenta el teclado). Si abajo no cabe, la
 *     lista se abre hacia arriba del input.
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

    var activo = null; // { dropdown, input, opts } de la última lista abierta

    function posicionar(dropdown, input, opts) {
        var margen      = 8;
        var anchoMinimo = opts.anchoMinimo != null ? opts.anchoMinimo : 350;
        var altoMaximo  = opts.altoMaximo != null ? opts.altoMaximo : 250;
        var altoMinimo  = 110;

        var vv = window.visualViewport;
        // getBoundingClientRect() y position:fixed comparten sistema de coordenadas
        // (layout viewport); visualViewport.offsetTop dice dónde arranca la parte
        // visible dentro de él cuando el teclado desplaza la pantalla.
        var visTop    = vv ? vv.offsetTop : 0;
        var visAlto   = vv ? vv.height : window.innerHeight;
        var visAncho  = vv ? vv.width : window.innerWidth;
        var visBottom = visTop + visAlto;

        var rect = input.getBoundingClientRect();

        // El input quedó fuera de la zona visible (scroll del modal): no tiene
        // sentido mostrar la lista pegada a algo que no se ve.
        if (rect.bottom < visTop || rect.top > visBottom) {
            dropdown.classList.add('d-none');
            return;
        }

        var ancho = Math.min(Math.max(rect.width, anchoMinimo), visAncho - margen * 2);
        dropdown.style.minWidth = Math.min(anchoMinimo, visAncho - margen * 2) + 'px';
        dropdown.style.width    = ancho + 'px';

        var espacioAbajo  = visBottom - rect.bottom - margen;
        var espacioArriba = rect.top - visTop - margen;
        var abrirArriba   = espacioAbajo < 140 && espacioArriba > espacioAbajo;

        var alto = Math.max(altoMinimo, Math.min(altoMaximo, abrirArriba ? espacioArriba : espacioAbajo));
        dropdown.style.maxHeight = alto + 'px';

        var left = Math.min(rect.left, visAncho - ancho - margen);
        dropdown.style.left = Math.max(margen, left) + 'px';

        if (abrirArriba) {
            // Anclada por abajo (no por arriba) para que quede pegada al input
            // aunque la lista traiga pocos resultados y no ocupe todo el alto.
            dropdown.style.top    = 'auto';
            dropdown.style.bottom = Math.max(0, window.innerHeight - rect.top + 2) + 'px';
        } else {
            dropdown.style.bottom = 'auto';
            dropdown.style.top    = (rect.bottom + 2) + 'px';
        }
    }

    /** Muestra la lista pegada al input y la deja como la lista activa. */
    window.CMG_anclarDropdown = function (dropdown, input, opts) {
        if (!dropdown || !input) return;
        activo = { dropdown: dropdown, input: input, opts: opts || {} };
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
