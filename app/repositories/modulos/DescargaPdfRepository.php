<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Consultas de la pestaña «Desde PDF» de Descargas del SRI: comprobar que el
 * comprobante leído no exista ya en el sistema y proponer el código de
 * retención del catálogo global `retenciones_sri` (el RIDE no lo imprime).
 */
class DescargaPdfRepository extends BaseRepository
{
    /**
     * Dónde vive cada comprobante según quién lo emitió.
     * clave: columna de la clave de acceso; estab/pto/sec: columnas del número;
     * tercero: [tabla, FK] del emisor cuando el documento es recibido.
     */
    private const TABLAS = [
        'emitido' => [
            '01' => ['tabla' => 'ventas_cabecera', 'clave' => 'clave_acceso'],
            '03' => ['tabla' => 'liquidaciones_cabecera', 'clave' => 'clave_acceso'],
            '04' => ['tabla' => 'notas_credito_cabecera', 'clave' => 'clave_acceso'],
            '05' => ['tabla' => 'nota_debito_cabecera', 'clave' => 'clave_acceso'],
            '07' => ['tabla' => 'retencion_compra_cabecera', 'clave' => 'clave_acceso'],
        ],
        'recibido' => [
            '01' => ['tabla' => 'compras_cabecera', 'clave' => 'numero_autorizacion', 'compra' => true],
            '03' => ['tabla' => 'compras_cabecera', 'clave' => 'numero_autorizacion', 'compra' => true],
            '04' => ['tabla' => 'compras_cabecera', 'clave' => 'numero_autorizacion', 'compra' => true],
            '05' => ['tabla' => 'compras_cabecera', 'clave' => 'numero_autorizacion', 'compra' => true],
            '07' => ['tabla' => 'retencion_venta_cabecera', 'clave' => 'clave_acceso', 'tercero' => ['clientes', 'id_cliente']],
        ],
    ];

    public function __construct()
    {
        parent::__construct('compras_cabecera');
    }

