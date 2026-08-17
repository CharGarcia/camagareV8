---
titulo: Permisos de módulos
resumen: Cómo se asignan los accesos por submódulo y qué significa cada permiso.
categoria: Configuración
ruta_modulo: config/permisos-modulos
tipo: modulo
visibilidad: superadmin
requiere_permiso_modulo: no
etiquetas: permisos, accesos, roles, niveles, usuarios, modulos asignados, acceso total
version: 1.1
orden: 10
estado: activo
---

Esta pantalla define **qué puede hacer cada usuario en cada submódulo** del
sistema, empresa por empresa.

## Niveles de usuario

- **Nivel 3 — Superadministrador**: acceso total a todos los módulos, empresas y
  configuraciones. No necesita asignaciones.
- **Nivel 2 — Administrador**: accede a los módulos y configuraciones que el
  superadministrador le asigne.
- **Nivel 1 — Usuario**: accede solo a los módulos y empresas asignados.

Ningún usuario, salvo el nivel 3, puede ver información de empresas que no tenga
asignadas.

## Los cinco permisos

- **Ver**: consultar el listado del submódulo.
- **Crear**: registrar nuevos documentos.
- **Modificar**: editar los existentes.
- **Eliminar**: dar de baja (siempre de forma lógica).
- **Acceso total**: ver los registros de *toda la empresa*.

## Registros propios frente a acceso total

Es la distinción que más consultas genera. Si un usuario **no** tiene acceso
total, ve únicamente *los registros que él mismo creó*. Con acceso total ve los
de todos los usuarios de esa empresa.

Por eso un vendedor nuevo entra y ve el listado vacío: no es un error, todavía no
ha creado nada y no tiene acceso total.

## Cómo asignar permisos

1. Elija el usuario y la empresa.
2. Marque los permisos submódulo por submódulo.
3. Guarde. El cambio se aplica en la siguiente pantalla que abra el usuario.

## Por qué un módulo manda al tablero

Cuando un usuario entra a un módulo y el sistema lo devuelve al tablero, es
porque no tiene el permiso **VER** de ese submódulo en la empresa activa. Revise
en este orden:

1. **El permiso está marcado para esa empresa.** Los permisos son por empresa: un
   usuario puede tener Movimientos de Inventario en una empresa y no en otra.
   Cambie la empresa en el selector de esta pantalla y confírmelo.
2. **Está marcado VER, no solo Crear o Modificar.** Sin VER no se abre el módulo.
   Desde esta versión el menú tampoco muestra los submódulos sin VER, así que un
   enlace que ya no aparece suele ser esto.
3. **El submódulo está activo** en el menú (los desactivados ya no se muestran).
4. **La ruta del submódulo coincide** con la del módulo (por ejemplo
   `modulos/inventario`). Si está mal escrita, el sistema no puede relacionar el
   permiso con el módulo.

El usuario debe recargar la pantalla después de que le asignen el permiso: el
cambio se aplica en la siguiente página que abra.

## Historial de cambios

- **1.1** — El menú deja de mostrar submódulos sin permiso VER o desactivados
  (antes aparecían y al abrirlos devolvían al tablero). El permiso se relaciona
  con el módulo por su ruta registrada en el menú, no por un número fijo, así que
  ya no depende de que los identificadores coincidan entre instalaciones. Se
  amplió el apartado "Por qué un módulo manda al tablero".
- **1.0** — Versión inicial.
