---
titulo: Estados financieros
resumen: Estado de resultados y estado de situación financiera a partir de los asientos contables.
categoria: Contabilidad
ruta_modulo: modulos/estados_financieros
tipo: modulo
visibilidad: todos
etiquetas: estados financieros, balance, estado de resultados, situacion financiera, perdidas y ganancias, activo pasivo patrimonio, reportes por periodos, comparativo mensual, horizontal por mes
version: 1.4
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
