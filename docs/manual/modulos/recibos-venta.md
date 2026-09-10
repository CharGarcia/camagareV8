---
titulo: Recibos de venta
resumen: Documento de venta interno, con o sin impuestos, que no se envía al SRI.
categoria: Ventas
ruta_modulo: modulos/recibo-venta
tipo: modulo
visibilidad: todos
etiquetas: recibo de venta, recibos, nota de venta, venta sin factura, documento interno, sin impuestos, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.6
orden: 35
estado: activo
---

El **recibo de venta** es un documento de venta interno: sirve para respaldar una
entrega y su cobro **sin emitir un comprobante electrónico**. No se envía al SRI.

Funciona como una factura en todo lo demás: descuenta inventario, registra el
cobro y genera su asiento contable.

## Con o sin impuestos

El recibo tiene un interruptor para emitirlo **con o sin impuestos**. Al
cambiarlo, los totales se recalculan al momento.

Es la diferencia principal con la factura, que siempre sigue las reglas
tributarias. Elija según lo que respalde el documento.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija el cliente.
3. Añada los productos.
4. Decida si lleva impuestos.
5. Registre el cobro.
6. Guarde.

## Qué genera

| Efecto | Detalle |
|--------|---------|
| Inventario | Descuenta stock igual que una factura |
| Cobro | Se registra como cobro de tipo recibo |
| Contabilidad | Genera asiento de venta |
| SRI | **No** se envía |

## Exportar el documento

En la barra de acciones superior del modal, junto al botón **PDF**, hay un
botón **Excel** (icono verde) que descarga el detalle, los totales y la forma
de pago de ese recibo puntual. Ambos se habilitan solo con el recibo ya
guardado.

## Cuándo no usarlo

Si la operación requiere comprobante válido para el cliente, hay que emitir
**factura**. El recibo no sustituye a un comprobante electrónico ante el SRI.

## Errores frecuentes

- **El cliente pide su factura y solo tiene un recibo**: emita la factura; el
  recibo es interno.
- **El stock bajó dos veces**: se emitió recibo *y* factura por la misma entrega.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **1.6** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar un recibo cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.5** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.4** — La **tirilla** respeta la *Presentación de los ítems* configurada en
  el módulo Empresa (pestaña Facturación): agrupa las líneas por nombre, lote o
  NUP y anexa a la descripción la unidad, el lote, la caducidad o el NUP, igual
  que ya lo hacían el PDF y el XML. Con la configuración por defecto la tirilla
  sale exactamente igual que antes: una línea por ítem.

- **1.3** — La ventana de la tirilla ya no desaparece al cancelar la
  impresión: antes el navegador avisaba igual al imprimir que al cancelar y la
  ventana desaparecía a los 2 segundos, obligando a pedir la tirilla otra vez.
  Ahora avisa de que se cerrará en 10 segundos y deja a mano **Imprimir de
  nuevo** —que reinicia la cuenta— y **Cerrar**.
- **1.2** — La tirilla se adapta al ancho de papel del driver en vez de imponer el
  suyo, con columnas de ancho proporcional y tipografía sans-serif: ya no sale
  reescalada, con los importes corridos ni con la letra entrecortada en
  impresoras térmicas de 80 mm.
- **1.1** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle, totales y forma de pago de un recibo puntual.
- **1.0** — Versión inicial.
