---
titulo: Configuración contable
resumen: Qué cuentas usa cada tipo de documento al generar su asiento automático.
categoria: Contabilidad
ruta_modulo: modulos/configuracion-contable
tipo: modulo
visibilidad: admin
etiquetas: configuracion contable, cuentas por documento, asiento automatico, parametrizacion, ventas, compras, cierre, tipo de produccion, bien, servicio, filtro por año, periodo, listado de proveedores, listado de clientes, cobros y pagos, ingresos y egresos, forma de pago, cuenta bancaria, efectivo, misma cuenta en los dos bloques, formas hermanas, cheques y transferencias, mismo banco, numero de cuenta, nomina, rol de pagos, prestamo quirografario, prestamo hipotecario, prestamo empresa, prestamos iess, cuentas opcionales, costo de ventas, costo de venta, inventario, asiento sin costo, no sale el costo, cuenta de iva del cliente, reglas por cliente, buscar proveedor, buscar cliente, buscar ficha, filtrar fichas, muchos proveedores, cuentas faltantes, modulos que contabilizan, apagar asientos, no generar asientos, no contabilizar, desactivar contabilidad, interruptor, consignaciones sin asiento, aviso de asientos pendientes, asientos pendientes en el balance, proveedores sin cuentas, clientes sin cuentas, productos sin cuentas, pendientes de configurar, retenciones en venta, retenciones en compra, retencion de renta, no aparecen las retenciones, codigo de retencion, catalogo de retenciones sri, codigo ats, codigo del anexo, retencion mal asignada
version: 1.18
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

En las pestañas de reglas por Cliente, Proveedor, Producto, Categoría, Marca, Tipo
de Producción e Ítem de compra, lo configurado se muestra en **una tarjeta por
entidad**, una debajo de otra y ordenadas por nombre. Cada tarjeta se pliega y se
despliega al hacer clic en su título, y reúne todas las cuentas de ese producto
(o cliente, o categoría), incluidas las de IVA por tarifa, repartidas en dos
columnas: **Debe** a la izquierda y **Haber** a la derecha.

Todas las tarjetas aparecen **plegadas** al entrar, para poder recorrer la lista
de un vistazo. No hace falta abrirlas para saber cuáles necesitan atención: la
propia cabecera indica *faltan N* o *completa*.

Dentro de cada columna hay una línea por concepto, con un campo donde se escribe
o se busca la cuenta. El campo dice de un vistazo cómo está ese concepto hoy:

- **Con cuenta propia**: muestra la cuenta asignada a esa entidad y, al lado, el
  botón para quitarla.
- **Vacío con la nota gris `General: …`**: la entidad no tiene cuenta propia, pero
  la configuración General resuelve ese concepto. No hay nada que hacer, salvo
  que se quiera una cuenta distinta para esta entidad en concreto.
- **Vacío con la nota roja `sin cuenta` y el borde rojo**: no hay cuenta ni en
  esta ficha ni en la General. Esos son los que hay que atender: dejan el asiento
  incompleto.

La cabecera de la tarjeta resume el estado: cuántas cuentas propias tiene y si
queda algo sin resolver (*completa* o *faltan N*). Al pie, una línea indica
cuántos conceptos más se resuelven con la cuenta General.

Así se distingue a simple vista, por ejemplo, un producto al que solo se le
asignó la cuenta de ingresos de otro que además tiene su propia cartera o su
costo, y se ve enseguida de qué lado del asiento falta algo.

## Agregar y quitar reglas por entidad

El alta se hace en dos pasos:

1. En el buscador de la parte superior de la pestaña se elige la entidad
   (cliente, producto, categoría…) y se pulsa **Agregar**. Su tarjeta aparece
   arriba de la lista, ya desplegada y todavía sin cuentas.
2. Dentro de la tarjeta se va asignando la cuenta de cada concepto. **Cada cuenta
   se guarda sola** al elegirla de la lista, sin botón de guardar; si se borra el
   contenido del campo, esa cuenta se quita.

