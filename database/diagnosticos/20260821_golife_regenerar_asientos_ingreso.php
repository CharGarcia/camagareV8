<?php
/**
 * GOLIFE (empresa 24) - Paso 2 de 2: regenerar los asientos de ingreso que quedaron con la MISMA
 * cuenta en el Debe y en el Haber (el banco contra si mismo).
 *
 * NO escribe SQL a mano: regenera a traves de IngresoService, que es quien sabe armar el asiento
 * (cartera + formas de cobro) y ya actualiza el asiento existente en vez de duplicarlo.
 *
 * Uso:
 *     php 20260821_golife_regenerar_asientos_ingreso.php              # simulacion, no escribe
 *     php 20260821_golife_regenerar_asientos_ingreso.php --aplicar    # escribe
 *
 * ORDEN: ejecutar DESPUES de configurar la regla General en GOLIFE y de correr
 * 20260821_golife_limpiar_cuenta_legada_conceptos.sql.
 *
 * GUARDIA: el script se niega a escribir si el asiento regenerado acreditaria una cuenta de
 * resultado (clase 4 o 5). Eso ocurria porque contrapartidaCarteraVentas() prorrateaba el monto
 * cobrado sobre TODAS las lineas del Debe de la factura, incluido el Costo de Ventas.
 * CORREGIDO el 21-08-2026 (soloLineasDeCartera): verificado contra los datos de produccion,
 * los 36 ingresos de GOLIFE ahora acreditan solo 1.1.2.01.001 CLIENTES NACIONALES y el desvio
 * paso de 484.53 a 0.00. El guardia se mantiene como red de seguridad: si vuelve a saltar, hay
 * un slot de cartera sin configurar en esa empresa.
 */

declare(strict_types=1);

require '/var/www/sistema/bootstrap.php';

const ID_EMPRESA = 24;

$aplicar = in_array('--aplicar', $argv, true);
$db = \App\core\Database::getConnection();

// ── 1. Detectar los asientos afectados (misma consulta del diagnostico, sin ids fijos) ──
$sql = "SELECT DISTINCT c.id            AS id_asiento,
                        c.id_referencia_origen AS id_ingreso,
                        i.numero_ingreso
          FROM asientos_contables_cabecera c
          JOIN ingresos_cabecera i ON i.id = c.id_referencia_origen
         WHERE c.modulo_origen = 'ingreso'
           AND c.id_empresa = " . ID_EMPRESA . "
           AND c.eliminado = false
           AND c.estado <> 'anulado'
           AND EXISTS (
                 SELECT 1
                   FROM asientos_contables_detalle d
                  WHERE d.id_asiento = c.id AND d.eliminado = false
                  GROUP BY d.id_cuenta_contable
                 HAVING SUM(d.debe) > 0 AND SUM(d.haber) > 0
               )
         ORDER BY i.numero_ingreso";
$afectados = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$afectados) {
    echo "No hay asientos de ingreso espejados en la empresa " . ID_EMPRESA . ". Nada que hacer.\n";
    exit(0);
}

echo "Asientos de ingreso espejados detectados: " . count($afectados) . "\n";
echo str_repeat('=', 96) . "\n";

// ── 2. Simular: que asiento saldria hoy, sin escribir ──
$builder   = new \App\Services\modulos\AsientoBuilderService();
$bloqueos  = [];
$listos    = [];

foreach ($afectados as $a) {
    $lineas = $builder->generarAsientoIngreso(ID_EMPRESA, (int) $a['id_ingreso']);
    echo "\n{$a['numero_ingreso']} (ingreso #{$a['id_ingreso']}, asiento #{$a['id_asiento']})\n";

    if (!$lineas) {
        echo "   [] el motor no arma asiento -> quedaria pendiente. Revise la configuracion.\n";
        $bloqueos[] = $a['numero_ingreso'] . ': el motor no arma asiento';
        continue;
    }

    $debe = 0.0; $haber = 0.0; $resultado = 0.0;
    foreach ($lineas as $l) {
        $cta = $db->query("SELECT codigo, nombre FROM plan_cuentas WHERE id = " . (int) $l['id_cuenta_contable'])
                  ->fetch(PDO::FETCH_ASSOC) ?: ['codigo' => '?', 'nombre' => '?'];
        printf("   %-16s %-34s debe=%8.2f haber=%8.2f  %s\n",
            $cta['codigo'], mb_substr((string) $cta['nombre'], 0, 34),
            (float) $l['debe'], (float) $l['haber'], (string) $l['referencia_detalle']);
        $debe  += (float) $l['debe'];
        $haber += (float) $l['haber'];
        // Acreditar una cuenta de resultado (4 = ingresos/descuentos, 5 = costo/gasto) en el
        // asiento de un COBRO es el sintoma del prorrateo sobre todo el Debe de la factura.
        if ((float) $l['haber'] > 0 && preg_match('/^[45]\./', (string) $cta['codigo'])) {
            $resultado += (float) $l['haber'];
        }
    }
    printf("   totales: debe=%.2f haber=%.2f\n", $debe, $haber);

    if (abs(round($debe - $haber, 2)) >= 0.01) {
        echo "   >> DESCUADRE: no se guardaria.\n";
        $bloqueos[] = $a['numero_ingreso'] . ': asiento descuadrado';
        continue;
    }
    if ($resultado > 0) {
        printf("   >> BLOQUEADO: acreditaria %.2f a cuenta(s) de resultado (costo/descuento).\n", $resultado);
        $bloqueos[] = sprintf('%s: acreditaria %.2f a resultado', $a['numero_ingreso'], $resultado);
        continue;
    }
    echo "   >> OK: cancela solo cartera.\n";
    $listos[] = $a;
}

// ── 3. Decidir ──
echo "\n" . str_repeat('=', 96) . "\n";
printf("Listos para regenerar: %d | Bloqueados: %d\n", count($listos), count($bloqueos));
foreach ($bloqueos as $b) {
    echo "   - $b\n";
}

if ($bloqueos) {
    echo "\nNO se escribe nada: hay asientos bloqueados.\n";
    echo "Corrija contrapartidaCarteraVentas() para que solo tome las cuentas del slot PORCOBRAR*\n";
    echo "de la cascada (ver memoria contrapartida-cartera-ventas-todo-el-debe) y vuelva a correr.\n";
    exit(1);
}

if (!$aplicar) {
    echo "\nSimulacion terminada. Para escribir: php " . basename(__FILE__) . " --aplicar\n";
    exit(0);
}

// ── 4. Aplicar ──
$service = new \App\Services\modulos\IngresoService(
    new \App\repositories\modulos\IngresoRepository(),
    new \App\Rules\modulos\IngresoRules(),
    new \App\Services\LogSistemaService()
);

$ok = 0; $fallos = 0;
foreach ($listos as $a) {
    try {
        // Regenera in-place: procesarAsientoContable() reutiliza el asiento existente del ingreso.
        $service->procesarAsientoContablePorSincronizacion((int) $a['id_ingreso']);
        echo "  OK  {$a['numero_ingreso']}\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "  ERROR {$a['numero_ingreso']}: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

printf("\nRegenerados: %d | Con error: %d\n", $ok, $fallos);
echo "Verifique con 20260821_ingresos_asiento_misma_cuenta_debe_haber.sql (consulta 1 debe dar 0 filas).\n";
