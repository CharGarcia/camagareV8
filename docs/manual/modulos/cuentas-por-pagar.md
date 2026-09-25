---
titulo: Cuentas por pagar
resumen: Qué le debe la empresa a sus proveedores, con sus vencimientos, y registro del pago.
categoria: Tesorería
ruta_modulo: modulos/cuentas_por_pagar
tipo: modulo
visibilidad: todos
etiquetas: cuentas por pagar, cxp, deudas, proveedores, saldo pendiente, vencimiento, pagar, obligaciones, fecha de corte, saldo a una fecha, fecha hasta, consolidado, establecimientos, sucursales, matriz, mismo ruc, deudas consolidadas, todas las sucursales, valores de terceros, otros conceptos, valores adicionales, bomberos, tasa de basura, planilla de luz, supera el saldo pendiente, filtrar por proveedor, error de conexion, serie, punto de emision, serie inactiva, registrar pago, cedula y ruc, cliente duplicado, proveedor duplicado, mismo cliente dos veces, mismo proveedor dos veces, identificacion repetida, ruc es la cedula mas 001, unificar fichas, cartera partida en dos, tildes, acentos, eñe, buscar sin tildes, no encuentra al proveedor, no aparece el proveedor, buscar por apellido, buscar por varias palabras, mayor, mayor del proveedor, deuda como mayor, agrupado por proveedor, subtotal por proveedor, total general, seccion por proveedor, no carga al entrar, boton aplicar, aplicar filtros, listado vacio al entrar, detalle por proveedor, columnas del detalle, nc, abonos, retenciones, dias vencidos, ordenar, ordenamiento, orden alfabetico, a-z, z-a, ordenar por proveedor, ordenar por saldo, ordenar por vencimiento, clic en la columna, ordenar la tabla, ordenar el excel, ordenar el pdf, flecha de la columna, saldo junto al nombre, saldo del proveedor, pdf vertical, pdf horizontal, orientacion del pdf, hoja vertical, pdf apaisado, acceso total, permiso de ver todos, registros propios, solo mis compras, no veo las compras de otro, cada usuario ve lo suyo, documentos migrados no aparecen, filtros del pdf, filtros aplicados, quitar filtros del pdf, encabezado del pdf, menu del celular, menu bloqueado, menu no responde, lineas montadas, lineas encimadas, lineas pisadas, texto montado en el pdf, filas cortadas, fila partida entre paginas, paginas en blanco, hojas en blanco en el pdf, pdf descuadrado, encabezado de columnas en cada pagina, nota de debito, notas de debito, nd del proveedor, no cuadra el pdf, total menos pagado no da el saldo, totales repetidos, totales en cada hoja, letra del pdf, pdf se lee chiquito, cuadricula del pdf, columna documento cortada
version: 1.26
orden: 50
estado: activo
---

**Cuentas por pagar** es el espejo de las cuentas por cobrar: qué facturas de
compra siguen sin pagarse, de qué proveedor y cuándo vencen.

## El listado se consulta al presionar Aplicar Filtros

Al entrar al módulo **no se consulta nada**: la tabla muestra la invitación
*«Elija los filtros y presione Aplicar Filtros»* y las tarjetas de arriba quedan
en cero. Primero se arman los filtros (tipo de documento, estado, fechas,
proveedor, establecimientos) y recién al presionar **Aplicar Filtros** el sistema
va a buscar la deuda.

- Así se evita la consulta pesada de "toda la cartera de proveedores" cada vez
  que alguien abre el módulo de paso, y se pueden elegir varios filtros sin que
  la pantalla se recargue en cada cambio.
- **Antes del primer Aplicar** ningún filtro dispara la consulta: cambiar el
  estado o agregar un proveedor solo prepara la búsqueda.
- **Después del primer Aplicar** el módulo se comporta como siempre: cambiar un
  filtro vuelve a consultar de inmediato.
- El botón **Limpiar** deja los filtros en sus valores por defecto; si todavía
  no se aplicó nada, tampoco consulta.

## Vista "Por proveedor": la deuda como el mayor de una cuenta

El botón **Por proveedor** resume la deuda en **una línea por proveedor**: su
identificación, cuántos documentos tiene y sus totales —total, NC, abonos,
retenciones y, sobre todo, el **saldo por pagar** de ese proveedor, en rojo
mientras se le deba algo—. Al desplegar una línea se lee como el **mayor de una
cuenta contable**: debajo aparecen los documentos de ese proveedor. Al final del
listado va la fila **TOTAL GENERAL**.

