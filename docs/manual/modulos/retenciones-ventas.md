---
titulo: Retenciones de venta
resumen: Retenciones que los clientes le practican a la empresa y que se registran para descontarlas del cobro.
categoria: Ventas
ruta_modulo: modulos/retenciones_ventas
tipo: modulo
visibilidad: todos
etiquetas: retencion de venta, eliminar retencion, retencion anulada en el sri, emitida por error, no volver a descargar, claves a no registrar, documentos para no descargar, otro establecimiento, retenciones recibidas, cliente retiene, credito tributario, periodo fiscal, cobro, buscar retencion, buscador, filtros, filtrar retenciones, documento sustento, codigo de retencion, filtro de fechas, chips, aparecen documentos que no busque, resultados que no corresponden, la busqueda trae otros documentos, imprimir, impresora, reasignar establecimiento, cambiar sucursal de la retencion, retencion en otro establecimiento, mismo ruc varios establecimientos
version: 1.20
orden: 40
estado: activo
---

Cuando un cliente que es agente de retención paga una factura, no entrega el
total: retiene una parte y entrega un **comprobante de retención**. Este módulo
registra esos comprobantes recibidos.

Es el espejo de las retenciones de compra: aquí la empresa es quien sufre la
retención, no quien la practica.

## Por qué hay que registrarlas

Por dos motivos, y ambos importan:

- **Para cobrar bien**: la factura se considera cobrada con el dinero recibido
  *más* la retención. Si no se registra, la factura queda con un saldo pendiente
  que nunca se va a cobrar.
- **Para la declaración**: la retención es crédito tributario de la empresa.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **cliente** que practicó la retención.
3. Complete **establecimiento**, **punto de emisión** y **secuencial** del
   comprobante que le entregaron.
4. Indique el **período fiscal** en formato **MM/YYYY** (por ejemplo `07/2026`).
5. Registre los valores retenidos de IVA y de renta. Al buscar el código de
   retención puede escribir cualquier dato del catálogo —código, concepto,
   porcentaje (`1.75`, `1,75` o `1.75 %`), impuesto o código del anexo—: se busca
   en todas esas columnas a la vez y admite varias palabras en cualquier orden.
   Solo se ofrecen los códigos vigentes a la fecha de emisión; la vigencia se
   revisa en **Configuración → Retenciones SRI**.
6. Guarde.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Cliente | Obligatorio |
| Fecha de emisión | Obligatoria |
| Establecimiento | Obligatorio |
| Punto de emisión | Obligatorio |
| Secuencial | Obligatorio |
| Período fiscal | Obligatorio, con formato `MM/YYYY` |

## Relación con el cobro

Al registrar el ingreso que cobra esa factura, la retención se descuenta del
saldo pendiente. Por eso conviene registrarla **antes** de dar por cobrada la
factura: así los números cuadran solos.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel. Los botones PDF y Excel
exportan las retenciones que coinciden con la búsqueda y el orden aplicados.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la retención: N°
retención, secuencial, fecha, cliente, identificación, período, total renta,
total IVA, total ISD, total retenido y el número de los documentos sustento de
sus líneas. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada.
El **número del documento sustento** se encuentra con o sin guiones:
`001-001-000000123`, `001001000000123` o solo una parte, como `123`.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo retenciones donde se vea por qué coinciden, estos datos se
consultan en la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso | Pestaña *Retención* |
| Usuario que registró | Pestaña *Retención* |
| Códigos de retención de las líneas | Pestaña *Búsqueda por detalle* |
| Origen | Pestaña *Retención* |

La clave de acceso son 49 dígitos que llevan dentro la fecha, el RUC y el número
del documento. Al escribir un número de documento en el cuadro aparecían
retenciones ajenas cuya clave contenía por casualidad esa secuencia: con las 29
retenciones de la base de pruebas, un número de seis dígitos devolvía **17
retenciones** que no lo tenían en ninguna columna. Ahora ese número solo
encuentra la retención que realmente lo tiene.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Retención** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), origen (manual o electrónico), serie, N° retención, secuencial, período fiscal, con o sin asiento contable, clave de acceso, usuario que registró |
| Documento sustento | N° del documento sustento, fecha de ese documento y código de retención (basta con que una línea coincida) |
| Valores | Total retenido, total renta, total IVA y total ISD (cada uno con mínimo y máximo) |
| Cliente | Cliente, RUC / cédula |

