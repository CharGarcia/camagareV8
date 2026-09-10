---
titulo: Retenciones de compra
resumen: Comprobantes de retención que la empresa emite a sus proveedores y envía al SRI.
categoria: Compras
ruta_modulo: modulos/retenciones_compras
tipo: modulo
visibilidad: todos
etiquetas: retencion, retenciones, comprobante de retencion, proveedor, iva, renta, sustento tributario, sri, plazo, base imponible, porcentaje, advertencias
version: 1.9
orden: 30
estado: activo
---

Cuando la empresa está obligada a retener, al recibir una factura de compra debe
emitir un **comprobante de retención** al proveedor y enviarlo al SRI. Este
módulo gestiona esos comprobantes.

La retención siempre se apoya en un **documento de sustento**: la factura de
compra que la origina.

## Cómo se emite

Lo habitual es generarla desde la propia compra: así el proveedor, el documento
de sustento —con su tipo, número, fecha y totales— vienen ya cargados.

1. Abra la compra y genere la retención.
2. Revise el **proveedor** y la **fecha de emisión**.
3. Compruebe el **documento de sustento**: tipo, número y fecha de emisión.
4. Elija los códigos de retención de la lista y revise bases y porcentajes.
5. Guarde y envíe al SRI.

El porcentaje sale del código que elija en la lista del catálogo del SRI y solo se
puede escribir cuando ese código es de **tarifa variable** (ver *El porcentaje lo
decide el catálogo*, más abajo).

## Buscar en el listado

El buscador acepta texto libre —número de la retención, número del documento de
sustento, nombre o RUC del proveedor y período fiscal— y filtros `clave:valor`:

| Filtro | Ejemplo | Qué hace |
|--------|---------|----------|
| `proveedor:` | `proveedor:"Corporación Favorita"` | Por nombre del proveedor |
| `ruc:` | `ruc:1790016919001` | Por RUC o cédula del proveedor |
| `numero:` | `numero:298` | Por número de la retención |
| `secuencial:` | `secuencial:298` | Igual, sin escribir los ceros a la izquierda |
| `doc_sustento:` | `doc_sustento:001-001-000000123` | Por el documento retenido |
| `serie:` | `serie:001-001` | Establecimiento y punto de emisión |
| `estado:` | `estado:borrador` | Borrador, autorizada, anulada… |
| `periodo:` | `periodo:07/2026` | Por período fiscal |
| `fecha:` | `fecha:2026-08` · `fecha:>=2026-01-01` | Por fecha de emisión, con rangos |
| `monto:` / `total:` | `monto:>=100` · `monto:100..500` | Por el total retenido |
| `renta:` `iva:` `isd:` | `iva:>0` | Por el importe retenido de cada impuesto |
| `clave_acceso:` | `clave_acceso:2608…` | Por clave de acceso |
| `usuario:` | `usuario:ana` | Quién registró la retención |

Se pueden combinar (`proveedor:favorita estado:borrador`) y negar anteponiendo un
guion (`-estado:anulada`).

## Cómo buscar el código de retención

En las líneas de la retención puede buscar el código del catálogo del SRI desde
**cualquiera de las tres columnas** —Código, Concepto o **% Ret.**—: escriba en
la que le resulte más natural y aparecerá la lista de coincidencias.

- Desde **Código** o **Concepto** se busca en todas las columnas del catálogo a
  la vez: código, concepto, porcentaje, impuesto y código del anexo. Así, escribir
  `1.75` en el concepto encuentra los códigos que retienen ese porcentaje, y
  `iva 100` encuentra la retención del 100 % de IVA.
- Desde **% Ret.** se busca solo por porcentaje, para que teclear `2` no le
  devuelva todos los conceptos que contienen un 2. Puede escribir `1,75` o
  `1.75 %`: el signo y la coma decimal se interpretan igual.
- Se pueden escribir **varias palabras** en cualquier orden (`renta honorarios`)
  y no importan mayúsculas ni tildes.
- Elija una opción de la lista para que se completen el código, el concepto, el
  impuesto y el porcentaje. Con **Esc** se cierra la lista sin tocar lo escrito.

