---
titulo: Anexo RDEP
resumen: Arma y genera el archivo anual RDEP-aaaa.xml de retenciones en la fuente bajo relación de dependencia a partir de la nómina, para cargarlo en SRI en Línea.
categoria: Impuestos
ruta_modulo: modulos/anexo-rdep
tipo: modulo
visibilidad: todos
etiquetas: anexo rdep, rdep, relacion de dependencia, retenciones empleados, impuesto a la renta empleados, formulario 107, anexo anual SRI, RDEP-2025.zip, dimm, sri en linea, gastos personales, rebaja gastos personales, discapacidad, tercera edad, otros empleadores, nomina, roles de pago, decimos, utilidades, xml rdep
version: 1.1
orden: 32
estado: activo
---

El Anexo RDEP es la declaración informativa anual con la que el empleador le
cuenta al SRI cuánto pagó a cada trabajador en relación de dependencia, cuánto
aportó al IESS, qué gastos personales proyectó y cuánto impuesto a la renta le
retuvo. El módulo lo arma desde la nómina del ejercicio y entrega el archivo
listo para subirlo, sin retipear nada en el DIMM. Se presenta en enero del año
siguiente al ejercicio (consulte el calendario vigente del SRI).

## Qué es y para qué sirve

Presentan el RDEP todos los empleadores, personas o sociedades, por los
trabajadores que tuvieron bajo relación de dependencia entre el 1 de enero y
el 31 de diciembre, aunque no hayan hecho retenciones. El módulo cubre la
estructura completa del esquema oficial (datos del trabajador, ingresos,
gastos, deducciones, exoneraciones y resumen impositivo) para los ejercicios
2024 en adelante y produce `RDEP-aaaa.xml` y su `RDEP-aaaa.zip`, que se carga
en **SRI en Línea → Anexos → Envío y consulta de anexos → Anexo RDEP**.

La misma información es la del **formulario 107** que el empleador debe
entregar a cada trabajador.

## Requisitos previos

- **Roles de pago mensuales del ejercicio** generados (o pagados o
  contabilizados). De ahí salen sueldos, otros ingresos, décimos
  mensualizados, fondos de reserva, aporte personal al IESS y lo retenido de
  impuesto a la renta. Las quincenas y semanas no se suman aparte: ya están
  dentro del rol mensual.
- **Décimos acumulados** calculados en los módulos Décimo Tercero y Décimo
  Cuarto del año, y **Utilidades** pagadas por Egresos en el año (módulo
  Utilidades).
- **Gastos personales** del año en la pestaña Impuesto a la renta de cada
  empleado (proyección, cargas familiares, caso especial).
- **Tabla de impuesto a la renta del ejercicio** en Configuración → Impuesto a
  la renta (tramos, canasta básica y porcentaje de rebaja). Sin tramos, el
  impuesto causado sale en cero y el módulo lo avisa.
- **Ficha del empleado completa**: identificación, tipo de identificación,
  nombres y apellidos (en ese orden), fecha de nacimiento, y la discapacidad con
  su porcentaje (pestaña General).

## Cómo se usa

1. Pulse **Nuevo**, indique el **ejercicio** y pulse **Abrir anexo**. El
   sistema crea el anexo con el RUC y la razón social de la empresa, toma los
   parámetros del año (fracción básica desgravada, canasta y porcentaje de
   rebaja) e importa la nómina. Si el anexo del ejercicio ya existe, lo abre.
2. En **Informante** revise el tipo de empleador (privado o mixto, público), el
   ente de seguridad social y los parámetros del ejercicio. **Guardar y
   recalcular** aplica los cambios a todos los trabajadores.
3. En **Trabajadores** revise cada fila. Haga clic en un trabajador para
   completar lo que la nómina no sabe: residencia en el exterior y convenio,
   persona sustituida (si el trabajador es sustituto de alguien con
   discapacidad), beneficio Galápagos,
   ingresos y aportes con otros empleadores (del formulario 107 que entrega el
   trabajador), impuesto asumido por el empleador. Lo que escriba a mano queda
   marcado y no se pierde al volver a importar.
4. En **Observaciones** corrija las **graves** (el SRI rechaza el archivo) y
   revise las **leves** (solo avisos).
5. Pulse **Generar XML**. El módulo recalcula, valida como lo hace el SRI,
   comprueba el archivo contra el esquema oficial y deja el XML y el ZIP para
   descargar. Si vuelve a editar algo, el anexo pasa a borrador y hay que
   generar de nuevo.

**Importar nómina** se puede repetir cuantas veces haga falta (por ejemplo,
después de corregir un rol): refresca los valores de nómina y conserva lo
editado a mano. **Agregar trabajador** sirve para incluir a alguien sin rol en
el sistema (se completa a mano) y **Quitar del anexo** lo saca.

## Campos del formulario

Cabecera:

| Campo | Obligatorio | Qué significa |
|-------|-------------|---------------|
| Ejercicio | Sí | Año informado, de enero a diciembre. |
| RUC del empleador | Sí | 13 dígitos terminados en 001. |
| Tipo de empleador | Sí | Privado o mixto, o público. Un privado solo puede informar IESS. |
| Ente de seguridad social | Sí | IESS, o ISSFA/ISSPOL (solo sector público). |
| Fracción básica desgravada | Sí | Límite del tramo de 0% del año; base de las exoneraciones. |
| Canasta familiar básica | Sí | Valor que publica el SRI para el ejercicio; base del tope de gastos personales. |
| % rebaja gastos personales | Sí | 18% desde 2023. |
| IPCEG | Sí | Índice de Galápagos que amplía el tope de gastos (1,803). |

