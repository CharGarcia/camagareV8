<?php
/**
 * Migración: Crear tabla empresa_documentos y carpeta storage
 */
define('ROOT_PATH', __DIR__ . '/../..');
require ROOT_PATH . '/bootstrap.php';

$db = App\core\Database::getConnection();

$sql = "CREATE TABLE IF NOT EXISTS empresa_documentos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_empresa INT(11) NOT NULL,
    tipo_documento VARCHAR(50) NOT NULL DEFAULT 'otro' COMMENT 'contrato, ruc, licencia, otro',
    descripcion VARCHAR(255) DEFAULT NULL,
    nombre_archivo VARCHAR(255) NOT NULL COMMENT 'nombre guardado en disco',
    nombre_original VARCHAR(255) NOT NULL COMMENT 'nombre original del archivo',
    fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_empresa (id_empresa),
    CONSTRAINT fk_empresa_doc_empresa FOREIGN KEY (id_empresa) REFERENCES empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

$db->query($sql);
echo "Tabla empresa_documentos creada.\n";

$dir = ROOT_PATH . '/storage/empresa_documentos';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Carpeta storage/empresa_documentos creada.\n";
} else {
    echo "Carpeta storage/empresa_documentos ya existe.\n";
}

echo "Migración completada.\n";
