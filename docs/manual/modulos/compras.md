---
titulo: Compras
resumen: Registro de las facturas de compra, carga desde el XML del SRI y entrada de la mercadería al inventario.
categoria: Compras
ruta_modulo: modulos/compras
tipo: modulo
visibilidad: todos
etiquetas: compras, compra, factura de compra, buscar compra, buscador, aparecen compras que no busque, resultados que no corresponden, la busqueda trae otras compras, buscar por numero de autorizacion, filtros, filtrar compras, buscar por producto comprado, filtro de fechas, saldo pendiente, estado de pago, chips, ordenar por dos columnas, ordenar por proveedor y fecha, asiento contable, editar asiento, pestaña asiento, proveedor, xml, sri, entrada de mercaderia, vincular producto, retencion, orden de compra, vincular orden, pedido a proveedor, comparar pedido vs facturado, entrega parcial, recibido parcial, cerrar orden, sustento tributario, codigo de sustento, autorizacion, fecha de caducidad, ats, persona natural, obligada a llevar contabilidad, tipo de contribuyente, registro manual, compra fisica, pagar la compra, pestaña pagos, saldo pendiente, valores de terceros, otros conceptos, valores adicionales, bomberos, tasa de basura, recoleccion de basura, planilla de luz, planilla de agua, servicios basicos, informacion adicional, info adicional, nombre muy largo, limite de caracteres, value too long, no se pudo guardar la compra, imprimir, impresora
version: 2.20
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

**Corregir el sustento en una compra migrada ELECTRÓNICA.** Una compra
migrada **electrónica** (respaldada por un XML del SRI) es de solo lectura
(no se puede editar nada más, ver más abajo), pero el selector de **Sustento
Tributario** queda habilitado con su propio botón de guardado (✓) junto al
campo, porque las compras migradas llegan sin esta clasificación bien resuelta
y el ATS/Declaración de IVA la necesitan correcta. Guardarlo no abre el resto
del documento a edición, y sigue respetando las mismas reglas: no se puede
tocar si el período contable de la compra está cerrado, y en una factura de
reembolso recibida se sigue forzando al código 08.

**Compras migradas y edición.** Una compra que viene de una migración es de
**solo lectura** si es **electrónica** — no tiene otra forma de editarse salvo
el Sustento Tributario de arriba. Una compra migrada **física**, en cambio,
**sí se puede editar por completo**: a diferencia de una electrónica, no tiene
un XML autorizado que la respalde como fuente de verdad, así que el usuario
puede corregir datos que la migración trajo mal (proveedor, fechas, montos,
detalle, etc.). En ambos casos, si el **período contable** de la compra está
cerrado, sigue siendo de solo lectura sin excepción. Guardar cambios en una
migrada física **no regenera su asiento contable**: conserva el histórico
migrado tal cual, para no duplicar la contabilidad.

## Pestaña ATS

La pestaña **ATS** del modal reúne los datos que no cambian la compra en sí,
pero sí cómo se reporta en el anexo transaccional. Tiene dos tarjetas:

**Parte relacionada.** Se marca cuando la transacción es con una parte
relacionada. Se activa sola al elegir un proveedor que ya está registrado como
tal en su ficha.

**Pago al exterior.** El anexo pide, en **cada** compra, si el pago se hizo
dentro o fuera del país:

| Campo | Cuándo se llena |
|---|---|
| Tipo de pago | Siempre. Arranca en *pago local* |
| País donde se efectuó el pago | Solo si es pago al exterior |
| Aplica convenio de doble tributación | Solo si es pago al exterior |
| Sujeto a retención según norma legal | Solo si es pago al exterior |

Si elige **pago al exterior**, los tres campos siguientes son obligatorios: sin
ellos el SRI rechaza el anexo. Con pago local quedan deshabilitados y el anexo
los reporta como "NA". Un aviso en el nombre de la pestaña señala lo que falta.

