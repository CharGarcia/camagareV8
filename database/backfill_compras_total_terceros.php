<?php
/**
 * Backfill de compras_cabecera.total_terceros para las planillas ya cargadas.
 *
 * Las facturas de servicios básicos (luz, agua) recaudan por cuenta de terceros
 * rubros —contribución bomberos, tasa de recolección de basura— que NO están dentro
 * del <importeTotal> del comprobante pero sí se pagan. Desde el 27-08-2026 se
 * totalizan en compras_cabecera.total_terceros al registrar la compra; las compras
 * cargadas ANTES quedaron en 0 y su saldo por pagar sale corto en esos centavos.
 *
 * Por qué un script y no SQL puro: la detección vive en App\Helpers\RubrosTerceros
 * (patrones por nombre de campo, y el TOTAL declarado por el emisor manda sobre la
 * suma de rubros). Reescribir esa lógica en SQL la duplicaría y se desviaría del
 * comportamiento real del sistema. El desglose ya está en compras_adicional.
 *
 * NO destructivo: solo toca compras con total_terceros = 0 y un total detectado > 0;
 * nunca pisa un valor ya calculado ni modifica importe_total (que es el valor
 * declarado al SRI, base del ATS y de la declaración de IVA).
 *
 * Requiere que exista la columna: database/migrations/20260827_compras_total_terceros.sql
 *
 * Uso:
 *   php database/backfill_compras_total_terceros.php                 (simulación, todas)
 *   php database/backfill_compras_total_terceros.php --apply         (aplica)
 *   php database/backfill_compras_total_terceros.php --apply --empresa=8
 */

require __DIR__ . '/../bootstrap.php';

use App\core\Database;
use App\Helpers\RubrosTerceros;

$apply   = in_array('--apply', $argv, true);
$soloEmp = null;
foreach ($argv as $a) {
    if (preg_match('/^--empresa=(\d+)$/', $a, $m)) { $soloEmp = (int) $m[1]; }
}

$db = Database::getConnection();

$existe = $db->query(
    "SELECT 1 FROM information_schema.columns
      WHERE table_name = 'compras_cabecera' AND column_name = 'total_terceros'"
)->fetchColumn();
if (!$existe) {
    fwrite(STDERR, "La columna compras_cabecera.total_terceros no existe.\n"
                 . "Ejecute primero database/migrations/20260827_compras_total_terceros.sql\n");
    exit(1);
}

// Compras que aún no tienen total de terceros y sí tienen campos adicionales.
$sql = "SELECT c.id, c.id_empresa, c.importe_total,
               CONCAT(c.establecimiento_prov,'-',c.punto_emision_prov,'-',c.secuencial_prov) AS numero
        FROM compras_cabecera c
        WHERE c.eliminado = false
          AND COALESCE(c.total_terceros, 0) = 0
          AND EXISTS (SELECT 1 FROM compras_adicional a WHERE a.id_compra = c.id)";
if ($soloEmp !== null) { $sql .= " AND c.id_empresa = " . $soloEmp; }
$sql .= " ORDER BY c.id";

$compras = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$stAd  = $db->prepare("SELECT nombre, valor FROM compras_adicional WHERE id_compra = ?");
$stUpd = $db->prepare("UPDATE compras_cabecera SET total_terceros = ? WHERE id = ?");

$afectadas = 0;
$suma      = 0.0;

echo ($apply ? "APLICANDO" : "SIMULACIÓN (agregue --apply para escribir)"), "\n";
echo str_repeat('-', 78), "\n";

foreach ($compras as $c) {
    $stAd->execute([(int) $c['id']]);
    $campos = $stAd->fetchAll(PDO::FETCH_ASSOC);
    if (!$campos) { continue; }

    $total = RubrosTerceros::total($campos);
    if ($total <= 0) { continue; }

    $afectadas++;
    $suma += $total;
    printf("  empresa %-4s compra %-7s #%-20s importe %8.2f  ->  terceros %6.2f  (a pagar %8.2f)\n",
        $c['id_empresa'], $c['id'], $c['numero'],
        (float) $c['importe_total'], $total, (float) $c['importe_total'] + $total);

    if ($apply) { $stUpd->execute([round($total, 2), (int) $c['id']]); }
}

echo str_repeat('-', 78), "\n";
printf("Compras revisadas: %d | con valores de terceros: %d | total detectado: %.2f\n",
    count($compras), $afectadas, $suma);
echo $apply ? "Cambios aplicados.\n" : "Nada se escribió. Repita con --apply para aplicar.\n";
