<?php
/**
 * Migración: Registrar submódulo Cuentas por Pagar
 * Ejecución: php database/migrations/cuentas_por_pagar_submodulo_menu.php
 */
define('MVC_ROOT',   dirname(__DIR__, 2));
define('MVC_CONFIG', MVC_ROOT . '/config');

$c   = require MVC_CONFIG . '/database.php';
$pdo = new PDO(
    'pgsql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'],
    $c['user'],
    $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1. Verificar si ya existe
$existe = $pdo->query("SELECT id FROM submodulos_menu WHERE ruta = 'modulos/cuentas_por_pagar'")->fetchColumn();
if ($existe) {
    echo "El submódulo 'Cuentas por Pagar' ya existe con id={$existe}.\n";

    // Actualizar modulos_mvc.php con el id correcto
    echo "→ Verifica que config/modulos_mvc.php tenga 'id_submodulo' => {$existe}\n";
    exit;
}

// 2. Obtener id_modulo e id_icono del submódulo Cuentas por Cobrar (id=36 o buscar por ruta)
$refRow = $pdo->query(
    "SELECT id, id_modulo, id_icono FROM submodulos_menu WHERE ruta = 'modulos/cuentas_por_cobrar' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$refRow) {
    echo "ERROR: No se encontró 'modulos/cuentas_por_cobrar' en submodulos_menu.\n";
    echo "Por favor inserta el submódulo manualmente en el mismo módulo que desees.\n";
    exit(1);
}

$idModulo  = $refRow['id_modulo'];
$idIcono   = $refRow['id_icono'];
$idCxC     = $refRow['id'];

echo "Referencia CxC: id={$idCxC}, id_modulo={$idModulo}, id_icono={$idIcono}\n";

// 3. Calcular siguiente orden dentro del mismo módulo
$maxOrden = (int) $pdo->query(
    "SELECT COALESCE(MAX(orden), 0) FROM submodulos_menu WHERE id_modulo = {$idModulo}"
)->fetchColumn();

// 4. Insertar el submódulo
$st = $pdo->prepare(
    "INSERT INTO submodulos_menu (nombre_submodulo, ruta, id_modulo, orden, id_icono, status)
     VALUES (:nombre, :ruta, :id_modulo, :orden, :icono, 1)
     RETURNING id"
);
$st->execute([
    ':nombre'    => 'Cuentas por Pagar',
    ':ruta'      => 'modulos/cuentas_por_pagar',
    ':id_modulo' => $idModulo,
    ':orden'     => $maxOrden + 1,
    ':icono'     => $idIcono,
]);
$newId = (int) $st->fetchColumn();

echo "✅ Submódulo 'Cuentas por Pagar' creado con id={$newId}.\n";
echo "\n";
echo "⚠️  IMPORTANTE: Actualiza config/modulos_mvc.php:\n";
echo "    'modulos/cuentas_por_pagar' => ['id_submodulo' => {$newId}, 'legacy_rutas' => []],\n";
echo "\n";
echo "→ Asigna el submódulo a las empresas y usuarios desde el panel de administración.\n";
