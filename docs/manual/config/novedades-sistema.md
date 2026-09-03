---
titulo: Novedades del sistema
resumen: Redactar y publicar avisos, mejoras y nuevas funciones que los usuarios ven en una ventana al ingresar al sistema.
categoria: Configuración
ruta_modulo: config/novedades-sistema
tipo: config
visibilidad: superadmin
etiquetas: novedades, noticias, avisos, anuncios, comunicados, qué hay de nuevo, nuevas funciones, mejoras, cambios del sistema, ventana al ingresar, popup, megáfono, informar a los usuarios, boletín, release notes
version: 1.0
orden: 0
estado: activo
---

Desde aquí el superadministrador redacta las **novedades del sistema**: avisos,
mejoras, correcciones y nuevas funciones. Cuando una novedad se publica, cada
usuario la ve en una **ventana al ingresar al sistema** (solo en PC) y puede
releerla después desde el **megáfono** de la barra superior.

## Qué es y para qué sirve

Es el canal para informar a todos los usuarios de la plataforma, sin correo y sin
depender de que abran el manual. Una novedad es un texto corto con título, tipo y
contenido con formato. Es **global**: la ven los usuarios de todas las empresas.

Tipos disponibles:

| Tipo | Cuándo usarlo |
|------|---------------|
| Nuevo | Se agregó una función o un módulo que antes no existía |
| Mejora | Algo que ya existía ahora funciona mejor o tiene más opciones |
| Aviso | Información operativa: mantenimientos, cambios del SRI, fechas límite |
| Corrección | Se resolvió un error que los usuarios pudieron haber notado |

## Requisitos previos

- Ser **superadministrador (nivel 3)**. La tarjeta "Novedades del sistema" solo
  aparece en Configuración para ese nivel.
- Haber aplicado en la base de datos el archivo
  `database/migrations/20260903_novedades_sistema.sql`.

## Cómo se usa

1. Entrar a **Configuración → Novedades del sistema** y pulsar **Nueva**. Para
   editar una novedad existente basta con hacer clic sobre su fila.
2. Escribir el título, elegir el tipo y redactar el contenido (negritas, listas,
   subtítulos y enlaces; no admite imágenes).
3. Indicar la fecha **Vigente hasta** (obligatoria; se propone 30 días) y,
   opcionalmente, el módulo relacionado y un enlace.
4. Guardar como **Borrador** si aún no debe verse, o directamente como
   **Publicada**. También se puede publicar después con el botón del megáfono
   en la fila.
5. Cuando la novedad ya no interesa, **Archivar**: deja de mostrarse a los
   usuarios pero conserva el registro de quién la leyó.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Título | Sí | Lo que el usuario lee primero en la lista de la ventana (máximo 200 caracteres) |
| Tipo | Sí | Nuevo, Mejora, Aviso o Corrección; define la etiqueta de color |
| Resumen | No | Una línea que se muestra en el listado de gestión |
| Contenido | Sí | Texto con formato. Se guarda solo el texto: se eliminan scripts, imágenes y estilos |
| Módulo relacionado | No | Se elige de la lista de submódulos del sistema. La ventana del usuario muestra el enlace "Abrir <módulo>" y, si el módulo tiene artículo en el manual, el enlace a esa guía |
| Enlace | No | Dirección a la que lleva el botón "Abrir enlace" de la novedad: una página externa (`https://…`) o una pantalla del sistema (`/modulos/…`). No admite otros esquemas |
| Vigente hasta | Sí | Fecha hasta la que se muestra la novedad (ese día inclusive). Al crear una nueva se propone 30 días, editable. El listado avisa cuando vence en 7 días o menos y marca las vencidas |
| Estado | Sí | Borrador (no se ve), Publicada (se ve), Archivada (ya no se ve) |

El modal tiene tres pestañas: **Novedad** (el formulario), **Adjuntos** (los
archivos que el usuario podrá descargar) y **Leída por** (quién la marcó como
leída, con empresa y fecha). Cada usuario puede ocultar pestañas con el
engranaje de la derecha. Encima de las pestañas está la barra de acciones con
**Publicar** o **Archivar** según el estado actual, y el contador de lecturas.
**Eliminar** está en el pie del modal, a la izquierda.

### Archivos adjuntos

- Se suben desde la pestaña **Adjuntos** con el botón **Subir archivos**;
  admite varios a la vez. La pestaña aparece una vez guardada la novedad (al
  crear una nueva, el modal queda abierto en modo edición justo para eso).
- Formatos: PDF, Excel (xls, xlsx, csv), Word, PowerPoint, texto, imágenes
  (png, jpg, gif, webp) y ZIP. Máximo 20 MB por archivo. El sistema revisa el
  contenido real del archivo, no solo la extensión.
- El usuario los ve al pie de la novedad, con el nombre original y el tamaño;
  las imágenes se muestran en miniatura. Al hacer clic se descargan.
- Solo se pueden descargar adjuntos de novedades **publicadas** (el
  superadministrador puede descargar los de cualquier estado).
- Eliminar un adjunto lo quita de la novedad y borra el archivo del servidor
  para no ocupar espacio. Queda registrado en la auditoría.
- Los archivos viven en `storage/novedades_sistema/`. En el servidor esa
  carpeta debe existir con permiso de escritura para el usuario de PHP.

