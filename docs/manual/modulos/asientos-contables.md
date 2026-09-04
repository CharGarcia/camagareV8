---
titulo: Asientos contables
resumen: Registro contable de cada operación; la mayoría se genera sola desde los documentos.
categoria: Contabilidad
ruta_modulo: modulos/asientos_contables
tipo: modulo
visibilidad: todos
etiquetas: asientos, asiento contable, diario, debe, haber, partida doble, cuadrado, comprobante, contabilidad, imprimir, pdf, excel, documento origen, cuadre con el documento, total de la factura, cuenta por cobrar, cartera, editar asiento desde el documento, pestaña asiento contable, editado a mano, restaurar asiento automático, permisos de contabilidad, documentos migrados sin asiento, migración, sistema anterior, aviso informativo
version: 1.11
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
  asiento se crea al guardar el documento o al generar la contabilidad.
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
generan asiento automático**. Su contabilidad viene en el diario histórico
migrado, y contabilizarlos otra vez duplicaría los saldos. Por eso la generación
en masa los deja de lado y no los cuenta como pendientes.

Lo normal es que cada documento migrado quede **enlazado** a su asiento
histórico y lo muestre en su pestaña *Asiento contable*. Cuando eso no ocurre,
el documento aparece sin asiento aunque no haya ningún error de configuración.
Para que no pase desapercibido, el sistema avisa en dos momentos:

- **Al abrir** Asientos Contables, Estados Financieros, Balance de Comprobación
  o Mayores: si hay documentos migrados sin asiento, aparece una nota azul con
  la cantidad. Si además hay pendientes normales, la nota va dentro de la misma
  pregunta de "¿Desea generarlos ahora?".
- **Al terminar la generación en masa**: el resumen incluye un bloque
  *Información* con el total por módulo y los primeros números de documento.

Es un aviso informativo, no un error: el botón *Generar* de los pendientes
normales no los contabiliza. Para resolverlo, en este orden:

1. **Volver a correr la migración de contabilidad** de la empresa, con el rango
   completo de fechas. Es segura de repetir: no duplica asientos y vuelve a
   enlazar cada documento con su asiento histórico por el código del diario. Los
   que sí tenían asiento en el sistema anterior desaparecen del aviso con este
   paso.
2. **Generar asientos a los migrados** que sigan sin asiento después de
   re-migrar. Son documentos que el sistema anterior nunca contabilizó, así que
   generarlos con la configuración contable actual no duplica nada. El botón
   está en el propio aviso azul al abrir el módulo y pide marcar una casilla de
   confirmación de que la migración de contabilidad ya se volvió a correr.
   Procesa módulo por módulo con barra de progreso, igual que la generación
   normal, y al final muestra lo generado y lo que no pudo generarse (por
   ejemplo, una cuenta sin configurar). La acción queda registrada en la
   auditoría del sistema.
3. **Registrar el asiento desde el propio documento**, en su pestaña *Asiento
   contable*, para los casos puntuales que la generación no pudo resolver.

**Cuidado con el orden.** Si se usa *Generar asientos a los migrados* antes de
volver a correr la migración de contabilidad, un documento que sí tenía asiento
histórico pero todavía no estaba enlazado recibiría un segundo asiento. Por eso
la casilla de confirmación.

Si después de re-migrar el aviso persiste, los documentos que quedan son los que
no tenían asiento en el sistema anterior, o cuyo tipo no lo enlaza la migración
(por ejemplo roles de pago). Para verlos uno por uno, con estado y total, está la
consulta `database/diagnosticos/20260904_migrados_sin_asiento.sql`.

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
