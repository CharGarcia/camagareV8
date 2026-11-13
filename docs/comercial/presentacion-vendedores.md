# CaMaGaRe — Presentación para vendedores

> **BORRADOR EN CONSTRUCCIÓN** — Documento interno del equipo comercial.
> No se entrega en PDF hasta que la dirección lo apruebe como versión final.

Versión actual: **0.16** (13-11-2026). El historial de cambios está al final.

---

## Índice

1. Quiénes somos: nombre, misión, visión y valores — *aprobado (falta historia del nombre)*
2. Conociendo al cliente: target y problemas que resolvemos — *borrador para revisión*
3. **Lo que ofrecemos** — *borrador listo para revisión*
4. **Módulos del sistema** — *borrador listo para revisión*
5. **Cómo funciona el sistema** (alta, puesta en marcha, soporte, pagos) — *borrador para revisión*
6. **Planes y precios**: cómo se calcula lo que paga el cliente — *reglas aprobadas; faltan los precios*
7. Qué cubre y qué no cubre la membresía — *pendiente*
8. De qué somos responsables y de qué no — *pendiente (base: Contrato de uso y Acuerdo de datos)*
9. Preguntas frecuentes y manejo de objeciones — *pendiente*
10. Proceso de venta: demo, cotización, contrato y alta — *pendiente*

Al final: **Pendientes por definir** (lista única) e **Historial de versiones**.

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
> de los horarios de soporte, que aún no están definidos (sección 5.3).

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
   - Aviso por correo antes de que caduque la **firma electrónica** de la
     empresa, para que nunca se quede sin poder facturar.

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
     solo usuario usa la web o Android (ver 2.5 y 5.1).

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
   - **Ambiente de pruebas**: se puede practicar emitiendo comprobantes sin
     validez tributaria antes de pasar a producción.

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

> **Nota interna:** siete módulos de esta sección aún no tienen artículo en
> el manual de usuario (ver *Pendientes*, punto P-20).

---

## 5. Cómo funciona el sistema *(borrador para revisión)*

### 5.1 Cómo se accede

- **En la nube**: se entra desde cualquier navegador, sin instalar nada. Los
  datos viven en el servidor, no en el computador del cliente.
- **App móvil** para **Android y iPhone/iPad**, con los mismos datos y permisos
  que la web. En iPhone/iPad la empresa necesita **al menos 2 usuarios
  activos** (regla de Apple); con un solo usuario usa la web o Android.
- **Un usuario, varias empresas**: quien trabaja en varias empresas (por
  ejemplo, un contador) cambia de empresa sin salir del sistema.

### 5.2 El camino del cliente, paso a paso

| # | Paso | Quién lo hace | Qué pasa en el sistema |
|---|---|---|---|
| 1 | **Cotización** | Vendedor | Arma el precio en el *Cotizador de Planes* y genera la proforma con la oportunidad en el CRM. |
| 2 | **Aceptación** | Cliente | Aprueba la proforma con el botón del correo. |
| 3 | **Alta de la empresa** | CaMaGaRe (superadministrador) | Crea la empresa en *Empresas del sistema*. La empresa queda registrada como cliente para facturarle la suscripción. |
| 4 | **Documentos legales** | Cliente | Recibe por correo el **Contrato de uso** y el **Acuerdo de datos personales (LOPDP)** y los acepta en línea; queda constancia de fecha, hora e IP. |
| 5 | **Configuración inicial** | *Por definir (CaMaGaRe o el cliente)* | Datos de la empresa y logo, establecimientos, puntos de emisión y secuenciales, y la **firma electrónica (.p12)**. |
| 6 | **Usuarios y permisos** | *Por definir* | Se crean los usuarios y se asigna a cada uno qué puede ver, crear, modificar o eliminar. |
| 7 | **Carga de datos** | *Por definir* | Clientes, proveedores y productos por **Excel**; **saldos iniciales** de cartera, bancos e inventario. |
| 8 | **Pruebas** | Cliente | Puede practicar en **ambiente de pruebas** (sin validez tributaria). |
| 9 | **Producción** | Cliente | Pasa a **producción** y empieza a emitir documentos reales. |
| 10 | **Operación y soporte** | Cliente / CaMaGaRe | Trabajo diario con chat de soporte, manual y asistente con IA. |
| 11 | **Renovación** | Cliente | Paga su suscripción según su modalidad (anual o mensual). |

