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
