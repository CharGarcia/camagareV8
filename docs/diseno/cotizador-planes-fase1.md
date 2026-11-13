# Cotizador de Planes — Diseño

> Documento de diseño. **No es el manual del usuario** (está en
> `docs/manual/modulos/cotizador-planes.md`) y no se publica en `/documentacion`.
> Versión 2 — 13-11-2026. Estado: **aprobado por el usuario; fase 1 en
> implementación**.
>
> **v2:** decisiones D1–D6 tomadas. Sin fase móvil por ahora. Los precios se
> fijan **por módulo** (no por plan). Cada módulo puede arrastrar otros que
> necesita para funcionar. El precio anual y el mensual se ven en vivo mientras
> el vendedor arma la cotización.

## 1. Objetivo

Que el vendedor arme, frente al cliente, el precio de CaMaGaRe según lo que
esa empresa realmente necesita:

1. escribe el RUC y el sistema trae los datos del SRI, incluidos los
   establecimientos abiertos;
2. marca qué establecimientos se contratan y qué módulos y servicios lleva
   cada uno;
3. indica cuántos usuarios necesita el RUC;
4. ve el precio **anual y mensual** en vivo;
5. genera la **Proforma** enlazada a una oportunidad del **CRM**, con una sola
   línea resumen (*Plan anual CaMaGaRe*) y el detalle en el **anexo de
   condiciones** de la proforma.

## 2. Decisiones tomadas

| # | Decisión |
|---|---|
| D1 | La salida es una **Proforma enlazada a una oportunidad del CRM**. |
| D2 | Los precios se fijan **en cada módulo** (submódulo del menú). Cada módulo declara de cuáles depende: al agregar *Facturas de Venta* se agregan solos *Empresa*, *Clientes*, *Productos*, etc. El precio anual y mensual se ve en vivo. |
| D3 | Todo tiene precio, aunque sea $0. Hay ítems **«por defecto»** que entran solos en cada establecimiento nuevo (por ejemplo, *Implementación* a $0). |
| D4 | El vendedor puede dar descuento **solo si la configuración lo permite**, con un tope (%). |
| D5 | El vendedor llena todo. La proforma lleva **una línea resumen** (producto de la empresa, p. ej. *Plan anual CaMaGaRe*) y el detalle va en el **anexo de condiciones** que ya existe en Proformas. |
| D6 | Lo usan **solo los vendedores propios** (no revendedores). |
| — | **Sin fase móvil** por ahora. |

## 3. Reglas de precio (presentación de vendedores v0.10)

Todas son **parámetros editables**, no constantes en el código:

| Parámetro | Valor inicial |
|---|---|
| Recargo del plan mensual | 20 % → mensual = anual ÷ 12 × 1,20 |
| Descuento por establecimiento adicional (solo plan anual) | 20 % |
| Usuarios incluidos por establecimiento (compartidos a nivel de RUC) | 3 |
| Precio anual del usuario adicional | $20 |
| ¿El vendedor puede dar descuento? / tope | No / 0 % |

**Cálculo** (clase pura `CotizadorPlanesCalculo`, con prueba en
`tools/verificar_cotizador_planes.php`):

- Cada ítem del catálogo es **recurrente** (se cobra cada año o cada mes) o
  **único** (se cobra una sola vez: implementación, migración…).
- Cada ítem recurrente dice si **entra en el descuento por establecimiento
  adicional** (módulos «base») o no (módulos adicionales, que van a precio
  completo en cada establecimiento).
- **Establecimiento principal** = el de mayor valor descontable (base); en
  empate, el primero. Paga completo.
- **Anual**: por establecimiento, base × (1 − 20 % si no es principal) +
  adicionales. **Mensual**: todo a precio completo ÷ 12 × 1,20.
- **Usuarios**: incluidos = 3 × establecimientos; adicionales =
  max(0, usuarios − incluidos) × $20 al año (o ÷ 12 × 1,20 al mes).
- **Descuento del vendedor**: % sobre lo recurrente de la modalidad elegida,
  si está habilitado y dentro del tope. Los servicios únicos no se descuentan.
- Redondeo a 2 decimales en cada paso, medio hacia arriba.

## 4. Datos

| Tabla | Tipo | Contenido |
|---|---|---|
| `cotizador_planes_config` | por empresa | Parámetros de §3, producto de la línea resumen anual y mensual, días de vigencia. |
| `cotizador_planes_items` | por empresa | Catálogo de precios: módulo del menú (`id_submodulo`, `ruta`) o servicio libre; precio anual; recurrente/único; entra o no en el descuento por establecimiento; por defecto; producto opcional; orden y estado. |
| `cotizador_planes_items_dependencias` | por empresa | Qué ítem requiere a qué otro (se agregan solos, en cadena). |
| `cotizaciones_planes` | por empresa | Cabecera: número, datos del RUC, CRM (contacto, oportunidad), cliente, proforma, modalidad, usuarios, descuento, totales, **copia de los parámetros usados** (JSON), estado. |
| `cotizaciones_planes_establecimientos` | por empresa | Establecimientos cotizados (código, nombre, dirección, si es principal). |
| `cotizaciones_planes_detalle` | por empresa | Ítems de cada establecimiento con su **precio copiado** del catálogo y si entró por dependencia. |

Todas llevan los campos de auditoría y la eliminación lógica de CLAUDE.md §5.
El número de la cotización se toma con `pg_advisory_xact_lock` (§8).

## 5. Flujo

1. **Listado** de cotizaciones (estándar §9): número, fecha, RUC, razón
   social, establecimientos, usuarios, modalidad, total, vendedor, estado,
   proforma y oportunidad.
2. **Modal de cotización** (pestañas):
   - **Cliente**: RUC + botón *Consultar SRI* (razón social, nombre comercial,
     tipo de contribuyente, dirección, establecimientos abiertos), correo,
     teléfono, observaciones.
   - **Establecimientos y módulos**: un bloque por establecimiento con un
     buscador de módulos/servicios del catálogo. Al agregar uno, entran sus
     dependencias. No se quita un ítem que otro necesita.
   - **Resumen**: usuarios, modalidad (anual/mensual), descuento del
     vendedor si está permitido, y la tabla **anual vs. mensual** con el
     detalle por establecimiento. El total se recalcula en el servidor en cada
     cambio (una sola fuente de verdad).
   - **Información** (niveles 2 y 3, pieza común).
3. **Generar proforma** (pide la serie con secuencial de Proformas): registra
   o reutiliza el **cliente**, el **contacto** y la **oportunidad** del CRM, y
   crea la proforma con la línea resumen y el anexo. Todo en una transacción,
   salvo el cliente, que se registra antes porque su servicio maneja su propia
   transacción y es idempotente (si ya existe, se reutiliza).
4. Desde ahí, el seguimiento sigue en el **CRM** y la aprobación, el correo y
   la conversión a factura en **Proformas**.

## 6. Permisos

- Ruta `modulos/cotizador-planes`. Vendedor sin acceso total → solo sus
  cotizaciones (§6).
- **Configuración y catálogo de precios**: solo niveles 2 y 3.
- Generar la proforma exige además permiso de **crear en Proformas**.

## 7. Fuera de la fase 1

- Crear las suscripciones y dar de alta las empresas al ganar.
- App móvil.
- Control automático de cupos de usuarios por RUC.