Una ficha sin ninguna cuenta asignada no queda registrada: si se agrega una
entidad y no se le pone nada, al volver a entrar simplemente no aparece.

Dentro de cada tarjeta, el botón **Copiar cuentas de General** rellena de una vez
los conceptos que aún no tienen cuenta propia con las de la configuración
General, para partir de esa base y ajustar solo lo que cambie. No pisa lo que ya
esté asignado en la ficha.

En las fichas de **Proveedor**, junto a ese botón está **Información de
adquisiciones**: muestra los ítems que se le han comprado a ese proveedor (según
el año elegido en el selector de la regla), para decidir las cuentas sin tener
que volver a buscarlo arriba.

El botón de la papelera de la cabecera elimina **toda la configuración de esa
entidad** de una vez: pide confirmación y, al aceptar, esa entidad vuelve a
contabilizarse con la configuración General. Solo afecta al tipo de asiento que
se esté viendo — si el mismo producto tiene reglas en Compras, esas se conservan.

## Buscar entre las fichas ya agregadas

Cuando una pestaña tiene muchas fichas (cientos de proveedores o clientes), no
hace falta bajar con el mouse hasta encontrar la que se busca. Encima de la
lista de tarjetas hay un campo **Buscar en las fichas ya agregadas**:

- Filtra al instante mientras se escribe, por el nombre de la entidad.
- No distingue mayúsculas ni tildes, y admite varias palabras en cualquier
  orden: `comercial ferreteria` encuentra "FERRETERÍA COMERCIAL S.A.".
- La casilla **Solo con cuentas faltantes** deja a la vista únicamente las fichas
  con el aviso rojo *faltan N*, para completarlas sin revisar una por una.
- A la derecha se ve cuántas fichas coinciden sobre el total.

El filtro se mantiene al guardar una cuenta (la lista se recarga pero sigue
filtrada). La ficha recién agregada con **Agregar** se muestra siempre, aunque
no coincida con la búsqueda.

Este buscador es distinto del de la parte superior de la pestaña: aquel sirve
para **agregar** una entidad nueva; este, para **encontrar** las que ya tienen
reglas.

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
conciliación de esa cuenta en Control Bancario.

## Filtrar los listados por año

En las reglas por **Proveedor**, **Cliente**, **Producto**, **Categoría** y
**Marca** hay un selector de año junto al botón que abre el listado (*Proveedores
con compras*, *Clientes con ventas*, *Ítems de compras*, *Categorías*,
*Marcas*…). Ese selector muestra solo los años en los que la empresa tuvo
movimientos.

Al elegir un año, el listado muestra únicamente las entidades que tuvieron
movimiento en ese año: proveedores con compras del año, clientes con ventas del
año, ítems comprados ese año, y las categorías y marcas de los productos que se
movieron ese año. El año elegido aparece como etiqueta en el título del listado.

Con **Todos los años** el listado se comporta como siempre: todas las entidades
con movimiento (y, en el caso de categorías y marcas, todas las registradas, para
poder configurarlas por adelantado).

En todos estos listados (*Proveedores con compras*, *Clientes con ventas*,
productos, *Ítems de compras*, *Categorías* y *Marcas*), las entidades que
todavía **no tienen cuentas asignadas** aparecen primero; las que ya las tienen
(marcadas con *con cuentas*) quedan al final. Así lo pendiente de configurar
queda siempre arriba.

El módulo del que salen los movimientos depende del tipo de asiento: en
*Adquisiciones de Compras/Servicios* se miran las compras; en *Ventas con
Factura* y *Recibos de Venta*, las ventas.

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
catálogo.

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

## Módulos que contabilizan: apagar los asientos de un módulo

El botón **Módulos que contabilizan** (arriba, junto a *Configurar*)
abre la lista de módulos que generan asientos automáticos, agrupados en Ventas,
Compras, Tesorería, Consignaciones y Nómina. Cada uno tiene un interruptor. Por
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

## Errores frecuentes

- **Un documento no genera asiento**: falta configurar su tipo de operación.
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

## Historial de cambios

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
