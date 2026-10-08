---
titulo: Configuración contable
resumen: Qué cuentas usa cada tipo de documento al generar su asiento automático.
categoria: Contabilidad
ruta_modulo: modulos/configuracion-contable
tipo: modulo
visibilidad: admin
etiquetas: configuracion contable, cuentas por documento, asiento automatico, parametrizacion, ventas, compras, cierre, tipo de produccion, bien, servicio, filtro por año, periodo, listado de proveedores, listado de clientes, cobros y pagos, ingresos y egresos, forma de pago, cuenta bancaria, efectivo, misma cuenta en los dos bloques, formas hermanas, cheques y transferencias, mismo banco, numero de cuenta, nomina, rol de pagos, prestamo quirografario, prestamo hipotecario, prestamo empresa, prestamos iess, cuentas opcionales, costo de ventas, costo de venta, inventario, asiento sin costo, no sale el costo, cuenta de iva del cliente, reglas por cliente, buscar proveedor, buscar cliente, buscar ficha, filtrar fichas, muchos proveedores, cuentas faltantes, modulos que contabilizan, apagar asientos, no generar asientos, no contabilizar, desactivar contabilidad, interruptor, consignaciones sin asiento, aviso de asientos pendientes, asientos pendientes en el balance, proveedores sin cuentas, clientes sin cuentas, productos sin cuentas, pendientes de configurar, retenciones en venta, retenciones en compra, retencion de renta, no aparecen las retenciones, codigo de retencion, catalogo de retenciones sri, codigo ats, codigo del anexo, retencion mal asignada, codigo de retencion no existe, en que documento esta el error, retencion con codigo invalido, sugerencias, sugerir cuentas, proveedores que compran lo mismo, copiar cuentas de otro proveedor, misma cuenta para varios proveedores, gasolineras, proveedores parecidos, subtotal de compras, cuenta de gasto del proveedor, mostrar las demas cuentas, ver todas las cuentas, tipo de asiento no aparece, falta tipo de asiento en el selector, modulo apagado, personalizar asiento contable, tabla de proveedores, detalle de compras, cuenta de subtotal por proveedor, recibos de venta, copiar configuracion de facturas, recibos con otras cuentas, recibo sin asiento, igualar recibos y facturas, ajustes de inventario, asiento de ajustes, sobrante de inventario, faltante de inventario, merma, perdida de inventario, baja de inventario
version: 1.37
orden: 5
estado: activo
---

Esta pantalla es la que hace que la contabilidad funcione sola. Define **qué
cuentas del plan usa cada tipo de documento** al generar su asiento: qué cuenta
se debita al facturar, cuál se acredita al cobrar, dónde va el IVA, dónde el
costo de ventas.

Sin esto configurado, los documentos no generan asiento.

## Cómo está organizado

Cada tipo de operación (venta con factura, compra, cobro, pago, traspaso,
consignación, cierre del ejercicio…) tiene su configuración con las cuentas que
necesita.

El selector **Seleccionar Tipo de Asiento** solo lista los tipos que la empresa usa:
los que tienen algún registro en su módulo (por ejemplo, *Suscripciones - Devengo*
aparece cuando hay al menos una suscripción) o que ya tienen alguna cuenta
configurada. *Cierre del Ejercicio* aparece siempre. Si llega desde el aviso de
asientos pendientes con **Configurar**, el tipo pedido se muestra aunque no cumpla
esa regla.

## De lo general a lo específico

Las cuentas se resuelven por **especificidad**: si una entidad concreta —un
producto, un cliente, una forma de pago— tiene su propia cuenta configurada, esa
manda sobre la configuración general.

Dicho al revés: la configuración general es el valor por defecto, y lo que se
configura en la ficha concreta lo sobreescribe. Es lo que permite que casi todo
funcione con una configuración única y solo las excepciones necesiten atención.

En **Ventas con Factura** (incluye Notas de Crédito, que reusan la misma
configuración) y **Recibos de Venta**, el orden exacto de la cascada es:

1. **Cliente** — si el cliente del documento tiene reglas, todo el documento se
   contabiliza con sus cuentas; lo que no le configuró pasa directo a General.
2. **Producto → Categoría → Marca** — solo si el documento no tiene cliente con
   reglas. Cada línea usa la cuenta de su producto; si no tiene, la de su
   categoría; si no, la de su marca.
3. **Tipo de Producción (Bien / Servicio)** — solo para las líneas que no
   resolvieron cuenta en el paso anterior. Es la dimensión menos específica
   (solo existen dos valores posibles), por eso se evalúa al final, justo antes
   de caer a General.
4. **General** — lo que ningún nivel anterior resolvió.

Qué cuenta como «el cliente tiene reglas»: solo las cuentas que se le asignaron
en ese mismo tipo de asiento. Su **cuenta de IVA por tarifa** no cuenta (el IVA
tiene su propia cascada) ni tampoco las reglas que tenga en otro tipo de asiento,
por ejemplo en Recibos de Venta.

### Costo de Ventas e Inventario

El costo sale del kardex de cada producto, así que estos dos conceptos se
resuelven **siempre por línea** (Producto → Categoría → Marca → Tipo de
Producción → General), aunque el cliente tenga reglas propias y aunque la
factura lleve descuento. La única excepción es que el propio cliente tenga
configurado Costo de Ventas o Inventario: entonces manda su cuenta para ese
concepto.

Si ninguna regla que aplique al producto tiene cuenta de costo, el asiento se
genera **sin** el costo. Lo mismo ocurre si solo una de las dos cuentas (Costo de
Ventas o Inventario) resuelve y la otra no: el bloque de costo entra completo o
no entra.

## Cómo se leen las reglas por entidad

