# Reglas del sistema — CaMaGaRe

Documento maestro de arquitectura y convenciones. **Toda regla aquí escrita OVERRIDE cualquier comportamiento por defecto y debe cumplirse al pie de la letra.** Al crear o modificar cualquier módulo, ajustarse a estas reglas.

---

## 1. Stack y principios

- **PHP 8+**, **PostgreSQL**, **Bootstrap 5+**, **JavaScript** y **Font Awesome**, sobre una **arquitectura MVC propia**.
- Todo lo que se construya debe ser: **seguro, auditable, no destructivo, multiempresa, modular y escalable**.

---

## 2. Estructura de carpetas

> Nota de nomenclatura: por PSR-4 el namespace es `App\…`. En disco, algunas carpetas usan mayúscula (`Services`, `Rules`, `Traits`, `Config`) y otras minúscula (`controllers`, `core`, `helpers`, `models`, `repositories`, `middleware`, `views`, `validadores`). Respetar el nombre exacto existente al crear archivos.

```
app/
  core/          Núcleo MVC: Application, Controller, Model, Router, Database
  controllers/   Controladores. Módulos operativos en controllers/modulos/
  models/        Acceso a datos puro (extienden BaseModel). Catálogos globales aquí
  repositories/  Repositorios PDO. Módulos operativos en repositories/modulos/
  Services/      Lógica de negocio. Módulos operativos en Services/modulos/
  Rules/         Validaciones de negocio. Módulos operativos en Rules/modulos/
  Traits/        Traits reutilizables (p. ej. PermisoModuloTrait)
  middleware/    AuthMiddleware (autenticación/sesión)
  validadores/   Validaciones específicas centralizadas
  helpers/       Funciones y helpers (namespace App\Helpers)
  lib/           Librerías internas (correo, etc.)
  migrations/    Migraciones SQL a nivel app
  views/         Vistas. Módulos operativos en views/modulos/{nombre}/
config/          Config global del sistema (incluye modulos_mvc.php)
routes/          web.php (controlador/acción por defecto)
public/          Assets: css/, js/ (JS de módulos en public/js/modulos/)
database/        Esquema y migraciones SQL
storage/         Almacenamiento (archivos generados, logs)
legacy/          Código antiguo en migración (no es la arquitectura objetivo)
vendor/          Dependencias (TCPDF, etc.)
```

- **Patrón `modulos/`**: todo módulo **operativo** (depende de empresa) vive bajo `modulos/` en cada capa. Los **catálogos/configuración global** van en la raíz de cada capa (p. ej. `controllers/BancosEcuadorController.php`, `models/Provincia.php`).
- **Enrutamiento** (por convención, `app/core/Router.php`): `/modulos/{nombre}/{accion}` → `App\controllers\modulos\{Nombre}Controller::{accion}()`; `/{controlador}/{accion}` para el resto. Acción por defecto: `index`.

---

## 3. Separación de responsabilidades

Flujo obligatorio:

```
Controller → Service → Rules → Repository / Model → Base de datos
```

- **Controller**: solo recibe la solicitud, valida datos básicos, delega y responde. **Sin lógica de negocio.**
- **Service**: orquesta la lógica de negocio, transacciones y auditoría.
- **Rules**: validaciones de negocio específicas (centralizadas, reutilizables).
- **Repository / Model**: **único** punto de acceso a la base de datos. **Sin lógica de negocio.**
- Toda consulta a BD pasa por un repository o model. Nunca SQL directo en controllers o services.

---

## 4. Multiempresa (multitenant)

- El sistema es multiempresa. Regla central de `id_empresa`:
  - **SÍ** lleva `id_empresa`: tablas **operativas** y configuraciones **específicas por empresa** (clientes, productos, facturas, inventario, ingresos, egresos, adquisiciones, etc.).
  - **NO** lleva `id_empresa`: **configuración global** del sistema y catálogos globales (son únicos para toda la aplicación).
