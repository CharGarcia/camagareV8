---
titulo: Automatizaciones
resumen: Tareas programadas que el sistema ejecuta solo, a la hora y con la frecuencia que usted defina.
categoria: Herramientas
ruta_modulo: modulos/automatizaciones
tipo: modulo
visibilidad: todos
etiquetas: automatizaciones, automatizacion, tareas programadas, tarea programada, programar, cron, robot, automatico, automatica, ejecutar solo, ejecutar ahora, frecuencia, cada dia, diario, semanal, mensual, cada hora, cada minutos, horario, proxima ejecucion, ultima ejecucion, historial de ejecuciones, enviar al sri automatico, enviar facturas al sri, enviar correo automatico, reenviar correos, correo duplicado, estado de cuenta automatico, cobranza automatica, recordatorio de pago, aviso whatsapp, mensajes no leidos, aviso iva, iva a pagar, rol de pagos automatico, quincena, nomina semanal, suscripciones, facturacion recurrente, generar facturas suscripciones, cobro nuvei, aviso de vencimiento, devengar ingresos, car wash, recordatorio de cita, cambio de aceite, aviso de mantenimiento, plantilla whatsapp
version: 1.0
orden: 0
estado: activo
---

Las **Automatizaciones** son tareas que el sistema ejecuta por su cuenta, sin que
nadie tenga que entrar a pulsar un botón: enviar al SRI las facturas en borrador,
mandar por correo las facturas autorizadas, enviar estados de cuenta, generar el
rol de pagos, facturar suscripciones, recordar citas de Car-Wash, etc. Usted elige
**qué** tarea, **sobre qué módulo** y **cada cuánto** se ejecuta; el sistema se
encarga del resto y deja un historial de cada ejecución.

## Qué es y para qué sirve

Cada automatización es una tarea programada de la empresa activa. Tiene:

- un **módulo** (sobre qué tipo de documento o proceso actúa),
- una **acción** (qué hace),
- unas **opciones** propias de esa acción (plantilla de WhatsApp, asunto del
  correo, días de anticipación…),
- una **programación** (todos los días a una hora, una vez por semana, una vez al
  mes, cada cierto número de horas o de minutos).

El servidor revisa constantemente qué automatizaciones activas ya llegaron a su
**próxima ejecución** y las ejecuta. Al terminar, calcula la siguiente fecha y
guarda el resultado en el historial. Las horas se calculan con la hora de Ecuador
(Guayaquil).

Las tareas que se ejecutan trabajan con todos los pendientes que encuentren en
ese momento (por ejemplo, todas las facturas en borrador), no con un número fijo
por corrida.

## Cómo se usa

### Crear una automatización

1. Pulse **Nueva**.
2. Escriba el **Nombre** (por ejemplo: *Enviar facturas al SRI — Noche*).
3. Elija el **Módulo** y luego la **Acción**. Debajo de la acción aparece una
   descripción de lo que hace.
4. Si la acción tiene opciones, aparecen en un recuadro gris debajo. Cada opción
   trae su texto de ayuda y un valor por defecto.
5. En **Programación**, elija **¿Cada cuánto se ejecuta?** y complete el día y la
   hora (o el número de horas o minutos). Debajo se muestra un resumen en texto,
   por ejemplo *«Se ejecutará todos los días a las 20:00.»*
6. Deje el **Estado** en **Activo** y pulse **Guardar**.

La descripción de la automatización se arma sola con el módulo, la acción y el
resumen de la programación.

### Revisar, cambiar o ejecutar una automatización

Haga clic sobre la fila en el listado. Se abre **Editar Automatización** con:

- los mismos campos de la creación, para cambiar lo que necesite y **Guardar**;
- el **Historial de ejecuciones**, con las 5 ejecuciones más recientes;
- el botón **Ejecutar ahora**, que la corre en ese momento sin esperar a su
  horario (pide confirmación y muestra *«Ejecutando…»* mientras trabaja);
- el botón **Eliminar**, en la parte izquierda del pie de la ventana.

Si su usuario no tiene permiso para modificar, al hacer clic se abre
**Detalle de Automatización**, de solo lectura, con el historial.

### Pausar una automatización

Cambie el **Estado** a **Inactivo** y guarde. Una automatización inactiva no se
ejecuta sola y no tiene próxima ejecución. Para reanudarla, vuelva a ponerla en
**Activo**.

