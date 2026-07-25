---
titulo: Productos
resumen: Catálogo de productos y servicios que se factura, se compra y se controla en inventario.
categoria: Inventario
ruta_modulo: modulos/productos
tipo: modulo
visibilidad: todos
etiquetas: productos, articulos, servicios, catalogo, precio, costo, iva, ice, stock, codigo de barras, inventariable
version: 1.0
orden: 10
estado: activo
---

El módulo de **Productos** es el catálogo de todo lo que la empresa vende o
compra. Alimenta las facturas, proformas, compras e inventario: si algo no está
aquí, no se puede facturar ni controlar su stock.

## Producto o servicio

El primer campo del formulario decide el resto: **Tipo de producción**.

- **Producto / Bien**: algo físico. Puede llevar control de stock.
- **Servicio**: mano de obra, asesoría, mantenimiento. No se inventaría.

Al cambiarlo, el formulario muestra u oculta los campos que aplican a cada caso.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija si es producto o servicio.
3. Complete el **código principal** (el sistema propone el siguiente disponible),
   el **nombre** y la **categoría**.
4. Indique el **precio base** y, si aplica, el **costo**.
5. Elija la **tarifa de IVA** que le corresponde.
6. Guarde.

## Campos principales

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de producción | Sí | Producto/Bien o Servicio |
| Código principal | Sí | Identificador con el que aparece en los documentos. Se propone automáticamente |
| Código auxiliar | No | Segundo código propio de la empresa |
| Código de barras | No | Para lectura con pistola en el punto de venta |
| Nombre | Sí | Descripción que sale impresa en la factura |
| Categoría / Marca | No | Sirven para agrupar y filtrar el catálogo |
| Unidad de medida | No | Unidad en la que se vende (unidad, caja, kilo…) |
| Precio base | Sí | Precio de venta antes de impuestos |
| Costo | No | Costo de referencia del producto |
| Tarifa de IVA | Sí | La tarifa vigente que aplica al producto |
| ICE | No | Solo para productos gravados con este impuesto |
| Inventariable | No | Si se lleva control de existencias |
| Stock mínimo / máximo | No | Referencias para los avisos de reposición |
| Se compra / Se vende | No | En qué documentos aparece el producto al buscarlo |
| Cuenta de inventario | No | Cuenta contable donde se registra el producto |
| Cuenta de costo o gasto | No | Cuenta contable de su costo |
| Imagen | No | Se muestra en el punto de venta |
| Estado | Sí | Activo o inactivo |

## Inventariable

Marque **Inventariable** solo cuando quiera que el sistema lleve las existencias
del producto: cada compra suma y cada venta resta, y el movimiento queda en el
kardex.

Los servicios y los productos que no controla por unidades (por ejemplo, insumos
menores) no deben marcarse. Un producto no inventariable se puede facturar sin
problema; simplemente no genera movimiento de stock.

## Se compra / se vende

Estas dos casillas controlan dónde aparece el producto al buscarlo:

- **Se vende**: aparece en facturas, proformas y punto de venta.
- **Se compra**: aparece en compras y órdenes de compra.

Sirven para que quien factura no tenga que ver los insumos internos, y viceversa.

## Buscar en el listado

Además del texto libre, el buscador acepta filtros `clave:valor`
(`categoria:Bebidas`, `codigo:0012`), rangos numéricos y negaciones con `-`.

El listado se puede ordenar por cualquier columna, ocultar columnas y **exportar
a PDF y Excel**. Cada usuario conserva su configuración de columnas.

## Permisos

Con **acceso total** se ven los productos de toda la empresa. Sin él, cada
usuario ve solo los que él mismo creó — lo que en un catálogo compartido suele
ser indeseable, así que revise este permiso si alguien reporta que "faltan
productos".

## Eliminar

La eliminación es **lógica**: el producto desaparece del listado pero los
documentos que ya lo usan siguen intactos. Si solo quiere dejar de usarlo,
prefiera cambiar su **estado a inactivo**: así conserva el historial y deja de
aparecer al facturar.

## Errores frecuentes

- **No aparece al facturar**: no tiene marcado *Se vende*, está inactivo, o
  pertenece a otra empresa.
- **Sale con IVA equivocado**: revise la tarifa de IVA del producto. Un producto
  con tarifa mal configurada arrastra el error a todas las facturas nuevas.
- **Un servicio descuadra el inventario**: no debería estar marcado como
  inventariable. Desmárquelo y revise su kardex.
- **El stock no cuadra**: compruebe que el producto es inventariable y que las
  compras que lo afectan quedaron vinculadas a este producto del catálogo.

## Historial de cambios

- **1.0** — Versión inicial.