- **Toda consulta operativa DEBE filtrar siempre por `id_empresa` Y por `eliminado = false`.**
- Las consultas globales **NO** deben filtrar por `id_empresa`.
- Ningún módulo operativo se ejecuta sin validar `id_empresa`.
- Las configuraciones por empresa se almacenan en una tabla independiente con `id_empresa`; las globales en una tabla sin `id_empresa`.
- **Helper canónico**: los repositorios de módulo extienden `App\repositories\BaseRepository` y usan `getBaseWhere($idEmpresa, $alias, $idUsuarioFiltro)`, que arma el `WHERE` aplicando automáticamente `id_empresa = :id_empresa AND eliminado = false` (y el filtro de registros propios; ver §6). Usarlo en todos los listados.

---

## 5. Modelo de datos

**Clasificación de tablas**
- **Globales**: configuración general y catálogos. Sin `id_empresa`.
- **Operativas**: dependen de empresa. Con `id_empresa`.

**Campos obligatorios en toda tabla operativa**
```
id_empresa, created_at, updated_at, created_by, updated_by,
eliminado (boolean), deleted_at, deleted_by
```

**Eliminación lógica (obligatoria)**
- Ningún registro se borra físicamente. Eliminar = marcar `eliminado = true` (con `deleted_at`, `deleted_by`).

**Campo `estado`**
- Las tablas que lo requieran incluyen `estado` (activo, inactivo, borrador, aprobado, anulado, etc.).
- `estado` **no reemplaza** a `eliminado`; son independientes.

---

## 6. Seguridad, autenticación y permisos

**Niveles de usuario**
- **Nivel 3 (Superadministrador)**: acceso total sin restricciones a todos los módulos, configuraciones, empresas y usuarios. No requiere registro en `modulos_asignados`.
- **Nivel 2 (Administrador)**: acceso a módulos, submódulos y configuraciones asignadas por el superadmin.
- **Nivel 1 (Usuario)**: acceso solo a módulos, submódulos y empresas asignadas.

**Reglas**
- Ningún usuario (salvo Nivel 3) puede acceder a información de empresas que no tenga asignadas.
- Todo acceso a módulos/submódulos se valida con middleware: **autenticación → empresa activa → permisos**.
- Permisos por submódulo (tabla `modulos_asignados`): `r` ver, `w` crear, `u` actualizar, `d` eliminar, `t` acceso total (`todo`).
- **Registros propios vs. acceso total**: si el usuario tiene `t` (acceso total) ve los registros de **toda la empresa**; si **no** lo tiene, solo ve/gestiona **los que él creó** (`created_by = id_usuario`). Mecanismo:
  - Controller: `$idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;` y se pasa al listado del repository.
  - Repository: `getBaseWhere(...)` añade `AND created_by = :id_usuario_filtro` cuando `$idUsuarioFiltro !== null`.
  - Nivel 3 (super admin) siempre ve todo.
- Seguridad técnica obligatoria: contraseñas con `password_hash`, **consultas preparadas (PDO)**, **CSRF**, validación de sesiones, control de acceso por middleware.

**CSRF (ya implementado, no reinventar)**
- Validación **centralizada** en `Application::run()`; el token lo inyecta `app/views/partials/csrf.php` (incluido en `partials/head.php`) y lo adjunta solo `public/js/csrf.js`, que envuelve `fetch`, `XMLHttpRequest` y los formularios. **Un módulo con layout estándar no debe hacer nada.**
- **Única regla a recordar**: si la vista es *standalone* (arma su propio `<head>` sin `partials/head.php`, como POS, KDS, mesas o comandas), añadir dentro de su `<head>`: `<?php require MVC_APP . "/views/partials/csrf.php"; ?>`. Sin eso, sus peticiones se rechazan con HTTP 419.
- Modo en `config/app.php` → `security.csrf` (`log` | `enforce` | `off`). Detalle en `SEGURIDAD_FASE3.md`.

**Piezas reutilizables (usarlas, no reinventar)**
- `app/controllers/modulos/BaseModuloController.php`: clase base de todo controlador de módulo. Implementar `getRutaModulo()` y usar `requireLeer()` / `requireCrear()` / `requireActualizar()` / `requireEliminar()`.
- `app/Traits/PermisoModuloTrait.php`: resuelve permisos por ruta y empresa.
- `getPermisos()` del controlador base entrega a la vista: `ver, crear, actualizar, eliminar, todo`.
- Administrar permisos en la UI: `/config/permisos-modulos`.

