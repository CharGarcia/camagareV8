# CaMaGaRe — Presentación para vendedores

> **BORRADOR EN CONSTRUCCIÓN** — Documento interno del equipo comercial.
> No se entrega en PDF hasta que la dirección lo apruebe como versión final.

| Versión | Fecha | Cambios |
|---|---|---|
| 0.1 | 13-11-2026 | Estructura general + secciones «Lo que ofrecemos» y «Módulos del sistema» |
| 0.14 | 13-11-2026 | Valores aprobados. Nueva sección 2 «Conociendo al cliente»: perfiles, dolores, preguntas de descubrimiento y señales de buen o mal cliente |
| 0.13 | 13-11-2026 | Aprobadas la misión (opción A) y la visión (opción B) |
| 0.12 | 13-11-2026 | Sección 1 «Quiénes somos»: propuestas de misión, visión, valores y propuesta de valor para elegir |
| 0.11 | 13-11-2026 | Los precios se calculan con el nuevo módulo **Cotizador de Planes** (precio por módulo, dependencias, anual y mensual en vivo, proforma enlazada al CRM) |
| 0.10 | 13-11-2026 | Los usuarios se cuentan por RUC y se comparten entre establecimientos (3 cupos por establecimiento, sumados a nivel de RUC) |
| 0.9 | 13-11-2026 | Usuario adicional: $20 anual ($2 mensual). Principal = establecimiento con el plan base más caro |
| 0.8 | 13-11-2026 | Regla fija de precios: mensual = anual ÷ 12 × 1,20 (20 % de recargo), para planes y módulos adicionales |
| 0.7 | 13-11-2026 | El plan mensual tiene un precio más alto que el anual. Se agrega el argumento de venta del pago anual |
| 0.6 | 13-11-2026 | Descuento por establecimiento adicional fijado en **20 %** (solo plan anual, solo plan base) |
| 0.5 | 13-11-2026 | El descuento por establecimiento adicional aplica **solo en planes anuales**; en mensual todos pagan precio completo |
| 0.4 | 13-11-2026 | Reglas del descuento: % fijo e igual para todos los establecimientos adicionales, solo sobre el plan base. Los módulos adicionales se cobran aparte en cada establecimiento, sin descuento |
| 0.3 | 13-11-2026 | Unidad de cobro definida: **por establecimiento**, con precio del establecimiento principal y descuento para los adicionales. Cada establecimiento incluye 3 usuarios. Se corrige la descripción de multisucursal |
| 0.2 | 13-11-2026 | Se suman los 7 módulos confirmados (Factura Express QR, Automatizaciones, Importaciones, Campañas de WhatsApp, Cotización de publicidad, Servicio externo y Portal de citas). Propuesta de arquitectura de planes: módulos que se venden y módulos que van incluidos |

---

## Índice

1. Quiénes somos: nombre, misión, visión y valores — *aprobado (falta historia del nombre)*
2. Conociendo al cliente: target y problemas que resolvemos — *borrador para revisión*
3. **Lo que ofrecemos** — *borrador listo para revisión*
4. **Módulos del sistema** — *borrador listo para revisión*
5. Cómo funciona el sistema (alta, puesta en marcha, soporte) — *pendiente*
6. Planes de suscripción y qué incluye cada plan — *en construcción: propuesta de arquitectura, faltan nombres y precios*
7. Qué cubre y qué no cubre la membresía — *pendiente*
8. De qué somos responsables y de qué no — *pendiente (base: Contrato de uso y Acuerdo de datos)*
9. Preguntas frecuentes y manejo de objeciones — *pendiente*
10. Proceso de venta: demo, cotización, contrato y alta — *pendiente*

---

## 1. Quiénes somos *(misión, visión y valores aprobados; falta la historia del nombre)*

### 1.1 Nombre

**CaMaGaRe** — sistema de gestión empresarial en la nube para empresas del
Ecuador.

> **Pendiente:** ¿qué significa el nombre? ¿Hay una historia detrás que el
> vendedor pueda contar (quién lo creó, desde cuándo, por qué)? Una historia
> real de origen genera confianza en la primera reunión.

### 1.2 Misión ✅

> Simplificar la gestión de las empresas ecuatorianas con un sistema en la
> nube que integra facturación electrónica, contabilidad, inventario, nómina e
> impuestos, para que sus dueños dediquen su tiempo a hacer crecer el negocio
> y no a cumplir trámites.

### 1.3 Visión ✅

> Que toda empresa ecuatoriana, sin importar su tamaño o su giro, pueda
> administrar su negocio con la misma información y control que una gran
> empresa.

### 1.4 Valores ✅

