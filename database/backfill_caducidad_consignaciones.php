<?php
/**
 * Backfill de fecha_caducidad en el DETALLE de las consignaciones de venta migradas,
 * para TODAS las empresas, tomando el dato autoritativo del sistema viejo
 * (detalle_consignacion.vencimiento). Corrige las consignaciones que se migraron
 * ANTES del fix de caducidad (fecha_caducidad quedó NULL).
 *
 * Por qué un script y no SQL puro: el vencimiento vive en MySQL (viejo) y hay que
 * escribirlo en PostgreSQL (nuevo). Este script corre en el servidor, que ve ambas.
 *
 * MATCH ROBUSTO (independiente del lote): casa cada línea nueva con la vieja por
 * (consignación migrada + código de producto). Solo usa el lote para desempatar
 * cuando un mismo producto aparece VARIAS veces en la misma consignación con lotes
 * distintos. Así corrige aunque el lote de la línea nueva no coincida con el viejo
 * (que es justo el caso que fallaba).
 *
 * Regla del cero: si el vencimiento viejo viene vacío/en cero, usa la fecha de la
 * consignación (mismo criterio que la migración).
 *
 * NO destructivo: por defecto solo rellena filas con fecha_caducidad IS NULL (no pisa
 * ediciones manuales). Con --sobrescribir fuerza el valor autoritativo del viejo.
 *
 * Uso:
 *   php database/backfill_caducidad_consignaciones.php                 (DRY-RUN, todas)
 *   php database/backfill_caducidad_consignaciones.php --apply         (aplica)
 *   php database/backfill_caducidad_consignaciones.php --apply --empresa=33
 *   php database/backfill_caducidad_consignaciones.php --apply --sobrescribir
 */

require __DIR__ . '/../bootstrap.php';

use App\core\Database;
use App\Services\MigracionMysql\LegacyMysqlConnection;

$apply        = in_array('--apply', $argv, true);
$sobrescribir = in_array('--sobrescribir', $argv, true);
$soloEmp      = null;
foreach ($argv as $a) { if (preg_match('/^--empresa=(\d+)$/', $a, $m)) { $soloEmp = (int) $m[1]; } }

$pg = Database::getConnection();
$my = LegacyMysqlConnection::get();

