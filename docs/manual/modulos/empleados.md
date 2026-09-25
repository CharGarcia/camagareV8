---
titulo: Empleados
resumen: Ficha del personal de la empresa; base de la nómina, las novedades y el control de asistencia.
categoria: Nómina
ruta_modulo: modulos/empleados
tipo: modulo
visibilidad: todos
etiquetas: empleados, empleado, personal, trabajadores, nomina, ficha, cedula, sueldo, contratacion, credencial, qr personal, asistencia, marcar, rostro, reconocimiento facial, probar rostro, no me reconoce, vacaciones del empleado, periodos de vacaciones, saldo de vacaciones, vacaciones tomadas, vacaciones pagadas, empleados de otro sistema, horario, turno, asignar turno, punto de servicio, atrasos, tratamiento de atrasos, descuento por atrasos, solicitud de vacaciones, solicitar vacaciones, enviar solicitud por correo, aprobar vacaciones, detalle de vacaciones pdf, cedula falsa, cedula invalida, cedula incorrecta, ruc invalido, digito verificador, validar cedula, comprobar cedula, sueldo neto, cuanto gana, liquido a recibir, resumen de sueldo
version: 1.11
orden: 10
estado: activo
---

El módulo de **Empleados** es el registro del personal. Alimenta los roles de
pago, las novedades, las vacaciones, los décimos y el control de asistencia: sin
la ficha, el empleado no existe para ninguno de esos procesos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **tipo de identificación** e ingrese el número.
3. Complete **nombres y apellidos**.
4. Añada el resto de datos personales y laborales.
5. Guarde.

## Validaciones

| Campo | Regla |
|-------|-------|
| Tipo de identificación | Obligatorio |
| Identificación | Obligatoria |
| Cédula | Si es cédula, exactamente **10 dígitos** |
| Nombres y apellidos | Obligatorios |
| Correo electrónico | Si se llena, debe tener formato válido |
| Sexo | Debe ser uno de los valores admitidos |
| Cargas familiares | Número entero, 0 o más. Vacío equivale a 0 |

## Aviso de cédula o RUC mal digitados (dígito verificador)

Al terminar de escribir una cédula (10 dígitos) o un RUC (13 dígitos), el
sistema comprueba su **dígito verificador**, el cálculo que tienen todas las
cédulas y RUC ecuatorianos. Si no cuadra, aparece debajo de *Identificación* un
**aviso en color ámbar**: «Cédula incorrecta, revisar.» (en un RUC: «RUC
incorrecto, revisar.»).

- Es **solo un aviso**: **no impide guardar**. Existen cédulas y RUC reales que
  no cumplen ese cálculo, así que la decisión es suya.
- Si el SRI **encuentra** el número, el aviso **desaparece solo**: el número
  existe aunque no cumpla el cálculo.
- Si el SRI responde **No encontrado**, se añade «El SRI no encontró este
  número.». Casi siempre es un dígito cambiado:
  corríjalo antes de guardar el empleado.
- Pasaporte, consumidor final e identificación del exterior no tienen este
  cálculo y no muestran aviso.

## Cargas familiares

En la pestaña **General**, en la última fila junto al contacto de emergencia,
se registra el **número de cargas familiares** del empleado (0 si no tiene). Es
un dato general de la ficha y sale en el PDF y el Excel del empleado.

También se puede cargar por Excel: la plantilla del botón *Importar* del módulo
trae la columna **CARGAS_FAMILIARES** al final, y la entidad *Empleados* del
Importador desde Excel (Configuración) tiene la misma columna. En ambos casos es
opcional: las plantillas antiguas sin esa columna siguen funcionando y dejan el
valor en 0.

> No confundir con las cargas que se declaran **por año** en la pestaña de
> gastos personales (formulario SRI-GP): esas son las que determinan la rebaja
> del Impuesto a la Renta y se registran aparte, año por año.

## Ficha en PDF y Excel

En la barra superior del modal (junto al título) hay dos botones para exportar
la ficha del empleado ya guardado: el ícono rojo descarga el **PDF** y el ícono
verde descarga el **Excel**. Ambos incluyen los mismos datos —generales,
laborales, bancarios, historial de periodos y rubros fijos—; el Excel los
organiza en secciones con pares etiqueta/valor. Los dos quedan deshabilitados
(avisan "Guarde primero") mientras el empleado no se ha guardado.

