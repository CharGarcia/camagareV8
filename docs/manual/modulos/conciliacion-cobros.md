---
titulo: Conciliación de cobros
resumen: Cruce entre los cobros registrados y lo que realmente entró al banco.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-cobros
tipo: modulo
visibilidad: todos
etiquetas: conciliacion de cobros, cuadrar cobros, banco, deposito, tarjeta, liquidacion, diferencias, serie, punto de emision, serie inactiva, generar ingresos, extracto bancario
version: 1.2
orden: 65
estado: activo
---

Este módulo cruza los **cobros registrados en el sistema** con lo que **realmente
llegó al banco**. Sirve para detectar cobros que nunca se depositaron y depósitos
que nadie registró.

## Por qué hace falta

Entre el cobro y el banco hay un trecho: el efectivo tarda en depositarse, las
tarjetas se liquidan días después y con comisión, y un cheque puede rebotar.
Mientras eso no se cruce, el saldo contable no es el saldo real.

## Cómo se usa

1. Cargue o consulte los movimientos del banco del periodo.
2. Cruce cada uno con el cobro que le corresponde.
3. Revise lo que queda sin cruzar por ambos lados.

## Qué mirar en las diferencias

- **Cobro sin depósito**: dinero cobrado que no llegó al banco. Puede ser normal
  (aún no se ha depositado) o no serlo.
- **Depósito sin cobro**: entró dinero que nadie registró. Falta un ingreso.
- **Diferencia de importe en tarjetas**: normalmente es la comisión de la
  procesadora, que hay que registrar como gasto.

## Serie de los ingresos: solo puntos de emisión activos

Al subir el extracto (**Subir y Conciliar**) se elige el **Punto de Emisión
(para los Ingresos)**: la serie con la que se numeran los ingresos que genera
la carga. La lista muestra únicamente los puntos de emisión **activos**; los
inactivos no aparecen.

- Para usar una serie que no aparece, actívela en **Empresa**, pestaña
  **Puntos de Emisión**.
- Si la empresa no tiene ningún punto activo, la lista muestra *Sin series
  activas* y no se puede subir el extracto.
- La serie se valida otra vez al pulsar **Generar ingresos de las líneas
  confirmadas**. Si se inactivó después de subir la carga, no se genera ningún
  ingreso y aparece el aviso *La serie (punto de emisión) de esta carga ya no es
  válida o está inactiva*: vuelva a activarla para continuar con esa carga.

## Errores frecuentes

- **Todo queda sin cruzar**: revise el rango de fechas y la cuenta bancaria
  seleccionada.
- **Las tarjetas nunca cuadran exactamente**: es esperable; la diferencia es la
  comisión y debe registrarse.
- **Un punto de emisión no aparece al subir el extracto**: está **inactivo**;
  actívelo en Empresa, pestaña Puntos de Emisión.

## Historial de cambios

- **1.2** — El selector **Punto de Emisión (para los Ingresos)** ya no ofrece
  puntos **inactivos**, y la serie se vuelve a validar al generar los ingresos:
  una carga cuya serie se inactivó no emite ingresos en ella. Nueva sección
  *Serie de los ingresos: solo puntos de emisión activos*.
- **1.1** — Corregido el bloqueo al abrir el módulo (y al procesar un extracto) en
  empresas con muchos clientes: la lista de clientes con cartera pendiente se calcula
  ahora en una sola consulta, en vez de una por cada cliente.
- **1.0** — Versión inicial.