## El listado

Columnas: **Nombre** (con el establecimiento debajo, si tiene uno),
**Módulo**, **Acción**, **Frecuencia**, **Próx. ejecución**, **Últ. ejecución**,
**Resultado** y **Estado**. Módulo, acción y frecuencia se muestran con su nombre
corto (por ejemplo `facturas_venta`, `enviar_sri`, `diario`).

- **Estado**: *activo*, *inactivo* o *en_proceso* (mientras se está ejecutando).
- **Resultado**: el de la última ejecución, *exitoso* o *error*. Si nunca se ha
  ejecutado, aparece un guion y en *Últ. ejecución* dice *Sin ejecuciones*.

El buscador (**Buscar en todas las columnas...**) busca por nombre,
establecimiento, descripción, fechas de próxima y última ejecución y usuario que
la creó. El botón de filtros abre **Filtros de automatizaciones**, con: Nombre,
Estado, Último resultado (Exitoso, Error, Sin ejecuciones), Módulo, Acción,
Frecuencia, Próxima ejecución, Última ejecución, Establecimiento, Usuario que la
creó y Descripción. Los selectores de módulo, acción, frecuencia, establecimiento
y usuario solo ofrecen lo que la empresa ya usa en alguna automatización.

Junto al buscador están el botón para mostrar u ocultar columnas y los botones
**PDF** y **Excel**, que exportan el listado con los filtros aplicados. Las
columnas se ordenan haciendo clic en su encabezado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Nombre | Sí | Nombre con el que reconocerá la tarea en el listado (hasta 150 caracteres). |
| Estado | Sí | **Activo** (se ejecuta según su programación) o **Inactivo** (en pausa). |
| Módulo | Sí | Sobre qué tipo de documento o proceso actúa la tarea. |
| Acción | Sí | Qué hace la tarea. Se habilita al elegir el módulo. |
| Opciones de la acción | Según la acción | Campos propios de cada acción (ver la tabla de tareas disponibles). |
| ¿Cada cuánto se ejecuta? | Sí | **Todos los días a una hora**, **Una vez por semana**, **Una vez al mes**, **Cada cierto número de horas** o **Cada cierto número de minutos**. |
| Día de la semana | Solo semanal | De lunes a domingo. |
| Día del mes | Solo mensual | Del 1 al 31. |
| Hora | Diario, semanal y mensual | Hora de la ejecución. Por defecto 20:00. |
| Repetir cada (horas) | Solo cada N horas | De 1 a 24. Por defecto 6. |
| Repetir cada (minutos) | Solo cada N minutos | De 1 a 59. Por defecto 30. |

## Tareas disponibles

Los textos son los que muestra la ventana al elegir cada acción. Entre paréntesis
va el valor que trae cada opción por defecto.

