---
titulo: Traspasos
resumen: Traslado de dinero entre formas de pago, por ejemplo de caja a banco, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/traspasos
tipo: modulo
visibilidad: todos
etiquetas: traspaso, traspasos, transferencia interna, caja a banco, deposito, mover dinero, saldo, formas de pago, excel, exportar
version: 1.1
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

## Historial de cambios

- **1.1** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.0** — Versión inicial.