Las reglas por **Proveedor, Cliente, Producto (o Ítem de compra), Categoría,
Marca y Tipo de Producción** se muestran en una **tabla** (ver la sección
siguiente). Las de **Empleado** siguen en **tarjetas** (ver *Tarjetas: Empleado*).

En los dos formatos, cada cuenta se edita en un campo donde se escribe o se busca
la cuenta. El campo dice de un vistazo cómo está ese concepto hoy:

- **Con cuenta propia**: muestra la cuenta asignada a esa entidad y, al lado, el
  botón para quitarla.
- **Vacío con la nota gris `General: …`**: la entidad no tiene cuenta propia, pero
  la configuración General resuelve ese concepto. La nota trae el código y el
  nombre de esa cuenta (por ejemplo `General: 1.1.4.01.001 - IVA en compras`); si
  no cabe entera, se lee completa al pasar el mouse sobre el campo. No hay nada
  que hacer, salvo que se quiera una cuenta distinta para esta entidad en concreto.
- **Vacío con la nota roja `sin cuenta` y el borde rojo**: no hay cuenta ni en
  esta ficha ni en la General. Esos son los que hay que atender: dejan el asiento
  incompleto.

Cada cuenta **se guarda sola** al elegirla de la lista, sin botón de guardar; si
se borra el contenido del campo, esa cuenta se quita.

Las reglas de un proveedor o de un cliente también se pueden ver y editar desde su
propia ficha, en la pestaña **Contable** de los módulos *Proveedores* y *Clientes*
(y de cualquier pantalla que abra esa ficha, como Compras, Liquidaciones o
Facturas de Venta). Es la misma regla: lo que se cambie en un lado se ve en el otro.

## Tablas de reglas: Proveedores, Clientes, Productos, Categorías, Marcas y Tipo de Producción

Al abrir la regla se listan **todas las entidades con movimiento**, sin tener que
agregarlas antes:

| Regla | Qué lista |
|---|---|
| Por Proveedores (compras) | Proveedores con compras o liquidaciones de compra |
| Por Clientes (ventas o recibos) | Clientes con facturas (o con recibos, en *Recibos de Venta*) |
| Por Productos, en ventas o recibos | Productos vendidos en esos documentos |
| Por Productos, en compras | Los **ítems** de las compras y liquidaciones (por su descripción) |
| Por Categorías y Marcas | Todas; con un año elegido, solo las de productos con movimiento ese año |
| Por Tipo de Producción (ventas o recibos) | Siempre las dos filas: **Bien** y **Servicio** |

La regla por Tipo de Producción se aplica según la clasificación Bien/Servicio del
producto de cada línea, solo cuando esa línea no resolvió cuenta por Producto,
Categoría ni Marca. Como son solo dos filas, no tiene buscador, año ni páginas, y
no admite cuentas de IVA por tarifa.

Primero salen las que **todavía no tienen cuenta propia en las columnas de la
tabla** y después las que ya la tienen; cada grupo, en orden alfabético (de la A
a la Z). Se muestran de 25 en 25 (flechas abajo para cambiar de página). Arriba
están el **buscador** y el selector de **año**; en Proveedores, además, el botón
**Sugerencias**.

Cada fila tiene:

- **El nombre** (con RUC, identificación o código cuando lo hay). Si tiene más
  cuentas propias que las de las columnas, lo indica (*+N personalizada(s)*).
- **Las cuentas que normalmente cambian** en esa regla, una por columna:

| Regla | Columnas |
|---|---|
| Por Proveedor | Subtotal de la compra (gasto o costo) |
| Por Cliente (ventas o recibos) | Subtotal (cuenta de ventas) |
| Por Producto, Categoría, Marca o Tipo de Producción, en ventas o recibos | Subtotal, Costo de Ventas e Inventario |
| Por Ítem de compra, Categoría o Marca, en compras | Subtotal de la compra e Inventario |

- **Detalle de compras** (proveedores) o **Detalle de ventas** (clientes): lo que
  se le ha comprado o vendido, según el año elegido, para decidir la cuenta.
- **Copiar de General**: le pone de una vez las cuentas de la configuración
  General en los conceptos que aún no tenga. No pisa lo que ya esté asignado.
- **Personalizar asiento contable** (debajo del nombre): despliega debajo de la
  fila **las demás cuentas** (cuenta por cobrar o por pagar, descuento, ICE,
  propina, IVA por tarifa, redondeo…) en dos columnas, Debe y Haber, para cambiar
  las que hagan falta. Ahí mismo está **Quitar todas sus cuentas**, que la devuelve
  a la configuración General (pide confirmación). Solo afecta al tipo de asiento
  que se esté viendo: si el mismo producto tiene reglas en Compras, esas se
  conservan.

Las entidades sin movimiento no aparecen en la tabla. Las cuentas de un proveedor
o cliente sin documentos se pueden poner desde la pestaña **Contable** de su ficha.

## Tarjetas: Empleado

En las reglas por **Empleado** (Nómina) lo configurado se muestra en **una
tarjeta por empleado**, plegada al entrar. Su cabecera indica *faltan N* o
*completa*, y al desplegarla reúne todas las cuentas en dos columnas, **Debe** y
**Haber**.

El alta se hace en dos pasos:

1. En el buscador de la parte superior se elige el empleado y se pulsa
   **Agregar**. Su tarjeta aparece arriba de la lista, ya desplegada y todavía sin
   cuentas.
2. Dentro de la tarjeta se va asignando la cuenta de cada concepto.

Una ficha sin ninguna cuenta asignada no queda registrada: si se agrega un
empleado y no se le pone nada, al volver a entrar simplemente no aparece.

Dentro de cada tarjeta, **Copiar cuentas de General** rellena de una vez los
conceptos que aún no tienen cuenta propia, y la papelera de la cabecera elimina
**toda la configuración de esa entidad** (pide confirmación).

## Sugerencias: proveedores que compran lo mismo

