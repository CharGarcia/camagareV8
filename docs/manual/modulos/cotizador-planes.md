---
titulo: Cotizador de Planes
resumen: El vendedor arma el precio del sistema según el RUC, los establecimientos, los módulos y los usuarios del cliente, ve el valor anual y mensual al instante y genera la proforma enlazada al CRM.
categoria: Ventas
ruta_modulo: modulos/cotizador-planes
tipo: modulo
visibilidad: todos
etiquetas: cotizador, cotizar, cotizacion del sistema, precio del sistema, cuanto cuesta, planes, plan anual, plan mensual, suscripcion, membresia, precio por modulo, lista de precios, catalogo de precios, vendedor, vendedores, venta del sistema, propuesta, oferta, proforma del plan, crm, oportunidad, seguimiento, establecimientos, sucursales, locales, usuarios adicionales, usuario adicional, descuento por establecimiento, descuento comercial, recargo mensual, implementacion, migracion, capacitacion, pago unico, consultar ruc, sri, modulos que se necesitan, dependencias
version: 1.0
orden: 0
estado: activo
---

El **Cotizador de Planes** es la herramienta del vendedor para calcular cuánto
cuesta el sistema para una empresa concreta. En lugar de una lista de precios
fija, el precio sale de lo que el cliente realmente necesita: cuántos
establecimientos tiene, qué módulos usará cada uno y cuántos usuarios. Al
terminar, genera una **Proforma** enlazada a una oportunidad del **CRM** para
hacer el seguimiento.

## Qué es y para qué sirve

- Escribe el RUC del cliente y el sistema trae del SRI su razón social, su tipo
  de contribuyente y la lista de **establecimientos abiertos**.
- Cada establecimiento elige sus módulos. Al agregar un módulo **entran solos**
  los que necesita para funcionar: por ejemplo, al agregar *Facturas de Venta*
  se agregan *Clientes*, *Productos* y la configuración de la empresa.
- Mientras arma la cotización, ve en todo momento el precio **anual**, el
  **mensual**, el **pago único** (implementación y otros servicios) y los
  usuarios incluidos.
- Con un clic genera la **proforma**: una sola línea resumen (por ejemplo,
  *Plan anual CaMaGaRe*) y todo el detalle del plan en el **anexo de
  condiciones**.

## Requisitos previos

Un administrador (nivel 2 o 3) deja listo el cotizador con el botón
**Configuración**:

1. **Catálogo de precios**: los módulos del menú que se venden, con su precio
   anual, y los servicios (implementación, migración, capacitación…).
2. **Parámetros**: recargo del plan mensual, descuento por establecimiento
   adicional, usuarios incluidos, precio del usuario adicional y los
   **productos** de la línea resumen (plan anual y plan mensual). Esos
   productos deben existir en *Productos* con su IVA.

Para generar la proforma hace falta además una **serie con secuencial de
Proformas** y permiso de **crear en Proformas**.

## Cómo se usa

1. Pulse **Nueva cotización**.
2. Pestaña **Cliente**: escriba el RUC o la cédula y pulse **Consultar SRI**. Si
   el RUC tiene varios establecimientos abiertos, el sistema ofrece cargarlos.
   Complete el **correo** (obligatorio para la proforma), el teléfono y la
   persona de contacto.
3. Pestaña **Establecimientos y módulos**: en cada establecimiento elija los
   módulos y servicios con la lista **Agregar módulo o servicio…**. Los módulos
   que entran solos se ven con el ícono de enlace y la nota *requerido por…*; se
   quitan solos cuando quita el módulo que los necesita. Con **Agregar
   establecimiento** suma otro local.
4. Pestaña **Resumen**: indique los **usuarios** que necesita el RUC, la
   **modalidad** que irá en la proforma (anual o mensual) y, si la
   configuración lo permite, el **descuento comercial**. La tabla muestra el
   valor de cada establecimiento, cuál es el principal y los totales.
5. Pulse **Guardar**. Puede volver a abrirla y cambiarla mientras esté en
   *Borrador*.
6. Pulse **Generar proforma** y elija la serie. El sistema:
   - registra al cliente en *Clientes* si todavía no existe;
   - crea el contacto y una **oportunidad** en el CRM, a su cargo;
   - crea la **proforma** con la línea resumen y el anexo de condiciones.
