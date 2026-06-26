<?php
/**
 * Migración: agrega la columna referencia_texto a asientos_programados.
 *
 * Permite reglas de dimensión cuya clave es TEXTO (no un id entero): el caso de la regla por
 * Producto en compras, que se contabiliza por el NOMBRE del ítem de compra (tipo_referencia
 * = 'item_compra', id_referencia NULL, referencia_texto = descripción del ítem).
 *
 * Ejecutar: php app/migrations/add_referencia_texto_to_asientos_programados.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\core\Database;

$db = Database::getConnection();
$db->exec("ALTER TABLE asientos_programados ADD COLUMN IF NOT EXISTS referencia_texto VARCHAR(500)");

echo "OK: columna referencia_texto agregada a asientos_programados.\n";