En *Adquisiciones de Compras/Servicios → Reglas por Proveedores*, el botón
**Sugerencias** (con el número de sugerencias al lado) lista los proveedores que
**todavía no tienen cuentas propias** pero a los que se les compra lo mismo que a
un proveedor **ya configurado**. Ejemplo: si la gasolinera ATIMASA ya tiene sus
cuentas y a otras gasolineras también se les compra *EXTRA*, se sugiere darles
las mismas cuentas.

- Cada sugerencia muestra el proveedor sin cuentas, el proveedor del que se
  copiarían, cuántas cuentas son y los ítems que comparten.
- Si varios proveedores configurados comparten ítems con el mismo proveedor, se
  sugiere el que más ítems tiene en común y se indica cuántas opciones más hay.
- **Asignar** le copia **todas** las cuentas propias de ese proveedor (conceptos
  e IVA por tarifa). Después se pueden ajustar en su tarjeta como cualquier otra.
- **Asignar las visibles** aplica de una vez las sugerencias que queden tras
  usar el filtro del modal; pide confirmación antes.
- Nunca se modifica un proveedor que ya tenga cuentas propias: por eso solo
  aparecen proveedores sin configurar.
- Se comparan las **descripciones** de los ítems de compras y liquidaciones de
  compra, sin distinguir mayúsculas, tildes ni signos. Si en el filtro de año hay
  un año elegido, solo se toman las compras de ese año.

Es una sugerencia: revise que el proveedor de verdad sea del mismo giro antes de
asignar. Un ítem genérico (por ejemplo *SERVICIO*) puede emparejar proveedores
que no tienen nada que ver.

## Recibos de Venta: copiar la configuración de Facturas de Venta

Recibos de Venta tiene **su propia configuración**, separada de la de Facturas de
Venta: los mismos conceptos (Cuenta por cobrar, Subtotal, Descuento, Costo,
Inventario, Propina, ICE, Ajuste por redondeo e IVA por tarifa), pero con sus
cuentas aparte. Así se puede, si se quiere, llevar los recibos a cuentas distintas
de las facturas. Si Recibos se deja vacío, **los recibos no generan asiento**: no
toman las cuentas de Facturas por su cuenta.

Para usar en Recibos las mismas cuentas de Facturas, elija **Recibos de Venta**
en el selector y pulse **Copiar configuración de Facturas de Venta** (arriba,
junto al título). Antes de cambiar nada, el sistema muestra la comparación:

- **Faltan en Recibos**: cuentas que Facturas tiene y Recibos no.
- **Cuenta distinta en Recibos**: el mismo concepto con otra cuenta (se ve la
  cuenta actual y la de Facturas).
- **Solo existen en Recibos**: reglas que Facturas no tiene.

Se copia todo: la configuración General, el IVA por tarifa y las reglas por
Cliente, Producto, Categoría, Marca y Tipo de producción. Luego se elige:

| Opción | Qué hace |
|---|---|
| **Completar lo que falta** | Solo crea las cuentas que Recibos no tiene. No cambia ninguna cuenta ya puesta. |
| **Dejar igual a Facturas** | Además reemplaza las cuentas distintas y elimina las reglas que solo están en Recibos. Recibos queda idéntico a Facturas. Requiere permiso de modificar y eliminar. |

Una cuenta que no corresponde a la naturaleza del concepto, o que ya no existe en
el plan de cuentas, no se copia y se avisa en la comparación. Cada copia queda
registrada en el historial del sistema. Después, cualquier cuenta de Recibos se
puede cambiar a mano como siempre.

Los asientos ya generados no cambian; los recibos que estaban sin asiento lo
generan con la nueva configuración en la siguiente sincronización de
contabilidad.

## Cobros y Pagos: cheques posfechados

En el tipo **Cobros y Pagos**, la sección **Cheques posfechados** tiene dos cuentas
opcionales: *Cheques posfechados por cobrar* (activo) y *Cheques posfechados por
pagar* (pasivo). Con ellas, un cheque con fecha posterior a la del ingreso o egreso
no va a Bancos sino a esa cuenta, hasta que se registra su Fecha Banco en Conciliación
Bancaria. Aplican a los documentos con fecha desde el día en que se asignan (la
pantalla muestra *Aplica desde*); lo anterior se ajusta a mano. Ver [Cheques posfechados en la contabilidad](../guias/cheques-posfechados.md).

## Suscripciones - Devengo: ingresos diferidos y por facturar

El tipo **Suscripciones - Devengo** tiene dos cuentas, que se usan solo con las
suscripciones que reconocen el ingreso *durante el período* (ver
[Suscripciones](modulos/suscripciones)):

- **Ingresos diferidos por suscripciones** (pasivo): la parte de una factura cobrada
  por adelantado que corresponde a meses que aún no se prestan. El devengo mensual la
  pasa a la cuenta de ingreso de cada servicio (la misma que usa la factura).
- **Ingresos devengados por facturar** (activo): el servicio de mes caído ya prestado
  que aún no se factura; la factura del período la cancela.

Se configuran solo en **General** (toda la empresa). En el asiento de la factura o del
recibo, la cuenta de ingreso recibe solo la parte que corresponde al mes de emisión, y el
resto va a estas cuentas. Si una factura tiene ingreso diferido y falta la cuenta, su
asiento **no se genera**: aparece en el aviso de asientos pendientes indicando la cuenta, y
se genera solo al asignarla. Si se reconociera como ingreso, se duplicaría con el que luego
registra el devengo mensual.

## Buscar en las tablas y en las tarjetas

En las **tablas** de reglas (Proveedores, Clientes, Productos, Categorías y
Marcas), el buscador de arriba filtra la tabla mientras se escribe, por nombre y
por RUC, identificación o código. No distingue mayúsculas ni tildes, y admite
varias palabras en cualquier orden: `comercial ferreteria` encuentra
"FERRETERÍA COMERCIAL S.A.". A la derecha se ve cuántas entidades coinciden.

