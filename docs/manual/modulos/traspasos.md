---
titulo: Traspasos
resumen: Traslado de dinero entre formas de pago, por ejemplo de caja a banco, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/traspasos
tipo: modulo
visibilidad: todos
etiquetas: traspaso, traspasos, transferencia interna, caja a banco, deposito, mover dinero, saldo, formas de pago, excel, exportar, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion
version: 1.2
orden: 30
estado: activo
---

Un **traspaso** mueve dinero de una forma de pago a otra dentro de la misma
empresa: depositar en el banco lo recaudado en caja, pasar fondos entre dos
cuentas bancarias, entregar efectivo a caja chica.

No es un ingreso ni un egreso: el dinero no entra ni sale de la empresa, solo
cambia de sitio. Por eso no afecta a cuentas por cobrar ni por pagar.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija la forma de pago de **origen** (de dónde sale el dinero).
3. Elija la de **destino** (a dónde va).
4. Indique el monto y la fecha.
5. Guarde.

## Reglas que aplica el sistema

- **No se puede traspasar más de lo disponible.** Si el saldo del origen no
  alcanza, el aviso indica cuánto hay realmente disponible.
- **El origen debe tener saldo determinable**: no sirve una forma de pago de tipo
  *Anticipo*, ni una que esté inactiva.
- **El secuencial no se repite**: si el número ya existe, hay que usar otro.
- **El periodo contable manda**: no se registra ni se anula un traspaso en un
  periodo cerrado.

## Anular

Un traspaso se **anula**, no se elimina, y su asiento contable se anula con él.
Un traspaso ya anulado no se puede volver a anular.

## Asiento contable

Cada traspaso genera su asiento automáticamente: acredita la forma de pago de
origen y debita la de destino, según la configuración contable de la empresa.

## Comprobante en PDF y Excel

Al abrir un traspaso ya guardado, la barra de acciones superior del modal
muestra el botón **PDF** (comprobante de traspaso) y, junto a él, el botón
**Excel**: descarga el mismo comprobante (fecha, estado, concepto y el
movimiento origen → destino con el monto) en un archivo `.xlsx`. Ambos botones
quedan ocultos mientras el traspaso es nuevo y no se ha guardado.

## Errores frecuentes

- **"Saldo insuficiente en la forma de pago de origen"**: el mensaje indica el
  disponible real. Revise si hay movimientos posteriores que no esperaba.
- **"No se pudo determinar el saldo de la forma de pago de origen"**: es de tipo
  anticipo o está inactiva; elija otra.
- **"El número de secuencial ya existe"**: cambie el número.
- **"El periodo contable está cerrado"**: la fecha cae en un mes cerrado.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.2** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.1** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.0** — Versión inicial.