> **Por definir con la dirección:** quién hace los pasos 5 a 7 (¿los hace
> CaMaGaRe como parte de la *Implementación* o el cliente con guía?), cuánto
> tiempo toma la puesta en marcha y si incluye capacitación (¿cuántas horas?,
> ¿presencial o en línea?).

### 5.3 Soporte y ayuda dentro del sistema

| Canal | Qué es |
|---|---|
| **Chat de soporte** | Burbuja de ayuda en todas las pantallas para escribirle al equipo de soporte. |
| **Manual en línea** | Artículos de cada módulo y guías paso a paso, con buscador, desde el propio sistema. |
| **Asistente con IA** | Responde preguntas usando el manual del sistema y los documentos que cargue la empresa. |
| **Novedades del sistema** | Al entrar, una tarjeta avisa de las mejoras nuevas. |

> **Por definir:** horario de atención del soporte, tiempos de respuesta y si
> hay soporte por teléfono o WhatsApp. **No prometer** «24/7» ni tiempos de
> respuesta hasta que estén definidos.

### 5.4 Pagos y vencimiento de la suscripción

Así funciona hoy en el sistema:

- La empresa ve su **suscripción y vigencia** en *Empresa → Información General*.
- Desde unos días antes del vencimiento aparece un **aviso en la barra
  superior** para todos los usuarios (5 días antes si es mensual; 15 si es
  anual).
- Después de la fecha de pago hay **3 días de gracia**. Pasados esos días, la
  suscripción figura como **vencida** y cada usuario ve una ventana de aviso al
  entrar.
- El cliente puede responder **«¿Ya pagaste? Envíanos el comprobante»** desde
  esa ventana: adjunta la transferencia y el pago queda **en revisión**. Si se
  rechaza, recibe un correo con el motivo.
- También se puede cobrar con **tarjeta** (débito automático).
- El aviso **no bloquea** el sistema. Según el Contrato de uso (cláusula
  TERCERA), la falta de pago permite **suspender el acceso, previo aviso**,
  conservando la información del cliente. La suspensión la hace CaMaGaRe
  desactivando la empresa, lo que cierra las sesiones de sus usuarios.

> **Por definir:** cuántos días de atraso se toleran antes de suspender y
> quién lo decide.

### 5.5 Seguridad de la información (en palabras del cliente)

- Cada usuario entra con su clave y solo ve lo que se le permite.
- Todo queda registrado: quién creó, modificó o anuló cada documento.
- Nada se borra físicamente; lo eliminado queda guardado.
- Respaldos periódicos y comunicación cifrada (Acuerdo de datos, cláusula QUINTA).
- Los datos son del cliente y puede pedir su exportación al terminar
  (Contrato de uso, cláusulas SÉPTIMA y NOVENA).

---

## 6. Planes y precios *(reglas aprobadas; faltan los precios)*

### 6.1 Cómo se arma el precio

No hay una lista de precios fija: **cada cliente paga por lo que usa**. El
vendedor arma la cotización en el módulo **CRM → Cotizador de Planes**:

1. Escribe el RUC y el sistema trae los establecimientos abiertos del SRI.
2. En cada establecimiento elige los **módulos**. Cada módulo tiene su precio
   anual y trae solos los módulos que necesita para funcionar (por ejemplo,
   *Facturas de Venta* trae *Clientes* y *Productos*).
3. Indica cuántos **usuarios** necesita el RUC.
4. El sistema muestra al instante el precio **anual** y el **mensual**, y genera
   la proforma enlazada al CRM.

