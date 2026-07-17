<?php
/**
 * Diagnóstico: detecta módulos que redirigen a dashboard por DESALINEACIÓN
 * entre la ruta guardada en submodulos_menu y el getRutaModulo() del controlador.
 *
 * Causa raíz del bug "al dar clic en un módulo me manda al dashboard":
 * el menú navega a la ruta de submodulos_menu; el controlador resuelve por
 * toCamelCase (que perdona - y _), PERO el chequeo de permiso usa getRutaModulo()
 * y lo busca EXACTO en submodulos_menu. Si difieren (p.ej. guión medio vs bajo),
 * getIdSubmoduloPorRutaMvc() devuelve null y a los usuarios NO super admin les
 * niega el permiso -> redirect a /home/index -> dashboard.
 */
$ROOT = __DIR__;
require_once $ROOT . '/bootstrap.php';
$pdo = App\core\Database::getConnection();

// 1) Rutas del menú (submodulos_menu) que apuntan a módulos
$menu = [];
foreach ($pdo->query("SELECT id, nombre_submodulo, ruta, status FROM submodulos_menu WHERE ruta ILIKE 'modulos/%' OR ruta ILIKE '%/modulos/%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ruta = trim((string)$r['ruta']);
    $menu[$ruta][] = $r;
}

// 2) getRutaModulo() real de cada controlador de módulo
function extraerRutaModulo(string $file): ?string {
    $src = file_get_contents($file);
    if ($src === false) return null;
    // getRutaModulo() { ... return 'modulos/xxx'; }
    if (preg_match('/function\s+getRutaModulo\s*\([^)]*\)\s*:\s*string\s*\{(.*?)\}/s', $src, $m)) {
        if (preg_match("/return\s+'([^']+)'/", $m[1], $mm)) return $mm[1];
        // return self::RUTA_MODULO;
        if (preg_match('/return\s+self::RUTA_MODULO/', $m[1])) {
            if (preg_match("/RUTA_MODULO\s*=\s*'([^']+)'/", $src, $mc)) return $mc[1];
        }
    }
    if (preg_match("/RUTA_MODULO\s*=\s*'([^']+)'/", $src, $mc)) return $mc[1];
    return null;
}

// toCamelCase igual que el Router
function toCamel(string $s): string {
    if (str_contains($s, '-') || str_contains($s, '_')) {
        $s = str_replace(['-', '_'], ' ', strtolower($s));
        return lcfirst(str_replace(' ', '', ucwords($s)));
    }
    return $s;
}

$ctrlPorRuta = [];   // getRutaModulo => archivo
$ctrlPorClase = [];  // nombre base slug del archivo => getRutaModulo
foreach (glob("$ROOT/app/controllers/modulos/*Controller.php") as $f) {
    $ruta = extraerRutaModulo($f);
    if ($ruta !== null) {
        $ctrlPorRuta[$ruta] = basename($f);
    }
    $ctrlPorClase[basename($f)] = $ruta;
}

$permModel = new App\models\PermisoSubmodulo();

echo "================ AUDITORÍA DE RUTAS DE MÓDULOS ================\n\n";

$problemas = [];
$GLOBALS['cosmeticos'] = [];

// A) Por cada entrada de menú modulos/*: ¿el controlador existe? ¿getRutaModulo coincide?
echo "--- A) MENÚ (submodulos_menu) -> CONTROLADOR ---\n";
foreach ($menu as $rutaMenu => $rows) {
    // normalizar: quitar prefijo /sistema/ si viniera
    $slug = preg_replace('#^.*modulos/#', '', $rutaMenu);
    if ($slug === '' ) { // ruta 'modulos/' vacía
        foreach ($rows as $r) $problemas[] = "MENÚ vacío/genérico: id={$r['id']} '{$r['nombre_submodulo']}' ruta='{$rutaMenu}' (no apunta a ningún módulo)";
        continue;
    }
    $pascal = ucfirst(toCamel($slug));
    $ctrlFile = "$ROOT/app/controllers/modulos/{$pascal}Controller.php";
    $existe = file_exists($ctrlFile);
    $rutaCtrl = $existe ? extraerRutaModulo($ctrlFile) : null;
    $rutaMenuNorm = 'modulos/' . $slug;

    if (!$existe) {
        $problemas[] = "SIN CONTROLADOR: menú '{$rows[0]['nombre_submodulo']}' ruta='{$rutaMenu}' -> esperado {$pascal}Controller.php (no existe)";
        continue;
    }
    // Lo que realmente importa: ¿el permiso resuelve el submódulo? Si es null,
    // el usuario no-admin es enviado al dashboard.
    if ($rutaCtrl !== null) {
        $idSubPorCtrl = $permModel->getIdSubmoduloPorRutaMvc($rutaCtrl);
        if ($idSubPorCtrl === null) {
            $problemas[] = "ROTO (redirige a dashboard p/ no-admin): menú '{$rows[0]['nombre_submodulo']}'\n"
                . "        submodulos_menu.ruta = '{$rutaMenuNorm}'\n"
                . "        getRutaModulo()      = '{$rutaCtrl}'  ({$pascal}Controller)\n"
                . "        getIdSubmoduloPorRutaMvc('{$rutaCtrl}') = NULL  <- permiso NO resoluble";
        } elseif ($rutaCtrl !== $rutaMenuNorm) {
            // resuelve OK pese al desalineado -/_ : solo cosmético
            // (no se agrega a $problemas; se informa aparte)
            $GLOBALS['cosmeticos'][] = "COSMÉTICO (resuelve OK, id={$idSubPorCtrl}): '{$rutaMenuNorm}' (menú) vs '{$rutaCtrl}' (getRutaModulo) — {$pascal}Controller";
        }
    }
}

// B) Controladores cuyo getRutaModulo NO está en submodulos_menu (submódulo sin registrar)
echo "\n--- B) CONTROLADOR -> MENÚ (submódulo registrado?) ---\n";
$rutasMenuNorm = [];
foreach ($menu as $rutaMenu => $rows) {
    $slug = preg_replace('#^.*modulos/#', '', $rutaMenu);
    if ($slug !== '') $rutasMenuNorm['modulos/' . $slug] = true;
}
foreach ($ctrlPorRuta as $rutaCtrl => $file) {
    if (!isset($rutasMenuNorm[$rutaCtrl])) {
        $idSub = $permModel->getIdSubmoduloPorRutaMvc($rutaCtrl);
        if ($idSub === null) {
            $problemas[] = "SUBMÓDULO NO REGISTRADO: {$file} getRutaModulo()='{$rutaCtrl}' no está en submodulos_menu (no-admin: sin permiso -> dashboard)";
        }
    }
}

if (empty($problemas)) {
    echo "\n✅ Ningún módulo del menú redirige a dashboard por permisos.\n";
} else {
    echo "\n\n================ ROTOS / SIN CONTROLADOR (" . count($problemas) . ") ================\n\n";
    foreach ($problemas as $i => $p) {
        echo ($i + 1) . ") " . $p . "\n\n";
    }
}

if (!empty($GLOBALS['cosmeticos'])) {
    echo "\n---- Desalineaciones cosméticas -/_ (YA resuelven, no rompen) ----\n";
    foreach ($GLOBALS['cosmeticos'] as $c) echo "  · $c\n";
}