**Endpoints públicos / sin login (obligatorio validar empresa activa)**
- Todo controlador registrado en `$publicControllers` (`app/core/Application.php`) sirve a usuarios **sin sesión** (clientes escaneando un QR, empleados marcando asistencia, aprobaciones por token en un correo, etc.). Ese flujo **nunca** pasa por `AuthMiddleware`, así que **nunca** recibe la revalidación de empresa activa que sí aplica a los usuarios logueados (`AuthMiddleware` → `Empresa::estaActiva()`, cada 5 min).
- **Regla**: cualquier endpoint público que identifique la empresa dueña del recurso por un token/QR (no por sesión) debe filtrar explícitamente `empresas.estado = '1' AND empresas.eliminado = false` en el mismo query que resuelve ese token — normalmente en el repository, con `JOIN empresas` sobre la tabla dueña del token. Si la empresa está inactiva, el token debe comportarse como si no existiera (mismo mensaje de error que "token inválido"), sin filtrar el motivo.
- Como defensa en profundidad, el Service que ejecuta la acción final (crear factura, registrar marca, etc.) debe repetir la validación antes de escribir en BD, por si el mismo Service se invoca también desde un flujo autenticado (aprobación manual) que no pasa por ese repository.
- Referencia de implementación: `FacturaExpressQrRepository::getPlantillaByToken()` + `FacturaExpressQrService::_facturarSolicitud()`, y `BiometriaRepository::getByQrToken()` + `AsistenciaPuntoRepository::getByQrToken()` (control de asistencia).

---

## 7. Auditoría

- Toda acción importante se registra en `log_sistema` vía `App\Services\LogSistemaService`.
- Firma: `registrar(int $idUsuario, ?int $idEmpresa, string $accion, string $tabla, ?int $idRegistro, ?array $antes, ?array $despues)`.
- Registra: usuario, empresa (cuando aplique), acción, tabla afectada, id del registro, datos anteriores y nuevos (más IP y user agent).
- En acciones sobre tablas globales, `id_empresa` puede ser `NULL`.
- Historial de un registro: `getHistorial($tabla, $id, $idEmpresa)` (expuesto en `getHistorialAjax`).

---

## 8. Transacciones

- Usar transacciones en **todos** los procesos que escriben datos. Si algo falla, revertir **todo** (rollback).

**Concurrencia: bloquear todo "leer → calcular → escribir" sobre un valor compartido**
- Regla general, para **todo el sistema** (no solo inventario): si un flujo hace *leer un valor
  agregado o cacheado → calcular el nuevo valor en PHP → escribirlo*, esa secuencia completa
  **debe** ir protegida por un bloqueo transaccional, dentro de la misma transacción, **antes**
  de la lectura. Sin esto, dos procesos concurrentes sobre la misma clave (mismo producto/bodega,
  mismo punto de emisión, mismo lo-que-sea) pueden leer el mismo valor de partida y el que escribe
  último sobreescribe silenciosamente el resultado del otro — el detalle (kardex, líneas, etc.)
  puede quedar completo, pero el valor cacheado/derivado queda desfasado. Este patrón ya causó una
  desincronización sistémica entre `productos_bodegas.stock_actual` y `inventario_kardex`
  (ver `docs/manual/modulos/reporte-inventarios.md`, pestaña Auditoría).
- **Mecanismo estándar**: `SELECT pg_advisory_xact_lock(hashtext('clave:' || ...))` — se libera solo
  al COMMIT/ROLLBACK de la transacción en curso, no requiere `UNLOCK` manual. Usar una clave de texto
  específica del recurso (`'stock:' || id_empresa || ':' || id_producto || ':' || id_bodega`,
  `'pedido_secuencial:' || id_punto || ':' || id_ambiente`, etc.) para no bloquear recursos que no
  se relacionan entre sí.
