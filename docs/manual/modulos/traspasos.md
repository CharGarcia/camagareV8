---
titulo: Traspasos
resumen: Traslado de dinero entre formas de pago, por ejemplo de caja a banco, con su asiento contable.
categoria: Tesorería
ruta_modulo: modulos/traspasos
tipo: modulo
visibilidad: todos
etiquetas: traspaso, traspasos, transferencia interna, caja a banco, deposito, mover dinero, saldo, formas de pago, excel, exportar, numeracion por fecha, numero con el año, reiniciar numeracion, reinicio anual, reinicio mensual, correlativo por año, correlativo por mes, modo de numeracion, buscar traspaso, buscador, filtros, filtrar traspasos, chips, imprimir, impresora
version: 1.5
orden: 30
estado: activo
---

Un **traspaso** mueve dinero de una forma de pago a otra dentro de la misma
empresa: depositar en el banco lo recaudado en caja, pasar fondos entre dos
cuentas bancarias, entregar efectivo a caja chica.

No es un ingreso ni un egreso: el dinero no entra ni sale de la empresa, solo
cambia de sitio. Por eso no afecta a cuentas por cobrar ni por pagar.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija la forma de pago de **origen** (de dónde sale el dinero).
3. Elija la de **destino** (a dónde va).
4. Indique el monto y la fecha.
5. Guarde.

## Reglas que aplica el sistema

- **No se puede traspasar más de lo disponible.** Si el saldo del origen no
  alcanza, el aviso indica cuánto hay realmente disponible.
- **El origen debe tener saldo determinable**: no sirve una forma de pago de tipo
  *Anticipo*, ni una que esté inactiva.
- **El secuencial no se repite**: si el número ya existe, hay que usar otro.
- **El periodo contable manda**: no se registra ni se anula un traspaso en un
  periodo cerrado.

## Buscar y filtrar el listado

Arriba de la tabla hay un solo grupo: el botón del **embudo**, el cuadro de
búsqueda y el botón de columnas.

**Búsqueda libre.** Escriba cualquier cosa en el cuadro y el listado se filtra
solo, sin menús ni sugerencias. Busca en las columnas del traspaso: N° traspaso,
secuencial, fecha, origen, destino y monto, y además en la observación y el usuario
que lo registró. La columna **Estado** no entra en la búsqueda libre: para filtrar
por ella use la ventana de filtros. Puede escribir varias palabras en cualquier
orden y no importan mayúsculas ni tildes. Para limpiar, borre el texto o pulse
Escape en el cuadro. Mientras busca, aparece un **círculo girando** al final del
cuadro y la tabla se ve atenuada.

**Filtros.** Pulse el **embudo** para abrir la ventana con los criterios. Llene los
que necesite y pulse **Aplicar**; nada se aplica hasta ese momento. La ventana solo
se cierra con la X, Cancelar, Aplicar o Limpiar filtros. No tiene pestaña
*Detalles*: un traspaso no tiene líneas internas.

| Bloque | Filtros |
|--------|---------|
| Documento | Fecha (con atajos *Hoy*, *Esta semana*, *Este mes*, *Mes pasado*, *Este año*), estado (registrado / anulado), con o sin asiento contable, serie, secuencial, N° traspaso, usuario que lo registró, monto (mínimo y máximo) |
| Cuentas | Forma de pago de origen, forma de pago de destino, observación |

Los selectores *Serie*, *Origen*, *Destino* y *Usuario* listan solo lo que la
empresa ya usó en sus traspasos.

**Chips.** Cada filtro aplicado aparece como una etiqueta **dentro del cuadro de
búsqueda**. La **×** quita solo ese filtro, y con el cuadro vacío la tecla
**Retroceso** quita el último.

## Anular

Un traspaso se **anula**, no se elimina, y su asiento contable se anula con él.
Un traspaso ya anulado no se puede volver a anular.

## Asiento contable

Cada traspaso genera su asiento automáticamente: acredita la forma de pago de
origen y debita la de destino, según la configuración contable de la empresa.

## Comprobante en PDF y Excel

Al abrir un traspaso ya guardado, la barra de acciones superior del modal
muestra el botón **PDF** (comprobante de traspaso) y, junto a él, el botón
**Excel**: descarga el mismo comprobante (fecha, estado, concepto y el
movimiento origen → destino con el monto) en un archivo `.xlsx`. Ambos botones
quedan ocultos mientras el traspaso es nuevo y no se ha guardado.

## Errores frecuentes

- **"Saldo insuficiente en la forma de pago de origen"**: el mensaje indica el
  disponible real. Revise si hay movimientos posteriores que no esperaba.
- **"No se pudo determinar el saldo de la forma de pago de origen"**: es de tipo
  anticipo o está inactiva; elija otra.
- **"El número de secuencial ya existe"**: cambie el número.
- **"El periodo contable está cerrado"**: la fecha cae en un mes cerrado.

## Numeración por fecha de emisión

Por defecto el número de estos documentos es un **correlativo corrido** que nunca
se reinicia (`000000017`). En **Empresa → Secuenciales** se puede configurar, por
cada punto de emisión, que el correlativo **vuelva a empezar en cada periodo**:

- **Anual** → `202600017` (documento 17 del año 2026)
- **Mensual** → `202609017` (documento 17 de septiembre de 2026)

Con ese modo activo, **al cambiar la fecha del documento su número se recalcula
solo**, para que caiga en el periodo correcto. Una vez guardado, el número queda
fijo aunque después se le cambie la fecha, y los documentos ya emitidos conservan
siempre el que tenían.

El detalle completo (qué tipos lo permiten, qué pasa al cambiar de modo y cuántos
documentos admite cada periodo) está en el manual de **Empresa**, sección
*Secuenciales por punto de emisión*.

## Historial de cambios

- **1.5** — El botón **PDF** del documento pregunta ahora si se quiere
  **Imprimir** (abre el cuadro de impresión con el documento ya cargado),
  **Descargar** o **Ver** en otra pestaña. Ver la guía *Descargar archivos*.

- **1.4** — Asiento contable pendiente: si faltaba una cuenta o el período estaba cerrado, el documento quedaba sin asiento y nada lo volvía a intentar. Ahora, al completar la configuración contable, el asiento se genera solo la próxima vez que alguien abra el módulo (igual que en Facturas de Venta), o desde la sincronización de Asientos Contables. Respeta el interruptor *Módulos que contabilizan*.
- **1.3** — Nuevo buscador del listado: el cuadro ya no despliega sugerencias; lo que
  se escribe se busca en las columnas del traspaso (incluidos fecha y monto), la
  observación y el usuario, salvo Estado. Los filtros pasan a una **ventana propia**
  (botón del embudo, se aplican con *Aplicar*) con criterios nuevos: con/sin asiento,
  usuario, y origen y destino como listas de las formas de pago usadas. Los filtros
  activos se ven como etiquetas dentro del cuadro y la tabla se atenúa mientras busca.
- **1.2** — El número del documento puede numerarse **por fecha de emisión**,
  reiniciando el correlativo cada año o cada mes (`202600017`, `202609017`). Se
  activa por punto de emisión en **Empresa → Secuenciales**; por defecto sigue
  siendo el correlativo corrido de siempre.

- **1.1** — Botón para exportar el comprobante a Excel, junto al de PDF, en la
  barra de acciones superior del modal.
- **1.0** — Versión inicial.
