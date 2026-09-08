---
titulo: Estados financieros
resumen: Estado de resultados y estado de situación financiera a partir de los asientos contables.
categoria: Contabilidad
ruta_modulo: modulos/estados_financieros
tipo: modulo
visibilidad: todos
etiquetas: estados financieros, balance, estado de resultados, situacion financiera, perdidas y ganancias, activo pasivo patrimonio, reportes por periodos, comparativo mensual, horizontal por mes, editar cuenta desde el balance, codigo sri, supercias, entidades de control, pdf con logo, firma del contador, firma del representante legal, balances firmados
version: 1.9
orden: 50
estado: activo
---

Este módulo arma los dos informes contables principales a partir de los asientos:

- **Estado de resultados**: ingresos menos gastos de un periodo. Dice si se ganó
  o se perdió.
- **Estado de situación financiera**: activo, pasivo y patrimonio a una fecha.
  Dice qué se tiene y qué se debe.

También permite consultar el **mayor auxiliar** de una cuenta y exportar los
informes.

## Antes de generarlos

Los estados financieros solo son fiables si la contabilidad está completa. Por
eso, al abrir el módulo, el sistema **pregunta si desea generar los asientos
pendientes** cuando detecta documentos sin contabilizar.

Si continúa sin generarlos, los informes saldrán sin esos movimientos. Es válido
para una consulta rápida, pero no para presentar nada.

## Cómo se generan

1. Indique el **rango de fechas** (o la fecha de corte).
2. Genere el estado que necesite.
3. Expórtelo si va a presentarlo o archivarlo.

### Formatos de exportación

- **PDF** y **Excel**: el reporte tal como se ve, con el nivel de agrupación
  elegido.
- **Formato del PDF**: cada página lleva en la cabecera el **logo** de la
  empresa, su razón social, RUC, dirección y contacto, el nombre del estado y
  el periodo ("Del … al …" en resultados, "Al …" en situación financiera), más
  la línea de filtros aplicados (nivel, centro de costo y proyecto). Las
  cuentas se muestran con sangría y peso según su nivel, y los **valores van
  escalonados** al estilo contable: los de nivel 1 y los totales pegados al
  borde derecho y cada nivel más profundo un poco más a la izquierda (en el
  comparativo por meses solo cuando la columna del mes deja espacio). Los
  totales y el resultado del ejercicio van resaltados (verde utilidad, rojo
  pérdida). Al
  final aparecen las **firmas del Representante Legal y del Contador** con su
  nombre y C.I./RUC. Esos datos, igual que el logo, se toman de
  **Configuración › Empresas** (campos *Representante legal*, *Cédula/RUC del
  representante*, *Contador* y *RUC del contador*); si están vacíos, la línea
  de firma sale sin nombre para llenarla a mano. El pie muestra la fecha de
  emisión y "Página N de M".
- **Formato SRI**: archivo XML para el formulario de renta. Agrupa las cuentas
  de nivel 5 por su **Código SRI** y suma sus saldos; el RUC va en el concepto
  80. Las cuentas sin Código SRI no se incluyen.
- **Supercias ESF, ERI, ECP y EFE**: archivos de texto (`.txt`) para cargar en
  el portal de la Superintendencia de Compañías. Cada línea es
  `casillero espacio valor` (en ECP, `fila espacio columna espacio valor`), con
  punto decimal y sin separador de miles. El archivo trae **todos** los
  casilleros del formulario, incluidos los que valen 0.00: el portal los exige
  completos (376 en ESF, 246 en ERI, 320 en ECP y 83 en EFE) y rechaza el
  archivo entero si falta uno, con el mensaje "El número total de cuentas no es
  el correcto".

Los archivos Supercias se calculan **con los mismos datos que el reporte en
pantalla**: mismo rango de fechas, mismo centro de costo y proyecto, y solo
asientos contabilizados del ambiente activo. Cada cuenta de nivel 5 aporta su
saldo al casillero que tenga asignado en **Supercias ESF**, **ERI** o **ECP**
(código y subcódigo); la fila *Utilidad o Pérdida del Ejercicio* del balance se
suma al casillero de la cuenta de cierre configurada. Después se resuelven las
fórmulas de la estructura (`/config/supercias`) para obtener los casilleros de
totales. El filtro de nivel no altera el archivo.

