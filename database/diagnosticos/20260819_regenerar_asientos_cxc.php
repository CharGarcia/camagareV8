<?php

declare(strict_types=1);

/**
 * Regenera los asientos de Facturas y Recibos de Venta que quedaron con la Cuenta por Cobrar
 * apuntando a una cuenta que no es de activo (típicamente la cuenta de Ingresos/Ventas), por una
 * regla mal configurada en Configuración Contable a nivel Cliente/Producto/Categoría/Marca.
 *
 * PREREQUISITO: haber corregido antes la configuración con
 *   database/diagnosticos/20260819_reparar_cxc_ventas_cuenta_incorrecta.sql
 * Si se corre sin eso, el asiento se vuelve a generar igual de mal (lee la misma configuración).
 *
 * No hace UPDATE directo sobre asientos_contables_detalle: vuelve a armar el asiento entero por
 * el mismo camino que el botón Sincronizar (FacturaVentaService / ReciboVentaService →
 * procesarAsientoContablePorSincronizacion), que reemplaza el asiento existente del documento
 * (mismo id, mismo número de comprobante) en vez de crear uno nuevo, y deja auditoría.
 *
 * Los documentos cuyo período contable esté CERRADO fallarán con su mensaje: hay que reabrir el
 * período (o dejarlos y corregirlos por ajuste), el script los reporta al final sin detenerse.
 *
 * Uso (en el servidor, desde la raíz del proyecto):
 *   php database/diagnosticos/20260819_regenerar_asientos_cxc.php <id_empresa> <id_usuario>            # vista previa
 *   php database/diagnosticos/20260819_regenerar_asientos_cxc.php <id_empresa> <id_usuario> --aplicar  # ejecuta
 */

require __DIR__ . '/../../bootstrap.php';

$idEmpresa = isset($argv[1]) ? (int) $argv[1] : 0;
$idUsuario = isset($argv[2]) ? (int) $argv[2] : 0;
$aplicar   = in_array('--aplicar', $argv, true);

if ($idEmpresa <= 0 || $idUsuario <= 0) {
    fwrite(STDERR, "Uso: php " . basename(__FILE__) . " <id_empresa> <id_usuario> [--aplicar]\n");
    exit(1);
}

$pdo = \App\core\Database::getConnection();

// Documentos con un asiento vivo cuya línea de cartera (Debe) usa una cuenta que no es de activo.
$sql = "SELECT DISTINCT c.modulo_origen,
               c.id_referencia_origen AS id_documento,
               c.id                   AS id_asiento,
               c.fecha_asiento,
               pc.codigo              AS cuenta_mal,
               pc.nombre              AS cuenta_mal_nombre,
               d.debe
        FROM asientos_contables_cabecera c
        JOIN asientos_contables_detalle d ON d.id_asiento = c.id AND d.eliminado = false AND d.debe > 0
        JOIN plan_cuentas pc              ON pc.id = d.id_cuenta_contable
        WHERE c.id_empresa    = :emp
          AND c.eliminado     = false
          AND c.estado       <> 'anulado'
          AND c.modulo_origen IN ('factura_venta', 'recibo_venta')
          AND d.referencia_detalle ILIKE '%cobrar%'
          AND LEFT(pc.codigo, 1) <> '1'
        ORDER BY c.fecha_asiento, c.id_referencia_origen";
$st = $pdo->prepare($sql);
$st->execute([':emp' => $idEmpresa]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo "Empresa {$idEmpresa}: " . count($rows) . " documento(s) con la cartera en una cuenta que no es de activo.\n";
if (!$rows) {
    exit(0);
}

$totalMal = 0.0;
foreach ($rows as $r) {
    $totalMal += (float) $r['debe'];
    echo "  - {$r['modulo_origen']} #{$r['id_documento']} (asiento {$r['id_asiento']}, {$r['fecha_asiento']}): "
       . "{$r['cuenta_mal']} {$r['cuenta_mal_nombre']} \${$r['debe']}\n";
}
echo "Monto total mal imputado: \$" . number_format($totalMal, 2) . "\n";

// Aviso si la configuración todavía no está corregida: regenerar ahora no arreglaría nada.
$stCfg = $pdo->prepare(
    "SELECT count(*) FROM asientos_programados ap
       JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
       JOIN plan_cuentas pc  ON pc.id = ap.id_cuenta
      WHERE ap.id_empresa = :emp AND ap.eliminado = false
        AND at.codigo IN ('PORCOBRARFACTURAVENTA', 'PORCOBRARRECIBOVENTA')
        AND LEFT(pc.codigo, 1) <> '1'"
);
$stCfg->execute([':emp' => $idEmpresa]);
if ((int) $stCfg->fetchColumn() > 0) {
    fwrite(STDERR, "\nATENCIÓN: la empresa TODAVÍA tiene reglas de Cuenta por Cobrar apuntando a cuentas que no son de activo.\n"
                 . "Corrija primero la configuración (20260819_reparar_cxc_ventas_cuenta_incorrecta.sql); si regenera ahora,\n"
                 . "los asientos se volverán a armar con la misma cuenta equivocada.\n");
    exit(2);
}

if (!$aplicar) {
    echo "\nVista previa: no se aplicó ningún cambio. Repita con --aplicar para regenerar.\n";
    exit(0);
}

echo "\nRegenerando...\n";

$logService = new \App\Services\LogSistemaService();
$facturaService = new \App\Services\modulos\FacturaVentaService(
    new \App\repositories\modulos\FacturaVentaRepository(),
    new \App\Rules\modulos\FacturaVentaRules(),
    $logService
);
$reciboService = new \App\Services\modulos\ReciboVentaService(
    new \App\repositories\modulos\ReciboVentaRepository(),
    new \App\Rules\modulos\ReciboVentaRules(),
    $logService
);

$ok = 0;
$errores = [];
foreach ($rows as $r) {
    $idDoc = (int) $r['id_documento'];
    echo "  {$r['modulo_origen']} #{$idDoc}... ";
    try {
        if ($r['modulo_origen'] === 'factura_venta') {
            $facturaService->procesarAsientoContablePorSincronizacion($idDoc);
        } else {
            $reciboService->procesarAsientoContablePorSincronizacion($idDoc);
        }
        echo "OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errores[] = "{$r['modulo_origen']} #{$idDoc}: " . $e->getMessage();
    }
}

echo "\nResumen: {$ok} regenerado(s), " . count($errores) . " con error.\n";
foreach ($errores as $e) {
    echo "  ! {$e}\n";
}

// Verificación final con el mismo criterio de la búsqueda inicial.
$st->execute([':emp' => $idEmpresa]);
$pendientes = $st->fetchAll(PDO::FETCH_ASSOC);
echo "Quedan " . count($pendientes) . " documento(s) con la cartera mal imputada.\n";
