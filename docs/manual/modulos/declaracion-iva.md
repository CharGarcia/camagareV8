---
titulo: Declaración de IVA
resumen: Cálculo de la declaración de IVA del periodo, su asiento y el egreso del pago.
categoria: Impuestos
ruta_modulo: modulos/declaracion_iva
tipo: modulo
visibilidad: todos
etiquetas: iva, declaracion de iva, formulario 104, impuesto, credito tributario, saldo a favor, pagar iva, casilleros, detalle de casilleros, excel, exportar, filtrar, sumas, cuadrar, casillero 609, retenciones de iva, formula, suma de casilleros, casillero en blanco, no calcula
version: 1.6
orden: 10
estado: activo
---

Este módulo arma la **declaración de IVA** del periodo a partir de las ventas y
compras registradas, y permite dejarla guardada con su asiento contable y el
egreso del pago.

## Periodo mensual o semestral

La declaración puede ser **mensual** o **semestral**, según cómo declare la
empresa. Al crearla se indica el tipo y el periodo:

- Mensual: año y **mes** (1 a 12).
- Semestral: año y **semestre**.

## El recorrido

1. **Cree la declaración** del periodo.
2. **Calcule**: el sistema toma las ventas y compras del periodo.
3. **Revise** las cifras contra sus registros.
4. **Guarde** la declaración.
5. **Genere el asiento** contable (es un paso aparte).
6. **Genere el egreso** del pago, eligiendo a quién se paga y con qué concepto.

## Cómo se ve el formulario

El **Resumen 104** se muestra completo, sin recuadro que lo encierre: las
secciones se suceden una tras otra y quien se desplaza es la página, no una caja
interna. Antes el formulario vivía apretado en media pantalla con su propio
scroll.

Para que no se pierdan de vista, el **título, los filtros del periodo, los
botones (GENERAR, EXCEL, GUARDAR…) y las pestañas quedan fijos** en la parte
superior mientras se recorre el formulario. El interruptor **Solo valores** sigue
disponible ahí para esconder las filas en cero.

## Detalle de casilleros: filtrar y ver sumas

La pestaña **Detalle de Casilleros** muestra, documento por documento, a qué
casillero fue cada valor. Sobre ella hay una barra de filtros que se puede
combinar libremente:

| Filtro | Qué hace |
| --- | --- |
| Tipo de documento | Deja solo facturas de venta, compras, retenciones, notas de crédito… |
| Casillero | Deja solo los valores que fueron a ese casillero. |
| Documento | Busca por número (por ejemplo `001-101` o el número completo). |
| Cliente / Proveedor | Busca por nombre **o por identificación (RUC/cédula)**. |
| Buscar (todo) | Texto libre sobre número, entidad, concepto, casillero y tipo. |

Debajo de los filtros se muestran las **sumas de lo que está filtrado en ese
momento**: cantidad de documentos, cantidad de registros y el total, más un
desglose con la **suma por casillero**. Al hacer clic en uno de esos casilleros
se filtra por él; un segundo clic quita el filtro. El botón **Limpiar** deja
todo como al inicio.

Los filtros trabajan sobre lo ya cargado, así que responden al instante y no
hace falta volver a presionar GENERAR.

## Exportar a Excel

El botón **EXCEL** descarga tres hojas:

- **Resumen 104**: el formulario tal como se ve en pantalla, incluidos los
  ajustes manuales que tenga puestos en ese momento (casilleros 615, 617, 481,
  484, 486 y 902), aunque todavía no haya guardado la declaración. Al final, si
  existen, se listan aparte los **casilleros con valor que no tienen fila en la
  estructura del formulario**.
- **Detalle Casilleros**: una fila por documento y casillero, con autofiltro.
  Al filtrar en Excel, la fila **TOTAL FILTRADO** de abajo se recalcula sola.
- **Por Casillero**: cuadre del formulario contra los documentos — cuántos
  documentos aportaron a cada casillero, la suma de esos documentos, el valor
  del formulario y la diferencia. La columna *Observación* señala los casos a
  revisar.

### Si en el Excel falta un casillero

Un casillero puede tener valores sincronizados y aun así **no aparecer en el
formulario** si nadie creó su fila en *Configuración → Casilleros SRI*. El caso
típico es el **609** (retenciones en la fuente de IVA que le efectuaron): el
sistema lo usa para calcular el saldo a favor, pero sin su fila no se dibuja en
pantalla.

