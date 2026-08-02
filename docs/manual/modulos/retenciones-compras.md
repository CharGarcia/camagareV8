---
titulo: Retenciones de compra
resumen: Comprobantes de retención que la empresa emite a sus proveedores y envía al SRI.
categoria: Compras
ruta_modulo: modulos/retenciones_compras
tipo: modulo
visibilidad: todos
etiquetas: retencion, retenciones, comprobante de retencion, proveedor, iva, renta, sustento tributario, sri
version: 1.1
orden: 30
estado: activo
---

Cuando la empresa está obligada a retener, al recibir una factura de compra debe
emitir un **comprobante de retención** al proveedor y enviarlo al SRI. Este
módulo gestiona esos comprobantes.

La retención siempre se apoya en un **documento de sustento**: la factura de
compra que la origina.

## Cómo se emite

Lo habitual es generarla desde la propia compra: así el documento de sustento y
los porcentajes del proveedor vienen ya cargados.

1. Abra la compra y genere la retención.
2. Revise el **proveedor** y la **fecha de emisión**.
3. Compruebe el **documento de sustento**: tipo, número y fecha de emisión.
4. Revise los porcentajes de IVA y de renta.
5. Guarde y envíe al SRI.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio |
| Fecha de emisión | Obligatoria |
| Tipo de documento de sustento | Obligatorio, y debe ser uno de los códigos válidos del SRI |
| Número del documento de sustento | Obligatorio |
| Fecha de emisión del documento de sustento | Obligatoria |

Los porcentajes se proponen desde la ficha del proveedor, pero se pueden cambiar
en cada retención.

## Relación con la compra

Una compra que ya tiene retención **no se puede eliminar**: primero hay que
eliminar la retención. Es una protección deliberada, porque la retención declara
al SRI una compra que dejaría de existir.

## Documentos del módulo

Desde la retención guardada están disponibles el **PDF** del comprobante, su
**Excel**, su **XML** y el envío por **correo**, en la barra de acciones al
inicio del formulario.

## Errores frecuentes

- **"El tipo de documento de sustento no es válido"**: use uno de los códigos
  admitidos por el SRI.
- **Los porcentajes salen equivocados**: revise las retenciones predeterminadas
  en la ficha del proveedor.
- **No puedo eliminar la compra**: elimine antes su retención.

## Historial de cambios

- **1.1** — Nuevo botón Excel en el documento de la retención (junto a PDF y XML).
- **1.0** — Versión inicial.
