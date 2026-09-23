-- ============================================================================
-- Interruptor por empresa: qué módulos generan asientos contables automáticos
--
-- Cada empresa decide, módulo por módulo, si el sistema contabiliza sus
-- documentos. El caso que lo originó: empresas que NO quieren reclasificar la
-- mercadería en consignación (Debe Mercadería en Consignación / Haber
-- Inventario) — el "enfoque A" de SAP/Odoo, donde la mercadería entregada sigue
-- en Inventario y solo la factura mueve cuentas.
--
-- Semántica:
--   * Sin fila (o contabiliza = TRUE) → el módulo contabiliza. Es el valor por
--     defecto: ninguna empresa cambia de comportamiento al aplicar este script.
--   * contabiliza = FALSE → no se generan asientos para los documentos que
--     todavía NO tienen uno. Los que ya lo tienen lo conservan y se siguen
--     actualizando al editarse (apagar no deja asientos desfasados).
--   * Retornos CV y Facturación CV no tienen interruptor propio: siguen a su
--     consignación de origen (solo generan el asiento inverso si la madre tiene
--     asiento). Ver config/contabilidad_modulos.php ('sigue_a').
--
-- Ver: app/Services/modulos/ContabilidadInterruptorService.php
--
-- Toca datos: NO (solo crea la tabla vacía).
-- Idempotente: se puede correr varias veces.
-- Reversible: DROP TABLE IF EXISTS contabilidad_modulos_empresa;
--   (el código trata la tabla ausente como "todos los módulos contabilizan").
-- ============================================================================

CREATE TABLE IF NOT EXISTS contabilidad_modulos_empresa (
    id              SERIAL PRIMARY KEY,
    id_empresa      INTEGER      NOT NULL,
    modulo_clave    VARCHAR(60)  NOT NULL,   -- clave de config/contabilidad_modulos.php: 'consignaciones', 'compras'…
    contabiliza     BOOLEAN      NOT NULL DEFAULT TRUE,

    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP    NULL,
    created_by      INTEGER      NULL,
    updated_by      INTEGER      NULL,
    eliminado       BOOLEAN      NOT NULL DEFAULT FALSE,
    deleted_at      TIMESTAMP    NULL,
    deleted_by      INTEGER      NULL
);

-- Una sola fila viva por empresa + módulo (el servicio hace UPSERT sobre esta clave).
CREATE UNIQUE INDEX IF NOT EXISTS ux_contab_modulos_empresa
    ON contabilidad_modulos_empresa (id_empresa, modulo_clave)
    WHERE eliminado = FALSE;

-- Comprobación (debe salir 1 fila):
-- SELECT to_regclass('public.contabilidad_modulos_empresa');
