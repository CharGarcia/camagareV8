---
titulo: Cuentas por cobrar
resumen: Qué le deben los clientes, desde cuándo, y registro del cobro sobre el mismo listado.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_cobrar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por cobrar, cxc, cartera, deudas de clientes, saldo pendiente, vencido, morosidad, cobrar, recibos de venta, tipo de documento, envio masivo, estado de cuenta, recordatorio de pago
version: 1.2
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
que le practicó el cliente; los recibos de venta solo se reducen con cobros.

## Filtrar por tipo de documento

El filtro **Documento** permite ver todo junto o solo un tipo:

- **Todos**: facturas, recibos de venta y saldos iniciales.
- **Facturas de venta**: solo facturas.
- **Recibos de venta**: solo recibos.
- **Saldos iniciales**: solo los saldos de apertura.

Las tarjetas superiores, el gráfico de antigüedad y las exportaciones a PDF y
Excel respetan este filtro. La columna **Origen** de la tabla indica de qué
tipo es cada fila.

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

## Errores frecuentes

- **Un cliente aparece debiendo algo que ya pagó**: revise si el ingreso quedó
  aplicado a esa factura concreta.
- **El saldo es menor de lo esperado**: puede haber notas de crédito aplicadas.
- **No veo las facturas de otro vendedor**: sin el permiso de *acceso total*,
  cada usuario ve solo los documentos que él creó.

## Historial de cambios

- **1.2** — El envío masivo de correo ahora agrupa por cliente: un solo correo
  con la tabla resumen de todos sus documentos pendientes (facturas y recibos)
  y el total, con ventana previa para revisar, corregir o completar el correo
  de cada cliente antes de enviar.
- **1.1** — Se incorporan los recibos de venta al listado y se agrega el filtro
  **Documento** (Todos / Facturas de venta / Recibos de venta / Saldos
  iniciales). Cobro, historial y recordatorio por correo disponibles para
  recibos.
- **1.0** — Versión inicial.
