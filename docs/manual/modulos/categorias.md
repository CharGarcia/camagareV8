---
titulo: Categorías
resumen: Agrupación de productos para organizar el catálogo y filtrar los reportes.
categoria: Inventario
ruta_modulo: modulos/categorias
tipo: modulo
visibilidad: todos
etiquetas: categorias, categoria, grupos, familias, clasificar productos, agrupar
version: 1.1
orden: 40
estado: activo
---

Las **categorías** agrupan los productos por familia: bebidas, repuestos,
servicios, herramientas. Sirven para encontrar productos rápido, filtrar el
listado y analizar las ventas por grupo.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** de la categoría (máximo 100 caracteres).
3. Deje encendido el interruptor **Estado** (*Activo*); apáguelo para dejar la
   categoría inactiva.
4. Pulse **Guardar**.

Mientras se guarda aparece el aviso **Guardando categoría...** (o **Actualizando
categoría...** si ya existía); espere a que termine. Al guardar, la ventana **no
se cierra**: queda abierta (una categoría nueva pasa a modo edición) y un siguiente
**Guardar** actualiza esa misma categoría. Ciérrela con **Cerrar** cuando termine.
La excepción es cuando la categoría se crea con el botón **+** desde la ficha de un
producto: ahí se cierra sola y queda seleccionada en el producto.

Es un catálogo deliberadamente simple: lo importante es acordar los nombres antes
de empezar a cargar productos, porque reorganizar categorías después obliga a
tocar producto por producto.

## Recomendación

Use pocas categorías y bien diferenciadas. Una lista de cincuenta categorías con
dos productos cada una no ayuda a nadie a encontrar nada.

## Eliminar

La eliminación es **lógica**. Los productos que ya la tenían asignada la
conservan, así que conviene reasignarlos antes de eliminarla.

## Errores frecuentes

- **No aparece al crear un producto**: pertenece a otra empresa o fue eliminada.

## Historial de cambios

- **1.1** — El **Estado** pasa a ser un interruptor (*Activo*), igual que en Productos, Clientes y demás catálogos; la estrella de favorito sigue funcionando. Al guardar, la ventana ya no se cierra (salvo al crear la categoría desde un producto) y muestra el aviso *Guardando / Actualizando categoría...* mientras el servidor responde.
- **1.0** — Versión inicial.
