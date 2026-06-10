<?php
// Diagnóstico del módulo Declaración de IVA (form 104 SRI).
// Subir al servidor y abrir en el navegador. Compara la BD contra lo que el
// módulo necesita y muestra el veredicto de por qué no carga la información.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../bootstrap.php';
session_start();

$idEmpresa = (int) ($_SESSION['id_empresa'] ?? ($_GET['empresa'] ?? 1));

function linea(string $msg, string $tipo = 'info'): void
{
    $color = ['ok' => 'green', 'error' => 'red', 'warn' => '#b8860b', 'info' => '#333'][$tipo] ?? '#333';
    $icono = ['ok' => '&#10004;', 'error' => '&#10008;', 'warn' => '&#9888;', 'info' => '&#8226;'][$tipo] ?? '';
    echo "<div style='color:{$color};font-family:monospace;margin:2px 0;'>{$icono} {$msg}</div>";
}

echo "<h3 style='font-family:Arial'>Diagnóstico Declaración IVA — empresa {$idEmpresa}</h3>";

$problemas = [];

try {
    $db = \App\core\Database::getConnection();
    linea("Conexión a la base de datos OK", 'ok');

    // ── 1. Existencia de tablas clave ──────────────────────────────────────
    $tablas = [
        'sri_casilleros_etiquetas'   => 'estructura del formulario 104 (global)',
        'empresa_casilleros_iva_sri' => 'mapeo tarifa→casillero por empresa',
        'casilleros_declaracion_sri' => 'valores sincronizados por documento',
        'tarifa_iva'                 => 'catálogo de tarifas de IVA',
        'ventas_cabecera'            => 'facturas de venta',
        'empresas'                   => 'empresas',
    ];
    foreach ($tablas as $t => $desc) {
        $st = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = ?");
        $st->execute([$t]);
        if ($st->fetch()) {
            linea("Tabla <b>{$t}</b> existe ({$desc})", 'ok');
        } else {
            linea("Tabla <b>{$t}</b> NO EXISTE ({$desc})", 'error');
            $problemas[] = "Falta la tabla {$t}. " . ($t === 'sri_casilleros_etiquetas'
                ? "Ejecutar database/seed_sri_casilleros_etiquetas.sql"
                : ($t === 'empresa_casilleros_iva_sri' ? "Ejecutar database/seed_empresa_casilleros_iva_sri.sql" : "Revisar migraciones."));
        }
    }

    // ── 2. Estructura del formulario 104 (debe tener ~32 filas) ───────────
    echo "<h4 style='font-family:Arial'>Estructura del formulario (sri_casilleros_etiquetas)</h4>";
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM sri_casilleros_etiquetas WHERE eliminado = false")->fetchColumn();
        if ($n > 0) {
            linea("Etiquetas activas: <b>{$n}</b> (en local hay 32)", $n >= 30 ? 'ok' : 'warn');
        } else {
            linea("La tabla está VACÍA: el Resumen 104 se pintará en blanco aunque haya valores", 'error');
            $problemas[] = "sri_casilleros_etiquetas sin datos → ejecutar database/seed_sri_casilleros_etiquetas.sql";
        }
        // Columnas que el código necesita
        foreach (['id', 'casillero_bruto', 'casillero_neto', 'casillero_impuesto', 'formula_bruto', 'eliminado'] as $col) {
            $st = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = 'sri_casilleros_etiquetas' AND column_name = ?");
            $st->execute([$col]);
            if (!$st->fetch()) {
                linea("Falta la columna <b>{$col}</b> en sri_casilleros_etiquetas", 'error');
                $problemas[] = "Columna {$col} ausente en sri_casilleros_etiquetas (el auto-parche del controlador debería crearla; revisar permisos del usuario de BD).";
            }
        }
    } catch (\Throwable $e) {
        linea("Error consultando sri_casilleros_etiquetas: " . $e->getMessage(), 'error');
    }

    // ── 3. Configuración de casilleros de la empresa ───────────────────────
    echo "<h4 style='font-family:Arial'>Configuración de casilleros de la empresa (empresa_casilleros_iva_sri)</h4>";
    try {
        $st = $db->prepare("SELECT tipo_documento, COUNT(*) AS n FROM empresa_casilleros_iva_sri WHERE id_empresa = ? AND eliminado = false GROUP BY tipo_documento ORDER BY tipo_documento");
        $st->execute([$idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            linea("La empresa {$idEmpresa} NO tiene mapeo tarifa→casillero: la sincronización no inserta NADA (falla silenciosa)", 'error');
            $problemas[] = "empresa_casilleros_iva_sri sin filas para la empresa {$idEmpresa} → ejecutar database/seed_empresa_casilleros_iva_sri.sql o configurar en Configuración → Empresa.";
        } else {
            foreach ($rows as $r) {
                linea("Tipo <b>{$r['tipo_documento']}</b>: {$r['n']} filas", 'ok');
            }
        }
    } catch (\Throwable $e) {
        linea("Error consultando empresa_casilleros_iva_sri: " . $e->getMessage(), 'error');
    }

    // ── 4. tipo_ambiente: empresa vs documentos vs casilleros ──────────────
    echo "<h4 style='font-family:Arial'>Tipo de ambiente</h4>";
    try {
        $st = $db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ?");
        $st->execute([$idEmpresa]);
        $amb = $st->fetchColumn();
        if ($amb === false || $amb === null) {
            linea("empresas.tipo_ambiente es NULL: NINGUNA consulta del módulo devolverá filas", 'error');
            $problemas[] = "Definir tipo_ambiente en la tabla empresas (1=pruebas, 2=producción).";
        } else {
            linea("Ambiente de la empresa: <b>{$amb}</b> (1=pruebas, 2=producción)", 'ok');
        }

        $st = $db->prepare("SELECT COALESCE(CAST(tipo_ambiente AS VARCHAR), 'NULL') AS amb, COUNT(*) AS n FROM ventas_cabecera WHERE id_empresa = ? AND eliminado = false GROUP BY tipo_ambiente");
        $st->execute([$idEmpresa]);
        $coincide = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $match = ((string) $r['amb'] === (string) $amb);
            if ($match) $coincide += (int) $r['n'];
            linea("Ventas con tipo_ambiente={$r['amb']}: {$r['n']}" . ($match ? ' (coinciden con la empresa)' : ' (NO se tomarán en cuenta)'), $match ? 'ok' : 'warn');
        }
        if ($coincide === 0) {
            linea("Ninguna factura coincide con el ambiente de la empresa: la sincronización seleccionará 0 documentos", 'error');
            $problemas[] = "Las ventas tienen tipo_ambiente distinto al de la empresa (o NULL). Igualarlos o corregir el ambiente de la empresa.";
        }

        $st = $db->prepare("SELECT COALESCE(tipo_ambiente, 'NULL') AS amb, COUNT(*) AS n FROM casilleros_declaracion_sri WHERE id_empresa = ? GROUP BY tipo_ambiente");
        $st->execute([$idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            linea("casilleros_declaracion_sri sin filas para la empresa (normal si nunca se ha sincronizado con éxito)", 'warn');
        } else {
            foreach ($rows as $r) {
                $match = ((string) $r['amb'] === (string) $amb);
                linea("Casilleros sincronizados con tipo_ambiente={$r['amb']}: {$r['n']}" . ($match ? '' : ' (NO se mostrarán)'), $match ? 'ok' : 'warn');
            }
        }
    } catch (\Throwable $e) {
        linea("Error verificando tipo_ambiente: " . $e->getMessage(), 'error');
    }

    // ── 5. Catálogo tarifa_iva (el mapeo depende de él) ────────────────────
    echo "<h4 style='font-family:Arial'>Catálogo tarifa_iva</h4>";
    try {
        $n = (int) $db->query("SELECT COUNT(*) FROM tarifa_iva")->fetchColumn();
        linea("Filas en tarifa_iva: <b>{$n}</b>", $n > 0 ? 'ok' : 'error');
        if ($n === 0) {
            $problemas[] = "tarifa_iva vacía: el mapeo código SRI → tarifa no resuelve y no se insertan casilleros.";
        }
    } catch (\Throwable $e) {
        linea("Error consultando tarifa_iva: " . $e->getMessage(), 'error');
    }

    // ── 6. Permisos de ALTER (el módulo auto-parcha columnas) ──────────────
    echo "<h4 style='font-family:Arial'>Permisos de ALTER TABLE</h4>";
    try {
        $db->exec("ALTER TABLE casilleros_declaracion_sri ADD COLUMN IF NOT EXISTS tipo_ambiente VARCHAR(1) DEFAULT '1'");
        linea("El usuario de BD puede ejecutar ALTER TABLE (auto-parche funcional)", 'ok');
    } catch (\Throwable $e) {
        linea("ALTER TABLE falla: " . $e->getMessage(), 'error');
        $problemas[] = "El usuario de BD no puede alterar tablas; el auto-parche del módulo falla. Ejecutar los ALTER manualmente como dueño de la BD.";
    }

    // ── Veredicto ───────────────────────────────────────────────────────────
    echo "<h4 style='font-family:Arial'>Veredicto</h4>";
    if (!$problemas) {
        linea("<b>No se detectaron problemas. Probar GENERAR en el módulo y revisar la respuesta de generar-ajax en la pestaña Red del navegador.</b>", 'ok');
    } else {
        foreach ($problemas as $i => $p) {
            linea("<b>" . ($i + 1) . ".</b> {$p}", 'error');
        }
    }

} catch (\Throwable $e) {
    linea("<b>ERROR FATAL:</b> " . $e->getMessage() . " — " . $e->getFile() . ":" . $e->getLine(), 'error');
}
