<?php
// ============================================================================
// Diagnóstico de SOLO LECTURA del módulo Declaración de IVA (form 104 SRI).
// No ejecuta ningún INSERT, UPDATE, DELETE ni ALTER: únicamente SELECT.
//
// USO: subir a public/ y abrir en el navegador:
//   https://tu-dominio/diagnostico_iva.php?empresa=1&anio=2026&mes=05
// (sin parámetros usa empresa 1 y el mes anterior al actual)
//
// Rastrea la cadena completa del reporte y señala en qué eslabón se pierde
// la información del Resumen 104 y del Detalle de Casilleros.
// ============================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../bootstrap.php';

$idEmpresa = (int) ($_GET['empresa'] ?? 1);
$anio = (string) ($_GET['anio'] ?? date('Y', strtotime('first day of last month')));
$mes  = str_pad((string) ($_GET['mes'] ?? date('m', strtotime('first day of last month'))), 2, '0', STR_PAD_LEFT);
$desde = "{$anio}-{$mes}-01";
$hasta = date('Y-m-t', strtotime($desde));

function fila(string $msg, string $tipo = 'info'): void
{
    $color = ['ok' => 'green', 'error' => 'red', 'warn' => '#b8860b', 'info' => '#333'][$tipo];
    $ico = ['ok' => '&#10004;', 'error' => '&#10008;', 'warn' => '&#9888;', 'info' => '&#8226;'][$tipo];
    echo "<div style='color:{$color};font-family:monospace;margin:2px 0;'>{$ico} {$msg}</div>";
}
function titulo(string $t): void
{
    echo "<h4 style='font-family:Arial;margin:14px 0 4px'>{$t}</h4>";
}

echo "<h3 style='font-family:Arial'>Diagnóstico Declaración IVA — empresa {$idEmpresa}, período {$desde} a {$hasta}</h3>";
echo "<p style='font-family:Arial;font-size:0.85em;color:#666'>Script de solo lectura: no modifica nada en la base de datos.</p>";

$db = \App\core\Database::getConnection();
$veredicto = [];

// Helper: ¿existe la tabla?
$existeTabla = function (string $t) use ($db): bool {
    $st = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?");
    $st->execute([$t]);
    return (bool) $st->fetch();
};
// Helper: ¿existe la columna?
$existeCol = function (string $t, string $c) use ($db): bool {
    $st = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name = ? AND column_name = ?");
    $st->execute([$t, $c]);
    return (bool) $st->fetch();
};