Manual del módulo: `docs/manual/modulos/cotizador-planes.md`.

### 6.2 Reglas de precio ✅ (aprobadas)

| Regla | Valor |
|---|---|
| Unidad de cobro | **Por establecimiento.** Cada local del RUC contrata sus módulos; puede ser un negocio distinto (p. ej. 001 ferretería, 002 restaurante). |
| Tipos de módulo | **Módulos base** (entran en el descuento por establecimiento) y **módulos adicionales** (por ejemplo, de una industria: van a precio completo). Cada módulo lo marca así el catálogo. |
| Plan mensual | **Precio anual ÷ 12 × 1,20** (20 % de recargo). Solo se cargan precios anuales. |
| Establecimiento principal | El de **módulos base más caros**. Paga completo. |
| Establecimientos adicionales | **20 % de descuento** en sus módulos base, **solo en plan anual**. En mensual todos pagan completo. |
| Módulos adicionales | Precio completo en **cada** establecimiento que los use. |
| Usuarios incluidos | **3 por establecimiento**, sumados y **compartidos en el RUC** (un usuario en dos locales ocupa un solo cupo). |
| Usuario adicional | **$20 al año** ($2 al mes). |
| Servicios de una sola vez | Implementación, migración, etc. Se cobran una vez; sin recargo ni descuentos. **Implementación** entra por defecto (valor inicial $0). |
| Descuento comercial del vendedor | Solo si la configuración lo permite y hasta el tope fijado. |

Todos los precios son **sin IVA**.

### 6.3 Ejemplo para explicar al cliente *(valores ilustrativos, no reales)*

Un RUC con 3 locales, cada uno con $1.000 al año en módulos base; el segundo
local además es restaurante ($300 al año, módulo adicional); 10 usuarios.

| | Anual | Mensual |
|---|---|---|
| 001 (principal) | $1.000 | $100 |
| 002 (con restaurante) | $800 + $300 = $1.100 | $130 |
| 003 | $800 | $100 |
| Usuarios: 10 − 9 incluidos = 1 adicional | $20 | $2 |
| **Total** | **$2.920 al año** | **$332 al mes** ($3.984 en 12 meses) |

**Argumento de venta:** *«pagando anual ahorra en el plan y, si tiene más
locales, cada uno le sale con 20 % de descuento».* En el ejemplo, pagar anual
ahorra **$1.064**.

### 6.4 Qué módulos traen consigo a otros *(propuesta — se carga como dependencias en el catálogo)*

El cliente no compra 130 módulos sueltos. Al elegir un módulo principal, el
cotizador agrega solos los que necesita. Si esos módulos tienen precio $0 en el
catálogo, en la práctica van **incluidos**.

