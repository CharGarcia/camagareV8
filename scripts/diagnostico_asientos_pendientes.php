<?php
/**
 * Diagnóstico — ¿de dónde salen los «asientos pendientes» de una empresa?
 *
 * Uso:
 *   php scripts/diagnostico_asientos_pendientes.php <id_empresa>
 *
 * Muestra los períodos contables de la empresa y, por módulo y año, cuántos documentos cuenta
 * el aviso de asientos pendientes (los mismos SQL que SincronizadorAsientosService), y cuántos
 * quedaron fuera por estar en un período cerrado. Sirve para ver si lo que el aviso reclama
 * está en períodos abiertos o en fechas sin ningún período registrado.
 *
 * Es de SOLO LECTURA: no genera asientos ni modifica nada.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por línea de comandos (CLI).\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\core\Database;
use App\Services\modulos\SincronizadorAsientosService;

$idEmpresa = (int) ($argv[1] ?? 0);
if ($idEmpresa <= 0) {
    fwrite(STDERR, "Indique el id de la empresa: php scripts/diagnostico_asientos_pendientes.php <id_empresa>\n");
    exit(1);
}

$db = Database::getConnection();
$sinc = new SincronizadorAsientosService();

echo "== Períodos contables de la empresa {$idEmpresa}\n";
$st = $db->prepare("SELECT nombre, fecha_inicial, fecha_final, CASE status WHEN 0 THEN 'CERRADO' ELSE 'abierto' END AS estado
                      FROM periodos_contables WHERE id_empresa = ? AND eliminado = false ORDER BY fecha_inicial");
$st->execute([$idEmpresa]);
$periodos = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$periodos) {
    echo "   (ninguno: todas las fechas se consideran abiertas)\n";
}
foreach ($periodos as $p) {
    echo "   {$p['fecha_inicial']} a {$p['fecha_final']}  {$p['estado']}  {$p['nombre']}\n";
}

// Lista de trabajos y fecha de cada módulo: los mismos que usa el aviso.
$ref = new ReflectionClass($sinc);
$fechas = $ref->getReflectionConstant('FECHA_DOCUMENTO');
if ($fechas === false) {
    echo "\n!! Este servidor no tiene la exclusión por períodos cerrados (falta desplegar el cambio).\n";
    exit(0);
}
$fechas = $fechas->getValue();
$mExc = $ref->getMethod('construirExclusionMigracion');
$mExc->setAccessible(true);
$mTra = $ref->getMethod('construirTrabajos');
$mTra->setAccessible(true);
$trabajos = $mTra->invoke($sinc, $idEmpresa, $mExc->invoke($sinc, $db));

echo "\n== Pendientes que cuenta el aviso, por módulo y año\n";
$total = 0;
foreach ($trabajos as $t) {
    $expr = $fechas[$t['clave']] ?? null;
    $anio = $expr !== null ? "EXTRACT(YEAR FROM CAST({$expr} AS DATE))::int" : "NULL::int";
    try {
        $st = $db->prepare("SELECT {$anio} AS anio, COUNT(*) AS n
                              FROM ({$t['sql']}) AS _pc
                              JOIN {$t['tablaVerif']} _d ON _d.id = _pc.id
                             GROUP BY 1 ORDER BY 1");
        $st->execute($t['params']);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "   {$t['nombre']}: no se pudo consultar (" . substr($e->getMessage(), 0, 90) . ")\n";
        continue;
    }
    foreach ($filas as $f) {
        $total += (int) $f['n'];
        $a = $f['anio'] ?? ($expr === null ? 'sin fecha (no se filtra por período)' : 'sin fecha');
        echo "   " . str_pad($t['nombre'], 34) . " {$a}: {$f['n']}\n";
    }
}
echo "   TOTAL: {$total}  (contarPendientes = " . $sinc->contarPendientes($idEmpresa) . ")\n";

echo "\n== Sin asiento pero fuera del aviso por estar en un período cerrado\n";
$mCon = $ref->getMethod('conteoEnPeriodosCerrados');
$mCon->setAccessible(true);
$fuera = $mCon->invoke($sinc, $db, $trabajos);
if (!$fuera) {
    echo "   (ninguno)\n";
}
foreach ($fuera as $nombre => $n) {
    echo "   " . str_pad($nombre, 34) . " {$n}\n";
}
echo "\nEl aviso puede mostrar unos pocos menos que el TOTAL: descuenta los documentos sin valor que contabilizar.\n";
