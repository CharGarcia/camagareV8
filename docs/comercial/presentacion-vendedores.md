# CaMaGaRe — Presentación para vendedores

> **BORRADOR EN CONSTRUCCIÓN** — Documento interno del equipo comercial.
> No se entrega en PDF hasta que la dirección lo apruebe como versión final.

| Versión | Fecha | Cambios |
|---|---|---|
| 0.1 | 13-11-2026 | Estructura general + secciones «Lo que ofrecemos» y «Módulos del sistema» |
| 0.2 | 13-11-2026 | Se suman los 7 módulos confirmados (Factura Express QR, Automatizaciones, Importaciones, Campañas de WhatsApp, Cotización de publicidad, Servicio externo y Portal de citas). Propuesta de arquitectura de planes: módulos que se venden y módulos que van incluidos |

---

## Índice

1. Quiénes somos: nombre, misión, visión y valores — *pendiente*
2. Conociendo al cliente: target y problemas que resolvemos — *pendiente*
3. **Lo que ofrecemos** — *borrador listo para revisión*
4. **Módulos del sistema** — *borrador listo para revisión*
5. Cómo funciona el sistema (alta, puesta en marcha, soporte) — *pendiente*
6. Planes de suscripción y qué incluye cada plan — *en construcción: propuesta de arquitectura, faltan nombres y precios*
7. Qué cubre y qué no cubre la membresía — *pendiente*
8. De qué somos responsables y de qué no — *pendiente (base: Contrato de uso y Acuerdo de datos)*
9. Preguntas frecuentes y manejo de objeciones — *pendiente*
10. Proceso de venta: demo, cotización, contrato y alta — *pendiente*

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
   varias empresas, y cada empresa con varios establecimientos y puntos de
   emisión. Hay balances consolidados de los establecimientos de un mismo RUC.
   Ideal para **estudios contables** y **grupos empresariales**.

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

> **Decisión pendiente:** hoy los combos se aplican **por usuario**. Hay que
> definir si el plan se cobra **por empresa**, **por usuario** o **por empresa
> con N usuarios incluidos y un valor por usuario adicional**. De esto depende
> cómo se arma el cotizador.

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
- Definir la unidad de cobro: empresa, usuario o empresa + usuarios.
- Poner nombre y precio a cada plan y adicional, y su periodicidad (mensual,
  anual, con descuento por pago anual).
- Con eso, cada plan se carga como **Combo de submódulos** con su precio, y
  esta tabla se convierte en la base del **cotizador por módulos**.
