---
titulo: Control bancario
resumen: Clasificación de los movimientos del banco y conciliación con lo registrado en el sistema.
categoria: Tesorería
ruta_modulo: modulos/control-bancario
tipo: modulo
visibilidad: todos
etiquetas: control bancario, conciliacion bancaria, estado de cuenta, banco, cheques, movimientos, cuadrar banco
version: 1.9
orden: 60
estado: activo
---

El **control bancario** sirve para cuadrar lo que dice el banco con lo que dice
el sistema: se cargan los movimientos del estado de cuenta, se clasifican y se
concilian contra los documentos registrados.

## Clasificar un movimiento

Cada movimiento del banco necesita:

| Dato | Regla |
|------|-------|
| Movimiento a clasificar | Obligatorio |
| Cuenta bancaria | Obligatoria |
| Tipo de transacción | Debe ser uno de los válidos |

### Qué se puede editar aquí y qué no

Si el movimiento **viene de un ingreso o un egreso**, sus datos son de ese
documento: el tipo de transacción, el número y la fecha del cheque y la
observación se muestran **como ficha en el encabezado del modal**, junto con la
fecha, el comprobante, la glosa y el monto. Abajo queda un solo campo: la
**Fecha Banco**, que es lo que decide este módulo.

Para corregir cualquiera de esos datos hay que ir al ingreso/egreso; cambiarlos
solo en la conciliación dejaría los dos módulos diciendo cosas distintas del
mismo pago.

Los movimientos que **no** tienen un cobro/pago detrás (asientos manuales o del
diario general) siguen siendo totalmente editables: ahí este módulo es el único
lugar donde se pueden anotar esos datos.

## Cheques

Si el movimiento es un **cheque**, hacen falta dos datos más:

- Si fue **emitido o recibido**.
- El **número de cheque**.

Sin esos dos datos el sistema no deja clasificarlo, porque son los que permiten
cruzarlo con el pago o el cobro correspondiente.

## Marcar un cheque como cobrado

Un cheque se considera **cobrado** cuando se le registra la **Fecha Banco**: el
día en que el banco lo hizo efectivo. Es un dato distinto de la fecha girada en
el cheque (la posfechada), que se captura en el egreso.

En el listado, la columna **Fecha Banco** muestra el estado de cada cheque:

- **✔ DD-MM-AAAA** (verde): cobrado en esa fecha.
- **⏳ No cobrado** (ámbar): sigue en circulación.

Para marcarlo:

1. Haga clic en la fila del cheque; se abre *Clasificar Movimiento*.
2. El modal muestra el estado y, si aún no se cobró, explica qué hacer.
3. Llene **Fecha Banco (conciliación)** con la fecha real del banco y guarde.

Ese dato viaja al egreso: el cheque pasa a verse como "Cobrado" y el sistema ya
no permite cambiarle la fecha ni anularlo (ver [Egresos](egresos.md)). El campo
queda **vacío mientras nadie lo haya conciliado**, para que llenarlo sea siempre
una decisión explícita.

Si el período ya fue marcado como conciliado, primero hay que reabrirlo desde el
historial de conciliaciones; mientras esté cerrado, sus movimientos no se
editan.

## Para qué sirve conciliar

Un movimiento en el banco que no está en el sistema significa que falta registrar
algo: un cobro, un pago, una comisión. Al revés, un pago registrado que no aparece
en el banco puede ser un cheque no cobrado todavía.

La conciliación es lo que convierte el saldo contable en un saldo en el que se
puede confiar.

## Tipo de transacción detectado automáticamente

Si el movimiento no tiene una clasificación manual guardada, el sistema intenta
adivinar el **tipo de transacción** antes de mostrar "Otro":

1. Si el ingreso/egreso que originó la línea ya trae el dato específico
   (depósito, transferencia, cheque, débito) — lo que el usuario elige al
   registrar el cobro/pago con una forma de tipo banco — se usa ese.
2. Si no, pero la línea sí corresponde a un ingreso o egreso real (incluyendo
   los **migrados** del sistema anterior), se usa el tipo de la cuenta bancaria:
   cuenta de tipo cheque → "Cheque"; cuenta bancaria → "Depósito" si el dinero
   **entra** a la cuenta o "Transferencia" si **sale**.
3. Solo queda como **"Otro"** cuando el asiento no tiene ningún ingreso/egreso
   detrás (asientos manuales o del diario general migrado sin esa clasificación).

La detección siempre queda atada a **la cuenta contable de la cuenta bancaria
que se está viendo** (la línea contable debe pertenecer a esa cuenta) y **a la
forma de pago usada en ese ingreso/egreso específico** — pero "la forma de
pago de la cuenta" no significa una sola fila: si esa cuenta bancaria tiene
**más de una forma de pago bancaria** apuntándole (p. ej. "Cheques Pichincha" y
"Transferencias Pichincha" son la misma cuenta física vista por dos formas),
un cobro/pago hecho con CUALQUIERA de esas formas se reconoce igual, sin
importar cuál de las dos se tenga seleccionada en el filtro — nunca se toman
datos de una cuenta contable distinta.

## Selector de cuenta bancaria

El selector lista toda forma de pago con **banco asignado** (activa, no
eliminada), tenga o no **cuenta contable** configurada. Si le falta, aparece
marcada como "— sin cuenta contable", y el módulo funciona igual: ver la
sección siguiente.

## Cuentas sin cuenta contable (empresas que no llevan contabilidad)

