<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;

class IndicesFinancierosRepository extends BaseRepository
{
    private const GRUPOS_NIVEL1 = ['ACTIVO_CORRIENTE', 'ACTIVO_NO_CORRIENTE', 'PASIVO_CORRIENTE', 'PASIVO_NO_CORRIENTE'];

    public function __construct()
    {
        parent::__construct('indices_financieros_indices');
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Normaliza valores boolean-ish (bool, 'true'/'false', 1/0) al literal que Postgres/PDO espera. */
    private static function toPgBool($valor): string
    {
        if (is_string($valor)) {
            return in_array(strtolower($valor), ['false', '0', ''], true) ? 'false' : 'true';
        }
        return $valor ? 'true' : 'false';
    }

    // ════════════════════════════════════════════════════════════════════
    // NIVEL 1 — Clasificación Corriente / No Corriente
    // ════════════════════════════════════════════════════════════════════

    /**
     * Cuentas de movimiento (nivel 5) de Activo/Pasivo que aún no tienen grupo asignado.
     * Incluye supercias_esf para poder sugerir la clasificación en la UI.
     */
    public function getCuentasSinClasificar(int $idEmpresa): array
    {
        $sql = "SELECT pc.id, pc.codigo, pc.nombre, pc.supercias_esf
                FROM plan_cuentas pc
                WHERE pc.id_empresa = :id_empresa
                  AND pc.eliminado = false
                  AND pc.nivel = '5'
                  AND (pc.codigo LIKE '1%' OR pc.codigo LIKE '2%')
                  AND NOT EXISTS (
                      SELECT 1 FROM indices_financieros_grupo_cuentas gc
                      WHERE gc.id_cuenta = pc.id AND gc.eliminado = false
                  )
                ORDER BY pc.codigo ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll();
    }

    /** Cuentas de Activo/Pasivo ya clasificadas, con su grupo. */
    public function getClasificacion(int $idEmpresa): array
    {
        $sql = "SELECT gc.id, gc.grupo, pc.id AS id_cuenta, pc.codigo, pc.nombre
                FROM indices_financieros_grupo_cuentas gc
                JOIN plan_cuentas pc ON pc.id = gc.id_cuenta
                WHERE gc.id_empresa = :id_empresa AND gc.eliminado = false
                ORDER BY pc.codigo ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll();
    }

    /** Mapa id_cuenta => grupo (ACTIVO_CORRIENTE, etc.) para el cálculo de índices. */
    public function getMapaGrupoCuentas(int $idEmpresa): array
    {
        $rows = $this->query(
            "SELECT id_cuenta, grupo FROM indices_financieros_grupo_cuentas WHERE id_empresa = :id_empresa AND eliminado = false",
            [':id_empresa' => $idEmpresa]
        )->fetchAll();

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[(int) $r['id_cuenta']] = $r['grupo'];
        }
        return $mapa;
    }

