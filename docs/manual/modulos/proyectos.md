---
titulo: Proyectos
resumen: Etiqueta para agrupar ingresos y gastos de una obra, contrato o iniciativa concreta.
categoria: Contabilidad
ruta_modulo: modulos/proyectos
tipo: modulo
visibilidad: todos
etiquetas: proyectos, obra, contrato, rentabilidad por proyecto, agrupar gastos, seguimiento, avance del proyecto, porcentaje de ejecucion, ejecucion presupuestaria, presupuesto del proyecto, documentos del proyecto, que documentos tiene el proyecto, pdf del proyecto, excel del proyecto, avance fisico, no se puede eliminar proyecto, proyecto en uso
version: 1.3
orden: 80
estado: activo
---

Un **proyecto** agrupa los movimientos de una obra, un contrato o una iniciativa
concreta, para poder responder cuánto costó y cuánto dejó.

Es una dimensión de análisis: no cambia la contabilidad, la organiza.

## Cómo se usa

1. Registre el proyecto con su nombre, presupuesto y fechas de inicio y fin.
2. Asigne el proyecto a los documentos: por periodo en la cuenta contable (Plan de
   Cuentas) o, documento por documento, en la pestaña **Asiento contable** de cada
   compra, factura o egreso.
3. Abra el proyecto y consulte sus pestañas **Documentos** y **Avance**.

## % de ejecución del listado

La columna **% Ejec.** se calcula sola: lo **ejecutado** (costos y gastos del
proyecto, cuentas 5 y 6) dividido para el **presupuesto**. Al pasar el mouse se ve
"Ejecutado $X de $Y". La barra es azul, amarilla desde el 90 % y roja si se pasó
del presupuesto. Un proyecto sin presupuesto muestra "—".

El campo **% Avance físico** del modal es otro dato: el avance de la obra que usted
estima y digita. Se ve en la pestaña Avance y en sus reportes. En el buscador,
`ejecucion:` filtra por el % calculado y `avance_fisico:` por el digitado.

## Pestaña Documentos

Lista, uno por fila, los documentos cuyo asiento tiene al menos una línea con este
proyecto: fecha, número de asiento, origen (compra, factura, egreso…), número del
documento, tercero y los importes **de esas líneas** (no el total del documento).

- Filtre por **Desde / Hasta** y pulse **Mostrar**; **Todo** quita las fechas.
- Clic en una fila para ver el documento.
- Se muestran hasta 2.000 documentos, los más recientes; si hay más, el pie lo
  avisa y conviene acotar el período.

## Pestaña Avance

Indicadores del proyecto en el período elegido:

| Indicador | Qué es |
|---|---|
| Presupuesto | El digitado en el proyecto |
| Ejecutado | Costos y gastos (cuentas 5 y 6: debe − haber) |
| % Ejecución | Ejecutado / presupuesto |
| Por ejecutar | Presupuesto − ejecutado (en rojo si se pasó) |
| Avance en el tiempo | Parte del plazo (inicio a fin) que ya transcurrió hoy |
| Avance físico | El % digitado en el proyecto |
| Ingresos | Cuentas 4 (haber − debe) |
| Resultado | Ingresos − costos y gastos |

Si lo ejecutado va más de 10 puntos por delante del tiempo transcurrido, se
muestra un aviso. Debajo están el detalle **por cuenta contable** y **por mes**.

Los botones **PDF** y **Excel** sacan el reporte del avance con el logo y los datos
de la empresa: indicadores, resumen por cuenta, avance por mes y la lista de
documentos. El Excel trae cada parte en su propia hoja.

## Qué asientos cuentan

Solo los asientos **contabilizados** de **producción**, no anulados ni eliminados.
Los de pruebas no se muestran, aunque la empresa esté trabajando en pruebas.

Las pestañas Documentos y Avance las ve todo el que puede ver Proyectos. Para un
usuario de **nivel 1**, el administrador decide cuáles ve en **Configuración →
Permisos de módulos → Pestañas que puede ver — Proyectos**; sin configurar, las ve
todas. Una pestaña oculta tampoco deja sacar su PDF o Excel. Además, cada usuario
puede ocultarlas para sí con el engranaje de las pestañas.

## No se puede eliminar un proyecto en uso

Si el proyecto ya está en alguna línea de asiento, en el periodo de una cuenta del
Plan de Cuentas o en un presupuesto, el sistema no lo elimina y dice dónde se usa.
Si ya no lo va a usar, desactive el interruptor **Activo**: deja de aparecer en las
listas y se conserva su historia.

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
- **El % de ejecución sale "—"**: el proyecto no tiene presupuesto.
- **Un documento no aparece en Documentos**: su asiento no tiene el proyecto en ninguna línea, está anulado o es de ambiente de pruebas.
- **No me deja eliminar el proyecto**: ya se usa; márquelo como inactivo.

## Historial de cambios

- **1.3** — Las pestañas Documentos y Avance ya no dependen del acceso a Asientos Contables: las ve quien ve Proyectos, y para nivel 1 el administrador decide en Permisos de módulos.
- **1.2** — Pestañas **Documentos** y **Avance** en el modal, con PDF y Excel con logo. La columna **% Ejec.** se calcula desde los asientos (ejecutado / presupuesto); el campo digitado pasa a llamarse **% Avance físico**. Ya no se puede eliminar un proyecto en uso.
- **1.1** — Buscador con ventana de filtros (embudo), orden por varias columnas (Shift+clic, incluidas presupuesto, % de ejecución y fechas) y PDF/Excel con el mismo orden; el modal ya no tiene pestaña *Información*.
- **1.0** — Versión inicial.
