---
titulo: Reporte de inventarios
resumen: Existencias y movimientos por producto y bodega, para cuadrar y valorar el stock.
categoria: Reportes
ruta_modulo: modulos/reporte_inventarios
tipo: modulo
visibilidad: todos
etiquetas: reporte de inventario, existencias, stock por bodega, valorizacion, kardex, faltantes, exportar, auditoria, stock cacheado, corregir stock, consignaciones
version: 1.3
orden: 40
estado: activo
---

El **reporte de inventarios** muestra las existencias y los movimientos del
periodo, por producto y por bodega. Es la herramienta para el conteo físico y
para valorar lo que hay en almacén.

## Qué permite ver

- Existencias actuales por producto y bodega.
- Movimientos del periodo: qué entró, qué salió y de dónde vino cada movimiento
  (columnas Entradas, Salidas y Saldo, en orden cronológico).
- Valor del inventario según el costo registrado.
- Consignaciones vigentes/entregadas, a nivel de cabecera con detalle por línea.
- Auditoría: diferencias entre el stock guardado y el saldo real del kardex.

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
