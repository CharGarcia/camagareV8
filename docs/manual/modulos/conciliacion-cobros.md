---
titulo: Conciliación de cobros
resumen: Cruce entre los cobros registrados y lo que realmente entró al banco.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-cobros
tipo: modulo
visibilidad: todos
etiquetas: conciliacion de cobros, cuadrar cobros, banco, deposito, tarjeta, liquidacion, diferencias, serie, punto de emision, serie inactiva, generar ingresos, extracto bancario, formato del banco, perfil de mapeo, formato de extracto
version: 1.3
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

## Formato del banco: cómo se lee el extracto

Al subir el extracto se elige también el **Formato del Banco**: indica en qué
columnas (Excel/CSV) o en qué líneas (PDF) vienen la fecha, la descripción y el
monto de cada movimiento.

- Los formatos **no se crean en este módulo**. Los configura el
  superadministrador (nivel 3) en **Configuración › Perfiles de mapeo de cobros**
  (`config/conciliacion-perfiles`) y sirven para todas las empresas.
- Al elegir la **Cuenta Bancaria**, la lista muestra los formatos de ese banco y
  los genéricos; si el banco no tiene ninguno propio, muestra todos. Si solo hay
  uno, se selecciona solo.
- Si no hay ningún formato activo, la lista queda vacía y no se puede subir el
  extracto: el superadministrador debe configurarlo.

## Errores frecuentes

- **Todo queda sin cruzar**: revise el rango de fechas y la cuenta bancaria
  seleccionada.
- **Las tarjetas nunca cuadran exactamente**: es esperable; la diferencia es la
  comisión y debe registrarse.
- **Un punto de emisión no aparece al subir el extracto**: está **inactivo**;
  actívelo en Empresa, pestaña Puntos de Emisión.

- **No aparece el formato de mi banco** o **El formato del banco seleccionado no
  existe o está inactivo**: el formato no está configurado o fue desactivado;
  lo gestiona el superadministrador en Configuración › Perfiles de mapeo de
  cobros.

## Historial de cambios

- **1.3** — Se quita el botón **Perfiles de Mapeo** del módulo: los formatos de
  extracto pasan a un catálogo global que configura el nivel 3 en
  **Configuración › Perfiles de mapeo de cobros**. El selector se llama ahora
  **Formato del Banco** y se filtra según el banco de la cuenta elegida. Los
  perfiles que ya existían quedan disponibles para todas las empresas. Nueva
  sección *Formato del banco: cómo se lee el extracto*.

- **1.2** — El selector **Punto de Emisión (para los Ingresos)** ya no ofrece
  puntos **inactivos**, y la serie se vuelve a validar al generar los ingresos:
  una carga cuya serie se inactivó no emite ingresos en ella. Nueva sección
  *Serie de los ingresos: solo puntos de emisión activos*.
- **1.1** — Corregido el bloqueo al abrir el módulo (y al procesar un extracto) en
  empresas con muchos clientes: la lista de clientes con cartera pendiente se calcula
  ahora en una sola consulta, en vez de una por cada cliente.
- **1.0** — Versión inicial.