El selector *Usuario que registró* lista solo a quienes ya registraron
retenciones en la empresa, y *Serie* solo las series con retenciones guardadas.

**Pestaña Búsqueda por detalle** (lo que hay dentro de la retención). Es un único cuadro,
**Buscar libremente dentro de las retenciones**: escriba el número de una
factura sustento, un código de retención, un impuesto, una base, un porcentaje
o un valor retenido, y aparece la lista de **cada línea que coincide** con la
retención a la que pertenece (número, fecha, cliente y origen). Un clic en la
fila deja el listado mostrando solo esa retención; el ícono de la derecha la
abre directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Exportar el comprobante

En el modal de una retención ya guardada, junto al botón **PDF** hay un botón
**Excel** que descarga el mismo comprobante en formato `.xlsx` (archivo
`Retencion_Venta_001-001-000000123.xlsx`): datos del cliente y del período
fiscal, el detalle de las líneas retenidas (documento sustento, código,
concepto, base imponible, porcentaje y valor) y los totales de renta, IVA, ISD
y total retenido. Ambos botones solo aparecen cuando la retención ya se guardó.

## Reasignar la retención a otro establecimiento del mismo RUC

Cuando el RUC tiene **más de un establecimiento** (en esta empresa o en otras
empresas registradas con el mismo RUC), al abrir una retención guardada aparece:

- Un **aviso azul** bajo la barra de botones que dice en qué establecimiento está
  la retención, cuántos establecimientos tiene el RUC y que las **reasignaciones
  masivas** se hacen en **Documentos › Reasignar establecimientos**.
- El botón **Reasignar establecimiento** (ícono de tienda), junto al botón Excel.

Al presionarlo se elige el **establecimiento destino** (agrupado por empresa si
el RUC tiene varias) y se confirma. El número de la retención no cambia; solo a
qué establecimiento queda atribuida. Si el destino es de **otra empresa** del
mismo RUC, la retención pasa a esa empresa y deja de verse en el listado de esta.

- Si la retención tiene **asiento contable**, el sistema lo avisa y pide marcar
  *Sí, anular y reasignar*: el asiento se anula y hay que volver a generarlo en
  el destino.
- No se puede mover a otra empresa que **ya tenga una retención con el mismo
  número**.
- Funciona también con las retenciones electrónicas.
- Requiere permiso de **modificar** en Retenciones de venta y en **Documentos ›
  Reasignar establecimientos**. Sin ese permiso solo se ve el aviso.

Para mover muchas retenciones a la vez use el módulo
[Reasignar establecimiento](reasignar-establecimiento).

## Qué pasa al eliminar una retención

Eliminar una retención **anula también su asiento contable**, en el mismo paso.
Antes el asiento quedaba vivo y seguía sumando en el Balance aunque la retención
ya no existiera.

El asiento no se borra: queda en estado **anulado**, así que el rastro se conserva
y deja de afectar los reportes.

### Retención electrónica: que no se vuelva a descargar

Si la retención es **electrónica** (tiene clave de acceso de 49 dígitos), al pulsar
**Eliminar** el sistema avisa que la próxima descarga del SRI, manual o
automática, la volvería a registrar, y pregunta **por qué la elimina**:

| Opción | Cuándo usarla | Qué pasa |
|---|---|---|
| **Fue anulada en el SRI o la emitieron por error** | El cliente anuló la retención en el SRI o se la emitió a la empresa por equivocación. | Se agrega a *Claves a no registrar* para **todos los establecimientos** de la empresa (mismo RUC). |
| **La voy a registrar en otro establecimiento** | Entró en un establecimiento y corresponde a otro de la misma empresa. | Se agrega a *Claves a no registrar* **solo en este establecimiento**. El otro sí puede descargarla o cargar su XML. |
| **Solo eliminar** | Quiere borrarla para volver a descargarla. | No se agrega a la lista. |

Puede escribir una **observación** (opcional), que se guarda en la lista junto con
el número de la retención.

Agregar a la lista y eliminar la retención se hacen juntos: si la eliminación no
se puede hacer (por ejemplo, por un **período contable cerrado**, ver abajo), la
retención tampoco se agrega. Si luego necesita volver a descargarla, quítela de
la pestaña *Claves a no registrar* de [Descargas del SRI](descargas-sri).

