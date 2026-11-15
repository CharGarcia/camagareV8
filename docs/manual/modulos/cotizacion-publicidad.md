---
titulo: Cotización de Publicidad
resumen: Cotizaciones de servicios publicitarios con comisión de agencia, versiones, costos reales por proveedor, utilidad y generación de la factura de venta.
categoria: Ventas
ruta_modulo: modulos/cotizacion-publicidad
tipo: modulo
visibilidad: todos
etiquetas: cotizacion de publicidad, cotizacion publicidad, cotizaciones de publicidad, publicidad, agencia de publicidad, agencia, medios, campana, campana publicitaria, pauta, proyecto, presupuesto, cotizar, cotizacion, proforma de publicidad, oferta, comision de agencia, comision, fee de agencia, ejecutivo, ejecutivo de cuenta, vendedor, ciudades, dias, categorias, categoria de servicio, version, nueva version, versiones, v1, v2, aprobar cotizacion, rechazar cotizacion, anular cotizacion, convertida, costos, costo real, costos por proveedor, factura del proveedor, utilidad, margen, perdida, rentabilidad, resumen, generar factura, facturar cotizacion, factura de venta, enviar por correo, correo, pdf, exportar excel
version: 1.0
orden: 0
estado: activo
---

La **Cotización de Publicidad** sirve para presupuestar servicios publicitarios a
un cliente: cada línea es un servicio con su categoría, precio, ciudades, días y
cantidad, y sobre el subtotal se suma la **comisión de agencia** y el IVA. La
cotización se puede versionar, aprobar, enviar por correo y, una vez aprobada,
convertir en una **factura de venta**. Además permite registrar lo que realmente
costó cada servicio a los proveedores y ver la **utilidad** obtenida.

## Qué es y para qué sirve

- Arma el presupuesto de un proyecto publicitario para un cliente.
- Lleva un número propio por cliente y año, con **versiones** (V1, V2…) para
  las revisiones que pida el cliente.
- Registra los **costos reales** de cada línea por proveedor y compara lo
  cotizado contra lo gastado.
- Genera la **factura de venta** cuando el cliente aprueba.

La cotización no tiene efecto tributario ni contable: eso lo hace la factura que
se genera desde ella.

## Requisitos previos

- **Clientes** registrados (se puede crear uno desde el propio modal).
- **Tarifas de IVA** activas.
- Para generar la factura: una **serie** de facturación configurada y los
  **productos** del catálogo con que se va a facturar.
- Para registrar costos: los **proveedores**; si se quiere tomar el costo de una
  factura de compra, esa compra debe estar registrada en **Compras**.

## Estados de la cotización

| Desde | Puede pasar a |
|-------|---------------|
| Borrador | Aprobada, Anulada |
| Aprobada | Rechazada, Anulada |

Cualquier otro cambio se rechaza. Al generar la factura, la cotización pasa a
**Convertida**.

- **Borrador**: se está preparando. Es el único estado en que se puede editar.
- **Aprobada**: el cliente la aceptó. Ya se puede facturar.
- **Rechazada**: el cliente no la tomó.
- **Anulada**: se descarta. El sistema pide confirmar porque no se puede deshacer.
- **Convertida**: ya generó una factura de venta.

Los botones **Aprobar**, **Rechazar** y **Anular** de la barra superior solo
aparecen en los estados en que esa acción es posible.

## Cómo se usa

1. Pulse **Nueva**.
2. Complete la **Fecha**, el **Proyecto**, el **Presupuesto** del cliente, el
   **Ejecutivo**, el **Cliente** y el **Contacto**.
3. Elija la **Tarifa IVA** y la **Comisión (%)** (por defecto 17%).
4. Agregue las líneas con **Agregar línea**: categoría, descripción, precio,
   ciudades, días y cantidad.
5. Pulse **Guardar**. El número se genera al guardar y la cotización queda en
   **Borrador**.
6. Envíela al cliente con **PDF** o **Enviar por correo**.
7. Si la acepta, pulse **Aprobar**.
8. Pulse **Generar factura de venta** (barra superior) o **Generar Factura**
   (pestaña Facturas).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha | Sí | Fecha de emisión. Su año forma parte del número. |
| N° Cotización | — | Lo asigna el sistema al guardar. |
| Proyecto | No | Nombre del proyecto o campaña. |
| Presupuesto | No | Lo que el cliente tiene previsto gastar. Se compara con el total en la pestaña Resumen. |
| Ejecutivo | No | Vendedor responsable. Se puede fijar uno favorito con la estrella. |
| Cliente | Sí | Se busca por RUC o razón social. |
| Contacto | No | Persona de contacto del cliente. |
| Observaciones | No | Notas internas. |
| Tarifa IVA | Sí | Tarifa que se aplica a toda la cotización. Se puede fijar una favorita con la estrella. |
| Comisión (%) | No | Comisión de agencia, entre 0 y 100. |