El sistema propone *pago al exterior* cuando elige un **comprobante emitido en
el Exterior (15)** o un proveedor con **pasaporte o identificación del
exterior** — pero la decisión final es suya, puede cambiarla.

Las compras registradas antes de que existiera esta pestaña quedaron como
**pago local**, que es lo que el anexo venía reportando. Si alguna era un pago
al exterior, ábrala y corríjala: el anexo avisa cuando encuentra un comprobante
del exterior declarado como pago local.

Como el resto de pestañas del modal, la pestaña ATS se puede ocultar por
usuario desde el ícono de configuración de pestañas.

## Formas de pago SRI (no confundir con Pagos)

Son dos cosas distintas dentro del mismo modal:

- **Formas de pago SRI** — sub-pestaña junto a *Info Adicional*. Es un **dato
  tributario del comprobante**: con qué medio se pactó pagar (efectivo,
  transferencia, tarjeta…) y a qué plazo. Es lo que viaja en el bloque `<pagos>`
  del comprobante electrónico y en el ATS. **No mueve dinero.**
- **Pagos** — pestaña propia, con Saldo Pendiente. Aquí se registra el **egreso
  real** que abona la deuda al proveedor y baja la cuenta por pagar.

El SRI exige que las formas de pago sumen exactamente el total del comprobante.
Esa validación se aplica **solo en el registro manual**, que es donde usted
captura los montos:

| Tipo de registro | Se valida el cuadre |
|---|---|
| Manual (físico) | **Sí.** No se guarda hasta que las formas de pago sumen el total |
| Electrónico (XML del SRI) | **No.** Se guarda tal cual lo declaró el emisor |
| Migrado | **No.** Se conserva lo que traía el sistema anterior |

Si aparece *"Las formas de pago SRI no coinciden con el total de la compra"*
—mensaje del botón **Guardar**, no del registro de un pago— abra la sub-pestaña
*Formas de pago SRI* y cuadre los montos. Con **una sola** forma de pago el
sistema la rellena sola con el total; en cuanto agrega una segunda, los montos
quedan a su cargo.

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

Una vez vinculada, se ve el **código del producto**, para confirmar de un vistazo
que se eligió el producto correcto:

- En la pestaña **Detalle**, en la columna **Código** (antes de la descripción),
  en azul. Mientras la línea no esté vinculada, esa columna muestra en gris el
  código del proveedor que trae el comprobante. La descripción no cambia: es la
  del comprobante y se guarda tal cual.
- En la pestaña **Inventario**, delante del nombre del producto (p. ej.
  *GUA-001 - Guantes de nitrilo*).

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

### Compras por cajas u otra presentación

El stock de un producto se lleva siempre en **su** unidad (la de su ficha, p. ej.
UNIDAD). Si el proveedor factura por cajas, en la tabla de envío a inventario
elija en **Medida** la unidad de la caja (p. ej. **CAJA X100**, creada en
[Unidades de medida](modulos/unidades-medida) con factor 100) y deje la cantidad y
el costo **tal como vienen en la factura**:

| Factura del proveedor | Medida elegida | Entra al inventario |
|---|---|---|
| 10 cajas a $15,00 | CAJA X100 | **1.000 unidades a $0,15** |

- Bajo la cantidad aparece en azul lo que realmente entrará, p. ej.
  *= 1000 UNIDAD a 0.1500*. El **total no cambia** ($150,00).
- El kardex anota la conversión en la observación: *… (10 CAJA X100 = 1000)*.
- Solo se ofrecen medidas del mismo tipo que el producto. Con la medida del
  propio producto no se convierte nada (como siempre).
- El aviso de **saldo por enviar** y la marca **Enviado** se calculan con la
  medida elegida en la fila. Si envió una parte por cajas y vuelve a abrir la
  compra, elija de nuevo la medida de la caja para ver el saldo correcto.

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

