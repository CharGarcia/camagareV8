<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: actividades (tareas, llamadas, reuniones, notas, correos) de una
 * oportunidad o de un contacto. Tabla crm_actividades.
 *
 * El acceso a una actividad se resuelve por su oportunidad/contacto (si el
 * usuario puede ver la oportunidad, ve sus actividades) o porque la actividad
 * está asignada a él.
 */
class CrmActividadRepository extends BaseRepository
{
    protected string $table = 'crm_actividades';

    private const SELECT = "SELECT a.id, a.id_oportunidad, a.id_contacto, a.tipo, a.titulo, a.descripcion,
               a.vence_at, a.id_responsable, a.completada, a.completada_at, a.created_at, a.created_by,
               ur.nombre AS responsable, uc.nombre AS creado_por,
               (a.completada = false AND a.vence_at IS NOT NULL AND a.vence_at < NOW()) AS vencida
          FROM crm_actividades a
          LEFT JOIN usuarios ur ON ur.id = a.id_responsable
          LEFT JOIN usuarios uc ON uc.id = a.created_by";

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /** Pendientes primero (por vencimiento), luego las completadas más recientes. */
    public function getPorOportunidad(int $idOportunidad, int $idEmpresa): array
    {
        $st = $this->db->prepare(self::SELECT . "
             WHERE a.id_oportunidad = :o AND a.id_empresa = :e AND a.eliminado = false
             ORDER BY a.completada, a.vence_at NULLS LAST, a.created_at DESC, a.id DESC");
        $st->execute([':o' => $idOportunidad, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorContacto(int $idContacto, int $idEmpresa): array
    {
        $st = $this->db->prepare(self::SELECT . "
             WHERE a.id_contacto = :c AND a.id_empresa = :e AND a.eliminado = false
             ORDER BY a.completada, a.vence_at NULLS LAST, a.created_at DESC, a.id DESC");
        $st->execute([':c' => $idContacto, ':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(self::SELECT . ' WHERE a.id = :id AND a.id_empresa = :e AND a.eliminado = false');
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Pendientes del usuario (aviso del navbar y «Mis actividades»). */
    public function getPendientesUsuario(int $idEmpresa, int $idUsuario, int $limite = 50): array
    {
        $st = $this->db->prepare(self::SELECT . "
             WHERE a.id_empresa = :e AND a.id_responsable = :u AND a.eliminado = false AND a.completada = false
             ORDER BY a.vence_at NULLS LAST, a.id
             LIMIT " . max(1, min(200, $limite)));
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarVencidasUsuario(int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM crm_actividades
              WHERE id_empresa = :e AND id_responsable = :u AND eliminado = false
                AND completada = false AND vence_at < NOW()"
        );
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario]);
        return (int) $st->fetchColumn();
    }

    public function insertar(array $d, int $idEmpresa, int $idUsuario): int
    {
        $st = $this->db->prepare(
            "INSERT INTO crm_actividades
                (id_empresa, id_oportunidad, id_contacto, tipo, titulo, descripcion, vence_at, id_responsable,
                 completada, completada_at, completada_por, created_by, updated_by)
             VALUES (:e, :o, :c, :t, :ti, :de, :ve, :r, :comp, :comp_at, :comp_por, :u, :u2)
             RETURNING id"
        );
        // Una nota se registra ya completada: es un apunte, no algo por hacer.
        $completada = $d['tipo'] === 'nota';
        $st->bindValue(':e', $idEmpresa, PDO::PARAM_INT);
        $st->bindValue(':o', $d['id_oportunidad'] ?: null, $d['id_oportunidad'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':c', $d['id_contacto'] ?: null, $d['id_contacto'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':t', $d['tipo']);
        $st->bindValue(':ti', $this->caparTexto('titulo', $d['titulo']));
        $st->bindValue(':de', $d['descripcion'] ?: null);
        $st->bindValue(':ve', $d['vence_at'] ?: null);
        $st->bindValue(':r', $d['id_responsable'] ?: null, $d['id_responsable'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':comp', $completada ? 'true' : 'false'); // literal: PDO manda false como ''
        $st->bindValue(':comp_at', $completada ? date('Y-m-d H:i:s') : null);
        $st->bindValue(':comp_por', $completada ? $idUsuario : null, $completada ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':u', $idUsuario, PDO::PARAM_INT);
        $st->bindValue(':u2', $idUsuario, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_actividades SET tipo = :t, titulo = :ti, descripcion = :de, vence_at = :ve,
                    id_responsable = :r, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([
            ':t' => $d['tipo'], ':ti' => $this->caparTexto('titulo', $d['titulo']), ':de' => $d['descripcion'] ?: null,
            ':ve' => $d['vence_at'] ?: null, ':r' => $d['id_responsable'] ?: null,
            ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa,
        ]);
    }

    public function marcarCompletada(int $id, int $idEmpresa, bool $completada, int $idUsuario): void
    {
        $set = $completada
            ? 'completada = true, completada_at = NOW(), completada_por = :por'
            : 'completada = false, completada_at = NULL, completada_por = NULL';
        $st = $this->db->prepare(
            "UPDATE crm_actividades SET {$set}, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $p = [':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa];
        if ($completada) {
            $p[':por'] = $idUsuario;
        }
        $st->execute($p);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $st = $this->db->prepare(
            "UPDATE crm_actividades SET eliminado = true, deleted_at = NOW(), deleted_by = :u,
                    updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }
}
