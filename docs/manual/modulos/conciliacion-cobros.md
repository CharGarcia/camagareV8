---
titulo: Conciliación de cobros
resumen: Cruce entre los cobros registrados y lo que realmente entró al banco.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-cobros
tipo: modulo
visibilidad: todos
etiquetas: conciliacion de cobros, cuadrar cobros, banco, deposito, tarjeta, liquidacion, diferencias, serie, punto de emision, serie inactiva, generar ingresos, extracto bancario, formato del banco, perfil de mapeo, formato de extracto, varias facturas, varios clientes, completar deposito, un deposito varias facturas, cargas anteriores, cobro duplicado, cobrar dos veces, saldo disponible, saldo apartado, movimiento repetido, extracto repetido, doble cobro, factura mas antigua, documento sugerido, orden de cobro, primero en vencer, buscar cliente, autocompletar cliente, un solo ingreso, ingreso varios clientes, no dividir la linea, sin asignar, diferencia de pago parcial
version: 1.7
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

## Qué documento se sugiere: el más antiguo

Al subir el extracto, el sistema identifica al cliente por el texto de la
descripción del banco (nombre o identificación) y propone sus documentos
pendientes **del más antiguo al más reciente**, en el orden en que se cobra la
cartera. **Ni el valor del depósito ni el texto del banco deciden qué factura se
sugiere**: aunque el monto coincida exactamente con una factura reciente, o la
descripción mencione el número de otra factura, se propone siempre la más
antigua. Si el pago corresponde a otro documento, se cambia desde la lupa.

Si el depósito **cubre más de un documento**, la línea del banco **no se
divide**: conserva el monto tal como vino en el archivo y, en la columna
**Documento Sugerido**, lista todos los documentos que lo completan, cada uno
con su monto. Las facturas más antiguas se cubren completas y la última toma lo
que queda. Si el depósito supera toda la cartera del cliente, en **Monto a
Aplicar** se ve el total asignado y cuánto queda **sin asignar**; ese resto se
completa desde la lupa con documentos de otros clientes o, al generar, se
agrega como línea nueva (ver *Diferencia de pago parcial*). Todo es solo una
sugerencia: la línea se confirma con su ✓ o se cambia desde la lupa.

- Cuando el mismo extracto trae **dos depósitos del mismo cliente**, el segundo
  se sugiere sobre los documentos que el primero no cubrió, no sobre la misma
  factura dos veces.
- Con un solo documento, el **Monto a Aplicar** se puede corregir en la misma
  fila antes de confirmar. Con varios documentos, los montos se corrigen desde
  la lupa.

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
clientes distintos, se completa desde la lupa de la línea. **La línea del banco
nunca se divide**: sigue siendo un solo movimiento con el monto del extracto,
al que se le asignan uno o varios documentos.

1. Pulse la **lupa** de la línea. Arriba se ven el monto **Recibido**, lo
   **Asignado** y lo **Restante**.
2. En **Cliente**, **escriba** parte del nombre o la identificación y elija el
   cliente en la lista que aparece (busca por varias palabras en cualquier orden
   y sin importar tildes; solo ofrece clientes con documentos pendientes de
   cobro). Con un cliente ya elegido, **Retroceso** o **Suprimir** lo quita de
   una vez para buscar otro. Luego **marque** sus documentos (clic en la fila o
   en su casilla). Puede buscar otro cliente y seguir marcando: lo marcado se
   conserva en **Documentos seleccionados**.
3. Al marcar un documento se propone el menor entre su saldo pendiente y lo que
   falta por asignar. El **Monto a Aplicar** se puede corregir; no puede superar
   el saldo del documento ni lo que queda del depósito.
4. Pulse **Confirmar con N documentos**. Si marcó varios documentos o dejó
   parte del monto sin asignar, el sistema resume lo que va a hacer y pide
   confirmar.

La línea queda **confirmada** con todos los documentos marcados (en la grilla
se listan en **Documento Sugerido**, y **Cliente Sugerido** muestra cuántos
clientes son). Si lo asignado es menor a lo recibido, la diferencia se muestra
como **sin asignar** y, al generar, se agrega como línea nueva.

### Un solo ingreso por depósito

Al pulsar **Generar ingresos de las líneas confirmadas**, cada línea confirmada
se cobra en **un solo ingreso**, con todos sus documentos en el detalle y **un
solo pago** por el total asignado, con la referencia del banco. Así el cobro
coincide con el depósito, **aunque los documentos sean de clientes distintos**.

- **Depósito de un solo cliente**: el ingreso va a nombre de ese cliente. Por
  ejemplo, un depósito de $51,50 que paga una factura de $11,50 y un saldo
  inicial de $40 genera un ingreso con esos dos documentos y un pago de $51,50.
- **Depósito de varios clientes**: un único ingreso igual. La cabecera queda
  **sin cliente** (como un cobro de varios clientes registrado a mano en
  Ingresos) y **Recibo de** lista los nombres de todos. En el detalle, cada
  documento muestra su cliente; en la cuenta por cobrar y en el estado de
  cuenta de cada cliente el cobro aparece solo por sus documentos, y en el
  asiento contable cada línea de cartera lleva el tercero que corresponde.
- Si se anula ese ingreso, la línea queda disponible para reactivarla y volver a
  generarlo.

