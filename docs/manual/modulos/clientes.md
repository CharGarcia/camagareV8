---
titulo: Clientes
resumen: Registro de los clientes de la empresa: datos de identificación, búsqueda, permisos y eliminación.
categoria: Ventas
ruta_modulo: modulos/clientes
tipo: modulo
visibilidad: todos
etiquetas: clientes, cliente, cartera, ruc, cedula, consumidor final, deudores, cobro automatico, cobros pendientes, forma de cobro, ingreso automatico, cheque, dias de credito, visitas, dias de visita, ruta de visita, rutero, frecuencia de visita, vendedor, preventa, visita del vendedor, horario de atencion, orden de visita
version: 1.2
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

Para la ruta de visita hay filtros propios:

- `dia_visita:martes` — los clientes que se visitan ese día. Acepta el nombre
  (`miércoles`, con o sin tilde), la abreviatura (`mie`, `X`) o el número
  (`1` = lunes … `7` = domingo).
- `dia_visita:lun,mie` — cualquiera de esos días.
- `-dia_visita:sabado` — los que **no** se visitan ese día (incluye a los que no
  tienen ruta definida).
- `frecuencia:quincenal` — por frecuencia de visita.
- `semana_visita:1` — los que se visitan en esa semana del mes.

Combínelos: `vendedor:"Juan Pérez" dia_visita:martes` da la ruta de ese vendedor
para los martes.

El listado permite además ordenar por cualquier columna, mostrar u ocultar
columnas, ajustar su ancho y exportar a PDF y Excel. Esas preferencias se
guardan por usuario.

## Días de visita del vendedor (pestaña Visitas)

La pestaña **Visitas** define cuándo el vendedor debe pasar por el cliente. Es
opcional: un cliente sin días marcados simplemente no forma parte de ninguna ruta.

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Días de visita | No | Los días de la semana en que se visita al cliente. Puede marcar varios |
| Frecuencia | Sí, si marcó días | *Semanal* (todas las semanas), *Quincenal* o *Mensual* |
| Semanas del mes | Sí, si la frecuencia no es semanal | En qué semanas del mes aplica la visita (S1 a S5) |
| Orden en la ruta | No | Secuencia del recorrido del día: el número menor se visita primero |
| Horario en que atienden | No | Franja de atención del cliente, por ejemplo de 08:00 a 11:00 |
| Nota para el vendedor | No | Indicación práctica: por quién preguntar, cómo llegar, restricciones |

### Cómo se combinan

Los días dicen *qué día* y la frecuencia dice *cada cuánto*. Marcar **martes** con
frecuencia **quincenal** y semanas **1 y 3** significa: se visita el martes de la
primera semana y el martes de la tercera semana de cada mes.

Con frecuencia **semanal** el bloque de semanas del mes ni siquiera aparece: se
visita todas las semanas, así que elegirlas no aportaría nada. Al pie de la
pestaña se muestra siempre el resumen de la pauta tal como quedará guardada
(por ejemplo: *Quincenal · Mar · S1, S3 · 08:00-11:00*).

### Cómo se ve en el listado

El listado trae la columna **Días de visita**, con la semana completa como una
matriz de siete letras (L M X J V S D): resaltadas las de visita, en gris el
resto, para poder recorrer la columna de un vistazo. Si la frecuencia no es
semanal, se añade una etiqueta que lo indica. Al pasar el cursor sobre la celda
se ve la pauta completa. Como toda columna, puede ocultarla o ajustar su ancho
desde el engranaje del listado; también sale en el PDF y en el Excel.

> El **orden en la ruta** no se copia al replicar un cliente hacia otra empresa,
> porque depende del vendedor asignado en cada una. Los días, la frecuencia, el
> horario y la nota sí se copian: describen al cliente, no a la empresa.

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
- **«Indique la frecuencia de visita»**: marcó días de visita pero no eligió cada
  cuánto se repiten. Elija semanal, quincenal o mensual, o quite los días con el
  botón *Limpiar* si este cliente no lleva ruta.
- **«Con frecuencia quincenal debe indicar al menos una semana del mes»**: falta
  marcar en qué semanas aplica. Solo la frecuencia semanal se guarda sin semanas.

## Historial de cambios

- **1.2** — Pestaña *Visitas*: días de visita del vendedor, frecuencia (semanal,
  quincenal, mensual), semanas del mes, orden dentro de la ruta, horario de
  atención y nota para el vendedor. Columna *Días de visita* en el listado, en el
  PDF y en el Excel, y filtros de búsqueda `dia_visita:`, `frecuencia:` y
  `semana_visita:`.
- **1.1** — Cobro automático al autorizar la factura de venta; rango de monto
  (mínimo y máximo); cobro con cheque y fecha diferida por días de crédito;
  botón *Generar cobros pendientes*; la ficha ya no se cierra al guardar, se
  refresca en la misma ventana.
- **1.0** — Versión inicial.
