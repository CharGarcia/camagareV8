<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class PlanCuentaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('plan_cuentas');
    }

    /**
     * Obtiene el listado de cuentas con filtros y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $whitelist = ['codigo', 'nombre', 'nivel', 'status'];
        if (!in_array($ordenCol, $whitelist)) $ordenCol = 'codigo';

        $whereSql = "WHERE pc.id_empresa = :id_empresa AND pc.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        if ($buscar !== '') {
            $whereSql .= " AND (pc.codigo ILIKE :buscar OR pc.nombre ILIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }

        // 1. Total
        $stTotal = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} pc {$whereSql}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        // 2. Filas
        $offset = ($page - 1) * $perPage;
        $sqlRows = "SELECT pc.*, 
                           cc.nombre as centro_costo_nombre, 
                           p.nombre as proyecto_nombre 
                    FROM {$this->table} pc
                    LEFT JOIN centro_costos cc ON cc.id = pc.id_centro_costos
                    LEFT JOIN proyectos p ON p.id = pc.id_proyecto 
                    {$whereSql} 
                    ORDER BY pc.{$ordenCol} {$ordenDir}";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $st = $this->db->prepare($sqlRows);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return ['rows' => $rows, 'total' => $total];
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, id_centro_costos, id_proyecto,
                    codigo, nivel, nombre, codigo_sri,
                    supercias_esf, supercias_eri, supercias_ecp_codigo, supercias_ecp_subcodigo,
                    status, eliminado, created_by, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :id_centro_costos, :id_proyecto,
                    :codigo, :nivel, :nombre, :codigo_sri,
                    :supercias_esf, :supercias_eri, :supercias_ecp_codigo, :supercias_ecp_subcodigo,
                    :status, false, :created_by, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'   => $data['id_empresa'],
            ':id_usuario'   => $data['id_usuario'],
            ':id_centro_costos' => $data['id_centro_costos'] ?: null,
            ':id_proyecto'  => $data['id_proyecto'] ?: null,
            ':codigo'       => $data['codigo'],
            ':nivel'        => $data['nivel'],
            ':nombre'       => $data['nombre'],
            ':codigo_sri'   => $data['codigo_sri'] ?? null,
            ':supercias_esf' => $data['supercias_esf'] ?? null,
            ':supercias_eri' => $data['supercias_eri'] ?? null,
            ':supercias_ecp_codigo' => $data['supercias_ecp_codigo'] ?? null,
            ':supercias_ecp_subcodigo' => $data['supercias_ecp_subcodigo'] ?? null,
            ':status'       => $data['status'] ?? 1,
            ':created_by'   => $data['created_by']
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                id_centro_costos = :id_centro_costos,
                id_proyecto = :id_proyecto,
                codigo = :codigo,
                nivel = :nivel,
                nombre = :nombre,
                codigo_sri = :codigo_sri,
                supercias_esf = :supercias_esf,
                supercias_eri = :supercias_eri,
                supercias_ecp_codigo = :supercias_ecp_codigo,
                supercias_ecp_subcodigo = :supercias_ecp_subcodigo,
                status = :status,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_centro_costos' => $data['id_centro_costos'] ?: null,
            ':id_proyecto'  => $data['id_proyecto'] ?: null,
            ':codigo'       => $data['codigo'],
            ':nivel'        => $data['nivel'],
            ':nombre'       => $data['nombre'],
            ':codigo_sri'   => $data['codigo_sri'] ?? null,
            ':supercias_esf' => $data['supercias_esf'] ?? null,
            ':supercias_eri' => $data['supercias_eri'] ?? null,
            ':supercias_ecp_codigo' => $data['supercias_ecp_codigo'] ?? null,
            ':supercias_ecp_subcodigo' => $data['supercias_ecp_subcodigo'] ?? null,
            ':status'       => $data['status'] ?? 1,
            ':updated_by'   => $data['updated_by'],
            ':id'           => $id,
            ':id_empresa'   => $idEmpresa
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_by = :id_u,
                deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id' => $id, 
            ':id_empresa' => $idEmpresa,
            ':id_u' => $idUsuario
        ]);
    }

    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT pc.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} pc
                LEFT JOIN usuarios u_crea ON u_crea.id = pc.created_by
                LEFT JOIN usuarios u_act ON u_act.id = pc.updated_by
                WHERE pc.id = :id AND pc.id_empresa = :id_empresa AND pc.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene las cuentas raíz (nivel 1) que faltan por crear (1 a 6).
     */
    public function getFaltantesNivelUno(int $idEmpresa): array
    {
        $sql = "SELECT codigo FROM {$this->table} 
                WHERE id_empresa = :id_e AND nivel = '1' AND eliminado = false 
                AND codigo IN ('1','2','3','4','5','6')";
        $st = $this->db->prepare($sql);
        $st->execute([':id_e' => $idEmpresa]);
        $existencia = $st->fetchAll(PDO::FETCH_COLUMN);

        $roots = [
            '1' => 'ACTIVOS',
            '2' => 'PASIVOS',
            '3' => 'PATRIMONIO',
            '4' => 'INGRESOS',
            '5' => 'COSTOS',
            '6' => 'GASTOS'
        ];

        $faltantes = [];
        foreach ($roots as $cod => $nom) {
            if (!in_array($cod, $existencia)) {
                $faltantes[] = ['codigo' => $cod, 'nombre' => $nom];
            }
        }
        return $faltantes;
    }

    /**
     * Cuenta cuántas cuentas tiene una empresa.
     */
    public function contarPorEmpresa(int $idEmpresa): int
    {
        $id = (int) $idEmpresa;
        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE id_empresa = :id_e AND eliminado = false");
        $st->execute([':id_e' => $id]);
        return (int) $st->fetchColumn();
    }

    /**
     * Crea masivamente las 6 cuentas raíz.
     */
    public function crearRaicesIniciales(int $idEmpresa, int $idUsuario): void
    {
        $roots = [
            ['c' => '1', 'n' => 'ACTIVOS'],
            ['c' => '2', 'n' => 'PASIVOS'],
            ['c' => '3', 'n' => 'PATRIMONIO'],
            ['c' => '4', 'n' => 'INGRESOS'],
            ['c' => '5', 'n' => 'COSTOS'],
            ['c' => '6', 'n' => 'GASTOS']
        ];
        
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, codigo, nivel, nombre, status, eliminado, created_by, created_at
                ) VALUES (
                    :id_e, :id_u, :cod, '1', :nom, 1, false, :id_u, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        
        foreach ($roots as $r) {
            $st->execute([
                ':id_e' => $idEmpresa,
                ':id_u' => $idUsuario,
                ':cod' => $r['c'],
                ':nom' => $r['n']
            ]);
        }
    }

    /**
     * Busca el último código hijo de un padre para autogenerar el siguiente.
     */
    public function getUltimoCodigoHijo(int $idEmpresa, string $codigoPadre): ?string
    {
        $nivelPadre = count(explode('.', $codigoPadre));
        $nuevoNivel = $nivelPadre + 1;
        
        $sql = "SELECT codigo FROM {$this->table} 
                WHERE id_empresa = :id_e AND eliminado = false 
                AND nivel = :nivel
                AND codigo LIKE :prefijo 
                ORDER BY codigo DESC LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_e' => $idEmpresa,
            ':nivel' => $nuevoNivel,
            ':prefijo' => $codigoPadre . '.%'
        ]);
        return $st->fetchColumn() ?: null;
    }

    /**
     * Verifica si una cuenta tiene subcuentas.
     */
    public function tieneHijos(int $idEmpresa, string $codigo): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE id_empresa = :id_e AND eliminado = false 
                AND codigo LIKE :prefijo AND codigo != :c";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_e' => $idEmpresa,
            ':prefijo' => $codigo . '.%',
            ':c' => $codigo
        ]);
        return ((int)$st->fetchColumn()) > 0;
    }

    public function findByCodigo(string $codigo, int $idEmpresa): ?array
    {
        $sql = "SELECT id, codigo, nombre FROM {$this->table} WHERE codigo = :codigo AND id_empresa = :id_empresa AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':codigo' => $codigo, ':id_empresa' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene las cuentas de Nivel 5 (movimiento) para una empresa.
     */
    public function getCuentasMovimiento(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, codigo, nivel 
                FROM plan_cuentas 
                WHERE id_empresa = ? 
                AND nivel = '5'
                AND eliminado = false 
                ORDER BY codigo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([$idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchCuentas(int $idEmpresa, string $q, string $tipo = '', int $limit = 10): array
    {
        $params = [':id_e' => $idEmpresa, ':q' => "%$q%"];
        $whereTipo = "";

        if ($tipo !== '') {
            $tiposMapeados = [
                'activo' => '1',
                'pasivo' => '2',
                'patrimonio' => '3',
                'ingreso' => '4',
                'costo' => '5',
                'gasto' => '6'
            ];

            if ($tipo === 'costo_gasto') {
                $whereTipo = " AND (codigo LIKE '5%' OR codigo LIKE '6%')";
            } else {
                $partes = array_map('trim', explode(',', strtolower($tipo)));
                $condiciones = [];
                foreach ($partes as $idx => $p) {
                    if (isset($tiposMapeados[$p])) {
                        $prefijo = $tiposMapeados[$p];
                        $paramName = ":pref_{$idx}";
                        $condiciones[] = "codigo LIKE {$paramName}";
                        $params[$paramName] = "{$prefijo}%";
                    }
                }
                if (!empty($condiciones)) {
                    $whereTipo = " AND (" . implode(" OR ", $condiciones) . ")";
                }
            }
        }

        $sql = "SELECT id, codigo, nombre 
                FROM plan_cuentas 
                WHERE id_empresa = :id_e 
                AND nivel = '5' 
                AND (codigo ILIKE :q OR nombre ILIKE :q) 
                AND eliminado = false 
                {$whereTipo} 
                ORDER BY codigo ASC LIMIT " . (int)$limit;
        
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
