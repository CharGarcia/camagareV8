<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de inicialización de empresa.
 * Crea registros y configuraciones por defecto cada vez que se crea o actualiza una empresa.
 * Cada tarea es idempotente: verifica si ya existe antes de insertar o modificar.
 * Para agregar una nueva tarea: crear el método privado y llamarlo desde inicializar().
 */
class EmpresaInicializadorService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\core\Database::getConnection();
    }

    /**
     * Orquestador principal. Llamar después de crear o actualizar una empresa.
     */
    public function inicializar(int $idEmpresa, int $idUsuario): void
    {
        $this->crearClienteConsumidorFinal($idEmpresa, $idUsuario);
        $this->crearFormaPagoEfectivo($idEmpresa, $idUsuario);
        $this->crearFormasPagoTarjetaOnline($idEmpresa, $idUsuario);
        $this->crearAnticiposDefault($idEmpresa, $idUsuario);
        $this->crearOpcionesIngresoEgresoDefault($idEmpresa, $idUsuario);
        $this->crearUnidadesMedidaDefault($idEmpresa, $idUsuario);
        $this->configurarCorreo($idEmpresa, $idUsuario);
        $idEst = $this->obtenerOCrearEstablecimientoPrincipal($idEmpresa, $idUsuario);
        if ($idEst > 0) {
            $idPunto = $this->obtenerOCrearPuntoEmision001($idEmpresa, $idEst, $idUsuario);
            if ($idPunto > 0) {
                $this->crearSecuencialesIniciales($idPunto, $idEmpresa, $idUsuario);
            }
            // Punto de emisión DEDICADO para Facturas de Reembolso: ante el SRI comparte
            // codDoc=01 con Facturas de venta (ver SecuencialRepository::FAMILIAS_CODDOC),
            // así que no puede vivir en el mismo punto 001 sin duplicar numeración.
            // Se deja listo desde la creación de la empresa para que el módulo esté
            // disponible de inmediato si la empresa lo necesita (no obliga a usarlo).
            $idPuntoReembolso = $this->obtenerOCrearPuntoEmisionReembolso($idEmpresa, $idEst, $idUsuario);
            if ($idPuntoReembolso > 0) {
                $this->crearSecuencialReembolso($idPuntoReembolso, $idEmpresa, $idUsuario);
            }
            $this->configurarFacturacion($idEst, $idUsuario);
        }
        $this->cargarCasilleros104($idEmpresa);
    }

    // ─────────────────────────────────────────────────────────────────
    // Clientes
    // ─────────────────────────────────────────────────────────────────

    /**
     * Crea el cliente "CONSUMIDOR FINAL" si no existe en la empresa.
     * Condiciones: tipo_id = '07', identificacion = '9999999999999'
     */
    private function crearClienteConsumidorFinal(int $idEmpresa, int $idUsuario): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM clientes
             WHERE id_empresa = :id_empresa
               AND tipo_id = '07'
               AND identificacion = '9999999999999'
               AND eliminado = false
             LIMIT 1"
        );
        $existe->execute([':id_empresa' => $idEmpresa]);

        if ($existe->fetchColumn()) {
            return;
        }

        // El correo del consumidor final se toma del correo de la empresa.
        $mailEmp = $this->db->prepare("SELECT mail FROM empresas WHERE id = :id LIMIT 1");
        $mailEmp->execute([':id' => $idEmpresa]);
        $correo = trim((string) ($mailEmp->fetchColumn() ?: ''));
        $correo = $correo !== '' ? $correo : null;

        $stmt = $this->db->prepare(
            "INSERT INTO clientes (
                id_empresa, id_usuario, nombre, tipo_id, identificacion,
                telefono, email, direccion, plazo, status,
                created_by, created_at, eliminado
             ) VALUES (
                :id_empresa, :id_usuario, 'CONSUMIDOR FINAL', '07', '9999999999999',
                NULL, :email, NULL, 0, 1,
                :id_usuario, NOW(), false
             )"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':id_usuario' => $idUsuario,
            ':email'      => $correo,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Formas de pago
    // ─────────────────────────────────────────────────────────────────

    /**
     * Crea la forma de pago "Efectivo" solo si la empresa no tiene
     * ningún registro en empresa_formas_pago (sin importar el tipo).
     */
    private function crearFormaPagoEfectivo(int $idEmpresa, int $idUsuario): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM empresa_formas_pago
             WHERE id_empresa = :id_empresa
               AND eliminado = false
             LIMIT 1"
        );
        $existe->execute([':id_empresa' => $idEmpresa]);

        if ($existe->fetchColumn()) {
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_formas_pago (
                id_empresa, nombre, tipo, aplica_en, activo,
                created_by, created_at, eliminado
             ) VALUES (
                :id_empresa, 'Efectivo', 'EFECTIVO', 'AMBAS', true,
                :created_by, CURRENT_TIMESTAMP, false
             )"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':created_by' => $idUsuario,
        ]);
    }

    /**
     * Crea las formas de pago "Tarjeta" (tipo TARJETA, tarjeta física/datáfono,
     * aplica a Ingresos y Egresos, activa desde el inicio), "Payphone" (tipo
     * PAYPHONE, cobro online, solo aplica a Ingresos) y "Nuvei" (tipo NUVEI,
     * cobro online, solo aplica a Ingresos) por defecto, si no existen. Payphone
     * y Nuvei se crean INACTIVAS: requieren credenciales/config propias de la
     * empresa antes de poder cobrar, así que el usuario las activa manualmente
     * cuando las configure (ver [[nuvei-link-pago-whatsapp]] / módulo Payphone).
     * Cada una se verifica de forma independiente por tipo, igual que
     * crearAnticiposDefault().
     */
    private function crearFormasPagoTarjetaOnline(int $idEmpresa, int $idUsuario): void
    {
        $formas = [
            ['nombre' => 'Tarjeta',  'tipo' => 'TARJETA',  'aplica_en' => 'AMBAS',   'modalidad' => 'AMBAS', 'activo' => true],
            ['nombre' => 'Payphone', 'tipo' => 'PAYPHONE', 'aplica_en' => 'INGRESO', 'modalidad' => null,    'activo' => false],
            ['nombre' => 'Nuvei',    'tipo' => 'NUVEI',    'aplica_en' => 'INGRESO', 'modalidad' => null,    'activo' => false],
        ];

        $check = $this->db->prepare(
            "SELECT 1 FROM empresa_formas_pago
             WHERE id_empresa = :id_empresa AND tipo = :tipo AND eliminado = false
             LIMIT 1"
        );
        $insert = $this->db->prepare(
            "INSERT INTO empresa_formas_pago (
                id_empresa, nombre, tipo, aplica_en, modalidad_tarjeta, activo, created_by, created_at, eliminado
             ) VALUES (
                :id_empresa, :nombre, :tipo, :aplica_en, :modalidad, :activo, :created_by, CURRENT_TIMESTAMP, false
             )"
        );

        foreach ($formas as $f) {
            $check->execute([':id_empresa' => $idEmpresa, ':tipo' => $f['tipo']]);
            if ($check->fetchColumn()) {
                continue;
            }
            $insert->execute([
                ':id_empresa' => $idEmpresa,
                ':nombre'     => $f['nombre'],
                ':tipo'       => $f['tipo'],
                ':aplica_en'  => $f['aplica_en'],
                ':modalidad'  => $f['modalidad'],
                ':activo'     => $f['activo'] ? 'true' : 'false',
                ':created_by' => $idUsuario,
            ]);
        }
    }

    /**
     * Crea las dos formas de pago tipo ANTICIPO por defecto (si no existen):
     *   - "Anticipos clientes"    → aplica_en INGRESO
     *   - "Anticipos Proveedores" → aplica_en EGRESO
     * Cada una se verifica de forma independiente por (tipo ANTICIPO + aplica_en).
     */
    private function crearAnticiposDefault(int $idEmpresa, int $idUsuario): void
    {
        $anticipos = [
            ['nombre' => 'Anticipos clientes',    'aplica_en' => 'INGRESO'],
            ['nombre' => 'Anticipos Proveedores', 'aplica_en' => 'EGRESO'],
        ];

        $check = $this->db->prepare(
            "SELECT 1 FROM empresa_formas_pago
             WHERE id_empresa = :id_empresa AND tipo = 'ANTICIPO' AND aplica_en = :aplica_en
               AND eliminado = false
             LIMIT 1"
        );
        $insert = $this->db->prepare(
            "INSERT INTO empresa_formas_pago (
                id_empresa, nombre, tipo, aplica_en, activo, created_by, created_at, eliminado
             ) VALUES (
                :id_empresa, :nombre, 'ANTICIPO', :aplica_en, true, :created_by, CURRENT_TIMESTAMP, false
             )"
        );

        foreach ($anticipos as $a) {
            $check->execute([':id_empresa' => $idEmpresa, ':aplica_en' => $a['aplica_en']]);
            if ($check->fetchColumn()) {
                continue;
            }
            $insert->execute([
                ':id_empresa' => $idEmpresa,
                ':nombre'     => $a['nombre'],
                ':aplica_en'  => $a['aplica_en'],
                ':created_by' => $idUsuario,
            ]);
        }
    }

    /**
     * Crea las opciones de ingreso/egreso por defecto (si no existen), una por cada
     * comportamiento ligado a un módulo del sistema:
     *   - "Facturas compras"      → COMPRA            (egreso)
     *   - "Liquidaciones compras" → LIQUIDACION       (egreso)
     *   - "Facturas ventas"       → FACTURA_VENTA     (ingreso)
     *   - "Recibos de venta"      → RECIBO_VENTA      (ingreso)
     *   - "Anticipos Clientes"    → ANTICIPO_CLIENTE  (ingreso)
     *   - "Anticipos Proveedores" → ANTICIPO_PROVEEDOR (egreso)
     *   - "Nómina"                → ROL               (egreso, pago de roles de pago)
     * Y dos conceptos sin módulo detrás, para los pagos recurrentes al fisco:
     *   - "SRI"                   → GENERAL           (egreso)
     *   - "IESS"                  → GENERAL           (egreso)
     * Cada una se verifica de forma independiente por comportamiento, salvo las dos
     * GENERAL, que se verifican por nombre (GENERAL no identifica a una sola opción).
     * Ninguna nace con cuenta contable: cuando se crea la empresa todavía no existe el
     * plan de cuentas. Las cuentas se asignan al cargar el plan modelo, en
     * PlanCuentaService::sembrarCuentasFormasYOpciones().
     */
    private function crearOpcionesIngresoEgresoDefault(int $idEmpresa, int $idUsuario): void
    {
        $opciones = [
            ['nombre' => 'Facturas compras',      'comportamiento' => 'COMPRA',            'ingresos' => 'false', 'egresos' => 'true'],
            ['nombre' => 'Liquidaciones compras', 'comportamiento' => 'LIQUIDACION',       'ingresos' => 'false', 'egresos' => 'true'],
            ['nombre' => 'Facturas ventas',       'comportamiento' => 'FACTURA_VENTA',     'ingresos' => 'true',  'egresos' => 'false'],
            ['nombre' => 'Recibos de venta',      'comportamiento' => 'RECIBO_VENTA',      'ingresos' => 'true',  'egresos' => 'false'],
            ['nombre' => 'Anticipos Clientes',    'comportamiento' => 'ANTICIPO_CLIENTE',   'ingresos' => 'true',  'egresos' => 'false'],
            ['nombre' => 'Anticipos Proveedores', 'comportamiento' => 'ANTICIPO_PROVEEDOR', 'ingresos' => 'false', 'egresos' => 'true'],
            ['nombre' => 'Nómina',                'comportamiento' => 'ROL',               'ingresos' => 'false', 'egresos' => 'true'],
            // Conceptos sin módulo detrás (comportamiento GENERAL): llevan su propia cuenta
            // contable, que se asigna al cargar el plan modelo (aquí todavía no hay cuentas).
            // Al ser ambos GENERAL, se verifican por nombre y no por comportamiento.
            ['nombre' => 'SRI',                   'comportamiento' => 'GENERAL',           'ingresos' => 'false', 'egresos' => 'true'],
            ['nombre' => 'IESS',                  'comportamiento' => 'GENERAL',           'ingresos' => 'false', 'egresos' => 'true'],
        ];

        try {
            // Asegura que la tabla exista (su repositorio la crea de forma perezosa).
            new \App\repositories\modulos\OpcionIngresoEgresoRepository();

            $check = $this->db->prepare(
                "SELECT 1 FROM empresa_opciones_ingreso_egreso
                 WHERE id_empresa = :id_empresa AND comportamiento = :comp AND eliminado = false
                 LIMIT 1"
            );
            // GENERAL no identifica a una sola opción: para esas se busca por nombre.
            $checkNombre = $this->db->prepare(
                "SELECT 1 FROM empresa_opciones_ingreso_egreso
                 WHERE id_empresa = :id_empresa AND UPPER(TRIM(nombre)) = :nombre AND eliminado = false
                 LIMIT 1"
            );
            $insert = $this->db->prepare(
                "INSERT INTO empresa_opciones_ingreso_egreso (
                    id_empresa, nombre, aplica_ingresos, aplica_egresos, comportamiento, estado, created_by, created_at, eliminado
                 ) VALUES (
                    :id_empresa, :nombre, :ingresos, :egresos, :comp, 'ACTIVO', :created_by, CURRENT_TIMESTAMP, false
                 )"
            );

            foreach ($opciones as $o) {
                if ($o['comportamiento'] === 'GENERAL') {
                    $checkNombre->execute([':id_empresa' => $idEmpresa, ':nombre' => mb_strtoupper($o['nombre'])]);
                    if ($checkNombre->fetchColumn()) {
                        continue;
                    }
                } else {
                    $check->execute([':id_empresa' => $idEmpresa, ':comp' => $o['comportamiento']]);
                    if ($check->fetchColumn()) {
                        continue;
                    }
                }
                $insert->execute([
                    ':id_empresa' => $idEmpresa,
                    ':nombre'     => $o['nombre'],
                    ':ingresos'   => $o['ingresos'],
                    ':egresos'    => $o['egresos'],
                    ':comp'       => $o['comportamiento'],
                    ':created_by' => $idUsuario,
                ]);
            }
        } catch (\Throwable $e) {
            // No bloquear la inicialización si el módulo/tabla aún no está disponible.
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Unidades de medida
    // ─────────────────────────────────────────────────────────────────

    /**
     * Siembra el catálogo por defecto de tipos y unidades de medida.
     * Delega en UnidadesMedidaService::sembrarCatalogoDefault(), que es
     * idempotente (completa lo que falte sin tocar ni duplicar lo que la
     * empresa ya tenga) y es también el método que usa el botón manual del
     * módulo modulos/unidades-medida.
     */
    private function crearUnidadesMedidaDefault(int $idEmpresa, int $idUsuario): void
    {
        try {
            $service = new \App\Services\modulos\UnidadesMedidaService(
                new \App\repositories\modulos\UnidadesMedidaRepository(),
                new \App\Rules\modulos\UnidadesMedidaRules(),
                new \App\Services\LogSistemaService()
            );
            $service->sembrarCatalogoDefault($idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            // Nunca impedir que se guarde la empresa: en el siguiente guardado
            // se vuelve a intentar y completa lo que falte.
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Correo
    // ─────────────────────────────────────────────────────────────────

    /**
     * Configura el correo por defecto usando el sistema de Camagare,
     * con envío automático y asunto predeterminado.
     */
    private function configurarCorreo(int $idEmpresa, int $idUsuario): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM empresa_correo WHERE id_empresa = :id_empresa AND eliminado = false LIMIT 1"
        );
        $existe->execute([':id_empresa' => $idEmpresa]);

        if ($existe->fetchColumn()) {
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_correo (
                id_empresa, tipo_correo, envio_automatico, asunto_correo,
                ssl_habilitado, host, puerto, correo_emisor, password_correo_emisor,
                cuerpo_correo, modo_cuerpo_correo, created_by, updated_by
             ) VALUES (
                :id_empresa, 'camagare', TRUE, 'Nuevo documento electrónico',
                TRUE, '', 0, '', '',
                '', 'diseno', :usuario, :usuario
             )"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':usuario'    => $idUsuario,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Establecimiento
    // ─────────────────────────────────────────────────────────────────

    /**
     * Devuelve el id del primer establecimiento activo de la empresa.
     * Si no existe ninguno, crea el establecimiento Matriz 001.
     */
    private function obtenerOCrearEstablecimientoPrincipal(int $idEmpresa, int $idUsuario): int
    {
        $res = $this->db->prepare(
            "SELECT id FROM empresa_establecimiento
             WHERE id_empresa = :id_empresa AND eliminado = false
             ORDER BY id ASC LIMIT 1"
        );
        $res->execute([':id_empresa' => $idEmpresa]);
        $id = $res->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_establecimiento (
                id_empresa, nombre, codigo, direccion, tipo,
                logo_ruta, leyenda_pdf_titulo, leyenda_pdf_mensaje,
                estado, created_by, updated_by
             ) VALUES (
                :id_empresa, 'Matriz', '001', '', 'Matriz',
                '', '', '',
                'activo', :usuario, :usuario
             ) RETURNING id"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':usuario'    => $idUsuario,
        ]);
        return (int) $stmt->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────────
    // Punto de emisión
    // ─────────────────────────────────────────────────────────────────

    /**
     * Devuelve el id del primer punto de emisión activo de la empresa.
     * Si no existe, crea el punto 001 "Principal" vinculado al establecimiento dado.
     */
    private function obtenerOCrearPuntoEmision001(int $idEmpresa, int $idEstablecimiento, int $idUsuario): int
    {
        $res = $this->db->prepare(
            "SELECT id FROM empresa_punto_emision
             WHERE id_empresa = :id_empresa AND eliminado = false
             ORDER BY id ASC LIMIT 1"
        );
        $res->execute([':id_empresa' => $idEmpresa]);
        $id = $res->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_punto_emision (
                id_empresa, id_establecimiento, nombre, codigo_punto,
                logo_ruta, estado, created_by, updated_by
             ) VALUES (
                :id_empresa, :id_est, 'Principal', '001',
                '', 'activo', :usuario, :usuario
             ) RETURNING id"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':id_est'     => $idEstablecimiento,
            ':usuario'    => $idUsuario,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve el id del punto de emisión dedicado a "Facturas de reembolso" de la
     * empresa. Si ya existe uno (tiene un secuencial activo de ese tipo), lo reutiliza;
     * si no, crea un punto nuevo con el siguiente código libre del establecimiento
     * (nunca el 001, que es el de Facturas de venta — mismo codDoc SRI, no pueden
     * compartir punto). Se crea INACTIVO: el módulo de Reembolso no se usa en todas
     * las empresas, así que el usuario lo activa manualmente cuando lo necesite.
     */
    private function obtenerOCrearPuntoEmisionReembolso(int $idEmpresa, int $idEstablecimiento, int $idUsuario): int
    {
        $res = $this->db->prepare(
            "SELECT es.id_punto_emision
             FROM empresa_secuencial es
             INNER JOIN empresa_punto_emision pe ON pe.id = es.id_punto_emision
             WHERE es.id_empresa = :id_empresa AND es.tipo_documento = 'Facturas de reembolso'
               AND es.eliminado = false AND pe.eliminado = false
             ORDER BY es.id ASC LIMIT 1"
        );
        $res->execute([':id_empresa' => $idEmpresa]);
        $id = $res->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $codigo = $this->siguienteCodigoPuntoLibre($idEmpresa, $idEstablecimiento);

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_punto_emision (
                id_empresa, id_establecimiento, nombre, codigo_punto,
                logo_ruta, estado, created_by, updated_by
             ) VALUES (
                :id_empresa, :id_est, 'Reembolso', :codigo,
                '', 'inactivo', :usuario, :usuario
             ) RETURNING id"
        );
        $stmt->execute([
            ':id_empresa' => $idEmpresa,
            ':id_est'     => $idEstablecimiento,
            ':codigo'     => $codigo,
            ':usuario'    => $idUsuario,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** Primer código de punto de emisión (a partir de "002") no usado todavía en el establecimiento. */
    private function siguienteCodigoPuntoLibre(int $idEmpresa, int $idEstablecimiento): string
    {
        $res = $this->db->prepare(
            "SELECT codigo_punto FROM empresa_punto_emision
             WHERE id_empresa = :id_empresa AND id_establecimiento = :id_est AND eliminado = false"
        );
        $res->execute([':id_empresa' => $idEmpresa, ':id_est' => $idEstablecimiento]);
        $usados = array_map('strval', $res->fetchAll(\PDO::FETCH_COLUMN));

        for ($n = 2; $n <= 999; $n++) {
            $codigo = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (!in_array($codigo, $usados, true)) {
                return $codigo;
            }
        }
        return '999'; // fallback extremo — no debería alcanzarse en la práctica
    }

    // ─────────────────────────────────────────────────────────────────
    // Secuenciales
    // ─────────────────────────────────────────────────────────────────

    /**
     * Crea los secuenciales iniciales (todos los tipos en 1) para el punto de emisión,
     * solo si el punto no tiene ningún secuencial registrado.
     */
    private function crearSecuencialesIniciales(int $idPunto, int $idEmpresa, int $idUsuario): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM empresa_secuencial
             WHERE id_punto_emision = :id_punto AND id_empresa = :id_empresa AND eliminado = false
             LIMIT 1"
        );
        $existe->execute([':id_punto' => $idPunto, ':id_empresa' => $idEmpresa]);

        if ($existe->fetchColumn()) {
            return;
        }

        $tipos = [
            'Facturas de venta',
            'Nota de crédito',
            'Nota de débito',
            'Retenciones de compras',
            'Guía de remisión',
            'Liquidación de compras o servicios',
            'Ingresos',
            'Egresos',
            'Pedidos',
            'Órdenes de compra',
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_secuencial (
                id_punto_emision, id_empresa, tipo_documento, secuencial_inicial, created_by, updated_by
             ) VALUES (
                :id_punto, :id_empresa, :tipo, 1, :usuario, :usuario
             )"
        );

        foreach ($tipos as $tipo) {
            $stmt->execute([
                ':id_punto'   => $idPunto,
                ':id_empresa' => $idEmpresa,
                ':tipo'       => $tipo,
                ':usuario'    => $idUsuario,
            ]);
        }
    }

    /** Secuencial de "Facturas de reembolso" en su punto dedicado, solo si aún no existe. */
    private function crearSecuencialReembolso(int $idPunto, int $idEmpresa, int $idUsuario): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM empresa_secuencial
             WHERE id_punto_emision = :id_punto AND id_empresa = :id_empresa
               AND tipo_documento = 'Facturas de reembolso' AND eliminado = false
             LIMIT 1"
        );
        $existe->execute([':id_punto' => $idPunto, ':id_empresa' => $idEmpresa]);
        if ($existe->fetchColumn()) {
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO empresa_secuencial (
                id_punto_emision, id_empresa, tipo_documento, secuencial_inicial, created_by, updated_by
             ) VALUES (
                :id_punto, :id_empresa, 'Facturas de reembolso', 1, :usuario, :usuario
             )"
        );
        $stmt->execute([
            ':id_punto'   => $idPunto,
            ':id_empresa' => $idEmpresa,
            ':usuario'    => $idUsuario,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Formulario 104 IVA
    // ─────────────────────────────────────────────────────────────────

    /**
     * Carga los casilleros 104 predeterminados si la empresa no tiene ninguno configurado.
     */
    private function cargarCasilleros104(int $idEmpresa): void
    {
        $existe = $this->db->prepare(
            "SELECT 1 FROM empresa_casilleros_iva_sri
             WHERE id_empresa = :id_empresa AND eliminado = false
             LIMIT 1"
        );
        $existe->execute([':id_empresa' => $idEmpresa]);

        if ($existe->fetchColumn()) {
            return;
        }

        try {
            (new \App\services\modulos\EmpresaService())->cargarCasilleros104Default($idEmpresa);
        } catch (\Throwable $e) {
            // No bloquear la inicialización si el archivo JSON no está disponible
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Configuración de facturación
    // ─────────────────────────────────────────────────────────────────

    /**
     * Aplica la configuración de facturación predeterminada al establecimiento,
     * solo si aún no ha sido configurada. Se usa id_forma_pago_sri_def IS NULL
     * como marca de "nunca configurado": a diferencia de editar_precio_factura
     * (que tiene DEFAULT 'true' en la columna y por eso NUNCA es NULL desde el
     * INSERT en Empresa::crear, lo que hacía que este método se saltara siempre
     * y jamás aplicara la forma de pago SRI / límite consumidor final por
     * defecto), id_forma_pago_sri_def no tiene default y permanece NULL hasta
     * que este método o el usuario lo fijan.
     *
     * Activa: editar precio, editar IVA, editar descuento.
     * Forma de pago SRI: código '20'.
     * Valor consumidor final: 50. Cálculo IVA: al subtotal.
     */
    private function configurarFacturacion(int $idEstablecimiento, int $idUsuario): void
    {
        $res = $this->db->prepare(
            "SELECT id_forma_pago_sri_def FROM empresa_establecimiento
             WHERE id = :id AND eliminado = false"
        );
        $res->execute([':id' => $idEstablecimiento]);
        $row = $res->fetch(\PDO::FETCH_ASSOC);

        if (!$row || $row['id_forma_pago_sri_def'] !== null) {
            return;
        }

        // Buscar el id de la forma de pago SRI con código '20'
        $idFormaPago = 'NULL';
        try {
            $fp = $this->db->query(
                "SELECT COALESCE(id_forma_pago, id) AS id FROM formas_pago_sri WHERE codigo = '20' LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            if ($fp && isset($fp['id'])) {
                $idFormaPago = (int) $fp['id'];
            }
        } catch (\Throwable $e) {
            try {
                $fp = $this->db->query(
                    "SELECT id FROM formas_pago_sri WHERE codigo = '20' LIMIT 1"
                )->fetch(\PDO::FETCH_ASSOC);
                if ($fp) {
                    $idFormaPago = (int) $fp['id'];
                }
            } catch (\Throwable $e2) {}
        }

        $sql = "UPDATE empresa_establecimiento SET
                    editar_precio_factura        = 'true',
                    editar_iva_factura           = 'true',
                    editar_descuento_factura     = 'true',
                    facturacion_inventario       = 'false',
                    facturacion_libre            = 'false',
                    factura_solo_stock_positivo  = 'false',
                    obligatorio_lotes            = 'false',
                    obligatorio_caducidad        = 'false',
                    obligatorio_nup              = 'false',
                    mostrar_cajero_factura       = 'false',
                    mostrar_vendedor_factura     = 'false',
                    mostrar_unidad_medida        = 'false',
                    mostrar_propina_factura      = 'false',
                    id_forma_pago_sri_def        = {$idFormaPago},
                    valor_limite_consumidor_final = 50,
                    calculo_iva_facturacion      = 'subtotal',
                    updated_by                   = :usuario,
                    updated_at                   = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id'      => $idEstablecimiento,
            ':usuario' => $idUsuario,
        ]);
    }
}
