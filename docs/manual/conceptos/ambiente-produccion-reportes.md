---
titulo: Los reportes solo muestran documentos de producción
resumen: Por qué los reportes, la Declaración de Renta y los estados financieros ignoran los documentos emitidos en ambiente de pruebas del SRI.
categoria: Reportes
tipo: concepto
visibilidad: todos
etiquetas: ambiente, produccion, pruebas, ambiente de pruebas, ambiente de produccion, tipo ambiente, sri, documentos de prueba, no aparece en el reporte, reporte vacio, faltan ventas, faltan compras, facturas de prueba, reporte de ventas, reporte de compras, estados financieros, declaracion de renta, ats, cartera, cuentas por cobrar, cuentas por pagar
version: 1.0
orden: 5
estado: activo
---

El SRI tiene dos ambientes para los comprobantes electrónicos: **pruebas** y
**producción**. Solo los documentos de producción tienen validez tributaria.
Por eso los reportes del sistema muestran **únicamente documentos de
producción**, sin importar en qué ambiente esté configurada la empresa.

## Qué reportes siguen esta regla

- Reporte de Ventas, Ventas por Vendedor, Reporte de Compras, Reporte de
  Retenciones y Retenciones pendientes.
- Reporte de Ingresos y Egresos, Reporte de Inventarios, Reporte de Cartera,
  Reporte de Pedidos y Reporte Consolidado.
- Cuentas por Cobrar y Cuentas por Pagar.
- Estados Financieros, Mayores y Balance de comprobación.
- Anexo Transaccional (ATS) y Declaración de Impuesto a la Renta.

## Qué no cambia

- **Los listados de cada módulo** (Facturas de venta, Compras, Retenciones…)
  siguen mostrando el ambiente en que está la empresa. Así se puede emitir y
  revisar documentos mientras la empresa todavía está en pruebas.
- **Los documentos se guardan igual**: cada factura, compra o asiento conserva
  el ambiente en que se emitió.
- **Declaración de IVA, Retenciones (formulario 103), Anexo de Dividendos y
  Auditoría contable** trabajan con el ambiente actual de la empresa, porque
  guardan su propia declaración, asiento y egreso. En una empresa que ya está en
  producción, esto equivale a usar solo documentos de producción.
- **Reporte de caja (POS) y Reporte de Restaurante** cuadran lo cobrado en caja
  y en las comandas, que no distinguen ambiente, así que no aplican este filtro.
- **La app móvil** mantiene por ahora el comportamiento anterior en sus reportes
  de ventas y compras (ambiente actual de la empresa).

## Errores frecuentes

- **El reporte sale vacío y la empresa está en pruebas**: es lo esperado. Los
  documentos emitidos en pruebas no aparecen en los reportes; cuando la empresa
  pase a producción, los documentos nuevos sí aparecerán.
- **Faltan documentos antiguos en un reporte**: revise que esos documentos estén
  en producción. Documentos migrados o emitidos mientras la empresa estaba en
  pruebas quedan con ese ambiente y no se cuentan.

## Historial de cambios

- **1.0** — Versión inicial: los reportes muestran solo documentos de producción.