### Resumen de sueldo mensual en el PDF y el Excel

Al final del PDF y del Excel sale el **Resumen de sueldo mensual (estimado)**: cuánto recibe
el empleado en un mes normal, con los ingresos a la izquierda, los descuentos a
la derecha y el **Neto a recibir**. Se calcula con el mismo motor del Rol de
Pagos, usando solo lo fijo de la ficha:

- **Ingresos**: sueldo base, rubros fijos de ingreso y, si están configurados en
  el rol, fondos de reserva y décimos mensualizados.
- **Descuentos**: aporte personal al IESS (si aporta), rubros fijos de descuento e
  Impuesto a la Renta proyectado (según tramos del año y gastos personales).

Es una referencia: toma el mes actual completo (30 días) y **no** incluye
novedades (horas extra, anticipos, préstamos, descuentos del mes), vacaciones ni
días no laborados, así que el rol real puede ser distinto.

## Pestaña Vacaciones: períodos y saldo

La pestaña **Vacaciones**, después de *Periodos*, muestra el saldo de vacaciones
del empleado y el cuadro de sus años de trabajo, contados desde la fecha de
ingreso. Es el mismo cuadro del módulo **Vacaciones**: ahí se marcan los
períodos que el empleado ya **tomó** o **cobró** antes de usar el sistema, para
dejar al día el saldo de quien viene de otro sistema con varios años en la
empresa.

1. Abra el empleado (debe estar guardado) y pase a la pestaña **Vacaciones**.
2. Arriba ve el resumen: ingreso, antigüedad, derecho acumulado, lo tomado o
   pagado antes del sistema, lo gozado en el sistema y el saldo pendiente.
3. Marque la casilla de cada período ya tomado o pagado. Si solo tomó una parte,
   cambie los días de la fila.
4. Pulse **Marcar como tomados** o **Marcar como pagados** y confirme. El saldo
   se actualiza al momento.

En la columna **Situación**, los períodos marcados como tomados se ven como
*Vacaciones tomadas* y los marcados como pagados, como *Pagado antes del
sistema*.

Las marcas se guardan al pulsar esos botones: no hace falta el botón *Guardar*
del empleado. Para quitar una marca, use la flecha circular de la fila.

### Solicitudes y PDF del detalle

Arriba del cuadro, la sección **Solicitudes** sirve para que el empleado pida sus
vacaciones sin entrar al sistema:

- **Enviar solicitud** le manda por correo un enlace personal (sirve una vez y
  caduca a los 15 días). Se propone el correo de la ficha. Si el correo no sale,
  el sistema avisa y deja **copiar el enlace** para enviarlo por otro medio.
- Cuando el empleado lo llena, su solicitud aparece aquí como *Esperando
  aprobación*: **✔** la aprueba —y registra la vacación con sus días y su valor—
  y **✘** la rechaza pidiendo el motivo. En ambos casos se le puede avisar por
  correo.
- El botón **PDF** de cada fila imprime la solicitud con las firmas.
- **Detalle PDF** descarga el historial de vacaciones del empleado: períodos,
  saldo y cada vacación con su valor (y el total ya pagado, si lo hay).

El detalle de cómo funciona cada estado está en el artículo del módulo
*Vacaciones*.

### Registrar vacaciones desde la ficha

Debajo de las solicitudes está la lista de **vacaciones registradas** del
empleado —fechas, días, valor, mes del rol, estado y observación—, con el total
de días y de dinero junto al título (sin contar las anuladas).

- **Registrar vacación** abre el mismo formulario del módulo *Vacaciones* con el
  empleado ya fijado: fechas, días gozados (se sugieren con el rango elegido),
  valor calculado con su sueldo, mes y año del rol (salen de la fecha *desde*),
  si se incluye en el rol de pagos y observación. Arriba se recuerda su saldo.
- Pulsando una fila se abre para **editar**; desde ahí también se **elimina**
  (botón a la izquierda del pie).
- Al registrar, el estado es siempre *Registrado*; al editar se puede cambiar a
  *Pagado* o *Anulado*.
- El saldo y los períodos de abajo se actualizan al momento.

Es lo mismo que registrarlas desde el módulo *Vacaciones*: mismas validaciones
—incluido el bloqueo cuando el rol mensual de ese período ya está pagado—, misma
auditoría, y el valor entra igual al rol del mes indicado.

