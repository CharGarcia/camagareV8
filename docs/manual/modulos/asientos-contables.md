---
titulo: Asientos contables
resumen: Registro contable de cada operación; la mayoría se genera sola desde los documentos.
categoria: Contabilidad
ruta_modulo: modulos/asientos_contables
tipo: modulo
visibilidad: todos
etiquetas: asientos, asiento contable, diario, debe, haber, partida doble, cuadrado, comprobante, contabilidad, imprimir, pdf, excel, documento origen, cuadre con el documento, total de la factura, cuenta por cobrar, cartera, editar asiento desde el documento, pestaña asiento contable, editado a mano, restaurar asiento automático, permisos de contabilidad, documentos migrados, migración, sistema anterior, buscar asiento, buscador, filtros, filtrar asientos, buscar por cuenta, buscar por referencia, libro diario, chips, asiento descuadrado, búsqueda lenta, se queda buscando, filtrar por origen, origen del asiento, módulo de origen, vista previa, costo de ventas, asiento sin costo
version: 1.19
orden: 20
estado: activo
---

Los **asientos contables** son el registro de cada operación en las cuentas. La
inmensa mayoría **no se escribe a mano**: la genera el sistema al guardar una
factura, un ingreso, un egreso, una compra o un traspaso, según la configuración
contable de la empresa.

Este módulo sirve para consultarlos, y para registrar los asientos de diario que
no nacen de ningún documento (provisiones, depreciaciones, ajustes).

## Un asiento tiene que cuadrar

La regla que el sistema no deja saltarse: **el total del Debe debe ser igual al
total del Haber**. Si no cuadran, el mensaje muestra ambas cifras para que vea la
diferencia.

Además:

- Debe tener **al menos un detalle de cuenta**.
- Todos los valores deben ser **mayores a cero**.
- La suma de los detalles debe coincidir con el total del asiento.

## El asiento de un documento debe reflejar su importe

Cuando el asiento **viene de un documento** (factura de venta, recibo de venta,
factura de reembolso, compra, liquidación, nota de crédito, nota de débito,
retención, ingreso o egreso), cuadrar Debe con Haber no alcanza: el asiento tiene que seguir
reflejando el **importe de ese documento**. Al editarlo a mano, el modal lo
muestra en una fila al pie del detalle, junto a los totales:

| Lo que muestra | Qué significa |
|----------------|---------------|
| **TOTAL DOCUMENTO** con el número del documento | El importe contra el que se compara |
| *Coincide con la cartera del asiento* | Todo en orden |
| *Cartera del asiento: … · diferencia: …* | El asiento ya no refleja el documento |
| *Falta la línea de la cuenta por cobrar / pagar* | El asiento perdió la línea de cartera |

Esa fila se **actualiza sola mientras edita**: al cambiar un valor del Debe o del
Haber, al cambiar la cuenta de una línea o al quitar una línea, el aviso vuelve a
calcularse en el momento, sin esperar a guardar. También aparece cuando abre el
asiento de un documento que **todavía no se ha contabilizado** (formulario en
blanco), para armar las líneas sabiendo con qué importe tienen que cerrar.

**Contra qué se compara.** En facturas y recibos de venta, notas de débito,
facturas de reembolso, compras y liquidaciones se mide sobre la línea de la
**cuenta por cobrar** (o por pagar), que es la que el sistema fija en el total
del documento. El Debe total no sirve de referencia: en una venta incluye además
el costo de ventas y el descuento, así que compararlo con el total de la factura
daría diferencia estando todo bien. En los demás documentos —notas de crédito,
retenciones, ingresos y egresos— se compara el **total del Debe** del asiento.
Se aceptan diferencias de hasta **3 centavos**, que son redondeo.

**Qué pasa al guardar.**

- Si hay diferencia, el sistema **avisa y pregunta**: se puede guardar igual
  ("Guardar de todos modos"), y esa decisión queda registrada en la auditoría
  del sistema con las dos cifras.
- Si al asiento le **falta la línea de la cuenta por cobrar/pagar** configurada
  para ese documento, **no se guarda**: sin esa línea no hay forma de comprobar
  que el asiento refleje el documento. Agregue la línea con la cuenta de cartera
  y vuelva a guardar.
