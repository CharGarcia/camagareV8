---
titulo: Vacaciones
resumen: Días de vacaciones a los que tiene derecho cada empleado, los que ya gozó y su saldo.
categoria: Nómina
ruta_modulo: modulos/vacaciones
tipo: modulo
visibilidad: todos
etiquetas: vacaciones, dias de vacaciones, descanso, saldo de vacaciones, antiguedad, gozadas, periodo vacacional, buscar vacaciones, buscador, filtros, filtrar vacaciones, vacaciones por empleado, chips
version: 1.1
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

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la vacación: empleado,
identificación, desde, hasta (tal como se ven, por ejemplo *07-07-2026*), días y
valor. Además busca en la observación, en el mes del rol al que se imputa (por
ejemplo *Julio 2026*) y en el usuario que la registró. La columna **Estado** no
entra en la búsqueda libre: para filtrar por ella use la ventana de filtros.
Puede escribir varias palabras en cualquier orden y no importan mayúsculas ni
tildes. Para limpiar, borre el texto o pulse Escape en el cuadro. Mientras
busca, aparece un **círculo girando** al final del cuadro y la tabla se ve
atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La
ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Vacación | Desde (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), hasta, mes y año del rol, estado (registrado, pagado, anulado) y si afecta al rol |
| Valores | Días gozados, días de derecho y valor (cada uno con mínimo y máximo) |
| Empleado | Empleado, identificación, observación y usuario que registró |

Los selectores *Año del rol* y *Usuario que registró* listan solo lo que la
empresa ya usó.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

> Quien no tenga **acceso total** solo ve las vacaciones que registró él mismo.

## Errores frecuentes

- **"La fecha hasta no puede ser anterior a la fecha desde"**: invirtió las
  fechas.
- **El saldo de días no es el que esperaba**: revise la fecha de ingreso del
  empleado, que es la base del cálculo de antigüedad.

## Historial de cambios

- **1.1** — Nuevo buscador del listado: búsqueda libre en todas las columnas
  (sin Estado) y botón embudo con la ventana de filtros; se suman los filtros de
  afecta al rol, días de derecho, identificación, observación y usuario que
  registró. Los filtros activos se ven como chips dentro del cuadro.
- **1.0** — Versión inicial.
