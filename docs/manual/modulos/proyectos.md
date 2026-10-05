---
titulo: Proyectos
resumen: Etiqueta para agrupar ingresos y gastos de una obra, contrato o iniciativa concreta.
categoria: Contabilidad
ruta_modulo: modulos/proyectos
tipo: modulo
visibilidad: todos
etiquetas: proyectos, obra, contrato, rentabilidad por proyecto, agrupar gastos, seguimiento
version: 1.1
orden: 80
estado: activo
---

Un **proyecto** agrupa los movimientos de una obra, un contrato o una iniciativa
concreta, para poder responder cuánto costó y cuánto dejó.

Es una dimensión de análisis: no cambia la contabilidad, la organiza.

## Cómo se usa

1. Registre el proyecto con su nombre.
2. Al capturar documentos (compras, egresos, facturas), asígnelos al proyecto que
   corresponda.
3. Consulte después el acumulado por proyecto.

## La disciplina es todo

Un proyecto solo sirve si **todos** los documentos que le corresponden se le
asignan. Si la mitad de las compras de una obra quedan sin proyecto, el costo que
muestre será menor que el real y la rentabilidad parecerá mejor de lo que es.

Conviene decidir desde el inicio quién asigna el proyecto y en qué momento.

## Diferencia con centro de costos

Un **proyecto** suele ser temporal y con inicio y fin (una obra, un contrato). Un
**centro de costos** es permanente y estructural (un departamento, una sucursal).
Se pueden usar los dos a la vez.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo. Busca en código, nombre, descripción y en el nombre e identificación del
cliente, por **palabras sueltas** en cualquier orden y **sin distinguir tildes ni
mayúsculas**. La columna **Estado** no entra en la búsqueda libre: para filtrar
por ella use la ventana de filtros. Mientras busca, la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
llene los que necesite y pulse **Aplicar**.

| Bloque | Filtros |
|--------|---------|
| Datos | Código, nombre, descripción |
| Cliente | Cliente (solo los que ya tienen proyectos), estado activo / inactivo |
| Avance | Presupuesto (mínimo y máximo), % de ejecución (mínimo y máximo) |
| Fechas | Fecha de inicio y fecha de fin, con atajos *Hoy*, *Esta semana*, *Este mes*, *Este año* |
| Registro | Fecha de registro, usuario que registró |

Cada filtro aplicado aparece como una etiqueta dentro del cuadro de búsqueda; la
**×** lo quita. También sirve la sintaxis `clave:valor` (`estado:activo`,
`presupuesto:>=5000`, `ejecucion:0..50`, `inicio:2026-01..2026-06`). **PDF** y
**Excel** exportan con la misma búsqueda, filtros y orden que haya en pantalla.

## Ordenar el listado

Pulse el título de cualquier columna para ordenar por ella y vuelva a pulsarlo
para invertir el sentido. Con **Shift+clic** puede encadenar hasta tres columnas
(p. ej. *Cliente* y luego Shift+clic en *F. Inicio*: los proyectos de cada
cliente por fecha). El número junto a la flecha indica la prioridad. El orden se
guarda para usted.

## Errores frecuentes

- **El costo del proyecto parece bajo**: hay documentos sin asignar.
- **No aparece al capturar un documento**: verifique que esté activo.

## Historial de cambios

- **1.1** — Buscador con ventana de filtros (embudo), orden por varias columnas (Shift+clic, incluidas presupuesto, % de ejecución y fechas) y PDF/Excel con el mismo orden; el modal ya no tiene pestaña *Información*.
- **1.0** — Versión inicial.
