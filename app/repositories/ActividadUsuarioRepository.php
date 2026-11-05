<?php

declare(strict_types=1);

namespace App\repositories;

/**
 * Escritura de `metricas_actividad_usuario` (Métricas del sistema, Fase B).
 *
 * Una fila por (fecha, usuario, empresa, canal). Cada llamada hace un upsert que solo
 * actualiza si pasaron al menos 4 minutos desde la última marca: así, aunque el llamador
 * no tenga freno propio (API móvil sin APCu), la BD no se escribe en cada petición y
 * `latidos` queda como "ventanas de ~5 min con actividad".
 */
class ActividadUsuarioRepository extends BaseRepository
{
    public const TABLA = 'metricas_actividad_usuario';

    public function __construct()
    {
        parent::__construct(self::TABLA);
    }

    public function registrar(int $idUsuario, int $idEmpresa, string $canal): void
    {
        $sql = "
            INSERT INTO metricas_actividad_usuario
                (fecha, id_usuario, id_empresa, canal, primera_actividad, ultima_actividad, latidos)
            VALUES (CURRENT_DATE, :id_usuario, :id_empresa, :canal, NOW(), NOW(), 1)
            ON CONFLICT (fecha, id_usuario, id_empresa, canal) DO UPDATE
                SET ultima_actividad = NOW(),
                    latidos          = metricas_actividad_usuario.latidos + 1
                WHERE metricas_actividad_usuario.ultima_actividad < NOW() - INTERVAL '4 minutes'";
        $st = $this->db->prepare($sql);
        $st->bindValue(':id_usuario', $idUsuario, \PDO::PARAM_INT);
        $st->bindValue(':id_empresa', $idEmpresa, \PDO::PARAM_INT);
        $st->bindValue(':canal', $canal);
        $st->execute();
    }

    public function tablaDisponible(): bool
    {
        return $this->tablaExiste(self::TABLA);
    }
}
