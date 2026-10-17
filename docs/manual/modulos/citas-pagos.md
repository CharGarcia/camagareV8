---
titulo: Pagos de citas
resumen: Registro y seguimiento de los cobros vinculados a las citas de la agenda.
categoria: Servicios
ruta_modulo: modulos/citas-pagos
tipo: modulo
visibilidad: todos
etiquetas: pagos de citas, cobros de citas, anticipo, abono, reserva pagada, reembolso, pago pendiente, comprobante, transferencia, efectivo, tarjeta, citas, agenda
version: 1.1
orden: 21
estado: activo
---

**Pagos de citas** lleva el control de lo que se cobra por las citas de la
**Agenda de citas**: anticipos, pagos totales, pendientes, fallidos y
reembolsos.

## Qué es y para qué sirve

Cada pago queda vinculado a una cita. Sirve para saber cuánto se ha cobrado,
cuánto falta por cobrar y qué pagos se devolvieron o no llegaron a completarse.

## Requisitos previos

Debe existir la cita en la **Agenda de citas**. El pago siempre se registra
sobre una cita ya agendada.

## Cómo se usa

1. Pulse **Nuevo pago** (en la tarjeta de arriba, junto al buscador).
2. Elija la **cita**, el **tipo de pago** y el **monto**.
3. Indique el **estado**, el **método de pago** y, si hay, la referencia o
   número de comprobante.
4. Guarde.

Para revisar o corregir un pago, haga clic en su fila.

## La pantalla

Arriba hay una tarjeta fija que no se mueve al bajar por la lista:

- **Título** del módulo.
- **Buscador** con el botón del embudo (filtros), columnas, PDF, Excel y
  **Nuevo pago**; a la derecha, las flechas para cambiar de página y el
  selector de **filas por página** (25, 50, 75 o 100; se recuerda por usuario).
- **Resumen** al pie: total **cobrado**, **pendiente**, **reembolsado** y
  número de pagos **fallidos** de toda la empresa.

Debajo va la lista de pagos; la página completa hace scroll.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Cita | Sí | La cita a la que corresponde el pago |
| Tipo de pago | Sí | *Total* o *Anticipo* |
| Monto | Sí | Valor cobrado |
| Estado | No | Pendiente, Completado, Fallido o Reembolsado |
| Método de pago | No | En sitio, efectivo, tarjeta, transferencia u otros |
| Referencia / Comprobante | No | Número de transacción o comprobante del pago |

## Buscar y filtrar

Lo que se escribe en el cuadro busca en las columnas de la lista. El **embudo**
abre la ventana de filtros, con dos bloques: **Pago** (fecha de registro,
estado, tipo de pago, método, monto, usuario que registró, referencia) y
**Cita** (fecha de la cita, tipo de cita, recurso, cliente, identificación,
título). Los filtros aplicados aparecen como etiquetas dentro del buscador.
PDF y Excel exportan lo que la lista muestra con los filtros aplicados.

## Permisos

- **Ver**: consultar la lista y exportarla.
- **Crear**: registrar pagos nuevos.
- **Modificar / Eliminar**: abrir un pago desde su fila para corregirlo o
  eliminarlo. Sin esos permisos la fila no abre nada.

## Integraciones con otros módulos

Los pagos se registran sobre las citas de la **Agenda de citas**, y la Agenda
permite filtrar las citas con o sin pago registrado.

## Errores frecuentes

- **La cita no aparece al registrar el pago**: verifique que la cita exista en
  la Agenda y no esté eliminada.

## Historial de cambios

- **1.1** — Se quita el botón *Agenda*. El título, el buscador y el resumen
  pasan a una tarjeta fija arriba, con margen a los lados; el selector de filas
  por página es el estándar del sistema (25/50/75/100, recordado por usuario).
- **1.0** — Versión inicial.
