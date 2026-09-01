---
titulo: Compras
resumen: Registro de las facturas de compra, carga desde el XML del SRI y entrada de la mercadería al inventario.
categoria: Compras
ruta_modulo: modulos/compras
tipo: modulo
visibilidad: todos
etiquetas: compras, compra, factura de compra, proveedor, xml, sri, entrada de mercaderia, vincular producto, retencion, orden de compra, vincular orden, pedido a proveedor, comparar pedido vs facturado, entrega parcial, recibido parcial, cerrar orden, sustento tributario, codigo de sustento, autorizacion, fecha de caducidad, ats, persona natural, obligada a llevar contabilidad, tipo de contribuyente, registro manual, compra fisica
version: 2.2
orden: 20
estado: activo
---

El módulo de **Compras** registra las facturas que recibe la empresa. De aquí
salen las cuentas por pagar, las retenciones de compra, la entrada de mercadería
al inventario y el gasto contable.

## Dos formas de registrar una compra

- **Desde el XML del SRI**: la forma recomendada. El comprobante llega ya
  descargado del SRI y el sistema lee el XML y arma la compra completa.
- **Manual**: se captura a mano, para comprobantes que no son electrónicos.

Al cargar desde XML, el sistema valida que el archivo sea un comprobante del SRI
con formato válido. Si el comprobante no trae XML o el archivo está dañado, lo
rechaza con un mensaje explícito.

Ninguna compra "nace" de una orden de compra — la orden es un pedido interno
previo (ver [Órdenes de Compra](../modulos/ordenes-compra.md)); cuando llega la
factura electrónica, se **vincula** con la orden desde la compra ya cargada
(ver más abajo).

## Tipos de comprobante del registro manual

El selector **Tipo de Comprobante** del modal ofrece únicamente los documentos
que se capturan a mano:

| Código | Comprobante |
|---|---|
| 02 | Nota o boleta de venta |
| 08 | Boletos o entradas a espectáculos públicos |
| 11 | Pasajes expedidos por empresas de aviación |
| 12 | Documentos emitidos por instituciones financieras |
| 15 | Comprobante de venta emitido en el Exterior |
| 16 | FUE / DAU / DAV |
| 19 | Comprobantes de Pago de Cuotas o Aportes |
| 20 | Documentos por Servicios Administrativos (Inst. del Estado) |
| 21 | Carta de Porte Aéreo |

Las **facturas (01)**, liquidaciones de compra (03) y notas de crédito/débito
(04 / 05) entran por su vía propia — la carga del **XML del SRI** — y ya no se
capturan a mano desde este selector. Una compra ya registrada con uno de esos
códigos **conserva su tipo**: al abrirla, el modal agrega su opción y la
muestra normalmente.

## Sustento tributario y datos de autorización

En el registro **manual** el modal pide, además del comprobante, el **sustento
tributario** (Tabla 5 del SRI), el **N° de autorización**, el rango **Desde /
Hasta** y la **fecha de caducidad**. Que esos campos aparezcan o no depende del
**Tipo de Contribuyente** configurado en la empresa (Configuración → Empresa):

| Tipo de contribuyente | Sustento y datos de autorización |
|---|---|
| Persona Natural (no obligada a llevar contabilidad) | **No aparecen.** El sistema los completa solo, porque este contribuyente no presenta ATS |
| Persona Natural Obligada a llevar contabilidad | **Aparecen y son obligatorios** |
| Sociedad | **Aparecen y son obligatorios** |
| Contribuyente especial | **Aparecen y son obligatorios** |
| Sector público | **Aparecen y son obligatorios** |

El sustento tributario se filtra por el **tipo de comprobante** elegido: primero
seleccione el comprobante y recién ahí se cargan los sustentos válidos para ese
tipo.

En una **factura de reembolso recibida** el sustento queda fijo en *08 - Valor
pagado para solicitar Reembolso de Gasto (intermediario)* y no se puede cambiar.

## No se puede repetir un comprobante

El sistema impide registrar **dos veces el mismo número de comprobante para el
mismo proveedor**. Si aparece ese aviso, la compra ya está en el sistema: búsquela
en el listado antes de volver a capturarla.

## Vincular los productos (paso clave)

Las compras que llegan del SRI traen los **códigos del proveedor**, que casi nunca
coinciden con los de su catálogo. Por eso, antes de que la mercadería entre al
inventario hay que **vincular cada línea con un producto suyo**.

Si intenta procesar la entrada sin vincular, el sistema avisa:

> El ítem '…' debe estar vinculado a un producto del catálogo.

