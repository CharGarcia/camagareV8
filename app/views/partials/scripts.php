<?php require MVC_APP . '/views/partials/agente_extension.php'; ?>
<script>
/* ---------------------------------------------------------------
 * Control de sesión activa: aviso de "sesión desplazada".
 *
 * Ya NO sondea por su cuenta. Antes pedía /auth/verificar-sesion cada 5 s, en
 * paralelo al sondeo de contadores del navbar: eran 24 peticiones por minuto y
 * por pestaña abierta, sin que el usuario hiciera nada, y la de sesión consultaba
 * la BD en cada una (sin caché). Ahora el dato `sesion_activa` viaja DENTRO de la
 * respuesta de /contadores/navbarAjax, que el navbar ya pedía de todos modos, y
 * ese sondeo pasó de 5 s a 30 s → 2 peticiones por minuto en total.
 *
 * Aquí solo queda la función del aviso, que el navbar invoca cuando el servidor
 * responde sesion_activa:false (o 401). Enterarse a los 30 s en vez de a los 5 no
 * cambia nada para el usuario: en cuanto intenta cualquier acción, el servidor ya
 * le devuelve 401 y lo manda al login por su cuenta.
 * --------------------------------------------------------------- */
(function() {
    var URL_LOGOUT = BASE_URL + '/auth/logout';
    var _alertaActiva = false;

    window.CMG_sesionCerrada = function() {
        if (_alertaActiva) return;      // no apilar avisos
        _alertaActiva = true;
        var msg = 'Su sesión fue cerrada porque se inició sesión desde otro dispositivo.';
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Sesión cerrada',
                text: msg,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#0d6efd',
                allowOutsideClick: false,
                allowEscapeKey: false,
            }).then(function() { window.location.href = URL_LOGOUT; });
        } else {
            alert(msg);
            window.location.href = URL_LOGOUT;
        }
    };

    /* Respaldo para pantallas que cargan este partial SIN el navbar (hoy solo el
     * layout `guest`, que ninguna vista viva usa). Sin navbar no hay quien pida
     * los contadores, así que nadie avisaría de una sesión desplazada: si al
     * cargar no existe CMG_refreshContadores, este bloque hace su propio sondeo,
     * a 60 s — suficiente para una pantalla sin datos del negocio. */
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.CMG_refreshContadores === 'function') return; // el navbar se encarga
        setInterval(function() {
            if (document.visibilityState !== 'visible') return;
            fetch(BASE_URL + '/auth/verificar-sesion', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data && !data.activa) window.CMG_sesionCerrada(); })
            .catch(function() { /* error de red: se reintenta en el próximo ciclo */ });
        }, 60000);
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Mixin global para notificaciones tipo Toast
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });
    window.Toast = Toast;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/app.js?v=<?= asset_ver('/js/app.js') ?>"></script>
<!-- Anterior / Siguiente en los modales abiertos desde una fila de listado (automático). -->
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/modal-nav.js?v=<?= asset_ver('/js/modal-nav.js') ?>"></script>
<!-- favoritos.js ahora se carga en <head> (ver head.php) para que CMG_initSort esté
     disponible antes de los scripts inline de las vistas. -->
