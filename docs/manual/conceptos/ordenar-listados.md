---
titulo: Cómo ordenar los listados
resumen: Pulse el título de una columna para ordenar por ella; con Shift+clic ordena por varias a la vez. El orden se guarda para la próxima vez.
categoria: Primeros pasos
tipo: concepto
visibilidad: todos
etiquetas: ordenar, orden, ordenamiento, clasificar, alfabetico, de mayor a menor, de menor a mayor, ascendente, descendente, flechita, encabezado, titulo de columna, columna, dos columnas, varias columnas, ordenar por dos campos, subordenar, agrupar por ciudad, shift, mayusculas, prioridad, 1 2 3, se me desordena, quitar el orden, ordenar la lista, ordenar la tabla
version: 1.0
orden: 16
estado: activo
---

En todos los listados del sistema usted decide **en qué orden ver las filas**:
pulse el título de la columna y la lista se reordena por ella. El sistema
recuerda su elección, así que la próxima vez que abra el módulo lo verá
ordenado igual.

## Ordenar por una columna

Pulse el **título de la columna** (por ejemplo *Razón Social*). La flecha que
aparece al lado indica el sentido:

| Icono | Significado |
|-------|-------------|
| ↓ | De menor a mayor: de la A a la Z, del número más chico al más grande, de la fecha más antigua a la más nueva |
| ↑ | De mayor a menor: de la Z a la A, del número más grande al más chico, de la fecha más nueva a la más antigua |
| ↕ gris | Esa columna no está ordenando ahora mismo |

Pulse **otra vez** el mismo título para invertir el sentido. Pulse el título de
**otra** columna para ordenar por esa en su lugar.

## Ordenar por varias columnas a la vez

A veces una sola columna no alcanza: usted quiere ver los clientes **agrupados
por ciudad** y, dentro de cada ciudad, **ordenados por nombre**.

1. Pulse normalmente el título de la primera columna (*Ciudad*).
2. Mantenga presionada la tecla **Shift** (⇧, la de las mayúsculas) y pulse el
   título de la segunda columna (*Razón Social*).

Junto a cada flecha aparece un **número pequeño** que indica el orden en que se
aplican: `1` es la columna que manda y `2` la que desempata dentro de ella.

Con Shift+clic sobre una columna que ya está en el orden, esta va rotando:

| Shift+clic | Qué pasa |
|------------|----------|
| 1.º | La columna entra al orden, de menor a mayor |
| 2.º | Cambia a de mayor a menor |
| 3.º | Sale del orden; las demás se renumeran |

Se pueden encadenar hasta **tres columnas**. Si intenta añadir una cuarta, el
sistema se lo avisa y no la agrega: con tres niveles ya se distingue
prácticamente cualquier ordenamiento, y más columnas harían la consulta
notoriamente más lenta.

Para **volver a un orden simple**, pulse el título de cualquier columna *sin*
Shift: el listado se ordena solo por ella y los demás criterios se descartan.

> El orden por varias columnas está disponible en los listados que ya lo tienen
> habilitado. Si en un módulo el Shift+clic se comporta como un clic normal, ese
> listado todavía ordena por una sola columna.

## El orden se guarda

El orden que elija queda guardado **para usted**, en esa empresa y ese módulo.
No afecta a lo que ven sus compañeros. Lo mismo ocurre con las columnas que
muestra u oculta y con el ancho que les da.

Al **exportar a PDF o Excel**, el archivo sale con el mismo orden que está
viendo en pantalla.

## Qué NO cambia el orden

- **No filtra**: ordenar no quita filas del listado. Para dejar solo algunas,
  use el buscador (ver *Cómo buscar en el sistema*).
- **No modifica los datos**: es solo la forma de mostrarlos.
- **No cambia la numeración** de sus documentos ni ningún dato del registro.

## Errores frecuentes

| Situación | Causa |
|-----------|-------|
| Pulso el título y no pasa nada | Esa columna no es ordenable (por ejemplo, la de botones de acción) |
| Shift+clic selecciona texto en vez de ordenar | Está pulsando sobre el texto y no sobre el encabezado; pulse sobre el título de la columna |
| Abro el módulo y sale ordenado de una forma que no elegí | Es el orden que usted dejó la última vez. Pulse el título de la columna que prefiera y quedará guardado el nuevo |
| Ordeno por una columna y parece que las filas de abajo están vacías | Las filas sin dato en esa columna se agrupan al final |

## Historial de cambios

- **1.0** — Primera versión: ordenamiento por una y por varias columnas
  (Shift+clic), con la prioridad numerada en cada encabezado.
