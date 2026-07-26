---
titulo: Carga de productos por Excel
resumen: Alta y actualización masiva del catálogo desde una plantilla de Excel.
categoria: Inventario
ruta_modulo: modulos/carga-productos
tipo: modulo
visibilidad: todos
etiquetas: carga masiva, importar productos, excel, plantilla, actualizar precios, catalogo masivo, subir productos
version: 1.0
orden: 15
estado: activo
---

Este módulo carga o actualiza **muchos productos de una vez** desde un Excel. Es
lo que se usa al arrancar con el catálogo completo, o para actualizar precios de
forma masiva.

## Cómo se usa

1. **Descargue la plantilla** desde el propio módulo. Viene con las hojas fijas y
   los catálogos ya cargados (categorías, marcas, unidades, tarifas de IVA), así
   que no hay que adivinar los valores válidos.
2. Complete las filas.
3. Suba el archivo.
4. Revise el resultado: qué se creó, qué se actualizó y qué tuvo errores.

## Crea o actualiza según el código

La clave es el campo **CODIGO**:

- Si el código **no existe**, se crea el producto.
- Si **ya existe**, se actualiza con los datos del archivo.

Por eso el mismo archivo sirve para dar de alta y para actualizar precios: basta
con cambiar la columna y volver a subirlo.

## Dar de baja

Para desactivar productos no se borran filas: se usa la columna **ESTADO**. Un
producto inactivo deja de aparecer al facturar pero conserva su historial.

## Validaciones de la plantilla

| Columna | Regla |
|---------|-------|
| TIPO | Debe ser exactamente `Producto` o `Servicio` |
| PRECIO_BASE | Numérico y no negativo |
| CODIGO_AUXILIAR / CODIGO_BARRAS | No pueden exceder su longitud máxima |

Los errores se informan por fila, indicando qué columna está mal.

## Recomendación

Pruebe primero con **cinco filas**. Un error de formato repetido en 800 productos
es mucho más caro de corregir que de prevenir.

## Errores frecuentes

- **"TIPO debe ser Producto o Servicio"**: revise la escritura exacta.
- **"PRECIO_BASE debe ser un número"**: la celda tiene texto, un símbolo de
  moneda o una coma en lugar de punto.
- **Se duplicaron productos**: los códigos del archivo no coinciden con los del
  sistema, así que se crearon en lugar de actualizarse.

## Historial de cambios

- **1.0** — Versión inicial.
