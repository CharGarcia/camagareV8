---
titulo: Conciliación de Tarjetas
resumen: Cruza el estado de cuenta de Payphone, Nuvei o el datáfono contra los cobros con tarjeta ya registrados.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-tarjetas
tipo: modulo
visibilidad: todos
etiquetas: conciliar tarjetas, payphone, nuvei, datafono, tarjeta de credito, liquidacion, comision de tarjeta, deposito de tarjeta, retenciones tarjeta, cuadrar tarjetas, cobros por depositar, asiento del deposito
version: 1.4
orden: 66
estado: activo
---

Cuando un cliente paga con tarjeta, el sistema da la factura por cobrada en ese
mismo momento. Pero el dinero no llega ese día: la procesadora lo deposita días
después y descontando su comisión y las retenciones. Este módulo cierra ese
ciclo.

## Qué es y para qué sirve

Cruza el **estado de cuenta que emite la procesadora** contra los **cobros con
tarjeta que ya están registrados** en el sistema. Del cruce salen tres
respuestas:

- **Cobros confirmados**: la procesadora sí depositó ese dinero.
- **Cobros sin depositar**: siguen pendientes. O aún no los liquidan, o ese
  cobro nunca ocurrió aunque la factura figure pagada.
- **Depósitos sin documento**: entró dinero que no corresponde a ningún cobro
  registrado. Aquí solo se reporta; el documento se registra en su módulo.

Al cerrar, usted elige **a qué forma de cobro entró el dinero** (normalmente un
banco) y, si la empresa lleva contabilidad, se genera el asiento del depósito.

No se confunda con **Conciliación de Cobros**: ese módulo lee el estado de
cuenta del banco y *genera* ingresos contra facturas pendientes. Aquí los
ingresos ya existen y lo que se determina es cuáles se depositaron de verdad.

## Requisitos previos

- Tener al menos una forma de cobro de tipo **Payphone**, **Nuvei** o
  **Tarjeta** en *Formas de Cobro/Pago*. El módulo trabaja con esas y solo con
  esas: son las que cobran hoy y depositan después. Los selectores
  **Procesadora** y **Depositado en** solo muestran las formas **activas** allí;
  una conciliación ya creada con una forma que después se desactivó se sigue
  pudiendo abrir y trabajar (la forma aparece marcada como *inactiva*).
- Un **perfil de lectura** para el archivo de su procesadora, si va a cargar el
  estado de cuenta. Los perfiles son un catálogo del sistema que administra el
  **superadministrador** en *Configuración → Perfiles de lectura de tarjetas*
  (ver `config/conciliacion-tarjetas-perfiles`). Si el suyo no está, pídale que
  lo cree.
- **Solo si lleva contabilidad**: la forma de cobro de la tarjeta debe apuntar a
  una **cuenta puente** (por ejemplo "Tarjetas de crédito por liquidar"), no a
  la cuenta del banco. El saldo de esa cuenta es justamente lo que la
  procesadora aún le debe.

La contabilidad es opcional. Sin cuentas configuradas el módulo concilia igual;
solo avisa que no generará el asiento.

## Configuración de la procesadora (cuentas y valores por defecto)

Las cuentas de comisión, IVA de la comisión y retenciones, los porcentajes, los
**días de liquidación** y la **tolerancia** se configuran en la pestaña
**Configuración** del modal de la conciliación (se abre con **Nueva
conciliación** o al abrir una existente). La pestaña muestra la configuración de
la procesadora elegida en ese modal.

Lo que se guarda ahí queda **para la procesadora**, no solo para esa
conciliación: se aplica a la que tiene abierta y a todas las siguientes. Se
necesita permiso de **Modificar** para ver la pestaña, y cada usuario puede
ocultarla con el engranaje de las pestañas.

## Cómo se usa

1. La pantalla muestra el listado de **conciliaciones** registradas. Para ver los
   cobros con tarjeta que siguen pendientes de depósito, abra una conciliación:
   la lista **Cobros del sistema** muestra los de esa procesadora, con un semáforo
   de días de atraso.
