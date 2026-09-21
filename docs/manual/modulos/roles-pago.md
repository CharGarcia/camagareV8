---
titulo: Roles de pago
resumen: Cálculo del sueldo de cada empleado en un periodo, con sus ingresos y descuentos.
categoria: Nómina
ruta_modulo: modulos/roles-pago
tipo: modulo
visibilidad: todos
etiquetas: rol de pago, roles, nomina, sueldo, quincena, semanal, mensual, pago de empleados, descuentos, liquido a recibir, neteo, ingresos de quincena, bono en quincena, horas extra en quincena, observacion, observaciones, detalle de novedad, motivo del descuento, asiento contable, contabilizacion, cuentas de nomina, prestamo quirografario, prestamo hipotecario, prestamo empresa, prestamos iess, aporte iess, base del iess, con iess, sin iess, bonos, comisiones, horas extra, dias no laborados, faltas, dias laborados, sueldo ganado, fondos de reserva, decimo tercero, decimo cuarto, buscar rol de pago, buscador, filtros, filtrar roles, buscar empleado en el rol, buscar rubro, chips, ordenar, ordenamiento, ordenar por periodo, ordenar columnas, orden del listado, periodo mas reciente
version: 1.9
orden: 30
estado: activo
---

El **rol de pago** calcula lo que cobra cada empleado en un periodo: el sueldo,
más lo que suman las novedades a favor, menos los descuentos.

## Tipos de rol

Un mismo módulo cubre los tres ritmos de pago:

| Tipo | Periodo | Dato adicional |
|------|---------|----------------|
| Mensual | Un mes completo | — |
| Quincenal | Media quincena | **Quincena**: 1 o 2 |
| Semanal | Una semana | **Semana**: de 1 a 5 |

El mes debe estar entre 1 y 12, y el año ser válido.

## Neteo entre roles

Cuando se paga por quincenas o semanas, lo ya entregado en el periodo se
descuenta del siguiente rol: el sistema **netea** para que el empleado no cobre
dos veces lo mismo. Por eso el orden de generación importa: primero la quincena
1, después la 2.

En la **quincena** y la **semana** solo hay **ingresos** (con o sin IESS) y
**descuentos**. Los **días no laborados** y el **aviso de salida** afectan
siempre al rol **mensual**, aunque al registrarlos se elija quincena o semana.

Al cerrar el mes, el rol mensual:

- **resta** lo que ya se pagó en las quincenas o semanas del mes (*Neteo
  semanas/quincenas del mes*) y los descuentos que se aplicaron en ellas;
- **vuelve a sumar** los ingresos que esas corridas pagaron además de su base
  (horas, otros ingresos, rubros fijos quincenales). Aparecen con el nombre de la
  corrida, por ejemplo *"Otros Ingresos — Quincena 1"*. Así el mensual no los
  descuenta, y los que aportan al IESS entran a la base del mes (en la quincena
  no se calcula IESS).

Ejemplo con sueldo de 480, quincena de 240 y un bono de 50 con IESS registrado
para la quincena:

| Rol | Concepto | Valor |
|-----|----------|-------|
| Quincena 1 | Quincena + bono | 290,00 (pagado) |
| Mensual | Sueldo | 480,00 |
| Mensual | Otros Ingresos — Quincena 1 | 50,00 |
| Mensual | Aporte IESS (9,45% de 530) | −50,09 |
| Mensual | Neteo semanas/quincenas del mes | −290,00 |
| Mensual | Neto a pagar | 189,91 |

En el mes el empleado recibe 290,00 + 189,91 = 479,91: el sueldo más el bono,
menos el IESS de ambos.

Solo se netean las quincenas y semanas que **ya tienen un pago** registrado en
Egresos; si una quincena todavía no se pagó, sus ingresos se cobran cuando se
pague esa quincena.

## De dónde salen las cifras

- El **sueldo** viene de la ficha del empleado.
- Los **ingresos y descuentos variables** vienen de las **novedades** del periodo:
  horas extra, faltas, anticipos, préstamos.
- Las **vacaciones** gozadas en el periodo se reflejan también.

Por eso, antes de generar un rol conviene revisar que todas las novedades del
periodo estén registradas: lo que no esté cargado, no se paga ni se descuenta.

## Qué suma a la base del IESS

El aporte personal y el patronal se calculan solo en el rol **mensual** y solo
si el empleado aporta al IESS (pestaña *Laboral* de su ficha). Suman a la base:

- el **sueldo** del mes, menos los **días no laborados** (ver más abajo);
- las **horas** nocturnas, suplementarias y extraordinarias, y los **Otros
  Ingresos** (bonos, comisiones), cuando la novedad está marcada **Aporta IESS:
  Sí**;
- los **rubros fijos** de ingreso marcados con IESS en la ficha del empleado;
- las **vacaciones** que se pagan en el rol.

