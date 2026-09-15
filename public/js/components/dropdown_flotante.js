/**
 * Posicionamiento de listas flotantes de autocompletado (productos, clientes, …).
 *
 * Estas listas viven colgadas del <body> con `position: fixed` y se pegan al input
 * que las abrió: **siempre justo debajo o justo encima de ese input**, nunca
 * sueltas en otra parte de la pantalla. Tres cosas que hay que respetar y que cada
 * módulo resolvía a mano:
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
 *     más el campo, este queda al ras del teclado o directamente debajo, y ahí no
 *     hay dónde dibujar la lista. La respuesta NO es despegarla del input (se
 *     probó anclándola al borde de la pantalla y se pierde la relación con el
 *     campo que se está llenando): antes de posicionarla se **sube el propio
 *     input** —scrolleando sus contenedores— hasta dejar hueco para la lista
 *     debajo. Solo si ni así cabe se abre hacia arriba, pegada al input igual.
 *
 * Uso:
 *     CMG_anclarDropdown(dropdown, input, { anchoMinimo: 350 });  // abre y posiciona
 *     CMG_reanclarDropdown();                                     // tras repintar su contenido
 *     CMG_asegurarInputVisible(input);                            // al enfocar, sin lista abierta
 *
 * El reposicionamiento por teclado, scroll del modal o rotación es automático
 * mientras la lista esté visible (sin la clase `d-none`).
 */
