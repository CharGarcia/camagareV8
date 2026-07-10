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
    /** Entidades migrables: clave => [label, tabla origen en MySQL]. Todas filtran por ruc_empresa. */
    public const ENTIDADES = [
        'clientes'          => ['label' => 'Clientes',                        'tabla' => 'clientes'],
        'productos'         => ['label' => 'Productos y servicios',           'tabla' => 'productos_servicios'],
        'proveedores'       => ['label' => 'Proveedores',                     'tabla' => 'proveedores'],
        'vendedores'        => ['label' => 'Vendedores',                      'tabla' => 'vendedores'],
        'bodegas'           => ['label' => 'Bodegas',                         'tabla' => 'bodega'],
        'facturas'          => ['label' => 'Facturas de venta',               'tabla' => 'encabezado_factura'],
        'notas_credito'     => ['label' => 'Notas de crédito',                'tabla' => 'encabezado_nc'],
        'retenciones_venta' => ['label' => 'Retenciones en venta',            'tabla' => 'encabezado_retencion_venta'],
        'retenciones_compra' => ['label' => 'Retenciones en compra',          'tabla' => 'encabezado_retencion'],
        'compras'           => ['label' => 'Compras',                         'tabla' => 'encabezado_compra'],
        'ingresos_egresos'  => ['label' => 'Cobros y pagos (ingresos/egresos)','tabla' => 'ingresos_egresos'],
    ];

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
            $fila = ['label' => $def['label'], 'tabla' => $def['tabla'], 'total' => null, 'error' => null];
            try {
                $st = $pdo->prepare("SELECT COUNT(*) FROM `{$def['tabla']}` WHERE LEFT(ruc_empresa, 10) = :b");
                $st->execute([':b' => $base]);
                $fila['total'] = (int) $st->fetchColumn();
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
    public function migrar(string $entidad, int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0): array
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
                return $this->migrarFacturas($idEmpresa, $ruc, $idUsuario, $limite);
            case 'compras':
                return $this->migrarCompras($idEmpresa, $ruc, $idUsuario, $limite);
            case 'notas_credito':
                return $this->migrarNotasCredito($idEmpresa, $ruc, $idUsuario, $limite);
            case 'retenciones_compra':
                return $this->migrarRetencionesCompra($idEmpresa, $ruc, $idUsuario, $limite);
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

    /**
     * Migra facturas de venta (cabecera + detalle + impuestos) resolviendo cliente/producto/
     * bodega vía el mapa. Requiere clientes/productos migrados. $limite>0 = solo para pruebas.
     */
    private function migrarFacturas(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0): array
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

        $cuerpoStmt = $mysql->prepare(
            "SELECT id_producto, cantidad_factura, valor_unitario_factura, subtotal_factura, descuento, tarifa_iva, codigo_producto, nombre_producto, id_bodega
               FROM cuerpo_factura WHERE ruc_empresa = :r AND serie_factura = :s AND secuencial_factura = :sec"
        );

        $sql = "SELECT id_encabezado_factura, ruc_empresa, fecha_factura, serie_factura, secuencial_factura, id_cliente, observaciones_factura, estado_sri, total_factura, ambiente, aut_sri, propina
                  FROM encabezado_factura WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_encabezado_factura";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ef = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ef['id_encabezado_factura'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }

            $idCliente = $mapCliente[(string) $ef['id_cliente']] ?? null;
            if (!$idCliente) { $res['omitidos']++; continue; } // cliente no migrado

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
                        'id_producto' => $mapProd[(string) $l['id_producto']] ?? null,
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
    private function migrarCompras(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'compras', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done     = $this->idsMigrados($pg, $idEmpresa, 'compras');
        $mapProv  = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap   = $this->stmtMap($pg, 'compras');

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
                  FROM encabezado_compra WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_encabezado_compra";
        if ($limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_compra'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idProv = $mapProv[(string) $ec['id_proveedor']] ?? null;
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
    private function migrarNotasCredito(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'notas_credito', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done       = $this->idsMigrados($pg, $idEmpresa, 'notas_credito');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $insMap     = $this->stmtMap($pg, 'notas_credito');

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
                  FROM encabezado_nc WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_encabezado_nc";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_nc'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idCliente = $mapCliente[(string) $ec['id_cliente']] ?? null;
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
    private function migrarRetencionesCompra(int $idEmpresa, string $ruc, int $idUsuario, int $limite = 0): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();

        $res = ['entidad' => 'retenciones_compra', 'total' => 0, 'migrados' => 0, 'ya_migrados' => 0, 'omitidos' => 0, 'errores' => 0];
        $done    = $this->idsMigrados($pg, $idEmpresa, 'retenciones_compra');
        $mapProv = $this->mapaDe($pg, $idEmpresa, 'proveedores');
        $insMap  = $this->stmtMap($pg, 'retenciones_compra');

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
                  FROM encabezado_retencion WHERE LEFT(ruc_empresa, 10) = " . $mysql->quote($base) . " ORDER BY id_encabezado_retencion";
        if ($limite > 0) { $sql .= " LIMIT " . (int) $limite; }
        $stmt = $mysql->query($sql);

        while ($ec = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $res['total']++;
            $old = (int) $ec['id_encabezado_retencion'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $idProv = $mapProv[(string) $ec['id_proveedor']] ?? null;
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