2. Pulse **Nueva** (arriba a la derecha) y llene el encabezado: procesadora,
   fecha del depósito y, si quiere, el período.
3. En el mismo encabezado elija el **Perfil de lectura** (el formato del archivo)
   y el **Estado de cuenta** (el archivo), y pulse **Cargar**: la conciliación se
   crea, el archivo se lee en ese mismo paso y debajo aparecen, cada una en su
   tarjeta, las listas **Estado de cuenta de la procesadora** y **Cobros del
   sistema** con los totales. La lista de perfiles muestra solo
   los de esa procesadora (Payphone, Nuvei o Tarjeta) y del banco de la forma de
   cobro, más los genéricos. Si el archivo no se puede leer, la conciliación queda
   guardada igual: corrija el perfil o el archivo y vuelva a pulsar **Cargar**.
   Elegir otro archivo en una conciliación que ya tiene líneas las reemplaza
   (el sistema lo pregunta antes). También puede agregar líneas a mano con
   **Línea manual** (ícono **+** en el encabezado de la tarjeta *Estado de cuenta de la procesadora*). Una vez cargado, el botón **Guardar** del pie guarda los
   cambios del encabezado mientras hace el cruce.
4. Pulse **Cruzar automáticamente** (ícono de varita, junto al anterior): se abre
   **Cruces sugeridos** con cada línea del estado de cuenta, el cobro o cobros que
   le corresponderían y el **criterio** con que se encontró (autorización,
   referencia, monto y fecha, suma del depósito o *solo monto*). Nada se cruza
   todavía: desmarque lo que no corresponda y pulse **Cruzar seleccionadas**. Las
   sugerencias de *solo monto* son las menos seguras y llegan sin marcar; si la
   suma de los cobros no coincide con el bruto de la línea, el monto sale en rojo.
   Lo que quede suelto se cruza a mano — clic en la línea de la izquierda y luego
   en el cobro de la derecha.
5. Las líneas que no correspondan a ningún cobro márquelas con el triángulo de
   aviso: quedan reportadas como *sin documento*.
6. Indique **Depositado en** (el banco) y el **Neto depositado**, revise que la
   diferencia sea cero y pulse **Conciliar y cerrar**.
7. Si la conciliación generó asiento, puede revisarlo en la pestaña **Asiento
   contable** del mismo modal. Una conciliación cerrada se anula con el botón
   **Anular** de la barra de acciones superior.

## Listado: buscar, filtrar, ordenar y páginas

- **Buscar**: escriba en el buscador; busca en número, observaciones,
  procesadora, banco destino y nombre del archivo.
- **Filtrar**: el botón del embudo, a la izquierda del buscador, abre todos los
  filtros —número, estado, fecha de depósito (con atajos: hoy, este mes…),
  procesadora, depositado en, neto depositado y diferencia—. Se aplican con
  **Aplicar** y quedan como etiquetas junto al buscador; cada etiqueta se quita
  con su ×.
- **Ordenar**: clic en el encabezado de una columna; otro clic invierte el
  sentido. Con **Shift + clic** en otra columna se ordena por varias a la vez
  (hasta tres). El orden se recuerda para su usuario y es el mismo que sale en
  el PDF y el Excel.
- **Mostrar u ocultar columnas**: botón de columnas, a la izquierda de PDF.
- **Ancho de columnas**: arrastre el borde del encabezado; se recuerda para su
  usuario.
- **Páginas**: el listado muestra 50 filas por página; use las flechas de la
  derecha. El contador indica qué filas está viendo y el total.
- **PDF / Excel**: exportan lo que cumple la búsqueda y los filtros, en el mismo
  orden de la pantalla.

## Pestaña Asiento contable