/** Normaliza a fecha corta 'Y-m-d'; null si vacía/basura. */
$fechaCorta = static function ($v): ?string {
    if ($v === null) { return null; }
    $s = trim((string) $v);
    if ($s === '' || strpos($s, '0000') === 0) { return null; }
    $d = substr($s, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
};
/** vencimiento, o la fecha del ítem si viene en cero (regla del cero). */
$caducidadODef = static function ($venc, $fechaItem) use ($fechaCorta): ?string {
    $c = $fechaCorta($venc);
    if ($c === null || $c < '2000-01-01') { $c = $fechaCorta($fechaItem); }
    return $c;
};

// Empresas con consignaciones migradas (insertadas por la migración).
$sqlEmp = "SELECT DISTINCT id_empresa FROM migracion_mysql_map WHERE entidad = 'consignaciones' AND vinculado IS NOT TRUE";
if ($soloEmp) { $sqlEmp .= " AND id_empresa = " . (int) $soloEmp; }
$empresas = $pg->query($sqlEmp)->fetchAll(PDO::FETCH_COLUMN);

echo ($apply ? '[APLICAR]' : '[DRY-RUN]') . ' backfill caducidad consignaciones — ' . count($empresas) . " empresa(s)" . ($sobrescribir ? ' (SOBRESCRIBIR)' : ' (solo NULL)') . "\n";

// Sentencias de actualización (por producto único / por lote).
$condNull = $sobrescribir ? '' : ' AND d.fecha_caducidad IS NULL';
$updProd = $pg->prepare("UPDATE consignaciones_ventas_detalles d SET fecha_caducidad = :cad, updated_at = now()
                          WHERE d.id_consignacion = :cons AND d.id_producto = :prod AND d.eliminado = false" . $condNull);
$updProdLote = $pg->prepare("UPDATE consignaciones_ventas_detalles d SET fecha_caducidad = :cad, updated_at = now()
                          WHERE d.id_consignacion = :cons AND d.id_producto = :prod AND COALESCE(d.lote,'') = COALESCE(:lote,'') AND d.eliminado = false" . $condNull);

$totalEmp = 0; $totalFilas = 0; $totalSinProd = 0; $totalSinCad = 0;

foreach ($empresas as $idEmpresa) {
    $idEmpresa = (int) $idEmpresa;

    // map: old id_consignacion -> new id_destino (solo insertadas por la migración)
    $mapDest = [];
    $q = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'consignaciones' AND vinculado IS NOT TRUE");
    $q->execute([$idEmpresa]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $mapDest[(int) $r['id_origen']] = (int) $r['id_destino']; }
    if (!$mapDest) { continue; }

    // productos nuevos por código (para resolver id_producto sin depender del lote)
    $prodPorCod = [];
    $qp = $pg->prepare("SELECT DISTINCT ON (codigo) codigo, id FROM productos WHERE id_empresa = ? ORDER BY codigo, id");
    $qp->execute([$idEmpresa]);
    foreach ($qp->fetchAll(PDO::FETCH_ASSOC) as $r) { $prodPorCod[(string) $r['codigo']] = (int) $r['id']; }

    // encabezados viejos (codigo_unico + fecha) de esas consignaciones
    $oldIds = array_keys($mapDest);
    $enc = [];
    foreach (array_chunk($oldIds, 500) as $chunk) {
        $in = implode(',', array_map('intval', $chunk));
        foreach ($my->query("SELECT id_consignacion, codigo_unico, fecha_consignacion FROM encabezado_consignacion WHERE id_consignacion IN ($in)") as $r) {
            $enc[(int) $r['id_consignacion']] = $r;
        }
    }

    $filasEmp = 0;
    foreach ($mapDest as $oldId => $newId) {
        if (!isset($enc[$oldId])) { continue; }
        $cu    = (string) $enc[$oldId]['codigo_unico'];
        $fecha = $enc[$oldId]['fecha_consignacion'];

        // detalle viejo de esa consignación
        $dst = $my->prepare("SELECT codigo_producto, lote, vencimiento FROM detalle_consignacion WHERE codigo_unico = :cu");
        $dst->execute([':cu' => $cu]);
        $lineas = $dst->fetchAll(PDO::FETCH_ASSOC);
        if (!$lineas) { continue; }

        // agrupar por id_producto resuelto
        $porProd = [];
        foreach ($lineas as $l) {
            $idProd = $prodPorCod[(string) $l['codigo_producto']] ?? null;
            if (!$idProd) { $totalSinProd++; continue; }
            $porProd[$idProd][] = $l;
        }

        foreach ($porProd as $idProd => $ls) {
            if (count($ls) === 1) {
                // producto único en la consignación → match SIN lote (robusto)
                $cad = $caducidadODef($ls[0]['vencimiento'], $fecha);
                if ($cad === null) { $totalSinCad++; continue; }
                if ($apply) { $updProd->execute([':cad' => $cad, ':cons' => $newId, ':prod' => $idProd]); $filasEmp += $updProd->rowCount(); }
                else { $filasEmp++; }
            } else {
                // producto repetido con lotes distintos → desempatar por lote
                foreach ($ls as $l) {
                    $cad = $caducidadODef($l['vencimiento'], $fecha);
                    if ($cad === null) { $totalSinCad++; continue; }
                    $lote = ($l['lote'] === null || trim((string) $l['lote']) === '') ? null : trim((string) $l['lote']);
                    if ($apply) { $updProdLote->execute([':cad' => $cad, ':cons' => $newId, ':prod' => $idProd, ':lote' => $lote]); $filasEmp += $updProdLote->rowCount(); }
                    else { $filasEmp++; }
                }
            }
        }
    }
    if ($filasEmp > 0) { echo "  empresa $idEmpresa: " . ($apply ? "$filasEmp fila(s) actualizadas" : "$filasEmp fila(s) candidatas") . "\n"; }
    $totalEmp++; $totalFilas += $filasEmp;
}

echo "\nTOTAL: " . ($apply ? "$totalFilas fila(s) actualizadas" : "$totalFilas fila(s) candidatas") . " en $totalEmp empresa(s)";
if ($totalSinProd) { echo " · $totalSinProd línea(s) sin producto resuelto (omitidas)"; }
if ($totalSinCad)  { echo " · $totalSinCad línea(s) sin fecha (ni vencimiento ni fecha de consignación)"; }
echo "\n";
if (!$apply) { echo "DRY-RUN: nada se escribió. Repite con --apply para aplicar.\n"; }
