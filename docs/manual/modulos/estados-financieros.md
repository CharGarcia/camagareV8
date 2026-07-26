---
titulo: Estados financieros
resumen: Estado de resultados y estado de situación financiera a partir de los asientos contables.
categoria: Contabilidad
ruta_modulo: modulos/estados_financieros
tipo: modulo
visibilidad: todos
etiquetas: estados financieros, balance, estado de resultados, situacion financiera, perdidas y ganancias, activo pasivo patrimonio
version: 1.0
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

- **1.0** — Versión inicial.
