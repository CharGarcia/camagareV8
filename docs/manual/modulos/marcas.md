---
titulo: Marcas
resumen: Marcas comerciales de los productos, para clasificarlos y filtrar por fabricante.
categoria: Inventario
ruta_modulo: modulos/marcas
tipo: modulo
visibilidad: todos
etiquetas: marcas, marca, fabricante, proveedor de marca, clasificar productos
version: 1.1
orden: 50
estado: activo
---

Las **marcas** identifican al fabricante de un producto. Junto con las
categorías, son las dos formas de clasificar el catálogo: la categoría dice *qué
es*, la marca dice *de quién es*.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** de la marca (máximo 100 caracteres).
3. Deje encendido el interruptor **Estado** (*Activo*); apáguelo para dejar la
   marca inactiva.
4. Pulse **Guardar**.

Mientras se guarda aparece el aviso **Guardando marca...** (o **Actualizando
marca...** si ya existía); espere a que termine. Al guardar, la ventana **no se
cierra**: queda abierta (una marca nueva pasa a modo edición) y un siguiente
**Guardar** actualiza esa misma marca. Ciérrela con **Cerrar** cuando termine.
La excepción es cuando la marca se crea con el botón **+** desde la ficha de un
producto: ahí se cierra sola y queda seleccionada en el producto.

## Cuándo usarlas

Si vende productos de varios fabricantes, las marcas le permiten filtrar el
catálogo y analizar qué marca rota mejor. Si todo lo que vende es propio o
genérico, puede prescindir de ellas: no son obligatorias en el producto.

## Eliminar

La eliminación es **lógica**. Los productos que la tenían asignada la conservan.

## Errores frecuentes

- **No aparece al crear un producto**: pertenece a otra empresa o fue eliminada.

## Historial de cambios

- **1.1** — El **Estado** pasa a ser un interruptor (*Activo*), igual que en Productos, Categorías y demás catálogos; la estrella de favorito sigue funcionando. Al guardar, la ventana ya no se cierra (salvo al crear la marca desde un producto) y muestra el aviso *Guardando / Actualizando marca...* mientras el servidor responde.
- **1.0** — Versión inicial.
