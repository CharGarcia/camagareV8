---
titulo: Mejor Cliente
resumen: Ranking de clientes por monto neto o cantidad de documentos, con filtro por asesor y periodo.
categoria: Reportes
ruta_modulo: modulos/mejor_cliente
tipo: modulo
visibilidad: todos
etiquetas: mejor cliente, top clientes, ranking de clientes, cliente top, cuanto compra cada cliente, mejores clientes, cliente estrella, cliente frecuente, cliente que mas compra
version: 1.0
orden: 15
estado: activo
---

El reporte **Mejor Cliente** responde a la pregunta de quiénes son los clientes
que más le compran, ya sea por dinero (monto neto) o por frecuencia (cantidad de
documentos), en el periodo que se indique.

## Qué es y para qué sirve

Arma un ranking de clientes a partir de las Facturas de Venta y/o los Recibos de
Venta emitidos, restando las Notas de Crédito de venta del mismo periodo. Sirve
para identificar a los clientes clave de la empresa (o de un asesor puntual) y
priorizar la atención comercial.

## Requisitos previos

El usuario necesita permiso de lectura sobre **Facturas de Venta** y/o
**Recibos de Venta**: el reporte solo ofrece como fuente aquellas que el usuario
puede consultar. Si no tiene acceso a ninguna de las dos, el reporte no puede
generarse.

## Cómo se usa

1. Elija la **Fuente**: Facturas, Recibos, o ambas (según lo que tenga habilitado).
2. Filtre por **Asesor/Vendedor** si quiere el ranking de un vendedor puntual, o
   deje "Todos" para ver el total de la empresa.
3. Elija el periodo con **Mes/Año** o con **Fecha Desde/Hasta**.
4. Elija el criterio de **Ordenar Por**: Monto Neto o Cantidad de Documentos.
5. Elija cuántos clientes mostrar en **Top** (10, 20, 50, 100 o Todos).
6. Descargue el resultado en **PDF** o **Excel**, o envíelo por **Correo**.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Fuente (Facturas / Recibos) | Sí (al menos una) | Qué documentos cuentan como venta del cliente |
| Asesor/Vendedor | No | Acota el ranking a las ventas de un vendedor específico |
| Ordenar Por | Sí | Monto Neto (dinero) o Cantidad de Documentos (frecuencia) |
| Top | Sí | Cuántos clientes mostrar del ranking |
| Fecha Desde / Hasta | Sí | El periodo a consultar |

## Permisos

Sigue el permiso propio del módulo (ver/crear/actualizar/eliminar no aplica más
allá de "ver", ya que es un reporte de solo lectura). El **acceso total** no
cambia la fuente de datos: el reporte de Mejor Cliente siempre mira los
documentos de toda la empresa, según lo que el usuario tenga permitido ver en
Facturas/Recibos.

## Reglas de negocio

- El **monto neto** es la suma de Facturas y/o Recibos (campo `total_sin_impuestos`,
  es decir sin IVA) menos las Notas de Crédito de venta del mismo cliente y
  periodo.
- Solo se cuentan documentos **autorizados** (Facturas y Notas de Crédito) o
  **no anulados/no borrador** (Recibos). Borradores y anulados no cuentan.
- La **venta promedio** es el monto neto dividido entre la cantidad de
  documentos (Facturas/Recibos, sin contar las Notas de Crédito como documento).
- Al filtrar por **Asesor/Vendedor**, las Notas de Crédito se restan igual al
  cliente sin distinguir de qué vendedor era la venta original (la Nota de
  Crédito no guarda ese dato). En la práctica solo distorsiona el ranking en el
  caso raro de que un cliente compre a más de un vendedor y devuelva algo del
  otro mientras se filtra por uno solo.

## Integraciones con otros módulos

- Lee de **Facturas de Venta** y **Recibos de Venta** (`ventas_cabecera`,
  `recibos_venta_cabecera`) y de **Notas de Crédito en Ventas**
  (`notas_credito_cabecera`).
- El filtro de Asesor/Vendedor usa el catálogo de **Vendedores**.

## Errores frecuentes

- **No aparece la opción de Recibos (o Facturas)**: el usuario no tiene permiso
  de lectura sobre ese módulo.
- **Las cifras no coinciden con Reporte de Ventas**: Reporte de Ventas usa el
  total con impuestos por documento; Mejor Cliente usa el monto **sin
  impuestos** y además resta las Notas de Crédito.
- **Un cliente aparece con monto negativo o menor al esperado**: revise si tiene
  Notas de Crédito grandes en el periodo.

## Historial de cambios

- **1.0** — Versión inicial.