El cuadro usa la fecha de ingreso **guardada** en la pestaña *Periodos*. Si la
corrige, guarde el empleado y el cuadro se recalcula.

La pestaña aparece solo a quien tiene permiso para **ver** el módulo Vacaciones.
Marcar períodos, enviar la solicitud al empleado y aprobarla requieren el permiso
de **crear** de ese módulo; rechazar una solicitud o anular un enlace, el de
**actualizar**; y quitar la marca de un período, el de **eliminar**. Los detalles (cómo se reparten las vacaciones entre
los períodos, marcas parciales, avisos) están en el artículo del módulo
*Vacaciones*.

## Pestaña Horario: tratamiento de atrasos y turnos

La pestaña **Horario** reúne lo que el control de asistencia necesita del
empleado, en dos secciones: **Atrasos** arriba y **Horarios** debajo. Ambas se
guardan con el botón **Guardar** del empleado.

> El tratamiento de atrasos estaba antes en una pestaña propia, *Atrasos*, que
> ya no existe: ahora es la primera sección de esta pestaña.

### Atrasos

El campo **Tratamiento de atrasos** decide qué pasa con los atrasos que el
empleado acumula en el mes cuando se usa *Generar Novedades* en el módulo
Jornadas:

| Opción | Qué genera para el rol |
|--------|------------------------|
| Se descuenta según horas | Una novedad de **Descuento** = horas de atraso × (sueldo base ÷ 240) |
| No se descuenta | Nada: el atraso solo se ve en Jornadas. Es la opción que trae un empleado nuevo |
| Solo informativo | Una novedad de registro con valor **$0**, para que quede constancia del atraso en el rol |

Por ejemplo, con un sueldo base de $480 y 3 horas de atraso en el mes, *Se
descuenta según horas* genera un descuento de 3 × ($480 ÷ 240) = **$6,00**. El
desplegable *Aquí la explicación con ejemplo*, debajo del campo, muestra este
mismo detalle. Solo aplica a empleados que marcan asistencia.

### Horarios

Asigne al empleado su **turno** y, si hace falta, su **punto de servicio**, con
las fechas *Vigente desde* y *Vigente hasta*. Use **Agregar asignación** para
sumar una fila. El módulo Jornadas usa el turno vigente para calcular atrasos,
horas extra y faltas.

## Credencial de asistencia (QR personal y rostro)

La pestaña **Credenciales** entrega al empleado lo que necesita para marcar su
asistencia desde el celular. Se habilita cuando el empleado ya está guardado.

- **Generar QR** crea su **credencial personal**: un código propio (empieza con
  `EMP-`) y un **enlace personal**. El empleado abre ese enlace **una sola vez**
  desde su teléfono —escaneando el QR o tocando el enlace— y el teléfono queda
  vinculado. Desde ahí en adelante solo escanea el QR del punto de servicio para
  marcar.
- **Copiar enlace** copia la dirección completa, con el dominio, para enviarla
  por WhatsApp o correo. **Copiar código** copia solo el código, para quien no
  puede escanear y lo escribe a mano en la pantalla de marcación.
- **Regenerar** entrega un código nuevo y **anula el anterior**: el teléfono que
  ya estaba vinculado deja de marcar hasta que el empleado abra el enlace nuevo.
- **Registrar rostro** guarda un vector facial (no una foto) para confirmar que
  quien marca es el empleado. Es **opcional** y requiere su consentimiento
  (LOPDP). Sin rostro registrado, la marcación se valida solo con la credencial
  y el GPS.
- **Probar reconocimiento** aparece una vez registrado el rostro y sirve para
  comprobarlo **ahí mismo**, con el empleado delante, en vez de que se entere en
  el punto de servicio de que no lo reconocen. Abre la cámara, compara lo que ve
  con el rostro guardado y responde:
  - **Reconocido**, con el porcentaje de coincidencia: el registro sirve.
  - **No coincide**: si es la persona correcta, vuelva a registrar el rostro con
    mejor luz, de frente y sin gorra ni lentes.
  - **No se detectó ningún rostro**: la cámara no ve una cara completa.

  La prueba **no guarda nada** y no genera ninguna marcación: solo compara.

