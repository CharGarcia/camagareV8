---
titulo: Novedades
resumen: Hechos que afectan al sueldo de un empleado en un periodo: horas extra, faltas, préstamos, avisos de salida.
categoria: Nómina
ruta_modulo: modulos/novedades
tipo: modulo
visibilidad: todos
etiquetas: novedades, novedad, horas extra, faltas, atrasos, prestamo, anticipo, descuento, aviso de salida, multa, carga masiva, importar, importacion, excel, plantilla, subir novedades, eliminar carga, revertir carga, deshacer carga, borrar importacion
version: 1.1
orden: 20
estado: activo
---

Las **novedades** son los hechos que modifican lo que un empleado cobra en un
periodo: horas extra, faltas, atrasos, anticipos, préstamos, descuentos, bonos.
Son el insumo del rol de pago: lo que se registra aquí es lo que después ajusta
el sueldo.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **empleado**.
3. Elija el **tipo de novedad** del catálogo.
4. Indique la **fecha** y el **periodo** (mes y año) al que se imputa.
5. Escriba el **valor** que corresponda al tipo elegido.
6. Guarde.

## El valor depende del tipo

El campo *valor* es flexible a propósito: según el tipo de novedad representa un
**monto**, un número de **horas** o de **días**. El formulario indica cuál
corresponde en cada caso.

Nunca puede ser negativo: para descontar se usa el tipo de novedad que
corresponde, no un valor en negativo.

## Aviso de salida

El aviso de salida es un tipo especial: exige indicar el **motivo de salida** del
catálogo y **no lleva valor**. Sirve para dejar constancia de la desvinculación y
su causa, que es lo que después determina la liquidación.

## Periodo, no solo fecha

Una novedad tiene fecha (cuándo ocurrió) y periodo (a qué mes se imputa). No
siempre coinciden: una hora extra del 31 de julio puede pagarse en el rol de
agosto. El mes debe estar entre 1 y 12.

## Carga masiva desde una plantilla Excel

Cuando hay muchas novedades del mismo periodo (por ejemplo las horas extra de
todo el personal) conviene subirlas de una vez en lugar de una por una.

1. Pulse **Importar** en la barra del listado.
2. Descargue la **plantilla** con el botón *Plantilla*. Trae los encabezados y una
   hoja «Referencia» con los códigos válidos.
3. Complete una fila por novedad: `IDENTIFICACION`, `TIPO`, `VALOR`, `MES`, `ANIO`,
   `AFECTA_A` y, si aplica, `FECHA`, `OBSERVACION` y `MOTIVO`. El tipo y el
   *afecta a* se pueden escribir por código o por nombre.
4. Elija el archivo y pulse **Importar**.

El sistema procesa **fila por fila**: las filas correctas se crean aunque otras
fallen, y al terminar se muestra cuántas se importaron y el detalle de las que no,
con el número de fila y el motivo. Cada importación queda registrada como una
**carga**, para poder deshacerla después.

## Eliminar una carga completa

Si se subió una plantilla equivocada, no hace falta borrar las novedades una a
una: se elimina la carga entera.

1. Pulse **Importar**.
2. En **Cargas realizadas** busque la carga por fecha, archivo y usuario.
3. Pulse el ícono de papelera y confirme.

Se eliminan todas las novedades de esa carga y la carga queda marcada como
revertida (queda registrada en la auditoría del sistema; nada se borra
físicamente). Los roles del periodo que estén en borrador se regeneran solos sin
esas novedades.

**Solo se puede eliminar una carga que todavía no se ha usado.** Una novedad se
considera usada cuando:

- el **rol** de ese empleado y periodo (mensual, quincena o semanal, según el
  campo *Afecta a*) ya está **pagado** o contabilizado, o
- se trata de un **anticipo** o una cuota de **préstamo empresa** que ya tiene un
  **desembolso registrado por egreso**.

Si aunque sea una novedad de la carga cumple lo anterior, la carga aparece con un
candado y **no se elimina nada**: el aviso indica qué novedades la están
bloqueando. En ese caso, anule primero el rol o el egreso correspondiente, o
elimine desde el listado, una a una, las novedades que sí se pueden.

La columna *Vigentes / Creadas* muestra cuántas novedades de esa carga siguen
existiendo respecto de cuántas se crearon: si ya se borraron todas a mano, la
carga ya no ofrece la papelera.

> Quien no tenga **acceso total** solo ve y elimina las cargas que hizo él mismo, y
> la eliminación requiere permiso de **eliminar** en el módulo.

## Errores frecuentes

- **"El valor no puede ser negativo"**: elija el tipo de novedad de descuento en
  lugar de poner un número negativo.
- **"El tipo de novedad no es válido"**: el catálogo de tipos es fijo; elija uno
  de la lista.
- **La novedad no aparece en el rol**: revise el periodo (mes y año) al que la
  imputó.
- **"No existe un empleado con identificación ..."** al importar: la cédula o RUC
  de esa fila no coincide con ningún empleado activo de la empresa.
- **"No se puede eliminar esta carga: N novedad(es) ya se usaron..."**: el rol de
  ese periodo ya está pagado o el anticipo/préstamo ya se desembolsó. Anule ese
  rol o egreso, o elimine una a una las novedades que aún no se usaron.
- **La carga no aparece en «Cargas realizadas»**: solo se listan las cargas hechas
  desde que se habilitó esta función y que crearon al menos una novedad. Las
  anteriores se eliminan novedad por novedad desde el listado.

## Historial de cambios

- **1.1** — Se documenta la carga masiva desde plantilla Excel y se añade la
  posibilidad de eliminar una carga completa mientras ninguna de sus novedades
  haya sido usada (rol pagado o desembolso registrado).
- **1.0** — Versión inicial.
