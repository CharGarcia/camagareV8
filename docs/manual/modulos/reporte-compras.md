---
titulo: Reporte de compras
resumen: Compras del periodo con filtros por proveedor y producto, agrupables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_compras
tipo: modulo
visibilidad: todos
etiquetas: reporte de compras, compras, cuanto compre, por proveedor, por producto, gasto, exportar, pdf, excel
version: 1.1
orden: 20
estado: activo
---

El **reporte de compras** muestra qué se compró en el periodo, a quién y de qué.
Es el espejo del reporte de ventas.

## Filtros

| Filtro | Para qué |
|--------|----------|
| Tipo de documento | Un solo tipo (factura, nota de venta, liquidación de compra, nota de crédito, nota de débito, etc.) o **Todas las compras**, donde las notas de crédito restan |
| Fecha desde / hasta | El periodo a consultar |
| Proveedor | Compras a un proveedor concreto |
| Producto | Compras de un producto concreto |

Se combinan entre sí para acotar la consulta.

El selector **Tipo de documento** empieza siempre con **Todas las compras** y
luego ofrece únicamente los tipos que ya tienen compras registradas en la
empresa (por ejemplo facturas, notas de crédito o notas de venta). Un tipo de
documento del que aún no se ha registrado ninguna compra no aparece en la lista.

## Agrupación

Los resultados se agrupan por proveedor, producto o periodo, según lo que se
quiera responder: *a qué proveedor le compro más*, *qué producto me está costando
más este año*, *cómo evoluciona el gasto mes a mes*.

## Compras por producto

Filtrar por producto sirve para negociar: muestra cuánto se le ha comprado de ese
artículo a cada proveedor y a qué precio, lo que deja ver si el precio subió sin
que nadie lo notara.

Tenga presente que solo aparece con precisión lo que esté **vinculado a un
producto del catálogo**: las líneas de compra sin vincular llevan el código del
proveedor y no se agrupan con las demás.

## Exportar

Disponible en **PDF** y **Excel**.

## Errores frecuentes

- **Un producto no aparece con todo lo comprado**: hay líneas de compra sin
  vincular al catálogo.
- **Las cifras no cuadran con cuentas por pagar**: este reporte muestra lo
  comprado, no lo pendiente de pago.

## Historial de cambios

- **1.1** (03-09-2026) — Se documenta el filtro *Tipo de documento*: lista solo
  los tipos con compras registradas, con "Todas las compras" como primera opción.
- **1.0** — Versión inicial.