| Valor | Qué significa | Cómo se ve en el sistema |
|---|---|---|
| **Cumplimiento** | Hacemos las cosas como exige la ley. | Comprobantes electrónicos, anexos y declaraciones del SRI, nómina según la ley laboral, acuerdo de datos personales (LOPDP). |
| **Confianza** | La información del cliente es suya y está protegida. | Permisos por usuario, registro de auditoría, nada se borra físicamente, respaldos periódicos. |
| **Simplicidad** | Lo complejo lo resuelve el sistema, no el usuario. | Asientos contables automáticos, carga desde Excel y desde el XML del SRI, guardado sin duplicados. |
| **Cercanía** | Acompañamos al cliente. | Chat de soporte en todas las pantallas, manual en línea y asistente con IA. |
| **Mejora continua** | El sistema crece con sus clientes. | Novedades del sistema y módulos por industria que se suman constantemente. |

### 1.5 Propuesta de valor (lo que el vendedor dice en 30 segundos)

> «CaMaGaRe es el sistema que lleva su negocio completo en la nube: factura
> electrónicamente al SRI, controla su inventario, sus cobros y pagos, su
> nómina y su contabilidad, que se hace sola. Lo usa desde el computador o el
> celular, sus datos están seguros y siempre tiene a quién preguntar. Usted se
> dedica a vender; nosotros nos encargamos de que todo cuadre.»

> ⚠️ **Antes de usar este discurso:** confirmar con la dirección que todo lo
> prometido se cumple. Por ejemplo, «siempre tiene a quién preguntar» depende
> de los horarios de soporte, que aún no están definidos (sección 7).

---

## 2. Conociendo al cliente *(borrador para revisión)*

> Los perfiles salen de los módulos que el sistema ya tiene. **No hay todavía
> datos de ventas** que digan cuáles compran más: la prioridad de cada perfil
> la define la dirección (ver 2.6).

### 2.1 ¿Quién decide y quién usa?

En casi toda venta hay tres personas. El vendedor debe hablar con cada una en
su idioma:

| Persona | Qué le preocupa | Qué le mostramos |
|---|---|---|
| **Dueño o gerente** (decide y paga) | Saber cuánto vende, cuánto le deben, cuánto tiene; no tener multas; no depender de una sola persona. | Tablero, cuentas por cobrar, reportes, permisos y auditoría, app en el celular. |
| **Contador** (influye mucho) | Recibir la información completa y a tiempo; declarar sin errores. | Asientos automáticos, ATS, declaraciones, estados financieros, Superintendencia de Compañías, multiempresa. |
| **Personal operativo** (usa todos los días) | Que sea rápido y fácil; no hacer doble trabajo. | Facturar en segundos, POS, carga desde el XML del SRI, app móvil. |

> **Tip:** el contador puede ser el mejor aliado o el mayor obstáculo. Si el
> contador ya usa CaMaGaRe con otros clientes, la venta es mucho más fácil.

### 2.2 Perfiles de cliente

| Perfil | Señales para reconocerlo | Dolores típicos | Módulos que lo resuelven |
|---|---|---|---|
| **Emprendedor o profesional independiente** | Persona natural, factura servicios, pocas facturas al mes. | Facturar electrónicamente sin complicarse; llevar sus retenciones. | Facturación electrónica, Proformas, Retenciones de venta, app móvil (Android). |
| **Comercio y distribuidora** | Vende productos, tiene bodega, vendedores o repartidores. | No sabe cuánto stock tiene; cartera desordenada; pedidos que se pierden. | Inventario, Compras desde XML, Pedidos, Consignaciones, Cuentas por cobrar, Vendedores, POS. |
| **Restaurante, cafetería o bar** | Mesas, cocina, cierre de caja diario. | Comandas en papel; descuadres de caja; pedidos que no llegan a cocina. | Mesas, Comandas, Pantalla de cocina (KDS), POS, Reporte de restaurante. |
| **Taller mecánico, car wash o servicio técnico** | Recibe vehículos o equipos, trabaja por órdenes. | No sabe en qué etapa está cada trabajo; olvida avisar mantenimientos. | Taller, Car wash, Vehículos, Servicio externo, Automatizaciones (recordatorios). |
| **Centro educativo** | Alumnos, pensiones mensuales. | Cobro de pensiones y control de pagos atrasados. | Alumnos, Suscripciones, Cuentas por cobrar. |
| **Condominio o administradora** | Alícuotas y cuotas extraordinarias. | Cobrar a cada condómino, mora y multas. | Condominios, Suscripciones, Cuentas por cobrar. |
| **Fábrica o productor** | Transforma materia prima en producto. | No conoce el costo real de lo que produce. | Producción (fórmulas, órdenes, costos), Inventario. |
| **Negocio con citas** | Consultorios, estéticas, profesionales con agenda. | Agenda desordenada; citas sin cobrar. | Citas, Portal de citas, Pagos de citas. |
| **Empresa con cobros recurrentes** | Vende planes, mantenimientos, arriendos, membresías. | Facturar cada mes a mano; olvidar cobros. | Suscripciones, Automatizaciones, cobro con tarjeta. |
| **Importador** | Trae mercadería del exterior. | Calcular el costo real con los gastos de importación. | Importaciones, Compras, Inventario. |
| **Empresa obligada a llevar contabilidad** | Sociedad o persona natural obligada; tiene contador. | Contabilidad atrasada; declaraciones con errores; presentar a la Superintendencia. | Contabilidad, Impuestos SRI, Nómina, Superintendencia de Compañías. |
| **Estudio contable** | Lleva la contabilidad de muchas empresas. | Recibir la información tarde y en desorden; digitar todo. | Multiempresa, Descargas del SRI, Compras desde XML, ATS, declaraciones. |
| **Cadena o grupo empresarial** | Un RUC con varios locales, o varias empresas del mismo dueño. | No ver el consolidado; mover mercadería entre locales. | Multiempresa, Transferencias entre establecimientos, Balances consolidados. |