Aparece solo si usted tiene acceso a **Contabilidad → Asientos Contables**, y
puede ocultarla con el engranaje de las pestañas. Muestra el asiento del
depósito que se generó al cerrar la conciliación; si no hay asiento, explica por
qué (conciliación en borrador, anulada o cerrada sin cuentas contables). Quien
además puede modificar asientos contables puede corregirlo ahí mismo.

## Campos del formulario

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Procesadora | Sí | La forma de cobro con tarjeta que se está conciliando. No se cambia después de crear la conciliación |
| Fecha depósito | Sí | El día en que el dinero entró al banco |
| Período desde / hasta | No | Acota qué cobros se ofrecen para cruzar |
| Depositado en | Sí, al cerrar | La forma de cobro (banco) donde entró el dinero |
| Neto depositado | No | Lo que realmente le acreditaron. Si lo deja vacío se asume el neto calculado |
| Bruto | Sí | Valor de la venta antes de descuentos, tal como lo cobró el cliente |
| Comisión / IVA comisión | No | Lo que cobra la procesadora por el servicio |
| Retención renta / IVA | No | Lo que la procesadora retuvo. Se digita del comprobante: el sistema no aplica porcentajes |
| Otros descuentos | No | Cualquier otro rubro descontado. Se contabiliza junto con la comisión |

## Permisos

- **Ver**: consultar las conciliaciones y los cobros pendientes de cada una.
- **Crear**: crear conciliaciones y cargar el estado de cuenta.
- **Modificar**: cruzar, descruzar, cerrar y anular.
- **Eliminar**: eliminar conciliaciones en borrador y líneas.
- **Acceso total**: sin él, el usuario solo ve los cobros que él mismo registró.
  Con él, ve los de toda la empresa.

## Reglas de negocio

- **Un cobro no se concilia dos veces.** La base de datos lo impide, incluso si
  dos personas concilian al mismo tiempo.
- **Lo que no aparece, vuelve.** Un cobro sin línea en el estado de cuenta sigue
  pendiente y se vuelve a ofrecer en la siguiente conciliación, como un cheque
  girado y no cobrado.
- **Solo se contabiliza lo cruzado.** Una línea marcada *sin documento* no entra
  al asiento: registre primero el documento que falta y vuelva a conciliar.
- **La diferencia tiene tope.** Si el neto depositado no coincide con lo
  calculado por más que la tolerancia configurada, el cierre se bloquea hasta
  que revise las comisiones y retenciones.
- **Cargar el archivo empieza de cero**: reemplaza las líneas y los cruces que ya
  tuviera esa conciliación.
- **Anular devuelve todo atrás**: revierte el asiento y los cobros vuelven a
  quedar pendientes.
- Una conciliación **cerrada** no se edita ni se elimina: primero se anula.

## Integraciones con otros módulos

- **Formas de Cobro/Pago**: de ahí sale qué formas se concilian aquí (tipo
  Payphone, Nuvei o Tarjeta) y la cuenta puente de cada una.
- **Ingresos**: la unidad que se concilia es la línea de cobro del ingreso, sin
  importar si nació de un link de pago, del POS o de un cobro digitado.
- **Payphone y Nuvei**: aportan el código de autorización de cada transacción,
  que es la llave más confiable para cruzar.
- **Contabilidad**: al cerrar genera un asiento — Banco por el neto, más
  comisión, IVA y retenciones, contra la cuenta puente por el bruto.
- **Control Bancario**: el depósito aparece solo en la cuenta bancaria, porque
  ese módulo se alimenta de los asientos.

## Errores frecuentes

- **"La forma de cobro no tiene cuenta contable asignada"**: la conciliación se
  guarda igual, pero sin asiento. Asigne una cuenta puente en *Formas de
  Cobro/Pago* si lleva contabilidad.
- **"Apunta a la misma cuenta contable que el banco destino"**: el cobro ya
  debitó esa cuenta; contabilizar el depósito la duplicaría. La tarjeta necesita
  una cuenta puente propia, distinta de la del banco.
