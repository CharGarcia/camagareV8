---
titulo: Reporte de restaurante
resumen: Consumo por mesa, platos más pedidos y anulaciones del servicio.
categoria: Reportes
ruta_modulo: modulos/reporte-restaurante
tipo: modulo
visibilidad: todos
etiquetas: reporte restaurante, comandas, mesas, platos mas vendidos, anulaciones, consumo, rotacion de mesas, forma de pago, filtro forma de pago, tirilla, imprimir tirilla, enviar por correo, pdf, excel, resumen por forma de pago, cuanto entro en efectivo, cuadrar caja, cierre de turno, sin forma de pago registrada
version: 1.2
orden: 70
estado: activo
---

Este reporte analiza el servicio de restaurante a partir de las comandas: qué se
consumió, en qué mesas y qué se anuló.

## Qué muestra

El selector **Ver por** cambia de vista sin recargar la página:

- **Ventas por mesa**: cuánto se facturó en cada una.
- **Ventas por mesero**: cuánto vendió cada uno y en cuántas comandas.
- **Resumen por forma de pago**: cuánto entró por Efectivo, por cada banco, por
  Payphone… (ver más abajo).
- **Ítems del menú más vendidos**: qué sale de la carta y qué no.
- **Ventas por categoría**.

## Resumen por forma de pago

Responde a *"¿cuánto entró en efectivo y cuánto por tarjeta?"*: una línea por
forma de pago con su tipo, cuántos cobros y el total, ordenadas de mayor a menor.
Es la vista para cuadrar la caja al cerrar el turno.

Aparece también una línea **"Sin forma de pago registrada"**, marcada con un
triángulo de aviso, cuando hay cobros cuyo Ingreso no llegó a generarse. **No es
un error del reporte**: son cobros reales a los que les falta el Ingreso, y hay
que registrarlos desde el módulo *Ingresos*. Mientras estén ahí, el dinero está
contado en el total pero no se sabe por dónde entró.

La suma de todas las líneas **siempre cuadra con el Total vendido** de la
cabecera: cada cobro cae en una sola línea.

## Las anulaciones son el dato de control

Como anular una comanda con consumos exige indicar un motivo, este reporte
permite revisar esos motivos juntos. Un patrón de anulaciones concentrado en un
turno o un usuario concreto es algo que conviene mirar de cerca.

## Platos que no rotan

El listado de lo menos pedido es tan útil como el de lo más pedido: son los
platos que ocupan carta e inventario sin venderse.

## Filtros

Por rango de fechas, mesa, mesero, ítem del menú, categoría y **forma de pago**.

La forma de pago es la de la empresa —Efectivo, un banco, Payphone…—, la misma
que elige quien cobra, no el código del SRI. Sale del **Ingreso** que generó cada
cobro, así que si una cuenta se cobró y su Ingreso no llegó a registrarse, esa
cuenta no aparece al filtrar por forma de pago (sí aparece sin ese filtro). Si
nota que los totales no cuadran al filtrar, revise en *Ingresos* que los cobros
del periodo estén registrados.

## Sacar el reporte

Cuatro botones, arriba a la derecha. Se habilitan cuando hay resultados en
pantalla y **todos respetan los filtros aplicados**: lo que sale es exactamente
lo que se está viendo.

| Botón | Qué hace |
|---|---|
| **PDF** | Descarga el reporte en A4 horizontal. |
| **Excel** | Descarga la tabla para trabajarla en hoja de cálculo. |
| **Tirilla** | Lo imprime en la **impresora térmica**, en el ancho configurado en *Configuración Restaurante*. Se abre en una ventana que se imprime sola. |
| **Correo** | Lo envía a uno o varios correos, con el PDF adjunto. |

La **tirilla** resume cada fila en dos líneas —el concepto y, debajo, su dato
secundario (ubicación, categoría, número de comandas) con el importe a la
derecha—, porque en 58 u 80 mm no caben las columnas de la pantalla. Al pie van
las comandas, los documentos y el total. Lleva la leyenda *"Reporte interno — sin
validez tributaria"*: es para la caja, no para el cliente.

El **correo** se abre con el **correo de la empresa** ya escrito (el de *Empresa →
Datos generales*), y se puede cambiar o añadir más separándolos con comas. El
enlace *Restaurar* vuelve a poner el de la empresa si lo borró. Si la empresa no
tiene correo configurado, el campo arranca vacío y lo dice.

El cuerpo del mensaje lleva el resumen —periodo, filtros aplicados, comandas,
documentos y total— y el PDF va adjunto.

## Errores frecuentes

- **Faltan consumos del día**: hay comandas todavía abiertas; el reporte cuenta
  las cerradas.
- **Un plato no aparece**: no se pidió en el periodo, o se registró como producto
  suelto en lugar de ítem del menú.
- **Al filtrar por forma de pago faltan cuentas**: esas cuentas se cobraron pero
  su Ingreso no se generó. Revíselas en *Ingresos*.
- **"No se pudo enviar el correo"**: revise la configuración de correo de la
  empresa. El reporte no se pierde: se puede descargar en PDF y enviarlo a mano.

## Historial de cambios

- **1.2** — Nueva vista **Resumen por forma de pago**: cuánto entró por cada una,
  con los cobros que aún no tienen su Ingreso agrupados aparte y señalados. Sale
  también en PDF, Excel, tirilla y correo, como el resto de vistas.
- **1.1** — Nuevo filtro por **forma de pago** y dos salidas más junto a PDF y
  Excel: **Tirilla** (impresora térmica) y **Correo** (con el PDF adjunto). Las
  cuatro salidas respetan los filtros aplicados. El envío por correo llega con
  el correo de la empresa ya escrito, editable. La pantalla pasó al diseño
  estándar de los reportes: título, filtros e indicadores en una sola tarjeta que
  **queda fija arriba** al desplazarse, y la tabla debajo creciendo hacia abajo
  en vez de dentro de una caja con scroll propio.
- **1.0** — Versión inicial.