En las **tarjetas** de Empleado, encima de la lista hay un campo **Buscar en las
fichas ya agregadas**, que funciona igual, y la casilla **Solo con cuentas
faltantes**, que deja a la vista únicamente las fichas con el aviso rojo
*faltan N*. El filtro se mantiene al guardar una cuenta, y la ficha recién
agregada con **Agregar** se muestra siempre.

## Cada concepto admite un solo tipo de cuenta

Cada concepto (*Cuenta por cobrar*, *Subtotal*, *IVA*, *Costo de Ventas*,
*Inventario*…) admite cuentas de una naturaleza concreta: la cartera solo acepta
cuentas de **activo**, el subtotal solo cuentas de **ingreso**, el IVA solo de
**pasivo**, y así. El buscador de cuentas de cada campo ya ofrece únicamente las
cuentas de esa naturaleza.

Si aun así se intenta guardar una cuenta que no corresponde, el sistema la
rechaza con un mensaje que dice qué tipo de cuenta espera ese concepto. La
comprobación aplica igual a la configuración General y a las reglas por Cliente,
Producto, Categoría, Marca y Tipo de Producción.

Esto evita el error más caro de esta pantalla: poner la cuenta de ventas en el
campo *Cuenta por cobrar* de un producto o un cliente. Como esa regla gana a la
general, todas las facturas de ese producto o cliente pasan a debitar ingresos en
lugar de cartera, y el error solo se nota al revisar el balance.

## Cobros y Pagos, Ingresos y Egresos: la misma cuenta en los dos bloques

Estos dos tipos de asiento se muestran en **dos bloques** — Cobros y Pagos, o
Ingresos y Egresos — y un mismo concepto puede salir en los dos a la vez:

- una **forma de cobro/pago** cuyo campo *Aplica en* está en **Ambas** (el caso
  normal de una cuenta bancaria, del efectivo o de una tarjeta: el mismo dinero
  entra y sale por ahí);
- una **opción de ingreso/egreso** marcada a la vez para Ingresos y para Egresos.

Cuando se asigna la cuenta contable en uno de los bloques y ese mismo concepto
aparece en el otro con **otra cuenta o sin cuenta**, el sistema lo avisa y
propone aplicar allí la misma:

> *Banco Pichincha Cta. Cte. también se usa en Pagos y aún no tiene cuenta
> contable. ¿Aplicar ahí también 1.1.02.01 - Bancos?*

Si el otro bloque ya tenía una cuenta distinta, el aviso muestra **cuál es** y
pregunta si se reemplaza. Nada se cambia sin aceptar: al responder que no, cada
bloque conserva su cuenta.

Las cuentas del bloque **Cobros y Pagos** también se pueden ver y cambiar desde
[Formas de cobro y pago](formas-cobros-pagos.md), en la ficha de cada forma
(*Cuenta Contable — Cobros* y *Cuenta Contable — Pagos*). Es la misma
configuración vista desde las dos pantallas: lo que se cambie en una aparece en
la otra.

### Formas distintas sobre la misma cuenta bancaria

La propuesta también alcanza a las **formas hermanas**: dos formas de pago
distintas que representan la misma cuenta del banco, como *Transferencias
Pichincha* y *Cheques Pichincha*. Son registros separados porque son dos medios
de cobro/pago, pero el dinero es el mismo y la cuenta contable debería ser una
sola.

Para que el sistema las reconozca como la misma cuenta, las dos formas deben
tener:

- **Tipo** *Banco* o *Cheque* (una puede ser Banco y la otra Cheque);
- el **mismo banco**;
- el **mismo número de cuenta**. Da igual cómo esté escrito — `3380-2300-04` y
  `33802300 04` se toman como el mismo número, porque se comparan solo las letras
  y los dígitos.

**El número de cuenta es obligatorio para el emparejamiento.** Dos formas del
mismo banco con el número vacío no se emparejan: podrían ser la cuenta corriente
y la de ahorros, y el sistema no tiene cómo distinguirlas.

Cuando hay varias filas que actualizar, el aviso las lista todas — el bloque, el
nombre y la cuenta que tiene hoy cada una — y se aplican de una sola vez al
aceptar:

> Este mismo dinero se registra en otras filas que hoy no tienen
> **1.1.02.01 - Bancos**:
> - **Pagos** · Transferencias Pichincha — *sin cuenta*
> - **Cobros** · Cheques Pichincha — 1.1.01.02 - Caja
> - **Pagos** · Cheques Pichincha — 1.1.01.02 - Caja
>
> ¿Aplicarla en todas?

El efectivo, las tarjetas y las demás formas no bancarias solo se emparejan
consigo mismas (su propia fila en el otro bloque): al no haber banco ni número de
cuenta, dos formas de efectivo distintas son cajas distintas.

Lo habitual es aceptar: una cuenta bancaria es la misma cuenta contable cobre o
pague y sea cual sea el medio, y tenerla distinta en cada fila descuadra la
conciliación de esa cuenta en Conciliación Bancaria.

## Filtrar los listados por año

En las tablas de reglas por **Proveedor**, **Cliente**, **Producto**,
**Categoría** y **Marca**, el selector de año de arriba muestra solo los años en
los que la empresa tuvo movimientos. Al elegir uno, la tabla muestra únicamente
las entidades que tuvieron movimiento ese año: proveedores con compras del año,
clientes con ventas del año, productos o ítems que se movieron ese año, y las
categorías y marcas de esos productos.

Con **Todos los años** la tabla muestra todas las entidades con movimiento (y, en
categorías y marcas, todas las registradas, para poder configurarlas por
adelantado).

