<?php
/**
 * Auditoría de rutas de módulos: detecta todo lo que hace que un usuario
 * nivel 1-2 no pueda entrar a un módulo (o pueda entrar sin permiso).
 *
 * Revisa los tres ejes que tienen que estar alineados:
 *   controlador (getRutaModulo)  ↔  submodulos_menu.ruta  ↔  config/modulos_mvc.php
 *
 * Los ids de submodulos_menu se generan por instalación, así que este script hay
 * que correrlo CONTRA CADA BASE (local y producción dan resultados distintos).
 *
 * Uso:  php scripts/auditoria_rutas_modulos.php
 *
 * Para diagnosticar un caso concreto (un usuario que no puede entrar a un módulo):
 *       php scripts/diagnostico_permisos_modulo.php <id_usuario> <id_empresa> <ruta>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$db    = \App\core\Database::getConnection();
$model = new \App\models\PermisoSubmodulo();

$norm = static function (string $r): string {
    $r = strtolower(trim($r));
    $r = str_replace(['../', './'], '', $r);
    $r = preg_replace('#^(sistema/)+#', '', ltrim($r, '/'));
    return str_replace('_', '-', ltrim((string) $r, '/'));
};

// ── submodulos_menu ─────────────────────────────────────────────────────────
$menu = [];
$porId = [];
$asignados = [];
foreach ($db->query("SELECT id, nombre_submodulo, ruta, id_modulo, COALESCE(status,1) AS status FROM submodulos_menu") as $r) {
    $menu[$norm((string) $r['ruta'])][(int) $r['id']] = $r;
    $porId[(int) $r['id']] = $r;
}
foreach ($db->query("SELECT id_submodulo, COUNT(*) AS n FROM modulos_asignados GROUP BY id_submodulo") as $r) {
    $asignados[(int) $r['id_submodulo']] = (int) $r['n'];
}

// ── getRutaModulo() y guard de cada controlador de módulo ───────────────────
$ctrl = [];
foreach (glob(MVC_APP . '/controllers/modulos/*Controller.php') ?: [] as $f) {
    $nombre = basename($f);
    if ($nombre === 'BaseModuloController.php') {
        continue;
    }
    $src = (string) file_get_contents($f);
    $ruta = null;
    if (preg_match('/function\s+getRutaModulo\s*\([^)]*\)\s*:\s*string\s*\{(.*?)\}/s', $src, $m)) {
        if (preg_match("/return\s+'([^']+)'/", $m[1], $mm)) {
            $ruta = $mm[1];
        } elseif (preg_match('/return\s+self::RUTA_MODULO/', $m[1]) && preg_match("/RUTA_MODULO\s*=\s*'([^']+)'/", $src, $mc)) {
            $ruta = $mc[1];
        }
    }
    if ($ruta === null && preg_match("/RUTA_MODULO\s*=\s*'([^']+)'/", $src, $mc)) {
        $ruta = $mc[1];
    }
    // Un controlador que no declara getRutaModulo() igual es alcanzable: el Router
    // resuelve la clase desde la ruta, así que su ruta efectiva es el nombre de la
    // clase en kebab-case (WhatsappChatController => modulos/whatsapp-chat).
    $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', substr($nombre, 0, -14)));
    $ctrl[$nombre] = [
        'ruta'          => $ruta,
        'ruta_efectiva' => $ruta ?? ('modulos/' . $slug),
        'con_guard'     => str_contains($src, 'extends BaseModuloController'),
    ];
}
ksort($ctrl);

$cfg = require MVC_CONFIG . '/modulos_mvc.php';
$hallazgos = [];
$ok = 0;

// ── 1. Controlador → menú → permiso ─────────────────────────────────────────
foreach ($ctrl as $archivo => $info) {
    $ruta = $info['ruta'];
    if ($ruta === null) {
        $enMenu = isset($menu[$norm($info['ruta_efectiva'])]);
        if ($info['con_guard']) {
            $hallazgos['CONTROLADOR SIN getRutaModulo()'][] = [$archivo, '', 'extiende BaseModuloController pero no declara su ruta'];
        } elseif ($enMenu) {
            $hallazgos['SIN VALIDACIÓN DE PERMISOS'][] = [$archivo, $info['ruta_efectiva'], 'está en el menú pero no extiende BaseModuloController: cualquier usuario con sesión entra, tenga o no el submódulo asignado'];
        }
        continue;
    }

    $filas = $menu[$norm($ruta)] ?? [];
    foreach (($cfg[$ruta]['legacy_rutas'] ?? []) as $l) {
        foreach ($menu[$norm((string) $l)] ?? [] as $id => $fx) {
            $filas[$id] = $fx;
        }
    }

    if (!$info['con_guard']) {
        $hallazgos['SIN VALIDACIÓN DE PERMISOS'][] = [$archivo, $ruta, 'no extiende BaseModuloController: cualquier usuario con sesión entra, tenga o no el submódulo asignado'];
        continue;
    }
    if (!$filas) {
        $hallazgos['SIN SUBMÓDULO EN EL MENÚ'][] = [$archivo, $ruta, 'ninguna fila de submodulos_menu con esa ruta => nivel 1-2 nunca puede entrar'];
        continue;
    }

    $ids = $model->getIdsSubmoduloPorRutaMvc($ruta);
    $activas = array_filter($filas, static fn($f) => (int) $f['status'] === 1);
    if (!$activas) {
        $hallazgos['SUBMÓDULO DESACTIVADO'][] = [$archivo, $ruta, 'ids ' . implode(', ', array_keys($filas)) . ' con status<>1: no aparece en el menú'];
        continue;
    }
    if (!$ids) {
        $hallazgos['GUARD NO RESUELVE'][] = [$archivo, $ruta, 'está en el menú (ids ' . implode(', ', array_keys($activas)) . ') pero el permiso no se puede resolver => dashboard'];
        continue;
    }
    $faltan = array_diff(array_keys($activas), $ids);
    if ($faltan) {
        $hallazgos['MENÚ FUERA DEL PERMISO'][] = [$archivo, $ruta, 'ids ' . implode(', ', $faltan) . ' abren el módulo pero su permiso no cuenta (el guard usa ' . implode(', ', $ids) . ')'];
        continue;
    }
    if (count($ids) > 1) {
        $hallazgos['RUTA DUPLICADA EN EL MENÚ'][] = [$archivo, $ruta, 'ids ' . implode(', ', $ids) . ' comparten la ruta; el permiso vale asignado en cualquiera'];
        continue;
    }
    if (!isset($cfg[$ruta])) {
        $hallazgos['FALTA EN config/modulos_mvc.php'][] = [$archivo, $ruta, 'funciona (resuelve por ruta, id=' . $ids[0] . ') pero la ruta no está registrada en el config'];
        continue;
    }
    $ok++;
}

// ── 2. Menú → controlador ───────────────────────────────────────────────────
foreach ($menu as $n => $filas) {
    if (!str_starts_with($n, 'modulos')) {
        continue;
    }
    foreach ($ctrl as $info) {
        if ($norm($info['ruta_efectiva']) === $n) {
            continue 2;
        }
    }
    foreach ($filas as $id => $f) {
        $uso = ($asignados[$id] ?? 0) > 0 ? " (asignado a {$asignados[$id]} usuario/s)" : ' (sin asignaciones)';
        $hallazgos['MENÚ SIN CONTROLADOR'][] = ["id={$id} {$f['nombre_submodulo']}", (string) $f['ruta'], 'no existe controlador para esa ruta: al abrirlo da error 404' . $uso];
    }
}

// ── Reporte ─────────────────────────────────────────────────────────────────
$total = 0;
foreach ($hallazgos as $lista) {
    $total += count($lista);
}
echo "Controladores de módulo: " . count($ctrl) . "   sin hallazgos: {$ok}   hallazgos: {$total}\n\n";

$orden = [
    'GUARD NO RESUELVE', 'MENÚ FUERA DEL PERMISO', 'SIN SUBMÓDULO EN EL MENÚ',
    'SUBMÓDULO DESACTIVADO', 'SIN VALIDACIÓN DE PERMISOS', 'MENÚ SIN CONTROLADOR',
    'CONTROLADOR SIN getRutaModulo()', 'RUTA DUPLICADA EN EL MENÚ', 'FALTA EN config/modulos_mvc.php',
];
foreach ($orden as $tipo) {
    if (empty($hallazgos[$tipo])) {
        continue;
    }
    echo "== {$tipo} (" . count($hallazgos[$tipo]) . ") ==\n";
    foreach ($hallazgos[$tipo] as [$quien, $ruta, $detalle]) {
        printf("   %-38s %-38s %s\n", $quien, $ruta, $detalle);
    }
    echo "\n";
}
if ($total === 0) {
    echo "Sin hallazgos.\n";
}
