/**
 * Pestañas deslizables — barras de pestañas (.nav-tabs) que no caben a lo ancho.
 *
 * Se aplica SOLO a:
 *   - todas las pestañas dentro de un modal (.modal .nav-tabs), automáticamente;
 *   - fuera de modales, a las barras con el atributo `data-cmg-tabs-deslizables`
 *     en el <ul class="nav nav-tabs …"> (p. ej. el módulo Empresa).
 * Pestañas verticales (flex-column) quedan fuera. Lo que hace:
 *   - flechas ‹ › a los lados, visibles SOLO cuando hay pestañas ocultas en esa
 *     dirección, sobre un degradado que avisa que la barra sigue;
 *   - la rueda del mouse (vertical) desliza las pestañas de lado;
 *   - la pestaña activa siempre queda a la vista (al cargar y al cambiar de pestaña);
 *   - se recalcula solo al cambiar el ancho (ventana, sidebar contraído, etc.).
 * Si todas las pestañas caben, no se ve nada distinto.
 *
 * Contenido cargado después (p. ej. un modal pintado por AJAX):
 *   window.CMG_tabsDeslizables(elementoUl)   — o sin argumento para revisar la página.
 * El fondo del degradado se toma de --cmg-tabs-fondo (por defecto, el del contenedor).
 */