El módulo del que salen los movimientos depende del tipo de asiento: en
*Adquisiciones de Compras/Servicios* se miran las compras y liquidaciones; en
*Ventas con Factura*, las facturas; en *Recibos de Venta*, los recibos.

## Cierre del ejercicio

Entre los tipos configurables está el **cierre del ejercicio**, que necesita dos
cuentas: la de *resumen de resultados* y la de *resultado del ejercicio*. Son las
que permiten cerrar el año llevando la utilidad al patrimonio.

## Retenciones en venta y en compra: qué códigos aparecen

En **Retenciones en Venta** y **Retenciones en Compra** la lista no es fija: muestra
cada **código de retención** (de renta y de IVA) que la empresa ya usó en sus
retenciones, más los que ya tienen una cuenta configurada. Se cuentan las
retenciones de cualquier ambiente (pruebas o producción), porque la cuenta
contable no depende del ambiente. Primero van los de renta y después los de IVA.

Los códigos de **renta** se muestran y se cruzan con el catálogo por su **código
ATS** (el que viene en el comprobante electrónico: 312, 323, 3440…). Los de **IVA**,
por el código del comprobante (1, 2, 3, 9, 10…), no por su código ATS (725, 730…).
Si con ese código no se encuentra el concepto, se busca por el otro código del
catálogo. Si el mismo concepto está varias veces en el catálogo (por vigencias
distintas), todas sus retenciones usan una sola fila y una sola cuenta.

Si un documento trae un código que **no existe en el catálogo de retenciones del
SRI**, la fila aparece en rojo con el aviso *No existe en el catálogo de
retenciones SRI* y no deja elegir cuenta: el asiento de esas retenciones sale sin
esa línea hasta que se corrija el código en el documento o se agregue al
catálogo. El aviso solo aparece si alguna de esas retenciones tiene **valor
retenido**: una línea con valor cero no genera asiento, así que no hay nada que
corregir ni que configurar.

Debajo del aviso se listan las **retenciones que usan ese código** (número,
fecha y cliente o proveedor; las de ambiente de pruebas llevan la marca
*Pruebas*). Cada número es un enlace que abre, en otra pestaña, el listado de
Retenciones en Ventas o en Compras ya filtrado por ese documento, para
corregir el código ahí. Si son más de 50, se muestran las 50 más recientes y
el total.

## Nómina: cuentas de los préstamos

En el tipo **Nómina** hay tres conceptos para las cuotas de préstamo que se
descuentan en el rol mensual:

- **Préstamos Quirografarios por Pagar** (pasivo): cuota del préstamo
  quirografario del IESS, que la empresa retiene y paga al IESS.
- **Préstamos Hipotecarios por Pagar** (pasivo): cuota del préstamo hipotecario.
- **Préstamos Empresa por Cobrar** (activo): cuota de un préstamo que la empresa
  le dio al empleado; reduce lo que el empleado le debe. Use la misma cuenta de
  activo con la que se registró el desembolso del préstamo.

Los tres son **opcionales**: si se dejan vacíos, la cuota se contabiliza en
**Descuentos**, igual que antes, y la pantalla no los marca como faltantes. Se
configuran en General y también en las **Reglas por Empleado**, donde la cuenta
del empleado manda sobre la General.

## Ajustes de Inventario: sobrantes y faltantes

El tipo **Ajustes de Inventario** da las cuentas del asiento que genera cada ajuste
(entrada o salida) del módulo **Inventario**, a costo:

- **Inventario**: sube con una entrada y baja con una salida. Si se deja vacía, se usa
  la cuenta de Inventario de *Ventas con Factura*.
- **Sobrante de inventario**: contrapartida de las **entradas** por ajuste (sobrantes
  de un conteo físico). Normalmente una cuenta de otros ingresos.
- **Faltante / merma de inventario**: contrapartida de las **salidas** por ajuste
  (faltantes, mermas, daños). Normalmente una cuenta de gasto.

Aparece en el selector cuando la empresa ya tiene movimientos de inventario (kardex) o
alguna de estas cuentas configurada. Se configuran solo en **General**. Mientras falte una cuenta, los ajustes se guardan sin
asiento y aparecen en el aviso de asientos pendientes con el nombre de la cuenta que
falta; al configurarla se contabilizan solos. Ver
[Inventario](modulos/inventario), sección *Asiento contable del ajuste*.

## Módulos que contabilizan: apagar los asientos de un módulo

El botón **Módulos que contabilizan** (arriba, junto a *Configurar*)
abre la lista de módulos que generan asientos automáticos, agrupados en Ventas,
Compras, Tesorería, Consignaciones, Inventario y Nómina. Cada uno tiene un interruptor. Por
defecto todos están **encendidos**.

Se apaga un módulo cuando la empresa **no quiere asientos automáticos** de sus
documentos. El caso típico son las **consignaciones**: muchas empresas no
reclasifican la mercadería entregada a *Mercadería en consignación*. La dejan en
*Inventario* y solo la factura mueve cuentas.

Con un módulo apagado:

- Sus documentos **nuevos no generan asiento**, ni al guardar, ni al abrir el
  módulo, ni al sincronizar Estados Financieros.
- **No aparecen como pendientes** en el aviso que sale al entrar a Balance de
  comprobación, Mayores, Asientos contables o Estados Financieros. Tampoco se
  avisan las cuentas que les falten (por ejemplo, conceptos de Ingresos/Egresos
  sin cuenta cuando ambos módulos están apagados).
- Los documentos que **ya tenían asiento lo conservan** y se siguen actualizando
  si se editan, para que asiento y documento no queden descuadrados.
