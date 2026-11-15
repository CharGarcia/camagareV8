---
titulo: Cotizador de Planes
resumen: El vendedor arma el precio del sistema según el RUC, los establecimientos, los módulos y los usuarios del cliente, ve el valor anual y mensual al instante y genera la proforma enlazada al CRM.
categoria: Ventas
ruta_modulo: modulos/cotizador-planes
tipo: modulo
visibilidad: todos
etiquetas: cotizador, cotizar, paquetes, paquete, precio fijo del paquete, modulo incluido, incluido, sin costo, modulo gratis, dependencia sin costo, combo de modulos, emprendedor, plan emprendedor, descuento del paquete, plan semestral, semestral, cada seis meses, cotizacion del sistema, precio del sistema, cuanto cuesta, planes, plan anual, plan mensual, suscripcion, membresia, precio por modulo, lista de precios, catalogo de precios, vendedor, vendedores, venta del sistema, propuesta, oferta, proforma del plan, crm, oportunidad, seguimiento, establecimientos, sucursales, locales, usuarios adicionales, usuario adicional, descuento por establecimiento, descuento comercial, recargo mensual, implementacion, migracion, capacitacion, pago unico, consultar ruc, sri, modulos que se necesitan, dependencias
version: 1.10
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
  **semestral**, el **mensual**, el **pago único** (implementación y otros servicios) y los
  usuarios incluidos.
- Con un clic genera la **proforma**: una sola línea resumen (por ejemplo,
  *Plan anual CaMaGaRe*, *Plan semestral* o *Plan mensual*) y todo el detalle del plan en el **anexo de
  condiciones**.

## Requisitos previos

El módulo está en el menú **CRM → Cotizador de Planes** (dirección
`/modulos/cotizador-planes`). Cada usuario necesita su permiso en
*Configuración → Permisos de módulos*.

Un administrador (nivel 2 o 3) deja listo el cotizador con el botón
**Configuración**:

1. **Catálogo de precios**: la pestaña muestra **todos los módulos del menú**.
   El precio anual se escribe directo en la tabla y se guarda solo. Los que
   dicen *Sin cargar* todavía no se ofrecen al cotizar: se cargan al escribir
   su precio o con el botón **Cargar** (también a $0, para los módulos que van
   incluidos). El filtro **Mostrar** separa cargados y sin cargar. Al hacer
   **clic en la fila** de un módulo se abre una ventana con su detalle
   (encima de la Configuración), con sus demás opciones: dependencias, si es base
   o adicional y la casilla **Sin costo cuando lo requiere otro módulo** (la
   columna *Incluido al requerirse* de la tabla). Con esa casilla, el módulo
   se cobra a su precio si se vende solo, pero va **incluido a $0** cuando otro
   módulo de la cotización lo requiere. Ejemplo: Clientes vendido solo se
   cobra; si el cliente lleva Facturas, Clientes entra incluido. Los servicios (implementación, migración,
   capacitación…) se agregan con **Agregar servicio**.
2. **Paquetes** (opcional): combinaciones listas de módulos, por ejemplo
   *Emprendedor*. Con **Nuevo paquete**, o con clic en un paquete de la lista,
   se abre una ventana con su detalle (encima de la Configuración): se elige el
   nombre, se **marcan las
   casillas** de sus módulos (hay un buscador arriba de la lista) y se elige
   **cómo se cobra**:
   - **Precio fijo**: se escribe el precio anual del paquete y sus módulos van
     **incluidos a $0**. Los pagos únicos que tenga el paquete (p. ej.
     Implementación) se cobran aparte, a su precio.
   - **Suma de módulos − %**: el paquete vale la suma de sus módulos menos un
     % de descuento; si cambia el precio de un módulo, el paquete se actualiza
     solo.

   La tabla muestra la suma de los módulos, el precio (fijo o %) y el valor
   del paquete al año.
3. **Parámetros**: recargo del plan semestral y del mensual, descuento por
   establecimiento adicional, usuarios incluidos, precio del usuario adicional
   y los **productos** de la línea resumen (plan anual, semestral y mensual). Esos
   productos deben existir en *Productos* con su IVA.

Para generar la proforma hace falta además una **serie con secuencial de
Proformas** y permiso de **crear en Proformas**.

