---
titulo: Balance de comprobación
resumen: Debe, haber y saldo de cada cuenta contable en un rango de fechas, con validación de cuadre.
categoria: Contabilidad
ruta_modulo: modulos/balance-comprobacion
tipo: modulo
visibilidad: todos
etiquetas: balance de comprobacion, balance de sumas y saldos, cuadre contable, debe haber, saldo deudor acreedor, comprobacion de saldos, sumas y saldos
version: 1.0
orden: 45
estado: activo
---

El **balance de comprobación** lista, para cada cuenta del plan de cuentas, el
total de **debe** y **haber** dentro de un rango de fechas y su **saldo neto**
(deudor o acreedor). Es la primera verificación de que la contabilidad del
periodo está cuadrada antes de armar los Mayores o los Estados Financieros.

A diferencia del Mayor (que muestra línea por línea cada movimiento), aquí cada
fila **es una cuenta** con sus totales ya sumados.

## Cómo se genera

1. Indique el **rango de fechas** (año/mes, o fecha desde-hasta libre).
2. Elija el **nivel** de agrupación: nivel 5 muestra cada cuenta de detalle;
   niveles 1 a 4 agrupan por cuenta mayor, sumando el movimiento de todas sus
   cuentas hijas.
3. Genere el informe.

Los valores mostrados corresponden **solo al movimiento dentro del rango
filtrado**: el reporte no calcula ni arrastra un saldo inicial de periodos
anteriores (igual criterio que Mayores y Estados Financieros).

## El cuadre

El sistema valida que la suma de **debe** sea igual a la suma de **haber** de
todas las cuentas y muestra un aviso:

- ✅ **El balance cuadra.**
- ⚠️ **El balance no cuadra**: revise si hay asientos pendientes de generar, si
  el rango de fechas es el correcto, o si existe un asiento descuadrado.

## Asientos pendientes

Al abrir el módulo, si hay documentos sin su asiento contable generado, el
sistema **pregunta** si desea generarlos antes de continuar. Conviene aceptar:
un balance calculado con asientos pendientes queda incompleto.

## Errores frecuentes

- **El balance no cuadra**: casi siempre son asientos pendientes de generar;
  acepte la generación al abrir el módulo. Si ya se generaron, revise el Mayor
  de las cuentas con saldo inusual para ubicar el asiento descuadrado.
- **Una cuenta con movimiento no aparece**: verifique el **nivel** elegido; si
  seleccionó un nivel agrupador (1-4) y la cuenta no tiene movimiento propio
  directo, solo se ve reflejada dentro del saldo de su cuenta padre.

## Historial de cambios

- **1.0** — Versión inicial.
