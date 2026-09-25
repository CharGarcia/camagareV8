---
titulo: Retenciones de venta
resumen: Retenciones que los clientes le practican a la empresa y que se registran para descontarlas del cobro.
categoria: Ventas
ruta_modulo: modulos/retenciones_ventas
tipo: modulo
visibilidad: todos
etiquetas: retencion de venta, retenciones recibidas, cliente retiene, credito tributario, periodo fiscal, cobro, buscar retencion, buscador, filtros, filtrar retenciones, documento sustento, codigo de retencion, filtro de fechas, chips, aparecen documentos que no busque, resultados que no corresponden, la busqueda trae otros documentos
version: 1.9
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
total IVA, total ISD y total retenido. Puede escribir varias palabras en
cualquier orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto
o pulse Escape en el cuadro. Mientras busca, aparece un **círculo girando** al
final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo retenciones donde se vea por qué coinciden, estos datos se
consultan en la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| Clave de acceso | Pestaña *Retención* |
| Usuario que registró | Pestaña *Retención* |
| Documentos sustento y códigos de retención de las líneas | Pestaña *Detalles* |
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

**Pestaña Detalles** (lo que hay dentro de la retención). Es un único cuadro,
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

## Qué pasa al eliminar una retención

Eliminar una retención **anula también su asiento contable**, en el mismo paso.
Antes el asiento quedaba vivo y seguía sumando en el Balance aunque la retención
ya no existiera.

El asiento no se borra: queda en estado **anulado**, así que el rastro se conserva
y deja de afectar los reportes.

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
