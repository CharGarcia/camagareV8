---
titulo: Departamentos del taller
resumen: Define las áreas por las que pasa un vehículo dentro del taller y el orden en que las recorre.
categoria: Operaciones
ruta_modulo: modulos/taller-departamentos
tipo: modulo
visibilidad: todos
etiquetas: departamentos, areas, estaciones, taller, mecanica, pintura, enderezada, armado, flujo, diagnostico, control de calidad, tablero
version: 2.0
orden: 1
estado: activo
---

Aquí se arma el flujo del taller: por qué áreas pasa un vehículo y en qué orden.
Es la configuración previa del módulo [Taller Mecánico](modulos/taller).

El **checklist de recepción** se administra aparte, en su propio módulo:
[Checklist de recepción](modulos/taller-checklist).

## Qué es y para qué sirve

Cada taller trabaja distinto: uno tiene mecánica, frenos y suspensión; otro tiene
desarme, enderezada, preparación, pintura, pulido y armado. Por eso los
departamentos no vienen fijos: los define cada empresa.

Cada departamento que se crea aquí genera automáticamente:

- Una **columna en el tablero** del taller, con su color.
- Una **pantalla de tablet** propia en `modulos/taller/estacion?id_departamento=N`.
- Una opción en el selector de «enviar el vehículo a».

## Requisitos previos

Ninguno. Es lo primero que se configura antes de usar el módulo de taller.

## Cómo se usa

1. **Nuevo departamento** → nombre, código corto, color e ícono.
2. El **orden** define cómo se listan en el tablero y en las tablets. Use saltos
   de 10 en 10 (10, 20, 30…) para poder intercalar después sin renumerar todo.
3. Marque **departamento de diagnóstico** en el área que revisa el vehículo y
   arma el presupuesto. Es el único que puede trabajar antes de que el cliente
   apruebe.
4. Marque **control de calidad** en el área que verifica el trabajo antes de
   entregar.
5. En cada tablet del taller, abra la estación de su departamento y deje la
   página guardada en marcadores. Cada pantalla se queda en su departamento.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre | Sí | Cómo se llama el área. No puede repetirse en la empresa |
| Código | No | Abreviatura corta (PIN, END, MEC) |
| Descripción | No | Aclaración de qué se hace ahí |
| Color | No | Color de la columna del tablero y de la barra en el informe técnico |
| Ícono | No | Se muestra en el tablero y en las pestañas de las tablets |
| Orden | No | Posición dentro del flujo del taller |
| Es de diagnóstico | No | Puede trabajar sin la aprobación del cliente |
| Es control de calidad | No | Área de verificación final |
| Activo | No | Un departamento inactivo no aparece en el tablero ni recibe vehículos |

## Permisos

- **Ver**: consultar los departamentos.
- **Crear** / **Modificar**: administrar el catálogo.
- **Eliminar**: quitar un departamento.

## Reglas de negocio

- No pueden existir dos departamentos con el mismo nombre en una empresa.
- **No se puede eliminar un departamento que tenga vehículos dentro.** Muévalos
  primero desde el tablero o desde la orden.
- Al eliminar, el departamento se marca como eliminado; las órdenes históricas
  siguen mostrando por dónde pasó el vehículo.

## Integraciones con otros módulos

- **Taller Mecánico**: consume este catálogo para el tablero, las estaciones, el
  recorrido del vehículo y el informe técnico.

## Errores frecuentes

- **«No se puede eliminar: hay vehículos en este departamento»**: mueva las
  órdenes activas a otra área y vuelva a intentarlo.
- **«Ya existe un departamento con ese nombre»**: puede haber uno inactivo con el
  mismo nombre. Búsquelo y reactívelo en lugar de crear otro.
- **La tablet dice «No hay departamentos configurados»**: cree al menos uno y
  déjelo activo.

## Historial de cambios

- **2.0** — El checklist de recepción se separó a su propio módulo,
  [Checklist de recepción](modulos/taller-checklist), con sus propios permisos.
  Esta pantalla queda solo para el flujo de departamentos.
- **1.1** — Los departamentos también se pueden crear desde la barra de acciones
  de una orden de trabajo, sin entrar a esta pantalla. Requiere permiso de
  creación sobre este módulo.
- **1.0** — Versión inicial: catálogo de departamentos por empresa con color,
  ícono, orden y roles especiales.
