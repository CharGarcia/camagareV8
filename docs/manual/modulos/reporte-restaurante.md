---
titulo: Reporte de restaurante
resumen: Consumo por mesa, platos más pedidos y anulaciones del servicio.
categoria: Reportes
ruta_modulo: modulos/reporte-restaurante
tipo: modulo
visibilidad: todos
etiquetas: reporte restaurante, comandas, mesas, platos mas vendidos, anulaciones, consumo, rotacion de mesas, forma de pago, filtro forma de pago, tirilla, imprimir tirilla, enviar por correo, pdf, excel, resumen por forma de pago, cuanto entro en efectivo, cuadrar caja, cierre de turno, sin forma de pago registrada, impuestos, iva, detalle de impuestos, desglose de iva, subtotal 15%, subtotal 0%, base imponible, no objeto de iva, exento de iva, total con impuestos, servicio, total no cuadra con el cierre de caja, total vendido, total cobrado, sin impuestos, con impuestos, factura anulada
version: 1.5
orden: 70
estado: activo
---

Este reporte analiza el servicio de restaurante a partir de las comandas: qué se
consumió, en qué mesas y qué se anuló.

## Qué muestra

El selector **Ver por** cambia de vista sin recargar la página:

- **Ventas por mesa**: cuánto se facturó en cada una.
- **Ventas por mesero**: cuánto vendió cada uno y en cuántas comandas.
- **Resumen por forma de pago**: cuánto entró por Efectivo, por cada banco, por
  Payphone… (ver más abajo).
- **Ítems del menú más vendidos**: qué sale de la carta y qué no.
- **Ventas por categoría**.

## Total vendido y Total cobrado: sin y con impuestos

Arriba, junto a las comandas y los documentos, el reporte muestra dos totales
que **no son lo mismo**:

| Total | Qué suma | Lleva impuestos |
|---|---|---|
| **Total vendido (sin imp.)** | Lo consumido según la comanda. | No: sin IVA ni servicio. |
| **Total cobrado (con imp.)** | El importe de las facturas y recibos emitidos al cobrar. | Sí: IVA y servicio incluidos. |

El **Total cobrado** es lo que entró a la caja y el que **cuadra con el cierre de
caja**, que suma igual. Las vistas por mesa, mesero, ítem del menú y categoría
van sin impuestos y suman el Total vendido; la vista *Resumen por forma de pago*
va con impuestos y suma el Total cobrado.

Si al lado del Total cobrado aparece un **triángulo de aviso** con
*"N anulado(s)"*, hay cobros cuya factura o recibo se anuló o eliminó después
de cobrar: siguen en el Total vendido, pero ya no en el Total cobrado.

## Resumen por forma de pago

Responde a *"¿cuánto entró en efectivo y cuánto por tarjeta?"*: una línea por
forma de pago con su tipo, cuántos cobros y el **total cobrado, con IVA y
servicio**, ordenadas de mayor a menor. Es la vista para cuadrar la caja al
cerrar el turno: suma exactamente como el correo del cierre de caja, el importe
de cada factura o recibo.

Aparece también una línea **"Sin forma de pago registrada"**, marcada con un
triángulo de aviso, cuando hay cobros cuyo Ingreso no llegó a generarse. **No es
un error del reporte**: son cobros reales a los que les falta el Ingreso, y hay
que registrarlos desde el módulo *Ingresos*. Mientras estén ahí, el dinero está
contado en el total pero no se sabe por dónde entró.

La suma de todas las líneas **siempre cuadra con el Total cobrado** de la
cabecera: cada factura o recibo cae en una sola línea. Las facturas y recibos
anulados o eliminados no entran.

## Las anulaciones son el dato de control

Como anular una comanda con consumos exige indicar un motivo, este reporte
permite revisar esos motivos juntos. Un patrón de anulaciones concentrado en un
turno o un usuario concreto es algo que conviene mirar de cerca.

## Platos que no rotan

El listado de lo menos pedido es tan útil como el de lo más pedido: son los
platos que ocupan carta e inventario sin venderse.

