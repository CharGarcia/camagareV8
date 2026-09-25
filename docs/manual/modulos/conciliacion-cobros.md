---
titulo: Conciliación de cobros
resumen: Cruce entre los cobros registrados y lo que realmente entró al banco.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-cobros
tipo: modulo
visibilidad: todos
etiquetas: conciliacion de cobros, cuadrar cobros, banco, deposito, tarjeta, liquidacion, diferencias, serie, punto de emision, serie inactiva, generar ingresos, extracto bancario, formato del banco, perfil de mapeo, formato de extracto, varias facturas, varios clientes, repartir deposito, dividir linea, un deposito varias facturas, cargas anteriores
version: 1.4
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

Al subir el extracto (**Subir y Conciliar**) se elige la **Serie** (el punto
de emisión): la serie con la que se numeran los ingresos que genera la carga. La lista muestra únicamente los puntos de emisión **activos**; los
inactivos no aparecen.

- Para usar una serie que no aparece, actívela en **Empresa**, pestaña
  **Puntos de Emisión**.
- Si la empresa no tiene ningún punto activo, la lista muestra *Sin series
  activas* y no se puede subir el extracto.
- La serie se valida otra vez al pulsar **Generar ingresos de las líneas
  confirmadas**. Si se inactivó después de subir la carga, no se genera ningún
  ingreso y aparece el aviso *La serie (punto de emisión) de esta carga ya no es
  válida o está inactiva*: vuelva a activarla para continuar con esa carga.

## Un depósito que paga varias facturas o varios clientes

Si una sola línea del banco cubre varias facturas, del mismo cliente o de
clientes distintos, se reparte desde la lupa de la línea:

1. Pulse la **lupa** de la línea. Arriba se ven el monto **Recibido**, lo
   **Asignado** y lo **Restante**.
2. Elija un cliente y **marque** sus documentos (clic en la fila o en su casilla). Puede cambiar a otro cliente y
   seguir marcando: lo marcado se conserva en **Documentos seleccionados**.
3. Al marcar un documento se propone el menor entre su saldo pendiente y lo que
   falta por asignar. El **Monto a Aplicar** se puede corregir; no puede superar
   el saldo del documento ni lo que queda del depósito.
4. Pulse **Aplicar a N documentos** y confirme.

La línea se divide en una línea por documento, ya **confirmadas**, con la
descripción del banco y la nota *(parte 1/3 del depósito de $…)*. Si lo
asignado es menor a lo recibido, se agrega otra línea con el **saldo sin
asignar** para seguir conciliándola.

### Un solo ingreso por depósito y cliente

Al pulsar **Generar ingresos de las líneas confirmadas**, las partes del mismo
depósito se cobran **juntas**: se crea **un solo ingreso por cliente**, con todos
sus documentos en el detalle y **un solo pago** por el total, con la referencia
del banco. Así el cobro coincide con el depósito.

- **Depósito de un solo cliente**: un único ingreso. Por ejemplo, un depósito
  de $51,50 que paga una factura de $11,50 y un saldo inicial de $40 genera un
  ingreso con esos dos documentos y un pago de $51,50.
- **Depósito de varios clientes**: un ingreso por cliente, porque cada ingreso
  pertenece a un solo cliente (su cartera y su asiento van a su nombre). La suma
  de los pagos de esos ingresos es igual al depósito.
- Si una parte se desconfirma y se genera después, sale en un ingreso aparte.
- Si se anula ese ingreso, todas sus partes quedan disponibles para reactivarlas
  y volver a generarlo.

## Observaciones del ingreso generado

Cada ingreso que genera la conciliación llena sus **Observaciones** con el
mismo texto que arma el módulo de Ingresos al registrar un cobro a mano, y
luego agrega los datos del extracto para rastrear el movimiento. Por ejemplo:

> Cobro factura de venta 501; Cobrado con BANCO PICHINCHA $30.00 (transferencia
> ref. 4455). Cobro conciliado desde extracto bancario (BANCO PICHINCHA).
> Descripción banco: … Referencia/documento banco: 4455. Fecha movimiento
> banco: 20-09-2026.

- Con **un solo** documento marcado, la lupa funciona como siempre: asigna el
  documento a la línea y se confirma con el botón ✓.
- Una parte confirmada por error se puede quitar (↺) o ignorar (✗) como
  cualquier otra línea.

## Cargas anteriores

La tabla **Cargas anteriores** lista los extractos ya subidos. Haga **clic en
cualquier fila** para ver sus líneas en el paso 2; la carga abierta queda
resaltada y su nombre aparece junto al título del paso 2. Si las líneas no se
pueden cargar (por ejemplo, porque la sesión venció), se muestra el motivo.

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
- **1.4** — La lupa de una línea permite marcar **varios documentos, de uno o
  varios clientes**, y repartir el depósito entre ellos (nueva sección *Un
  depósito que paga varias facturas o varios clientes*). En **Cargas
  anteriores** se quita el botón **Ver**: se abre con clic en la fila, y ya no
  desaparecen las cargas cuya cuenta bancaria fue eliminada. El selector
  **Punto de Emisión** se llama ahora **Serie**, y todos los campos de la carga
  y el botón **Subir y Conciliar** quedan en una sola fila. Las observaciones de
  los ingresos generados empiezan ahora con el mismo texto del módulo de
  Ingresos (*Cobro factura de venta …; Cobrado con …*). Las partes de un depósito
  repartido se cobran en **un solo ingreso por cliente con un solo pago**
  (sección *Un solo ingreso por depósito y cliente*). Corregido además: los
  ingresos generados desde la conciliación no creaban su asiento contable.


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
