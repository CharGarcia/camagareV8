---
titulo: Estados financieros
resumen: Estado de resultados y estado de situación financiera a partir de los asientos contables.
categoria: Contabilidad
ruta_modulo: modulos/estados_financieros
tipo: modulo
visibilidad: todos
etiquetas: estados financieros, balance, estado de resultados, situacion financiera, perdidas y ganancias, activo pasivo patrimonio, reportes por periodos, comparativo mensual, horizontal por mes
version: 1.1
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

## Si el balance no cuadra

Revise en este orden:

1. **Asientos pendientes** de generar.
2. **Periodos** correctos: que el rango de fechas sea el que cree.
3. El **resultado del ejercicio**: si el resultado del periodo no está cerrado
   contra patrimonio, el balance puede mostrar un descuadre que en realidad es la
   utilidad acumulada del propio ejercicio.

## Errores frecuentes

- **Faltan movimientos del mes**: hay asientos pendientes; acéptelos al abrir.
- **La utilidad no coincide con lo esperado**: compare con el mayor de las
  cuentas de ingreso y gasto para ver qué documento falta o sobra.

## Historial de cambios

- **1.1** — Se agregan las variantes "por periodos" (Estado de Resultados y
  Estado de Situación Financiera horizontales, una columna por mes), con
  exportación a PDF/Excel.
- **1.0** — Versión inicial.
