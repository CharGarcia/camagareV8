---
titulo: Novedades
resumen: Hechos que afectan al sueldo de un empleado en un periodo: horas extra, faltas, préstamos, avisos de salida.
categoria: Nómina
ruta_modulo: modulos/novedades
tipo: modulo
visibilidad: todos
etiquetas: novedades, novedad, horas extra, faltas, atrasos, prestamo, anticipo, descuento, aviso de salida, multa, carga masiva, importar, importacion, excel, plantilla, subir novedades, eliminar carga, revertir carga, deshacer carga, borrar importacion, historial de cargas, duplicados, repetida, todo o nada, buscar novedades, buscador, filtros, filtrar novedades, buscar por empleado, novedades pagadas, novedades pendientes, chips
version: 1.3
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
todo el personal) conviene subirlas de una vez en lugar de una por una. El botón
**Importar** del listado abre un cuadro con dos pestañas: **Importar** e
**Historial**.

La pestaña **Importar** tiene dos pasos:

**1. Descargar la plantilla.** Elija el **tipo de novedad**, el **mes**, el **año**
y a qué pago **afecta**, y pulse **Descargar plantilla**. El archivo sale con esos
datos ya escritos y con **todo el personal activo** de la empresa: una fila por
empleado, con su identificación, su nombre, el tipo, el periodo, la fecha de
registro y la observación *"Tipo - Mes Año"*.

> Esas cuatro opciones son solo **sugerencias para crear la plantilla con datos**:
> el archivo baja con esos valores ya escritos en cada fila para no tener que
> llenarlos a mano. Puede cambiarlos dentro del Excel —incluso fila por fila— y, al
> importar, manda lo que diga el archivo, no lo que quedó seleccionado en pantalla.

**2. Subir la plantilla completada.** Complete la columna **VALOR** solo de los
empleados a los que les corresponde la novedad —**las filas que deje sin valor se
ignoran**, no hace falta borrarlas—, elija el archivo y pulse **Importar**.

La plantilla tiene dos hojas: **Novedades** (la que se llena, y la que se abre al
abrir el archivo) y **Referencia** (los códigos válidos de tipo, *afecta a* y
motivos de salida). La identificación y el nombre están guardados como texto para
que una cédula que empieza en cero no pierda ese cero.

### La importación es todo o nada

Antes de guardar nada, el sistema revisa el archivo completo. **Si una sola fila
tiene un error, no se registra ninguna novedad**: se muestra el detalle fila por
fila para que corrija la plantilla y la vuelva a subir. Se revisa que:

- la identificación corresponda a un empleado de la empresa;
- el tipo, el *afecta a* y el motivo existan en el catálogo;
- el valor sea un número y el periodo sea válido;
- el rol de ese empleado y periodo **no esté ya pagado**;
- **no exista ya la misma novedad**: mismo empleado, mismo tipo y mismo mes/año.
  Vale tanto contra lo ya registrado como contra la propia plantilla (dos filas
  iguales dentro del archivo también se rechazan).

Ese último control es el que evita subir dos veces la misma carga. Si de verdad
necesita dos novedades del mismo tipo y periodo para una persona, regístrelas a
mano desde **Nuevo**.

## Historial de cargas

La pestaña **Historial** del mismo cuadro muestra las **10 últimas** cargas con su
fecha, el archivo, el usuario que la hizo y cuántas de sus novedades siguen
vigentes respecto de las que creó.

Si se subió una plantilla equivocada, no hace falta borrar las novedades una a
una: pulse el ícono de papelera de esa carga y confirme. Se eliminan todas sus
novedades y la carga queda marcada como revertida (queda registrada en la
auditoría del sistema; nada se borra físicamente). Los roles del periodo que
estén en borrador se regeneran solos sin esas novedades.

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

