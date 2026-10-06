---
titulo: Inmuebles del condominio
resumen: Inmuebles de un condominio (departamentos, locales, parqueaderos, bodegas) con su propietario, pagador, alícuota, historial y restricción de áreas comunes.
categoria: Ventas
ruta_modulo: modulos/condominios
tipo: modulo
visibilidad: todos
etiquetas: condominio, condominios, edificio, conjunto, urbanizacion, expensas, alicuota, alicuotas, cuota de mantenimiento, propiedad horizontal, inmuebles, departamento, parqueadero, bodega, local, propietario, arrendatario, inquilino, condomino, fondo de reserva, intereses de mora, multas, areas comunes, administrador, cargar inmuebles excel
version: 1.1
orden: 0
estado: activo
---

**Condominios** administra las expensas de un edificio, conjunto o urbanización. Cada
condominio es una empresa del sistema; este módulo guarda lo que ningún otro tiene: las
**unidades** (los inmuebles), quién es su **propietario** y quién **paga**, cómo se calcula su
**alícuota** y las reglas del condominio (fondo de reserva, intereses, multas, restricción de
áreas comunes). La emisión mensual la hace **Suscripciones**, el cobro **Ingresos**, la cartera
**Cuentas por Cobrar** y el estado de cuenta **Reporte de Cartera**, como siempre.

## Qué es y para qué sirve

- Tener el **listado de unidades** con su área, % de alícuota, propietario, arrendatario y
  pagador, tal como está en la escritura de propiedad horizontal.
- Saber **a nombre de quién** sale cada recibo o factura (propietario o arrendatario) sin perder
  nunca al propietario, que es el responsable legal de las expensas.
- Guardar el **historial de propietarios**: la deuda es del inmueble, y al venderse el inmueble el
  estado de cuenta sigue siendo el mismo.
- Definir una sola vez las reglas del condominio: método de alícuota, fondo de reserva,
  intereses y multas. El recibo o factura de cada inmueble se emite desde su **suscripción**.
- Marcar la **restricción de uso de áreas comunes** a un inmueble en mora y sacar el listado para
  administración y guardianía.

Esta es la **fase 1** del módulo (configuración e inmuebles). La emisión mensual automática de
las expensas, los otros aportes con meta, los intereses y la liquidación para cobro judicial
se habilitan en las fases siguientes y usan lo que aquí se registra.

## Requisitos previos

- La empresa debe ser el condominio (RUC, establecimiento y serie propios).
- Los conceptos que se cobran (p. ej. «Alícuotas», IVA 0 %) son **servicios de Productos** que se
  eligen en cada suscripción; el módulo **no crea productos**. El IVA y la cuenta contable salen del
  producto.
- Los condóminos (propietarios y arrendatarios) son **clientes**. Si no existen, la carga por
  Excel los crea.
- Haber guardado la **Configuración de condominios** (submódulo propio del menú Condominios): sin ella
  el módulo no está activo y no se pueden registrar inmuebles.

## Cómo se usa

1. Primero, en **Configuración de condominios**, indique el administrador, el producto para la
   alícuota y las demás reglas (ver su manual). Al guardar, el módulo queda **activo**.
2. **Nuevo inmueble** o **Cargar Excel** para registrar los inmuebles. Cada inmueble —incluidos
   parqueaderos y bodegas— emite su propio recibo o factura.
3. En cada inmueble: propietario (obligatorio), arrendatario (opcional) y **quién paga**; área y %
   de alícuota; método propio si difiere del condominio (p. ej. un local con monto manual).
4. **Restringidas** (junto a PDF y Excel): PDF con los inmuebles que tienen restringido el uso
   de áreas comunes. **Cargar Excel** está en el mismo grupo de botones.

### Pestañas del modal de inmueble

