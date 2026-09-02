<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Cola de impresión de órdenes de cocina/barra (`comandas_impresiones`).
 *
 * Quien imprime no es el servidor —está fuera de la red del restaurante y no
 * alcanza a la impresora— sino el navegador que ya tiene abierto el KDS de la
 * estación: en cada poll recoge lo pendiente, lo manda a la impresora de ese
 * equipo y marca la fila como impresa. De ahí que esto sea una cola y no un
 * disparo en caliente: si la tablet estaba apagada, la orden espera y sale
 * cuando vuelve, sin perderse ni duplicarse.
 *
 * Ver `database/migrations/20260901_ordenes_impresion.sql`.
 */
class ImpresionComandaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('comandas_impresiones');
    }

    /**
     * ¿Está aplicada la migración de impresión de órdenes? Mientras el SQL no
     * se corra en el servidor, todo esto se comporta como si la función no
     * existiera en vez de tumbar el envío a cocina, que es lo que de verdad no
     * puede fallar en un salón lleno.
     */
    public function disponible(): bool
    {
        return $this->tablaExiste('comandas_impresiones');
    }

    /**
     * Estaciones con impresora configurada, indexadas por id. Se usa para
     * decidir qué se encola y con qué formato sale (ancho, copias).
     *
     * @return array<int, array> id de estación => configuración
     */
    public function getEstacionesConImpresora(int $idEmpresa): array
    {
        $sql = "SELECT id, nombre, tipo, imprime_ordenes, imprimir_auto, ancho_papel, copias
                FROM estaciones_impresion
                WHERE id_empresa = :e AND eliminado = false AND activo = true
                  AND imprime_ordenes = true";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);

        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $out[(int) $fila['id']] = $fila;
        }
        return $out;
    }

    /**
     * Líneas de la comanda que siguen vivas en una estación. Es lo que se manda
     * a imprimir cuando alguien pide el ticket a mano o lo reimprime: el envío
     * automático usa las líneas que acaba de mover, no esto.
     *
     * @return int[] ids de comanda_detalle
     */
    public function getLineasVivasDeEstacion(int $idComanda, int $idEmpresa, int $idEstacion): array
    {
        $sql = "SELECT id FROM comanda_detalle
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND id_estacion_impresion = :est
                  AND estado_linea NOT IN ('anulado', 'pendiente')
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa, ':est' => $idEstacion]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Encola un ticket. `$idsLineas` congela qué sale en ESE papel: lo que se
     * agregue después a la misma comanda va en un ticket nuevo.
     */
    public function encolar(int $idEmpresa, int $idComanda, int $idEstacion, array $idsLineas, int $idUsuario, bool $esReimpresion = false): int
    {
        $sql = "INSERT INTO comandas_impresiones
                    (id_empresa, id_comanda, id_estacion, ids_lineas, es_reimpresion, created_by, updated_by)
                VALUES (:e, :ic, :est, :ids, :reimp, :u, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'     => $idEmpresa,
            ':ic'    => $idComanda,
            ':est'   => $idEstacion,
            ':ids'   => implode(',', array_map('intval', $idsLineas)),
            // Booleano explícito como cadena: PHP false se bindea como '' y
            // Postgres rechaza el tipo boolean (ver memoria del proyecto).
            ':reimp' => $esReimpresion ? 'true' : 'false',
            ':u'     => $idUsuario,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Tickets pendientes de una estación, ya armados: cabecera de la comanda +
     * las líneas que le tocan a ese papel. Es lo que consume el KDS en su poll.
     */
    public function getPendientes(int $idEmpresa, int $idEstacion, int $limite = 10): array
    {
        $sql = "SELECT i.id, i.id_comanda, i.id_estacion, i.ids_lineas, i.es_reimpresion, i.created_at,
                       c.numero_comanda, c.observaciones AS comanda_observaciones,
                       m.nombre AS mesa_nombre,
                       e.nombre AS estacion_nombre, e.ancho_papel, e.copias,
                       u.nombre AS mesero_nombre
                FROM comandas_impresiones i
                JOIN comandas c ON c.id = i.id_comanda
                JOIN mesas m ON m.id = c.id_mesa
                JOIN estaciones_impresion e ON e.id = i.id_estacion
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                WHERE i.id_empresa = :e AND i.id_estacion = :est
                  AND i.estado = 'pendiente' AND i.eliminado = false
                ORDER BY i.id ASC
                LIMIT {$limite}";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa, ':est' => $idEstacion]);
        $tickets = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tickets as &$t) {
            $t['lineas'] = $this->getLineasDeTicket((string) $t['ids_lineas'], $idEmpresa);
        }
        return $tickets;
    }

    /**
     * Las líneas que se congelaron en un ticket. Se leen por id y no por estado,
     * porque el ticket debe salir tal como se pidió aunque la cocina ya haya
     * empezado a moverlas.
     */
    private function getLineasDeTicket(string $idsLineas, int $idEmpresa): array
    {
        $ids = array_filter(array_map('intval', explode(',', $idsLineas)));
        if (empty($ids)) {
            return [];
        }

        $ph = [];
        $params = [':e' => $idEmpresa];
        foreach (array_values($ids) as $i => $id) {
            $ph[] = ":l{$i}";
            $params[":l{$i}"] = $id;
        }

        $sql = "SELECT id, descripcion, cantidad, observacion_item, estado_linea
                FROM comanda_detalle
                WHERE id_empresa = :e AND id IN (" . implode(',', $ph) . ")
                  AND eliminado = false AND estado_linea != 'anulado'
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Marca el ticket como impreso. Devuelve false si ya lo estaba (o no existe). */
    public function marcarImpreso(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE comandas_impresiones
                SET estado = 'impreso', impreso_at = CURRENT_TIMESTAMP, impreso_by = :u,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND estado = 'pendiente' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
        return $st->rowCount() > 0;
    }

    /** Historial de impresiones de una comanda (para el botón de reimprimir). */
    public function getPorComanda(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT i.id, i.id_estacion, i.estado, i.es_reimpresion, i.impreso_at, i.created_at,
                       e.nombre AS estacion_nombre
                FROM comandas_impresiones i
                JOIN estaciones_impresion e ON e.id = i.id_estacion
                WHERE i.id_comanda = :ic AND i.id_empresa = :e AND i.eliminado = false
                ORDER BY i.id DESC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Descarta lo que quedó pendiente de una comanda. Se usa al anularla: sin
     * esto, el ticket de una mesa que ya no existe saldría igual en cuanto la
     * pantalla de cocina volviera a tener red.
     */
    public function anularPendientesDeComanda(int $idComanda, int $idEmpresa): int
    {
        $sql = "UPDATE comandas_impresiones
                SET estado = 'anulado', updated_at = CURRENT_TIMESTAMP
                WHERE id_comanda = :ic AND id_empresa = :e AND estado = 'pendiente' AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return $st->rowCount();
    }
}
