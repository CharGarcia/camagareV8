---
titulo: Pedidos
resumen: Registra lo que un cliente encarga, con fecha y hora de entrega y responsable, para después entregarlo en consignación o facturarlo.
categoria: Ventas
ruta_modulo: modulos/pedidos
tipo: modulo
visibilidad: todos
etiquetas: pedidos, pedido de cliente, encargo, orden de pedido, reserva, entregas, despacho, agenda de entrega, hora de entrega, responsable de entrega, rango horario, pedidos pendientes
version: 1.1
orden: 0
estado: activo
---

Un pedido es el compromiso de entregar algo: qué productos, a qué cliente, qué día
y en qué rango de horas, y quién lo lleva. No mueve inventario ni maneja precios —
eso ocurre después, cuando el pedido se convierte en una **consignación de venta**
o en una **factura**. Sirve para organizar la agenda de entregas y para que nadie
despache de memoria.

## Qué es y para qué sirve

Está en el menú **Ventas → Pedidos**. Se usa para:

- Anotar el encargo del cliente con su fecha y su ventana horaria de entrega.
- Asignar el responsable que hará la entrega.
- Saber qué queda pendiente de despachar (el contador de pendientes del sistema
  se alimenta de aquí).
- Servir de origen para la consignación o la factura, sin volver a teclear las
  líneas.

Los pedidos son por empresa: solo se ven los de la empresa activa.

## Requisitos previos

- **Puntos de emisión con secuencial para Pedidos** (Empresa → Puntos de emisión).
  Sin eso el pedido no puede numerarse y el sistema lo avisa al abrir el formulario.
- **Clientes** registrados (se pueden crear desde el propio modal del pedido).
- **Productos** registrados.
- **Responsables de traslado**, que también se pueden crear al vuelo desde el pedido.

## Cómo se usa

1. **Nuevo** abre el formulario. La serie y el secuencial se llenan solos según el
   punto de emisión.
2. Busque el **cliente** por nombre o identificación.
3. Ponga la **fecha del pedido**, la **fecha de entrega** y el **rango horario**
   (hora inicial y hora máxima).
4. Elija el **responsable de entrega**.
5. Agregue los **productos** con su cantidad. No se piden precios.
6. Guarde. El pedido nace en estado **Pendiente**.
7. Para modificarlo, haga clic en la fila del listado.

Desde el formulario de un pedido guardado, la barra superior permite descargarlo en
**PDF** o **Excel** y **enviarlo por correo** al cliente (la dirección se puede
editar antes de enviar).

### El listado

- **Buscador**: acepta texto libre (número, cliente, responsable, observaciones) y
  filtros por cliente, responsable, observaciones, fecha de emisión, fecha de
  entrega, estado, serie y secuencial. Los botones rápidos filtran Pendientes,
  Facturados, Anulados, Hoy, Este mes, Mes pasado y Este año.
- **Orden**: se ordena haciendo clic en cualquier encabezado y el sistema recuerda
  su elección para la próxima vez. De fábrica muestra **lo más reciente primero**
  (por fecha de emisión).
- **La columna Estado no se ordena alfabéticamente**, sino por el flujo del pedido:
  ascendente muestra primero lo que falta atender — Pendiente, Procesado,
  Facturado, Anulado — y descendente lo invierte.
- **Columnas**: cada usuario puede ocultar las que no usa y ajustar su ancho.
- **PDF y Excel** exportan lo que está en pantalla, con el mismo filtro y el mismo
  orden. El tope es de **500 filas**: si la búsqueda trae más, el sistema pide
  acotarla antes de descargar.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Serie | Sí | Punto de emisión que numera el pedido. Se puede marcar uno como favorito para que venga elegido. |
| Secuencial | Sí | Número del pedido. Lo asigna el sistema y no se escribe a mano. |
| Estado | Sí | Pendiente, Procesado o Anulado. Nace en Pendiente. |
| Cliente | Sí | A quién se le entrega. Se busca por nombre o identificación. |
| Fecha Pedido | Sí | Cuándo se tomó el encargo. |
| Fecha de Entrega | Sí | Cuándo se entrega. No puede ser anterior a hoy ni al día del pedido. |
| Hora Inicial / Hora Máxima | Sí | Ventana horaria de la entrega, en formato 00:00. La inicial debe ser menor que la máxima. |
| Responsable de Entrega | Sí | Quién lleva el pedido. Se puede crear uno nuevo sin salir del formulario. |
| Observaciones Generales | No | Notas que acompañan al pedido (salen en el PDF). |
| Observaciones Internas | No | Notas para el equipo. |
| Productos: Descripción y Cantidad | Sí | Al menos un producto con cantidad mayor a cero. Sin precios. |

