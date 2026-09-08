---
titulo: Anexo de Dividendos (ADI)
resumen: Prepara y genera el archivo anual ADI-aaaa.xml con las utilidades y los dividendos distribuidos a socios y accionistas, para cargarlo en SRI en Línea.
categoria: Impuestos
ruta_modulo: modulos/anexo-dividendos
tipo: modulo
visibilidad: todos
etiquetas: anexo dividendos, ADI, dividendos, utilidades, accionistas, socios, participes, reparto de utilidades, retencion dividendos, impuesto unico dividendos, articulo 39.2, anexo anual SRI, ADI-2025.zip, dimm anexos
version: 1.2
orden: 31
estado: activo
---

El Anexo de Dividendos (ADI) es la declaración informativa anual con la que una
sociedad le cuenta al SRI cuánta utilidad generó, cuánta repartió y a quién. El
módulo arma ese archivo a partir de los asientos contables del año y lo entrega
listo para subirlo al portal, sin tener que retipear nada en el DIMM.

## Qué es y para qué sirve

Presentan el ADI las sociedades residentes o establecidas en Ecuador (incluidas
las de economía mixta y las organizaciones de la economía popular y solidaria)
por las utilidades generadas en el período o pendientes de distribución, y por
los dividendos que repartieron durante el año.

El módulo cubre las secciones **A** (informante), **B** (información de
utilidades) y **C** (dividendos distribuidos y sus beneficiarios) del anexo, con
el esquema vigente **desde el período fiscal 2020**. Las secciones D (préstamos a
accionistas), E (dividendos anticipados) y F (dividendos recibidos del exterior)
todavía no se generan.

Produce dos archivos: `ADI-aaaa.xml` y su `ADI-aaaa.zip`, que es el que se carga
en **SRI en Línea → Anexos → Envío y consulta de anexos → Anexo de Dividendos**.

## Requisitos previos

- **Contabilidad del año cerrada y contabilizada.** El módulo solo lee asientos
  en estado *contabilizado*; los borradores no cuentan.
- **Cuentas contables identificadas.** Debe existir en el plan una cuenta donde
  se registre la distribución de dividendos (normalmente *Dividendos por pagar*)
  y otra de *resultados acumulados*. El módulo las propone solo si están
  mapeadas al casillero SuperCías correspondiente (2010706 y 3060x) o si su
  nombre las delata.
- **Accionistas registrados como terceros.** Cada línea del asiento de
  distribución debe tener asignado el cliente, proveedor o empleado que recibe el
  dividendo. De ahí salen la identificación y el nombre del beneficiario; las
  líneas sin tercero se reportan aparte para completarlas a mano.
- **Salario básico del año registrado** en la tabla de salarios (la misma que
  usa la nómina). De ahí sale la franja exenta de tres SBU de las personas
  naturales residentes.

## Cómo se usa

1. Pulse **Nuevo**. Se abre el anexo en blanco con el RUC y la razón social de la
   empresa activa ya cargados.
2. En la pestaña **Informante y origen**, elija el **año informado**: es el
   primer campo, igual que en la ficha del SRI. Los datos del informante y el
   salario básico se rellenan solos —los primeros desde la empresa activa, el
   segundo desde la tabla de salarios del año elegido—. Pulse **Guardar**: ahí se
   crea el anexo y el módulo propone las cuentas contables que reconoce. Marque
   las de dividendos y las de resultados acumulados, y guarde otra vez.
3. Pulse **Importar de contabilidad**. El módulo lee los asientos del año en esas
   cuentas, crea un beneficiario por cada tercero y un dividendo por cada línea.
   Al terminar muestra qué movimientos quedaron sin beneficiario asignado.
4. En la pestaña **Dividendos**, corrija lo que haga falta: el año que generó la
   utilidad (por defecto el anterior), el tipo de dividendo, si está pagado y el
   ISD. Agregue a mano los que no salieron de la contabilidad.