- **No reinventar el candado**: si el recurso es el stock de un producto/bodega, usar
  `InventarioRepository::lockStock($idProducto, $idBodega, $idEmpresa)` — llamarlo **antes** de
  `getStockActual()`/`getStockCache()` en cualquier flujo nuevo que luego escriba
  `registrarMovimiento()` + `actualizarStock()`. Si el recurso es el secuencial de un documento
  (factura, ingreso, egreso, etc.), el candado ya vive **dentro** de
  `SecuencialService::obtenerSiguienteSecuencial()` (vía `SecuencialRepository::lockSecuencial()`)
  — no hay que llamarlo aparte, pero sí hay que **abrir la transacción del llamador ANTES** de
  invocar `obtenerSiguienteSecuencial()` y mantenerla abierta hasta el `INSERT`/`UPDATE` final
  (el candado se libera solo al COMMIT/ROLLBACK; si no hay transacción abierta en ese momento, no
  protege nada). Otros ejemplos ya existentes del mismo mecanismo: `ComandaRepository`
  (`comanda_num`), `PedidoService` (`pedido_secuencial`).
- **Nunca duplicar en SQL crudo dentro de un controller** la lógica que ya vive en un
  Repository/Service (rompe además la regla de §3): si hace falta escribir stock, usar
  `InventarioRepository`/`InventarioService`, no reescribir el `INSERT`/`UPDATE` a mano.

---

## 9. Estándar de UI/UX

**Tablas y vistas (listados principales)**
- Deben incluir: **ordenamiento, buscador, paginación, exportación a PDF y Excel**, y opción de **filtrar/mostrar columnas por usuario**.
- **Redimensionamiento de columnas**: los `<th>` usan `data-col`; el ancho se persiste por usuario con la clave `__columnas_anchos__` en preferencias.
- **Formato de celdas personalizadas**: `text-overflow: ellipsis`, `white-space: nowrap` cuando la celda tenga un ancho acotado (`max-width`/`data-col`).

**Layout a pantalla completa (borde a borde) — obligatorio en todo listado con tabla**
- El listado ocupa **todo el ancho y alto disponibles**: la tarjeta de la tabla pega contra los filos izquierdo, derecho y el borde inferior de la ventana (justo sobre la barra de tareas). El título y el buscador/paginación quedan fijos; **solo hace scroll la tabla**.
- Es **automático**: lo aplica el *app-shell* en `public/css/app.css`. Se activa en cualquier página que contenga una tarjeta con la clase **`cmg-table-card`** (vía la clase JS `body.cmg-has-table` y, como respaldo en CSS puro, `body:not(.cmg-no-app-shell) .cmg-main-content:has(.cmg-table-card)`). **El controlador NO necesita `fullWidth`** para esto.
- **Excepción (reportes/dashboards con contenido sobre la tabla)**: el app-shell asume **título + una sola tabla** que llena el alto. Si la página tiene **filtros, tarjetas de KPI u otro contenido encima de la tabla** (p. ej. `reporte_ventas`, `reporte_compras`, `cuentas_por_cobrar`, `cuentas_por_pagar`), **debe desactivarse** el app-shell para que la página haga scroll normal; de lo contrario el `body` queda bloqueado (`overflow: hidden`) y la pantalla se ve **inmóvil**. Para desactivarlo, agregar al inicio de la vista: `<script>document.body.classList.add('cmg-no-app-shell');</script>`. Eso lo respetan tanto el JS como el CSS `:has()`. En ese caso, define tú el scroll de la tabla en la vista (p. ej. `.{modulo}-scroll { max-height: …; overflow: auto; }`).
- **Estructura requerida en la vista**:
  - Tarjeta del listado con la clase **`cmg-table-card`**.
  - Fila de título con un **`<h5>`** (así recibe la sangría horizontal correcta al ir borde a borde).
  - `card-body` con `p-0`, y dentro el **contenedor de scroll**.