Si un casillero sale en cero cuando debería tener valor, revise que las cuentas
correspondientes tengan asignado el código Supercias (columna *Ent. control* del
reporte, o pulse el código de la cuenta para completarlo). El ECP y el EFE no
usan un casillero propio por cuenta: se calculan a partir de los asientos y del
mapeo ESF/ERI, como se explica en las dos secciones siguientes.

### Revisar Supercias: qué falta configurar

El botón **Revisar Supercias** revisa la empresa para el rango elegido y lista
todo lo que impide que los cuatro archivos salgan completos y cuadrados:
cuentas con movimiento sin casillero ESF o ERI, casilleros que no existen o
que son de totales, patrimonio sin columna ECP, caja y bancos sin identificar,
cuenta de cierre del ejercicio sin configurar, saldos iniciales sin tipo
*apertura*, impuesto o participación registrados solo como pasivo, fórmulas de
totales pendientes en la estructura global, y los cuadres del balance, del ECP
y del EFE.

Para cada cuenta el sistema **sugiere el casillero** según su nombre. Con
permiso de actualizar en Plan de Cuentas se aplica con un clic, fila por fila
o todo el hallazgo; la sugerencia nunca se guarda sola. Al pulsar cualquier
descarga de Supercias la revisión se ejecuta antes; si hay hallazgos en rojo
se muestran y el usuario decide si descarga de todos modos. El recorrido
completo está en la guía *Presentar los estados financieros a Supercías*.

### Supercias ECP (Estado de Cambios en el Patrimonio)

El ECP es una matriz: **filas** por concepto del cambio y **columnas** por
componente del patrimonio (301 Capital, 302 Aportes, 303 Prima, 30401 y 30402
Reservas, 30501 a 30504 Otros resultados integrales, 30601 a 30607 Resultados
acumulados, 30701 Ganancia neta, 30702 Pérdida neta). El sistema lo arma así:

- **Columna**: la que tiene cada cuenta de patrimonio en *Supercias ECP
  Columna* (Plan de Cuentas). Sin ese dato la cuenta no entra al ECP.
- **990101 Saldo del período anterior**: los asientos de tipo **apertura**
  dentro del rango, que es como este sistema registra el saldo con que arranca
  el período.
- **9902xx Cambios del año**: el resto de asientos del rango, en la fila que
  corresponde a la columna: capital a 990201, aportes a 990202, prima a 990203,
  reservas y resultados acumulados a 990205, otros resultados integrales a
  990209. Si el movimiento de una cuenta va a otra fila (por ejemplo una cuenta
  de dividendos declarados a 990204), fíjela en *Supercias ECP Fila de cambios*.
- **990210 Resultado del año**: la utilidad o pérdida del ejercicio del
  balance, en 30701 si es ganancia o en 30702 (negativo) si es pérdida.
- **9901, 9902 y 99**: totales. La fila 99 suele tener fórmula `[ESF:…]` en
  `/config/supercias`, y en ese caso toma el valor del balance.

El botón **Ver ECP** muestra la matriz completa antes de descargar el archivo,
con una fila *Diferencia* que compara 99 con 9901 + 9902 por columna. Debe ser
cero; si no lo es, abajo se listan las cuentas que alimentan cada columna con su
saldo inicial y su movimiento, para ubicar la que falta o sobra. Lo que la
contabilidad no puede distinguir por sí sola (un dividendo frente a una
transferencia a reservas, cambios de políticas, corrección de errores) se
resuelve con cuentas separadas y su fila fijada, o retocando en el portal.

### Supercias EFE (Estado de Flujos de Efectivo)

El EFE tiene dos bloques que deben cuadrar entre sí: el **método directo**
(casilleros 95xx) y la **conciliación** con la utilidad (96 a 9820). El sistema
los arma sin ningún mapeo adicional, a partir de los asientos y de los
casilleros ESF y ERI que ya tienen las cuentas.

