<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Acceso a datos para RESTAURAR (des-eliminar) comprobantes electrónicos eliminados en
 * borrador, con su mismo número. Lo usa App\Services\DocumentoSriRestauracionService.
 *
 * Los nombres de tabla nunca vienen del usuario: salen de la whitelist TIPOS del servicio.
 */
class DocumentoSriRestauracionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Cabecera de un documento ELIMINADO de la empresa; null si no existe, es de otra o no está eliminado. */
    public function getEliminado(string $tabla, int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, id_empresa, id_punto_emision, establecimiento, punto_emision, secuencial,
                    fecha_emision, estado, COALESCE(tipo_ambiente, '1') AS tipo_ambiente,
                    created_by, deleted_at, deleted_by
               FROM {$tabla}
              WHERE id = ? AND id_empresa = ? AND eliminado = TRUE"
        );
        $st->execute([$id, $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** ¿Otro documento vigente del mismo punto y ambiente ya usa este secuencial? */
    public function secuencialOcupado(string $tabla, int $idPunto, string $secuencial, string $tipoAmbiente, int $excluirId): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM {$tabla}
              WHERE id_punto_emision = ? AND TRIM(secuencial) = TRIM(?) AND eliminado = FALSE
                AND COALESCE(tipo_ambiente, '1') = ? AND id <> ?
              LIMIT 1"
        );
        $st->execute([$idPunto, $secuencial, $tipoAmbiente, $excluirId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * ¿Al eliminar el documento se revirtió inventario? El borrado da de baja sus movimientos
     * del kardex (InventarioService::revertirMovimientosPorReferencia) en la MISMA transacción,
     * así que su deleted_at coincide con el del documento. Restaurar solo la cabecera dejaría
     * el stock desfasado, por eso esos documentos no se restauran.
     *
     * No cuentan los movimientos dados de baja en otro momento (p. ej. al reabrir una factura
     * autorizada) ni los que siguen vigentes: no son algo que el borrado haya deshecho.
     * Si el documento no tiene deleted_at (datos viejos), cuenta cualquier baja: conservador.
     *
     * @param string[] $tiposReferencia referencia_tipo del kardex que usa el documento
     */
    public function inventarioRevertidoAlEliminar(array $tiposReferencia, int $id, int $idEmpresa, ?string $deletedAtDoc): bool
    {
        if ($tiposReferencia === [] || !$this->existeTabla('inventario_kardex')) {
            return false;
        }
        $ph     = implode(',', array_fill(0, count($tiposReferencia), '?'));
        $params = [...array_values($tiposReferencia), $id, $idEmpresa];
        $ventana = '';
        if ($deletedAtDoc !== null && $deletedAtDoc !== '') {
            $ventana  = " AND deleted_at BETWEEN CAST(? AS timestamp) - INTERVAL '2 minutes' AND CAST(? AS timestamp) + INTERVAL '2 minutes'";
            $params[] = $deletedAtDoc;
            $params[] = $deletedAtDoc;
        }
        $st = $this->db->prepare(
            "SELECT 1 FROM inventario_kardex
              WHERE referencia_tipo IN ({$ph}) AND referencia_id = ? AND id_empresa = ?
                AND eliminado = TRUE{$ventana}
              LIMIT 1"
        );
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /** ¿Existe alguna fila en $tabla con $columna = $id (y la empresa)? Para vínculos que el borrado deshizo. */
    public function existeVinculo(string $tabla, string $columna, int $id, int $idEmpresa, string $extra = ''): bool
    {
        if (!$this->existeTabla($tabla)) {
            return false;
        }
        $st = $this->db->prepare("SELECT 1 FROM {$tabla} WHERE {$columna} = ? AND id_empresa = ? {$extra} LIMIT 1");
        $st->execute([$id, $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** Deshace la eliminación lógica. False si el documento ya no estaba eliminado. */
    public function restaurar(string $tabla, int $id, int $idEmpresa, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE {$tabla}
                SET eliminado = FALSE, deleted_at = NULL, deleted_by = NULL,
                    updated_by = ?, updated_at = CURRENT_TIMESTAMP
              WHERE id = ? AND id_empresa = ? AND eliminado = TRUE"
        );
        $st->execute([$idUsuario, $id, $idEmpresa]);
        return $st->rowCount() > 0;
    }

    public function existeTabla(string $tabla): bool
    {
        static $cache = [];
        if (!array_key_exists($tabla, $cache)) {
            $st = $this->db->prepare('SELECT to_regclass(?) IS NOT NULL');
            $st->execute(['public.' . $tabla]);
            $cache[$tabla] = (bool) $st->fetchColumn();
        }
        return $cache[$tabla];
    }
}
