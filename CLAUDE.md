El sistema será desarrollado utilizando PHP 8 o superior, PostgreSQL, Bootstrap 5 o superior, JavaScript y Font Awesome, bajo una arquitectura propia basada en el patrón MVC.

La estructura del sistema estará organizada en carpetas como app (controllers, models, views, core, middleware, services, rules, repositories, helpers, validations, traits), config, routes, public, database, storage, legacy y vendor.

Los controladores solo deben recibir solicitudes, validar datos básicos y delegar la lógica a services o rules. Los modelos solo deben manejar acceso a base de datos. La lógica de negocio debe estar en services y rules. Las validaciones específicas deben centralizarse.

El sistema tendrá tres niveles de usuario:
Nivel 3 (Superadministrador): acceso total sin restricciones a todos los módulos, configuraciones, empresas y usuarios.
Nivel 2 (Administrador): acceso a módulos, submódulos y configuraciones asignadas por el superadministrador.
Nivel 1 (Usuario): acceso únicamente a módulos, submódulos y empresas asignadas.

El sistema será multiempresa (multitenant). Todas las tablas operativas que esten dentro de submodulos deben incluir el campo id_empresa.

Las configuraciones generales del sistema no deben llevar id_empresa. Estas pertenecen al sistema completo y no a una empresa específica. Solo los módulos operativos y configuraciones específicas por empresa deben usar id_empresa.

Toda consulta sobre módulos operativos debe filtrar obligatoriamente por id_empresa y por eliminado = false.

Ningún usuario, excepto el superadministrador, puede acceder a información de empresas que no tenga asignadas.

Todo acceso a módulos y submódulos debe validarse mediante middleware, verificando autenticación, empresa activa y permisos (ver, crear, modificar, eliminar, acceso a todo o solo registros propios).

Ningún registro debe eliminarse físicamente de la base de datos. Todas las tablas operativas deben incluir el campo eliminado (boolean), y se debe usar eliminación lógica.

Además, toda tabla operativa debe incluir:
created_at, updated_at, created_by, updated_by, eliminado, deleted_at y deleted_by.

Las tablas que lo requieran deben incluir un campo estado (activo, inactivo, borrador, aprobado, anulado, etc.). El estado no reemplaza el campo eliminado.

Toda acción importante del sistema debe registrarse en una tabla de auditoría (log_sistema), incluyendo usuario, empresa (cuando aplique), acción, tabla afectada, datos anteriores y nuevos.

En tablas globales (configuración del sistema), el campo id_empresa no debe existir. En auditoría, id_empresa puede ser NULL en estos casos.

Se debe utilizar transacciones en todos los procesos. Si una operación falla, todo el proceso debe revertirse.

Las contraseñas deben almacenarse con hash seguro (password_hash). Se deben usar consultas preparadas (PDO), protección CSRF, validación de sesiones y control de acceso mediante middleware.

El sistema debe separar claramente responsabilidades:
Controller → Service → Rules → Repository → Base de datos.

Ningún controlador debe contener lógica de negocio. Ningún modelo debe contener lógica de negocio.

Todas las consultas a base de datos deben pasar por modelos o repositories.

Ningún módulo operativo debe ejecutarse sin validar id_empresa.

Las tablas del sistema se clasifican en:

- Tablas globales: no llevan id_empresa (configuración general, catálogos globales).
- Tablas operativas: sí llevan id_empresa (clientes, productos, facturas, inventario, ingresos, egresos, adquisiciones, etc.).

Las configuraciones por empresa deben almacenarse en una tabla independiente con id_empresa. Las configuraciones globales deben almacenarse en una tabla sin id_empresa.

El sistema puede permitir personalización por usuario (columnas, filtros, preferencias), almacenadas en formato JSON.

Todas las tablas de vistas deben mantener el mismo formato, con ordenamiento, opcion de firltar campos por usuario, buscador, paginacion, exportacion a pdf y excel.

Todas las ventanas modales deben tener diseño estandarizado, con los mismos campos, botones, ordenamiento, etc.

Las tablas que se encuentren dentro de ventanas modales deben tener filas compactas: los <td> con class="p-0" y los inputs con style="padding:0 4px;height:20px;font-size:0.78rem;" para mantener un alto de fila reducido y uniforme.

Todos los botones, input, select, etc deben tener el mismo diseño, color, tamaño, etc.

Todas las pestañas que esten dentro de una ventana modal deben tener la opcion de ocultarse y mostrarse para cada usuario.

En los modulos operativos dentro de una empresa donde tenga un usuario asignado, deben tener la opcion de establecer permisos de ver, crear, modificar, eliminar, acceso a todo o solo registros propios.

Todo módulo nuevo debe considerar desde su diseño:

- multitenancy
- permisos
- auditoría
- eliminación lógica
- seguridad

Todo acceso debe ser seguro, auditable, no destructivo, modular y escalable.

- id_empresa SOLO debe usarse en módulos y tablas que dependan de usuario y empresa.
- id_empresa NO debe usarse en configuraciones globales del sistema.
- Las configuraciones globales son únicas para toda la aplicación.
- Las configuraciones por empresa deben almacenarse en tablas separadas.
- Las consultas globales NO deben filtrar por id_empresa.
- Las consultas operativas SIEMPRE deben filtrar por id_empresa.

las fechas siempre muestra con formato d-m-Y H:m:s.

los botones de eliminar que tienen todos los modales, deben ir en el footer a la izquierda

- La tarjeta debe mostrar los badges de **VER, CREAR, MODIFICAR, ELIMINAR** y **ACCESO TOTAL**.
- Debe utilizar la clase `p-2 border rounded-3 bg-white shadow-sm mb-3 mx-3`.
- Los badges deben usar colores suaves con opacidad (`bg-opacity-10`) y bordes adaptados:
  - Activo: `bg-success` / `text-success`.
  - Inactivo: `bg-secondary text-opacity-50` / `text-secondary`.
  - Acceso Total (si aplica): `bg-info` / `text-info`.

### Estándar de Tablas y Vistas

- Todas las tablas operativas deben incluir ordenamiento, buscador, paginación y exportación (PDF/Excel).
- **Redimensionamiento**: Todas las tablas principales deben permitir el ajuste manual de ancho de columnas. Los encabezados `<th>` deben usar `data-col` para identificar la columna y permitir la persistencia del ancho mediante la clave `__columnas_anchos__` en las preferencias.
- **Formato**: Usar `table-layout: fixed`, `text-overflow: ellipsis` y `white-space: nowrap` para celdas personalizadas.

las respuestas siempre en español

- No se deben realizar comprobaciones o validaciones mediante herramientas de simulación de pantalla o agentes de navegación (como browser_subagent) bajo ninguna circunstancia.
