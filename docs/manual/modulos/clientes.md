---
titulo: Clientes
resumen: Registro de los clientes de la empresa: datos de identificación, búsqueda, permisos y eliminación.
categoria: Ventas
ruta_modulo: modulos/clientes
tipo: modulo
visibilidad: todos
etiquetas: clientes, cliente, cartera, ruc, cedula, consumidor final, deudores
version: 1.0
orden: 10
estado: activo
---

El módulo de **Clientes** mantiene el registro de las personas y empresas a las
que se les factura. Es la base de las facturas de venta, las proformas, los
cobros y las cuentas por cobrar.

## Qué es y para qué sirve

Cada cliente pertenece a **una empresa**: los clientes registrados en una empresa
no se ven desde otra. Si trabaja con varias empresas, debe registrarlos en cada una.

Un cliente guardado aquí queda disponible en todos los documentos de venta sin
volver a escribir sus datos.

## Cómo se usa

1. Abra el módulo desde el menú *Ventas → Clientes*.
2. Pulse **Nuevo** para registrar un cliente.
3. Complete la identificación (RUC, cédula o pasaporte), el nombre y los datos de contacto.
4. Guarde. El cliente queda disponible de inmediato en facturas y proformas.

Para modificar un cliente existente, haga clic sobre su fila en el listado.

## Buscar en el listado

El buscador acepta texto libre y también filtros con la forma `clave:valor`:

- `garcia` busca ese texto en las columnas principales.
- `identificacion:1712345678` filtra por un campo concreto.
- `clave:"valor con espacios"` para valores que llevan espacios.
- `-clave:valor` excluye los que coincidan.

El listado permite además ordenar por cualquier columna, mostrar u ocultar
columnas, ajustar su ancho y exportar a PDF y Excel. Esas preferencias se
guardan por usuario.

## Permisos

Lo que puede hacer cada persona depende de los permisos asignados al submódulo:

- **Ver**: consultar el listado.
- **Crear**, **Modificar**, **Eliminar**: las acciones correspondientes.
- **Acceso total**: ver los clientes de toda la empresa. Sin este permiso, cada
  usuario ve únicamente *los clientes que él mismo creó*.

Si no ve clientes que sabe que existen, lo más probable es que le falte el
permiso de acceso total.

## Eliminar un cliente

La eliminación es **lógica**: el cliente deja de aparecer en los listados pero no
se borra de la base de datos, y los documentos que ya lo referencian siguen
intactos. Toda eliminación queda registrada en la auditoría del sistema con el
usuario y la fecha.

## Errores frecuentes

- **No aparece en la factura**: verifique que el cliente esté en la misma empresa
  en la que está facturando.
- **No puedo editarlo**: le falta el permiso de modificar, o el cliente lo creó
  otro usuario y usted no tiene acceso total.

## Historial de cambios

- **1.0** — Versión inicial.
