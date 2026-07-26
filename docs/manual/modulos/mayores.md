---
titulo: Mayores
resumen: Movimientos de una cuenta contable en un periodo, con su saldo y el documento que originó cada línea.
categoria: Contabilidad
ruta_modulo: modulos/mayores
tipo: modulo
visibilidad: todos
etiquetas: mayor, mayores, libro mayor, movimientos de cuenta, saldo de cuenta, auxiliar, cuadre
version: 1.0
orden: 40
estado: activo
---

El **mayor** muestra todos los movimientos de una cuenta contable en un rango de
fechas, con su saldo acumulado. Es la herramienta para responder *"¿por qué esta
cuenta tiene este saldo?"*.

## Cómo se consulta

1. Elija la **cuenta** en el buscador.
2. Indique el **rango de fechas**.
3. Genere el informe.

En el buscador de cuenta, si ya hay una seleccionada, pulsar **Retroceso** o
**Suprimir** limpia toda la selección de una vez.

## Llegar al documento de origen

Cada línea del mayor indica de qué documento salió. Desde la columna de
**Documento** se abre el documento original en una ventana, sin salir del
informe. Es la forma rápida de auditar un movimiento que no cuadra: del saldo se
llega a la línea, y de la línea a la factura o al egreso que la generó.

## Asientos pendientes

Al abrir el módulo, si hay documentos sin su asiento contable generado, el
sistema **pregunta** si desea generarlos antes de continuar. Conviene aceptar: un
mayor calculado con asientos pendientes muestra saldos incompletos.

Si prefiere revisar primero y generar después, puede continuar sin generarlos.

## Errores frecuentes

- **El saldo no coincide con el balance**: puede haber asientos pendientes de
  generar; acepte la generación al abrir el módulo.
- **Una línea sin tercero ni documento**: los movimientos migrados de otro
  sistema pueden no traer ese dato; el resto se resuelve desde el documento de
  origen.

## Historial de cambios

- **1.0** — Versión inicial.
