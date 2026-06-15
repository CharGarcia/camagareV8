<script>
/* ---------------------------------------------------------------
 * Control de sesión activa: polling cada 30 s.
 * Si el servidor indica que la sesión fue desplazada (otro dispositivo
 * inició sesión), muestra una alerta y redirige al login.
 * --------------------------------------------------------------- */
(function() {
    var URL_VERIFICAR = BASE_URL + '/auth/verificar-sesion';
    var URL_LOGOUT    = BASE_URL + '/auth/logout';
    var INTERVALO_MS  = 5000; // 5 segundos
    var _timer = null;
    var _alertaActiva = false;

    function verificarSesion() {
        fetch(URL_VERIFICAR, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.activa && !_alertaActiva) {
                _alertaActiva = true;
                clearInterval(_timer);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesión cerrada',
                        text: 'Su sesión fue cerrada porque se inició sesión desde otro dispositivo.',
                        confirmButtonText: 'Aceptar',
                        confirmButtonColor: '#0d6efd',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    }).then(function() {
                        window.location.href = URL_LOGOUT;
                    });
                } else {
                    alert('Su sesión fue cerrada porque se inició sesión desde otro dispositivo.');
                    window.location.href = URL_LOGOUT;
                }
            }
        })
        .catch(function() {
            // Error de red: no cerrar sesión, intentar de nuevo en el próximo ciclo
        });
    }

    // Iniciar polling solo si hay sesión activa (el elemento body con id_usuario en data es
    // suficiente señal; usamos el hecho de que este script está en el layout autenticado)
    document.addEventListener('DOMContentLoaded', function() {
        _timer = setInterval(verificarSesion, INTERVALO_MS);
        // Verificar también cuando la pestaña vuelve a estar visible
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                verificarSesion();
            }
        });
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
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/app.js?v=<?= time() ?>"></script>
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/favoritos.js?v=<?= time() ?>"></script>
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
        if (document.querySelector('.cmg-table-card') && !document.body.classList.contains('cmg-no-app-shell')) {
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
        document.querySelectorAll('[class*="-scroll"]:not([class*="cmg-"]):not(.cmg-scroll-wrapped)').forEach(function(el) {
            el.classList.add('cmg-scroll-wrapped');
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
        var aplicar = (isXl && w <= 991) || (isLg && w <= 767);
        if (aplicar) {
            // Activar scroll nativo de Bootstrap en el body del modal
            dialog.classList.add('modal-dialog-scrollable');
            // Ya no bloqueamos el scroll del overlay (.modal) para evitar
            // que en móviles (ej. Safari iOS) el footer quede oculto
            // por la barra de navegación y no se pueda hacer scroll.
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
        var w = window.innerWidth;
        var dialog = e.target.querySelector('.modal-dialog');
        if (!dialog) return;
        var isLg = dialog.classList.contains('modal-lg');
        var isXl = dialog.classList.contains('modal-xl');
        var aplicar = (isXl && w <= 991) || (isLg && w <= 767);
        if (aplicar) {
            // Marcar si ya tenía la clase antes de que la agreguemos
            if (!dialog.classList.contains('modal-dialog-scrollable')) {
                dialog.classList.add('modal-dialog-scrollable');
                dialog.dataset.cmgScrollable = '1';
            }
            // Eliminado el bloqueo de overflow para móviles para que pueda escrolearse si es muy alto
        }
    });

    document.addEventListener('hidden.bs.modal', function(e) {
        restoreModalScroll(e.target);
    });
})();
</script>