- **Nombre del contenedor de scroll (regla crítica)**: debe llamarse **`{modulo}-scroll`** (p. ej. `compras-scroll`, `clientes-scroll`). Debe **contener `-scroll`** y **NO** debe contener el prefijo **`cmg-`**. El prefijo `cmg-` está reservado para clases del framework y queda **excluido** del app-shell (`:not([class*="cmg-"])`); usar `cmg-scroll` rompe el layout (la tabla no se estira ni recorta bien). El marcador interno que agrega el JS es `js-scroll-wrapped` (sin `cmg-`, a propósito): **no** marcar contenedores de scroll con clases `cmg-…`.
- **Comportamiento de columnas**: las columnas conservan su **ancho natural**. Si no caben, la tabla se ensancha y aparece **scroll horizontal** (las columnas NO se recortan). La tabla usa `table-layout: auto` con `min-width: 100%` (llena el ancho cuando sobra espacio).
- **Barras de scroll**: 14px de grosor (vertical y horizontal), definidas globalmente en `app.css` para `[class*="-scroll"]:not([class*="cmg-"])`. No redefinir por módulo.

**Búsqueda (buscador de listados)**
- El buscador usa el helper `App\Helpers\FiltrosBusqueda` (texto libre + filtros `clave:valor`). **Nunca** concatenar la entrada del usuario en el SQL: siempre pasa por este helper con PDO preparadas.
- Sintaxis soportada en el campo de búsqueda:
  - `texto` → busca en las columnas por defecto del módulo (ILIKE).
  - `clave:valor` → filtra por un campo; usar `clave:"valor con espacios"` para valores con espacios.
  - Operadores: `clave:>=2026-01-01`, `<=`, `>`, `<`, `=`.
  - Rango: `clave:2026-01..2026-03` (también numérico, p. ej. `100..500`).
  - Lista (IN): `clave:a,b,c`.
  - Negación: `-clave:valor`.
- En el repository (patrón estándar):
  1. `$parsed = FiltrosBusqueda::parsear($buscar);`
  2. Si `$parsed['texto_libre'] !== ''`, aplicar `ILIKE` del texto libre sobre las columnas por defecto del módulo.
  3. `FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], $mapas);` con el mapa de campos por tipo: `texto` (ILIKE), `exacto` (=/IN), `fecha` (rangos y fechas parciales), `numerico` (=/>/</BETWEEN).
- Las claves no incluidas en el mapa del módulo se ignoran de forma silenciosa.

**Preferencias de usuario (favoritos, columnas, pestañas)**
- Pieza central: `UsuarioPreferenciaService` (`guardarPreferencia` / `obtenerPreferencias`, por `idUsuario` + `idEmpresa` + `modulo` + `campo`) y `App\Helpers\PreferenciasHelper` (render). Persisten en la tabla `usuarios_preferencias` con `valor` en **JSON**. Endpoints en `PreferenciasController`.
- **Empresa favorita** (global del usuario, no por empresa): estrella del navbar → `/Preferencias/guardarEmpresaFavoritaAjax` → `Usuario::setEmpresaFavorita()` (columna en `usuarios`); en sesión `$_SESSION['id_empresa_favorita']`.
- **Favoritos de campos** (valor por defecto en formularios): `PreferenciasHelper::renderEstrellaFavorito($modulo, $idSelectUi, $campoDb)` coloca una estrella junto a un select/input para que el usuario fije un valor preferido que se precargue. Se guarda por campo con `/Preferencias/guardarAjax`. JS: `public/js/favoritos.js` (`APP_FAVORITOS`).
- **Vista de listados** (columnas, anchos, pestañas): se guardan en la clave `__vista__` del módulo vía `/Preferencias/guardarVistaAjax` (mezcla incremental). Sub-claves:
  - `__columnas_ocultas__`: columnas ocultas (por `data-col`).
  - `__columnas_anchos__`: ancho por columna (`data-col => px`).
  - `__pestanas_ocultas__`: pestañas ocultas en modales.