(function () {
    'use strict';

    if (window.CMG_anclarDropdown) return; // ya cargado por otra vista

    var activo    = null;   // { dropdown, input, opts } de la última lista abierta
    var MOVIL_MAX = 767.98; // mismo corte que las media queries del sistema
    var MARGEN    = 8;
    // Alto por debajo del cual un hueco no sirve para mostrar la lista: con menos
    // que esto solo se ve un renglón cortado.
    var ALTO_UTIL = 120;

    /** Franja de pantalla realmente visible (el teclado abierto recorta o desplaza). */
    function franjaVisible() {
        var vv = window.visualViewport;
        // getBoundingClientRect() y position:fixed comparten sistema de coordenadas
        // (layout viewport); visualViewport.offsetTop dice dónde arranca la parte
        // visible dentro de él cuando el teclado desplaza la pantalla.
        var top   = vv ? vv.offsetTop : 0;
        var alto  = vv ? vv.height    : window.innerHeight;
        var ancho = vv ? vv.width     : window.innerWidth;
        return { top: top, alto: alto, ancho: ancho, bottom: top + alto };
    }

    /** Contenedores con scroll propio que hay entre el input y el <body>, de dentro a fuera. */
    function ancestrosScrollables(el) {
        var lista = [];
        var n = el.parentElement;
        while (n && n !== document.body && n !== document.documentElement) {
            var oy = window.getComputedStyle(n).overflowY;
            if ((oy === 'auto' || oy === 'scroll') && n.scrollHeight > n.clientHeight + 1) {
                lista.push(n);
            }
            n = n.parentElement;
        }
        return lista;
    }

    /**
     * Mueve el contenido `delta` píxeles (positivo = el input sube) repartiendo el
     * desplazamiento entre los contenedores con scroll, del más interno al más
     * externo. Devuelve cuánto se pudo mover realmente.
     */
    function desplazarContenedores(el, delta) {
        var restante = delta;
        var aplicado = 0;
        var conts = ancestrosScrollables(el);
        for (var i = 0; i < conts.length && Math.abs(restante) >= 1; i++) {
            var antes = conts[i].scrollTop;
            conts[i].scrollTop = antes + restante;
            var hecho = conts[i].scrollTop - antes;
            aplicado += hecho;
            restante -= hecho;
        }
        return aplicado;
    }

    /**
     * Deja el input dentro de la franja visible y, si se puede, con `altoDeseado`
     * píxeles libres por debajo para la lista. Devuelve el rect ya actualizado.
     */
    function asegurarSitio(input, altoDeseado) {
        var f    = franjaVisible();
        var rect = input.getBoundingClientRect();

        // La franja no da ni para el input más un pedazo de lista: no hay nada que
        // ganar moviendo el contenido, se resolverá abriendo hacia el lado que dé.
        if (f.alto < rect.height + ALTO_UTIL + MARGEN * 2) return rect;

        var objetivoTop = null;
        if (rect.top < f.top + MARGEN) {
            // El input quedó por encima de lo visible: basta con bajarlo a la vista.
            objetivoTop = f.top + MARGEN;
        } else if (rect.bottom + 2 + altoDeseado + MARGEN > f.bottom) {
            // No hay hueco debajo del input (caso del teclado): se sube el input lo
            // justo para que la lista quepa entre él y el borde de lo visible.
            objetivoTop = Math.max(f.top + MARGEN, f.bottom - MARGEN - altoDeseado - 2 - rect.height);
        }

        if (objetivoTop === null) return rect;

        desplazarContenedores(input, rect.top - objetivoTop);
        return input.getBoundingClientRect();
    }

    function posicionar(dropdown, input, opts, permitirScroll) {
        var anchoMinimo = opts.anchoMinimo != null ? opts.anchoMinimo : 350;
        var altoMaximo  = opts.altoMaximo != null ? opts.altoMaximo : 250;

        var f     = franjaVisible();
        var movil = f.ancho <= MOVIL_MAX;
        var rect  = input.getBoundingClientRect();

        // Alto al que se aspira: el tope del módulo, recortado por lo que da la
        // pantalla con el teclado abierto.
        var altoDeseado = Math.min(altoMaximo, Math.max(ALTO_UTIL, f.alto - rect.height - MARGEN * 3));

        if (permitirScroll) {
            rect = asegurarSitio(input, altoDeseado);
            f    = franjaVisible(); // el scroll pudo cambiar la franja (barras del navegador)
        }

        var inputVisible = rect.bottom > f.top && rect.top < f.bottom;

        // El input se fue de la pantalla y no se lo pudo traer de vuelta (scroll del
        // modal en escritorio): no tiene sentido dibujar la lista pegada a algo que
        // no se ve.
        if (!inputVisible) {
            dropdown.classList.add('d-none');
            return;
        }

        var anchoTope = Math.max(160, f.ancho - MARGEN * 2);
        // En el celular la lista ocupa el ancho de la pantalla: los nombres de
        // producto son largos y la columna del input es angosta.
        var ancho = movil ? anchoTope : Math.min(Math.max(rect.width, anchoMinimo), anchoTope);
        dropdown.style.minWidth = (movil ? 0 : Math.min(anchoMinimo, anchoTope)) + 'px';
        dropdown.style.width    = ancho + 'px';

        var espacioAbajo  = f.bottom - rect.bottom - MARGEN;
        var espacioArriba = rect.top - f.top - MARGEN;
        // Debajo del input salvo que arriba haya claramente más sitio: así la lista
        // sale donde el ojo la busca, justo bajo lo que se está escribiendo.
        var abrirAbajo = espacioAbajo >= ALTO_UTIL || espacioAbajo >= espacioArriba;
        var alto = Math.min(altoMaximo, Math.max(ALTO_UTIL, abrirAbajo ? espacioAbajo : espacioArriba));

        dropdown.style.maxHeight = alto + 'px';

        if (abrirAbajo) {
            dropdown.style.bottom = 'auto';
            dropdown.style.top    = Math.max(f.top + MARGEN, rect.bottom + 2) + 'px';
        } else {
            // Anclada por abajo (no por arriba) para que quede pegada al input
            // aunque la lista traiga pocos resultados y no ocupe todo el alto.
            dropdown.style.top    = 'auto';
            dropdown.style.bottom = Math.max(0, window.innerHeight - rect.top + 2) + 'px';
        }

        var left = movil ? MARGEN : Math.min(rect.left, f.ancho - ancho - MARGEN);
        dropdown.style.left = Math.max(MARGEN, left) + 'px';
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
        posicionar(dropdown, input, activo.opts, true);
    };

    function reanclar(permitirScroll) {
        if (!activo) return;
        var dropdown = activo.dropdown;
        if (!dropdown || dropdown.classList.contains('d-none')) return;
        if (!document.body.contains(activo.input)) return;
        posicionar(dropdown, activo.input, activo.opts, permitirScroll);
    }

    /** Vuelve a pegar la lista activa al input (tras repintar su contenido). */
    window.CMG_reanclarDropdown = function () { reanclar(true); };

    /**
     * Sube el input a la zona visible sin abrir ninguna lista. Para usar al enfocar
     * un campo en el celular: el teclado lo tapa y se escribe a ciegas.
     */
    window.CMG_asegurarInputVisible = function (input, altoDeseado) {
        if (!input || !document.body.contains(input)) return;
        asegurarSitio(input, altoDeseado != null ? altoDeseado : ALTO_UTIL);
    };

    // El cuerpo del modal puede scrollear por debajo de la lista: ahí solo se la
    // vuelve a pegar al input, SIN reacomodar nada — mover el contenido mientras el
    // usuario arrastra el dedo sería pelearse con él. Si con ese scroll el input se
    // va de la pantalla, la lista se cierra: el usuario dejó de mirar ese campo.
    // capture: true porque el scroll de un contenedor no burbujea.
    window.addEventListener('scroll', function () { reanclar(false); }, { passive: true, capture: true });

    // El teclado del celular, en cambio, aparece DESPUÉS de abrirse la lista y puede
    // dejar el input tapado sin que el usuario haya hecho nada. Ahí sí se reacomoda,
    // para que la lista siga pegada a un campo visible. Lo mismo al rotar.
    window.addEventListener('resize', function () { reanclar(true); }, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () { reanclar(true); }, { passive: true });
        window.visualViewport.addEventListener('scroll', function () { reanclar(true); }, { passive: true });
    }
})();
