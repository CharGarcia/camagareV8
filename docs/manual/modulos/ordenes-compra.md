---
titulo: Órdenes de compra
resumen: Pedido formal a un proveedor antes de recibir la mercadería y su factura.
categoria: Compras
ruta_modulo: modulos/ordenes-compra
tipo: modulo
visibilidad: todos
etiquetas: orden de compra, ordenes, pedido a proveedor, requisicion, compra pendiente, autorizar compra, vincular compra, recibido, pedido vs facturado
version: 1.2
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
- Vincularla con la compra cuando llegue la factura electrónica del
  proveedor, para comparar cantidades y precios pedidos contra los
  facturados.

## Cómo se usa

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Añada los productos con cantidad y precio acordado.
4. Guarde y envíela al proveedor: en la parte superior del modal (una vez
   guardada) están los botones **PDF**, **Excel** y **Correo** para descargar
   o enviar el comprobante directamente al proveedor. Al enviar por correo,
   si el proveedor tiene correo registrado se precarga como destinatario;
   igual se puede escribir uno distinto.
5. La orden queda en estado **Aprobado** (o **Borrador**) esperando la
   mercadería.

## Cuando llega la factura del proveedor

Las compras de este sistema son documentos electrónicos que se cargan solos
desde el SRI (módulo **Compras**), no se "convierten" desde la orden. Para
cerrar el ciclo, abra esa compra ya cargada y use su pestaña **Orden de
Compra**:

1. Seleccione, en el desplegable, la orden abierta del mismo proveedor que
   corresponde a esa factura (solo aparecen órdenes en borrador/aprobado que
   no estén ya vinculadas a otra compra) y pulse **Vincular**.
2. La orden pasa automáticamente a estado **Recibido** (con la fecha de hoy
   como fecha de recepción si no tenía una).
3. La pestaña muestra una tabla comparativa por producto: cantidad y precio
   **pedidos** (de la orden) vs. **facturados** (de la compra), con un
   estado por línea — *OK*, *Diferencia* (cantidad o precio no coincide),
   *Pendiente* (se pidió pero no llegó en esta factura) o *No pedido*
   (llegó algo que no estaba en la orden). Las líneas que no tienen un
   producto del catálogo vinculado en algún lado (ni en la orden ni por
   homologación del código del proveedor en la compra) se listan aparte, sin
   comparar automáticamente.
4. Si se vinculó por error, el botón **Desvincular** deshace el enlace y
   regresa la orden a **Aprobado**.

Esto es solo informativo: no bloquea guardar la compra, no mueve inventario
ni genera cuentas por pagar por sí mismo — eso sigue el flujo normal de
Compras (procesar entradas, retención, etc.).

## Errores frecuentes

- **El stock no cambió al crear la orden**: es correcto, la orden no mueve
  inventario. Eso ocurre al procesar las entradas de la compra.
- **No aparece en cuentas por pagar**: tampoco genera deuda; la deuda nace con la
  compra.
- **El proveedor no aparece**: regístrelo primero en Proveedores.
- **No aparece en el desplegable de la pestaña "Orden de Compra"**: revise
  que la orden esté en estado Borrador o Aprobado (no Recibido/Anulado), que
  sea del mismo proveedor de la compra, y que no esté ya vinculada a otra
  compra.

## Historial de cambios

- **1.2** — Vinculación con Compras: pestaña "Orden de Compra" en el modal de
  Compras para enlazar la factura electrónica del proveedor con la orden que
  la originó y comparar cantidades/precios pedidos vs. facturados.
- **1.1** — Botones de PDF, Excel y Correo en el modal para descargar o enviar
  la orden guardada directamente al proveedor.
- **1.0** — Versión inicial.
