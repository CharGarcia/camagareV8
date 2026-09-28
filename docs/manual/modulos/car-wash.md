---
titulo: Servicio de car wash
resumen: Órdenes de servicio por vehículo (lavado, cambio de aceite, mecánica ligera) que luego se facturan o se cobran con recibo, con historial por vehículo y por cliente.
categoria: Servicios
ruta_modulo: modulos/car-wash
tipo: modulo
visibilidad: todos
etiquetas: car wash, lavado, lavadora de autos, lubricadora, cambio de aceite, mecanica, taller, orden de servicio, orden mecanica, orden de trabajo, vehiculo, placa, historial del vehiculo, historial del cliente, visitas, ultima visita, facturar orden, recibo de venta, refacturar, factura anulada, proxima cita, proximo chequeo, migracion, sistema anterior, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, buscar orden, buscar placa, buscador, filtros, filtrar ordenes, filtro de fechas, buscar por servicio, chips, imprimir, impresora
version: 1.6
orden: 10
estado: activo
---

El módulo de **car wash** registra cada ingreso de un vehículo: qué vehículo es, de
qué cliente, qué servicios y productos se le hicieron, las novedades encontradas y
la fecha de la próxima cita. Desde la misma orden se emite la **factura
electrónica** o el **recibo de venta**.

Sirve igual para una lavadora de autos, una lubricadora o un taller de mecánica
ligera: la orden es el control interno del trabajo y el documento de venta es lo que
se le entrega al cliente.

## Qué es y para qué sirve

- Llevar el registro de cada vehículo atendido, con su placa, kilometraje y nivel de
  combustible al ingresar.
- Cobrar el servicio sin volver a digitar nada: la orden se convierte en factura o
  recibo con un clic.
- Consultar el **historial** de un vehículo o de un cliente: cuántas veces vino, qué
  se le hizo y cuándo fue su última visita.
- Saber en qué factura o recibo se cobró cada orden.

## Requisitos previos

- Un **punto de emisión** con numeración para *Ordenes car-wash* (Empresa →
  Secuenciales). Si no se configura, arranca en 1.
- Para facturar: numeración de *Facturas de venta* o *Recibos de venta* en el mismo
  punto de emisión, y firma electrónica vigente en el caso de la factura.
- Los vehículos se crean desde el propio modal (botón del carrito) o desde el módulo
  **Vehículos**; los clientes y los servicios, igual.

## Cómo se usa

1. Pulse **Nuevo** para abrir la orden. El cursor empieza en **Vehículo**: escriba
   la placa, la marca o el propietario y elija.
2. Elija el **cliente** (opcional al registrar, obligatorio para facturar), la
   **bodega** y, si quiere, kilometraje, combustible y **próxima cita**.
3. Agregue los **servicios y productos** en la grilla (igual que en una factura).
4. Anote las novedades en **Info. Adicional** y pulse **Guardar**.
5. Para cobrar, abra la orden y pulse **Factura** o **Recibo** en la barra superior y
   confirme. La **forma de pago SRI** no se pregunta: se toma de la ficha del
   **cliente** o, si no la tiene, de la **configuración de facturación** (Empresa →
   Facturación); si ninguna está definida, se usa *01 - Sin utilización del sistema
   financiero*. La ventana de confirmación muestra cuál se usará y de dónde sale. La orden pasa a **Facturado** y queda
   bloqueada.

El modal tiene tres pestañas:

- **General**: la orden en sí (cabecera, servicios, info adicional y totales).
- **Historial**: todas las órdenes de un **vehículo** o de un **cliente** (ver abajo).
- **Facturación**: en qué factura(s) o recibo(s) se emitió esta orden y el estado
  actual de cada documento.

Cada usuario puede ocultar las pestañas que no use con el engranaje a la derecha de
las pestañas.

## Orden sin guardar: se recupera

Mientras llena una orden, lo que escribe se guarda en su navegador. Si cierra el
modal, recarga la página o se corta la conexión antes de pulsar **Guardar**, al volver
a abrir la orden (o una orden nueva) el sistema pregunta si quiere **Recuperar** los
cambios o **Descartarlos**. El borrador es de su usuario y empresa, queda solo en ese
navegador y se borra al guardar o eliminar la orden.

