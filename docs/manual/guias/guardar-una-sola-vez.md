---
titulo: Pulsar Guardar varias veces no duplica
resumen: Por qué un doble clic en Guardar, Cobrar o Registrar crea un solo documento, y qué hacer si se pierde la conexión mientras se guarda.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: doble clic, dos clics, guardar dos veces, se guardo dos veces, documento duplicado, registro duplicado, factura duplicada, consignacion duplicada, egreso duplicado, ingreso duplicado, cobrar dos veces, boton guardar, no responde, se queda guardando, error de conexion, no se recibio respuesta, perdi la conexion, se corto el internet
version: 1.0
orden: 35
estado: activo
---

## Qué hace el sistema ante un doble clic

En todos los módulos, mientras un documento se está guardando el sistema no
envía el mismo guardado otra vez. Pulsar **Guardar**, **Cobrar** o **Registrar**
dos veces seguidas, o pulsar Enter repetido, crea **un solo** documento. El
segundo clic recibe la misma respuesta que el primero.

Si entre un clic y otro se cambió algún dato del formulario, ya no es el mismo
guardado y sí se envía.

Hay pantallas donde repetir es justamente lo que se quiere, y ahí no se aplica.
Por ejemplo, en **Comandas**, tocar dos veces un producto agrega dos unidades.

## Después de guardar

En los módulos donde el formulario queda abierto después de guardar (Ingresos,
Egresos, Consignaciones, Transferencias de inventario, Guías de remisión), el
botón queda desactivado hasta que el documento vuelve a cargarse con su número.
Desde ese momento el botón dice **Actualizar**, o desaparece si el documento ya
no se puede editar. Así no se crea un segundo documento por pulsar Guardar justo
después del primero.

En el **punto de venta** (caja), el botón **Cobrar** no se reactiva mientras el
cobro sigue en curso, aunque se escanee o se edite una línea del carrito.

## Si se pierde la conexión mientras se guarda

Si aparece un error de conexión (*«No se recibió respuesta del servidor»*), el
documento **pudo haberse guardado igual**: lo que se perdió fue la respuesta, no
necesariamente el guardado.

En estos módulos se puede **volver a pulsar Guardar (o Cobrar / Generar) sin
riesgo**, sin cerrar el formulario: si el documento ya se había registrado, el
sistema lo reconoce y muestra el mismo, con un aviso como *«ya estaba
registrado; no se creó otro»*.

| Módulo | Qué no se duplica |
|---|---|
| Consignaciones de venta | La consignación |
| Pedidos | El pedido |
| Transferencias de inventario | La transferencia (el stock no se mueve dos veces) |
| Traspasos | El traspaso entre formas de pago |
| Roles de pago | La corrida (al pulsar **Generar**) |
| Punto de venta (caja) | El comprobante del cobro |

En el **punto de venta**, el reintento funciona mientras el carrito siga igual.
Si se vacía el carrito o se cambia de bodega, se considera una venta nueva.
Incluso si se recarga la página, la venta en curso conserva su identificación.

En los demás módulos, **revise el listado antes de volver a guardar**. Si el
documento aparece, ábralo desde ahí en lugar de guardarlo otra vez.

## Historial de cambios

- **1.0** — Primera versión: protección general contra el doble clic en todos los
  módulos, botón bloqueado hasta recargar el documento guardado (Ingresos,
  Egresos, Transferencias de inventario, Guías de remisión) y botón Cobrar del
  punto de venta bloqueado durante el cobro. Reintento seguro tras un error de
  conexión en Consignaciones, Pedidos, Transferencias de inventario, Traspasos,
  Roles de pago y el cobro del punto de venta.
