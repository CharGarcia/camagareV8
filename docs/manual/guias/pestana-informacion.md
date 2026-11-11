---
titulo: Pestaña Información: quién creó y modificó un registro
resumen: Pestaña de solo lectura en las ventanas de los módulos, para administradores, con quién registró y modificó el documento, cuándo, qué cambió y qué movimientos de inventario generó.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: información, informacion, pestaña información, quién creó, quien creo, quién modificó, quien modifico, quién lo hizo, historial, historial de cambios, auditoría, auditoria, fecha y hora, última edición, ultima edicion, movimientos de inventario, kardex del documento, anulado por, solo administradores, nivel 2, nivel 3, no veo la pestaña
version: 1.6
orden: 36
estado: activo
---

## Qué es la pestaña Información

En la ventana de un registro (por ejemplo, una consignación de venta), la pestaña
**Información** responde tres preguntas sin salir del documento: **quién lo hizo,
cuándo y qué movimientos generó**. Es solo de consulta: no se edita nada ahí.

## Quién la ve

Solo los **administradores y superadministradores** (usuarios de nivel 2 y 3). A
un usuario de nivel 1 la pestaña no le aparece. Quien no tiene *acceso total* en
el módulo solo puede consultarla en los registros que él mismo creó.

Como las demás pestañas, se puede ocultar con el **engranaje** que está a la
derecha de la barra de pestañas; esa preferencia es de cada usuario.

## Qué muestra

| Sección | Qué contiene |
|---|---|
| Creado | Quién registró el documento, con fecha y hora |
| Última edición | Quién lo modificó por última vez, cuándo y qué cambió |
| Acciones registradas | Línea de tiempo con cada acción (creación, ediciones, cambios de estado, entrega, anulación, eliminación): usuario, fecha y hora y los campos que cambiaron, con el valor anterior tachado y el nuevo |
| Movimientos de inventario generados | Solo en documentos que mueven stock: cada entrada o salida con usuario, fecha y hora, producto, bodega, lote, NUP y cantidad. Los movimientos **anulados** también aparecen, con quién y cuándo los anuló |

Las fechas se muestran como día-mes-año y hora (`dd-mm-aaaa hh:mm:ss`).

Los cambios se muestran con **nombres, no con códigos internos**: la forma de pago
o de cobro, el concepto de ingreso o egreso, las retenciones, el sustento
tributario, el banco, la provincia, la ciudad, el tipo de identificación y el tipo
de cuenta salen con su nombre (por ejemplo *Forma de pago predeterminada: (vacío)
→ Transferencia*).

## En qué módulos está

- **Consignaciones de Venta**.
- **Facturas de Venta**, **Recibos de Venta**, **Notas de Crédito**, **Notas de
  Débito**, **Proformas** y **Retenciones de Ventas**.
- **Compras**, **Liquidaciones de Compra** y **Retenciones de Compras**.
- **Ingresos** y **Egresos** (sin movimientos de inventario: no mueven stock).
- **Clientes**.
- **Proveedores**.
- **Productos** (sin movimientos de inventario: esos se consultan en el kardex del producto).
- **Vendedores**.
- **Empleados**.
- **Pedidos** (sin la sección de movimientos de inventario: un pedido no mueve
  stock).

La pestaña se irá agregando a los demás módulos; en todos se llama
**Información**.

## Errores frecuentes

- **No veo la pestaña**: su usuario es de nivel 1, o la ocultó con el engranaje de
  las pestañas.
- **«Guarde el registro para ver…»**: el documento es nuevo y todavía no se ha
  guardado.
- **La última edición muestra quién y cuándo, pero no qué cambió**: el registro
  viene de una migración o se modificó antes de que el sistema registrara los
  cambios.
- **Un cambio muestra un número en vez de un nombre** (p. ej. *Forma de pago
  predeterminada: 13*): la opción que tenía ese código ya no existe en el
  catálogo, así que no hay nombre que mostrar.
- **«No hay información disponible para este registro»**: el registro no es
  suyo y no tiene *acceso total* en el módulo.

## Historial de cambios

- **1.6** — Se agrega a Ingresos y Egresos. Pedidos pasa a usar esta misma pestaña (antes tenía una propia). Se retira el aviso «Este último cambio no quedó detallado en el historial»: cuando no hay detalle, la última edición muestra solo quién y cuándo.
- **1.5** — Los cambios muestran el **nombre** en lugar del código interno en forma de pago y de cobro, concepto de ingreso y egreso, retenciones, sustento tributario, banco, provincia, ciudad, tipo de identificación y tipo de cuenta. Los campos tienen además etiquetas más claras (*Forma de pago predeterminada*, *Retención de renta*…).
- **1.4** — Se agrega a Proveedores, Productos, Vendedores y Empleados.
- **1.3** — Se agrega a Clientes.
- **1.2** — Se agrega a Recibos de Venta, Notas de Crédito, Notas de Débito,
  Proformas, Retenciones de Ventas, Retenciones de Compras y Liquidaciones de Compra.
- **1.1** — Se agrega a Facturas de Venta y Compras; la pestaña *Detalle* de
  Pedidos pasa a llamarse **Información**.
- **1.0** — Pestaña Información como pieza común del sistema; primer módulo:
  Consignaciones de Venta.
