---
titulo: Utilidades
resumen: Reparto del 15% de participación de los trabajadores en las utilidades, informe al Ministerio del Trabajo, pago por Egresos y asiento del 31 de diciembre.
categoria: Nómina
ruta_modulo: modulos/utilidades
tipo: modulo
visibilidad: todos
etiquetas: utilidades, participacion trabajadores, 15%, reparto de utilidades, 10% tiempo, 5% cargas familiares, cargas, ex trabajadores, tope 24 sbu, ministerio de trabajo, salarios en linea, informe empresarial, pago de utilidades, abril, asiento 31 de diciembre, participacion trabajadores por pagar
version: 1.1
orden: 61
estado: activo
---

Este módulo calcula cuánto le toca a cada trabajador de la participación en
las utilidades de la empresa (el 15% que manda el Código del Trabajo), prepara
el archivo para el Ministerio del Trabajo, deja cada valor listo para pagarlo
desde Egresos y, si la empresa lleva contabilidad, genera el asiento del cierre.
Funciona igual que los módulos de Décimo Tercero y Décimo Cuarto.

## Qué es y para qué sirve

Cada año, hasta el 15 de abril, el empleador reparte entre sus trabajadores el
15% de la utilidad líquida del ejercicio anterior y registra ese reparto en el
Sistema de Salarios en Línea del Ministerio del Trabajo. El módulo resuelve las
cuatro partes de ese proceso:

1. **Calcular** el reparto por trabajador, incluidos los que salieron durante el
   año.
2. **Exportar** el archivo con el informe para el Ministerio.
3. **Pagar** cada valor desde Egresos → Nómina, igual que un décimo.
4. **Contabilizar** el gasto y el pasivo al 31 de diciembre del ejercicio.

## Requisitos previos

- **Períodos de empleo al día** en la ficha de cada empleado (fecha de ingreso
  y, si salió, fecha de salida). De ahí salen los días laborados. Un empleado
  sin período dentro del ejercicio no entra en el reparto.
- **Cargas familiares** registradas en la ficha del empleado (campo *Cargas
  familiares*). Para utilidades cuentan los hijos menores de 18 años, los hijos
  con discapacidad de cualquier edad y el cónyuge o conviviente en unión de
  hecho, siempre que el trabajador los haya acreditado ante la empresa hasta el
  31 de marzo. Se pueden corregir en la grilla sin tocar la ficha.
- **Salario básico del año** en la tabla de salarios (la misma de la nómina),
  para el tope de 24 SBU por trabajador.
- **Utilidad líquida del ejercicio**: la utilidad contable antes de la
  participación de trabajadores y del impuesto a la renta. La toma el contador
  de los estados financieros; el módulo no la calcula.
- Para contabilizar: las cuentas **Gasto Participación Trabajadores** y
  **Participación Trabajadores por Pagar** asignadas en Configuración Contable
  → Nómina.

## Cómo se usa

1. Pulse **Nuevo**, indique el **ejercicio fiscal** (el año que generó la
   utilidad) y la **utilidad líquida**. El sistema propone el 15% como **monto a
   repartir**; puede ajustarlo si la empresa reparte otro valor.
2. Al guardar se abre el detalle. En la pestaña **Resumen** verá el monto del
   10% y del 5%, el tope por trabajador, el total a pagar y el excedente que va
   al IESS.
3. En la pestaña **Trabajadores** revise nombres y apellidos (salen de la ficha,
   con los nombres primero; si algo está al revés, corríjalo en la ficha del
   empleado o aquí), las cargas, el tipo de pago, la discapacidad y la retención
   judicial. Los cambios se guardan solos al salir del campo.
4. Pulse **CSV Ministerio** para obtener el archivo del Ministerio del Trabajo.
5. Pulse **Contabilizar** para generar el asiento del 31 de diciembre.
6. Pague desde **Egresos → Nómina**: cada trabajador aparece con su valor de
   utilidades pendiente, igual que un décimo cuarto.

Si vuelve a calcular el mismo ejercicio (por ejemplo, porque cambió la utilidad
líquida), el sistema recalcula sobre el mismo registro y conserva lo que usted
editó en la grilla.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Ejercicio fiscal | Sí | Año que generó la utilidad. Se reparte en abril del año siguiente. |
| Utilidad líquida | No | Utilidad contable del ejercicio antes de participación e impuesto a la renta. Informativa; sirve para proponer el monto. |
| Monto a repartir | Sí | Lo que efectivamente se reparte (por defecto, el 15% de la utilidad líquida). |

En la grilla de trabajadores:

| Columna | Editable | Qué significa |
|---------|----------|---------------|
| Días | No | Días laborados dentro del ejercicio, base 360, según los períodos de empleo. |
| Cargas | Sí (sin pagos) | Cargas familiares acreditadas. Cambiarlas vuelve a repartir el 5% entre todos. |
| 10% | No | Participación por tiempo: proporcional a los días. |
| 5% | No | Participación por cargas: proporcional a días × cargas. |
| Excedente | No | Lo que supera 24 SBU. No se le paga al trabajador; se deposita al IESS. |
| A pagar | No | 10% + 5% menos el excedente. Es lo que aparece en Egresos. |
| Tipo de pago | Sí | P pago directo, A acreditación en cuenta, RP y RA sus variantes con retención. |
| Discap. | Sí | Marca de discapacidad para el archivo del Ministerio. |
| Ret. judicial | Sí | Retención judicial sobre este pago. Arranca en cero: el valor mensual de la ficha es del rol, no de este pago único. |