- El listado arranca **plegado**: de un vistazo se ve cuánto se le debe a cada
  proveedor. Un clic en su línea despliega los documentos y otro la vuelve a
  plegar; el saldo del proveedor se sigue viendo en los dos estados.
- El **saldo va junto al nombre**, en una etiqueta roja (o verde si ya no se le
  debe nada), además de su columna: así se lee de inmediato sin recorrer la fila
  hasta el final.

**El detalle de cada proveedor** no repite las columnas del listado general (el
proveedor ya es la cabecera de la sección): muestra **fecha, n. de documento,
total, NC, abonos, retenciones, saldo y días**.

| Columna | Qué muestra |
|---|---|
| Fecha | Fecha de emisión del documento. |
| N. Documento | Número del comprobante. Las liquidaciones, importaciones y saldos iniciales llevan una marca (`LIQ`, `IMP`, `SI`); las facturas de compra no. En consolidado antecede el código del establecimiento. |
| Total | Valor del documento. Si tuvo **nota de débito**, se indica al lado (`+valor`), porque esa nota suma al saldo. |
| NC | Notas de crédito del proveedor aplicadas al documento. |
| Abonos | Pagos realizados (egresos). |
| Retenciones | Retenciones practicadas al proveedor. |
| Saldo | Lo que falta pagar: total + ND − NC − abonos − retenciones. En rojo si hay saldo. |
| Días | Días vencidos (en rojo); si aún no vence, muestra `—` y la fecha de vencimiento al pasar el mouse. |

- Dentro de cada proveedor los documentos van en **orden cronológico** por fecha
  de emisión; los proveedores se ordenan **alfabéticamente (A-Z)**, en pantalla
  y en los archivos.
- Cada documento conserva sus acciones normales (pagar, historial).
- Un proveedor cargado dos veces —con la cédula y con el RUC— forma **una sola
  sección** (ver *Un mismo proveedor registrado con cédula y con RUC*).

### PDF y Excel de esta vista

Con la vista **Por proveedor** activa, los botones **PDF** y **Excel** salen con
esa misma estructura, no como lista plana. A diferencia de la pantalla, en el
archivo **todas las secciones salen desplegadas** (con su detalle), esté como
esté el listado en ese momento:

- **PDF**: cabecera con la identificación, el nombre y el **saldo** del proveedor
  (`1791234567001 - DISTRIBUIDORA EJEMPLO S.A. · saldo: 1,234.56`), la tabla de
  sus documentos con **las mismas columnas de la pantalla** (fecha, n. de
  documento, total, NC, abonos, retenciones, saldo y días) y, al cierre del
  reporte, el **TOTAL GENERAL**. Cada proveedor no lleva fila de subtotal: su
  saldo ya está en la cabecera de la sección. Arriba se mantienen las tarjetas
  de resumen; el recuadro de filtros aplicados no se imprime (solo va en el
  Excel). La **fila de encabezado de columnas se repite en todas las páginas**,
  y ninguna línea se parte entre una hoja y la siguiente: cada documento sale
  entero en una sola página.
- **Excel**: una **sección por proveedor** (título con su identificación, nombre
  y saldo, en el mismo formato del PDF), sus documentos con esas mismas columnas —más *Tipo*,
  *ND* y *Estado*, que en una hoja de cálculo no estorban— y el **TOTAL GENERAL** al
  final de la hoja. El proveedor no va como columna: es el título de la sección,
  igual que la cuenta en el mayor.
- En **consolidado por RUC** ambos archivos agregan la columna **Estab.** con el
  establecimiento dueño de cada documento.
- Para la lista plana de siempre (una fila por documento, con proveedor y RUC
  como columnas) se exporta desde la vista **Detallado**.

## De dónde sale el saldo

Del conjunto de:

- Las **compras** registradas y no pagadas.
- Los **saldos iniciales** de proveedores cargados al empezar.

Menos lo ya pagado mediante egresos, las notas de crédito y las retenciones
(más las notas de débito).

Una retención se descuenta del documento al que está enlazada. Las retenciones
**migradas**, que no traen ese enlace, se reconocen por el número del documento
sustento **y el proveedor**: un mismo número (p. ej. `001-001-000000054`) se
repite entre proveedores, y solo cuenta la retención del proveedor de la compra.