- **No se comprueba** cuando el asiento se guarda como **borrador** (igual que el
  cuadre Debe/Haber, se exige recién al registrarlo), en asientos de **diario**
  (no tienen documento), en documentos con total en cero, ni en los orígenes
  cuyo total no es comparable con el asiento por diseño: **nómina** (el asiento
  incluye aportes y provisiones), **consignaciones**, **retornos**, **cambios de
  producto** y los asientos **migrados** del sistema anterior.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha | Obligatoria |
| Tipo de comprobante | Obligatorio |
| Concepto | Obligatorio: explica de qué se trata el asiento |
| Detalle | Al menos una línea, cuadrada y con valores mayores a cero |

## Asientos automáticos frente a asientos de diario

- **Automáticos**: nacen de un documento. Si el documento se modifica, el asiento
  se regenera; si se anula, el asiento se anula.
- **De diario**: los escribe el contador. No dependen de ningún documento.

**Un asiento automático que usted corrija a mano deja de regenerarse.** Desde el
momento en que lo guarda —da igual si lo hizo aquí o en la pestaña *Asiento
contable* del documento— el sistema lo marca como editado a mano y ya no lo
vuelve a armar cuando el documento se guarde otra vez: manda su corrección. Para
devolverlo al automático, use **Restaurar automático** en la pestaña del
documento (ver la sección siguiente).

## Editar el asiento desde el propio documento

Los modales de documento (compras, facturas y recibos de venta, notas de crédito
y débito, ingresos, egresos, liquidaciones de compra, retenciones, importaciones,
traspasos, factura de reembolso, consignaciones de venta, retornos y cambios de
producto) tienen la pestaña **Asiento contable**, y desde ahí se corrige el
asiento sin pasar por el Libro Diario.

**Quién la ve y quién la puede usar** — la pestaña depende del permiso sobre este
módulo (*Contabilidad → Asientos Contables*):

| Permiso | Qué ocurre |
| --- | --- |
| Sin *Ver* | La pestaña **no aparece** en el modal |
| Solo *Ver* | La pestaña se muestra en **solo lectura** |
| *Ver* + *Modificar* | Se puede editar el asiento y guardarlo desde la pestaña |

**Cómo se usa**

1. Abra el documento y vaya a la pestaña **Asiento contable**.
2. Corrija lo que haga falta: cambiar la cuenta (se busca escribiendo código o
   nombre), el Debe o el Haber, la referencia, agregar o quitar líneas.
3. Pulse **Guardar asiento**.

**Lo que se comprueba al guardar** es exactamente lo mismo que en el Libro
Diario: que el Debe sea igual al Haber, que ninguna línea quede sin cuenta, que
el período contable no esté cerrado y que **el asiento siga cuadrando con el
documento** (ver *El asiento de un documento debe reflejar su importe*). El pie
de la pestaña muestra en vivo el importe del documento y si el asiento lo
refleja; si al guardar hay diferencia, se avisa y usted decide.

**Cuándo no se puede editar**

- Mientras el documento **no tenga asiento**: la pestaña muestra la vista previa
  de lo que armarán las reglas contables, y no hay nada que guardar todavía. El
  asiento se crea al guardar el documento o al generar la contabilidad. En
  facturas y recibos de venta ya guardados, la vista previa usa los datos reales
  del documento (incluido el Costo de Ventas que salió del inventario), así que
  muestra lo mismo que se va a registrar; solo mientras se editan sus líneas se
  calcula con los importes de la pantalla, y ahí todavía no puede incluir el costo.
- En un asiento **anulado** que no sea de tipo Diario.

**Excepción: consignaciones de venta, retornos y cambios de producto.** En estos
tres, el asiento (que va **a costo**) solo se registra si todas sus líneas tienen
cuenta; si a la configuración contable le falta alguna, el documento se guardaba
sin asiento y no había forma de arreglarlo desde la pantalla. Por eso ahí la
**vista previa sí es editable**: complete la cuenta que falte y el asiento se
registra al pulsar **Guardar** en el modal del documento — no hay botón *Guardar
asiento* porque todavía no existe un asiento que actualizar. Una vez registrado,
la pestaña funciona como en el resto: se corrige y se guarda desde ahí mismo.