- **"No se pudo leer ninguna línea del archivo"**: casi siempre es la fila de
  inicio o el mapeo de columnas del perfil. El superadministrador puede subir el
  archivo como muestra en *Configuración → Perfiles de lectura de tarjetas* y
  usar **Ver / Probar** para ver qué está leyendo.
- **"No hay perfiles para esta procesadora"** o **"El perfil es para otra
  procesadora"**: no hay un perfil activo del tipo (o del banco) de la forma de
  cobro. Pida al superadministrador que lo cree o que lo deje como genérico.
- **Las cifras no cuadran por centavos**: pida revisar el separador decimal del
  perfil (punto o coma) y suba la tolerancia si su procesadora redondea distinto.
- **Un cobro no aparece para cruzar**: puede estar fuera del período de la
  conciliación, ya conciliado en otra, o pertenecer a otro usuario si usted no
  tiene acceso total.

## Períodos contables cerrados

Lo que mueve inventario o contabilidad no puede tocar un período ya cerrado.
Cerrarla se rechaza si la fecha de conciliación cae en un mes cerrado: el cierre
genera el asiento con esa fecha.

Al modificar se revisan **las dos fechas** —la nueva y aquella con la que está
registrado—: mover un documento de un mes cerrado a uno abierto lo alteraría
igual. Los períodos se abren y se cierran en **Contabilidad → Períodos
Contables**; reabrir el período permite la operación de inmediato.

## Historial de cambios

- **1.4** — La pantalla pasa al diseño estándar de listados (como Proveedores):
  botón **Nueva** arriba, buscador con embudo de filtros y etiquetas, columnas,
  PDF, Excel y paginación. Se retiran la tarjeta de filtros con indicadores y
  la vista **Pendientes por depositar** con sus botones de cambio de vista: los
  cobros pendientes de cada procesadora se ven dentro del modal de la
  conciliación (lista *Cobros del sistema*).
- **1.3** — Los **perfiles de lectura** ya no se crean por empresa en la
  configuración del módulo: pasan a un catálogo global en *Configuración →
  Perfiles de lectura de tarjetas*, que administra el nivel 3. Cada perfil se
  asocia a un tipo de procesadora (y, si hace falta, a un banco) en lugar de a
  una forma de cobro de la empresa. La configuración contable (cuentas, días de
  liquidación, tolerancia) deja de tener botón propio en el listado y pasa a la
  pestaña **Configuración** del modal de la conciliación; se sigue guardando por
  procesadora, para las siguientes conciliaciones. En pantallas grandes, el modal de la conciliación ya no se
  desplaza entero: datos, totales y botones quedan fijos y solo se recorren las
  listas del estado de cuenta y de los cobros. El perfil y el archivo del estado
  de cuenta se eligen en el encabezado del modal y se leen con el botón **Cargar**
  (desaparecen el botón *Cargar estado de cuenta* y su ventana aparte): una
  conciliación nueva se crea y carga el archivo en un solo paso. La vista ocupa
  todo el ancho de la pantalla, como Reporte de Ventas. Corrección: la cuenta
  puente de la tarjeta y la del banco destino se toman igual que en el asiento
  del cobro —primero la regla de *Configuración Contable / Formas de Cobros y
  Pagos*, luego la cuenta de la forma—; antes se leía solo la segunda y el módulo
  decía «no tiene cuenta contable asignada» a formas que sí la tenían.
- **1.2** — Listado con **paginación** (antes mostraba solo las primeras 100
  conciliaciones), **orden por columnas** (también por varias), columnas que se
  pueden ocultar y ancho recordado. **Pendientes por depositar** ya no exige
  elegir una procesadora: muestra todas y agrega la columna Procesadora. Los
  indicadores ahora suman todo el filtro (antes solo las 100 primeras filas).
  Nueva pestaña **Asiento contable** en el modal, y **Anular** pasa a la barra
  de acciones superior.
- **1.1** — El módulo respeta ahora el **cierre contable**: no se puede operar
  sobre una conciliación cuyo período esté cerrado. Antes no se comprobaba.
- **1.0** — Versión inicial.
