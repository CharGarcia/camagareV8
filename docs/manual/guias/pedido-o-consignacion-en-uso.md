---
titulo: Aviso "en uso" al editar un Pedido, una Consignación o una Factura de Venta
resumen: Qué significa el aviso de "este registro lo está usando otro usuario" y por qué el sistema no deja editarlo ni eliminarlo hasta que se libere.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: pedido en uso, factura en uso, bloqueo de edición, dos usuarios al mismo tiempo, consignacion en uso, edicion concurrente, choque de usuarios, aviso solo lectura, candado, eliminar factura mientras se edita
version: 1.1
orden: 30
estado: activo
---

Si dos personas trabajan al mismo tiempo sobre el mismo registro, el sistema
avisa y bloquea el lado que llegó segundo, para que no se pisen los cambios ni
se pierda información. Hoy esto cubre dos situaciones:

- **Pedidos ↔ Consignaciones de Venta**: una persona edita un pedido en
  **Pedidos** mientras otra ya lo está usando para armar una **Consignación de
  Venta** (y viceversa).
- **Factura de Venta**: una persona tiene abierta una factura en **borrador**
  para editarla mientras otra la abre para **eliminarla** (o al revés).

## Qué se ve en pantalla

Cuando alguien abre un pedido que ya está en uso, el modal muestra una franja
amarilla arriba:

> ⚠ Este pedido lo está usando ahora mismo **[Nombre del usuario]**. Puedes
> verlo, pero no editarlo hasta que termine.

Los campos quedan en solo lectura y el botón **Guardar** se oculta. El pedido
sigue siendo visible, solo no se puede modificar mientras tanto.

Si en cambio alguien intenta traer ese mismo pedido a una consignación nueva y
otro usuario ya lo tiene abierto en **Pedidos**, ve un aviso equivalente y no
puede seleccionarlo hasta que el primero cierre su edición.

En **Factura de Venta** el aviso solo aplica a facturas en **borrador** (una
factura ya autorizada no se puede eliminar y solo permite cambiar el vendedor,
así que no hay riesgo de choque). Mientras el aviso está activo, los botones
**Guardar** y **Eliminar** del modal se ocultan; el resto de acciones del
documento (ver PDF, XML, historial, enviar por correo o WhatsApp, etc.) sigue
disponible porque no modifican la factura.

## Cómo se libera

El aviso desaparece solo cuando el otro usuario:

- Guarda sus cambios y cierra el formulario, o
- Cierra el formulario sin guardar (botón Cerrar, la X, o la tecla Esc), o
- Pierde la conexión o cierra el navegador sin avisar: en ese caso el sistema
  libera el bloqueo automáticamente a los pocos minutos de inactividad.

No hace falta ninguna acción manual para "desbloquear" un registro: basta con
esperar a que la otra persona termine.

## Por qué existe esta regla

Antes de este control, si un Pedido se editaba justo mientras otra persona lo
usaba para facturar/consignar, el pedido podía quedar mal marcado (pendiente
cuando en realidad ya estaba procesado, o viceversa) sin ningún mensaje de
error — un problema silencioso, difícil de detectar después. El aviso "en uso"
evita que esa situación llegue a ocurrir.

## Otros lugares donde puede aparecer

Este mecanismo es genérico: cualquier módulo que comparta un mismo registro con
otro (por ejemplo, un documento de Compras y su Cuenta por Pagar, o una
Consignación y la Factura que la liquida) puede reusarlo. Si ve el mismo tipo
de aviso en otro módulo, aplica la misma lógica: solo hay que esperar a que la
otra persona termine.

## Historial de cambios

- **1.1** — Se agregó Factura de Venta (editar vs. eliminar un borrador).
- **1.0** — Versión inicial: bloqueo entre Pedidos y Consignaciones de Venta.