### 2.3 Preguntas para descubrir al cliente

El vendedor **pregunta antes de mostrar**. Estas preguntas ayudan a armar la
cotización en el Cotizador de Planes:

1. ¿A qué se dedica su negocio y desde cuándo?
2. ¿Cuántos locales o establecimientos tiene? ¿Piensa abrir más?
3. ¿Cuántas personas trabajarán en el sistema?
4. ¿Cómo factura hoy? ¿Qué es lo que más le cuesta de facturar?
5. ¿Maneja inventario? ¿Sabe hoy, sin contar, cuánto tiene en bodega?
6. ¿Vende a crédito? ¿Cómo controla lo que le deben?
7. ¿Tiene contador? ¿Cómo le pasa la información cada mes?
8. ¿Está obligado a llevar contabilidad? ¿Tiene empleados en relación de dependencia?
9. ¿Qué sistema o herramienta usa hoy (Excel, otro sistema, el portal del SRI)?
10. Si pudiera resolver un solo problema este mes, ¿cuál sería?

> **Tip:** la pregunta 10 es la más importante. La demo debe empezar por ese
> problema, no por la lista de módulos.

### 2.4 Señales de un buen cliente

- Tiene un problema concreto que el sistema resuelve (pregunta 10).
- Quien decide está en la conversación o es fácil llegar a él.
- Tiene RUC activo en el SRI y emite o debe emitir comprobantes electrónicos.
- Tiene un equipo de trabajo (2 o más usuarios): podrá usar también la app en iPhone.

### 2.5 Señales de alerta (cuidado con prometer)

- Opera **fuera de Ecuador** o no emite comprobantes ante el SRI: el sistema
  está hecho para la normativa ecuatoriana.
- Pide funciones que el sistema **no tiene**: no se promete «lo hacemos
  después» sin confirmarlo con la dirección.
- Quiere usar **solo iPhone con un único usuario**: en iPhone/iPad la app
  exige un equipo de al menos 2 usuarios; con uno solo usa la web o Android.
- Espera que el sistema **reemplace al contador** o que nos hagamos cargo de
  sus declaraciones: el sistema prepara la información, pero las declaraciones
  y su contenido son responsabilidad del cliente (Contrato de uso, cláusula
  OCTAVA).

### 2.6 Pendiente de definir por la dirección

- ¿Cuáles son los **3 perfiles prioritarios** a los que deben apuntar los
  vendedores primero?
- ¿Hay **ciudades o provincias** de enfoque?
- ¿Hay un **tamaño de empresa** ideal (número de facturas al mes, de empleados
  o de establecimientos)?
- ¿Se trabaja con **contadores como aliados o referidores**? ¿Tienen algún
  beneficio por traer clientes?

---

## 3. Lo que ofrecemos

### En una frase

**CaMaGaRe es un sistema de gestión empresarial en la nube, hecho para la
normativa ecuatoriana: factura electrónicamente ante el SRI, lleva la
contabilidad, el inventario, la cartera, la nómina y los impuestos de la
empresa, y se usa desde el computador o desde el celular.**

### Por qué CaMaGaRe (argumentos de venta)

1. **Todo en un solo sistema.** Ventas, compras, inventario, cobros, pagos,
   bancos, contabilidad, nómina e impuestos trabajan con los mismos datos. Lo
   que se registra en una factura llega solo al inventario, a la cartera y a la
   contabilidad. Se evita digitar lo mismo dos veces y pasar información de un
   programa a otro.

2. **Hecho para Ecuador y el SRI.**
   - Comprobantes electrónicos: facturas, notas de crédito y de débito,
     retenciones, liquidaciones de compra, guías de remisión y facturas de
     reembolso.
   - Descarga de los comprobantes que los proveedores emiten a nombre de la
     empresa y registro de la compra desde el XML.
   - Envío de comprobantes al SRI en lote, en segundo plano.
   - Anexos y declaraciones: ATS, Declaración de IVA, Declaración de
     retenciones, Impuesto a la Renta (formulario 101 o 102), RDEP y Anexo de
     Dividendos.
   - Archivos para la Superintendencia de Compañías (ESF, ERI, ECP y EFE), con
     revisión automática.
   - Nómina según la ley laboral: roles de pago, décimo tercero, décimo
     cuarto, utilidades (15%) y vacaciones.
   - IVA del 8% en feriados programable: los productos vuelven solos a su
     tarifa normal al terminar.

3. **La contabilidad se hace sola.** Cada documento genera su asiento contable
   automáticamente. El sistema entrega estados financieros, balance de
   comprobación, mayores, índices financieros y cierre del ejercicio. La
   Auditoría contable revisa que cada documento tenga su asiento correcto.

