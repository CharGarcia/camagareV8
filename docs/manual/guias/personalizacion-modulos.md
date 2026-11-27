---
titulo: Personalización de módulos por empresa y por cliente
resumen: En Configuración de Empresa, cada módulo personalizable tiene su pestaña con opciones para toda la empresa y, si la opción lo admite, valores propios para algunos clientes. Hoy: columnas del comprobante de Consignaciones y asiento del rol de pagos.
categoria: Configuración de empresa
tipo: guia
visibilidad: todos
etiquetas: personalizar, roles de pago, asiento del rol, un asiento por empleado, asiento general de nomina, personalizacion, personalizar modulo, opciones por cliente, configuracion por cliente, columnas por cliente, columnas del pdf, columnas del excel, ocultar columnas, mostrar caducidad, caducidad solo para algunos clientes, consignaciones, comprobante de consignacion, pestaña consignaciones, configuracion de empresa, valores por defecto
version: 1.1
orden: 38
estado: activo
---

Algunos módulos se pueden ajustar a la forma de trabajar de cada empresa y, en
ciertos casos, de cada cliente. Todo eso se configura en un solo lugar:
**Configuración de Empresa**, una pestaña por módulo.

## Qué es y para qué sirve

Sirve para que el sistema se adapte sin cambiar nada para las demás empresas.
Cada opción tiene un **valor por defecto**: mientras nadie la toque, el módulo
funciona exactamente igual que antes.

Hay dos niveles:

- **Para toda la empresa**: el valor que usan todos los clientes.
- **Por cliente** (solo en las opciones que lo admiten): una lista de clientes
  con su propio valor. Un cliente de la lista usa **su** valor; los demás, el de
  la empresa.

## Opciones disponibles

| Módulo | Pestaña | Opción | Por cliente |
|--------|---------|--------|-------------|
| Consignaciones de Venta | Consignaciones | Columnas del detalle de productos en el PDF y el Excel del comprobante | Sí |
| Roles de Pago | Roles de Pago | Asiento contable del rol mensual: automático, uno general o uno por empleado | No |

En Consignaciones, las columnas posibles son Código, Descripción, Bodega, Lote,
NUP, Caducidad, Cantidad, Retorno, Facturados y Acon. Descripción y Cantidad
salen siempre. Por defecto salen todas menos Caducidad, que además solo se
imprime en el PDF (el Excel no la lleva). El detalle está en el
manual del módulo **Empresa**, sección *Pestaña Consignaciones*.

En Roles de Pago, **Asiento contable del rol mensual** decide si el rol se
contabiliza en un solo asiento o en uno por empleado (por defecto *Automático*,
como siempre). El detalle está en el manual de **Roles de pago**, sección
*Un asiento general o uno por empleado*.

## Cómo se usa

1. Abra **Configuración de Empresa** y la pestaña del módulo (p. ej.
   **Consignaciones**).
2. Ajuste los valores **para todos los clientes**.
3. Si la opción admite clientes, búsquelos por nombre o identificación,
   agréguelos y ajuste su valor. Para que un cliente vuelva al valor de la
   empresa, quítelo con la ✕.
4. Pulse **Guardar Configuración**. El cambio aplica a los documentos que se
   impriman o descarguen desde ese momento, también a los ya emitidos.

## Permisos

Se necesita permiso de **modificar** en Configuración de Empresa. Cada cambio
queda en el historial del sistema (quién lo hizo, cuándo, valor anterior y
nuevo).

## Errores frecuentes

- **«Falta ejecutar … config_modulos.sql»**: la base de datos todavía no tiene la
  tabla de personalización. Mientras tanto los módulos usan los valores por
  defecto. Avise al administrador del sistema.
- **Marqué Caducidad y no sale en el PDF**: la columna solo aparece si alguna
  línea de la consignación tiene fecha de caducidad. Lo mismo con Lote.
- **Cambié las columnas por defecto y un cliente no cambió**: ese cliente está en
  la lista de clientes con columnas propias. Quítelo o ajuste sus columnas.

## Historial de cambios

- **1.1** — Nueva pestaña **Roles de Pago**: cómo se arma el asiento del rol mensual (automático, uno general o uno por empleado).
- **1.0** — Versión inicial: pestaña Consignaciones (columnas del comprobante por cliente).