### Líneas de la cotización

| Columna | Qué significa |
|---------|---------------|
| Categoría | Tipo de servicio publicitario (ver *Categorías*). |
| Descripción | Texto libre del servicio. Obligatoria. |
| Precio | Precio unitario. No puede ser negativo. |
| Ciudades / Días | Datos informativos: **no** afectan el cálculo. |
| Cant. | Cantidad. Debe ser mayor a cero. |
| Subtotal | Precio × Cantidad. |

## Cómo se calcula el total

1. **Subtotal** = suma de Precio × Cantidad de todas las líneas.
2. **Comisión de agencia** = Subtotal × Comisión (%).
3. **IVA** = (Subtotal + Comisión) × tarifa de IVA elegida.
4. **TOTAL** = Subtotal + Comisión + IVA.

## Número y versiones

El número se forma con un correlativo **por cliente y por año**, el año y la
versión: por ejemplo `001-2026 V1`. La primera cotización de un cliente en un año
es la 001, la siguiente de ese mismo cliente la 002, y así.

El botón **Nueva versión** crea una copia con el **mismo número** y la versión
siguiente (V2, V3…):

- Queda en **Borrador**, con la fecha del día.
- Copia el cliente, ejecutivo, contacto, proyecto, presupuesto, tarifa de IVA,
  comisión, observaciones y las líneas.
- **No** copia los costos: cada versión registra los suyos.

La versión original no cambia.

## Costos reales por proveedor (pestaña Costos)

Después de guardar la cotización, la pestaña **Costos** muestra cada línea
cotizada con su valor y permite registrar lo que costó realmente. Una línea puede
tener costos de varios proveedores: use el botón **+** de la línea para agregar otro.

En cada fila de costo:

- **Proveedor**: se busca por nombre o identificación.
- **N° Factura**: se puede escribir libremente o buscar entre las facturas de
  compra ya registradas de ese proveedor. Al elegir una, el **Costo** se llena
  con el subtotal (sin impuestos) de esa compra. No se ofrecen notas de crédito
  ni de débito, ni las facturas que ya se usaron en otra línea de esta cotización.
- **Costo** y **Observación**.

Pulse **Guardar costos**. Debajo se ve la **Utilidad** (o **Pérdida**) y su
porcentaje: lo que se cobra (subtotal + comisión, sin IVA) menos el costo
registrado. Las filas sin proveedor, factura ni costo no se guardan.

Guardar costos no cambia el estado ni los valores cotizados.

## Resumen de la cotización (pestaña Resumen)

Muestra:

- Tarjetas con **Subtotal cotizado**, **Comisión agencia**, **IVA**, **Total
  cotizado**, **Costo real** y **Utilidad** o **Pérdida** con su margen.
- Un aviso si hay líneas que todavía no tienen costo: la utilidad puede no ser
  definitiva.
- La comparación del **presupuesto del cliente** contra el total cotizado.
- Tablas **Detalle por línea**, **Por categoría** (cotizado, costo, utilidad y
  margen) y **Por proveedor** (facturas usadas y costo total).
- Si ya se facturó, la fecha en que se facturó.

## Generar la factura de venta

Solo se puede generar si la cotización está **Aprobada** (o **Convertida** cuya
factura anterior fue anulada) y **no existe otra factura activa** generada desde
ella. Requiere permiso de **crear** en este módulo.

1. Pulse **Generar factura de venta** (barra superior) o **Generar Factura**
   (pestaña Facturas).
2. En la ventana **Generar Factura de Venta**:
   - **Fecha**: viene con la fecha de hoy.
   - **Serie** y **Secuencial**: el secuencial se carga solo al elegir la serie.
   - **Cliente**: viene el de la cotización; se puede cambiar.
   - **Productos**: cada línea debe ser un **producto del catálogo**, con su
     cantidad y precio. La primera línea trae como precio el subtotal de la
     cotización (sin comisión ni IVA). El IVA de cada línea sale de la tarifa del
     producto.
3. Pulse **Generar Factura**.

La factura **no** copia las líneas de la cotización: se factura con los
productos que elija en esa ventana. Además:

- Lleva el ejecutivo de la cotización como vendedor y sus observaciones.
- En la información adicional agrega el **Proyecto**, la **Cotización de
  publicidad origen** (su número y versión) y el **Contacto**, si lo hay.
- Se registra con forma de pago SRI 01 por el total.
- El IVA se calcula según la configuración de facturación del establecimiento de
  la serie elegida.

