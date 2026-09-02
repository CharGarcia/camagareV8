<?php
/**
 * Estilos de tirilla térmica — bloque <style> compartido por TODAS las tirillas
 * del sistema (cuenta de mesa, cobro de comanda, POS mostrador, Facturas y
 * Recibos de Venta). Antes vivía copiado en seis vistas y cada copia fue
 * divergiendo; ahora se incluye desde el template literal que arma el HTML de
 * la ventana de impresión:
 *
 *     const html = `<!DOCTYPE html><html><head>
 *         <?php require MVC_APP . "/views/partials/tirilla_estilos.php"; ?>
 *     </head>…`;
 *
 * PHP lo resuelve en servidor, así que el CSS llega literal dentro del literal
 * JS (no contiene backticks ni `${`, que romperían la interpolación).
 *
 * Reglas de la maquetación. Cada una corrige un defecto que YA se vio impreso
 * en una JP80HUB (80 mm / 203 dpi); no cambiarlas sin probar en papel:
 *
 *  - NADA de ancho fijo, ni en `@page` ni en el body. El ancho imprimible real
 *    depende del papel que tenga configurado el driver (una "80 mm" imprime
 *    72,1 mm útiles, pero cada driver lo declara distinto). Si el CSS pide un
 *    ancho concreto y el papel es otro, el navegador REESCALA la página entera
 *    para ajustarla: ahí es donde el texto encoge y los importes se corren de
 *    renglón. Con `@page { margin: 0 }` sin `size` y el contenido en %, manda el
 *    papel del driver y la impresión sale 1:1 sea cual sea.
 *  - Fuente SANS-SERIF, no monoespaciada. La térmica rasteriza a 1 bit, sin
 *    antialias: los trazos finos y las serifas de Courier New se rompen y el
 *    texto sale entrecortado. Arial a 12px imprime sólido.
 *  - `overflow-wrap: break-word` sí, `word-break: break-word` NO. El segundo
 *    parte las palabras a la mitad aunque quepan; el primero solo corta cuando
 *    una palabra no entra en la columna.
 *  - `table-layout: fixed` + <colgroup> en las tablas de detalle, totales y
 *    datos del cliente. Sin ancho declarado, una descripción larga ensancha la
 *    tabla y arrastra la columna del importe fuera del papel. Los anchos van en
 *    PORCENTAJE (clases .col-num / .col-etq) para que acompañen al ancho real
 *    del papel en vez de asumir milímetros.
 *  - Nada de grises (#555, #ccc): la térmica no tiene escala de grises y los
 *    resuelve con tramado, que se lee sucio. Todo en negro.
 *
 * ANCHO DE PAPEL: la vista puede definir `$anchoTirilla` (58 u 80) antes del
 * require, para ajustar el tamaño de letra — en 58 mm no cabe la misma que en
 * 80. Se configura por empresa en modulos/configuracion-restaurante y lo pasa el
 * controlador; sin ese dato se asumen 80 mm, que es como salía antes.
 */
$anchoTirilla = (int) ($anchoTirilla ?? 80);
$esAngosta    = $anchoTirilla === 58;

// Escalas de fuente por ancho. En 58 mm entra bastante menos: bajar un punto
// evita que las descripciones partan en tres líneas y que el importe se salga.
$fBase   = $esAngosta ? 10 : 12;
$fTotal  = $esAngosta ? 11 : 13;
$fSub    = $esAngosta ?  9 : 11;
$fTitulo = $esAngosta ? 12 : 14;
$fPie    = $esAngosta ?  9 : 11;
?>
<style>
    /* Sin declarar size: el papel lo define el driver de la impresora.
       OJO: este CSS se inyecta DENTRO de un template literal de JavaScript, así
       que aquí no puede haber acentos graves ni la secuencia dólar-llave — cierran
       el literal y rompen el script completo de la vista que lo incluye. */
    @page { margin: 0; }
    * { box-sizing: border-box; }
    html, body { width: auto; }
    body {
        font-family: Arial, Helvetica, "Liberation Sans", sans-serif;
        font-size: <?= $fBase ?>px;
        line-height: 1.3;
        margin: 0;
        padding: 2mm 2mm 0;
        color: #000;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .sep { border: none; border-top: 1px dashed #000; margin: 2px 0; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td {
        vertical-align: top;
        font-size: <?= $fBase ?>px;
        overflow-wrap: break-word;
    }
    /* Columna de importes y columna de etiquetas: en % del ancho real. */
    .col-num { width: 30%; }
    .col-etq { width: 26%; }
    .num { text-align: right; white-space: nowrap; }
    .t-detalle td, .t-totales td { padding: 1px 0; }
    .t-totales tr:last-child td { font-weight: bold; font-size: <?= $fTotal ?>px; }
    .t-detalle hr { margin: 2px 0; border: none; border-top: 1px solid #000; }
    /* Sublínea "2 x $3,50 (IVA 15%)": se distingue por tamaño, no por color. */
    .t-detalle .sub { font-size: <?= $fSub ?>px; }

    h2 { font-size: <?= $fTitulo ?>px; margin: 2px 0; }
    h3 { font-size: <?= $fPie ?>px; margin: 1px 0; font-weight: normal; }
    img { max-width: 45%; max-height: 16mm; }

    /* Campo que el cliente rellena a mano sobre el papel (nombre, RUC, correo
       en la cuenta previa). Punteado y con alto suficiente para escribir. */
    .dato-manual { border-bottom: 1px dotted #000; height: 5mm; }

    /* Avance de papel al final del ticket. Sin esto la térmica deja la
       última parte dentro del cabezal, sin imprimir, y la suelta al
       principio del ticket siguiente: el usuario ve que le falta el final
       de una impresión y le sobra al inicio de la otra. */
    /* 40mm: con drivers genéricos —sin orden de corte— hace falta bastante
       avance para empujar el final del ticket fuera del cabezal y forzar que la
       impresora vacíe el buffer. Con el driver propio de la impresora bastaría
       menos, pero de más no estorba: solo alarga un poco el papel. */
    .feed { height: 40mm; }

    @media print {
        button { display: none; }
    }
</style>