## Períodos contables cerrados

Una retención mueve la cartera de la factura y genera asiento, así que su fecha no
puede caer en un período cerrado. Se comprueba al **registrarla**, al
**modificarla** —tanto la fecha nueva como aquella con la que está registrada— y
al **eliminarla**.

Los períodos se abren y se cierran en **Contabilidad → Períodos Contables**;
reabrir el período permite la operación de inmediato.

Las retenciones que llegan **descargadas del SRI** son la excepción: son
documentos que el cliente ya emitió y se registran tal cual, aunque su mes esté
cerrado.

## Errores frecuentes

- **La retención no genera asiento: "el código de retención … no existe en el catálogo
  del SRI"**: el código guardado en la retención no está en el catálogo de retenciones
  (por ejemplo, 344, que es el casillero del formulario; los códigos vigentes son 3440,
  344A o 344B). A un código así no se le puede asignar cuenta: corrija el código en la
  retención y el asiento se genera en la siguiente pasada.
- **La retención no genera asiento: "el código de retención … no tiene cuenta contable"**:
  asígnele la cuenta en Configuración Contable → Retenciones en Venta.
- **"El período fiscal debe tener el formato MM/YYYY"**: escríbalo con mes y año,
  por ejemplo `07/2026`.
- **La factura queda con saldo pendiente que nadie va a pagar**: falta registrar
  la retención que le practicó el cliente.
- **"No se puede ... porque su período contable está cerrado"**: la fecha de la
  retención cae en un mes ya cerrado. Reabra el período en Contabilidad →
  Períodos Contables si realmente necesita hacer el cambio.
- **Una retención con valor cero no tiene asiento**: es lo esperado, no hay nada
  que contabilizar. Tampoco aparece en el aviso de asientos pendientes.

## Historial de cambios

- **1.20** — Ventana de la retención: si el RUC tiene varios establecimientos, un **aviso** lo indica (y remite a *Documentos › Reasignar establecimientos* para las reasignaciones masivas) y el botón **Reasignar establecimiento** mueve la retención abierta a otro establecimiento del mismo RUC. Ver *Reasignar la retención a otro establecimiento del mismo RUC*. Los botones de la barra superior (nuevo cliente, PDF, XML, Excel) pasan a ser solo ícono, del mismo tamaño y estilo que en Compras y Facturas de Venta; al dejar el puntero encima se ve su nombre.
- **1.19** — Al guardar una retención sobre una factura que ya estaba cobrada o tiene nota de crédito, si la factura queda cobrada de más aparece el aviso **Saldo a favor del cliente** con el valor. Es informativo: la retención se guarda igual. Las retenciones que llegan por Descargas SRI no muestran el aviso; la factura queda marcada *Saldo a favor* en su listado. Ver *Saldo a favor del cliente* en [Facturas de Venta](factura-venta.md).
- **1.18** — La pestaña de Descargas SRI donde quedan las retenciones eliminadas (antes *Documentos para no descargar*) ahora se llama **Claves a no registrar**; los avisos al eliminar usan el nombre nuevo.
- **1.17** — Al **eliminar una retención electrónica** se pregunta el motivo: *anulada en el SRI o emitida por error* (no se vuelve a descargar en ningún establecimiento), *la voy a registrar en otro establecimiento* (no se vuelve a descargar solo en este) o *solo eliminar*. Las dos primeras la agregan a *Documentos para no descargar* de Descargas SRI. Si el período contable de la retención está cerrado, la eliminación se rechaza y no se agrega nada a la lista. Requiere `database/2026-11-15_documentos_ignorados_sri_alcance.sql`. Ver *Retención electrónica: que no se vuelva a descargar*.
- **1.16** — Nueva pestaña **Información** en la ventana de la retención (solo niveles 2 y 3, solo lectura): quién la creó y modificó, con fecha y hora, y sus acciones registradas. Ver la guía *Pestaña Información*.
- **1.15** — El botón **Registrar nuevo cliente** no hacía nada: ahora abre la ficha del cliente y, al guardarlo, queda seleccionado en la retención.
- **1.14** — La búsqueda libre encuentra la retención por el **número del documento
  sustento** (con o sin guiones, o solo una parte). Antes solo se buscaba desde la
  ventana de filtros.
