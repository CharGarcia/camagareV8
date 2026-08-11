---
titulo: Balances Consolidados
resumen: Mapea manualmente qué cuenta de cada establecimiento del mismo RUC es el mismo concepto, para verlo sumado en Estados Financieros y Balance de Comprobación.
categoria: Contabilidad
ruta_modulo: modulos/balances-consolidados
tipo: modulo
visibilidad: todos
etiquetas: consolidado, RUC, establecimientos, sucursales, balance general, estado de resultados, grupo de cuentas, matriz, plan de cuentas, multiempresa
version: 1.2
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
4. Para cada establecimiento listado, buscar (por código o nombre) y elegir la
   cuenta de su propio plan de cuentas que corresponde a ese concepto — es un
   buscador tipo "chip": se escribe, se elige de la lista filtrada, y queda fija
   la selección (Backspace/Delete la limpia de una sola vez). Se puede dejar en
   blanco el establecimiento que no tenga esa cuenta o no aplique.
5. Si el concepto **no debe sumarse entre establecimientos** (típicamente
   Capital u otra cuenta de Patrimonio que es la misma para toda la empresa,
   no un valor independiente por establecimiento), marcar **"No sumar entre
   establecimientos"** y elegir de cuál establecimiento se toma el valor. Los
   demás establecimientos mapeados quedan solo como referencia — no se suman.
6. Guardar. El grupo queda disponible de inmediato en Estados Financieros y
   Balance de Comprobación (pestaña/vista "Consolidado por RUC").
7. Repetir por cada concepto que se quiera consolidar. Las cuentas que no se
   mapeen a ningún grupo se siguen viendo en el reporte, pero por establecimiento
   por separado (no se inventan sumas).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|----------------|
| Nombre | Sí | Nombre del concepto consolidado, como aparecerá en el reporte. |
| Tipo | Sí | Clasificación contable (Activo/Pasivo/Patrimonio/Ingreso/Costo/Gasto), solo para agrupar visualmente. |
| Cuenta por establecimiento | Al menos 2 | La cuenta de plan de cuentas de ESE establecimiento que representa el concepto. Una cuenta no puede estar en dos grupos a la vez. |
| No sumar entre establecimientos | No (por defecto: sumar) | Cambia el modo de cálculo de SUMA (default) a ÚNICA — ver "Modo de cálculo" abajo. |
| Tomar el valor de | Sí, si "No sumar" está marcado | De cuál de los establecimientos mapeados se toma el valor cuando el modo es ÚNICA. |

## Modo de cálculo: sumar vs. cuenta única

Cada grupo tiene un modo, elegido con el checkbox "No sumar entre
establecimientos":

- **Sumar (SUMA, por defecto)**: el concepto existe de forma independiente en
  cada establecimiento — caja, bancos, cuentas por cobrar/pagar, inventario,
  etc. Cada establecimiento tiene SU PROPIO saldo real de ese concepto, así
  que el total del RUC es la suma de todos.
- **Cuenta única (UNICA)**: el concepto es el mismo registro para toda la
  empresa, aunque cada establecimiento lleve su propia contabilidad —
  típicamente **Capital social** y otras cuentas de **Patrimonio** (reservas,
  aportes). No es que cada establecimiento tenga "su" capital: es el mismo
  capital, y sumarlo entre establecimientos lo duplicaría. En este modo, el
  Total General Consolidado de Estados Financieros toma el valor de **un
  solo** establecimiento (el configurado como fuente); los demás
  establecimientos mapeados a ese grupo se siguen mostrando en el detalle,
  tachados, solo como referencia — nunca se suman al total.

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
  capital duplicado en vez de capital real). Para evitarlo, usar el modo
  "cuenta única" (ver arriba) en vez del modo sumar. Verificarlo con el
  contador antes de mapear estas cuentas específicamente.
- **La Utilidad/Pérdida del Ejercicio del Total General Consolidado siempre se
  suma** entre todos los establecimientos, sin importar el modo de los grupos
  de patrimonio — es un cálculo aparte (no depende de ningún grupo mapeado),
  porque a diferencia del capital, el resultado del período sí es propio de
  cada establecimiento y legítimamente aditivo.
- Eliminar un grupo no borra ninguna cuenta del plan de cuentas — solo quita la
  equivalencia; los establecimientos vuelven a mostrarse por separado.

## Integraciones con otros módulos

- **Estados Financieros** (`modulos/estados-financieros`) y **Balance de
  Comprobación** (`modulos/balance-comprobacion`): leen estos grupos para armar
  la vista "Consolidado por RUC", incluyendo el **Total General Consolidado**
  (un solo Estado de Situación Financiera/Resultados por RUC): las cuentas
  mapeadas se muestran en una sola línea (sumadas, o con el valor único según
  el modo del grupo); las que no están en ningún grupo se muestran por
  establecimiento, con el reporte jerárquico normal de cada uno.
- No afecta Libro Diario, Auditoría Contable ni la generación de asientos
  automáticos — cada establecimiento sigue contabilizando en su propio libro.

## Errores frecuentes

- **"Una de las cuentas seleccionadas ya pertenece a otro grupo consolidado"**:
  esa cuenta ya está mapeada en otro concepto. Hay que quitarla del grupo
  anterior antes de asignarla a uno nuevo.
- **El botón "Nuevo grupo consolidado" aparece deshabilitado**: la empresa activa
  es la única de su RUC a la que el usuario tiene acceso — no hay con qué
  consolidar.
- **Una cuenta no aparece al buscarla**: puede que ya esté usada en otro grupo
  del mismo RUC (esas no aparecen en los resultados de búsqueda, salvo que sea
  la que este mismo grupo ya tiene asignada a ese establecimiento), o que el
  establecimiento no tenga plan de cuentas cargado.

## Historial de cambios

- **1.2** — Se agrega el modo "cuenta única" (no sumar entre establecimientos),
  para conceptos que son el mismo registro en toda la empresa (Capital y demás
  cuentas de Patrimonio) en vez de un valor independiente por establecimiento.
  Estados Financieros ahora arma un Total General Consolidado (un solo Estado
  de Situación Financiera/Resultados por RUC) que usa este modo.
- **1.1** — El selector de cuenta por establecimiento pasó de lista desplegable
  a buscador tipo "chip" (por código o nombre), para no tener que recorrer el
  plan de cuentas completo uno por uno.
- **1.0** — Versión inicial: configuración de grupos de cuentas equivalentes por
  RUC, consumida por Estados Financieros y Balance de Comprobación.
