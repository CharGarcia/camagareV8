<?php
/**
 * marcar_cheques_cobrados.php — Corrección puntual de conciliación bancaria en lo YA migrado.
 *
 * Para TODAS las empresas migradas (con ingresos/egresos en migracion_mysql_map):
 *   1) Fija tipo_operacion_bancaria = 'CHEQUE' en todo pago con número de cheque
 *      (corrige los cheques que se migraron como TRANSFERENCIA/DEPOSITO por la letra
 *       de detalle_pago; así un cheque GIRADO no afecta el saldo).
 *   2) Para los cheques COBRADOS en el viejo (formas_pagos_ing_egr.estado_pago='PAGADO'),
 *      crea la fila en control_bancario_movimientos (fecha_banco = fecha_pago) para que
 *      el cheque SÍ afecte el saldo bancario, igual que en el sistema anterior.
 *
 * Necesita acceso al MySQL viejo (igual que la migración). Idempotente: se puede correr
 * varias veces (no duplica movimientos, no re-etiqueta lo ya correcto). NO toca cabeceras
 * ni detalles: solo pagos-cheque y sus movimientos de banco.
 *
 * Uso:  "C:/xampp/php/php.exe" database/marcar_cheques_cobrados.php
 *   (en el servidor:  php database/marcar_cheques_cobrados.php )
 */

require_once __DIR__ . '/../bootstrap.php';

use App\core\Database;
use App\Services\MigracionMysql\LegacyMysqlConnection;