| Módulo | Tarea | Qué hace | Opciones |
|--------|-------|----------|----------|
| Facturas de venta | Enviar al SRI | Autoriza y envía al SRI todos los documentos en estado borrador | Ninguna. |
| Facturas de venta | Enviar correo | Envía por email todos los documentos autorizados con correo pendiente | **Reintentar los que fallaron antes** (activado). |
| Retenciones en compras | Enviar al SRI / Enviar correo | Mismas acciones que en Facturas de venta. | Las mismas. |
| Notas de crédito | Enviar al SRI / Enviar correo | Mismas acciones que en Facturas de venta. | Las mismas. |
| Liquidaciones en compras | Enviar al SRI / Enviar correo | Mismas acciones que en Facturas de venta. | Las mismas. |
| Guías de remisión | Enviar al SRI / Enviar correo | Mismas acciones que en Facturas de venta. | Las mismas. |
| Avisos de WhatsApp | Avisar mensajes no leídos | Envía un aviso (por WhatsApp) a los números configurados cuando hay chats con mensajes sin leer durante más del umbral definido en la configuración de WhatsApp. | Ninguna: todo se toma de la configuración de WhatsApp. |
| Declaración de IVA | Avisar IVA a pagar (Correo) | Calcula el IVA a pagar del período (por defecto el mes en curso, acumulado hasta la fecha) y envía un correo con el desglose: IVA en ventas, crédito tributario, retenciones y total a pagar. Útil para decidir a tiempo si conviene registrar más compras (crédito) antes del cierre del mes. | **Período a calcular**: Mes en curso (aún abierto, hasta hoy) o Mes anterior (ya cerrado, a declarar) (Mes en curso). **Correos de destino (opcional)**: si queda vacío se usa el correo de la empresa. **Asunto del correo** (`IVA a pagar {periodo}: {iva_a_pagar}`). **Mensaje (antes del desglose)**. Etiquetas: `{empresa} {periodo} {iva_ventas} {credito_tributario} {credito_anterior} {retenciones} {retenciones_efectuadas} {iva_a_pagar} {saldo_favor} {fecha_limite}`. |
| Car-Wash | Recordatorio de próxima cita (Correo) | Envía un correo a los clientes cuyo vehículo tiene la próxima cita (fijada en la orden de Car-Wash) dentro de los días indicados. Cada cita se avisa una sola vez, así que puede programarse a diario. Destinatario: el correo del vehículo o, si no tiene, el del cliente de la orden. | **Avisar con cuántos días de anticipación** (1; 0 = solo las citas de hoy). **Asunto del correo** (`Recordatorio de su próxima cita - {empresa}`). **Mensaje**. Etiquetas: `{cliente} {placa} {marca} {fecha_cita} {empresa} {orden} {motivo}`. |
| Car-Wash | Recordatorio de próxima cita (WhatsApp) | Envía por WhatsApp, con una plantilla aprobada por Meta, el recordatorio de la próxima cita de Car-Wash a los clientes con cita dentro de los días indicados. Cada cita se avisa una sola vez. Teléfono: el del vehículo o, si no tiene, el del cliente. | **Avisar con cuántos días de anticipación** (1). **Plantilla de WhatsApp**: variables del cuerpo, en orden: {{1}} cliente, {{2}} placa, {{3}} fecha de la cita, {{4}} empresa, {{5}} N.° de orden. |
| Car-Wash | Recordatorio de próxima cita (Correo y WhatsApp) | Envía el recordatorio de la próxima cita de Car-Wash por los DOS canales en la misma ejecución: correo (si hay correo) y WhatsApp con plantilla aprobada por Meta (si hay teléfono). Cada cita se avisa una sola vez por canal. Destinatarios: los del vehículo o, si no tiene, los del cliente de la orden. | Las de las dos tareas anteriores: días de anticipación, asunto, mensaje del correo y plantilla de WhatsApp. |
| Car-Wash | Aviso de próximo mantenimiento (Correo) | Avisa por correo a los clientes cuyo vehículo está por llegar (o ya pasó) al km del próximo cambio de aceite de motor, de caja u otro servicio de mantenimiento. El km de hoy se estima con los km registrados en las órdenes. Un mensaje por vehículo con todos sus servicios por avisar; cada cambio se avisa una vez y, si sigue vencido, se recuerda según lo configurado. Puede programarse a diario. Destinatario: el correo del vehículo o, si no tiene, el del cliente de su última orden. | **Si sigue vencido, volver a avisar cada (días)** (7; 0 = solo el primer aviso). **Máximo de recordatorios por vencido** (3). **Km por mes de un vehículo con una sola lectura** (1000). **Servicios a avisar (vacío = todos)**. **Asunto del correo** (`Mantenimiento de su vehículo {placa} - {empresa}`). **Mensaje**. Etiquetas: `{cliente} {placa} {marca} {servicios} {km_estimado} {empresa}`. |
| Car-Wash | Aviso de próximo mantenimiento (WhatsApp) | Avisa por WhatsApp, con una plantilla aprobada por Meta, a los clientes cuyo vehículo está por llegar (o ya pasó) al km del próximo cambio de aceite u otro servicio de mantenimiento. Un mensaje por vehículo; cada cambio se avisa una vez y, si sigue vencido, se recuerda según lo configurado. Teléfono: el del vehículo o, si no tiene, el del cliente de su última orden. | Las cuatro primeras de la tarea anterior y **Plantilla de WhatsApp**: {{1}} cliente, {{2}} placa, {{3}} servicios con su km, {{4}} empresa, {{5}} km estimado de hoy. El cuerpo no puede terminar en una variable. |
| Car-Wash | Aviso de próximo mantenimiento (Correo y WhatsApp) | Envía el aviso de próximo mantenimiento por los DOS canales en la misma ejecución: correo (si hay correo) y WhatsApp con plantilla aprobada por Meta (si hay teléfono). Cada canal lleva su propio control de lo ya avisado. | Las de las dos tareas anteriores. |
| Cuentas por cobrar | Enviar estado de cuenta (Correo) | Envía por correo a cada cliente con saldo pendiente su estado de cuenta, en el nivel de detalle elegido (total, por factura o por línea). | **Nivel de detalle**: Total por cobrar vencido, Total por cobrar general, Detallado por cada factura o Detallado por cada línea de factura (Detallado por cada factura). **Considerar solo facturas vencidas** (activado). **Mínimo de días vencido** (0 = sin mínimo). **Asunto del correo** (`Estado de cuenta - {empresa}`). **Mensaje (antes del detalle)**. Etiquetas: `{cliente} {empresa} {total_general} {total_vencido} {num_facturas}`. |
| Cuentas por cobrar | Enviar estado de cuenta (WhatsApp) | Envía por WhatsApp a cada cliente con saldo pendiente, usando una plantilla aprobada por Meta. | **Plantilla de WhatsApp**. **Considerar solo facturas vencidas** (activado). **Mínimo de días vencido** (0). **Detalle del PDF adjunto**: Detallado por cada factura o por cada línea de factura; solo aplica si la plantilla lleva un PDF adjunto. |
| Roles de pago | Generar rol mensual | Seleccione el día que desea generar el rol de pagos. | **Período a generar**: Mes anterior (recomendado: ya cerrado) o Mes en curso (Mes anterior). |
| Roles de pago | Generar quincena | Seleccione qué día quiere generar la quincena en la parte de abajo. | Ninguna. Siempre genera los días 1 al 15 del mes en que se ejecuta. |
| Roles de pago | Generar semana | Seleccione cuando quiere que se genere más abajo. (solo se puede generar hasta 3 veces por mes) | Ninguna. Genera la última semana ya completa (lunes a domingo) y solo las tres primeras semanas del mes; las demás se liquidan con el rol mensual. |
| Suscripciones | Generar facturación | Genera las facturas (en borrador) de las suscripciones con períodos vencidos. El envío al SRI y por correo lo realizan las automatizaciones de Facturas de venta. | **Serie (punto de emisión)**. **Texto adicional en cada ítem (opcional)**. **Concepto de info. adicional (opcional)**. **Detalle de info. adicional (opcional)**. **Personalización por periodicidad (opcional)**: pestañas Mensual, Bimestral, Trimestral, Semestral, Anual, Diario, Semanal y Quincenal para cambiar esos tres textos por periodicidad. Etiquetas: `{mes} {MES} {mes_num} {anio} {mes_anio} {fecha} {anio_mes} {anio_mes_fin} {fecha_fin} {mes_ant} {mes_anio_ant} {anio_mes_ant}`. |
| Suscripciones | Devengar ingresos del mes | NO hace falta programarla: el sistema ya devenga solo, todos los días, en todas las empresas (asiento NIIF 15 que pasa al ingreso lo diferido de los meses cumplidos y provisiona el mes caído). Esta acción solo sirve para forzar una corrida en un horario propio; correrla de más no duplica nada. | Ninguna. |
| Suscripciones | Cobrar suscripciones (Nuvei) | Cobra con la tarjeta guardada (Nuvei) los períodos ya generados que quedaron pendientes de cobro. Si el cobro es aprobado, registra el Ingreso (con su asiento contable) y avisa al cliente. Si es rechazado, avisa al cliente y a la empresa, y reintenta en las próximas corridas hasta el tope de intentos. | **Máximo de intentos** (3). |
| Suscripciones | Enviar aviso de vencimiento (Correo) | Envía un correo a los clientes cuya suscripción vence en exactamente N días. Usa el correo configurado en la empresa. | **Avisar con anticipación (días)** (5). **Asunto del correo** (`Su suscripción vence el {fecha_vencimiento}`). **Cuerpo del correo**. Etiquetas: `{cliente} {empresa} {fecha_vencimiento} {dias} {periodicidad}`. |
| Suscripciones | Enviar aviso de vencimiento (WhatsApp) | Envía un WhatsApp a los clientes cuya suscripción vence en exactamente N días, usando una plantilla aprobada por Meta. El mensaje lo define la plantilla; sus variables se rellenan automáticamente EN ESTE ORDEN: {{1}}=cliente, {{2}}=fecha de vencimiento, {{3}}=días, {{4}}=periodicidad, {{5}}=empresa. | **Avisar con anticipación (días)** (5). **Plantilla de WhatsApp**. |

