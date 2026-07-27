---
titulo: Taller Mecánico
resumen: Recibe el vehículo, lo hace recorrer los departamentos del taller registrando lo que hace cada uno, y termina en informe técnico y factura.
categoria: Operaciones
ruta_modulo: modulos/taller
tipo: modulo
visibilidad: todos
etiquetas: taller, mecanica, precuenta, whatsapp, mecánica, orden de trabajo, OT, orden de reparacion, enderezada, pintura, latoneria, repuestos, mano de obra, tecnico, diagnostico, informe tecnico, garantia, siniestro, aseguradora, vehiculo, auto, carro, presupuesto, aprobacion
version: 1.6
orden: 0
estado: activo
---

Este módulo acompaña al vehículo desde que entra al taller hasta que se lo
llevan facturado. El asesor lo recibe, el técnico lo diagnostica, el cliente
aprueba el presupuesto, y a partir de ahí el vehículo va pasando por los
departamentos del taller. En cada departamento el operario registra desde una
tablet qué hizo y qué repuestos usó. Todo eso se convierte al final en el
informe técnico que recibe el cliente.

## Qué es y para qué sirve

Una **orden de trabajo (OT)** es el expediente completo de una visita al taller:
el vehículo, quién lo trajo, por qué, qué se encontró, qué se autorizó, quién lo
reparó, con qué repuestos y cuánto costó.

Este módulo es la pantalla del **asesor de servicio**: recibe el vehículo, arma
el presupuesto, registra la aprobación del cliente, mueve la orden entre
departamentos y cierra con la entrega y la factura.

El taller se completa con otros cuatro módulos, cada uno con sus propios permisos:

- [Tablero del taller](modulos/taller-tablero): dónde está cada vehículo, en
  columnas por departamento. Para el jefe de taller.
- [Estación del taller](modulos/taller-estacion): la tablet fija de cada
  departamento, donde trabajan los operarios.
- [Departamentos del taller](modulos/taller-departamentos): el catálogo de áreas
  por las que pasa el vehículo.
- [Checklist de recepción](modulos/taller-checklist): qué se revisa al recibir
  cada vehículo.

Están separados a propósito: así el personal del taller usa lo suyo sin acceder
a la facturación ni a la edición de las órdenes.

## Requisitos previos

- **Departamentos del taller** creados en `modulos/taller-departamentos`. Sin
  ellos el vehículo no puede moverse por el taller.
- **Secuencial** del tipo de documento `Ordenes de taller`, configurado igual que
  el de cualquier otro documento: **Empresa → Secuenciales**, se elige el punto
  de emisión y se agrega desde el selector *«Agregar Tipo Documento»*. Aparece en
  el grupo *Operativos*. Sin él el sistema avisa y no deja registrar la orden.
- **Vehículos** y **clientes** en sus catálogos (se pueden crear al vuelo desde
  la propia orden).
- **Productos** para los repuestos y servicios que se van a cobrar.
- Opcional: **empleados**, para saber qué técnico hizo cada trabajo.

## Cómo se usa

1. **Recibir el vehículo.** Botón *Recibir vehículo*. Se busca el vehículo por
   placa (o se crea), se indica el motivo de ingreso, el kilometraje, quién lo
   usa y cómo contactarlo. Se llena el checklist de accesorios y se toman las
   fotos del estado en que llega. Guardar.
2. **Diagnosticar.** En la pestaña *Presupuesto* se escribe el diagnóstico y se
   agregan los repuestos y la mano de obra que hará falta. Todo nace como
   **sugerido**.
3. **Aprobar.** Se le muestra el presupuesto al cliente y se registra su
   respuesta: quién aprobó, por qué medio y cuándo. **Sin esta aprobación
   ningún departamento puede trabajar.** Si el cliente rechaza algo puntual, se
   marca esa línea como rechazada con su motivo.
4. **Mandar el vehículo a un departamento.** Pestaña *Departamentos* → elegir el
   destino → *Enviar*. El vehículo aparece en la tablet de ese departamento.
5. **Trabajar (desde la tablet).** El operario toca *Tomar*, hace el trabajo,
   agrega los repuestos que usó, escribe qué hizo y toca *Terminar y enviar*
   eligiendo el siguiente departamento. Si no elige ninguno, el vehículo queda
   listo para entrega.