7. El seguimiento sigue en el **CRM** (actividades, etapas) y en **Proformas**
   (enviar por correo para que el cliente apruebe, convertir en factura).

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| RUC o cédula | Sí | Identificación del cliente. Con **Consultar SRI** se llenan los demás datos. |
| Razón social | Sí | Nombre legal del cliente. |
| Tipo de contribuyente | No | Persona natural o sociedad. |
| Obligado a llevar contabilidad | No | Dato informativo del perfil del cliente. |
| Persona de contacto | No | Quien decide la compra; es el nombre del contacto en el CRM. |
| Correo | Para la proforma | Con él se registra al cliente y se le envía la proforma. |
| Teléfono | No | Para el contacto del CRM. |
| Vendedor | No | Vendedor del sistema; pasa a la oportunidad y a la proforma. |
| Observaciones | No | Se agregan a las observaciones de la proforma. |
| Establecimiento (código y nombre) | Sí | Cada local del RUC que contrata el sistema (001, 002…). |
| Usuarios del RUC | Sí | Cuántas personas usarán el sistema en total. |
| Modalidad | Sí | Anual o mensual: lo que irá en la proforma. La pantalla muestra ambas. |
| Descuento comercial | No | Solo si la configuración lo permite y hasta el máximo fijado. |

## Cómo se calcula el precio

| Regla | Valor inicial (configurable) |
|---|---|
| Unidad de cobro | Cada establecimiento contrata sus módulos |
| Plan mensual | Precio anual ÷ 12 + 20 % |
| Establecimientos adicionales (solo plan anual) | 20 % de descuento en los módulos **base** |
| Módulos adicionales (marcados así en el catálogo) | Precio completo en cada establecimiento |
| Establecimiento principal | El de módulos base más caros; paga completo |
| Usuarios incluidos | 3 por establecimiento, sumados y compartidos en el RUC |
| Usuario adicional | $20 al año ($2 al mes) |
| Servicios de pago único | Se cobran una vez; no llevan recargo ni descuentos |
| Descuento comercial | % sobre lo recurrente, si está permitido |

Todos los precios son **sin IVA**; el IVA lo pone la proforma según el producto
de cada línea.

*Ejemplo:* un RUC con dos locales que llevan módulos base de $1.000 cada uno
paga en plan anual $1.000 + $800 = **$1.800**, o en plan mensual $100 + $100 =
**$200 al mes** ($2.400 en el año).

## Permisos

- **Ver**: entrar al módulo y ver cotizaciones.
- **Crear**: nuevas cotizaciones.
- **Modificar**: cambiar una cotización en borrador y **generar su proforma**
  (esto último exige además **crear en Proformas**).
- **Eliminar**: cotizaciones en borrador (eliminación lógica).
- **Acceso total**: ve las cotizaciones de toda la empresa. Sin acceso total,
  cada vendedor ve solo las suyas.
- La **Configuración** (parámetros y catálogo de precios) solo la ven y la
  cambian los niveles 2 y 3.

## Reglas de negocio

- Un establecimiento debe tener al menos un módulo o servicio.
- Al volver a guardar un **borrador**, sus precios se actualizan con el
  catálogo vigente. Una cotización que ya generó su proforma **conserva** los
  precios y parámetros con que se hizo, aunque después cambie el catálogo.
- Con proforma generada, la cotización ya no se modifica ni se elimina; si ya
  no aplica, se anula la proforma desde *Proformas*.
- Un doble clic o un reintento al guardar no crea dos cotizaciones, y pulsar
  dos veces **Generar proforma** no crea dos proformas.
- Un ítem del catálogo que se inactiva deja de ofrecerse y se quita de los
  borradores al volver a calcularlos (el sistema avisa).
- Los ítems **por defecto** (por ejemplo *Implementación* a $0) entran solos
  en cada establecimiento nuevo; el vendedor puede quitarlos.

## Integraciones con otros módulos

- **SRI**: datos del RUC y establecimientos abiertos (consulta en línea).
- **Clientes**: si el RUC no es cliente, se registra al generar la proforma.
- **CRM**: contacto y oportunidad con el valor de la cotización, a cargo del
  vendedor. Si el CRM no está instalado, la proforma se crea igual y se avisa.
- **Proformas**: una línea resumen con el producto del plan, las líneas de
  pago único y el detalle completo en el **anexo de condiciones**, que viaja
  con el correo de la proforma.
- Toda acción queda en el registro de auditoría; la pestaña **Información**
  (niveles 2 y 3) muestra quién creó y modificó la cotización.

## Errores frecuentes

- **«Catálogo de precios vacío»**: un administrador debe cargar los módulos y
  sus precios con el botón **Configuración**.
- **«Falta configurar el producto del plan anual (o mensual)»**: elija en
  Configuración → Parámetros el producto que va en la proforma.
- **«Escriba el correo del cliente»**: el correo es obligatorio para registrar
  al cliente y enviarle la proforma.
- **«No hay una serie activa con secuencial de Proformas»**: configure el
  secuencial de Proformas en *Empresa → Puntos de emisión y secuenciales*.
- **«El descuento máximo permitido es …»**: el descuento comercial supera el
  tope fijado en la configuración, o la configuración no permite descuentos.
- **El SRI no responde**: escriba los datos y los establecimientos a mano; la
  cotización funciona igual.

## Historial de cambios

- **1.0** — Versión inicial: catálogo de precios por módulo con dependencias,
  cálculo anual y mensual en vivo, consulta del RUC con sus establecimientos y
  generación de la proforma enlazada al CRM.
