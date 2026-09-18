---
titulo: Vacaciones
resumen: Días de vacaciones a los que tiene derecho cada empleado, los que ya gozó y su saldo.
categoria: Nómina
ruta_modulo: modulos/vacaciones
tipo: modulo
visibilidad: todos
etiquetas: vacaciones, dias de vacaciones, descanso, saldo de vacaciones, antiguedad, gozadas, periodo vacacional, buscar vacaciones, buscador, filtros, filtrar vacaciones, vacaciones por empleado, chips, periodos, periodos de vacaciones, periodos tomados, periodos pagados, vacaciones pagadas, vacaciones no gozadas, vacaciones acumuladas, saldo inicial de vacaciones, ajustar saldo, empleados de otro sistema, migracion de empleados, vacaciones al dia
version: 1.2
orden: 40
estado: activo
---

El módulo de **Vacaciones** lleva la cuenta de los días que le corresponden a
cada empleado, los que ya tomó y los que le quedan.

## Días de derecho

El derecho crece con la antigüedad: la base son **15 días por año** y, pasados
los cinco primeros años, se suma **un día más por cada año adicional** (16 días
el sexto año, 17 el séptimo…), con un **tope de 30 días**.

El sistema calcula el derecho a partir de la fecha de ingreso del empleado, así
que esa fecha tiene que estar bien en su ficha.

Al elegir un empleado, el panel de saldo muestra:

| Dato | Qué es |
|------|--------|
| Antigüedad | Tiempo desde la fecha de ingreso, y los días de derecho del año en curso |
| Derecho acumulado | Todos los días ganados desde el ingreso, con la parte proporcional del año en curso |
| Tomados/pagados antes | Días de los períodos marcados como ya tomados o pagados antes de usar el sistema |
| Gozados en el sistema | Días de las vacaciones registradas en este módulo (sin las anuladas) |
| Saldo pendiente | Derecho acumulado − tomados/pagados antes − gozados en el sistema |

## Períodos ya tomados o pagados antes del sistema

Cuando un empleado viene de otro sistema con varios años en la empresa, el
sistema le calcula todo el derecho desde su fecha de ingreso y el saldo sale
inflado. Para dejarlo al día, marque los **períodos** (años de trabajo) que el
empleado ya **tomó** o **cobró** antes de usar el sistema: sus días dejan de
contar en el saldo.

### Cómo marcarlos

1. Pulse **Períodos** (arriba del listado). El mismo cuadro aparece en
   **Nueva** al elegir el empleado, y en el módulo **Empleados**, en la pestaña
   **Vacaciones** de la ficha del empleado.
2. Elija el **empleado**. Se muestra un cuadro con todos sus períodos, del más
   antiguo al más nuevo.
3. Marque la casilla de cada período ya tomado o pagado. Puede marcar varios a
   la vez, o todos con la casilla del encabezado.
4. En **Antes del sistema** aparecen los días del período completo. Si solo tomó
   una parte (por ejemplo 10 de 15), cambie el número.
5. Si quiere, escriba una **observación** (por ejemplo *Tomadas en el sistema
   anterior*).
6. Pulse **Marcar como tomados** o **Marcar como pagados** y confirme.

Marcar un período no genera ningún valor ni toca el rol de pagos: solo ajusta el
saldo de días.

### Qué muestra el cuadro

| Columna | Qué significa |
|---------|---------------|
| # | Número del período: 1 es el primer año de trabajo |
| Desde / Hasta | Fechas del año de trabajo, contadas desde la fecha de ingreso |
| Derecho | Días que da ese año. En el año en curso muestra lo acumulado hasta hoy y el total (por ejemplo *7,5 de 16*) |
| Antes del sistema | Días marcados como tomados o pagados antes de usar el sistema |
| En el sistema | Días de las vacaciones registradas en este módulo que se descuentan de ese período |
| Pendiente | Días que al empleado todavía le quedan de ese período |
| Situación | Pendiente, Parcial, Tomado o Pagado antes del sistema, Gozado en el sistema o En curso |

Las vacaciones registradas en el sistema se descuentan del **período pendiente
más antiguo** que no esté marcado. Por eso, si registró vacaciones antes de
marcar los períodos viejos, esas vacaciones pueden aparecer cubriendo el primer
año: marque igual los períodos que el empleado ya tomó antes, aunque se vean
como *Gozado en el sistema*, y esos días pasarán solos al período que sigue.

El **año en curso** no se puede marcar hasta que se complete. Si el empleado
gozó más días de los que lleva acumulados, el cuadro lo avisa como días gozados
por adelantado y el saldo queda en negativo.

### Quitar una marca

Pulse la flecha circular al final de la fila y confirme. Los días de ese período
vuelven a contar en el saldo. Para cambiar los días de un período ya marcado,
quite la marca y vuelva a marcarlo.

Si después de marcar se corrige la **fecha de ingreso** del empleado, los
períodos marcados con las fechas anteriores muestran un aviso: revíselos y
quite la marca de los que ya no correspondan.

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

## Permisos

| Acción | Permiso que necesita |
|--------|----------------------|
| Ver el listado, el saldo y el cuadro de períodos | Ver |
| Registrar vacaciones y marcar períodos | Crear |
| Quitar la marca de un período | Eliminar. Sin **acceso total**, solo las marcas que hizo el propio usuario |

## Errores frecuentes

- **"La fecha hasta no puede ser anterior a la fecha desde"**: invirtió las
  fechas.
- **El saldo de días no es el que esperaba**: revise la fecha de ingreso del
  empleado, que es la base del cálculo de antigüedad, y que los períodos que ya
  tomó o cobró antes de usar el sistema estén marcados.
- **"El período ya está marcado"**: otro usuario lo marcó mientras usted tenía
  la ventana abierta. Cierre y vuelva a elegir al empleado.
- **"El período todavía está en curso"**: solo se marcan años de trabajo
  completos.
- **"El empleado no tiene fecha de ingreso"**: regístrela en su ficha (módulo
  Empleados); sin ella no hay períodos que mostrar.

## Historial de cambios

- **1.2** — Períodos ya tomados o pagados antes del sistema: al elegir un
  empleado se ve el cuadro de sus años de trabajo y se marcan los que ya gozó o
  cobró (completos o en parte), para dejar al día el saldo de quienes vienen de
  otro sistema. Nuevo botón **Períodos** en el listado, y el mismo cuadro en la
  pestaña **Vacaciones** de la ficha del empleado (módulo Empleados). El panel de
  saldo muestra ahora el derecho acumulado y los días tomados/pagados antes.

- **1.1** — Nuevo buscador del listado: búsqueda libre en todas las columnas
  (sin Estado) y botón embudo con la ventana de filtros; se suman los filtros de
  afecta al rol, días de derecho, identificación, observación y usuario que
  registró. Los filtros activos se ven como chips dentro del cuadro.
- **1.0** — Versión inicial.
