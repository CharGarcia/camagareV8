---
titulo: Control bancario
resumen: Clasificación de los movimientos del banco y conciliación con lo registrado en el sistema.
categoria: Tesorería
ruta_modulo: modulos/control-bancario
tipo: modulo
visibilidad: todos
etiquetas: control bancario, conciliacion bancaria, estado de cuenta, banco, cheques, movimientos, cuadrar banco, buscar movimiento, buscador, filtros, filtrar movimientos bancarios, buscar cheque, chips, cheques posfechados, cheque por cobrar, cheque por depositar, aviso de cheques, alerta, notificacion, vencimiento de cheques, comprobar con contabilidad, cuadrar con contabilidad, saldo contable vs banco, diferencia contable, asiento faltante, sin asiento
version: 1.16
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

### Beneficiario / Cliente

La columna **Beneficiario / Cliente** del listado (y la misma línea en el
encabezado del modal al hacer clic en un movimiento) muestra quién está detrás
de cada movimiento, sea cual sea su tipo:

- En un **ingreso** (depósito, transferencia o cheque recibido): el cliente que
  pagó. Si el ingreso se registró sin cliente de catálogo, se muestra el nombre
  escrito en "Recibí de".
- En un **egreso**: el beneficiario escrito en el cheque; si no lo hay, el
  proveedor o el empleado al que se le pagó, o el beneficiario libre del egreso.

En el modal, la etiqueta dice **Cliente**, **Proveedor** o **Empleado** según el
tipo de tercero del movimiento.

### Ingresos y egresos anulados no aparecen

Un ingreso o egreso **anulado** (o eliminado) no se muestra en el listado, no
entra en el saldo acumulado ni en el resumen del período, y tampoco aparece
entre los cheques posfechados o en circulación. El módulo mira el estado del documento de origen.

### Qué se puede editar aquí y qué no

Si el movimiento **viene de un ingreso o un egreso**, sus datos son de ese
documento: el tipo de transacción, el número y la fecha del cheque y la
observación se muestran **como ficha en el encabezado del modal**, junto con la
fecha, el comprobante, la glosa y el monto. Abajo queda un solo campo: la
**Fecha Banco**, que es lo que decide este módulo.

Para corregir cualquiera de esos datos hay que ir al ingreso/egreso; cambiarlos
solo en la conciliación dejaría los dos módulos diciendo cosas distintas del
mismo pago.

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

### Un cheque solo descuenta cuando se cobra

Girar un cheque no saca la plata de la cuenta: el banco la descuenta el día que
lo hace efectivo. El módulo trabaja con ese criterio.

- Un cheque **sin Fecha Banco no mueve el saldo**: aparece en el listado (para
  poder marcarlo), pero no entra en los créditos/débitos del período ni cambia
  el saldo acumulado, que se muestra atenuado en esa fila.
- Al registrarle la Fecha Banco, el cheque **pasa a pesar en el período de esa
  fecha**, no en el de su emisión. Un cheque girado en marzo y cobrado en mayo
  se ve y descuenta en mayo.
- Vale igual para los **cheques recibidos** de clientes: suman al saldo cuando
  se acreditan, no cuando se reciben.

Los demás movimientos (transferencias, depósitos, débitos) no cambian: cuentan
con la fecha del documento, como siempre.

Como consecuencia, el saldo de este módulo refleja el **extracto del banco**, y
puede diferir del saldo contable de la cuenta mientras haya cheques girados sin
cobrar. Esa diferencia es exactamente el listado *Cheques girados pendientes de
cobro* del reporte de conciliación.

### Filtro "Cheques"

En la barra de filtros, el selector **Cheques** deja ver de una sola vez los que
importan:

| Opción | Qué muestra |
|--------|-------------|
| Todos | Sin filtrar (todos los movimientos, sean cheque o no) |
| No cobrados | Cheques sin Fecha Banco: los que siguen en circulación y no descuentan |
| Cobrados | Cheques que el banco ya hizo efectivos |
| Posfechados | Cheques girados con fecha futura, se hayan cobrado o no |

Se combina con los demás filtros (flujo, tipo, período y buscador), y lo que se
exporta a PDF y Excel es exactamente lo que quedó en pantalla.

Si el período ya fue marcado como conciliado, primero hay que reabrirlo desde el
historial de conciliaciones; mientras esté cerrado, sus movimientos no se
editan.

## Ventana "Cheques Posfechados"

El botón **Cheques Posfechados** (arriba, junto al título) abre una ventana con
los cheques de **todas las cuentas bancarias** de la empresa, en tres pestañas:
**Recibidos** (de clientes), **Emitidos** (a proveedores) y **Emitidos a
Empleados**. Muestra:

- Los cheques con **fecha futura**, todos.
- **Todos** los cheques **posfechados cuya fecha ya llegó** y que todavía **no
  tienen Fecha Banco**, sin importar hace cuánto. Son los que hay que depositar
  (si son recibidos) o los que el beneficiario ya puede cobrar (si son emitidos).

Junto a la fecha, cada cheque lleva una etiqueta:

| Etiqueta | Significado |
|----------|-------------|
| **Por cobrar** (rojo) | La fecha del cheque ya llegó y no tiene Fecha Banco |
| **Vence en N días** (ámbar) | La fecha cae dentro de los próximos 5 días |
| Sin etiqueta | Fecha más lejana |

Un cheque sale de la ventana en cuanto se le registra la **Fecha Banco** (ver
*Marcar un cheque como cobrado*). "Posfechado" quiere decir que la fecha del
cheque es posterior a la del ingreso/egreso: un cheque al día no aparece aquí.

Los cheques **migrados del sistema anterior** se tratan aparte: si su fecha es
futura se siguen listando, pero **sin etiqueta**; si su fecha ya pasó y no tienen
Fecha Banco, **no se muestran**. Las etiquetas y el aviso son solo para los
cheques registrados en este sistema.

## Aviso de cheques posfechados en la barra superior

En la barra superior del sistema aparece un ícono de **billete con monedas**
cuando hay cheques posfechados pendientes. El número es la cantidad total de
cheques y el color indica la urgencia:

- **Rojo**: hay al menos un cheque con la fecha ya cumplida y sin Fecha Banco.
- **Ámbar**: solo hay cheques que vencen en los próximos 5 días.

Al hacer clic se despliega el detalle, con la cantidad y el monto de cada grupo:

| Línea | Qué cuenta |
|-------|------------|
| Recibidos: listos para depositar | Cheques de clientes con fecha cumplida sin Fecha Banco |
| Recibidos: por vencer | Cheques de clientes con fecha en los próximos 5 días |
| Emitidos: ya se pueden cobrar | Cheques girados con fecha cumplida sin Fecha Banco |
| Emitidos: por vencer | Cheques girados con fecha en los próximos 5 días (conviene tener fondos) |

Cada línea abre esta pantalla con la ventana **Cheques Posfechados** ya
desplegada en su pestaña. En el celular, el aviso aparece en el menú lateral
como dos accesos: **Ch. recib.** y **Ch. emit.**

Detalles:

- Solo lo ven los usuarios con permiso para **ver** Control Bancario en la
  empresa activa.
- Se actualiza solo, sin recargar la pantalla, y al instante tras guardar un
  ingreso, un egreso o una Fecha Banco.
- Solo cuenta cheques de **cuentas bancarias** (formas de pago con banco) cuyo
  ingreso/egreso no esté anulado ni eliminado. Un cheque de egreso **anulado**
  no cuenta.
- Solo avisa de cheques **registrados en este sistema**. Los que vienen de
  ingresos o egresos **migrados** del sistema anterior no cuentan, estén
  vencidos o por vencer.
- Un cheque vencido sigue avisándose **hasta que se le registre la Fecha
  Banco**, por antiguo que sea.

## Fecha de cobro de transferencias, depósitos y débitos

Al guardar un ingreso o un egreso con una **cuenta bancaria**, la fecha de cobro
de las **transferencias, depósitos y débitos** es automáticamente la **fecha de
emisión** del documento: esos movimientos se hacen efectivos el mismo día y no
quedan pendientes de nada. Si se cambia la fecha del documento, la fecha de cobro
lo acompaña.

Solo el **cheque** conserva su propia fecha (la que lleva girada) y queda
**pendiente** hasta que se registre su **Fecha Banco** en esta pantalla.

Por lo mismo, al abrir una transferencia, depósito o débito en la ventana
**Clasificar Movimiento**, el campo *Fecha Banco* ya viene con la fecha del
documento (o con la que se haya registrado a mano). En un cheque viene vacío
hasta que se confirme su cobro.

## Cómo está organizada la pantalla

Arriba hay una sola **tarjeta de control** que queda fija bajo la barra superior
mientras se baja por la tabla:

- **Encabezado**: el título, la etiqueta *Período conciliado* (si aplica) y los
  botones **Cheques Posfechados**, **Comprobar con Contabilidad**, **Historial** de
  conciliaciones y **Conciliar Período**. En pantallas medianas los tres primeros
  se ven solo con su ícono; al pasar el mouse se lee su nombre.
