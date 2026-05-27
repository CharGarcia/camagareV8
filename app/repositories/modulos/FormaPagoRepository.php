<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class FormaPagoRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = [
        'nombre', 'tipo', 'aplica_en', 'activo', 'banco_nombre'
    ];

    public function __construct()
    {
        parent::__construct('empresa_formas_pago');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';

        $params = [':id_empresa' => $idEmpresa];
        $whereSql = "WHERE fp.id_empresa = :id_empresa AND fp.eliminado = FALSE";

        if ($buscar !== '') {
            $whereSql .= " AND (fp.nombre ILIKE :b OR b.nombre_banco ILIKE :b OR fp.numero_cuenta ILIKE :b)";
            $params[':b'] = '%' . $buscar . '%';
        }

        // 1. Count
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} fp LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id {$whereSql}";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Rows
        $offset = ($page - 1) * $perPage;
        $orderExpr = match($ordenCol) {
            'banco_nombre' => 'b.nombre_banco',
            default        => "fp.{$ordenCol}"
        };

        $sqlRows = "SELECT fp.*, 
                           b.nombre_banco AS banco_nombre,
                           pc.codigo AS cuenta_contable_codigo,
                           pc.nombre AS cuenta_contable_nombre
                    FROM {$this->table} fp
                    LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                    LEFT JOIN plan_cuentas pc ON fp.id_cuenta_contable = pc.id
                    {$whereSql}
                    ORDER BY $orderExpr $dir
                    LIMIT :limit OFFSET :offset";

        $stRows = $this->db->prepare($sqlRows);
        // PDO BindValue for LIMIT offset safety
        foreach ($params as $key => $val) {
            $stRows->bindValue($key, $val);
        }
        $stRows->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stRows->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stRows->execute();

        return [
            'total' => $total,
            'rows'  => $stRows->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT fp.*, 
                       b.nombre_banco AS banco_nombre,
                       pc.codigo AS cuenta_contable_codigo,
                       pc.nombre AS cuenta_contable_nombre
                FROM {$this->table} fp
                LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                LEFT JOIN plan_cuentas pc ON fp.id_cuenta_contable = pc.id
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getBancosDisponibles(): array
    {
        $sql = "SELECT id, nombre_banco FROM bancos_ecuador ORDER BY nombre_banco ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCuentasContables(int $idEmpresa, string $q = ''): array
    {
        $sql = "SELECT id, codigo, nombre 
                FROM plan_cuentas 
                WHERE id_empresa = :id_empresa AND eliminado = FALSE 
                  AND (codigo ILIKE :q OR nombre ILIKE :q)
                ORDER BY codigo ASC LIMIT 30";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $idEmpresa,
            ':q' => '%' . $q . '%'
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, nombre, tipo, aplica_en, id_banco, tipo_cuenta, numero_cuenta, 
                    id_cuenta_contable, activo, created_by, created_at
                ) VALUES (
                    :id_empresa, :nombre, :tipo, :aplica_en, :id_banco, :tipo_cuenta, :numero_cuenta, 
                    :id_cuenta_contable, :activo, :created_by, CURRENT_TIMESTAMP
                )";
        
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa'         => $data['id_empresa'],
            ':nombre'             => $data['nombre'],
            ':tipo'               => $data['tipo'] ?? 'EFECTIVO',
            ':aplica_en'          => $data['aplica_en'] ?? 'AMBAS',
            ':id_banco'           => !empty($data['id_banco']) ? $data['id_banco'] : null,
            ':tipo_cuenta'        => !empty($data['tipo_cuenta']) ? $data['tipo_cuenta'] : null,
            ':numero_cuenta'      => !empty($data['numero_cuenta']) ? $data['numero_cuenta'] : null,
            ':id_cuenta_contable' => !empty($data['id_cuenta_contable']) ? $data['id_cuenta_contable'] : null,
            ':activo'             => !empty($data['activo']) ? 'true' : 'false',
            ':created_by'         => $data['usuario_id'] ?? null
        ]);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                    nombre = :nombre,
                    tipo = :tipo,
                    aplica_en = :aplica_en,
                    id_banco = :id_banco,
                    tipo_cuenta = :tipo_cuenta,
                    numero_cuenta = :numero_cuenta,
                    id_cuenta_contable = :id_cuenta_contable,
                    activo = :activo,
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':nombre'             => $data['nombre'],
            ':tipo'               => $data['tipo'],
            ':aplica_en'          => $data['aplica_en'],
            ':id_banco'           => !empty($data['id_banco']) ? $data['id_banco'] : null,
            ':tipo_cuenta'        => !empty($data['tipo_cuenta']) ? $data['tipo_cuenta'] : null,
            ':numero_cuenta'      => !empty($data['numero_cuenta']) ? $data['numero_cuenta'] : null,
            ':id_cuenta_contable' => !empty($data['id_cuenta_contable']) ? $data['id_cuenta_contable'] : null,
            ':activo'             => !empty($data['activo']) ? 'true' : 'false',
            ':updated_by'         => $data['usuario_id'] ?? null,
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa
        ]);
    }

    public function delete(int $id, int $idEmpresa, int $usuarioId): bool
    {
        $sql = "UPDATE {$this->table} SET 
                    eliminado = TRUE, 
                    deleted_by = :uid, 
                    deleted_at = CURRENT_TIMESTAMP 
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([':id' => $id, ':id_empresa' => $idEmpresa, ':uid' => $usuarioId]);
    }

    public function getFormasFiltradas(int $idEmpresa, string $flujo): array
    {
        // Flujo can be INGRESO or EGRESO
        $sql = "SELECT fp.*, b.nombre_banco AS banco_nombre 
                FROM {$this->table} fp 
                LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                WHERE fp.id_empresa = :id_empresa 
                  AND fp.activo = TRUE 
                  AND fp.eliminado = FALSE 
                  AND (fp.aplica_en = 'AMBAS' OR fp.aplica_en = :flujo)
                ORDER BY fp.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':flujo' => $flujo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function estaUsado(int $id, int $idEmpresa): bool
    {
        // 1. Verificar en ingresos_pagos
        $sqlIng = "SELECT COUNT(*) 
                   FROM ingresos_pagos ip 
                   JOIN ingresos_cabecera ic ON ip.id_ingreso = ic.id 
                   WHERE ip.id_forma_cobro = :id 
                     AND ic.id_empresa = :id_empresa 
                     AND ic.eliminado = FALSE";
        $stIng = $this->db->prepare($sqlIng);
        $stIng->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        if ((int)$stIng->fetchColumn() > 0) {
            return true;
        }

        // 2. Verificar en egresos_pagos
        $sqlEgr = "SELECT COUNT(*) 
                   FROM egresos_pagos ep 
                   JOIN egresos_cabecera ec ON ep.id_egreso = ec.id 
                   WHERE ep.id_forma_pago = :id 
                     AND ec.id_empresa = :id_empresa 
                     AND ec.eliminado = FALSE 
                     AND ep.eliminado = FALSE";
        $stEgr = $this->db->prepare($sqlEgr);
        $stEgr->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        if ((int)$stEgr->fetchColumn() > 0) {
            return true;
        }

        return false;
    }
}
