---
titulo: Configuración contable
resumen: Qué cuentas usa cada tipo de documento al generar su asiento automático.
categoria: Contabilidad
ruta_modulo: modulos/configuracion-contable
tipo: modulo
visibilidad: admin
etiquetas: configuracion contable, cuentas por documento, asiento automatico, parametrizacion, ventas, compras, cierre, tipo de produccion, bien, servicio, filtro por año, periodo, listado de proveedores, listado de clientes
version: 1.3
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

## Configurar cuentas sugeridas

Dentro de **Configuración General** hay un botón **Configurar cuentas
sugeridas**. Asigna de una sola vez las cuentas que propone el
[plan de cuentas modelo](plan-cuentas.md) a todos los conceptos que estén **sin
cuenta**: tipos de asiento de ventas, recibos de venta, compras y nómina, IVA
por tarifa, cierre del ejercicio, formas de cobro/pago y opciones de
ingreso/egreso.

**No modifica ninguna cuenta ya asignada** y **no toca el plan de cuentas**.
Puede pulsarlo las veces que quiera: si no hay nada pendiente, lo dice y no
cambia nada.

Sirve sobre todo en dos casos: una empresa que cargó su plan por Excel (esa vía
no configura cuentas), y una empresa configurada hace tiempo a la que le faltan
conceptos añadidos después.

Si su plan de cuentas usa códigos distintos a los del modelo, los conceptos cuya
cuenta no exista se informan al terminar y quedan para asignar a mano.

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

El botón de la papelera de la cabecera elimina **toda la configuración de esa
entidad** de una vez: pide confirmación y, al aceptar, esa entidad vuelve a
contabilizarse con la configuración General. Solo afecta al tipo de asiento que
se esté viendo — si el mismo producto tiene reglas en Compras, esas se conservan.

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

El módulo del que salen los movimientos depende del tipo de asiento: en
*Adquisiciones de Compras/Servicios* se miran las compras; en *Ventas con
Factura* y *Recibos de Venta*, las ventas.

## Cierre del ejercicio

Entre los tipos configurables está el **cierre del ejercicio**, que necesita dos
cuentas: la de *resumen de resultados* y la de *resultado del ejercicio*. Son las
que permiten cerrar el año llevando la utilidad al patrimonio.

## Cuándo tocar esta pantalla

- Al poner en marcha la empresa.
- Al cambiar el plan de cuentas.
- Cuando un asiento automático va a una cuenta equivocada de forma sistemática.

Si el error es en un solo documento, el problema no está aquí sino en ese
documento o en la ficha de la entidad implicada.

## Errores frecuentes

- **Un documento no genera asiento**: falta configurar su tipo de operación.
- **El asiento va a una cuenta que no corresponde**: revise primero la ficha del
  producto, cliente o forma de pago; su cuenta manda sobre la general.
- **Todas las facturas debitan una cuenta de ventas en lugar de la cartera**:
  hay una regla por Cliente o por Producto con la cuenta de ingresos puesta en el
  concepto *Cuenta por cobrar*. Corríjala en la pestaña de esa dimensión (o
  bórrela para que herede la cuenta general) y vuelva a generar los asientos de
  los documentos afectados.

## Historial de cambios

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