Cada usuario que cotiza (niveles 1 y 2) debe estar **vinculado a su vendedor**: en
la ficha del vendedor, campo **Usuario del sistema** (o con la misma cédula en
el usuario y en el vendedor). Sin ese vínculo no puede guardar cotizaciones.

## Cómo se usa

1. Pulse **Nueva cotización**.
2. Pestaña **Cliente**: escriba el RUC o la cédula y pulse **Consultar SRI**. Si
   el RUC tiene varios establecimientos abiertos, el sistema ofrece cargarlos.
   También se llenan solos el **tipo de contribuyente** y, si el SRI lo informa,
   si está **obligado a llevar contabilidad** (toda sociedad lo está; para una
   persona natural sin ese dato, elíjalo a mano). Complete el **correo**
   (obligatorio para la proforma), el teléfono y la persona de contacto.
3. Pestaña **Establecimientos y módulos**: en cada establecimiento puede elegir
   un **paquete** en la lista de la cabecera. Sus módulos entran marcados como
   *Paquete*, con su precio rebajado (o *Incluido* si el paquete es de precio
   fijo), y **no se pueden quitar** (para eso se
   cambia o se quita el paquete). Además, o en lugar del paquete, elija módulos
   y servicios sueltos con la lista **Agregar módulo o servicio…**. Los módulos
   que entran solos se ven con el ícono de enlace y la nota *requerido por…*; se
   quitan solos cuando quita el módulo que los necesita. Con **Agregar
   establecimiento** suma otro local.
4. Pestaña **Resumen**: indique los **usuarios** que necesita el RUC, la
   **modalidad** que irá en la proforma (anual, semestral o mensual) y, si la
   configuración lo permite, el **descuento comercial**. La tabla muestra el
   valor de cada establecimiento, cuál es el principal y los totales.
5. Pulse **Guardar**. Puede volver a abrirla y cambiarla mientras esté en
   *Borrador*.
   - Con el botón **PDF** (barra superior de la ventana) se obtiene la
     cotización en PDF, con el logo y el diseño de los demás documentos:
     cliente, establecimientos con sus módulos, resumen anual y mensual, pago
     único y condiciones. Si hay cambios sin guardar, primero se guardan. Al
     pulsarlo pregunta si imprimir, descargar o ver.
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
| Vendedor | Sí | Vendedor al que queda atada la cotización; pasa a la oportunidad del CRM y a la proforma, y es el que se ve en el listado y en el PDF. Viene **puesto por defecto** con el vendedor vinculado al usuario y, en los niveles 1 y 2, no se puede cambiar. Solo el **superadministrador (nivel 3)** puede cambiarlo. |
| Observaciones | No | Se agregan a las observaciones de la proforma. |
| Establecimiento (código y nombre) | Sí | Cada local del RUC que contrata el sistema (001, 002…). |
| Usuarios del RUC | Sí | Cuántas personas usarán el sistema en total. |
| Modalidad | Sí | Anual, semestral o mensual: lo que irá en la proforma. La pantalla muestra las tres. |
| Descuento comercial | No | Solo si la configuración lo permite y hasta el máximo fijado. |

## Cómo se calcula el precio

| Regla | Valor inicial (configurable) |
|---|---|
| Unidad de cobro | Cada establecimiento contrata sus módulos |
| Plan semestral | Precio anual ÷ 2 + 10 % |
| Plan mensual | Precio anual ÷ 12 + 20 % |
| Establecimientos adicionales (solo plan anual; no en semestral ni mensual) | 20 % de descuento en los módulos **base** |
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
**$200 al mes** ($2.400 en el año). En plan semestral: (1.000 + 1.000) ÷ 2 × 1,10 =
**$1.100 por semestre** ($2.200 en el año).

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

- **Toda cotización tiene vendedor.** Niveles 1 y 2: siempre el suyo (si edita una
  cotización que ya tenía vendedor, se conserva). Nivel 3: el que elija,
  de los vendedores de la empresa. Las cotizaciones anteriores a esta regla que
  no tenían vendedor muestran en el listado a quien las creó.
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
- Una cotización nueva **no trae módulos puestos**: cada establecimiento
  empieza vacío y el vendedor elige el paquete y/o agrega los módulos sueltos
  (y servicios como *Implementación*). Las dependencias sí entran solas.