**Cuentas de efectivo.** Son las que tienen casillero ESF que empieza por 10101
(Caja, bancos públicos y privados). Sin ese mapeo no hay flujo que calcular.

**Método directo.** Se toma cada asiento contabilizado del rango (sin los de
apertura) que mueva una cuenta de efectivo. El efecto en efectivo de cada
contrapartida es su importe con signo contrario, y esa contrapartida se
clasifica según su casillero:

| Contrapartida | Entrada | Salida |
|---|---|---|
| Clientes, anticipos de clientes, ingresos (ERI 4) | 95010101 cobros de ventas | |
| Proveedores, inventario, anticipos a proveedores, costos y gastos (ERI 5 y 6) | | 95010201 pagos a proveedores |
| Sueldos y beneficios, IESS, participación trabajadores | | 95010203 pagos a empleados |
| Impuesto a la renta por pagar o anticipado | | 950107 |
| Intereses ganados / gastos financieros | 950106 | 950105 |
| Propiedad, planta y equipo, propiedades de inversión, activos biológicos | 950208 | 950209 |
| Activos intangibles | 950210 | 950211 |
| Inversiones y activos financieros | 950204 | 950205 |
| Préstamos bancarios y de accionistas | 950304 | 950305 |
| Valores emitidos | 950302 | 950305 |
| Arrendamientos | 950310 | 950306 |
| Capital y aportes | 950301 | 950303 |
| Dividendos por pagar y otras cuentas de patrimonio | 950301 | 950308 |
| Cualquier otra | 95010105 otros cobros | 95010205 otros pagos |

Las líneas de IVA, retenciones y crédito tributario no se clasifican solas: su
importe se suma a la contrapartida principal del asiento, así una venta de
contado con IVA entra completa en cobros de ventas. Los asientos de nómina y de
alta de activos fijos se clasifican por su origen aunque la contrapartida sea
genérica. Las transferencias entre caja y bancos no generan flujo.

**Conciliación.** 96 es la ganancia antes de participación e impuesto (ERI 600;
si ese casillero no tiene fórmula se reconstruye desde la utilidad del balance).
9701 suma las depreciaciones y amortizaciones del ERI. 9709 y 9710 llevan en
**negativo** el impuesto a la renta (ERI 603) y la participación de trabajadores
(ERI 601): son gasto devengado que no salió de caja, y su pago real entra por
950107 o por la variación del pasivo en 9807. Los casilleros
9801 a 9810 toman la variación del año de las cuentas de capital de trabajo
según su ESF: clientes (9801), otras cuentas por cobrar (9802), anticipos a
proveedores (9803), inventarios (9804), otros activos corrientes (9805),
proveedores (9806), otras cuentas por pagar (9807), beneficios a empleados
(9808), anticipos de clientes (9809) y otros pasivos (9810). Un aumento de
activo resta y un aumento de pasivo suma. Los ajustes que la contabilidad no
identifica (deterioro, provisiones, diferencias de cambio, 9702 a 9711) quedan
en cero; si aplican, se completan con fórmula en `/config/supercias`.

**Totales.** 9506 es el efectivo de los asientos de apertura, 9505 la suma de
operación, inversión y financiación, 9507 = 9506 + 9505, y 9820 = 96 + 97 + 98.

El botón **Ver EFE** muestra los casilleros con su valor, tres controles que
deben estar en cero (9507 contra el ESF 10101, 9505 contra el movimiento
contable del efectivo, y 9820 contra 9501) y, a la derecha, cada asiento de
efectivo con sus contrapartidas y el casillero asignado. Lo que cae en *otros
cobros* u *otros pagos* se marca en amarillo: casi siempre es una cuenta sin
casillero ESF o ERI, y se corrige asignándoselo desde Plan de Cuentas o pulsando
el código en el reporte. Si el tercer control no cuadra, revise cuentas de
capital de trabajo sin ESF, asientos de saldos iniciales sin tipo *apertura* y
ajustes sin efectivo pendientes de fórmula.

