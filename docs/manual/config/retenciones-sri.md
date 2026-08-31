---
titulo: Retenciones SRI
resumen: Catálogo de códigos de retención del SRI (código, concepto, porcentaje, impuesto y vigencia) que alimenta las retenciones de compra y de venta.
categoria: Configuración
ruta_modulo: config/retenciones-sri
requiere_permiso_modulo: no
tipo: modulo
visibilidad: superadmin
etiquetas: retenciones sri, codigos de retencion, porcentaje de retencion, renta, iva, isd, codigo ats, anexo transaccional, vigencia, desde hasta, catalogo tributario
version: 1.0
orden: 0
estado: activo
---

Esta pantalla mantiene el **catálogo de códigos de retención del SRI**: el código,
su concepto, el porcentaje que se retiene, el impuesto al que corresponde (Renta,
IVA o ISD), el código para el anexo transaccional y las fechas entre las que el
código está vigente.

Es un catálogo **global del sistema**: no pertenece a ninguna empresa y lo que se
edita aquí lo usan todas.

## Qué es y para qué sirve

Es la fuente de los códigos que aparecen al emitir una **retención de compra** o
una **retención de venta**: cuando en el modal se busca un código, se está
buscando en esta tabla. También alimenta las retenciones predeterminadas de la
ficha del proveedor y el Formulario 103.

## Requisitos previos

Acceso de **nivel 2 (administrador) o superior**. La pantalla está en
Configuración; los usuarios de nivel 1 no la ven.

## Cómo se usa

1. Entre a **Configuración → Retenciones SRI**.
2. Use el buscador de la parte superior para localizar un código.
3. Haga clic en una fila para abrir su ficha y modificarla, o use el botón de
   **Crear nuevo** para agregar un código que no exista.
4. Guarde. El cambio queda disponible de inmediato para todas las empresas.

## Cómo buscar

El buscador cubre **todas las columnas de la tabla**: código, descripción,
porcentaje, impuesto, código del anexo (ATS), estado y vigencia.

- **Porcentaje**: escriba `1.75`, `1,75` o `1.75 %` — da lo mismo; el signo y la
  coma decimal se interpretan igual.
- **Estado**: escriba `activo` o `inactivo`.
- **Fechas de vigencia**: escríbalas como se ven en la tabla, `01-01-2026`.
- Puede escribir **varias palabras** en cualquier orden (`iva 100`, `renta
  honorarios`): se muestran las filas que contengan todas. No importan mayúsculas
  ni tildes.

Las columnas se ordenan haciendo clic en su encabezado.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Código | Sí | Código de retención del SRI (por ejemplo `303`) |
| Descripción | No | Concepto de la retención, como lo nombra el SRI |
| Porcentaje | Sí | Porcentaje que se retiene sobre la base imponible |
| Impuesto | Sí | RENTA, IVA o ISD |
| Cód. ATS | No | Código con el que el concepto se reporta en el anexo transaccional |
| Estado | Sí | Solo los códigos **activos** se ofrecen al emitir retenciones |
| Desde / Hasta | No | Vigencia del código. En blanco significa "sin límite" por ese lado |

## Permisos

Es una pantalla de configuración global: solo **nivel 2 o superior**. No depende
de la empresa activa ni de los permisos por módulo, y lo que se cambia aquí
afecta a todas las empresas del sistema.

## Reglas de negocio

- No pueden existir dos códigos con el **mismo código, descripción y porcentaje**.
- No pueden existir dos códigos con la **misma descripción y la misma vigencia**
  (desde-hasta). Así es como conviven dos versiones de un mismo concepto cuando el
  SRI cambia su porcentaje: la anterior se cierra con una fecha *Hasta* y la nueva
  empieza al día siguiente.
- Al emitir una retención solo se ofrecen los códigos **activos** y **vigentes a
  la fecha de emisión** del comprobante.

## Integraciones con otros módulos

- **Retenciones de compra** y **Retenciones de venta**: el selector de código de
  cada línea lee este catálogo.
- **Proveedores**: las retenciones predeterminadas de la ficha salen de aquí.
- **Declaración de retenciones (Formulario 103)**: cada código lleva el casillero
  de base y de valor con el que se declara.

## Errores frecuentes

- **"Ya existe una retención con el mismo código, descripción y porcentaje"**: el
  código ya está registrado; búsquelo y edítelo en lugar de crear otro.
- **"Ya existe una retención con la misma descripción y vigencia"**: para
  registrar una nueva versión de un concepto, cierre antes la anterior poniéndole
  fecha *Hasta*.
- **Un código no aparece al emitir una retención**: revise su **estado** y su
  **vigencia**. Si la fecha de emisión del comprobante queda fuera del rango
  Desde-Hasta, el código no se ofrece.

## Historial de cambios

- **1.0** — Versión inicial. Incluye el buscador por todas las columnas de la tabla.