| Módulo principal | Trae consigo |
|---|---|
| **Facturación electrónica** | Clientes, Productos, Categorías, Marcas, Unidades de medida, Vendedores, Notas de crédito, Notas de débito, Retenciones de venta, Envío en lote al SRI, Descargas masivas, Plantillas PDF, Tablero, Reporte de ventas, Mejor cliente, Productos más vendidos |
| **Proformas y pedidos** | Reporte de pedidos, Responsables de traslado |
| **Punto de venta (POS)** | Reporte del punto de venta, Resumen diario |
| **Guías de remisión** | Transportistas |
| **Consignaciones** | Entregas, Facturación de consignaciones, Retornos, Cambios de productos, Responsables de traslado |
| **Suscripciones** | Carga de suscripciones por Excel, Reporte de ingresos diferidos |
| **Factura Express por QR** | Configuración y solicitudes de Factura Express |
| **Compras** | Proveedores, Descargas del SRI, Retenciones de compra, Liquidaciones de compra, Órdenes de compra, Reporte de compras, Reporte de retenciones |
| **Importaciones** | Requiere Compras |
| **Inventario** | Bodegas, Transferencias de inventario, Cargas de inventario, Carga de productos por Excel, Reporte de inventarios, Trazabilidad de productos |
| **Tesorería** | Cuentas por cobrar, Cuentas por pagar, Ingresos, Egresos, Traspasos, Formas de cobro y pago, Opciones de ingreso y egreso, Reporte de cartera, Reporte de ingresos y egresos, Flujo de caja, Retenciones de venta pendientes |
| **Conciliaciones** | Conciliación bancaria, de cobros y de tarjetas, Cargar transferencias |
| **Contabilidad** | Plan de cuentas, Configuración contable, Asientos, Mayores, Balance de comprobación, Estados financieros, Índices financieros, Periodos contables, Cierre del ejercicio, Auditoría contable, Centros de costo, Proyectos, Saldos iniciales |
| **Contabilidad avanzada** | Presupuestos, Activos fijos, Balances consolidados |
| **Impuestos SRI** | ATS, Declaración de IVA, Declaración de retenciones, Impuesto a la Renta, Anexo de Dividendos, Reporte consolidado, archivos de la Superintendencia de Compañías |
| **Nómina** | Empleados, Novedades, Roles de pago, Décimo tercero, Décimo cuarto, Utilidades, Vacaciones, Anexo RDEP |
| **Control de asistencia** | Horarios, Marcaciones, Puntos de servicio, Jornadas (requiere Empleados) |
| **Restaurante** | Mesas, Comandas, Menú, Pantalla de cocina (KDS), Configuración de restaurante, Reporte de restaurante (requiere POS) |
| **Taller mecánico** | Checklist de recepción, Departamentos, Estación, Tablero del taller, Vehículos |
| **Car wash** | Vehículos |
| **Centros educativos** | Alumnos, Campus, Niveles y cursos (las pensiones se cobran con Suscripciones) |
| **Condominios** | Configuración de condominios |
| **Citas** | Agenda, Pagos de citas, Portal de citas |
| **Producción** | Configuración, Fórmulas, Órdenes de producción, Reportes de producción (requiere Inventario) |
| **CRM y redes sociales** | Configuración del CRM, Bandeja social, Asistente virtual de Instagram, Publicaciones |
| **WhatsApp** | Chat de WhatsApp, Plantillas, Campañas |
| Sin dependencias | Servicio externo, Cotización de publicidad, Firmas electrónicas (reventa) |
| **En todos los planes** *(propuesta)* | Chat de soporte, IA de soporte, manual en línea, Automatizaciones, Videollamadas, app móvil (según los módulos contratados) |

### 6.5 Paquetes de referencia *(opcional — por decidir si se usan)*

Para que el vendedor no arme cada cotización desde cero, se podrían definir
combinaciones típicas. El precio de cada una igual lo calcula el cotizador.

| Paquete | Para quién | Módulos principales |
|---|---|---|
| **Facturación** | Emprendedores y profesionales | Facturación electrónica + Proformas |
| **Comercial** | Negocios que compran, venden y manejan stock | Facturación + Proformas y pedidos + POS + Compras + Inventario + Tesorería |
| **Empresarial** | Empresas obligadas a llevar contabilidad | Comercial + Contabilidad + Impuestos SRI + Conciliaciones + Nómina |
| **Estudio contable** | Contadores que llevan varias empresas | Por definir |

---

## Pendientes por definir (lista única)

Todo lo que falta para entregar el documento. Cuando un punto se resuelva, se
actualiza su sección y se tacha aquí.

### A. Identidad
| # | Pendiente | Sección |
|---|---|---|
| P-01 | Significado del nombre **CaMaGaRe** y su historia de origen (quién lo creó, desde cuándo, por qué). | 1.1 |
| P-02 | Confirmar que el discurso de 30 segundos se cumple (depende de P-11). | 1.5 |

