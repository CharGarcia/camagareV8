---
titulo: Notas de débito
resumen: Documento que aumenta el valor pendiente de una factura de venta ya autorizada (intereses, cargos, valores no cobrados en su momento).
categoria: Ventas
ruta_modulo: modulos/nota_debito
tipo: modulo
visibilidad: todos
etiquetas: nota de debito, notas de debito, cargo adicional, interes por mora, sri, buscar nota de debito, buscador, filtros, filtrar notas de debito, buscar por motivo, filtro de fechas, documento modificado, chips, aparecen notas que no busque, resultados que no corresponden, buscar por clave de acceso, imprimir, impresora
version: 1.8
orden: 31
estado: activo
---

La **nota de débito** es el documento con el que se **aumenta** lo que un
cliente debe por una factura ya emitida: intereses por mora, gastos no
facturados en su momento, o cualquier valor adicional que corresponde cobrar
después. Es el reverso de la nota de crédito: mientras esta rebaja, la nota de
débito **suma** al saldo pendiente de la factura.

A diferencia de la factura o la nota de crédito, no tiene detalle de
productos: se sustenta en uno o más **motivos** (razón + valor) que en
conjunto forman el subtotal del documento.

## Solo sobre facturas autorizadas

Únicamente se puede emitir una nota de débito sobre una factura en estado
**autorizado**.

## Cómo se emite

1. Seleccione el cliente.
2. Elija la **factura o documento a modificar** (o escriba el número
   manualmente si no aparece en el listado).
3. Agregue uno o más **motivos** (razón + valor); su suma forma el subtotal.
4. Seleccione la **tarifa de IVA** aplicable, si corresponde.
5. Opcionalmente, agregue una o más **formas de pago**.
6. Revise el total y guarde.
7. Envíe al SRI.

## Editar y eliminar

Solo se pueden **editar** o **eliminar** notas de débito en estado
**borrador**. Una vez enviada y autorizada, el documento es definitivo ante el
SRI.

## Efecto en la cartera y la contabilidad

A diferencia de la nota de crédito, la nota de débito **no afecta
inventario** (no tiene productos). Sí **aumenta el saldo por cobrar** de la
factura relacionada (en Cuentas por Cobrar) y genera un asiento contable en el
mismo sentido que una factura: debita Cuentas por Cobrar y acredita Ventas
(más IVA Ventas si aplica).

## Exportar el documento

En la barra de acciones superior del modal, además de **PDF** y **XML**, hay
un botón **Excel** (icono verde) que descarga los motivos, totales y forma de
pago de esa nota de débito puntual. Solo se habilita con el documento ya
guardado.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la nota: N° nota,
secuencial, fecha, cliente, identificación, documento modificado, subtotal,
total y usuario; además, en las observaciones. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo notas donde se vea por qué coinciden, estos datos se consultan en
la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso y N° de autorización | Pestaña *Nota de débito* |
| Motivos de la nota | Pestaña *Detalles* |
| Correo y Estado | Pestaña *Nota de débito* |