Al terminar aparece el aviso *«Se ha generado una factura de venta para que sea
revisada y autorizada.»*, la cotización pasa a **Convertida** y la factura se ve
en la pestaña **Facturas** (fecha, número, valor total y estado). La revisión y
el envío al SRI se hacen desde **Facturas de Venta**.

## Enviar por correo y PDF

- **PDF**: genera la cotización con los datos del cliente, proyecto, contacto y
  fecha, las líneas (categoría, descripción, ciudades, días, cantidad, precio y
  subtotal) y los totales: subtotal, comisión de agencia, IVA y total.
- **Enviar por correo**: pide los correos destino, separados por coma. Si lo
  deja vacío, se usa el correo del cliente. El correo lleva el PDF adjunto con el
  asunto *Cotización de Publicidad* y el número.

Ambos botones aparecen cuando la cotización ya está guardada.

## Categorías

El botón **Gestionar categorías** de la barra superior abre la lista de
categorías de servicio publicitario de la empresa: se pueden agregar, renombrar
y eliminar. Una categoría que ya se usa en alguna cotización no se puede eliminar.

## Buscar y filtrar el listado

El listado muestra Número, Fecha, Cliente, Contacto, Proyecto, Ejecutivo,
Presupuesto, Comisión %, Total, Estado y Observaciones, y se puede exportar a
**Excel**.

Los filtros permiten buscar por fecha de emisión, estado, Nº cotización (por
ejemplo `001-2026 V1`), número, versión, categoría, usuario que registró, rangos
de valores (total, presupuesto, comisión %, subtotal, valor de comisión, IVA),
cliente, RUC / CI, ejecutivo, contacto, proyecto, observaciones y factura
generada.

La búsqueda libre dentro de las cotizaciones encuentra descripciones,
categorías, valores, proveedores y facturas de proveedor de la pestaña Costos.

## Permisos

| Acción | Permiso |
|--------|---------|
| Ver el listado, abrir cotizaciones, PDF, correo y Excel | Ver |
| Crear cotizaciones, nuevas versiones, categorías y generar la factura | Crear |
| Modificar, aprobar, rechazar, anular, guardar costos y renombrar categorías | Actualizar |
| Eliminar cotizaciones y categorías | Eliminar |

Sin **acceso total**, el usuario solo ve las cotizaciones que él registró.

## Reglas de negocio

- Solo se edita una cotización en **Borrador**.
- Una cotización **Convertida** no se puede eliminar.
- No puede haber dos facturas activas generadas desde la misma cotización: para
  generar otra hay que anular la anterior.
- Al editar, las líneas que se conservan mantienen sus costos; si se quita una
  línea, se borran también sus costos.

## Integraciones con otros módulos

- **Facturas de Venta**: recibe la factura generada, que queda enlazada a la
  cotización.
- **Compras**: sus facturas se pueden usar como costo real de una línea.
- **Clientes**, **Productos**, **Proveedores** y **Vendedores**: catálogos que se
  usan en la cotización y en la factura. Desde la barra superior se puede
  registrar un cliente o un producto nuevo.

## Errores frecuentes

- **Debe seleccionar un cliente.** / **Debe seleccionar la tarifa de IVA.**:
  complete esos campos antes de guardar.
- **Debe agregar al menos un ítem a la cotización.**: agregue una línea.
- **Fila …: la descripción es obligatoria.** / **Fila …: la cantidad debe ser
  mayor a cero.**: corrija la línea indicada.
- **La comisión de agencia debe estar entre 0 y 100.**: revise el porcentaje.
- **Solo se pueden editar cotizaciones en estado borrador.**: para cambiar una
  cotización ya aprobada, cree una **Nueva versión**.
- **No se puede cambiar de '…' a '…'.**: ese cambio de estado no está permitido
  (ver *Estados de la cotización*).
- **La cotización debe estar aprobada para generar una factura.**: apruébela primero.
- **Ya existe una factura activa para esta cotización. Anúlela antes de generar
  otra.**: revise la pestaña Facturas.
- **Seleccione un producto del catálogo en cada línea**: en la ventana de
  factura, cada línea debe tener un producto elegido de la lista.
- **El cliente no tiene correo registrado. Ingrese uno manualmente.**: escriba el
  correo en la ventana de envío o regístrelo en la ficha del cliente.
- **No se pudo enviar el correo. Verifica la configuración de correo o el
  destinatario.**: revise la configuración de correo de la empresa o la dirección.
- **No se puede eliminar una cotización ya convertida a factura.**
- **No se puede eliminar: la categoría está en uso en una o más cotizaciones.**

## Historial de cambios

- **1.0** — Versión inicial del manual.
