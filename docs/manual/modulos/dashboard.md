---
titulo: Tablero
resumen: Pantalla de inicio con los indicadores y avisos del día.
categoria: Primeros pasos
ruta_modulo: modulos/dashboard
tipo: modulo
visibilidad: todos
etiquetas: tablero, dashboard, inicio, resumen, indicadores, avisos, pantalla principal, home
version: 1.0
orden: 2
estado: activo
---

El **tablero** es la pantalla de inicio: el resumen de cómo va la empresa y lo
que requiere atención hoy.

## Qué muestra

Depende de los módulos que tenga asignados cada usuario, pero en general:
indicadores de ventas y cobros, documentos pendientes y avisos de cosas que
requieren acción.

## Lo que ve cada usuario

El tablero **respeta los permisos**: solo muestra información de los módulos a
los que la persona tiene acceso, y de la empresa activa. Dos usuarios verán
tableros distintos, y es correcto.

Si además no tiene el permiso de *acceso total*, los indicadores reflejan solo
sus propios registros.

## Si el tablero sale vacío

Suele ser una de tres cosas:

1. No hay **empresa activa** seleccionada.
2. El usuario no tiene módulos asignados todavía.
3. Es una empresa nueva sin movimientos.

## Errores frecuentes

- **Los números no coinciden con los reportes**: revise el periodo que muestra
  cada indicador y si le falta el permiso de acceso total.
- **Entré a un módulo y volví al tablero**: es lo que ocurre cuando no se tiene
  permiso sobre ese módulo. Pida el acceso al administrador.

## Historial de cambios

- **1.0** — Versión inicial.
