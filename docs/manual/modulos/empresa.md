---
titulo: Empresa
resumen: Datos de la empresa, sus establecimientos y la configuración que rige a todos los módulos.
categoria: Configuración de empresa
ruta_modulo: modulos/empresa
tipo: modulo
visibilidad: admin
etiquetas: empresa, datos de la empresa, ruc, establecimiento, punto de emision, logo, ambiente, pruebas, produccion, configuracion
version: 1.0
orden: 5
estado: activo
---

El módulo de **Empresa** guarda los datos de la compañía y la configuración que
condiciona el comportamiento del resto del sistema. Es lo primero que se
configura y lo que hay que revisar cuando algo se comporta distinto de lo
esperado en varios módulos a la vez.

## Datos generales

Razón social, RUC, nombre comercial, dirección, contacto y **logo** (que aparece
en los documentos impresos).

## Establecimientos y puntos de emisión

Cada local es un **establecimiento**, y dentro de él hay uno o varios **puntos de
emisión**. La numeración de los comprobantes depende de esta estructura: el
`001-002-000000123` de una factura son precisamente el establecimiento, el punto
de emisión y el secuencial.

### Logo del establecimiento

En la pestaña **Establecimientos** se sube el logo que aparece en los documentos
impresos (factura, nota de crédito, retención, liquidación de compra, guía de
remisión y recibo de venta). El logo ocupa un recuadro de **81 mm × 25.4 mm**
(relación de aspecto aproximada **3.2 : 1**, panorámico): la imagen se ajusta
completa dentro de ese recuadro sin deformarse (se centra y se reduce si hace
falta), por lo que un logo **horizontal/apaisado** aprovecha mejor el espacio que
uno cuadrado o vertical. Formato **PNG con fondo transparente** (también acepta
JPG o GIF), máximo 2 MB; resolución sugerida ~960 × 300 px o mayor, misma
proporción, para que se vea nítido al imprimir. Junto a la miniatura hay un
enlace para **descargar el logo actualmente guardado**.

Si los comprobantes salen con una numeración que no esperaba, es aquí donde se
corrige.

## Ambiente: pruebas o producción

La empresa opera en **ambiente de pruebas** o en **producción**. Es la
configuración más delicada del sistema:

- En **pruebas**, los comprobantes van al entorno de pruebas del SRI y **no
  tienen validez tributaria**.
- En **producción**, son documentos reales.

Los documentos quedan marcados con el ambiente en el que se emitieron. Por eso,
al cambiar de pruebas a producción, los documentos anteriores dejan de verse en
los listados: siguen ahí, pero pertenecen al otro ambiente.

## Configuración por módulo

Desde aquí se ajustan comportamientos que afectan a módulos concretos: cómo se
presentan los ítems en la factura, si las cargas de inventario requieren
aprobación, los textos de los correos, entre otros.

## Errores frecuentes

- **Los comprobantes salen con numeración equivocada**: revise establecimiento y
  punto de emisión.
- **Desaparecieron los documentos antiguos**: se cambió el ambiente; los
  documentos de pruebas no se ven en producción.
- **El logo no sale en el PDF**: compruebe que esté cargado y en un formato
  admitido.

## Operadoras de transporte comercial (placa en la factura)

La marca **"Operadora de transporte comercial (excepto taxis)"** la define el
**superadministrador** al crear o editar la empresa en **Configuración → Empresas
del sistema**. Si la empresa está marcada, la factura pide la **placa del
vehículo** como campo obligatorio y la incluye en el XML y el PDF, según la Ficha
Técnica SRI v2.34 (Anexo 25). No aplica para taxis ni para socios o accionistas
de taxis.

## Historial de cambios

- **1.2** — Se documenta el tamaño exacto del logo en el PDF (81×25.4 mm) y el
  enlace para descargar el logo actualmente guardado en la pestaña
  Establecimientos.
- **1.1** — Se documenta la marca "Operadora de transporte comercial" (se define
  en Configuración → Empresas del sistema; placa del vehículo obligatoria en la
  factura, normativa SRI 2026).
- **1.0** — Versión inicial.
