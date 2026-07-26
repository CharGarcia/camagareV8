---
titulo: IA Soporte
resumen: Asistente que responde preguntas usando los documentos cargados por la empresa y el manual del sistema.
categoria: Herramientas
ruta_modulo: modulos/ia-soporte
tipo: modulo
visibilidad: todos
etiquetas: ia, inteligencia artificial, asistente, chat, preguntar, documentos, consultas legales, tributarias, contables
version: 1.0
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

## Historial de cambios

- **1.0** — Versión inicial.