6. **Entregar.** Pestaña *Entrega*: a quién se entrega, kilometraje de salida,
   garantía, recomendaciones y próximo mantenimiento. Se imprime o envía el
   **informe técnico**.
7. **Facturar.** Botones *Factura* o *Recibo* de la barra superior. Solo se
   cobran las líneas aprobadas y facturables.

### Cuándo se puede facturar

La orden se puede facturar cuando cumple **todo** esto:

1. Está **guardada**.
2. Tiene **cliente** asignado.
3. El cliente **aprobó el presupuesto**.
4. Hay al menos **un repuesto o trabajo aprobado y facturable**.
5. No generó ya un documento y no está anulada.

Los botones están siempre activos: **al pulsarlos, si falta algo, el sistema lo
explica** y ofrece ir directo a la pestaña donde se resuelve. Además, la barra de
la orden lo adelanta con *«Para facturar: …»* y el motivo aparece al pasar el
cursor por el botón. Cuando ya se puede, la barra muestra **«Lista para
facturar»**; emitido el documento, pasa a mostrar su número.

Si los botones no aparecen del todo, es que el usuario no tiene permiso de
creación sobre *Facturas de venta* o *Recibos de venta*.

Además, el punto de emisión necesita configurado el secuencial del documento
que se va a emitir (`Facturas de venta` o `Recibos de venta`) en
**Empresa → Secuenciales**. Si falta, el sistema lo avisa al intentar emitir.

La barra de botones de la orden permite crear sobre la marcha lo que falte, sin
salir de la pantalla: vehículo, cliente, repuesto o servicio, un **departamento**
del taller y un **ítem del checklist** de recepción. Los dos últimos solo
aparecen si el usuario tiene permiso de creación sobre *Departamentos del
taller*.

## Documentos que emite la orden

Desde la barra superior de la orden salen tres PDF, que además se pueden enviar
por **correo** o por **WhatsApp**:

| Documento | Qué contiene | Cuándo se usa |
|---|---|---|
| **Orden de trabajo** | Datos del vehículo, motivo de ingreso, checklist de recepción, presupuesto y firmas de autorización | Al recibir el vehículo; lo firma el cliente |
| **Informe técnico** | El recorrido completo: diagnóstico, qué hizo cada departamento, repuestos usados, fotos y garantía | Al entregar el vehículo |
| **Precuenta** | Solo los valores a pagar, con el desglose de repuestos y mano de obra | Antes de facturar, para que el cliente revise |

La precuenta es un documento **sin valor tributario** y lo advierte de forma
visible. Incluye únicamente las líneas aprobadas y facturables; si quedan
trabajos sugeridos sin aprobar, avisa que no están en ese total.

### Envío por WhatsApp

Usa la cuenta de WhatsApp Business de la empresa y **plantillas aprobadas por
Meta**: se elige el documento, la plantilla y el teléfono, y el PDF se adjunta al
mensaje. Es el mismo mecanismo de Factura de Venta.

Las plantillas se crean en *Plantillas de WhatsApp*. Los nombres sugeridos para
el taller son `orden_taller`, `informe_tecnico_taller` y `precuenta_taller`, y el
cuerpo puede usar hasta cuatro variables, en este orden:

1. `{{1}}` nombre del cliente
2. `{{2}}` placa del vehículo
3. `{{3}}` número de orden
4. `{{4}}` total

Para que el PDF se adjunte, la plantilla debe tener una cabecera de tipo
**DOCUMENT**. Si la empresa todavía no conectó WhatsApp Business, el botón
ofrece abrir el chat normal para escribirle al cliente, aunque sin adjunto.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fecha de ingreso | Sí | Cuándo entró el vehículo al taller |
| Serie y secuencial | Sí | Numeración de la orden, por punto de emisión |
| Vehículo | Sí | Se busca por placa, marca, modelo o propietario |
| Cliente | No al recibir, sí al facturar | A quién se le factura el trabajo |
| Motivo de ingreso | Sí | Lo que reporta el cliente, en sus palabras |
| Usuario del vehículo | No | Quién lo maneja, cuando no es el dueño (flotas, empresas) |
| Kilometraje | No | Se guarda también en la ficha del vehículo |
| Tipo de servicio | Sí | Mantenimiento, correctivo, colisión, garantía o revisión |
| Prioridad | Sí | Ordena las tarjetas del tablero y de las tablets |
| Bodega | No | De dónde salen los repuestos por defecto |
| Siniestro | No | Aseguradora, número de siniestro, deducible y ajustador |
| Checklist de recepción | No | Accesorios, documentos, carrocería y niveles al ingresar |
| Diagnóstico | No | Lo que encontró el técnico |
| Garantía (días / km) | No | Condiciones que se imprimen en el informe técnico |
| Próxima cita | No | Fecha sugerida para el siguiente mantenimiento |

