---
titulo: Tablero del taller
resumen: Muestra en columnas por departamento dónde está cada vehículo que hay dentro del taller y cuánto lleva ahí.
categoria: Operaciones
ruta_modulo: modulos/taller-tablero
tipo: modulo
visibilidad: todos
etiquetas: tablero, kanban, taller, mecanica, control, jefe de taller, vehiculos en taller, seguimiento, donde esta mi carro, departamentos, avance
version: 1.0
orden: 2
estado: activo
---

Es la pantalla de control del jefe de taller: una columna por departamento y una
tarjeta por vehículo. De un vistazo se ve qué hay en pintura, qué lleva días
parado y qué está esperando la aprobación del cliente.

## Qué es y para qué sirve

Responde a la pregunta de todos los días: **¿dónde está cada carro?**

Cada tarjeta muestra la placa, el vehículo, el motivo por el que entró, cuánto
tiempo lleva dentro del taller y el número de orden. Un ícono indica si el
presupuesto está aprobado (✓ verde) o si todavía se espera al cliente
(⏱ ámbar). Las órdenes urgentes o de alta prioridad llevan una franja de color
al costado.

La primera columna, **Recibidos**, agrupa los vehículos que ingresaron pero
todavía no se enviaron a ningún departamento, para que no se queden olvidados en
el patio.

Es una pantalla **de solo lectura**: no se modifica nada desde aquí. Al tocar una
tarjeta se abre esa orden en [Órdenes de Trabajo](modulos/taller).

## Requisitos previos

- **Departamentos del taller** creados en
  [Departamentos del taller](modulos/taller-departamentos): son las columnas.
- Órdenes registradas en [Órdenes de Trabajo](modulos/taller).

## Cómo se usa

1. Se abre y muestra el estado actual del taller.
2. Se actualiza sola cada 30 segundos; el botón de recarga fuerza el refresco.
3. Al tocar una tarjeta se abre la orden completa para trabajarla.

## Permisos

- **Ver**: es el único permiso que necesita esta pantalla.
- **Acceso total**: muestra los vehículos de toda la empresa. Sin él, cada
  usuario ve solo las órdenes que él mismo registró — lo habitual es dar acceso
  total al jefe de taller.

Por eso es un módulo separado: permite darle el tablero a gerencia o a la
recepción sin abrirles la edición de las órdenes.

## Reglas de negocio

- Solo aparecen las órdenes **en curso**. Las entregadas, facturadas y anuladas
  salen del tablero.
- Las tarjetas se ordenan por prioridad y, dentro de cada prioridad, por
  antigüedad: lo urgente y lo más viejo quedan arriba.
- El vehículo aparece en la columna del departamento donde está **ahora**. Cambia
  de columna cuando alguien lo envía a otro departamento, desde la orden o desde
  la tablet de la estación.

## Integraciones con otros módulos

- **Órdenes de Trabajo**: es la fuente de los datos y el destino al tocar una
  tarjeta.
- **Departamentos del taller**: define las columnas, su color, su ícono y su
  orden.
- **Estación del taller**: cuando un operario cierra su etapa y envía el
  vehículo, el tablero lo refleja en el siguiente refresco.

## Errores frecuentes

- **No aparece ninguna columna**: no hay departamentos activos. Créelos en
  Departamentos del taller.
- **Falta un vehículo que sí está en el taller**: puede que su orden ya esté
  marcada como entregada o facturada, o que al usuario le falte el permiso de
  acceso total y esa orden la registró otra persona.

## Historial de cambios

- **1.0** — Versión inicial como módulo propio, separado de Órdenes de Trabajo
  para poder asignarle permisos independientes.
