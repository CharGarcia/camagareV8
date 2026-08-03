<?php
/**
 * Limpieza: fusiona el tipo de medida "CANTIDAD" duplicado dentro de "UNIDAD".
 *
 * Contexto: versiones antiguas del catálogo de medidas nombraban el tipo base como "UNIDAD";
 * una versión intermedia creó además un tipo "CANTIDAD" (misma categoría). Empresas sembradas
 * a través de ambas versiones quedaron con DOS unidades "UNIDAD" (una en cada tipo) → duplicado
 * en el selector. El creador actual ya no duplica; esto solo cura los datos existentes.
 *
 * Qué hace, SOLO en empresas que tienen a la vez un tipo "UNIDAD" y un tipo "CANTIDAD" (vivos):
 *   - Unidad del tipo CANTIDAD cuyo nombre YA existe en el tipo UNIDAD (p. ej. la propia UNIDAD):
 *     repunta todas las referencias (9 tablas) hacia la unidad del tipo UNIDAD y elimina la duplicada.
 *   - Unidad del tipo CANTIDAD que NO existe en el tipo UNIDAD (PAR/DOCENA/CIENTO/MILLAR):
 *     la MUEVE al tipo UNIDAD (no se pierde).
 *   - Repunta productos.id_tipo_medida de CANTIDAD → UNIDAD y elimina el tipo CANTIDAD (ya vacío).
 * Las empresas que solo tienen CANTIDAD (sin UNIDAD) NO se tocan (no quedarse sin unidad base).
 *
 * Uso:   php database/limpiar_unidad_cantidad_duplicada.php            (DRY-RUN: solo muestra)
 *        php database/limpiar_unidad_cantidad_duplicada.php --apply    (aplica los cambios)
 *        php database/limpiar_unidad_cantidad_duplicada.php --apply --empresa=8   (una empresa)
 * No borra físicamente: usa eliminado=true (soft-delete). Todo va en una transacción por empresa.
 */

require __DIR__ . '/../bootstrap.php';

use App\core\Database;

$apply     = in_array('--apply', $argv, true);
$idUsuario = 2; // deleted_by / updated_by
$soloEmp   = null;
foreach ($argv as $a) { if (preg_match('/^--empresa=(\d+)$/', $a, $m)) { $soloEmp = (int) $m[1]; } }

$pg = Database::getConnection();

