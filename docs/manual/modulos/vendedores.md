---
titulo: Vendedores
resumen: Personas a las que se asignan las ventas, para medir su gestión comercial.
categoria: Ventas
ruta_modulo: modulos/vendedores
tipo: modulo
visibilidad: todos
etiquetas: vendedores, vendedor, comercial, agente, asesor, comision, ventas por vendedor, importar vendedores, carga masiva, excel
version: 1.1
orden: 50
estado: activo
---

Los **vendedores** son las personas a quienes se atribuye cada venta. Se eligen
en la factura y permiten después analizar las ventas por vendedor.

Un vendedor no es lo mismo que un usuario del sistema: puede haber vendedores que
no entran al sistema, y usuarios que no venden.

## Cómo se registra

1. Pulse **Nuevo**.
2. Escriba el **nombre** y la **identificación** (ambos obligatorios).
3. Añada el correo si quiere (se valida el formato).
4. Guarde.

## Carga masiva desde Excel

Si tiene muchos vendedores, no hace falta registrarlos uno por uno: en
*Configuración → Importador desde Excel* está la entidad **Vendedores**. La
plantilla pide identificación y nombre (obligatorios) y correo, teléfono y
dirección (opcionales). Si la identificación ya existe, el vendedor se actualiza
en lugar de duplicarse.

En esa misma herramienta, la plantilla de **Clientes** trae la columna VENDEDOR
para asignar cada cliente a su vendedor por identificación o nombre. Detalle en
la guía *Importar datos desde Excel*.

## Errores frecuentes

- **"El formato del correo electrónico no es válido"**: revise la dirección.
- **No aparece al facturar**: pertenece a otra empresa o fue eliminado.

## Historial de cambios

- **1.1** — Carga masiva desde Excel: entidad *Vendedores* en el importador y
  columna VENDEDOR en la plantilla de clientes.
- **1.0** — Versión inicial.
