---
titulo: Mayores
resumen: Movimientos de una cuenta contable en un periodo, con su saldo y el documento que originó cada línea.
categoria: Contabilidad
ruta_modulo: modulos/mayores
tipo: modulo
visibilidad: todos
etiquetas: mayor, mayores, libro mayor, movimientos de cuenta, saldo de cuenta, auxiliar, cuadre, pdf mayor, imprimir mayor
version: 1.3
orden: 40
estado: activo
---

El **mayor** muestra todos los movimientos de una cuenta contable en un rango de
fechas, con su saldo acumulado. Es la herramienta para responder *"¿por qué esta
cuenta tiene este saldo?"*.

## Cómo se consulta

Los filtros están en la tarjeta de arriba, que queda fija al bajar por el informe:

1. Elija el **año** y el **mes** (o escriba el rango de **fechas**).
2. Si quiere, elija la **cuenta** en el buscador y el **tercero** (primero su tipo:
   cliente, proveedor o empleado).
3. Pulse **Generar**. **Limpiar** devuelve todos los filtros a su valor inicial.

Los filtros **C. Costo** y **Proyecto** solo aparecen si la empresa tiene centros
de costo o proyectos activos.

Al pie de la tarjeta se resume el informe generado: número de **cuentas** y de
**movimientos**, **total debe** y **total haber**.

En el buscador de cuenta, si ya hay una seleccionada, pulsar **Retroceso** o
**Suprimir** limpia toda la selección de una vez.

## Exportar a PDF y Excel

El **PDF** sale en hoja horizontal con el formato de los demás reportes del
sistema: logo del establecimiento, nombre de la empresa, período, recuadro de
**Filtros aplicados**, banda de totales (cuentas, movimientos, debe, haber y
diferencia), el detalle por cuenta con su subtotal, el **total general** al
final y la numeración *Página x/y*. El encabezado de columnas se repite en cada
hoja.

El **Excel** sigue el mismo formato de los demás reportes: nombre de la empresa,
filtros aplicados, totales y fecha de generación arriba; luego un solo
encabezado de columnas (queda fijo al desplazarse), cada cuenta con su fila de
título resaltada y su subtotal, y el **total general** al final. Debe, Haber y
Saldo son números, así que se pueden sumar o filtrar directamente.

## Llegar al documento de origen

Cada línea del mayor indica de qué documento salió. Desde la columna de
**Documento** se abre el documento original en una ventana, sin salir del
informe. Es la forma rápida de auditar un movimiento que no cuadra: del saldo se
llega a la línea, y de la línea a la factura o al egreso que la generó.

Si el asiento no nació de un documento (un asiento de diario manual, el balance
inicial, o un asiento migrado del sistema anterior sin documento), la columna
**Documento** queda vacía: el movimiento se identifica por su **Comprobante**.

Los asientos manuales que vienen de la migración del sistema anterior se
muestran con el comprobante `DIARIO-número` o `APERTURA-número`, donde el
número es el id que tenía ese asiento en el sistema anterior (sirve para
ubicarlo allá). Los asientos de documentos migrados conservan su código de
origen (p. ej. `FAC145065`, `EGR67320`).

## Asientos pendientes

Al abrir el módulo, si hay documentos sin su asiento contable generado, el
sistema **pregunta** si desea generarlos antes de continuar. Conviene aceptar: un
mayor calculado con asientos pendientes muestra saldos incompletos.

Si prefiere revisar primero y generar después, puede continuar sin generarlos.

Mientras el sistema verifica si hay asientos pendientes, y hasta que usted
responda el aviso (o termine la generación), el botón **Generar** y las
exportaciones quedan bloqueados.

## Errores frecuentes

- **El saldo no coincide con el balance**: puede haber asientos pendientes de
  generar; acepte la generación al abrir el módulo.
- **Una línea sin tercero ni documento**: los movimientos migrados de otro
  sistema pueden no traer ese dato; el resto se resuelve desde el documento de
  origen.

## Historial de cambios

- **1.3** — La columna **Documento** queda vacía cuando el asiento no tiene
  documento de origen (antes repetía el número del comprobante). Los asientos
  manuales migrados del sistema anterior, que mostraban un código aleatorio
  (`sJCe1fMeygv5Xdx8STC3`), pasan a mostrarse como `DIARIO-número` /
  `APERTURA-número` con el id del asiento en el sistema anterior. Aplica
  también al mayor auxiliar de Estados Financieros.
- **1.2** — Los filtros pasan a una tarjeta fija con un resumen al pie y botón
  **Limpiar**; C. Costo y Proyecto solo se muestran si la empresa los usa. El PDF
  y el Excel adoptan el formato común de reportes (logo en el PDF, filtros aplicados,
  totales; *Página x/y* en el PDF y encabezado fijo en el Excel).
- **1.1** — El aviso de asientos pendientes ya no muestra la sección **Otros
  avisos**; mientras el aviso no se resuelve, **Generar** y las exportaciones
  quedan bloqueados.
- **1.0** — Versión inicial.
