-- Migración: mover campos de configuración de tabla empresas → empresa_establecimiento
-- Ejecutar una sola vez. Usa IF NOT EXISTS / IF EXISTS para ser idempotente.

-- ============================================================
-- 1. Agregar columnas en empresa_establecimiento
-- ============================================================
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS decimales_cantidad       INTEGER     DEFAULT 2;
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS decimales_precio         INTEGER     DEFAULT 2;
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS calculo_iva_facturacion  VARCHAR(20) DEFAULT 'linea_linea';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS facturacion_inventario   VARCHAR(10) DEFAULT 'true';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS metodo_costeo            VARCHAR(20) DEFAULT 'promedio';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS facturacion_libre        VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS obligatorio_lotes        VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS obligatorio_caducidad    VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS obligatorio_nup          VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS mostrar_cajero_factura   VARCHAR(10) DEFAULT 'false';
ALTER TABLE empresa_establecimiento ADD COLUMN IF NOT EXISTS mostrar_vendedor_factura VARCHAR(10) DEFAULT 'false';

-- ============================================================
-- 2. Copiar valores de empresas → empresa_establecimiento
--    Solo copia columnas que existan en empresas (bloque seguro)
-- ============================================================
DO $$
DECLARE
    col_exists BOOLEAN;
BEGIN
    -- decimales_cantidad
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='decimales_cantidad') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET decimales_cantidad = COALESCE((SELECT decimales_cantidad FROM empresas WHERE id = ee.id_empresa), 2)
        WHERE ee.eliminado = false;
    END IF;

    -- decimales_precio
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='decimales_precio') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET decimales_precio = COALESCE((SELECT decimales_precio FROM empresas WHERE id = ee.id_empresa), 2)
        WHERE ee.eliminado = false;
    END IF;

    -- calculo_iva_facturacion
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='calculo_iva_facturacion') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET calculo_iva_facturacion = COALESCE((SELECT calculo_iva_facturacion FROM empresas WHERE id = ee.id_empresa), 'linea_linea')
        WHERE ee.eliminado = false;
    END IF;

    -- facturacion_inventario
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='facturacion_inventario') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET facturacion_inventario = COALESCE((SELECT facturacion_inventario FROM empresas WHERE id = ee.id_empresa), 'true')
        WHERE ee.eliminado = false;
    END IF;

    -- metodo_costeo
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='metodo_costeo') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET metodo_costeo = COALESCE((SELECT metodo_costeo FROM empresas WHERE id = ee.id_empresa), 'promedio')
        WHERE ee.eliminado = false;
    END IF;

    -- facturacion_libre
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='facturacion_libre') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET facturacion_libre = COALESCE((SELECT facturacion_libre FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;

    -- obligatorio_lotes
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='obligatorio_lotes') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET obligatorio_lotes = COALESCE((SELECT obligatorio_lotes FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;

    -- obligatorio_caducidad
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='obligatorio_caducidad') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET obligatorio_caducidad = COALESCE((SELECT obligatorio_caducidad FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;

    -- obligatorio_nup
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='obligatorio_nup') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET obligatorio_nup = COALESCE((SELECT obligatorio_nup FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;

    -- mostrar_cajero_factura
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='mostrar_cajero_factura') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET mostrar_cajero_factura = COALESCE((SELECT mostrar_cajero_factura FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;

    -- mostrar_vendedor_factura
    SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='empresas' AND column_name='mostrar_vendedor_factura') INTO col_exists;
    IF col_exists THEN
        UPDATE empresa_establecimiento ee
        SET mostrar_vendedor_factura = COALESCE((SELECT mostrar_vendedor_factura FROM empresas WHERE id = ee.id_empresa), 'false')
        WHERE ee.eliminado = false;
    END IF;
END $$;

-- ============================================================
-- 3. Eliminar columnas de empresas (solo si existen)
-- ============================================================
ALTER TABLE empresas DROP COLUMN IF EXISTS decimales_cantidad;
ALTER TABLE empresas DROP COLUMN IF EXISTS decimales_precio;
ALTER TABLE empresas DROP COLUMN IF EXISTS calculo_iva_facturacion;
ALTER TABLE empresas DROP COLUMN IF EXISTS facturacion_inventario;
ALTER TABLE empresas DROP COLUMN IF EXISTS metodo_costeo;
ALTER TABLE empresas DROP COLUMN IF EXISTS facturacion_libre;
ALTER TABLE empresas DROP COLUMN IF EXISTS obligatorio_lotes;
ALTER TABLE empresas DROP COLUMN IF EXISTS obligatorio_caducidad;
ALTER TABLE empresas DROP COLUMN IF EXISTS obligatorio_nup;
ALTER TABLE empresas DROP COLUMN IF EXISTS mostrar_cajero_factura;
ALTER TABLE empresas DROP COLUMN IF EXISTS mostrar_vendedor_factura;