- **Módulos incluidos**: un módulo con la casilla *Sin costo cuando lo requiere
  otro módulo* va a $0 si otro módulo del mismo establecimiento lo requiere,
  aunque el vendedor también lo haya elegido suelto. Sin la casilla, una
  dependencia se cobra a su precio de catálogo.
- **Paquete de precio fijo**: su precio cuenta como valor **base** del
  establecimiento (en plan anual lleva el descuento por establecimiento
  adicional) y sus módulos recurrentes van incluidos. Los módulos que el
  paquete requiere y no están en él siguen la regla anterior.
- **Paquetes por %**: un paquete por establecimiento. El % del paquete se aplica solo
  a sus módulos **recurrentes** (los pagos únicos del paquete van completos) y
  se aplica **antes** que el descuento por establecimiento adicional. Los
  módulos que el paquete requiere entran solos a su precio de catálogo. La
  cotización guarda el nombre, el % o el precio fijo del paquete: si después cambian, una
  cotización ya hecha no se altera. Un paquete inactivo o eliminado deja de
  ofrecerse y se quita de los borradores al recalcularlos (el sistema avisa).

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
- **«El módulo aún no está instalado»**: falta ejecutar en la base los SQL
  `database/2026-11-13_cotizador_planes.sql`,
  `database/2026-11-15_cotizador_planes_semestral.sql`,
  `database/2026-11-15_cotizador_planes_paquetes.sql` y
  `database/2026-11-15_cotizador_planes_precio_incluido.sql`, en ese orden.
- **«Escriba el precio anual del paquete (mayor que cero)»**: un paquete de
  precio fijo necesita su precio.
- **«Falta configurar el producto del plan anual (semestral o mensual)»**: elija en
  Configuración → Parámetros el producto que va en la proforma.
- **«Su usuario no está vinculado a un vendedor»**: un administrador debe abrir
  la ficha del vendedor y elegir su usuario en **Usuario del sistema**.
- **«Elija el vendedor de la cotización»**: el nivel 3 debe elegirlo (si su
  usuario no está vinculado a un vendedor, no viene puesto).
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
- **1.1** — El catálogo de precios lista todos los módulos del menú (los que faltan, «Sin cargar» a $0) y el precio se escribe directo en la tabla. El botón Configuración pasa junto a PDF y Excel, y el de crear se llama «Nueva».
- **1.2** — PDF de la cotización con logo y diseño de los documentos del sistema. Al consultar el SRI se llena también «Obligado a llevar contabilidad».
- **1.3** — Plan **semestral**: anual ÷ 2 + 10 % (configurable), sin descuento por establecimiento adicional (solo el anual lo tiene). Se ve en pantalla, listado, PDF, proforma y configuración (recargo y producto propio).
- **1.4** — **Paquetes** (p. ej. Emprendedor): módulos agrupados con un % de descuento propio, configurables en Configuración → Paquetes. Al cotizar se elige un paquete por establecimiento; sus módulos quedan fijos y se pueden sumar módulos sueltos. Se ven en pantalla, PDF y anexo de la proforma.
- **1.5** — Una cotización nueva ya no trae módulos asignados: cada establecimiento empieza vacío y el vendedor agrega el paquete y/o los módulos. Se quita la opción «Por defecto» del catálogo.
- **1.6** — Módulos **incluidos**: casilla «Sin costo cuando lo requiere otro módulo» en el catálogo. Paquetes de **precio fijo** (sus módulos van incluidos) además de los de suma − %. Se ve en pantalla, PDF y anexo.
- **1.7** — El detalle de un módulo del catálogo se abre en su propia ventana al hacer clic en la fila, en lugar de desplegarse dentro de la Configuración.
- **1.8** — El detalle de un paquete también se abre en su propia ventana.
- **1.9** — Toda cotización queda atada a un **vendedor** (obligatorio): nivel 1, el vinculado a su usuario y fijo; niveles 2 y 3 lo eligen. El listado y el PDF muestran ese vendedor.
- **1.10** — El vendedor viene puesto por defecto y solo el superadministrador (nivel 3) lo cambia; los niveles 1 y 2 cotizan a nombre de su vendedor.
