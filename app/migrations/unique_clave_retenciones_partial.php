<?php
/**
 * Migración: corregir el índice único de clave_acceso en retencion_venta_cabecera.
 *
 * Problema:
 *   El índice 'uq_ret_vta_cab_clave' era UNIQUE global sobre (clave_acceso), sin
 *   considerar id_empresa ni eliminado. Esto provocaba violaciones de unicidad
 *   (SQLSTATE 23505) al cargar documentos desde el SRI cuando existía un registro
 *   lógicamente eliminado con la misma clave, o de otra empresa. La lógica de la
 *   aplicación (existeClaveAcceso / existeEnTabla) valida por (id_empresa,
 *   clave_acceso, eliminado = false), por lo que el índice debe coincidir.
 *
 * Solución:
 *   Reemplazar el índice global por un índice único PARCIAL sobre
 *   (id_empresa, clave_acceso) WHERE eliminado = false AND clave_acceso IS NOT NULL.
 *   Así no bloquea registros eliminados ni cruza empresas, y sigue evitando
 *   duplicados activos dentro de la misma empresa.
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

try {
    echo "Iniciando migración: fix_retencion_venta_clave_unique...\n";

    // 1. Eliminar la restricción/índice único global antiguo si existe.
    //    El nombre puede corresponder a una CONSTRAINT (respaldada por un índice)
    //    o a un índice suelto, por lo que se contemplan ambos casos.
    $esConstraint = (int) $db->query(
        "SELECT count(*) FROM pg_constraint WHERE conname = 'uq_ret_vta_cab_clave'
           AND conrelid = 'retencion_venta_cabecera'::regclass"
    )->fetchColumn();

    if ($esConstraint > 0) {
        $db->exec("ALTER TABLE retencion_venta_cabecera DROP CONSTRAINT uq_ret_vta_cab_clave");
        echo "Restricción 'uq_ret_vta_cab_clave' eliminada.\n";
    } else {
        $existeIndice = (int) $db->query(
            "SELECT count(*) FROM pg_indexes WHERE indexname = 'uq_ret_vta_cab_clave'"
        )->fetchColumn();
        if ($existeIndice > 0) {
            $db->exec("DROP INDEX uq_ret_vta_cab_clave");
            echo "Índice global 'uq_ret_vta_cab_clave' eliminado.\n";
        } else {
            echo "No existe la restricción/índice 'uq_ret_vta_cab_clave' (ya fue eliminado).\n";
        }
    }

    // 2. Crear el índice único parcial nuevo si no existe
    $existeNuevo = (int) $db->query(
        "SELECT count(*) FROM pg_indexes WHERE indexname = 'uq_ret_vta_cab_clave_active'"
    )->fetchColumn();

    if ($existeNuevo === 0) {
        $db->exec(
            "CREATE UNIQUE INDEX uq_ret_vta_cab_clave_active
             ON retencion_venta_cabecera (id_empresa, clave_acceso)
             WHERE eliminado = false AND clave_acceso IS NOT NULL"
        );
        echo "Índice único parcial 'uq_ret_vta_cab_clave_active' creado exitosamente.\n";
    } else {
        echo "El índice 'uq_ret_vta_cab_clave_active' ya existe.\n";
    }

    // 3. Mismo arreglo para retencion_compra_cabecera (idéntico defecto al cargar desde SRI)
    $esConstraintCompra = (int) $db->query(
        "SELECT count(*) FROM pg_constraint WHERE conname = 'uq_ret_cab_clave'
           AND conrelid = 'retencion_compra_cabecera'::regclass"
    )->fetchColumn();

    if ($esConstraintCompra > 0) {
        $db->exec("ALTER TABLE retencion_compra_cabecera DROP CONSTRAINT uq_ret_cab_clave");
        echo "Restricción 'uq_ret_cab_clave' eliminada.\n";
    } else {
        $existeIndiceCompra = (int) $db->query(
            "SELECT count(*) FROM pg_indexes WHERE indexname = 'uq_ret_cab_clave'"
        )->fetchColumn();
        if ($existeIndiceCompra > 0) {
            $db->exec("DROP INDEX uq_ret_cab_clave");
            echo "Índice global 'uq_ret_cab_clave' eliminado.\n";
        } else {
            echo "No existe la restricción/índice 'uq_ret_cab_clave' (ya fue eliminado).\n";
        }
    }

    $existeNuevoCompra = (int) $db->query(
        "SELECT count(*) FROM pg_indexes WHERE indexname = 'uq_ret_cab_clave_active'"
    )->fetchColumn();

    if ($existeNuevoCompra === 0) {
        $db->exec(
            "CREATE UNIQUE INDEX uq_ret_cab_clave_active
             ON retencion_compra_cabecera (id_empresa, clave_acceso)
             WHERE eliminado = false AND clave_acceso IS NOT NULL"
        );
        echo "Índice único parcial 'uq_ret_cab_clave_active' creado exitosamente.\n";
    } else {
        echo "El índice 'uq_ret_cab_clave_active' ya existe.\n";
    }

} catch (\PDOException $e) {
    echo "ERROR en la migración: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'duplicate key value') !== false) {
        echo "AVISO: Existen claves duplicadas activas en la misma empresa. Límpielas antes de aplicar.\n";
    }
    exit(1);
}

echo "Migración completada.\n";
