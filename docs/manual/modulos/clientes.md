---
titulo: Clientes
resumen: Registro de los clientes de la empresa: datos de identificación, búsqueda, permisos y eliminación.
categoria: Ventas
ruta_modulo: modulos/clientes
tipo: modulo
visibilidad: todos
etiquetas: clientes, cliente, cartera, ruc, cedula, consumidor final, deudores, cobro automatico, cobros pendientes, forma de cobro, ingreso automatico, cheque, dias de credito
version: 1.1
orden: 10
estado: activo
---

El módulo de **Clientes** mantiene el registro de las personas y empresas a las
que se les factura. Es la base de las facturas de venta, las proformas, los
cobros y las cuentas por cobrar.

## Qué es y para qué sirve

Cada cliente pertenece a **una empresa**: los clientes registrados en una empresa
no se ven desde otra. Si trabaja con varias empresas, debe registrarlos en cada una.

Un cliente guardado aquí queda disponible en todos los documentos de venta sin
volver a escribir sus datos.

## Cómo se usa

1. Abra el módulo desde el menú *Ventas → Clientes*.
2. Pulse **Nuevo** para registrar un cliente.
3. Complete la identificación (RUC, cédula o pasaporte), el nombre y los datos de contacto.
4. Guarde. El cliente queda disponible de inmediato en facturas y proformas.

Para modificar un cliente existente, haga clic sobre su fila en el listado.

Al guardar, la ficha **no se cierra**: se queda abierta y se refresca con lo que
quedó realmente grabado, para que siga completando pestañas sin volver a buscar
el cliente. Si lo creó desde una factura, pedido u orden de lavado, el documento
de fondo ya lo tiene seleccionado; cierre la ficha cuando termine.

## Buscar en el listado

El buscador acepta texto libre y también filtros con la forma `clave:valor`:

- `garcia` busca ese texto en las columnas principales.
- `identificacion:1712345678` filtra por un campo concreto.
- `clave:"valor con espacios"` para valores que llevan espacios.
- `-clave:valor` excluye los que coincidan.

El listado permite además ordenar por cualquier columna, mostrar u ocultar
columnas, ajustar su ancho y exportar a PDF y Excel. Esas preferencias se
guardan por usuario.

## Cobro automático (pestaña Cobros)

En la ficha del cliente, la pestaña **Cobros** define cómo se le cobra sin tener
que registrar el ingreso a mano.

| Campo | Para qué sirve |
|-------|----------------|
| Forma de cobro | Activa el cobro automático: sin ella no se genera ningún ingreso solo |
| Operación bancaria | Solo si la forma es de tipo banco: depósito, transferencia o cheque |
| Concepto de ingreso | Concepto contable con el que se registra el cobro |
| Monto mínimo / máximo | Rango dentro del cual el saldo del documento entra en el cobro automático |
| Días de crédito | Difiere la fecha de cobro: fecha de emisión del documento + estos días |

Deje vacío el mínimo o el máximo para no aplicar ese límite.

### Al autorizar una factura

Cuando el cliente tiene **forma de cobro** configurada, cada factura de venta suya
que el SRI autoriza **genera sola su ingreso**, por el saldo real del documento.

Se omite si la forma de cobro fue desactivada, si el saldo queda fuera del rango,
o si la factura ya está cobrada — por ejemplo una venta del POS, que registra su
propio cobro en el momento. Nunca se cobra dos veces el mismo documento.

Si el cobro no se puede registrar, la factura **igual queda autorizada**: el
problema se anota en el registro del sistema, no interrumpe la facturación.

### Cobro con cheque

A diferencia de los pagos a proveedores, el cheque aquí lo entrega el cliente: su
número viene impreso en el documento físico, así que **no se asigna solo**. El
cobro se registra sin número de cheque y con la fecha de cobro diferida por los
días de crédito; complete el número después en el ingreso si lo necesita.

### Generar cobros pendientes

El botón **Generar cobros pendientes**, al pie de la pestaña *Cobros*, registra de
una sola vez los ingresos de las facturas y recibos de venta de ese cliente
**emitidos hasta hoy que todavía tienen saldo**. Sirve para ponerse al día cuando
la forma de cobro se configuró después de que ya se habían emitido documentos.

Antes de hacer nada le muestra **cuántos cobros son y por qué monto total**, con
el detalle de los documentos, y pide confirmación.

Cobra el **saldo real** de cada documento: el importe menos lo ya cobrado, menos
las retenciones que le hizo el cliente, menos las notas de crédito. Es el mismo
saldo que ve en Cuentas por Cobrar y en el selector de documentos de Ingresos.
Quedan fuera los documentos que no entran en el rango de monto; el aviso le dice
cuántos son.

Cada cobro se registra por separado: si uno falla —por ejemplo, porque su período
contable está cerrado— los demás igual se generan y se le informa cuál falló y por
qué. El botón solo aparece si el cliente ya está guardado, tiene forma de cobro
configurada y usted tiene permiso para crear ingresos.

> Los **saldos iniciales** de cartera no entran en el cobro automático: se cobran
> desde el módulo Ingresos, para revisarlos uno a uno.

## Permisos

Lo que puede hacer cada persona depende de los permisos asignados al submódulo:

- **Ver**: consultar el listado.
- **Crear**, **Modificar**, **Eliminar**: las acciones correspondientes.
- **Acceso total**: ver los clientes de toda la empresa. Sin este permiso, cada
  usuario ve únicamente *los clientes que él mismo creó*.

Si no ve clientes que sabe que existen, lo más probable es que le falte el
permiso de acceso total.

## Eliminar un cliente

La eliminación es **lógica**: el cliente deja de aparecer en los listados pero no
se borra de la base de datos, y los documentos que ya lo referencian siguen
intactos. Toda eliminación queda registrada en la auditoría del sistema con el
usuario y la fecha.

## Errores frecuentes

- **No aparece en la factura**: verifique que el cliente esté en la misma empresa
  en la que está facturando.
- **No puedo editarlo**: le falta el permiso de modificar, o el cliente lo creó
  otro usuario y usted no tiene acceso total.

## Historial de cambios

- **1.1** — Cobro automático al autorizar la factura de venta; rango de monto
  (mínimo y máximo); cobro con cheque y fecha diferida por días de crédito;
  botón *Generar cobros pendientes*; la ficha ya no se cierra al guardar, se
  refresca en la misma ventana.
- **1.0** — Versión inicial.
