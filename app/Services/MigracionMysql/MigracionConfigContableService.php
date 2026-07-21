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
        'CCXRPP'   => 'BANCOSNOMINA', // SUELDO POR PAGAR → Líquido a pagar (acordado con el usuario)
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
        $slotPorCodigo = [];
        foreach ($pg->query("SELECT id, tipo_asiento, referencia, codigo FROM asientos_tipo WHERE eliminado = false") as $r) {
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
        $res   = ['total' => 0, 'listas' => 0, 'sin_slot' => 0, 'sin_cuenta' => 0, 'ya' => 0];

        foreach ($mysql->query($sql) as $r) {
            $res['total']++;
            $codViejo = trim((string) $r['cod_viejo']);
            $codNuevo = self::MAPA_SLOTS[$codViejo] ?? null;
            $slot     = $codNuevo !== null ? ($slotPorCodigo[$codNuevo] ?? null) : null;

            $codCtaVieja = trim((string) ($r['codigo_cuenta'] ?? ''));
            $codCasa     = $codCtaVieja !== '' ? MigracionMysqlService::codigoCasaPublico($codCtaVieja) : '';
            $cta         = $codCasa !== '' ? ($ctaPorCodigo[$codCasa] ?? null) : null;

            if ($slot === null)      { $estado = 'sin_slot';   $res['sin_slot']++; }
            elseif ($cta === null)   { $estado = 'sin_cuenta'; $res['sin_cuenta']++; }
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
        $res = ['aplicadas' => 0, 'actualizadas' => 0, 'errores' => 0];

        $buscar = $pg->prepare(
            "SELECT id FROM asientos_programados
              WHERE id_empresa = ? AND id_asiento_tipo = ? AND id_referencia = ?
                AND tipo_referencia = 'asientos tipo' AND eliminado = false LIMIT 1"
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
}