## Permisos

Se administran en **Configuración → Permisos por módulo**, sobre la ruta
`modulos/pedidos`:

- **Ver**: entrar al listado y abrir pedidos.
- **Crear**, **Modificar**, **Eliminar**: las acciones correspondientes.
- **Acceso total**: sin este permiso, el usuario solo ve y gestiona **los pedidos
  que él mismo creó**; con él, ve los de toda la empresa. El superadministrador
  siempre ve todo.

## Reglas de negocio

- **Fechas y horas**: la entrega no puede quedar antes de hoy ni antes de la fecha
  del pedido; las horas deben tener formato `00:00` y la inicial debe ser menor
  que la máxima (no pueden ser iguales).
- **Al menos un producto**, y toda cantidad debe ser mayor a cero.
- **El secuencial no se repite**: al guardar, el sistema toma el siguiente número
  bajo un bloqueo, de modo que dos personas guardando a la vez no obtienen el mismo.
- **Un pedido a la vez**: si otra persona lo está editando (aquí o en Consignaciones
  de Venta), el sistema lo avisa con su nombre y no deja guardar encima.
- **Lo ya despachado no se puede deshacer desde el pedido**: si una línea ya fue
  entregada en una consignación o facturada, no se la puede quitar, ni cambiarle el
  producto, ni bajarle la cantidad por debajo de lo ya consumido. Aumentar la
  cantidad o agregar líneas nuevas sí se puede.
- **Eliminar es lógico**: el pedido deja de verse pero no se borra de la base. Y no
  se permite eliminarlo si alguna de sus líneas ya tiene consumo.
- **Estado automático**: al registrar entregas en una consignación, el pedido pasa a
  **Procesado** cuando todas sus líneas quedan completas, y vuelve a **Pendiente**
  si aún falta algo. *Facturado* no se asigna desde este módulo; existe como filtro
  del buscador para los pedidos que se resolvieron por factura.
- **Auditoría**: crear, actualizar y eliminar un pedido quedan registrados en la
  bitácora del sistema (`/config/log-sistema`, módulo *Pedidos*).

## Integraciones con otros módulos

- **Consignaciones de Venta**: la entrega real se registra allí; cada línea
  entregada queda enlazada a la línea del pedido y de ahí sale el control de lo ya
  despachado y el cambio de estado a Procesado.
- **Factura de Venta**: "Facturar desde pedido" arrastra las líneas al formulario de
  la factura y conserva el enlace con la línea de origen, de modo que lo facturado
  también cuenta como consumido.
- **Reporte de Pedidos**: vista de análisis sobre estos mismos datos.
- **Clientes**, **Productos** y **Responsables de Traslado**: catálogos de los que
  se alimenta el formulario.

## Errores frecuentes

- **"Este pedido lo está usando ahora mismo <usuario>"**: alguien lo tiene abierto
  en Pedidos o en Consignaciones de Venta. Espere a que lo cierre.
- **"El secuencial ... ya está en uso para esta serie"**: otro pedido tomó ese
  número mientras usted tenía el formulario abierto. Recargue y guarde de nuevo.
- **"Secuenciales no configurados"**: falta configurar el secuencial de Pedidos para
  ese punto de emisión, en Empresa → Puntos de emisión.
- **"No se puede quitar / cambiar / reducir ... ya tiene N registrado en una
  consignación o factura"**: esa línea ya se despachó. Corrija primero el documento
  que la consumió.
- **"La fecha de entrega no puede ser menor a la fecha actual"**: la entrega quedó
  en el pasado; corrija la fecha.
- **La descarga pide acotar la búsqueda**: el listado supera las 500 filas. Use el
  buscador (por ejemplo, un rango de fechas) y vuelva a exportar.

## Historial de cambios

- **1.1** — El listado abre ordenado por fecha de emisión, del más reciente al más
  antiguo (antes, por número de pedido ascendente). La columna por la que se está
  ordenando ahora se distingue con su flecha desde que se abre la pantalla, y
  ordenar ya no borra el filtro que tuviera puesto en el buscador. Se documentó que
  la columna Estado ordena por el flujo del pedido y no alfabéticamente.
- **1.0** — Versión inicial.
