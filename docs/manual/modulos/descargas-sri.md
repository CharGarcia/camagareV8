---
titulo: Descargas del SRI
resumen: Descarga de los comprobantes electrónicos que otros emitieron a nombre de la empresa.
categoria: Compras
ruta_modulo: modulos/descargas-sri
tipo: modulo
visibilidad: todos
etiquetas: descargas sri, comprobantes recibidos, xml, facturas de proveedores, importar compras, portal sri
version: 1.11
orden: 50
estado: activo
---

Este módulo trae automáticamente los **comprobantes electrónicos que otros
emitieron a nombre de la empresa**: facturas de proveedores, notas de crédito,
retenciones que le practicaron. Evita capturarlas a mano.

## Qué hace

Se conecta al portal del SRI con las credenciales de la empresa y descarga los
comprobantes recibidos del periodo indicado. De cada uno guarda el XML íntegro
tal como lo entrega el SRI.

Desde ahí, los comprobantes se pueden registrar como compras sin volver a
escribir nada.

## Duplicados

El sistema evita registrar dos veces el mismo comprobante, aunque se descargue
varias veces o dos personas lo hagan a la vez. Si un documento ya existe, no se
duplica.

## Después de descargar

Descargar **no es lo mismo que registrar la compra**. El comprobante queda
disponible para convertirlo en compra; solo entonces afecta a cuentas por pagar y
puede generar entrada de inventario.

Recuerde que las líneas traen los **códigos del proveedor**: para que la
mercadería entre al inventario hay que vincularlas con productos de su catálogo.

## Permisos

| Permiso | Qué habilita |
|---|---|
| Ver | Abrir el módulo, ver la configuración, el historial y los documentos ignorados |
| Crear | Descargar y registrar comprobantes, procesar claves, TXT y XML, e ignorar documentos |
| Modificar | Guardar la configuración de descarga |
| Eliminar | Quitar documentos de la lista de ignorados |

La extensión de Chrome no usa estos permisos: se identifica con el token
personal del usuario, así que sigue funcionando igual.

### La contraseña del portal SRI

La tarjeta **Contraseña SRI** solo la ven los **administradores (nivel 2)** y
**superadministradores (nivel 3)**. Un usuario de nivel 1 con permiso en el
módulo puede descargar y registrar comprobantes, pero no ve ni puede cambiar
las credenciales del portal, aunque tenga el permiso *Modificar*.

La clave se guarda cifrada y **nunca se devuelve a la pantalla**: el campo
siempre aparece vacío y dejarlo así conserva la que ya estaba guardada. Por eso
tampoco hay un botón de "ver clave" — no habría nada que mostrar. Si se olvida,
se escribe una nueva; no hay forma de recuperarla desde el sistema.

## Errores frecuentes

- **No descarga nada**: revise las credenciales del SRI de la empresa y el rango
  de fechas.
- **"No tiene permiso para esta acción"**: pida que le asignen el submódulo en
  *Permisos de módulos*. Antes el módulo se abría sin permiso asignado; ahora lo
  exige, igual que los demás.
- **El comprobante está descargado pero no aparece en compras**: falta
  registrarlo como compra.
- **La descarga tarda**: el portal del SRI impone sus propios tiempos; para
  periodos largos conviene descargar por tramos.
- **"XML obtenido pero error en registro: el código del documento de sustento es
  obligatorio"**: ocurría al registrar retenciones cuyo XML no incluye el código
  del documento de sustento (el SRI lo permite: en la versión 1.0.0 del
  comprobante de retención ese dato es opcional). Ya no bloquea: si el XML no lo
  trae, el sistema toma el código presente en el propio comprobante y, si no hay
  ninguno, asume **01 – Factura**. Puede corregirlo después desde la retención.
- **"XML obtenido pero error en registro: Línea 1: la fecha del documento de
  sustento es obligatoria"**: ocurría con retenciones cuyo XML no incluye la
  fecha del documento de sustento. Es típico de los **bancos** cuando retienen
  sobre rendimientos financieros (intereses): el sustento es el código **12 –
  Documento emitido por institución financiera**, el número viene en ceros y la
  fecha no se informa (en la versión 1.0.0 del comprobante ese dato es opcional).
  No es un problema de versión del comprobante. Ya no bloquea: si el XML no trae
  la fecha, el sistema usa la primera fecha de sustento que exista en el propio
  comprobante y, si no hay ninguna, la **fecha de emisión de la retención**.
  Puede corregirla después desde la retención.
- **Un XML válido se marca como ERROR al subirlo**: ocurría con archivos en los
  que el emisor deja un salto de línea entre `<comprobante>` y el bloque `CDATA`
  del sobre de autorización. El lector exigía que el comprobante empezara justo
  en la declaración `<?xml`, así que descartaba el archivo completo. Ya se
  admiten esos espacios; vuelva a subir el archivo.
