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
        'facturas'          => ['label' => 'Facturas de venta',               'tabla' => 'encabezado_factura'],
        'notas_credito'     => ['label' => 'Notas de crédito',                'tabla' => 'encabezado_nc'],
        'retenciones_venta' => ['label' => 'Retenciones en venta',            'tabla' => 'encabezado_retencion_venta'],
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
    public function migrar(string $entidad, int $idEmpresa, string $ruc, int $idUsuario): array
    {
        switch ($entidad) {
            case 'clientes':
                return $this->migrarClientes($idEmpresa, $ruc, $idUsuario);
            case 'productos':
                return $this->migrarProductos($idEmpresa, $ruc, $idUsuario);
            case 'proveedores':
                return $this->migrarProveedores($idEmpresa, $ruc, $idUsuario);
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
