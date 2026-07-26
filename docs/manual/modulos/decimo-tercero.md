---
titulo: Décimo tercero
resumen: Declaración del décimo tercer sueldo, su cálculo, el archivo para el Ministerio y su pago.
categoria: Nómina
ruta_modulo: modulos/decimo-tercero
tipo: modulo
visibilidad: todos
etiquetas: decimo tercero, decimo, bono navideno, declaracion, ministerio de trabajo, acumulado, mensualizado
version: 1.0
orden: 50
estado: activo
---

Este módulo prepara la declaración del **décimo tercer sueldo**: calcula lo que
corresponde a cada empleado, genera el archivo para el Ministerio y permite
registrar su pago.

## El recorrido

1. **Cree la declaración** del año.
2. **Calcule**: el sistema determina el valor de cada empleado según la base de
   cálculo elegida.
3. **Revise** los valores empleado por empleado.
4. **Exporte** el archivo para el Ministerio.
5. **Pague**: los pagos se registran como egresos.

El orden importa: **no se puede exportar sin haber calculado antes**.

## Tipos de pago

Cada empleado se marca con el tipo que le corresponde:

| Código | Significado |
|--------|-------------|
| P | Pagado |
| A | Acumulado |
| RP | Reingreso pagado |
| RA | Reingreso acumulado |

Cualquier otro valor se rechaza.

## Una vez pagado, no se recalcula

Esta es la regla que más sorprende: **si ya hay egresos registrados sobre la
declaración, no se puede recalcular ni anular**.

Es deliberado. Recalcular cambiaría valores que ya se pagaron y dejaría los
egresos apuntando a cifras que dejaron de existir. Si de verdad hay que corregir,
primero se anulan los pagos.

## Errores frecuentes

- **"Ya se registraron pagos (Egresos) sobre esta declaración; no se puede
  recalcular"**: anule primero los egresos.
- **"Calcule la declaración antes de exportar el archivo"**: falta el paso 2.
- **"El tipo de pago no es válido"**: use P, A, RP o RA.
- **Un empleado no aparece**: compruebe que esté activo y con su fecha de ingreso
  registrada.

## Historial de cambios

- **1.0** — Versión inicial.
