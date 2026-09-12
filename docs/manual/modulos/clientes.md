---
titulo: Clientes
resumen: Registro de los clientes de la empresa: datos de identificación, búsqueda, permisos y eliminación.
categoria: Ventas
ruta_modulo: modulos/clientes
tipo: modulo
visibilidad: todos
etiquetas: clientes, cliente, cartera, ruc, cedula, consumidor final, deudores, cobro automatico, cobros pendientes, forma de cobro, ingreso automatico, cheque, dias de credito, visitas, dias de visita, ruta de visita, rutero, frecuencia de visita, vendedor, preventa, visita del vendedor, horario de atencion, orden de visita, importar clientes, carga masiva, asignar vendedor, transacciones, productos vendidos, servicios vendidos, historial de ventas, que le vendi, ultimo precio, precio de venta, estado de cuenta, kardex, saldo del cliente, historial de cobros, cobros realizados, ingresos, ver ingreso, cedula y ruc, cliente duplicado, proveedor duplicado, identificacion repetida, ruc es la cedula mas 001, mismo tercero dos fichas, estado de cuenta partido
version: 1.9
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

## Transacciones: productos y servicios vendidos

La pestaña *Transacciones* lista lo que se le ha vendido al cliente a partir de sus
**facturas de venta**, **recibos de venta** y **notas de crédito**. Es de solo
lectura.

Tiene dos vistas:

| Vista | Qué muestra |
|-------|-------------|
| Detalle | Una fila por línea de documento: fecha, tipo y número del documento, código, descripción, cantidad, precio unitario, descuento, subtotal e IVA |
| Por producto | Una fila por producto o servicio (mismo código y descripción): veces vendido, cantidad total, último precio, fecha de la última venta y total |

Las **notas de crédito** se ven en rojo y **restan** en cantidades y totales,
porque son devoluciones o descuentos posteriores. No aparecen los documentos en
borrador, anulados ni los que el SRI no aceptó; tampoco los recibos ya
convertidos en factura, para no contar dos veces la misma venta.

El **subtotal** de cada línea va **sin impuestos** y a su lado se muestra el
**IVA** de esa misma línea; al pasar el cursor por encima se ve la tarifa
aplicada (por ejemplo 15 %). Al pie de la tabla se totalizan los dos: el neto sin
impuestos y el IVA del conjunto que esté viendo filtrado.

Haga clic en el título de una columna para ordenar por ella; otro clic invierte
el orden. La tabla muestra **20 filas por página**; para avanzar, use las flechas
de la derecha (al lado se ve cuántas filas está viendo del total).

La pestaña **solo aparece** si el cliente tiene documentos: a un cliente al que
todavía no se le ha vendido nada no se le muestra esta pestaña.

### Buscar en las transacciones

El buscador de la pestaña encuentra por **palabras sueltas**, en cualquier orden
y sin distinguir tildes ni mayúsculas, en el código, la descripción y el número y
tipo de documento.

También acepta filtros por campo con la sintaxis `clave:valor`:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `producto:` (o `descripcion:`, `servicio:`) | `producto:arroz` | Busca solo en la descripción |
| `codigo:` | `codigo:7861` | Busca solo en el código |
| `documento:` | `documento:000123` | Número del documento |
| `tipo:` | `tipo:credito` o `-tipo:credito` | Tipo de documento; el `-` lo excluye |
| `fecha:` | `fecha:2026-08` o `fecha:2026-01..2026-03` | Año, mes, día o rango |
| `precio:` | `precio:>10` | Precio unitario |
| `cantidad:` | `cantidad:1..5` | Cantidad |
| `total:` (o `subtotal:`) | `total:>=100` | Subtotal de la línea |

## Estado de cuenta e historial de cobros

La pestaña *Estado de cuenta* muestra en orden cronológico todo lo que suma a la
deuda del cliente (**cargos**: facturas, recibos, notas de débito y saldos
iniciales) y todo lo que la baja (**abonos**: cobros, retenciones y notas de
crédito), con el **saldo corriendo** fila por fila. Es el mismo cálculo del
*Reporte de Cartera*, que sigue las reglas de **Cuentas por Cobrar**.