### Diferencia de pago parcial

Si al generar lo recibido en el banco es mayor a lo asignado a los documentos,
la diferencia se agrega como una **línea nueva** en la misma carga, con la nota
*(diferencia de pago parcial)* y, si el cliente de la línea tiene más documentos
pendientes, con el más antiguo sugerido. Esa línea se concilia como cualquier
otra (otro cliente, ignorarla, etc.). Para que un depósito salga completo en un
solo ingreso, asigne todo el monto antes de generar.

## Observaciones del ingreso generado

Cada ingreso que genera la conciliación llena sus **Observaciones** con el
mismo texto que arma el módulo de Ingresos al registrar un cobro a mano, y
luego agrega los datos del extracto para rastrear el movimiento. Por ejemplo:

> Cobro factura de venta 501; Cobrado con BANCO PICHINCHA $30.00 (transferencia
> ref. 4455). Cobro conciliado desde extracto bancario (BANCO PICHINCHA).
> Descripción banco: … Referencia/documento banco: 4455. Fecha movimiento
> banco: 20-09-2026.

- Con **un solo** documento marcado, la lupa también confirma la línea de una
  vez (**Confirmar con este documento**).
- Una línea confirmada por error se puede quitar (↺) o ignorar (✗) como
  cualquier otra; al quitar la confirmación conserva los documentos elegidos
  para corregirlos desde la lupa.

## Cómo se evita cobrar dos veces lo mismo

Cada confirmación se valida contra el **saldo de la cuenta por cobrar** del
documento en ese momento, y el sistema impide cobrar dos veces por tres caminos:

- **Varias líneas contra el mismo documento.** Lo que una línea confirmada
  (aún sin ingreso generado) aplica a un documento queda **apartado**. Otra línea
  solo puede usar el **saldo disponible**: saldo de la cuenta por cobrar menos lo
  apartado. En la lupa, la columna **Saldo Disponible** ya lo descuenta e indica
  cuánto está apartado en otras líneas; un documento sin saldo disponible no
  aparece. Aplica también entre cargas distintas.
- **Generar dos veces a la vez.** Si alguien pulsa **Generar ingresos** mientras
  otra persona (u otra ventana) genera la misma carga, se rechaza con el aviso
  *Los ingresos de esta carga ya se están generando*. Cada línea se revisa otra
  vez justo antes de cobrarla: si ya se cobró o cambió, no se vuelve a cobrar.
- **El mismo movimiento en dos extractos.** Al subir un extracto que se solapa
  en fechas con uno anterior de la misma cuenta, los movimientos que ya estaban
  (misma fecha, referencia, descripción y monto) entran como **REPETIDO**
  (ignorados). Al pasar el mouse sobre la etiqueta se ve en qué carga estaban. Si
  en verdad es otro depósito idéntico, reactívelo con ↺.

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

- **"El monto a aplicar supera el saldo disponible del documento"** o **"ya
  tiene todo su saldo apartado por otras líneas confirmadas"**: otra línea
  confirmada ya aplica ese saldo. Revise las líneas confirmadas (también en otras
  cargas); si una está mal, quite su confirmación (↺).
- **Una línea aparece como REPETIDO al subir el extracto**: ese movimiento ya
  estaba en una carga anterior de la misma cuenta. No hace falta hacer nada; si
  es otro depósito idéntico, reactívelo.
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
- **1.7** — **La línea del banco ya no se divide.** Un depósito es siempre una
  sola línea con el monto del extracto; los documentos que lo completan (uno o
  varios, de uno o **varios clientes**) se listan en **Documento Sugerido** y se
  eligen desde la lupa, cuyo botón pasa a **Confirmar con N documentos** (ya no
  hace falta el ✓ después). Al generar, cada línea confirmada crea **un solo
  ingreso** con todos sus documentos y un solo pago, también cuando son de
  clientes distintos (cabecera sin cliente, **Recibo de** con todos los
  nombres, tercero por documento en el asiento). Desaparecen las *partes
  (1/3 del depósito…)* y la pregunta **Confirmar las N / Solo esta**. Nueva
  sección *Diferencia de pago parcial*.
- **1.6** — El **Documento Sugerido** ya no se elige por el valor del depósito:
  se propone el documento pendiente **más antiguo** del cliente (nueva sección
  *Qué documento se sugiere: el más antiguo*), y si el depósito cubre varios, la
  línea llega **repartida en partes** que, al confirmarlas (el sistema ofrece
  **Confirmar las N** de una vez), se cobran en **un solo ingreso** con todos
  los documentos y un solo pago. La diferencia de un pago parcial también se
  sugiere por antigüedad. En la lupa, el campo **Cliente** pasa de una lista
  desplegable con todos los clientes a un **buscador**: se escribe el nombre o
  la identificación y se elige de las coincidencias.
- **1.5** — No se puede cobrar dos veces lo mismo: cada confirmación se valida  contra el saldo de la cuenta por cobrar menos lo ya apartado por otras líneas  confirmadas (columna **Saldo Disponible** en la lupa), **Generar ingresos** no  corre dos veces a la vez sobre la misma carga, y los movimientos que ya estaban  en otro extracto de la misma cuenta entran como **REPETIDO**. Nueva sección  *Cómo se evita cobrar dos veces lo mismo*.
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