4. **Multiempresa y multisucursal.** Un mismo usuario puede trabajar con
   varias empresas desde un solo acceso. Un mismo RUC puede tener varios
   establecimientos (locales), y cada uno opera de forma independiente, con
   sus propios puntos de emisión, módulos y plan de cuentas. Entre
   establecimientos del mismo RUC se puede transferir mercadería y ver los
   balances consolidados. Ideal para **estudios contables**, **cadenas de
   locales** y **grupos empresariales**.

5. **Control y seguridad.**
   - Permisos por usuario y por módulo: ver, crear, modificar, eliminar y
     acceso total. Sin acceso total, cada usuario ve solo lo que él registró.
   - Registro de auditoría: quién creó, modificó o anuló cada documento, y
     cuándo.
   - Nada se borra físicamente: lo eliminado queda registrado.
   - Aprobaciones previas configurables para los procesos sensibles.
   - Periodos contables que se cierran y bloquean el registro de documentos en
     esas fechas.
   - Un doble clic en Guardar crea un solo documento, nunca dos.

6. **En la nube y en el celular.**
   - Se usa desde el navegador, sin instalar nada.
   - App móvil para **Android y iPhone**, con los mismos datos y permisos que
     la web: clientes, proveedores, productos, compras, facturas, proformas,
     pedidos, órdenes de servicio y entregas con ubicación GPS.
   - ⚠️ En **iPhone/iPad** la app solo está disponible para empresas con
     equipo de trabajo (**al menos 2 usuarios activos**). Una empresa con un
     solo usuario usa la web o Android. Ver la regla de Apple en la sección 9.

7. **Se adapta a cada giro de negocio.** Además de lo general, hay módulos
   especializados para restaurantes, talleres mecánicos, car wash, centros
   educativos, condominios, producción o manufactura, negocios con citas y
   empresas que venden por consignación (ver sección 4).

8. **Ventas y marketing conectados.** CRM con embudo de ventas, mensajes de
   Instagram en una bandeja compartida, un asistente con IA que responde los
   mensajes directos, publicaciones programadas en Instagram y LinkedIn y chat
   de WhatsApp desde el sistema.

9. **Fácil de empezar y de usar.**
   - Carga por Excel de clientes, proveedores, productos, facturas,
     suscripciones, inventario y otros catálogos.
   - Saldos iniciales para arrancar con la cartera, los bancos y el inventario
     del día.
   - Manual en línea dentro del sistema, ayuda de cada pantalla, asistente de
     IA que responde con el manual y chat de soporte en todas las pantallas.
   - Todos los listados tienen buscador, filtros y exportación a PDF y Excel.
     Cada usuario elige qué columnas ver.

10. **Los datos son del cliente.** Así lo dicen el Contrato de uso y el
    Acuerdo de tratamiento de datos personales (LOPDP), que el cliente acepta
    electrónicamente con fecha, hora e IP. Al terminar la relación puede pedir
    la exportación de su información.

> **Tip para el vendedor:** no recites la lista. Pregunta primero qué le duele
> al cliente (¿pasa la información a mano al contador? ¿no sabe cuánto
> inventario tiene? ¿le multan por declarar tarde?) y muéstrale solo los
> argumentos que resuelven ese dolor.

---

## 4. Módulos del sistema

El sistema tiene **más de 130 módulos**, agrupados por área. Cada uno tiene su
artículo en el manual en línea (`/documentacion`). La columna «Para qué le
sirve al cliente» está escrita en lenguaje de venta.

### 4.1 Ventas y facturación

| Módulo | Para qué le sirve al cliente |
|---|---|
| Clientes | Su base de clientes, con búsqueda rápida y carga por Excel. |
| Facturas de venta | Factura electrónica al SRI con PDF y envío por correo. |
| Punto de venta (POS) | Venta rápida en mostrador, con apertura y cierre de caja por turno. |
| Proformas | Cotiza, el cliente aprueba por correo y la proforma se convierte en factura. |
| Pedidos | Registra lo que el cliente encarga, con fecha, hora y responsable de la entrega. |
| Notas de crédito / débito | Anula o rebaja una factura, o cobra intereses y cargos adicionales. |
| Recibos de venta | Documento interno de venta que no va al SRI. |
| Guías de remisión | Respaldan legalmente el traslado de mercadería. |
| Factura de reembolso | Factura los gastos pagados a nombre del cliente. |
| Retenciones de venta | Registra las retenciones que le hacen sus clientes y las descuenta del cobro. |
| Suscripciones | Cobros recurrentes (mensual, anual…) con facturación automática. |
| Carga de facturas / suscripciones | Crea muchas facturas o suscripciones de una vez desde Excel. |
| Envío en lote al SRI | Envía muchos comprobantes al SRI sin esperar frente a la pantalla. |
| Vendedores | Asigna las ventas a cada asesor y mide su gestión. |
| Firmas electrónicas | Permite vender firmas electrónicas a sus propios clientes (Uanataca). |
| Factura Express por QR | El cliente escanea un código QR, llena sus datos en un formulario público y recibe su factura. La empresa decide si la factura sale automáticamente o si primero revisa la solicitud. |

