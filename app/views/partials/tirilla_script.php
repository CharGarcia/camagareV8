<?php
/**
 * Comportamiento de las ventanas de tirilla: imprimir al abrir y cerrarse sola
 * DESPUÉS de imprimir, sin cerrarse cuando el usuario cancela.
 *
 * POR QUÉ EXISTE
 * --------------
 * Antes cada tirilla llevaba esto copiado:
 *
 *     window.onload = function () {
 *         window.print();
 *         window.onafterprint = function () { setTimeout(function(){ window.close(); }, 2000); };
 *     };
 *
 * El problema: "afterprint" se dispara igual al imprimir que al cancelar. El
 * navegador no distingue los dos casos —el estándar define el evento como
 * "después de que el usuario imprimió (o canceló)"— así que dar Cancelar
 * cerraba la ventana a los 2 segundos y había que volver al módulo a pedir la
 * tirilla otra vez.
 *
 * QUÉ HACE AHORA
 * --------------
 * Como no se puede saber cuál de las dos fue, al cerrarse el diálogo arranca una
 * cuenta atrás VISIBLE y la ventana se cierra al terminar. Durante esos segundos
 * hay dos botones: **Imprimir** (detiene la cuenta y vuelve a lanzar el diálogo,
 * que es lo que necesita quien canceló) y **Cerrar** (cierra ya).
 *
 * Solo esos botones detienen la cuenta. Se probó cancelarla con cualquier clic o
 * toque en la ventana y salió mal: en el salón se trabaja con tablet, y un toque
 * para mirar el ticket —o el simple desplazamiento— dejaba la ventana abierta
 * para siempre. El síntoma reportado fue justo ese: "la tirilla de comandas no
 * se cierra sola".
 *
 * Tampoco cierra de golpe, lo que preserva el motivo del retardo original: que
 * el trabajo llegue completo a la impresora antes de cerrar la ventana.
 *
 * RED DE SEGURIDAD
 * ----------------
 * La cuenta atrás arranca con `afterprint`, pero además hay un respaldo por
 * tiempo tras `window.print()`: en la mayoría de navegadores esa llamada es
 * BLOQUEANTE —no retorna hasta que se cierra el diálogo—, así que si por lo que
 * sea el evento no llega (o llega antes de que se le asigne el manejador), el
 * respaldo la arranca igual y la ventana no se queda colgada. `empezarCierre()`
 * es idempotente: la que llegue segunda no hace nada.
 *
 * CÓMO SE USA
 * -----------
 * Es JavaScript PURO, sin etiquetas: las pone la vista, porque la forma de
 * cerrarlas cambia según dónde se arme la tirilla.
 *
 *   - Vista PHP normal (p. ej. reporte_restaurante/tirilla.php):
 *         <script>
 *         <?php require MVC_APP . "/views/partials/tirilla_script.php"; ?>
 *         </script>
 *
 *   - Tirilla armada dentro de un template literal de JavaScript (comandas,
 *     POS, facturas, recibos): la etiqueta de cierre va escapada, o cortaría el
 *     <script> de la vista que la contiene:
 *         <script>
 *         <?php require MVC_APP . "/views/partials/tirilla_script.php"; ?>
 *         <\/script>
 *
 * Por eso aquí dentro no puede haber ni acentos graves ni la secuencia
 * dólar-llave: cerrarían ese template literal. Solo comillas simples.
 *
 * Los estilos de la barra están en partials/tirilla_estilos.php.
 */
?>
(function () {
    var SEGUNDOS = 10;
    var RESPALDO_MS = 1500;

    var barra = document.createElement('div');
    barra.className = 'tirilla-barra';
    barra.innerHTML =
        '<span class="tirilla-barra__aviso" id="tirilla-aviso">Enviando a la impresora…</span>' +
        '<button type="button" id="tirilla-btn-imprimir">Imprimir de nuevo</button>' +
        '<button type="button" id="tirilla-btn-cerrar">Cerrar</button>';
    document.body.appendChild(barra);

    var aviso = document.getElementById('tirilla-aviso');
    var timer = null;
    var cerrando = false;

    function detenerCierre() {
        if (!cerrando) { return; }
        cerrando = false;
        clearInterval(timer);
        timer = null;
        aviso.textContent = 'Enviando a la impresora…';
    }

    function textoCuenta(restan) {
        return 'Esta ventana se cerrará en ' + restan + ' s…';
    }

    function empezarCierre() {
        if (cerrando) { return; }
        cerrando = true;
        var restan = SEGUNDOS;
        aviso.textContent = textoCuenta(restan);
        timer = setInterval(function () {
            restan--;
            if (restan <= 0) {
                clearInterval(timer);
                window.close();
                return;
            }
            aviso.textContent = textoCuenta(restan);
        }, 1000);
    }

    document.getElementById('tirilla-btn-imprimir').addEventListener('click', function () {
        detenerCierre();
        window.print();
        setTimeout(empezarCierre, RESPALDO_MS);
    });
    document.getElementById('tirilla-btn-cerrar').addEventListener('click', function () {
        window.close();
    });

    window.onload = function () {
        // El manejador se asigna ANTES de imprimir: window.print() bloquea el
        // hilo hasta que se cierra el diálogo, así que asignarlo después podía
        // llegar tarde y perder el evento.
        window.onafterprint = empezarCierre;
        window.print();
        // Respaldo por si el evento no llega: idempotente, no duplica la cuenta.
        setTimeout(empezarCierre, RESPALDO_MS);
    };
})();