Solo se listan los códigos **vigentes a la fecha de emisión** de la retención. Si
alguno coincide pero está fuera de vigencia, la lista lo avisa al pie en lugar de
callarlo: es la causa habitual de "el código existe pero no me aparece". La
vigencia (Desde / Hasta) se revisa en **Configuración → Retenciones SRI**.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Proveedor | Obligatorio, y debe ser un proveedor de la empresa activa |
| Fecha de emisión | Obligatoria, y no puede ser posterior a hoy |
| Tipo de documento de sustento | Obligatorio, y debe ser uno de los códigos válidos del SRI |
| Número del documento de sustento | Obligatorio, con el formato `000-000-000000000` |
| Fecha de emisión del documento de sustento | Obligatoria, y no posterior a la de la retención |
| Período fiscal | Es siempre el mes de la fecha de emisión; se calcula solo |

## Qué comprueba el sistema al guardar

La comprobación tiene **dos niveles**, porque no todo lo que llama la atención en
una retención es un error.

### Lo que impide guardar

Son datos que hacen inválido el comprobante —el SRI lo rechazaría— o que dejarían
un registro inconsistente. Hay que corregirlos:

- El **código de retención no existe** en el catálogo del SRI.
- El **impuesto no corresponde al código**: por ejemplo, un código de renta
  marcado como IVA.
- La **base de la retención de renta supera el subtotal** del documento de
  sustento.
- La **base de la retención de IVA supera el IVA** del documento. Es el error
  más habitual: la retención de IVA se calcula sobre **el IVA**, no sobre el
  subtotal. En una factura de 200 + 30 de IVA, retener el 70 % significa
  30 × 70 % = 21, con base 30 —no con base 200—.
- El **documento de sustento vinculado es de otro proveedor**, está anulado o
  pertenece a otra empresa. Ocurre al cambiar el proveedor después de haber
  abierto la retención desde una compra.
- La retención se emite **a la propia empresa** (el proveedor tiene la misma
  identificación que quien retiene).
- El **porcentaje no es el que fija el catálogo** para ese código (ver más abajo).

### La fecha de emisión y el envío al SRI

Dos reglas que conviene entender juntas, porque tiran en direcciones opuestas:

- **Para enviar al SRI, la fecha de emisión debe ser la de hoy.** Si intenta
  enviar una retención fechada otro día, el envío se detiene antes de salir con
  el aviso *"la fecha de emisión de la retención (…) debe ser la fecha actual"*.
  Edite la retención, ponga la fecha de hoy y vuelva a enviar.
- **El plazo legal es de cinco días hábiles** desde el documento de sustento. Se
  cuentan de lunes a viernes: sábados y domingos no cuentan. Una factura del
  viernes se puede retener hasta el viernes siguiente sin aviso.

Puestas juntas significan que **una retención atrasada no se arregla poniéndole
una fecha pasada**: el SRI no la aceptaría. Lo que corresponde es emitirla con la
fecha de hoy y aceptar el aviso de plazo, que deja constancia de que se emitió
fuera de término.

### Períodos contables cerrados

Una retención mueve cartera y genera asiento, así que **ninguna operación puede
tocar un período cerrado**:

| Operación | Qué se comprueba |
|-----------|------------------|
| Emitir | Que la fecha de emisión no caiga en un período cerrado |
| Modificar | La fecha nueva **y** aquella con la que está registrada |
| Anular | La fecha de la retención (anular revierte su asiento) |
| Eliminar | La fecha de la retención |

Al modificar se revisan las dos fechas a propósito: cambiar una retención de un
mes cerrado a uno abierto lo alteraría igual. Los períodos se abren y se cierran
en **Contabilidad → Períodos Contables**; reabrir el período permite la operación
de inmediato.

El mismo control rige en **Compras** al registrar, modificar o eliminar una
compra.

### Lo que avisa y usted decide

Son criterios tributarios donde el contador puede tener razón. El sistema **no
guarda nada** hasta que usted los acepte expresamente en el aviso; si continúa,
queda constancia de lo aceptado junto al registro de la retención.

Cada aviso muestra debajo la **norma en la que se apoya**, para que pueda ir a
comprobarla en lugar de tener que creerse el mensaje:

| Aviso | Base legal que se muestra |
|-------|---------------------------|
| Se emite **fuera del plazo** de cinco días hábiles desde el documento de sustento | Art. 50 de la Ley de Régimen Tributario Interno |
| El **código no está vigente** a la fecha de emisión | Catálogo de códigos del SRI vigente a esa fecha |
| La **base de renta es menor a USD 50** | Resolución NAC-DGERCGC14-00787 |
| Se retiene **solo una parte del IVA**, o se retiene IVA sobre un documento sin IVA | Resolución NAC-DGERCGC20-00000061 |
| El **tipo de documento no coincide** con el del documento vinculado | — (no es una regla tributaria, sino una discrepancia entre dos datos del sistema) |

La retención fuera de plazo sigue siendo válida: se avisa, pero se puede
registrar con su fecha real.

Los comprobantes que llegan **descargados del SRI** ya están autorizados: se
registran tal cual y no pasan por estas comprobaciones.

## El porcentaje lo decide el catálogo

El porcentaje de cada línea no se escribe libremente: lo determina el código que
elija, según lo que tenga registrado **Configuración → Retenciones SRI**.

| En el catálogo | En la retención |
|----------------|-----------------|
| Porcentaje **definido** (1.75 %, 10 %, 70 %…) | Ese es el que se aplica. El campo queda **bloqueado** y el sistema rechaza cualquier otro valor |
| Porcentaje en **0** | El concepto es de **tarifa variable**: el campo queda abierto y usted escribe el que corresponda al caso, incluido 0 |

Así, el 0 del catálogo cumple dos funciones a la vez:

- **Conceptos que no retienen**: el **332** (*otras compras de bienes y servicios
  no sujetas a retención*), la compra de inmuebles, el transporte público, los
  pagos con tarjeta de crédito, los rendimientos financieros exentos. Se dejan en
  0 y la retención se guarda con total 0.
- **Conceptos de tarifa variable**: dividendos y pagos al exterior, donde el
  porcentaje depende del convenio de doble tributación, del beneficiario o de la
  tabla aplicable. Al estar en 0, el campo permite escribir el que corresponda.

En el catálogo actual hay 28 códigos de 147 en esa situación.

**Si una tarifa cambió**, no se corrige retención por retención: se actualiza el
código en **Configuración → Retenciones SRI** y desde ahí rige para todas. Y si
un concepto resulta ser de porcentaje variable pero está registrado con una
tarifa fija, basta con ponerlo en 0 para poder escribirlo en cada retención.

## Una sola retención por documento y proveedor

No se puede emitir dos veces la retención del mismo documento **al mismo
proveedor**: el sistema lo bloquea al guardar e indica cuál es la retención que ya
lo cubre (su número, su fecha y su estado), para que pueda encontrarla.

Lo que identifica al documento es la combinación **proveedor + tipo de documento +
número**. Dos proveedores distintos pueden entregarle facturas con el mismo número
—cada uno numera por su cuenta— y ambas se pueden retener sin problema.

Detalles que conviene conocer:

- Un **borrador** ya reserva el documento. Si le aparece el bloqueo y no encuentra
  la retención, búsquela por el número del documento en el listado: puede estar sin
  emitir, o haberla creado otro usuario.
- Las retenciones **eliminadas** o **anuladas** no bloquean: el documento vuelve a
  quedar libre.
- Las retenciones de **otro ambiente** (las de pruebas cuando la empresa ya está en
  producción) tampoco bloquean, igual que no aparecen en el listado.

## Relación con la compra

Una compra que ya tiene retención **no se puede eliminar**: primero hay que
eliminar la retención. Es una protección deliberada, porque la retención declara
al SRI una compra que dejaría de existir.

## Documentos del módulo

Desde la retención guardada están disponibles el **PDF** del comprobante, su
**Excel**, su **XML** y el envío por **correo**, en la barra de acciones al
inicio del formulario.

### Envío automático del correo

