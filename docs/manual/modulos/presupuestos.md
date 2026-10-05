---
titulo: Presupuestos
resumen: Presupuesto de ingresos y gastos por cuenta contable y por mes, con versiones aprobadas y comparación automática contra lo realmente contabilizado.
categoria: Contabilidad
ruta_modulo: modulos/presupuestos
tipo: modulo
visibilidad: todos
etiquetas: presupuesto, presupuestos, presupuesto anual, presupuesto mensual, presupuesto vs real, ejecucion presupuestaria, presupuestado vs ejecutado, control presupuestario, reforma presupuestaria, version del presupuesto, aprobar presupuesto, acta, rubros, centro de costo, proyecto, semaforo, cuanto llevamos gastado, nos pasamos del presupuesto, plan de gastos, proyeccion de gastos, cargar presupuesto excel
version: 1.0
orden: 0
estado: activo
---

**Presupuestos** registra cuánto se espera ingresar y gastar en cada cuenta contable, mes a
mes, y lo compara solo con lo que la contabilidad va registrando. Sirve para toda la empresa, para
un centro de costo o para un proyecto, y guarda cada versión aprobada (la original y sus reformas)
con su acta.

## Qué es y para qué sirve

- Saber **cuánto llevamos gastado frente a lo aprobado**, por cuenta, por rubro y por mes, con un
  semáforo que avisa cuando una partida se acerca o se pasa.
- Guardar el presupuesto **tal como se aprobó**: si a mitad de año cambia, se crea una reforma y
  la original queda intacta para comparar.
- Lo ejecutado **no se digita**: sale de los asientos que ya registran Compras, Ventas, Nómina,
  Ingresos y Egresos. Si la contabilidad está al día, la ejecución también.
- Los condominios lo usan además como base para calcular las alícuotas (ver
  [Suscripciones](modulos/suscripciones) y, cuando exista, el módulo Condominios).

## Requisitos previos

- Plan de cuentas con las cuentas de **ingresos (4)** y **costos y gastos (5 y 6)** que se van a
  presupuestar. Las cuentas de balance (activos, pasivos, patrimonio) no se presupuestan en esta
  versión.
- Para el alcance por centro de costo o proyecto, que las compras y los asientos se registren
  **con ese centro o proyecto en sus líneas**; si no, la ejecución de ese presupuesto sale en cero.

## Cómo se usa

1. Pulse **Nuevo**, escriba el nombre, elija el período (año, un mes o un rango de meses), el
   alcance y los umbrales del semáforo. Pulse **Guardar**: nace la versión **Original** en
   borrador.
2. En la pestaña **Presupuesto** agregue las cuentas con el buscador (código o nombre) y escriba
   los montos de cada mes. Si escribe el **Total** de la fila, se reparte en partes iguales entre
   los meses. Asigne un **rubro** a cada cuenta si quiere agruparlas en los informes. Pulse
   **Guardar presupuesto**.
3. Cuando esté listo, **Aprobar** (requiere Acceso total): indique el acta o documento. La versión
   queda congelada y pasa a ser la **vigente**.
4. La pestaña **Ejecución** compara la vigente con lo contabilizado. Un clic en una cifra
   ejecutada muestra los asientos que la forman.
5. Si hay que cambiarlo: **Reforma** crea una versión nueva en borrador copiando la vigente; se
   edita y se aprueba con su motivo. La anterior queda como *reemplazada* y se puede consultar en
   **Versiones**.

### Herramientas para armar la grilla

- **Copiar de…**: trae las cuentas y montos de otro presupuesto o versión, con un ajuste % (por
  ejemplo, el del año pasado + 5 %). Los meses se alinean por posición.
- **Desde lo ejecutado…**: toma las cuentas con movimiento real en un período anterior y arma el
  presupuesto a partir de ese gasto, con un ajuste %.
- **Plantilla** y **Cargar Excel**: descargue la plantilla (una fila por cuenta, una columna por
  mes), llénela y súbala. Lo que se lee aparece en la grilla para revisar; se graba al pulsar
  **Guardar presupuesto**. Los rubros que no existen se crean.
- **Rubros**: crear, renombrar o eliminar los rubros de la empresa.

Ninguna de estas herramientas graba por sí sola: reemplazan lo que hay en la grilla y hay que
pulsar **Guardar presupuesto**.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre | Sí | Cómo se llama el presupuesto («Presupuesto 2027», «Remodelación lobby»). |
| Período | Sí | **Anual** (los 12 meses de un año), **Un mes** o **Personalizado** (de un mes a otro, hasta 60 meses). El detalle siempre es mes a mes. |
| Alcance | Sí | **Toda la empresa**, un **centro de costo** o un **proyecto**. Con centro o proyecto, la ejecución solo toma los asientos que llevan ese dato. |
| Aviso desde % | Sí | Porcentaje de ejecución desde el que el semáforo se pone **amarillo** (por defecto 90). |
| Exceso desde % | Sí | Porcentaje desde el que se pone **rojo** (por defecto 100). |
| Observaciones | No | Notas internas. |
| Cuenta (grilla) | Sí | Cuenta de ingresos, costos o gastos. Puede ser una **cuenta de grupo**: al ejecutar suma todas sus subcuentas. |
| Rubro (grilla) | No | Nombre amigable que agrupa cuentas en los informes (Guardianía, Servicios básicos…). |
| Montos por mes | Sí | Monto presupuestado de cada mes, sin signo. |