## Permisos

- **Ver la ventana y la lista de novedades**: cualquier usuario autenticado, de
  cualquier nivel y empresa.
- **Crear, editar, publicar, archivar y eliminar**: solo superadministrador
  (nivel 3). No se administra por submódulo ni por `modulos_asignados`.

## Reglas de negocio

- Solo se muestran a los usuarios las novedades en estado **Publicada** cuya
  fecha "Vigente hasta" no haya pasado. La fecha es obligatoria: al vencer, la
  novedad desaparece sola (ventana, megáfono, lista y descarga de adjuntos) sin
  cambiar su estado; para reactivarla basta con mover la fecha hacia adelante.
- La **fecha de publicación** se fija automáticamente la primera vez que la
  novedad pasa a Publicada y no cambia aunque se edite después.
- Al usuario no se le muestra una ventana modal sino una **tarjeta flotante**
  que se desliza desde la esquina inferior derecha (encima del chat de
  soporte) y no bloquea la pantalla. Muestra una novedad a la vez, con flechas
  para pasar a la siguiente; las no leídas van primero.
- La tarjeta se abre sola **al iniciar sesión** (una vez por ingreso, en la
  primera pantalla) mientras haya novedades sin leer: al pulsar
  **Entendido** se registra la lectura de esa novedad (usuario, empresa activa,
  fecha, IP y navegador) y se pasa a la siguiente; cuando no queda ninguna, la
  tarjeta se cierra y no vuelve a aparecer hasta que se publique otra.
- Cerrar la tarjeta con la X, con Esc o haciendo clic fuera **no marca nada**:
  mientras al usuario le queden novedades sin leer, la tarjeta vuelve a salir
  en su siguiente inicio de sesión. El contador del megáfono sigue mostrando
  las pendientes.
- La ventana solo se abre en **PC** (pantallas de 992 px o más de ancho). En
  celular no aparece ni la ventana ni el megáfono, para no estorbar en
  pantallas pequeñas.
- La columna **Leída por** muestra "usuarios que la marcaron como leída /
  usuarios activos del sistema". Al pulsarla se ve el detalle con nombre,
  empresa y fecha.
- El listado sigue el estándar del sistema: buscador, orden por cualquier
  columna (clic en el encabezado), paginación de 20 en 20, y el botón de
  columnas para mostrar u ocultar cada una y arrastrar su ancho. Esas
  preferencias se guardan por usuario.
- Eliminar es lógico: la novedad desaparece de la gestión pero queda en la base
  de datos y su historial en la auditoría (`log_sistema`).

## Integraciones

- **Manual del sistema**: si se indica la ruta del artículo, la ventana muestra
  el enlace directo al manual.
- **Auditoría** (`log_sistema`): crear, actualizar, cambiar estado y eliminar
  quedan registrados con usuario y fecha.

## Errores frecuentes

- **"La novedad no aparece a los usuarios"**: revisar que el estado sea
  Publicada, que "vigente hasta" no haya pasado y que el usuario esté en PC.
  Si ese usuario ya la marcó como leída, la ventana no se abre sola pero la
  novedad sigue disponible desde el megáfono.
- **"El enlace al manual debe ser una ruta como modulos/proformas"**: la ruta
  no debe llevar dominio ni `/documentacion/`; solo la parte final.
- **La ventana no sale en ningún usuario**: falta aplicar el SQL de la
  migración; el sistema no rompe la pantalla, simplemente no consulta nada.

## Historial de cambios

| Versión | Fecha | Cambio |
|---------|-------|--------|
| 1.0 | 03-09-2026 | Versión inicial: ventana al ingresar (solo PC), megáfono con contador, lista completa, gestión para superadmin, lecturas y aplazo de 12 horas |
| 1.1 | 03-09-2026 | Gestión con el listado estándar: filas clicables para editar, columnas visibles y anchos por usuario, paginación; botón "Nueva"; campo Estado reubicado en el modal |
| 1.2 | 03-09-2026 | Acciones de la fila movidas al modal (barra Publicar/Archivar, Eliminar al pie); modal con pestañas Novedad y Leída por; Módulo relacionado se elige del catálogo de submódulos y genera el enlace "Abrir módulo" y el del manual; indicador de vencimiento en Vigente hasta |
| 1.3 | 03-09-2026 | El campo "Artículo del Manual" pasa a ser **Enlace** libre (externo o interno); pestaña **Adjuntos** con archivos descargables (PDF, Excel, Word, imágenes, ZIP; 20 MB por archivo) que el usuario ve al pie de la novedad; el modal crece sin barra de desplazamiento interna |
| 1.4 | 03-09-2026 | **Vigente hasta** pasa a ser obligatoria (30 días propuestos al crear); las novedades existentes sin fecha reciben 30 días desde la actualización |
| 1.5 | 03-09-2026 | La ventana modal del usuario se reemplaza por una **tarjeta flotante** que entra desde la esquina inferior derecha, una novedad a la vez con flechas; "Entendido" marca solo la novedad en pantalla |
| 1.6 | 03-09-2026 | La tarjeta se abre **una vez por inicio de sesión** mientras haya novedades sin leer; se elimina el aplazo de 12 horas y el botón "Más tarde"; cierra con X, Esc o clic fuera; el megáfono despliega la lista de vigentes |
