---
titulo: Balances Consolidados
resumen: Mapea manualmente qué cuenta de cada establecimiento del mismo RUC es el mismo concepto, para verlo sumado en Estados Financieros y Balance de Comprobación.
categoria: Contabilidad
ruta_modulo: modulos/balances-consolidados
tipo: modulo
visibilidad: todos
etiquetas: consolidado, RUC, establecimientos, sucursales, balance general, estado de resultados, grupo de cuentas, matriz, plan de cuentas, multiempresa
version: 1.0
orden: 0
estado: activo
---

Este módulo configura, para las empresas que comparten RUC (varios establecimientos
registrados como filas separadas de `empresas`), qué cuenta del plan de cuentas de
cada una representa el mismo concepto contable — para que **Estados Financieros** y
**Balance de Comprobación** puedan mostrar ese concepto sumado entre establecimientos.
No genera ningún reporte por sí mismo: solo configura la equivalencia.

## Qué es y para qué sirve

En este sistema, un mismo RUC puede tener varios establecimientos registrados como
filas independientes de `empresas`, cada una con su **propio plan de cuentas**. Dos
establecimientos no comparten IDs de cuenta ni, necesariamente, el mismo código —
el código de cuenta es libre y editable por cada empresa, así que dos cuentas con
el mismo concepto ("Caja General", por ejemplo) pueden tener códigos distintos en
cada establecimiento, y no hay ninguna forma automática de saber que son "la misma".

Este módulo resuelve eso dejando que el usuario **arme manualmente** los grupos:
"la cuenta X del establecimiento A es el mismo concepto que la cuenta Y del
establecimiento B". Solo aplica cuando el RUC tiene más de un establecimiento al
que el usuario tenga acceso.

## Requisitos previos

- El RUC debe tener más de un establecimiento (fila de `empresas`) accesible al
  usuario (nivel 3 ve todo el RUC; el resto, solo los establecimientos que tenga
  asignados).
- Cada establecimiento que se quiera mapear necesita tener su plan de cuentas ya
  cargado (`modulos/plan-cuentas`).

## Cómo se usa

1. Entrar a **Balances Consolidados** con la empresa activa que sea parte del RUC
   a consolidar.
2. Pulsar **Nuevo grupo consolidado**.
3. Poner un nombre al concepto (ej. "Caja General") y elegir su tipo (Activo,
   Pasivo, Patrimonio, Ingreso, Costo, Gasto) — es solo para agrupar visualmente
   el reporte, no afecta el cálculo.
4. Para cada establecimiento listado, elegir la cuenta de su propio plan de
   cuentas que corresponde a ese concepto. Se puede dejar en blanco el
   establecimiento que no tenga esa cuenta o no aplique.
5. Guardar. El grupo queda disponible de inmediato en Estados Financieros y
   Balance de Comprobación (pestaña/vista "Consolidado por RUC").
6. Repetir por cada concepto que se quiera consolidar. Las cuentas que no se
   mapeen a ningún grupo se siguen viendo en el reporte, pero por establecimiento
   por separado (no se inventan sumas).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|----------------|
| Nombre | Sí | Nombre del concepto consolidado, como aparecerá en el reporte. |
| Tipo | Sí | Clasificación contable (Activo/Pasivo/Patrimonio/Ingreso/Costo/Gasto), solo para agrupar visualmente. |
| Cuenta por establecimiento | Al menos 2 | La cuenta de plan de cuentas de ESE establecimiento que representa el concepto. Una cuenta no puede estar en dos grupos a la vez. |

## Permisos

Ver/crear/actualizar/eliminar grupos sigue el permiso estándar del módulo
(`requireLeer`/`requireCrear`/`requireActualizar`/`requireEliminar`). No hay
distinción de "registros propios" — un grupo consolidado es del RUC completo, lo
ve cualquiera con acceso de lectura al módulo en cualquiera de los establecimientos
del grupo.

## Reglas de negocio

- **El mapeo es 100% manual.** El sistema nunca sugiere ni auto-completa
  equivalencias por coincidencia de código de cuenta: el código no es una clave
  confiable entre establecimientos (es editable, y pueden convivir distintas
  convenciones incluso dentro de una sola empresa).
- **Solo se pueden mapear cuentas de movimiento (nivel 5)** — mismo criterio que
  Mayores e Índices Financieros. Una cuenta padre/agrupadora (nivel 1 a 4) no
  recibe asientos directos, así que mapearla mostraría siempre $0.00 en el
  consolidado sin ningún aviso; por eso el selector solo ofrece cuentas de
  nivel 5, y el servidor lo vuelve a validar al guardar.
- Una cuenta no puede pertenecer a más de un grupo consolidado del mismo RUC.
- Un grupo no puede tener dos cuentas del mismo establecimiento.
- Un grupo necesita al menos 2 cuentas (de 2 establecimientos distintos) — un
  grupo de una sola cuenta no consolida nada.
- **Cuentas de patrimonio**: el formulario avisa, pero no impide, mapear cuentas
  de tipo Patrimonio — capital social, utilidades retenidas o resultado del
  ejercicio no siempre deben sumarse entre establecimientos (podría representar
  capital duplicado en vez de capital real). Verificarlo con el contador antes
  de mapear estas cuentas específicamente.
- Eliminar un grupo no borra ninguna cuenta del plan de cuentas — solo quita la
  equivalencia; los establecimientos vuelven a mostrarse por separado.

## Integraciones con otros módulos

- **Estados Financieros** (`modulos/estados-financieros`) y **Balance de
  Comprobación** (`modulos/balance-comprobacion`): leen estos grupos para armar
  la vista "Consolidado por RUC" — las cuentas mapeadas se muestran sumadas en
  una sola línea; las que no están en ningún grupo se muestran por establecimiento,
  con el reporte jerárquico normal de cada uno.
- No afecta Libro Diario, Auditoría Contable ni la generación de asientos
  automáticos — cada establecimiento sigue contabilizando en su propio libro.

## Errores frecuentes

- **"Una de las cuentas seleccionadas ya pertenece a otro grupo consolidado"**:
  esa cuenta ya está mapeada en otro concepto. Hay que quitarla del grupo
  anterior antes de asignarla a uno nuevo.
- **El botón "Nuevo grupo consolidado" aparece deshabilitado**: la empresa activa
  es la única de su RUC a la que el usuario tiene acceso — no hay con qué
  consolidar.
- **Una cuenta no aparece en el selector**: puede que ya esté usada en otro grupo
  (se muestra atenuada con la leyenda "ya usada en otro grupo"), o que el
  establecimiento no tenga plan de cuentas cargado.

## Historial de cambios

- **1.0** — Versión inicial: configuración de grupos de cuentas equivalentes por
  RUC, consumida por Estados Financieros y Balance de Comprobación.
