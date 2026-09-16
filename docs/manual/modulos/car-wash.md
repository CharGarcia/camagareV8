---
titulo: Servicio de car wash
resumen: Órdenes de lavado con tablero por estado, que luego se convierten en factura o recibo.
categoria: Servicios
ruta_modulo: modulos/car-wash
tipo: modulo
visibilidad: todos
etiquetas: car wash, lavado, lavadora de autos, orden de servicio, tablero, vehiculo, placa, estado del servicio, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, buscar orden, buscar placa, buscador, filtros, filtrar ordenes, filtro de fechas, buscar por servicio, chips
version: 1.2
orden: 10
estado: activo
---

El módulo de **car wash** gestiona las órdenes de lavado: qué vehículo entró, qué
servicio se le hace, en qué estado está y quién lo atiende.

Está pensado para tablet: el tablero se maneja de pie, junto al vehículo.

## El tablero por estado

Las órdenes se organizan en columnas según su estado, de modo que de un vistazo
se sabe qué hay en cola, qué se está lavando y qué está listo para entregar.

Cambiar de estado es mover la orden: no hace falta abrir formularios para
actualizar el avance.

## El recorrido

1. **Recepción**: se registra el vehículo y el servicio solicitado.
2. **Proceso**: la orden avanza por los estados del tablero.
3. **Entrega y cobro**: la orden se convierte en **factura** o **recibo de
   venta**, según lo que pida el cliente.

## La orden no es el documento de venta

Una orden de lavado no es un comprobante: es el control interno del trabajo. La
venta existe cuando se genera la factura o el recibo a partir de ella.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y los botones de columnas, PDF y Excel.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas de la orden: fecha, N°
orden, serie, secuencial, placa, cliente (nombre y RUC / cédula) y total.
Además busca en la marca y el modelo del vehículo, las observaciones y
novedades, el número del documento de venta generado, el usuario que registró
la orden y los **servicios y productos** de la orden (código y descripción). La
columna **Estado** y el tipo de documento generado no entran en la búsqueda
libre: para filtrar por ellos use la ventana de filtros. Puede escribir varias
palabras en cualquier orden y no importan mayúsculas ni tildes. Para limpiar,
borre el texto o pulse Escape en el cuadro. Mientras busca, aparece un
**círculo girando** al final del cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con todos los criterios,
en dos pestañas. Llene los que necesite y pulse **Aplicar**; nada se aplica
hasta ese momento. La ventana solo se cierra con la X, Cancelar, Aplicar o
Limpiar filtros.

**Pestaña Orden** (datos de la cabecera):

| Bloque | Filtros |
|--------|---------|
| Orden | Fecha de ingreso (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (borrador, facturado, anulado), serie, N° orden, secuencial, total (mínimo y máximo), con o sin documento de venta generado, tipo de documento (factura o recibo de venta), N° del documento generado, fecha de entrega y próxima cita |
| Vehículo | Placa, marca, modelo y kilometraje (mínimo y máximo) |
| Cliente y registro | Cliente, RUC / cédula, usuario que registró (lista), observaciones / novedades |

Los selectores *Serie* y *Usuario que registró* listan solo lo que la empresa ya
usó en sus órdenes.

**Pestaña Detalles** (lo que hay dentro de la orden). Es un único cuadro,
**Buscar libremente dentro de las órdenes**: escriba un servicio, un producto,
un código o una novedad, y aparece la lista de **cada línea que coincide** con
la orden a la que pertenece (número, fecha, placa, cliente y estado). Un clic en
la fila deja el listado mostrando solo esa orden; el ícono de la derecha la abre
directamente.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

Si no tiene **acceso total** al módulo, tanto el listado como la pestaña
Detalles muestran solo las órdenes que usted registró.

## Errores frecuentes

- **La orden no aparece en ventas**: falta generar el documento de venta.
- **Una orden lleva días en el tablero**: se quedó sin cerrar; ciérrela o
  anúlela para que el tablero refleje la realidad.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Estos documentos no llevan una fecha de emisión editable: se numeran por la fecha
en que se registran. Los documentos ya emitidos conservan siempre el número que
tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.2** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias;
  lo que se escribe se busca en las columnas de la orden y en sus servicios y
  productos, salvo el Estado. Los filtros pasan a una **ventana propia** (botón
  del embudo, se aplican con *Aplicar*) con dos pestañas: **Orden** (filtros por
  campo, con criterios nuevos: fechas de ingreso, entrega y próxima cita, total,
  documento generado, marca, modelo, kilometraje, RUC, usuario y observaciones)
  y **Detalles**, un cuadro de búsqueda libre dentro de las órdenes. Se quitaron
  los accesos *En proceso* y *Terminados*, que filtraban estados que las órdenes
  ya no usan. Nueva sección *Buscar y filtrar el listado*.

- **1.1** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.0** — Versión inicial.
