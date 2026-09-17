<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use App\core\Database;
use PDO;
use Throwable;

/**
 * Importa la CONFIGURACIÓN CONTABLE (reglas generales de `asientos_programados`) del sistema viejo
 * al nuevo, con revisión previa del usuario.
 *
 * Alcance: SOLO reglas GENERALES (las que en el viejo tienen `id_asiento_tipo > 0`: ventas,
 * compras_servicios, RECIBOS, inventarios, rol_pagos). Las reglas por entidad (proveedor, cliente,
 * opciones de cobro/pago, IVA, retenciones…) quedan fuera a propósito.
 *
 * Flujo: `previsualizar()` arma la tabla comparativa (concepto → cuenta vieja → cuenta nueva) sin
 * tocar nada; el usuario elige cuáles aplicar y `aplicar()` guarda solo esas.
 *
 * PREREQUISITO: haber migrado el Plan de cuentas (la cuenta destino se resuelve por su código en
 * formato de la casa; ver MigracionMysqlService::codigoCasa).
 */
class MigracionConfigContableService
{
    /**
     * Equivalencias slot VIEJO (asientos_tipo.codigo) → slot NUEVO (asientos_tipo.codigo).
     * Validado con el usuario. Lo que no está aquí se muestra como "sin equivalente" (omitir).
     */
    private const MAPA_SLOTS = [
        // Ventas → ventas_factura
        'CCXCC'    => 'PORCOBRARFACTURAVENTA',
        'CCSV'     => 'SUBTOTALFACTURAVENTA',
        'CCOV'     => 'PROPINAFACTURAVENTA',
        // Compras/servicios → adquisiciones_compras
        'CCXPP'    => 'PORPAGARFACTURACOMPRA',
        'CCCGP'    => 'SUBTOTALFACTURACOMPRA',
        'CCOP'     => 'PROPINAFACTURACOMPRA',
        // Recibos → recibos_venta
        'CCXRC'    => 'PORCOBRARRECIBOVENTA',
        'CCSVR'    => 'SUBTOTALRECIBOVENTA',
        'CCOVR'    => 'PROPINARECIBOVENTA',
        // Inventarios → se reparten entre compras y ventas
        'CCXPPI'   => 'PORPAGARFACTURACOMPRA',
        'CCGAI'    => 'INVENTARIOFACTURACOMPRA',
        'CCCVI'    => 'COSTOFACTURAVENTA',
        // Rol de pagos → nomina
        'CCXCGS'   => 'GASTOSUELDOSNOMINA',
        'CCXAPA'   => 'GASTOAPORTEPATRONALNOMINA',
        'CCXS14'   => 'GASTODECIMOCUARTONOMINA',
        'CCXS13'   => 'GASTODECIMOTERCERONOMINA',
        'CCXVA'    => 'GASTOVACACIONESNOMINA',
        'CCXFR'    => 'GASTOFONDOSRESERVANOMINA',
        'CCXCGD'   => 'GASTODESAHUCIONOMINA',
        'CCXS13PP' => 'DECIMOTERCEROPORPAGARNOMINA',
        'CCXS14PP' => 'DECIMOCUARTOPORPAGARNOMINA',
        'CCXVAPP'  => 'VACACIONESPORPAGARNOMINA',
        'CCXFRPP'  => 'FONDOSRESERVAPORPAGARNOMINA',
        'CCXDPP'   => 'DESAHUCIOPORPAGARNOMINA',
        'CCXAPE'   => 'IESSPORPAGARNOMINA',
        'CCXAS'    => 'ANTICIPOSDESCUENTOSNOMINA',
        'CCXRPP'   => 'SUELDOSPORPAGARNOMINA', // SUELDO POR PAGAR → Sueldos por Pagar (antes mapeaba a BANCOSNOMINA/líquido a pagar; ahora el rol mensual se contabiliza en base devengado y esta es la cuenta que de verdad representa "sueldo devengado, aún no pagado")
    ];