Ese **total a pagar** es el que usan **todos** los caminos por los que se paga la
planilla: la pestaña **Pagos** de la propia compra, **Cuentas por Pagar**, el
módulo de **Egresos** y el **pago automático** configurado en la ficha del
proveedor. Así la planilla se salda por su valor real y no queda un descuadre de
centavos cada mes. El **subtotal, el IVA y el total** que se declaran al SRI (y
que alimentan el ATS y la declaración de IVA) no cambian: los valores de terceros
nunca se suman ahí.

En la pestaña **Pagos** de la compra, cuando la factura trae estos rubros aparece
la tarjeta **Valores de Terceros** junto a las de retenciones y notas de crédito,
y el **saldo pendiente** ya los incluye. Si la tarjeta no aparece, esa factura no
trae valores de terceros.

El detalle de cada rubro queda en la pestaña **Info Adicional** de la compra, tal
como lo envió el proveedor, y se imprime en el PDF. El **nombre** de cada campo
admite hasta **255 caracteres** (el valor no tiene tope); si un XML trae un
nombre más largo, se recorta a 255 en vez de rechazar la compra.

El reconocimiento es automático y no depende de la distribuidora: se detectan los
campos cuyo nombre menciona *bomberos*, *basura*, *recolección* o *terceros* y
cuyo valor es un número. En una compra registrada a mano se consigue lo mismo
agregando el rubro en la pestaña Info Adicional con uno de esos nombres.

## Documentos del módulo

Desde la compra guardada se puede generar el **PDF** del documento, exportarlo a
**Excel** y consultar su **XML**. Los botones están en la barra de acciones al
inicio del formulario (solo visibles si la compra es un comprobante electrónico
con XML guardado).

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en el N° de comprobante, el secuencial, la
fecha, el proveedor, el subtotal, el IVA, el total y el **saldo**; además, en las
observaciones y en el documento modificado de las notas de crédito. Puede
escribir varias palabras en cualquier orden y no importan mayúsculas ni tildes.
Para limpiar, borre el texto o pulse Escape en el cuadro. Mientras busca,
aparece un **círculo girando** al final del cuadro y la tabla se ve atenuada.

**Lo que NO entra en la búsqueda libre, y dónde buscarlo.** Para que el cuadro
devuelva solo compras donde se vea por qué coinciden, estos datos se consultan
en la ventana de filtros (botón del embudo):

| Dato | Dónde se busca |
|------|----------------|
| N° de autorización | Pestaña *Compra* → **N° autorización** |
| RUC / cédula del proveedor | Pestaña *Compra* → **RUC / cédula** |
| Usuario que registró | Pestaña *Compra* → **Usuario que registró** |
| Productos comprados (código y descripción) | Pestaña *Detalles* |
| Tipo, Sustento, Pago, Estado | Pestaña *Compra* |

El número de autorización de un comprobante electrónico son 49 dígitos que
llevan dentro la fecha, el RUC y el número del documento, así que al escribir un
número de factura en el cuadro aparecían compras ajenas cuya autorización
contenía por casualidad esa secuencia. Ahora ese número solo encuentra la compra
que realmente lo tiene.

**Montos y fechas.** Las fechas y los montos solo se comparan cuando lo escrito
tiene números. El **saldo** se busca cuando se escribe un monto con decimales
(`34.78` o `34,78`, con punto o con coma): calcularlo es lo más costoso de la
búsqueda y así escribir un nombre o un producto responde mucho más rápido. Para
buscar compras con un saldo aproximado, use el rango *Saldo pendiente* de la
ventana de filtros.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Compra** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha de emisión (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), fecha de registro, estado, estado de pago (pendiente / abonada / pagada), tipo de comprobante, tipo de registro (electrónico, física o migrado), serie del proveedor, N° comprobante, secuencial, N° autorización, sustento tributario, deducible (declaración de IVA o gasto personal), documento modificado, con o sin asiento contable, con o sin orden de compra, con o sin retención, parte relacionada |
| Valores | Total, subtotal, IVA, descuento, saldo pendiente y valor retenido (cada uno con mínimo y máximo) |
| Proveedor | Proveedor, RUC / cédula, usuario que registró, observaciones |