Lo que no suma aparece en el rol con *(sin IESS)*, por ejemplo *"Otros Ingresos
(sin IESS)"*. Esa misma base es la de las provisiones (décimo tercero,
vacaciones, fondos de reserva) y la del Impuesto a la Renta. Los roles en estado
*generado* se recalculan solos al cambiar la marca de una novedad.

## Días no laborados

En el rol **mensual**, los días no laborados (novedad *Días no laborados*)
restan del **sueldo ganado**: aparecen en la columna de ingresos con valor
negativo, junto al sueldo. Por ejemplo, con sueldo de 500 y 3 días no laborados:

| Concepto | Valor |
|----------|-------|
| Sueldo | 500,00 |
| Días no laborados (3d) | −50,00 |
| Sueldo de los días laborados | 450,00 |

Sobre esos 450 se calculan el **aporte al IESS** (42,53 en lugar de 47,25), el
**Impuesto a la Renta**, los **fondos de reserva** y el **décimo tercero** que se
pagan en el rol, las **provisiones** y la base del módulo *Décimo Tercero*. El
**décimo cuarto** no cambia: se sigue calculando por los días de contrato del
mes, igual que en el módulo *Décimo Cuarto*.

Los días no laborados se aplican **siempre en el rol mensual**, aunque al
registrarlos se elija quincena o semana.

## Ficha del empleado dentro del rol

Al abrir el detalle de un empleado en una corrida ya generada, el encabezado del
modal tiene un botón rojo para el **PDF** del rol individual y, junto a él, uno
verde para el **Excel**: una tabla Concepto/Ingreso/Egreso con los mismos
rubros del PDF, más el neto a recibir.

En el desglose de ingresos y egresos, cuando el rubro proviene de una novedad
(hora extra, anticipo, cuota de préstamo, falta, etc.) se muestra debajo del
concepto la **observación** que se escribió al registrar esa novedad, en letra
pequeña. Así se ve el motivo sin salir del rol. Si la novedad se guardó sin
observación, no aparece nada adicional. Lo mismo se imprime en el **PDF** del
rol individual del empleado.

En los **Excel** (el del rol completo y el de la ficha individual) la observación
se agrega dentro de la misma celda del concepto, separada por un guion:
`Horas extra 50% (6h) — cobertura del feriado`. Aplica tanto a la hoja principal
como a las hojas *Novedades* y *Otros Detalles*.

## Asiento contable del rol mensual

Solo el rol **mensual** se contabiliza; las quincenas y semanas se netean dentro
de él. Cada rubro va a la cuenta de su concepto en **Configuración Contable →
Nómina**, o a la cuenta propia del empleado si tiene una en las *Reglas por
Empleado*.

Las cuotas de préstamo descontadas en el rol mensual salen en su propia línea
cuando su concepto tiene cuenta:

| Novedad | Concepto en Configuración Contable | Naturaleza |
|---------|------------------------------------|------------|
| Préstamo Quirografario | Préstamos Quirografarios por Pagar | Pasivo |
| Préstamo hipotecario | Préstamos Hipotecarios por Pagar | Pasivo |
| Préstamo Empresa | Préstamos Empresa por Cobrar | Activo |

Esas tres cuentas son **opcionales**: si el concepto queda sin cuenta, la cuota se
contabiliza en **Descuentos**, como antes. Siguen yendo a Descuentos, aunque el
concepto tenga cuenta, las cuotas cargadas como rubro fijo de descuento en la
ficha del empleado y las descontadas en una quincena o semana (llegan al mensual
dentro de *Descuentos aplicados en quincenas/semanas del mes*).