- **"XML obtenido pero error en registro: value too long for type character
  varying(150)"**: el comprobante traía un dato más largo de lo que aceptaba la
  columna donde se guarda —normalmente la **dirección o la razón social del
  proveedor**, que el SRI admite hasta 300 caracteres. El XML se descargaba bien
  pero el documento no llegaba a registrarse. Ya no ocurre: el sistema recorta el
  texto al largo que admite el campo en lugar de rechazar el comprobante
  completo. Con la actualización de base de datos aplicada, los datos se guardan
  completos (hasta 300 caracteres); sin ella, la dirección se guarda recortada
  pero la factura se registra igual.
- **La compra muestra IVA en el listado pero la línea del detalle dice 0%**: el
  XML del emisor es inconsistente. Algunos proveedores (por ejemplo los recaudos
  de BANECUADOR) declaran en los totales del comprobante un IVA del 15% y en la
  única línea del detalle ponen tarifa 0%. El SRI lo autoriza igual porque el
  total del comprobante cuadra con los totales, no con el detalle. El sistema lo
  resuelve así al registrar la compra:
  - Si el comprobante tiene **una sola línea** y **una sola tarifa** en los
    totales, la línea se registra con el IVA de los totales (tarifa, base y
    valor), que es lo que el proveedor cobró y lo que cuadra con el total. La
    compra queda con una observación que lo explica.
  - Si tiene **varias líneas o varias tarifas**, no hay forma de saber a qué
    línea pertenece la diferencia: el detalle se guarda tal como vino y la
    compra queda con una observación pidiendo revisar las tarifas antes de
    declarar. Corríjalas desde el modal de *Compras*.
  Las compras registradas antes de este cambio no se corrigen solas; hay que
  editar la tarifa de la línea en *Compras*.
- **La base con IVA de una compra no coincide con su subtotal en la Declaración
  de IVA, el ATS o el Reporte de Compras**: es otro caso de XML inconsistente.
  Algunos proveedores (por ejemplo SERVIENTREGA) informan como base del IVA de
  cada línea el precio **antes del descuento**, aunque el IVA sí lo calculan sobre
  el subtotal ya descontado. El SRI lo autoriza porque los totales del comprobante
  cuadran. El sistema lo resuelve así al registrar la compra:
  - Si la base de las líneas no coincide con la de los totales, pero el subtotal
    de esas líneas sí, cada línea se registra con su subtotal como base. La compra
    queda con una observación que lo explica.
  - Si tampoco coincide el subtotal, el detalle se guarda tal como vino.
  El valor del IVA no cambia en ningún caso. Las compras registradas antes de este
  cambio se corrigen con una actualización de datos que aplica el administrador
  del sistema.
- **El enlace del correo para aprobar una compra descargada del SRI dice que ya
  no está pendiente**: les ocurrió a las facturas y liquidaciones cargadas antes
  de la versión 1.9 en empresas que exigen aprobar las compras. Quedaban
  registradas aunque los aprobadores recibían el correo, así que el enlace ya no
  tenía nada que aprobar y tampoco se generaba su pago automático. Las cargas
  nuevas quedan pendientes, como se explica en *Compras → Aprobación de
  compras*; las anteriores las revisa el administrador del sistema.
- **"SQLSTATE[23514]: Check violation … retencion_compra_cabecera_estado_check"**:
  ocurría al cargar un comprobante de retención **emitido por la propia
  empresa** (retención en compras). El sistema lo grababa con un estado que la
  tabla no admite y el comprobante no se registraba. Ya no ocurre: vuelva a
  cargar el XML. Si el archivo es el sobre de autorización del SRI y dice
  **AUTORIZADO**, la retención queda directamente **autorizada**, con su número
  y fecha de autorización, y ya no aparece como pendiente de envío.
- **El XML descargado o enviado por correo de una retención en compras cargada
  desde el SRI no tiene firma ni autorización**: al registrarla, el sistema
  reemplazaba el XML cargado por uno generado con los datos guardados. Ahora se
  conserva el archivo tal como se cargó (con su firma y su autorización), que es
  el que se descarga y se envía al proveedor.
- **La retención en compras cargada desde el SRI sale sin proveedor (nombre en
  blanco), sin enlace a la compra y con las líneas sin concepto**: el sistema leía
  los datos del proveedor de una sección del XML que el comprobante de retención
  no usa, así que llegaban vacíos; y las líneas guardaban el código pero no el
  concepto del catálogo. Ahora el proveedor se toma del *sujeto retenido* del XML
  (y se enlaza la compra si ya está registrada), y cada línea queda enlazada al
  concepto de *Configuración → Retenciones SRI* que corresponde a su impuesto,
  código y porcentaje. Las retenciones cargadas antes no se corrigen solas: el
  superadministrador puede eliminarlas desde *Retenciones en Compras* y volver a
  cargarlas.