## Ambiente de pruebas y de producción

Como en Facturas y Recibos, cada orden pertenece al ambiente en que está la empresa
(**Empresa → Tipo de ambiente**). Las órdenes de pruebas no aparecen en producción ni
ocupan sus números, y al pasar a producción la numeración de las órdenes empieza en
la configurada para ese ambiente.

## La bodega es una sola para toda la orden

La bodega se elige **una vez**, en la cabecera, y aplica a todos los productos de la
orden: de ahí se descuenta el inventario al guardar y de ahí sale la factura o el
recibo. Las líneas ya no tienen una columna de bodega propia.

## La grilla de servicios y productos

Es la misma de **Facturas de venta**: **Código** (busca por código; Enter toma el
primero), **Descripción** (busca por nombre), Adicional, Cantidad, P. Sin Imp.,
P. Con Imp., Descuento, IVA y Subtotal. Las columnas **Medida** y **Precios** solo
aparecen cuando algún ítem tiene unidades de medida o listas de precios.

**Descuento rápido.** Junto al descuento de cada línea está el botón **+** (si la
empresa permite editar descuentos): abre una ventana para aplicar el descuento por
**porcentaje** o por **valor**, a esa línea o, con *Aplicar a todos los ítems*, a toda
la orden. Muestra el valor calculado antes de confirmar. No acepta más del 100 % ni un
valor mayor que el subtotal de la línea, y deja el descuento con 2 decimales, igual
que en la factura o el recibo que se emite.

## Condiciones de ingreso y Acta de ingreso del vehículo

Junto a **Info. Adicional** está la pestaña **Condiciones de ingreso**: un editor de
texto con formato (negrita, colores, listas), igual al de *Condiciones* de la
Proforma, para describir cómo llega el vehículo (golpes, rayones, objetos que deja,
accesorios).

Con la orden guardada, el botón del portapapeles de la barra superior (o **Acta de
ingreso** dentro de la pestaña) genera el **Acta de ingreso del vehículo**: un PDF
aparte, sin datos tributarios, con los datos del vehículo (placa, marca, modelo, año,
color, chasis, motor, kilometraje, combustible), del cliente, las condiciones de
ingreso, las novedades, la próxima cita y las firmas de quien recibe (taller) y de quien entrega (cliente).
Deja constancia de cómo ingresa el vehículo; no lleva servicios, productos ni valores.

Para enviarla, pulse **Correo** y elija el documento *Acta de ingreso del vehículo*
(o *Orden de servicio*).

## La orden sigue la configuración de facturación

La orden aplica la misma configuración de facturación del establecimiento que la
**Factura de venta**, así que no acepta nada que luego la factura o el recibo
rechazarían:

- **Facturación libre** apagada: solo servicios y productos del catálogo.
- **Lote**, **caducidad** y **NUP / serie** obligatorios: en los productos
  inventariables aparecen esas columnas. Al elegir el producto se cargan los lotes y
  vencimientos disponibles en la bodega de la orden (lote y vencimiento van juntos:
  elegir uno elige el otro). Sin ellos la orden no se guarda. Los servicios y los
  productos no inventariables no los piden.
- **Unidad de medida**: la unidad elegida en la línea se guarda y la factura o el
  recibo descuentan el inventario en esa unidad (por ejemplo, una caja de 12).

Lote, caducidad, NUP y unidad pasan tal cual a la factura o al recibo.

## Producto sin saldo: productos similares

Si elige un producto que **no tiene saldo** en la bodega de la orden, se abre una
ventana con **productos similares que sí tienen saldo**: de la misma categoría, de
la misma marca o con un nombre parecido, ordenados por parecido y saldo. Cada uno
muestra su saldo y precio; **Usar este** reemplaza el producto en la línea.
**Mantener el producto** deja el que eligió.

## Historial por vehículo o por cliente

En la pestaña **Historial** elija **Buscar por**: *Vehículo* o *Cliente*.

- Al entrar, se carga solo el historial del vehículo (o cliente) de la orden abierta.
  El botón **De esta orden** vuelve a cargarlo.
