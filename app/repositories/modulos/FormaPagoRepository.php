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

    /**
     * Cuenta contable VIGENTE de cada flujo, con el mismo criterio que usa la contabilidad
     * (AsientoBuilderService::lineasFormas) y Configuración Contable: manda el asiento
     * programado de la forma en ese flujo ('forma_cobro' / 'forma_pago') y, si no existe,
     * se cae a la cuenta base del propio módulo (empresa_formas_pago.id_cuenta_contable).
     * Sin esto el módulo mostraría la cuenta base aunque el asiento la esté sobrescribiendo.
     */
    private const SELECT_CUENTAS_FLUJO = "
                           COALESCE(apc.id_cuenta, fp.id_cuenta_contable) AS id_cuenta_cobro,
                           pcc.codigo AS cuenta_cobro_codigo,
                           pcc.nombre AS cuenta_cobro_nombre,
                           COALESCE(app.id_cuenta, fp.id_cuenta_contable) AS id_cuenta_pago,
                           pcp.codigo AS cuenta_pago_codigo,
                           pcp.nombre AS cuenta_pago_nombre";

    /** Joins que alimentan SELECT_CUENTAS_FLUJO (placeholders distintos: PDO/pgsql no repite). */
    private const JOIN_CUENTAS_FLUJO = "
                    LEFT JOIN asientos_programados apc ON apc.id_referencia = fp.id
                                                     AND apc.tipo_referencia = 'forma_cobro'
                                                     AND apc.id_empresa = :emp_ap_cobro
                                                     AND apc.eliminado = false
                    LEFT JOIN plan_cuentas pcc ON pcc.id = COALESCE(apc.id_cuenta, fp.id_cuenta_contable)
                    LEFT JOIN asientos_programados app ON app.id_referencia = fp.id
                                                     AND app.tipo_referencia = 'forma_pago'
                                                     AND app.id_empresa = :emp_ap_pago
                                                     AND app.eliminado = false
                    LEFT JOIN plan_cuentas pcp ON pcp.id = COALESCE(app.id_cuenta, fp.id_cuenta_contable)";

    public function __construct()
    {
        parent::__construct('empresa_formas_pago');
        $this->runMigrations();
    }

    /**
     * Inyecta tipo_cuenta_contable si no existe: filtro opcional del buscador de cuentas
     * (CSV de activo/pasivo/patrimonio/ingreso/costo/gasto; vacío = sin restricción).
     * No puede llamarse "tipo_cuenta" porque esa columna ya existe con otro significado
     * (AHORROS/CORRIENTE/VIRTUAL, subtipo de cuenta bancaria).
     */
    private function runMigrations(): void
    {
        try {
            $check = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'empresa_formas_pago' AND column_name = 'tipo_cuenta_contable'");
            if (!$check->fetch()) {
                $this->db->exec("ALTER TABLE empresa_formas_pago ADD COLUMN tipo_cuenta_contable VARCHAR(50) NULL");
            }
        } catch (\Throwable $e) {
            // Silent catch for runtime safety
        }
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
                           pc.nombre AS cuenta_contable_nombre,
                           " . self::SELECT_CUENTAS_FLUJO . "
                    FROM {$this->table} fp
                    LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                    LEFT JOIN plan_cuentas pc ON fp.id_cuenta_contable = pc.id
                    " . self::JOIN_CUENTAS_FLUJO . "
                    {$whereSql}
                    ORDER BY $orderExpr $dir
                    LIMIT :limit OFFSET :offset";

        $stRows = $this->db->prepare($sqlRows);
        // PDO BindValue for LIMIT offset safety
        foreach ($params as $key => $val) {
            $stRows->bindValue($key, $val);
        }
        $stRows->bindValue(':emp_ap_cobro', $idEmpresa, PDO::PARAM_INT);
        $stRows->bindValue(':emp_ap_pago', $idEmpresa, PDO::PARAM_INT);
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
                       pc.nombre AS cuenta_contable_nombre,
                       " . self::SELECT_CUENTAS_FLUJO . "
                FROM {$this->table} fp
                LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                LEFT JOIN plan_cuentas pc ON fp.id_cuenta_contable = pc.id
                " . self::JOIN_CUENTAS_FLUJO . "
                WHERE fp.id = :id AND fp.id_empresa = :id_empresa AND fp.eliminado = FALSE";

        $st = $this->db->prepare($sql);
        $st->execute([
            ':id'           => $id,
            ':id_empresa'   => $idEmpresa,
            ':emp_ap_cobro' => $idEmpresa,
            ':emp_ap_pago'  => $idEmpresa
        ]);
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
                    modalidad_tarjeta, id_cuenta_contable, tipo_cuenta_contable, activo, created_by, created_at
                ) VALUES (
                    :id_empresa, :nombre, :tipo, :aplica_en, :id_banco, :tipo_cuenta, :numero_cuenta,
                    :modalidad_tarjeta, :id_cuenta_contable, :tipo_cuenta_contable, :activo, :created_by, CURRENT_TIMESTAMP
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
            ':modalidad_tarjeta'  => !empty($data['modalidad_tarjeta']) ? $data['modalidad_tarjeta'] : null,
            ':id_cuenta_contable' => !empty($data['id_cuenta_contable']) ? $data['id_cuenta_contable'] : null,
            ':tipo_cuenta_contable' => !empty($data['tipo_cuenta_contable']) ? $data['tipo_cuenta_contable'] : null,
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
                    modalidad_tarjeta = :modalidad_tarjeta,
                    id_cuenta_contable = :id_cuenta_contable,
                    tipo_cuenta_contable = :tipo_cuenta_contable,
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
            ':modalidad_tarjeta'  => !empty($data['modalidad_tarjeta']) ? $data['modalidad_tarjeta'] : null,
            ':id_cuenta_contable' => !empty($data['id_cuenta_contable']) ? $data['id_cuenta_contable'] : null,
            ':tipo_cuenta_contable' => !empty($data['tipo_cuenta_contable']) ? $data['tipo_cuenta_contable'] : null,
            ':activo'             => !empty($data['activo']) ? 'true' : 'false',
            ':updated_by'         => $data['usuario_id'] ?? null,
            ':id'                 => $id,
            ':id_empresa'         => $idEmpresa
        ]);
    }

    /**
     * Actualiza únicamente la cuenta contable asignada a una forma de cobro/pago.
     * Usado desde Configuración Contable para sincronizar la cuenta de la forma.
     */
    public function updateCuentaContable(int $id, int $idEmpresa, ?int $idCuenta, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET
                    id_cuenta_contable = :id_cuenta,
                    updated_by = :usr,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id_cuenta'  => $idCuenta !== null && $idCuenta > 0 ? $idCuenta : null,
            ':usr'        => $idUsuario,
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    /**
     * Formas de ANTICIPO del flujo indicado (INGRESO = cobro / EGRESO = pago) que aún NO tienen
     * cuenta contable asignada. Incluye las de aplica_en = 'AMBAS'. Se usa para propagar la cuenta
     * del concepto de anticipo (Ingresos/Egresos) a su forma de Cobro/Pago sin pisar una ya puesta.
     *
     * @return array<int,array{id:int,nombre:string,aplica_en:string}>
     */
    public function getFormasAnticipoSinCuenta(int $idEmpresa, string $flujo): array
    {
        $flujo = strtoupper($flujo) === 'EGRESO' ? 'EGRESO' : 'INGRESO';
        $sql = "SELECT id, nombre, aplica_en
                FROM {$this->table}
                WHERE id_empresa = :id_empresa
                  AND eliminado = FALSE
                  AND tipo = 'ANTICIPO'
                  AND id_cuenta_contable IS NULL
                  AND (aplica_en = 'AMBAS' OR aplica_en = :flujo)";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':flujo' => $flujo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
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

    /**
     * Saldo actual de cada forma NO-anticipo (Efectivo/Banco/Tarjeta/Otro):
     *   saldo = saldo_inicial (saldos_iniciales_bancos) + Σ cobros (ingresos_pagos) − Σ pagos (egresos_pagos)
     *         + Σ traspasos recibidos − Σ traspasos enviados (traspasos_cabecera)
     * Filtra por empresa + ambiente, excluyendo anulados/eliminados.
     *
     * @return array Mapa [id_forma => saldo (float)]
     */
    public function getSaldosActuales(int $idEmpresa): array
    {
        $sql = "
            SELECT efp.id,
                   COALESCE(sib.saldo_inicial, 0)
                   + COALESCE(ing.total, 0)
                   - COALESCE(egr.total, 0)
                   + COALESCE(trsIn.total, 0)
                   - COALESCE(trsOut.total, 0) AS saldo
            FROM {$this->table} efp
            LEFT JOIN saldos_iniciales_bancos sib
                   ON sib.id_forma_pago = efp.id
                  AND sib.id_empresa   = efp.id_empresa
                  AND sib.eliminado    = FALSE
            LEFT JOIN (
                SELECT ip.id_forma_cobro AS id_forma, SUM(ip.monto) AS total
                FROM ingresos_pagos ip
                INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                WHERE ic.id_empresa = :id_empresa
                  AND ic.eliminado  = FALSE
                  AND ic.estado    <> 'anulado'
                  AND ic.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                GROUP BY ip.id_forma_cobro
            ) ing ON ing.id_forma = efp.id
            LEFT JOIN (
                SELECT ep.id_forma_pago AS id_forma, SUM(ep.monto) AS total
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                WHERE ec.id_empresa = :id_empresa
                  AND ec.eliminado  = FALSE
                  AND ec.estado    <> 'anulado'
                  AND ep.eliminado  = FALSE
                  AND ec.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                GROUP BY ep.id_forma_pago
            ) egr ON egr.id_forma = efp.id
            LEFT JOIN (
                SELECT tc.id_forma_destino AS id_forma, SUM(tc.monto) AS total
                FROM traspasos_cabecera tc
                WHERE tc.id_empresa = :id_empresa
                  AND tc.eliminado  = FALSE
                  AND tc.estado    <> 'anulado'
                  AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                GROUP BY tc.id_forma_destino
            ) trsIn ON trsIn.id_forma = efp.id
            LEFT JOIN (
                SELECT tc.id_forma_origen AS id_forma, SUM(tc.monto) AS total
                FROM traspasos_cabecera tc
                WHERE tc.id_empresa = :id_empresa
                  AND tc.eliminado  = FALSE
                  AND tc.estado    <> 'anulado'
                  AND tc.tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = :id_empresa)
                GROUP BY tc.id_forma_origen
            ) trsOut ON trsOut.id_forma = efp.id
            WHERE efp.id_empresa = :id_empresa
              AND efp.eliminado  = FALSE
              AND efp.activo     = TRUE
              AND efp.tipo      <> 'ANTICIPO'
              AND efp.tipo      <> 'PAYPHONE'";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa]);

        $mapa = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mapa[(int)$r['id']] = (float)$r['saldo'];
        }
        return $mapa;
    }

    /**
     * Saldo de un anticipo a favor de un tercero (cliente o proveedor):
     *   saldo = saldo_inicial (saldos_iniciales_anticipos por forma + tercero)
     *         + generado  (anticipos registrados con una OPCIÓN de comportamiento
     *                      ANTICIPO_CLIENTE en ingresos / ANTICIPO_PROVEEDOR en egresos)
     *         − aplicado  (pagos que usan esta forma de anticipo para ese tercero)
     * La dirección (cliente/proveedor) la define el aplica_en de la forma.
     */
    public function getSaldoAnticipo(int $idEmpresa, int $idForma, int $idTercero): float
    {
        $stF = $this->db->prepare(
            "SELECT aplica_en FROM {$this->table}
             WHERE id = :id AND id_empresa = :e AND eliminado = FALSE AND tipo = 'ANTICIPO'"
        );
        $stF->execute([':id' => $idForma, ':e' => $idEmpresa]);
        $forma = $stF->fetch(PDO::FETCH_ASSOC);
        if (!$forma) {
            return 0.0;
        }
        $esEgreso = strtoupper((string)$forma['aplica_en']) === 'EGRESO';

        // Saldo inicial registrado en el módulo de saldos iniciales
        $stI = $this->db->prepare(
            "SELECT COALESCE(SUM(saldo_inicial), 0)
             FROM saldos_iniciales_anticipos
             WHERE id_empresa = :e AND id_forma_pago = :forma AND eliminado = FALSE
               AND (id_cliente = :t OR id_proveedor = :t)"
        );
        $stI->execute([':e' => $idEmpresa, ':forma' => $idForma, ':t' => $idTercero]);
        $inicial = (float)$stI->fetchColumn();

        // Generado: anticipos registrados vía una OPCIÓN de ingreso/egreso con
        // comportamiento ANTICIPO_CLIENTE (ingresos) / ANTICIPO_PROVEEDOR (egresos)
        // para ese tercero. Es la fuente principal del saldo a favor.
        if ($esEgreso) {
            $stG = $this->db->prepare(
                "SELECT COALESCE(SUM(ec.monto_total), 0)
                 FROM egresos_cabecera ec
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ec.id_egreso_concepto
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_PROVEEDOR'
                   AND ec.id_proveedor = :t"
            );
        } else {
            $stG = $this->db->prepare(
                "SELECT COALESCE(SUM(ic.monto_total), 0)
                 FROM ingresos_cabecera ic
                 INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ic.id_ingreso_concepto
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND o.comportamiento = 'ANTICIPO_CLIENTE'
                   AND COALESCE(ic.id_cliente, ic.id_recibo_cliente) = :t"
            );
        }
        $stG->execute([':e' => $idEmpresa, ':t' => $idTercero]);
        $generado = (float)$stG->fetchColumn();

        // Aplicado: pagos que consumen este anticipo (forma tipo ANTICIPO) para ese tercero.
        if ($esEgreso) {
            $stA = $this->db->prepare(
                "SELECT COALESCE(SUM(ep.monto), 0)
                 FROM egresos_pagos ep
                 INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                 WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                   AND ep.eliminado = FALSE
                   AND ep.id_forma_pago = :forma
                   AND ec.id_proveedor = :t"
            );
        } else {
            $stA = $this->db->prepare(
                "SELECT COALESCE(SUM(ip.monto), 0)
                 FROM ingresos_pagos ip
                 INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
                 WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
                   AND ip.id_forma_cobro = :forma
                   AND ic.id_cliente = :t"
            );
        }
        $stA->execute([':e' => $idEmpresa, ':forma' => $idForma, ':t' => $idTercero]);
        $aplicado = (float)$stA->fetchColumn();

        return round($inicial + $generado - $aplicado, 2);
    }

    /**
     * Otra forma de pago (de la misma empresa) que ya usa esta cuenta contable, si la hay.
     * Se usa para evitar que una forma NO bancaria (efectivo, tarjeta...) comparta la cuenta
     * de una forma BANCO/CHEQUE: Control Bancario filtra el mayor por id_cuenta_contable, así
     * que compartirla mezcla movimientos ajenos en la conciliación de esa cuenta bancaria.
     */
    public function getOtraFormaConMismaCuenta(int $idEmpresa, int $idCuenta, ?int $excluirId): ?array
    {
        $sql = "SELECT id, nombre, tipo FROM {$this->table}
                WHERE id_empresa = :id_empresa AND id_cuenta_contable = :id_cuenta AND eliminado = FALSE";
        $params = [':id_empresa' => $idEmpresa, ':id_cuenta' => $idCuenta];
        if ($excluirId !== null) {
            $sql .= " AND id != :excluir";
            $params[':excluir'] = $excluirId;
        }
        $sql .= " LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
