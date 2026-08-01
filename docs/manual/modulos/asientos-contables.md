---
titulo: Asientos contables
resumen: Registro contable de cada operación; la mayoría se genera sola desde los documentos.
categoria: Contabilidad
ruta_modulo: modulos/asientos_contables
tipo: modulo
visibilidad: todos
etiquetas: asientos, asiento contable, diario, debe, haber, partida doble, cuadrado, comprobante, contabilidad, imprimir, pdf, excel, documento origen
version: 1.1
orden: 20
estado: activo
---

Los **asientos contables** son el registro de cada operación en las cuentas. La
inmensa mayoría **no se escribe a mano**: la genera el sistema al guardar una
factura, un ingreso, un egreso, una compra o un traspaso, según la configuración
contable de la empresa.

Este módulo sirve para consultarlos, y para registrar los asientos de diario que
no nacen de ningún documento (provisiones, depreciaciones, ajustes).

## Un asiento tiene que cuadrar

La regla que el sistema no deja saltarse: **el total del Debe debe ser igual al
total del Haber**. Si no cuadran, el mensaje muestra ambas cifras para que vea la
diferencia.

Además:

- Debe tener **al menos un detalle de cuenta**.
- Todos los valores deben ser **mayores a cero**.
- La suma de los detalles debe coincidir con el total del asiento.

## Datos obligatorios

| Campo | Regla |
|-------|-------|
| Fecha | Obligatoria |
| Tipo de comprobante | Obligatorio |
| Concepto | Obligatorio: explica de qué se trata el asiento |
| Detalle | Al menos una línea, cuadrada y con valores mayores a cero |

## Asientos automáticos frente a asientos de diario

- **Automáticos**: nacen de un documento. Si el documento se modifica, el asiento
  se regenera; si se anula, el asiento se anula. **No conviene editarlos a mano**:
  el sistema los vuelve a generar desde el documento.
- **De diario**: los escribe el contador. Son los únicos que se mantienen tal
  cual se escribieron.

## Diferencias de centavos

En documentos con impuestos, la base y el IVA pueden dejar diferencias de un
centavo al redondear. El sistema las absorbe automáticamente en la línea de mayor
monto del lado que quedó corto. Un descuadre mayor que un redondeo sí detiene el
proceso: eso indica un error real.

## Imprimir en PDF o Excel

Al abrir un asiento ya guardado aparece, debajo del título del modal, una barra
con dos botones:

- **PDF** (ícono rojo): descarga el asiento con su cabecera, el detalle de
  cuentas (con centro de costo, proyecto y documento/ref) y los totales de
  Debe y Haber.
- **Excel** (ícono verde): la misma información en una hoja de cálculo.

Estos botones no aparecen en un asiento nuevo sin guardar, porque todavía no
tiene número de comprobante.

## Ver el documento que originó el asiento

Cuando el asiento nace de un documento (factura de venta, compra, ingreso,
egreso, nota de crédito/débito, retención, liquidación de compra, importación,
consignación, etc.), la misma barra muestra el botón **Ver Documento**: abre
una ventana de solo lectura con el documento completo, sin salir del asiento.
El botón no aparece en asientos manuales (tipo Diario) ni en los generados por
nómina, activos fijos, declaraciones o traspasos, porque esos procesos no
tienen un documento individual con tercero que mostrar.

## Errores frecuentes

- **"El asiento no está cuadrado"**: el mensaje muestra el total del Debe y del
  Haber; la diferencia le dice qué línea falta o sobra.
- **"El asiento debe contener al menos un detalle de cuenta"**: falta añadir
  líneas.
- **Modifiqué un asiento automático y volvió a cambiar**: es el comportamiento
  esperado; corrija el documento de origen, no el asiento.

## Historial de cambios

- **1.1** — Botones de impresión en PDF y Excel del asiento, y botón para ver
  el documento origen (factura, compra, egreso, etc.) sin salir del modal.
- **1.0** — Versión inicial.
