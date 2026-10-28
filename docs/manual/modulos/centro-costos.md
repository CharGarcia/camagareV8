---
titulo: Centros de costo
resumen: División estructural de la empresa para saber cuánto gasta cada área o sucursal.
categoria: Contabilidad
ruta_modulo: modulos/centro-costos
tipo: modulo
visibilidad: todos
etiquetas: centro de costos, centros de costo, departamento, sucursal, area, gasto por area, presupuesto, documentos del centro de costo, resumen del centro de costo, pdf centro de costo, excel centro de costo, no se puede eliminar centro de costo, centro en uso
version: 1.2
orden: 85
estado: activo
---

Un **centro de costo** es una división estable de la empresa: un departamento,
una sucursal, una línea de negocio. Sirve para saber cuánto consume cada una.

## Cómo se usa

1. Registre los centros de costo de la empresa.
2. Asigne cada documento de gasto al centro que corresponda: por periodo en la
   cuenta contable (Plan de Cuentas) o, documento por documento, en la pestaña
   **Asiento contable** del documento.
3. Abra el centro y consulte sus pestañas **Documentos** y **Resumen**.

## Pestaña Documentos

Lista, uno por fila, los documentos cuyo asiento tiene al menos una línea con este
centro de costo: fecha, número de asiento, origen, número del documento, tercero y
los importes **de esas líneas**. Se filtra por **Desde / Hasta** con **Mostrar**,
**Todo** quita las fechas y un clic en la fila abre el documento. Se muestran hasta
2.000, los más recientes.

## Pestaña Resumen

Ingresos (cuentas 4), costos y gastos (cuentas 5 y 6), resultado y número de
documentos del período, con el detalle **por cuenta contable** y **por mes**.
Los botones **PDF** y **Excel** sacan este resumen con el logo de la empresa y la
lista de documentos; el Excel trae cada parte en su propia hoja.

Solo cuentan los asientos **contabilizados** de **producción**, no anulados. Las dos
pestañas aparecen si el usuario tiene acceso a **Contabilidad → Asientos
Contables**, y cada usuario puede ocultarlas con el engranaje de las pestañas.

## No se puede eliminar un centro en uso

Si el centro ya está en alguna línea de asiento, en una cuenta o periodo del Plan
de Cuentas o en un presupuesto, el sistema no lo elimina y dice dónde se usa. Si ya
no lo va a usar, desactive el interruptor **Activo**.

## Cuántos crear

Pocos y estables. La utilidad de un centro de costo está en poder compararlo
consigo mismo mes a mes, y eso se pierde si la estructura cambia cada trimestre o
si hay veinte centros con movimientos residuales.

## Diferencia con proyectos

El centro de costo es **permanente** (existe mientras exista el área); el
proyecto es **temporal** (nace y termina). Un gasto puede llevar los dos: la obra
X ejecutada por el departamento Y.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo. Busca en código, nombre y descripción, por **palabras sueltas** en
cualquier orden y **sin distinguir tildes ni mayúsculas**. La columna **Estado**
no entra en la búsqueda libre: para filtrar por ella use la ventana de filtros.
Mientras busca, la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
llene los que necesite y pulse **Aplicar**.

| Bloque | Filtros |
|--------|---------|
| Datos | Código, nombre, descripción |
| Estado | Activo / inactivo |
| Registro | Fecha de registro (con atajos *Hoy*, *Esta semana*, *Este mes*…), usuario que registró |

Cada filtro aplicado aparece como una etiqueta dentro del cuadro de búsqueda; la
**×** lo quita. También sirve la sintaxis `clave:valor` (`estado:inactivo`,
`registro:2026-01..2026-03`, `-codigo:ADM`). **PDF** y **Excel** exportan con la
misma búsqueda, filtros y orden que haya en pantalla.

## Ordenar el listado

Pulse el título de una columna (código, nombre, descripción o estado) para
ordenar por ella y vuelva a pulsarlo para invertir el sentido. Con **Shift+clic**
puede encadenar hasta tres columnas (p. ej. *Estado* y luego Shift+clic en
*Nombre*: los activos primero, cada grupo en orden alfabético). El número junto a
la flecha indica la prioridad. El orden se guarda para usted.

## Errores frecuentes

- **Un centro no muestra gastos**: los documentos no se le están asignando.
- **La suma por centros no llega al total de gastos**: hay documentos sin centro
  asignado.
- **No me deja eliminar el centro**: ya se usa; márquelo como inactivo.

## Historial de cambios

- **1.2** — Pestañas **Documentos** y **Resumen** en el modal, con PDF y Excel con logo. Ya no se puede eliminar un centro de costo en uso.
- **1.1** — Buscador con ventana de filtros (embudo), orden por varias columnas (Shift+clic) y PDF/Excel con el mismo orden; el modal ya no tiene pestaña *Información*.
- **1.0** — Versión inicial.