    public function guardarClasificacion(int $idEmpresa, int $idCuenta, string $grupo, int $idUsuario): void
    {
        if (!in_array($grupo, self::GRUPOS_NIVEL1, true)) {
            throw new \Exception('Grupo de clasificación inválido.');
        }

        $existente = $this->query(
            "SELECT id FROM indices_financieros_grupo_cuentas WHERE id_empresa = :id_empresa AND id_cuenta = :id_cuenta AND eliminado = false",
            [':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta]
        )->fetchColumn();

        if ($existente) {
            $this->query(
                "UPDATE indices_financieros_grupo_cuentas SET grupo = :grupo, updated_by = :id_usuario, updated_at = NOW() WHERE id = :id",
                [':grupo' => $grupo, ':id_usuario' => $idUsuario, ':id' => (int) $existente]
            );
        } else {
            $this->query(
                "INSERT INTO indices_financieros_grupo_cuentas (id_empresa, id_cuenta, grupo, created_by, updated_by)
                 VALUES (:id_empresa, :id_cuenta, :grupo, :id_usuario, :id_usuario)",
                [':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta, ':grupo' => $grupo, ':id_usuario' => $idUsuario]
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // NIVEL 2 — Grupos personalizados
    // ════════════════════════════════════════════════════════════════════

    public function getGrupos(int $idEmpresa): array
    {
        $sql = "SELECT g.*,
                       (SELECT COUNT(*) FROM indices_financieros_grupo_detalle d WHERE d.id_grupo = g.id AND d.eliminado = false) AS total_cuentas
                FROM indices_financieros_grupos g
                " . $this->getBaseWhere($idEmpresa, 'g') . "
                ORDER BY g.nombre ASC";
        return $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll();
    }

    public function getGrupoPorId(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM indices_financieros_grupos WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch();
        return $row ?: null;
    }

    public function codigoGrupoExiste(int $idEmpresa, string $codigo, ?int $idExcluir = null): bool
    {
        $sql = "SELECT 1 FROM indices_financieros_grupos WHERE id_empresa = ? AND eliminado = false AND UPPER(codigo) = UPPER(?)";
        $params = [$idEmpresa, $codigo];
        if ($idExcluir !== null) {
            $sql .= " AND id <> ?";
            $params[] = $idExcluir;
        }
        return (bool) $this->query($sql, $params)->fetchColumn();
    }

    public function crearGrupo(array $data): int
    {
        return (int) $this->query(
            "INSERT INTO indices_financieros_grupos (id_empresa, codigo, nombre, descripcion, created_by, updated_by)
             VALUES (:id_empresa, :codigo, :nombre, :descripcion, :id_usuario, :id_usuario) RETURNING id",
            [
                ':id_empresa' => (int) $data['id_empresa'],
                ':codigo' => strtoupper(trim($data['codigo'])),
                ':nombre' => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':id_usuario' => (int) $data['id_usuario'],
            ]
        )->fetchColumn();
    }

    public function actualizarGrupo(int $id, array $data): void
    {
        $this->query(
            "UPDATE indices_financieros_grupos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion, updated_by = :id_usuario, updated_at = NOW()
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [
                ':codigo' => strtoupper(trim($data['codigo'])),
                ':nombre' => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':id_usuario' => (int) $data['id_usuario'],
                ':id' => $id,
                ':id_empresa' => (int) $data['id_empresa'],
            ]
        );
    }

    public function eliminarGrupo(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE indices_financieros_grupos SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?",
            [$idUsuario, $id, $idEmpresa]
        );
        $this->query(
            "UPDATE indices_financieros_grupo_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id_grupo = ? AND id_empresa = ?",
            [$idUsuario, $id, $idEmpresa]
        );
    }

    public function getCuentasDeGrupo(int $idGrupo): array
    {
        $sql = "SELECT d.id, pc.id AS id_cuenta, pc.codigo, pc.nombre
                FROM indices_financieros_grupo_detalle d
                JOIN plan_cuentas pc ON pc.id = d.id_cuenta
                WHERE d.id_grupo = :id_grupo AND d.eliminado = false
                ORDER BY pc.codigo ASC";
        return $this->query($sql, [':id_grupo' => $idGrupo])->fetchAll();
    }

    /** Reemplaza el conjunto de cuentas de un grupo (borra las que ya no estén, agrega las nuevas). */
    public function setCuentasDeGrupo(int $idGrupo, int $idEmpresa, array $idCuentas, int $idUsuario): void
    {
        $this->query(
            "UPDATE indices_financieros_grupo_detalle SET eliminado = true, deleted_at = NOW(), deleted_by = ?
             WHERE id_grupo = ? AND id_empresa = ? AND eliminado = false",
            [$idUsuario, $idGrupo, $idEmpresa]
        );

        foreach (array_unique(array_map('intval', $idCuentas)) as $idCuenta) {
            if ($idCuenta <= 0) continue;
            $this->query(
                "INSERT INTO indices_financieros_grupo_detalle (id_empresa, id_grupo, id_cuenta, created_by, updated_by)
                 VALUES (:id_empresa, :id_grupo, :id_cuenta, :id_usuario, :id_usuario)
                 ON CONFLICT DO NOTHING",
                [':id_empresa' => $idEmpresa, ':id_grupo' => $idGrupo, ':id_cuenta' => $idCuenta, ':id_usuario' => $idUsuario]
            );
        }
    }

    /** Mapa codigo_grupo => [id_cuenta,...] de todos los grupos personalizados de la empresa. */
    public function getMapaCuentasGrupoPersonalizado(int $idEmpresa): array
    {
        $sql = "SELECT g.codigo, d.id_cuenta
                FROM indices_financieros_grupos g
                JOIN indices_financieros_grupo_detalle d ON d.id_grupo = g.id AND d.eliminado = false
                WHERE g.id_empresa = :id_empresa AND g.eliminado = false";
        $rows = $this->query($sql, [':id_empresa' => $idEmpresa])->fetchAll();

        $mapa = [];
        foreach ($rows as $r) {
            $mapa[$r['codigo']][] = (int) $r['id_cuenta'];
        }
        return $mapa;
    }

    /** Cuentas hoja (nivel 5) de la empresa, para el selector de cuentas del constructor de grupos. */
    public function getCuentasParaSelector(int $idEmpresa, string $buscar = ''): array
    {
        $params = [':id_empresa' => $idEmpresa];
        $where = "WHERE pc.id_empresa = :id_empresa AND pc.eliminado = false AND pc.nivel = '5'";
        if ($buscar !== '') {
            $where .= " AND (pc.codigo ILIKE :buscar OR pc.nombre ILIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }
        return $this->query(
            "SELECT pc.id, pc.codigo, pc.nombre FROM plan_cuentas pc $where ORDER BY pc.codigo ASC LIMIT 200",
            $params
        )->fetchAll();
    }

    // ════════════════════════════════════════════════════════════════════
    // ÍNDICES (estándar + personalizados)
    // ════════════════════════════════════════════════════════════════════

    public function getIndices(int $idEmpresa, ?string $categoria = null, bool $soloActivos = false): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'i');
        $params = [':id_empresa' => $idEmpresa];
        if ($categoria !== null) {
            $where .= " AND i.categoria = :categoria";
            $params[':categoria'] = $categoria;
        }
        if ($soloActivos) {
            $where .= " AND i.activo = true";
        }
        return $this->query(
            "SELECT * FROM indices_financieros_indices i $where ORDER BY i.categoria ASC, i.orden ASC, i.nombre ASC",
            $params
        )->fetchAll();
    }

    public function getIndicePorId(int $id, int $idEmpresa): ?array
    {
        $row = $this->query(
            "SELECT * FROM indices_financieros_indices WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [':id' => $id, ':id_empresa' => $idEmpresa]
        )->fetch();
        return $row ?: null;
    }

    public function codigoIndiceExiste(int $idEmpresa, string $codigo, ?int $idExcluir = null): bool
    {
        $sql = "SELECT 1 FROM indices_financieros_indices WHERE id_empresa = ? AND eliminado = false AND UPPER(codigo) = UPPER(?)";
        $params = [$idEmpresa, $codigo];
        if ($idExcluir !== null) {
            $sql .= " AND id <> ?";
            $params[] = $idExcluir;
        }
        return (bool) $this->query($sql, $params)->fetchColumn();
    }

    public function crearIndice(array $data): int
    {
        return (int) $this->query(
            "INSERT INTO indices_financieros_indices
                (id_empresa, codigo, nombre, categoria, tipo, unidad, formula, descripcion, orden, activo, created_by, updated_by)
             VALUES (:id_empresa, :codigo, :nombre, :categoria, :tipo, :unidad, :formula, :descripcion, :orden, :activo, :id_usuario, :id_usuario)
             RETURNING id",
            [
                ':id_empresa' => (int) $data['id_empresa'],
                ':codigo' => strtoupper(trim($data['codigo'])),
                ':nombre' => $data['nombre'],
                ':categoria' => $data['categoria'],
                ':tipo' => $data['tipo'] ?? 'personalizado',
                ':unidad' => $data['unidad'] ?? 'razon',
                ':formula' => is_array($data['formula']) ? json_encode($data['formula']) : $data['formula'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':orden' => (int) ($data['orden'] ?? 0),
                ':activo' => self::toPgBool($data['activo'] ?? true),
                ':id_usuario' => (int) $data['id_usuario'],
            ]
        )->fetchColumn();
    }

    public function actualizarIndice(int $id, array $data): void
    {
        $this->query(
            "UPDATE indices_financieros_indices SET
                nombre = :nombre, categoria = :categoria, unidad = :unidad, formula = :formula,
                descripcion = :descripcion, orden = :orden, activo = :activo, updated_by = :id_usuario, updated_at = NOW()
             WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false",
            [
                ':nombre' => $data['nombre'],
                ':categoria' => $data['categoria'],
                ':unidad' => $data['unidad'] ?? 'razon',
                ':formula' => is_array($data['formula']) ? json_encode($data['formula']) : $data['formula'],
                ':descripcion' => $data['descripcion'] ?? null,
                ':orden' => (int) ($data['orden'] ?? 0),
                ':activo' => self::toPgBool($data['activo'] ?? true),
                ':id_usuario' => (int) $data['id_usuario'],
                ':id' => $id,
                ':id_empresa' => (int) $data['id_empresa'],
            ]
        );
    }

    public function eliminarIndice(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->query(
            "UPDATE indices_financieros_indices SET eliminado = true, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND id_empresa = ?",
            [$idUsuario, $id, $idEmpresa]
        );
    }
}
