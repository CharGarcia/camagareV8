---
titulo: Personalizar un reporte para toda la empresa
resumen: El administrador elige qué columnas muestra un reporte en pantalla, Excel y PDF, y si el PDF sale vertical u horizontal. Aplica a todos los usuarios de la empresa. Disponible en Reporte de Ventas, Reporte de Compras, Cuentas por Cobrar, Cuentas por Pagar y Reporte de Cartera.
categoria: Reportes
tipo: guia
visibilidad: todos
etiquetas: personalizar reporte, personalizar, columnas del reporte, ocultar columnas, mostrar columnas, quitar columna, no quiero ver una columna, columnas del pdf, columnas del excel, orientación del pdf, pdf horizontal, pdf vertical, hoja horizontal, hoja vertical, apaisado, retrato, configurar reporte, reporte por empresa, botón personalizar, no veo el botón personalizar, restablecer reporte, solo administradores, nivel 2, nivel 3, reporte de compras, reporte de ventas, cuentas por cobrar, cuentas por pagar, cartera, agregar columna, columna ruc, columna vendedor, raya, columna no disponible, reporte de cartera, estado de cuenta
version: 1.3
orden: 37
estado: activo
---

## Qué es personalizar un reporte

Cada empresa puede decidir **cómo quiere ver sus reportes**: qué columnas
aparecen en la tabla, cuáles salen en el PDF y si la hoja del PDF va vertical u
horizontal. Lo configura un administrador una sola vez y queda así para **todos
los usuarios de esa empresa**.

No cambia los datos ni los totales: solo qué columnas se muestran.

## Quién puede personalizar

Solo los **administradores y superadministradores** (usuarios de nivel 2 y 3). A
un usuario de nivel 1 el botón **Personalizar** no le aparece; él ve el reporte
tal como lo dejó el administrador.

## En qué reportes está disponible

| Reporte | Disponible | Qué se personaliza |
|---------|------------|--------------------|
| Reporte de Compras | Sí | Todas sus vistas (detallado, por proveedor, por producto, por fecha, por mes) |
| Cuentas por Cobrar | Sí | La vista Detallado |
| Cuentas por Pagar | Sí | La vista Detallado |
| Reporte de Ventas | Sí | Todas sus agrupaciones; se eligen las columnas a ocultar |
| Reporte de Cartera | Sí | El estado de cuenta de cada cliente o proveedor (también lo que se envía por correo) |

En Cuentas por Cobrar y Cuentas por Pagar las vistas *Por cliente / Por
proveedor*, *Por producto* y *Por antigüedad* tienen una estructura propia y no
se personalizan.

## Cómo se usa

1. Abra el reporte y pulse **Personalizar**, arriba a la derecha, en el
   encabezado de los filtros.
2. En **Vista del reporte** elija la vista que quiere ajustar (por ejemplo
   *Detallado* o *Por proveedor*). Cada vista se configura por separado. La
   ventana se abre en la vista que tiene en pantalla.
3. Marque o desmarque las columnas. Hay una casilla por cada medio:
   - En el **Reporte de Compras**: **Pantalla y Excel** (la tabla y el Excel
     llevan las mismas columnas) y **PDF**.
   - En **Cuentas por Cobrar**, **Cuentas por Pagar**, el **Reporte de
     Ventas** y el **Reporte de Cartera**: **Pantalla**, **Excel** y **PDF**,
     cada uno por separado.
4. Elija la **Orientación del PDF**.
5. Pulse **Guardar**. Si ya había generado el reporte, la tabla se actualiza
   sola con las columnas nuevas.

Puede cambiar varias vistas antes de guardar: se guardan todas juntas. Las
vistas ya personalizadas se marcan con un punto (•) en la lista.

## Columnas en pantalla, Excel y PDF

El PDF se elige aparte porque la hoja tiene un ancho limitado: es normal mostrar
todo en pantalla y dejar el PDF con menos columnas para que se lea mejor.

En el Reporte de Compras la pantalla y el Excel comparten las mismas columnas.
En Cuentas por Cobrar y Cuentas por Pagar el Excel también se elige aparte,
porque suele llevar más detalle que la pantalla (RUC, días, notas de crédito,
retenciones). Ahí **cualquier columna se puede activar en cualquier medio**: por
ejemplo, agregar el RUC a la pantalla o las retenciones al PDF.

En el **Reporte de Ventas** cada formato conserva sus columnas propias y se
elige cuáles ocultar. Una **raya (—)** en lugar de la casilla significa que esa
columna no existe en ese formato (por ejemplo, la *Clave de acceso* solo va en
el Excel). Las columnas que dependen de los datos —los meses, las categorías,
*Motivo* y *Estab.*— no aparecen en la ventana y siempre se muestran.

En el **Reporte de Cartera** las siete columnas del estado de cuenta existen en
los tres formatos. Si se oculta *Saldo*, el saldo anterior se escribe junto a su
etiqueta en la primera fila.

Las columnas con **candado** identifican la fila (por ejemplo el número de
documento o el nombre del proveedor) y siempre se muestran.

Al ocultar una columna de importes, su total también deja de mostrarse en la
fila de totales. Las tarjetas de resumen de arriba no cambian.

## Orientación del PDF: vertical u horizontal

| Opción | Qué hace |
|--------|----------|
| Automática | La orientación de siempre del reporte (se indica entre paréntesis) |
| Vertical | Hoja A4 en vertical |
| Horizontal | Hoja A4 en horizontal |

En **vertical** la hoja es más angosta. Las columnas se reparten el ancho
disponible y la letra se reduce cuando hay muchas; para que el PDF se lea bien
conviene dejar **9 columnas o menos** en vertical y **12 o menos** en
horizontal.

## Restablecer el reporte

El botón **Restablecer** (abajo a la izquierda) devuelve **todas las vistas** de
ese reporte a sus columnas y orientación originales, para toda la empresa. Pide
confirmación antes de hacerlo.

## Reglas

- La personalización es **por empresa**: cada empresa (establecimiento) tiene la
  suya. En un reporte consolidado por RUC se usa la de la empresa activa.
- Si nadie ha personalizado el reporte, se ve y se exporta como siempre.
- Cada cambio queda registrado en la auditoría del sistema (quién y cuándo).

## Errores frecuentes

| Qué pasa | Por qué | Qué hacer |
|----------|---------|-----------|
| No veo el botón Personalizar | Su usuario es de nivel 1 | Pídalo a un administrador de la empresa |
| En el PDF vertical los valores se ven apretados | Hay demasiadas columnas para el ancho de la hoja | Desmarque columnas en **PDF** o use horizontal |
| Un usuario dice que le falta una columna | El administrador la ocultó para la empresa | Vuelva a marcarla en Personalizar |
| «Falta ejecutar … reportes_personalizacion.sql» | La base de datos aún no tiene la tabla de esta función | Avise a soporte |

## Historial de cambios

- **1.3** (11-11-2026) — Disponible en el **Reporte de Cartera** (estado de cuenta por cliente o proveedor).
- **1.2** (10-11-2026) — Disponible en el **Reporte de Ventas** (todas sus agrupaciones).
- **1.1** (10-11-2026) — Disponible en **Cuentas por Cobrar** y **Cuentas por Pagar** (vista Detallado), con columnas elegibles por separado para pantalla, Excel y PDF.
- **1.0** (10-11-2026) — Versión inicial, disponible en el Reporte de Compras.