El código distingue mayúsculas de minúsculas; al dictarlo o escribirlo a mano hay
que respetarlo tal cual, aunque la pantalla de marcación corrige por su cuenta el
caso más frecuente: el código escrito todo en mayúsculas.

## Un catálogo por empresa

Los empleados pertenecen a una empresa. A diferencia de los comprobantes
electrónicos, **la ficha del empleado no distingue entre ambiente de pruebas y
producción**: es un catálogo maestro, siempre el mismo.

## Errores frecuentes

- **Aviso ámbar «Cédula incorrecta, revisar.»**: revise el número, lo más
  probable es un dígito mal digitado. Si está seguro de que es correcto (el SRI lo
  encuentra, o el documento físico lo confirma), puede guardar igual: el aviso no
  bloquea.
- **"La cédula debe tener exactamente 10 dígitos"**: revise el número o cambie el
  tipo de identificación si es un extranjero con pasaporte.
- **No aparece al generar el rol de pago**: compruebe que esté activo y en la
  empresa correcta.
- **"La credencial guardada en este teléfono ya no es válida"** o **"Ese código
  no corresponde a una credencial activa"**: la credencial fue regenerada, el
  empleado está inactivo o el código se escribió mal. Abra la pestaña
  *Credenciales* y reenvíele el enlace o el código vigente.
- **Al escanear el QR de la credencial el celular no abre nada**: vuelva a abrir
  la pestaña *Credenciales* y reenvíe el enlace. Los QR generados antes de esta
  corrección guardaban una dirección incompleta (sin el dominio) y ningún lector
  podía abrirla.

## Historial de cambios

- **1.11** — El PDF de la ficha incluye un resumen del sueldo mensual estimado:
  ingresos, descuentos (IESS, rubros fijos, Impuesto a la Renta) y neto a recibir.
  Nuevo encabezado con el logo del establecimiento, dirección y teléfono de la
  empresa, igual al del Rol de Pago. El Excel de la ficha también incluye el resumen del sueldo.
- **1.10** — Aviso (sin bloquear) cuando la cédula o el RUC no supera el
  dígito verificador; se retira solo si el SRI encuentra el número y se refuerza
  si el SRI no lo encuentra.
- **1.9** — Corregido: en la pestaña *Vacaciones*, sección **Solicitudes**, los cuadros para
  escribir —el correo al enviar la solicitud, el comentario al aprobar y el motivo al
  rechazar— no aceptaban texto: se veían, pero al escribir no pasaba nada. Ya se puede
  escribir con normalidad.

- **1.8** — En la pestaña *Vacaciones*, lista de **vacaciones registradas** del
  empleado y botón **Registrar vacación**: el mismo formulario del módulo
  Vacaciones con el empleado ya fijado, con editar y eliminar desde la propia
  ficha.
- **1.7** — En la pestaña *Vacaciones*, sección **Solicitudes**: se le envía al
  empleado un enlace por correo para que pida sus vacaciones, y desde ahí se
  aprueban o se rechazan. Nuevos PDF de la solicitud y del detalle de vacaciones
  del empleado.
- **1.6** — Se quita la pestaña *Atrasos*: el tratamiento de atrasos pasa a la
  pestaña *Horario*, en su propia sección arriba de los horarios. En la pestaña
  *Vacaciones*, la situación de los períodos marcados como tomados se muestra
  como *Vacaciones tomadas* (antes *Tomado antes del sistema*).
- **1.5** — Pestaña *Vacaciones* después de *Periodos*: saldo de vacaciones del
  empleado y cuadro de sus años de trabajo, donde se marcan los períodos ya
  tomados o pagados antes de usar el sistema.
- **1.4** — Botón *Probar reconocimiento* en la pestaña *Credenciales*: comprueba
  con la cámara si el sistema reconoce el rostro ya registrado, sin guardar nada.
- **1.3** — Se documenta la pestaña *Credenciales*. El enlace y el QR personal
  ahora llevan la dirección completa con el dominio; los anteriores no se podían
  abrir al escanearlos desde el celular.
- **1.2** — Campo *Cargas familiares* en la pestaña General, en la ficha
  PDF/Excel y como columna opcional en las plantillas de importación.
- **1.1** — Botón para exportar la ficha del empleado a Excel, junto al de PDF.
- **1.0** — Versión inicial.
