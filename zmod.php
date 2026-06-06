<?php
$pdo = new PDO("pgsql:host=127.0.0.1;dbname=camagare_v8", "postgres", "CmGr1980");
$pdo->exec("
CREATE TABLE IF NOT EXISTS kushki_config (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER      NOT NULL UNIQUE,
    public_key  VARCHAR(300) NOT NULL,
    private_key VARCHAR(300) NOT NULL,
    ambiente    VARCHAR(20)  NOT NULL DEFAULT 'uat',
    moneda      VARCHAR(3)   NOT NULL DEFAULT 'USD',
    activo      BOOLEAN      NOT NULL DEFAULT true,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    updated_by  INTEGER,
    eliminado   BOOLEAN      NOT NULL DEFAULT false
)");
echo "Tabla kushki_config creada OK" . PHP_EOL;
// Registrar submódulo
$st = $pdo->prepare("INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
    SELECT 'Configuración Kushki', 'modulos/configuracion-kushki', 310, 2, 65, 1
    WHERE NOT EXISTS (SELECT 1 FROM submodulos_menu WHERE ruta='modulos/configuracion-kushki')");
$st->execute();
echo "Submódulo registrado OK" . PHP_EOL;