**Restaurar automático** — el botón aparece en cuanto el asiento está marcado
como editado a mano. Descarta las correcciones, vuelve a armar el asiento con la
configuración contable de la empresa y deja el documento otra vez bajo el
automático. La operación queda registrada en la auditoría del sistema.

**Roles de pago** — su pestaña *Asiento contable* (dentro del detalle de cada
empleado) **es de solo lectura**, y también respeta el permiso de ver Asientos
Contables. No se edita ahí porque lo que muestra es el asiento **calculado de ese
empleado**, no un asiento registrado: según cómo se contabilice el rol, el
asiento real puede ser uno solo para toda la nómina o uno por empleado. El
asiento del rol se corrige desde el Libro Diario.

## Cada módulo genera lo suyo al abrirlo

Además de la sincronización que se lanza desde esta pantalla, **cada módulo
contabiliza sus propios documentos cuando se lo abre**: entrar a Facturas de
Venta genera los asientos que les falten a las facturas, entrar a Compras los de
las compras, y así con los demás. Ocurre en segundo plano, sin avisos y sin
intervención de nadie. Ver
[Generación automática de asientos contables](guias/generacion-automatica-de-asientos).

Eso no reemplaza al aviso de esta pantalla, que sigue siendo el lugar donde se
ve **qué quedó sin contabilizar y por qué**: la generación automática es
silenciosa a propósito, y lo que no puede resolver (una cuenta sin configurar, un
período cerrado) se sigue reportando aquí.

## Aviso antes de generar asientos en masa

Al sincronizar asientos, antes de contabilizar nada el sistema revisa la
configuración contable de la empresa y avisa de lo que encuentre: conceptos o
formas de cobro/pago sin cuenta, y **cuentas cuya naturaleza no corresponde al
concepto** (por ejemplo una cuenta de ventas puesta en *Cuenta por cobrar*, o una
cuenta de caja en un concepto de nómina).

Ese último aviso conviene atenderlo antes de continuar: la sincronización genera
los asientos con las cuentas tal como estén configuradas, así que un concepto mal
apuntado se propaga a todos los documentos de golpe y el error solo se nota al
revisar el balance.

## Documentos migrados sin asiento contable

Los documentos que llegaron desde el sistema anterior por la migración **no
generan asiento automático** y **no se revisan** como pendientes. Su
contabilidad es el diario histórico migrado tal cual: lo que vino sin asiento
queda así. La generación en masa y el aviso al abrir los módulos contables solo
miran los documentos **creados en este sistema**.

Si un documento migrado necesita asiento por alguna razón puntual, se registra a
mano desde su pestaña *Asiento contable*.

**Documentos que ya tienen su asiento migrado.** Cuando la migración de
contabilidad trae el diario del sistema anterior, deja cada documento enlazado a
su asiento histórico (es el asiento que se ve en la pestaña *Asiento contable*
del documento). Un documento así **nunca recibe un asiento automático**, sin
importar cómo haya llegado al sistema: ni por la generación en masa, ni al
abrir el módulo, ni al guardar el documento, ni desde **Regenerar** en Asientos
Contables o en Auditoría Contable. Esto cubre también a los documentos que ya
existían en este sistema antes de migrar (por ejemplo, facturas descargadas del
SRI) y que la migración solo enlazó con su asiento histórico: si su contabilidad
ya vino del sistema anterior, no se vuelve a generar. Al intentar guardar a mano
un asiento nuevo sobre uno de estos documentos, el sistema avisa cuál es el
asiento migrado para corregirlo ahí.

**Cómo saber si un documento es migrado.** Se creó en bloque el día de la
migración (todos los de esa carga comparten fecha y hora de creación) y su fecha
de emisión es anterior. La generación en masa nunca lo incluye, así que si
aparece en un aviso de "asiento por generar" es porque se creó en este sistema.

## Diferencias de centavos