5. Pulse **Recalcular** para que el sistema proponga el ingreso gravado, la
   retención y cuadre la sección B con el detalle.
6. Revise la pestaña **Validaciones**. Los errores impiden generar; las
   advertencias conviene revisarlas pero no bloquean.
7. Pulse **Generar anexo** y descargue el **ZIP** para subirlo al portal.

El listado admite las mismas herramientas que el resto de módulos: buscador con
filtros (año, informante, identificación, estado y tipo de informante), orden por
cualquier columna —incluidos los totales—, paginación y exportación a **PDF** y
**Excel** con los filtros aplicados. Las columnas visibles se configuran con el
botón de columnas y quedan guardadas para cada usuario, igual que el orden.

## Campos del formulario

### Informante (sección A)

El anexo se presenta siempre a nombre de la **empresa activa**, así que ningún
dato del informante se captura: identificación, tipo de identificación, tipo de
informante y razón social se toman de ella y solo se muestran. El tipo de
informante sale del **tipo de contribuyente** configurado en la empresa
(*Persona natural* y *Persona natural obligada a llevar contabilidad* →
informante 02; *Sociedad*, *Contribuyente especial* y *Sector público* →
informante 01) y, si ese dato falta, del propio RUC. Para corregir cualquiera de
ellos se editan los datos de la empresa, no el anexo.

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Año informado | Sí | Período que se declara, desde 2010 y nunca posterior al año en curso. Se elige al crear el anexo y después queda bloqueado: es parte de su identidad y solo puede haber un anexo por año. Para otro período, cree uno nuevo. |
| Informante | Derivado | Identificación, tipo de identificación y tipo de informante de la empresa activa. Si a la empresa le falta el RUC o el tipo de contribuyente, la pantalla lo advierte. |
| Razón social | Derivado | Nombre de la empresa activa. Para cambiarlo, edítelo en la configuración de la empresa. |
| Salario básico del año | Derivado | Se toma de la tabla de salarios según el año elegido y cambia con él. La franja exenta es tres veces ese valor por cada persona natural residente. Si el año no está en la tabla, la pantalla lo advierte. |

### Información de utilidades (sección B)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| 1. Utilidad del ejercicio informado | Sí | Se toma del estado de resultados del año. |
| 2. Utilidad distribuida del ejercicio | Condicional | Lo repartido que corresponde a la utilidad del mismo año informado. Se cuadra con el detalle al recalcular. |
| 3 y 4. Utilidad reinvertida | Condicional | Con o sin derecho a la reducción del artículo 37 de la LRTI. Se capturan a mano. |
| 5. Utilidad distribuida por anticipado | Opcional | Dividendos anticipados y préstamos a accionistas (secciones D y E, aún no generadas). |
| 6. Utilidad no distribuida | Derivado | Utilidad del ejercicio menos lo distribuido y lo reinvertido. No se edita. |
| 7. Utilidad de ejercicios anteriores pendiente | Sí | Saldo acreedor de las cuentas de resultados acumulados al 31 de diciembre del año anterior. |
| 8. Utilidad distribuida de ejercicios anteriores | Condicional | Suma de los dividendos cuyo año de generación es anterior al período informado. |

### Beneficiario del dividendo (sección C.1)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo y número de identificación | Sí | RUC, cédula, pasaporte o identificación tributaria del exterior. No puede repetirse dentro del mismo anexo. |
| Tipo de beneficiario | Sí | De la tabla 2 del SRI. La lista se filtra según el tipo de identificación. |
| País de residencia | Sí | Se fija en Ecuador (593) para los beneficiarios locales; para los del exterior no se admite Ecuador ni el código 999. |
| ¿Gravado en el estado de residencia? | Condicional | Solo para beneficiarios no residentes (tipos 02, 07, 08, 09, 11 y 12). |
| Beneficiario efectivo | Condicional | Solo para los tipos 09 y 11, con RUC, cédula o pasaporte. Si hay varios, se registra uno por cada beneficiario efectivo. |