<?= \App\Helpers\PreferenciasHelper::getJavascriptVariables($rutaModulo ?? '') ?>
<script>
(function() {
    /* ----------------------------------------------------------------
     * 1. Medir altura del sticky-header y exponerla como CSS variable
     *    para que el CSS de móvil calcule correctamente los offsets.
     * ---------------------------------------------------------------- */
    function updateStickyHeaderHeight() {
        var h = document.querySelector('.cmg-sticky-header');
        if (h) {
            document.documentElement.style.setProperty('--cmg-sticky-h', h.offsetHeight + 'px');
        }
    }

    /* ----------------------------------------------------------------
     * 2. App-shell móvil: cuando hay tabla, marcar body con clase
     *    para activar el layout de altura fija solo en esas páginas.
     * ---------------------------------------------------------------- */
    function applyAppShell() {
        // En móvil (<768px, mismo corte que el CSS) el app-shell de altura fija
        // se desactiva: la página usa scroll natural. Bloquear aquí el <html>/<body>
        // con estilos inline dejaba el scroll roto en móvil porque el CSS de
        // rescate (@media max-width:767.98px) solo puede sobreescribir el <body>
        // vía clase; el <html> se quedaba con overflow:hidden inline para siempre.
        var esMovil = window.innerWidth < 768;
        if (document.querySelector('.cmg-table-card') && !document.body.classList.contains('cmg-no-app-shell') && !esMovil) {
            document.body.classList.add('cmg-has-table');
            // Bloquear scroll en html y body para que NADA fuera de
            // los contenedores de tabla pueda moverse
            document.documentElement.style.overflow = 'hidden';
            document.documentElement.style.height   = '100%';
            document.body.style.overflow = 'hidden';
            document.body.style.height   = '100%';
        } else {
            document.body.classList.remove('cmg-has-table');
            document.documentElement.style.overflow = '';
            document.documentElement.style.height   = '';
            document.body.style.overflow = '';
            document.body.style.height   = '';
        }
    }

    /* Prevenir que el sticky-header arrastre la página con el dedo */
    function blockHeaderTouch() {
        var header = document.querySelector('.cmg-sticky-header');
        if (!header || header._cmgTouchBlocked) return;
        header._cmgTouchBlocked = true;
        header.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    }

    /* ----------------------------------------------------------------
     * 3. Scroll horizontal en tablas sin romper thead sticky.
     *    Envuelve cada -scroll con un div que maneja overflow-x,
     *    dejando al contenedor original solo con overflow-y.
     *    Además aplica bloqueo de dirección: al detectar el primer
     *    movimiento significativo del dedo, bloquea la dirección
     *    contraria para evitar el efecto "hoja suelta".
     * ---------------------------------------------------------------- */
    function attachScrollDirectionLock(outerEl, innerEl) {
        var startX = 0, startY = 0, dir = null;

        outerEl.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) return;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            dir = null;
            // Asegurar estado limpio al iniciar gesto
            outerEl.style.overflowX = '';
            innerEl.style.overflowY  = '';
        }, { passive: true });

        outerEl.addEventListener('touchmove', function(e) {
            if (e.touches.length !== 1 || dir) return;
            var dx = Math.abs(e.touches[0].clientX - startX);
            var dy = Math.abs(e.touches[0].clientY - startY);
            if (dx < 6 && dy < 6) return; // umbral mínimo antes de decidir
            dir = dx > dy ? 'h' : 'v';
            if (dir === 'h') {
                // Movimiento horizontal: bloquar scroll vertical del inner
                innerEl.style.overflowY = 'hidden';
            } else {
                // Movimiento vertical: bloquar scroll horizontal del outer
                outerEl.style.overflowX = 'hidden';
            }
        }, { passive: true });

        function onEnd() {
            dir = null;
            outerEl.style.overflowX = '';
            innerEl.style.overflowY  = '';
        }
        outerEl.addEventListener('touchend',    onEnd, { passive: true });
        outerEl.addEventListener('touchcancel', onEnd, { passive: true });
    }

    function wrapScrollContainers() {
        document.querySelectorAll('[class*="-scroll"]:not([class*="cmg-"]):not(.js-scroll-wrapped)').forEach(function(el) {
            el.classList.add('js-scroll-wrapped');
            // En móvil NO creamos el wrapper horizontal: sin arrastre lateral
        });
    }

    /* ----------------------------------------------------------------
     * 5. Prevenir zoom al enfocar inputs en iOS/Android.
     *    Si el navegador ya hizo zoom, lo resetea al perder el foco.
     * ---------------------------------------------------------------- */
    function fixInputZoom() {
        if (window.innerWidth > 767) return;
        var viewport = document.querySelector('meta[name="viewport"]');
        if (!viewport) return;
        var original = viewport.content;

        document.addEventListener('focus', function(e) {
            var tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
                // Temporalmente desactivar zoom del usuario durante el foco
                viewport.content = original + ', maximum-scale=1';
            }
        }, true);

        document.addEventListener('blur', function(e) {
            var tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') {
                // Restaurar el viewport original para volver al tamaño normal
                viewport.content = original;
            }
        }, true);
    }

    function init() {
        updateStickyHeaderHeight();
        applyAppShell();
        wrapScrollContainers();
        blockHeaderTouch();
        fixInputZoom();
    }

    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('resize', function() {
        updateStickyHeaderHeight();
        applyAppShell();
    });
    window.addEventListener('cmg:tableRefreshed', wrapScrollContainers);

    /* ----------------------------------------------------------------
     * 4. Scroll interno en modales fullscreen (móvil/tablet).
     *
     *    Bootstrap pone overflow-y:auto en el overlay .modal, lo que
     *    hace que scrollee TODO el modal (header+body+footer juntos).
     *    La solución:
     *    a) Agregar modal-dialog-scrollable al diálogo (si no lo tiene),
     *       para que Bootstrap active scroll solo en .modal-body.
     *    b) Poner overflow:hidden en el overlay .modal para que no
     *       compita con el scroll interno.
     *    Se aplica solo cuando el modal ocupa toda la pantalla.
     * ---------------------------------------------------------------- */
    function fixModalScroll(modalEl) {
        if (!modalEl) return;
        var w = window.innerWidth;
        var dialog = modalEl.querySelector('.modal-dialog');
        if (!dialog) return;
        var isLg = dialog.classList.contains('modal-lg');
        var isXl = dialog.classList.contains('modal-xl');
        var isDefault = !isLg && !isXl && !dialog.classList.contains('modal-sm');
        var aplicar = (isXl && w <= 991) || (isLg && w <= 767) || (isDefault && w <= 575);
        
        if (aplicar) {
            if (!dialog.classList.contains('modal-dialog-scrollable')) {
                dialog.classList.add('modal-dialog-scrollable');
                dialog.dataset.cmgScrollable = '1';
            }
        }
    }

    function restoreModalScroll(modalEl) {
        if (!modalEl) return;
        var dialog = modalEl.querySelector('.modal-dialog');
        // Solo quitar si lo agregamos nosotros (no si ya lo tenía)
        if (dialog && dialog.dataset.cmgScrollable === '1') {
            dialog.classList.remove('modal-dialog-scrollable');
            delete dialog.dataset.cmgScrollable;
        }
        modalEl.style.overflow = '';
    }

    document.addEventListener('show.bs.modal', function(e) {
        fixModalScroll(e.target);
    });

    document.addEventListener('hidden.bs.modal', function(e) {
        restoreModalScroll(e.target);
    });
})();
</script>
