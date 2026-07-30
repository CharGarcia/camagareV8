---
titulo: Compras
resumen: Registro de las facturas de compra, carga desde el XML del SRI y entrada de la mercadería al inventario.
categoria: Compras
ruta_modulo: modulos/compras
tipo: modulo
visibilidad: todos
etiquetas: compras, compra, factura de compra, proveedor, xml, sri, entrada de mercaderia, vincular producto, retencion
version: 1.1
orden: 20
estado: activo
---

El módulo de **Compras** registra las facturas que recibe la empresa. De aquí
salen las cuentas por pagar, las retenciones de compra, la entrada de mercadería
al inventario y el gasto contable.

## Tres formas de registrar una compra

- **Desde el XML del SRI**: la forma recomendada. El comprobante llega ya
  descargado del SRI y el sistema lee el XML y arma la compra completa.
- **Manual**: se captura a mano, para comprobantes que no son electrónicos.
- **Desde una orden de compra**: se convierte la orden en compra.

Al cargar desde XML, el sistema valida que el archivo sea un comprobante del SRI
con formato válido. Si el comprobante no trae XML o el archivo está dañado, lo
rechaza con un mensaje explícito.

## No se puede repetir un comprobante

El sistema impide registrar **dos veces el mismo número de comprobante para el
mismo proveedor**. Si aparece ese aviso, la compra ya está en el sistema: búsquela
en el listado antes de volver a capturarla.

## Vincular los productos (paso clave)

Las compras que llegan del SRI traen los **códigos del proveedor**, que casi nunca
coinciden con los de su catálogo. Por eso, antes de que la mercadería entre al
inventario hay que **vincular cada línea con un producto suyo**.

Si intenta procesar la entrada sin vincular, el sistema avisa:

> El ítem '…' debe estar vinculado a un producto del catálogo.

La vinculación se guarda: la próxima compra de ese proveedor con el mismo código
se relaciona sola. Es un trabajo que se hace una vez por producto y proveedor.

## Entrada al inventario

Una compra registrada **no mueve el stock por sí sola**. La mercadería entra
cuando se procesan las entradas, indicando la bodega de destino. Ese paso:

- Suma el stock del producto en esa bodega.
- Genera el movimiento en el kardex con su costo.

Solo entran los productos **inventariables** y vinculados al catálogo.

## Retenciones

Desde la compra se genera la retención al proveedor, con los porcentajes que
tenga configurados en su ficha. La retención es un documento aparte que también
se envía al SRI.

**Importante**: una compra con retención asociada **no se puede eliminar**. Hay
que eliminar primero la retención. El sistema lo avisa con ese mismo mensaje.

## Notas de crédito y débito de compra

Las notas de crédito y débito que emite el proveedor se registran también en este
módulo y quedan vinculadas al documento que modifican. En Cuentas por Pagar no
aparecen como documentos sueltos: se restan del saldo de la factura a la que
corresponden.

## Documentos del módulo

Desde la compra guardada se puede generar el **PDF** del documento y consultar su
**XML**. Los botones están en la barra de acciones al inicio del formulario.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
compras que coinciden con el buscador y el orden aplicados en ese momento (no
solo la página visible).

## Permisos

Con **acceso total** se ven las compras de toda la empresa; sin él, cada usuario
ve solo las que registró.

## Errores frecuentes

- **"Ya existe una compra registrada con ese número de comprobante para este
  proveedor"**: está duplicando. Búsquela en el listado.
- **"El ítem debe estar vinculado a un producto del catálogo"**: falta vincular
  esa línea antes de procesar la entrada al inventario.
- **"No se puede eliminar la compra porque tiene una retención asociada"**:
  elimine primero la retención.
- **"El comprobante no tiene XML"** o **"El XML no tiene un formato válido del
  SRI"**: el archivo no es un comprobante electrónico válido; regístrela a mano.
- **El stock no subió tras registrar la compra**: registrar no es lo mismo que
  procesar la entrada. Compruebe además que el producto sea inventariable.

## Historial de cambios

- **1.1** — Corregidos los botones Excel y PDF del listado: no descargaban nada.
- **1.0** — Versión inicial.
