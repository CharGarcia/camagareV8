---
titulo: Tablero
resumen: Pantalla de inicio con los indicadores y avisos del día.
categoria: Primeros pasos
ruta_modulo: modulos/dashboard
tipo: modulo
visibilidad: todos
etiquetas: tablero, dashboard, inicio, resumen, indicadores, avisos, pantalla principal, home, ordenar tarjetas, mover tarjetas, arrastrar, reubicar, personalizar tablero, filtros fijos
version: 1.1
orden: 2
estado: activo
---

El **tablero** es la pantalla de inicio: el resumen de cómo va la empresa y lo
que requiere atención hoy.

## Qué muestra

Depende de los módulos que tenga asignados cada usuario, pero en general:
indicadores de ventas y cobros, documentos pendientes y avisos de cosas que
requieren acción.

## Lo que ve cada usuario

El tablero **respeta los permisos**: solo muestra información de los módulos a
los que la persona tiene acceso, y de la empresa activa. Dos usuarios verán
tableros distintos, y es correcto.

Si además no tiene el permiso de *acceso total*, los indicadores reflejan solo
sus propios registros.

## Ordenar las tarjetas a su gusto

Las tarjetas del tablero se pueden **mover de lugar**, y cada usuario guarda su
propio orden: lo que usted acomode no le cambia el tablero a nadie más.

Cómo se hace:

1. Pase el cursor sobre la tarjeta que quiere mover. En su **esquina superior
   izquierda** aparece un pequeño agarre (⣿).
2. Mantenga presionado ese agarre y arrastre la tarjeta hasta la posición donde
   la quiere. Las demás se van corriendo para dejarle el espacio.
3. Suelte. El orden se guarda solo, en su usuario, y así lo encontrará la
   próxima vez que entre — desde cualquier equipo o navegador.

Hay **dos grupos independientes**: la fila de indicadores de arriba (Ventas,
Compras, Nómina, Utilidad, Margen, Ingresos, Egresos, CxC y CxP) y los paneles
de abajo (saldos, gráficos y tablas). Una tarjeta se mueve dentro de su grupo;
un indicador no baja a la zona de paneles ni al revés.

Para volver todo como venía de fábrica, use el enlace **Restablecer orden**, que
está en el texto pequeño debajo del título.

> Los paneles conservan su ancho al moverlos (unos ocupan dos tercios de la
> pantalla y otros un tercio). Si el orden que arma no completa el ancho de una
> fila, quedará un espacio en blanco a la derecha: es normal, basta acomodar los
> paneles en otro orden.

## Los filtros quedan siempre a la vista

La barra de filtros (período, rango de fechas, tendencia y tipo de gráfico) se
queda **fija bajo el menú** mientras usted baja por el tablero, de modo que
puede cambiar el período sin volver arriba.

## Si el tablero sale vacío

Suele ser una de tres cosas:

1. No hay **empresa activa** seleccionada.
2. El usuario no tiene módulos asignados todavía.
3. Es una empresa nueva sin movimientos.

## Errores frecuentes

- **Los números no coinciden con los reportes**: revise el periodo que muestra
  cada indicador y si le falta el permiso de acceso total.
- **Entré a un módulo y volví al tablero**: es lo que ocurre cuando no se tiene
  permiso sobre ese módulo. Pida el acceso al administrador.

## Historial de cambios

- **1.1** — Las tarjetas e indicadores se pueden arrastrar para reubicarlas y el
  orden se guarda por usuario; la barra de filtros quedó fija bajo el menú.
- **1.0** — Versión inicial.
