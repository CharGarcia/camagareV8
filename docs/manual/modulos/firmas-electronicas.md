---
titulo: Firmas electrónicas
resumen: Certificado de firma con el que se firman los comprobantes antes de enviarlos al SRI.
categoria: Configuración de empresa
ruta_modulo: modulos/firmas_electronicas
tipo: modulo
visibilidad: admin
etiquetas: firma electronica, certificado, p12, token, caducada, por caducar, caduca mañana, aviso de caducidad, correo de aviso, notificacion por correo, renovar firma, vencimiento de la firma, firmar comprobantes, sri, firma invalida, xades, no se ajusta a xades, certificado de firma, anf, uanataca, security data, banco central
version: 1.3
orden: 20
estado: activo
---

La **firma electrónica** es el certificado con el que la empresa firma sus
comprobantes antes de enviarlos al SRI. Sin una firma vigente cargada, no se
puede emitir ninguna factura electrónica.

## Qué se registra

| Campo | Regla |
|-------|-------|
| Tipo de identificación | Debe ser uno de los válidos |
| Número de identificación | Obligatorio |
| Cédula | Exactamente **10 dígitos numéricos** |
| Pasaporte | Solo letras y números, máximo 20 caracteres |
| Usuario responsable | Obligatorio |

Se carga el archivo del certificado junto con su clave.

## Vigencia

El certificado **caduca**. Cuando vence, todos los envíos al SRI empiezan a
fallar de golpe, y el mensaje de error no siempre lo dice con claridad.

Conviene anotar la fecha de caducidad y renovar antes de que llegue: la
renovación con la entidad certificadora no es inmediata, y mientras tanto no se
puede facturar electrónicamente.

## Aviso de caducidad: ícono del navbar y correo a la empresa

El sistema avisa de dos formas que la firma activa está por vencer:

- **Ícono en la barra superior** (junto a los demás avisos): aparece cuando la
  firma activa caduca en **5 días o menos**, cuando ya caducó, o cuando la
  empresa no tiene ninguna firma activa cargada. Al hacer clic lleva a la ficha
  de la empresa. Solo lo ve quien entra al sistema.
- **Correo automático a la empresa**: se envía **un día antes** de la fecha de
  caducidad al correo registrado en la ficha de la empresa (pestaña *Datos
  generales*, campo **Correo**). Llega aunque nadie haya iniciado sesión.

Cómo funciona el correo:

| Regla | Detalle |
|-------|---------|
| Cuándo | Cuando a la firma activa le queda **1 día o menos** (incluye el día de caducidad). |
| A quién | Al correo de la ficha de la empresa. Si está vacío o no es válido, no se envía y queda una traza en el log del servidor. |
| Cuántas veces | **Una sola vez por firma.** El envío queda en la bitácora del sistema (tabla *Firma electrónica de la empresa*, acción *Aviso caducidad correo*) y esa marca evita repetirlo. |
| Si ya caducó | Si la firma venció hace **hasta 7 días** y nunca se avisó (por ejemplo, el correo del servidor estaba caído), se envía igual indicando desde cuándo está caducada. Firmas vencidas hace más tiempo no generan correo. |
| Hora | La revisión corre una vez al día, a partir de las **06:00**, sobre el cron del servidor. |
| Firma nueva | Al cargar y activar la firma renovada, el aviso se reinicia: la nueva firma recibirá su propio correo cuando le toque. |

El correo indica la empresa, el RUC, la fecha de caducidad y qué hacer
(renovar con la entidad certificadora y cargar el nuevo `.p12` como firma
activa). No es configurable por empresa y no requiere programar nada.

## El SRI responde "FIRMA INVALIDA"

Cuando el SRI devuelve:

> **[ERROR] FIRMA INVALIDA** — La firma es invalida [Firma inválida. La
> información sobre el certificado de firma no se ajusta a XAdES.]

quiere decir que el comprobante **sí se firmó**, pero los datos del certificado
que viajan dentro de la firma no le cuadran al SRI con el certificado usado.

Que el error aparezca con una firma y no con otra es normal: depende de **quién
emitió** el certificado. Cada entidad certificadora (ANF, UANATACA, Security
Data, Banco Central) escribe internamente el nombre de su autoridad emisora de
una forma distinta, y el SRI lo compara carácter por carácter.

Si aparece este error con una firma recién cargada, avise a soporte indicando
**qué entidad emitió el certificado**. No es un problema de la clave ni del
archivo, y volver a cargar la firma no lo soluciona.

## Errores frecuentes

- **Todos los comprobantes empiezan a fallar el mismo día**: lo primero a revisar
  es si la firma caducó.
- **"La cédula debe tener exactamente 10 dígitos numéricos"**: revise el número
  o cambie el tipo de identificación.
- **El sistema no acepta el archivo**: compruebe que la clave sea la correcta y
  que el archivo no esté dañado.
- **"FIRMA INVALIDA / no se ajusta a XAdES"**: ver la sección anterior. La firma
  y la clave están bien; es la forma en que el sistema describe a la entidad
  emisora.

- **No llegó el correo de aviso de caducidad**: revise que la ficha de la
  empresa tenga un correo válido en *Datos generales* y que el correo de
  *notificaciones* del sistema esté configurado. El aviso se envía una sola vez
  por firma; si ya se envió, no se repite aunque la firma siga caducada.

## Historial de cambios

- **1.3** — Nuevo aviso automático por correo: un día antes de la caducidad
  de la firma activa (o hasta 7 días después si nunca se avisó) se envía un
  correo al correo registrado en la ficha de la empresa. Una sola vez por
  firma; queda registrado en la bitácora del sistema.
- **1.2** — Se corrigió el número de serie del certificado en la firma. Los
  certificados con número de serie largo (ANF, entre otros) se firmaban con un
  valor mal calculado y el SRI los rechazaba; los de serie corta (Lazzate,
  Security Data) no se veían afectados, por eso el fallo parecía depender de la
  empresa.
- **1.1** — Se corrigió el rechazo "FIRMA INVALIDA / la información sobre el
  certificado de firma no se ajusta a XAdES" con certificados de ANF Ecuador
  (y de cualquier entidad cuyo emisor incluya atributos poco comunes). El
  nombre de la autoridad emisora ahora se lee del propio certificado.
- **1.0** — Versión inicial.
