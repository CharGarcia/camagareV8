---
titulo: Periodos contables
resumen: Apertura y cierre de los periodos; un periodo cerrado bloquea el registro de documentos en esas fechas.
categoria: Contabilidad
ruta_modulo: modulos/periodos_contables
tipo: modulo
visibilidad: todos
etiquetas: periodos contables, cerrar mes, periodo cerrado, abrir periodo, bloqueo de fechas, cierre mensual
version: 1.0
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

La fecha inicial no puede ser posterior a la final.

## Reabrir

Si hay que corregir algo de un periodo ya cerrado, se reabre, se corrige y se
vuelve a cerrar. Es una decisión del contador, no de quien captura: reabrir un
mes ya declarado puede dejar la contabilidad distinta de lo presentado al SRI.

Cuando la corrección no es imprescindible, la alternativa correcta es registrar
el ajuste en el periodo abierto.

## Errores frecuentes

- **"No se puede registrar porque el periodo contable está cerrado"**: la fecha
  del documento cae en un periodo cerrado. Use una fecha del periodo abierto o
  pida al contador que lo reabra.
- **"La fecha inicial no puede ser mayor a la fecha final"**: revise las fechas
  del periodo.

## Historial de cambios

- **1.0** — Versión inicial.
