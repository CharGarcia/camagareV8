---
titulo: Configuración Restaurante
resumen: Estaciones de preparación (cocina, barra) y su impresora, en un solo lugar.
categoria: Restaurante
ruta_modulo: modulos/configuracion-restaurante
tipo: modulo
visibilidad: todos
etiquetas: configuracion restaurante, estaciones, estacion de preparacion, cocina, barra, parrilla, impresora de cocina, imprimir ordenes, estacion predeterminada, stock general, sin menu, sin carta, preparar en, kds, ancho de papel, tirilla, 58mm, 80mm, papel de la tirilla
version: 1.1
orden: 45
estado: activo
---

Aquí se define **dónde se prepara la comida** en el local: las estaciones
(Cocina, Barra, Parrilla… las que haga falta), si cada una imprime sus órdenes
en papel, y cuál recoge lo que no tiene estación propia.

Estas estaciones se administraban antes en una pestaña del modal de *Menú*.
Se movieron aquí porque hay locales que **no usan la carta** y aun así necesitan
configurar su cocina.

## Qué es una estación

Un punto de preparación con su propia pantalla y, opcionalmente, su propia
impresora. Cada ítem de una comanda se enruta a una estación, y ahí lo ve la
cocina o la barra que corresponde: la parrilla no recibe los cócteles.

El **tipo** (cocina / barra / otro) solo define el ícono y el color. Se pueden
crear tantas estaciones de cada tipo como haga falta.

## Cómo se decide dónde se prepara un ítem

En cascada, de lo más específico a lo más general. Se toma la primera que
resuelva:

1. La estación del **ítem de la carta** (*Menú → Preparar en*).
2. La estación de la **categoría** de ese ítem.
3. La estación de la **categoría del producto** vinculado.

Si ninguna resuelve, ese ítem **no pasa por preparación**: se entrega directo, no
aparece en ninguna pantalla de cocina y no genera orden automática.

## Locales que preparan y locales que no

El sistema distingue los dos casos solo, mirando si hay **al menos un ítem o una
categoría enrutados a una estación activa**:

| | Con preparación | Sin preparación |
| --- | --- | --- |
| Hay ítems enrutados a una estación | Sí | No |
| Botón *Enviar a preparación* en la comanda | Se muestra | Se esconde |
| Los ítems que agrega el mesero | Nacen pendientes, esperan el envío | Nacen entregados |
| Pantalla de cocina (KDS) | Recibe lo enviado | No recibe nada |
| Botón de imprimir la orden | Reimprime lo ya enviado | Imprime la comanda entera, cuando se quiera |

## La estación predeterminada

Dice **por qué impresora sale la orden** cuando no hay preparación. No enruta
ítems a cocina: un local que no prepara nada no debe ver sus pedidos esperando en
una pantalla.

Se marca con la **estrella** de la columna *Predeterminada*, en la fila de la
estación: un clic la fija, y otro clic sobre la estrella encendida la quita. En un
local sin preparación, el botón de imprimir de la comanda manda la orden completa
a esa impresora.

Solo puede haber **una predeterminada por empresa**: al marcar otra, la anterior
deja de serlo sola. Una estación **inactiva** no puede serlo, y su estrella
aparece deshabilitada. Si el local tiene una sola estación con impresora, se usa
esa aunque no esté marcada.

## La impresora de la estación

Marcando *Imprime las órdenes en papel* se configura:

| Opción | Para qué |
| --- | --- |
| Papel | 58 u 80 mm, según la impresora térmica. Ajusta el tamaño de letra del ticket. |
| Copias | Cuántas veces sale cada orden (por ejemplo 2: una para quien prepara y otra para el pase). |
| Sale sola al enviar a cocina | Marcada, la orden se imprime al enviar la comanda. Sin marcar, solo cuando alguien la pide desde la comanda. |

Quien saca el papel es la **pantalla de preparación (KDS) de esa estación**, que
debe estar abierta en un equipo con la impresora conectada. El detalle está en el
manual del KDS.

## El papel de la tirilla