/** Normaliza nombre de tipo/unidad: mayúsculas, sin acentos, sin espacios sobrantes. */
$norm = static function ($s): string {
    $s = strtr((string) $s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N','á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N']);
    return strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
};

// Columnas que referencian una UNIDAD (id de unidades_medida).
$refsUnidad = [
    ['productos', 'id_medida'],
    ['inventario_kardex', 'id_medida'],
    ['ventas_detalle', 'id_unidad_medida'],
    ['recibos_venta_detalle', 'id_unidad_medida'],
    ['proformas_detalle', 'id_unidad_medida'],
    ['importaciones_detalle', 'id_medida'],
    ['productos_componentes', 'id_medida'],
    ['proformas_plantillas_detalle', 'id_unidad_medida'],
];
// Solo cuenta las columnas/tablas que existan en este esquema.
$refsUnidad = array_values(array_filter($refsUnidad, function ($r) use ($pg) {
    $st = $pg->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?");
    $st->execute([$r[0], $r[1]]);
    return (bool) $st->fetchColumn();
}));

echo ($apply ? '== APLICANDO ==' : '== DRY-RUN (no cambia nada) ==') . PHP_EOL;

$sqlEmp = "SELECT DISTINCT id_empresa FROM tipo_medida WHERE eliminado = false" . ($soloEmp ? " AND id_empresa = " . (int) $soloEmp : "") . " ORDER BY id_empresa";
$empresas = $pg->query($sqlEmp)->fetchAll(PDO::FETCH_COLUMN);

$totEmp = 0; $totBorradas = 0; $totMovidas = 0; $totRepunt = 0;

foreach ($empresas as $e) {
    $e = (int) $e;
    // Tipos vivos de la empresa, indexados por nombre normalizado (primero por id).
    $tipos = [];
    $st = $pg->prepare("SELECT id, nombre FROM tipo_medida WHERE id_empresa = ? AND eliminado = false ORDER BY id");
    $st->execute([$e]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $n = $norm($t['nombre']);
        if (!isset($tipos[$n])) { $tipos[$n] = (int) $t['id']; }
    }
    $keeperType = $tipos['UNIDAD']   ?? null;
    $loserType  = $tipos['CANTIDAD'] ?? null;
    if (!$keeperType || !$loserType) { continue; } // solo el caso DUPLICADO (ambos)

    // Unidades vivas del tipo UNIDAD (keeper) por nombre normalizado.
    $keeperUnits = [];
    $st = $pg->prepare("SELECT id, nombre FROM unidades_medida WHERE id_empresa = ? AND id_tipo = ? AND eliminado = false ORDER BY es_base DESC, id");
    $st->execute([$e, $keeperType]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) { $keeperUnits[$norm($u['nombre'])] ??= (int) $u['id']; }

    // Unidades vivas del tipo CANTIDAD (loser).
    $st = $pg->prepare("SELECT id, nombre, es_base FROM unidades_medida WHERE id_empresa = ? AND id_tipo = ? AND eliminado = false ORDER BY id");
    $st->execute([$e, $loserType]);
    $loserUnits = $st->fetchAll(PDO::FETCH_ASSOC);

    $accBorrar = []; $accMover = [];
    foreach ($loserUnits as $u) {
        $n = $norm($u['nombre']);
        if (isset($keeperUnits[$n])) { $accBorrar[] = ['de' => (int) $u['id'], 'a' => $keeperUnits[$n], 'nom' => $u['nombre']]; }
        else                         { $accMover[]  = ['id' => (int) $u['id'], 'nom' => $u['nombre']]; }
    }

    $totEmp++;
    echo "Empresa $e: tipo UNIDAD=$keeperType, CANTIDAD=$loserType | fusionar " . count($accBorrar) . " dup, mover " . count($accMover) . PHP_EOL;
    foreach ($accBorrar as $a) { echo "   dup '{$a['nom']}' unidad {$a['de']} → {$a['a']} (repunta+elimina)" . PHP_EOL; }
    foreach ($accMover as $a)  { echo "   mover '{$a['nom']}' unidad {$a['id']} → tipo $keeperType" . PHP_EOL; }

    if (!$apply) { $totBorradas += count($accBorrar); $totMovidas += count($accMover); continue; }

    try {
        $pg->beginTransaction();
        // 1) Duplicadas: repuntar referencias y soft-delete.
        foreach ($accBorrar as $a) {
            foreach ($refsUnidad as [$tabla, $col]) {
                $u = $pg->prepare("UPDATE $tabla SET $col = ? WHERE $col = ?");
                $u->execute([$a['a'], $a['de']]);
                $totRepunt += $u->rowCount();
            }
            $pg->prepare("UPDATE unidades_medida SET eliminado = true, deleted_at = now(), deleted_by = ? WHERE id = ?")->execute([$idUsuario, $a['de']]);
        }
        // 2) No duplicadas: mover al tipo UNIDAD (no son base, UNIDAD ya es la base del keeper).
        foreach ($accMover as $a) {
            $pg->prepare("UPDATE unidades_medida SET id_tipo = ?, es_base = false, updated_at = now(), updated_by = ? WHERE id = ?")->execute([$keeperType, $idUsuario, $a['id']]);
        }
        // 3) Productos que apuntaban al tipo CANTIDAD → tipo UNIDAD.
        $pg->prepare("UPDATE productos SET id_tipo_medida = ?, updated_at = now(), updated_by = ? WHERE id_empresa = ? AND id_tipo_medida = ?")->execute([$keeperType, $idUsuario, $e, $loserType]);
        // 4) Eliminar el tipo CANTIDAD (ya sin unidades vivas propias).
        $pg->prepare("UPDATE tipo_medida SET eliminado = true, deleted_at = now(), deleted_by = ? WHERE id = ?")->execute([$idUsuario, $loserType]);
        $pg->commit();
        $totBorradas += count($accBorrar); $totMovidas += count($accMover);
    } catch (Throwable $ex) {
        if ($pg->inTransaction()) { $pg->rollBack(); }
        echo "   ERROR empresa $e: " . $ex->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "Resumen: empresas afectadas=$totEmp | unidades fusionadas=$totBorradas | unidades movidas=$totMovidas" . ($apply ? " | referencias repuntadas=$totRepunt" : " (dry-run)") . PHP_EOL;
echo $apply ? "Hecho." : "Dry-run. Vuelve a correr con --apply para aplicar." . PHP_EOL;
