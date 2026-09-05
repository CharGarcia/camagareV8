---
titulo: Cuentas por cobrar
resumen: Qué le deben los clientes, desde cuándo, y registro del cobro sobre el mismo listado.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_cobrar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por cobrar, cxc, cartera, deudas de clientes, saldo pendiente, vencido, morosidad, cobrar, recibos de venta, tipo de documento, envio masivo, estado de cuenta, recordatorio de pago, fecha de corte, saldo a una fecha, fecha hasta, vendedor, cartera por vendedor, filtrar por vendedor
version: 1.6
orden: 40
estado: activo
---

**Cuentas por cobrar** es la cartera de la empresa: qué facturas siguen sin
cobrarse, de qué cliente y cuántos días llevan vencidas.

## De dónde sale el saldo

El listado se arma con:

- Las **facturas de venta** emitidas y no cobradas.
- Los **recibos de venta** (comprobante interno) con saldo pendiente. No se
  incluyen los anulados ni los que ya se convirtieron en factura (en ese caso
  la deuda pasa a la factura).
- Los **saldos iniciales** cargados al empezar a usar el sistema.

Y se descuenta lo que ya se cobró: cada ingreso que cobra un documento reduce
su saldo. En las facturas también restan las notas de crédito y las retenciones
que le practicó el cliente, y suman las notas de débito; los recibos de venta
solo se reducen con cobros.

Fórmula de cada factura:

```
saldo = total de la factura + notas de débito − cobros − retenciones − notas de crédito
```

### Cómo se enlazan las retenciones y las notas con la factura

- Una **retención** se aplica a la factura desde la que se registró o, si vino
  del SRI, a la factura que figura como *documento de sustento* en sus líneas.
  Si una misma retención sustenta **varias facturas**, cada factura recibe
  solo el valor retenido de **sus** líneas, no el total de la retención.
- Las **notas de crédito y débito** se aplican a la factura que consta como
  *documento modificado*.
- En ambos casos el número se compara **normalizado** (sin guiones y con ceros a la izquierda): `001-001-1120`,
  `001001000001120` y `001-001-000001120` son la misma factura. Un formato
  distinto ya no deja la retención o la nota sin descontar.
- La misma regla la usan el buscador de documentos pendientes de **Ingresos** y
  el estado de pago de **Facturas de Venta**, así que los tres muestran el
  mismo saldo para la misma factura.

## Filtrar por tipo de documento

El filtro **Documento** permite ver todo junto o solo un tipo:

- **Todos**: facturas, recibos de venta y saldos iniciales.
- **Facturas de venta**: solo facturas.
- **Recibos de venta**: solo recibos.
- **Saldos iniciales**: solo los saldos de apertura.

Las tarjetas superiores, el gráfico de antigüedad y las exportaciones a PDF y
Excel respetan este filtro. La columna **Origen** de la tabla indica de qué
tipo es cada fila.

## Filtrar por vendedor

El filtro **Vendedor** deja ver solo la cartera de un vendedor: las facturas y
los recibos de venta que tienen a ese vendedor asignado en el documento. La
lista muestra todos los vendedores de la empresa, incluidos los inactivos,
porque un vendedor dado de baja puede seguir teniendo cartera pendiente.

Las tarjetas superiores, el gráfico de antigüedad y las exportaciones a PDF y
Excel respetan el filtro, igual que el de tipo de documento y el de cliente.
El **Excel** incluye además la columna **Vendedor** con el nombre del vendedor
asignado a cada factura o recibo (vacía en los saldos iniciales).

> Los **saldos iniciales** no tienen vendedor, así que al elegir un vendedor
> quedan fuera del listado y de los totales. Con **Todos** vuelven a aparecer.

## Fecha Hasta como fecha de corte

El filtro **Fecha Hasta** no solo limita qué documentos se muestran (los
emitidos hasta esa fecha): también es la **fecha de corte del saldo**. Los
cobros, retenciones y notas de crédito o débito fechados **después** de esa
fecha no se descuentan, así el listado muestra lo que se debía **ese día**.

Ejemplo: una factura cobrada el 31 de mayo aparece pendiente, con su saldo
completo, en cualquier consulta con Fecha Hasta igual o anterior al 30 de mayo,
y desaparece de los pendientes a partir del 31.

Aplica por igual a facturas, recibos de venta y **saldos iniciales**; las
tarjetas, el gráfico de antigüedad y las exportaciones respetan el corte. Sin
Fecha Hasta, el saldo es el actual. La fecha que manda para un cobro es la
**fecha del ingreso**.

## Días vencidos

Cada documento muestra los **días vencidos**, calculados desde su fecha de
vencimiento. Un documento con días vencidos en cero está pendiente pero todavía
en plazo.

Puede filtrar entre ver solo lo pendiente, solo lo vencido o todo.

## Registrar el cobro