### Campos de una línea de presupuesto

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo | Sí | Repuesto, mano de obra, insumo o trabajo de terceros |
| Descripción | Sí | Del catálogo o escrita libre |
| Cantidad | Sí | Debe ser mayor a cero |
| Horas | No | Solo para mano de obra; sirve para medir productividad |
| Precio unitario | Sí | Lo que se le cobra al cliente |
| Departamento | No | Qué departamento lo agregó o lo va a ejecutar |
| Técnico | No | El empleado que lo ejecuta |
| Lo trae el cliente | No | Se registra en el informe pero no se factura |

## Permisos

- **Ver**: consultar el listado, el tablero, las estaciones y los PDF.
- **Crear**: recibir vehículos y agregar repuestos y trabajos a una orden.
- **Modificar**: cambiar datos de la orden, registrar la aprobación del cliente,
  mover el vehículo entre departamentos, cerrar etapas y entregar.
- **Eliminar**: quitar líneas, fotos y órdenes que todavía no facturaron.
- **Acceso total**: ve las órdenes de toda la empresa. Sin él, cada usuario solo
  ve las que él mismo registró.

## Reglas de negocio

- **La aprobación del cliente es obligatoria.** Ningún departamento puede iniciar
  el trabajo si el presupuesto no está aprobado. La única excepción es el
  departamento marcado como *de diagnóstico*, que existe justamente para producir
  el presupuesto.
- **Los repuestos salen de bodega cuando la línea queda aprobada.** Mientras está
  sugerida no toca el stock. Si la línea se rechaza o se elimina, el repuesto
  vuelve al inventario.
- **Un repuesto traído por el cliente no se factura**, pero sí queda registrado
  en el informe técnico.
- **Cerrar una etapa exige describir el trabajo realizado.** Ese texto es lo que
  el cliente lee en el informe, así que el sistema no permite dejarlo vacío.
- **No se entrega el vehículo con departamentos abiertos.** Todas las etapas
  deben estar cerradas.
- **Una orden facturada, entregada o anulada ya no se modifica.**
- Solo se facturan las líneas **aprobadas o ejecutadas** y marcadas como
  facturables.
- El kilometraje más alto registrado queda guardado en la ficha del vehículo.

## Integraciones con otros módulos

- **Inventario**: los repuestos aprobados generan salidas de kardex con la
  referencia `taller_orden`. Al facturar, esas salidas se revierten y el
  documento de venta hace su propia descarga de stock (igual que Car-Wash).
- **Info. adicional**: lo que se escriba ahí se copia al comprobante. La fila
  *Correo del cliente* se llena sola al elegir el cliente y es la dirección a la
  que sale la factura, igual que en Factura de Venta.
- **Facturas de venta / Recibos de venta**: la orden genera uno de los dos. El
  documento se encarga del XML, el SRI y el asiento contable.
- **Contabilidad**: la orden **no** genera asiento propio. El asiento lo produce
  la factura o el recibo al emitirse.
- **Vehículos**: la orden usa y actualiza el catálogo, incluidos el kilometraje
  y los datos técnicos.
- **Empleados**: los técnicos responsables salen de ahí, y alimentan el
  indicador de productividad.
- **Auditoría**: cada creación, aprobación, cambio de departamento y línea queda
  registrada en `log_sistema` y en la bitácora de la propia orden.

## Errores frecuentes

- **«El cliente todavía no aprueba el presupuesto»**: alguien intentó iniciar el
  trabajo antes de registrar la aprobación. Vaya a la pestaña *Presupuesto* y
  complete quién aprobó y por qué medio.
