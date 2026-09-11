---
titulo: Proveedores
resumen: Registro de proveedores con sus datos de pago, retenciones y valores predeterminados que agilizan cada compra.
categoria: Compras
ruta_modulo: modulos/proveedores
tipo: modulo
visibilidad: todos
etiquetas: proveedores, proveedor, acreedor, ruc, retencion, cuenta bancaria, plazo, credito, parte relacionada, pago automatico, cheque, egreso automatico, pagos pendientes, resumen comercial, por pagar, buscar, buscador, filtrar, copiar a otra empresa, replicar, duplicar, multiempresa, valores de terceros, otros conceptos, valores adicionales, bomberos, tasa de basura, planilla de luz, transacciones, productos comprados, servicios comprados, historial de compras, que le compre, ultimo precio, precio de compra, estado de cuenta, kardex, saldo del proveedor, historial de pagos, pagos realizados, egresos, ver egreso
version: 1.7
orden: 10
estado: activo
---

El módulo de **Proveedores** guarda a quién se le compra. Es la base de las
compras, las retenciones, los egresos y los pagos por transferencia.

Su valor real está en los **valores predeterminados**: bien configurado un
proveedor, cada compra suya llega con la retención, la forma de pago y el
concepto ya propuestos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Complete la **identificación** (RUC o cédula) y la **razón social**.
3. Añada los datos de contacto y ubicación.
4. Configure los valores predeterminados (ver más abajo). No son obligatorios,
   pero es lo que ahorra tiempo después.
5. Guarde.

Al guardar, la ficha **no se cierra**: se queda abierta y se refresca con lo que
quedó realmente grabado, para que siga completando pestañas sin volver a
buscar el proveedor. Si lo creó desde una compra, liquidación u orden, el
documento de fondo ya lo tiene seleccionado; cierre la ficha cuando termine.

## Campos

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Identificación | Sí | RUC, cédula o pasaporte |
| Razón social | Sí | Nombre legal, el que sale en los documentos |
| Nombre comercial | No | Cómo se lo conoce habitualmente |
| Dirección, ciudad, provincia | No | Ubicación del proveedor |
| Teléfono, correo | No | Contacto |
| Parte relacionada | No | Está en la pestaña *SRI*. Marque si lo es: se reporta en el anexo |
| Estado | Sí | Activo o inactivo |

Razón social, nombre comercial y dirección admiten hasta **300 caracteres**, el
máximo que el SRI permite en un comprobante electrónico. Si un texto llega más
largo —por ejemplo desde una factura descargada del SRI— se guarda recortado a
ese largo en lugar de rechazar el registro.

## Datos de pago

| Campo | Para qué sirve |
|-------|----------------|
| Banco, número y tipo de cuenta | Necesarios para pagarle por transferencia y para generar el archivo bancario |
| Forma de pago predeterminada | Activa el pago automático: sin ella no se genera ningún egreso solo |
| Operación bancaria predeterminada | Solo si la forma es de tipo banco: transferencia, depósito, débito o cheque |
| Plazo | Días de crédito que le da el proveedor. Define cuándo vence la factura en Cuentas por Pagar |
| Monto mínimo / máximo de pago automático | Rango dentro del cual sus documentos entran en los pagos automáticos |
| Concepto de egreso predeterminado | Concepto contable con el que se registra el egreso |

Deje vacío el mínimo o el máximo para no aplicar ese límite: se puede configurar
solo "mayor a", solo "menor a", o un rango cerrado.

## Pago automático de las compras

Cuando el proveedor tiene **forma de pago predeterminada**, cada compra suya que
entra por **Descargas del SRI** genera sola su egreso, por el total del documento
y con fecha de emisión igual a la de la compra.

Se omite, y queda anotado en el historial de la descarga, si la forma de pago fue
desactivada, si el proveedor tiene retenciones configuradas (aún no existe la
retención, así que pagar el total sería incorrecto), si el monto queda fuera del
rango, o si falta concepto de egreso o punto de emisión activo.

### Pago con cheque

Si la operación bancaria es **Cheque**, el egreso se genera con:

- **Número de cheque**: el consecutivo siguiente al último cheque emitido con esa
  forma de pago. Si no hay ninguno previo, el pago automático se omite y debe
  emitir el primero a mano.
- **Fecha de cobro**: fecha de emisión del documento de compra + los *Días de
  Crédito* del proveedor.

### Generar pagos pendientes

El botón **Generar pagos pendientes**, al pie de la pestaña *Pagos*, registra de
una sola vez los egresos de las compras de ese proveedor **emitidas hasta hoy que
todavía no tienen pago**. Sirve para ponerse al día cuando la forma de pago se
configuró después de que ya habían entrado facturas.

