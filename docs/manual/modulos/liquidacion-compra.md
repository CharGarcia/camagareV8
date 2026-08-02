---
titulo: Liquidaciones de compra
resumen: Comprobante que emite la empresa cuando el proveedor no puede emitir factura.
categoria: Compras
ruta_modulo: modulos/liquidacion-compra
tipo: modulo
visibilidad: todos
etiquetas: liquidacion de compra, liquidacion, proveedor sin factura, comprobante 03, sri, sustento
version: 1.1
orden: 40
estado: activo
---

La **liquidación de compra** es el comprobante que emite la propia empresa cuando
compra a alguien que no puede darle factura. En lugar de recibir el documento, la
empresa lo emite y lo envía al SRI.

## Cuándo se usa

En los casos que la normativa permite: proveedores sin RUC, personas naturales no
obligadas a facturar en ciertos supuestos, servicios ocasionales.

No es un sustituto de la factura: si el proveedor puede emitirla, corresponde
registrar una compra normal.

## Cómo se emite

1. Pulse **Nuevo**.
2. Elija el **proveedor**.
3. Indique la **fecha de emisión**.
4. Elija la **serie de emisión** y revise el **secuencial**.
5. Elija el **código de sustento tributario**.
6. Añada al menos un **ítem**.
7. Guarde y envíe al SRI.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio |
| Fecha de emisión | Obligatoria |
| Serie de emisión | Obligatoria |
| Código de sustento tributario | Obligatorio |
| Secuencial | Obligatorio |
| Ítems | Al menos uno |

## Documentos del módulo

Desde la liquidación guardada están disponibles el **PDF** del documento, su
**Excel**, su **XML** y el envío por **correo** o **WhatsApp**, en la barra de
acciones al inicio del formulario.

## Errores frecuentes

- **"La liquidación debe tener al menos un ítem"**: añada el detalle antes de
  guardar.
- **"Debe seleccionar el código de sustento tributario"**: es obligatorio para
  que el SRI acepte el comprobante.
- **El SRI rechaza el comprobante**: revise que los datos del proveedor sean
  correctos y que el sustento elegido corresponda al tipo de compra.

## Historial de cambios

- **1.1** — Nuevo botón Excel en el documento de la liquidación (junto a PDF y XML).
- **1.0** — Versión inicial.