- **Render (usar estos helpers, no reinventar)**:
  - `getPreferenciasVista($modulo)` lee `__vista__`.
  - `renderDropdownColumnas($columnas, $vista, $modulo)` + `renderEstilosColumnasOcultas($vista)`: dropdown para mostrar/ocultar columnas y CSS de columnas ocultas/anchos.
  - `renderDropdownPestanas($pestanas, $vista, $modulo)` + `renderEstilosPestanasOcultas($vista)`: visibilidad de pestañas de modales.
  - `getJavascriptVariables($modulo)`: inyecta las variables JS (`APP_FAVORITOS`, `APP_FAVORITOS_URL`, `APP_VISTAS_URL`).
- Requisito: los `<th>`/`<td>` personalizables deben llevar `data-col`.

**Modales**
- Diseño estandarizado: mismos campos, botones, orden y estilo en todas.
- El botón **Eliminar** va en el **footer, a la izquierda**.
- Las **pestañas** dentro de un modal deben poder **ocultarse/mostrarse por cada usuario**.
- Tablas dentro de modales con **filas compactas**: `<td class="p-0">` e inputs con
  `style="padding:0 4px;height:20px;font-size:0.78rem;"`.
- **Barra de acciones de documento (regla general)**: los botones de **PDF, Correo y WhatsApp** (y otras acciones de documento como XML, ticket, duplicar, enviar al SRI) van en una **barra de acciones superior** al **inicio del cuerpo del modal**, **antes de las pestañas/contenido** — NO sueltos dentro de una pestaña. Es una fila horizontal `d-flex gap-1 align-items-center flex-wrap` con borde inferior; los botones son `btn btn-sm btn-outline-*` solo con ícono (`bi-file-earmark-pdf` rojo, `bi-envelope` info, `bi-whatsapp` verde) y `title`. Agrupar sets con un separador `<div class="vr mx-1"></div>`. Referencia canónica: el modal de **Facturas de Venta** (`app/views/modulos/factura_venta/index.php`, "Barra de Acciones Superior"). Cada acción valida primero que el documento esté guardado.

**Controles**
- Todos los botones, inputs, selects, etc. comparten diseño, color y tamaño.
- **Inputs de búsqueda con selección tipo "chip" (autocomplete que fija un valor, ej. buscador de cuenta/cliente/proveedor)**: cuando el input ya tiene una selección activa (input oculto con el id/código fijado y el texto mostrando una etiqueta tipo "código - nombre"), presionar **Backspace o Delete debe limpiar toda la selección de una vez** (input visible + input oculto + cerrar dropdown), no borrar la etiqueta letra por letra. Referencia de implementación: `setupTypeahead()` en `app/views/modulos/mayores/index.php`.



**Tarjeta de permisos (badges)**
- Muestra los badges **VER, CREAR, MODIFICAR, ELIMINAR** y **ACCESO TOTAL**.
- Clase del contenedor: `p-2 border rounded-3 bg-white shadow-sm mb-3 mx-3`.
- Colores suaves con opacidad (`bg-opacity-10`) y bordes adaptados:
  - Activo: `bg-success` / `text-success`.
  - Inactivo: `bg-secondary text-opacity-50` / `text-secondary`.
  - Acceso Total (si aplica): `bg-info` / `text-info`.

**Fechas**
- Mostrar siempre con formato **`d-m-Y H:i:s`**.

---

## 10. Cómo crear un módulo nuevo (checklist)

Todo módulo nuevo debe contemplar desde el diseño: **multiempresa, permisos, auditoría, eliminación lógica y seguridad**.

