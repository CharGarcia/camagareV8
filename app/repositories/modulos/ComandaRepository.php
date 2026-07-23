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
    public function getTablero(int $idEmpresa): array
    {
        $sql = "SELECT m.id, m.nombre, m.estado, m.capacidad, m.ubicacion, m.pos_x, m.pos_y,
                       c.id AS id_comanda, c.numero_comanda, c.fecha_apertura, c.comensales,
                       c.id_usuario_mesero, u.nombre AS mesero_nombre,
                       COALESCE(dt.total, 0) AS total_comanda,
                       COALESCE(dt.items, 0) AS items_comanda
                FROM mesas m
                LEFT JOIN comandas c ON c.id_mesa = m.id AND c.estado = 'abierta' AND c.eliminado = false
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                LEFT JOIN (
                    SELECT id_comanda, SUM(subtotal) AS total, COUNT(*) AS items
                    FROM comanda_detalle
                    WHERE eliminado = false AND estado_linea != 'anulado'
                    GROUP BY id_comanda
                ) dt ON dt.id_comanda = c.id
                WHERE m.id_empresa = :e AND m.eliminado = false
                ORDER BY m.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
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

    public function crear(array $data): int
    {
        $sql = "INSERT INTO comandas (
                    id_empresa, id_mesa, id_usuario_mesero, id_caja_sesion, numero_comanda,
                    estado, id_cliente, comensales, observaciones, created_by
                ) VALUES (
                    :id_empresa, :id_mesa, :id_usuario_mesero, :id_caja_sesion, :numero_comanda,
                    :estado, :id_cliente, :comensales, :observaciones, :created_by
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
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
        ]);
        return (int) $st->fetchColumn();
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT c.*, m.nombre AS mesa_nombre, m.estado AS mesa_estado,
                       cl.nombre AS cliente_nombre, u.nombre AS mesero_nombre
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

    public function getLineas(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT d.*, p.codigo AS producto_codigo
                FROM comanda_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                WHERE d.id_comanda = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertLinea(array $d): int
    {
        $sql = "INSERT INTO comanda_detalle (
                    id_empresa, id_comanda, id_producto, descripcion, cantidad, precio_unitario,
                    descuento, subtotal, observacion_item, id_estacion_impresion, estado_linea, created_by
                ) VALUES (
                    :e, :ic, :prod, :desc, :cant, :pu,
                    :dscto, :sub, :obs, :est, 'pendiente', :cb
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $d['id_empresa'],
            ':ic'    => $d['id_comanda'],
            ':prod'  => $d['id_producto'],
            ':desc'  => $d['descripcion'],
            ':cant'  => $d['cantidad'],
            ':pu'    => $d['precio_unitario'],
            ':dscto' => $d['descuento'] ?? 0,
            ':sub'   => $d['subtotal'],
            ':obs'   => $d['observacion_item'] ?? null,
            ':est'   => $d['id_estacion_impresion'] ?? null,
            ':cb'    => $d['created_by'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function anularLinea(int $idLinea, int $idComanda, int $idEmpresa): void
    {
        $sql = "UPDATE comanda_detalle SET estado_linea = 'anulado'
                WHERE id = :id AND id_comanda = :ic AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':id' => $idLinea, ':ic' => $idComanda, ':e' => $idEmpresa]);
    }

    /** Anula todas las líneas activas de la comanda (usado al anular la comanda completa: saca todo del KDS). */
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

    /** Marca como 'enviado' las líneas pendientes de la comanda (todas, o solo las indicadas). */
    public function enviarLineasACocina(int $idComanda, int $idEmpresa, array $idsLineas = []): int
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
        $sql = "UPDATE comanda_detalle
                SET estado_linea = 'enviado', enviado_at = CURRENT_TIMESTAMP
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea = 'pendiente' $filtroIds";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
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

    /** Estación de impresión configurada en la categoría del menú (ítems sin producto). */
    public function getEstacionImpresionMenuCategoria(int $idCategoria, int $idEmpresa): ?int
    {
        $sql = "SELECT id_estacion_impresion FROM menu_categorias WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idCategoria, ':e' => $idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }
}
