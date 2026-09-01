---
titulo: Recibos de venta
resumen: Documento de venta interno, con o sin impuestos, que no se envía al SRI.
categoria: Ventas
ruta_modulo: modulos/recibo-venta
tipo: modulo
visibilidad: todos
etiquetas: recibo de venta, recibos, nota de venta, venta sin factura, documento interno, sin impuestos
version: 1.2
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

## Historial de cambios

- **1.2** — La tirilla se maqueta para el ancho imprimible real de 72 mm y con
  columnas de ancho fijo: ya no sale reescalada ni con los importes corridos en
  impresoras térmicas de 80 mm.
- **1.1** — Botón **Excel** en la barra de acciones del modal, para exportar
  el detalle, totales y forma de pago de un recibo puntual.
- **1.0** — Versión inicial.
