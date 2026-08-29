---
titulo: Reporte de retenciones
resumen: Retenciones practicadas y recibidas en un solo listado, para cuadrar la declaración.
categoria: Reportes
ruta_modulo: modulos/reporte_retenciones
tipo: modulo
visibilidad: todos
etiquetas: reporte de retenciones, retenciones practicadas, retenciones recibidas, iva, renta, declaracion, cuadrar 103
version: 1.2
orden: 50
estado: activo
---

Este reporte reúne en un mismo listado las **retenciones de compra** (las que la
empresa practicó a sus proveedores) y las **de venta** (las que le practicaron
sus clientes).

## Para qué sirve

Para cuadrar la declaración antes de presentarla. Las retenciones practicadas son
lo que hay que pagar al SRI; las recibidas son crédito tributario. Tenerlas
juntas evita presentar con una cifra y descubrir después que faltaba un
comprobante.

## Filtros

Por periodo, por tipo de retención (IVA o renta) y por tercero.

El selector **Mostrar** (Retenciones de compras / de ventas) tiene una estrella de
favorito: al marcarla, ese valor queda precargado la próxima vez que se abre el
reporte.

## Ver por

- **Línea de impuesto (detalle)**: una fila por cada línea de impuesto retenido
  (más granular).
- **Por retención (resumen)**: una fila por comprobante de retención, con el
  desglose Renta/IVA/ISD ya sumado.
- **Sujeto retenido**: una fila por cliente/proveedor, con el total acumulado.

## Detalle de una retención

En las vistas **Línea de impuesto** y **Por retención**, un clic sobre la fila
abre un panel lateral con el desglose completo de esa retención: número, fecha,
sujeto retenido, cada línea de impuesto (código, concepto, base imponible, %) y
los totales de Renta/IVA/ISD. La vista **Sujeto retenido** no lo tiene porque
agrupa varios comprobantes en una sola fila, sin una retención puntual que mostrar.

## Exportar

Disponible en **PDF** y **Excel**.

## Errores frecuentes

- **No coincide con la declaración de retenciones**: revise que todos los
  comprobantes del periodo estén emitidos y con la fecha correcta.
- **Falta una retención recibida**: compruebe que se haya registrado en
  Retenciones de venta; si no se registra, no aparece aquí ni descuenta la
  factura.

## Historial de cambios

- **1.2** — Estrella de favorito en el selector "Mostrar". La vista "Comprobante
  (resumen)" se renombró a "Por retención (resumen)". Clic sobre una fila (vistas
  Detalle y Por retención) abre un panel lateral con el desglose completo de esa
  retención.
- **1.1** — El detalle exportado a Excel/PDF ya no se corta en 5000 filas; el tope
  de 5000 se mantiene solo para la vista en pantalla.
- **1.0** — Versión inicial.
