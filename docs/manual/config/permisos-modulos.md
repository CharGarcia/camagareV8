---
titulo: Permisos de módulos
resumen: Cómo se asignan los accesos por submódulo y qué significa cada permiso.
categoria: Configuración
ruta_modulo: config/permisos-modulos
tipo: modulo
visibilidad: superadmin
requiere_permiso_modulo: no
etiquetas: permisos, accesos, roles, niveles, usuarios, modulos asignados, acceso total
version: 1.0
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

Cuando un usuario con permisos correctos entra a un módulo y el sistema lo
devuelve al tablero, casi siempre es porque la ruta registrada en el menú no
coincide con la ruta real del módulo. Revise que la ruta del submódulo esté
escrita igual que la del controlador.

## Historial de cambios

- **1.0** — Versión inicial.