### Dividendo distribuido (sección C.2)

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Año que generó la utilidad | Sí | No puede ser posterior al período informado. |
| Tipo de dividendo | Sí | De la tabla 9. La lista se filtra por el tipo de beneficiario y por el año, porque muchos códigos ya no están vigentes. |
| Fecha de registro contable | Sí | Fecha del acta o del asiento de distribución. Debe caer dentro del año informado. |
| Monto distribuido | Sí | Valor bruto del dividendo, antes de la retención. |
| Ingreso gravado | Condicional | Cero en los dividendos exentos. En los gravados lo calcula el sistema (ver reglas). |
| Monto de la retención | Condicional | No puede superar el ingreso gravado. |
| ¿Está pagado? | Sí | Habilita el campo de ISD. |
| ISD pagado | Condicional | Solo si el dividendo está pagado, y nunca mayor al monto distribuido. |

## Permisos

- **Ver**: consultar los anexos y descargar los archivos ya generados.
- **Crear**: abrir el período y registrar beneficiarios y dividendos.
- **Actualizar**: editar el anexo, importar de contabilidad y recalcular.
- **Eliminar**: borrar el anexo completo, un beneficiario (con sus dividendos) o
  un dividendo suelto.
- **Acceso total**: sin él, el usuario solo ve los anexos que él mismo creó. Con
  acceso total ve los de toda la empresa. El superadministrador ve todo siempre.

## Reglas de negocio

**Qué se importa de la contabilidad.** Solo las líneas de asientos
*contabilizados* del año, en las cuentas marcadas, y solo por el lado
configurado: el **haber** en cuentas de pasivo y el **debe** en cuentas de
patrimonio. Así el pago posterior del dividendo, que va por el lado contrario, no
se cuenta como una segunda distribución. Reimportar reemplaza únicamente lo
importado antes: lo capturado o corregido a mano se conserva.

**Ingreso gravado.** En los dividendos exentos es siempre 0,00. En los gravados:

- Del período 2020 hasta agosto de 2025: el **40 %** del monto distribuido.
- Desde septiembre de 2025, con la Ley Orgánica de Transparencia Social: el
  **monto distribuido completo**, descontando la franja exenta de **tres salarios
  básicos** que corresponde a cada persona natural residente por cada sociedad y
  período fiscal. La franja se consume una sola vez al año por beneficiario, en
  orden de fecha.

**Retención.** Desde septiembre de 2025 se aplica el impuesto único del artículo
39.2 de la LRTI: **12 %** al beneficiario residente, **10 %** al no residente y
**14 %** cuando en la cadena de propiedad hay un paraíso fiscal con beneficiario
efectivo residente en Ecuador o cuando no se informó la composición societaria.
Hasta agosto de 2025 se usa la tabla progresiva de la Resolución
NAC-DGERCGC20-00000013 para personas naturales residentes (sobre el acumulado del
año) y una tarifa fija para el resto.

> Tanto el ingreso gravado como la retención son **sugerencias**. Dependen de
> datos que el sistema no conoce —composición societaria, convenios para evitar
> la doble imposición, residencia efectiva del beneficiario— y de tablas que el
> SRI actualiza por resolución. Revíselos antes de presentar; ambos campos son
> editables.

**Cuadres que exige el SRI.** La utilidad no distribuida es siempre la utilidad
del ejercicio menos lo distribuido y lo reinvertido; si el resultado sale
negativo, el anexo no se puede generar. La utilidad distribuida de ejercicios
anteriores no puede superar la que estaba pendiente al inicio del período. Si hay
utilidad distribuida del ejercicio, debe existir al menos un dividendo con ese
año de generación.

**Compatibilidades de catálogo.** El tipo de identificación limita los tipos de
beneficiario; el tipo de beneficiario limita el país y los tipos de dividendo; y
cada tipo de dividendo tiene años de vigencia. El módulo aplica esas reglas al
guardar, así que no se puede registrar una combinación que el portal vaya a
rechazar.

