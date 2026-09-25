---
titulo: Tablero
resumen: Pantalla de inicio con los indicadores y avisos del día.
categoria: Primeros pasos
ruta_modulo: modulos/dashboard
tipo: modulo
visibilidad: todos
etiquetas: tablero, dashboard, inicio, resumen, indicadores, avisos, pantalla principal, home, ordenar tarjetas, mover tarjetas, arrastrar, reubicar, personalizar tablero, filtros fijos, ancho de tarjetas, redimensionar, cambiar tamaño, columnas, saldo cxc, saldo cxp, cartera, cuentas por cobrar, cuentas por pagar, comparativo mensual, nómina
version: 1.3
orden: 2
estado: activo
---

El **tablero** es la pantalla de inicio: el resumen de cómo va la empresa y lo
que requiere atención hoy.

## Qué muestra

Depende de los módulos que tenga asignados cada usuario, pero en general:
indicadores de ventas y cobros, documentos pendientes y avisos de cosas que
requieren acción.

## Cómo se calcula cada indicador

| Indicador | Qué suma |
|---|---|
| Ventas | Facturas **autorizadas** emitidas en el período (igual que el Reporte de Ventas). Los borradores, las pendientes de envío y las rechazadas no cuentan. |
| Compras | Comprobantes de compra del período; las **notas de crédito restan**. Las compras anuladas o rechazadas no cuentan. |
| Nómina | Total devengado de los roles de pago del período (sin borradores ni anulados). |
| Ingresos / Egresos (caja) | Ingresos y egresos del período, sin los anulados. |
| CxC Pendiente | **El mismo saldo que muestra Cuentas por Cobrar** con su Fecha Hasta en la fecha de corte: facturas, recibos de venta y saldos iniciales, con cobros, retenciones, notas de crédito y notas de débito. |
| CxP Pendiente | **El mismo saldo que muestra Cuentas por Pagar** con su Fecha Hasta en la fecha de corte: facturas y demás comprobantes de compra, liquidaciones, importaciones y saldos iniciales, con pagos, retenciones y notas de crédito/débito. |
| CxC / CxP Vencidas | Los cinco documentos más atrasados, con los mismos días de vencido que muestran esos módulos (desde la fecha de vencimiento). |

La **fecha de corte** de la cartera es el último día del período elegido o hoy,
lo que ocurra primero; se ve debajo del valor ("Saldo por cobrar al…"). Con el
mes en curso es hoy, igual que la Fecha Hasta con la que se abren Cuentas por
Cobrar y Cuentas por Pagar, así que los tres muestran el mismo valor.

El **Comparativo mensual** arranca con Ventas y Compras marcadas; Nómina,
Ingresos, Egresos y Utilidad se marcan en las casillas de la tarjeta.

## Lo que ve cada usuario

El tablero **respeta los permisos**: solo muestra información de los módulos a
los que la persona tiene acceso, y de la empresa activa. Dos usuarios verán
tableros distintos, y es correcto.

Si además no tiene el permiso de *acceso total*, los indicadores reflejan solo
sus propios registros.

## Arme el tablero a su gusto

Todas las tarjetas del tablero —tanto los indicadores de arriba como los
paneles de gráficos y tablas— se pueden **mover de lugar** y **cambiar de
ancho**. Cada usuario guarda su propio tablero: lo que usted acomode no le
cambia nada a nadie más, y lo encontrará igual la próxima vez que entre, desde
cualquier equipo o navegador.

### Mover una tarjeta

1. Pase el cursor sobre la tarjeta. En su **esquina superior izquierda** aparece
   un pequeño agarre (⣿).
2. Mantenga presionado ese agarre y arrastre la tarjeta hasta donde la quiere.
   Las demás se van corriendo para dejarle el espacio.
3. Suelte. Se guarda solo.

Puede soltarla en **cualquier posición**: al final de una fila que quedó a
medias, entre dos tarjetas, o debajo de la última. No hace falta que "encaje"
en la fila; si no cabe, se acomoda en la siguiente. Un indicador puede quedar
entre los paneles y un panel entre los indicadores: no hay zonas separadas.

### Cambiar el ancho

El tablero es una cuadrícula de **12 columnas**. Cada tarjeta ocupa un número de
columnas: 12 es todo el ancho de la pantalla, 6 es la mitad, 4 es un tercio y 2
es el mínimo.

1. Pase el cursor sobre la tarjeta: en su **borde derecho** aparece una barrita
   vertical.
2. Arrástrela hacia la derecha para ensancharla o hacia la izquierda para
   angostarla. Mientras arrastra, un cartelito muestra cuántas columnas de 12
   está ocupando.
3. Suelte. También se guarda solo.

Si la suma de anchos de una fila no llega a 12, queda un espacio en blanco a la
derecha: es normal, y se corrige ensanchando alguna tarjeta o moviendo otra a
esa fila.

Para volver todo como venía de fábrica —orden y anchos— use el enlace
**Restablecer tablero**, en el texto pequeño debajo del título.

> En celulares y tablets el tablero se ordena solo (indicadores de a dos por
> fila y paneles a todo el ancho), así que ahí el ancho manual no se aplica. El
> orden que usted definió sí se respeta.

## Los filtros quedan siempre a la vista

La barra de filtros (período, rango de fechas, tendencia y tipo de gráfico) se
queda **fija bajo el menú** mientras usted baja por el tablero, de modo que
puede cambiar el período sin volver arriba.

## Si el tablero sale vacío

Suele ser una de tres cosas:

1. No hay **empresa activa** seleccionada.
2. El usuario no tiene módulos asignados todavía.
3. Es una empresa nueva sin movimientos.

## Errores frecuentes

- **Los números no coinciden con los reportes**: revise el periodo que muestra
  cada indicador y si le falta el permiso de acceso total.
- **Entré a un módulo y volví al tablero**: es lo que ocurre cuando no se tiene
  permiso sobre ese módulo. Pida el acceso al administrador.

## Historial de cambios

- **1.3** — *CxC Pendiente* y *CxP Pendiente* muestran ahora el mismo saldo que
  Cuentas por Cobrar y Cuentas por Pagar: antes solo sumaban lo emitido en el
  período y dejaban fuera recibos de venta, notas de débito, liquidaciones,
  importaciones y otros comprobantes de compra. Las tablas de vencidos usan los
  mismos días de vencido que esos módulos. *Ventas* cuenta solo facturas
  autorizadas (antes sumaba borradores y rechazadas) y en *Compras* las notas de
  crédito restan (antes sumaban). En el comparativo mensual, Ingresos y Egresos ya
  no mezclan los documentos del ambiente de pruebas, los meses no se repiten
  cuando el día es 29, 30 o 31, y Nómina viene desmarcada.
- **1.2** — El tablero carga mucho más rápido en empresas con muchas facturas o con
  saldos iniciales de cartera: el cálculo de *CxC Pendiente* y de las tablas de
  vencidos ya no recorre los documentos de todas las empresas. Además, *CxC* solo
  descuenta los cobros hechos a facturas y *CxP* solo los pagos hechos a compras:
  antes, un cobro de un recibo de venta o de un saldo inicial (o un pago de un rol
  o un egreso manual) podía restarse por error de una factura o compra que no tenía
  nada que ver, cuando coincidía su número interno. En *CxC Vencidas* y *CxP
  Vencidas*, los documentos con los mismos días de atraso se muestran ahora de mayor
  a menor saldo.
- **1.1** — Las tarjetas se pueden arrastrar a cualquier posición y cambiarles el
  ancho; el tablero se guarda por usuario y la barra de filtros quedó fija bajo el menú.
- **1.0** — Versión inicial.