En documentos con impuestos, la base y el IVA pueden dejar diferencias de
centavos al redondear. El sistema las lleva automáticamente a la cuenta de
**Ajuste por redondeo** del concepto (se configura en Configuración contable).
Un descuadre mayor que un redondeo sí detiene el proceso: eso indica un error
real, casi siempre una cuenta sin asignar o un documento cuyos totales no
coinciden con sus líneas.

**Cuánto se tolera.** Hasta **3 centavos** en todos los documentos. En **facturas
de compra y liquidaciones de compra** el margen crece con el tamaño del documento:
**1 centavo por cada línea con IVA**, con un mínimo de 3. Una factura de proveedor
de 20 líneas tolera hasta 20 centavos, porque el IVA sumado línea a línea puede
alejarse legítimamente del total de cabecera cuando el emisor redondea distinto.
El ajuste queda visible como una línea más del asiento, contra la cuenta de
redondeo, y nunca enmascara un descuadre de dólares.

Si el asiento de una compra sigue sin cuadrar, el mensaje muestra la diferencia y
el tope aplicado. Lo que hay que revisar es el documento: que el subtotal, el IVA
de las líneas y el importe total sean consistentes. Al volver a guardar la compra
el sistema recalcula los totales desde las líneas.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del asiento: comprobante,
fecha (como *31-07-2026* o *2026-07-31*), concepto y total. Además busca en las
observaciones, el usuario que lo registró y los **documentos y referencias de
sus líneas** (por ejemplo *Egreso 001-101-000000003* o *pago planilla*). Las
columnas **Tipo**, **Origen** y **Estado** no entran en la búsqueda libre: para
filtrar por ellas use la ventana de filtros. Las cuentas contables tampoco (casi
todo asiento usa Caja o Bancos): búsquelas en la pestaña *Detalles* o con el
filtro *Cuenta contable*. Puede escribir varias palabras en cualquier orden y no
importan mayúsculas ni tildes. El total también se encuentra escrito con coma
decimal (*1078,09* encuentra *1.078,09*). Para limpiar, borre el texto o pulse
Escape en el cuadro. Mientras busca, aparece un **círculo girando** al final del
cuadro y la tabla se ve atenuada. Si sigue escribiendo, la búsqueda anterior se
cancela y el listado muestra solo el resultado de lo último que escribió.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Asiento**:

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha del asiento (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (contabilizado, borrador, anulado), tipo, N° comprobante, origen, usuario que registró, total (mínimo y máximo), cuadrado o descuadrado (Debe = Haber), editado a mano, fecha de registro, concepto, observaciones |
| Líneas | Cuenta contable (código o nombre) y referencia / documento: el asiento aparece si **alguna** de sus líneas coincide |

El selector *Origen* lista **todos** los módulos que generan asientos, con su
nombre (factura de venta, compra, ingreso, egreso, retenciones, notas de crédito y
débito, importación, traspaso, conciliación de tarjetas, consignación, retorno y
cambio de productos, facturación de consignación, rol de pagos, activos fijos,
declaraciones de IVA y de retenciones, asiento manual y migración del sistema
anterior), aunque la empresa todavía no tenga asientos de alguno; si la empresa
tiene asientos con otro origen, también aparece, al final. Los selectores *Tipo* y
*Usuario que registró* listan solo lo que la empresa ya tiene en sus asientos.

**Pestaña Detalles** (lo que hay dentro del asiento). Es un único cuadro,
**Buscar libremente dentro de los asientos**: escriba una cuenta (código o
nombre), una referencia, un documento, un cliente, proveedor o empleado, un
centro de costo, un proyecto o un valor del Debe o Haber, y aparece la lista de
**cada línea que coincide** con el asiento al que pertenece (número, fecha y
estado). Un clic en la fila deja el listado mostrando solo ese asiento; el
ícono de la derecha lo abre directamente. Si usted no tiene acceso total al
módulo, solo ve líneas de los asientos que registró.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Imprimir en PDF o Excel

Al abrir un asiento ya guardado aparece, debajo del título del modal, una barra
con dos botones:

- **PDF** (ícono rojo): descarga el asiento con su cabecera, el detalle de
  cuentas (con centro de costo, proyecto y documento/ref) y los totales de
  Debe y Haber.
- **Excel** (ícono verde): la misma información en una hoja de cálculo.

Estos botones no aparecen en un asiento nuevo sin guardar, porque todavía no
tiene número de comprobante.

## Ver el documento que originó el asiento

Cuando el asiento nace de un documento (factura de venta, compra, ingreso,
egreso, nota de crédito/débito, retención, liquidación de compra, importación,
consignación, etc.), la misma barra muestra el botón **Ver Documento**: abre
una ventana de solo lectura con el documento completo, sin salir del asiento.
El botón no aparece en asientos manuales (tipo Diario) ni en los generados por
nómina, activos fijos, declaraciones o traspasos, porque esos procesos no
tienen un documento individual con tercero que mostrar.

## Errores frecuentes

- **"El asiento no está cuadrado"**: el mensaje muestra el total del Debe y del
  Haber; la diferencia le dice qué línea falta o sobra.
- **"El asiento debe contener al menos un detalle de cuenta"**: falta añadir
  líneas.
- **"El asiento no cuadra con el documento"**: la cartera del asiento (o su total
  Debe) dejó de coincidir con el importe del documento. Revise la línea que
  cambió; si el ajuste es correcto, confirme con "Guardar de todos modos".
- **"El asiento no tiene ninguna línea con la cuenta de cartera configurada"**:
  falta la línea de la cuenta por cobrar/pagar del documento. Agréguela y
  vuelva a guardar.
- **No veo la pestaña "Asiento contable" en el documento**: no tiene permiso de
  *Ver* sobre Asientos Contables. Se asigna en `/config/permisos-modulos`.
- **Veo la pestaña pero no puedo escribir en ella**: tiene *Ver* pero no
  *Modificar* sobre Asientos Contables.
- **Corregí el asiento y al reguardar el documento volvió a cambiar**: ya no
  ocurre. Desde que un asiento se guarda a mano queda marcado y el sistema no lo
  regenera. Si lo que quiere es justo lo contrario, use **Restaurar automático**.
- **"Aún no se ha generado el asiento contable"**: el documento todavía no está
  contabilizado. Guárdelo (o genere la contabilidad) y vuelva a la pestaña.

## Historial de cambios

- **1.19** — En facturas y recibos de venta guardados que aún no tienen asiento, la vista previa de la pestaña *Asiento contable* se arma con los datos reales del documento: ahora muestra el Costo de Ventas y el Inventario, el IVA por tarifa y el reparto por categoría, igual que el asiento que se registrará. Antes se calculaba con los importes de la pantalla y el costo salía siempre en 0 (o aparecía «El asiento no cuadra»).
- **1.18** — La búsqueda del listado y la de la pestaña *Detalles* son mucho más rápidas con muchos asientos: medido con 200.000 asientos, la búsqueda libre pasa de 7 a 33 segundos a entre 1,5 y 3 segundos, y en *Detalles* lo que aparece poco (un número de documento, un monto) pasa de hasta 40 segundos a entre 1 y 1,5 segundos. Encuentran lo mismo que antes; además, el total ahora también se encuentra escrito con coma decimal. Mientras se busca, el resto del sistema ya no queda esperando, y una búsqueda nueva cancela la anterior. El selector *Origen* de la ventana de filtros lista todos los orígenes con su nombre, aunque la empresa todavía no tenga asientos de alguno.
- **1.17** — Los **cambios de productos migrados** no reciben asiento por ninguna vía: ni en la generación en masa, ni desde Auditoría Contable, ni al abrir su pestaña *Asiento contable* (que ahora lo indica), ni al cambiarles el estado. Antes se les podía generar, aunque el sistema anterior no contabilizaba los cambios. Los que ya lo recibieron se detectan y se quitan (eliminación lógica) con `database/diagnosticos/20260916_cambios_migrados_con_asiento.sql`.
- **1.16** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas del asiento (salvo Tipo, Origen y
  Estado) y en observaciones, usuario y documentos/referencias de las líneas.
  Los filtros pasan a una **ventana propia** (botón del embudo, se aplican con
  *Aplicar*) con dos pestañas: **Asiento** (criterios nuevos: estado borrador,
  tipo y origen como listas de lo usado, usuario, cuadrado/descuadrado, editado
  a mano, fecha de registro, observaciones, cuenta contable y referencia de las
  líneas) y **Detalles**, un cuadro de **búsqueda libre dentro de las líneas**
  (cuenta, referencia, tercero, centro de costo, proyecto, valores) que dice a
  qué asiento pertenece cada coincidencia. El filtro por tipo ya no distingue
  mayúsculas (*VENTAS* y *ventas* son el mismo tipo). Nueva sección *Buscar y
  filtrar el listado*.
- **1.15** — Un documento que ya está enlazado a su asiento migrado del sistema anterior no vuelve a recibir asiento automático por ninguna vía (generación en masa, apertura del módulo, guardado del documento, Regenerar en Asientos o Auditoría). Antes solo se protegían los documentos que la migración había insertado; los que ya existían y solo se enlazaron (p. ej. facturas descargadas del SRI) recibían un segundo asiento y quedaban duplicados.
- **1.14** — Los documentos migrados ya no se revisan: se retiran el aviso azul de "migrados sin asiento" y el botón **Generar asientos a los migrados**. Lo que vino de la migración sin asiento queda así; solo se revisan los documentos creados en este sistema.
- **1.13** — El aviso al abrir el módulo indica el módulo y los números de los documentos migrados sin asiento, no solo la cantidad.
- **1.12** — Las consignaciones migradas (y sus retornos, cambios y facturaciones) quedan fuera del aviso de migrados sin asiento y del botón de generar: el sistema anterior no las contabilizaba.
- **1.11** — Botón **Generar asientos a los migrados** en el aviso de documentos migrados sin asiento: genera, previa confirmación de que la migración de contabilidad ya se volvió a correr, los asientos de los migrados que el sistema anterior nunca contabilizó. Queda en la auditoría.
- **1.10** — Aviso de documentos migrados sin asiento contable: nota informativa (azul) al abrir los módulos contables y en el resumen de la generación en masa, con la cantidad por módulo y los documentos. No los genera; explica cómo resolverlo (re-migrar contabilidad o registrar el asiento desde el documento).
- **1.9** — El margen de redondeo en facturas y liquidaciones de compra ya no es fijo: 1 centavo por línea con IVA, mínimo 3. El mensaje de descuadre indica el tope aplicado y pide revisar los totales del documento.
- **1.8** — La pestaña llega también a consignaciones de venta, retornos y cambios de producto: ahí la vista previa se puede completar a mano para registrar el asiento junto con el documento cuando falta alguna cuenta. En roles de pago la pestaña sigue siendo de solo lectura (muestra el asiento calculado del empleado, no uno registrado).
- **1.7** — El asiento se puede corregir y guardar desde la pestaña *Asiento
  contable* del propio documento, con las mismas validaciones del Libro Diario.
  La pestaña solo aparece con permiso de ver Asientos Contables y solo se edita
  con permiso de modificar. Un asiento corregido a mano deja de regenerarse al
  reguardar el documento; se vuelve al automático con **Restaurar automático**.
- **1.6** — El aviso de cuadre contra el documento se actualiza en vivo al editar
  (montos, cuenta de la línea o líneas quitadas) y también se muestra al abrir el
  asiento de un documento que todavía no se ha contabilizado.
- **1.5** — Al editar el asiento de un documento, el modal muestra el total de ese
  documento y avisa si el asiento deja de reflejarlo; se puede guardar igual
  confirmando, salvo que falte la línea de la cuenta por cobrar/pagar.
- **1.4** — Corregido: el botón **Guardar Asiento** aparecía apagado y no dejaba
  registrar ni actualizar el asiento desde el modal.
- **1.3** — Cada módulo genera en segundo plano los asientos que le faltan al abrirlo, sin mostrar mensajes.
- **1.2** — Al sincronizar asientos, el resumen previo avisa también de las
  cuentas configuradas con una naturaleza que no corresponde al concepto, antes
  de generar los asientos.
- **1.1** — Botones de impresión en PDF y Excel del asiento, y botón para ver
  el documento origen (factura, compra, egreso, etc.) sin salir del modal.
- **1.0** — Versión inicial.