    /**
     * Arma la tabla comparativa. NO escribe nada.
     *
     * @return array{filas: array<int,array>, resumen: array{total:int,listas:int,sin_slot:int,sin_cuenta:int,ya:int}}
     */
    public function previsualizar(int $idEmpresa, string $ruc): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        // Slots nuevos por código, y plan de cuentas nuevo por código de la casa.
        // tipo_cuenta viaja para poder descartar las equivalencias cuya cuenta destino tiene una
        // naturaleza incompatible con el concepto (ver estado 'incompatible' más abajo).
        $slotPorCodigo = [];
        foreach ($pg->query("SELECT id, tipo_asiento, referencia, codigo, tipo_cuenta FROM asientos_tipo WHERE eliminado = false") as $r) {
            $slotPorCodigo[(string) $r['codigo']] = $r;
        }
        $ctaPorCodigo = [];
        $q = $pg->prepare("SELECT id, codigo, nombre FROM plan_cuentas WHERE id_empresa = ? AND eliminado = false");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $ctaPorCodigo[(string) $r['codigo']] = $r; }

        // Reglas ya configuradas en el nuevo (para avisar que se sobreescribirían).
        $yaConfig = [];
        $q2 = $pg->prepare(
            "SELECT at.codigo FROM asientos_programados ap
               JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
              WHERE ap.id_empresa = ? AND ap.eliminado = false
                AND ap.id_referencia = at.id
                AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)"
        );
        $q2->execute([$idEmpresa]);
        foreach ($q2->fetchAll(PDO::FETCH_COLUMN) as $c) { $yaConfig[(string) $c] = true; }

        // Reglas GENERALES del viejo (id_asiento_tipo > 0).
        $sql = "SELECT ap.id_asi_pro, ap.tipo_asiento, at.concepto_cuenta, at.codigo AS cod_viejo,
                       pc.codigo_cuenta, pc.nombre_cuenta
                  FROM asientos_programados ap
                  JOIN asientos_tipo at ON at.id_asiento_tipo = ap.id_asiento_tipo
                  LEFT JOIN plan_cuentas pc ON pc.id_cuenta = ap.id_cuenta
                 WHERE LEFT(ap.ruc_empresa, 10) = " . $mysql->quote($base) . " AND ap.id_asiento_tipo > 0
                 ORDER BY ap.tipo_asiento, at.id_asiento_tipo";

        $filas = [];
        $res   = ['total' => 0, 'listas' => 0, 'sin_slot' => 0, 'sin_cuenta' => 0, 'ya' => 0, 'incompatible' => 0];

        foreach ($mysql->query($sql) as $r) {
            $res['total']++;
            $codViejo = trim((string) $r['cod_viejo']);
            $codNuevo = self::MAPA_SLOTS[$codViejo] ?? null;
            $slot     = $codNuevo !== null ? ($slotPorCodigo[$codNuevo] ?? null) : null;

            $codCtaVieja = trim((string) ($r['codigo_cuenta'] ?? ''));
            $codCasa     = $codCtaVieja !== '' ? MigracionMysqlService::codigoCasaPublico($codCtaVieja) : '';
            $cta         = $codCasa !== '' ? ($ctaPorCodigo[$codCasa] ?? null) : null;

            // Naturaleza incompatible: la cuenta del sistema viejo existe en el plan nuevo, pero es
            // de una clase que ese concepto no admite (p. ej. una cuenta de Ventas para la Cuenta
            // por Cobrar). Importarla dejaría TODAS las facturas de la empresa mal contabilizadas,
            // igual que ocurrió configurándola a mano — así que se muestra y no se puede marcar.
            $incompatible = $slot !== null && $cta !== null
                && !\App\Services\modulos\AsientoProgramadoService::cuentaCompatible(
                    (string) ($slot['tipo_cuenta'] ?? ''), (string) $cta['codigo']
                );

            if ($slot === null)      { $estado = 'sin_slot';   $res['sin_slot']++; }
            elseif ($cta === null)   { $estado = 'sin_cuenta'; $res['sin_cuenta']++; }
            elseif ($incompatible)   { $estado = 'incompatible'; $res['incompatible']++; }
            else {
                $estado = isset($yaConfig[$codNuevo]) ? 'ya_configurada' : 'lista';
                if ($estado === 'ya_configurada') { $res['ya']++; } else { $res['listas']++; }
            }

            $filas[] = [
                'tipo_viejo'     => (string) $r['tipo_asiento'],
                'concepto_viejo' => (string) $r['concepto_cuenta'],
                'cod_viejo'      => $codViejo,
                'cuenta_vieja'   => $codCtaVieja . ' · ' . (string) ($r['nombre_cuenta'] ?? ''),
                'slot_nuevo'     => $slot !== null ? ($slot['tipo_asiento'] . ' · ' . $slot['referencia']) : null,
                'id_asiento_tipo'=> $slot !== null ? (int) $slot['id'] : null,
                'cuenta_nueva'   => $cta !== null ? ($cta['codigo'] . ' · ' . $cta['nombre']) : null,
                // Código que se buscó en el plan nuevo: permite explicar al usuario qué cuenta falta.
                'cod_casa'       => $codCasa,
                // Solo en estado 'incompatible': qué naturaleza esperaba el concepto destino.
                'tipo_cuenta'    => $incompatible ? (string) ($slot['tipo_cuenta'] ?? '') : null,
                'id_cuenta'      => $cta !== null ? (int) $cta['id'] : null,
                'estado'         => $estado,
            ];
        }

        return ['filas' => $filas, 'resumen' => $res];
    }

    /**
     * Guarda SOLO las reglas que el usuario marcó. Cada elemento de $seleccion es
     * ['id_asiento_tipo' => int, 'id_cuenta' => int]. Idempotente: si ya existe la regla general
     * para ese slot, se actualiza la cuenta; si no, se inserta.
     */
    public function aplicar(int $idEmpresa, int $idUsuario, array $seleccion): array
    {
        $pg  = Database::getConnection();
        $res = ['aplicadas' => 0, 'actualizadas' => 0, 'errores' => 0, 'descartadas' => 0, 'motivos' => []];

        // Naturaleza declarada de cada concepto, para rechazar aquí también lo que la
        // previsualización marca como 'incompatible': esta selección llega por POST y no tiene por
        // qué coincidir con lo que se mostró en pantalla.
        $tipoCuentaPorSlot = [];
        foreach ($pg->query("SELECT id, referencia, COALESCE(tipo_cuenta, '') AS tipo_cuenta FROM asientos_tipo WHERE eliminado = false") as $r) {
            $tipoCuentaPorSlot[(int) $r['id']] = ['referencia' => (string) $r['referencia'], 'tipo_cuenta' => (string) $r['tipo_cuenta']];
        }
        $codigoCuenta = $pg->prepare("SELECT codigo FROM plan_cuentas WHERE id = ? AND id_empresa = ? AND eliminado = false LIMIT 1");

        // La regla general vive con tipo_referencia = 'asientos tipo' O con el nombre del tipo de
        // asiento ('ventas_factura', …): el lector acepta las dos formas (ver
        // AsientoProgramadoRepository::getReglasGeneralesPorConcepto), así que aquí hay que
        // buscarlas igual. Mirando solo 'asientos tipo' se insertaba una SEGUNDA regla general
        // para un concepto que ya la tenía en el otro formato, y el motor entonces leía dos filas
        // del mismo concepto y duplicaba su línea en todos los asientos de la empresa.
        $buscar = $pg->prepare(
            "SELECT ap.id FROM asientos_programados ap
               JOIN asientos_tipo at ON at.id = ap.id_asiento_tipo
              WHERE ap.id_empresa = ? AND ap.id_asiento_tipo = ? AND ap.id_referencia = ?
                AND (ap.tipo_referencia = 'asientos tipo' OR ap.tipo_referencia = at.tipo_asiento)
                AND ap.eliminado = false LIMIT 1"
        );
        $upd = $pg->prepare("UPDATE asientos_programados SET id_cuenta = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $ins = $pg->prepare(
            "INSERT INTO asientos_programados (id_empresa, id_usuario, id_asiento_tipo, id_cuenta, id_referencia, tipo_referencia, eliminado, created_by)
             VALUES (?, ?, ?, ?, ?, 'asientos tipo', false, ?)"
        );

        foreach ($seleccion as $s) {
            $idTipo   = (int) ($s['id_asiento_tipo'] ?? 0);
            $idCuenta = (int) ($s['id_cuenta'] ?? 0);
            if ($idTipo <= 0 || $idCuenta <= 0) { $res['errores']++; continue; }

            // Naturaleza incompatible → no se importa. Es preferible dejar el concepto sin cuenta
            // (el motor avisa y no genera el asiento) a grabar una cuenta que contabilizaría mal
            // todos los documentos de la empresa en silencio.
            $codigoCuenta->execute([$idCuenta, $idEmpresa]);
            $codCta = (string) ($codigoCuenta->fetchColumn() ?: '');
            $slot   = $tipoCuentaPorSlot[$idTipo] ?? null;
            if ($slot !== null && !\App\Services\modulos\AsientoProgramadoService::cuentaCompatible($slot['tipo_cuenta'], $codCta)) {
                $res['descartadas']++;
                $res['motivos'][] = sprintf(
                    'La cuenta %s no se importó en «%s»: ese concepto admite cuentas de tipo %s.',
                    $codCta !== '' ? $codCta : "#{$idCuenta}",
                    $slot['referencia'],
                    str_replace(',', ', ', $slot['tipo_cuenta'])
                );
                continue;
            }

            try {
                $pg->beginTransaction();
                $buscar->execute([$idEmpresa, $idTipo, $idTipo]);
                $existe = $buscar->fetchColumn();
                if ($existe !== false) {
                    $upd->execute([$idCuenta, $idUsuario, (int) $existe]);
                    $res['actualizadas']++;
                } else {
                    $ins->execute([$idEmpresa, $idUsuario, $idTipo, $idCuenta, $idTipo, $idUsuario]);
                    $res['aplicadas']++;
                }
                $pg->commit();
            } catch (Throwable $e) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
            }
        }
        return $res;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  FASE 3 — reglas POR ENTIDAD (viejo id_asiento_tipo = 0): IVA por tarifa,
    //  retenciones por código, cuenta de gasto por proveedor, cuenta de las formas de
    //  cobro/pago y bancos, y cuenta de los conceptos de ingreso/egreso.
    //  Cadena base: asientos_programados(viejo) → id_cuenta → plan_cuentas(viejo).codigo
    //  → código casa → plan_cuentas(nuevo).codigo → id. La ENTIDAD destino se resuelve
    //  distinto por tipo_asiento (ver cada rama). Igual que la Fase 2: previsualizar sin
    //  escribir; aplicar solo lo marcado; idempotente.
    // ─────────────────────────────────────────────────────────────────────────────

    /** Normaliza un nombre para cruzar por texto (mayúsculas, sin tildes, espacios colapsados). */
    private static function norm(string $s): string
    {
        $s = mb_strtoupper(trim($s), 'UTF-8');
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        return preg_replace('/\s+/', ' ', $s);
    }

    public function previsualizarFase3(int $idEmpresa, string $ruc): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        // ── Precargas del sistema NUEVO ─────────────────────────────────────────
        $ctaPorCodigo = [];
        $q = $pg->prepare("SELECT id, codigo, nombre FROM plan_cuentas WHERE id_empresa = ? AND eliminado = false");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { $ctaPorCodigo[(string) $r['codigo']] = $r; }

        // Slot de compras para el override de gasto por proveedor.
        $slotSubtotalCompra = null;
        foreach ($pg->query("SELECT id, codigo FROM asientos_tipo WHERE eliminado = false") as $r) {
            if ((string) $r['codigo'] === 'SUBTOTALFACTURACOMPRA') { $slotSubtotalCompra = (int) $r['id']; }
        }

        $provPorIdent = [];
        foreach ($pg->query("SELECT id, TRIM(identificacion) AS ident FROM proveedores WHERE id_empresa = " . (int) $idEmpresa . " AND eliminado = false") as $r) {
            if ($r['ident'] !== '') { $provPorIdent[(string) $r['ident']] = (int) $r['id']; }
        }
        $retPorCodigo = [];
        foreach ($pg->query("SELECT id, TRIM(codigo_ret) AS c FROM retenciones_sri") as $r) {
            if ($r['c'] !== '' && !isset($retPorCodigo[(string) $r['c']])) { $retPorCodigo[(string) $r['c']] = (int) $r['id']; }
        }
        $tarifaPorPct = [];
        foreach ($pg->query("SELECT codigo, porcentaje_iva FROM tarifa_iva WHERE status = 1") as $r) {
            $tarifaPorPct[(string) (int) round((float) $r['porcentaje_iva'])] = (string) $r['codigo'];
        }
        $formaPorNombre = [];
        foreach ($pg->query("SELECT id, nombre, tipo FROM empresa_formas_pago WHERE id_empresa = " . (int) $idEmpresa) as $r) {
            $formaPorNombre[self::norm((string) $r['nombre'])] = ['id' => (int) $r['id'], 'tipo' => (string) $r['tipo']];
        }
        $opcionPorNombre = [];
        foreach ($pg->query("SELECT id, nombre FROM empresa_opciones_ingreso_egreso WHERE id_empresa = " . (int) $idEmpresa) as $r) {
            $opcionPorNombre[self::norm((string) $r['nombre'])] = (int) $r['id'];
        }
        // Mapa de cuentas bancarias viejas → forma de pago nueva (entidad migrada).
        $bancoMap = [];
        $qb = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'cuentas_bancarias'");
        $qb->execute([$idEmpresa]);
        foreach ($qb->fetchAll(PDO::FETCH_ASSOC) as $r) { $bancoMap[(string) (int) $r['id_origen']] = (int) $r['id_destino']; }
        // Reglas ya existentes en el nuevo (para marcar 'ya_configurada'): clave por
        // tipo_referencia + id_referencia (+ id_asiento_tipo para el proveedor).
        $yaAP = [];
        $qy = $pg->prepare("SELECT tipo_referencia, id_referencia, id_asiento_tipo FROM asientos_programados WHERE id_empresa = ? AND eliminado = false AND tipo_referencia IS NOT NULL");
        $qy->execute([$idEmpresa]);
        foreach ($qy->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $yaAP[$r['tipo_referencia'] . '|' . (int) $r['id_referencia'] . '|' . (int) $r['id_asiento_tipo']] = true;
        }

        // ── Precargas del sistema VIEJO ─────────────────────────────────────────
        $qBase = $mysql->quote($base);
        $oldProv = [];
        foreach ($mysql->query("SELECT id_proveedor, TRIM(ruc_proveedor) AS ruc FROM proveedores WHERE LEFT(ruc_empresa,10) = $qBase") as $r) {
            $oldProv[(string) (int) $r['id_proveedor']] = (string) $r['ruc'];
        }
        $oldRet = [];
        foreach ($mysql->query("SELECT id_ret, TRIM(codigo_ret) AS c FROM retenciones_sri") as $r) {
            $oldRet[(string) (int) $r['id_ret']] = (string) $r['c'];
        }

        // Todas las reglas por entidad (id_asiento_tipo = 0) con su cuenta vieja.
        $sql = "SELECT ap.tipo_asiento, ap.concepto_tipo, ap.id_pro_cli, ap.id_cuenta,
                       pc.codigo_cuenta, pc.nombre_cuenta
                  FROM asientos_programados ap
                  LEFT JOIN plan_cuentas pc ON pc.id_cuenta = ap.id_cuenta
                 WHERE LEFT(ap.ruc_empresa,10) = $qBase
                   AND (ap.id_asiento_tipo IS NULL OR ap.id_asiento_tipo = 0)
                 ORDER BY ap.tipo_asiento, ap.concepto_tipo";

        $filas = [];
        $res   = ['total' => 0, 'listas' => 0, 'sin_cuenta' => 0, 'sin_referencia' => 0, 'ya' => 0, 'omitidas' => 0];
        // Para el desempate "fuente gana" en retenciones: clave (tipo|codigo) → índice de fila ya elegida.
        $retElegida = [];

        foreach ($mysql->query($sql) as $r) {
            $tipo   = strtolower(trim((string) $r['tipo_asiento']));
            $codCtaVieja = trim((string) ($r['codigo_cuenta'] ?? ''));
            $codCasa = $codCtaVieja !== '' ? MigracionMysqlService::codigoCasaPublico($codCtaVieja) : '';
            $cta     = $codCasa !== '' ? ($ctaPorCodigo[$codCasa] ?? null) : null;
            $idProCli = (int) $r['id_pro_cli'];
            $concepto = (string) $r['concepto_tipo'];

            $grupo = ''; $destino = null; $aplicar = null; $refTxt = '';

            switch ($tipo) {
                case 'proveedor':
                    $grupo = 'Gasto/costo por proveedor';
                    $ruc   = $oldProv[(string) $idProCli] ?? '';
                    $idProvNuevo = $ruc !== '' ? ($provPorIdent[$ruc] ?? 0) : 0;
                    $refTxt = $ruc !== '' ? $ruc : "id {$idProCli}";
                    if ($idProvNuevo > 0 && $slotSubtotalCompra) {
                        $destino = "Proveedor {$ruc} · Subtotal documento en compras";
                        $aplicar = ['via' => 'ap', 'tipo_referencia' => 'proveedor', 'id_referencia' => $idProvNuevo, 'id_asiento_tipo' => $slotSubtotalCompra];
                    }
                    break;

                case 'iva_ventas': case 'iva_compras':
                case 'tarifa_iva_ventas': case 'tarifa_iva_ventas_iva':
                    // Del concepto o del nombre de la cuenta sale la tarifa (12/15…).
                    if (preg_match('/(\d{1,2})\s*%/', $concepto . ' ' . $codCtaVieja . ' ' . (string) ($r['nombre_cuenta'] ?? ''), $mm)) {
                        $pct = (string) (int) $mm[1];
                    } else { $pct = ''; }
                    $tarCod = $pct !== '' ? ($tarifaPorPct[$pct] ?? '') : '';
                    $esVenta = str_contains($tipo, 'venta');
                    $tipoRef = $esVenta ? 'iva_ventas_factura' : 'iva_compras_factura';
                    $grupo   = $esVenta ? 'IVA en ventas' : 'IVA en compras';
                    $refTxt  = $pct !== '' ? "{$pct}%" : $concepto;
                    if ($tarCod !== '') {
                        $destino = ($esVenta ? 'IVA ventas' : 'IVA compras') . " · tarifa {$pct}%";
                        $aplicar = ['via' => 'ap', 'tipo_referencia' => $tipoRef, 'id_referencia' => (int) $tarCod, 'id_asiento_tipo' => 0];
                    }
                    break;

                case 'retenciones_compras': case 'retenciones_ventas':
                    $esVenta = str_contains($tipo, 'venta');
                    $grupo   = $esVenta ? 'Retención en ventas' : 'Retención por pagar (compras)';
                    // Resolver el código: id_pro_cli → id_ret → codigo_ret; si no cruza, es el código directo.
                    $codigoRet = $oldRet[(string) $idProCli] ?? (string) $idProCli;
                    $esFuente  = !isset($oldRet[(string) $idProCli]); // grupo directo = fuente
                    $refTxt    = $codigoRet;
                    $idRetNuevo = $codigoRet !== '' ? ($retPorCodigo[$codigoRet] ?? 0) : 0;
                    $tipoRef = $esVenta ? 'retenciones_venta_debe' : 'retenciones_compra_haber';
                    if ($idRetNuevo > 0) {
                        $destino = ($esVenta ? 'Ret. venta' : 'Ret. compra') . " · código {$codigoRet}";
                        $aplicar = ['via' => 'ap', 'tipo_referencia' => $tipoRef, 'id_referencia' => $idRetNuevo, 'id_asiento_tipo' => 0, '_es_fuente' => $esFuente, '_dedup' => $tipoRef . '|' . $codigoRet];
                    }
                    break;

                case 'opcion_cobro': case 'opcion_pago':
                    $flujo = $tipo === 'opcion_cobro' ? 'cobro' : 'pago';
                    $grupo = $flujo === 'cobro' ? 'Forma de cobro' : 'Forma de pago';
                    $refTxt = $concepto;
                    $forma = $formaPorNombre[self::norm($concepto)] ?? null;
                    if ($forma) {
                        $destino = "Forma de {$flujo} «{$concepto}»";
                        $aplicar = ['via' => 'forma', 'id_forma' => $forma['id'], 'flujo' => $flujo, 'tipo_referencia' => ($flujo === 'cobro' ? 'forma_cobro' : 'forma_pago')];
                    }
                    break;

                case 'opcion_ingreso': case 'opcion_egreso':
                    $nat   = $tipo === 'opcion_ingreso' ? 'ingreso' : 'egreso';
                    $grupo = $nat === 'ingreso' ? 'Concepto de ingreso' : 'Concepto de egreso';
                    $refTxt = $concepto;
                    $op = $opcionPorNombre[self::norm($concepto)] ?? 0;
                    if ($op > 0) {
                        $destino = "Concepto de {$nat} «{$concepto}»";
                        $aplicar = ['via' => 'opcion', 'id_opcion' => $op, 'naturaleza' => $nat, 'tipo_referencia' => ($nat === 'ingreso' ? 'opcion_ingreso' : 'opcion_egreso')];
                    }
                    break;

                case 'bancos': case 'concepto_pago_egresos':
                    $grupo  = 'Banco / forma de pago';
                    $refTxt = "cta. bancaria {$idProCli}";
                    $idForma = $bancoMap[(string) $idProCli] ?? 0;
                    if ($idForma > 0) {
                        $destino = "Cuenta contable del banco (forma de pago #{$idForma})";
                        $aplicar = ['via' => 'forma', 'id_forma' => $idForma, 'flujo' => 'pago', 'tipo_referencia' => 'forma_pago'];
                    }
                    break;

                default:
                    continue 2; // tipo_asiento no contemplado → se ignora
            }

            $res['total']++;

            // Estado.
            if ($aplicar === null)        { $estado = 'sin_referencia'; $res['sin_referencia']++; }
            elseif ($cta === null)        { $estado = 'sin_cuenta';     $res['sin_cuenta']++; }
            else {
                $aplicar['id_cuenta'] = (int) $cta['id'];
                // Desempate "fuente gana" para retenciones repetidas.
                if (isset($aplicar['_dedup'])) {
                    $prev = $retElegida[$aplicar['_dedup']] ?? null;
                    if ($prev !== null) {
                        // ya hay una elegida: si la nueva es fuente y la previa no, reemplazar; si no, omitir.
                        if (!empty($aplicar['_es_fuente']) && empty($filas[$prev]['_es_fuente'])) {
                            $filas[$prev]['estado'] = 'omitida'; $res['omitidas']++; $res['listas']--;
                        } else {
                            $res['total']--; $res['omitidas']++;
                            $filas[] = ['grupo' => $grupo, 'concepto_viejo' => $concepto, 'referencia' => $refTxt,
                                        'cuenta_vieja' => $codCtaVieja . ' · ' . (string) ($r['nombre_cuenta'] ?? ''),
                                        'destino' => $destino, 'cuenta_nueva' => $cta['codigo'] . ' · ' . $cta['nombre'],
                                        'estado' => 'omitida', 'aplicar' => null, '_es_fuente' => !empty($aplicar['_es_fuente'])];
                            continue;
                        }
                    }
                }
                // ya configurada
                $claveYa = ($aplicar['via'] === 'ap')
                    ? ($aplicar['tipo_referencia'] . '|' . (int) $aplicar['id_referencia'] . '|' . (int) $aplicar['id_asiento_tipo'])
                    : ($aplicar['tipo_referencia'] . '|' . (int) ($aplicar['id_forma'] ?? $aplicar['id_opcion']) . '|0');
                if (isset($yaAP[$claveYa])) { $estado = 'ya_configurada'; $res['ya']++; }
                else { $estado = 'lista'; $res['listas']++; }
            }

            $filas[] = [
                'grupo'          => $grupo,
                'concepto_viejo' => $concepto,
                'referencia'     => $refTxt,
                'cuenta_vieja'   => $codCtaVieja . ' · ' . (string) ($r['nombre_cuenta'] ?? ''),
                'cod_casa'       => $codCasa,
                'destino'        => $destino,
                'cuenta_nueva'   => $cta !== null ? ($cta['codigo'] . ' · ' . $cta['nombre']) : null,
                'estado'         => $estado,
                'aplicar'        => ($estado === 'lista' || $estado === 'ya_configurada') ? $aplicar : null,
                '_es_fuente'     => !empty($aplicar['_es_fuente']),
            ];
            if (isset($aplicar['_dedup'])) { $retElegida[$aplicar['_dedup']] = count($filas) - 1; }
        }

        return ['filas' => $filas, 'resumen' => $res];
    }

    /**
     * Aplica SOLO las reglas marcadas de la Fase 3. Cada elemento de $seleccion es la estructura
     * `aplicar` que devolvió previsualizarFase3 (con `id_cuenta` incluido). Idempotente.
     */
    public function aplicarFase3(int $idEmpresa, int $idUsuario, array $seleccion): array
    {
        $pg  = Database::getConnection();
        $res = ['aplicadas' => 0, 'actualizadas' => 0, 'errores' => 0];

        $formaSvc  = new \App\Services\modulos\FormaPagoService(new \App\repositories\modulos\FormaPagoRepository());
        $opcionSvc = new \App\Services\modulos\OpcionIngresoEgresoService();

        $buscarAP = $pg->prepare(
            "SELECT id FROM asientos_programados WHERE id_empresa = ? AND tipo_referencia = ?
               AND id_referencia = ? AND id_asiento_tipo = ? AND eliminado = false LIMIT 1"
        );
        $updAP = $pg->prepare("UPDATE asientos_programados SET id_cuenta = ?, updated_at = now(), updated_by = ? WHERE id = ?");
        $insAP = $pg->prepare(
            "INSERT INTO asientos_programados (id_empresa, id_usuario, id_asiento_tipo, id_cuenta, id_referencia, tipo_referencia, eliminado, created_by)
             VALUES (?, ?, ?, ?, ?, ?, false, ?)"
        );

        foreach ($seleccion as $a) {
            $via      = (string) ($a['via'] ?? '');
            $idCuenta = (int) ($a['id_cuenta'] ?? 0);
            if ($idCuenta <= 0) { $res['errores']++; continue; }

            try {
                if ($via === 'ap') {
                    $tipoRef = (string) $a['tipo_referencia'];
                    $idRef   = (int) $a['id_referencia'];
                    $idTipo  = (int) ($a['id_asiento_tipo'] ?? 0);
                    $pg->beginTransaction();
                    $buscarAP->execute([$idEmpresa, $tipoRef, $idRef, $idTipo]);
                    $ex = $buscarAP->fetchColumn();
                    if ($ex !== false) { $updAP->execute([$idCuenta, $idUsuario, (int) $ex]); $res['actualizadas']++; }
                    else { $insAP->execute([$idEmpresa, $idUsuario, $idTipo, $idCuenta, $idRef, $tipoRef, $idUsuario]); $res['aplicadas']++; }
                    $pg->commit();
                } elseif ($via === 'forma') {
                    $formaSvc->sincronizarCuentaFlujo($idEmpresa, $idUsuario, (int) $a['id_forma'], (string) $a['flujo'], $idCuenta);
                    $res['aplicadas']++;
                } elseif ($via === 'opcion') {
                    $opcionSvc->sincronizarCuentaNaturaleza($idEmpresa, $idUsuario, (int) $a['id_opcion'], (string) $a['naturaleza'], $idCuenta);
                    $res['aplicadas']++;
                } else {
                    $res['errores']++;
                }
            } catch (Throwable $e) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
            }
        }
        return $res;
    }
}
