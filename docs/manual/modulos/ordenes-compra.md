---
titulo: Órdenes de compra
resumen: Pedido formal a un proveedor antes de recibir la mercadería y su factura.
categoria: Compras
ruta_modulo: modulos/ordenes-compra
tipo: modulo
visibilidad: todos
etiquetas: orden de compra, ordenes, pedido a proveedor, requisicion, compra pendiente, autorizar compra
version: 1.1
orden: 15
estado: activo
---

La **orden de compra** es el pedido formal que la empresa hace a un proveedor:
qué se le compra, en qué cantidad y a qué precio, antes de que llegue la
mercadería.

No tiene efecto contable ni tributario: no genera cuentas por pagar ni mueve
inventario. Es un compromiso, no una compra.

## Para qué sirve

- Dejar por escrito lo pedido, para reclamar si llega otra cosa.
- Controlar qué está pedido y aún no ha llegado.
- Convertirla en compra cuando llegue la factura, sin volver a capturar el
  detalle.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Añada los productos con cantidad y precio acordado.
4. Guarde y envíela al proveedor: en la parte superior del modal (una vez
   guardada) están los botones **PDF**, **Excel** y **Correo** para descargar
   o enviar el comprobante directamente al proveedor. Al enviar por correo,
   si el proveedor tiene correo registrado se precarga como destinatario;
   igual se puede escribir uno distinto.
5. Cuando llegue la mercadería con su factura, **conviértala en compra**.

## Lo que ocurre al convertirla

La compra se crea con los datos de la orden, y a partir de ahí sigue el flujo
normal: se vincula con la factura del proveedor, se procesan las entradas de
inventario y se genera la retención si corresponde.

## Errores frecuentes

- **El stock no cambió al crear la orden**: es correcto, la orden no mueve
  inventario. Eso ocurre al procesar las entradas de la compra.
- **No aparece en cuentas por pagar**: tampoco genera deuda; la deuda nace con la
  compra.
- **El proveedor no aparece**: regístrelo primero en Proveedores.

## Historial de cambios

- **1.1** — Botones de PDF, Excel y Correo en el modal para descargar o enviar
  la orden guardada directamente al proveedor.
- **1.0** — Versión inicial.
