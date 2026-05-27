<?php
define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');
$c   = require MVC_CONFIG . '/database.php';
$pdo = new PDO('pgsql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'], $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("
CREATE TABLE IF NOT EXISTS citas_tipos_recursos (
    id          SERIAL PRIMARY KEY,
    id_empresa  INTEGER NOT NULL,
    id_tipo     INTEGER NOT NULL REFERENCES citas_tipos(id),
    id_recurso  INTEGER NOT NULL REFERENCES citas_recursos(id),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by  INTEGER,
    UNIQUE(id_tipo, id_recurso)
);
CREATE INDEX IF NOT EXISTS idx_ctr_tipo    ON citas_tipos_recursos(id_tipo);
CREATE INDEX IF NOT EXISTS idx_ctr_recurso ON citas_tipos_recursos(id_recurso);
CREATE INDEX IF NOT EXISTS idx_ctr_empresa ON citas_tipos_recursos(id_empresa);
");
echo "OK\n";
