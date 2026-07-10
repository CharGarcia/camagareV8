<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use App\core\Database;
use PDO;
use Throwable;

/**
 * Migración desde la BD MySQL del sistema anterior hacia el sistema nuevo (PostgreSQL).
 * Fase actual: ANÁLISIS/RESUMEN (solo lectura) — cuenta cuántos registros hay por
 * entidad para la empresa seleccionada, antes de migrar.
 * La transferencia real por entidad se implementa por fases (catálogos → documentos → cobros).
 */
class MigracionMysqlService
{
    /**
     * Entidades migrables: clave => [label, tabla, fecha (columna de fecha o null), tipo].
     * `tipo` (catalogo|documento) ajusta la estimación de tiempo. Todas filtran por ruc_empresa.
     */
    public const ENTIDADES = [
        'clientes'          => ['label' => 'Clientes',                         'tabla' => 'clientes',                   'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'productos'         => ['label' => 'Productos y servicios',            'tabla' => 'productos_servicios',        'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'proveedores'       => ['label' => 'Proveedores',                      'tabla' => 'proveedores',                'fecha' => 'fecha_agregado', 'tipo' => 'catalogo'],
        'vendedores'        => ['label' => 'Vendedores',                       'tabla' => 'vendedores',                 'fecha' => 'fecha_registro', 'tipo' => 'catalogo'],
        'bodegas'           => ['label' => 'Bodegas',                          'tabla' => 'bodega',                     'fecha' => null,             'tipo' => 'catalogo'],
        'facturas'          => ['label' => 'Facturas de venta',                'tabla' => 'encabezado_factura',         'fecha' => 'fecha_factura',  'tipo' => 'documento'],
        'notas_credito'     => ['label' => 'Notas de crédito',                 'tabla' => 'encabezado_nc',              'fecha' => 'fecha_nc',       'tipo' => 'documento'],
        'retenciones_venta' => ['label' => 'Retenciones en venta',             'tabla' => 'encabezado_retencion_venta', 'fecha' => 'fecha_emision',  'tipo' => 'documento'],
        'retenciones_compra' => ['label' => 'Retenciones en compra',           'tabla' => 'encabezado_retencion',       'fecha' => 'fecha_emision',  'tipo' => 'documento'],
        'recibos'           => ['label' => 'Recibos de venta',                 'tabla' => 'encabezado_recibo',          'fecha' => 'fecha_recibo',   'tipo' => 'documento'],
        'liquidaciones'     => ['label' => 'Liquidaciones de compra',           'tabla' => 'encabezado_liquidacion',     'fecha' => 'fecha_liquidacion', 'tipo' => 'documento'],
        'guias'             => ['label' => 'Guías de remisión',                 'tabla' => 'encabezado_gr',              'fecha' => 'fecha_gr',       'tipo' => 'documento'],
        'compras'           => ['label' => 'Compras',                          'tabla' => 'encabezado_compra',          'fecha' => 'fecha_compra',   'tipo' => 'documento'],
        'ingresos'          => ['label' => 'Cobros (ingresos)',                 'tabla' => 'ingresos_egresos',           'fecha' => 'fecha_ing_egr',  'tipo' => 'documento', 'filtro' => "tipo_ing_egr = 'INGRESO'"],
        'egresos'           => ['label' => 'Pagos (egresos)',                   'tabla' => 'ingresos_egresos',           'fecha' => 'fecha_ing_egr',  'tipo' => 'documento', 'filtro' => "tipo_ing_egr = 'EGRESO'"],
    ];

    /** Segundos estimados por registro según tipo (aprox., calibrado en pruebas). */
    private const SEG_POR_REG = ['catalogo' => 0.012, 'documento' => 0.04];

    /**
     * Resumen de cuántos registros hay por entidad para la empresa (por RUC base,
     * incluye todos los establecimientos del contribuyente). Solo lectura.
     *
     * @param string[] $entidades  claves a incluir; vacío = todas
     * @return array<string,array{label:string,tabla:string,total:?int,error:?string}>
     */
    public function analizar(string $rucEmpresa, array $entidades = []): array
    {
        $pdo  = LegacyMysqlConnection::get();
        $base = substr(preg_replace('/\D+/', '', $rucEmpresa), 0, 10);

        $out = [];
        foreach (self::ENTIDADES as $key => $def) {
            if (!empty($entidades) && !in_array($key, $entidades, true)) {
                continue;
            }
            $fecha = $def['fecha'] ?? null;
            $fila = ['label' => $def['label'], 'tabla' => $def['tabla'], 'total' => null, 'fecha_min' => null, 'fecha_max' => null, 'est_segundos' => 0, 'error' => null];
            try {
                $sel = "COUNT(*) AS n";
                if ($fecha) {
                    $sel .= ", MIN(CASE WHEN `$fecha` >= '2000-01-01' THEN `$fecha` END) AS fmin, MAX(`$fecha`) AS fmax";
                }
                $whereF = "LEFT(ruc_empresa, 10) = :b" . (!empty($def['filtro']) ? " AND " . $def['filtro'] : "");
                $st = $pdo->prepare("SELECT $sel FROM `{$def['tabla']}` WHERE $whereF");
                $st->execute([':b' => $base]);
                $row = $st->fetch();
                $fila['total'] = (int) $row['n'];
                if ($fecha) {
                    $fila['fecha_min'] = self::fechaCorta($row['fmin'] ?? null);
                    $fila['fecha_max'] = self::fechaCorta($row['fmax'] ?? null);
                }
                $rate = self::SEG_POR_REG[$def['tipo'] ?? 'catalogo'] ?? 0.02;
                $fila['est_segundos'] = (int) ceil($fila['total'] * $rate);
            } catch (Throwable $e) {
                $fila['error'] = substr($e->getMessage(), 0, 140);
            }
            $out[$key] = $fila;
        }
        return $out;
    }

    /**
     * Migra una entidad de la empresa (idempotente vía migracion_mysql_map).
     * @return array contadores del proceso
     */
    public function migrar(string $entidad, int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        switch ($entidad) {
            case 'clientes':
                return $this->migrarClientes($idEmpresa, $ruc, $idUsuario);
            case 'productos':
                return $this->migrarProductos($idEmpresa, $ruc, $idUsuario);
            case 'proveedores':
                return $this->migrarProveedores($idEmpresa, $ruc, $idUsuario);
            case 'vendedores':
                return $this->migrarVendedores($idEmpresa, $ruc, $idUsuario);
            case 'bodegas':
                return $this->migrarBodegas($idEmpresa, $ruc, $idUsuario);
            case 'facturas':
                return $this->migrarFacturas($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'compras':
                return $this->migrarCompras($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'notas_credito':
                return $this->migrarNotasCredito($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'retenciones_compra':
                return $this->migrarRetencionesCompra($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'retenciones_venta':
                return $this->migrarRetencionesVenta($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'recibos':
                return $this->migrarRecibos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'liquidaciones':
                return $this->migrarLiquidaciones($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'guias':
                return $this->migrarGuias($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'ingresos':
                return $this->migrarIngresos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            case 'egresos':
                return $this->migrarEgresos($idEmpresa, $ruc, $idUsuario, $limite, $desde, $hasta);
            default:
                return [
                    'entidad' => $entidad, 'total' => 0, 'migrados' => 0, 'vinculados' => 0,
                    'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0, 'no_implementado' => true,
                ];
        }
    }

    /** Migra los clientes del contribuyente (todos los establecimientos). */
    private function migrarClientes(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'clientes', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        // Ya migrados (anti-reproceso)
        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'clientes'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }

        // Buscar SIN filtrar eliminado: la restricción unique_cliente_empresa cubre TODAS las filas
        // (incluidas las soft-deleted); si existe una borrada, hay que enlazar a ella, no insertar.
        $buscar = $pg->prepare("SELECT id FROM clientes WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, provincia, ciudad, status, created_by)
             VALUES (:e, :u, :nom, :tipo, :ident, :tel, :mail, :dir, :plazo, :prov, :ciu, :status, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'clientes', :o, :d, :cn, :vin, :cb)
             ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $stmt = $mysql->query(
            "SELECT id, nombre, tipo_id, ruc, telefono, email, direccion, plazo, provincia, ciudad, status
               FROM clientes WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) {
                $res['ya_migrados']++;
                continue;
            }
            $ident = trim((string) $r['ruc']);
            if ($ident === '') {
                $res['omitidos']++;
                continue;
            }
            $nombre = trim((string) $r['nombre']);
            if ($nombre === '') {
                $nombre = $ident;
            }
            $tipo = trim((string) $r['tipo_id']);
            if ($tipo === '') {
                $tipo = self::inferirTipoId($ident);
            }

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':u' => $idUsuario, ':nom' => $nombre, ':tipo' => $tipo, ':ident' => $ident,
                        ':tel' => self::nz($r['telefono']), ':mail' => self::nz($r['email']), ':dir' => self::nz($r['direccion']),
                        ':plazo' => (int) ($r['plazo'] ?? 0), ':prov' => self::nz($r['provincia']), ':ciu' => self::nz($r['ciudad']),
                        ':status' => (int) ($r['status'] ?? 1), ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los productos/servicios del contribuyente. */
    private function migrarProductos(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'productos', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'productos'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }

        // Dedup por (id_empresa, codigo) — no hay unique constraint, se controla aquí.
        $buscar = $pg->prepare("SELECT id FROM productos WHERE id_empresa = :e AND codigo = :cod LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO productos (id_empresa, codigo, nombre, codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva, status, inventariable, id_usuario, created_by)
             VALUES (:e, :cod, :nom, :aux, :barras, :precio, :tipo, :iva, :status, :inv, :u, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'productos', :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $ivaValidos = ['0', '2', '3', '4', '5', '6', '7', '8', '10'];
        $stmt = $mysql->query(
            "SELECT id, codigo_producto, nombre_producto, codigo_auxiliar, precio_producto, tipo_produccion, tarifa_iva, status
               FROM productos_servicios WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) {
                $res['ya_migrados']++;
                continue;
            }
            $codigo = trim((string) $r['codigo_producto']);
            $nombre = trim((string) $r['nombre_producto']);
            if ($codigo === '' && $nombre === '') {
                $res['omitidos']++;
                continue;
            }
            if ($codigo === '') {
                $codigo = 'MIG-' . $old; // sin código: se genera uno estable
            }
            if ($nombre === '') {
                $nombre = $codigo;
            }
            $tipo = trim((string) $r['tipo_produccion']);
            if ($tipo === '') {
                $tipo = '01';
            }
            $iva = trim((string) $r['tarifa_iva']);
            if (!in_array($iva, $ivaValidos, true)) {
                $iva = '0';
            }

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':cod' => $codigo]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':cod' => $codigo, ':nom' => $nombre,
                        ':aux' => trim((string) ($r['codigo_auxiliar'] ?? '')), ':barras' => '',
                        ':precio' => (float) ($r['precio_producto'] ?? 0), ':tipo' => $tipo, ':iva' => (int) $iva,
                        ':status' => (int) ($r['status'] ?? 1), ':inv' => $tipo === '01' ? 't' : 'f',
                        ':u' => $idUsuario, ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($codigo, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los proveedores del contribuyente. */
    private function migrarProveedores(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'proveedores', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = 'proveedores'");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }

        // Dedup por (id_empresa, identificacion) — sin unique constraint, se controla aquí.
        $buscar = $pg->prepare("SELECT id FROM proveedores WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO proveedores (id_empresa, id_usuario, razon_social, nombre_comercial, tipo_id_proveedor, identificacion, email, direccion, telefono, tipo_empresa, plazo, unidad_tiempo, relacionado, tipo_cta, numero_cta, created_by)
             VALUES (:e, :u, :rs, :nc, :tipo, :ident, :mail, :dir, :tel, :temp, :plazo, :ut, :rel, :tcta, :ncta, :cb) RETURNING id"
        );
        $insMap = $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, 'proveedores', :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );

        $stmt = $mysql->query(
            "SELECT id_proveedor, razon_social, nombre_comercial, tipo_id_proveedor, ruc_proveedor, mail_proveedor, dir_proveedor, telf_proveedor, tipo_empresa, plazo, unidad_tiempo, relacionado, tipo_cta, numero_cta
               FROM proveedores WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base)
        );

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_proveedor'];
            if (isset($done[(string) $old])) {
                $res['ya_migrados']++;
                continue;
            }
            $ident = trim((string) $r['ruc_proveedor']);
            if ($ident === '') {
                $res['omitidos']++;
                continue;
            }
            $rs = trim((string) $r['razon_social']);
            if ($rs === '') {
                $rs = trim((string) $r['nombre_comercial']) ?: $ident;
            }
            $tipo = trim((string) $r['tipo_id_proveedor']);
            if ($tipo === '') {
                $tipo = self::inferirTipoId($ident);
            }
            $rel = in_array(strtolower(trim((string) $r['relacionado'])), ['1', 'si', 'true', 't'], true) ? 't' : 'f';
            $plazo = is_numeric($r['plazo']) ? (int) $r['plazo'] : null;

            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $existente = $buscar->fetchColumn();
                if ($existente !== false) {
                    $idDest = (int) $existente;
                    $vin = true;
                    $res['vinculados']++;
                } else {
                    $ins->execute([
                        ':e' => $idEmpresa, ':u' => $idUsuario, ':rs' => $rs, ':nc' => self::nz($r['nombre_comercial']),
                        ':tipo' => $tipo, ':ident' => $ident, ':mail' => self::nz($r['mail_proveedor']),
                        ':dir' => self::nz($r['dir_proveedor']), ':tel' => self::nz($r['telf_proveedor']),
                        ':temp' => self::nz($r['tipo_empresa']), ':plazo' => $plazo, ':ut' => self::nz($r['unidad_tiempo']) ?? 'Días',
                        ':rel' => $rel, ':tcta' => self::nz($r['tipo_cta']), ':ncta' => self::nz($r['numero_cta']), ':cb' => $idUsuario,
                    ]);
                    $idDest = (int) $ins->fetchColumn();
                    $vin = false;
                    $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) {
                    $pg->rollBack();
                }
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra los vendedores del contribuyente. */
    private function migrarVendedores(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'vendedores', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done = $this->idsMigrados($pg, $idEmpresa, 'vendedores');
        $buscar = $pg->prepare("SELECT id FROM vendedores WHERE id_empresa = :e AND identificacion = :ident LIMIT 1");
        $ins = $pg->prepare(
            "INSERT INTO vendedores (id_empresa, id_usuario, identificacion, nombre, correo, telefono, direccion, created_by)
             VALUES (:e, :u, :ident, :nom, :cor, :tel, :dir, :cb) RETURNING id"
        );
        $insMap = $this->stmtMap($pg, 'vendedores');

        $stmt = $mysql->query("SELECT id_vendedor, numero_id, nombre, correo, telefono, direccion FROM vendedores WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_vendedor'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $ident = trim((string) $r['numero_id']) ?: ('V-' . $old);
            $nombre = trim((string) $r['nombre']) ?: $ident;
            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':ident' => $ident]);
                $ex = $buscar->fetchColumn();
                if ($ex !== false) { $idDest = (int) $ex; $vin = true; $res['vinculados']++; }
                else {
                    $ins->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':ident' => $ident, ':nom' => $nombre,
                        ':cor' => self::nz($r['correo']), ':tel' => self::nz($r['telefono']), ':dir' => self::nz($r['direccion']), ':cb' => $idUsuario]);
                    $idDest = (int) $ins->fetchColumn(); $vin = false; $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($ident, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) $pg->rollBack();
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Migra las bodegas del contribuyente. */
    private function migrarBodegas(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'bodegas', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done = $this->idsMigrados($pg, $idEmpresa, 'bodegas');
        $buscar = $pg->prepare("SELECT id FROM bodegas WHERE id_empresa = :e AND nombre = :nom LIMIT 1");
        $ins = $pg->prepare("INSERT INTO bodegas (id_empresa, id_usuario, nombre, created_by) VALUES (:e, :u, :nom, :cb) RETURNING id");
        $insMap = $this->stmtMap($pg, 'bodegas');

        $stmt = $mysql->query("SELECT id_bodega, nombre_bodega FROM bodega WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base));
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $r['id_bodega'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $nombre = trim((string) $r['nombre_bodega']);
            if ($nombre === '') { $res['omitidos']++; continue; }
            try {
                $pg->beginTransaction();
                $buscar->execute([':e' => $idEmpresa, ':nom' => $nombre]);
                $ex = $buscar->fetchColumn();
                if ($ex !== false) { $idDest = (int) $ex; $vin = true; $res['vinculados']++; }
                else {
                    $ins->execute([':e' => $idEmpresa, ':u' => $idUsuario, ':nom' => $nombre, ':cb' => $idUsuario]);
                    $idDest = (int) $ins->fetchColumn(); $vin = false; $res['migrados']++;
                }
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idDest, ':cn' => substr($nombre, 0, 120), ':vin' => $vin ? 't' : 'f', ':cb' => $idUsuario]);
                $pg->commit(); $done[(string) $old] = true;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) $pg->rollBack();
                $res['errores']++;
            }
        }
        return $res;
    }

    /** % de IVA por código SRI (para derivar impuestos del detalle). */
    private const IVA_PCT = ['0' => 0, '2' => 12, '3' => 14, '4' => 15, '5' => 5, '6' => 0, '7' => 0, '8' => 8, '10' => 13];

    /** get-or-create de una forma de cobro/pago por nombre. */
    private function getOrCreateFormaPago(int $idEmpresa, int $idUsuario, string $nombre, PDO $pg): int
    {
        $nombre = trim($nombre) !== '' ? trim($nombre) : 'Efectivo';
        $st = $pg->prepare("SELECT id FROM empresa_formas_pago WHERE id_empresa = ? AND nombre = ? LIMIT 1");
        $st->execute([$idEmpresa, $nombre]);
        $r = $st->fetchColumn();
        if ($r !== false) { return (int) $r; }
        $ins = $pg->prepare("INSERT INTO empresa_formas_pago (id_empresa, nombre, activo, tipo, aplica_en, created_by) VALUES (?, ?, true, 'EFECTIVO', 'AMBAS', ?) RETURNING id");
        $ins->execute([$idEmpresa, $nombre, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Migra cobros (ingresos): cabecera + detalle (enlaza facturas por el mapa) + pagos (forma de cobro). */
    private function migrarIngresos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'ingresos', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'ingresos');
        $mapFactura = $this->mapaDe($pg, $idEmpresa, 'facturas');
        $insMap     = $this->stmtMap($pg, 'ingresos');

        // Formas de cobro: pre-crear desde el catálogo viejo (fuera de transacción) + una por defecto
        $formaCache = [];
        foreach ($mysql->query("SELECT id, descripcion FROM opciones_cobros_pagos WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $o) {
            $formaCache[(string) $o['id']] = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, (string) $o['descripcion'], $pg);
        }
        $formaDef = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, 'Efectivo', $pg);

        $detStmt   = $mysql->prepare("SELECT valor_ing_egr, detalle_ing_egr, codigo_documento_cv FROM detalle_ingresos_egresos WHERE codigo_documento = :cd AND tipo_documento = 'INGRESO'");
        $formaStmt = $mysql->prepare("SELECT valor_forma_pago, codigo_forma_pago, fecha_pago, cheque FROM formas_pagos_ing_egr WHERE codigo_documento = :cd AND tipo_documento = 'INGRESO'");

        $vc      = $pg->prepare("SELECT id_cliente FROM ventas_cabecera WHERE id = :id");
        $insCab  = $pg->prepare("INSERT INTO ingresos_cabecera (id_empresa, id_usuario, fecha_emision, secuencial, numero_ingreso, tipo_ingreso, id_cliente, monto_total, observaciones, created_by) VALUES (?, ?, ?, ?, ?, 'FACTURA_VENTA', ?, ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO ingresos_detalle (id_ingreso, tipo_documento, id_referencia_documento, descripcion, monto_documento, monto_cobrado) VALUES (?, 'FACTURA_VENTA', ?, ?, ?, ?)");
        $insPago = $pg->prepare("INSERT INTO ingresos_pagos (id_ingreso, id_forma_cobro, monto, fecha_cobro, numero_cheque) VALUES (?, ?, ?, ?, ?)");

        $sql = "SELECT id_ing_egr, codigo_documento, numero_ing_egr, valor_ing_egr, fecha_ing_egr, detalle_adicional
                  FROM ingresos_egresos WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND tipo_ing_egr = 'INGRESO'" . $this->clausulaFecha('fecha_ing_egr', $desde, $hasta, $mysql) . " ORDER BY id_ing_egr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ie = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ie['id_ing_egr'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $cod = (string) $ie['codigo_documento'];
            $sec = str_pad(preg_replace('/\D+/', '', (string) $ie['numero_ing_egr']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $detStmt->execute([':cd' => $cod]);
                $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);

                $idCliente = null;
                foreach ($dets as $d) {
                    $idFac = $mapFactura[(string) (int) $d['codigo_documento_cv']] ?? null;
                    if ($idFac) { $vc->execute([':id' => $idFac]); $idCliente = ($vc->fetchColumn() ?: null); break; }
                }

                $insCab->execute([$idEmpresa, $idUsuario, substr((string) $ie['fecha_ing_egr'], 0, 10), $sec, (string) $ie['numero_ing_egr'], $idCliente, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $idUsuario]);
                $idIng = (int) $insCab->fetchColumn();

                foreach ($dets as $d) {
                    $idFac = $mapFactura[(string) (int) $d['codigo_documento_cv']] ?? null;
                    $insDet->execute([$idIng, $idFac, self::nz($d['detalle_ing_egr']), (float) $d['valor_ing_egr'], (float) $d['valor_ing_egr']]);
                }

                $formaStmt->execute([':cd' => $cod]);
                foreach ($formaStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $idForma = $formaCache[(string) $f['codigo_forma_pago']] ?? $formaDef;
                    $insPago->execute([$idIng, $idForma, (float) $f['valor_forma_pago'], self::fechaCorta($f['fecha_pago']), ((int) $f['cheque']) ?: null]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idIng, ':cn' => (string) $ie['numero_ing_egr'], ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra pagos (egresos): cabecera + detalle (enlaza compras) + pagos (forma de pago). */
    private function migrarEgresos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'egresos', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done      = $this->idsMigrados($pg, $idEmpresa, 'egresos');
        $mapCompra = $this->mapaDe($pg, $idEmpresa, 'compras');
        $insMap    = $this->stmtMap($pg, 'egresos');

        // codigo_documento (string, viejo) → id compra nueva
        $compraPorCod = [];
        foreach ($mysql->query("SELECT id_encabezado_compra, codigo_documento FROM encabezado_compra WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $c) {
            $nid = $mapCompra[(string) (int) $c['id_encabezado_compra']] ?? null;
            if ($nid) { $compraPorCod[(string) $c['codigo_documento']] = $nid; }
        }

        // Formas de pago (mismo catálogo que cobros)
        $formaCache = [];
        foreach ($mysql->query("SELECT id, descripcion FROM opciones_cobros_pagos WHERE LEFT(ruc_empresa,10) = " . $mysql->quote($base)) as $o) {
            $formaCache[(string) $o['id']] = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, (string) $o['descripcion'], $pg);
        }
        $formaDef = $this->getOrCreateFormaPago($idEmpresa, $idUsuario, 'Efectivo', $pg);

        $detStmt   = $mysql->prepare("SELECT valor_ing_egr, detalle_ing_egr, codigo_documento_cv FROM detalle_ingresos_egresos WHERE codigo_documento = :cd AND tipo_documento = 'EGRESO'");
        $formaStmt = $mysql->prepare("SELECT valor_forma_pago, codigo_forma_pago, fecha_pago, cheque FROM formas_pagos_ing_egr WHERE codigo_documento = :cd AND tipo_documento = 'EGRESO'");

        $cc      = $pg->prepare("SELECT id_proveedor FROM compras_cabecera WHERE id = :id");
        $insCab  = $pg->prepare("INSERT INTO egresos_cabecera (id_empresa, fecha_emision, numero_egreso, secuencial, tipo_egreso, tipo_sujeto, id_proveedor, monto_total, observaciones, created_by) VALUES (?, ?, ?, ?, 'COMPRA', 'PROVEEDOR', ?, ?, ?, ?) RETURNING id");
        $insDet  = $pg->prepare("INSERT INTO egresos_detalle (id_egreso, tipo_documento, id_referencia_documento, descripcion, monto_documento, monto_pagado) VALUES (?, 'COMPRA', ?, ?, ?, ?)");
        $insPago = $pg->prepare("INSERT INTO egresos_pagos (id_egreso, id_forma_pago, monto, fecha_cobro, numero_cheque) VALUES (?, ?, ?, ?, ?)");

        $sql = "SELECT id_ing_egr, codigo_documento, numero_ing_egr, valor_ing_egr, fecha_ing_egr, detalle_adicional
                  FROM ingresos_egresos WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND tipo_ing_egr = 'EGRESO'" . $this->clausulaFecha('fecha_ing_egr', $desde, $hasta, $mysql) . " ORDER BY id_ing_egr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ie = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ie['id_ing_egr'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $cod = (string) $ie['codigo_documento'];
            $sec = str_pad(preg_replace('/\D+/', '', (string) $ie['numero_ing_egr']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $detStmt->execute([':cd' => $cod]);
                $dets = $detStmt->fetchAll(PDO::FETCH_ASSOC);

                $idProv = null;
                foreach ($dets as $d) {
                    $idComp = $compraPorCod[(string) $d['codigo_documento_cv']] ?? null;
                    if ($idComp) { $cc->execute([':id' => $idComp]); $idProv = ($cc->fetchColumn() ?: null); break; }
                }

                $insCab->execute([$idEmpresa, substr((string) $ie['fecha_ing_egr'], 0, 10), (string) $ie['numero_ing_egr'], $sec, $idProv, (float) $ie['valor_ing_egr'], self::nz($ie['detalle_adicional']), $idUsuario]);
                $idEgr = (int) $insCab->fetchColumn();

                foreach ($dets as $d) {
                    $idComp = $compraPorCod[(string) $d['codigo_documento_cv']] ?? null;
                    $insDet->execute([$idEgr, $idComp, self::nz($d['detalle_ing_egr']), (float) $d['valor_ing_egr'], (float) $d['valor_ing_egr']]);
                }

                $formaStmt->execute([':cd' => $cod]);
                foreach ($formaStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                    $idForma = $formaCache[(string) $f['codigo_forma_pago']] ?? $formaDef;
                    $insPago->execute([$idEgr, $idForma, (float) $f['valor_forma_pago'], self::fechaCorta($f['fecha_pago']), ((int) $f['cheque']) ?: null]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idEgr, ':cn' => (string) $ie['numero_ing_egr'], ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra guías de remisión (cabecera + detalle, sin importes). */
    private function migrarGuias(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'guias', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'guias');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap     = $this->stmtMap($pg, 'guias');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO guias_remision_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_transportista, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, placa, fecha_inicio_transporte, fecha_fin_transporte, direccion_partida, direccion_destino, motivo_traslado, num_doc_sustento, tipo_ambiente, estado, created_by)
             VALUES (:e, :est, :pto, :cli, :tra, :u, :fe, :estc, :ptoc, :sec, :clave, :placa, :fini, :ffin, :dpart, :ddest, :mot, :nds, :amb, :estado, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO guias_remision_detalle (id_guia_remision, id_producto, codigo_principal, descripcion, cantidad)
             VALUES (:g, :prod, :cod, :desc, :cant)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad_gr, codigo_producto, nombre_producto FROM cuerpo_gr WHERE ruc_empresa = :r AND serie_gr = :s AND secuencial_gr = :sec");
        $transStmt  = $mysql->prepare("SELECT ruc, nombre, tipo_id FROM clientes WHERE id = :id LIMIT 1");

        $sql = "SELECT id_encabezado_gr, ruc_empresa, fecha_gr, fecha_salida, fecha_llegada, serie_gr, secuencial_gr, factura_aplica, origen, destino, id_transportista, id_cliente, placa, estado_sri, ambiente, aut_sri, motivo
                  FROM encabezado_gr WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_gr', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_gr";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_gr'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_gr']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_gr']), 9, '0', STR_PAD_LEFT);
            $fe    = substr((string) $ec['fecha_gr'], 0, 10);
            $fini  = self::fechaCorta($ec['fecha_salida']) ?: $fe;
            $ffin  = self::fechaCorta($ec['fecha_llegada']) ?: $fe;

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                // Transportista: get-or-create desde el cliente viejo
                $transStmt->execute([':id' => (int) $ec['id_transportista']]);
                $t = $transStmt->fetch(PDO::FETCH_ASSOC);
                $tIdent = $t ? trim((string) $t['ruc']) : '';
                if ($tIdent === '') { throw new \RuntimeException('transportista sin identificación'); }
                $idTra = $this->getOrCreateTransportista($idEmpresa, $idUsuario, $tIdent, (($t['nombre'] ?? '') ?: $tIdent), (trim((string) $t['tipo_id']) ?: '04'), (string) ($ec['placa'] ?? ''));
                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':tra' => $idTra, ':u' => $idUsuario,
                    ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':clave' => self::nz($ec['aut_sri']),
                    ':placa' => (string) ($ec['placa'] ?? ''), ':fini' => $fini, ':ffin' => $ffin,
                    ':dpart' => (string) ($ec['origen'] ?? ''), ':ddest' => (string) ($ec['destino'] ?? ''),
                    ':mot' => (string) ($ec['motivo'] ?: 'Venta'), ':nds' => self::nz($ec['factura_aplica']),
                    ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1', ':estado' => $this->estadoFacturaSri((string) $ec['estado_sri']), ':cb' => $idUsuario,
                ]);
                $idGr = (int) $insCab->fetchColumn();

                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_gr']]);
                foreach ($cuerpoStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    $insDet->execute([
                        ':g' => $idGr, ':prod' => $mapProd[(string) $l['id_producto']] ?? null, ':cod' => (string) $l['codigo_producto'],
                        ':desc' => (string) ($l['nombre_producto'] ?: 'ITEM'), ':cant' => (float) $l['cantidad_gr'],
                    ]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idGr, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra liquidaciones de compra (cabecera + detalle + impuestos) resolviendo proveedor. */
    private function migrarLiquidaciones(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'liquidaciones', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done    = $this->idsMigrados($pg, $idEmpresa, 'liquidaciones');
        $mapProv = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap  = $this->stmtMap($pg, 'liquidaciones');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO liquidaciones_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_proveedor, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, numero_autorizacion, total_sin_impuestos, total_descuento, importe_total, moneda, estado, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :prov, :u, :fe, :estc, :ptoc, :sec, :clave, :aut, :tsi, :tdes, :tot, 'DOLAR', :estado, :amb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO liquidaciones_detalle (id_cabecera, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:c, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO liquidaciones_detalle_impuestos (id_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT cantidad, valor_unitario, subtotal, tarifa_iva, descuento, codigo_producto, nombre_producto FROM cuerpo_liquidacion WHERE ruc_empresa = :r AND serie_liquidacion = :s AND secuencial_liquidacion = :sec");

        $sql = "SELECT id_encabezado_liq, ruc_empresa, fecha_liquidacion, serie_liquidacion, secuencial_liquidacion, id_proveedor, estado_sri, total_liquidacion, ambiente, aut_sri
                  FROM encabezado_liquidacion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_liquidacion', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_liq";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_liq'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_liquidacion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_liquidacion']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_liquidacion']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0;
                foreach ($lineas as $l) { $tsi += (float) $l['subtotal'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':prov' => $idProv, ':u' => $idUsuario,
                    ':fe' => substr((string) $ec['fecha_liquidacion'], 0, 10), ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec,
                    ':clave' => self::nz($ec['aut_sri']), ':aut' => self::nz($ec['aut_sri']), ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                    ':tot' => (float) $ec['total_liquidacion'], ':estado' => $this->estadoFacturaSri((string) $ec['estado_sri']),
                    ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1', ':cb' => $idUsuario,
                ]);
                $idLiq = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':c' => $idLiq, ':cod' => (string) $l['codigo_producto'], ':desc' => (string) ($l['nombre_producto'] ?: 'ITEM'),
                        ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['valor_unitario'], ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idLiq, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Migra facturas de venta (cabecera + detalle + impuestos) resolviendo cliente/producto/
     * bodega vía el mapa. Requiere clientes/productos migrados. $limite>0 = solo para pruebas.
     */
    private function migrarFacturas(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $repo  = new \App\repositories\modulos\FacturaVentaRepository();

        $res = ['entidad' => 'facturas', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];

        $done       = $this->idsMigrados($pg, $idEmpresa, 'facturas');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBodega  = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $insMap     = $this->stmtMap($pg, 'facturas');

        // Fallback: clientes existentes en el sistema nuevo por identificación (aunque no estén
        // en el mapa de migración; p.ej. creados por el importador XML). Prefiere el no eliminado.
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $cuerpoStmt = $mysql->prepare(
            "SELECT id_producto, cantidad_factura, valor_unitario_factura, subtotal_factura, descuento, tarifa_iva, codigo_producto, nombre_producto, id_bodega
               FROM cuerpo_factura WHERE ruc_empresa = :r AND serie_factura = :s AND secuencial_factura = :sec"
        );
        $prodPorCod = $this->productosPorCodigo($pg, $idEmpresa);

        $sql = "SELECT id_encabezado_factura, ruc_empresa, fecha_factura, serie_factura, secuencial_factura, id_cliente, observaciones_factura, estado_sri, total_factura, ambiente, aut_sri, propina
                  FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_factura', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_factura";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ef = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ef['id_encabezado_factura'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }

            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ef['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; } // cliente viejo sin identificación

            $serie = trim((string) $ef['serie_factura']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $secuencial = str_pad(preg_replace('/\D+/', '', (string) $ef['secuencial_factura']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);

                // Detalle: leer líneas y totales
                $cuerpoStmt->execute([':r' => $ef['ruc_empresa'], ':s' => $serie, ':sec' => $ef['secuencial_factura']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $totalSinImp = 0.0; $totalDesc = 0.0;
                foreach ($lineas as $l) {
                    $totalSinImp += (float) $l['subtotal_factura'] - (float) $l['descuento'];
                    $totalDesc   += (float) $l['descuento'];
                }

                $estado = $this->estadoFacturaSri((string) $ef['estado_sri']);
                $idVenta = $repo->insertCabecera([
                    'id_empresa' => $idEmpresa, 'id_establecimiento' => $idEst, 'id_punto_emision' => $idPto,
                    'id_cliente' => $idCliente, 'id_usuario' => $idUsuario,
                    'fecha_emision' => substr((string) $ef['fecha_factura'], 0, 10),
                    'establecimiento' => $estab, 'punto_emision' => $pto, 'secuencial' => $secuencial,
                    'total_sin_impuestos' => round($totalSinImp, 2), 'total_descuento' => round($totalDesc, 2),
                    'importe_total' => (float) $ef['total_factura'], 'propina' => (float) $ef['propina'],
                    'moneda' => 'DOLAR', 'estado' => $estado,
                    'observaciones' => self::nz($ef['observaciones_factura']),
                    'clave_acceso' => self::nz($ef['aut_sri']),
                    'tipo_ambiente' => ((string) $ef['ambiente'] === '2') ? '2' : '1',
                    'tipo_registro' => 'migrado',
                ]);

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal_factura'] - (float) $l['descuento'];
                    $idDet = $repo->insertDetalle([
                        'id_venta' => $idVenta,
                        'id_producto' => $this->resolverOCrearProducto($prodPorCod, $mapProd, (int) $l['id_producto'], (string) $l['codigo_producto'], (string) $l['nombre_producto'], trim((string) $l['tarifa_iva']), $idEmpresa, $idUsuario, $pg),
                        'id_bodega' => ((int) $l['id_bodega'] > 0) ? ($mapBodega[(string) $l['id_bodega']] ?? null) : null,
                        'codigo_principal' => (string) $l['codigo_producto'],
                        'descripcion' => (string) $l['nombre_producto'],
                        'cantidad' => (float) $l['cantidad_factura'],
                        'precio_unitario' => (float) $l['valor_unitario_factura'],
                        'descuento' => (float) $l['descuento'],
                        'precio_total_sin_impuesto' => round($base_i, 2),
                    ]);
                    $cod = trim((string) $l['tarifa_iva']); // el valor viejo puede traer espacios
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $repo->insertImpuesto([
                        'id_venta_detalle' => $idDet, 'codigo_impuesto' => '2', 'codigo_porcentaje' => $cod,
                        'tarifa' => $pct, 'base_imponible' => round($base_i, 2), 'valor' => round($base_i * $pct / 100, 2),
                    ]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idVenta, ':cn' => "$estab-$pto-$secuencial", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /**
     * Migra compras (cabecera + detalle + impuestos) resolviendo proveedor vía el mapa.
     * El cuerpo liga por codigo_documento. $limite>0 = solo pruebas.
     */
    private function migrarCompras(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'compras', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done     = $this->idsMigrados($pg, $idEmpresa, 'compras');
        $mapProv  = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap   = $this->stmtMap($pg, 'compras');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO compras_cabecera (id_empresa, id_proveedor, establecimiento_prov, punto_emision_prov, secuencial_prov, numero_autorizacion, fecha_emision, fecha_registro, importe_total, total_sin_impuestos, total_descuento, propina, observaciones, tipo_registro, tipo_ambiente, id_usuario, created_by)
             VALUES (:e, :prov, :est, :pto, :sec, :aut, :fe, :fr, :tot, :tsi, :tdes, :prop, :obs, 'migrado', '1', :u, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO compras_detalle (id_compra, id_producto, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:c, NULL, :cod, :desc, :cant, :pu, :desc2, :base) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO compras_detalle_impuestos (id_compra_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT codigo_producto, detalle_producto, cantidad, precio, descuento, impuesto, subtotal FROM cuerpo_compra WHERE codigo_documento = :cd");

        $sql = "SELECT id_encabezado_compra, codigo_documento, numero_documento, id_proveedor, aut_sri, fecha_compra, fecha_registro, total_compra, propina
                  FROM encabezado_compra WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_compra', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_compra";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_compra'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; } // proveedor no migrado

            $num = explode('-', trim((string) $ec['numero_documento']));
            $est = str_pad($num[0] ?? '', 3, '0', STR_PAD_LEFT);
            $pto = str_pad($num[1] ?? '', 3, '0', STR_PAD_LEFT);
            $sec = str_pad(preg_replace('/\D+/', '', $num[2] ?? ''), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $cuerpoStmt->execute([':cd' => $ec['codigo_documento']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0;
                foreach ($lineas as $l) { $tsi += (float) $l['subtotal'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                $insCab->execute([
                    ':e' => $idEmpresa, ':prov' => $idProv, ':est' => $est, ':pto' => $pto, ':sec' => $sec,
                    ':aut' => self::nz($ec['aut_sri']), ':fe' => substr((string) $ec['fecha_compra'], 0, 10),
                    ':fr' => substr((string) $ec['fecha_registro'], 0, 19) ?: null, ':tot' => (float) $ec['total_compra'],
                    ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2), ':prop' => (float) $ec['propina'],
                    ':obs' => null, ':u' => $idUsuario, ':cb' => $idUsuario,
                ]);
                $idCompra = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':c' => $idCompra, ':cod' => (string) $l['codigo_producto'], ':desc' => (string) ($l['detalle_producto'] ?: $l['codigo_producto'] ?: 'ITEM'),
                        ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['precio'], ':desc2' => (float) $l['descuento'], ':base' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['impuesto']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idCompra, ':cn' => "$est-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /** Migra notas de crédito (cabecera + detalle + impuestos). Mismo patrón que facturas. */
    private function migrarNotasCredito(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'notas_credito', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'notas_credito');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap     = $this->stmtMap($pg, 'notas_credito');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO notas_credito_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, cod_doc_modificado, num_doc_modificado, fecha_emision_docs_sustento, motivo, total_sin_impuestos, total_descuento, importe_total, estado, clave_acceso, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :cli, :u, :fe, :estc, :ptoc, :sec, '01', :ndm, :fds, :mot, :tsi, :tdes, :tot, :estado, :clave, :amb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO notas_credito_detalle (id_nota_credito, id_producto, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:n, :prod, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO notas_credito_detalle_impuestos (id_nota_credito_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad_nc, valor_unitario_nc, subtotal_nc, descuento, tarifa_iva, codigo_producto, nombre_producto FROM cuerpo_nc WHERE ruc_empresa = :r AND serie_nc = :s AND secuencial_nc = :sec");

        $sql = "SELECT id_encabezado_nc, ruc_empresa, fecha_nc, serie_nc, secuencial_nc, factura_modificada, id_cliente, estado_sri, total_nc, ambiente, aut_sri, motivo, fecha_factura
                  FROM encabezado_nc WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_nc', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_nc";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_nc'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_nc']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_nc']), 9, '0', STR_PAD_LEFT);
            $fe    = substr((string) $ec['fecha_nc'], 0, 10);
            $fds   = substr((string) $ec['fecha_factura'], 0, 10);
            if ($fds === '' || strpos($fds, '0000') === 0) { $fds = $fe; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_nc']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0;
                foreach ($lineas as $l) { $tsi += (float) $l['subtotal_nc'] - (float) $l['descuento']; $tdes += (float) $l['descuento']; }

                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':u' => $idUsuario,
                    ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':ndm' => (string) $ec['factura_modificada'],
                    ':fds' => $fds, ':mot' => (string) ($ec['motivo'] ?: 'Migración'), ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                    ':tot' => (float) $ec['total_nc'], ':estado' => $this->estadoFacturaSri((string) $ec['estado_sri']),
                    ':clave' => self::nz($ec['aut_sri']), ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1', ':cb' => $idUsuario,
                ]);
                $idNc = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal_nc'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':n' => $idNc, ':prod' => $mapProd[(string) $l['id_producto']] ?? null, ':cod' => (string) $l['codigo_producto'],
                        ':desc' => (string) $l['nombre_producto'], ':cant' => (float) $l['cantidad_nc'], ':pu' => (float) $l['valor_unitario_nc'],
                        ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idNc, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /** Migra retenciones de compra (cabecera + detalle) resolviendo proveedor vía el mapa. */
    private function migrarRetencionesCompra(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'retenciones_compra', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done    = $this->idsMigrados($pg, $idEmpresa, 'retenciones_compra');
        $mapProv = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap  = $this->stmtMap($pg, 'retenciones_compra');
        $provPorIdent = $this->proveedoresPorIdentificacion($pg, $idEmpresa);
        $oldProvRuc   = $mysql->prepare("SELECT ruc_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO retencion_compra_cabecera (id_empresa, id_proveedor, id_usuario, id_establecimiento, id_punto_emision, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, numero_autorizacion, tipo_ambiente, tipo_doc_sustento, num_doc_sustento, fecha_emision_doc_sustento, total_retenido, created_by)
             VALUES (:e, :prov, :u, :est, :pto, :fe, :estc, :ptoc, :sec, :clave, :aut, :amb, :tds, :nds, :fds, :tot, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO retencion_compra_detalle (id_empresa, id_retencion, codigo_impuesto, codigo_retencion, concepto, base_imponible, porcentaje_retener, valor_retenido)
             VALUES (:e, :r, :ci, :cr, :con, :bi, :pct, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT codigo_impuesto, id_retencion, base_imponible, porcentaje_retencion, valor_retenido, nombre_retencion FROM cuerpo_retencion WHERE ruc_empresa = :r AND serie_retencion = :s AND secuencial_retencion = :sec");

        $sql = "SELECT id_encabezado_retencion, ruc_empresa, id_proveedor, serie_retencion, secuencial_retencion, total_retencion, aut_sri, fecha_emision, fecha_documento, tipo_comprobante, numero_comprobante, ambiente
                  FROM encabezado_retencion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_emision', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_retencion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_retencion'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idProv = $this->resolverOCrearProveedor($provPorIdent, $mapProv, (int) $ec['id_proveedor'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idProv) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_retencion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_retencion']), 9, '0', STR_PAD_LEFT);
            $fe    = substr((string) $ec['fecha_emision'], 0, 10);
            $fds   = substr((string) $ec['fecha_documento'], 0, 10);
            if ($fds === '' || strpos($fds, '0000') === 0) { $fds = $fe; }

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $insCab->execute([
                    ':e' => $idEmpresa, ':prov' => $idProv, ':u' => $idUsuario, ':est' => $idEst, ':pto' => $idPto,
                    ':fe' => $fe, ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec, ':clave' => self::nz($ec['aut_sri']),
                    ':aut' => self::nz($ec['aut_sri']), ':amb' => ((string) $ec['ambiente'] === '2') ? '2' : '1',
                    ':tds' => (string) ($ec['tipo_comprobante'] ?: '01'), ':nds' => self::nz($ec['numero_comprobante']),
                    ':fds' => $fds, ':tot' => (float) $ec['total_retencion'], ':cb' => $idUsuario,
                ]);
                $idRet = (int) $insCab->fetchColumn();

                $cuerpoStmt->execute([':r' => $ec['ruc_empresa'], ':s' => $serie, ':sec' => $ec['secuencial_retencion']]);
                foreach ($cuerpoStmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
                    $insDet->execute([
                        ':e' => $idEmpresa, ':r' => $idRet, ':ci' => trim((string) $l['codigo_impuesto']) ?: '1',
                        ':cr' => trim((string) $l['id_retencion']), ':con' => self::nz($l['nombre_retencion']),
                        ':bi' => (float) $l['base_imponible'], ':pct' => (float) $l['porcentaje_retencion'], ':val' => (float) $l['valor_retenido'],
                    ]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRet, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra retenciones de venta (cabecera + detalle) resolviendo cliente vía el mapa. */
    private function migrarRetencionesVenta(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'retenciones_venta', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'retenciones_venta');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $insMap     = $this->stmtMap($pg, 'retenciones_venta');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO retencion_venta_cabecera (id_empresa, id_cliente, fecha_emision, establecimiento, punto_emision, secuencial, clave_acceso, periodo_fiscal, total_isd, total_iva, total_renta, tipo_ambiente, created_by, updated_by)
             VALUES (:e, :cli, :fe, :est, :pto, :sec, :clave, :per, :isd, :iva, :renta, '1', :cb, :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO retencion_venta_detalle (id_retencion, cod_doc_sustento, fecha_emision_doc_sustento, codigo_impuesto, codigo_retencion, base_imponible, porcentaje_retencion, valor_retenido, num_doc_sustento)
             VALUES (:r, :cds, :fds, :ci, :cr, :bi, :pct, :val, :nds)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT ejercicio_fiscal, base_imponible, codigo_impuesto, impuesto, porcentaje_retencion, valor_retenido, tipo_documento, numero_documento FROM cuerpo_retencion_venta WHERE codigo_unico = :cu");

        $sql = "SELECT id_encabezado_retencion, ruc_empresa, id_cliente, serie_retencion, secuencial_retencion, aut_sri, fecha_emision, codigo_unico, numero_documento
                  FROM encabezado_retencion_venta WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_emision', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_retencion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_retencion'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_retencion']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_retencion']), 9, '0', STR_PAD_LEFT);
            $fe    = substr((string) $ec['fecha_emision'], 0, 10);
            $per   = ($fe !== '' && strpos($fe, '0000') !== 0) ? (substr($fe, 5, 2) . '/' . substr($fe, 0, 4)) : '';

            try {
                $pg->beginTransaction();
                $cuerpoStmt->execute([':cu' => $ec['codigo_unico']]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tRenta = 0.0; $tIva = 0.0; $tIsd = 0.0;
                foreach ($lineas as $l) {
                    $tipo = trim((string) $l['impuesto']);
                    $v = (float) $l['valor_retenido'];
                    if ($tipo === '2') $tIva += $v; elseif ($tipo === '6') $tIsd += $v; else $tRenta += $v;
                }
                if ($per === '' && !empty($lineas[0]['ejercicio_fiscal'])) { $per = (string) $lineas[0]['ejercicio_fiscal']; }

                $insCab->execute([
                    ':e' => $idEmpresa, ':cli' => $idCliente, ':fe' => $fe, ':est' => $estab, ':pto' => $pto, ':sec' => $sec,
                    ':clave' => self::nz($ec['aut_sri']), ':per' => ($per ?: '01/1900'), ':isd' => round($tIsd, 2),
                    ':iva' => round($tIva, 2), ':renta' => round($tRenta, 2), ':cb' => $idUsuario,
                ]);
                $idRet = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $insDet->execute([
                        ':r' => $idRet, ':cds' => (string) ($l['tipo_documento'] ?: '01'), ':fds' => $fe,
                        ':ci' => trim((string) $l['impuesto']) ?: '1', ':cr' => trim((string) $l['codigo_impuesto']),
                        ':bi' => (float) $l['base_imponible'], ':pct' => (float) $l['porcentaje_retencion'], ':val' => (float) $l['valor_retenido'],
                        ':nds' => self::nz($l['numero_documento']),
                    ]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRet, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /** Migra recibos de venta (cabecera + detalle + impuestos). Patrón de facturas. */
    private function migrarRecibos(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0, ?string $desde = null, ?string $hasta = null): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'recibos', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'recibos');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapBodega  = $this->mapaDe($pg, $idEmpresa, 'bodegas');
        $insMap     = $this->stmtMap($pg, 'recibos');
        $cliPorIdent = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $oldCliRuc   = $mysql->prepare("SELECT ruc FROM clientes WHERE id = :id LIMIT 1");

        $insCab = $pg->prepare(
            "INSERT INTO recibos_venta_cabecera (id_empresa, id_establecimiento, id_punto_emision, id_cliente, id_usuario, fecha_emision, establecimiento, punto_emision, secuencial, recibo_numero, con_impuestos, total_sin_impuestos, total_descuento, importe_total, propina, moneda, tipo_ambiente, created_by)
             VALUES (:e, :est, :pto, :cli, :u, :fe, :estc, :ptoc, :sec, :num, :ci, :tsi, :tdes, :tot, :prop, 'DOLAR', '1', :cb) RETURNING id"
        );
        $insDet = $pg->prepare(
            "INSERT INTO recibos_venta_detalle (id_recibo, id_producto, id_bodega, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto)
             VALUES (:r, :prod, :bod, :cod, :desc, :cant, :pu, :desc2, :baseCol) RETURNING id"
        );
        $insImp = $pg->prepare(
            "INSERT INTO recibos_venta_detalle_impuestos (id_recibo_detalle, codigo_impuesto, codigo_porcentaje, tarifa, base_imponible, valor)
             VALUES (:d, '2', :cp, :tar, :base, :val)"
        );
        $cuerpoStmt = $mysql->prepare("SELECT id_producto, cantidad, valor_unitario, subtotal, descuento, tarifa_iva, codigo_producto, nombre_producto, id_bodega FROM cuerpo_recibo WHERE id_encabezado_recibo = :id");

        $sql = "SELECT id_encabezado_recibo, fecha_recibo, serie_recibo, secuencial_recibo, id_cliente, total_recibo, propina
                  FROM encabezado_recibo WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . $this->clausulaFecha('fecha_recibo', $desde, $hasta, $mysql) . " ORDER BY id_encabezado_recibo";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_recibo'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idCliente = $this->resolverOCrearCliente($cliPorIdent, $mapCliente, (int) $ec['id_cliente'], $idEmpresa, $idUsuario, $mysql, $pg);
            if (!$idCliente) { $res['omitidos']++; continue; }

            $serie = trim((string) $ec['serie_recibo']);
            $partes = explode('-', $serie);
            $estab = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto   = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec   = str_pad(preg_replace('/\D+/', '', (string) $ec['secuencial_recibo']), 9, '0', STR_PAD_LEFT);

            try {
                $pg->beginTransaction();
                $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
                $idPto = $this->getPuntoEmisionId($idEmpresa, $estab, $pto, $idUsuario);
                $cuerpoStmt->execute([':id' => $old]);
                $lineas = $cuerpoStmt->fetchAll(PDO::FETCH_ASSOC);
                $tsi = 0.0; $tdes = 0.0; $ivaTot = 0.0;
                foreach ($lineas as $l) {
                    $b = (float) $l['subtotal'] - (float) $l['descuento'];
                    $tsi += $b; $tdes += (float) $l['descuento'];
                    $ivaTot += $b * (self::IVA_PCT[trim((string) $l['tarifa_iva'])] ?? 0) / 100;
                }
                $conImp = $ivaTot > 0.005 ? 't' : 'f';

                $insCab->execute([
                    ':e' => $idEmpresa, ':est' => $idEst, ':pto' => $idPto, ':cli' => $idCliente, ':u' => $idUsuario,
                    ':fe' => substr((string) $ec['fecha_recibo'], 0, 10), ':estc' => $estab, ':ptoc' => $pto, ':sec' => $sec,
                    ':num' => "$estab-$pto-$sec", ':ci' => $conImp, ':tsi' => round($tsi, 2), ':tdes' => round($tdes, 2),
                    ':tot' => (float) $ec['total_recibo'], ':prop' => (float) $ec['propina'], ':cb' => $idUsuario,
                ]);
                $idRec = (int) $insCab->fetchColumn();

                foreach ($lineas as $l) {
                    $base_i = (float) $l['subtotal'] - (float) $l['descuento'];
                    $insDet->execute([
                        ':r' => $idRec, ':prod' => $mapProd[(string) $l['id_producto']] ?? null,
                        ':bod' => ((int) $l['id_bodega'] > 0) ? ($mapBodega[(string) $l['id_bodega']] ?? null) : null,
                        ':cod' => (string) $l['codigo_producto'], ':desc' => (string) $l['nombre_producto'],
                        ':cant' => (float) $l['cantidad'], ':pu' => (float) $l['valor_unitario'], ':desc2' => (float) $l['descuento'], ':baseCol' => round($base_i, 2),
                    ]);
                    $idDet = (int) $insDet->fetchColumn();
                    $cod = trim((string) $l['tarifa_iva']);
                    $pct = self::IVA_PCT[$cod] ?? 0;
                    $insImp->execute([':d' => $idDet, ':cp' => ($cod === '' ? '0' : $cod), ':tar' => $pct, ':base' => round($base_i, 2), ':val' => round($base_i * $pct / 100, 2)]);
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idRec, ':cn' => "$estab-$pto-$sec", ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 180); }
            }
        }
        return $res;
    }

    /**
     * Verifica en la base vieja qué facturas están anuladas (estado_sri) y ANULA en el
     * sistema nuevo las que existan (sin importar cómo entraron: XML, migración, manual).
     * Empareja por NÚMERO de factura (establecimiento-punto-secuencial), no por el mapa,
     * y usa el anular() oficial (reversa inventario/asiento/cobros si los hay). Idempotente.
     */
    public function verificarAnuladasFacturas(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['anuladas_en_viejo' => 0, 'anuladas_ahora' => 0, 'ya_anuladas' => 0, 'no_estan_en_nuevo' => 0, 'errores' => 0];

        $facturaService = new \App\Services\modulos\FacturaVentaService(
            new \App\repositories\modulos\FacturaVentaRepository(),
            new \App\Rules\modulos\FacturaVentaRules(),
            new \App\Services\LogSistemaService()
        );

        $chk = $pg->prepare("SELECT id, estado FROM ventas_cabecera WHERE id_empresa = :e AND establecimiento = :est AND punto_emision = :pto AND secuencial = :sec AND eliminado = false LIMIT 1");
        $st  = $mysql->query("SELECT serie_factura, secuencial_factura FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " AND UPPER(estado_sri) LIKE '%ANULAD%'");

        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $res['anuladas_en_viejo']++;
            $partes = explode('-', trim((string) $r['serie_factura']));
            $est = str_pad($partes[0] ?? '001', 3, '0', STR_PAD_LEFT);
            $pto = str_pad($partes[1] ?? '001', 3, '0', STR_PAD_LEFT);
            $sec = str_pad(preg_replace('/\D+/', '', (string) $r['secuencial_factura']), 9, '0', STR_PAD_LEFT);

            $chk->execute([':e' => $idEmpresa, ':est' => $est, ':pto' => $pto, ':sec' => $sec]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $res['no_estan_en_nuevo']++; continue; }
            if ($row['estado'] === 'anulado') { $res['ya_anuladas']++; continue; }
            try {
                $facturaService->anular((int) $row['id'], $idEmpresa, $idUsuario, false); // sin candado SRI
                $res['anuladas_ahora']++;
            } catch (Throwable $e) {
                $res['errores']++;
            }
        }
        return $res;
    }

    /** Cláusula SQL de filtro por rango de fechas (sobre la columna de fecha del documento). */
    private function clausulaFecha(string $col, ?string $desde, ?string $hasta, PDO $mysql): string
    {
        $c = '';
        if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $c .= " AND DATE(`$col`) >= " . $mysql->quote($desde);
        }
        if ($hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $c .= " AND DATE(`$col`) <= " . $mysql->quote($hasta);
        }
        return $c;
    }

    /** Mapa identificacion => id de los clientes del sistema nuevo (prefiere el no eliminado). */
    private function clientesPorIdentificacion(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (identificacion) identificacion, id FROM clientes WHERE id_empresa = ? ORDER BY identificacion, eliminado, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['identificacion']] = (int) $r['id'];
        }
        return $m;
    }

    /** Mapa identificacion => id de los proveedores del sistema nuevo. */
    private function proveedoresPorIdentificacion(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (identificacion) identificacion, id FROM proveedores WHERE id_empresa = ? ORDER BY identificacion, eliminado, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['identificacion']] = (int) $r['id'];
        }
        return $m;
    }

    /**
     * Resuelve el id nuevo de un cliente/proveedor: primero por el mapa de migración,
     * y si no está, por identificación contra los existentes en el sistema nuevo.
     */
    private function resolverEntidadPorId(array $mapMig, array $porIdent, \PDOStatement $oldRucStmt, int $oldId): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $oldRucStmt->execute([':id' => $oldId]);
        $ruc = trim((string) $oldRucStmt->fetchColumn());
        return $ruc !== '' ? ($porIdent[$ruc] ?? null) : null;
    }

    /**
     * Resuelve el cliente de un documento: mapa → identificación → y si no existe, lo CREA
     * desde la base vieja (para que los documentos no fallen aunque no se migraron catálogos).
     * Actualiza $cliPorIdent por referencia. Devuelve id nuevo o null (si el viejo no tiene RUC).
     */
    private function resolverOCrearCliente(array &$cliPorIdent, array $mapMig, int $oldId, int $idEmpresa, int $idUsuario, PDO $mysql, PDO $pg): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $st = $mysql->prepare("SELECT ruc, nombre, tipo_id, telefono, email, direccion, plazo FROM clientes WHERE id = :id LIMIT 1");
        $st->execute([':id' => $oldId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        $ident = $c ? trim((string) $c['ruc']) : '';
        if ($ident === '') {
            return null;
        }
        if (isset($cliPorIdent[$ident])) {
            return $cliPorIdent[$ident];
        }
        $tipo   = trim((string) $c['tipo_id']) ?: self::inferirTipoId($ident);
        $nombre = trim((string) $c['nombre']) ?: $ident;
        try {
            $ins = $pg->prepare("INSERT INTO clientes (id_empresa, id_usuario, nombre, tipo_id, identificacion, telefono, email, direccion, plazo, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $nombre, $tipo, $ident, self::nz($c['telefono']), self::nz($c['email']), self::nz($c['direccion']), (int) ($c['plazo'] ?? 0), $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM clientes WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
            $q->execute([$idEmpresa, $ident]);
            $id = (int) $q->fetchColumn();
            if (!$id) { return null; }
        }
        $cliPorIdent[$ident] = $id;
        return $id;
    }

    /** Igual que resolverOCrearCliente pero para proveedores. */
    private function resolverOCrearProveedor(array &$provPorIdent, array $mapMig, int $oldId, int $idEmpresa, int $idUsuario, PDO $mysql, PDO $pg): ?int
    {
        if (isset($mapMig[(string) $oldId])) {
            return $mapMig[(string) $oldId];
        }
        $st = $mysql->prepare("SELECT ruc_proveedor, razon_social, nombre_comercial, tipo_id_proveedor, mail_proveedor, dir_proveedor, telf_proveedor FROM proveedores WHERE id_proveedor = :id LIMIT 1");
        $st->execute([':id' => $oldId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        $ident = $c ? trim((string) $c['ruc_proveedor']) : '';
        if ($ident === '') {
            return null;
        }
        if (isset($provPorIdent[$ident])) {
            return $provPorIdent[$ident];
        }
        $tipo = trim((string) $c['tipo_id_proveedor']) ?: self::inferirTipoId($ident);
        $rs   = trim((string) $c['razon_social']) ?: (trim((string) $c['nombre_comercial']) ?: $ident);
        try {
            $ins = $pg->prepare("INSERT INTO proveedores (id_empresa, id_usuario, razon_social, nombre_comercial, tipo_id_proveedor, identificacion, email, direccion, telefono, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $ins->execute([$idEmpresa, $idUsuario, $rs, self::nz($c['nombre_comercial']), $tipo, $ident, self::nz($c['mail_proveedor']), self::nz($c['dir_proveedor']), self::nz($c['telf_proveedor']), $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM proveedores WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
            $q->execute([$idEmpresa, $ident]);
            $id = (int) $q->fetchColumn();
            if (!$id) { return null; }
        }
        $provPorIdent[$ident] = $id;
        return $id;
    }

    /** Mapa codigo => id de los productos del sistema nuevo. */
    private function productosPorCodigo(PDO $pg, int $idEmpresa): array
    {
        $m = [];
        $q = $pg->prepare("SELECT DISTINCT ON (codigo) codigo, id FROM productos WHERE id_empresa = ? ORDER BY codigo, id");
        $q->execute([$idEmpresa]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['codigo']] = (int) $r['id'];
        }
        return $m;
    }

    /**
     * Resuelve el producto de una línea: mapa → código → y si no existe, lo CREA
     * (ventas_detalle.id_producto es NOT NULL). Devuelve siempre un id válido.
     */
    private function resolverOCrearProducto(array &$prodPorCod, array $mapProd, int $oldId, string $codigo, string $nombre, string $ivaCode, int $idEmpresa, int $idUsuario, PDO $pg): int
    {
        if (isset($mapProd[(string) $oldId])) {
            return $mapProd[(string) $oldId];
        }
        $codigo = trim($codigo) !== '' ? trim($codigo) : ('MIG-' . $oldId);
        if (isset($prodPorCod[$codigo])) {
            return $prodPorCod[$codigo];
        }
        $iva = in_array($ivaCode, ['0', '2', '3', '4', '5', '6', '7', '8', '10'], true) ? $ivaCode : '0';
        try {
            $ins = $pg->prepare("INSERT INTO productos (id_empresa, codigo, nombre, codigo_auxiliar, codigo_barras, precio_base, tipo_produccion, tarifa_iva, status, inventariable, id_usuario, created_by) VALUES (?, ?, ?, '', '', 0, '01', ?, 1, false, ?, ?) RETURNING id");
            $ins->execute([$idEmpresa, $codigo, ($nombre !== '' ? $nombre : $codigo), (int) $iva, $idUsuario, $idUsuario]);
            $id = (int) $ins->fetchColumn();
        } catch (Throwable $e) {
            $q = $pg->prepare("SELECT id FROM productos WHERE id_empresa = ? AND codigo = ? LIMIT 1");
            $q->execute([$idEmpresa, $codigo]);
            $id = (int) $q->fetchColumn();
            if (!$id) { throw $e; }
        }
        $prodPorCod[$codigo] = $id;
        return $id;
    }

    /** Mapa id_origen(string) => id_destino(int) de una entidad ya migrada. */
    private function mapaDe(PDO $pg, int $idEmpresa, string $entidad): array
    {
        $m = [];
        $q = $pg->prepare("SELECT id_origen, id_destino FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
        $q->execute([$idEmpresa, $entidad]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $m[(string) $r['id_origen']] = (int) $r['id_destino'];
        }
        return $m;
    }

    private function estadoFacturaSri(string $e): string
    {
        $e = strtoupper(trim($e));
        if (strpos($e, 'ANULAD') !== false) return 'anulado';
        if ($e === 'AUTORIZADO') return 'autorizado';
        return 'autorizado'; // histórico: se asume emitido
    }

    /** Transportista (get-or-create) por (id_empresa, identificacion). */
    private function getOrCreateTransportista(int $idEmpresa, int $idUsuario, string $ident, string $nombre, string $tipoId, string $placa): int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM transportistas WHERE id_empresa = ? AND identificacion = ? LIMIT 1");
        $st->execute([$idEmpresa, $ident]);
        $r = $st->fetchColumn();
        if ($r !== false) {
            return (int) $r;
        }
        $ins = $db->prepare("INSERT INTO transportistas (id_empresa, id_usuario, tipo_id, identificacion, nombre, placa, created_by) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, $idUsuario, $tipoId, $ident, $nombre, $placa, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Establecimiento (get-or-create). Réplica de DocumentoAutomatedRegisterService. */
    private function getEstablecimientoId(int $idEmpresa, string $cod, int $idUsuario): int
    {
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_establecimiento WHERE id_empresa = ? AND codigo = ? LIMIT 1");
        $st->execute([$idEmpresa, $cod]);
        $r = $st->fetchColumn();
        if ($r !== false) return (int) $r;
        $ins = $db->prepare("INSERT INTO empresa_establecimiento (id_empresa, nombre, codigo, direccion, tipo, logo_ruta, leyenda_pdf_titulo, leyenda_pdf_mensaje, created_by, updated_by) VALUES (?, ?, ?, '', 'otro', '', '', '', ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, "Establecimiento $cod", $cod, $idUsuario, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Punto de emisión (get-or-create). */
    private function getPuntoEmisionId(int $idEmpresa, string $estab, string $pto, int $idUsuario): int
    {
        $idEst = $this->getEstablecimientoId($idEmpresa, $estab, $idUsuario);
        $db = Database::getConnection();
        $st = $db->prepare("SELECT id FROM empresa_punto_emision WHERE id_establecimiento = ? AND codigo_punto = ? LIMIT 1");
        $st->execute([$idEst, $pto]);
        $r = $st->fetchColumn();
        if ($r !== false) return (int) $r;
        $ins = $db->prepare("INSERT INTO empresa_punto_emision (id_empresa, id_establecimiento, nombre, codigo_punto, logo_ruta, estado, created_by, updated_by) VALUES (?, ?, ?, ?, '', 'activo', ?, ?) RETURNING id");
        $ins->execute([$idEmpresa, $idEst, "Punto $pto", $pto, $idUsuario, $idUsuario]);
        return (int) $ins->fetchColumn();
    }

    /** Ids ya migrados de una entidad (anti-reproceso). */
    private function idsMigrados(PDO $pg, int $idEmpresa, string $entidad): array
    {
        $done = [];
        $q = $pg->prepare("SELECT id_origen FROM migracion_mysql_map WHERE id_empresa = ? AND entidad = ?");
        $q->execute([$idEmpresa, $entidad]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $o) {
            $done[(string) $o] = true;
        }
        return $done;
    }

    /** Prepared statement de inserción en el mapa (idempotente). */
    private function stmtMap(PDO $pg, string $entidad): \PDOStatement
    {
        return $pg->prepare(
            "INSERT INTO migracion_mysql_map (id_empresa, entidad, id_origen, id_destino, clave_natural, vinculado, created_by)
             VALUES (:e, " . $pg->quote($entidad) . ", :o, :d, :cn, :vin, :cb) ON CONFLICT (id_empresa, entidad, id_origen) DO NOTHING"
        );
    }

    /** Fecha a 'Y-m-d' o null (descarta ceros / vacíos). */
    private static function fechaCorta($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || strpos($v, '0000') === 0) {
            return null;
        }
        return substr($v, 0, 10);
    }

    /** Cadena vacía -> null (para columnas nullable). */
    private static function nz($v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    /** Infiere el tipo de identificación SRI a partir del número. */
    private static function inferirTipoId(string $ident): string
    {
        $d = preg_replace('/\D+/', '', $ident);
        if (strlen($d) === 13) return '04'; // RUC
        if (strlen($d) === 10) return '05'; // Cédula
        return '06';                         // Pasaporte / otro
    }
}
