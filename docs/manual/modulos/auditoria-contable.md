---
titulo: Auditoría contable
resumen: Verifica que cada documento tenga su asiento correcto y permite corregirlos y regenerarlos en masa.
categoria: Contabilidad
ruta_modulo: modulos/auditoria_contable
tipo: modulo
visibilidad: admin
etiquetas: auditoria contable, revisar asientos, documentos sin asiento, descuadres, regenerar contabilidad, hallazgos
version: 1.0
orden: 70
estado: activo
---

La **auditoría contable** revisa toda la contabilidad de la empresa y compara
cada documento con su asiento: busca documentos sin asiento, asientos
descuadrados, asientos de documentos anulados y otras inconsistencias.

Es la herramienta a la que hay que ir cuando el balance no cuadra y no se sabe
por dónde empezar.

## Cómo se usa

1. Ejecute la revisión sobre el periodo que le interese.
2. Revise los **hallazgos** agrupados por tipo.
3. Corrija los que correspondan, en masa o uno a uno.

## Regenerar toda la contabilidad

Existe la opción de **regenerar toda la contabilidad** por lotes: borra y vuelve
a crear los asientos automáticos a partir de los documentos.

Dos advertencias importantes:

- **Nunca toca los asientos de tipo Diario.** Los asientos que escribió el
  contador a mano se respetan siempre.
- Es una operación pesada. Ejecútela con la contabilidad ya revisada y fuera del
  horario de trabajo si la empresa tiene mucho volumen.

## Documentos migrados

Los documentos que vinieron de otro sistema **no generan asiento propio**: su
contabilidad ya vino migrada. La auditoría los excluye a propósito, así que no
aparecerán como "documentos sin asiento".

## Errores frecuentes

- **Muchos documentos sin asiento**: normalmente hay configuración contable
  faltante para ese tipo de documento.
- **Un asiento descuadrado por centavos**: el sistema absorbe los redondeos; un
  descuadre mayor indica un problema real en el documento.

## Historial de cambios

- **1.0** — Versión inicial.