    /**
     * Busca el comprobante en la tabla que le corresponde, primero por clave de
     * acceso y después por número (emisor + serie + secuencial), para atrapar
     * también los que se digitaron a mano sin clave.
     *
     * Operativo: filtra por las empresas del mismo RUC, `eliminado = false` y el
     * ambiente activo de la empresa (mismo criterio que el registro desde XML).
     *
     * @param int[] $idsEmpresa Empresas que comparten el RUC (incluida la activa).
     * @return array{id:int, por:string}|null
     */
    public function buscarComprobante(
        string $rol,
        string $codDoc,
        string $claveAcceso,
        string $rucEmisor,
        string $estab,
        string $ptoEmi,
        string $secuencial,
        array $idsEmpresa,
        string $ambiente
    ): ?array {
        $def = self::TABLAS[$rol][$codDoc] ?? null;
        $idsEmpresa = array_values(array_filter(array_map('intval', $idsEmpresa)));
        if ($def === null || $idsEmpresa === []) {
            return null;
        }
        $t = $def['tabla'];
        $in = implode(',', array_fill(0, count($idsEmpresa), '?'));
        $amb = $this->columnaExiste($t, 'tipo_ambiente') ? ' AND d.tipo_ambiente = ?' : '';
        $pAmb = $amb !== '' ? [$ambiente] : [];

        if ($claveAcceso !== '') {
            $st = $this->db->prepare(
                "SELECT d.id FROM {$t} d
                  WHERE d.{$def['clave']} = ? AND d.id_empresa IN ({$in}) AND d.eliminado = false{$amb}
                  LIMIT 1"
            );
            $st->execute(array_merge([$claveAcceso], $idsEmpresa, $pAmb));
            $id = $st->fetchColumn();
            if ($id) {
                return ['id' => (int) $id, 'por' => 'clave'];
            }
        }

        if ($estab === '' || $ptoEmi === '' || $secuencial === '') {
            return null;
        }
        $num = [(int) $estab, (int) $ptoEmi, (int) $secuencial];
        // Los números se comparan como enteros: hay registros manuales sin ceros a la izquierda.
        $entero = static fn(string $col): string => "NULLIF(regexp_replace(d.{$col}::text, '\\D', '', 'g'), '')::bigint";

        if (!empty($def['compra'])) {
            $sql = "SELECT d.id FROM compras_cabecera d
                      JOIN proveedores p ON p.id = d.id_proveedor
                     WHERE d.id_empresa IN ({$in}) AND d.eliminado = false{$amb}
                       AND d.tipo_comprobante = ? AND p.identificacion = ?
                       AND {$entero('establecimiento_prov')} = ? AND {$entero('punto_emision_prov')} = ?
                       AND {$entero('secuencial_prov')} = ?
                     LIMIT 1";
            $params = array_merge($idsEmpresa, $pAmb, [$codDoc, $rucEmisor], $num);
        } elseif (!empty($def['tercero'])) {
            [$tabTercero, $fk] = $def['tercero'];
            $sql = "SELECT d.id FROM {$t} d
                      JOIN {$tabTercero} x ON x.id = d.{$fk}
                     WHERE d.id_empresa IN ({$in}) AND d.eliminado = false{$amb}
                       AND x.identificacion = ?
                       AND {$entero('establecimiento')} = ? AND {$entero('punto_emision')} = ?
                       AND {$entero('secuencial')} = ?
                     LIMIT 1";
            $params = array_merge($idsEmpresa, $pAmb, [$rucEmisor], $num);
        } else {
            $sql = "SELECT d.id FROM {$t} d
                     WHERE d.id_empresa IN ({$in}) AND d.eliminado = false{$amb}
                       AND {$entero('establecimiento')} = ? AND {$entero('punto_emision')} = ?
                       AND {$entero('secuencial')} = ?
                     LIMIT 1";
            $params = array_merge($idsEmpresa, $pAmb, $num);
        }

        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $id = $st->fetchColumn();
            return $id ? ['id' => (int) $id, 'por' => 'numero'] : null;
        } catch (\PDOException $e) {
            // Una tabla sin esas columnas no debe impedir la carga: la clave ya se revisó.
            return null;
        }
    }

    /**
     * Candado de registro de comprobantes por RUC (namespace 815, el mismo de la
     * carga de XML y del agente): un solo flujo de registro a la vez por
     * contribuyente. Es de sesión porque el registro abre sus propias transacciones;
     * hay que soltarlo siempre con soltarCandadoRegistro().
     */
    public function tomarCandadoRegistro(string $ruc): bool
    {
        $st = $this->db->prepare('SELECT pg_try_advisory_lock(?, hashtext(?))');
        $st->execute([815, $ruc]);
        return (bool) $st->fetchColumn();
    }

    public function soltarCandadoRegistro(string $ruc): void
    {
        $this->db->prepare('SELECT pg_advisory_unlock(?, hashtext(?))')->execute([815, $ruc]);
    }

    /**
     * Códigos del catálogo global `retenciones_sri` para un impuesto y un
     * porcentaje (sin `id_empresa`: es configuración global).
     *
     * @return list<array{codigo:string, concepto:string}>
     */
    public function opcionesRetencion(string $impuesto, float $porcentaje): array
    {
        $st = $this->db->prepare(
            "SELECT codigo_ret, concepto_ret, porcentaje_ret
               FROM retenciones_sri
              WHERE UPPER(TRIM(impuesto_ret)) = ?
              ORDER BY codigo_ret"
        );
        $st->execute([strtoupper(trim($impuesto))]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $cod = trim((string) $f['codigo_ret']);
            if ($cod === '' || isset($out[$cod])) {
                continue;
            }
            if (abs((float) $f['porcentaje_ret'] - $porcentaje) > 0.001) {
                continue;
            }
            $out[$cod] = ['codigo' => $cod, 'concepto' => mb_substr(trim((string) $f['concepto_ret']), 0, 160)];
        }
        return array_values($out);
    }
}