| Pestaña | Qué tiene |
|---|---|
| General | Datos del inmueble, propietario / arrendatario / pagador, área, alícuota, método, monto manual, fondo propio, estado. Al cambiar las personas aparece **Rige desde**. |
| Propietarios | Historial de propietario, arrendatario y pagador con desde / hasta. |
| Áreas comunes | Marcar o levantar la restricción (fecha de notificación y motivo obligatorios) y su bitácora. |
| Expensa | La suscripción que emite la expensa del inmueble (ahí se definen recibo o factura, periodicidad, día de cobro y serie al generar). También se puede asociar desde la propia suscripción: en Suscripciones, el campo **Inmueble** lista los que paga el cliente y, si paga uno solo, se asocia solo. Al emitir, el inmueble y su propietario salen en la Información adicional del documento. Si el pagador ya tiene una suscripción sin inmueble (por ejemplo un programado migrado del sistema anterior), se puede **enlazar** aquí. |

Las pestañas se pueden ocultar por usuario desde el engranaje junto a ellas.

## Campos del formulario

La configuración del condominio (administrador, productos,
método, fondo, intereses, multas, descuentos) se documenta en **Configuración de condominios**.

| Campo | Obligatorio | Qué significa |
|---|---|---|
| Código | Sí | Único en el condominio (DPTO-302, P-12). Se puede reutilizar el de un inmueble eliminado. |
| Tipo | Sí | Departamento, local, oficina, parqueadero, bodega, casa, otro. |
| Área m² / Alícuota % | Según método | Obligatorios según el método efectivo (m² o %). |
| Propietario | Sí | Cliente. Siempre se guarda. |
| Arrendatario / Paga el | No | Si paga el arrendatario, el documento sale a su nombre. |
| Rige desde | Al cambiar personas | Fecha desde la que rige el nuevo propietario, arrendatario o pagador. |
| Método / Monto manual | No | Vacío = el del condominio. Con método manual el monto es obligatorio. |
| Fondo de reserva propio | No | Valor propio del inmueble; vacío = regla del condominio. |
| Estado | Sí | Inactivo = no emite. |

**Cuota**: la columna *Cuota* del listado y la «cuota ordinaria estimada» de la ficha se calculan con
el **valor que rige hoy** (Configuración de condominios → Valores que rigen): manual = su monto;
por m² = tarifa × área; por % = monto a repartir × % (o reparto del resto si la base es un
presupuesto). Sin valor vigente aparece «—».

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de búsqueda y los
botones de columnas, PDF y Excel.

**Búsqueda libre.** Busca en código, nombre, torre, piso y en el nombre e identificación del
propietario y del arrendatario, por palabras sueltas y sin distinguir tildes. **Tipo, método,
estado y restricción** no entran en la búsqueda libre: use la ventana de filtros.

| Bloque | Filtros |
|---|---|
| Inmueble | Código, nombre, tipo, torre / bloque |
| Personas | Propietario, quién paga, con / sin arrendatario |
| Cobro | Método, comprobante, área (rango), alícuota (rango) |
| Estado | Activo / inactivo, con / sin restricción, usuario que registró |

Sintaxis directa: `tipo:parqueadero`, `pagador:arrendatario`, `metodo:manual`,
`restringida:si`, `area:50..100`, `-estado:inactivo`. **PDF** y **Excel** exportan con la misma
búsqueda, filtros y orden. El pie del listado muestra inmuebles activos, suma de alícuotas y de m².

## Ordenar el listado

Clic en un título ordena por esa columna; con **Shift+clic** se encadenan hasta tres (p. ej.
*Torre* y luego Shift+clic en *Código*). El orden se guarda para usted.

## Cargar inmuebles desde Excel

**Cargar Excel → Descargar plantilla** entrega un archivo con una fila por inmueble y filas de
ejemplo (bórrelas). Columnas: `CODIGO`, `NOMBRE`, `TIPO`, `TORRE_BLOQUE`, `PISO`, `AREA_M2`,
`ALICUOTA_PCT`, `PROPIETARIO_IDENTIFICACION`, `PROPIETARIO_NOMBRE`, `PROPIETARIO_EMAIL`,
`PROPIETARIO_TELEFONO`, `ARRENDATARIO_IDENTIFICACION`, `ARRENDATARIO_NOMBRE`, `PAGADOR`,
`COMPROBANTE`, `METODO`, `MONTO_MANUAL`, `FONDO_RESERVA_PROPIO`, `OBSERVACIONES`.

