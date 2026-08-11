<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio de "Configuración de Balances Consolidados": grupos de cuentas
 * equivalentes entre establecimientos del mismo RUC (consolidacion_grupos +
 * consolidacion_grupos_cuentas). Consumido tanto por el CRUD de configuración
 * como, en modo lectura, por los reportes que consolidan (Estados Financieros,
 * Balance de Comprobación).
 */
class ConsolidacionGruposRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('consolidacion_grupos');
    }

    /** Grupos del RUC con sus cuentas (una fila por cuenta, agrupar en el Service/vista). */
    public function listarGruposConCuentas(string $ruc): array
    {
        $sql = "SELECT g.id AS id_grupo, g.nombre, g.tipo, g.orden,
                       g.modo_consolidacion, g.id_empresa_fuente,
                       gc.id AS id_detalle, gc.id_empresa, gc.id_cuenta,
                       pc.codigo AS cuenta_codigo, pc.nombre AS cuenta_nombre,
                       COALESCE(NULLIF(e.nombre_comercial, ''), e.nombre) AS empresa_nombre,
                       e.establecimiento
                FROM consolidacion_grupos g
                LEFT JOIN consolidacion_grupos_cuentas gc ON gc.id_grupo = g.id AND gc.eliminado = FALSE
                LEFT JOIN plan_cuentas pc ON pc.id = gc.id_cuenta
                LEFT JOIN empresas e ON e.id = gc.id_empresa
                WHERE g.ruc = :ruc AND g.eliminado = FALSE
                ORDER BY g.tipo, g.orden, g.nombre, e.establecimiento";
        $st = $this->db->prepare($sql);
        $st->execute([':ruc' => $ruc]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getGrupo(int $id, string $ruc): ?array
    {
        $sql = "SELECT * FROM consolidacion_grupos WHERE id = :id AND ruc = :ruc AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':ruc' => $ruc]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearGrupo(array $d): int
    {
        $sql = "INSERT INTO consolidacion_grupos
                    (ruc, id_empresa_matriz, nombre, tipo, orden, modo_consolidacion, id_empresa_fuente, created_by, updated_by)
                VALUES (:ruc, :idem, :nombre, :tipo, :orden, :modo, :fuente, :usr, :usr) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':ruc' => $d['ruc'], ':idem' => $d['id_empresa_matriz'], ':nombre' => $d['nombre'],
            ':tipo' => $d['tipo'], ':orden' => $d['orden'] ?? 0,
            ':modo' => $d['modo_consolidacion'] ?? 'SUMA', ':fuente' => $d['id_empresa_fuente'] ?? null,
            ':usr' => $d['usuario_id'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizarGrupo(int $id, string $ruc, array $d): void
    {
        $sql = "UPDATE consolidacion_grupos SET nombre = :nombre, tipo = :tipo, orden = :orden,
                    modo_consolidacion = :modo, id_empresa_fuente = :fuente,
                    updated_by = :usr, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND ruc = :ruc AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':nombre' => $d['nombre'], ':tipo' => $d['tipo'], ':orden' => $d['orden'] ?? 0,
            ':modo' => $d['modo_consolidacion'] ?? 'SUMA', ':fuente' => $d['id_empresa_fuente'] ?? null,
            ':usr' => $d['usuario_id'], ':id' => $id, ':ruc' => $ruc,
        ]);
    }

    public function eliminarGrupo(int $id, string $ruc, int $idUsuario): bool
    {
        $st = $this->db->prepare(
            "UPDATE consolidacion_grupos SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr
             WHERE id = :id AND ruc = :ruc AND eliminado = FALSE"
        );
        $st->execute([':usr' => $idUsuario, ':id' => $id, ':ruc' => $ruc]);
        if ($st->rowCount() === 0) {
            return false;
        }
        $this->db->prepare(
            "UPDATE consolidacion_grupos_cuentas SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr
             WHERE id_grupo = :id AND eliminado = FALSE"
        )->execute([':usr' => $idUsuario, ':id' => $id]);
        return true;
    }

    /**
     * Reemplaza por completo las cuentas del grupo (borra las que ya no vienen, inserta las
     * nuevas). $cuentas: [['id_empresa'=>int,'id_cuenta'=>int], ...] — como mucho una por empresa.
     */
    public function guardarCuentasDelGrupo(int $idGrupo, array $cuentas, int $idUsuario): void
    {
        $this->db->prepare(
            "UPDATE consolidacion_grupos_cuentas SET eliminado = TRUE, deleted_at = CURRENT_TIMESTAMP, deleted_by = :usr
             WHERE id_grupo = :id AND eliminado = FALSE"
        )->execute([':usr' => $idUsuario, ':id' => $idGrupo]);

        if (!$cuentas) {
            return;
        }
        $ins = $this->db->prepare(
            "INSERT INTO consolidacion_grupos_cuentas (id_grupo, id_empresa, id_cuenta, created_by, updated_by)
             VALUES (:g, :e, :c, :u, :u)"
        );
        foreach ($cuentas as $c) {
            $ins->execute([':g' => $idGrupo, ':e' => (int) $c['id_empresa'], ':c' => (int) $c['id_cuenta'], ':u' => $idUsuario]);
        }
    }

    /** id_cuenta -> id_grupo, para todas las cuentas ya usadas en algún grupo del RUC (evita duplicar). */
    public function getCuentasUsadasDelRuc(string $ruc, ?int $excluirGrupo = null): array
    {
        $sql = "SELECT gc.id_cuenta, gc.id_grupo FROM consolidacion_grupos_cuentas gc
                JOIN consolidacion_grupos g ON g.id = gc.id_grupo
                WHERE g.ruc = :ruc AND g.eliminado = FALSE AND gc.eliminado = FALSE";
        $params = [':ruc' => $ruc];
        if ($excluirGrupo !== null) {
            $sql .= " AND gc.id_grupo != :ex";
            $params[':ex'] = $excluirGrupo;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_cuenta']] = (int) $r['id_grupo'];
        }
        return $out;
    }

    /**
     * Cuentas de MOVIMIENTO (nivel 5) de una empresa, para el selector del picker. Solo nivel 5
     * recibe asientos directos (mismo criterio que Mayores/Índices Financieros) — una cuenta
     * padre casi nunca tiene movimiento propio, así que mapearla daría siempre $0.00 en el
     * consolidado sin ningún aviso.
     */
    public function getCuentasDeEmpresa(int $idEmpresa): array
    {
        $sql = "SELECT id, codigo, nombre, nivel FROM plan_cuentas
                WHERE id_empresa = :e AND eliminado = FALSE AND nivel = '5' ORDER BY codigo";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** ¿La cuenta pertenece a esa empresa y es de nivel 5 (movimiento)? Refuerzo de servidor al guardar. */
    public function esCuentaNivel5DeEmpresa(int $idCuenta, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM plan_cuentas WHERE id = :c AND id_empresa = :e AND nivel = '5' AND eliminado = FALSE"
        );
        $st->execute([':c' => $idCuenta, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Grupos del RUC con sus cuentas, indexado por id_cuenta -> ['id_grupo','nombre','tipo'].
     * Forma de lectura que usan los reportes (Estados Financieros, Balance de Comprobación)
     * para saber si una cuenta pertenece a un grupo consolidado.
     */
    public function getMapaCuentaGrupo(string $ruc): array
    {
        $sql = "SELECT gc.id_cuenta, gc.id_empresa, g.id AS id_grupo, g.nombre, g.tipo, g.orden,
                       g.modo_consolidacion, g.id_empresa_fuente
                FROM consolidacion_grupos_cuentas gc
                JOIN consolidacion_grupos g ON g.id = gc.id_grupo AND g.eliminado = FALSE
                WHERE g.ruc = :ruc AND gc.eliminado = FALSE";
        $st = $this->db->prepare($sql);
        $st->execute([':ruc' => $ruc]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['id_cuenta']] = [
                'id_grupo' => (int) $r['id_grupo'],
                'nombre'   => $r['nombre'],
                'tipo'     => $r['tipo'],
                'orden'    => (int) $r['orden'],
                'modo'     => $r['modo_consolidacion'] ?? 'SUMA',
                'id_empresa_fuente' => $r['id_empresa_fuente'] !== null ? (int) $r['id_empresa_fuente'] : null,
            ];
        }
        return $out;
    }
}
