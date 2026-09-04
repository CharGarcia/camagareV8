<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Repositorio del módulo Comandas (POS Restaurantes).
 *
 * Una comanda es de UNA mesa; queda 'abierta' mientras se le agregan líneas
 * en rondas. numero_comanda es un correlativo INTERNO por empresa (no es un
 * secuencial SRI). El cobro (Fase 3) genera uno o varios documentos de venta
 * vía PosVentaService, sin que este repositorio conozca esa lógica.
 */
class ComandaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('comandas');
    }

    // ─── TABLERO DE MESAS ──────────────────────────────────────────────────────

    /**
     * Mesas de la empresa con su comanda abierta (si tiene). Sin filtro de
     * "registros propios": el salón es compartido, todo mesero debe ver todas
     * las mesas (a diferencia del listado CRUD de mesas, que sí filtra por
     * created_by cuando el usuario no tiene acceso total).
     */
    /**
     * ¿Está aplicada la migración del recargo por servicio
     * (20260819_servicio_restaurante_propina.sql)? Se consulta una vez por
     * request. Mientras falte, el módulo funciona como antes —sin servicio—
     * en vez de romperse porque el código llegó al servidor antes que el SQL.
     */
    public function tieneColumnasServicio(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $st = $this->db->query("SELECT 1 FROM information_schema.columns
                                        WHERE table_name = 'comandas' AND column_name = 'aplica_servicio'");
                $existe = (bool) $st->fetchColumn();
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
    }

    /** Cacheado por request. Mientras falte comandas_aviso_pedido_qr.sql, el aviso simplemente no existe. */
    public function tieneColumnaAvisoPedidoQr(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $st = $this->db->query("SELECT 1 FROM information_schema.columns
                                        WHERE table_name = 'comandas' AND column_name = 'pedido_qr_pendiente'");
                $existe = (bool) $st->fetchColumn();
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
    }

    /** Enciende o apaga el aviso de "esta mesa pidió desde el QR" (lo lee el tablero). */
    public function marcarPedidoQr(int $idComanda, int $idEmpresa, bool $pendiente): void
    {
        if (!$this->tieneColumnaAvisoPedidoQr()) {
            return;
        }
        $sql = "UPDATE comandas
                   SET pedido_qr_pendiente = :p,
                       pedido_qr_at = CASE WHEN :p2 THEN CURRENT_TIMESTAMP ELSE pedido_qr_at END
                 WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':p'  => $pendiente ? 'true' : 'false',
            ':p2' => $pendiente ? 'true' : 'false',
            ':id' => $idComanda,
            ':e'  => $idEmpresa,
        ]);
    }

    /**
     * Cacheado por request. Igual que arriba: si el código llega al servidor
     * antes que comandas_forma_pago_sugerida_qr.sql, la sugerencia del cliente
     * simplemente no se lee, en vez de tumbar la pantalla de la comanda.
     */
    public function tieneColumnaFormaPagoSugerida(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $st = $this->db->query("SELECT 1 FROM information_schema.columns
                                        WHERE table_name = 'comanda_grupos_cobro' AND column_name = 'id_forma_pago_sugerida'");
                $existe = (bool) $st->fetchColumn();
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
    }

    /**
     * @param string $modoServicio 'no' | 'obligatorio' | 'opcional', ya resuelto
     *                             por PosVentaService::getConfigServicio().
     * @param float  $pctVigente   Porcentaje configurado, para las comandas que
     *                             se abrieron antes de que existiera el recargo
     *                             y no tienen snapshot propio.
     */
    public function getTablero(int $idEmpresa, string $modoServicio = 'no', float $pctVigente = 0.0, int $idProductoPropina = 0): array
    {
        // La propina voluntaria viaja como una línea más, pero no genera recargo
        // por servicio: se excluye de la base igual que en PosVentaService y en
        // el pie de la comanda, o el tablero mostraría un total mayor. Igual se
        // excluye un producto marcado "no aplica recargo de servicio" (envases,
        // empaques, etc.) — mismo criterio en los tres sitios.
        $condPropina = $idProductoPropina > 0
            ? "d.id_producto IS DISTINCT FROM " . $idProductoPropina . " AND COALESCE(p.excluir_recargo_servicio, false) = false"
            : "COALESCE(p.excluir_recargo_servicio, false) = false";

        // Aviso de "esta mesa pidió desde el QR". Mientras falte su migración se
        // devuelve en false, para no tumbar el tablero.
        $selPedidoQr = $this->tieneColumnaAvisoPedidoQr()
            ? "COALESCE(c.pedido_qr_pendiente, false) AS pedido_qr_pendiente,"
            : "false AS pedido_qr_pendiente,";

        // Misma regla que ComandaService::porcentajeServicioComanda(): con
        // 'obligatorio' el recargo va sí o sí, sin mirar el estado de la
        // comanda; con 'opcional' manda lo que decidió el mesero.
        $conServicio = $this->tieneColumnasServicio() && $modoServicio !== 'no';
        $pct = "COALESCE(NULLIF(c.porcentaje_servicio, 0), " . (float) $pctVigente . ")";
        $condicion = $modoServicio === 'obligatorio' ? "c.id IS NOT NULL" : "COALESCE(c.aplica_servicio, false)";
        $exprServicio = "CASE WHEN {$condicion}
                              THEN ROUND(COALESCE(dt.base_servicio, 0) * {$pct} / 100.0, 2)
                              ELSE 0 END";

        $selServicio = $conServicio
            ? "CASE WHEN {$condicion} THEN true ELSE false END AS aplica_servicio,
               CASE WHEN {$condicion} THEN {$pct} ELSE 0 END AS porcentaje_servicio,
               {$exprServicio} AS servicio_comanda,"
            : "false AS aplica_servicio, 0 AS porcentaje_servicio, 0 AS servicio_comanda,";
        $sumaServicio = $conServicio ? "+ {$exprServicio}" : "";

        $sql = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.ubicacion, m.pos_x, m.pos_y,
                       c.id AS id_comanda, c.numero_comanda, c.fecha_apertura, c.comensales,
                       c.id_usuario_mesero, u.nombre AS mesero_nombre,
                       COALESCE(c.solicita_asistencia, false) AS solicita_asistencia,
                       {$selPedidoQr}
                       -- Recargo por servicio (el 10%): sobre la base sin impuestos,
                       -- igual que la propina del comprobante.
                       {$selServicio}
                       COALESCE(dt.base, 0) + COALESCE(dt.iva, 0) {$sumaServicio} AS total_comanda,
                       COALESCE(dt.items, 0) AS items_comanda,
                       COALESCE(dt.pendientes, 0) AS pendientes_comanda,
                       COALESCE(dt.listos, 0) AS listos_comanda
                FROM mesas m
                LEFT JOIN comandas c ON c.id_mesa = m.id AND c.estado = 'abierta' AND c.eliminado = false
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                LEFT JOIN (
                    -- El total de la mesa se muestra CON impuestos: es lo que va a
                    -- pagar el cliente. El IVA se redondea línea por línea y se
                    -- suma, igual que PosVentaService::cobrar() al emitir el
                    -- documento, para que el tablero, el pie de la comanda y la
                    -- factura digan el mismo número.
                    SELECT d.id_comanda,
                           SUM(d.subtotal) AS base,
                           -- Mismo orden que SQL_SELECT_IVA: manda la tarifa del
                           -- ítem del Menú y solo si no tiene, la del producto.
                           SUM(ROUND(d.subtotal * COALESCE(tm.porcentaje_iva, tp.porcentaje_iva, 0) / 100.0, 2)) AS iva,
                           -- Base del recargo por servicio: el consumo, sin la
                           -- línea de propina voluntaria (que no lo genera).
                           SUM(d.subtotal) FILTER (WHERE {$condPropina}) AS base_servicio,
                           COUNT(*) AS items,
                           COUNT(*) FILTER (WHERE d.estado_linea = 'pendiente') AS pendientes,
                           COUNT(*) FILTER (WHERE d.estado_linea = 'listo') AS listos
                    FROM comanda_detalle d
                    " . self::SQL_JOIN_IVA . "
                    WHERE d.id_empresa = :e2 AND d.eliminado = false AND d.estado_linea != 'anulado'
                    GROUP BY d.id_comanda
                ) dt ON dt.id_comanda = c.id
                WHERE m.id_empresa = :e AND m.eliminado = false
                ORDER BY m.nombre ASC";
        $st = $this->db->prepare($sql);
        // :e2 repite el valor de :e — con consultas preparadas reales no se
        // puede reusar el mismo placeholder en dos sitios.
        $st->execute([':e' => $idEmpresa, ':e2' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── NUMERACIÓN INTERNA ────────────────────────────────────────────────────

    /**
     * Serializa la generación del correlativo por empresa dentro de la
     * transacción en curso (mismo patrón de advisory lock ya usado en el
     * sistema para evitar duplicados de numeración bajo concurrencia).
     */
    public function bloquearNumeracion(int $idEmpresa): void
    {
        $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('comanda_num:' || :e))")
                  ->execute([':e' => $idEmpresa]);
    }

    /**
     * Candado del cobro de un grupo. A diferencia del resto de candados del
     * sistema NO es `_xact`: la sección crítica del cobro (validar que el grupo
     * siga pendiente → emitir el documento → marcarlo cobrado) atraviesa VARIAS
     * transacciones, porque PosVentaService::cobrar() abre la suya para emitir
     * el comprobante fiscal. Un `pg_advisory_xact_lock` se soltaría en ese
     * primer COMMIT y dejaría el hueco abierto justo donde importa.
     *
     * Es `try`, no bloqueante, a propósito: si otro dispositivo ya está cobrando
     * esta cuenta, el segundo debe recibir un error inmediato — no quedarse
     * esperando para acabar emitiendo un documento duplicado. Se libera siempre
     * en el `finally` del cobro (y, si el proceso muriera antes, al cerrarse la
     * conexión del request).
     */
    public function intentarLockCobroGrupo(int $idGrupo, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT pg_try_advisory_lock(hashtext('comanda_cobro:' || :e || ':' || :g))");
        $st->execute([':e' => $idEmpresa, ':g' => $idGrupo]);
        return (bool) $st->fetchColumn();
    }

    public function liberarLockCobroGrupo(int $idGrupo, int $idEmpresa): void
    {
        $this->db->prepare("SELECT pg_advisory_unlock(hashtext('comanda_cobro:' || :e || ':' || :g))")
                  ->execute([':e' => $idEmpresa, ':g' => $idGrupo]);
    }

    public function getSiguienteNumero(int $idEmpresa): string
    {
        $sql = "SELECT COALESCE(MAX(CAST(regexp_replace(numero_comanda, '\\D', '', 'g') AS INTEGER)), 0) + 1
                FROM comandas WHERE id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $next = (int) $st->fetchColumn();
        return 'C-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    // ─── CABECERA ──────────────────────────────────────────────────────────────

    public function existeComandaAbierta(int $idMesa, int $idEmpresa): bool
    {
        $sql = "SELECT 1 FROM comandas
                WHERE id_mesa = :m AND id_empresa = :e AND estado = 'abierta' AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':m' => $idMesa, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    /** Comanda abierta de una mesa (portal público QR: reutilizarla si ya existe, en vez de abrir otra). */
    public function getAbiertaPorMesa(int $idMesa, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM comandas
                WHERE id_mesa = :m AND id_empresa = :e AND estado = 'abierta' AND eliminado = false
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':m' => $idMesa, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crear(array $data): int
    {
        $cols = "id_empresa, id_mesa, id_usuario_mesero, id_caja_sesion, numero_comanda,
                 estado, id_cliente, comensales, observaciones, created_by";
        $vals = ":id_empresa, :id_mesa, :id_usuario_mesero, :id_caja_sesion, :numero_comanda,
                 :estado, :id_cliente, :comensales, :observaciones, :created_by";
        $params = [
            ':id_empresa'        => $data['id_empresa'],
            ':id_mesa'           => $data['id_mesa'],
            ':id_usuario_mesero' => $data['id_usuario_mesero'],
            ':id_caja_sesion'    => $data['id_caja_sesion'] ?? null,
            ':numero_comanda'    => $data['numero_comanda'],
            ':estado'            => $data['estado'] ?? 'abierta',
            ':id_cliente'        => $data['id_cliente'] ?? null,
            ':comensales'        => $data['comensales'] ?? null,
            ':observaciones'     => $data['observaciones'] ?? null,
            ':created_by'        => $data['created_by'],
        ];

        if ($this->tieneColumnasServicio()) {
            $cols .= ", aplica_servicio, porcentaje_servicio";
            $vals .= ", :aplica_servicio, :porcentaje_servicio";
            // Booleano a Postgres por PDO: 'true'/'false' como texto — el false
            // de PHP viaja como cadena vacía y pgsql lo rechaza.
            $params[':aplica_servicio']     = !empty($data['aplica_servicio']) ? 'true' : 'false';
            $params[':porcentaje_servicio'] = (float) ($data['porcentaje_servicio'] ?? 0);
        }

        $st = $this->db->prepare("INSERT INTO comandas ({$cols}) VALUES ({$vals}) RETURNING id");
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT c.*, m.nombre AS mesa_nombre, m.estado AS mesa_estado,
                       cl.nombre AS cliente_nombre, cl.identificacion AS cliente_identificacion,
                       cl.email AS cliente_email, cl.direccion AS cliente_direccion,
                       cl.telefono AS cliente_telefono, u.nombre AS mesero_nombre
                FROM comandas c
                JOIN mesas m ON m.id = c.id_mesa
                LEFT JOIN clientes cl ON cl.id = c.id_cliente
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                WHERE c.id = :id AND c.id_empresa = :e AND c.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** "Llamar al mesero" desde el portal QR — aviso visible en el tablero y en la comanda hasta que alguien lo atienda. */
    public function solicitarAsistencia(int $id, int $idEmpresa): void
    {
        $sql = "UPDATE comandas SET solicita_asistencia = true, asistencia_solicitada_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    public function atenderAsistencia(int $id, int $idEmpresa): void
    {
        $sql = "UPDATE comandas SET solicita_asistencia = false
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':id' => $id, ':e' => $idEmpresa]);
    }

    public function actualizarEstadoComanda(int $id, int $idEmpresa, string $estado, int $idUsuario, bool $setFechaCierre = false): void
    {
        $extra = $setFechaCierre ? ", fecha_cierre = CURRENT_TIMESTAMP" : "";
        $sql = "UPDATE comandas
                SET estado = :estado, updated_by = :u, updated_at = CURRENT_TIMESTAMP $extra
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':estado' => $estado, ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function actualizarCabecera(int $id, int $idEmpresa, array $data): void
    {
        $fields = [];
        $params = [':id_' => $id, ':e_' => $idEmpresa];
        foreach ($data as $k => $v) {
            $fields[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        if (empty($fields)) return;
        $sql = "UPDATE comandas SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP
                WHERE id = :id_ AND id_empresa = :e_ AND eliminado = false";
        $this->db->prepare($sql)->execute($params);
    }

    // ─── LÍNEAS ────────────────────────────────────────────────────────────────

    /**
     * IVA de una línea (alias 'd'): manda la tarifa del ítem del Menú y, solo si
     * la línea no viene del Menú, la del producto — mismo criterio que
     * MenuRepository::getDisponibles(). La carta define con qué impuesto se
     * vende el plato: si el ítem se creó con una tarifa, esa es la que ve el
     * mesero y la que termina en el comprobante.
     *
     * Devuelve también el id de la tarifa resuelta, porque al cobrar viaja hasta
     * PosVentaService (clave 'id_tarifa_iva' de cada ítem) para que el documento
     * se emita con esta misma tarifa en vez de volver a resolverla desde el
     * producto. Sin eso, la pantalla mostraría una y la factura saldría con otra.
     */
    private const SQL_SELECT_IVA = "COALESCE(tm.porcentaje_iva, tp.porcentaje_iva, 0) AS porcentaje_iva,
                COALESCE(mi.id_tarifa_iva, p.tarifa_iva) AS id_tarifa_iva,
                COALESCE(p.excluir_recargo_servicio, false) AS excluir_recargo_servicio";
    private const SQL_JOIN_IVA = "LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN tarifa_iva tp ON tp.id = p.tarifa_iva
                LEFT JOIN menu_items mi ON mi.id = d.id_menu_item
                LEFT JOIN tarifa_iva tm ON tm.id = mi.id_tarifa_iva";

    public function getLineas(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT d.*, p.codigo AS producto_codigo, " . self::SQL_SELECT_IVA . "
                FROM comanda_detalle d
                " . self::SQL_JOIN_IVA . "
                WHERE d.id_comanda = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertLinea(array $d): int
    {
        // estado_linea normalmente arranca en 'pendiente' (falta enviarla a
        // preparación). La propina voluntaria entra ya como 'entregado': no se
        // prepara ni se sirve, y dejarla pendiente la haría figurar en el aviso
        // de ítems por entregar y bloquearía el pago desde el QR del cliente
        // (ComandaRules::validarLineaCobrableQr exige que todo esté entregado).
        // Lo mismo pasa con el local que no trabaja con preparación (ninguna
        // estación activa): sus líneas nacen entregadas, porque no hay ningún
        // paso posterior que las mueva de estado.
        $estado = (string) ($d['estado_linea'] ?? 'pendiente');

        // Una línea que nace entregada se sella con su fecha: si no, quedaría
        // como entregada pero sin cuándo, y los reportes de tiempos la contarían
        // como si nunca hubiera salido.
        $colEntregado = $estado === 'entregado' ? ', entregado_at' : '';
        $valEntregado = $estado === 'entregado' ? ', CURRENT_TIMESTAMP' : '';

        $sql = "INSERT INTO comanda_detalle (
                    id_empresa, id_comanda, id_producto, id_menu_item, descripcion, cantidad, precio_unitario,
                    descuento, subtotal, observacion_item, id_estacion_impresion, lote, caducidad, nup, estado_linea, created_by{$colEntregado}
                ) VALUES (
                    :e, :ic, :prod, :menu, :desc, :cant, :pu,
                    :dscto, :sub, :obs, :est, :lote, :cad, :nup, :estado, :cb{$valEntregado}
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $d['id_empresa'],
            ':ic'    => $d['id_comanda'],
            ':prod'  => $d['id_producto'] ?: null,
            ':menu'  => $d['id_menu_item'] ?? null,
            ':desc'  => $d['descripcion'],
            ':cant'  => $d['cantidad'],
            ':pu'    => $d['precio_unitario'],
            ':dscto' => $d['descuento'] ?? 0,
            ':sub'   => $d['subtotal'],
            ':obs'   => $d['observacion_item'] ?? null,
            ':est'   => $d['id_estacion_impresion'] ?? null,
            ':lote'  => $d['lote'] ?: null,
            ':cad'   => $d['caducidad'] ?: null,
            ':nup'   => $d['nup'] ?: null,
            ':estado' => $estado,
            ':cb'    => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    // ─── Propina voluntaria ───────────────────────────────────────────────────

    /**
     * La línea de propina de una comanda, si ya existe. Es única por comanda: el
     * mesero cambia el monto, no acumula varias. Se reconoce por su producto (el
     * configurado en el establecimiento), no por la descripción.
     */
    public function getLineaPropina(int $idComanda, int $idEmpresa, int $idProductoPropina): ?array
    {
        // La propina editable: la que no está en ninguna cuenta y, si no hay, la
        // de una cuenta todavía PENDIENTE — el cliente pide la cuenta y después
        // decide dejar más propina, y esa cuenta se recalcula sola al cambiarla.
        // La de una cuenta ya cobrada queda fuera: pertenece a un documento
        // emitido. Así, además, una comanda dividida puede llevar una propina por
        // cuenta en vez de chocar con la anterior.
        $sql = "SELECT d.* FROM comanda_detalle d
                LEFT JOIN comanda_grupos_cobro g ON g.id = d.id_grupo_cobro AND g.eliminado = false
                WHERE d.id_comanda = :ic AND d.id_empresa = :e AND d.id_producto = :p
                  AND d.eliminado = false AND d.estado_linea != 'anulado'
                  AND (d.id_grupo_cobro IS NULL OR COALESCE(g.estado, '') <> 'cobrado')
                ORDER BY (d.id_grupo_cobro IS NULL) DESC, d.id ASC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa, ':p' => $idProductoPropina]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Cambia el monto de la línea de propina (precio y subtotal van siempre iguales: cantidad 1, sin descuento). */
    public function actualizarMontoPropina(int $idLinea, int $idEmpresa, float $monto): void
    {
        $sql = "UPDATE comanda_detalle SET precio_unitario = :m, subtotal = :m2
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':m' => $monto, ':m2' => $monto, ':id' => $idLinea, ':e' => $idEmpresa]);
    }

    /** Quita la propina (monto 0): eliminación lógica, no queda como ítem anulado a la vista. */
    public function eliminarLineaPropina(int $idLinea, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET eliminado = true
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':id' => $idLinea, ':e' => $idEmpresa]);
    }

    public function anularLinea(int $idLinea, int $idComanda, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET estado_linea = 'anulado'
                WHERE id = :id AND id_comanda = :ic AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':id' => $idLinea, ':ic' => $idComanda, ':e' => $idEmpresa]);
    }

    /** Deshace un "Eliminar ítem": vuelve a 'pendiente' (se re-envía a preparación si hace falta). */
    public function restaurarLinea(int $idLinea, int $idComanda, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET estado_linea = 'pendiente'
                WHERE id = :id AND id_comanda = :ic AND id_empresa = :e AND eliminado = false AND estado_linea = 'anulado'";
        $this->db->prepare($sql)->execute([':id' => $idLinea, ':ic' => $idComanda, ':e' => $idEmpresa]);
    }

    /** Descuento por línea (Porcentaje/Valor ya resuelto a $ por el cliente) — recalcula subtotal. */
    public function actualizarDescuentoLinea(int $idLinea, int $idEmpresa, float $descuento, float $subtotal): void
    {
        $sql = "UPDATE comanda_detalle SET descuento = :d, subtotal = :s
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':d' => $descuento, ':s' => $subtotal, ':id' => $idLinea, ':e' => $idEmpresa]);
    }

    /** Anula todas las líneas activas de la comanda (usado al anular la comanda completa: saca todo del KDS). */
    /** Cuenta las líneas de la comanda (agregadas o ya anuladas) — usado para exigir motivo al anular la comanda completa. */
    public function contarLineas(int $idComanda, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM comanda_detalle WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    public function anularLineasDeComanda(int $idComanda, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET estado_linea = 'anulado'
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea NOT IN ('anulado', 'entregado')";
        $this->db->prepare($sql)->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
    }

    public function getTotal(int $idComanda): float
    {
        $sql = "SELECT COALESCE(SUM(subtotal), 0) FROM comanda_detalle
                WHERE id_comanda = :id AND eliminado = false AND estado_linea != 'anulado'";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idComanda]);
        return (float) $st->fetchColumn();
    }

    // ─── KDS (cocina/barra) ────────────────────────────────────────────────────

    /**
     * Líneas activas (enviado|preparando) para una estación de impresión, con
     * datos de mesa/comanda para agrupar en tarjetas en la pantalla de cocina/barra.
     */
    public function getLineasParaKds(int $idEmpresa, int $idEstacion): array
    {
        $sql = "SELECT d.id, d.id_comanda, d.id_producto, d.descripcion, d.cantidad,
                       d.observacion_item, d.id_estacion_impresion, d.estado_linea,
                       d.enviado_at, d.listo_at,
                       c.numero_comanda, m.nombre AS mesa_nombre
                FROM comanda_detalle d
                JOIN comandas c ON c.id = d.id_comanda
                JOIN mesas m ON m.id = c.id_mesa
                WHERE d.id_empresa = :e AND d.eliminado = false
                  AND d.estado_linea IN ('enviado', 'preparando')
                  AND d.id_estacion_impresion = :est
                ORDER BY d.enviado_at ASC, d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':est' => $idEstacion]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca como 'enviado' las líneas pendientes de la comanda (todas, o solo
     * las indicadas). Devuelve las filas movidas —no un contador— porque la
     * impresión de órdenes necesita saber QUÉ líneas y a qué estación fue cada
     * una para armar un ticket por estación (ImpresionComandaService).
     *
     * @return array<int, array{id:int, id_estacion_impresion:?int}>
     */
    public function enviarLineasACocina(int $idComanda, int $idEmpresa, array $idsLineas = []): array
    {
        $params = [':ic' => $idComanda, ':e' => $idEmpresa];
        $filtroIds = '';
        if (!empty($idsLineas)) {
            $ph = [];
            foreach ($idsLineas as $i => $id) {
                $key = ":l{$i}";
                $ph[] = $key;
                $params[$key] = (int) $id;
            }
            $filtroIds = " AND id IN (" . implode(',', $ph) . ")";
        }
        // Cada línea va a donde le corresponde: la que tiene estación pasa a
        // 'enviado' y espera a cocina/barra; la que no tiene ninguna queda
        // 'entregado' de una vez, porque no hay nada que preparar ni
        // confirmación que esperar. Marcarla 'enviado' la dejaría colgada, sin
        // poder entregarse ni pagarse desde el QR.
        $sql = "UPDATE comanda_detalle
                SET estado_linea = CASE WHEN id_estacion_impresion IS NULL THEN 'entregado' ELSE 'enviado' END,
                    enviado_at = CURRENT_TIMESTAMP,
                    entregado_at = CASE WHEN id_estacion_impresion IS NULL THEN CURRENT_TIMESTAMP ELSE entregado_at END
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea = 'pendiente' $filtroIds
                RETURNING id, id_estacion_impresion";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Transición de estado de una línea (KDS: preparando/listo; mesero: entregado). */
    public function actualizarEstadoLinea(int $idLinea, int $idEmpresa, string $estado): void
    {
        $colFecha = match ($estado) {
            'listo'     => 'listo_at',
            'entregado' => 'entregado_at',
            default     => null,
        };
        $extra = $colFecha ? ", {$colFecha} = CURRENT_TIMESTAMP" : '';
        $sql = "UPDATE comanda_detalle SET estado_linea = :estado $extra
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':estado' => $estado, ':id' => $idLinea, ':e' => $idEmpresa]);
    }

    public function getLinea(int $idLinea, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM comanda_detalle WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idLinea, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Estación de impresión configurada en la categoría del producto (o null si no tiene). */
    public function getEstacionImpresionProducto(int $idProducto, int $idEmpresa): ?int
    {
        $sql = "SELECT cat.id_estacion_impresion
                FROM productos p
                LEFT JOIN categorias cat ON cat.id = p.id_categoria AND cat.id_empresa = p.id_empresa
                WHERE p.id = :id AND p.id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idProducto, ':e' => $idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }

    // ─── COBRO / DIVISIÓN DE CUENTA (comanda_grupos_cobro) ────────────────────

    /**
     * Punto de emisión con el que se abrió el turno de caja de esta comanda
     * (comandas solo guarda id_caja_sesion; el punto de emisión que exige
     * PosVentaService::cobrar() se resuelve por ese turno).
     */
    public function getIdPuntoEmisionDeComanda(int $idComanda, int $idEmpresa): ?int
    {
        $sql = "SELECT cs.id_punto_emision
                FROM comandas c
                JOIN caja_sesiones cs ON cs.id = c.id_caja_sesion
                WHERE c.id = :id AND c.id_empresa = :e";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idComanda, ':e' => $idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }

    /**
     * Líneas activas (no anuladas) que todavía no están asignadas a ningún
     * grupo de cobro — ni "por ítems" (id_grupo_cobro) ni ya metidas en un
     * pool de "partes iguales" (comanda_grupo_partes_lineas).
     */
    public function getLineasSinGrupo(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT d.* FROM comanda_detalle d
                LEFT JOIN comanda_grupo_partes_lineas gpl ON gpl.id_linea = d.id
                WHERE d.id_comanda = :ic AND d.id_empresa = :e AND d.eliminado = false
                  AND d.estado_linea != 'anulado' AND d.id_grupo_cobro IS NULL
                  AND gpl.id IS NULL
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vincula las líneas del "pool" compartido de una división en partes iguales (todas las N partes lo comparten). */
    public function agregarLineasAPoolPartes(int $idGrupoRaiz, array $idsLineas): void
    {
        $sql = "INSERT INTO comanda_grupo_partes_lineas (id_grupo_raiz, id_linea) VALUES (:g, :l)";
        $st = $this->db->prepare($sql);
        foreach ($idsLineas as $idLinea) {
            $st->execute([':g' => $idGrupoRaiz, ':l' => (int) $idLinea]);
        }
    }

    /** Líneas del pool compartido de una división en partes iguales (para armar los ítems fraccionados de cada parte al cobrar). */
    public function getLineasDelPoolPartes(int $idGrupoRaiz, int $idEmpresa): array
    {
        $sql = "SELECT d.*, " . self::SQL_SELECT_IVA . "
                FROM comanda_detalle d
                JOIN comanda_grupo_partes_lineas gpl ON gpl.id_linea = d.id
                " . self::SQL_JOIN_IVA . "
                WHERE gpl.id_grupo_raiz = :g AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':g' => $idGrupoRaiz, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSiguienteNumeroGrupo(int $idComanda): int
    {
        $sql = "SELECT COALESCE(MAX(numero_grupo), 0) + 1 FROM comanda_grupos_cobro WHERE id_comanda = :ic";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda]);
        return (int) $st->fetchColumn();
    }

    public function crearGrupoCobro(array $d): int
    {
        // Mientras falte la migración de la sugerencia, el grupo se crea igual
        // (sin ella) en vez de romper el cobro entero.
        $tieneFps = $this->tieneColumnaFormaPagoSugerida();
        $colFps = $tieneFps ? ', id_forma_pago_sugerida' : '';
        $valFps = $tieneFps ? ', :fps' : '';
        $sql = "INSERT INTO comanda_grupos_cobro (
                    id_empresa, id_comanda, numero_grupo, etiqueta, tipo_split, created_by,
                    id_cliente, tipo_documento_solicitado, origen{$colFps},
                    id_grupo_padre, numero_parte, total_partes
                ) VALUES (
                    :e, :ic, :num, :et, :split, :cb,
                    :cli, :tds, :origen{$valFps},
                    :padre, :nparte, :tpartes
                ) RETURNING id";
        $params = [
            ':e'       => $d['id_empresa'],
            ':ic'      => $d['id_comanda'],
            ':num'     => $d['numero_grupo'],
            ':et'      => $d['etiqueta'],
            ':split'   => $d['tipo_split'] ?? 'items',
            ':cb'      => $d['created_by'],
            ':cli'     => $d['id_cliente'] ?? null,
            ':tds'     => $d['tipo_documento_solicitado'] ?? null,
            ':origen'  => $d['origen'] ?? 'mesero',
            ':padre'   => $d['id_grupo_padre'] ?? null,
            ':nparte'  => $d['numero_parte'] ?? null,
            ':tpartes' => $d['total_partes'] ?? null,
        ];
        if ($tieneFps) {
            // Sugerencia del cliente desde el QR; el cobro real lo decide el mesero.
            $params[':fps'] = !empty($d['id_forma_pago_sugerida']) ? (int) $d['id_forma_pago_sugerida'] : null;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function getGrupo(int $idGrupo, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM comanda_grupos_cobro WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idGrupo, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getGruposDeComanda(int $idComanda, int $idEmpresa): array
    {
        // fps: cómo dijo el cliente que piensa pagar al pedir su cuenta desde el
        // QR. Se muestra al mesero y se le precarga al cobrar; la decisión final
        // sigue siendo suya.
        $selFps  = $this->tieneColumnaFormaPagoSugerida() ? ", fps.nombre AS forma_pago_sugerida_nombre" : ", NULL AS forma_pago_sugerida_nombre";
        $joinFps = $this->tieneColumnaFormaPagoSugerida() ? "LEFT JOIN empresa_formas_pago fps ON fps.id = g.id_forma_pago_sugerida" : "";
        $sql = "SELECT g.*, cl.nombre AS cliente_nombre,
                       cl.identificacion AS cliente_identificacion, cl.email AS cliente_email,
                       cl.direccion AS cliente_direccion, cl.telefono AS cliente_telefono{$selFps}
                FROM comanda_grupos_cobro g
                LEFT JOIN clientes cl ON cl.id = g.id_cliente
                {$joinFps}
                WHERE g.id_comanda = :ic AND g.id_empresa = :e AND g.eliminado = false
                ORDER BY g.numero_grupo ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignarLineasAGrupo(array $idsLineas, int $idGrupo, int $idComanda, int $idEmpresa): int
    {
        if (empty($idsLineas)) return 0;
        $ph = [];
        $params = [':g' => $idGrupo, ':ic' => $idComanda, ':e' => $idEmpresa];
        foreach ($idsLineas as $i => $id) {
            $key = ":l{$i}";
            $ph[] = $key;
            $params[$key] = (int) $id;
        }
        $sql = "UPDATE comanda_detalle SET id_grupo_cobro = :g
                WHERE id_comanda = :ic AND id_empresa = :e AND id_grupo_cobro IS NULL
                  AND estado_linea != 'anulado' AND id IN (" . implode(',', $ph) . ")";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public function getLineasDelGrupo(int $idGrupo, int $idEmpresa): array
    {
        $sql = "SELECT d.*, " . self::SQL_SELECT_IVA . "
                FROM comanda_detalle d
                " . self::SQL_JOIN_IVA . "
                WHERE d.id_grupo_cobro = :g AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':g' => $idGrupo, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Libera las líneas de un grupo (vuelven a quedar "sin grupo") — usado al deshacer un grupo pendiente. */
    public function liberarLineasDelGrupo(int $idGrupo, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET id_grupo_cobro = NULL WHERE id_grupo_cobro = :g AND id_empresa = :e";
        $this->db->prepare($sql)->execute([':g' => $idGrupo, ':e' => $idEmpresa]);
    }

    /** Todas las "partes" (hermanos) de una división en partes iguales, incluida la raíz — para deshacer el split completo. */
    public function getGruposHermanos(int $idGrupoRaiz, int $idEmpresa): array
    {
        $sql = "SELECT * FROM comanda_grupos_cobro
                WHERE id_empresa = :e AND eliminado = false
                  AND (id = :g OR id_grupo_padre = :g)
                ORDER BY numero_parte ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':g' => $idGrupoRaiz, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Libera el pool compartido de una división en partes iguales (al deshacer el split completo). */
    public function liberarPoolPartes(int $idGrupoRaiz): void
    {
        $sql = "DELETE FROM comanda_grupo_partes_lineas WHERE id_grupo_raiz = :g";
        $this->db->prepare($sql)->execute([':g' => $idGrupoRaiz]);
    }

    public function eliminarGrupo(int $idGrupo, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE comanda_grupos_cobro
                SET eliminado = true, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND estado = 'pendiente'";
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':id' => $idGrupo, ':e' => $idEmpresa]);
    }

    public function marcarGrupoCobrado(int $idGrupo, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE comanda_grupos_cobro
                SET estado = 'cobrado', tipo_documento = :td, id_documento = :idd,
                    numero_documento = :nd, forma_pago = :fp,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e";
        $this->db->prepare($sql)->execute([
            ':td'  => $d['tipo_documento'],
            ':idd' => $d['id_documento'],
            ':nd'  => $d['numero_documento'],
            ':fp'  => $d['forma_pago'],
            ':u'   => $d['updated_by'],
            ':id'  => $idGrupo,
            ':e'   => $idEmpresa,
        ]);
    }

    public function contarGruposPendientes(int $idComanda, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM comanda_grupos_cobro
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false AND estado = 'pendiente'";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }
}
