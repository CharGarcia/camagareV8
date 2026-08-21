<?php
/**
 * Disparo silencioso de la generación de asientos contables.
 *
 * Se incluye en el layout, no en cada módulo: así los 15 módulos con
 * contabilidad no necesitan ni una línea de cambio. Solo emite algo cuando la
 * pantalla que se está sirviendo es la de un módulo declarado en
 * config/contabilidad_modulos.php; en el resto del sistema no deja rastro.
 *
 * El usuario no ve NADA: no hay mensajes, ni spinner, ni recarga de la tabla.
 * La petición sale después de que la pantalla ya está usable y su respuesta se
 * descarta. Si algo falla, falla en silencio y queda en el log del servidor.
 */

$rutaUri = \App\Helpers\ContabilidadModulos::rutaDesdeUri($_SERVER['REQUEST_URI'] ?? null);

// rutaCanonica() devuelve null si esa pantalla no lleva contabilidad, que es el
// caso de la mayoría de los módulos: ahí este partial no emite nada.
$rutaContabilidadAuto = $rutaUri !== null
    ? \App\Helpers\ContabilidadModulos::rutaCanonica($rutaUri)
    : null;

if ($rutaContabilidadAuto === null || empty($_SESSION['id_usuario']) || empty($_SESSION['id_empresa'])) {
    return;
}
?>
<script>
/* Generación automática de asientos contables del módulo actual (silenciosa). */
(function () {
    'use strict';

    var MODULO = <?= json_encode($rutaContabilidadAuto, JSON_UNESCAPED_SLASHES) ?>;
    var URL_GENERAR = BASE_URL + '/modulos/contabilidad-auto/generar-ajax';

    function generar() {
        var cuerpo = new URLSearchParams();
        cuerpo.append('modulo', MODULO);

        /* keepalive: si el usuario se va a otra pantalla enseguida, la petición
           igual llega al servidor y la pasada no se pierde a medias. */
        fetch(URL_GENERAR, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: cuerpo.toString()
        }).catch(function () {
            /* Silencio: esto es una tarea de fondo, no una acción del usuario. */
        });
    }

    /* Después de que la pantalla esté cargada y el navegador desocupado: la
       prioridad es que el listado se vea rápido, no contabilizar cuanto antes. */
    function programar() {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(generar, { timeout: 3000 });
        } else {
            window.setTimeout(generar, 1200);
        }
    }

    if (document.readyState === 'complete') {
        programar();
    } else {
        window.addEventListener('load', programar, { once: true });
    }
})();
</script>
