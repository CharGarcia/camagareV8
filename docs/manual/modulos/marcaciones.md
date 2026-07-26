---
titulo: Marcaciones
resumen: Registro de entradas y salidas del personal, con su método y ubicación.
categoria: Asistencia
ruta_modulo: modulos/marcaciones
tipo: modulo
visibilidad: todos
etiquetas: marcaciones, marcar, entrada, salida, asistencia, reloj, gps, ubicacion, atrasos, faltas
version: 1.0
orden: 30
estado: activo
---

Las **marcaciones** son los registros de entrada y salida del personal. De ellas
salen los atrasos, las faltas y las horas trabajadas que después alimentan las
novedades y el rol de pago.

## Qué guarda cada marcación

| Dato | Detalle |
|------|---------|
| Empleado | Quién marcó |
| Tipo | Entrada o salida |
| Método | Cómo se registró la marcación |
| Fecha y hora | Cuándo |
| Ubicación | Latitud y longitud, cuando el punto exige GPS |

## La ubicación

Cuando el punto de servicio exige GPS, la marcación guarda las coordenadas desde
donde se hizo. El sistema valida que sean coordenadas posibles (latitud entre -90
y 90, longitud entre -180 y 180) y que caigan dentro de la geocerca del punto.

Ese cruce es lo que permite detectar una marcación hecha desde otro sitio.

## Marcaciones manuales

Cuando alguien no pudo marcar (olvido, teléfono sin batería, fallo del equipo),
la marcación se registra manualmente. Conviene que ese registro quede
identificado como tal: una asistencia llena de marcaciones manuales deja de ser
un control.

## Errores frecuentes

- **"No se pudo identificar al empleado"**: la marcación no está asociada a
  ninguna ficha; revise que el empleado exista y esté activo.
- **"El tipo de marcación no es válido"** o **"El método no es válido"**: use uno
  de los valores admitidos.
- **La marcación se rechaza estando en el sitio**: revise el radio de la geocerca
  del punto de servicio.
- **Faltan marcaciones de un día**: puede que el dispositivo no tuviera conexión;
  regístrelas manualmente.

## Historial de cambios

- **1.0** — Versión inicial.
