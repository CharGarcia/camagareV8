<script>const BASE_URL = '<?= rtrim(BASE_URL ?? '', '/') ?>';</script>
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
})();
</script>
