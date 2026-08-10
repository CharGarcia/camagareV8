---
titulo: Reasignar establecimiento
resumen: Cambia en lote la sucursal (establecimiento) a la que pertenece un documento ya registrado, sin alterar su número.
categoria: Configuración
ruta_modulo: modulos/reasignar-establecimiento
tipo: modulo
visibilidad: todos
etiquetas: reasignar establecimiento, cambiar sucursal, establecimiento de compras, atribución, migración, importación, corregir establecimiento, mover documentos de sucursal
version: 1.0
orden: 0
estado: activo
---

Sirve para corregir la **sucursal (establecimiento)** a la que quedaron atribuidos
documentos que ya están registrados —normalmente **compras y retenciones de venta
migradas o importadas** que cayeron en el establecimiento equivocado— y hacerlo de
a muchos, filtrando por fecha y sucursal.

## Qué es y para qué sirve

Cuando se importa o migra un lote de documentos, todos entran atribuidos a un mismo
establecimiento (la **matriz**), aunque algunos pertenezcan a otra sucursal. Este
módulo permite **seleccionar varios documentos por filtros y reasignarlos** a otro
establecimiento de la misma empresa, en una sola operación. **No cambia el número
del documento** (en compras, la serie es del proveedor; en retención de venta, del
cliente): solo cambia la atribución interna usada en reportes.

Requiere que la empresa tenga **más de un establecimiento**; si solo tiene uno, el
módulo lo indica y no hay nada que reasignar.

## Requisitos previos

- Empresa activa con **dos o más establecimientos** (Empresa → Establecimientos).
- Los documentos a reasignar ya deben estar registrados/migrados.

## Cómo se usa

1. Elige el **tipo de documento**: Compras (incluye NC/ND de compra) o Retenciones de venta.
2. Aplica filtros: **rango de fechas**, **establecimiento origen** y/o texto (proveedor/cliente/número).
3. Presiona **Buscar**. Se listan los documentos y un resumen de cuántos hay por establecimiento actual.
4. Marca los documentos a mover (o usa *Seleccionar todos*).
5. Elige el **establecimiento destino** y presiona **Reasignar seleccionados**.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Tipo de documento | Sí | Qué documentos vas a reasignar (compras o retenciones de venta) |
| Desde / Hasta | No | Rango de fecha de emisión para acotar la búsqueda |
| Establecimiento origen | No | Muestra solo los documentos que hoy están en esa sucursal |
| Buscar | No | Filtra por RUC/nombre del proveedor o cliente, o por número |
| Establecimiento destino | Sí | Sucursal a la que se moverán los documentos seleccionados |

## Permisos

- **Ver** (r): entrar y listar documentos.
- **Actualizar** (u): ejecutar la reasignación.
- **Acceso total** (t): si lo tiene, ve y reasigna documentos de toda la empresa; si
  no, solo los que él creó.

## Reglas de negocio

- La reasignación cambia únicamente `id_establecimiento` del documento seleccionado.
  **No modifica el número del documento** ni su autorización.
- **No hay cascada**: pagos y contabilidad no guardan establecimiento (no se tocan);
  las **retenciones de compra** y el **inventario** se dejan intactos a propósito.
- Es **reversible**: basta volver a reasignar al establecimiento anterior.
- Cada cambio se registra en la **auditoría** del sistema (`log_sistema`).
- Las **compras migradas** nacen atribuidas a la matriz; con este módulo se mueven
  solo las excepciones. Las **retenciones de venta** reciben una atribución propia de
  establecimiento (independiente del número del cliente), inicializada en la matriz.

## Integraciones con otros módulos

- **Compras** y **Retenciones de venta**: es la fuente de los documentos y donde se
  refleja el cambio (reportes por establecimiento, ATS).
- **Migración desde base anterior**: es el caso de uso típico (documentos importados
  que quedaron en el establecimiento equivocado).

## Errores frecuentes

- **"Esta empresa tiene un solo establecimiento"**: no hay a dónde reasignar; crea
  otro establecimiento primero.
- **El botón Reasignar está deshabilitado**: falta seleccionar documentos o elegir el
  establecimiento destino, o no tienes permiso de actualización.
- **Se listan hasta 200**: afina el filtro (fecha/sucursal) y reasigna por bloques.

## Historial de cambios

- **1.0** — Versión inicial: reasignación en lote de compras (incl. NC/ND de compra) y
  retenciones de venta, con filtros, vista previa por establecimiento y auditoría.