Con una versión aprobada, el **período y el alcance quedan fijos**; se pueden cambiar el nombre,
los umbrales y las observaciones.

## Ejecución: cómo se compara

- **Ejecutado** = movimiento neto de la cuenta en los asientos vivos de la empresa (en su ambiente),
  entre las fechas del presupuesto: en ingresos, haber − debe; en costos y gastos, debe − haber.
- Si se presupuesta una **cuenta de grupo** y también una de sus subcuentas, el movimiento de esa
  subcuenta se cuenta una sola vez, en la subcuenta.
- **Semáforo** (gastos): verde por debajo del umbral de aviso, amarillo entre aviso y exceso, rojo
  desde el exceso. En **ingresos** se invierte: lo malo es no llegar, así que un mes ya cerrado por
  debajo del 100 % se marca. Los **meses futuros** no se evalúan.
- Vistas: **por cuenta y mes** (presupuesto y ejecutado de cada mes) o **por rubro**.
- **PDF** y **Excel** del presupuesto (barra superior del modal) incluyen la grilla de la versión
  mostrada y, si hay aprobada, la ejecución.

## Versiones

| Estado | Qué significa |
|--------|---------------|
| Borrador | Se puede editar. Solo hay un borrador a la vez: la Original antes de aprobar, o la reforma en curso. |
| Aprobada | Congelada, con acta, fecha y quién aprobó. Es la **vigente** (la última aprobada). |
| Reemplazada | Fue vigente y una reforma posterior la sustituyó. Se puede ver y exportar. |

Una reforma en borrador se puede **descartar**; una aprobada, nunca. La Original tampoco se
descarta: si no sirve, se elimina el presupuesto completo (solo mientras nada esté aprobado).

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Consultar presupuestos, ejecución, versiones, PDF y Excel. |
| Crear | Crear presupuestos y armar su grilla. |
| Actualizar | Editar datos y grilla de borradores, crear reformas, cerrar y reabrir. |
| Eliminar | Eliminar presupuestos nunca aprobados, descartar reformas en borrador, eliminar rubros. |
| Acceso total | **Aprobar** versiones (decisión de la empresa: aprobar no es editar). Ver los presupuestos de toda la empresa; sin él, solo los propios. |

## Reglas de negocio

- Solo cuentas de ingresos (4), costos y gastos (5 y 6); una cuenta no se repite en la misma
  versión; los montos no son negativos; los meses fuera del período se ignoran.
- Aprobar exige un acta o documento. Al aprobar, las aprobadas anteriores pasan a reemplazadas y el
  presupuesto queda **aprobado**.
- Un presupuesto **cerrado** no se edita ni se reforma; se puede reabrir.
- Un presupuesto con alguna versión aprobada **no se elimina**: se cierra.
- Todo queda en `log_sistema`: creación, cambios de datos, guardado de grilla, aprobaciones,
  reformas, cierres y rubros.

## Integraciones con otros módulos

- **Asientos contables**: fuente de lo ejecutado (Compras, Ventas, Nómina, Ingresos, Egresos…).
- **Centros de costo** y **Proyectos**: alcance del presupuesto.
- **Suscripciones / Condominios**: la marca *base de alícuotas* queda reservada para calcular las
  cuotas de los condóminos desde el presupuesto aprobado.

## Errores frecuentes

- **La ejecución sale en cero con alcance por centro de costo o proyecto**: los asientos no llevan
  ese dato en sus líneas. Regístrelo en las compras y asientos, o use alcance *Toda la empresa*.
- **«Ya hay una reforma en borrador»**: apruébela o descártela antes de crear otra.
- **No aparece el botón Aprobar**: requiere Acceso total en este módulo, o que el presupuesto
  tenga una versión en borrador y no esté cerrado.
- **Los encabezados del Excel no coinciden**: cada presupuesto tiene su plantilla (una columna por
  mes de su período). Descárguela desde ese presupuesto.
- **Un mes aparece gris en el semáforo**: es un mes futuro o no tiene presupuesto ni ejecución.

## Historial de cambios

- **1.0** — Versión inicial: presupuestos por año, mes o período; alcance por empresa, centro de
  costo o proyecto; versiones con aprobación y reformas; ejecución desde los asientos con
  semáforo; copiar de otra versión, partir de lo ejecutado y carga por Excel; PDF y Excel.
