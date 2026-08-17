<?php
/**
 * Diagnóstico: por qué una ruta MVC manda al dashboard a un usuario no-superadmin.
 *
 * El guard de todo módulo (BaseModuloController::requireLeer) resuelve el
 * id_submodulo de la ruta y busca ese id en modulos_asignados. Si no aparece,
 * redirige a /home/index (el dashboard) sin explicar el motivo. Este script
 * muestra cada paso de esa cadena para saber dónde se corta.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/diagnostico_permisos_modulo.php <id_usuario> <id_empresa> [ruta_mvc]
 *
 * Ejemplo:
 *   php scripts/diagnostico_permisos_modulo.php 9 1 modulos/inventario
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$idUsuario = (int) ($argv[1] ?? 0);
$idEmpresa = (int) ($argv[2] ?? 0);
$ruta      = trim((string) ($argv[3] ?? 'modulos/inventario'));

if ($idUsuario <= 0 || $idEmpresa <= 0) {
    fwrite(STDERR, "Uso: php scripts/diagnostico_permisos_modulo.php <id_usuario> <id_empresa> [ruta_mvc]\n");
    exit(1);
}

$db     = \App\core\Database::getConnection();
$model  = new \App\models\PermisoSubmodulo();
$normar = static function (string $r): string {
    $r = strtolower(trim($r));
    $r = str_replace(['../', './'], '', $r);
    $r = preg_replace('#^(sistema/)+#', '', $r);
    return str_replace('_', '-', ltrim($r, '/'));
};

echo "Ruta MVC .................. {$ruta}\n";

$cfg = require MVC_CONFIG . '/modulos_mvc.php';
echo "config/modulos_mvc.php .... " . (isset($cfg[$ruta]) ? json_encode($cfg[$ruta]) : 'NO REGISTRADA (revisar config/modulos_mvc.php)') . "\n";

// ── Submódulos de submodulos_menu cuya ruta se parece a la buscada ──────────
echo "\nsubmodulos_menu (coincidencias por ruta):\n";
$hay = false;
foreach ($db->query("SELECT id, nombre_submodulo, ruta, id_modulo, COALESCE(status,1) AS status FROM submodulos_menu ORDER BY id") as $r) {
    if ($normar((string) $r['ruta']) !== $normar($ruta)) {
        continue;
    }
    $hay = true;
    echo "  id={$r['id']}  {$r['nombre_submodulo']}  ruta={$r['ruta']}  id_modulo={$r['id_modulo']}  status={$r['status']}\n";
}
if (!$hay) {
    echo "  NINGUNA. El submódulo no está en el menú con esa ruta: el guard no puede\n";
    echo "  resolverlo y ningún usuario nivel 1-2 podrá entrar. Corregir submodulos_menu.ruta.\n";
}

// ── Id que realmente usa el guard ───────────────────────────────────────────
$idSub = $model->getIdSubmoduloPorRutaMvc($ruta);
echo "\nid_submodulo que usa el guard: " . var_export($idSub, true) . "\n";
if ($idSub === null) {
    echo "  Sin id resuelto => permiso 'ver' = false => redirección al dashboard.\n";
    exit(0);
}

// ── Permisos del usuario ────────────────────────────────────────────────────
$nivel = 0;
foreach ($db->query("SELECT nivel FROM usuarios WHERE id = {$idUsuario}") as $r) {
    $nivel = (int) $r['nivel'];
}
echo "\nUsuario {$idUsuario} (nivel {$nivel}) en empresa {$idEmpresa}:\n";
if ($nivel >= 3) {
    echo "  Nivel 3: acceso total, no depende de modulos_asignados.\n";
    exit(0);
}

$map = $model->getPermisosDeUsuario($idUsuario, $idEmpresa);
if (isset($map[$idSub])) {
    $p = $map[$idSub];
    echo "  Fila encontrada: " . json_encode($p) . "\n";
    echo empty($p['ver'])
        ? "  ver = 0 => redirección al dashboard. Marcar VER en /config/permisos-modulos.\n"
        : "  ver = 1 => el guard deja pasar. Si aun así redirige, revisar empresa activa en sesión.\n";
} else {
    echo "  SIN fila para id_submodulo={$idSub} => redirección al dashboard.\n";
    echo "  Asignar el submódulo en /config/permisos-modulos para esta empresa.\n";
}

echo "\n  Submódulos que sí tiene asignados en esta empresa:\n";
foreach ($map as $s => $p) {
    $rutaSub = '(sin fila en submodulos_menu)';
    foreach ($db->query("SELECT ruta FROM submodulos_menu WHERE id = " . (int) $s) as $r) {
        $rutaSub = (string) $r['ruta'];
    }
    printf("    %-5d %-42s %s\n", $s, $rutaSub, json_encode($p));
}