Antes de hacer nada le muestra **cuántos pagos son y por qué monto total**, con el
detalle de los documentos, y pide confirmación.

A diferencia del pago automático en caliente, aquí se paga el **saldo real** de
cada factura (total + valores de terceros − retenciones − notas de crédito +
notas de débito), el mismo que muestra Cuentas por Pagar. Por eso sí funciona con
proveedores a los que se les retiene. Quedan fuera las facturas sin saldo y las
que no entran en el rango de monto configurado; el aviso le dice cuántas son.

En las **planillas de luz y agua**, ese saldo incluye los rubros que la
distribuidora recauda para terceros (bomberos, tasa de basura), que no forman
parte del importe declarado al SRI pero sí se transfieren. Ver *Planillas de luz
y agua: valores de terceros* en el manual de Compras.

Cada pago se registra por separado: si uno falla —por ejemplo, porque su período
contable está cerrado— los demás igual se generan y se le informa cuál falló y por
qué. El botón solo aparece si el proveedor ya está guardado, tiene forma de pago
configurada y usted tiene permiso para crear egresos.

## Resumen comercial

En la pestaña *Comercial* se muestran, **solo como lectura**, tres cifras del
proveedor en la empresa y ambiente activos:

| Dato | Qué suma |
|------|----------|
| Documentos recibidos | Compras, notas de crédito/débito y liquidaciones |
| Total compras | Facturas y liquidaciones − notas de crédito + notas de débito |
| Por pagar | Saldo pendiente, con el mismo criterio de Cuentas por Pagar, más los saldos iniciales |

## Transacciones: productos y servicios comprados

La pestaña *Transacciones* lista lo que se le ha comprado al proveedor a partir
de sus **compras** (facturas, notas de venta, notas de débito y demás
comprobantes) y sus **liquidaciones de compra**. Es de solo lectura.

Tiene dos vistas:

| Vista | Qué muestra |
|-------|-------------|
| Detalle | Una fila por línea de documento: fecha, tipo y número del documento, código, descripción, cantidad, precio unitario, descuento y subtotal |
| Por producto | Una fila por producto o servicio (mismo código y descripción): veces comprado, cantidad total, último precio pagado, fecha de la última compra y total |

Las **notas de crédito** del proveedor se ven en rojo y **restan** en cantidades
y totales, porque son devoluciones o descuentos posteriores. Los documentos
anulados o rechazados no aparecen. Los montos son **sin impuestos**.

Haga clic en el título de una columna para ordenar por ella; otro clic invierte
el orden. La tabla muestra **20 filas por página**; para avanzar, use las flechas
de la derecha (al lado se ve cuántas filas está viendo del total).

### Buscar en las transacciones

El buscador de la pestaña encuentra por **palabras sueltas**, en cualquier orden
y sin distinguir tildes ni mayúsculas, en el código, la descripción y el número
y tipo de documento: `aceite oliva` encuentra *CARBONELL ACEITE SOL OLIVA PET*.

También acepta filtros por campo con la sintaxis `clave:valor`:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `producto:` (o `descripcion:`, `servicio:`) | `producto:arroz` | Busca solo en la descripción |
| `codigo:` | `codigo:7861` | Busca solo en el código |
| `documento:` | `documento:582276` | Número del documento |
| `tipo:` | `tipo:credito` o `-tipo:credito` | Tipo de documento; el `-` lo excluye |
| `fecha:` | `fecha:2026-08` o `fecha:2026-01..2026-03` | Año, mes, día o rango |
| `precio:` | `precio:>10` | Precio unitario |
| `cantidad:` | `cantidad:1..5` | Cantidad |
| `total:` (o `subtotal:`) | `total:>=100` | Subtotal de la línea |

## Estado de cuenta e historial de pagos

La pestaña *Estado de cuenta* muestra en orden cronológico todo lo que suma a la
deuda con el proveedor (**cargos**: compras, liquidaciones, facturas del
exterior, notas de débito y saldos iniciales) y todo lo que la baja (**abonos**:
pagos, retenciones y notas de crédito), con el **saldo corriendo** fila por fila.
Es el mismo cálculo del *Reporte de Cartera*, que sigue las reglas de **Cuentas
por Pagar**.

El *Por pagar* de la pestaña Comercial se calcula documento por documento y no
incluye facturas del exterior: si el proveedor tiene importaciones o documentos
pagados de más, puede no coincidir con el saldo de esta pestaña.

Arriba se resumen los totales del período: compras y cargos, pagos, retenciones
y notas de crédito, y el saldo por pagar.