**Consignaciones** (mercadería entregada que se cobra cuando se vende):
Consignaciones de venta, Entregas (con la app del repartidor), Facturación de
consignaciones, Retornos, Cambios de productos, Responsables de traslado y
Transportistas.

### 4.2 Compras

| Módulo | Para qué le sirve al cliente |
|---|---|
| Compras | Registra la factura del proveedor desde su XML y la mercadería entra sola al inventario. |
| Descargas del SRI | Trae los comprobantes que otros emitieron a nombre de la empresa. |
| Retenciones de compra | Emite y envía al SRI las retenciones a proveedores. |
| Liquidaciones de compra | Comprobante para proveedores que no pueden emitir factura. |
| Órdenes de compra | Pedido formal al proveedor antes de recibir la mercadería. |
| Proveedores | Ficha del proveedor con sus datos de pago y retenciones predeterminadas. |
| Importaciones | Registra la nacionalización de la mercadería importada y reparte (prorratea) los gastos de importación para obtener el costo real de cada producto. Cuenta para la Declaración de IVA. |

### 4.3 Inventario

| Módulo | Para qué le sirve al cliente |
|---|---|
| Productos, categorías, marcas, unidades | Catálogo ordenado, también cargable por Excel. |
| Inventario y bodegas | Stock por bodega y kardex con cada movimiento. |
| Transferencias de inventario | Mueve mercadería entre bodegas y sucursales, con lote y serie. |
| Cargas de inventario | Ajustes masivos desde archivo, con aprobación si la empresa la exige. |

### 4.4 Tesorería (cobros, pagos y bancos)

| Módulo | Para qué le sirve al cliente |
|---|---|
| Cuentas por cobrar | Qué le deben, desde cuándo, y el cobro registrado desde el mismo listado. |
| Cuentas por pagar | Qué debe la empresa y cuándo vence. |
| Ingresos / Egresos | Todo el dinero que entra y sale, con su asiento contable. |
| Traspasos | Movimientos entre caja y bancos. |
| Cargar transferencias | Arma el archivo del banco para pagar en lote. |
| Conciliación bancaria, de cobros y de tarjetas | Cruza lo registrado con el banco, Payphone, Nuvei o el datáfono. |
| Flujo de caja | De dónde vino el dinero y a dónde se fue. |

### 4.5 Contabilidad

Plan de cuentas, asientos contables (la mayoría automáticos), mayores, balance
de comprobación, estados financieros, balances consolidados, índices
financieros, centros de costo, proyectos, presupuestos (comparados contra lo
real), activos fijos con depreciación, periodos contables, cierre del
ejercicio y auditoría contable.

### 4.6 Impuestos

ATS, Declaración de IVA, Declaración de retenciones, Declaración de Impuesto
a la Renta (101/102), Anexo RDEP, Anexo de Dividendos y los archivos de la
Superintendencia de Compañías.

### 4.7 Nómina y talento humano

| Módulo | Para qué le sirve al cliente |
|---|---|
| Empleados | Ficha del personal. |
| Roles de pago y novedades | Sueldo de cada periodo con horas extra, faltas y préstamos. |
| Décimo tercero / cuarto | Cálculo, archivo para el Ministerio y pago. |
| Utilidades | Reparto del 15%, informe al Ministerio y asiento contable. |
| Vacaciones | Días ganados, gozados y saldo de cada empleado. |
| Control de asistencia | Horarios, marcaciones con ubicación y geocerca, puntos de servicio y jornadas con atrasos, faltas y horas extra calculados. |

### 4.8 Soluciones por industria

| Giro | Módulos |
|---|---|
| **Restaurantes** | Mesas, comandas, menú, pantalla de cocina (KDS), estaciones de preparación con su impresora y reporte de restaurante. |
| **Talleres mecánicos** | Recepción con checklist, departamentos, estación en tablet, tablero del taller, informe técnico y factura. |
| **Car wash / mantenimiento** | Órdenes por vehículo, historial y recordatorios de mantenimiento por kilometraje. |
| **Centros educativos** | Alumnos, campus, niveles y cursos, y facturación de pensiones. |
| **Condominios** | Condóminos, alícuotas, cuotas extraordinarias, mora, multas y reajustes. |
| **Producción / manufactura** | Fórmulas, órdenes de producción con costo real, lotes, mermas y reportes. Ejemplos en el manual: imprenta, laboratorio de cosméticos, camal y panadería. |
| **Servicios con agenda** | Agenda de citas, pagos de citas y **Portal de citas**: una página pública de reservas que el cliente puede poner en su propia web. |
| **Servicio técnico en sitio** | **Servicio externo**: órdenes de mantenimiento en las instalaciones del cliente, con repuestos de bodega y mano de obra. Se convierten en factura electrónica o recibo y se envían por correo o WhatsApp. |
| **Agencias de publicidad** | **Cotización de publicidad**: cotizaciones con versiones, aprobación y envío por correo, costos reales por proveedor y generación de la factura de venta. |

