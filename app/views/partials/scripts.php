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
/**
 * Responsive móvil: scroll horizontal en tablas sin romper thead sticky.
 * En móvil/tablet, envuelve cada contenedor "-scroll" con un div que maneja
 * el overflow-x, dejando al contenedor original solo con overflow-y (sticky funciona).
 */
(function() {
    function wrapScrollContainers() {
        if (window.innerWidth > 991) return; // solo en tablets y móviles
        document.querySelectorAll('[class*="-scroll"]:not([class*="cmg-"]):not(.cmg-scroll-wrapped)').forEach(function(el) {
            // Crear wrapper externo para scroll horizontal
            var wrapper = document.createElement('div');
            wrapper.className = 'cmg-scroll-x-wrap';
            el.parentNode.insertBefore(wrapper, el);
            wrapper.appendChild(el);
            el.classList.add('cmg-scroll-wrapped'); // evitar doble wrap
        });
    }
    // Ejecutar al cargar el DOM y en cada navegación AJAX
    document.addEventListener('DOMContentLoaded', wrapScrollContainers);
    window.addEventListener('cmg:tableRefreshed', wrapScrollContainers);
})();
</script>