- **Filtros**: cuenta bancaria, flujo, tipo, cheques, año, mes y fechas, con el
  botón **Mostrar**. Al abrir la pantalla viene seleccionado el **mes actual**;
  para ver el año completo, elija *Todos* en Mes.
- **Resumen del período**, en una línea: saldo inicial, créditos (entradas),
  débitos (salidas) y saldo final.

Debajo, la tabla de movimientos muestra **todos** los movimientos del período,
sin páginas ni alto máximo: se lee hacia abajo y se recorre con el scroll de la
página. Arriba de la tabla, a la derecha, se ve cuántos movimientos hay. Si el
período es muy largo y la tabla tarda en cargar, acote las fechas.

## Buscar y filtrar el listado

Los **filtros de la tarjeta de arriba** (cuenta bancaria, flujo, tipo, cheques, año,
mes y fechas) definen **qué cuenta y qué período** se revisan, y con eso se calculan
el saldo inicial, los créditos, los débitos y el saldo final. El buscador de la
tabla afina **dentro** de esos movimientos, sin cambiar el resumen del período.

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel (los de **Conciliación** quedan
aparte, a la derecha).

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del movimiento: fecha,
fecha banco, comprobante, número y fecha del cheque, beneficiario / cliente,
documento de referencia, tercero, glosa (o el concepto del documento), debe, haber y
**saldo**, y además en la observación registrada al clasificarlo. La columna
**Tipo** y la dirección del cheque (recibido / emitido) no entran en la búsqueda
libre: para filtrar por ellas use la ventana de filtros o el selector *Tipo* de la
tarjeta. Puede escribir varias palabras en cualquier orden y no importan
mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en el cuadro.
Mientras busca, aparece un **círculo girando** al final del cuadro y la tabla se ve
atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con los criterios. Llene los
que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La ventana solo
se cierra con la X, Cancelar, Aplicar o Limpiar filtros. No tiene pestaña
*Detalles*: un movimiento bancario no tiene líneas internas.

| Bloque | Filtros |
|--------|---------|
| Movimiento | Fecha del movimiento (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), fecha banco, fecha del cheque, tipo (depósito, transferencia, cheque, débito, nota de débito, nota de crédito, tarjeta, Payphone, otro), dirección del cheque (recibido / emitido), comprobante, N° de cheque, documento de referencia, con o sin clasificación manual |
| Valores | Debe, haber y saldo (cada uno con mínimo y máximo) |
| Tercero | Tercero, beneficiario / cliente, concepto, glosa, observación |

Las fechas de la ventana recortan **dentro** del período de la tarjeta: si elige un
rango fuera de ese período, la tabla queda vacía.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Para qué sirve conciliar

Un movimiento en el banco que no está en el sistema significa que falta registrar
algo: un cobro, un pago, una comisión. Al revés, un pago registrado que no aparece
en el banco puede ser un cheque no cobrado todavía.

La conciliación es lo que convierte el saldo contable en un saldo en el que se
puede confiar. El período se concilia con los cobros y pagos de **Ingresos y
Egresos** (ver *De dónde salen los movimientos*).

## Comprobar con la contabilidad

El botón **Comprobar con Contabilidad** (arriba) compara, para la cuenta y el
período seleccionados, lo registrado en Ingresos/Egresos con la **cuenta
contable** del banco. No modifica nada: es solo una revisión.

Muestra tres líneas, cada una con su diferencia:

| Línea | Qué compara |
|-------|-------------|
| Saldo al inicio del período | Todo lo anterior a la fecha de inicio |
| Movimiento del período | Lo registrado dentro del rango |
| Saldo al final del período | Todo hasta la fecha de fin |

"Según Ingresos/Egresos" es el **saldo en libros**: saldo inicial de
[Saldos iniciales](saldos-iniciales.md) más todos los cobros y pagos, con los
cheques desde que se emiten (así los registra la contabilidad). Puede diferir del
saldo del listado, que solo cuenta un cheque cuando tiene Fecha Banco.

### Buscar dónde se descuadra (saldo por saldo)

Debajo está la tabla **Movimientos del período, saldo por saldo**, que se lee como
un mayor de los dos lados a la vez:

1. La primera fila es el **saldo al inicio del período**, según Ingresos/Egresos y
   según contabilidad, con su diferencia.
2. Después viene **cada movimiento del período** (los que cuadran y los que no),
   en orden de fecha. Cada fila muestra el saldo acumulado de cada lado y la
   **diferencia acumulada**.
3. La última fila es el **saldo al final del período**.

La fila donde **cambia la diferencia acumulada** es la que descuadra: queda marcada
con una raya roja a la izquierda y, bajo la diferencia acumulada, lo que esa fila
agrega. Un monto **tachado** tiene su fecha fuera del período, así que no suma en
ese lado.