### 4.9 CRM, marketing y comunicación

CRM con embudo de ventas, Bandeja social (Instagram), asistente virtual con
IA para Instagram, Publicaciones programadas en Instagram y LinkedIn con
redacción por IA, Chat y Plantillas de WhatsApp (envío de documentos y
enlaces de pago), **Campañas de WhatsApp** (envío masivo de una plantilla
aprobada por Meta, personalizada con los datos de cada cliente) y
Videollamadas dentro del sistema.

### 4.10 Reportes

Reporte de ventas, de ventas por vendedor, de compras, de inventarios, de
cartera (estado de cuenta), de ingresos y egresos, de retenciones, consolidado
de transacciones, resumen diario, reporte del POS, de pedidos, de restaurante,
de ingresos diferidos, retenciones pendientes, trazabilidad de productos,
mejor cliente y productos más vendidos. Todos se exportan a PDF y Excel, y el
administrador puede personalizar las columnas de varios de ellos para toda la
empresa.

### 4.11 Herramientas generales

Tablero de inicio con indicadores, descargas masivas de PDF y XML, plantillas
PDF personalizables (facturas, comprobantes y cheques), chat de soporte, IA de
soporte con los documentos de la empresa y el manual, y avisos de tareas y
obligaciones por vencer.

**Automatizaciones**: tareas que el sistema hace solo, en el horario que se
programe. Por ejemplo:
- enviar al SRI los documentos en borrador;
- enviar por correo los comprobantes autorizados, reintentando los que fallaron;
- sincronizar las descargas del SRI;
- avisar por correo el IVA a pagar del mes, a tiempo para decidir antes del cierre;
- avisar por WhatsApp los chats sin leer;
- recordar a los clientes de car wash su próxima cita o cambio de aceite.

> **Nota interna:** los módulos Factura Express por QR, Automatizaciones,
> Importaciones, Campañas de WhatsApp, Cotización de publicidad, Servicio
> externo y Portal de citas todavía **no tienen artículo en el manual de
> usuario**. Las descripciones de arriba salen de sus pantallas en el sistema.
> Antes de entregar este documento conviene crear esos manuales, para que el
> vendedor pueda remitir al cliente.

---

## 6. Planes de suscripción *(en construcción)*

### 6.1 Lo que el sistema ya permite

- **Combos de submódulos** (`/config/combos-submodulos`, solo superadmin):
  catálogo global para armar «planes» de submódulos (por ejemplo, *Solo
  Facturación Electrónica*), cada uno con nombre, descripción y **precio**.
  Se aplica **a un usuario** con un clic desde `/config/permisos-modulos`.
  Aplicar un combo **suma** permisos a los que el usuario ya tiene; no los
  reemplaza.
- **Suscripciones** de la empresa controladora: así se factura y se cobra el
  plan a cada cliente, con vigencia, avisos de vencimiento, cobro con tarjeta
  y reventa.
- **Regalía** («Plan sin costo») para empresas que no pagan, como cortesías o
  demos.

### 6.1.1 Unidad de cobro: por establecimiento ✅ (decidido)

**El plan se cobra por establecimiento.** Un RUC puede tener varios
establecimientos, y cada uno puede ser un negocio o local separado (por
ejemplo, el 001 es una ferretería y el 002 un restaurante). Por eso **cada
establecimiento contrata su propio plan**:

- **Establecimiento principal**: precio completo del plan.
- **Establecimientos adicionales del mismo RUC**: el plan base con un
  **descuento fijo del 20 %**, el mismo para todos los adicionales (2.º, 3.º,
  4.º…). **El descuento solo aplica en planes anuales.** En plan mensual,
  todos los establecimientos pagan el precio completo.
- **Módulos adicionales** (Restaurante, Taller, Producción, CRM, WhatsApp,
  etc.): se cobran **aparte en cada establecimiento** que los use, a
  **precio completo**. El descuento del establecimiento adicional **no** se
  aplica a ellos.
- Cada establecimiento puede tener un **plan distinto** según su giro.
- **Cada establecimiento nace con 3 usuarios incluidos.** Con 2 o más
  usuarios activos, la empresa cumple el requisito de Apple para usar la app
  en iPhone/iPad (sección 3). Ojo: ese requisito cuenta los usuarios
  **activos y asignados**, no los cupos del plan.
- **Los usuarios se cuentan por RUC y se comparten entre establecimientos.**
  Un usuario que trabaja en varios establecimientos del mismo RUC ocupa
  **un solo cupo**. Los cupos incluidos se suman a nivel de RUC: 3 por cada
  establecimiento contratado. *Ejemplo:* un RUC con 2 establecimientos tiene
  6 usuarios incluidos, y cada uno puede trabajar en uno o en los dos locales.
- **Usuario adicional** (sobre los cupos incluidos del RUC): **$20 al año** por usuario,
  o **$2 al mes** con la regla mensual (20 ÷ 12 × 1,20 = 2,00).
