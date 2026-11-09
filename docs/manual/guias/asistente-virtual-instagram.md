---
titulo: Asistente virtual de Instagram
resumen: Cómo activar y ajustar el asistente con IA que responde solo los mensajes directos de Instagram y pasa la conversación a una persona cuando hace falta.
categoria: Ventas
tipo: guia
visibilidad: todos
etiquetas: asistente virtual, bot, chatbot, bot de instagram, respuesta automatica, responder automaticamente, ia, inteligencia artificial, claude, anthropic, mensajes directos, dm, atencion automatica, fuera de horario, pasar a una persona, traspaso, pausar bot, reactivar bot, probar asistente, consumo ia, costo ia, crm instagram
version: 1.1
orden: 51
estado: activo
---

El **asistente virtual** responde por la empresa los **mensajes directos** de
Instagram, al instante y a cualquier hora. Contesta con la información que la
empresa le da, se presenta siempre como asistente (nunca se hace pasar por una
persona) y **pasa la conversación al equipo** cuando el cliente quiere comprar,
reclama, pide hablar con alguien o pregunta algo que no sabe.

## Qué es y para qué sirve

- Que ningún mensaje quede horas sin respuesta, ni de noche ni el fin de semana.
- Resolver lo repetitivo (horarios, precios, si hay un producto, dirección,
  políticas) y dejar al equipo lo que necesita una persona.
- Registrar en el CRM a quien quiere comprar, con la oportunidad ya creada.

No responde **comentarios** (ni en público ni en privado): solo mensajes directos.

## Requisitos previos

1. La cuenta de Instagram conectada en **Configuración del CRM → Canales** (ver la
   guía **App de Meta para Instagram (CRM)**).
2. La IA de la empresa configurada con **Anthropic (Claude)** y su propia clave, en
   **IA Soporte → Configuración**. La empresa paga ese consumo directamente a
   Anthropic; el sistema no lo cobra.
3. Recomendado: subir en **IA Soporte** los documentos con la información que el
   cliente pregunta (horarios, envíos, cambios y devoluciones, servicios, preguntas
   frecuentes). El asistente los consulta antes de responder.

## Cómo se activa

1. **Configuración del CRM → Asistente virtual**.
2. Elija la cuenta de Instagram (arriba a la derecha).
3. Complete los datos (ver la tabla) y marque **Responder automáticamente los
   mensajes directos**.
4. Pulse **Guardar**.
5. Antes de dejarlo trabajando, use **Probar** (ver abajo).

## Campos de la configuración

| Campo | Qué significa |
|-------|---------------|
| Nombre del asistente | Con qué nombre se presenta (por ejemplo «Luna»). |
| Tono | **Cercano** (tutea) o **Formal** (trata de usted). |
| Presentación | Su primer mensaje en cada conversación. Vacío = «Hola, soy …, el asistente virtual de …». |
| Lo que debe saber | Datos e indicaciones de la empresa: horarios, envíos, formas de atención, qué no ofrecer. No puede cambiar sus reglas (por ejemplo, no puede autorizarlo a dar descuentos). |
| Temas de los que no habla | Si salen, ofrece pasar con una persona. |
| Cuándo responde | **Siempre**, o **Solo fuera del horario de atención** (en ese horario responde el equipo). Un día sin horario = ese día responde todo el día. |
| Decir si hay disponibilidad | Si está marcado, al buscar un producto dice si hay o no; **nunca** dice la cantidad. |
| Crear la oportunidad | Si el cliente quiere comprar, crea la oportunidad en el CRM (primera etapa, a cargo del responsable de la conversación) y la pasa a una persona. |
| Máx. respuestas por conversación | Al llegar, pasa la conversación a una persona. Por defecto 6. |
| Máx. respuestas por día | Tope de toda la empresa, para controlar el gasto. Por defecto 300. |

A la derecha se ve la IA configurada y el **consumo del mes**: respuestas,
conversaciones pasadas a una persona, errores, tokens y un **costo estimado**
(referencial: el valor real lo cobra Anthropic).

## Probar antes de activarlo

En **Probar** escriba como si fuera un cliente. El asistente responde con la
configuración **guardada**:

- Las búsquedas son reales (documentos y productos).
- Guardar datos del cliente, crear una oportunidad o pasar a una persona **no se
  hacen**: se muestran en cursiva como «Guardaría…», «Crearía…», «Pasaría…».
- No se envía nada a Instagram, pero **sí consume** la clave de IA. Máximo 30
  pruebas por hora.

**Empezar de nuevo** borra la conversación de prueba.

## Qué hace en cada mensaje

1. Espera unos **8 segundos** por si el cliente manda varios mensajes seguidos, y
   los responde juntos. La respuesta suele llegar en **10 a 20 segundos** (lo que
   tarda la IA en redactar). Si el servidor no pudo atenderla en ese momento, la
   responde en la siguiente revisión automática, que corre cada minuto.
2. Se presenta en su primer mensaje y responde en mensajes cortos.
3. Para afirmar algo usa solo los documentos de la empresa, el catálogo de
   productos (precio **con IVA**) y lo que usted escribió en «Lo que debe saber».
   Si no lo encuentra, lo dice y ofrece pasar con una persona.
4. Si el cliente da su nombre, teléfono o correo, los guarda en su contacto del CRM.
   El nombre solo lo cambia si el contacto lo creó el sistema (no pisa lo que
   escribió alguien del equipo).
5. **Nunca** da descuentos, negocia, ofrece crédito, promete entregas, toma
   pedidos ni cobra.

## Cuándo pasa a una persona

- El cliente pide hablar con alguien, quiere comprar, reservar, cotizar o pagar,
  o tiene un reclamo.
- No puede responder con la información que tiene.
- Llega al máximo de respuestas de la conversación o al tope del día.
- La IA falla dos veces seguidas, no da una respuesta o rechaza el mensaje.
- La IA de la empresa no está configurada con Anthropic.

Al pasarla: se despide avisando que alguien del equipo seguirá, la conversación
se asigna según el reparto de la cuenta, vuelve a contar como **sin leer** (suena
el aviso de la barra superior) y en la Bandeja se ve el **motivo**. Desde ahí el
asistente ya no escribe en esa conversación.

## Cuando responde alguien del equipo

Si una persona responde desde la **Bandeja** o desde la **app de Instagram**, el
asistente se **pausa solo** en esa conversación: nunca hablan los dos a la vez.

En la Bandeja, el panel del contacto muestra el estado del asistente y los
botones **Pausar asistente** y **Reactivar asistente** (reactivar reinicia su
contador de respuestas). El filtro **Pasadas por el asistente** lista las que
le tocan al equipo.

## Reglas de Instagram que respeta

- Solo responde dentro de las **24 horas** siguientes al último mensaje del
  cliente. La ventana de 7 días (*Human Agent*) es solo para personas: el
  asistente nunca la usa.
- No escribe primero: siempre responde a un mensaje del cliente.

## Permisos

- **Ver** (Configuración del CRM): consultar la configuración y el consumo.
- **Modificar** (Configuración del CRM): guardar la configuración y **Probar**.
- **Modificar** (Bandeja social): pausar y reactivar el asistente en una conversación.
- La clave de IA se configura en **IA Soporte** (permiso de modificar ahí).

## Errores frecuentes

- **«Para activar el asistente, configure Anthropic (Claude)…»**: la IA de la
  empresa no está configurada o es otro proveedor. Cámbiela en IA Soporte →
  Configuración.
- **«Guarde primero la configuración del asistente»** al probar: pulse Guardar.
- **«Llegó al máximo de 30 pruebas por hora»**: espere un rato.
- **El asistente no responde un mensaje**: revise que esté activo en la cuenta,
  que la conversación no esté pausada o pasada a una persona, que el cliente haya
  escrito hace menos de 24 horas y, si elige «Solo fuera del horario», que no sea
  horario de atención.
- **Muchas conversaciones pasan a una persona por error de la IA**: revise la
  clave y el saldo de la cuenta de Anthropic (el motivo exacto aparece en la
  Bandeja).
- **Dice que no tiene información sobre algo que sí ofrecen**: agréguelo en «Lo
  que debe saber» o suba el documento en IA Soporte.

## Historial de cambios

- **1.1** — Responde más rápido: espera 8 segundos (antes 20) y contesta apenas vence esa espera, sin esperar la revisión de cada minuto.
- **1.0** — Versión inicial.
