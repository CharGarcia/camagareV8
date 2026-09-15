---
titulo: Reporte de inventarios
resumen: Existencias y movimientos por producto y bodega, para cuadrar y valorar el stock.
categoria: Reportes
ruta_modulo: modulos/reporte_inventarios
tipo: modulo
visibilidad: todos
etiquetas: reporte de inventario, existencias, stock por bodega, valorizacion, kardex, faltantes, exportar, auditoria, stock cacheado, corregir stock, consignaciones, stock por lote, por caducidad, que se vence, vencimientos, limpiar filtros
version: 1.8
orden: 40
estado: activo
---

El **reporte de inventarios** muestra las existencias y los movimientos del
periodo, por producto y por bodega. Es la herramienta para el conteo físico y
para valorar lo que hay en almacén.

## Qué permite ver

- Existencias actuales por producto y bodega, y —si hace falta— desglosadas
  por lote, por caducidad o por ambos.
- Movimientos del periodo: qué entró, qué salió y de dónde vino cada movimiento
  (columnas Entradas, Salidas y Saldo, en orden cronológico).
- Valor del inventario según el costo registrado.
- Consignaciones vigentes/entregadas, a nivel de cabecera con detalle por línea.
- Auditoría: diferencias entre el stock guardado y el saldo real del kardex.

## Ver el stock por lote o por caducidad (selector Detalle)

En **Existencias**, el primer selector (**Detalle**) decide hasta dónde se
desglosa el stock de cada producto. Las cuatro opciones muestran siempre el
mismo total; lo que cambia es en cuántas filas se reparte:

| Detalle | Una fila por | Para qué sirve |
|---|---|---|
| **En general** | producto y bodega | El stock del día a día. Es el que permite editar mínimo, máximo y categoría al hacer clic en la fila. |
| **Por lotes** | producto, bodega y lote | Cuánto queda de cada lote. Si un lote entró con dos caducidades distintas, aquí se ven sumadas. |
| **Por caducidad** | producto, bodega y fecha de caducidad | Qué se vence y cuándo, sin importar de qué lote venga. |
| **Lote + caducidad** | cada combinación de lote, NUP y caducidad | El máximo detalle, para cuadrar un lote concreto. |

Mientras el Detalle no esté en *En general*, el selector **Agrupar por** queda
desactivado: el desglose ya define las filas por sí solo.

La columna **Consignación** acompaña al desglose elegido: si la fila no
distingue caducidad, lo consignado tampoco, de modo que el stock propio y lo
que está en poder de clientes siempre se pueden comparar en la misma fila.

> El desglose por lote/caducidad se calcula desde el kardex, no desde el stock
> guardado del producto: el sistema no almacena un stock por lote.

## Limpiar los filtros

Cada pestaña tiene, junto al botón **Mostrar**, un botón con un icono de goma
de borrar que devuelve todos sus filtros al valor inicial. No vuelve a consultar
solo: deja la tabla en blanco para que elija los filtros nuevos y pulse Mostrar.

## Cómo se calcula el stock (saldo en vivo)

El **saldo de Movimientos** y el **stock de Existencias** se calculan siempre
en vivo, sumando y restando el kardex (`SUM(cantidad)`: entradas suman,
salidas restan) — nunca se confía en un campo de saldo guardado
(`stock_posterior` del kardex, `productos_bodegas.stock_actual`). Esto evita
que un stock cacheado desincronizado (por ejemplo, por una migración
incompleta) muestre un número que no corresponde a la suma real de
movimientos.

## Para el conteo físico

El uso más común: se imprime el listado de existencias, se cuenta en bodega, se
anotan las diferencias y se ajustan en Inventario. Es lo que convierte un conteo
en una corrección trazable en lugar de un número cambiado a mano.

## Solo productos inventariables

Únicamente aparecen los productos marcados como **inventariables**. Si un
artículo no está en el reporte, revise su ficha antes de dar por perdido el
stock.

## Exportar

Disponible en **PDF** y **Excel**. Para el conteo, el PDF es el más práctico.

## Pestaña Auditoría

Compara, para cada producto y bodega, el stock **guardado**
(`productos_bodegas.stock_actual`) contra el **real** (la suma en vivo del
kardex). Solo se listan las combinaciones que difieren.

El botón **Corregir** de cada fila deja el stock guardado igual al real del
kardex — es la única acción de escritura del módulo. Antes de corregir,
confirme que el kardex de ese producto/bodega está completo; si el kardex
tiene movimientos faltantes, "corregir" solo iguala el guardado a un kardex
incompleto, no repara el dato real. Ante la duda, un conteo físico es la única
forma de saber el stock verdadero.

Toda corrección queda registrada en la auditoría del sistema
(`log_sistema`), con el valor anterior y el nuevo.

**Corregir todo**: además del botón por fila, hay un botón **Corregir todo** en la
cabecera de la tabla que corrige de una vez todas las discrepancias visibles con
los filtros actuales (respeta bodega/producto/búsqueda si están puestos). Pide
confirmación mostrando cuántas va a corregir antes de ejecutar. Cada corrección
individual queda igual de auditada en `log_sistema` que si se hiciera fila por
fila.

La Auditoría siempre queda limitada a la empresa activa en sesión, sin importar
el nivel del usuario: cada empresa revisa su propio inventario.

### Por qué aparecen discrepancias