Los selectores *Tipo de comprobante* y *Sustento tributario* listan solo lo que
la empresa ya usó. El *estado de pago* y el *saldo pendiente* se calculan con la
misma regla que las columnas Pago y Saldo: pagos de Egresos, notas de crédito y
retenciones; las notas de crédito cuentan siempre como pagadas.

**Pestaña Detalles** (lo que hay dentro de la compra). Es un único cuadro,
**Buscar libremente dentro de las compras**: escriba un producto, un código,
una forma de pago SRI, un plazo, un dato de la información adicional o un
comprobante de reembolso de terceros, y aparece la lista de **cada línea que
coincide** con la compra a la que pertenece (número, fecha, proveedor y estado).
Un clic en la fila deja el listado mostrando solo esa compra; el ícono de la
derecha la abre directamente. Por ejemplo, *extra* lista todas las compras
donde aparece ese producto.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último. El aviso *N pendientes de aprobación* del título
también aplica su filtro como etiqueta, que se puede quitar igual.

## Ordenar el listado

Pulse el título de una columna para ordenar por ella y vuelva a pulsarlo para
invertir el sentido. De fábrica el listado muestra **lo más reciente primero**.

Puede **encadenar hasta tres columnas**: mantenga presionada la tecla **Shift**
(⇧) y pulse el título de la segunda. Usos típicos en compras:

| Para ver… | Ordene así |
|-----------|-----------|
| Todo lo comprado a cada proveedor, en orden de fecha | *Proveedor*, luego Shift+clic en *Fecha* |
| Las compras más altas dentro de cada tipo de comprobante | *Tipo*, luego Shift+clic en *Total* |
| El detalle de un proveedor por número de comprobante | *Proveedor*, luego Shift+clic en *N° Comprobante* |

El número pequeño junto a cada flecha indica qué columna manda (`1`) y cuál
desempata (`2`). Un tercer Shift+clic sobre la misma columna la saca del orden, y
un clic normal en cualquier encabezado vuelve a dejar una sola.

El orden se guarda para usted. Detalles en *Cómo ordenar los listados*.

## Exportar el listado

Los botones **Excel** y **PDF** de la parte superior del listado exportan las
compras que coinciden con el buscador y el orden aplicados en ese momento (no
solo la página visible), incluido el orden por varias columnas.

## Pestaña Asiento contable

La pestaña muestra el asiento que la compra generó en contabilidad y permite
corregirlo sin salir del modal.

- **Solo se ve** si el usuario tiene permiso de ver *Contabilidad → Asientos
  Contables*; **solo se edita** si además puede modificar ese módulo.
- Mientras la compra no tenga asiento, la pestaña muestra la **vista previa** de
  lo que armarán las reglas contables; no hay nada que guardar todavía.
- Al pulsar **Guardar asiento** se comprueba que el Debe sea igual al Haber, que
  todas las líneas tengan cuenta y que **la cuenta por pagar del asiento siga
  coincidiendo con el total de la compra**. Si hay diferencia, se avisa y usted
  decide si guardar igual; si falta la línea de la cuenta por pagar, no se puede
  guardar.
- Un asiento corregido a mano **ya no se regenera** al volver a guardar la
  compra. El botón **Restaurar automático** descarta la corrección y lo vuelve a
  armar desde la configuración contable.

El detalle completo está en el manual de [Asientos contables](modulos/asientos-contables).

## Permisos

Con **acceso total** se ven las compras de toda la empresa; sin él, cada usuario
ve solo las que registró.