> **Importante:** aunque *Enviar al SRI* y *Enviar correo* aparecen en Retenciones
> en compras, Notas de crédito, Liquidaciones en compras y Guías de remisión, por
> ahora solo trabajan en **Facturas de venta**. En los demás módulos la ejecución
> termina con el mensaje *«El envío automático al SRI por ahora solo está
> disponible para Facturas de venta.»* o *«El envío de correo automático por ahora
> solo está disponible para Facturas de venta.»*

### Detalles de algunas tareas

- **Enviar al SRI** toma las facturas en **borrador** del ambiente (Pruebas o
  Producción) que tiene configurado la empresa en la ficha de Emisor Electrónico.
- **Enviar correo** toma solo facturas **autorizadas** cuyo correo está pendiente;
  nunca reenvía una factura cuyo correo ya se envió. Con *Reintentar los que
  fallaron antes* activado, también vuelve a intentar las que dieron error.
- **Avisar mensajes no leídos** se configura en **Configuración de WhatsApp**: allí
  se activan los avisos, se define el umbral de minutos, el tiempo mínimo entre
  avisos y los números que los reciben. El aviso enumera hasta 10 chats pendientes.
- **Generar rol mensual / quincena / semana** no duplica: si la corrida del
  período ya existe, informa *«Ya existe la corrida …; no se duplicó.»* Cuando la
  genera, el mensaje es *«Corrida … generada. Revísala en Roles de Pago antes de
  pagar.»*