> Quien no tenga **acceso total** solo ve y elimina las cargas que hizo él mismo, y
> la eliminación requiere permiso de **eliminar** en el módulo. La pestaña
> *Historial* se puede ocultar desde el engranaje de las pestañas.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF, Excel e Importar.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la novedad: empleado,
identificación, fecha (tal como se ve, por ejemplo *07-07-2026*), período (por
ejemplo *Julio 2026*) y valor. Además busca en la observación y en el usuario
que registró la novedad. Las columnas **Tipo**, **Afecta a**, **Motivo**,
**Estado** y **Pago** no entran en la búsqueda libre: para filtrar por ellas use
la ventana de filtros. Puede escribir varias palabras en cualquier orden y no
importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse Escape en el
cuadro. Mientras busca, aparece un **círculo girando** al final del cuadro y la
tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios.
Llene los que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La
ventana solo se cierra con la X, Cancelar, Aplicar o Limpiar filtros.

| Bloque | Filtros |
|--------|---------|
| Novedad | Fecha (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), mes y año del período, tipo de novedad, afecta a (rol de pagos, quincena o pago semanal), motivo de salida, estado (activo / anulado), pago (pagada / pendiente) y origen (registro manual o importada desde Excel) |
| Valor y registro | Valor, con mínimo y máximo (es monto, horas o días según el tipo), y usuario que registró |
| Empleado | Empleado, identificación y observación |

El selector *Año del período* y el de *Usuario que registró* listan solo lo que
la empresa ya usó. El filtro *Pago* aplica la misma regla que la columna Pago:
la novedad está pagada cuando el rol en el que se descontó o pagó ya está
cubierto por egresos, o cuando vino marcada como pagada en la migración.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. Los botones PDF y Excel exportan lo que se ve
filtrado.

> Quien no tenga **acceso total** solo ve en el listado las novedades que
> registró él mismo.

## Errores frecuentes

- **"El valor no puede ser negativo"**: elija el tipo de novedad de descuento en
  lugar de poner un número negativo.
- **"El tipo de novedad no es válido"**: el catálogo de tipos es fijo; elija uno
  de la lista.
- **La novedad no aparece en el rol**: revise el periodo (mes y año) al que la
  imputó.
- **"No existe un empleado con identificación ..."** al importar: la cédula o RUC
  de esa fila no coincide con ningún empleado activo de la empresa.
- **"Ya existe una novedad de ... para ... en ..."**: esa persona ya tiene
  registrada una novedad de ese tipo en ese mes; quite la fila de la plantilla o
  corrija el periodo.
- **"Repetida en la plantilla ..."**: el archivo trae dos filas con el mismo
  empleado, tipo y periodo; deje solo una.
- **"Ninguna fila tiene VALOR"**: descargó la plantilla y la subió sin completar
  la columna VALOR.
- **"No se puede eliminar esta carga: N novedad(es) ya se usaron..."**: el rol de
  ese periodo ya está pagado o el anticipo/préstamo ya se desembolsó. Anule ese
  rol o egreso, o elimine una a una las novedades que aún no se usaron.
- **La carga no aparece en «Cargas realizadas»**: solo se listan las cargas hechas
  desde que se habilitó esta función y que crearon al menos una novedad. Las
  anteriores se eliminan novedad por novedad desde el listado.

## Historial de cambios

- **1.3** — Nuevo buscador del listado: búsqueda libre en todas las columnas
  (sin Tipo, Afecta a, Motivo, Estado ni Pago) y botón embudo con la ventana de
  filtros; se suman los filtros de afecta a, motivo de salida, pago, origen,
  identificación y usuario que registró. Los filtros activos se ven como chips
  dentro del cuadro.
- **1.2** — El cuadro de Importar se divide en dos pestañas (Importar e Historial,
  con las 10 últimas cargas). La plantilla se descarga ya llena con el personal
  activo —identificación, nombre, tipo, periodo, fecha y observación— y suma la
  columna NOMBRE. La importación pasa a ser todo o nada y rechaza novedades
  duplicadas (mismo empleado, tipo y periodo).
- **1.1** — Se documenta la carga masiva desde plantilla Excel y se añade la
  posibilidad de eliminar una carga completa mientras ninguna de sus novedades
  haya sido usada (rol pagado o desembolso registrado).
- **1.0** — Versión inicial.
