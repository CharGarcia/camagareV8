<?php
/**
 * Token CSRF + interceptor. Incluir dentro del <head> de TODA página que haga
 * peticiones autenticadas.
 *
 * Ya viene incluido en partials/head.php, así que las vistas con layout no
 * necesitan hacer nada. Solo hay que requerirlo a mano en las vistas
 * "standalone" que arman su propio <head> (POS, KDS, mesas, comandas, soporte
 * IA y el manual): sin esto, sus fetch saldrían sin token y serían rechazados
 * cuando la validación pase a modo 'enforce'.
 *
 * Debe ir ANTES que cualquier otro <script> de la página.
 */
?>
<script>
    window.CSRF_TOKEN = '<?= htmlspecialchars(\App\Helpers\Csrf::token(), ENT_QUOTES) ?>';
</script>
<script src="<?= rtrim(BASE_URL ?? '', '/') ?>/js/csrf.js?v=<?= time() ?>"></script>