Esos casilleros salen igualmente en el Excel: al final de la hoja *Resumen 104*
y marcados en rojo en la hoja *Por Casillero* con la observación "Sin fila en la
estructura". Para que aparezcan también en el formulario hay que crear la fila
correspondiente en la configuración de casilleros.

## Casilleros que suman otros (fórmulas)

En *Configuración → Casilleros SRI* un casillero puede definirse como la suma de
otros escribiendo una fórmula, por ejemplo `401+402+405`. Se aceptan `+`, `-`,
`*`, `/` y **paréntesis**, con la precedencia matemática de siempre, y también
separar los casilleros por coma (`401,402,405`). Los paréntesis se pueden anidar:
`((411+412)*2)/419`.

### Divisiones: el denominador en cero da cero

Cuando una fórmula divide y **el denominador vale cero, el resultado de esa
división es cero**, no un error. Es lo que corresponde en el formulario:

- Un **factor de proporcionalidad** como
  `(411+412+420+435+415+416+417+418) / 419` vale cero si no hubo ventas en el
  periodo (casillero 419 en cero).
- Un **interruptor** como `(615/615)*609` sirve para arrastrar el 609 solo cuando
  el 615 tiene saldo; si el 615 está en cero, el casillero queda en cero.

Solo se anula esa división, no el resto: en `411+412/419`, si el 419 es cero, el
resultado sigue siendo el valor del 411.

### Cuando un casillero con fórmula sale en blanco

Puede quedarse en blanco por cuatro motivos, y el módulo **lo dice en un aviso
amarillo** sobre el formulario (y en el Excel), en vez de mostrar un cero sin
explicación:

- **La fórmula está en una columna que no tiene casillero.** Cada fila tiene tres
  columnas (Bruto, Neto, Impuesto) con su propio casillero y su propia fórmula.
  La fórmula de la columna *Bruto* solo alimenta al casillero de la columna
  *Bruto*: si el casillero está en *Impuesto*, la fórmula hay que escribirla en
  *Fórmula Impuesto*.
- **La fila es de tipo "título".** Esas filas se dibujan como un encabezado a
  todo lo ancho, sin columnas de valor: el resultado se calcula pero no se ve.
  Hay que cambiarla a tipo *valor*.
- **La fórmula menciona casilleros que no existen** en la estructura. Esos
  cuentan como cero; el aviso indica cuáles son. Si el resultado le importa, cree
  la fila de esos casilleros (ver *Casilleros de ajuste* más abajo).
- **Está mal escrita**: falta cerrar un paréntesis, sobra uno, o hay un operador
  sin su valor. El aviso dice cuál de esos es el caso.

Las fórmulas pueden apoyarse unas en otras (el 485 usa el 482, que sale del 429):
el sistema las resuelve en cadena.

## Casilleros de ajuste (los que se llenan a mano)

Algunos casilleros del 104 no salen de ningún documento: son ajustes
excepcionales que solo el contribuyente conoce. En el sistema aparecen como un
**campo escribible** (fondo amarillo) dentro del formulario, y lo que escriba se
guarda junto con la declaración:

| Casillero | Qué es |
| --- | --- |
| 623 | Saldo de crédito tributario del mes anterior por fusión o absorción de sociedades. |
| 622 | IVA devuelto o descontado por ventas a adultos mayores o personas con discapacidad. |
| 610 | Ajuste por IVA devuelto o descontado en adquisiciones con medio electrónico. |
| 611 | Ajuste por IVA devuelto o descontado en adquisiciones en zonas afectadas (Ley de solidaridad). |
| 612 | Ajuste por IVA devuelto y rechazado en adquisiciones e importaciones, imputable al crédito tributario. |
| 613 | Ajuste por IVA devuelto y rechazado en retenciones de IVA, imputable al crédito tributario. |
| 614 | Ajuste por IVA devuelto por otras instituciones del sector público. |
| 898 | Imputación al pago: impuesto (solo en declaraciones sustitutivas). |
| 615 / 617 | Saldo de crédito tributario que se arrastra al próximo mes. |
| 481 / 484 / 486 | Liquidación diferida del IVA por ventas a crédito. |

Al escribir en cualquiera de ellos, **los casilleros que dependen de él se
recalculan al instante**: por ejemplo el 620 (subtotal a pagar) suma el 610 al 614
y resta el 622 y el 623, y el 902 resta el 898 del 859. Mientras no se llenen
valen cero, que es el caso normal.

Cualquier casillero que se marque como *editable* en *Configuración → Casilleros
SRI* se comporta así, sin necesidad de tocar el sistema.