Hay empresas que no llevan contabilidad pero sí controlan su cuenta bancaria.
En ellas la cuenta no tiene cuenta contable asignada, así que **no existe un
mayor** del cual sacar el detalle. En ese caso el módulo arma el movimiento
directamente desde los **cobros y pagos** registrados con esa cuenta:

- Cada línea de pago de un **ingreso** es una entrada de dinero; cada línea de
  pago de un **egreso**, una salida.
- Se excluyen los documentos eliminados o anulados y los cheques anulados.
- El saldo del período, los créditos y débitos, el saldo acumulado línea a
  línea, el buscador, los filtros, la exportación a PDF/Excel y la
  conciliación del período funcionan igual que con contabilidad.
- La **clasificación manual** (tipo, Nº de cheque, fechas, observación)
  también funciona: la anotación se guarda contra el cobro/pago en vez de
  contra una línea de asiento.
- Los **cheques posfechados** y los cheques emitidos en circulación de estas
  cuentas también aparecen en sus pestañas.

Al seleccionar una de estas cuentas el módulo lo avisa arriba del listado, para
que quede claro de dónde salen las cifras. Dos diferencias con una cuenta que
sí lleva contabilidad: el número de comprobante es el del ingreso/egreso y no
abre el asiento (no hay), y la segunda hoja del Excel de conciliación se llama
"Movimientos" en vez de "Mayor Contable".

Si más adelante se le asigna la cuenta contable (desde
[Formas de cobro y pago](formas-cobros-pagos.md)), el módulo pasa a mostrar el
mayor contable, sin ninguna acción adicional.

## Errores frecuentes

- **"Para un cheque debe indicar si fue emitido o recibido"**: falta ese dato.
- **"Debe indicar el número de cheque"**: es obligatorio para los movimientos de
  tipo cheque.
- **El saldo del banco no coincide con el contable**: revise los movimientos sin
  clasificar y los cheques girados que aún no se cobraron.
- **Aparecen movimientos de Efectivo/Tarjeta/otra forma no bancaria mezclados
  en esta cuenta**: esa forma de pago quedó configurada con la MISMA cuenta
  contable que este banco (ver [Formas de cobro y pago](formas-cobros-pagos.md)).
  Como este módulo arma el mayor filtrando por cuenta contable, cualquier
  forma que postee ahí aparece, sea o no bancaria. Corrija la cuenta contable
  de esa forma de pago (el sistema ya bloquea que esto vuelva a pasar al
  guardar una forma nueva).
- **Movimientos migrados aparecían todos como "Otro"** (incluso depósitos):
  el enlace a los pagos migrados se buscaba por un dato que los migrados no
  siempre tienen, y las corridas de migración antiguas guardaron el dato de
  origen en mayúsculas en vez de minúsculas, así que la comparación nunca
  hacía match. Corregido; además ahora un movimiento de entrada sin
  clasificar dice "Depósito" en vez de "Transferencia".

## Historial de cambios

- **1.9** — Modal reorganizado: en los movimientos que vienen de un
  ingreso/egreso, el tipo, los datos del cheque y la observación pasan a la
  ficha del encabezado (solo lectura, se corrigen en el documento) y abajo
  queda únicamente la Fecha Banco. Los asientos manuales siguen editándose
  como antes. Además, ahora se puede conciliar un movimiento registrado como
  "Débito".
- **1.8** — La columna Fecha Banco muestra si el cheque está cobrado o sigue en
  circulación, y el modal explica cómo marcarlo. Corregido: el campo "Fecha
  Banco" ya no viene precargado con la fecha del movimiento, así que guardar
  otro cambio no marca por accidente el cheque como cobrado.
- **1.7** — Las cuentas bancarias **sin cuenta contable** ya no se ven vacías:
  el módulo arma su detalle, saldos, cheques, exportaciones y conciliación
  desde los cobros y pagos hechos con esa cuenta, y también permite
  clasificarlos. Pensado para empresas que no llevan contabilidad pero sí
  controlan su banco.
- **1.6** — La cuenta contable ya no es obligatoria para que una cuenta
  bancaria aparezca en el selector; sin ella se ve con un aviso y saldo/
  movimientos en 0 hasta que se le asigne una.
- **1.5** — Corrección: cuando una cuenta bancaria tiene dos o más formas de
  pago bancarias (Banco/Cheque) apuntándole (p. ej. al convertir una forma
  antes no bancaria para que quede junto al banco real), un cobro/pago hecho
  con cualquiera de esas formas ya se reconoce correctamente sin importar cuál
  esté seleccionada como "cuenta bancaria" en el filtro.
- **1.4** — Documentado el caso de una forma de pago no bancaria compartiendo
  cuenta contable con un banco (causa raíz y cómo corregirlo); ver
  [Formas de cobro y pago](formas-cobros-pagos.md).
- **1.3** — Corrección: cuando un ingreso/egreso paga dos veces con la MISMA
  forma de pago (p. ej. dos cheques distintos depositados el mismo día a la
  misma cuenta), el enlace ya no duplica la línea del movimiento en el
  listado ni descuadra el saldo acumulado.
- **1.2** — Corrección: la comparación que enlaza un asiento migrado con su
  ingreso/egreso original ahora ignora mayúsculas/minúsculas (las corridas de
  migración antiguas guardaron ese dato en mayúsculas), así que los migrados
  también reciben el tipo automático. Además, un movimiento bancario de
  entrada sin clasificar ahora dice "Depósito" en vez de "Transferencia".
- **1.1** — Corrección: los movimientos de ingresos/egresos migrados ya no caen
  siempre en "Otro"; heredan el tipo (Transferencia/Cheque) de la cuenta bancaria.
- **1.0** — Versión inicial.