// ── PASO 1: ambiente de la empresa ──────────────────────────────────────────
titulo("Paso 1 — Ambiente de la empresa (filtro global de todas las consultas)");
$amb = null;
try {
    $st = $db->prepare("SELECT tipo_ambiente FROM empresas WHERE id = ?");
    $st->execute([$idEmpresa]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        fila("La empresa {$idEmpresa} no existe", 'error');
        $veredicto[] = "La empresa {$idEmpresa} no existe en la tabla empresas.";
    } else {
        $amb = $row['tipo_ambiente'];
        if ($amb === null || $amb === '') {
            fila("empresas.tipo_ambiente es NULL/vacío: NINGUNA consulta del módulo devolverá filas", 'error');
            $veredicto[] = "tipo_ambiente NULL en la empresa: el filtro 'tipo_ambiente = (SELECT ... FROM empresas)' nunca coincide.";
        } else {
            fila("tipo_ambiente de la empresa: <b>{$amb}</b> (1=pruebas, 2=producción)", 'ok');
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 2: estructura del formulario (dibuja el Resumen 104) ───────────────
titulo("Paso 2 — Estructura del formulario 104 (sri_casilleros_etiquetas)");
$layoutOk = false;
try {
    if (!$existeTabla('sri_casilleros_etiquetas')) {
        fila("La tabla NO EXISTE: el módulo no puede dibujar el Resumen 104", 'error');
        $veredicto[] = "Falta la tabla sri_casilleros_etiquetas (en local tiene 32 filas).";
    } else {
        foreach (['id', 'casillero_bruto', 'casillero_neto', 'casillero_impuesto', 'formula_bruto', 'eliminado', 'seccion', 'orden'] as $c) {
            if (!$existeCol('sri_casilleros_etiquetas', $c)) {
                fila("Falta la columna <b>{$c}</b>: la consulta del módulo fallará", 'error');
                $veredicto[] = "Columna {$c} ausente en sri_casilleros_etiquetas.";
            }
        }
        $n = (int) $db->query("SELECT COUNT(*) FROM sri_casilleros_etiquetas WHERE eliminado = false")->fetchColumn();
        if ($n === 0) {
            fila("La tabla está VACÍA: el Resumen 104 se pinta en blanco aunque existan valores", 'error');
            $veredicto[] = "sri_casilleros_etiquetas sin filas activas (en local hay 32). Sin esto el Resumen 104 sale vacío SIEMPRE.";
        } else {
            fila("Filas activas: <b>{$n}</b> (en local: 32)", $n >= 30 ? 'ok' : 'warn');
            $layoutOk = true;
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 3: configuración de casilleros de la empresa ───────────────────────
titulo("Paso 3 — Mapeo tarifa→casillero de la empresa (empresa_casilleros_iva_sri)");
$configOk = false;
try {
    if (!$existeTabla('empresa_casilleros_iva_sri')) {
        fila("La tabla NO EXISTE: la sincronización no puede insertar nada", 'error');
        $veredicto[] = "Falta la tabla empresa_casilleros_iva_sri.";
    } else {
        $st = $db->prepare("SELECT tipo_documento,
                                   COUNT(*) AS total,
                                   COUNT(*) FILTER (WHERE COALESCE(casillero_bruto,'') <> '' OR COALESCE(casillero_neto,'') <> '' OR COALESCE(casillero_impuesto,'') <> '') AS con_casillero
                            FROM empresa_casilleros_iva_sri
                            WHERE id_empresa = ? AND eliminado = false
                            GROUP BY tipo_documento ORDER BY tipo_documento");
        $st->execute([$idEmpresa]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            fila("La empresa {$idEmpresa} NO tiene mapeo: sincronizarCasilleros() retorna sin insertar (falla silenciosa)", 'error');
            $veredicto[] = "empresa_casilleros_iva_sri sin filas para la empresa {$idEmpresa} (en local la empresa 1 tiene 70). Configurar en Configuración → Empresa, pestaña casilleros IVA.";
        } else {
            foreach ($rows as $r) {
                $t = $r['con_casillero'] > 0 ? 'ok' : 'warn';
                fila("<b>{$r['tipo_documento']}</b>: {$r['total']} filas, {$r['con_casillero']} con casillero asignado", $t);
            }
            $configOk = true;
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 4: catálogo de tarifas ──────────────────────────────────────────────
titulo("Paso 4 — Catálogo tarifa_iva (puente código SRI → configuración)");
try {
    $rows = $db->query("SELECT id, codigo FROM tarifa_iva ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        fila("tarifa_iva está VACÍA: ningún impuesto se puede mapear a casillero", 'error');
        $veredicto[] = "tarifa_iva sin filas: el mapa código→tarifa queda vacío y no se inserta ningún casillero.";
    } else {
        $lista = implode(', ', array_map(fn($r) => "id {$r['id']}=código {$r['codigo']}", $rows));
        fila("Tarifas: {$lista}", 'ok');
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 5: documentos del período, filtro por filtro ────────────────────────
titulo("Paso 5 — Documentos del período (lo que la sincronización seleccionaría)");
$origenes = [
    'ventas_cabecera'           => ["estado = 'autorizado'", 'Facturas de venta'],
    'compras_cabecera'          => ["COALESCE(deducible, '') = 'declaracion_iva'", 'Compras (deducible=declaracion_iva)'],
    'liquidaciones_cabecera'    => ["estado = 'autorizado'", 'Liquidaciones de compra'],
    'notas_credito_cabecera'    => ["estado = 'autorizado'", 'Notas de crédito'],
    'retencion_compra_cabecera' => ["estado = 'autorizado'", 'Retenciones en compras'],
    'retencion_venta_cabecera'  => ["TRUE", 'Retenciones en ventas (sin filtro de estado)'],
];
$docsSeleccionables = 0;
foreach ($origenes as $tabla => [$filtroEstado, $nombre]) {
    try {
        if (!$existeTabla($tabla)) {
            fila("<b>{$nombre}</b>: la tabla {$tabla} no existe", 'error');
            continue;
        }
        $tieneAmbiente = $existeCol($tabla, 'tipo_ambiente');
        $sqlBase = "SELECT
                COUNT(*) AS en_periodo,
                COUNT(*) FILTER (WHERE eliminado = false) AS no_eliminados,
                COUNT(*) FILTER (WHERE eliminado = false AND {$filtroEstado}) AS con_estado"
            . ($tieneAmbiente
                ? ", COUNT(*) FILTER (WHERE eliminado = false AND {$filtroEstado}
                       AND CAST(tipo_ambiente AS VARCHAR) = (SELECT CAST(tipo_ambiente AS VARCHAR) FROM empresas WHERE id = :emp2)) AS con_ambiente"
                : "")
            . " FROM {$tabla} WHERE id_empresa = :emp AND fecha_emision BETWEEN :d AND :h";
        $st = $db->prepare($sqlBase);
        $params = [':emp' => $idEmpresa, ':d' => $desde, ':h' => $hasta];
        if ($tieneAmbiente) $params[':emp2'] = $idEmpresa;
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        if (!$tieneAmbiente) {
            fila("<b>{$nombre}</b>: la columna tipo_ambiente NO EXISTE en {$tabla} → la consulta del módulo FALLARÁ con error SQL", 'error');
            $veredicto[] = "{$tabla} no tiene columna tipo_ambiente: getDocumentosPeriodo lanza error de SQL para ese origen.";
            continue;
        }
        $sel = (int) $r['con_ambiente'];
        $docsSeleccionables += $sel;
        $detalle = "{$r['en_periodo']} en el período → {$r['no_eliminados']} no eliminados → {$r['con_estado']} con estado válido → <b>{$sel} seleccionables</b>";
        if ((int) $r['en_periodo'] === 0) {
            fila("<b>{$nombre}</b>: sin documentos en el período (normal si no hubo movimientos)", 'info');
        } elseif ($sel === 0) {
            fila("<b>{$nombre}</b>: {$detalle} — los filtros descartan TODO", 'error');
            if ((int) $r['con_estado'] > 0) {
                $veredicto[] = "{$nombre}: hay {$r['con_estado']} documentos válidos pero su tipo_ambiente no coincide con el de la empresa ({$amb}).";
            }
        } else {
            fila("<b>{$nombre}</b>: {$detalle}", 'ok');
        }
    } catch (\Throwable $e) {
        fila("<b>{$nombre}</b>: error — " . $e->getMessage(), 'error');
    }
}

// ── PASO 6: tarifas usadas en ventas vs configuración ────────────────────────
titulo("Paso 6 — Tarifas de IVA usadas en las ventas del período vs mapeo configurado");
try {
    $sql = "SELECT i.codigo_porcentaje, COUNT(*) AS usos,
                   t.id AS id_tarifa,
                   c.casillero_bruto, c.casillero_neto, c.casillero_impuesto
            FROM ventas_cabecera v
            JOIN ventas_detalle d ON v.id = d.id_venta
            JOIN ventas_detalle_impuestos i ON d.id = i.id_venta_detalle
            LEFT JOIN tarifa_iva t ON CAST(t.codigo AS VARCHAR) = CAST(i.codigo_porcentaje AS VARCHAR)
            LEFT JOIN empresa_casilleros_iva_sri c ON c.id_empresa = v.id_empresa
                 AND c.tipo_documento = 'factura_venta' AND c.codigo = t.id AND c.eliminado = false
            WHERE v.id_empresa = ? AND v.eliminado = false AND v.estado = 'autorizado'
              AND v.fecha_emision BETWEEN ? AND ?
              AND CAST(i.codigo_impuesto AS VARCHAR) = '2'
            GROUP BY i.codigo_porcentaje, t.id, c.casillero_bruto, c.casillero_neto, c.casillero_impuesto
            ORDER BY i.codigo_porcentaje";
    $st = $db->prepare($sql);
    $st->execute([$idEmpresa, $desde, $hasta]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        fila("No hay líneas de IVA en facturas autorizadas del período (sin filtrar por ambiente)", 'warn');
    } else {
        foreach ($rows as $r) {
            if ($r['id_tarifa'] === null) {
                fila("Código porcentaje <b>{$r['codigo_porcentaje']}</b> ({$r['usos']} usos): NO existe en tarifa_iva → no se inserta casillero", 'error');
                $veredicto[] = "El código de porcentaje {$r['codigo_porcentaje']} usado en ventas no existe en tarifa_iva.";
            } elseif (trim((string)$r['casillero_bruto']) === '' && trim((string)$r['casillero_neto']) === '' && trim((string)$r['casillero_impuesto']) === '') {
                fila("Código porcentaje <b>{$r['codigo_porcentaje']}</b> ({$r['usos']} usos): sin casillero configurado para factura_venta → se omite", 'error');
                $veredicto[] = "La tarifa con código {$r['codigo_porcentaje']} no tiene casilleros asignados en la configuración de la empresa.";
            } else {
                fila("Código porcentaje <b>{$r['codigo_porcentaje']}</b> ({$r['usos']} usos) → bruto:{$r['casillero_bruto']} neto:{$r['casillero_neto']} imp:{$r['casillero_impuesto']}", 'ok');
            }
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 7: lo ya sincronizado en casilleros_declaracion_sri ─────────────────
titulo("Paso 7 — Valores ya sincronizados (casilleros_declaracion_sri) en el período");
$visibles = 0;
$totalSync = 0;
try {
    if (!$existeTabla('casilleros_declaracion_sri')) {
        fila("La tabla NO EXISTE", 'error');
        $veredicto[] = "Falta la tabla casilleros_declaracion_sri.";
    } else {
        $tieneFecha = $existeCol('casilleros_declaracion_sri', 'fecha');
        $tieneAmb = $existeCol('casilleros_declaracion_sri', 'tipo_ambiente');
        if (!$tieneFecha) {
            fila("Falta la columna <b>fecha</b>: el módulo no puede filtrar por período", 'error');
            $veredicto[] = "casilleros_declaracion_sri sin columna fecha.";
        }
        if (!$tieneAmb) {
            fila("Falta la columna <b>tipo_ambiente</b>: las consultas del módulo fallarán", 'error');
            $veredicto[] = "casilleros_declaracion_sri sin columna tipo_ambiente.";
        }
        if ($tieneFecha && $tieneAmb) {
            $st = $db->prepare("SELECT origen, COALESCE(tipo_ambiente,'NULL') AS amb, COUNT(*) AS n, ROUND(SUM(valor)::numeric, 2) AS suma
                                FROM casilleros_declaracion_sri
                                WHERE id_empresa = ? AND fecha BETWEEN ? AND ?
                                GROUP BY origen, tipo_ambiente ORDER BY origen");
            $st->execute([$idEmpresa, $desde, $hasta]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                fila("CERO filas sincronizadas en el período: el detalle mostrará 'No hay documentos sincronizados'", 'warn');
            } else {
                foreach ($rows as $r) {
                    $match = ((string) $r['amb'] === (string) $amb);
                    $totalSync += (int) $r['n'];
                    if ($match) $visibles += (int) $r['n'];
                    fila("{$r['origen']} (ambiente {$r['amb']}): {$r['n']} filas, suma {$r['suma']}" . ($match ? '' : ' — <b>NO visibles</b> (ambiente distinto al de la empresa)'), $match ? 'ok' : 'warn');
                }
            }
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── PASO 8: simulación del Resumen 104 (lo que vería el usuario) ─────────────
titulo("Paso 8 — Simulación del Resumen 104 (suma por casillero con filtro de ambiente)");
try {
    $st = $db->prepare("SELECT casillero, ROUND(SUM(valor)::numeric, 2) AS total
                        FROM casilleros_declaracion_sri
                        WHERE id_empresa = ? AND fecha BETWEEN ? AND ?
                          AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)
                        GROUP BY casillero ORDER BY casillero");
    $st->execute([$idEmpresa, $desde, $hasta, $idEmpresa]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        fila("La consulta del Resumen devuelve CERO filas: el formulario mostrará solo ceros", 'warn');
    } else {
        foreach ($rows as $r) {
            fila("Casillero <b>{$r['casillero']}</b>: {$r['total']}", 'ok');
        }
    }
} catch (\Throwable $e) {
    fila("Error: " . $e->getMessage(), 'error');
}

// ── VEREDICTO ─────────────────────────────────────────────────────────────────
echo "<h3 style='font-family:Arial'>Veredicto</h3>";
if ($totalSync === 0 && $docsSeleccionables > 0 && $configOk && $layoutOk) {
    $veredicto[] = "Hay {$docsSeleccionables} documentos seleccionables pero CERO sincronizados: este período nunca se sincronizó. "
        . "Pulsar GENERAR con la vista actualizada (envía sincronizar=1) poblará los casilleros. "
        . "OJO: la sincronización SÍ escribe en casilleros_declaracion_sri (es su función).";
}
if ($totalSync > 0 && $visibles === 0) {
    $veredicto[] = "Existen {$totalSync} valores sincronizados pero NINGUNO visible: su tipo_ambiente no coincide con el de la empresa ({$amb}).";
}
if (!$veredicto) {
    fila("<b>No se detectó ningún corte en la cadena. Si el módulo sigue vacío, revisar la respuesta JSON de generar-ajax (F12 → Red).</b>", 'ok');
} else {
    foreach (array_unique($veredicto) as $i => $v) {
        fila("<b>" . ($i + 1) . ".</b> {$v}", 'error');
    }
}
echo "<p style='font-family:Arial;font-size:0.8em;color:#999;margin-top:16px'>Eliminar este archivo del servidor al terminar el diagnóstico.</p>";
