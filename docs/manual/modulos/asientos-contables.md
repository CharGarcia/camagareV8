---
titulo: Asientos contables
resumen: Registro contable de cada operación; la mayoría se genera sola desde los documentos.
categoria: Contabilidad
ruta_modulo: modulos/asientos_contables
tipo: modulo
visibilidad: todos
etiquetas: asientos, asiento contable, diario, debe, haber, partida doble, cuadrado, comprobante, contabilidad, imprimir, pdf, excel, documento origen, cuadre con el documento, total de la factura, cuenta por cobrar, cartera
version: 1.6
orden: 20
estado: activo
---

Los **asientos contables** son el registro de cada operación en las cuentas. La
inmensa mayoría **no se escribe a mano**: la genera el sistema al guardar una
factura, un ingreso, un egreso, una compra o un traspaso, según la configuración
contable de la empresa.

Este módulo sirve para consultarlos, y para registrar los asientos de diario que
no nacen de ningún documento (provisiones, depreciaciones, ajustes).

## Un asiento tiene que cuadrar

La regla que el sistema no deja saltarse: **el total del Debe debe ser igual al
total del Haber**. Si no cuadran, el mensaje muestra ambas cifras para que vea la
diferencia.

Además:

- Debe tener **al menos un detalle de cuenta**.
- Todos los valores deben ser **mayores a cero**.
- La suma de los detalles debe coincidir con el total del asiento.

## El asiento de un documento debe reflejar su importe

Cuando el asiento **viene de un documento** (factura de venta, recibo de venta,
factura de reembolso, compra, liquidación, nota de crédito, nota de débito,
retención, ingreso o egreso), cuadrar Debe con Haber no alcanza: el asiento tiene que seguir
reflejando el **importe de ese documento**. Al editarlo a mano, el modal lo
muestra en una fila al pie del detalle, junto a los totales:

| Lo que muestra | Qué significa |
|----------------|---------------|
| **TOTAL DOCUMENTO** con el número del documento | El importe contra el que se compara |
| *Coincide con la cartera del asiento* | Todo en orden |
| *Cartera del asiento: … · diferencia: …* | El asiento ya no refleja el documento |
| *Falta la línea de la cuenta por cobrar / pagar* | El asiento perdió la línea de cartera |

Esa fila se **actualiza sola mientras edita**: al cambiar un valor del Debe o del
Haber, al cambiar la cuenta de una línea o al quitar una línea, el aviso vuelve a
calcularse en el momento, sin esperar a guardar. También aparece cuando abre el
asiento de un documento que **todavía no se ha contabilizado** (formulario en
blanco), para armar las líneas sabiendo con qué importe tienen que cerrar.

**Contra qué se compara.** En facturas y recibos de venta, notas de débito,
facturas de reembolso, compras y liquidaciones se mide sobre la línea de la
**cuenta por cobrar** (o por pagar), que es la que el sistema fija en el total
del documento. El Debe total no sirve de referencia: en una venta incluye además
el costo de ventas y el descuento, así que compararlo con el total de la factura
daría diferencia estando todo bien. En los demás documentos —notas de crédito,
retenciones, ingresos y egresos— se compara el **total del Debe** del asiento.
Se aceptan diferencias de hasta **3 centavos**, que son redondeo.

**Qué pasa al guardar.**

- Si hay diferencia, el sistema **avisa y pregunta**: se puede guardar igual
  ("Guardar de todos modos"), y esa decisión queda registrada en la auditoría
  del sistema con las dos cifras.
- Si al asiento le **falta la línea de la cuenta por cobrar/pagar** configurada
  para ese documento, **no se guarda**: sin esa línea no hay forma de comprobar
  que el asiento refleje el documento. Agregue la línea con la cuenta de cartera
  y vuelva a guardar.
- **No se comprueba** cuando el asiento se guarda como **borrador** (igual que el
  cuadre Debe/Haber, se exige recién al registrarlo), en asientos de **diario**
  (no tienen documento), en documentos con total en cero, ni en los orígenes
  cuyo total no es comparable con el asiento por diseño: **nómina** (el asiento
  incluye aportes y provisiones), **consignaciones**, **retornos**, **cambios de
  producto** y los asientos **migrados** del sistema anterior.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha | Obligatoria |
| Tipo de comprobante | Obligatorio |
| Concepto | Obligatorio: explica de qué se trata el asiento |
| Detalle | Al menos una línea, cuadrada y con valores mayores a cero |

## Asientos automáticos frente a asientos de diario

- **Automáticos**: nacen de un documento. Si el documento se modifica, el asiento
  se regenera; si se anula, el asiento se anula. **No conviene editarlos a mano**:
  el sistema los vuelve a generar desde el documento.
- **De diario**: los escribe el contador. Son los únicos que se mantienen tal
  cual se escribieron.