Trabajador, pestaña **Datos**: tipo e identificación, apellidos y nombres (solo
letras, sin ñ ni tildes: el sistema los limpia), establecimiento del RUC,
residencia, país, convenio de doble imposición, condición de discapacidad con
porcentaje y persona sustituida, Galápagos, con o a cargo de discapacidad o
enfermedad catastrófica, cargas familiares (0 a 5) y tercera edad.

Pestaña **Ingresos**: sueldos y salarios con IESS, otros ingresos gravados sin
IESS, utilidades, IR asumido por el empleador, décimos, fondo de reserva,
salario digno, ingresos no gravados, y lo ganado y aportado con otros
empleadores.

Pestaña **Gastos y exoneraciones**: sistema de salario neto, aporte personal,
los seis rubros de gastos personales y las exoneraciones calculadas.

Pestaña **Resumen impositivo**: base imponible, impuesto causado, rebaja,
impuesto después de la rebaja (calculados) y lo retenido o asumido (editables),
con la diferencia que el SRI va a comparar.

## Permisos

- **Ver**: abrir el listado y los anexos, descargar los archivos ya generados,
  PDF y Excel del listado.
- **Crear**: abrir el anexo de un ejercicio.
- **Modificar**: importar, editar la cabecera y los trabajadores, recalcular y
  generar el archivo.
- **Eliminar**: eliminar un anexo.
- **Acceso total**: ve los anexos de toda la empresa; sin él, solo los que creó
  el propio usuario.

## Reglas de negocio

- **Quién entra**: todo empleado con rol mensual, décimo acumulado o utilidades
  pagadas en el ejercicio. Los empleados eliminados no se informan.
- **Ingresos desde la nómina**: sueldos = rubros de ingreso que aportan al
  IESS; otros gravados = ingresos que no aportan (bonos, comisiones sin IESS);
  décimos = mensualizados del rol más los acumulados pagados; fondo de reserva
  = lo pagado en el rol; retenido = la retención de impuesto a la renta del rol.
- **Base imponible** = sueldos + otros gravados + utilidades + ingresos con
  otros empleadores + IR asumido − aportes personales (con este y otros
  empleadores) − exoneraciones. Los décimos, fondos, salario digno e ingresos no
  gravados no entran, y los gastos personales tampoco: solo generan la rebaja.
- **Rebaja por gastos personales** = % × el menor entre la canasta por el
  factor de cargas (7, 9, 11, 14, 17 o 20 canastas; 100 con discapacidad o
  enfermedad catastrófica, multiplicado por el IPCEG en Galápagos) y la suma de
  los seis rubros.
- **Exoneraciones**: tercera edad (65 años o más al cierre) hasta una fracción
  básica; discapacidad con 30% o más (trabajador o sustituto) 60, 70, 80 o
  100% de dos fracciones básicas según el grado. Se aplica solo la más
  beneficiosa y nunca deja la base en negativo.
- **Validaciones graves** (bloquean la generación): cédula inválida, nombres
  con caracteres no permitidos, establecimiento 000, residencia y país o
  convenio incoherentes, discapacidad sin porcentaje o con identificación del
  sustituido incorrecta, identificación repetida, retenido por otros sin
  ingresos de otros, salario neto con aporte.
- **Validaciones leves** (solo avisan): aporte personal mayor al 9,45% (11,45%
  o 23,10% en público), aportes sin ingresos y viceversa, y la diferencia
  entre retenido + asumido y el impuesto después de la rebaja. Esa diferencia
  no la corrige el anexo: si retuvo de menos, se regulariza en la última nómina
  del año o la declara el trabajador.
- **Campos manuales**: lo que edite en un trabajador queda marcado y no se
  pisa al reimportar. Cambiar los parámetros de la cabecera recalcula a todos.
- El archivo se valida contra el esquema oficial (`rdep.xsd`) antes de
  entregarse; se escribe en ISO-8859-1, como el ejemplo del SRI.

## Integraciones con otros módulos

- **Roles de pago, Décimo Tercero, Décimo Cuarto y Utilidades**: fuentes de
  los ingresos, aportes y retenciones.
- **Empleados** (pestaña Impuesto a la renta): gastos personales, cargas y caso
  especial del año; ficha para identificación, nombres, nacimiento y
  discapacidad.
- **Configuración → Impuesto a la renta**: tramos, canasta y % de rebaja del
  ejercicio.

## Errores frecuentes

- **"Hay N observación(es) grave(s) que el SRI rechazaría"**: corríjalas en la
  pestaña Observaciones antes de generar.
- **"La cédula X no es válida"**: la identificación de la ficha del empleado
  está mal; corríjala allí e importe de nuevo, o en el trabajador del anexo.
- **Apellidos y nombres al revés**: la ficha del empleado guarda primero los
  nombres y luego los apellidos; corrija el orden en la ficha o edite el
  trabajador en el anexo.
- **El impuesto causado sale en cero**: falta la tabla de tramos del ejercicio
  en Configuración → Impuesto a la renta.
- **"El archivo no cumple el esquema del SRI"**: algún valor no respeta el
  formato del esquema; el mensaje indica la línea. Reporte a soporte.
- **Un trabajador no aparece**: no tiene rol mensual generado en el ejercicio;
  agréguelo con el buscador de la pestaña Trabajadores.

## Historial de cambios

- **1.1** — La discapacidad y su porcentaje se toman de la ficha del empleado.
- **1.0** — Versión inicial: importación desde la nómina, edición por
  trabajador, cálculo del resumen impositivo según el catálogo 2024 del SRI,
  validaciones graves y leves, XML y ZIP validados contra el esquema oficial.