- Las tareas de WhatsApp solo ofrecen las plantillas **aprobadas por Meta** de la
  empresa (ver [Plantillas de WhatsApp](modulos/plantillas-whatsapp)).

## Programación: cuándo se ejecuta

| Opción | Cuándo se ejecuta |
|--------|-------------------|
| Todos los días a una hora | Cada día a la hora indicada. Si al guardar ya pasó esa hora, empieza mañana. |
| Una vez por semana | El día y la hora indicados. Si el día elegido es hoy, la primera ejecución queda para la semana siguiente. |
| Una vez al mes | El día del mes y la hora indicados. Si en este mes ya pasó, empieza el mes siguiente. |
| Cada cierto número de horas | Cada N horas, contadas desde que se guarda o desde la última ejecución. |
| Cada cierto número de minutos | Cada N minutos, contados desde que se guarda o desde la última ejecución. |

Cada vez que se guarda una automatización activa, su próxima ejecución se vuelve a
calcular desde ese momento.

## Historial de ejecuciones

En la ventana de cada automatización, el **Historial de ejecuciones** muestra las
5 más recientes con: **Iniciado**, **Finalizado**, **Duración**, **Resultado**,
**Registros** (cuántos documentos o mensajes procesó), **Por** (*cron* si la
ejecutó el sistema solo, *manual* si se usó **Ejecutar ahora**) y **Mensaje**.

Cuando la ejecución termina en **error**, el mensaje dice *«Error en la
ejecución.»*; al pasar el mouse sobre él se ve el motivo técnico.

Muchas situaciones no son errores sino avisos: la ejecución queda como
**exitoso** con 0 registros y el mensaje explica qué faltó (por ejemplo, que no
había nada pendiente o que falta elegir la plantilla).

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Entrar al listado, buscar, exportar a PDF y Excel y abrir el detalle de solo lectura con el historial. |
| Crear | Botón **Nueva**. |
| Modificar | Abrir la automatización para editarla, **Guardar** cambios y usar **Ejecutar ahora**. |
| Eliminar | Botón **Eliminar**. |
| Acceso total | Ver las automatizaciones de toda la empresa. Sin acceso total, cada usuario ve solo las que él creó. |

## Reglas de negocio

- El nombre, el módulo, la acción y la frecuencia son obligatorios.
- Solo se ejecutan solas las automatizaciones en estado **Activo**. Mientras una
  se está ejecutando su estado es **en_proceso** y no se puede volver a lanzar con
  **Ejecutar ahora**.
