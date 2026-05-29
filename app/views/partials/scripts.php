<script>const BASE_URL = '<?= rtrim(BASE_URL ?? '', '/') ?>';</script>
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
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/app.js"></script>
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
        if (window.innerWidth > 991) {
            document.body.classList.remove('cmg-has-table');
            return;
        }
        if (document.querySelector('.cmg-table-card')) {
            document.body.classList.add('cmg-has-table');
        }
    }

    /* ----------------------------------------------------------------
     * 3. Scroll horizontal en tablas sin romper thead sticky.
     *    Envuelve cada -scroll con un div que maneja overflow-x,
     *    dejando al contenedor original solo con overflow-y.
     * ---------------------------------------------------------------- */
    function wrapScrollContainers() {
        if (window.innerWidth > 991) return;
        document.querySelectorAll('[class*="-scroll"]:not([class*="cmg-"]):not(.cmg-scroll-wrapped)').forEach(function(el) {
            var wrapper = document.createElement('div');
            wrapper.className = 'cmg-scroll-x-wrap';
            el.parentNode.insertBefore(wrapper, el);
            wrapper.appendChild(el);
            el.classList.add('cmg-scroll-wrapped');
        });
    }

    function init() {
        updateStickyHeaderHeight();
        applyAppShell();
        wrapScrollContainers();
    }

    document.addEventListener('DOMContentLoaded', init);
    window.addEventListener('resize', function() {
        updateStickyHeaderHeight();
        applyAppShell();
    });
    window.addEventListener('cmg:tableRefreshed', wrapScrollContainers);

    /* ----------------------------------------------------------------
     * 4. Scroll interno en modales fullscreen (móvil/tablet).
     *    Bootstrap pone overflow-y:auto en el overlay .modal, lo que
     *    hace que scrollee todo el modal en vez del .modal-body.
     *    Al abrir cualquier modal en pantalla pequeña, bloqueamos el
     *    scroll del overlay y lo dejamos solo en el .modal-body.
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
            modalEl.style.overflow = 'hidden';
        } else {
            modalEl.style.overflow = '';
        }
    }

    document.addEventListener('show.bs.modal', function(e) {
        fixModalScroll(e.target);
    });
    document.addEventListener('hidden.bs.modal', function(e) {
        e.target.style.overflow = '';
    });
})();
</script>