El cobro se registra desde el propio listado, sin salir a otro módulo, tanto
para facturas como para recibos de venta y saldos iniciales. Lo que se registra
aquí es exactamente lo mismo que un ingreso: reduce el saldo del documento y
genera su asiento.

El recordatorio por **correo** funciona para facturas y recibos; el envío por
**WhatsApp** está disponible solo para facturas.

## Envío masivo de recordatorios por correo

Marque los documentos con el casillero de cada fila (o el casillero **Todos**
de la cabecera) y use el botón **Envío Masivo Email**. Antes de enviar se abre
una ventana de **revisión por cliente**: cuántos documentos y qué saldo suma
cada uno, y el **correo destinatario** precargado desde la ficha del cliente.

En esa ventana puede:

- **Corregir el correo** de cualquier cliente o poner **varios destinatarios**
  separados por coma. El cambio aplica solo a ese envío (la ficha del cliente
  no se modifica).
- **Completar** el correo de un cliente que no lo tiene registrado (su casilla
  aparece resaltada).
- **Omitir** a un cliente dejando su correo vacío.

Al confirmar, el sistema envía **un solo correo por cliente** con la tabla
resumen de sus documentos pendientes — facturas y recibos mezclados — con
emisión, vencimiento, días vencidos, total y saldo de cada uno, más el
**total pendiente**.

Detalles del envío:

- Los documentos que ya no tienen saldo al momento del envío se excluyen del
  resumen automáticamente.
- Los saldos iniciales no participan del envío masivo (no tienen ficha de
  contacto); para ellos use el cobro directo.
- Cada envío queda registrado en la auditoría del sistema con los correos
  usados.

## Por qué un saldo no cuadra

Casi siempre por una de estas tres razones, en este orden de frecuencia:

1. **Falta registrar la retención** que le practicó el cliente. La factura queda
   con ese saldo pendiente para siempre.
2. **Falta la nota de crédito** de una devolución ya acordada.
3. El cobro se registró **contra otro documento** del mismo cliente.

Y dos casos que el reporte **no** descuenta a propósito:

- Una retención o nota de crédito cuyo documento de sustento **no existe** como
  factura ni como saldo inicial (por ejemplo, de una factura anterior al uso
  del sistema). Regístrela como saldo inicial o corrija el número.
- Una factura que sigue en **borrador** (aún sin autorizar): no aparece en el
  listado aunque tenga saldo.

## Errores frecuentes

- **Un cliente aparece debiendo algo que ya pagó**: revise si el ingreso quedó
  aplicado a esa factura concreta.
- **El saldo es menor de lo esperado**: puede haber notas de crédito aplicadas.
- **No veo las facturas de otro vendedor**: sin el permiso de *acceso total*,
  cada usuario ve solo los documentos que él creó.

## Historial de cambios

- **1.6** — El PDF y el Excel exportados muestran, bajo el encabezado, los
  **filtros aplicados** (tipo de documento, estado, vendedor, período y cliente),
  para que quien lo reciba sepa exactamente qué cartera está viendo. En el Excel
  los montos ahora son celdas numéricas con dos decimales y sin separador de
  miles, listas para sumar.
- **1.5** — Nuevo filtro **Vendedor** en la tarjeta de filtros: acota facturas y
  recibos al vendedor asignado en el documento. Aplica a listado, tarjetas,
  gráfico de antigüedad y exportaciones. Los saldos iniciales, que no tienen
  vendedor, se excluyen mientras haya un vendedor seleccionado. El Excel
  incorpora la columna **Vendedor**.
- **1.4** — Los **saldos iniciales** respetan la fecha de corte igual que las
  facturas: con Fecha Hasta, un cobro, retención o nota de crédito posterior a
  esa fecha ya no descuenta el saldo inicial (antes se usaba el acumulado
  cobrado sin importar la fecha). Aplica al listado, a las tarjetas y al
  gráfico de antigüedad.
- **1.3** — Corrección del cruce del saldo: (1) una retención que sustenta
  varias facturas ya no resta su total completo a cada una, sino solo el valor
  retenido de las líneas de cada factura; (2) las retenciones y las notas de
  crédito/débito se enlazan a la factura comparando el número **normalizado** (sin guiones, con ceros a la izquierda) del
  número, así un formato distinto (`001-001-1120`) ya no deja el abono sin
  descontar. La misma regla quedó compartida con Ingresos y Facturas de Venta.
- **1.2** — El envío masivo de correo ahora agrupa por cliente: un solo correo
  con la tabla resumen de todos sus documentos pendientes (facturas y recibos)
  y el total, con ventana previa para revisar, corregir o completar el correo
  de cada cliente antes de enviar.
- **1.1** — Se incorporan los recibos de venta al listado y se agrega el filtro
  **Documento** (Todos / Facturas de venta / Recibos de venta / Saldos
  iniciales). Cobro, historial y recordatorio por correo disponibles para
  recibos.
- **1.0** — Versión inicial.