## Cada módulo genera lo suyo al abrirlo

Además de la sincronización que se lanza desde esta pantalla, **cada módulo
contabiliza sus propios documentos cuando se lo abre**: entrar a Facturas de
Venta genera los asientos que les falten a las facturas, entrar a Compras los de
las compras, y así con los demás. Ocurre en segundo plano, sin avisos y sin
intervención de nadie. Ver
[Generación automática de asientos contables](guias/generacion-automatica-de-asientos).

Eso no reemplaza al aviso de esta pantalla, que sigue siendo el lugar donde se
ve **qué quedó sin contabilizar y por qué**: la generación automática es
silenciosa a propósito, y lo que no puede resolver (una cuenta sin configurar, un
período cerrado) se sigue reportando aquí.

## Aviso antes de generar asientos en masa

Al sincronizar asientos, antes de contabilizar nada el sistema revisa la
configuración contable de la empresa y avisa de lo que encuentre: conceptos o
formas de cobro/pago sin cuenta, y **cuentas cuya naturaleza no corresponde al
concepto** (por ejemplo una cuenta de ventas puesta en *Cuenta por cobrar*, o una
cuenta de caja en un concepto de nómina).

Ese último aviso conviene atenderlo antes de continuar: la sincronización genera
los asientos con las cuentas tal como estén configuradas, así que un concepto mal
apuntado se propaga a todos los documentos de golpe y el error solo se nota al
revisar el balance.

## Diferencias de centavos

En documentos con impuestos, la base y el IVA pueden dejar diferencias de un
centavo al redondear. El sistema las absorbe automáticamente en la línea de mayor
monto del lado que quedó corto. Un descuadre mayor que un redondeo sí detiene el
proceso: eso indica un error real.

## Imprimir en PDF o Excel

Al abrir un asiento ya guardado aparece, debajo del título del modal, una barra
con dos botones:

- **PDF** (ícono rojo): descarga el asiento con su cabecera, el detalle de
  cuentas (con centro de costo, proyecto y documento/ref) y los totales de
  Debe y Haber.
- **Excel** (ícono verde): la misma información en una hoja de cálculo.

Estos botones no aparecen en un asiento nuevo sin guardar, porque todavía no
tiene número de comprobante.

## Ver el documento que originó el asiento

Cuando el asiento nace de un documento (factura de venta, compra, ingreso,
egreso, nota de crédito/débito, retención, liquidación de compra, importación,
consignación, etc.), la misma barra muestra el botón **Ver Documento**: abre
una ventana de solo lectura con el documento completo, sin salir del asiento.
El botón no aparece en asientos manuales (tipo Diario) ni en los generados por
nómina, activos fijos, declaraciones o traspasos, porque esos procesos no
tienen un documento individual con tercero que mostrar.

## Errores frecuentes

- **"El asiento no está cuadrado"**: el mensaje muestra el total del Debe y del
  Haber; la diferencia le dice qué línea falta o sobra.
- **"El asiento debe contener al menos un detalle de cuenta"**: falta añadir
  líneas.
- **"El asiento no cuadra con el documento"**: la cartera del asiento (o su total
  Debe) dejó de coincidir con el importe del documento. Revise la línea que
  cambió; si el ajuste es correcto, confirme con "Guardar de todos modos".
- **"El asiento no tiene ninguna línea con la cuenta de cartera configurada"**:
  falta la línea de la cuenta por cobrar/pagar del documento. Agréguela y
  vuelva a guardar.
- **Modifiqué un asiento automático y volvió a cambiar**: es el comportamiento
  esperado; corrija el documento de origen, no el asiento.

## Historial de cambios

- **1.6** — El aviso de cuadre contra el documento se actualiza en vivo al editar
  (montos, cuenta de la línea o líneas quitadas) y también se muestra al abrir el
  asiento de un documento que todavía no se ha contabilizado.
- **1.5** — Al editar el asiento de un documento, el modal muestra el total de ese
  documento y avisa si el asiento deja de reflejarlo; se puede guardar igual
  confirmando, salvo que falte la línea de la cuenta por cobrar/pagar.
- **1.4** — Corregido: el botón **Guardar Asiento** aparecía apagado y no dejaba
  registrar ni actualizar el asiento desde el modal.
- **1.3** — Cada módulo genera en segundo plano los asientos que le faltan al abrirlo, sin mostrar mensajes.
- **1.2** — Al sincronizar asientos, el resumen previo avisa también de las
  cuentas configuradas con una naturaleza que no corresponde al concepto, antes
  de generar los asientos.
- **1.1** — Botones de impresión en PDF y Excel del asiento, y botón para ver
  el documento origen (factura, compra, egreso, etc.) sin salir del modal.
- **1.0** — Versión inicial.