## Filtros

Por rango de fechas, mesa, mesero, ítem del menú, categoría y **forma de pago**.

La forma de pago es la de la empresa —Efectivo, un banco, Payphone…—, la misma
que elige quien cobra, no el código del SRI. Sale del **Ingreso** que generó cada
cobro, así que si una cuenta se cobró y su Ingreso no llegó a registrarse, esa
cuenta no aparece al filtrar por forma de pago (sí aparece sin ese filtro). Si
nota que los totales no cuadran al filtrar, revise en *Ingresos* que los cobros
del periodo estén registrados.

## Sacar el reporte

Cuatro botones, arriba a la derecha. Se habilitan cuando hay resultados en
pantalla y **todos respetan los filtros aplicados**: lo que sale es exactamente
lo que se está viendo.

| Botón | Qué hace |
|---|---|
| **PDF** | Descarga el reporte en A4 horizontal, con el Total vendido y el Total cobrado en la cabecera. |
| **Excel** | Descarga la tabla para trabajarla en hoja de cálculo. |
| **Tirilla** | Lo imprime en la **impresora térmica**, en el ancho configurado en *Configuración Restaurante*. Se abre en una ventana que se imprime sola. |
| **Correo** | Lo envía a uno o varios correos, con el PDF adjunto. |

El **correo** se abre con el **correo de la empresa** ya escrito (el de *Empresa →
Datos generales*), y se puede cambiar o añadir más separándolos con comas. El
enlace *Restaurar* vuelve a poner el de la empresa si lo borró. Si la empresa no
tiene correo configurado, el campo arranca vacío y lo dice.

El cuerpo del mensaje lleva el resumen —periodo, filtros aplicados, comandas,
documentos, Total vendido y Total cobrado— y el PDF va adjunto.

## La tirilla, bloque por bloque: detalle de impuestos y forma de pago

La **tirilla** resume cada fila en dos líneas —el concepto y, debajo, su dato
secundario (ubicación, categoría, número de comandas) con el importe a la
derecha—, porque en 58 u 80 mm no caben las columnas de la pantalla. Lleva la
leyenda *"Reporte interno — sin validez tributaria"*: es para la caja, no para el
cliente.

De arriba abajo imprime:

1. **El detalle de la vista activa**, igual que la pantalla: **sin impuestos**, salvo en la vista *Resumen por forma de pago*, que ya va con impuestos.
2. **Comandas, documentos y Total vendido (sin imp.)**.
3. **Detalle de impuestos**: cómo se pasa del Total vendido a lo cobrado, con las mismas líneas del bloque de totales de una factura. Solo salen las que tienen valor, para no gastar papel en ceros:
    - **Subtotal por tarifa**: *Subtotal 15%*, *Subtotal 5%*, *Subtotal 0%*, *Subtotal no objeto de IVA* y *Subtotal exento de IVA*.
    - **Subtotal sin impuestos**: la suma de los anteriores. Sale siempre.
    - **IVA por tarifa** (*IVA 15%*, *IVA 5%*…) y el **ICE**, si lo hubiera.
    - **Servicio**: el recargo del local.
    - **TOTAL CON IMPUESTOS**, en negrita.
4. **Resumen por forma de pago**, el mismo que llega por correo al cerrar la caja: una línea por forma de pago con su tipo, cuántos cobros y **cuánto entró, con impuestos**; el **Total cobrado** —igual al *TOTAL CON IMPUESTOS* del bloque anterior—; y, aparte, el **Servicio** y la **Propina voluntaria** (la que dejó el cliente). Las dos propinas ya están dentro del total: son un *"de esto, tanto se reparte al personal"*, no se suman encima. En la vista *Resumen por forma de pago* la lista no se repite —ya es el detalle de arriba—: solo salen el total y las propinas.

