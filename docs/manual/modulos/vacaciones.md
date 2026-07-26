---
titulo: Vacaciones
resumen: Días de vacaciones a los que tiene derecho cada empleado, los que ya gozó y su saldo.
categoria: Nómina
ruta_modulo: modulos/vacaciones
tipo: modulo
visibilidad: todos
etiquetas: vacaciones, dias de vacaciones, descanso, saldo de vacaciones, antiguedad, gozadas, periodo vacacional
version: 1.0
orden: 40
estado: activo
---

El módulo de **Vacaciones** lleva la cuenta de los días que le corresponden a
cada empleado, los que ya tomó y los que le quedan.

## Días de derecho

El derecho crece con la antigüedad: la base son **15 días por año** y, a partir
del quinto año de trabajo, se suma **un día más por cada año adicional**, con un
**tope de 30 días**.

El sistema calcula el derecho a partir de la fecha de ingreso del empleado, así
que esa fecha tiene que estar bien en su ficha.

## Registrar días gozados

1. Pulse **Nuevo**.
2. Elija el **empleado**.
3. Indique **desde** y **hasta**.
4. Revise los **días gozados** calculados.
5. Indique el **mes del rol** en el que se refleja.
6. Guarde.

## Validaciones

| Regla | Detalle |
|-------|---------|
| Empleado | Obligatorio |
| Fechas desde y hasta | Ambas obligatorias |
| Orden de fechas | *Hasta* no puede ser anterior a *desde* |
| Días gozados | Mayores a cero |
| Mes del rol | Entre 1 y 12 |

## Relación con el rol de pago

Las vacaciones registradas se reflejan en el rol del mes indicado. Por eso el
campo *mes del rol* importa: unas vacaciones tomadas a fin de mes pueden
liquidarse en el rol del mes siguiente.

## Errores frecuentes

- **"La fecha hasta no puede ser anterior a la fecha desde"**: invirtió las
  fechas.
- **El saldo de días no es el que esperaba**: revise la fecha de ingreso del
  empleado, que es la base del cálculo de antigüedad.

## Historial de cambios

- **1.0** — Versión inicial.
