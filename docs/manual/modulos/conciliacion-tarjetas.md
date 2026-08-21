---
titulo: Conciliación de Tarjetas
resumen: Cruza el estado de cuenta de Payphone, Nuvei o el datáfono contra los cobros con tarjeta ya registrados.
categoria: Tesorería
ruta_modulo: modulos/conciliacion-tarjetas
tipo: modulo
visibilidad: todos
etiquetas: conciliar tarjetas, payphone, nuvei, datafono, tarjeta de credito, liquidacion, comision de tarjeta, deposito de tarjeta, retenciones tarjeta, cuadrar tarjetas
version: 1.0
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
  esas: son las que cobran hoy y depositan después.
- Un **perfil de lectura** por cada archivo que reciba, si va a cargar el estado
  de cuenta. Se crea en *Configuración → Perfiles*.
- **Solo si lleva contabilidad**: la forma de cobro de la tarjeta debe apuntar a
  una **cuenta puente** (por ejemplo "Tarjetas de crédito por liquidar"), no a
  la cuenta del banco. El saldo de esa cuenta es justamente lo que la
  procesadora aún le debe.

La contabilidad es opcional. Sin cuentas configuradas el módulo concilia igual;
solo avisa que no generará el asiento.

## Cómo se usa

1. Elija la procesadora en los filtros. La pestaña **Pendientes por depositar**
   muestra los cobros que todavía no aparecen en ningún estado de cuenta, con un
   semáforo de días de atraso.
2. Pulse **Nueva conciliación**, escoja la procesadora y la fecha del depósito, y
   guarde.
3. Pulse **Cargar estado de cuenta** y suba el archivo con el perfil que
   corresponda. También puede agregar líneas a mano con **Línea manual**.
4. Pulse **Cruzar automáticamente**: el sistema empareja lo evidente. Lo que
   quede suelto se cruza a mano — clic en la línea de la izquierda y luego en el
   cobro de la derecha.
5. Las líneas que no correspondan a ningún cobro márquelas con el triángulo de
   aviso: quedan reportadas como *sin documento*.
6. Indique **Depositado en** (el banco) y el **Neto depositado**, revise que la
   diferencia sea cero y pulse **Conciliar y cerrar**.

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

- **Ver**: consultar pendientes y conciliaciones.
- **Crear**: crear conciliaciones, cargar el estado de cuenta y crear perfiles.
- **Modificar**: cruzar, descruzar, cerrar y anular.
- **Eliminar**: eliminar conciliaciones en borrador, líneas y perfiles.
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
  inicio o el mapeo de columnas del perfil. Suba el archivo como muestra en el
  editor de perfiles y use **Probar mapeo** para ver qué está leyendo.
- **Las cifras no cuadran por centavos**: revise el separador decimal del perfil
  (punto o coma) y suba la tolerancia si su procesadora redondea distinto.
- **Un cobro no aparece para cruzar**: puede estar fuera del período de la
  conciliación, ya conciliado en otra, o pertenecer a otro usuario si usted no
  tiene acceso total.

## Historial de cambios

- **1.0** — Versión inicial.