El **vencimiento** se calcula con el *plazo* configurado en la ficha del
proveedor. Si un documento vence antes de lo que esperaba, ese es el campo a
revisar.

## El PDF y el Excel de la vista Detallado

Con la vista **Detallado** activa (una fila por documento), los botones **PDF** y
**Excel** exportan lo mismo que se ve en pantalla.

- El **PDF** sale en hoja vertical con las columnas *Documento* (con su tipo
  encima: `Fac.`, `Liq.`, `Imp.` o `Saldo ini.`), *Proveedor*, *F. Emisión*,
  *F. Vencimiento* (con el estado debajo: *Pagada*, *Vigente* o *Nd vencida*),
  *Total*, *Pagado/Ret/NC* y *Saldo*. La fila **TOTALES** se imprime **una sola
  vez, al final del reporte**.
- **La columna *Pagado/Ret/NC* descuenta las notas de débito.** La ND del
  proveedor *suma* a lo que se le debe, así que se resta de esa columna para que
  la cuenta cierre: **Total − Pagado/Ret/NC = Saldo**, con o sin notas de débito.
- El **Excel** trae esas cifras en columnas separadas —*Total*, *Abonos*,
  *Notas de Crédito*, *Notas de Débito*, *Retenciones*, *Pagado* y *Saldo*—, más
  el RUC, los días vencidos y el estado. La columna *Pagado* es
  `abonos + notas de crédito + retenciones − notas de débito`.
- En **consolidado por RUC** ambos archivos agregan la columna **Estab.**
- Si los filtros no devuelven ningún documento, el PDF sale con los encabezados
  de la tabla y la línea *No se encontraron cuentas por pagar con los filtros
  aplicados*, en vez de una hoja con las tarjetas en cero y nada debajo.
- Cada hoja del PDF —en esta vista y en *Por proveedor*— lleva al pie, a la
  derecha, su **número de página** sobre el total (*Página 2/5*).

## Buscar el proveedor: tildes, ñ y varias palabras

El buscador **Proveedor** de la tarjeta de filtros encuentra al proveedor aunque
no se escriban las tildes ni la eñe: `ORDONEZ` encuentra a *OCHOA ORDOÑEZ*,
`Electrica` a *Empresa Eléctrica Quito* y `RUMINAHUI` a *Rumiñahui*. También
funciona al revés: escribir la tilde o la eñe aunque la ficha esté guardada sin
ellas.

Además busca **por palabras sueltas y en cualquier orden**: `ORDONEZ OCHOA`
encuentra a *OCHOA ORDOÑEZ ERMA*, sin escribir la razón social completa ni en el
mismo orden en que está guardada. Cada palabra puede aparecer en la razón social
o en la identificación, así que el RUC también sirve.

Basta con escribir **dos letras** para que aparezca la lista. Al elegir un
proveedor queda como una etiqueta y se pueden elegir varios.

## Quién ve qué: el permiso de Acceso total

El listado respeta el permiso **Acceso total** del módulo (*Configuración →
Permisos por módulo*):

- **Con acceso total** (o siendo superadministrador): se ve toda la deuda de la
  empresa.
- **Sin acceso total**: cada usuario ve **solo los documentos que él registró**
  — las compras, liquidaciones e importaciones que cargó y los saldos iniciales
  que ingresó. Lo que no sale en la tabla tampoco entra en las tarjetas de
  arriba, en el gráfico de antigüedad, en la vista *Por proveedor*, ni en el PDF
  y el Excel: todo parte del mismo listado.
- Tampoco se puede llegar a un documento ajeno por otras vías: registrar un pago
  o ver su historial responde *No tiene permiso sobre este registro: lo creó
  otro usuario*.

Aviso sobre documentos antiguos: los **migrados** desde el sistema anterior
quedaron a nombre del usuario que corrió la migración, así que solo él (o
alguien con acceso total) los verá.

## Ordenar el listado

Las deudas se abren **ordenadas por proveedor, de la A a la Z**. Para verlas de
otra forma, haga clic en el título de la columna: el primer clic ordena de menor
a mayor (A-Z, la fecha más antigua, el monto más bajo) y volver a hacer clic en
la misma columna invierte el orden. La flecha azul del título indica por cuál
columna está ordenada la tabla y en qué sentido.