## El importe del egreso

El egreso del pago se genera por el valor del casillero **902 (Total impuesto a
pagar)** tal como aparece en el formulario. Es decir:

- Si el 902 tiene una fórmula configurada (por ejemplo `902 = (859-898)`), manda
  el resultado de esa fórmula.
- Si no tiene fórmula pero usted escribió un valor en el campo, manda ese.
- Si no hay ni fórmula ni valor escrito, manda el neto que calcula el sistema
  (IVA en ventas − crédito tributario − retenciones).

En el modal de **Generar Egreso** ese importe llega precargado y todavía se puede
cambiar a mano, por si hubo un abono previo u otro ajuste.

El asiento contable es distinto: cuadra contra el neto calculado por el sistema,
porque tiene que cerrar contra las cuentas de IVA en ventas, crédito tributario y
retenciones.

## Saldo a favor

Cuando el periodo termina con **saldo a favor**, el sistema lo arrastra
automáticamente al periodo siguiente como crédito tributario. No hay que anotarlo
a mano.

## Con egreso generado, se bloquea

Una vez generado el egreso del pago, **la declaración no se puede modificar**. Si
necesita corregirla, primero hay que **anular el egreso desde el módulo de
Egresos**; el sistema lo indica con ese mismo mensaje.

Es la misma lógica de los décimos: no se cambia lo que ya se pagó.

## Errores frecuentes

- **"Esta declaración ya tiene un egreso generado"**: anule el egreso para poder
  modificarla.
- **"El tipo de período debe ser mensual o semestral"**: revise el tipo elegido.
- **Las cifras no coinciden con lo que espera**: compruebe que todas las facturas
  y compras del periodo estén registradas, y que ninguna tenga fecha fuera del
  periodo. Para ubicar la diferencia, use la hoja **Por Casillero** del Excel:
  compara casillero por casillero el formulario contra la suma de los documentos.
- **En el Excel falta un casillero**: probablemente no tiene fila en
  *Configuración → Casilleros SRI*. Revise la lista del final de la hoja
  *Resumen 104*.
- **Un casillero configurado con fórmula sale en blanco**: lea el aviso amarillo
  sobre el formulario, que dice exactamente por qué no se aplicó (ver
  *Casilleros que suman otros*).

## Historial de cambios

- **1.6** — El egreso se genera por el casillero 902 tal como se ve en el formulario: si el
  902 tiene fórmula configurada, esa manda sobre el neto calculado internamente. Antes la
  pantalla mostraba el resultado de la fórmula y el egreso salía por otro importe, y además
  quedaba bloqueado cuando el neto interno era cero.
- **1.5** — Se agregan a la estructura los casilleros de ajuste que faltaban (623, 622,
  610 a 614 y 898), con las descripciones del formulario oficial. Ahora cualquier casillero
  marcado como editable se guarda con la declaración y se exporta al Excel, no solo los seis
  que tienen columna propia en la tabla.
- **1.4** — Las fórmulas que dividen ya no fallan cuando el denominador es cero (factores
  de proporcionalidad como el 563 e interruptores como (615/615)*609): esa división vale
  cero. El aviso de fórmula no aplicada dice ahora el motivo exacto (paréntesis sin cerrar,
  operador sin valor…) en vez de un mensaje genérico de sintaxis.
- **1.3** — El Resumen 104 se muestra completo, sin la caja de media pantalla que lo
  encerraba: ahora se desplaza la página. El título, los filtros, los botones y las
  pestañas quedan fijos arriba. El detalle de casilleros también se extiende libre.
- **1.2** — Aviso cuando una fórmula configurada no se aplica (columna sin
  casillero, fila de tipo título, casilleros inexistentes o sintaxis inválida);
  antes el campo salía en blanco sin explicación. Las fórmulas ahora toleran
  comas como separador y prefijos o adornos alrededor del código, y se resuelven
  en más pasadas encadenadas. Se agrega la fila del casillero **609**
  (retenciones de IVA que le efectuaron) a la estructura del formulario.
- **1.1** — Filtros y sumas en la pestaña *Detalle de Casilleros* (por tipo de
  documento, casillero, número de documento y cliente/proveedor). El Excel pasa a
  tres hojas: se agregan el detalle plano con autofiltro y totales, y el cuadre
  *Por Casillero*; el Resumen 104 exporta los ajustes manuales que estén en
  pantalla y ya no oculta los casilleros sin fila en la estructura.
- **1.0** — Versión inicial.