## Ver o editar una cuenta desde el reporte

En cualquiera de los cuatro reportes, el **código** de cada cuenta de nivel 2 a
5 es un enlace. Al pulsarlo se abre la ficha de esa cuenta, la misma del módulo
Plan de Cuentas, sin salir del balance:

- **Código y nivel**: solo lectura. El código de una cuenta nunca se cambia una
  vez creada, ni desde aquí ni desde Plan de Cuentas.
- **Nombre y estado**: editables en todos los niveles (en niveles 2 a 4 el
  nombre va en mayúsculas).
- **Centro de costo y proyecto**: solo en cuentas de nivel 5.
- **Códigos de entidades de control** (sección *Configurar códigos entidades de
  control*, solo nivel 5): Código SRI, Supercias ESF, ERI y ECP (código y
  subcódigo). Son los que usan los formatos de exportación **Renta SRI** y
  **Supercias**, así que este es el lugar natural para completarlos cuando, al
  revisar el balance, una cuenta aparece sin mapear.

Los grupos de nivel 1 (Activo, Pasivo, Patrimonio, Ingresos, Costos, Gastos) no
se editan desde el reporte.

### Columna "Ent. control"

Junto al código, la columna **Ent. control** muestra en etiquetas los códigos
de entidades de control que la cuenta tiene asignados: **SRI** (casillero del
formulario de renta), **ESF**, **ERI** y **ECP** (casilleros Supercias; el ECP
se ve como `código.subcódigo`). Una cuenta de nivel 5 con la celda vacía es una
cuenta que todavía no se ha mapeado: pulse su código para completarla. Esta
columna es solo de pantalla; no sale en las exportaciones a PDF y Excel.

Al guardar, el reporte se vuelve a generar para reflejar el nombre nuevo.

El **nombre** de la cuenta sigue abriendo el **mayor auxiliar**; el código abre
la ficha. Si el usuario no tiene permiso de *actualizar* en Plan de Cuentas, la
ficha se abre en modo consulta: se ven todos los datos pero sin botón Guardar.

## Reportes por periodos (comparativo mensual)

Además de los dos informes de un solo corte, el selector **Tipo de Reporte**
incluye dos variantes horizontales que muestran una **columna por mes** dentro
del rango de fechas elegido (por ejemplo, del 01-01 al 31-08 muestra columnas
de Enero a Agosto):

- **Estado de Resultados por Periodos**: cada columna es el **movimiento propio
  de ese mes** (no acumulado), más una columna final de **Total** con la suma
  del rango. Sirve para ver la tendencia mes a mes de ingresos, costos y gastos.
- **Estado de Situación Financiera por Periodos**: cada columna es el **saldo
  acumulado** desde la fecha de inicio hasta el fin de ese mes (un balance es
  una fotografía a una fecha, no un movimiento del mes). Por eso no lleva
  columna de Total: el último mes ya es el saldo final del rango.

En ambos, cada cuenta de nivel 5 sigue siendo clickeable para abrir su **mayor
auxiliar**. El rango de fechas está limitado a 36 meses para no generar una
tabla horizontal inmanejable. Los formatos **Renta SRI** y **Supercias** no
aplican a estas variantes (son formatos de un solo corte) y se ocultan al
seleccionarlas; **PDF** y **Excel** sí exportan el comparativo completo (el PDF
en orientación horizontal).

**Meses sin movimiento no se muestran como columna.** Si un mes no tuvo ningún
asiento contabilizado (en ninguna cuenta), esa columna se omite — no aparece
como una columna en cero. El criterio se evalúa siempre sobre el movimiento
propio de ese mes, incluso en el Estado de Situación Financiera por Periodos
(donde el saldo mostrado es acumulado): un mes sin movimiento repetiría el
mismo saldo del mes anterior, así que no aporta una columna nueva.

## Consolidado por RUC

Si el RUC activo tiene más de un establecimiento (empresa) al que el usuario
tenga acceso, aparece el botón **Consolidado por RUC**. Abre un modal con:

- **Total General Consolidado**: un solo Estado de Situación Financiera y un
  solo Estado de Resultados para **todo el RUC**, sin duplicar nada. Cada
  concepto mapeado en [Balances Consolidados](modulos/balances-consolidados) aparece
  **una sola vez** (sumado entre establecimientos, o con el valor de un solo
  establecimiento si así se configuró — ver "Cuenta única" abajo); cada cuenta
  que no está mapeada se lista aparte, identificada con su propio
  establecimiento. Incluye subtotales (Total Activos, Total Pasivos, Total
  Patrimonio, Total Pasivo + Patrimonio, Utilidad Bruta, Utilidad Neta).
- **Detalle de conceptos consolidados**: cómo se armó cada valor del Total
  General — de qué establecimiento(s) viene y con qué cuenta.
- **Totales por establecimiento**: el resumen de cada establecimiento por
  separado, tal cual su propio reporte individual, como referencia.

**Cuenta única (no se suma entre establecimientos)**: algunos conceptos —
típicamente **Capital** y otras cuentas de Patrimonio — no son un valor
independiente por establecimiento: son el mismo capital de la misma empresa,
aunque cada establecimiento lleve su propia contabilidad. Sumarlos infla el
total. Por eso, en Balances Consolidados un grupo se puede marcar como
"cuenta única": el Total General toma el valor de **un solo** establecimiento
(el que se configuró como fuente) y no suma los demás — que igual se muestran
en el detalle, tachados, solo como referencia.

La **Utilidad/Pérdida del Ejercicio** del Total General sí se suma entre
todos los establecimientos (a diferencia del capital, el resultado del
período es propio de cada uno y legítimamente aditivo).

## Si el balance no cuadra

Revise en este orden:

1. **Asientos pendientes** de generar.
2. **Periodos** correctos: que el rango de fechas sea el que cree.
3. El **resultado del ejercicio**: si el resultado del periodo no está cerrado
   contra patrimonio, el balance puede mostrar un descuadre que en realidad es la
   utilidad acumulada del propio ejercicio.

## Errores frecuentes

- **Faltan movimientos del mes**: hay asientos pendientes; acéptelos al abrir.
- **"Revise la configuración contable" pero ya está todo configurado**: el aviso
  de conceptos sin cuenta ya no incluye los que toman su cuenta del propio módulo
  (Facturas de compra, Liquidaciones, Facturas de venta, Recibos de venta y
  Nómina): esos no se configuran en Configuración Contable y antes se avisaban
  igual. Si el aviso persiste, el concepto o la forma de cobro/pago que nombra sí
  está sin cuenta.
- **Un ingreso o egreso que nunca termina de generar su asiento**: abra "Ver
  detalle" del aviso. Si dice que no queda ningún cheque vigente o que no hay
  formas de cobro/pago, no falta configurar nada: ese documento no tiene valor que
  contabilizar. Los documentos **anulados** no se toman en cuenta.
- **"El asiento no está cuadrado. Total Debe (0) no coincide con Total Haber"**:
  ese aviso ya no debería aparecer en Ingresos ni en Egresos. En su lugar el
  detalle dice **qué cuenta falta y dónde se configura** — por ejemplo *"Falta la
  cuenta «Cuentas por Pagar» en Configuración Contable → Adquisiciones de
  Compras/Servicios, o las facturas de compra que paga este egreso todavía no
  tienen su propio asiento generado"*. Un Debe (o un Haber) en cero significa
  siempre lo mismo: la contrapartida se quedó sin cuenta, no que el documento
  esté mal.
- **Un egreso que paga varias facturas de compra no genera su asiento**: no es
  por tener varios documentos. Cada factura pagada toma su cuenta del asiento de
  **esa** factura, así que si las facturas todavía no están contabilizadas —o son
  documentos migrados, que no se sincronizan— el egreso se queda sin
  contrapartida. Genere primero los asientos de Facturas de Compra y vuelva a
  intentarlo; si aun así falta, configure la cuenta «Cuentas por Pagar» en
  Configuración Contable.