Se puede ordenar por **Documento**, **Origen**, **Proveedor**, **F.Emisión**,
**F.Vencimiento**, **Total**, **Pagado**, **NC/Ret.**, **Saldo** y **Estado**
(por días vencidos).

- Cuando dos filas coinciden en la columna elegida, aparece primero la de
  vencimiento más próximo.
- Las filas sin dato en esa columna (por ejemplo, una compra sin fecha de
  vencimiento) van siempre al final, se ordene de mayor a menor o al revés.
- Las mayúsculas y las tildes no cambian el orden: *Álvarez* y *ALVAREZ* quedan
  juntos.
- El orden elegido **queda guardado para usted**: la próxima vez que entre al
  módulo, el listado se abre así.
- El **PDF y el Excel salen en el mismo orden** que la pantalla. El PDF se
  genera en **hoja vertical** (A4 retrato), en cualquiera de las vistas.

En la vista *Por proveedor* las secciones salen siempre en **orden alfabético**
(A-Z), tanto en pantalla como en el PDF y el Excel; el orden que elija en las
cabeceras acomoda los documentos dentro de cada sección.

## Consolidado de establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), las deudas de cada uno viven por separado.
Desde la **matriz** se puede ver la cartera por pagar de todo el grupo en una
sola pantalla con el filtro **Establecimientos**:

- **Solo este (matriz)**: comportamiento normal, únicamente los documentos de la
  empresa activa.
- **Consolidado (N establec.)**: suma las facturas de compra, liquidaciones,
  importaciones y saldos iniciales de todos los establecimientos del mismo RUC a
  los que el usuario tiene acceso. Las tarjetas, el gráfico de antigüedad, la
  vista *Por proveedor*, el PDF y el Excel consolidan de la misma forma. Aparece
  una tarjeta extra con la cantidad de establecimientos incluidos.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
  En una sucursal no se muestra.
- Un usuario que no es superadministrador solo ve los establecimientos que tiene
  asignados; los demás no entran al consolidado aunque compartan RUC.
- Cada documento muestra un **badge con el código del establecimiento** (001,
  002, …) al inicio de la columna *Documento*; al pasar el mouse se ve el nombre.
- **Pagar un documento de otra sucursal desde la matriz**: el botón de pago de
  la fila abre el mismo modal, pero el egreso se registra **en los libros de la
  sucursal dueña del documento**: sus series (puntos de emisión), su secuencial
  de egresos, sus conceptos, sus formas de pago y su contabilidad. El modal lo
  avisa con una franja azul con el nombre del establecimiento. La matriz no
  registra nada propio: no hay asiento intercompañías.
- Para pagar en una sucursal el usuario necesita permiso de **crear** en
  Cuentas por Pagar **en esa sucursal** (superadministrador siempre puede). Si
  no lo tiene, el botón aparece deshabilitado con el aviso "Sin permiso para
  registrar pagos en el establecimiento…".
- El historial de pagos de un documento de otra sucursal se consulta desde la
  matriz. Al hacer clic en la fila, el panel de detalle muestra solo el resumen.
- El buscador de **Proveedor** busca en todos los establecimientos y muestra al
  proveedor una sola vez por identificación; al elegirlo, el filtro alcanza sus
  documentos en todas las sucursales (el cruce es por RUC, porque cada
  establecimiento tiene su propia lista de proveedores).
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.
- En el **Excel** el encabezado indica *Alcance: Consolidado por RUC* con la
  lista de establecimientos (el PDF no imprime los filtros), y en los dos
  archivos se agrega la columna **Estab.**

## Un mismo proveedor registrado con cédula y con RUC

Es habitual que el mismo proveedor esté cargado **dos veces**: una ficha con la
**cédula** (10 dígitos) y otra con el **RUC** (13 dígitos), que en las personas
naturales es esa misma cédula seguida de **001**. Por ejemplo
`1717136574` y `1717136574001`. Son dos filas distintas en `proveedores`, cada una con
sus propios documentos, aunque para efectos prácticos sean la misma persona.

Cuentas por pagar los trata como **uno solo**:

- El **buscador de proveedor** muestra **una sola entrada**, no dos. Se puede escribir
  la cédula o el RUC: en ambos casos aparece la misma opción (se muestra la ficha
  con el RUC, que es la identificación completa).
