---
titulo: Periodos contables
resumen: Apertura y cierre de los periodos; un periodo cerrado bloquea el registro de documentos en esas fechas.
categoria: Contabilidad
ruta_modulo: modulos/periodos_contables
tipo: modulo
visibilidad: todos
etiquetas: periodos contables, reabrir periodo, motivo de reapertura, periodos superpuestos, periodos cruzados, no deja eliminar periodo, cerrar mes, periodo cerrado, abrir periodo, bloqueo de fechas, cierre mensual
version: 1.3
orden: 30
estado: activo
---

Los **periodos contables** delimitan los tramos de tiempo en los que se puede
registrar. Cerrar un periodo es la forma de decirle al sistema *"este mes ya está
declarado, que nadie lo toque"*.

## Qué implica cerrar un periodo

Con el periodo cerrado, **no se puede registrar, modificar ni anular** ningún
documento con fecha dentro de él. Afecta a ingresos, egresos, traspasos y en
general a todo lo que genere movimiento contable.

El sistema lo comprueba también al **modificar la fecha** de un documento: valida
tanto el periodo de origen como el de destino, para que no se pueda sacar un
movimiento de un mes cerrado cambiándole la fecha.

## Cómo se crea un periodo

1. Pulse **Nuevo**.
2. Escriba el **nombre** (por ejemplo, `Julio 2026`).
3. Indique la **fecha inicial** y la **fecha final**.
4. Guarde.

La fecha inicial no puede ser posterior a la final, y **un período no puede cruzarse
con otro**: si dos períodos se superponen, uno abierto y otro cerrado, no se sabría si
esas fechas están bloqueadas. El sistema indica con qué período choca.

## Reabrir

Si hay que corregir algo de un periodo ya cerrado, se reabre, se corrige y se
vuelve a cerrar. Es una decisión del contador, no de quien captura: reabrir un
mes ya declarado puede dejar la contabilidad distinta de lo presentado al SRI.

Por eso, para reabrir:

- El usuario necesita **acceso total** en Periodos Contables (el nivel 3 siempre lo
  tiene).
- Al cambiar el estado a *Abierto* aparece el campo **Motivo de la reapertura**, que es
  obligatorio. Queda en el log del sistema junto con quién y cuándo reabrió.
- Un período de un año cerrado con el **Cierre del Ejercicio** no se reabre desde aquí:
  se revierte ese cierre.

Mientras el período está cerrado **no se pueden cambiar sus fechas ni eliminarlo**
(acortarlo o borrarlo desbloquearía días sin dejar rastro). Para hacerlo, primero se
reabre con el motivo.

Cuando la corrección no es imprescindible, la alternativa correcta es registrar
el ajuste en el periodo abierto.

## Cierre del ejercicio

El módulo **Cierre del Ejercicio** cierra los períodos abiertos del año y, para los días
que ningún período cubre, crea períodos cerrados llamados *Ejercicio AAAA (cierre)*, uno
por cada tramo sin período, sin superponerse a los existentes. Si un período abierto
cruza el inicio o el fin del año, el cierre pide ajustar sus fechas primero. Al revertir
el cierre, los períodos vuelven a como estaban.

## Errores frecuentes

- **"No se puede registrar porque el periodo contable está cerrado"**: la fecha
  del documento cae en un periodo cerrado. Use una fecha del periodo abierto o
  pida al contador que lo reabra.
- **"La fecha inicial no puede ser mayor a la fecha final"**: revise las fechas
  del periodo.
- **"Las fechas se cruzan con el período…"**: ajuste las fechas para que no se
  superpongan con ese período.
- **"Solo un usuario con acceso total… puede reabrir"**: pida a un administrador que
  lo reabra, o que le asigne acceso total en Periodos Contables.
- **"No se pueden cambiar las fechas de un período cerrado"** o **"No se puede
  eliminar un período cerrado"**: reábralo primero indicando el motivo.
- **"El período pertenece al ejercicio…, cerrado con el Cierre del Ejercicio"**:
  revierta ese cierre en su módulo.

## Historial de cambios

- **1.3** — Los períodos no pueden cruzarse. Reabrir un período cerrado exige acceso total
  y un motivo (queda en el log), y no se permite en un año cerrado con el Cierre del
  Ejercicio. Un período cerrado no se puede eliminar ni cambiar de fechas.
- **1.2** — El **Cierre del Ejercicio** cierra los períodos del año (y los devuelve a
  como estaban si se revierte).
- **1.1** — El modal ya no tiene pestaña *Información* (historial de cambios); el historial
  del registro se consulta en el log del sistema.
- **1.0** — Versión inicial.
