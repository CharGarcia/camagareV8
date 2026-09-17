<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\Helpers\OrdenListado;
use App\repositories\BaseRepository;
use PDO;

class FormaPagoRepository extends BaseRepository
{
    /** Columnas ordenables del listado: clave del encabezado => expresión SQL. */
    public const MAPA_ORDEN = [
        'nombre'        => 'fp.nombre',
        'tipo'          => 'fp.tipo',
        'aplica_en'     => 'fp.aplica_en',
        // Las formas sin orden quedan al final en ambos sentidos, como en Ingresos/Egresos.
        'orden'         => '(fp.orden IS NULL), fp.orden',
        'mostrar_saldo' => 'fp.mostrar_saldo',
        'activo'        => 'fp.activo',
        'banco_nombre'  => 'b.nombre_banco',
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

    /**
     * ¿Ya existen `mostrar_saldo` y `orden` (database/migrations/20260916_formas_pago_mostrar_saldo_orden.sql)?
     * El código puede llegar a una base antes que su SQL: mientras falten, Ingresos/Egresos listan
     * las formas por nombre con el saldo visible (lo de siempre) y el guardado no toca esas columnas.
     */
    private function tieneColumnasPresentacion(): bool
    {
        return $this->columnaExiste($this->table, 'mostrar_saldo')
            && $this->columnaExiste($this->table, 'orden');
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        $mapaOrden = self::MAPA_ORDEN;
        if (!$this->tieneColumnasPresentacion()) {
            unset($mapaOrden['orden'], $mapaOrden['mostrar_saldo']);
        }
        $orderBy = OrdenListado::clausula(
            [['col' => $ordenCol, 'dir' => $ordenDir]], $mapaOrden, 'fp.nombre', 'fp.id DESC'
        );

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
                    {$orderBy}
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
        $presentacion = $this->tieneColumnasPresentacion();

        $sql = "INSERT INTO {$this->table} (
                    id_empresa, nombre, tipo, aplica_en, id_banco, tipo_cuenta, numero_cuenta,
                    modalidad_tarjeta, id_cuenta_contable, tipo_cuenta_contable, activo, created_by, created_at"
                    . ($presentacion ? ", mostrar_saldo, orden" : "") . "
                ) VALUES (
                    :id_empresa, :nombre, :tipo, :aplica_en, :id_banco, :tipo_cuenta, :numero_cuenta,
                    :modalidad_tarjeta, :id_cuenta_contable, :tipo_cuenta_contable, :activo, :created_by, CURRENT_TIMESTAMP"
                    . ($presentacion ? ", :mostrar_saldo, :orden" : "") . "
                )";

        $params = [
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
        ];
        if ($presentacion) {
            $params += $this->paramsPresentacion($data);
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $this->lastInsertId();
    }

    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $presentacion = $this->tieneColumnasPresentacion();

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
                    activo = :activo,"
                    . ($presentacion ? "
                    mostrar_saldo = :mostrar_saldo,
                    orden = :orden," : "") . "
                    updated_by = :updated_by,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = FALSE";

        $params = [
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
        ];
        if ($presentacion) {
            $params += $this->paramsPresentacion($data);
        }

        $st = $this->db->prepare($sql);
        return $st->execute($params);
    }

    /**
     * Parámetros de "Mostrar saldo" y "Orden". Si $data no trae mostrar_saldo se guarda visible,
     * que es el valor por defecto de la columna; un orden vacío queda NULL (al final, por nombre).
     */
    private function paramsPresentacion(array $data): array
    {
        $mostrarSaldo = !array_key_exists('mostrar_saldo', $data) || !empty($data['mostrar_saldo']);

        return [
            ':mostrar_saldo' => $mostrarSaldo ? 'true' : 'false',
            ':orden'         => (isset($data['orden']) && $data['orden'] !== '') ? (int)$data['orden'] : null,
        ];
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

    /**
     * Formas activas de un flujo (INGRESO | EGRESO).
     *
     * $ordenConfigurado: respeta el "Orden" definido en Formas de Cobro y Pago (1 = primera; las que
     * no tienen orden van al final, por nombre). Lo piden Ingresos y Egresos; el resto de módulos
     * sigue listando por nombre.
     */
    public function getFormasFiltradas(int $idEmpresa, string $flujo, bool $ordenConfigurado = false): array
    {
        $orderBy = ($ordenConfigurado && $this->tieneColumnasPresentacion())
            ? 'fp.orden ASC NULLS LAST, fp.nombre ASC'
            : 'fp.nombre ASC';

        $sql = "SELECT fp.*, b.nombre_banco AS banco_nombre
                FROM {$this->table} fp
                LEFT JOIN bancos_ecuador b ON fp.id_banco = b.id
                WHERE fp.id_empresa = :id_empresa
                  AND fp.activo = TRUE
                  AND fp.eliminado = FALSE
                  AND (fp.aplica_en = 'AMBAS' OR fp.aplica_en = :flujo)
                ORDER BY {$orderBy}";
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
     * Movimientos de anticipos de un tercero, con las MISMAS fuentes y filtros de
     * getSaldoAnticipo() pero detallados y sin acotar a una forma: saldo inicial (+),
     * anticipos recibidos/entregados (+) y aplicaciones a un cobro/pago (−). Así el total
     * de la pestaña "Anticipos" de la ficha cuadra con el saldo que muestra la forma.
     * $esProveedor define la dirección: egresos/proveedor o ingresos/cliente.
     */
    public function getMovimientosAnticipoTercero(int $idEmpresa, int $idTercero, bool $esProveedor): array
    {
        $st = $this->db->prepare($this->sqlMovimientosAnticipo($esProveedor) . " ORDER BY fecha, id_documento");
        $st->execute([':e' => $idEmpresa, ':t' => $idTercero]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿El tercero tiene algún movimiento de anticipos? (la ficha no pinta la pestaña vacía). */
    public function tieneAnticiposTercero(int $idEmpresa, int $idTercero, bool $esProveedor): bool
    {
        $st = $this->db->prepare("SELECT EXISTS (SELECT 1 FROM ( "
            . $this->sqlMovimientosAnticipo($esProveedor) . " ) m) AS hay");
        $st->execute([':e' => $idEmpresa, ':t' => $idTercero]);
        return (bool) $st->fetchColumn();
    }

    /**
     * UNION de las tres fuentes del anticipo. Tablas, signos y etiquetas son fijos; del
     * exterior solo entran la empresa y el tercero, como parámetros.
     */
    private function sqlMovimientosAnticipo(bool $esProveedor): string
    {
        if ($esProveedor) {
            return "
                SELECT a.fecha_saldo::date AS fecha, 'SALDO_INICIAL'::text AS origen,
                       'Saldo inicial'::text AS movimiento, COALESCE(fp.nombre, '')::text AS forma,
                       ''::text AS numero_documento, COALESCE(a.observaciones, '')::text AS detalle,
                       a.saldo_inicial AS monto, 1 AS signo, 0 AS id_documento
                FROM saldos_iniciales_anticipos a
                LEFT JOIN empresa_formas_pago fp ON fp.id = a.id_forma_pago
                WHERE a.id_empresa = :e AND a.eliminado = FALSE AND a.id_proveedor = :t
                UNION ALL
                SELECT ec.fecha_emision::date, 'ENTREGADO'::text, 'Anticipo entregado'::text,
                       COALESCE(o.nombre, '')::text, ec.numero_egreso::text,
                       COALESCE(ec.observaciones, '')::text, ec.monto_total, 1, ec.id
                FROM egresos_cabecera ec
                INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ec.id_egreso_concepto
                WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                  AND o.comportamiento = 'ANTICIPO_PROVEEDOR' AND ec.id_proveedor = :t
                UNION ALL
                SELECT ec.fecha_emision::date, 'APLICADO'::text, 'Aplicado a un pago'::text,
                       fp.nombre::text, ec.numero_egreso::text,
                       COALESCE(ec.observaciones, '')::text, ep.monto, -1, ec.id
                FROM egresos_pagos ep
                INNER JOIN egresos_cabecera ec ON ec.id = ep.id_egreso
                INNER JOIN empresa_formas_pago fp ON fp.id = ep.id_forma_pago AND fp.tipo = 'ANTICIPO'
                WHERE ec.id_empresa = :e AND ec.eliminado = FALSE AND ec.estado <> 'anulado'
                  AND ep.eliminado = FALSE AND ec.id_proveedor = :t";
        }

        return "
            SELECT a.fecha_saldo::date AS fecha, 'SALDO_INICIAL'::text AS origen,
                   'Saldo inicial'::text AS movimiento, COALESCE(fp.nombre, '')::text AS forma,
                   ''::text AS numero_documento, COALESCE(a.observaciones, '')::text AS detalle,
                   a.saldo_inicial AS monto, 1 AS signo, 0 AS id_documento
            FROM saldos_iniciales_anticipos a
            LEFT JOIN empresa_formas_pago fp ON fp.id = a.id_forma_pago
            WHERE a.id_empresa = :e AND a.eliminado = FALSE AND a.id_cliente = :t
            UNION ALL
            SELECT ic.fecha_emision::date, 'RECIBIDO'::text, 'Anticipo recibido'::text,
                   COALESCE(o.nombre, '')::text, ic.numero_ingreso::text,
                   COALESCE(ic.observaciones, '')::text, ic.monto_total, 1, ic.id
            FROM ingresos_cabecera ic
            INNER JOIN empresa_opciones_ingreso_egreso o ON o.id = ic.id_ingreso_concepto
            WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
              AND o.comportamiento = 'ANTICIPO_CLIENTE'
              AND COALESCE(ic.id_cliente, ic.id_recibo_cliente) = :t
            UNION ALL
            SELECT ic.fecha_emision::date, 'APLICADO'::text, 'Aplicado a un cobro'::text,
                   fp.nombre::text, ic.numero_ingreso::text,
                   COALESCE(ic.observaciones, '')::text, ip.monto, -1, ic.id
            FROM ingresos_pagos ip
            INNER JOIN ingresos_cabecera ic ON ic.id = ip.id_ingreso
            INNER JOIN empresa_formas_pago fp ON fp.id = ip.id_forma_cobro AND fp.tipo = 'ANTICIPO'
            WHERE ic.id_empresa = :e AND ic.eliminado = FALSE AND ic.estado <> 'anulado'
              AND ic.id_cliente = :t";
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