/** Fecha 'Y-m-d' o null si viene vacía/0000/inválida. */
function _fechaCorta($v): ?string
{
    $s = trim((string) $v);
    if ($s === '' || strpos($s, '0000') === 0) { return null; }
    $d = substr($s, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}

$pg = Database::getConnection();
$my = LegacyMysqlConnection::get();

$idUsuario = (int) ($pg->query("SELECT id FROM usuarios WHERE nivel = 3 AND estado = 1 ORDER BY id LIMIT 1")->fetchColumn() ?: 1);

$empresas = $pg->query(
    "SELECT DISTINCT m.id_empresa, e.ruc, COALESCE(NULLIF(e.nombre_comercial,''), e.nombre) AS nombre
       FROM migracion_mysql_map m JOIN empresas e ON e.id = m.id_empresa
      WHERE m.entidad IN ('ingresos','egresos') ORDER BY m.id_empresa"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Empresas migradas a procesar: " . count($empresas) . "\n";

$totCbm = 0; $totTipo = 0; $errores = 0;

// origen_tipo, entidad(map), tabla_pago, col_doc, col_forma, tabla_cabecera, direccion, tipo_viejo
$config = [
    ['ingreso', 'ingresos', 'ingresos_pagos', 'id_ingreso', 'id_forma_cobro', 'ingresos_cabecera', 'RECIBIDO', 'INGRESO'],
    ['egreso',  'egresos',  'egresos_pagos',  'id_egreso',  'id_forma_pago',  'egresos_cabecera',  'EMITIDO',  'EGRESO'],
];

foreach ($empresas as $emp) {
    $idEmpresa = (int) $emp['id_empresa'];
    $base = substr(preg_replace('/\D/', '', (string) $emp['ruc']), 0, 10);
    if ($base === '') { continue; }
    $cbmEmp = 0; $tipoEmp = 0;

    try {
        $pg->beginTransaction();

        foreach ($config as $cfg) {
            [$origenTipo, $entidad, $tablaPago, $colDoc, $colForma, $tablaCab, $direccion, $tipoOld] = $cfg;

            // 1) Fijar tipo='CHEQUE' en todo pago con número de cheque (idempotente).
            $up = $pg->prepare("UPDATE $tablaPago p SET tipo_operacion_bancaria = 'CHEQUE'
                                 WHERE COALESCE(p.numero_cheque,'') <> ''
                                   AND COALESCE(p.tipo_operacion_bancaria,'') <> 'CHEQUE'
                                   AND p.$colDoc IN (SELECT id FROM $tablaCab WHERE id_empresa = ?)");
            $up->execute([$idEmpresa]);
            $tipoEmp += $up->rowCount();

            // 2) Mapa old doc -> new doc.
            $map = [];
            $qm = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
            $qm->execute([$idEmpresa, $entidad]);
            foreach ($qm->fetchAll(PDO::FETCH_ASSOC) as $r) { $map[(int) $r['id_origen']] = (int) $r['id_destino']; }
            if (!$map) { continue; }

            // old id -> codigo_documento
            $oldCod = [];
            foreach ($my->query("SELECT id_ing_egr, codigo_documento FROM ingresos_egresos WHERE LEFT(ruc_empresa,10) = " . $my->quote($base) . " AND tipo_ing_egr = '$tipoOld'") as $r) {
                $oldCod[(int) $r['id_ing_egr']] = (string) $r['codigo_documento'];
            }
            // new doc id -> codigo
            $newToCod = [];
            foreach ($map as $oldId => $newId) {
                $cod = $oldCod[$oldId] ?? '';
                if ($cod !== '') { $newToCod[$newId] = $cod; }
            }
            if (!$newToCod) { continue; }

            // Cheques COBRADOS del viejo, agrupados por codigo_documento.
            $chqByCod = [];
            foreach ($my->query("SELECT codigo_documento, cheque, valor_forma_pago, estado_pago, fecha_pago, fecha_entrega
                                   FROM formas_pagos_ing_egr
                                  WHERE tipo_documento = '$tipoOld' AND cheque > 0 AND id_cuenta > 0 AND estado_pago = 'PAGADO'
                                    AND LEFT(ruc_empresa,10) = " . $my->quote($base)) as $r) {
                $chqByCod[(string) $r['codigo_documento']][] = $r;
            }
            if (!$chqByCod) { continue; }

            // Pagos-cheque nuevos por documento.
            $qp = $pg->prepare("SELECT p.id, p.$colDoc AS iddoc, p.$colForma AS idforma, p.monto, p.numero_cheque
                                  FROM $tablaPago p JOIN $tablaCab c ON c.id = p.$colDoc
                                 WHERE c.id_empresa = ? AND COALESCE(p.numero_cheque,'') <> ''");
            $qp->execute([$idEmpresa]);
            $payByDoc = [];
            foreach ($qp->fetchAll(PDO::FETCH_ASSOC) as $r) { $payByDoc[(int) $r['iddoc']][] = $r; }

            $cbmExiste = $pg->prepare("SELECT 1 FROM control_bancario_movimientos WHERE origen_tipo = ? AND origen_id = ? AND eliminado = false LIMIT 1");
            $insCbm    = $pg->prepare("INSERT INTO control_bancario_movimientos (id_empresa, id_forma_pago, tipo_transaccion, cheque_direccion, numero_cheque, fecha_cheque, fecha_banco, origen_tipo, origen_id, eliminado, created_at, updated_at, created_by) VALUES (?, ?, 'CHEQUE', ?, ?, ?, ?, ?, ?, false, now(), now(), ?)");

            foreach ($payByDoc as $newId => $pays) {
                $cod = $newToCod[$newId] ?? null;
                if ($cod === null) { continue; }
                $oldChqs = $chqByCod[$cod] ?? [];
                if (!$oldChqs) { continue; }
                $usados = [];
                foreach ($oldChqs as $oc) {
                    $fBanco = _fechaCorta($oc['fecha_pago']);
                    if ($fBanco === null) { continue; }
                    $numChq = (string) (int) $oc['cheque'];
                    $valor  = round((float) $oc['valor_forma_pago'], 2);
                    foreach ($pays as $ix => $p) {
                        if (isset($usados[$ix])) { continue; }
                        if ((string) $p['numero_cheque'] === $numChq && round((float) $p['monto'], 2) === $valor) {
                            $cbmExiste->execute([$origenTipo, (int) $p['id']]);
                            if ($cbmExiste->fetchColumn() === false) {
                                $insCbm->execute([$idEmpresa, (int) $p['idforma'], $direccion, $numChq, _fechaCorta($oc['fecha_entrega']) ?: $fBanco, $fBanco, $origenTipo, (int) $p['id'], $idUsuario]);
                                $cbmEmp++;
                            }
                            $usados[$ix] = true;
                            break;
                        }
                    }
                }
            }
        }

        $pg->commit();
        $totCbm += $cbmEmp; $totTipo += $tipoEmp;
        if ($cbmEmp > 0 || $tipoEmp > 0) {
            echo "  [$idEmpresa] " . substr((string) $emp['nombre'], 0, 40) . ": cheques cobrados marcados=$cbmEmp, tipo corregido=$tipoEmp\n";
        }
    } catch (Throwable $ex) {
        if ($pg->inTransaction()) { $pg->rollBack(); }
        $errores++;
        echo "  [$idEmpresa] ERROR: " . substr($ex->getMessage(), 0, 160) . "\n";
    }
}

echo "\nLISTO. Cheques cobrados marcados (control_bancario_movimientos): $totCbm | tipos corregidos a CHEQUE: $totTipo | empresas con error: $errores\n";
