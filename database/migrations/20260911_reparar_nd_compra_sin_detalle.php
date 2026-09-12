<?php
/**
 * Repara Notas de Débito de COMPRA importadas del SRI antes del 2026-09-11, que
 * se guardaron sin líneas de detalle ni impuestos (compras_detalle /
 * compras_detalle_impuestos vacíos) porque insertarCompra() reutilizaba la
 * lógica de facturas (<detalles><detalle>) para todos los tipos de documento,
 * y una Nota de Débito del SRI no trae esa estructura — trae
 * <motivos><motivo> (líneas), como HERMANO de <infoNotaDebito> a nivel de raíz
 * (NO anidado dentro de infoNotaDebito), e
 * <infoNotaDebito><impuestos><impuesto> (impuestos a nivel de cabecera).
 * El total de la cabecera (importe_total/total_sin_impuestos) siempre estuvo
 * bien, porque se toma directo del XML; lo que faltaba era el desglose.
 *
 * Este script reconstruye ese detalle a partir del XML ya guardado en
 * compras_cabecera.detalle_xml (el sobre <autorizacion> completo del SRI, tal
 * cual se recibió), con la MISMA lógica que ya usa el importador para las ND
 * nuevas desde el fix de hoy.
 *
 * Uso (desde la raíz del proyecto, en el servidor):
 *   php database/migrations/20260911_reparar_nd_compra_sin_detalle.php --dry-run
 *   php database/migrations/20260911_reparar_nd_compra_sin_detalle.php
 *   php database/migrations/20260911_reparar_nd_compra_sin_detalle.php --empresa=8
 *
 * --dry-run: solo diagnostica e imprime qué haría, no guarda nada (todo dentro
 *            de una transacción que se revierte al final de cada compra).
 * --empresa=N: limita la reparación a una sola empresa.
 *
 * Idempotente: solo toca compras con tipo_comprobante='05', eliminado=false,
 * detalle_xml no vacío, y CERO filas en compras_detalle — una vez reparada deja
 * de calificar, así que correrlo de nuevo no duplica nada.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\core\Database;

$dryRun = in_array('--dry-run', $argv, true);
$idEmpresaFiltro = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--empresa=')) {
        $idEmpresaFiltro = (int) substr($arg, strlen('--empresa='));
    }
}

/** Mismo desenvolvido del sobre <autorizacion> que usa ComprasService::parsearComprobanteXml(). */
function desenvolverXmlSri(string $xmlString): string
{
    $xmlString = trim($xmlString);
    if (strpos($xmlString, '<autorizacion>') !== false) {
        if (preg_match('/<comprobante>\s*<!\[CDATA\[(.*?)\]\]>\s*<\/comprobante>/s', $xmlString, $m)) {
            $xmlString = trim($m[1]);
        } elseif (preg_match('/<comprobante>(.*?)<\/comprobante>/s', $xmlString, $m)) {
            $xmlString = trim(htmlspecialchars_decode($m[1]));
        }
    }
    return $xmlString;
}

$db = Database::getConnection();

$sql = "SELECT c.id, c.id_empresa, c.detalle_xml
        FROM compras_cabecera c
        WHERE c.tipo_comprobante = '05'
          AND c.eliminado = false
          AND c.detalle_xml IS NOT NULL AND c.detalle_xml <> ''
          AND NOT EXISTS (SELECT 1 FROM compras_detalle d WHERE d.id_compra = c.id)";
$params = [];
if ($idEmpresaFiltro !== null) {
    $sql .= " AND c.id_empresa = ?";
    $params[] = $idEmpresaFiltro;
}
$st = $db->prepare($sql);
$st->execute($params);
$pendientes = $st->fetchAll(PDO::FETCH_ASSOC);

echo count($pendientes) . " Nota(s) de Débito sin detalle encontradas" . ($idEmpresaFiltro !== null ? " (empresa $idEmpresaFiltro)" : "") . ".\n";
if ($dryRun) {
    echo "Modo --dry-run: no se va a guardar nada.\n";
}
echo "\n";

$reparadas = 0;
$errores = 0;
$sinXmlValido = 0;

foreach ($pendientes as $row) {
    $idCompra = (int) $row['id'];
    try {
        $xmlString = desenvolverXmlSri((string) $row['detalle_xml']);
        $prevLibxml = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        libxml_use_internal_errors($prevLibxml);

        if ($xml === false) {
            echo "  [SIN XML VÁLIDO] compra #$idCompra: no se pudo parsear el XML guardado.\n";
            $sinXmlValido++;
            continue;
        }

        $info = $xml->infoNotaDebito ?? null;
        if ($info === null) {
            echo "  [SIN XML VÁLIDO] compra #$idCompra: el XML no tiene <infoNotaDebito>.\n";
            $sinXmlValido++;
            continue;
        }

        $db->beginTransaction();

        $primerDetalle = null;
        $lineas = 0;
        if (isset($xml->motivos->motivo)) {
            foreach ($xml->motivos->motivo as $m) {
                $valorMotivo = (float) $m->valor;
                $stDet = $db->prepare(
                    "INSERT INTO compras_detalle
                        (id_compra, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
                     VALUES (?, ?, 1, ?, 0, ?) RETURNING id"
                );
                $stDet->execute([$idCompra, (string) $m->razon, $valorMotivo, $valorMotivo]);
                $idDetalle = (int) $stDet->fetchColumn();
                if ($primerDetalle === null) {
                    $primerDetalle = $idDetalle;
                }
                $lineas++;
            }
        }

        $impuestosInsertados = 0;
        if ($primerDetalle !== null && isset($info->impuestos->impuesto)) {
            foreach ($info->impuestos->impuesto as $imp) {
                $db->prepare(
                    "INSERT INTO compras_detalle_impuestos
                        (id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $primerDetalle,
                    (string) $imp->codigo,
                    (string) $imp->codigoPorcentaje,
                    (float) $imp->tarifa,
                    (float) $imp->baseImponible,
                    (float) $imp->valor,
                ]);
                $impuestosInsertados++;
            }
        }

        // total_ice: mismo recálculo que hace el importador para documentos nuevos.
        $stIce = $db->prepare(
            "SELECT COALESCE(SUM(cdi.valor), 0) FROM compras_detalle_impuestos cdi
             JOIN compras_detalle cd ON cd.id = cdi.id_compra_detalle
             WHERE cd.id_compra = ? AND cdi.codigo_impuesto = '3'"
        );
        $stIce->execute([$idCompra]);
        $totalIce = (float) $stIce->fetchColumn();
        if ($totalIce > 0) {
            $db->prepare("UPDATE compras_cabecera SET total_ice = ? WHERE id = ?")
               ->execute([$totalIce, $idCompra]);
        }

        if ($dryRun) {
            $db->rollBack();
            echo "  [dry-run] compra #$idCompra: se crearían $lineas línea(s), $impuestosInsertados impuesto(s), ICE=$totalIce\n";
        } else {
            $db->commit();
            echo "  OK compra #$idCompra reparada: $lineas línea(s), $impuestosInsertados impuesto(s), ICE=$totalIce\n";
        }
        $reparadas++;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "  [ERROR] compra #$idCompra: " . $e->getMessage() . "\n";
        $errores++;
    }
}

echo "\n== Resumen ==\n";
echo "Reparadas: $reparadas\n";
echo "Sin XML válido (revisar a mano): $sinXmlValido\n";
echo "Con error: $errores\n";
if ($dryRun) {
    echo "\n(Modo --dry-run: nada se guardó. Corre sin --dry-run para aplicar.)\n";
}