Cuando el SRI autoriza la retención, el sistema envía el comprobante al correo
del proveedor y la columna **Correo** del listado pasa a **Enviado**. Esto
también ocurre cuando la autorización no se resuelve en el primer intento: si al
volver a enviar la retención el SRI responde que ya estaba autorizada, el correo
sale igual y el asiento contable se genera en ese momento.

El envío automático requiere que la empresa lo tenga activado en su
configuración de correo y que el proveedor tenga un correo válido en su ficha.
Si el proveedor no tiene correo, la columna se queda en **Pendiente**; puede
enviarlo a mano desde el botón de correo del formulario indicando el
destinatario.

## Errores frecuentes

- **"El tipo de documento de sustento no es válido"**: use uno de los códigos
  admitidos por el SRI.
- **"La base de la retención de IVA supera el IVA del documento"**: puso el
  subtotal donde va el IVA. La base de la retención de IVA es el **valor del
  IVA** de la factura; el porcentaje (30 %, 70 %, 100 %…) se aplica sobre él.
- **"El código de retención no existe en el catálogo del SRI"**: se escribió a
  mano un código que no está en Configuración → Retenciones SRI. Elíjalo de la
  lista, o agréguelo al catálogo si es nuevo. Sin código válido la retención no
  se puede declarar después en el Formulario 103.
- **"El documento de sustento vinculado es del proveedor ..."**: la retención se
  abrió desde una compra y luego se cambió el proveedor. Vuelva a abrirla desde
  la compra correcta.
- **"El código X retiene N % y no admite otro porcentaje"**: el porcentaje lo fija
  el catálogo. Si el que usted necesita es el correcto y el catálogo está
  desactualizado, corrija el código en Configuración → Retenciones SRI; si el
  concepto es de tarifa variable, póngalo en 0 allí y el campo se podrá escribir
  en cada retención.
- **"La fecha de emisión de la retención … debe ser la fecha actual"** al enviar al
  SRI: el comprobante quedó fechado otro día. Abra la retención, ponga la fecha de
  hoy, guarde y vuelva a enviar. Si con eso se pasa del plazo de cinco días hábiles,
  saldrá el aviso correspondiente: acéptelo, es la forma correcta de registrar una
  retención atrasada.
- **No me deja escribir el porcentaje**: el campo se bloquea cuando el código
  elegido tiene una tarifa definida. Es el comportamiento previsto.
- **No puedo eliminar la compra**: elimine antes su retención.
- **"El documento de sustento ... ya está retenido en la retención ..."**: ese
  proveedor ya tiene una retención viva sobre ese mismo documento. El mensaje dice
  cuál es; si es un borrador que no sirve, elimínelo y vuelva a intentar. Recuerde
  que dos proveedores distintos **sí** pueden tener el mismo número de factura.
- **No aparece un código de retención que sí existe**: casi siempre es la
  **vigencia**. Solo se listan los códigos vigentes a la fecha de emisión de la
  retención; la lista avisa cuántos quedaron fuera por ese motivo. Corrija la
  fecha de emisión o la vigencia del código en Configuración → Retenciones SRI.
- **"El SRI devolvió la retención con errores" con motivo "RESPUESTA INESPERADA DEL
  SRI" o "ERROR REPORTADO POR EL SERVICIO DEL SRI"**: el SRI no rechazó la
  retención, su servicio contestó mal (mantenimiento, intermitencia o un error
  interno) y no llegó a recibirla. Espere unos minutos y vuelva a enviar; el
  detalle del historial SRI muestra lo que respondió el servicio. Si el motivo es
  un código del SRI (por ejemplo 35 "ARCHIVO NO CUMPLE ESTRUCTURA XML"), sí es un
  rechazo real: corrija lo que indica el detalle antes de reenviar.

## Historial de cambios

- **1.9** — El envío al SRI ahora comprueba que la **fecha de emisión sea la de hoy**,
  como ya hacían factura de venta, factura de reembolso y liquidación de compra. Antes
  la retención salía hacia el SRI con cualquier fecha y era el propio SRI quien la
  rechazaba. El aviso dice qué fecha tiene y cuál debe tener.