- Al elegirla, el listado trae los documentos de **las dos fichas**, y los totales
  de arriba suman las dos.
- La vista **Agrupado por proveedor** los junta en **una sola tarjeta**, con su
  saldo total, en vez de dos tarjetas con la deuda partida.

Esto es solo de consulta: **no se fusionan ni se modifican las fichas**, y cada
documento sigue perteneciendo a la ficha con la que se emitió. Si quiere dejar
una sola ficha de verdad, hay que hacerlo en el módulo de Proveedores.

**Qué NO se agrupa**: solo se cruzan la cédula de 10 dígitos y su RUC terminado
en 001. Un RUC de sucursal (…002, …003), el consumidor final
(`9999999999999`), un pasaporte o cualquier otra identificación se comportan
como siempre: cada ficha por su lado. Las fichas **sin identificación** tampoco
se agrupan entre sí.

## Fecha Hasta como fecha de corte

El filtro **Fecha Hasta** no solo limita qué documentos se muestran (los
emitidos hasta esa fecha): también es la **fecha de corte del saldo**. Los
pagos, retenciones y notas de crédito o débito fechados **después** de esa
fecha no se descuentan, así el listado muestra lo que se debía **ese día**.

Ejemplo: una compra pagada el 31 de mayo aparece pendiente, con su saldo
completo, en cualquier consulta con Fecha Hasta igual o anterior al 30 de mayo,
y desaparece de los pendientes a partir del 31.

La regla es la misma que en Cuentas por Cobrar y aplica por igual a las
compras, liquidaciones, importaciones y **saldos iniciales**; las tarjetas
superiores, el gráfico de antigüedad y las exportaciones respetan el corte.
Sin Fecha Hasta, el saldo es el actual.

La fecha que manda para un pago es la **fecha del egreso**. Si el egreso se
generó automáticamente (descarga del SRI o *Generar pagos pendientes*), esa
fecha es la de la compra, aunque el cheque tenga fecha posterior.

## Notas de crédito y débito del proveedor

Las notas de crédito y débito que emite el proveedor **no aparecen como
documentos sueltos** en este listado: se restan (o suman) directamente al saldo
de la factura que modifican. Así el listado muestra lo que realmente se le debe a
cada proveedor, y no tres líneas que hay que compensar mentalmente.

## Registrar el pago

Se registra desde el propio listado. Equivale a crear un egreso: reduce el saldo
del documento, deja constancia de la forma de pago y genera el asiento contable.

En las **planillas de luz y agua**, el saldo del documento incluye los rubros que
la distribuidora recauda para terceros (bomberos, tasa de basura): no están
dentro del importe declarado al SRI, pero sí se pagan. Ver *Planillas de luz y
agua: valores de terceros* en el manual de Compras.

También queda disponible el **historial de pagos** de cada documento, útil cuando
una factura se pagó en varias partes.

### Serie del pago: solo puntos de emisión activos

La lista **Serie** del modal muestra únicamente los puntos de emisión en estado
**activo**; los inactivos no aparecen. Es el mismo criterio de Egresos y
Cuentas por Cobrar, porque el pago emite un egreso nuevo con el secuencial de
esa serie. En el consolidado, la lista es la de la sucursal dueña del documento.

- Para usar una serie que no aparece, actívela en **Empresa**, pestaña
  **Puntos de Emisión**.
- Si la empresa no tiene ningún punto activo, la lista muestra *Sin series
  activas* y el pago no se puede registrar.
- Si una serie se inactiva con el modal ya abierto, al guardar el sistema
  rechaza el pago con el aviso *La serie (punto de emisión) no es válida o
  está inactiva*: cierre el modal y vuelva a abrirlo.

## Errores frecuentes

- **Un documento aparece vencido antes de tiempo**: revise el *plazo* del
  proveedor.
- **El saldo no coincide con lo que dice el proveedor**: compruebe si hay notas
  de crédito aplicadas a esa factura.
- **Pagué y sigue pendiente**: verifique que el egreso quedó aplicado a ese
  documento y no registrado como concepto general.
- **Una serie no aparece en el modal de pago**: está **inactiva**. Solo se
  ofrecen los puntos de emisión activos; actívela en Empresa, pestaña Puntos de
  Emisión (ver *Serie del pago: solo puntos de emisión activos*).
- **"La compra ... está pendiente de aprobación: no se puede pagar hasta que la
  aprueben"**: la empresa exige aprobar las compras. La compra se lista porque es
  una deuda real, pero se paga después de aprobarla.