- **Establecimiento principal**: si los establecimientos tienen planes
  distintos, el principal es **el de plan base más caro**. Ese paga completo
  y los demás reciben el 20 % de descuento en plan anual. Así nunca se
  descuenta el plan de mayor valor.

**Por qué funciona con el sistema tal como está hoy:**

- En CaMaGaRe cada establecimiento de un RUC se registra como una **empresa
  independiente**, con su propio plan de cuentas, configuración, puntos de
  emisión y usuarios asignados.
- Cada empresa (establecimiento) se vincula a **su propia suscripción**
  (campo *suscripción* en Empresas del sistema → Cobro y vigencia).
- Los permisos y los combos se asignan por usuario **en cada empresa**, así
  que cada establecimiento recibe solo los módulos de su plan.

**Mensual vs. anual:** el plan **mensual tiene un precio más alto** que el
anual. **Regla fija:** se carga solo el **precio anual** de cada plan y de
cada módulo adicional, y el mensual se calcula así:

> **Mensual = precio anual ÷ 12 × 1,20** (20 % de recargo)

Ejemplo ilustrativo (no es un precio real): un plan de $1.000 al año cuesta
$100 al mes (1.000 ÷ 12 = 83,33 × 1,20 = 100). Doce mensualidades suman
$1.200, es decir, **pagando anual el cliente ahorra $200**.
Además, solo el plan anual tiene el 20 % de descuento en los establecimientos
adicionales. **Argumento de venta:** *«pagando anual ahorra en el plan y,
si tiene más locales, cada uno le sale con 20 % de descuento».*

**Fórmula por establecimiento:**

| Periodicidad | Establecimiento principal | Establecimiento adicional |
|---|---|---|
| **Mensual** | plan base + módulos adicionales | plan base + módulos adicionales (sin descuento) |
| **Anual** | plan base + módulos adicionales | plan base × 0,80 (20 % de descuento) + módulos adicionales |

**Ejemplo de cálculo en plan anual** (valores ilustrativos, **no son precios reales**):

| Establecimiento | Plan base | Descuento plan | Módulos adicionales | Paga |
|---|---|---|---|---|
| 001 – Ferretería (principal) | Comercial: $ A | — | — | $ A |
| 002 – Restaurante | Comercial: $ A | 20 % | Restaurante: $ R (sin descuento) | $ A × 0,80 + $ R |
| 003 – Ferretería sucursal | Comercial: $ A | 20 % | — | $ A × 0,80 |
| **Total anual del RUC** | | | | **suma** |

> **Por definir:**
> - **Control de cupos:** hoy el sistema no limita automáticamente el número
>   de usuarios por RUC. El control lo hace quien da de alta los usuarios
>   (o el futuro cotizador o un aviso en Empresas del sistema).

> **Nota técnica para el cotizador:** hoy *Combos de submódulos* guarda un
> precio por combo, pero no sabe de establecimiento principal ni de
> descuentos. El cotizador debe aplicar la fórmula de arriba
> (descuento solo en plan anual y solo al plan base de los adicionales; módulos adicionales a
> precio completo) y la suscripción de cada establecimiento se factura con su valor
> final.

### 6.2 Principio: módulos que se venden y módulos que van incluidos

El cliente no compra 130 módulos sueltos. Compra **módulos principales**, y
cada uno trae los **módulos incluidos** que necesita para funcionar. Estos no
se venden ni se cobran por separado.

**Propuesta para validar** (armada según cómo se relacionan los módulos en el
sistema):