- Después de cada ejecución (automática o con **Ejecutar ahora**), la
  automatización queda en **Activo** con su próxima ejecución calculada. Si usa
  **Ejecutar ahora** sobre una automatización **Inactiva**, esta queda activa.
- Las tareas se ejecutan en nombre del usuario que creó la automatización.
- **Correo duplicado de facturas**: si la empresa tiene activada la opción *«Enviar
  correos de forma automática después de autorizar en el SRI»*, el sistema muestra
  un aviso y **no permite guardar** una automatización de *Facturas de venta →
  Enviar correo*, porque el cliente recibiría dos correos de la misma factura.
  Desactive esa opción en *Empresa → Configuración → Correo* si prefiere usar la
  automatización.
- Eliminar no borra físicamente: la automatización deja de aparecer y deja de
  ejecutarse. Cada creación, cambio y eliminación queda registrada en la
  auditoría del sistema.

## Integraciones con otros módulos

- **Facturas de venta**: envío al SRI de los borradores y envío de correos de las
  autorizadas.
- **[Suscripciones](modulos/suscripciones)**: generación de las facturas de los
  períodos vencidos (en borrador; el envío al SRI y el correo los hacen las
  automatizaciones de Facturas de venta), cobro con Nuvei, avisos de vencimiento
  y devengo de ingresos.
- **[Cuentas por cobrar](modulos/cuentas-por-cobrar)**: estados de cuenta por
  correo o WhatsApp.
- **[Declaración de IVA](modulos/declaracion-iva)**: aviso por correo del IVA a
  pagar.
- **[Roles de pago](modulos/roles-pago)**: generación de la corrida mensual, de
  la quincena o semanal, para revisarla antes de pagar.
- **[Car-Wash](modulos/car-wash)**: recordatorios de cita y avisos de
  mantenimiento por correo y WhatsApp.
- **[Plantillas de WhatsApp](modulos/plantillas-whatsapp)** y
  **[Chat de WhatsApp](modulos/whatsapp-chat)**: las tareas de WhatsApp usan las
  plantillas aprobadas; el aviso de mensajes no leídos vigila los chats.

## Errores frecuentes

- **«El nombre de la automatización es obligatorio.»**, **«El módulo es
  obligatorio.»**, **«La acción es obligatoria.»** o **«El valor de frecuencia es
  obligatorio.»**: falta completar ese dato antes de guardar.
- **«No se puede guardar»** (envío automático de correo): ver la regla de correo
  duplicado más arriba.
- **«La tarea ya está en ejecución.»**: la automatización se está ejecutando en
  este momento. Espere a que termine y vuelva a intentarlo.
- **«Debe seleccionar la plantilla de WhatsApp en la automatización.»**: edite la
  automatización y elija la plantilla.
- **«La plantilla '…' no existe o no está aprobada.»**: la plantilla elegida ya
  no está aprobada por Meta o fue eliminada. Revísela en Plantillas de WhatsApp.
- **«Debe configurar el asunto y el mensaje del correo en la automatización.»**:
  el asunto o el mensaje quedaron vacíos.
- **«No hay correo de destino: configure los destinatarios en la automatización o
  el correo de la empresa.»** (aviso de IVA): indique un correo de destino o
  registre el correo de la empresa.
- **«La empresa no tiene configurado el tipo de ambiente (Pruebas/Producción) en la
  ficha de Emisor Electrónico.»**: complete esa ficha antes de enviar al SRI
  automáticamente.
- **«No se configuró la serie (punto de emisión) en la automatización.»** o **«La
  serie configurada no es válida o está inactiva.»** (Generar facturación de
  suscripciones): elija una serie activa en las opciones.
- **«Avisos desactivados en la configuración de WhatsApp.»** o **«No hay números
  configurados para recibir los avisos.»**: active los avisos y registre los
  números en Configuración de WhatsApp.
- **«Cooldown activo — último aviso hace … min (mínimo … min).»**: todavía no pasa
  el tiempo mínimo entre avisos de mensajes no leídos; no es un error.
- La ejecución figura como *exitoso* pero con 0 registros: no había nada pendiente
  para procesar, o el mensaje del historial indica qué falta configurar.

## Historial de cambios

- **1.0** — Versión inicial del manual.