La pestaña **Asiento contable** no se rige por los permisos de Compras sino por
los de *Contabilidad → Asientos Contables* (ver la sección anterior).

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
- **Aprobé una factura del SRI y no se generó el pago**: el aviso al aprobar
  dice por qué (proveedor sin pago automático o con retenciones configuradas,
  saldo fuera del rango, ya tenía pago, sin saldo o un error como un período
  cerrado). Páguela desde la pestaña *Pagos* o con *Generar pagos pendientes* del
  proveedor.
- **La compra no generó asiento contable**: si está pendiente de aprobación, el
  asiento se genera al aprobarla, no al registrarla.
- **"No se puede registrar el asiento: la fecha ... corresponde a un período
  contable cerrado"** al eliminar: la eliminación anula el asiento, y eso no se
  puede hacer en un período cerrado. Reabra el período.
- **La compra no se guardaba y el error no decía por qué** con un campo de *Info
  Adicional* cuyo nombre era muy largo (más de 255 caracteres, típico de un XML
  con un campo mal armado): hasta la versión 2.18 ese solo campo hacía fallar el
  registro entero. Ahora el nombre se recorta a 255 y la compra se guarda.

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
  las que quedaron pendientes, no uno por documento. Además, una factura del SRI
  que quede pendiente **no genera su pago automático al descargarse**: si el
  proveedor lo tiene configurado, el egreso se genera **al aprobarla** (ver
  *Pago automático al aprobar*).
- Quedan fuera los **documentos históricos**: las compras que vienen de una
  migración o de una importación de datos antiguos entran como registradas. Son
  operaciones que ya ocurrieron; ponerlas a esperar aprobación las dejaría sin
  asiento y sin poder pagarse.
- Si en Aprobaciones se configuró un **monto mínimo**, las compras por debajo de
  ese valor se registran directamente, sin pedir autorización.

### Pago automático al aprobar

Al aprobar una **factura descargada del SRI**, el sistema genera el pago que
habría hecho al descargarla si la aprobación no la hubiera detenido. Aplica lo
mismo desde el modal que desde el enlace del correo, y usa la configuración de
pago automático del proveedor (ver *Proveedores → Pago automático de las
compras*):

- Se paga el **saldo** de la factura en el momento de aprobarla: si mientras
  esperaba llegó una nota de crédito, se descuenta.
- El egreso lleva la **fecha de la factura**, igual que en la descarga.
- **No se genera** si el proveedor no tiene pago automático, tiene retenciones
  configuradas, el saldo queda fuera de su rango de monto, la factura ya tiene
  un pago o no le queda saldo. Al aprobar aparece el motivo.
- Si el pago falla (por ejemplo, un período contable cerrado), la compra **queda
  aprobada igual** y el aviso lo indica; se paga a mano o con *Generar pagos
  pendientes* del proveedor.
- Las compras **registradas a mano** y las **liquidaciones de compra** no
  generan pago automático al aprobarlas, igual que no lo generan al registrarse.

Mientras la compra está pendiente, *Generar pagos pendientes* del proveedor
tampoco la incluye. Si dos aprobadores la aprueban a la vez, solo una de las
aprobaciones pasa, así que no se paga dos veces.

## Historial de cambios

- **2.20** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **2.19** — **Compras por cajas**: al pasar la compra al inventario, si se elige
  una medida distinta a la del producto (p. ej. CAJA X100 en un producto por
  UNIDAD), la cantidad y el costo se convierten solos: 10 cajas de $15,00 entran
  como 1.000 unidades a $0,15. Bajo la cantidad se ve lo que realmente entrará.
  Nueva sección *Compras por cajas u otra presentación*. Nueva columna
  **Código** en la pestaña Detalle (código del producto vinculado, o el del
  proveedor en gris si falta vincular) y el código delante del nombre en la
  pestaña Inventario (*GUA-001 - Guantes de nitrilo*).

- **2.18** — El **nombre** de cada campo de *Info Adicional* tiene un tope de
  **255 caracteres** en pantalla, y si un XML trae uno más largo se recorta a 255
  en lugar de fallar el registro completo de la compra sin explicación.

