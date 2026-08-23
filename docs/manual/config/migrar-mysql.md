---
titulo: Migración desde MySQL
resumen: Trae empresas, usuarios y catálogos del sistema anterior (MySQL) al sistema nuevo, empresa por empresa.
categoria: Configuración global
ruta_modulo: config/migrar-mysql
tipo: modulo
visibilidad: superadmin
etiquetas: migracion, migrar, sistema anterior, mysql, migrar empresas, establecimientos migracion, ruc base, elegir establecimiento, fusionar establecimientos, cliente separado
version: 1.1
orden: 2
estado: activo
---

**Migración desde MySQL** (`Configuración → Migración MySQL`) trae los datos
del sistema anterior (una base MySQL) hacia este sistema, exclusivo del
**superadministrador**. Se migra empresa por empresa: primero la empresa
(con su establecimiento, un usuario administrador opcional y las asignaciones
de usuarios), y luego, ya con la empresa creada, el resto de catálogos
(clientes, productos, inventario, documentos, etc.) desde las demás pestañas
de la herramienta.

## Migrar empresas: cada establecimiento es un cliente distinto

En esta plataforma, una "empresa" es siempre **un RUC + un establecimiento**.
El mismo RUC puede repetirse en varias empresas del sistema nuevo (una por
cada establecimiento), porque cada establecimiento es, para nosotros, un
**cliente distinto** — aunque compartan el mismo RUC legal, son suscripciones
y negocios independientes.

El sistema anterior guardaba cada establecimiento de un contribuyente como
una fila separada en su tabla de empresas, todas compartiendo los primeros 10
dígitos del RUC ("RUC base") y difiriendo en los 3 dígitos finales (el código
de establecimiento, ej. `001`, `002`). Al listar empresas por migrar, la
herramienta agrupa esas filas por RUC base para mostrarlas juntas, pero **por
defecto cada una se migra como su propia empresa nueva** — no se combinan.

- **Si el RUC base tiene una sola fila activa**, no hay nada que decidir: se
  migra directo, como una empresa.
- **Si tiene más de una** (badge amarillo con la cantidad, junto a la columna
  "Estab."), aparece debajo un selector por cada establecimiento encontrado
  (código, nombre, dirección), con tres opciones:
  - **Cliente separado** (la opción por defecto): se migra como su propia
    empresa nueva, independiente de los demás.
  - **Fusionar con [otro establecimiento de la misma base]**: sus datos NO
    generan una empresa propia — se descartan por completo, y la empresa
    resultante es la del establecimiento elegido como destino, con **sus
    propios datos** (no se combinan campos de ambos). Úsela solo cuando está
    seguro de que ambas filas del sistema anterior son, en realidad, el mismo
    negocio (por ejemplo, datos que quedaron fragmentados por error en el
    sistema anterior) — no porque compartan RUC.
  - **No migrar**: ese establecimiento no se migra en absoluto (ni solo, ni
    fusionado). Útil para filas viejas, de prueba, o que ya no corresponden a
    un negocio real.
- El resultado de la migración muestra, en **Avisos**, qué establecimientos
  se fusionaron en cuáles.
- Migrar es **por establecimiento**, no por toda la base: si más adelante
  aparecen establecimientos nuevos para un RUC ya parcialmente migrado (o se
  vuelve a listar), la herramienta solo ofrece los que todavía no existen en
  el sistema nuevo — los que ya se migraron no vuelven a aparecer, pero el
  resto de la base sigue disponible.

## Errores frecuentes

- **Se fusionó un establecimiento por error**: no hay forma de deshacerlo
  desde acá — sus datos no se guardaron en ningún lado. Si de verdad hacía
  falta como cliente separado, se crea una empresa nueva a mano desde
  **Configuración → Empresas del sistema** con esos datos.
- **Un establecimiento no debía migrar como cliente separado, sino fusionarse**:
  al revés del caso anterior, si ya se migró como empresa propia y en
  realidad correspondía fusionarlo, hay que decidir manualmente qué hacer con
  esa empresa duplicada (eliminarla desde Empresas del sistema si no tiene
  datos reales todavía).

## Historial de cambios

- **1.1** — Cambio de modelo: cada establecimiento del sistema anterior se
  migra por defecto como su **propia empresa** (antes: se elegía uno solo por
  RUC base y el resto se descartaba). Se agrega la opción de **fusionar**
  explícitamente dos o más establecimientos en una sola empresa cuando
  realmente son el mismo negocio. La idempotencia pasa a ser por
  establecimiento, no por RUC base completo.

- **1.0** — Primera versión del artículo. Documentaba el modelo anterior:
  elegir un establecimiento por RUC base y descartar el resto.