- El propietario (y el arrendatario) se cruza con **Clientes por cédula/RUC**; si no existe se
  crea con el nombre, correo y teléfono del archivo.
- Si el código ya existe, el inmueble se **actualiza**; si cambian las personas, se abre una fila
  nueva en el historial.
- **Vista previa** antes de grabar: acción por fila (crear / actualizar), clientes nuevos, suma
  de alícuotas y errores por fila (esas filas no se cargan). Nada se graba hasta **Aplicar**.

## Permisos

| Permiso | Qué permite |
|---|---|
| Ver | Consultar inmuebles, PDF y Excel. |
| Crear | Crear inmuebles y cargar Excel. |
| Actualizar | Editar inmuebles, marcar / levantar restricción, enlazar suscripciones. |
| Eliminar | Eliminar inmuebles sin expensas emitidas. |

La configuración del condominio y el catálogo de multas tienen sus propios permisos en el
submódulo **Configuración de condominios**.

Los registros propios no aplican a los inmuebles: la cartera del condominio es una sola y todos
los usuarios con permiso ven todos los inmuebles.

## Reglas de negocio

- El módulo se activa al guardar la configuración; mientras no exista, no se pueden crear
  inmuebles.
- Los productos elegidos deben ser **servicios** de la empresa; si falta uno, el listado avisa
  qué falta para poder emitir.
- Código único entre los inmuebles no eliminadas. Un inmueble con expensas emitidas **no se
  elimina**: se marca inactiva.
- Cada cambio de propietario, arrendatario o pagador cierra la fila vigente del historial y abre
  otra desde la fecha indicada.
- Para que pague el arrendatario primero debe registrarse uno. El pagador define el cliente del
  documento; el propietario siempre queda guardado.
- Solo se enlazan suscripciones **del pagador** y que no pertenezcan a otro inmueble; al eliminar
  el inmueble, la suscripción queda libre.
- Restringir áreas comunes exige la **fecha de notificación** y el **motivo**. Todo queda en la
  bitácora del inmueble y en el log del sistema.
- Todas las acciones (inmuebles, historial, restricción, enlaces, Excel) quedan en
  `log_sistema`.

## Integraciones con otros módulos

- **Productos**: los conceptos se facturan con servicios creados allí.
- **Clientes**: propietarios y arrendatarios.
- **Suscripciones**: emite la expensa mensual de cada inmueble (la suscripción queda marcada con
  su inmueble). Un programado migrado se puede enlazar desde la pestaña *Expensa*.
- **Presupuestos**: un presupuesto aprobado marcado como *base de alícuotas* alimenta el monto a
  repartir del método por % (fase siguiente).
- **Ingresos / Cuentas por Cobrar / Reporte de Cartera**: cobro y cartera, como siempre.

## Errores frecuentes

- **«El módulo Condominios aún no está instalado»**: falta aplicar en la base de datos
  `database/migrations/20261005_condominios.sql`.
- **«Elija el producto (servicio) con el que se factura…»**: cree el servicio en Productos y
  elíjalo con el buscador; un producto tipo *bien* no sirve.
- **«Con método por % el inmueble necesita su % de alícuota»**: indique el % de la escritura o
  cambie el método de esa inmueble a m² o manual.
- **«El inmueble ya tiene expensas emitidas: no se puede eliminar»**: márquela como inactiva.
- **«La suscripción es de otro cliente»**: solo se enlazan suscripciones del pagador del inmueble.
- **El Excel dice que el propietario no existe**: agregue `PROPIETARIO_NOMBRE` para que se cree
  el cliente, o regístrelo antes en Clientes.

## Historial de cambios

- **1.1** — La cuota del listado y de la ficha se calcula con el valor que rige (tarifa por m² o monto
  a repartir de la configuración); sin campos de comprobante/serie/vencimiento propios (viven en la
  suscripción); asociación con la suscripción desde Suscripciones.
- **1.0** — Fase 1: inmuebles con historial de propietarios,
  restricción de áreas comunes, catálogo de multas, enlace de suscripciones, carga por Excel,
  listado con filtros, orden múltiple, PDF y Excel.