El interruptor **Ver solo las filas con diferencia** oculta los movimientos que
cuadran; los saldos acumulados siguen contando todos.

Si el período ya **empieza descuadrado**, la diferencia viene de antes de la fecha
de inicio. Para encontrar la fila que la causa, ponga como fecha de inicio el
comienzo de las operaciones y vuelva a comprobar. La tabla muestra hasta 3000
movimientos; si hay más, acote el período.

| Situación | Qué significa |
|-----------|---------------|
| Cuadra | El documento y su asiento mueven lo mismo en el período |
| Sin asiento contable | El ingreso/egreso no tiene asiento contabilizado en la cuenta del banco |
| Solo en contabilidad | Asiento sin ingreso/egreso detrás (manual, migrado, apertura) |
| Asiento de documento anulado | El ingreso/egreso está anulado, pero su asiento sigue contabilizado |
| Cobrado/pagado con otra cuenta | El asiento toca esta cuenta contable, pero el documento usó otra forma de pago |
| Monto distinto | El documento y su asiento mueven montos distintos en el banco |
| Fecha en otro período | El documento y su asiento tienen fechas en períodos distintos |

El número del asiento abre su detalle. La diferencia al **inicio** viene de
períodos anteriores (por ejemplo, la apertura migrada): para ver sus partidas,
compruebe un período anterior.

Si la cuenta contable la usan **varias cuentas bancarias**, se comparan todas
juntas (la contabilidad no las distingue) y la ventana lo avisa. Una cuenta sin
cuenta contable no tiene contra qué compararse. En la vista **Consolidar por
RUC** la comprobación no está disponible: se hace cuenta por cuenta.

Al **marcar un período como conciliado**, la ventana muestra también el saldo
según contabilidad y si cuadra, con un enlace a este detalle.

## De dónde salen los movimientos

El detalle de **cada cuenta bancaria** se arma con los **cobros y pagos
registrados en Ingresos y Egresos** con esa cuenta. No depende de los asientos
contables:

- Cada línea de pago de un **ingreso** es una entrada de dinero; cada línea de
  pago de un **egreso**, una salida. Esto incluye los ingresos y egresos que
  generan otros módulos (recibos, facturas, POS, roles de pago, liquidaciones).
- La **fecha** de cada fila es la fecha de emisión del ingreso/egreso y el
  **comprobante** es su número, no los del asiento.
- Se excluyen los documentos eliminados o anulados y los cheques anulados.
- Cada cuenta muestra **solo sus propios cobros y pagos**, aunque varias cuentas
  bancarias compartan la misma cuenta contable.
- El saldo del período, los créditos y débitos, el saldo acumulado línea a
  línea, el buscador, los filtros, la exportación a PDF/Excel, los cheques
  posfechados y la conciliación del período salen todos de esa misma fuente.
- Lo que se registró **solo como asiento** (asientos manuales, el diario o la
  apertura migrados del sistema anterior) **no aparece** aquí. El saldo con que
  arranca la cuenta se registra en [Saldos iniciales](saldos-iniciales.md).

## Tipo de transacción

El tipo de cada movimiento es el que se eligió al registrar el cobro/pago
(depósito, transferencia, cheque, débito). Si el cobro/pago no lo trae (por
ejemplo, los **migrados** del sistema anterior), se usa el tipo de la forma de
pago: cuenta de tipo cheque → "Cheque"; cuenta bancaria → "Depósito" si el
dinero **entra** o "Transferencia" si **sale**.

## Selector de cuenta bancaria

El selector lista toda forma de pago con **banco asignado** (activa, no
eliminada), tenga o no cuenta contable configurada: el módulo funciona igual en
los dos casos.

## Errores frecuentes

- **"Para un cheque debe indicar si fue emitido o recibido"**: falta ese dato.
- **"Debe indicar el número de cheque"**: es obligatorio para los movimientos de
  tipo cheque.
- **El saldo del banco no coincide con el contable**: revise los movimientos sin
  clasificar y los cheques girados que aún no se cobraron.
- **Falta un movimiento que está en contabilidad**: este módulo solo muestra
  lo registrado en Ingresos y Egresos. Un asiento manual no aparece; registre el
  movimiento como ingreso o egreso con la cuenta bancaria.
- **Movimientos migrados aparecían todos como "Otro"** (incluso depósitos):
  el enlace a los pagos migrados se buscaba por un dato que los migrados no
  siempre tienen, y las corridas de migración antiguas guardaron el dato de
  origen en mayúsculas en vez de minúsculas, así que la comparación nunca
  hacía match. Corregido; además ahora un movimiento de entrada sin
  clasificar dice "Depósito" en vez de "Transferencia".