- **Desde / Hasta**: limitan el período. Con *Desde*, la primera fila es el
  **saldo anterior** a esa fecha, así el saldo final sigue siendo el real.
- **Historial de pagos**: deja a la vista solo los pagos (egresos). El saldo de
  cada fila sigue siendo el que quedó después de ese pago.

Los movimientos se muestran de **20 en 20**, con las flechas de avance abajo a la
derecha. Los totales de arriba y el saldo anterior corresponden a **todo el
período filtrado**, no solo a la página que está viendo.

### Ver un egreso con un clic

Cada pago es un **egreso**. Haga **clic en la fila del pago** y se despliega
debajo su detalle: número, fecha, concepto, documentos que pagó, formas de pago
(banco, cheque, referencia) y quién lo registró. El botón rojo de PDF descarga
el comprobante de egreso. Otro clic en la fila lo pliega.

Un egreso que paga varias facturas del proveedor aparece como **una sola fila**
con el total pagado; el reparto por factura se ve al desplegarlo.

## Retenciones y sustento

| Campo | Para qué sirve |
|-------|----------------|
| Retención de IVA | Porcentaje que se le suele retener de IVA |
| Retención de renta | Porcentaje habitual de retención en la fuente |
| Sustento tributario | El código de sustento con el que se registran sus compras |
| Parte relacionada | Marca al proveedor como parte relacionada; se reporta en el anexo transaccional (ATS) |
| Concepto de egreso predeterminado | Concepto que se propone al pagarle |

Estos valores son **propuestas**, no imposiciones: al registrar la compra o la
retención se pueden cambiar. Configurarlos bien evita el error más común, que es
retener con el porcentaje equivocado por descuido.

## Buscar en el listado

El buscador revisa **todas las columnas del listado**: identificación, tipo de
identificación, razón social, nombre comercial, correo, teléfono, dirección,
plazo, banco, tipo de empresa, provincia y ciudad. Busca por **palabras sueltas**
en cualquier orden y **sin distinguir tildes ni mayúsculas**: escribir
`comercial andina` encuentra *COMERCIAL SANTA ANDINA S.A.* aunque las palabras no
estén juntas.

Escribir exactamente `activo`, `inactivo`, `si` o `no` también filtra por las
columnas *Estado* y *Rela. SRI*.

Además acepta filtros por campo con la sintaxis `clave:valor` (o el desplegable
del buscador): `nombre`, `comercial`, `ruc`, `email`, `telefono`, `direccion`,
`ciudad`, `provincia`, `tipo_empresa`, `banco`, `tipo_id`, `plazo`, `tipo`,
`estado` y `relacionado`. Ejemplos: `ciudad:quito`, `plazo:30..60`,
`estado:inactivo`, `-relacionado:si`.

## Copiar proveedores a otra empresa

Si trabaja con varias empresas, no hace falta volver a teclear la misma ficha en
cada una.

- **Un proveedor**: dentro de su ficha, marque *Aplicar también en otras
  empresas*, elija las empresas y guarde. Al terminar se muestra en qué empresas
  quedó creado, reactivado u omitido.
- **Todos de golpe**: en el listado, el botón **Copiar a otra empresa** copia
  todos los proveedores de la empresa activa hacia la que elija.

Reglas en ambos casos:

- Si el proveedor **ya existe** en la empresa destino (misma identificación), **no
  se duplica ni se sobrescribe**: se respeta lo que ya haya allí.
- Si existía pero estaba **eliminado**, se **reactiva** tal como estaba.
- Solo aparecen las empresas que usted tiene asignadas y en las que tiene permiso
  de **crear** proveedores.
- Los datos que dependen de cada empresa —forma de pago y concepto de egreso
  predeterminados, rango de monto para el pago automático y la ubicación
  geográfica— **no se copian**: se configuran en la empresa destino. Los
  catálogos generales (banco, cuenta, tipo de empresa, retenciones y sustento
  tributario) sí se copian.

## Permisos

Con **acceso total** se ven los proveedores de toda la empresa. Sin ese permiso,
cada usuario ve solo los que creó él — revíselo si alguien reporta proveedores
que "desaparecieron".

Copiar proveedores a otra empresa exige permiso de **crear** en el módulo, tanto
en la empresa de origen como en la de destino. En el copiado masivo, un usuario
sin *acceso total* copia únicamente los proveedores que él creó.

Las pestañas de consulta dependen de los permisos de los módulos de donde salen
sus datos:

- **Transacciones**: aparece si puede ver **Compras** o **Liquidaciones de
  Compra**, y solo trae los documentos de los módulos que puede ver. Sin
  *acceso total* en uno de ellos, de ese módulo ve únicamente los documentos
  que usted registró.
