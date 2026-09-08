---
titulo: Estructuras SuperCías
resumen: Casilleros y fórmulas de los estados financieros que se cargan en el portal de la Superintendencia de Compañías (ESF, ERI, ECP, EFE).
categoria: Configuración
ruta_modulo: config/supercias
tipo: modulo
visibilidad: superadmin
requiere_permiso_modulo: no
etiquetas: supercias, superintendencia de compañias, casilleros, formulario niif, estado de situacion financiera, estado de resultado integral, estado de cambios en el patrimonio, flujo de efectivo, esf, eri, ecp, efe, formulas, txt supercias, carga de balances
version: 1.1
orden: 60
estado: activo
---

Aquí se define la **estructura oficial** de los cuatro estados financieros que
la Superintendencia de Compañías recibe bajo NIIF: qué casilleros existen, en
qué orden van y cómo se calcula cada uno. Es una configuración **global**: la
misma para todas las empresas. Los valores de cada empresa salen de su
contabilidad, a través del mapeo de cuentas en Plan de Cuentas, y se descargan
desde Estados Financieros.

## Qué es y para qué sirve

El portal de Supercías acepta los balances como archivos de texto con el código
del casillero y su valor. Este módulo guarda esos casilleros para los cuatro
estados:

- **ESF**: Estado de Situación Financiera.
- **ERI**: Estado de Resultado Integral.
- **ECP**: Estado de Cambios en el Patrimonio.
- **EFE**: Estado de Flujos de Efectivo.

Cada casillero puede ser de dos clases:

- **Origen contable mapeado**: su valor es la suma de las cuentas del plan que
  lo tienen asignado (campos *Código SRI / Supercias* de la cuenta).
- **Con fórmula**: su valor se calcula a partir de otros casilleros.

## Requisitos previos

- Ser superadministrador (nivel 3).
- Para que los archivos tengan valores, las cuentas de nivel 5 del plan deben
  tener asignado su casillero en Plan de Cuentas.

## Cómo se usa

1. Elija la pestaña del estado (ESF, ERI, ECP o EFE). La pestaña activa se
   recuerda por usuario.
2. Use el buscador para ubicar un casillero por código o descripción.
3. Pulse la fila para editarla, o **Nuevo Casillero** para agregar uno.
4. En el modal indique tipo, código, descripción, la fórmula si aplica y,
   opcionalmente, *Ubicar después del código* para colocarlo en el orden
   correcto.

### Cómo se escribe una fórmula

Solo sumas y restas de casilleros, sin paréntesis ni funciones:

- `101+102` suma los casilleros 101 y 102 del mismo estado.
- `[ESF:301]` toma el casillero 301 del ESF desde otro estado. Es la forma
  recomendada cuando el casillero está en otra pestaña o cuando el mismo código
  existe en varios estados.
- `10101-10102` resta.

Un número que no coincide con ningún casillero se trata como constante. Las
fórmulas se resuelven en cadena: un casillero con fórmula puede usar otros que
a su vez tienen fórmula.

### Pasar las fórmulas desde una hoja de Excel

Si tiene la estructura en Excel con fórmulas del tipo `=D10+D47` o
`=SUMA(D10:D20)`, donde la columna B tiene el código del casillero, use en una
columna libre esta fórmula (Excel 365, cambie `D3` por la celda de la fórmula)
y copie el resultado al campo Fórmula:

```
=SI.ERROR(LET(f;SUSTITUIR(SUSTITUIR(SUSTITUIR(SUSTITUIR(SUSTITUIR(SUSTITUIR(SUSTITUIR(FORMULATEXTO(D3);"$";"");"=";"");" ";"");"SUMA(";"");")";"");";";"+");"D";"");t;DIVIDIRTEXTO(f;"+");u;FILTRAR(t;t<>"");filas;EXCLUIR(REDUCE(0;u;LAMBDA(a;x;LET(i;--TEXTOANTES(x;":";;;;x);j;--TEXTODESPUES(x;":";;;;x);APILARV(a;SECUENCIA(j-i+1;1;i)))));1);cods;INDICE($B:$B;filas);UNIRCADENAS("+";VERDADERO;FILTRAR(cods;(cods<>"")*(cods<>0))));"")
```

## Campos del formulario

