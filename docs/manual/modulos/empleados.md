---
titulo: Empleados
resumen: Ficha del personal de la empresa; base de la nómina, las novedades y el control de asistencia.
categoria: Nómina
ruta_modulo: modulos/empleados
tipo: modulo
visibilidad: todos
etiquetas: empleados, empleado, personal, trabajadores, nomina, ficha, cedula, sueldo, contratacion, credencial, qr personal, asistencia, marcar, rostro
version: 1.3
orden: 10
estado: activo
---

El módulo de **Empleados** es el registro del personal. Alimenta los roles de
pago, las novedades, las vacaciones, los décimos y el control de asistencia: sin
la ficha, el empleado no existe para ninguno de esos procesos.

## Cómo se registra

1. Pulse **Nuevo**.
2. Elija el **tipo de identificación** e ingrese el número.
3. Complete **nombres y apellidos**.
4. Añada el resto de datos personales y laborales.
5. Guarde.

## Validaciones

| Campo | Regla |
|-------|-------|
| Tipo de identificación | Obligatorio |
| Identificación | Obligatoria |
| Cédula | Si es cédula, exactamente **10 dígitos** |
| Nombres y apellidos | Obligatorios |
| Correo electrónico | Si se llena, debe tener formato válido |
| Sexo | Debe ser uno de los valores admitidos |
| Cargas familiares | Número entero, 0 o más. Vacío equivale a 0 |

## Cargas familiares

En la pestaña **General**, en la última fila junto al contacto de emergencia,
se registra el **número de cargas familiares** del empleado (0 si no tiene). Es
un dato general de la ficha y sale en el PDF y el Excel del empleado.

También se puede cargar por Excel: la plantilla del botón *Importar* del módulo
trae la columna **CARGAS_FAMILIARES** al final, y la entidad *Empleados* del
Importador desde Excel (Configuración) tiene la misma columna. En ambos casos es
opcional: las plantillas antiguas sin esa columna siguen funcionando y dejan el
valor en 0.

> No confundir con las cargas que se declaran **por año** en la pestaña de
> gastos personales (formulario SRI-GP): esas son las que determinan la rebaja
> del Impuesto a la Renta y se registran aparte, año por año.

## Ficha en PDF y Excel

En la barra superior del modal (junto al título) hay dos botones para exportar
la ficha del empleado ya guardado: el ícono rojo descarga el **PDF** y el ícono
verde descarga el **Excel**. Ambos incluyen los mismos datos —generales,
laborales, bancarios, historial de periodos y rubros fijos—; el Excel los
organiza en secciones con pares etiqueta/valor. Los dos quedan deshabilitados
(avisan "Guarde primero") mientras el empleado no se ha guardado.

## Credencial de asistencia (QR personal y rostro)

La pestaña **Credenciales** entrega al empleado lo que necesita para marcar su
asistencia desde el celular. Se habilita cuando el empleado ya está guardado.

- **Generar QR** crea su **credencial personal**: un código propio (empieza con
  `EMP-`) y un **enlace personal**. El empleado abre ese enlace **una sola vez**
  desde su teléfono —escaneando el QR o tocando el enlace— y el teléfono queda
  vinculado. Desde ahí en adelante solo escanea el QR del punto de servicio para
  marcar.
- **Copiar enlace** copia la dirección completa, con el dominio, para enviarla
  por WhatsApp o correo. **Copiar código** copia solo el código, para quien no
  puede escanear y lo escribe a mano en la pantalla de marcación.
- **Regenerar** entrega un código nuevo y **anula el anterior**: el teléfono que
  ya estaba vinculado deja de marcar hasta que el empleado abra el enlace nuevo.
- **Registrar rostro** guarda un vector facial (no una foto) para confirmar que
  quien marca es el empleado. Es **opcional** y requiere su consentimiento
  (LOPDP). Sin rostro registrado, la marcación se valida solo con la credencial
  y el GPS.

El código distingue mayúsculas de minúsculas; al dictarlo o escribirlo a mano hay
que respetarlo tal cual, aunque la pantalla de marcación corrige por su cuenta el
caso más frecuente: el código escrito todo en mayúsculas.

## Un catálogo por empresa

Los empleados pertenecen a una empresa. A diferencia de los comprobantes
electrónicos, **la ficha del empleado no distingue entre ambiente de pruebas y
producción**: es un catálogo maestro, siempre el mismo.

## Errores frecuentes

- **"La cédula debe tener exactamente 10 dígitos"**: revise el número o cambie el
  tipo de identificación si es un extranjero con pasaporte.
- **No aparece al generar el rol de pago**: compruebe que esté activo y en la
  empresa correcta.
- **"La credencial guardada en este teléfono ya no es válida"** o **"Ese código
  no corresponde a una credencial activa"**: la credencial fue regenerada, el
  empleado está inactivo o el código se escribió mal. Abra la pestaña
  *Credenciales* y reenvíele el enlace o el código vigente.
- **Al escanear el QR de la credencial el celular no abre nada**: vuelva a abrir
  la pestaña *Credenciales* y reenvíe el enlace. Los QR generados antes de esta
  corrección guardaban una dirección incompleta (sin el dominio) y ningún lector
  podía abrirla.

## Historial de cambios

- **1.3** — Se documenta la pestaña *Credenciales*. El enlace y el QR personal
  ahora llevan la dirección completa con el dominio; los anteriores no se podían
  abrir al escanearlos desde el celular.
- **1.2** — Campo *Cargas familiares* en la pestaña General, en la ficha
  PDF/Excel y como columna opcional en las plantillas de importación.
- **1.1** — Botón para exportar la ficha del empleado a Excel, junto al de PDF.
- **1.0** — Versión inicial.
