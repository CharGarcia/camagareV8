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
                       COALESCE(c.solicita_asistencia, false) AS solicita_asistencia,
                       COALESCE(dt.total, 0) AS total_comanda,
                       COALESCE(dt.items, 0) AS items_comanda,
                       COALESCE(dt.pendientes, 0) AS pendientes_comanda,
                       COALESCE(dt.listos, 0) AS listos_comanda
                FROM mesas m
                LEFT JOIN comandas c ON c.id_mesa = m.id AND c.estado = 'abierta' AND c.eliminado = false
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                LEFT JOIN (
                    SELECT id_comanda, SUM(subtotal) AS total, COUNT(*) AS items,
                           COUNT(*) FILTER (WHERE estado_linea = 'pendiente') AS pendientes,
                           COUNT(*) FILTER (WHERE estado_linea = 'listo') AS listos
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

    /** porcentaje_iva es solo informativo (para la "vista previa" de la cuenta antes de cobrar) — al cobrar, PosVentaService resuelve el IVA de nuevo desde el producto, esto no lo reemplaza. */
    public function getLineas(int $idComanda, int $idEmpresa): array
    {
        // El % de IVA se resuelve primero del producto vinculado y, si no hay
        // uno (ítem puro del Menú), del tarifa_iva propio del menu_item —
        // mismo criterio que MenuRepository::getDisponibles().
        $sql = "SELECT d.*, p.codigo AS producto_codigo,
                       COALESCE(tp.porcentaje_iva, tm.porcentaje_iva, 0) AS porcentaje_iva
                FROM comanda_detalle d
                LEFT JOIN productos p ON p.id = d.id_producto
                LEFT JOIN tarifa_iva tp ON tp.id = p.tarifa_iva
                LEFT JOIN menu_items mi ON mi.id = d.id_menu_item
                LEFT JOIN tarifa_iva tm ON tm.id = mi.id_tarifa_iva
                WHERE d.id_comanda = :id AND d.id_empresa = :e AND d.eliminado = false
                ORDER BY d.id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertLinea(array $d): int
    {
        $sql = "INSERT INTO comanda_detalle (
                    id_empresa, id_comanda, id_producto, id_menu_item, descripcion, cantidad, precio_unitario,
                    descuento, subtotal, observacion_item, id_estacion_impresion, lote, caducidad, nup, estado_linea, created_by
                ) VALUES (
                    :e, :ic, :prod, :menu, :desc, :cant, :pu,
                    :dscto, :sub, :obs, :est, :lote, :cad, :nup, 'pendiente', :cb
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

    /** Líneas activas (no anuladas) que todavía no están asignadas a ningún grupo de cobro. */
    public function getLineasSinGrupo(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT * FROM comanda_detalle
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea != 'anulado' AND id_grupo_cobro IS NULL
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
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
        $sql = "INSERT INTO comanda_grupos_cobro (
                    id_empresa, id_comanda, numero_grupo, etiqueta, tipo_split, created_by,
                    id_cliente, tipo_documento_solicitado, origen
                ) VALUES (
                    :e, :ic, :num, :et, :split, :cb,
                    :cli, :tds, :origen
                ) RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $d['id_empresa'],
            ':ic'     => $d['id_comanda'],
            ':num'    => $d['numero_grupo'],
            ':et'     => $d['etiqueta'],
            ':split'  => $d['tipo_split'] ?? 'items',
            ':cb'     => $d['created_by'],
            ':cli'    => $d['id_cliente'] ?? null,
            ':tds'    => $d['tipo_documento_solicitado'] ?? null,
            ':origen' => $d['origen'] ?? 'mesero',
        ]);
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
        $sql = "SELECT g.*, cl.nombre AS cliente_nombre
                FROM comanda_grupos_cobro g
                LEFT JOIN clientes cl ON cl.id = g.id_cliente
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
        $sql = "SELECT * FROM comanda_detalle
                WHERE id_grupo_cobro = :g AND id_empresa = :e AND eliminado = false
                ORDER BY id ASC";
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