- **Estado de cuenta**: aparece si puede ver **Cuentas por Pagar** o el
  **Reporte de Cartera**. Para desplegar un egreso hace falta, además, permiso
  para ver **Egresos**.

## Eliminar

Es una eliminación **lógica**: el proveedor sale del listado y las compras que ya
lo referencian se conservan intactas. Si solo quiere dejar de usarlo, cámbielo a
**inactivo**.

## Errores frecuentes

- **No aparece al registrar una compra**: está inactivo o pertenece a otra empresa.
- **La retención sale con el porcentaje equivocado**: revise las retenciones
  predeterminadas de su ficha; se aplican a cada compra nueva.
- **No se puede pagar por transferencia**: le faltan banco, número o tipo de
  cuenta.
- **La factura vence en la fecha equivocada**: revise el campo *Plazo*.
- **No se generó el pago automático de una compra**: revise el historial de la
  descarga del SRI; ahí queda escrito el motivo (forma de pago inactiva,
  retenciones configuradas, monto fuera de rango, falta de concepto de egreso).
- **El botón "Generar pagos pendientes" no aparece**: el proveedor aún no está
  guardado, no tiene forma de pago predeterminada, o usted no tiene permiso para
  crear egresos.
- **Se generaron menos pagos de los esperados**: el aviso indica cuántas facturas
  quedaron fuera por no tener saldo o por caer fuera del rango de monto.
- **Una factura del SRI no se registraba por "value too long"**: la dirección o la
  razón social del emisor venía más larga que el campo. Ya no bloquea la carga;
  ver *Descargas del SRI → Errores frecuentes*.
- **No veo las pestañas Transacciones o Estado de cuenta**: le falta permiso para
  ver Compras o Liquidaciones de Compra (Transacciones), o Cuentas por Pagar o el
  Reporte de Cartera (Estado de cuenta). También pudo ocultarlas con el botón de
  configurar pestañas de la ficha.
- **La pestaña pide "Guarde el proveedor"**: la ficha es nueva. Al guardarla, la
  pestaña se carga sola.
- **Faltan compras en Transacciones**: sin *acceso total* en Compras solo ve las
  que usted registró; además no se muestran las anuladas o rechazadas ni las del
  otro ambiente (pruebas/producción).
- **Un pago no se despliega al hacer clic**: necesita permiso para ver Egresos.

## Historial de cambios

- **1.7** — Las pestañas *Transacciones* y *Estado de cuenta* muestran **20 filas
  por página**, con los botones de avance y el contador de filas a la derecha.
- **1.6** — La casilla **Parte relacionada** pasó de la pestaña *Comercial* a la
  pestaña *SRI*, junto al sustento tributario: es un dato tributario del anexo,
  no comercial. No cambia cómo se guarda ni el filtro `relacionado:` del listado.
- **1.5** — Pestaña **Transacciones**: productos y servicios comprados al
  proveedor (compras y liquidaciones), en detalle o agrupados por producto, con
  buscador, filtros `clave:valor`, orden por columna y último precio pagado.
  Pestaña **Estado de cuenta**: movimientos con saldo corriendo (mismo cálculo
  que el Reporte de Cartera y Cuentas por Pagar), rango de fechas, vista de
  historial de pagos y detalle del egreso con un clic. La ficha es más ancha
  para dar espacio a estas tablas.
- **1.4** — Razón social, nombre comercial y dirección aceptan hasta 300
  caracteres (antes 200, 200 y 150). Un texto más largo se recorta en vez de
  impedir que el proveedor se cree, que era lo que hacía fallar el registro
  automático de facturas descargadas del SRI.
- **1.3** — El **pago automático** (tanto el de las descargas del SRI como el
  botón *Generar pagos pendientes*) ya cubre los **valores de terceros** de las
  planillas de luz y agua. Antes pagaba solo el importe declarado al SRI y la
  planilla quedaba con unos centavos pendientes. El **saldo por pagar** del
  resumen comercial de la ficha se calcula con el mismo criterio.
- **1.2** — El buscador del listado cubre todas las columnas (incluidas banco,
  tipo de identificación, tipo de empresa, provincia y ciudad), por palabras y sin
  tildes; nuevos filtros `banco:` y `tipo_id:`, y `estado:`/`relacionado:` ya
  funcionan. Copiar un proveedor —o todos— a otra empresa, igual que en Clientes.
- **1.1** — Rango de monto (mínimo y máximo) para el pago automático; pago con
  cheque con número consecutivo y fecha de cobro por días de crédito; botón
  *Generar pagos pendientes*; resumen comercial de solo lectura; la ficha ya no
  se cierra al guardar, se refresca en la misma ventana.
- **1.0** — Versión inicial.