- Cuando **todos** los módulos que usan un tipo de asiento están apagados, ese
  tipo **deja de aparecer** en el selector *Seleccionar Tipo de Asiento*: no hay
  nada que configurar. Por ejemplo, *Ventas con Factura* solo desaparece si están
  apagados Facturas de Venta, Notas de Crédito, Notas de Débito y Cambios de
  Productos; *Cobros y Pagos*, si lo están Ingresos, Egresos, Conciliación de
  Tarjetas y Traspasos. El selector se actualiza en el momento; al volver a
  encender un módulo, su tipo de asiento reaparece. *Cierre del Ejercicio* y
  *Activos Fijos - Depreciación* no dependen de ningún interruptor y siempre se
  muestran.
- **Suscripciones (devengo de ingresos)**, en el grupo Ventas: apagado, las facturas
  nuevas de suscripciones reconocen todo el ingreso al facturar y el cierre no
  provisiona el mes caído. Lo que ya estaba diferido se sigue devengando mes a mes,
  para que no quede detenido en el pasivo.

**Retornos** y **Facturación de consignaciones** no tienen interruptor propio:
aparecen con la etiqueta *Sigue a Consignaciones en Ventas*. Su asiento es el
inverso del de la consignación, así que solo se genera cuando la consignación de
origen tiene asiento.

Al **apagar Consignaciones**, si la cuenta *Mercadería en consignación* tiene
saldo, el sistema lo muestra: corresponde a consignaciones ya contabilizadas y se
descargará con sus retornos y facturaciones, o con un asiento manual.

Al **volver a encender** un módulo, los documentos que quedaron sin asiento se
contabilizan solos al abrir el módulo o al sincronizar Estados Financieros,
salvo los de períodos cerrados.

Cada cambio queda registrado en el historial del sistema (`log_sistema`) con el
usuario, la fecha y el valor anterior. Para usar el interruptor hace falta el
permiso **Actualizar** de este módulo.

## Cuándo tocar esta pantalla

- Al poner en marcha la empresa.
- Al cambiar el plan de cuentas.
- Cuando un asiento automático va a una cuenta equivocada de forma sistemática.

Si el error es en un solo documento, el problema no está aquí sino en ese
documento o en la ficha de la entidad implicada.

## Llegar desde el aviso de asientos pendientes

El aviso de asientos sin generar (Asientos Contables, Mayores, Estados
Financieros y Balance de Comprobación) trae, en cada línea, un enlace
**Configurar**. Abre esta pantalla en otra pestaña con el tipo de asiento ya
elegido y la sección donde falta la cuenta desplegada: la General, las reglas por
Proveedor o por Cliente, las formas de cobro o de pago, o los conceptos de
ingresos o egresos. Solo falta asignar la cuenta.

## Errores frecuentes

- **Un documento no genera asiento**: falta configurar su tipo de operación.
- **"No se puede generar el asiento de ventas: «X» no tiene categoría asignada"**
  (o *marca*): las cuentas de ese asiento están configuradas por categoría (o por
  marca) y el producto o servicio X no la tiene. Lo que falta no es una cuenta:
  asígnele la categoría (o marca) en **Productos** y vuelva a generar el asiento.
- **"Faltan cuentas por configurar: la categoría «C» (de «X») no tiene la cuenta
  «Cuenta por cobrar»"**: el producto sí tiene categoría, pero esa categoría no
  tiene la cuenta del concepto y no hay General de respaldo. Configúrela en la
  regla de esa categoría. Si el mensaje dice *«Cuenta por cobrar» para PRODUCTO X*,
  el producto no entra en ninguna regla (producto, categoría, marca o tipo de
  producción) ni hay General: elija la regla que corresponda a su forma de
  trabajar. No es obligatorio usar la General.
- **Avisos al generar asientos sobre productos**: *"Ventas con Factura se
  contabiliza por categorías, pero hay N producto(s)/servicio(s) sin categoría
  asignada"* (lo mismo con marcas, y con Recibos de Venta y Compras) pide asignarla
  en **Productos**. *"Hay N producto(s)/servicio(s) cuya categoría, marca o tipo de
  producción no tiene la cuenta de Ventas con Factura"* pide configurar la cuenta
  en la regla de esa categoría/marca.
- **No aparece una retención (de renta o IVA) para configurar**: la lista solo
  muestra códigos ya usados en retenciones de la empresa. Si el código sale con el
  aviso de que no existe en el catálogo SRI, el problema es el código del
  documento, no la configuración.
- **El asiento va a una cuenta que no corresponde**: revise primero la ficha del
  producto, cliente o forma de pago; su cuenta manda sobre la general.
- **La misma cuenta bancaria contabiliza distinto al cobrar que al pagar**: la
  forma de pago tiene una cuenta en el bloque de Cobros y otra en el de Pagos.
  Vuelva a asignar la cuenta correcta en uno de los dos y acepte la propuesta de
  aplicarla también en el otro.
- **El sistema no propone copiar la cuenta entre dos formas del mismo banco**:
  revise en *Formas de Cobros y Pagos* que las dos tengan el número de cuenta
  escrito y que su tipo sea Banco o Cheque. Sin número de cuenta no se emparejan.
- **El asiento de la factura sale sin Costo de Ventas**: revise, en este orden,
  que el producto sea inventariable, que la línea tenga bodega y que la salida de
  inventario tenga costo (si el producto se vendió sin haber entrado antes con
  costo, la salida queda en 0 y no hay costo que contabilizar). Si todo eso está
  bien, falta la cuenta de Costo de Ventas o la de Inventario en algún nivel de
  la cascada.
- **Todas las facturas debitan una cuenta de ventas en lugar de la cartera**:
  hay una regla por Cliente o por Producto con la cuenta de ingresos puesta en el
  concepto *Cuenta por cobrar*. Corríjala en la pestaña de esa dimensión (o
  bórrela para que herede la cuenta general) y vuelva a generar los asientos de
  los documentos afectados.
