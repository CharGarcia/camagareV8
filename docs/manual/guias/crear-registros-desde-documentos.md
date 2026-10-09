---
titulo: Crear un cliente, proveedor, producto, forma de pago o concepto sin salir del documento
resumen: Desde una factura, compra, nota, retención u orden se puede registrar un cliente, proveedor o producto nuevo y queda puesto en el documento al guardarlo.
categoria: Primeros pasos
tipo: guia
visibilidad: todos
etiquetas: crear forma de pago, crear forma de cobro, nueva forma de pago, crear concepto, nueva opcion de ingreso, nueva opcion de egreso, crear empleado, crear cliente, nuevo cliente, crear proveedor, nuevo proveedor, crear producto, nuevo producto, registrar cliente desde la factura, registrar proveedor desde la compra, crear al vuelo, no se selecciona el cliente, no aparece el cliente nuevo, no se agrega el producto, la ficha no se cierra, boton persona con mas, boton caja, no esta disponible
version: 1.2
orden: 40
estado: activo
---

## Qué es y para qué sirve

En la barra superior de muchos documentos hay atajos para registrar algo que
todavía no existe en el sistema, sin cerrar el documento:

- **Registrar nuevo cliente** (ícono de persona con «+»).
- **Registrar nuevo proveedor**.
- **Registrar nuevo producto** (ícono de caja).

Al guardar el registro nuevo, la ficha se cierra sola y lo creado **queda puesto
en el documento** que estaba llenando.

## Requisitos previos

- Permiso de **crear** en Clientes, Proveedores o Productos, según el caso. Sin
  ese permiso el botón no aparece.
- El documento debe estar **abierto y editable** (por ejemplo, una factura en
  borrador). En un documento autorizado, anulado o bloqueado el registro se crea
  igual, pero no se coloca en el documento.

## Cómo se usa

1. Con el documento abierto, pulse el atajo de la barra superior.
2. Complete la ficha y pulse **Guardar**.
3. La ficha se cierra y aparece el aviso *«… creado y seleccionado»*.
4. Siga con el documento:
   - **Cliente / proveedor**: queda seleccionado en el buscador del documento,
     con sus datos (dirección, correo, vendedor, plazo, según el documento).
   - **Producto**: se agrega al detalle, en la primera línea vacía o en una
     nueva, con su precio e IVA, igual que si lo hubiera buscado. En **Taller**
     queda precargado en el formulario de línea: complete cantidad y técnico y
     pulse **Agregar**.

Si abre la ficha desde su propio listado (Clientes o Proveedores), la ficha
**no** se cierra al guardar, para que siga completando sus pestañas.

## Dónde funciona

| Registro | Documentos |
|---|---|
| Cliente | Factura de venta, recibo, notas de crédito y débito, retención de venta, proforma, pedido, guía de remisión, ingreso, suscripción, cotización de publicidad, facturación de consignaciones, órdenes de lavado, taller y servicio externo, alumnos |
| Proveedor | Compra, egreso, orden de compra, liquidación de compra, retención de compra, importación |
| Producto | Factura de venta, recibo, compra, proforma, suscripción, importación, órdenes de lavado, taller y servicio externo |
| Empleado | Egreso |
| Forma de cobro / pago | Ingreso, egreso |
| Opción (concepto) de ingreso / egreso | Ingreso, egreso (un concepto ligado a un módulo queda elegido como si se pulsara su botón) |
| Vendedor | Consignación de venta (queda como *Asesor*) |
| Responsable de traslado | Pedido, consignación de venta |
| Transportista | Guía de remisión |
| Vehículo | Órdenes de lavado y de taller |
| Campus / nivel | Alumnos |
| Departamento / ítem de checklist | Órdenes de taller |
| Categoría / marca | Ficha del producto, desde cualquier pantalla que la abra |

## Reglas de negocio

- Solo se coloca lo **nuevo**: editar un cliente o proveedor existente desde el
  documento no lo cambia por otro.
- Si el documento está bloqueado, no se toca.
- **Ingresos / Egresos**: una forma de pago o un concepto creado solo para el
  otro tipo de documento (por ejemplo, un concepto de *Egreso* creado desde un
  ingreso) no se coloca; se avisa.
- **Importación**: solo admite proveedores del exterior. Si el proveedor creado
  no lo es, se avisa y no se coloca.
- **Cotización de publicidad**: su detalle son líneas por categoría, no
  productos; el producto creado queda disponible para la factura de la
  cotización.

## Errores frecuentes

- **«Producto creado. Búscalo en el detalle para agregarlo»**: el producto se
  creó, pero el buscador del documento no lo ofrece (por ejemplo, inactivo o no
  apto para ese documento). Búsquelo a mano.
- **El botón no aparece**: falta el permiso de crear en ese catálogo; pídalo al
  administrador en *Permisos por módulo*.

## Historial de cambios

- **1.2** — Se suman vendedor, responsable de traslado, transportista, vehículo, campus/nivel, departamento/checklist de taller y categoría/marca del producto.
- **1.1** — Se suman empleado, forma de cobro/pago y opción de ingreso/egreso (Ingresos y Egresos).
- **1.0** — Primera versión: cliente, proveedor y producto creados desde un
  documento quedan puestos en él y la ficha se cierra sola.
