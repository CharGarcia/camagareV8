<?php
/**
 * Migración: Crear tablas del módulo de Automatizaciones (Cron dinámico).
 *
 * Tablas:
 *   - automatizaciones        → registro de tareas programadas por empresa/establecimiento
 *   - automatizaciones_log    → historial de ejecuciones
 */

define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

try {
    echo "Iniciando migración: create_automatizaciones...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS automatizaciones (
            id                  SERIAL PRIMARY KEY,
            id_empresa          INTEGER NOT NULL,
            id_establecimiento  INTEGER DEFAULT NULL,
            nombre              VARCHAR(150) NOT NULL,
            descripcion         TEXT DEFAULT NULL,
            modulo              VARCHAR(80) NOT NULL,
            accion              VARCHAR(80) NOT NULL,
            parametros          JSONB DEFAULT '{}'::jsonb,
            frecuencia_tipo     VARCHAR(20) NOT NULL DEFAULT 'diario',
            frecuencia_valor    VARCHAR(100) NOT NULL DEFAULT '00:00',
            cron_expression     VARCHAR(100) DEFAULT NULL,
            proxima_ejecucion   TIMESTAMP DEFAULT NULL,
            ultima_ejecucion    TIMESTAMP DEFAULT NULL,
            ultimo_resultado    VARCHAR(20) DEFAULT NULL,
            estado              VARCHAR(20) NOT NULL DEFAULT 'activo',
            eliminado           BOOLEAN NOT NULL DEFAULT false,
            created_at          TIMESTAMP NOT NULL DEFAULT NOW(),
            updated_at          TIMESTAMP NOT NULL DEFAULT NOW(),
            created_by          INTEGER DEFAULT NULL,
            updated_by          INTEGER DEFAULT NULL,
            deleted_at          TIMESTAMP DEFAULT NULL,
            deleted_by          INTEGER DEFAULT NULL
        )
    ");
    echo "  Tabla automatizaciones creada.\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_automatizaciones_empresa  ON automatizaciones (id_empresa)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_automatizaciones_prox_ej  ON automatizaciones (proxima_ejecucion) WHERE estado = 'activo' AND eliminado = false");
    echo "  Índices de automatizaciones creados.\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS automatizaciones_log (
            id                  SERIAL PRIMARY KEY,
            id_automatizacion   INTEGER NOT NULL REFERENCES automatizaciones(id),
            id_empresa          INTEGER NOT NULL,
            iniciado_en         TIMESTAMP NOT NULL DEFAULT NOW(),
            finalizado_en       TIMESTAMP DEFAULT NULL,
            duracion_ms         INTEGER DEFAULT NULL,
            resultado           VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            registros_afectados INTEGER DEFAULT 0,
            mensaje             TEXT DEFAULT NULL,
            detalle_error       TEXT DEFAULT NULL,
            ejecutado_por       VARCHAR(30) DEFAULT 'cron'
        )
    ");
    echo "  Tabla automatizaciones_log creada.\n";

    $db->exec("CREATE INDEX IF NOT EXISTS idx_auto_log_automatizacion ON automatizaciones_log (id_automatizacion)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_auto_log_empresa        ON automatizaciones_log (id_empresa)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_auto_log_iniciado_en    ON automatizaciones_log (iniciado_en DESC)");
    echo "  Índices de automatizaciones_log creados.\n";

    echo "Migración completada: tablas automatizaciones y automatizaciones_log creadas.\n";
} catch (\Throwable $e) {
    echo "Error en migración: " . $e->getMessage() . "\n";
    exit(1);
}