## Qué comprobantes de compra aparecen

Aparece como cuenta por pagar todo comprobante de compra que genera una
obligación con el proveedor: la factura, la **nota de venta**, los documentos de
instituciones financieras, las planillas de servicios básicos y los demás tipos
autorizados por el SRI, además de las liquidaciones de compra, importaciones y
saldos iniciales. Una liquidación de compra se muestra desde que se autoriza y
sigue visible cuando pasa a *contabilizado*; solo sale de la lista al anularse o
al quedar pagada. Las compras anuladas o rechazadas no se muestran. Las
**pendientes de aprobación** sí se muestran, pero no se pueden pagar hasta que las
aprueben. No aparecen
como fila las notas de crédito y de débito recibidas: esas ajustan el saldo de
la factura que modifican. Mismo criterio que
el Reporte de Cartera y que el asiento contable de la compra.

## Historial de cambios

- **1.26** — Una retención **migrada** ya no se descuenta del saldo de compras o
  liquidaciones de **otro proveedor** que tengan el mismo número de documento
  (antes se restaba a todas). Aplica al listado, al detalle y al registrar el
  pago. Actualizada *De dónde sale el saldo*.

- **1.25** — Los **PDF del listado** (vistas *Detallado* y *Por proveedor*)
  muestran el **número de página al pie** de cada hoja (*Página 2/5*).
  Actualizada *El PDF y el Excel de la vista Detallado*.

- **1.24** — Tres arreglos en los archivos del módulo:
  - **La fila TOTALES ya no se repite en cada hoja del PDF.** En la vista
    *Detallado* salía al pie de **todas** las páginas y siempre con el total del
    reporte completo, como si fuera el total de esa hoja. Ahora se imprime una
    sola vez, al final.
  - **Las notas de débito ya cuadran.** La ND del proveedor suma a lo que se le
    debe, pero el PDF *Detallado* no la descontaba de *Pagado/Ret/NC*: un
    documento con ND mostraba `Total − Pagado/Ret/NC ≠ Saldo`. En el **Excel**
    detallado se agrega además la columna **Notas de Débito** (y la columna
    *Pagado* ya la resta), y el Excel *Por proveedor* agrega la columna **ND**.
  - **Los PDF se leen más grandes y la cuadrícula se ve al imprimir**: la tabla
    pasa de 7 a **8 puntos**, los encabezados de 7.5 a **8.5**, y las líneas de
    las tablas pasan de gris claro a **gris marcado**, igual que en Cuentas por
    cobrar. Con la letra más grande, la columna *Documento* de la vista
    *Detallado* se ensancha (del 14% al 17%, a costa de *Proveedor*, que parte el
    nombre en varias líneas) para que el número no invada la columna vecina.
  - **El PDF *Detallado* avisa cuando no hay resultados**: antes salía con las
    tarjetas en cero, sin encabezados de tabla y sin explicación; ahora imprime
    la cabecera de columnas y la línea *No se encontraron cuentas por pagar con
    los filtros aplicados*, como ya hacía la vista *Por proveedor*.

  Nueva sección *El PDF y el Excel de la vista Detallado*; actualizada
  *PDF y Excel de esta vista*.

- **1.23** — **El PDF *Por proveedor* ya no sale con las líneas montadas.**
  Cuando la sección de un proveedor empezaba en el último centímetro de la hoja,
  su primera línea se partía: unos datos quedaban pisando el pie de esa página,
  el resto se repartía entre las hojas siguientes y aparecían **una o dos
  páginas casi en blanco** con renglones sueltos. Ahora todo el listado va en
  **una sola tabla** —cada proveedor es una fila de cabecera dentro de ella—,
  así que ninguna línea se corta y el **encabezado de columnas se repite en
  todas las páginas** (antes solo aparecía al empezar cada proveedor). La
  separación entre proveedores, los anchos de las columnas y el resto del diseño
  no cambian. Es el mismo arreglo del PDF *Por cliente* de **Cuentas por
  cobrar**. Actualizada *PDF y Excel de esta vista*.