## Buscar y filtrar el listado

El buscador escribe directo sobre el ejercicio. El botón de filtros abre el modal con
ejercicio, fecha límite de pago, estado, monto a repartir y total a pagar; los filtros
activos quedan como chips junto al buscador. Las columnas se pueden ocultar o mostrar
por usuario y el orden se encadena con Shift+clic en los encabezados. Los botones PDF
y Excel descargan el listado tal como está filtrado y ordenado.

## Permisos

- **Ver**: abrir el listado y el detalle, exportar el CSV del Ministerio y el PDF o Excel del listado.
- **Crear**: calcular y recalcular.
- **Modificar**: editar la grilla y contabilizar.
- **Eliminar**: eliminar (anular) un cálculo con el botón Eliminar del modal.
- **Acceso total**: ve los cálculos de toda la empresa; sin él, solo los que creó
  el propio usuario.

## Reglas de negocio

- **Quién participa**: todo trabajador o ex trabajador con al menos un día
  laborado dentro del ejercicio. Los ex trabajadores se muestran en gris.
- **10% por tiempo**: monto × días del trabajador ÷ suma de días de todos.
- **5% por cargas**: monto × (días × cargas del trabajador) ÷ suma de (días ×
  cargas) de todos. Si **ningún** trabajador tiene cargas, el 5% se reparte por
  días, igual que el 10%, y el resumen lo avisa.
- **Redondeo**: cada valor va a centavos y la suma cierra exacta con el monto
  repartido; la diferencia de redondeo (centavos) se carga al trabajador con
  más días.
- **Tope**: ningún trabajador recibe más de 24 salarios básicos del ejercicio.
  Lo que sobra queda como *excedente* y no entra en Egresos → Nómina: se paga al
  IESS con un egreso aparte.
- **Un cálculo por ejercicio**: recalcular reemplaza el detalle conservando lo
  editado a mano (nombres, cargas, tipo de pago, discapacidad, retención).
- **Con pagos registrados no se recalcula, no se cambian las cargas ni se
  anula**: el reparto es interdependiente y moverlo dejaría a otros trabajadores
  mal pagados. Anule primero los egresos.
- **Plazo**: la fecha límite de pago que muestra el módulo es el 15 de abril del
  año siguiente al ejercicio.

## Integraciones con otros módulos

- **Egresos → Nómina**: cada trabajador aparece con su valor pendiente de
  utilidades (tipo de documento *Utilidades*). Ese egreso debita directamente la
  cuenta *Participación Trabajadores por Pagar*.
- **Contabilidad**: el botón **Contabilizar** genera el asiento con fecha 31 de
  diciembre del ejercicio: *Gasto Participación Trabajadores* contra
  *Participación Trabajadores por Pagar*, por el monto repartido completo (lo
  que cobran los trabajadores más el excedente del IESS). Si ya existía, se
  regenera sobre el mismo asiento. El período contable de diciembre debe estar
  abierto. Al anular el cálculo se anula también su asiento.
- **Anexo RDEP**: las utilidades pagadas en el año alimentan el campo
  *Participación de utilidades* de cada trabajador en el anexo (en desarrollo).
- **Empleados**: nombres, sexo, código de ocupación, discapacidad y cargas
  familiares salen de la ficha en el momento del cálculo.

## Errores frecuentes

- **"Indique el monto a repartir"**: escribió la utilidad líquida en cero y no
  puso monto. Ingrese la utilidad líquida (el sistema propone el 15%) o el monto
  directamente.
- **"No se puede recalcular: ya hay pagos registrados"**: anule primero los
  egresos de esa corrida.
- **"Configure las cuentas de nómina en Configuración Contable"**: faltan las
  cuentas Gasto Participación Trabajadores o Participación Trabajadores por
  Pagar en la sección Nómina.
- **"La fecha corresponde a un período contable cerrado"**: diciembre del
  ejercicio está cerrado; reábralo o pida al contador que registre el asiento.
- **Un trabajador no aparece**: no tiene período de empleo dentro del
  ejercicio en su ficha (pestaña de períodos), o está eliminado.
- **El archivo del Ministerio es rechazado por las columnas**: el formato de
  carga del Sistema de Salarios en Línea puede cambiar de un año a otro.
  Compare el encabezado del CSV con la plantilla vigente del Ministerio y avise
  a soporte para ajustar el orden de las columnas.

## Historial de cambios

- **1.1** — Listado con el diseño estándar (buscador con filtros, columnas por
  usuario, orden múltiple, PDF y Excel); botón **Nuevo**; el módulo avisa si falta
  ejecutar su SQL en la base.
- **1.0** — Versión inicial: cálculo 10% / 5%, tope de 24 SBU, archivo CSV para
  el Ministerio, pago por Egresos → Nómina y asiento del 31 de diciembre.