- **"Está contabilizando por categorías: hay N producto(s)/servicio(s) sin categoría asignada"**
  (o *por marcas … sin marca*): aparece arriba de la configuración cuando el tipo de
  asiento elegido tiene reglas por categoría (o por marca) y hay productos o servicios
  sin ella. Mientras no la tengan, sus documentos no usan las cuentas de la categoría
  (o marca): toman la General o, si no la hay, no generan asiento. Los productos con
  regla propia no se listan. Asígneles la categoría o marca en **Productos**.

## Historial de cambios

- **1.37** — Nuevo tipo de asiento **Ajustes de Inventario** (Inventario, Sobrante y
  Faltante / merma) para el asiento automático de los ajustes del módulo Inventario, y
  su interruptor en *Módulos que contabilizan* (grupo Inventario).

- **1.36** — El selector de tipos de asiento solo lista los que la empresa usa (con registros
  en su módulo o con alguna cuenta configurada).

- **1.35** — Nuevo tipo de asiento **Suscripciones - Devengo**: cuentas de ingresos diferidos
  (pasivo) e ingresos devengados por facturar (activo) para el devengado de suscripciones.
  En **Módulos que contabilizan**, nuevo interruptor *Suscripciones (devengo de ingresos)*.

- **1.34** — Cobros y Pagos: cuentas de **cheques posfechados** por cobrar y por pagar.
- **1.33** — En Recibos de Venta, botón **Copiar configuración de Facturas de Venta**: compara las
  dos configuraciones y permite completar lo que falta o dejar Recibos igual a Facturas. El aviso
  de productos sin categoría o marca ahora también considera el IVA configurado por categoría o
  marca en Recibos.
- **1.32** — Cuando el asiento se contabiliza por categoría o marca y un producto no la tiene,
  el mensaje dice que falta asignarle la categoría (o marca) en Productos, en lugar de pedir
  una cuenta. Igual en los avisos al generar asientos y en el aviso de esta pantalla.
- **1.31** — Aviso preventivo al generar asientos: productos y servicios sin categoría o sin
  marca (o sin ninguna regla) que se quedarían sin cuenta en Ventas con Factura. Además, en esta
  pantalla: si el tipo de asiento elegido se contabiliza **por categorías** (o **por marcas**), se
  comprueba que todos los productos y servicios tengan una categoría (o marca) asignada y se
  listan los que no, con enlace a Productos.
- **1.30** — Si un producto de una venta, recibo, nota de crédito o compra no tiene cuenta en
  ninguna regla (ni producto, ni categoría, ni marca, ni tipo de producción) ni en la General,
  el asiento ya no se genera sin esa línea: se detiene y dice qué producto y qué cuenta faltan.
- **1.29** — La regla por Tipo de Producción también pasa a la tabla: dos filas fijas (Bien y
  Servicio) con Subtotal, Costo de Ventas e Inventario como columnas y *Personalizar asiento
  contable* para el resto; se quitó su formulario *Nueva Asociación*.
- **1.28** — Las reglas por Clientes, Productos (e Ítems de compra), Categorías y Marcas
  pasan al mismo diseño de tabla que Proveedores: todas las entidades con movimiento, las
  cuentas principales como columnas, *Personalizar asiento contable* para el resto y sin el
  formulario *Nueva Asociación*. Primero las que no tienen esas cuentas, de la A a la Z.
- **1.27** — *Reglas por Proveedores* pasa a ser una tabla con todos los proveedores con
  compras: nombre, cuenta del Subtotal, *Detalle de compras* y *Copiar de General*; las
  demás cuentas se abren con *Personalizar asiento contable*. Se quitó el formulario
  *Nueva Asociación por Proveedor*. Primero salen los proveedores sin cuenta de Subtotal,
  de la A a la Z.
- **1.26** — Los tipos de asiento cuyos módulos están todos apagados en *Módulos que
  contabilizan* ya no aparecen en el selector de tipo de asiento.
- **1.25** — La vista resumida se extiende a las reglas por Cliente (cuenta de ventas) y
  a las de Producto, Categoría, Marca, Tipo de Producción e Ítem de compra (Subtotal,
  Costo e Inventario según el asiento).
- **1.24** — Las tarjetas de proveedor muestran de entrada solo la cuenta del Subtotal de
  la compra; las demás se despliegan con *Mostrar las demás cuentas*.
- **1.23** — En las tarjetas de reglas por entidad, la nota gris `General: …` muestra
  también el nombre de la cuenta, no solo su código.
- **1.22** — Nuevo botón **Sugerencias** en *Reglas por Proveedores*: propone copiar las
  cuentas de un proveedor ya configurado a los proveedores sin cuentas a los que se les
  compra lo mismo.
- **1.21** — El aviso *No existe en el catálogo de retenciones SRI* (retenciones en
  venta y en compra) lista las retenciones que usan ese código, con enlace a cada una,
  y solo aparece si esas retenciones tienen valor retenido.
- **1.20** — Las reglas por **Proveedor** y por **Cliente** se pueden editar también desde la
  pestaña **Contable** de la ficha del proveedor o del cliente.
- **1.19** — La pantalla se puede abrir desde el enlace **Configurar** del aviso
  de asientos pendientes: carga sola el tipo de asiento y despliega la sección
  donde falta la cuenta.
- **1.18** — El botón *Configurar Asientos* pasa a llamarse **Configurar** y es
  más compacto, para que *Crear Cuenta Contable* y *Módulos que contabilizan*
  quepan en la misma fila que el selector de tipo de asiento.
- **1.17** — Las retenciones de renta se identifican por su **código ATS**, tanto
  en esta pantalla como en el asiento. Antes se cruzaban por otro código del
  catálogo y, cuando no coincidían (por ejemplo 323 y 323I), la cuenta se asignaba
  a un concepto distinto del que traía el documento. Si el código no se encuentra
  así, se busca por el otro código del catálogo. Revise las cuentas de sus
  retenciones de renta: algún código puede aparecer ahora sin cuenta.