- Para consultar otro, escriba la placa, marca o propietario (o el nombre o la
  identificación del cliente). Se aceptan varias palabras en cualquier orden y no
  importan mayúsculas ni tildes.
- Arriba se resume: número de órdenes, total facturado (sin las anuladas), fecha de
  la última visita y cuántos vehículos o clientes distintos aparecen.
- Cada fila muestra fecha, número de orden, placa, cliente, servicios, total,
  documento emitido y estado. Un clic en una fila abre esa orden. La orden abierta
  se resalta.

Se muestran hasta las 200 órdenes más recientes.

## Facturación: en qué documento se emitió la orden

La pestaña **Facturación** lista cada documento emitido desde la orden con su fecha,
tipo (factura o recibo), número, total, **estado actual** (autorizado, borrador,
anulado o eliminado), origen (*Car-Wash* o *Sistema anterior*) y usuario. El ícono
rojo abre el PDF del documento (Imprimir / Descargar / Ver).

**Volver a facturar una orden.** Si la factura o el recibo se **anula** o se
**elimina** en su módulo, la orden se libera sola: en el listado aparece como
**Doc. anulado**, se puede corregir y volver a emitir. El documento anterior se
conserva en esta pestaña, así que siempre queda el rastro completo.

## Campos del formulario

| Campo | Para qué sirve |
|-------|----------------|
| Fecha ingreso | Fecha y hora en que el vehículo llegó. |
| Serie / Secuencial | Punto de emisión y número de la orden. |
| Cliente | A quién se le factura. Opcional al registrar. |
| Vehículo | Placa del vehículo atendido. Obligatorio. |
| Kilometraje / Combustible | Estado del vehículo al ingresar. |
| Próx. cita | Fecha sugerida para la siguiente visita (no puede ser anterior a hoy al crear la orden). |
| Bodega | De dónde se toman los productos. Aplica a toda la orden. |
| Servicios / productos | Grilla igual a la de la factura: precio, descuento e IVA por línea. |
| Info. Adicional | Novedades, observaciones y el correo del cliente; viajan a la factura. |

## Permisos

- **Ver**: consultar órdenes, su historial y su facturación.
- **Crear**: registrar órdenes y emitir la factura o el recibo.
- **Modificar**: editar órdenes no facturadas (o cuyo documento se anuló).
- **Eliminar**: eliminar órdenes sin documento vigente.
- **Acceso total**: ver las órdenes de toda la empresa; sin él, cada usuario ve solo
  las que registró (también en el Historial).

Los botones **PDF**, **Correo** y **WhatsApp** del documento generado usan los
módulos de Facturas y Recibos de venta, así que requieren permiso de lectura en ellos.

## Reglas de negocio

- La orden descuenta inventario al guardarse (si el establecimiento trabaja con
  inventario). Al emitir el documento, esa salida se devuelve y la hace el
  documento, así el stock nunca se descuenta dos veces.
- Toda la emisión (número del documento, inventario, documento y marca de la orden)
  ocurre en una sola operación: si algo falla, nada queda a medias.
- La factura lleva en Info. Adicional la **placa** y el **número de orden**, además de
  lo que se haya escrito en la orden.
- Una orden con documento vigente no se puede editar, eliminar ni volver a facturar.
- **Solo se factura a clientes activos.** El buscador de clientes muestra solo los
  activos; si el cliente de una orden se desactiva después, la orden muestra el aviso
  *Cliente inactivo* y los botones Factura y Recibo quedan deshabilitados (el sistema
  lo vuelve a comprobar al emitir).
- **Los importes son exactos**: la pantalla, la orden guardada, la factura y el recibo
  calculan subtotal, descuento, IVA y total con la misma regla (cantidad y precio a 6
  decimales, descuento a 2, IVA línea a línea o al subtotal según el establecimiento
  de la serie). El documento sale con el mismo total que la orden, al centavo.

## Integraciones con otros módulos

- **Facturas de venta** y **Recibos de venta**: reciben el documento emitido; ahí se
  envía al SRI, se anula o se cobra.