La vinculación se guarda: la próxima compra de ese proveedor con el mismo código
se relaciona sola. Es un trabajo que se hace una vez por producto y proveedor.

## Vincular con la orden de compra

Si esta factura corresponde a un pedido que se hizo antes por
[Órdenes de Compra](../modulos/ordenes-compra.md), la pestaña **Orden de
Compra** del modal permite enlazarla:

1. Busque y elija la orden ya **Aprobada** o **Recibida parcial** (el
   proveedor la aprobó desde el correo, o alguien la aprobó manualmente —
   Borrador o Enviado no bastan), del mismo proveedor, y pulse **Vincular**.
   Una orden puede tener **varias compras vinculadas** (entregas parciales
   del proveedor): según cuánto cubra esta factura del total pedido, la
   orden queda en **Recibido parcial** (falta saldo) o **Recibido**
   (se completó).
2. La pestaña muestra una comparación por producto: cantidad y precio
   **pedidos** vs. **facturados** — este último es el acumulado de *todas*
   las compras vinculadas a la orden, no solo la que se está viendo, con la
   lista de esas compras justo arriba de la tabla. Cada línea se marca como
   *OK*, *Diferencia*, *Pendiente* (pedido y aún no facturado en ninguna
   compra) o *No pedido* (facturado sin estar en la orden). El
   emparejamiento usa el producto del catálogo de cada línea — en la compra,
   si la línea no tiene un producto vinculado directamente, se resuelve con
   la misma homologación código-proveedor → producto de la sección anterior.
3. **Cerrar orden**: si la orden queda en Recibido parcial y el proveedor ya
   no va a entregar el saldo, este botón la fuerza a Recibido sin más
   compras.
4. **Desvincular esta compra** deshace solo ese enlace y recalcula el
   estado de la orden con las compras que le queden vinculadas.

Es solo informativo: no bloquea guardar la compra ni afecta inventario o
cuentas por pagar.

## Entrada al inventario

Una compra registrada **no mueve el stock por sí sola**. La mercadería entra
cuando se procesan las entradas, indicando la bodega de destino. Ese paso:

- Suma el stock del producto en esa bodega.
- Genera el movimiento en el kardex con su costo.

Solo entran los productos **inventariables** y vinculados al catálogo.

**Lote, NUP y caducidad son opcionales aquí.** Aunque la empresa tenga activados
los interruptores *Obligatorio usar Lotes*, *Obligatorio usar Fecha de
Caducidad* u *Obligatorio usar NUP* (en Empresa → configuración de facturación),
esas reglas aplican a la **facturación** (la salida de la mercadería), no al
ingreso desde una compra. El documento del proveedor muchas veces no trae ese
dato, así que la entrada al inventario no lo exige: llénelos solo si los conoce
y los necesita para la trazabilidad.

## Retenciones

Desde la compra se genera la retención al proveedor, con los porcentajes que
tenga configurados en su ficha. La retención es un documento aparte que también
se envía al SRI.

**Importante**: una compra con retención asociada **no se puede eliminar**. Hay
que eliminar primero la retención. El sistema lo avisa con ese mismo mensaje.

## Qué pasa al eliminar una compra

Eliminar una compra **anula también su asiento contable**, en el mismo paso. Antes
el asiento quedaba vivo: la compra desaparecía del listado pero seguía sumando en
el Balance y en Cuentas por Pagar, y si el mismo documento del proveedor se volvía
a registrar, quedaba contabilizado dos veces.

El asiento no se borra: queda en estado **anulado**, así que el rastro de lo que
existió se conserva y deja de afectar los reportes.

Por eso, si la fecha de la compra cae en un **período contable cerrado**, la
eliminación se rechaza — no se puede tocar la contabilidad de un período cerrado.
Reabra el período si realmente necesita eliminarla.

Si en su empresa quedaron asientos huérfanos de antes de este cambio, la
**Auditoría Contable** los detecta como *huérfano* y los anula, de uno en uno o en
lote (ver [Auditoría Contable](auditoria-contable)).

## Notas de crédito y débito de compra

Las notas de crédito y débito que emite el proveedor se registran también en este
módulo y quedan vinculadas al documento que modifican. En Cuentas por Pagar no
aparecen como documentos sueltos: se restan del saldo de la factura a la que
corresponden.

## Planillas de luz y agua: valores de terceros

Las facturas de servicios básicos cobran, además de su propio importe, rubros que
la empresa recauda **para terceros**: contribución al Cuerpo de Bomberos, tasa de
recolección de basura y similares. Esos valores **no forman parte del importe de
la factura** que se declara al SRI ni de las bases de IVA — el emisor los publica
como campos sueltos de la información adicional del comprobante.