## Historial de cambios

- **1.11** — El registro de retenciones ya no falla con *la fecha del documento de
  sustento es obligatoria* cuando el XML del SRI omite esa fecha (retenciones de
  bancos sobre intereses, sustento **12**, dato opcional en la versión 1.0.0): se
  toma la fecha de sustento presente en el comprobante o, en su defecto, la fecha
  de emisión de la retención. Aplica a retenciones recibidas y emitidas.
- **1.10** — Corregido el error *retencion_compra_cabecera_estado_check* al
  cargar retenciones emitidas por la empresa. Si el XML es el sobre de
  autorización del SRI con estado **AUTORIZADO**, la retención se registra como
  autorizada, con número, fecha de autorización y XML autorizado. Además, la
  retención conserva el XML cargado en lugar de reemplazarlo por uno regenerado
  sin firma. Las retenciones en compras cargadas toman el proveedor del sujeto
  retenido (antes quedaba en blanco y sin enlace a la compra) y enlazan cada línea
  con su concepto del catálogo de retenciones del SRI.

- **1.9** — En empresas que exigen aprobar las compras (módulo *Aprobaciones*),
  las facturas y liquidaciones descargadas del SRI quedan **pendientes de
  aprobación**, como ya indicaba *Compras → Aprobación de compras*. Por un error
  se grababan como registradas, aunque los aprobadores recibían el correo. Al
  aprobar una factura se genera su pago automático, si el proveedor lo tiene
  configurado (ver *Compras → Pago automático al aprobar*). Las notas de crédito
  y de débito siguen entrando como registradas. Ver *Errores frecuentes*.

- **1.8** — Al registrar una compra desde el SRI, si la base del IVA de las líneas
  no coincide con la de los totales del comprobante pero el subtotal de las líneas
  sí, cada línea se registra con su subtotal como base y la compra queda con una
  observación. Antes esa base inflada llegaba a la Declaración de IVA, al ATS y al
  Reporte de Compras. Las notas de crédito recibidas guardan además el descuento
  total de sus líneas, que antes quedaba en 0 y no salían al filtrar por descuento
  en *Compras*. Ver *Errores frecuentes*.

- **1.7** — Al registrar una compra desde el SRI, si el IVA de los totales del
  comprobante no coincide con el del detalle, el sistema corrige la línea desde
  los totales cuando el caso es inequívoco (una línea, una tarifa) y, si no,
  deja una observación en la compra. Antes el IVA de esos comprobantes no
  entraba en la Declaración de IVA ni en el Reporte de Compras. Ver *Errores
  frecuentes*.

- **1.6** — Un comprobante ya no se queda sin registrar porque un dato del emisor
  (dirección, razón social, nombre comercial o descripción de un ítem) venga más
  largo de lo que aceptaba el campo: el texto se recorta al largo del campo en
  vez de rechazar la carga entera. Ver *Errores frecuentes*.
- **1.5** — La carga de XML acepta sobres de autorización en los que el
  comprobante va separado de la etiqueta `<comprobante>` por saltos de línea o
  espacios (antes el archivo se rechazaba entero con estado ERROR). Además, las
  **notas de crédito** conservan el código del ítem de cada línea, que el SRI
  nombra `codigoInterno` en vez de `codigoPrincipal`.
- **1.4** — La tarjeta **Contraseña SRI** queda reservada a administradores y
  superadministradores; los usuarios de nivel 1 ya no la ven ni pueden guardar
  credenciales. Se retiró el botón de "ver clave", que nunca tuvo nada que
  mostrar porque la clave guardada no viaja a la pantalla. Nueva sección
  *La contraseña del portal SRI*.
- **1.3** — Las facturas de **servicios básicos** (luz, agua) cargadas desde el
  SRI reconocen los **valores de terceros** (contribución bomberos, tasa de
  recolección de basura) que el emisor declara en la información adicional. Se
  totalizan aparte del importe de la factura y se suman al saldo por pagar. Ver
  *Compras → Planillas de luz y agua: valores de terceros*.
- **1.2** — El registro automático de retenciones ya no falla cuando el XML del
  SRI omite el código del documento de sustento (dato opcional en la versión
  1.0.0 del comprobante): se asume el del propio comprobante o **01 – Factura**.
- **1.1** — El módulo pasa a exigir el permiso del submódulo (antes entraba
  cualquier usuario con sesión). Nueva sección *Permisos*.
- **1.0** — Versión inicial.