### B. Cliente y mercado
| # | Pendiente | Sección |
|---|---|---|
| P-03 | Los **3 perfiles de cliente prioritarios**. | 2.6 |
| P-04 | **Ciudades o provincias** de enfoque. | 2.6 |
| P-05 | **Tamaño de empresa ideal** (facturas al mes, empleados, establecimientos). | 2.6 |
| P-06 | ¿Trabajamos con **contadores como aliados o referidores**? ¿Con qué beneficio? | 2.6 |

### C. Puesta en marcha y soporte
| # | Pendiente | Sección |
|---|---|---|
| P-07 | Quién hace la **configuración inicial, usuarios y carga de datos** (CaMaGaRe o el cliente con guía). | 5.2 |
| P-08 | **Tiempo** de la puesta en marcha. | 5.2 |
| P-09 | ¿Incluye **capacitación**? Horas y modalidad (presencial / en línea). | 5.2 |
| P-10 | ¿*Implementación* sigue en $0 o tiene precio según el tamaño del cliente? | 6.2 |
| P-11 | **Soporte**: horario, tiempos de respuesta y canales (chat, teléfono, WhatsApp). | 5.3 |
| P-12 | **Días de atraso** tolerados antes de suspender el servicio y quién lo decide. | 5.4 |

### D. Precios
| # | Pendiente | Sección |
|---|---|---|
| P-13 | **Precio anual de cada módulo** y de cada servicio, para cargarlo en el Cotizador. | 6.2 |
| P-14 | Validar la tabla de **dependencias** (qué módulo trae a cuál). | 6.4 |
| P-15 | ¿Qué módulos son **base** y cuáles **adicionales** (con o sin descuento por establecimiento)? | 6.2 |
| P-16 | ¿Automatizaciones y Videollamadas van en todos los planes o se venden aparte? ¿Contabilidad e Impuestos se venden juntos? ¿Retenciones de venta van con Facturación o con Tesorería? | 6.4 |
| P-17 | ¿Se usan **paquetes de referencia**? Si sí, sus nombres y el del estudio contable. | 6.5 |
| P-18 | ¿El **vendedor puede dar descuento comercial**? ¿Hasta qué %? | 6.2 |
| P-19 | **Control de cupos** de usuarios por RUC: hoy no es automático; ¿quién lo controla? | 6.2 |

### E. Producto y documentación
| # | Pendiente | Sección |
|---|---|---|
| P-20 | Crear el manual de usuario de 7 módulos: Factura Express por QR, Automatizaciones, Importaciones, Campañas de WhatsApp, Cotización de publicidad, Servicio externo y Portal de citas. | 4 |
| P-21 | Instalar el Cotizador de Planes (dos SQL), dar permisos y crear los productos «Plan anual» y «Plan mensual». | 6.1 |

### F. Secciones por escribir
| # | Pendiente | Sección |
|---|---|---|
| P-22 | Qué cubre y qué no cubre la membresía. | 7 |
| P-23 | De qué somos responsables y de qué no (base: Contrato de uso y Acuerdo de datos). | 8 |
| P-24 | Preguntas frecuentes y manejo de objeciones. | 9 |
| P-25 | Proceso de venta: demo, cotización, contrato y alta. | 10 |
| P-26 | Capacitación de vendedores (documento aparte): cómo vender, target, guion de demo. | — |
| P-27 | Revisión legal del Contrato de uso y del Acuerdo de datos (el propio sistema lo recomienda). | 8 |

---

## Historial de versiones

| Versión | Fecha | Cambios |
|---|---|---|
| 0.16 | 13-11-2026 | Revisión completa: sección 6 reescrita con las reglas aprobadas y un ejemplo; referencias corregidas; lista única de pendientes; historial al final |
| 0.15 | 13-11-2026 | Nueva sección 5 «Cómo funciona el sistema» (acceso, ciclo del cliente, soporte, pagos y vencimiento). Sección 3: aviso de firma por caducar y ambiente de pruebas |
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
| 0.1 | 13-11-2026 | Estructura general + secciones «Lo que ofrecemos» y «Módulos del sistema» |