| Módulo principal (se vende) | Incluye (no se cobra aparte) |
|---|---|
| **Facturación electrónica** | Clientes, Productos, Categorías, Marcas, Unidades de medida, Vendedores, Notas de crédito, Notas de débito, Retenciones de venta, Envío en lote al SRI, Descargas masivas, Plantillas PDF, Tablero, Reporte de ventas, Mejor cliente, Productos más vendidos |
| **Proformas y pedidos** | Reporte de pedidos, Responsables de traslado |
| **Punto de venta (POS)** | Reporte del punto de venta, Resumen diario |
| **Guías de remisión** | Transportistas |
| **Consignaciones** | Entregas, Facturación de consignaciones, Retornos, Cambios de productos, Responsables de traslado |
| **Suscripciones** (cobros recurrentes) | Carga de suscripciones por Excel, Reporte de ingresos diferidos |
| **Factura Express por QR** | Configuración y solicitudes de Factura Express |
| **Compras** | Proveedores, Descargas del SRI, Retenciones de compra, Liquidaciones de compra, Órdenes de compra, Reporte de compras, Reporte de retenciones |
| **Importaciones** | — (requiere Compras) |
| **Inventario** | Bodegas, Transferencias de inventario, Cargas de inventario, Carga de productos por Excel, Reporte de inventarios, Trazabilidad de productos |
| **Tesorería** | Cuentas por cobrar, Cuentas por pagar, Ingresos, Egresos, Traspasos, Formas de cobro y pago, Opciones de ingreso y egreso, Reporte de cartera, Reporte de ingresos y egresos, Flujo de caja, Retenciones de venta pendientes |
| **Conciliaciones** | Conciliación bancaria, Conciliación de cobros, Conciliación de tarjetas, Cargar transferencias |
| **Contabilidad** | Plan de cuentas, Configuración contable, Asientos, Mayores, Balance de comprobación, Estados financieros, Índices financieros, Periodos contables, Cierre del ejercicio, Auditoría contable, Centros de costo, Proyectos, Saldos iniciales |
| **Contabilidad avanzada** | Presupuestos, Activos fijos, Balances consolidados |
| **Impuestos SRI** | ATS, Declaración de IVA, Declaración de retenciones, Impuesto a la Renta, Anexo de Dividendos, Reporte consolidado, archivos de la Superintendencia de Compañías |
| **Nómina** | Empleados, Novedades, Roles de pago, Décimo tercero, Décimo cuarto, Utilidades, Vacaciones, Anexo RDEP |
| **Control de asistencia** | Horarios, Marcaciones, Puntos de servicio, Jornadas (requiere Empleados) |
| **Restaurante** | Mesas, Comandas, Menú, Pantalla de cocina (KDS), Configuración de restaurante, Reporte de restaurante (requiere POS) |
| **Taller mecánico** | Checklist de recepción, Departamentos, Estación, Tablero del taller, Vehículos |
| **Car wash** | Vehículos |
| **Servicio externo** | — |
| **Centros educativos** | Alumnos, Campus, Niveles y cursos (las pensiones se cobran con Suscripciones) |
| **Condominios** | Configuración de condominios |
| **Citas** | Agenda, Pagos de citas, Portal de citas |
| **Producción** | Configuración, Fórmulas, Órdenes de producción, Reportes de producción (requiere Inventario) |
| **Cotización de publicidad** | — |
| **CRM y redes sociales** | Configuración del CRM, Bandeja social, Asistente virtual de Instagram, Publicaciones |
| **WhatsApp** | Chat de WhatsApp, Plantillas, Campañas |
| **Firmas electrónicas** (reventa) | — |
| **Incluido en todos los planes** | Chat de soporte, IA de soporte, manual en línea, Automatizaciones, Videollamadas, app móvil (según los módulos contratados) |

> Puntos a confirmar en esta tabla: ¿Automatizaciones y Videollamadas van
> incluidas en todos los planes o se venden aparte? ¿Contabilidad e Impuestos
> se venden juntos? ¿Retenciones de venta van con Facturación o con
> Tesorería?

### 6.3 Planes sugeridos (sin precios todavía)

| Plan | Para quién | Módulos principales |
|---|---|---|
| **Facturación** | Emprendedores y profesionales que solo necesitan facturar | Facturación electrónica + Proformas |
| **Comercial** | Negocios que compran, venden y manejan stock | Facturación + Proformas y pedidos + POS + Compras + Inventario + Tesorería |
| **Empresarial** | Empresas obligadas a llevar contabilidad | Comercial + Contabilidad + Impuestos SRI + Conciliaciones + Nómina |
| **Estudio contable** | Contadores que llevan varias empresas | A definir (Contabilidad + Impuestos + Compras + Facturación, por empresa atendida) |

**Adicionales** (se suman a cualquier plan): Restaurante, Taller, Car wash,
Servicio externo, Centros educativos, Condominios, Citas, Producción,
Importaciones, Consignaciones, Suscripciones, Factura Express QR, Cotización
de publicidad, CRM y redes, WhatsApp, Control de asistencia, Contabilidad
avanzada y Firmas electrónicas.

---

## Notas internas para la siguiente revisión

- Validar la tabla 6.2: qué es módulo principal y qué va incluido.
- ~~Definir la unidad de cobro~~ → **por establecimiento** (v0.3).
- ~~Reglas del descuento~~ → % fijo al plan base de los adicionales; módulos
  adicionales aparte y sin descuento (v0.4). Solo en planes anuales (v0.5).
  Descuento: **20 %** (v0.6).
- ~~Mensual vs. anual~~ → mensual = anual ÷ 12 × 1,20 (v0.8). Solo se cargan
  precios anuales.
- ~~Usuario adicional~~ → $20 anual / $2 mensual (v0.9).
- ~~Establecimiento principal~~ → el de plan base más caro (v0.9).
- ~~Usuarios compartidos~~ → se cuentan por RUC (v0.10).
- Poner nombre y precio a cada plan y adicional, y su periodicidad (mensual,
  anual, con descuento por pago anual).
- Con eso, cada plan se carga como **Combo de submódulos** con su precio, y
  esta tabla se convierte en la base del **cotizador por módulos**.
- **Los precios salen del Cotizador de Planes** (módulo *Cotizador de Planes*,
  manual en `docs/manual/modulos/cotizador-planes.md`): cada módulo tiene su
  precio anual y declara qué otros módulos necesita; el vendedor arma la
  cotización por establecimiento y el sistema calcula anual y mensual con estas
  mismas reglas. La tabla 6.2 se carga como **dependencias** en el catálogo de
  precios del cotizador.