- **1.22** — **El saldo se redondea a centavos.** Los importes de egresos se
  guardan con seis decimales, así que restar lo pagado podía dejar un residuo
  como $0.000001: el documento quedaba listado como pendiente para siempre,
  porque no hay forma de pagar menos de un centavo. Ahora ese residuo se da por
  pagado y el documento sale de la lista. Afecta al listado, a las tarjetas de
  totales, a la antigüedad de saldos, a los saldos iniciales CXP y al saldo que
  propone el modal de pago. **Las cifras de los documentos normales no cambian**:
  el redondeo solo actúa cuando hay fracciones por debajo del centavo.

- **1.21** — **El centavo pendiente ya se puede pagar.** El saldo de
  $0.01 que este reporte mostraba como pendiente no se ofrecía en *Egresos*,
  que lo daba por pagado. Los dos módulos usan ahora el mismo criterio: **hay
  saldo mientras quede al menos un centavo**. Este reporte no cambia; el que se
  corrigió fue Egresos.


- **1.20** — El **PDF** (vistas *Detallado* y *Por proveedor*) ya no imprime el
  recuadro de **filtros aplicados**: bajo el encabezado van directamente las
  tarjetas de resumen y la tabla. El **Excel** sigue describiendo los filtros en
  su encabezado. En el celular, el **menú lateral** vuelve a responder en esta
  pantalla (el fondo oscuro del menú quedaba por encima del propio menú).
- **1.19** — Pagar una compra **pendiente de aprobación** se rechaza con un aviso
  claro: sigue listada como deuda, pero se paga después de aprobarla. Antes el
  pago se registraba igual.
- **1.18** — Se quitó la fila **SUBTOTAL** de cada proveedor en la vista *Por
  proveedor*: su saldo ya aparece en la línea del proveedor y en la cabecera de
  la sección del PDF y el Excel. El **TOTAL GENERAL** del listado se mantiene.
- **1.17** — En el PDF y el Excel de la vista *Por proveedor*, la cabecera de cada
  sección muestra el **saldo** en lugar del número de documentos:
  `1791234567001 - DISTRIBUIDORA EJEMPLO S.A. · saldo: 1,234.56`.
- **1.16** — El módulo respeta el permiso de **Acceso total**: quien no lo tiene
  ve solo los documentos que él registró, tanto en la tabla como en las tarjetas,
  el gráfico de antigüedad, la vista por proveedor y las exportaciones, y ya no
  puede pagar ni consultar el historial de documentos de otro usuario. Antes el
  permiso no cambiaba nada: cualquiera con permiso de ver la deuda la veía
  completa. Nueva sección *Quién ve qué: el permiso de Acceso total*.
- **1.15** — En la vista *Por proveedor* las secciones salen ahora en **orden
  alfabético**, no por saldo, tanto en pantalla como en el PDF y el Excel. La
  línea de cada proveedor muestra además su **saldo junto al nombre**, en una
  etiqueta de color. El **PDF sale en hoja vertical** (antes horizontal) en las
  dos vistas.
- **1.14** — El listado se abre **ordenado por proveedor de la A a la Z** (antes
  salía por fecha de vencimiento) y ahora se puede **ordenar por cualquier
  columna** haciendo clic en su título, como en el Reporte de Ventas. El orden
  elegido queda guardado para el usuario y **el PDF y el Excel salen con ese
  mismo orden**. Nueva sección *Ordenar el listado*.
- **1.13** — El **detalle de cada proveedor** (vista *Por proveedor*) pasa a
  mostrar **fecha, n. de documento, total, NC, abonos, retenciones, saldo y
  días** —con NC y retenciones separadas, antes iban sumadas en una sola columna
  *NC/Ret.*—, en vez de repetir las columnas del listado general; el **PDF** y el
  **Excel** de esa vista salen con las mismas columnas. Mismo cambio que en
  Cuentas por Cobrar.
- **1.12** — La vista **Por proveedor** ahora se lee como el **mayor de una
  cuenta**: cada proveedor es una línea con su **saldo por pagar** y, al
  desplegarla, aparecen sus documentos cerrados con una fila de **SUBTOTAL**;
  al final del listado, el **TOTAL GENERAL**. El **PDF** y el **Excel** de esa
  vista salen con la misma estructura (antes salían siempre como lista plana,
  y en pantalla la agrupación no mostraba subtotales ni total general). Además,
  al entrar al módulo **ya no se carga nada**: el listado se consulta al
  presionar **Aplicar Filtros**. Nuevas secciones *El listado se consulta al
  presionar Aplicar Filtros* y *Vista "Por proveedor": la deuda como el mayor de
  una cuenta*. Mismo cambio que en Cuentas por Cobrar.
