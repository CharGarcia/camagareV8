---
titulo: Reporte de compras
resumen: Compras del periodo con filtros por proveedor y producto, agrupables y exportables.
categoria: Reportes
ruta_modulo: modulos/reporte_compras
tipo: modulo
visibilidad: todos
etiquetas: reporte de compras, compras, cuanto compre, por proveedor, por producto, gasto, exportar, pdf, excel, establecimientos, sucursales, matriz, mismo ruc, consolidado por ruc
version: 1.2
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

## Consolidar varios establecimientos (solo desde la matriz)

Cuando un mismo RUC tiene varios establecimientos registrados como empresas
distintas (matriz y sucursales), el reporte muestra por defecto solo las compras
de la empresa activa. Desde la **matriz** aparece el filtro **Establecimientos**,
el mismo que tienen Cuentas por Cobrar, Cuentas por Pagar y el Reporte
Consolidado:

- **Solo este (matriz)**: comportamiento normal.
- **Consolidado (N establec.)**: junta las compras de todos los establecimientos
  del mismo RUC a los que el usuario tiene acceso. Tarjetas, tabla, gráfico, PDF
  y Excel consolidan de la misma forma; las agrupaciones (por proveedor,
  producto, fecha o mes) suman los establecimientos en una sola fila.

Reglas:

- El filtro **solo aparece en la matriz** del grupo (la empresa marcada como
  matriz en *Empresas*) y solo si existe al menos otro establecimiento accesible.
- En el **detallado**, cada comprobante lleva un **badge con el código del
  establecimiento** (001, 002, …) delante del número. En el PDF y el Excel del
  detallado se agrega la columna **Estab.** y el encabezado indica *Alcance:
  Consolidado por RUC*.
- Los filtros **Proveedor** y **Producto** se cruzan entre establecimientos por
  identificación y por código de producto (cada establecimiento tiene su propia
  lista).
- El selector **Tipo de documento** lista los tipos con compras de la empresa
  activa; en consolidado, un tipo que solo exista en una sucursal se consulta
  con "Todas las compras".
- En la agrupación **por producto**, un mismo producto puede aparecer una vez
  por establecimiento si su código no coincide entre ellos.
- Cada establecimiento se filtra por **su propio ambiente** (producción o
  pruebas), no por el de la matriz.

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

- **1.2** — Nuevo filtro **Establecimientos** (solo desde la matriz del grupo
  RUC) para consolidar las compras de todas las sucursales del mismo RUC: badge
  del establecimiento en el detallado, columna "Estab." en PDF y Excel, y línea
  "Alcance" en el encabezado. Los filtros Proveedor y Producto cruzan por
  identificación y código entre establecimientos.
- **1.1** (03-09-2026) — Se documenta el filtro *Tipo de documento*: lista solo
  los tipos con compras registradas, con "Todas las compras" como primera opción.
- **1.0** — Versión inicial.
