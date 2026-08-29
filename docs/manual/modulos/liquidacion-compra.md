---
titulo: Liquidaciones de compra
resumen: Comprobante que emite la empresa cuando el proveedor no puede emitir factura.
categoria: Compras
ruta_modulo: modulos/liquidacion-compra
tipo: modulo
visibilidad: todos
etiquetas: liquidacion de compra, liquidacion, proveedor sin factura, comprobante 03, sri, sustento, eliminar, borrar, borrador, anular
version: 1.2
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

## Eliminar una liquidación

Solo se pueden eliminar las liquidaciones en estado **borrador** —incluidas las
que llegaron así desde una migración—. Una vez emitida al SRI (autorizada) el
comprobante ya no se borra: se **anula**.

El botón **Eliminar** aparece abajo a la izquierda del formulario, y solo si su
usuario tiene el permiso de eliminar en el módulo. Al confirmar:

- La liquidación deja de aparecer en el listado (eliminación lógica: el registro
  se conserva en la base y queda anotado en la auditoría).
- Se **anula el asiento contable** de la liquidación, si tenía uno.
- Se **anulan los pagos (egresos) vinculados** que no estuvieran ya anulados.
- Se limpian los casilleros de la declaración de IVA correspondientes.

Si la liquidación tiene una **retención asociada**, el sistema no la deja
eliminar: primero elimine la retención desde el módulo *Retenciones de compra* y
vuelva a intentarlo.

## Errores frecuentes

- **"Solo se pueden eliminar liquidaciones en estado borrador"**: el comprobante
  ya fue emitido. Use **Anular** en la barra de acciones.
- **"No se puede eliminar la liquidación porque tiene una retención asociada"**:
  elimine primero esa retención en el módulo *Retenciones de compra*.
- **"La liquidación debe tener al menos un ítem"**: añada el detalle antes de
  guardar.
- **"Debe seleccionar el código de sustento tributario"**: es obligatorio para
  que el SRI acepte el comprobante.
- **El SRI rechaza el comprobante**: revise que los datos del proveedor sean
  correctos y que el sustento elegido corresponda al tipo de compra.

## Historial de cambios

- **1.2** — Se puede eliminar una liquidación en estado borrador desde el formulario (botón Eliminar). Anula su asiento y sus pagos vinculados; bloqueada si tiene retención asociada.
- **1.1** — Nuevo botón Excel en el documento de la liquidación (junto a PDF y XML).
- **1.0** — Versión inicial.