| Campo | Descripción |
|---|---|
| Tipo de Estado | ESF, ERI, ECP o EFE. |
| Código | Código del casillero en el formulario oficial. En ECP es la **fila** (99, 9901, 990101, 9902, 990201…). |
| Subcódigo | Solo ECP: la **columna**, es decir el componente del patrimonio (301, 302, 303, 30401, 30402, 30501 a 30504, 30601 a 30607, 30701, 30702). |
| Descripción | Nombre del casillero. En ECP se usa `fila / columna`, por ejemplo `SALDO AL FINAL DEL PERÍODO / CAPITAL`. |
| Ubicar después del código | Opcional. Reordena el casillero detrás del indicado. Vacío al crear lo deja al final; vacío al editar conserva su posición. |
| Fórmula | Opcional. Si está vacía, el casillero toma su valor de las cuentas mapeadas. |

## Permisos

Solo superadministradores. No pasa por permisos de submódulo.

## Reglas de negocio

- Un casillero se identifica por **tipo + código + subcódigo**. En ESF, ERI y
  EFE el subcódigo va vacío, así que el código no se repite. En ECP el mismo
  código se repite en todas las columnas y lo que no puede repetirse es la
  pareja código y subcódigo.
- Los casilleros no se borran físicamente: quedan marcados como eliminados.
- Un casillero con fórmula ignora las cuentas que lo tengan mapeado; manda la
  fórmula.

### El ECP (Estado de Cambios en el Patrimonio)

Es una matriz. Las **filas** son los conceptos del cambio y las **columnas**
los componentes del patrimonio. Las filas que reconoce el sistema:

| Fila | Concepto |
|---|---|
| 99 | Saldo al final del período |
| 9901 | Saldo reexpresado del período inmediato anterior |
| 990101 | Saldo del período inmediato anterior |
| 990102 | Cambios en políticas contables |
| 990103 | Corrección de errores |
| 9902 | Cambios del año en el patrimonio |
| 990201 | Aumento (disminución) de capital social |
| 990202 | Aportes para futuras capitalizaciones |
| 990203 | Prima por emisión primaria de acciones |
| 990204 | Dividendos |
| 990205 | Transferencia de resultados a otras cuentas patrimoniales |
| 990206 | Realización de la reserva por valuación de activos financieros |
| 990207 | Realización de la reserva por valuación de propiedades, planta y equipo |
| 990208 | Realización de la reserva por valuación de activos intangibles |
| 990209 | Otros cambios (detallar) |
| 990210 | Resultado integral total del año (ganancia o pérdida) |

Recomendación: la fila **99** de cada columna con fórmula `[ESF:<columna>]`
(por ejemplo `[ESF:301]`), para que el saldo final del ECP sea exactamente el
del balance. El resto de filas las calcula Estados Financieros a partir de los
asientos (ver el manual de ese módulo). Si una fila tiene fórmula, la fórmula
manda sobre ese cálculo.

## Integraciones con otros módulos

- **Plan de Cuentas**: cada cuenta de nivel 5 indica su casillero ESF, ERI y,
  para el ECP, su columna y opcionalmente su fila de cambios.
- **Estados Financieros**: botones *Supercias ESF / ERI / ECP / EFE* que
  descargan los TXT con los datos del reporte en pantalla, y *Ver ECP* para
  revisar la matriz antes de bajar el archivo.

## Errores frecuentes

- **"El casillero 99 / subcódigo 301 ya existe en el tipo ECP"**: ya hay una
  fila con esa pareja código y subcódigo. Edite la existente.
- **Un casillero con fórmula sale en cero**: alguno de los códigos que usa no
  existe en la estructura, o las cuentas de esos casilleros no están mapeadas.
- **En ECP la fila 99 no coincide con 9901 + 9902**: revise en *Ver ECP* de
  Estados Financieros la fila *Diferencia*; suele faltar la columna ECP en
  alguna cuenta de patrimonio o un asiento de apertura sin el tipo *apertura*.

## Historial de cambios

- **1.1** — La clave única de un casillero pasa a ser tipo + código +
  subcódigo (antes solo código, lo que impedía editar las filas del ECP). Se
  documenta la estructura del ECP y la fórmula `[ESF:…]` para su fila 99.
- **1.0** — Versión inicial.