- **Inventario**: movimientos de salida por la orden y luego por el documento.
- **Vehículos** y **Clientes**: catálogos que usa la orden.
- **Migración desde el sistema anterior** (`/config/migrar-mysql`, solo
  superadministrador): la entidad *Órdenes de servicio (Car-Wash / mecánica)* trae las
  órdenes del módulo *Orden mecánica* del sistema anterior (ver abajo).

## Órdenes migradas del sistema anterior

La migración trae cada orden con sus servicios y productos, el vehículo (se crea en
**Vehículos** si no existe, uno por placa), el cliente, la fecha y hora de recepción
y de entrega, la próxima cita y sus observaciones (en Info. Adicional, junto con la
persona a cargo del vehículo). Conserva el **número de orden** del sistema anterior
como secuencial.

- Las órdenes **cerradas** llegan como **Facturado**; las que estaban *En taller* o
  *En espera*, como **Borrador**.
- La pestaña **Facturación** muestra la factura o el recibo en que se emitió cada
  una (origen *Sistema anterior*). Si esas facturas y recibos ya se migraron, quedan
  enlazados y se ve su estado y su PDF; conviene migrar **Facturas** y **Recibos**
  antes que las órdenes. Si se migran después, basta volver a correr la entidad de
  órdenes: re-enlaza los documentos sin duplicar nada.
- Las órdenes migradas **no mueven inventario**: el kardex del sistema anterior se
  migra aparte, como dato.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la orden: fecha, N°
orden, serie, secuencial, placa, cliente (nombre y RUC / cédula) y total.
Además busca en la marca y el modelo del vehículo, las observaciones y
novedades, el número del documento de venta generado, el usuario que registró
la orden y los **servicios y productos** de la orden (código y descripción). La
columna **Estado** y el tipo de documento generado no entran en la búsqueda
libre: para filtrar por ellos use la ventana de filtros. Puede escribir varias
palabras en cualquier orden y no importan mayúsculas ni tildes. Para limpiar,
borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Orden** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Orden | Fecha de ingreso (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, facturado, anulado), serie, N° orden, secuencial, total (mínimo y máximo), con o sin documento de venta generado, tipo de documento (factura o recibo de venta), N° del documento generado, fecha de entrega y próxima cita |
| Vehículo | Placa, marca, modelo y kilometraje (mínimo y máximo) |
| Cliente y registro | Cliente, RUC / cédula, usuario que registró (lista), observaciones / novedades |

Los selectores *Serie* y *Usuario que registró* listan solo lo que la empresa ya
usó en sus órdenes.

**Pestaña Detalles** (lo que hay dentro de la orden). Es un único cuadro,
**Buscar libremente dentro de las órdenes**: escriba un servicio, un producto,
un código o una novedad, y aparece la lista de **cada línea que coincide** con
la orden a la que pertenece (número, fecha, placa, cliente y estado). Un clic en
la fila deja el listado mostrando solo esa orden; el ícono de la derecha la abre
directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

Si no tiene **acceso total** al módulo, tanto el listado como la pestaña
Detalles muestran solo las órdenes que usted registró.

## Errores frecuentes

- **La orden no aparece en ventas**: falta emitir la factura o el recibo desde la orden.
- **"Debe asignar un cliente a la orden antes de facturar"**: elija el cliente y
  guarde antes de pulsar Factura o Recibo.
- **"El cliente seleccionado está inactivo o eliminado"**: actívelo en **Clientes**
  o elija otro cliente activo, guarde y vuelva a facturar.
- **"Esta orden ya generó un documento vigente"**: para volver a facturarla, anule
  primero ese documento en Facturas o Recibos de venta.
- **"No se permite el ingreso de ítems libres"**: la empresa no admite servicios
  escritos a mano; use un servicio del catálogo (botón de la caja para crearlo).
- **No puedo editar una orden migrada del sistema anterior**: si estaba cerrada allá,
  llega como Facturado y queda bloqueada, igual que las facturadas aquí.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Estos documentos no llevan una fecha de emisión editable: se numeran por la fecha
