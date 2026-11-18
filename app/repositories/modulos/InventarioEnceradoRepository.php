<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * «Encerar existencias» de Movimientos de Inventario: deja en cero, a una fecha de corte, el
 * saldo de cada producto / bodega / lote / NUP. Cabecera en `inventario_encerados`; los
 * movimientos que compensan cada saldo viven en `inventario_kardex` con
 * referencia_tipo = 'encerado_inventario' y referencia_id = id de la cabecera.
 * SQL: database/2026-11-18_inventario_encerados.sql.
 */
class InventarioEnceradoRepository extends BaseRepository
{
    public const REFERENCIA_TIPO = 'encerado_inventario';

    public function __construct()
    {
        parent::__construct('inventario_encerados');
    }

    public function tablaDisponible(): bool
    {
        return $this->tablaExiste('inventario_encerados');
    }

    /** Lista de ids para un parámetro `CAST(:x AS integer[])`. */
    private static function arrayPg(array $ids): string
    {
        return '{' . implode(',', array_map('intval', $ids)) . '}';
    }

    /**
     * Saldo a la fecha de corte (incluida) de cada producto / bodega / lote / NUP que no está
     * en cero, en el ambiente actual de la empresa. Mismo criterio de lote que getStockLote():
     * vacío y 'sin_lote' cuentan como «sin lote». Trae también el saldo total del par
     * producto/bodega y su costo promedio de entradas a esa fecha (al que se valora el ajuste).
     *
     * @param int[]|null $idsBodegas null = todas las bodegas de la empresa.
     */
    public function getSaldosAFecha(int $idEmpresa, ?array $idsBodegas, string $fechaCorte): array
    {
        $params = [':e' => $idEmpresa, ':e_amb' => $idEmpresa, ':fecha' => $fechaCorte];
        $whereBodega = '';
        if ($idsBodegas !== null) {
            $whereBodega = ' AND k.id_bodega = ANY(CAST(:bods AS integer[]))';
            $params[':bods'] = self::arrayPg($idsBodegas);
        }

        $sql = "WITH k AS (
                    SELECT k.id_producto, k.id_bodega,
                           NULLIF(NULLIF(TRIM(k.numero_lote), ''), 'sin_lote') AS lote,
                           NULLIF(TRIM(k.nup), '') AS nup,
                           k.cantidad, k.costo_total, k.tipo_movimiento, k.fecha_caducidad
                      FROM inventario_kardex k
                     WHERE k.id_empresa = :e
                       AND k.eliminado = false
                       AND k.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e_amb)
                       AND k.fecha_movimiento < CAST(:fecha AS date) + 1
                       {$whereBodega}
                ),
                g AS (
                    SELECT id_producto, id_bodega, lote, nup,
                           ROUND(SUM(cantidad)::numeric, 4) AS saldo,
                           MAX(fecha_caducidad) AS fecha_caducidad
                      FROM k
                  GROUP BY id_producto, id_bodega, lote, nup
                    HAVING ROUND(SUM(cantidad)::numeric, 4) <> 0
                ),
                c AS (
                    SELECT id_producto, id_bodega,
                           CASE WHEN SUM(cantidad) FILTER (WHERE tipo_movimiento = 'entrada' AND cantidad > 0) > 0
                                THEN ROUND(SUM(costo_total) FILTER (WHERE tipo_movimiento = 'entrada' AND cantidad > 0)::numeric
                                         / SUM(cantidad) FILTER (WHERE tipo_movimiento = 'entrada' AND cantidad > 0)::numeric, 6)
                                ELSE 0 END AS costo_promedio
                      FROM k
                     WHERE (id_producto, id_bodega) IN (SELECT id_producto, id_bodega FROM g)
                  GROUP BY id_producto, id_bodega
                )
                SELECT g.id_producto, g.id_bodega, g.lote, g.nup, g.saldo, g.fecha_caducidad,
                       COALESCE(c.costo_promedio, 0) AS costo_promedio,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       b.nombre AS bodega_nombre
                  FROM g
             LEFT JOIN c ON c.id_producto = g.id_producto AND c.id_bodega = g.id_bodega
             LEFT JOIN productos p ON p.id = g.id_producto
             LEFT JOIN bodegas b   ON b.id = g.id_bodega
              ORDER BY b.nombre, g.id_bodega, p.nombre, g.id_producto, g.lote NULLS FIRST, g.nup NULLS FIRST";

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Crea la cabecera (con el ambiente actual de la empresa) y devuelve su id. */
    public function insertCabecera(array $d): int
    {
        $sql = "INSERT INTO inventario_encerados
                    (id_empresa, fecha_corte, todas_bodegas, ids_bodegas, observacion, tipo_ambiente,
                     estado, created_by, updated_by)
                VALUES
                    (:e, :fecha, :todas, CAST(:bods AS integer[]), :obs,
                     (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e_amb),
                     'aplicado', :uid, :uid2)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $d['id_empresa'],
            ':e_amb' => $d['id_empresa'],
            ':fecha' => $d['fecha_corte'],
            ':todas' => !empty($d['todas_bodegas']) ? 'true' : 'false',
            ':bods'  => self::arrayPg($d['ids_bodegas'] ?? []),
            ':obs'   => $d['observacion'],
            ':uid'   => $d['id_usuario'],
            ':uid2'  => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarTotales(int $id, int $idEmpresa, array $t): void
    {
        $sql = "UPDATE inventario_encerados
                   SET total_movimientos = :mov, total_productos = :prod,
                       unidades_salida = :us, unidades_entrada = :ue,
                       valor_salida = :vs, valor_entrada = :ve,
                       ids_bodegas = CAST(:bods AS integer[]),
                       updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':mov'  => $t['total_movimientos'],
            ':prod' => $t['total_productos'],
            ':us'   => $t['unidades_salida'],
            ':ue'   => $t['unidades_entrada'],
            ':vs'   => $t['valor_salida'],
            ':ve'   => $t['valor_entrada'],
            ':bods' => self::arrayPg($t['ids_bodegas']),
            ':id'   => $id,
            ':e'    => $idEmpresa,
        ]);
    }

    /** Cabecera vigente; con $bloquear, FOR UPDATE (dos reversiones simultáneas no se pisan). */
    public function getById(int $id, int $idEmpresa, bool $bloquear = false): ?array
    {
        $sql = "SELECT * FROM inventario_encerados
                 WHERE id = :id AND id_empresa = :e AND eliminado = false"
             . ($bloquear ? ' FOR UPDATE' : '');
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Historial de encerados de la empresa (del ambiente actual), el más reciente primero. */
    public function listar(int $idEmpresa, int $limite = 100): array
    {
        $sql = "SELECT ie.id, ie.fecha_corte, ie.todas_bodegas, ie.ids_bodegas::text AS ids_bodegas,
                       ie.observacion, ie.estado, ie.total_movimientos, ie.total_productos,
                       ie.unidades_salida, ie.unidades_entrada, ie.valor_salida, ie.valor_entrada,
                       ie.created_at, ie.revertido_at, ie.motivo_reversion,
                       uc.nombre AS creado_por, ur.nombre AS revertido_por,
                       (SELECT string_agg(b.nombre, ', ' ORDER BY b.nombre)
                          FROM bodegas b WHERE b.id = ANY(ie.ids_bodegas)) AS bodegas_nombres
                  FROM inventario_encerados ie
             LEFT JOIN usuarios uc ON uc.id = ie.created_by
             LEFT JOIN usuarios ur ON ur.id = ie.revertido_by
                 WHERE ie.id_empresa = :e AND ie.eliminado = false
                   AND ie.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :e_amb)
              ORDER BY ie.id DESC
                 LIMIT " . max(1, min(500, $limite));
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':e_amb' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Encerado vigente creado DESPUÉS de este que comparte alguna bodega (o es de todas).
     * Mientras exista, este no se puede revertir: el posterior se calculó contando con él.
     */
    public function getPosteriorVigente(array $cab, int $idEmpresa): ?array
    {
        $sql = "SELECT id, fecha_corte FROM inventario_encerados
                 WHERE id_empresa = :e AND eliminado = false AND estado = 'aplicado'
                   AND id > :id AND tipo_ambiente = :amb
                   AND (todas_bodegas OR CAST(:todas AS boolean) OR ids_bodegas && CAST(:bods AS integer[]))
              ORDER BY id DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $idEmpresa,
            ':id'    => (int) $cab['id'],
            ':amb'   => (string) $cab['tipo_ambiente'],
            ':todas' => \App\Helpers\Booleano::es($cab['todas_bodegas']) ? 'true' : 'false',
            ':bods'  => (string) $cab['ids_bodegas'],
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Pares producto/bodega con movimientos vigentes de un encerado. */
    public function getParesMovimientos(int $id, int $idEmpresa): array
    {
        $sql = "SELECT DISTINCT id_producto, id_bodega
                  FROM inventario_kardex
                 WHERE id_empresa = :e AND referencia_tipo = :rt AND referencia_id = :id AND eliminado = false
              ORDER BY id_producto, id_bodega";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':rt' => self::REFERENCIA_TIPO, ':id' => $id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Anula (eliminación lógica) todos los movimientos vigentes del encerado. Devuelve cuántos. */
    public function anularMovimientos(int $id, int $idEmpresa, int $idUsuario): int
    {
        $sql = "UPDATE inventario_kardex
                   SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :uid,
                       observaciones = COALESCE(NULLIF(TRIM(observaciones), '') || ' — ANULADO', 'Movimiento anulado')
                 WHERE id_empresa = :e AND referencia_tipo = :rt AND referencia_id = :id AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':e' => $idEmpresa, ':rt' => self::REFERENCIA_TIPO, ':id' => $id]);
        return $st->rowCount();
    }

    public function marcarRevertido(int $id, int $idEmpresa, int $idUsuario, string $motivo): void
    {
        $sql = "UPDATE inventario_encerados
                   SET estado = 'revertido', revertido_at = CURRENT_TIMESTAMP, revertido_by = :uid,
                       motivo_reversion = :motivo, updated_at = CURRENT_TIMESTAMP, updated_by = :uid2
                 WHERE id = :id AND id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':uid' => $idUsuario, ':uid2' => $idUsuario, ':motivo' => $motivo, ':id' => $id, ':e' => $idEmpresa]);
    }
}