- **La utilidad no coincide con lo esperado**: compare con el mayor de las
  cuentas de ingreso y gasto para ver qué documento falta o sobra.

## Historial de cambios

- **1.9** — Nuevo botón **Revisar Supercias**: diagnóstico de la empresa con
  sugerencias de casillero por cuenta aplicables con un clic, y revisión
  automática antes de cada descarga Supercias. Guía nueva *Presentar los
  estados financieros a Supercías*.
- **1.8** — El **Supercias EFE** se calcula automáticamente: método directo
  clasificando cada asiento de efectivo por el casillero ESF/ERI de su
  contrapartida, y conciliación desde el ERI y la variación del capital de
  trabajo del ESF. Nuevo botón **Ver EFE** con casilleros, controles de cuadre y
  el detalle de asientos clasificados.
- **1.7** — Nuevo diseño del **PDF** de los estados financieros (un periodo y
  por periodos): cabecera con el logo y los datos de la empresa en todas las
  páginas, título y periodo del estado, filtros aplicados, filas con sangría
  por nivel de cuenta, totales resaltados y aviso de descuadre en situación
  financiera, pie con fecha de emisión y paginación, y bloque final de
  **firmas del Representante Legal y del Contador** con nombre y C.I./RUC
  tomados de Configuración › Empresas.
- **1.6** — El **Supercias ECP** se calcula como matriz: saldo de apertura
  (990101), movimientos del año por fila según la columna o la fila fijada en
  la cuenta (9902xx), resultado del ejercicio (990210) y totales. Nuevo botón
  **Ver ECP** con la matriz, la fila *Diferencia* y las cuentas que alimentan
  cada columna.
- **1.5** — El código de cada cuenta de nivel 2 a 5 del reporte abre la ficha de
  la cuenta (modal de Plan de Cuentas): se pueden ver y editar nombre y estado y,
  en nivel 5, centro de costo, proyecto y los códigos SRI / Supercias sin salir
  del balance. Se agrega la columna **Ent. control** con los códigos SRI, ESF,
  ERI y ECP de cada cuenta. Los archivos **Supercias** (ESF/ERI/ECP/EFE) se
  calculan ahora con los mismos datos del reporte en pantalla (fechas, centro
  de costo, proyecto, solo asientos contabilizados del ambiente activo) e
  incluyen el resultado del ejercicio; antes tomaban el año calendario completo
  sin esos filtros.
  El código y el nivel son de solo lectura. Sin permiso de actualizar en Plan de
  Cuentas la ficha se abre en modo consulta.
- **1.4** — Cuando el asiento de un ingreso o un egreso no se puede generar por
  cuentas sin configurar, el aviso dice **qué cuenta falta y en qué sección de
  Configuración Contable se asigna**, en lugar del genérico "El asiento no está
  cuadrado. Total Debe (0) no coincide con Total Haber". Los documentos que fallan
  por la misma causa se agrupan en un solo aviso.
- **1.3** — El control de asientos pendientes deja de avisar cuentas faltantes que
  no lo son: reconoce la cuenta configurada en Configuración Contable (no solo la
  del módulo de Opciones/Formas) y omite los conceptos cuya cuenta la define su
  propio módulo. Los avisos nombran ahora el concepto o la forma concreta, y los
  documentos sin formas de cobro/pago vigentes (p. ej. un egreso con todos sus
  cheques anulados) explican ese motivo en vez del genérico de configuración.
- **1.2** — El modal "Consolidado por RUC" agrega el Total General Consolidado:
  un solo Estado de Situación Financiera / Resultados por RUC, sin duplicar
  conceptos ya mapeados en Balances Consolidados (incluye el modo "cuenta
  única" para Capital y demás cuentas de Patrimonio que no deben sumarse).
- **1.1** — Se agregan las variantes "por periodos" (Estado de Resultados y
  Estado de Situación Financiera horizontales, una columna por mes), con
  exportación a PDF/Excel.
- **1.0** — Versión inicial.