Los bloques 3 y 4 salen de las **facturas y recibos** que se emitieron al
cobrar, porque el IVA y el servicio solo existen en el comprobante. Si una
factura o recibo se anuló o se eliminó después de cobrar, ese cobro sigue en el
Total vendido pero ya no entra en esos dos bloques, y la tirilla lo avisa debajo
del detalle de impuestos (*"N cobro(s) sin comprobante vigente"*).

A diferencia del correo del cierre, aquí **no hay "contado" ni "diferencia"**:
el reporte se filtra por fechas, no por turno de caja, así que no existe un
arqueo contra qué cuadrar. El *Total cobrado* se calcula igual que en el cierre
—el importe total de cada comprobante—, pero coincide con el de un cierre solo
si el filtro abarca exactamente los mismos cobros (por ejemplo, un día con un
único turno).

## Errores frecuentes

- **Faltan consumos del día**: hay comandas todavía abiertas; el reporte cuenta
  las cerradas.
- **Un plato no aparece**: no se pidió en el periodo, o se registró como producto
  suelto en lugar de ítem del menú.
- **Al filtrar por forma de pago faltan cuentas**: esas cuentas se cobraron pero
  su Ingreso no se generó. Revíselas en *Ingresos*.
- **"No se pudo enviar el correo"**: revise la configuración de correo de la
  empresa. El reporte no se pierde: se puede descargar en PDF y enviarlo a mano.
- **El Total vendido no coincide con el cierre de caja**: el Total vendido va sin impuestos; el cierre suma lo cobrado, con IVA y servicio. Compárelo con el **Total cobrado** —en la pantalla, junto al Total vendido, o en la tirilla—, que se calcula igual que el cierre.
- **"N anulado(s)" junto al Total cobrado, o la tirilla dice "N cobro(s) sin comprobante vigente"**: la factura o el recibo de esos cobros se anuló o eliminó después de cobrar. Siguen sumando en el Total vendido, pero no en el Total cobrado, el detalle de impuestos ni el resumen por forma de pago; revíselos en *Facturas de Venta* o *Recibos de Venta*.

## Historial de cambios

- **1.5** — La vista **Resumen por forma de pago** muestra lo cobrado **con impuestos** —IVA y servicio incluidos, igual que el correo del cierre de caja— en pantalla, PDF, Excel, correo y tirilla. Nuevo indicador **Total cobrado (con imp.)** junto al **Total vendido**, que ahora dice que va sin impuestos, con un aviso si hay cobros con la factura o el recibo anulado. La **tirilla** imprime además el **Detalle de impuestos**: subtotal por tarifa, subtotal sin impuestos, IVA por tarifa, ICE, servicio y total con impuestos.
- **1.4** — La **tirilla** imprime, en cualquier vista, el bloque **Resumen por
  forma de pago** igual al del correo del cierre de caja: cobros y total por
  forma de pago, total cobrado, servicio y propina voluntaria.
- **1.3** — La ventana de la tirilla ya no desaparece al cancelar la
  impresión: antes el navegador avisaba igual al imprimir que al cancelar y la
  ventana desaparecía a los 2 segundos, obligando a pedir la tirilla otra vez.
  Ahora avisa de que se cerrará en 10 segundos y deja a mano **Imprimir de
  nuevo** —que reinicia la cuenta— y **Cerrar**.
- **1.2** — Nueva vista **Resumen por forma de pago**: cuánto entró por cada una,
  con los cobros que aún no tienen su Ingreso agrupados aparte y señalados. Sale
  también en PDF, Excel, tirilla y correo, como el resto de vistas.
- **1.1** — Nuevo filtro por **forma de pago** y dos salidas más junto a PDF y
  Excel: **Tirilla** (impresora térmica) y **Correo** (con el PDF adjunto). Las
  cuatro salidas respetan los filtros aplicados. El envío por correo llega con
  el correo de la empresa ya escrito, editable. La pantalla pasó al diseño
  estándar de los reportes: título, filtros e indicadores en una sola tarjeta que
  **queda fija arriba** al desplazarse, y la tabla debajo creciendo hacia abajo
  en vez de dentro de una caja con scroll propio.
- **1.0** — Versión inicial.