La pestaña **solo aparece** si se cumplen dos cosas: que el usuario tenga acceso
al **Reporte de Cartera** (de donde sale el cálculo) y que el cliente tenga
movimientos. El botón **Excel** descarga exactamente lo que está viendo —los
movimientos del período elegido, con el saldo de cada fila y el resumen— en una
hoja de cálculo.

Al pie, debajo de los movimientos, se resumen los totales del período: ventas y
cargos, cobros, retenciones y notas de crédito, y el saldo por cobrar.

- **Desde / Hasta**: limitan el período. Con *Desde*, la primera fila es el
  **saldo anterior** a esa fecha, así el saldo final sigue siendo el real.
- **Historial de cobros**: deja a la vista solo los cobros (ingresos). El saldo de
  cada fila sigue siendo el que quedó después de ese cobro.

Los movimientos se muestran de **20 en 20**, con las flechas de avance arriba a la
derecha, junto a los filtros. Los totales del pie y el saldo anterior corresponden
a **todo el período filtrado**, no solo a la página que está viendo.

### Ver un ingreso con un clic

Cada cobro es un **ingreso**. Haga **clic en la fila del cobro** y se despliega
debajo su detalle: número, fecha, concepto, documentos que cobró, formas de cobro
(banco, cheque, referencia) y quién lo registró. El botón rojo de PDF descarga el
comprobante de ingreso. Otro clic en la fila lo pliega.

Un ingreso que cobra varias facturas del cliente aparece como **una sola fila**
con el total cobrado; el reparto por factura se ve al desplegarlo.

## Anticipos

La pestaña *Anticipos* muestra el dinero que el cliente entregó por adelantado y
cuánto le queda **a favor**. Cada fila es un movimiento, con el saldo corriendo:

| Movimiento | Qué es | Efecto |
|------------|--------|--------|
| Saldo inicial | El anticipo con el que arrancó el sistema (módulo *Saldos iniciales*) | Suma |
| Anticipo recibido | Un ingreso registrado con un concepto de tipo *anticipo de cliente* | Suma |
| Aplicado a un cobro | Un cobro pagado con la forma de cobro tipo *anticipo* | Resta |

Al pie se ven los tres totales: lo recibido, lo ya aplicado y el **saldo a favor**.
Ese saldo es el mismo que aparece al elegir la forma de cobro *anticipo* mientras
se registra un cobro: sale de la misma fórmula, así que no puede discrepar.

La pestaña **solo aparece** si el cliente tiene anticipos y el usuario puede ver
**Ingresos**, que es donde se registran.

## Si el mismo cliente está cargado con cédula y con RUC

Un mismo contribuyente puede tener **dos fichas**: una con la **cédula** (10
dígitos) y otra con el **RUC** (13 dígitos), que en las personas naturales es esa
cédula seguida de **001** —por ejemplo `1717136574` y `1717136574001`—. Al abrir
cualquiera de las dos, las consultas de la ficha muestran **el total del
cliente**, no solo lo de esa fila:

- **Resumen comercial**: los documentos y el total vendido suman las dos fichas.
- **Transacciones**: lista los productos y servicios de los documentos de ambas.
- **Estado de cuenta**: un solo kardex, con el saldo real (el mismo que muestran
  Cuentas por cobrar y el Reporte de Cartera).

Los **datos de la ficha** (nombre, dirección, correo…) siguen siendo los de la
fila que abrió, y **no se fusiona nada**: son dos registros y se pueden editar o
eliminar por separado. La pestaña **Anticipos** tampoco se suma, se queda en la
ficha: es el saldo que se consume al registrar un cobro, y ahí cada ficha
tiene el suyo.

## Carga masiva desde Excel

En *Configuración → Importador desde Excel* la entidad **Clientes** permite
cargar o actualizar clientes en bloque (se reconocen por identificación). La
plantilla incluye al final la columna opcional **VENDEDOR**, donde se escribe la
identificación o el nombre exacto del vendedor asignado; la hoja de consulta
*Vendedores* del mismo archivo lista los que existen en la empresa. Si la celda
va vacía, un cliente que ya existía conserva su vendedor. Detalle y errores
frecuentes en la guía *Importar datos desde Excel*.

## Permisos

Lo que puede hacer cada persona depende de los permisos asignados al submódulo:

- **Ver**: consultar el listado.
- **Crear**, **Modificar**, **Eliminar**: las acciones correspondientes.
- **Acceso total**: ver los clientes de toda la empresa. Sin este permiso, cada
  usuario ve únicamente *los clientes que él mismo creó*.

Si no ve clientes que sabe que existen, lo más probable es que le falte el
permiso de acceso total.

Las pestañas de consulta de la ficha dependen de los permisos de los módulos de
donde salen sus datos:

- **Transacciones**: aparece si puede ver **Facturas de Venta**, **Recibos de
  Venta** o **Notas de Crédito**, y solo trae los documentos de los módulos que
  puede ver. Sin *acceso total* en uno de ellos, de ese módulo ve únicamente los
  documentos que usted registró.
- **Estado de cuenta**: aparece si puede ver **Cuentas por Cobrar** o el
  **Reporte de Cartera**. Para desplegar un cobro hace falta, además, permiso
  para ver **Ingresos**.

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
- **No veo las pestañas Transacciones o Estado de cuenta**: le falta permiso para
  ver Facturas, Recibos o Notas de Crédito (Transacciones), o Cuentas por Cobrar
  o el Reporte de Cartera (Estado de cuenta). También pudo ocultarlas con el
  botón de configurar pestañas de la ficha.
- **La pestaña pide «Guarde el cliente»**: la ficha es nueva. Al guardarla, la
  pestaña se carga sola.
- **Faltan ventas en Transacciones**: sin *acceso total* en Facturas de Venta
  solo ve las que usted registró; además no se muestran las anuladas, en
  borrador o no aceptadas por el SRI, ni las del otro ambiente
  (pruebas/producción).
- **Un cobro no se despliega al hacer clic**: necesita permiso para ver Ingresos.

## Historial de cambios

- **1.9** — Si el mismo contribuyente está cargado **dos veces** —una ficha con la cédula y
  otra con el RUC, que es esa cédula + `001`—, el **Resumen comercial**, las
  **Transacciones** y el **Estado de cuenta** de la ficha muestran el total del
  cliente, sumando las dos fichas, en vez de la mitad en cada una. Nueva sección
  *Si el mismo cliente está cargado con cédula y con RUC*.
- **1.8** — Las pestañas de consulta **solo aparecen cuando hay algo que mostrar**:
  *Transacciones* si el cliente tiene documentos, *Estado de cuenta* si tiene
  movimientos y además el usuario puede ver el **Reporte de Cartera** (antes
  bastaba con Cuentas por Cobrar), y la nueva pestaña **Anticipos** si tiene
  anticipos. El estado de cuenta se puede **descargar en Excel** con el período
  que esté viendo.
- **1.7** — La ficha del cliente ya no tiene barra de desplazamiento propia dentro
  del modal: cuando el contenido no cabe en la pantalla se desplaza el modal
  completo, igual que en Facturas de Venta o Compras.
- **1.6** — *Transacciones* muestra el **IVA de cada línea** (con su tarifa en el
  tooltip) y el total de IVA al pie. Al abrir la ficha de un cliente, la cabecera
  del modal muestra su **nombre**.
- **1.5** — Las pestañas *Transacciones* y *Estado de cuenta* muestran **20 filas
  por página**, con los botones de avance y el contador de filas arriba a la
  derecha. En el estado de cuenta, el resumen del período pasó al pie, debajo de
  los movimientos.
  Además, el estado de cuenta abre mucho más rápido: el cruce de las notas de
  crédito/débito y de las retenciones con su factura ya no recalcula el número de
  cada factura por cada documento (ver *Reporte de Cartera*).
- **1.4** — Pestaña **Transacciones**: productos y servicios vendidos al cliente
  (facturas, recibos y notas de crédito), en detalle o agrupados por producto,
  con buscador, filtros `clave:valor`, orden por columna y último precio.
  Pestaña **Estado de cuenta**: movimientos con saldo corriendo (mismo cálculo
  que el Reporte de Cartera y Cuentas por Cobrar), rango de fechas, vista de
  historial de cobros y detalle del ingreso con un clic. La ficha es más ancha
  para dar espacio a estas tablas.
- **1.3** — Carga masiva desde Excel: la plantilla de clientes admite la columna
  VENDEDOR para asignar el vendedor por identificación o nombre.
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
