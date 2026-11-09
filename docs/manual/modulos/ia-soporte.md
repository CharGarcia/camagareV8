---
titulo: IA Soporte
resumen: Asistente que responde preguntas usando los documentos cargados por la empresa y el manual del sistema.
categoria: Herramientas
ruta_modulo: modulos/ia-soporte
tipo: modulo
visibilidad: todos
etiquetas: ia, inteligencia artificial, asistente, chat, preguntar, documentos, consultas legales, tributarias, contables, proveedor de ia, openai, chatgpt, anthropic, claude, api key, clave de ia, modelo, nivel de razonamiento, asistente virtual instagram
version: 1.2
orden: 10
estado: activo
---

**IA Soporte** es un asistente al que se le puede preguntar en lenguaje normal.
No responde de memoria: busca la respuesta en los documentos que la empresa haya
cargado y en el **manual del sistema**, y cita de dónde la sacó.

## De dónde saca las respuestas

| Fuente | Qué aporta |
|--------|------------|
| Documentos de la empresa | Leyes, reglamentos, contratos y cualquier PDF que se haya subido |
| Manual del sistema | Cómo se usa el ERP: dónde está una opción, qué pasos seguir |

Las fuentes aparecen debajo de cada respuesta. Las de documentos despliegan el
fragmento exacto; las del manual abren el artículo correspondiente.

## Antes de usarlo

La empresa necesita tener configurado su proveedor de IA con su propia clave, en
la pestaña de configuración. Sin eso, el asistente avisa de que no está
configurado.

## Configurar el proveedor de IA

En la pestaña **Configuración**:

| Campo | Qué significa |
|-------|---------------|
| Proveedor | **OpenAI (ChatGPT)** o **Anthropic (Claude)**. |
| Modelo | El sugerido aparece al elegir el proveedor (`gpt-4o-mini` u `claude-opus-5-5`). Con Anthropic debe empezar con `claude-`. |
| Nivel de razonamiento | Solo Anthropic: Bajo (más rápido y económico), Medio o Alto. «El del modelo» deja el valor por defecto. |
| API key | La clave de la cuenta de la empresa en ese proveedor. Se guarda cifrada y no se vuelve a mostrar; vacío al editar = se conserva. |

- Si cambia de proveedor, debe escribir la **clave del nuevo**: la anterior no
  sirve.
- Esta misma configuración la usa el **asistente virtual de Instagram** del CRM,
  que necesita **Anthropic**. Si eligió «El del modelo» en el nivel de
  razonamiento, el asistente usa «Bajo». Los documentos que suba aquí también
  los consulta el asistente para responder a los clientes.
- El consumo lo cobra el proveedor directamente a la empresa.

## Cómo se usa

1. Cree una **conversación** eligiendo el agente que corresponda al tema.
2. Escriba su pregunta.
3. Revise la respuesta **y sus fuentes**.

## Lo que ve cada usuario

El asistente respeta los permisos: solo puede citar documentación del manual que
esa persona tenga derecho a leer. Dos usuarios distintos pueden recibir
respuestas distintas a la misma pregunta, y es correcto.

## Cómo preguntar mejor

- Concreto antes que general: *"¿qué datos son obligatorios en una factura de
  venta?"* funciona mejor que *"háblame de facturas"*.
- Si la respuesta no cita fuentes, el asistente está respondiendo de forma
  general: contrástela antes de darla por buena.

## Errores frecuentes

- **"Esta empresa no tiene configurado un proveedor de IA"**: falta la
  configuración con la clave del proveedor.
- **Responde que no encuentra información**: no hay documentos cargados sobre ese
  tema, o el manual aún no cubre ese módulo.
- **Cita un documento desactualizado**: los documentos los carga la empresa;
  revise cuáles están subidos.
- **«Cambió de proveedor: ingrese la API key del nuevo proveedor»**: escriba la
  clave del proveedor elegido.
- **«Con Anthropic el modelo empieza con claude-»**: use, por ejemplo,
  `claude-opus-5-5`.
- **«La clave de Anthropic configurada no es válida…»** o **«…no tiene saldo»**:
  revísela en console.anthropic.com.
- **«Anthropic rechazó la solicitud (datos no válidos): This API key is not scoped
  to a workspace…»**: la clave no pertenece a un espacio de trabajo. En la consola
  de Anthropic cree un espacio de trabajo propio (*Settings → Workspaces → Create
  Workspace*; el «Default» puede no servir), cree la clave **dentro** de él,
  reemplácela aquí y revoque la anterior.
- **«Anthropic rechazó la solicitud (datos no válidos): …»** con otro texto: el
  motivo que sigue a los dos puntos es el que da Anthropic; con él se sabe qué
  corregir.

## Historial de cambios

- **1.2** — Los rechazos de Anthropic muestran su motivo; error de clave sin espacio
  de trabajo.

- **1.1** — Proveedor **Anthropic (Claude)** con nivel de razonamiento; al cambiar
  de proveedor se exige la clave nueva. La configuración la usa también el
  asistente virtual de Instagram del CRM.
- **1.0** — Versión inicial.