- **1.13** — En la ventana de filtros, la pestaña *Detalles* se llama ahora
  **Búsqueda por detalle**, y la ventana ya no tiene barra de desplazamiento
  vertical propia: se muestra completa.
- **1.12** — En el número de la retención y en el documento sustento se puede escribir solo el secuencial (`1311`): al salir del campo se completa como `001-001-000001311`. Antes se partía por posición y quedaba como `131-001-000000000`.
- **1.11** — Cuando un código de retención no tiene cuenta o no existe en el catálogo
  del SRI, el aviso de asientos pendientes lo dice así, en lugar de «error inesperado».
- **1.10** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.9** — El concepto de cada línea de renta se toma por su **código ATS**, el que trae el
  comprobante (antes podía mostrar otro, p. ej. 323I en lugar de 323); si el código no
  existe como ATS se busca por el otro código del catálogo. Al registrar una
  retención a mano, el selector del catálogo pone en renta el código ATS (el mismo que
  trae el comprobante electrónico). Una línea cuyo código aparece
  más de una vez en el catálogo ya no se muestra duplicada. El asiento usa la misma regla.

- **1.8** — Corregido: las retenciones con **valor retenido cero** aparecían en el
  aviso de asientos pendientes de generar, aunque no tienen nada que
  contabilizar. Ya no se cuentan como pendientes.

- **1.7** — Corregido: al buscar un **número de documento** en el cuadro aparecían
  también documentos que no lo tenían. La búsqueda libre miraba dentro de la **clave de
  acceso** (49 dígitos, que llevan la fecha, el RUC y el número del documento) y cualquier
  número corto caía ahí por casualidad. Ahora la clave se consulta en la ventana de
  filtros,
  junto con el **usuario que registró**; los **documentos sustento** y **códigos de
  retención** de las líneas pasan a la pestaña *Detalles*, que sí muestra qué línea
  coincidió. Con las 29 retenciones de la base de pruebas, un número de seis dígitos
  devolvía 17 retenciones que no lo tenían en ninguna columna; ahora, ninguna.

- **1.6** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en todas las columnas (incluido el total retenido,
  cada palabra por separado y sin importar tildes) y en la clave de acceso, el
  usuario y los documentos sustento y códigos de las líneas, salvo Origen. Los
  filtros pasan a una **ventana propia** (botón del embudo, se aplican con
  *Aplicar*) con dos pestañas: **Retención** (filtros por campo, con criterios
  nuevos: con/sin asiento, usuario como lista, documento sustento, fecha del
  documento sustento y código de retención) y **Detalles** (búsqueda dentro de
  las líneas retenidas). Corregido el filtro de origen: la opción
  *Automáticas* no encontraba nada porque el valor real es *Electrónico*. Tras
  buscar, los botones PDF y Excel exportan ahora lo filtrado (antes seguían con
  la búsqueda con la que se abrió la pantalla) y las columnas ocultas se
  mantienen ocultas.
- **1.5** — El modal de la retención se abre **siempre en la pestaña *General***, tanto al
  registrar una nueva como al cargar una existente. Antes, si el usuario había dejado
  activa *Asiento contable* al cerrar, la siguiente retención se abría en esa pestaña.
- **1.4** — El control del **cierre contable** ya no depende del asiento: se
  comprueba al registrar y al modificar la retención, no solo al eliminarla, y el
  aviso dice qué operación se rechazó en vez de hablar del asiento. Las retenciones
  descargadas del SRI quedan exentas: son documentos que el cliente ya emitió.
- **1.3** — El buscador del código de retención cubre todas las columnas del
  catálogo del SRI (código, concepto, porcentaje, impuesto y código del anexo).
- **1.2** — Eliminar una retención ahora **anula su asiento contable**. Antes el
  asiento sobrevivía a la retención y seguía afectando el Balance. Efecto
  secundario esperado: ya no se puede eliminar una retención cuya fecha esté en un
  período contable cerrado.
- **1.1** — Nuevo botón **Excel** junto al de PDF en el modal: descarga el
  comprobante de la retención en `.xlsx` con el mismo detalle y totales.
- **1.0** — Versión inicial.