- **1.16** — En Retenciones en Venta y en Compra aparecen todos los códigos
  usados por la empresa, también los de renta que antes no salían: la lista ya no
  se limita al ambiente actual e incluye los códigos que ya tienen cuenta. Los
  códigos que no existen en el catálogo SRI se muestran con un aviso en vez de
  ocultarse.
- **1.15** — En los listados de entidades de las reglas por Proveedor, Cliente,
  Producto, Categoría y Marca, las que no tienen cuentas asignadas se muestran
  primero. En cada ficha de Proveedor se agregó el botón *Información de
  adquisiciones* para ver lo comprado mientras se asignan las cuentas.
- **1.14** — **Módulos que contabilizan**: un interruptor por módulo para que la
  empresa deje de generar asientos automáticos (por ejemplo, de consignaciones).
  Los módulos apagados no figuran como pendientes en Balance, Mayores ni Estados
  Financieros; retornos y facturaciones de consignación siguen a su consignación
  de origen.
- **1.13** — Buscador sobre las fichas ya agregadas en las reglas por Cliente,
  Proveedor, Empleado, Producto, Categoría y Marca, con opción de ver solo las
  fichas con cuentas faltantes.
- **1.12** — Costo de Ventas e Inventario se resuelven siempre por producto,
  categoría, marca y tipo de producción, salvo que el cliente los tenga
  configurados. Antes el costo no se contabilizaba en tres casos aunque estuviera
  configurado por producto o categoría: cuando el cliente tenía reglas propias,
  cuando solo tenía una cuenta de IVA propia (o reglas de otro tipo de asiento) y
  cuando la factura llevaba descuento con cuenta de Descuento configurada. En ese
  último caso la Cuenta por Cobrar y el ICE configurados por categoría también se
  ignoraban. Aplica a Facturas de Venta, Recibos de Venta y Notas de Crédito.
- **1.11** — Se retiró el botón "Configurar cuentas sugeridas" de Configuración
  General, porque asignaba cuentas equivocadas. Las cuentas se asignan a mano en
  cada concepto. Las cuentas que ese botón ya había asignado no cambian:
  revíselas y corrija las que no correspondan.
- **1.10** — Nómina incorpora tres conceptos opcionales para las cuotas de préstamo
  (quirografario, hipotecario y empresa), configurables en General y por
  empleado. Si quedan sin cuenta, la cuota sigue yendo a Descuentos.
- **1.9** — Las cuentas del bloque Ingresos y Egresos también se administran desde
  el módulo Opciones de ingreso y egreso, en la ficha de cada concepto libre: es
  la misma configuración, sincronizada en las dos pantallas. Los conceptos atados
  a un módulo (Compras, Liquidaciones, Facturas y Recibos de Venta, Nómina) siguen
  tomando su cuenta de la sección de ese módulo. Además, el asiento del ingreso o
  del egreso usa ahora exactamente la cuenta que muestra esta pantalla: antes leía
  solo la cuenta guardada en el módulo de conceptos, así que una cuenta cargada
  por la importación de configuración contable se veía puesta pero no llegaba al
  asiento.
- **1.8** — Las cuentas del bloque Cobros y Pagos se pueden administrar también
  desde el módulo Formas de cobro y pago, en la ficha de cada forma: es la misma
  configuración, sincronizada en las dos pantallas.
- **1.7** — En Cobros y Pagos y en Ingresos y Egresos, al asignar la cuenta de un
  concepto que también aparece en el bloque contrario (forma con *Aplica en:
  Ambas*, u opción marcada para ingresos y egresos), el sistema propone aplicar
  allí la misma cuenta. La propuesta alcanza además a las **formas hermanas**:
  otras formas de tipo Banco o Cheque con el mismo banco y el mismo número de
  cuenta, en los dos bloques, que se actualizan de una sola vez. Si alguna fila
  ya tenía otra cuenta, el aviso la muestra y pregunta si se reemplaza; no cambia
  nada sin confirmación.
- **1.6** — El alta de reglas por entidad se divide en dos pasos: primero se
  agrega la entidad y luego se asignan las cuentas dentro de su tarjeta, que se
  guardan una a una al elegirlas. Cada tarjeta incorpora *Copiar cuentas de
  General* y un botón para eliminar toda su configuración de golpe.
- **1.5** — Las reglas por entidad (Cliente, Proveedor, Producto, Categoría,
  Marca, Tipo de Producción, Ítem de compra) pasan de una tabla plana a **una
  tarjeta plegable por entidad**, una por fila y ordenadas por nombre, con sus
  cuentas separadas en Debe y Haber, aviso de los conceptos que quedan sin cuenta
  en ningún nivel y resumen de los que se resuelven con la configuración General.
  Las tarjetas arrancan plegadas; su cabecera ya indica si falta alguna cuenta.
- **1.4** — Cada concepto acepta únicamente cuentas de la naturaleza que le
  corresponde (la cartera de ventas, solo cuentas de activo). El sistema rechaza
  el guardado si la cuenta no cuadra, tanto en la configuración General como en
  las reglas por entidad.
- **1.3** — Nuevo botón "Configurar cuentas sugeridas" en Configuración
  General: asigna las cuentas del plan de cuentas modelo a los conceptos que
  estén sin cuenta, sin tocar los que ya la tienen.
- **1.2** — Los listados de entidades (Proveedores con compras, Clientes con
  ventas, Ítems de compras, Categorías, Marcas) ahora respetan el selector de
  año. Se agregó ese selector a las reglas por Categoría y por Marca.
- **1.1** — Se agregó la regla por Tipo de Producción (Bien / Servicio) en la
  cascada de Ventas con Factura, Notas de Crédito y Recibos de Venta, entre
  Producto/Categoría/Marca y General.
- **1.0** — Versión inicial.