Además de migraciones incompletas, la causa más frecuente es una condición de
carrera: dos movimientos del mismo producto/bodega procesándose casi al mismo
tiempo (dos ventas simultáneas, una compra mientras se hace un ajuste, etc.)
podían leer el mismo stock de partida y uno sobrescribía silenciosamente el
resultado del otro en el caché — aunque el kardex sí quedaba completo. Se
corrigió con un bloqueo por producto/bodega (`InventarioRepository::lockStock()`)
en todos los puntos donde se lee el stock antes de escribirlo (ventas,
compras, consignaciones, retornos, cambios de producto y ajustes manuales).
Los productos con discrepancias que ya existían antes de esta corrección
siguen apareciendo aquí hasta que se corrigen manualmente.

## Errores frecuentes

- **Un producto no aparece**: no es inventariable.
- **El stock está en otra bodega**: revise el filtro de bodega.
- **El valor no coincide con la contabilidad**: compare contra el mayor de la
  cuenta de inventario; las diferencias suelen venir de compras sin procesar sus
  entradas.
- **Existencias y Valorización vacías, pero Movimientos (Kardex) sí muestra
  datos**: en empresas migradas desde el sistema anterior, el kardex migrado no
  actualizaba el stock cacheado del producto/bodega del que leen estas dos
  pestañas. Se corrigió para migraciones nuevas; las empresas ya migradas antes
  de la corrección necesitan el script de reparación
  `database/migrations/20260730_backfill_productos_bodegas_migracion.sql`.

## Historial de cambios

- **1.8** — **Existencias**: nuevo selector **Detalle** (En general / Por
  lotes / Por caducidad / Lote + caducidad) como primer filtro. Antes, las
  opciones *Por Lote*, *Por NUP* y *Por Caducidad* de **Agrupar por** hacían
  las tres exactamente lo mismo (una fila por cada combinación de lote, NUP y
  caducidad; solo cambiaba el orden), así que no había forma de ver el stock
  de un lote completo ni de saber qué se vence un día concreto. Esas tres
  opciones salen de *Agrupar por*, que se queda con las consolidaciones
  (Producto, Categoría, Bodega). **Consignaciones**: la columna *Productos*
  contaba productos distintos, así que una consignación con el mismo producto
  en varias líneas (una por lote) anunciaba menos líneas de las que luego
  aparecían al abrirla; ahora cuenta las líneas del documento. Además el
  **N° de consignación** pasa a ser el primer filtro, en **Movimientos** el
  **Tipo de movimiento** pasa a ser el primero, y las cinco pestañas tienen un
  botón para **limpiar todos los filtros** junto a *Mostrar*.
- **1.7** — El módulo **abre mucho más rápido**. Al entrar, la pantalla
  llenaba los selectores "Origen", "Usuario" y "Año" recorriendo TODOS los
  movimientos de kardex de la empresa (tres veces), y la lista de categorías
  contaba además cuántos productos tiene cada una, dato que ningún selector
  muestra. Medido sobre 600.000 movimientos: entre 0,7 y 1,3 segundos de
  espera antes de ver nada, creciendo cada mes. Ahora esos selectores se
  resuelven por índice (1,3 ms en la misma prueba) y la página ya no descarga
  una librería de gráficos externa que no usaba. **Requiere ejecutar**
  `database/20260914_indices_reporte_inventarios_arranque.sql`; sin él el
  reporte funciona igual, solo que sin la mejora de velocidad. La misma
  corrección acelera la apertura del módulo **Inventario**, que llenaba dos
  de esos selectores de la misma forma.
- **1.6** — Pestaña **Consignaciones** mucho más rápida. El cálculo del saldo
  vigente repetía por cada línea la búsqueda del costo en el kardex sin ningún
  índice que la sostuviera, y además ejecutaba dos veces la consulta completa
  en cada "Mostrar" (una para la tabla y otra para unos indicadores que la
  pantalla no muestra). Se notaba sobre todo al filtrar por **Responsable
  traslado**. Requiere ejecutar `database/indices_reporte_consignaciones.sql`.
  Corrección relacionada: una consignación cuyo producto o cliente ya no
  existiera en su tabla desaparecía del listado sin aviso y su saldo no sumaba
  en los totales; ahora se muestra igual, con el nombre en blanco.
- **1.5** — Quitado el check **Todas las empresas** de Auditoría (era solo
  para Nivel 3): cada empresa audita y corrige únicamente su propio
  inventario, sin excepción de nivel.
- **1.4** — Botón **Corregir todo** en Auditoría (corrige de una vez todas las
  discrepancias filtradas) y check **Todas las empresas** para Nivel 3 (audita
  y corrige el sistema completo, no solo la empresa activa).
- **1.3** — Corregida la causa raíz más frecuente de las discrepancias que
  detecta Auditoría: una condición de carrera al escribir el stock guardado
  cuando dos movimientos del mismo producto/bodega se procesaban casi al
  mismo tiempo. Ver "Por qué aparecen discrepancias" arriba.
- **1.2** — Nueva pestaña **Auditoría** para revisar y corregir diferencias
  entre el stock cacheado y el kardex. El saldo de Movimientos y el stock de
  Existencias ahora se calculan siempre en vivo desde el kardex, en lugar de
  confiar en campos de saldo guardados. Nueva pestaña **Consignaciones** a
  nivel de cabecera con detalle por línea.
- **1.1** — Corrección: el kardex migrado desde el sistema anterior no
  sincronizaba el stock cacheado, dejando vacías Existencias y Valorización
  para empresas migradas.
- **1.0** — Versión inicial.