Cambiar la configuración no modifica los asientos ya generados: las cuentas
nuevas se usan en los roles que se contabilicen desde ese momento.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la corrida: período
(por ejemplo *Julio 2026* o *Julio 2026 #2*), número de empleados y neto.
Además busca en la descripción, la fecha de pago, el usuario que creó la
corrida y en el **nombre e identificación de los empleados incluidos** en el
rol. Las columnas **Tipo** y **Estado** no entran en la búsqueda libre: para
filtrar por ellas use la ventana de filtros. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el
texto o pulse Escape en el cuadro. Mientras busca, aparece un **círculo
girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Rol** (datos de la corrida):

| Bloque | Filtros |
|--------|---------|
| Corrida | Fecha de pago (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), mes y año del período, tipo de rol (mensual, quincena o semanal), estado (borrador, generado, pagado, contabilizado, anulado), con o sin asiento contable, corrida (tipo y período, por ejemplo *Rol Mensual Julio 2026*), descripción y usuario que registró |
| Valores | Neto, número de empleados, total de ingresos, total de egresos y aporte patronal (cada uno con mínimo y máximo) |
| Empleado | Empleado incluido e identificación: muestra las corridas donde aparece ese empleado |

Los selectores *Año del período* y *Usuario que registró* listan solo lo que la
empresa ya usó.

**Pestaña Detalles** (lo que hay dentro de cada corrida). Es un único cuadro,
**Buscar libremente dentro de los roles de pago**: escriba un empleado, una
identificación, un cargo, un rubro (*Sueldo*, *Horas suplementarias*, *IESS*,
*Transporte*…), un valor o la observación de la novedad que originó un rubro, y
aparece la lista de **cada línea de empleado o rubro que coincide** con la
corrida a la que pertenece (tipo y período, fecha de pago y estado). Un clic en
la fila deja el listado mostrando solo esa corrida; el ícono de la derecha la
abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

> Quien no tenga **acceso total** solo ve las corridas que creó él mismo, también
> en la pestaña Detalles.

## Ordenar el listado

Al entrar, el listado muestra **primero el período más reciente**: el rol de
agosto de 2026 va arriba del de julio, y dentro de un mismo mes la quincena 2 va
arriba de la quincena 1.

**Todas las columnas ordenan.** Un clic en el título de cualquiera —*Tipo*,
*Período*, *Empleados*, *Neto* o *Estado*— ordena por ella; otro clic invierte la
dirección. La flecha del encabezado indica el orden vigente.

**Varias columnas a la vez.** Con **Shift + clic** en un segundo encabezado se
encadena otro criterio, hasta **tres**: por ejemplo *Tipo* y, dentro de cada
tipo, el *Período* más reciente. Un número pequeño junto a la flecha indica la
prioridad de cada columna. El tercer Shift + clic sobre una columna la saca del
orden.

El orden elegido **se guarda para cada usuario** y se conserva al volver al
módulo. Para regresar al orden de fábrica, ordene por *Período* de mayor a menor.

## Errores frecuentes

- **"La quincena debe ser 1 o 2"** / **"La semana debe estar entre 1 y 5"**:
  revise el periodo elegido.
- **Falta una hora extra en el rol**: la novedad está imputada a otro mes o año.
- **El líquido no coincide con lo esperado**: compare con las novedades del
  periodo; casi siempre es una novedad no registrada o imputada al periodo
  equivocado.
- **La cuota de un préstamo sigue saliendo en Descuentos**: su concepto no tiene
  cuenta en Configuración Contable → Nómina, o la cuota se descontó en una
  quincena o semana en lugar del rol mensual.

## Historial de cambios

- **1.9** — **El listado abre por el período más reciente** y **todas las
  columnas ordenan** (*Tipo*, *Período*, *Empleados*, *Neto* y *Estado*; antes
  solo tres). Con **Shift + clic** se ordena por hasta tres columnas a la vez.

- **1.8** — **Un rol al que le queda $0.01 sigue pendiente.** Antes ese
  centavo lo marcaba como pagado: el rol no aparecía en el pago por lote y su
  estado salía *Pagado*. Ahora cuenta como **Parcial** y se puede terminar de
  pagar, igual que en *Egresos*.


- **1.7** — El rol mensual vuelve a sumar los ingresos pagados en las quincenas y semanas del mes (antes el neteo los descontaba a fin de mes) y los que aportan al IESS entran a la base del mes. En quincena y semana solo hay ingresos y descuentos: los días no laborados y el aviso de salida van siempre al rol mensual.
- **1.6** — En el rol mensual los días no laborados restan del sueldo ganado (ingreso negativo): bajan el aporte al IESS, el Impuesto a la Renta, las provisiones y la base del décimo tercero, y los fondos de reserva y el décimo tercero pagados en el rol se calculan sobre los días laborados. El décimo cuarto sigue por días de contrato.
- **1.5** — Las horas y los Otros Ingresos suman a la base del IESS según la marca *Aporta IESS* de la novedad (antes las horas siempre sumaban y Otros Ingresos nunca). Lo que va sin IESS se señala en el concepto del rubro.
- **1.4** — Nuevo buscador del listado: búsqueda libre en todas las columnas (sin Tipo ni Estado) y en los empleados incluidos, botón embudo con la ventana de filtros (se suman asiento, corrida, descripción, usuario, totales, número de empleados y empleado incluido) y pestaña Detalles para buscar dentro de las líneas de empleado y los rubros. Los filtros activos se ven como chips dentro del cuadro.
- **1.3** — El asiento del rol mensual lleva las cuotas de préstamo quirografario, hipotecario y empresa a su propia cuenta cuando está configurada en Configuración Contable → Nómina.
- **1.2** — El desglose de ingresos/egresos del empleado (modal, PDF individual y Excel) muestra la observación de la novedad que originó cada rubro.
- **1.1** — Botón para exportar a Excel la ficha individual del empleado, junto al de PDF.
- **1.0** — Versión inicial.