## Historial de cambios

- **1.16** — Pantalla reorganizada: título, botones, filtros y resumen del
  período van en una sola **tarjeta de control** fija arriba (el resumen pasa de
  cuatro tarjetas grandes a una línea), y la tabla de movimientos deja de paginar (muestra todo el período; al abrir, el
  mes actual) y se extiende
  hacia abajo sin scroll propio. El botón *Marcar Período como Conciliado* pasa a
  llamarse **Conciliar Período**. En *Comprobar con Contabilidad*, la lista de
  partidas se convierte en un mayor **saldo por saldo**: arranca en el saldo al
  inicio, muestra todos los movimientos del período con el saldo acumulado de
  cada lado y la diferencia acumulada, y marca la fila donde se descuadra (con
  opción de ver solo esas filas). Las tablas usan el estilo de los demás
  listados.
- **1.15** — El módulo deja de depender de los asientos contables: el detalle,
  el saldo, los cheques y la conciliación de **todas** las cuentas salen de los
  cobros y pagos de Ingresos y Egresos, con su fecha y su número. Cada cuenta
  muestra solo lo suyo aunque varias compartan la cuenta contable. En el detalle
  del movimiento, "Fecha asiento" pasa a ser **Fecha**. Nuevo botón **Comprobar
  con Contabilidad**: compara el saldo según Ingresos/Egresos con el de la cuenta
  contable y lista, documento por documento, las partidas que explican la
  diferencia; la ventana de conciliar muestra si cuadra.
- **1.14** — La ventana *Cheques Posfechados* y el aviso de la barra superior
  muestran **todos** los cheques posfechados vencidos sin Fecha Banco (antes, solo
  los de los últimos 15 días). Las transferencias, depósitos y débitos de cuentas
  bancarias toman como fecha de cobro la fecha de emisión del ingreso/egreso, y en
  *Clasificar Movimiento* su Fecha Banco ya viene llena; solo el cheque queda
  pendiente de su Fecha Banco.
- **1.13** — Nuevo **aviso de cheques posfechados** en la barra superior (recibidos
  y emitidos, con fecha cumplida sin Fecha Banco o por vencer en 5 días); cada línea
  abre la ventana *Cheques Posfechados* en su pestaña. Esa ventana ahora muestra
  también los posfechados cuya fecha llegó en los últimos 15 días y siguen sin
  cobrar, con las etiquetas **Por cobrar** y **Vence en N días**. Los cheques
  migrados del sistema anterior no generan aviso ni etiqueta.
- **1.12** — Nuevo buscador de la tabla: el cuadro ya no despliega sugerencias; lo que
  se escribe se busca en todas las columnas del movimiento (incluidos fechas, debe,
  haber, saldo y beneficiario) y en la observación, salvo Tipo y dirección del cheque.
  Los filtros pasan a una **ventana propia** (botón del embudo, se aplican con
  *Aplicar*) con criterios nuevos: fecha del movimiento con atajos, fecha del cheque,
  comprobante, beneficiario / cliente, con/sin clasificación manual, saldo, glosa y
  observación; el tipo lista también débito, tarjeta y Payphone, y elegir *Débito* ya no
  trae las notas de débito. Los filtros activos se ven como etiquetas dentro del cuadro
  y la tabla se atenúa mientras carga, en lugar de vaciarse.
- **1.11** — La columna **Beneficiario** pasa a llamarse **Beneficiario /
  Cliente** y se llena en todos los movimientos, no solo en los cheques: en un
  ingreso muestra el cliente que pagó (o el "Recibí de"), en un egreso el
  beneficiario, proveedor o empleado. El modal del movimiento muestra la misma
  línea, con la etiqueta Cliente / Proveedor / Empleado según corresponda. Los
  ingresos y egresos **anulados o eliminados** ya no aparecen en el listado ni
  suman al saldo, aunque su asiento haya quedado sin anular.
- **1.10** — Cambio de criterio en el saldo: un cheque solo se descuenta (o
  suma, si es recibido) cuando está registrado como **cobrado**. Mientras no
  tenga Fecha Banco no afecta saldos; cuando la tiene, cuenta en el período de
  esa fecha y no en el de emisión. El saldo del módulo pasa a reflejar el
  extracto bancario y puede diferir del contable mientras haya cheques en
  circulación. Se agregó el filtro **Cheques** (no cobrados / cobrados /
  posfechados) y las exportaciones a PDF y Excel ahora respetan también los
  filtros de flujo, tipo y estado del cheque.
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