**Eliminación lógica.** Nada se borra físicamente. Al eliminar un anexo se
marcan también sus beneficiarios y dividendos, y todo queda registrado en el log
del sistema.

## Integraciones con otros módulos

- **Contabilidad**: es la fuente de los datos. La utilidad del ejercicio sale del
  estado de resultados del año y los dividendos, de los asientos en las cuentas
  configuradas. El módulo **solo lee**; nunca genera ni modifica asientos.
- **Plan de cuentas**: el mapeo a los casilleros de SuperCías (2010706
  *Dividendos por pagar* y 3060x *Resultados acumulados*) es lo que permite
  proponer las cuentas automáticamente.
- **Clientes, proveedores y empleados**: de ahí salen la identificación y el
  nombre de cada beneficiario, tanto al importar como al buscarlos en el modal.

## Errores frecuentes

- **«Seleccione al menos una cuenta contable de dividendos»**: no se marcó
  ninguna cuenta en la pestaña *Informante y origen*. Si la lista aparece vacía,
  el plan de cuentas no tiene ninguna cuenta con «dividendo» en el nombre ni
  mapeada al casillero 2010706 de SuperCías.
- **«Ese tipo de informante no reporta utilidades ni dividendos distribuidos»**:
  la empresa está registrada como *Persona natural* o *Sucesión indivisa*, y para
  ellas el anexo se limita a los dividendos recibidos del exterior (sección F,
  aún no generada). Si en realidad es una sociedad, corrija el **tipo de
  contribuyente** en la configuración de la empresa.
- **«Falta el RUC o el tipo de contribuyente en la configuración de la
  empresa»**: el informante sale de esos datos, así que hay que completarlos
  antes de presentar el anexo.
- **«El año no tiene salario básico registrado»**: falta ese año en la tabla de
  salarios. Sin el SBU no se puede descontar la franja exenta de las personas
  naturales residentes, y el ingreso gravado saldría por el monto completo.
- **«N movimientos no tienen un cliente, proveedor o empleado asignado»**: el
  asiento de distribución no identifica al accionista en la línea. Corrija el
  asiento y vuelva a importar, o registre esos dividendos a mano.
- **«El tipo de dividendo X no está vigente para el período»**: se eligió un
  código derogado. Muchos códigos (02, 05, 06, 08, 12, 18, 19) dejaron de existir
  después de 2019.
- **«El país 593 no es válido para el tipo de beneficiario»**: un beneficiario
  del exterior no puede tener Ecuador como país de residencia. Revise si el tipo
  de beneficiario es el correcto.
- **«Ya existe un beneficiario con la identificación …»**: el SRI usa la
  identificación como parte de la clave del registro. Si la misma persona recibió
  varios dividendos, agréguelos como líneas del mismo beneficiario, no como
  beneficiarios repetidos.
- **El portal rechaza el archivo por esquema**: descargue el esquema oficial
  desde SRI en Línea y déjelo en `storage/anexos/dividendos/ADI.xsd`. Si está
  presente, el módulo valida el XML contra él al generarlo y muestra los errores.

## Historial de cambios

- **1.2** — El año informado se elige dentro del anexo, como primer campo de la
  pestaña *Informante y origen*, siguiendo el orden de la ficha del SRI. Dejan de
  capturarse los datos del informante (salen de la empresa activa y de su tipo de
  contribuyente) y el salario básico, que se toma de la tabla de salarios según
  el año elegido.
- **1.1** — El listado adopta el estándar del sistema: buscador con filtros,
  ordenamiento por columna, paginación y exportación a PDF y Excel.
- **1.0** — Versión inicial. Secciones A, B y C del anexo con el esquema 2020 en
  adelante, importación desde los asientos contables, cálculo sugerido del
  ingreso gravado y la retención (régimen del 40 % e impuesto único del artículo
  39.2 LRTI), validaciones de la ficha técnica y generación de `ADI-aaaa.xml` con
  su ZIP.
