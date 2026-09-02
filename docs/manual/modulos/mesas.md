---
titulo: Mesas
resumen: Mesas del local, sobre las que se abren las comandas del servicio de restaurante.
categoria: Restaurante
ruta_modulo: modulos/mesas
tipo: modulo
visibilidad: todos
etiquetas: mesas, mesa, restaurante, salon, comanda, ocupada, disponible, tablero, cocina, barra, pantalla de preparacion, kds, turno de caja, punto de emision, no hay caja abierta, mesa ocupada por otro usuario
version: 1.2
orden: 10
estado: activo
---

Las **mesas** son los puestos del local sobre los que se abre una comanda. Es un
catálogo mínimo: nombre y poco más, pero es lo que permite saber qué está ocupado
y qué está libre.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** de la mesa (máximo 100 caracteres).
3. Guarde.

Conviene usar nombres que el personal reconozca de inmediato: `Mesa 1`,
`Terraza 3`, `Barra 2`.

## Disponible u ocupada

Una mesa con una comanda abierta está **ocupada** y no admite otra comanda: al
intentarlo, el sistema avisa de que *la mesa no está disponible*. Se libera al
cerrar o anular la comanda.

## El tablero del salón

Además del listado, las mesas tienen un **tablero**: el plano del local, con cada
mesa en su sitio y su estado a la vista. Es la pantalla con la que trabaja el
salón durante el servicio.

Se entra por el botón **Restaurante** (el cubierto) de la barra superior, o por
el menú **Restaurante → Comandas**. Ambos lo abren en una **pestaña nueva**: el
tablero es una pantalla completa, sin el menú del sistema, y así el trabajo del
salón no reemplaza lo que se estuviera haciendo. Para volver al sistema basta
cerrar esa pestaña.

Cada mesa ocupada muestra su consumo **con impuestos y recargo por servicio
incluidos**: el mismo valor que el pie de la comanda y que la factura, no la
suma de precios sin IVA.

En su barra superior hay accesos directos a la **pantalla de preparación**
(cocina, barra o las estaciones que tenga el local): un botón por estación
cuando hay tres o menos, y un desplegable **Preparación** cuando hay más. Se
abren en una pestaña nueva, así que el tablero queda intacto detrás y se vuelve
a él cerrando la pestaña.

Dos detalles a tener en cuenta:

- Los botones solo aparecen si el local tiene **estaciones creadas** (se crean en
  *Configuración Restaurante*).
- Solo los ve quien tenga permiso de lectura sobre la **pantalla de preparación**.
  Si un mesero no los ve y sus compañeros sí, es cuestión de permisos, no del
  tablero.

## Requisito: turno de caja abierto

El salón **no funciona sin un turno de caja abierto**. Al entrar al tablero, si
no hay turno para su punto de emisión, aparece el aviso **"No hay caja abierta"**
con el botón **Volver a caja**, que lleva a *Punto de Venta → Cajas*. Ahí se
elige el establecimiento y el punto de emisión, se abre el turno (o se continúa
el que ya está abierto) y **Continuar** devuelve al tablero.

Ese aviso también aparece si la caja se cierra mientras el salón está trabajando.
Aunque se vuelva con el botón **atrás** del navegador, la pantalla se refresca
contra el servidor: no se sigue operando sobre una caja cerrada.

## Cada usuario elige su punto de emisión

Un salón puede tener **varios puntos de emisión**, y cada usuario decide por cuál
trabaja al abrir su turno en Cajas. Esa elección manda:

- El tablero muestra en su cabecera el **punto de emisión activo**, junto al
  cajero del turno (por ejemplo `001-002`).
- Las mesas que se abran desde ahí quedan atadas a ese turno, y es **por ese
  punto de emisión** que se factura al cobrarlas — no por el de otro compañero.
- Dos meseros pueden estar en el mismo salón con puntos de emisión distintos, y
  cada uno emite por el suyo.

Cuando la empresa tiene un solo establecimiento con un solo punto de emisión, la
pantalla de Cajas los selecciona sola: no hay que elegir lo obvio.

## Una mesa atendida por dos personas

Varios usuarios pueden entrar a la misma mesa. Cuando eso pasa, quien llega
después ve un aviso: *"Esta mesa la está atendiendo …"*.

- **Tomar el pedido sigue permitido**: los dos pueden agregar ítems. Una línea de
  más se corrige anulándola.
- **Cobrar, no**: mientras otro tenga la mesa abierta, el cobro se rechaza. De un
  cobro sale un comprobante electrónico, y dos cobros de la misma cuenta serían
  dos documentos por el mismo consumo.

El aviso se libera solo cuando el otro usuario sale de la mesa, y a más tardar a
los tres minutos sin actividad suya (por si cerró la tablet o se quedó sin red).

## Permisos

- **Ver**: entrar al tablero y al listado de mesas.
- **Crear**: abrir comandas sobre las mesas.
- **Actualizar**: mover las mesas en el plano y editar el catálogo.
- **Eliminar**: dar de baja una mesa.

Quien tenga permiso sobre **Mesas** puede además abrir y continuar el **turno de
caja** aunque no tenga asignado el submódulo *Cajas*: sin turno el salón no
opera, así que el camino no puede depender de un permiso aparte. Eso alcanza solo
al turno — el mostrador del Punto de Venta (vender, cobrar en caja) sigue
requiriendo su propio permiso.

## Errores frecuentes

- **"La mesa no está disponible"**: tiene una comanda abierta. Ciérrela o
  anúlela antes de abrir otra.
- **Una mesa quedó ocupada sin nadie sentado**: hay una comanda abierta olvidada;
  búsquela en Comandas y ciérrela.
- **"No hay caja abierta"**: nadie ha abierto turno para su punto de emisión.
  Pulse *Volver a caja* y ábralo, o pídale al cajero que lo haga.
- **"No hay un turno de caja abierto. Abre la caja en Punto de Venta antes de
  abrir mesas"**: la caja se cerró mientras el salón seguía en pantalla. Abra el
  turno de nuevo.
- **"Esta mesa la está atendiendo …"**: otro usuario tiene la comanda abierta.
  Puede seguir tomando el pedido; para cobrar, espere a que salga.
- **"Esta cuenta se está cobrando en otro dispositivo"**: alguien pulsó cobrar al
  mismo tiempo (o fue un doble clic). Espere unos segundos y revise la comanda
  antes de reintentar: es probable que el cobro ya se haya emitido.

## Historial de cambios

- **1.2** — El tablero exige un turno de caja abierto y se abre en pestaña nueva
  también desde el menú. La cabecera muestra el punto de emisión activo, y las
  mesas se facturan por el punto que eligió cada usuario. Aviso cuando otra
  persona atiende la mesa, con el cobro bloqueado mientras tanto. Quien tiene
  permiso sobre Mesas puede abrir el turno de caja sin tener el submódulo Cajas.
- **1.1** — El importe de cada mesa se muestra con impuestos incluidos, y el
  tablero del salón muestra accesos directos a la pantalla de
  preparación, uno por estación.
- **1.0** — Versión inicial.
