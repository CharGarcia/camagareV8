<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\FiltrosBusqueda;
use App\repositories\BaseRepository;
use PDO;

/**
 * Configuración del módulo Producción (una fila por empresa) y catálogos de apoyo
 * (bodegas, cuentas contables). Acceso a datos puro.
 *
 * El módulo es independiente del resto del sistema (docs/diseno/produccion-fase1.md §10.4):
 * si las tablas produccion_* no existen, instalado() responde false y nada se ejecuta.
 */
class ProduccionConfiguracionRepository extends BaseRepository
{
    public const CAMPOS_CUENTA = [
        'id_cuenta_materia_prima',
        'id_cuenta_en_proceso',
        'id_cuenta_terminado',
        'id_cuenta_mano_obra',
        'id_cuenta_cif',
        'id_cuenta_merma',
    ];

    public function __construct()
    {
        parent::__construct('produccion_configuracion');
    }

    /** ¿Ya se aplicó database/2026-11-03_produccion.sql? */
    public function instalado(): bool
    {
        return $this->tablaExiste('produccion_configuracion') && $this->tablaExiste('produccion_ordenes');
    }

    public function getPorEmpresa(int $idEmpresa): ?array
    {
        $st = $this->db->prepare("SELECT * FROM produccion_configuracion WHERE id_empresa = :e AND eliminado = false LIMIT 1");
        $st->execute([':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(array $d, int $idEmpresa, int $idUsuario): int
    {
        $cols = array_keys($d);
        $sql = 'INSERT INTO produccion_configuracion (id_empresa, ' . implode(', ', $cols) . ', created_by, updated_by)
                VALUES (:id_empresa, :' . implode(', :', $cols) . ', :uid, :uid) RETURNING id';
        $st = $this->db->prepare($sql);
        $params = [':id_empresa' => $idEmpresa, ':uid' => $idUsuario];
        foreach ($d as $k => $v) {
            $params[':' . $k] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
        }
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $sets = [];
        $params = [':id' => $id, ':e' => $idEmpresa, ':uid' => $idUsuario];
        foreach ($d as $k => $v) {
            $sets[] = "{$k} = :{$k}";
            $params[':' . $k] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
        }
        $sql = 'UPDATE produccion_configuracion SET ' . implode(', ', $sets) . ',
                       updated_by = :uid, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND id_empresa = :e AND eliminado = false';
        $this->db->prepare($sql)->execute($params);
    }

    /** Bodegas activas de la empresa con su establecimiento. */
    public function getBodegas(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT b.id, b.nombre, b.id_establecimiento, ee.codigo AS establecimiento_codigo
               FROM bodegas b
               LEFT JOIN empresa_establecimiento ee ON ee.id = b.id_establecimiento
              WHERE b.id_empresa = :e AND b.eliminado = false AND COALESCE(b.status, true) = true
              ORDER BY b.nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuentas de movimiento (nivel 5) para el buscador de la configuración. */
    public function buscarCuentas(int $idEmpresa, string $q): array
    {
        $params = [':e' => $idEmpresa];
        $where  = "WHERE id_empresa = :e AND eliminado = false AND nivel = '5'";
        $cond = FiltrosBusqueda::condicionTexto(['codigo', 'nombre'], $q, $params, 'cta');
        if ($cond !== '') {
            $where .= " AND {$cond}";
        }
        $st = $this->db->prepare("SELECT id, codigo, nombre FROM plan_cuentas {$where} ORDER BY codigo LIMIT 30");
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Código y nombre de las cuentas configuradas (para mostrarlas como etiqueta).
     *
     * @param int[] $ids
     * @return array<int, array{id:int, codigo:string, nombre:string}>
     */
    public function getCuentas(array $ids, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $marcas = [];
        $params = [':e' => $idEmpresa];
        foreach ($ids as $i => $id) {
            $marcas[] = ":c{$i}";
            $params[":c{$i}"] = $id;
        }
        $st = $this->db->prepare(
            'SELECT id, codigo, nombre FROM plan_cuentas
              WHERE id_empresa = :e AND id IN (' . implode(', ', $marcas) . ')'
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id']] = $r;
        }
        return $out;
    }

    /** ¿La cuenta es de movimiento, vigente y de la empresa? */
    public function cuentaValida(int $idCuenta, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM plan_cuentas WHERE id = :id AND id_empresa = :e AND eliminado = false AND nivel = '5'"
        );
        $st->execute([':id' => $idCuenta, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** ¿La bodega es de la empresa y está vigente? */
    public function bodegaValida(int $idBodega, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM bodegas WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $idBodega, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }
}