En un comprobante electrónico la clave de acceso y el número de autorización son
el mismo número de 49 dígitos, que lleva dentro la fecha, el RUC y el número del
documento. Al escribir un número de documento en el cuadro aparecían notas ajenas
cuya clave contenía por casualidad esa secuencia; ahora ese número solo encuentra
la nota que realmente lo tiene.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Nota de débito** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, autorizado, anulado), correo (enviado o pendiente), serie, N° nota, secuencial, con o sin asiento contable, fecha de autorización, usuario que registró |
| Documento modificado | N° de la factura modificada, fecha de esa factura, motivo (busca en las razones de la nota) |
| Valores | Total, subtotal e IVA (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula, observaciones, N° autorización, clave de acceso |

El selector *Usuario que registró* lista solo a quienes ya registraron notas
de débito en la empresa, y *Serie* solo las series con notas guardadas. El
*IVA* se calcula como total menos subtotal.

**Pestaña Detalles** (lo que hay dentro de la nota). Es un único cuadro,
**Buscar libremente dentro de las notas de débito**: escriba un motivo, un
valor, una forma de pago SRI, un plazo o un dato de la información adicional,
y aparece la lista de **cada línea que coincide** con la nota a la que
pertenece (número, fecha, factura modificada, cliente y estado). Un clic en la
fila deja el listado mostrando solo esa nota; el ícono de la derecha la abre
directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
notas de débito que coinciden con el buscador y el orden aplicados en ese
momento (no solo la página visible).

## Errores frecuentes

- **"Solo se pueden generar notas de débito para facturas en estado
  autorizado"**: la factura aún no fue autorizada por el SRI.
- **"Solo se pueden editar Notas de Débito en estado borrador"**: ya fue
  enviada.
- **"La suma de los pagos no coincide con el valor total"**: si se ingresan
  formas de pago, su suma debe cuadrar exactamente con el total del documento.

## La fecha de emisión y el envío al SRI

Para enviar al SRI, la **fecha de emisión debe ser la de hoy**. Si intenta enviar
la nota de débito fechada otro día, el envío se detiene antes de salir —sin gastar
un intento contra el SRI— con el aviso *"la fecha de emisión de la nota de débito
(…) debe ser la fecha actual"*. Ponga la fecha de hoy, guarde y vuelva a enviar.

## Períodos contables cerrados

Un documento que mueve cartera, inventario o contabilidad no puede tocar un
período ya cerrado. El sistema lo comprueba en las cuatro operaciones:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrado |
| Anular | La fecha del documento (anular revierte sus movimientos) |
| Eliminar | La fecha del documento |

Al modificar se revisan las dos fechas a propósito: mover un documento de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se
cierran en **Contabilidad → Períodos Contables**; reabrir el período permite
la operación de inmediato.

## Historial de cambios

- **1.8** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.6** — Corregido: al buscar un **número de documento** en el cuadro aparecían
  también notas que no lo tenían. La búsqueda libre miraba dentro de la **clave de
  acceso** y del **número de autorización** —el mismo número de 49 dígitos, que lleva
  la fecha, el RUC y el número del documento— y cualquier número corto caía ahí por
  casualidad. Ahora esos dos datos se consultan en la ventana de filtros, y los
  **motivos** de la nota pasan a la pestaña *Detalles*, que sí muestra qué línea
  coincidió.

- **1.5** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la nota (y en autorización,
  clave de acceso, observaciones y motivos), salvo Correo y Estado. Los filtros
  pasan a una **ventana propia** (botón del embudo, se aplican con *Aplicar*)
  con dos pestañas: **Nota de débito** (filtros por campo, con criterios
  nuevos: correo, con/sin asiento, fecha de autorización, usuario como lista,
  fecha del documento modificado, motivo, IVA, observaciones, autorización y
  clave de acceso) y **Detalles** (búsqueda dentro de motivos, formas de pago e
  información adicional). Los filtros activos se ven como etiquetas dentro del
  cuadro y la tabla se atenúa mientras carga.
- **1.4** — El envío al SRI comprueba ahora que la **fecha de emisión sea la de
  hoy**, como ya hacían factura de venta y liquidación de compra. Antes el documento
  salía con cualquier fecha y era el propio SRI quien lo rechazaba.
- **1.3** — El módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una nota de débito cuyo período esté cerrado. Antes no se
  comprobaba en ninguna de las cuatro operaciones.
- **1.2** — Corregido el botón **Nueva Nota de Débito**: después de enviar una
  nota al SRI, al crear la siguiente el modal conservaba datos de la anterior
  (estado en la pestaña **SRI**, ambiente y tipo de emisión, y el contenido de
  las pestañas **Asiento contable** y **Factura relacionada**), y quedaba
  abierto en la pestaña **SRI** si el envío había sido rechazado. Ya no hace
  falta recargar la pantalla: el modal arranca limpio y en borrador.
- **1.1** — Botón **Excel** en la barra de acciones del modal, para exportar
  motivos, totales y forma de pago de una nota de débito puntual.
- **1.0** — Versión inicial (emisión de notas de débito de venta).