- **2.17** — Corregido: al **rechazar una compra** desde su ventana, el cuadro del *motivo del
  rechazo* no aceptaba texto —se veía, pero al escribir no pasaba nada—. Ya se puede
  escribir el motivo con normalidad.

- **2.16** — **Una compra con $0.01 de saldo queda como Abonada, no como
  Pagada.** Antes, el centavo restante se daba por pagado. Ahora el criterio es
  el mismo de *Cuentas por Pagar* y *Egresos*: hay saldo mientras quede al menos
  un centavo. Afecta al badge de la columna **Pago**, al filtro
  `pago:pagada|abonada|pendiente`, al color del saldo y al panel de pago del
  modal. Las notas de crédito (04) siguen contando como pagadas.


- **2.15** — Corregido: al buscar un **número de factura** en el cuadro aparecían
  también compras que no lo tenían. La búsqueda libre miraba dentro del **número de
  autorización** —49 dígitos que llevan la fecha, el RUC y el número del documento— y
  cualquier número corto caía ahí por casualidad. Ahora la autorización se consulta en
  la ventana de filtros (pestaña *Compra*), igual que el **RUC del proveedor** y el
  **usuario que registró**; los **productos comprados** pasan a la pestaña *Detalles*,
  que sí muestra qué línea coincidió. El cuadro de búsqueda queda para el N° de
  comprobante, la fecha, el proveedor, los importes, el saldo, las observaciones y el
  documento modificado.

- **2.14** — **Pago automático al aprobar**: al aprobar una factura descargada
  del SRI se genera su pago automático, por el saldo de la factura y con la
  configuración del proveedor; antes no se generaba y había que pagarla a mano.
  *Generar pagos pendientes* ya no incluye compras pendientes de aprobación, y
  una misma compra ya no puede aprobarse dos veces a la vez. Nueva sección
  *Pago automático al aprobar*.

- **2.13** — **Búsqueda del listado más rápida**: el conteo y la página salen de
  una sola consulta, los pagos, notas de crédito y retenciones se calculan solo
  para las 20 compras visibles (antes, para todas las de la empresa), y el saldo
  solo se busca cuando se escribe un monto con decimales. Los montos escritos con
  coma decimal (`34,78`) ahora sí se encuentran. Corregido: una compra de un tipo
  de comprobante repetido en el catálogo (código 52) salía dos veces en el listado.
- **2.12** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la compra (incluidos IVA, saldo
  y los productos comprados), salvo Tipo, Sustento, Pago y Estado. Los filtros
  pasan a una **ventana propia** (botón del embudo, se aplican con *Aplicar*)
  con dos pestañas: **Compra** (filtros por campo, con criterios nuevos: estado
  de pago, tipo de registro, deducible, sustento como lista, documento
  modificado, con/sin asiento, orden de compra y retención, parte relacionada,
  IVA, descuento, saldo pendiente, valor retenido y usuario como lista) y
  **Detalles**, un cuadro de **búsqueda libre dentro de las compras**
  (productos, formas de pago, información adicional y reembolsos de terceros)
  que dice a qué compra pertenece cada coincidencia. Los filtros activos se ven
  como etiquetas dentro del cuadro. Nueva sección *Buscar y filtrar el listado*.
- **2.11** — El listado se puede **ordenar por hasta tres columnas a la vez**:
  Shift+clic en el título de la segunda columna la encadena a la primera (por
  ejemplo *Proveedor* y, dentro de cada proveedor, la *Fecha*). Cada encabezado
  activo muestra un número con su prioridad. El orden se guarda por usuario y se
  respeta al exportar a PDF y Excel. Además, la columna **Pago** ya no responde al
  clic: no es ordenable y al pulsarla se perdía el orden que estuviera aplicado.
  Nueva sección *Ordenar el listado*.
