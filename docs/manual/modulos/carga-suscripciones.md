---
titulo: Carga de Suscripciones por Excel
slug: modulos/carga-suscripciones
etiquetas: [suscripciones, excel, carga masiva, importar, plantilla, xlsx, alta masiva, recurrente]
visibilidad: usuario
---

## Qué es

La **Carga de Suscripciones** permite dar de alta **muchas suscripciones a la vez**
desde un archivo Excel, en lugar de crearlas una por una en el módulo de
Suscripciones. Se descarga una plantilla que ya trae los catálogos de la empresa
(clientes, productos, periodicidades y tarifas de IVA), se completa y se sube. El
sistema revisa todo el archivo y, si está correcto, **crea** las suscripciones.

Esta carga **solo crea** suscripciones nuevas: no actualiza ni elimina las
existentes.

## Requisitos

- Los **clientes** y **productos** que use en el archivo **deben existir** ya en la
  empresa. Se referencian por el **RUC/cédula** del cliente y por el **código** del
  producto (ambos vienen listados en las hojas de consulta de la plantilla).
- Permiso de **crear** en el módulo para poder subir y aplicar el archivo.

## Cómo se usa

1. **Descargue la plantilla** (botón *Descargar plantilla*). Viene con cuatro hojas
   de consulta pobladas: `Ref_Clientes`, `Ref_Productos`, `Ref_Periodicidades` y
   `Ref_IVA`.
2. Complete la hoja **Suscripciones**: una fila por cada suscripción. Invente una
   **CLAVE** (por ejemplo `SUS-001`) para identificarla.
3. Complete la hoja **Detalle**: una fila por cada producto/servicio de la
   suscripción, repitiendo la misma **CLAVE** de la cabecera. Cada suscripción debe
   tener al menos una línea.
4. **Suba el archivo** y pulse *Revisar archivo*. No se guarda nada todavía: se
   muestra un resumen (a crear, bloqueadas, filas con error) y el detalle de los
   problemas.
5. Si todo está bien, pulse **Crear suscripciones**. Las filas con error se omiten
   y el resto se crea (aplicación parcial).

> No borre ni agregue hojas al libro, ni cambie los encabezados de las columnas:
> el sistema rechaza el archivo. Descargue la plantilla nuevamente si se dañó.

## Campos

### Hoja Suscripciones (cabecera)

| Columna | Obligatorio | Descripción |
|---|---|---|
| CLAVE | Sí | Identificador que usted inventa para enlazar la cabecera con su detalle. Único en el archivo. |
| RUC_CLIENTE | Sí | RUC o cédula de un cliente existente (vea `Ref_Clientes`). |
| PERIODICIDAD | Sí | Código de periodicidad (mensual, trimestral, anual… vea `Ref_Periodicidades`). |
| FECHA_INICIO | Sí | Fecha de inicio, formato AAAA-MM-DD. |
| FECHA_FIN | No | Fecha de fin (opcional). Debe ser posterior a la de inicio. |
| PROXIMO_COBRO | No | Si se deja vacío, se toma la fecha de inicio. |
| FORMA_COBRO | No | Credito o Tarjeta (por defecto Credito). |
| TIPO_COMPROBANTE | No | Factura o Recibo (por defecto Factura). |
| ESTADO | No | Activo, Pausado, Suspendido o Cancelado (por defecto Activo). |
| OBSERVACIONES | No | Texto libre. |
| INFO_CONCEPTO / INFO_DETALLE | No | Información adicional (un par concepto/detalle). Se llenan juntos o se dejan ambos vacíos. |

### Hoja Detalle (productos/servicios)

| Columna | Obligatorio | Descripción |
|---|---|---|
| CLAVE | Sí | La misma CLAVE de la suscripción en la hoja Suscripciones. |
| CODIGO_PRODUCTO | Sí | Código de un producto existente (vea `Ref_Productos`). |
| DESCRIPCION | No | Si se deja vacía, se usa el nombre del producto. |
| CANTIDAD | Sí | Mayor que cero. |
| PRECIO_UNITARIO | No | Si se deja vacío, se usa el precio del producto. |
| CODIGO_IVA | No | Si se deja vacío, se usa la tarifa de IVA del producto. |

## Permisos

- **Ver / descargar plantilla**: permiso de lectura (`r`).
- **Subir, revisar y crear**: permiso de creación (`w`).

## Reglas de negocio

- La CLAVE debe ser única dentro del archivo; una CLAVE repetida se marca con error.
- Toda suscripción debe tener al menos una línea de detalle válida.
- Si una línea de detalle tiene error, **su suscripción completa se bloquea** (no se
  crea), para no perder ningún ítem.
- Cada suscripción se crea con su propia transacción a través del módulo de
  Suscripciones, de modo que hereda sus validaciones, el cálculo del próximo cobro y
  la auditoría. Un fallo aislado no detiene el resto de la carga.
- La plantilla lleva embebida la empresa para la que se generó: no se puede aplicar
  en otra empresa.

## Integraciones

- **Suscripciones**: las suscripciones creadas aparecen en su módulo y, según su
  periodicidad, generan **Factura de Venta** o **Recibo de Venta** en la
  automatización de facturación de suscripciones.
- **Clientes** y **Productos**: se referencian por RUC/cédula y por código; deben
  existir previamente.
- **Auditoría**: la carga registra un resumen en `log_sistema`
  (acción `carga_masiva_excel`, tabla `suscripciones`).

## Errores frecuentes

- *"Al archivo le faltan hojas…"*: borró o renombró una hoja. Descargue la plantilla
  de nuevo.
- *"Esta plantilla se generó para otra empresa"*: descargue la plantilla desde la
  empresa en la que quiere cargar.
- *"No existe un cliente con RUC/cédula…"* o *"El CODIGO_PRODUCTO … no existe"*: cree
  primero el cliente o el producto, o corrija el valor con uno de la hoja de consulta.
- *"La suscripción no tiene ninguna línea…"*: agregue al menos una fila en la hoja
  Detalle con la misma CLAVE.

## Historial de cambios

- **1.0** — Versión inicial. Carga masiva de suscripciones (solo alta) con plantilla
  de dos hojas de datos (Suscripciones + Detalle) y hojas de consulta de clientes,
  productos, periodicidades e IVA.