- **«Seleccione la bodega del repuesto»**: la empresa trabaja con inventario y la
  línea no tiene bodega. Elija una en la cabecera de la orden o en la línea.
- **«Describa el trabajo realizado antes de cerrar la etapa»**: el operario
  intentó cerrar sin escribir qué hizo. Es obligatorio porque sale en el informe.
- **«Hay departamentos con trabajo sin cerrar»**: se intentó entregar el vehículo
  con etapas abiertas. Revise la pestaña *Departamentos*.
- **«Secuencial no configurado»**: el punto de emisión no tiene el tipo
  `Ordenes de taller`. Vaya a **Empresa → Secuenciales**, elija el punto y
  agréguelo desde el selector *«Agregar Tipo Documento»*. El aviso sale al abrir
  la orden y también al intentar guardarla.
- **«Esta serie no tiene configurado el secuencial "Facturas de venta"»** (o
  «Recibos de venta»): falta el secuencial del documento que se quiere emitir.
  Se agrega en el mismo sitio, para ese punto de emisión.
- **«Todavía no se puede facturar»**: sale al pulsar Factura o Recibo y dice qué
  falta. Lo más habitual es que no se haya registrado la aprobación del cliente
  o que la orden no tenga cliente asignado. El propio aviso lleva a la pestaña
  donde se corrige.
- **Los botones Factura y Recibo no aparecen**: el usuario no tiene permiso de
  creación sobre esos módulos. Se asigna en `/config/permisos-modulos`.
- **«No tiene permiso para emitir facturas de venta»**: mismo caso que el
  anterior, pero llegando por otra vía.
- **«Sin punto de emisión»**: la empresa no tiene puntos de emisión activos.
  Créelos en **Empresa → Puntos de emisión**.
- **«El secuencial ya existe para este punto de emisión»**: dos personas
  registraron una orden a la vez. Recargue y vuelva a guardar.
- **La tablet no muestra vehículos**: verifique que la URL tenga el
  `?id_departamento=` correcto y que haya órdenes enviadas a ese departamento.

## Historial de cambios

- **1.6** — Al pulsar *Factura* o *Recibo*, el sistema explica qué falta y lleva
  a la pestaña donde se resuelve, en lugar de dejar los botones grises sin dar
  motivo. También avisa si el punto de emisión no tiene configurado el
  secuencial del documento que se va a emitir.
- **1.5** — Nuevo documento **Precuenta** con los valores a pagar, y envío por
  **WhatsApp** con plantillas aprobadas por Meta (orden, informe o precuenta,
  con el PDF adjunto). Los botones de *Factura* y *Recibo* solo aparecen si el
  usuario tiene permiso de creación sobre esos módulos.
- **1.4** — El tablero y la pantalla de estación pasan a ser módulos propios
  (`modulos/taller-tablero` y `modulos/taller-estacion`), cada uno con sus
  permisos. Se quitaron sus botones de esta pantalla: ahora se entra por el menú.
- **1.3** — La pestaña *Info. adicional* usa el mismo diseño que Factura de
  Venta e incorpora la fila fija **Correo del cliente**, que se actualiza al
  elegir el cliente y viaja al comprobante que se emita desde la orden. Además,
  el buscador de la pestaña *Presupuesto* encuentra productos y servicios del
  catálogo, distinguiendo unos de otros.
- **1.2** — El secuencial `Ordenes de taller` se configura en Empresa igual que
  el resto de documentos. Si el punto de emisión no lo tiene, la orden avisa al
  abrirse y el guardado queda bloqueado, tanto en pantalla como en el servidor.
- **1.1** — Desde la barra de acciones de la orden se pueden crear también
  departamentos del taller e ítems del checklist de recepción, igual que ya se
  hacía con cliente, vehículo y repuesto. Los botones solo aparecen si el
  usuario tiene permiso de creación sobre *Departamentos del taller*.
- **1.0** — Versión inicial: recepción con checklist y fotos, diagnóstico,
  presupuesto con aprobación obligatoria del cliente, flujo por departamentos con
  pantalla de tablet, bitácora, entrega con garantía, informe técnico en PDF,
  facturación a Factura o Recibo e indicadores de tiempo por departamento y
  productividad por técnico.