(function () {
    'use strict';
    if (window.CMG_tabsDeslizables) return;

    var PASO = 0.7; // cada clic en una flecha desliza el 70 % del ancho visible

    function fondoDe(el) {
        // Primer color de fondo no transparente hacia arriba: el degradado debe fundirse con él.
        for (var n = el; n && n !== document.documentElement; n = n.parentElement) {
            var c = getComputedStyle(n).backgroundColor;
            if (c && c !== 'transparent' && !/rgba\(\s*0,\s*0,\s*0,\s*0\s*\)/.test(c)) return c;
        }
        return '#fff';
    }

    function crearFlecha(lado) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'cmg-tabs-desl__flecha cmg-tabs-desl__flecha--' + lado;
        b.setAttribute('aria-label', lado === 'izq' ? 'Ver pestañas anteriores' : 'Ver más pestañas');
        b.tabIndex = -1;
        b.innerHTML = '<i class="bi bi-chevron-' + (lado === 'izq' ? 'left' : 'right') + '"></i>';
        return b;
    }

    function activar(ul) {
        if (!ul || ul.__cmgTabsDesl || !ul.parentNode) return;
        // Pestañas verticales (flex-column): no aplica.
        if ((getComputedStyle(ul).flexDirection || '').indexOf('column') === 0) return;
        ul.__cmgTabsDesl = true;

        // Envoltorio: ocupa el lugar del <ul> en su contenedor (que suele ser un d-flex).
        var wrap = document.createElement('div');
        wrap.className = 'cmg-tabs-desl';
        // Los márgenes verticales de la barra (mb-3, mt-2…) pasan al envoltorio, para que
        // las flechas midan lo mismo que las pestañas y no se estiren sobre ese espacio.
        var cs = getComputedStyle(ul);
        wrap.style.marginTop = cs.marginTop;
        wrap.style.marginBottom = cs.marginBottom;
        ul.style.setProperty('margin-top', '0', 'important');
        ul.style.setProperty('margin-bottom', '0', 'important');
        ul.parentNode.insertBefore(wrap, ul);
        wrap.appendChild(ul);
        ul.classList.add('cmg-tabs-desl__lista');
        if (!wrap.style.getPropertyValue('--cmg-tabs-fondo')) {
            wrap.style.setProperty('--cmg-tabs-fondo', fondoDe(wrap.parentElement || wrap));
        }

        var izq = crearFlecha('izq');
        var der = crearFlecha('der');
        wrap.appendChild(izq);
        wrap.appendChild(der);

        function actualizar() {
            var max = ul.scrollWidth - ul.clientWidth;
            wrap.classList.toggle('cmg-tabs-desl--izq', ul.scrollLeft > 2);
            wrap.classList.toggle('cmg-tabs-desl--der', ul.scrollLeft < max - 2);
        }
        var pendiente = false;
        function programar() {
            if (pendiente) return;
            pendiente = true;
            requestAnimationFrame(function () { pendiente = false; actualizar(); });
        }

        function deslizar(dir) {
            ul.scrollBy({ left: dir * Math.max(80, ul.clientWidth * PASO), behavior: 'smooth' });
        }
        izq.addEventListener('click', function () { deslizar(-1); });
        der.addEventListener('click', function () { deslizar(1); });

        // Rueda vertical → desplazamiento lateral, solo si hay hacia dónde moverse
        // (si no, la rueda sigue desplazando la página como siempre).
        ul.addEventListener('wheel', function (e) {
            if (e.ctrlKey || Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
            var max = ul.scrollWidth - ul.clientWidth;
            if (max <= 0) return;
            if ((e.deltaY < 0 && ul.scrollLeft <= 0) || (e.deltaY > 0 && ul.scrollLeft >= max - 1)) return;
            e.preventDefault();
            ul.scrollLeft += e.deltaY;
        }, { passive: false });

        // La pestaña activa, siempre visible (sin mover la página en vertical).
        function mostrarActiva(btn, suave) {
            var link = btn || ul.querySelector('.nav-link.active');
            if (!link) return;
            var li = link.closest('.nav-item') || link;
            var a = li.offsetLeft, b = a + li.offsetWidth, margen = 32; // deja ver la flecha
            if (a - margen < ul.scrollLeft) {
                ul.scrollTo({ left: Math.max(0, a - margen), behavior: suave ? 'smooth' : 'auto' });
            } else if (b + margen > ul.scrollLeft + ul.clientWidth) {
                ul.scrollTo({ left: b + margen - ul.clientWidth, behavior: suave ? 'smooth' : 'auto' });
            }
        }
        ul.addEventListener('shown.bs.tab', function (e) { mostrarActiva(e.target, true); });

        ul.addEventListener('scroll', programar, { passive: true });
        if (window.ResizeObserver) new ResizeObserver(programar).observe(ul);
        // Pestañas que se muestran/ocultan (según el documento o las preferencias del
        // usuario) cambian lo que cabe sin cambiar el tamaño de la barra.
        if (window.MutationObserver) {
            new MutationObserver(programar).observe(ul, {
                subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style', 'hidden']
            });
        }
        window.addEventListener('resize', programar);

        // Para re-centrar la pestaña activa al reabrir un modal (algunos la activan sin Bootstrap).
        ul.__cmgTabsDeslRefrescar = function () { mostrarActiva(null, false); actualizar(); };

        mostrarActiva(null, false);
        actualizar();
    }

    // Marcadas a mano + TODAS las pestañas de los modales.
    var SELECTOR = '[data-cmg-tabs-deslizables], .modal .nav-tabs';

    window.CMG_tabsDeslizables = function (raiz) {
        if (raiz && raiz.matches && raiz.matches(SELECTOR)) { activar(raiz); return; }
        (raiz || document).querySelectorAll(SELECTOR).forEach(activar);
    };

    // Modales: se preparan al empezar a abrirse (aún ocultos, sin salto visual) y se
    // recalculan ya abiertos, cuando las pestañas tienen su ancho real. Cubre también los
    // modales que se pintan o cambian por AJAX antes de abrirse.
    document.addEventListener('show.bs.modal', function (e) {
        if (e.target && e.target.querySelectorAll) window.CMG_tabsDeslizables(e.target);
    });
    document.addEventListener('shown.bs.modal', function (e) {
        if (!e.target || !e.target.querySelectorAll) return;
        window.CMG_tabsDeslizables(e.target);
        e.target.querySelectorAll('.cmg-tabs-desl__lista').forEach(function (ul) {
            if (ul.__cmgTabsDeslRefrescar) ul.__cmgTabsDeslRefrescar();
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.CMG_tabsDeslizables(); });
    } else {
        window.CMG_tabsDeslizables();
    }
})();