- **1.11** — El buscador de **Proveedor** ya no distingue tildes ni eñe
  —`ORDONEZ` encuentra a *OCHOA ORDOÑEZ*, `Electrica` a *Eléctrica*— y busca por
  palabras sueltas en cualquier orden, igual que el resto de buscadores del
  sistema. Antes exigía escribir el texto exacto, con sus tildes y en el mismo
  orden. Nueva sección *Buscar el proveedor: tildes, ñ y varias palabras*.
- **1.10** — Un mismo proveedor cargado **dos veces** —una ficha con la cédula y otra con el
  RUC, que es esa cédula + `001`— deja de aparecer partido en dos: el buscador
  muestra una sola entrada, el listado trae los documentos de las dos fichas y la
  vista agrupada las junta en una tarjeta. Nueva sección *Un mismo proveedor
  registrado con cédula y con RUC*.
- **1.9** — La lista **Serie** del modal de pago ya no ofrece puntos de emisión
  **inactivos**: solo los activos, igual que Egresos y Cuentas por Cobrar. El
  servidor también rechaza un pago con una serie inactiva, y si la empresa no
  tiene ninguna activa la lista lo indica (*Sin series activas*). Nueva sección
  *Serie del pago: solo puntos de emisión activos*.
- **1.8** — Se corrigió el filtro por **proveedor**: al elegir uno, la pantalla
  mostraba «Error de conexión» en vez de sus documentos. Las tarjetas de resumen y
  la antigüedad de saldos también vuelven a filtrarse por el proveedor elegido.
- **1.7** — El listado abre **mucho más rápido**. Con varios miles de compras, la
  pantalla podía tardar más de diez segundos en mostrar aunque solo hubiera cuatro
  documentos pendientes; ahora responde en décimas. También se corrigió el orden:
  las filas con la misma fecha de vencimiento ya no cambian de posición entre una
  carga y otra.
- **1.6** — Al **registrar el pago** de una planilla de luz o agua, el saldo que
  se valida ya incluye los **valores de terceros** (bomberos, tasa de basura). El
  listado sí los mostraba, pero al guardar el pago se rechazaba el monto por
  "superar el saldo pendiente"; ahora ambos usan la misma cifra.
- **1.5** — Consolidado, fase 2: desde la matriz ya se puede **registrar el
  pago** de una factura de compra, liquidación, importación o saldo inicial de
  otra sucursal. El egreso se registra en los libros de la sucursal dueña (sus
  series, secuencial, conceptos, formas de pago y contabilidad) y exige permiso
  de crear en esa sucursal.
- **1.4** — Nuevo filtro **Establecimientos** para ver las deudas **consolidadas
  de todos los establecimientos del mismo RUC**, disponible solo desde la
  **matriz** del grupo (fase 1, solo lectura): los documentos de las sucursales
  se listan con el badge de su establecimiento, suman en tarjetas, antigüedad,
  PDF y Excel, y permiten ver su historial, pero el pago se registra desde la
  empresa dueña del documento. El buscador de proveedor cruza por identificación
  entre establecimientos.
- **1.3** — El PDF y el Excel exportados muestran, bajo el encabezado, los
  **filtros aplicados** (tipo de documento, estado, período y proveedor), para
  que quien lo reciba sepa exactamente qué cartera está viendo. En el Excel los
  montos ahora son celdas numéricas con dos decimales y sin separador de miles,
  listas para sumar.
- **1.2** — Se listan y se pueden pagar todos los comprobantes de compra que generan deuda (notas de venta, documentos financieros, planillas, etc.), no solo la factura. Las **liquidaciones de compra ya contabilizadas** vuelven a aparecer (antes desaparecían de la cartera al registrarse su asiento). Las compras anuladas o rechazadas dejan de mostrarse como deuda. También aplica al saldo de la ficha del proveedor y al pago automático a proveedores.
- **1.1** — Los **saldos iniciales** respetan la fecha de corte igual que las
  compras: con Fecha Hasta, un pago posterior a esa fecha ya no descuenta el
  saldo inicial (antes se usaba el acumulado pagado sin importar la fecha).
  Aplica al listado, a las tarjetas y al gráfico de antigüedad.
- **1.0** — Versión inicial.