Arriba de la pantalla, junto al botón *Nueva*, se elige el **ancho del papel de
la tirilla**: 58 u 80 mm. Es la cuenta y la factura que se imprimen desde la
comanda y desde el punto de venta —no las órdenes de cocina, que usan el ancho de
su propia estación—.

No cambia el tamaño de la página (eso lo manda el driver de la impresora): ajusta
el **tamaño de letra**, para que una tirilla de 58 mm no salga con la tipografía
pensada para 80 y las descripciones no se partan en tres líneas.

Se guarda solo al elegirlo, y vale para toda la empresa.
## El listado

Como el resto de listados del sistema: **buscador** (texto libre o filtros
`clave:valor` — `tipo:barra`, `imprime:true`, `estado:false`, `papel:80`),
**ordenamiento** por cualquier columna, **paginación**, **exportación a PDF y
Excel** y elección de las columnas visibles, que se recuerda por usuario.

La columna *En uso* dice cuántos ítems de la carta o categorías preparan en cada
estación: es lo que impide eliminarla.

## Campos de la estación

| Campo | Descripción |
| --- | --- |
| Nombre | Cómo la ve el personal. Se guarda en mayúsculas y no puede repetirse en la empresa. |
| Tipo | Cocina, Barra u Otro. Solo ícono y color. |
| Activa | Una estación inactiva deja de aparecer como destino. No puede ser la predeterminada. |
| Imprime las órdenes | Activa la impresión en papel y sus opciones. |

La **predeterminada** no se marca en este formulario, sino con la estrella de la
fila correspondiente del listado.

## Permisos

| Permiso | Qué habilita |
| --- | --- |
| Ver | Entrar y consultar las estaciones. |
| Crear | Crear estaciones nuevas. |
| Actualizar | Editar una estación y cambiar la predeterminada. |
| Eliminar | Eliminar estaciones que no estén en uso. |

## Reglas de negocio

- El nombre es obligatorio, único por empresa y se guarda en mayúsculas.
- Una estación **en uso** (hay ítems de la carta o categorías que preparan en
  ella) **no se puede eliminar**: quedarían apuntando a nada y dejarían de llegar
  a preparación sin que nadie se entere. La columna *En uso* del listado avisa.
- Solo una estación predeterminada por empresa; al marcar una, la anterior deja
  de serlo automáticamente.
- Una estación inactiva no puede ser la predeterminada. Si se desactiva la que lo
  era, la empresa se queda sin predeterminada.
- Eliminar una estación no afecta a los pedidos ya enviados a preparación.

## Integraciones

- **Menú**: el selector *Preparar en* de cada ítem lista estas estaciones.
- **Comandas**: al agregar una línea se resuelve su estación con la cascada de
  arriba.
- **Pantalla de cocina (KDS)**: una pestaña por estación, y es quien imprime las
  órdenes de la suya.

## Errores frecuentes

- **"No se puede eliminar: N ítem(s) o categoría(s) preparan en esta estación"**:
  cambie primero esos ítems a otra estación (o a *Ninguna*) en Menú.
- **"Ya existe una estación con el nombre X"**: los nombres no se repiten dentro
  de la empresa, sin distinguir mayúsculas.
- **La estrella está deshabilitada**: esa estación está inactiva, y una estación
  inactiva no puede ser la predeterminada. Actívela primero.
- **No aparece el botón "Enviar a preparación" en la comanda**: ningún ítem de
  la carta ni ninguna categoría está enrutado a una estación activa, así que el
  sistema entiende que el local entrega todo directo. Asigne la estación en
  *Menú → Preparar en* (o en la categoría del producto) y el botón vuelve.
- **"Marque la estación predeterminada…"** al imprimir: el local no prepara nada
  y hay varias impresoras; marque con la estrella cuál debe sacar la orden.

## Historial de cambios

- **1.1** — Ajuste del ancho de papel (58/80 mm) de la tirilla de cuenta y
  factura, para toda la empresa.
- **1.0** — Versión inicial. Las estaciones salen de la pestaña del modal de
  *Menú* y pasan a este módulo, que suma la estación predeterminada: la impresora
  por la que sale la orden en los locales que no trabajan con preparación.