- **1.8** — El **porcentaje lo decide el catálogo**. Si el código tiene una tarifa
  definida, el campo queda bloqueado y no se admite otro valor; si está en 0, el
  concepto es de porcentaje variable —dividendos, pagos al exterior— y se escribe el
  que corresponda. Con esto se corrige además que los códigos que **no retienen**
  (el 332 y los otros 27 conceptos informativos del catálogo) se rechazaran al guardar.
  Una tarifa reformada por el SRI se corrige ahora en Configuración → Retenciones SRI,
  no retención por retención.
  Además, cada aviso muestra la **norma en la que se apoya** (artículo de la ley o
  número de resolución del SRI), para poder revisarla antes de aceptarlo.
- **1.7** — El módulo comprueba ahora los requisitos de fondo de una retención, en
  dos niveles: lo que la haría inválida ante el SRI **impide guardar** (código
  inexistente o de otro impuesto, base mayor que la del documento de sustento —el
  caso típico: retener el IVA sobre el subtotal—, documento de otro proveedor,
  retención a la propia empresa, fecha futura, período contable cerrado); lo que
  es criterio tributario **se avisa y se confirma**, y hasta que se acepta no se
  guarda nada (fuera del plazo de cinco días hábiles, porcentaje distinto al del
  catálogo, código no vigente, base de renta bajo USD 50, retención parcial del
  IVA). Lo aceptado queda registrado junto a la retención.
  Antes, el plazo de cinco días era un bloqueo que impedía registrar cualquier
  retención atrasada; ahora se cuenta en días hábiles —como dice la ley— y se
  puede continuar tras confirmarlo.
  Al emitir desde una compra se arrastra también el **tipo de documento**: si la
  compra era una liquidación o una nota de débito, la retención salía marcada
  como factura.
  El período fiscal se recalcula siempre desde la fecha de emisión.
  Además, el módulo respeta ahora el **cierre contable**: no se puede emitir,
  modificar, anular ni eliminar una retención cuyo período esté cerrado. Antes no
  se comprobaba en ninguna de las cuatro operaciones.
- **1.6** — Al enviar al SRI, cuando el servicio del SRI responde algo que no es su
  formato normal (una falla interna, una página de mantenimiento o una respuesta
  vacía), el aviso y el historial SRI quedaban en "devuelta con errores" sin ningún
  motivo. Ahora siempre se muestra qué respondió el SRI y se indica que se trata de
  una intermitencia del servicio y no de un rechazo del comprobante.
  Además, el envío ya no sigue la redirección que a veces devuelve el frente del SRI
  (era lo que terminaba en "No fue posible comunicarse con los servidores del SRI")
  y reintenta hasta tres veces antes de rendirse; ese mensaje ya no muestra el
  detalle técnico de la conexión.
- **1.5** — Corregido el envío automático del correo al autorizar. Cuando el SRI
  ya tenía la retención autorizada de un intento anterior (el caso típico: el
  primer envío queda "en procesamiento" y el segundo la encuentra resuelta), la
  retención quedaba autorizada pero sin correo enviado y sin asiento contable.
  Ahora ese camino hace lo mismo que una autorización directa.
- **1.4** — Corregido el buscador del listado: los filtros `monto:`, `total:`, `renta:`,
  `iva:` e `isd:` apuntaban a columnas que no existen en la tabla y rompían la búsqueda
  con un error de base de datos. Ahora `monto:` y `total:` usan el total retenido, y
  `renta:`, `iva:` e `isd:` se calculan desde el detalle de la retención.
- **1.3** — La unicidad del documento de sustento ahora considera al **proveedor**:
  antes bastaba con que otro proveedor tuviera una factura con el mismo número para
  que el sistema bloqueara la retención. Se aplica también al editar un borrador, no
  bloquean ya las retenciones de otro ambiente, y el mensaje dice cuál es la retención
  que ocupa el documento.
- **1.2** — El código de retención se puede buscar desde las columnas Código,
  Concepto o % Ret., y la búsqueda cubre todas las columnas del catálogo. La lista
  avisa cuando hay códigos que coinciden pero no están vigentes a la fecha de emisión.
- **1.1** — Nuevo botón Excel en el documento de la retención (junto a PDF y XML).
- **1.0** — Versión inicial.
