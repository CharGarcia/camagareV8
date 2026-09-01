<?php
/**
 * Estilos de tirilla térmica de 80 mm — bloque <style> compartido por TODAS las
 * tirillas del sistema (cuenta de mesa, cobro de comanda, POS mostrador,
 * Facturas y Recibos de Venta). Antes vivía copiado en seis vistas y cada copia
 * fue divergiendo; ahora se incluye desde el template literal que arma el HTML
 * de la ventana de impresión:
 *
 *     const html = `<!DOCTYPE html><html><head>
 *         <?php require MVC_APP . "/views/partials/tirilla_estilos.php"; ?>
 *     </head>…`;
 *
 * PHP lo resuelve en servidor, así que el CSS llega literal dentro del literal
 * JS (no contiene backticks ni `${`, que romperían la interpolación).
 *
 * Por qué 72 mm y no 80: una impresora "de 80 mm" (JP80HUB y equivalentes)
 * imprime 72,1 mm útiles — 576 puntos a 203 dpi; el resto es borde físico no
 * imprimible. Declarar `size: 80mm` con un body de 74 mm entrega al driver más
 * contenido del que cabe en el área imprimible y el navegador REESCALA la
 * página para ajustarla: ahí es donde el texto encoge, las columnas se corren y
 * los importes saltan de renglón. Declarando el ancho imprimible real y
 * `margin: 0` (el respiro va en el padding del body), el navegador imprime 1:1.
 *
 * Reglas de la maquetación, para no repetir los errores que se corrigieron aquí:
 *  - `table-layout: fixed` + <colgroup> obligatorio en las tablas de detalle y
 *    totales. Sin ancho fijo, una descripción larga ensancha la tabla más allá
 *    del papel y arrastra la columna del importe fuera del área imprimible.
 *    Clases listas para eso: .t-detalle, .t-totales y .t-datos.
 *  - Nada de grises (#555, #ccc): la térmica no tiene escala de grises y los
 *    resuelve con tramado, que se lee sucio. Todo en negro.
 *  - Fuente monoespaciada: alinea verticalmente cantidades e importes, cosa que
 *    una proporcional (Arial) no consigue a este tamaño.
 */
?>
<style>
    @page { size: 72mm auto; margin: 0; }
    * { box-sizing: border-box; }
    html, body { width: 72mm; }
    body {
        font-family: "Courier New", Courier, "Consolas", monospace;
        font-size: 11px;
        line-height: 1.25;
        width: 72mm;
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
        font-size: 11px;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    /* Columna de importes: ancho fijo y sin corte de línea, para que el valor
       nunca se parta ni empuje a la descripción. */
    .num { text-align: right; white-space: nowrap; }
    .t-detalle td, .t-totales td { padding: 1px 0; }
    .t-totales tr:last-child td { font-weight: bold; font-size: 12px; }
    .t-detalle hr { margin: 2px 0; border: none; border-top: 1px solid #000; }
    /* Sublínea "2 x $3,50 (IVA 15%)": se distingue por tamaño, no por color. */
    .t-detalle .sub { font-size: 10px; }

    h2 { font-size: 13px; margin: 2px 0; }
    h3 { font-size: 10px; margin: 1px 0; font-weight: normal; }
    img { max-width: 40mm; max-height: 16mm; }

    @media print {
        html, body { width: 72mm; }
        button { display: none; }
    }
</style>
