---
titulo: Proveedores
resumen: Registro de proveedores con sus datos de pago, retenciones y valores predeterminados que agilizan cada compra.
categoria: Compras
ruta_modulo: modulos/proveedores
tipo: modulo
visibilidad: todos
etiquetas: proveedores, proveedor, acreedor, ruc, retencion, cuenta bancaria, plazo, credito, parte relacionada
version: 1.0
orden: 10
estado: activo
---

El módulo de **Proveedores** guarda a quién se le compra. Es la base de las
compras, las retenciones, los egresos y los pagos por transferencia.

Su valor real está en los **valores predeterminados**: bien configurado un
proveedor, cada compra suya llega con la retención, la forma de pago y el
concepto ya propuestos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Complete la **identificación** (RUC o cédula) y la **razón social**.
3. Añada los datos de contacto y ubicación.
4. Configure los valores predeterminados (ver más abajo). No son obligatorios,
   pero es lo que ahorra tiempo después.
5. Guarde.

## Campos

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Identificación | Sí | RUC, cédula o pasaporte |
| Razón social | Sí | Nombre legal, el que sale en los documentos |
| Nombre comercial | No | Cómo se lo conoce habitualmente |
| Dirección, ciudad, provincia | No | Ubicación del proveedor |
| Teléfono, correo | No | Contacto |
| Parte relacionada | No | Marque si lo es. Afecta a la declaración del anexo |
| Estado | Sí | Activo o inactivo |

## Datos de pago

| Campo | Para qué sirve |
|-------|----------------|
| Banco, número y tipo de cuenta | Necesarios para pagarle por transferencia y para generar el archivo bancario |
| Forma de pago predeterminada | Se propone sola al registrar el egreso |
| Plazo | Días de crédito que le da el proveedor. Define cuándo vence la factura en Cuentas por Pagar |
| Monto mínimo / máximo de pago automático | Rango dentro del cual sus documentos entran en los pagos automáticos |

## Retenciones y sustento

| Campo | Para qué sirve |
|-------|----------------|
| Retención de IVA | Porcentaje que se le suele retener de IVA |
| Retención de renta | Porcentaje habitual de retención en la fuente |
| Sustento tributario | El código de sustento con el que se registran sus compras |
| Concepto de egreso predeterminado | Concepto que se propone al pagarle |

Estos valores son **propuestas**, no imposiciones: al registrar la compra o la
retención se pueden cambiar. Configurarlos bien evita el error más común, que es
retener con el porcentaje equivocado por descuido.

## Permisos

Con **acceso total** se ven los proveedores de toda la empresa. Sin ese permiso,
cada usuario ve solo los que creó él — revíselo si alguien reporta proveedores
que "desaparecieron".

## Eliminar

Es una eliminación **lógica**: el proveedor sale del listado y las compras que ya
lo referencian se conservan intactas. Si solo quiere dejar de usarlo, cámbielo a
**inactivo**.

## Errores frecuentes

- **No aparece al registrar una compra**: está inactivo o pertenece a otra empresa.
- **La retención sale con el porcentaje equivocado**: revise las retenciones
  predeterminadas de su ficha; se aplican a cada compra nueva.
- **No se puede pagar por transferencia**: le faltan banco, número o tipo de
  cuenta.
- **La factura vence en la fecha equivocada**: revise el campo *Plazo*.

## Historial de cambios

- **1.0** — Versión inicial.