Al cargar una de estas facturas desde **Descargas SRI**, el sistema los reconoce
solo y los totaliza aparte. En la compra se ven así:

| Línea | Ejemplo |
|---|---|
| TOTAL | 66.59 — el importe de la factura, el que se declara |
| (+) Valores de terceros | 2.41 — bomberos y tasa de basura |
| **TOTAL A PAGAR** | **69.00** — lo que se transfiere al proveedor |

Ese **total a pagar** es el que usan **Cuentas por Pagar** y el registro de
**egresos**, así que la planilla se salda por su valor real y no queda un
descuadre de centavos cada mes. El **subtotal, el IVA y el total** que se
declaran al SRI (y que alimentan el ATS y la declaración de IVA) no cambian: los
valores de terceros nunca se suman ahí.

El detalle de cada rubro queda en la pestaña **Info Adicional** de la compra, tal
como lo envió el proveedor, y se imprime en el PDF.

El reconocimiento es automático y no depende de la distribuidora: se detectan los
campos cuyo nombre menciona *bomberos*, *basura*, *recolección* o *terceros* y
cuyo valor es un número. En una compra registrada a mano se consigue lo mismo
agregando el rubro en la pestaña Info Adicional con uno de esos nombres.

## Documentos del módulo

Desde la compra guardada se puede generar el **PDF** del documento, exportarlo a
**Excel** y consultar su **XML**. Los botones están en la barra de acciones al
inicio del formulario (solo visibles si la compra es un comprobante electrónico
con XML guardado).

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
compras que coinciden con el buscador y el orden aplicados en ese momento (no
solo la página visible).

## Permisos

Con **acceso total** se ven las compras de toda la empresa; sin él, cada usuario
ve solo las que registró.

## Errores frecuentes

- **"Ya existe una compra registrada con ese número de comprobante para este
  proveedor"**: está duplicando. Búsquela en el listado.
- **"El ítem debe estar vinculado a un producto del catálogo"**: falta vincular
  esa línea antes de procesar la entrada al inventario.
- **"No se puede eliminar la compra porque tiene una retención asociada"**:
  elimine primero la retención.
- **"El comprobante no tiene XML"** o **"El XML no tiene un formato válido del
  SRI"**: el archivo no es un comprobante electrónico válido; regístrela a mano.
- **El stock no subió tras registrar la compra**: registrar no es lo mismo que
  procesar la entrada. Compruebe además que el producto sea inventariable.
- **"La compra está pendiente de aprobación"** al pagar o al procesar el
  inventario: la empresa exige aprobar las compras. Un aprobador debe autorizarla
  primero (por el correo o desde el listado).
- **"No puede aprobar una compra que usted mismo registró"**: la autorización
  tiene que darla otra persona. Es intencional.
- **La compra no generó asiento contable**: si está pendiente de aprobación, el
  asiento se genera al aprobarla, no al registrarla.
- **"No se puede registrar el asiento: la fecha ... corresponde a un período
  contable cerrado"** al eliminar: la eliminación anula el asiento, y eso no se
  puede hacer en un período cerrado. Reabra el período.

## Aprobación de compras

Si la empresa lo configura en el módulo **Aprobaciones**, una compra registrada
a mano queda **pendiente de aprobación** en lugar de quedar registrada de una
vez. Mientras esté pendiente:

- **no se puede pagar** (no se le registra el egreso),
- **no se puede procesar su inventario** (no mueve stock),
- **no se genera su asiento contable**.

Los aprobadores reciben un correo con un enlace para aprobar o rechazar sin
iniciar sesión, y también pueden hacerlo abriendo la compra desde el listado
(botones *Aprobar* y *Rechazar* arriba del modal). Al aprobarla pasa a
**Registrado** y recién entonces se genera su asiento y se habilitan el pago y
el inventario. Si la rechazan, la compra **no se elimina**: queda como
*Rechazada* con el motivo, para que haya rastro de que el documento llegó y se
decidió no aceptarlo.

Quien registra la compra **no puede aprobarla** (salvo un superadministrador):
la autorización tiene que venir de otra persona. En el buscador, el filtro
rápido *Pend. aprobación* deja el listado solo con las que esperan decisión, y
el título del módulo muestra cuántas hay.

Dos cosas que conviene tener claras:

- La aprobación aplica **a toda compra nueva**, tanto la que se captura a mano
  como la que entra por la **descarga del SRI**. Cuando se registra un lote de
  comprobantes, los aprobadores reciben **un solo correo** con la lista de todas
  las que quedaron pendientes, no uno por documento. Además, una compra del SRI
  que quede pendiente **no genera su pago automático**: el egreso se registra
  cuando se apruebe.
- Quedan fuera los **documentos históricos**: las compras que vienen de una
  migración o de una importación de datos antiguos entran como registradas. Son
  operaciones que ya ocurrieron; ponerlas a esperar aprobación las dejaría sin
  asiento y sin poder pagarse.
- Si en Aprobaciones se configuró un **monto mínimo**, las compras por debajo de
  ese valor se registran directamente, sin pedir autorización.

## Historial de cambios

- **2.2** — El selector **Tipo de Comprobante** del modal se acotó a los
  documentos que se capturan a mano: **02, 08, 11, 12, 15, 16, 19, 20 y 21**.
  Los demás códigos del catálogo (factura 01, liquidación 03, notas 04/05,
  retenciones, guías, RECAP, etc.) ya no se ofrecen para captura manual; las
  compras ya registradas con esos códigos conservan su tipo al abrirlas. Nueva
  sección *Tipos de comprobante del registro manual*.
- **2.1** — Corregido el registro **manual** en empresas **Persona Natural
  Obligada a llevar contabilidad**: el sistema pedía el sustento tributario
  pero mantenía el campo oculto, así que la compra no se podía guardar. Ahora
  la única exenta de sustento y datos de autorización es la **Persona Natural
  no obligada a llevar contabilidad** (la única que no presenta ATS); los
  demás tipos de contribuyente ven esos campos y son obligatorios. Nueva
  sección *Sustento tributario y datos de autorización*.
- **2.0** — Las facturas de **servicios básicos** (luz, agua) que descargan del
  SRI ahora reconocen los **valores recaudados para terceros** (contribución
  bomberos, tasa de recolección de basura) que viajan en la información
  adicional del comprobante. Se totalizan aparte del importe declarado y se
  suman al saldo por pagar y al egreso, de modo que la planilla se cancele por
  su valor real. Nueva sección *Planillas de luz y agua: valores de terceros*.
- **1.9** — El modal ya no arrastra datos de la compra abierta anteriormente:
  al registrar una compra nueva (o al abrir otra) se limpian el **asiento
  contable**, el aviso y los botones de **aprobación** (pendiente / rechazada)
  y el botón de **emitir retención**, que quedaba habilitado sin compra
  guardada. Antes había que recargar la pantalla.
- **1.8** — Nueva **aprobación de compras**: si se activa en el módulo
  Aprobaciones, la compra registrada a mano queda pendiente y no se puede pagar,
  ni procesar su inventario, ni se genera su asiento hasta autorizarla. Se añade
  el filtro por **Estado** en el buscador. Nota: la columna de estado ya existía
  pero el listado mostraba siempre "Registrado"; ahora refleja el estado real.
- **1.7** — Eliminar una compra ahora **anula su asiento contable**. Antes el
  asiento sobrevivía a la compra y seguía sumando en el Balance y en Cuentas por
  Pagar (y duplicaba el gasto si el documento se volvía a registrar). Efecto
  secundario esperado: ya no se puede eliminar una compra cuya fecha esté en un
  período contable cerrado.
- **1.6** — La entrada al inventario desde una compra ya **no exige** lote, fecha
  de caducidad ni NUP, aunque esos campos estén marcados como obligatorios en la
  configuración de la empresa (esa configuración sigue aplicando a la
  facturación).
- **1.5** — Entregas parciales: una orden de compra puede vincularse con
  varias compras (una orden en Recibido parcial también aparece para
  vincular); nuevo botón "Cerrar orden" para cerrar manualmente una
  Recibido parcial. La comparación pedido-vs-facturado ahora es el
  acumulado de todas las compras vinculadas, no solo la actual.
- **1.4** — Vincular con una orden de compra ahora requiere que esté en
  estado **Aprobada** (antes bastaba Borrador o Aprobado), acorde al nuevo
  flujo de aprobación de Órdenes de Compra.
- **1.3** — Pestaña "Orden de Compra" en el modal: vincula la compra con la
  orden de compra del proveedor que la originó y compara cantidades/precios
  pedidos vs. facturados.
- **1.2** — Nuevo botón Excel en el documento de la compra (junto a PDF y XML).
- **1.1** — Corregidos los botones Excel y PDF del listado: no descargaban nada.
- **1.0** — Versión inicial.
