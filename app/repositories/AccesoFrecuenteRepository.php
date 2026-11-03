<?php

declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Uso de submódulos por usuario y empresa (tabla usuarios_submodulos_uso):
 * alimenta los accesos frecuentes de la pantalla Inicio.
 * Script: database/20261103_usuarios_submodulos_uso.sql
 */
class AccesoFrecuenteRepository extends BaseRepository
{
    /** El puntaje se reduce a la mitad cada 7 días (en segundos). */
    private const VIDA_MEDIA_SEG = 604800;
    /** Abrir la misma pantalla dentro de este lapso cuenta una sola vez. */
    private const MIN_ENTRE_USOS = '5 minutes';

    public function __construct()
    {
        parent::__construct('usuarios_submodulos_uso');
    }

    public function enTransaccion(): bool
    {
        return $this->db->inTransaction();
    }

    /**
     * Suma un uso. Una sola sentencia (INSERT … ON CONFLICT DO UPDATE): el decaimiento
     * y la suma se calculan en la base sobre la fila bloqueada, así dos pestañas a la
     * vez no se pisan el puntaje.
     */
    public function registrarUso(int $idUsuario, int $idEmpresa, int $idSubmodulo): void
    {
        $st = $this->db->prepare(
            "INSERT INTO {$this->table} AS u (id_usuario, id_empresa, id_submodulo)
             VALUES (:id_u, :id_e, :id_s)
             ON CONFLICT (id_usuario, id_empresa, id_submodulo) DO UPDATE SET
                 puntaje    = u.puntaje * power(0.5, EXTRACT(EPOCH FROM (now() - u.ultimo_uso)) / " . self::VIDA_MEDIA_SEG . ") + 1,
                 veces      = u.veces + 1,
                 ultimo_uso = now(),
                 updated_at = now()
             WHERE u.ultimo_uso < now() - INTERVAL '" . self::MIN_ENTRE_USOS . "'"
        );
        $st->execute([':id_u' => $idUsuario, ':id_e' => $idEmpresa, ':id_s' => $idSubmodulo]);
    }

    /**
     * Submódulos ordenados por puntaje actual (con el decaimiento hasta hoy).
     *
     * @return int[] id_submodulo, del más usado al menos usado
     */
    public function idsMasUsados(int $idUsuario, int $idEmpresa, int $limite): array
    {
        $st = $this->db->prepare(
            "SELECT id_submodulo
               FROM {$this->table}
              WHERE id_usuario = :id_u AND id_empresa = :id_e
              ORDER BY puntaje * power(0.5, EXTRACT(EPOCH FROM (now() - ultimo_uso)) / " . self::VIDA_MEDIA_SEG . ") DESC,
                       ultimo_uso DESC
              LIMIT :lim"
        );
        $st->bindValue(':id_u', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':id_e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':lim', max(1, $limite), PDO::PARAM_INT);
        $st->execute();
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