en que se registran. Los documentos ya emitidos conservan siempre el número que
tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.6** — El modal se organiza en pestañas: **General**, **Historial** (todas las
  órdenes de un vehículo o de un cliente, con resumen de visitas) y **Facturación**
  (en qué factura o recibo se emitió la orden, con su estado actual y PDF). La
  **bodega** se elige una sola vez en la cabecera; se quitó la columna de bodega de
  cada línea y se quitó el saldo que se mostraba bajo cada ítem; la grilla ahora es
  igual a la de Factura de venta (columna **Código**, Medida y Precios solo cuando se
  usan). Si un producto no tiene saldo, se ofrecen **productos similares con saldo**.
  El **PDF de la orden** usa el mismo diseño que el RIDE de la factura en el cuerpo
  (Cód. Principal, Cantidad, Descripción, Detalle Adicional con lote/caducidad/NUP,
  Precio Unitario, Descuento, Precio Total), los **totales** (subtotal por tarifa, no
  objeto, exento, sin impuestos, descuento, ICE, IVA por tarifa y VALOR TOTAL) y la
  **información adicional**, más observaciones y la leyenda del PDF de la empresa.
  La **forma de pago SRI** ya no se pide al emitir: sale del cliente o de la
  configuración de facturación, como en Factura de venta.
  **Descuento rápido** por porcentaje o valor, por línea o a todos los ítems (como la
  factura).
  Nueva pestaña **Condiciones de ingreso** y nuevo PDF **Acta de ingreso del vehículo**,
  que se imprime y se envía por correo. Al editar una orden ya no se borran sus
  observaciones (p. ej. las de órdenes migradas). La factura y el recibo llevan una sola
  vez el **correo del cliente**, siempre el vigente (antes el recibo podía llevar un
  correo viejo o no llevarlo).
  Solo se puede facturar a **clientes activos**. La orden sigue la **configuración de
  facturación** del establecimiento igual que la factura: ítems libres, **lote,
  caducidad y NUP** obligatorios (con la carga de lotes disponibles) y la **unidad de
  medida** por línea, que pasan a la factura o recibo. Las órdenes respetan el **ambiente**
  (pruebas / producción): antes toda orden se guardaba como pruebas y, con la empresa
  en producción, no se podía registrar ninguna ("El secuencial ya existe"). La orden
  que no se alcanzó a guardar se **recupera** al volver a abrirla. Subtotales, IVA y total son
  **exactos** entre la pantalla, la orden, la factura y el recibo (antes podía haber
  diferencias de un centavo o usar el modo de IVA de otro establecimiento). Si la
  factura o el recibo se anula o elimina, la orden se libera para corregirla y
  volver a facturarla. Correcciones al facturar: la factura ahora queda
  con su **XML** listo para enviar al SRI y con el **código** de cada ítem; usa el
  IVA elegido en cada línea de la orden; lleva la placa y el número de orden en la
  información adicional; y si la emisión falla, el inventario ya no se descuenta dos
  veces. Nueva migración de las órdenes del módulo *Orden mecánica* del sistema
  anterior.
- **1.5** — La orden y la factura o recibo que se genera desde ella respetan ahora el IVA
  **al subtotal** configurado en la empresa. Antes el sistema guardaba siempre
  el IVA línea por línea, aunque la pantalla mostrara el total calculado al
  subtotal.
- **1.4** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.3** — Con **"La facturación afecta al inventario"** apagada en Empresa, la orden
  ya no descuenta stock ni exige bodega en los productos (antes lo hacía igual por un
  error al leer la opción).
- **1.2** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la orden y en sus servicios y
  productos, salvo el Estado. Los filtros pasan a una **ventana propia** (botón
  del embudo, se aplican con *Aplicar*) con dos pestañas: **Orden** (filtros por
  campo, con criterios nuevos: fechas de ingreso, entrega y próxima cita, total,
  documento generado, marca, modelo, kilometraje, RUC, usuario y observaciones)
  y **Detalles**, un cuadro de búsqueda libre dentro de las órdenes. Se quitaron
  los accesos *En proceso* y *Terminados*, que filtraban estados que las órdenes
  ya no usan. Nueva sección *Buscar y filtrar el listado*.

- **1.1** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.0** — Versión inicial.