- **2.10** — Una compra **migrada FÍSICA** ya se puede editar por completo (antes
  era de solo lectura igual que una electrónica): no tiene XML que la respalde
  como fuente de verdad, así que hacía falta poder corregir datos mal traídos de
  la migración. Guardar cambios no regenera el asiento contable de la compra
  (se conserva el histórico migrado, para no duplicar la contabilidad). Las
  migradas **electrónicas** siguen de solo lectura, con la excepción del
  Sustento Tributario (ver 2.9).
- **2.9** — En una compra **migrada** (solo lectura) ahora se puede corregir el
  **Sustento Tributario** sin abrir el resto del documento: el selector queda
  habilitado con su propio botón de guardado. Antes, si el dato venía mal
  clasificado desde el sistema anterior, no había forma de arreglarlo salvo
  editando la base de datos directamente.
- **2.8** — La pestaña **Pagos** de la compra ya toma en cuenta los **valores de
  terceros** de las planillas de luz y agua: antes el saldo pendiente se calculaba
  solo con el importe declarado al SRI, así que la planilla quedaba con unos
  centavos por pagar aunque se hubiera cancelado completa (y el egreso se
  registraba corto). Ahora aparece la tarjeta *Valores de Terceros* y el saldo los
  incluye, igual que en Egresos y Cuentas por Pagar. Mismo arreglo en el **pago
  automático** de la ficha del proveedor y en el pago que se genera solo al
  descargar la factura del SRI.
- **2.7** — Nueva pestaña **ATS** en el modal, con dos tarjetas: *Parte
  relacionada* (que estaba abajo, como sub-pestaña del detalle) y *Pago al
  exterior*, nueva — tipo de pago, país, convenio de doble tributación y
  sujeción a retención. Estos últimos son datos que el anexo transaccional exige
  en cada compra y que hasta ahora se reportaban siempre como pago local, lo que
  hacía que el SRI rechazara los comprobantes emitidos en el exterior. Las
  compras ya registradas quedan como pago local, igual que antes.
- **2.6** — El asiento automático tolera más centavos de redondeo en facturas con muchas líneas: 1 centavo por línea con IVA (mínimo 3), llevados a la cuenta de Ajuste por redondeo. Si aun así no cuadra, el mensaje pide revisar subtotal, IVA e importe total de la compra.
- **2.5** — La pestaña **Asiento contable** ahora solo aparece si el usuario tiene acceso a Contabilidad → Asientos Contables, y con permiso de modificar permite corregir el asiento y guardarlo desde el propio modal, validando que siga cuadrando con el total de la compra. Un asiento corregido a mano deja de regenerarse al reguardar la compra; se vuelve al automático con **Restaurar automático**.

- **2.4** — Se corrigió la **paginación del listado**: al pasar de página podía
  repetirse una compra y quedar otra sin mostrarse nunca, porque las compras que
  comparten fecha de emisión no tenían un orden estable. Ahora el listado ordena
  de forma determinista y el contador "desde-hasta/total" cuadra siempre con las
  compras que se recorren. Además el listado se volvió notablemente más rápido:
  dejó de arrastrar el XML completo de cada comprobante en cada fila y se
  agregaron índices de base de datos para el filtro por empresa y ambiente. Sin
  cambios en la forma de usar el módulo.

- **2.3** — El cuadre de las **formas de pago SRI** contra el total ya solo se
  exige en el **registro manual**. Un comprobante **electrónico** (o migrado) se
  guarda tal cual viene del XML descargado del SRI, aunque el emisor haya
  declarado un importe distinto o no haya declarado formas de pago: antes ese
  descuadre bloqueaba el guardado sin dejar forma de corregirlo, porque esos
  campos están deshabilitados. El aviso del registro manual ahora dice cuánto
  falta y abre la sub-pestaña correspondiente. Nueva sección *Formas de pago
  SRI (no confundir con Pagos)*.
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