1. **Base de datos** (`database/` o `app/migrations/`): crear la tabla operativa con `id_empresa`, los campos de auditoría obligatorios (§5), `eliminado` y, si aplica, `estado`.
2. **Repository** en `app/repositories/modulos/{Nombre}Repository.php`: extiende `BaseRepository`, PDO con consultas preparadas. Usar `getBaseWhere($idEmpresa, $alias, $idUsuarioFiltro)` para filtrar siempre por `id_empresa` + `eliminado = false` (y registros propios cuando aplique), y `FiltrosBusqueda` para el buscador (ver §9).
3. **Rules** en `app/Rules/modulos/{Nombre}Rules.php`: validaciones de negocio.
4. **Service** en `app/Services/modulos/{Nombre}Service.php`: lógica de negocio, **transacciones** y **auditoría** (`LogSistemaService`).
5. **Model** en `app/models/` solo si se necesita acceso a datos adicional (extiende `BaseModel`).
6. **Controller** en `app/controllers/modulos/{Nombre}Controller.php`: extiende `BaseModuloController`, implementa `getRutaModulo()` (p. ej. `'modulos/productos'`) y llama `requireLeer/requireCrear/requireActualizar/requireEliminar` en cada acción. Para el listado, calcular `$idUsuarioFiltro = empty($this->getPermisos()['todo']) ? (int)$_SESSION['id_usuario'] : null` y pasarlo al repository (registros propios). Sin lógica de negocio.
7. **Vista** en `app/views/modulos/{nombre}/`: tabla estándar (§9) y modales estándar (§9). Para columnas visibles/anchos, pestañas y favoritos usar `PreferenciasHelper` (ver §9, *Preferencias de usuario*).
8. **JS** en `public/js/modulos/{nombre}.js`.
9. **Registrar la ruta** en `config/modulos_mvc.php` con `id_submodulo` y `legacy_rutas` (ese archivo documenta el procedimiento exacto).
10. **Menú y permisos** (BD): registrar/actualizar el submódulo en `submodulos_menu` (campo `ruta` = ruta MVC, p. ej. `modulos/productos`) y asignar permisos en `modulos_asignados`. Verificar en `/config/permisos-modulos`.
11. **Documentar el módulo** en `docs/manual/modulos/{ruta-mvc}.md` siguiendo `docs/manual/_PLANTILLA.md`. **Sin ese archivo el módulo no está terminado** (ver §12).

---

## 11. Convenciones de trabajo (para el asistente)

- **Responder siempre en español.**
- **Nunca** usar herramientas de simulación de pantalla ni agentes de navegación (browser_subagent, etc.) para comprobar o validar, bajo ninguna circunstancia.

---

## 12. Documentación obligatoria (Manual del Sistema)

El manual que leen los usuarios vive en **`/documentacion`** y su contenido son
**archivos Markdown del repositorio**, en `docs/manual/`. La documentación es
parte del entregable, no un extra posterior.

**Regla:** todo módulo nuevo, y todo cambio que altere lo que el usuario ve o
hace, incluye su archivo `.md` **en el mismo cambio que el código**. Un módulo
sin `.md` no está terminado.

- **Dónde**: la ruta del archivo es la dirección del artículo.
  `docs/manual/modulos/clientes.md` → `modulos/clientes`. Carpetas: `modulos/`,
  `config/`, `guias/` (procesos que cruzan módulos) y `conceptos/`.
- **Cómo**: copiar `docs/manual/_PLANTILLA.md` y respetar sus secciones (qué es,
  requisitos, cómo se usa, campos, permisos, reglas de negocio, integraciones,
  errores frecuentes, historial de cambios).
- **Al modificar un módulo ya documentado**: actualizar las secciones afectadas y
  añadir una línea al *Historial de cambios* con la nueva versión.
- **Publicación**: `git pull` en el servidor y luego el botón **Sincronizar** de
  `/documentacion/gestion` (solo nivel 3). El sincronizador cruza por `slug`,
  detecta cambios por hash y **nunca sobrescribe** los artículos escritos desde
  la pantalla (`origen = 'manual'`).
- **Configuración**: los artículos bajo `config/` se fuerzan automáticamente a
  `visibilidad = 'superadmin'` por su ruta. No depende de marcarlo a mano.
- **Formato**: Markdown acotado (`App\lib\MarkdownSimple`): encabezados
  `##`/`###`/`####`, listas, tablas, citas, bloques de código, énfasis y
  enlaces. El HTML crudo se escapa y se ve como texto.
- Cada `##` se indexa por separado y el buscador lleva directo a esa sección:
  los títulos deben decir de qué tratan. Las **etiquetas** del front-matter pesan
  igual que el título, así que deben incluir los sinónimos que usaría alguien
  que no conoce el sistema.
