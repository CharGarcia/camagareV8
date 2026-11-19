---
titulo: Carga de clientes por Excel
resumen: Descargue todos sus clientes a Excel, edítelos y súbalos para crear o actualizar muchos a la vez.
categoria: Ventas
ruta_modulo: modulos/carga-clientes
tipo: modulo
visibilidad: todos
etiquetas: carga masiva, importar clientes, exportar clientes, excel, plantilla, actualizar clientes, editar clientes masivo, subir clientes, cambiar vendedor masivo, cambiar plazo, base de clientes, migrar clientes
version: 1.0
orden: 16
estado: activo
---

Este módulo descarga **todos sus clientes a un Excel** para que los edite con
calma y luego los vuelva a subir: los que ya existen se **actualizan** y los
nuevos se **crean**. Sirve para arrancar con la cartera completa de clientes,
para corregir datos de muchos a la vez (correos, teléfonos, direcciones) o para
reasignar vendedores y plazos de crédito de forma masiva.

## Requisitos

- Permiso **Ver** en el módulo para descargar la plantilla.
- Permiso **Crear** para dar de alta clientes nuevos y **Modificar** para
  actualizar los existentes. Con uno solo de los dos, las filas del otro tipo se
  marcan con error y se omiten.
- Sin **Acceso total**, la plantilla trae solo los clientes que usted registró y
  solo puede modificar esos.

## Cómo se usa

1. **Descargue la plantilla.** Viene con sus clientes y con hojas de consulta
   (tipos de identificación, provincias, ciudades, vendedores y formas de pago
   del SRI) para no adivinar los valores válidos.
2. Edite las filas o agregue clientes nuevos al final de la hoja **Clientes**.
3. Elija el modo (carga completa o solo algunas columnas) y suba el archivo.
4. Revise el resultado: cuántos se crearán, cuántos se actualizarán, cuántos no
   cambiaron y qué filas tienen errores. No se guarda nada hasta que pulse
   **Aplicar cambios**.

## Crea o actualiza según la identificación

La clave es la columna **IDENTIFICACION** (cédula, RUC, pasaporte):

- Si la identificación **no existe** en la empresa, se crea el cliente.
- Si **ya existe**, se actualiza con los datos del archivo.
- Si la fila es **igual** a lo que ya está registrado, se ignora: no se reescribe
  ni aparece en la auditoría. Por eso puede subir el archivo completo aunque solo
  haya cambiado tres clientes.

La identificación no se puede cambiar desde el Excel. Si un cliente fue
**eliminado**, su identificación no se puede volver a crear.

En la **carga completa** se escriben todas las columnas: una celda que deje vacía
**borra** ese dato del cliente (por ejemplo, un VENDEDOR vacío deja al cliente sin
vendedor). La forma de cobro predeterminada, el cobro automático y la ubicación
en el mapa no están en la plantilla y nunca se tocan.

## Actualizar solo algunas columnas

Si solo quiere cambiar un dato —por ejemplo el vendedor o el plazo— sin riesgo de
alterar lo demás, antes de revisar el archivo elija **Actualizar solo algunas
columnas** y marque lo que desea cambiar:

- Solo las columnas marcadas se toman del archivo. En las demás se conserva el
  valor que el cliente ya tiene, aunque en el Excel estén vacías o tengan otro dato.
- En este modo **no se crean clientes**: una identificación que no existe se
  marca con error y se omite.

Si cambia la elección después de revisar el archivo, tendrá que revisarlo de nuevo.

## Columnas de la plantilla

| Columna | Qué escribir |
|---------|--------------|
| IDENTIFICACION | Cédula (10 dígitos), RUC (13, termina en 001 o 002), pasaporte. Obligatoria |
| TIPO_IDENTIFICACION | RUC, Cedula, Pasaporte, Consumidor final o Exterior. Si la deja vacía se deduce por el largo |
| NOMBRE | Nombre o razón social, hasta 300 caracteres. Obligatorio |
| EMAIL | Obligatorio. Varios correos separados por coma |
| TELEFONO | Hasta 20 caracteres |
| DIRECCION | Hasta 300 caracteres |
| PROVINCIA / CIUDAD | Como aparecen en las hojas Ref_Provincias y Ref_Ciudades |
| PLAZO | Días de crédito (0 = contado) |
| VENDEDOR | Nombre como aparece en Ref_Vendedores (da igual mayúsculas, minúsculas o tildes), o su identificación si hay dos con el mismo nombre. El vendedor debe estar creado: si no existe, la fila se marca con error y no se cambia |
| FORMA_PAGO_SRI | Código de la hoja Ref_FormasPago (por ejemplo 20) |
| ESTADO | Activo o Inactivo |
| OBSERVACIONES | Notas internas del cliente |
| DIAS_VISITA | Días de visita del vendedor: Lun, Mar, Mie, Jue, Vie, Sab, Dom separados por coma |
| FRECUENCIA_VISITA | Semanal, Quincenal o Mensual (obligatoria si hay días) |
| SEMANAS_VISITA | Semanas del mes (1 a 5) separadas por coma; obligatoria en Quincenal y Mensual |
| HORA_VISITA_DESDE / HASTA | Formato HH:MM, por ejemplo 08:30 |
| ORDEN_VISITA | Número de orden en la ruta del vendedor |
| OBSERVACION_VISITA | Indicaciones para la visita |

Las validaciones son las mismas que al guardar un cliente desde su ficha.

## Dar de baja

Desde el Excel no se elimina. Para dejar de usar un cliente ponga
**ESTADO = Inactivo**: conserva su historial y sus documentos.

## Permisos

| Permiso | Qué permite |
|---------|-------------|
| Ver | Entrar al módulo y descargar la plantilla |
| Crear | Dar de alta clientes nuevos desde el archivo |
| Modificar | Actualizar clientes existentes desde el archivo |
| Acceso total | Descargar y modificar los clientes de toda la empresa (sin él, solo los propios) |

## Reglas de negocio

- La plantilla está atada a la empresa en la que se descargó: no se puede subir
  en otra empresa.
- No se pueden borrar ni agregar hojas ni cambiar los encabezados.
- Cada cliente se guarda por separado: si uno falla, los demás se guardan igual y
  el fallo se informa al final.
- Cada alta y cada modificación queda en la auditoría del cliente (antes y
  después), igual que si se hiciera desde la ficha. La descarga de la plantilla
  también queda registrada.
- Una misma revisión solo se puede aplicar una vez.

## Errores frecuentes

- **"La cédula debe tener exactamente 10 dígitos"** con una cédula de 9: Excel le
  quitó el cero inicial. Escriba la identificación como texto (la plantilla ya
  trae esa columna en formato texto; al pegar use "Pegar solo valores").
- **"El correo electrónico es obligatorio"**: el cliente no tiene correo. También
  ocurre al actualizar solo otras columnas, porque el cliente debe quedar válido.
- **"Existe un cliente ELIMINADO con esta identificación"**: ese cliente se
  eliminó antes; no se puede crear otro con la misma identificación.
- **"Hay varios vendedores llamados…"**: escriba la identificación del vendedor.
- **"Esta plantilla se generó para otra empresa"**: descargue la plantilla desde
  la empresa en la que va a cargar.

## Historial de cambios

- **1.0** — Versión inicial.
