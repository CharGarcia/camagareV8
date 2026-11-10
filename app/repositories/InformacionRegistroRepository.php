<?php

declare(strict_types=1);

namespace App\repositories;

use App\core\Database;
use PDO;

/**
 * Consultas de la pestaña «Información» de los modales (App\Helpers\InformacionRegistro).
 * Solo lectura. El nombre de la tabla llega siempre del catálogo del helper, nunca del usuario.
 */
class InformacionRegistroRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Quién y cuándo creó y modificó el registro.
     *
     * @param int|null $idEmpresa       null solo para tablas globales (sin id_empresa).
     * @param int|null $idUsuarioFiltro registros propios (§6): si llega, el registro debe ser suyo.
     */
    public function getDatosRegistro(string $tabla, int $id, ?int $idEmpresa, ?int $idUsuarioFiltro = null): ?array
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $tabla)) {
            throw new \InvalidArgumentException('Tabla no válida para la pestaña Información.');
        }

        $where  = 't.id = :id';
        $params = [':id' => $id];
        if ($idEmpresa !== null) {
            $where .= ' AND t.id_empresa = :e';
            $params[':e'] = $idEmpresa;
        }
        if ($idUsuarioFiltro !== null) {
            $where .= ' AND t.created_by = :u';
            $params[':u'] = $idUsuarioFiltro;
        }

        $sql = "SELECT t.created_at, t.updated_at,
                       uc.nombre AS creado_por, uu.nombre AS modificado_por
                  FROM {$tabla} t
                  LEFT JOIN usuarios uc ON uc.id = t.created_by
                  LEFT JOIN usuarios uu ON uu.id = t.updated_by
                 WHERE {$where}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Movimientos de inventario que generó el documento, INCLUIDOS los anulados (con quién y
     * cuándo los anuló): es el rastro completo, no el stock vigente.
     *
     * @param string[]   $referenciasTipo valores de inventario_kardex.referencia_tipo del documento.
     * @param array|null $detalle         ['tabla' => ..., 'fk' => ...] cuando el kardex referencia las
     *                                    LÍNEAS del documento (p. ej. compras_detalle) y no la cabecera.
     */
    public function getMovimientosInventario(array $referenciasTipo, int $idReferencia, int $idEmpresa, ?array $detalle = null): array
    {
        $condReferencia = 'k.referencia_id = :id';
        if ($detalle !== null) {
            $tablaDet = (string) ($detalle['tabla'] ?? '');
            $fk       = (string) ($detalle['fk'] ?? '');
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $tablaDet) || !preg_match('/^[a-z_][a-z0-9_]*$/', $fk)) {
                throw new \InvalidArgumentException('Detalle de kardex no válido para la pestaña Información.');
            }
            $condReferencia = "k.referencia_id IN (SELECT d.id FROM {$tablaDet} d WHERE d.{$fk} = :id)";
        }

        $tipos = array_values(array_filter(array_map('strval', $referenciasTipo), static fn(string $t) => $t !== ''));
        if (empty($tipos)) {
            return [];
        }
        $params = [':e' => $idEmpresa, ':id' => $idReferencia];
        $in = [];
        foreach ($tipos as $i => $tipo) {
            $in[] = ":t{$i}";
            $params[":t{$i}"] = $tipo;
        }

        $sql = "SELECT k.id, k.created_at, k.tipo_movimiento,
                       p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                       b.nombre AS bodega_nombre, k.numero_lote, k.nup, k.cantidad,
                       u.nombre AS usuario_nombre,
                       k.eliminado, k.deleted_at, ua.nombre AS anulado_por
                  FROM inventario_kardex k
                  LEFT JOIN productos p ON p.id = k.id_producto
                  LEFT JOIN bodegas   b ON b.id = k.id_bodega
                  LEFT JOIN usuarios  u ON u.id = k.created_by
                  LEFT JOIN usuarios ua ON ua.id = k.deleted_by
                 WHERE k.id_empresa = :e AND {$condReferencia}
                   AND k.referencia_tipo IN (" . implode(', ', $in) . ")
                 ORDER BY k.created_at ASC, k.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
