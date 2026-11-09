<?php

declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * CRM: catálogos por empresa (orígenes, motivos de pérdida) y las búsquedas
 * auxiliares que necesita el CRM sobre tablas de otros módulos (usuarios de la
 * empresa, vendedores, clientes). Solo lectura sobre esas tablas ajenas.
 */
class CrmCatalogoRepository extends BaseRepository
{
    /** Tablas de catálogo que administra este repositorio. */
    private const TABLAS = ['origenes' => 'crm_origenes', 'motivos' => 'crm_motivos_perdida'];

    public function __construct()
    {
        parent::__construct('crm_origenes');
    }

    private function tabla(string $catalogo): string
    {
        if (!isset(self::TABLAS[$catalogo])) {
            throw new \InvalidArgumentException('Catálogo no válido.');
        }
        return self::TABLAS[$catalogo];
    }

    public function listar(string $catalogo, int $idEmpresa, bool $soloActivos = true): array
    {
        $t = $this->tabla($catalogo);
        $extra = $catalogo === 'origenes' ? ', tipo_canal' : '';
        $sql = "SELECT id, nombre, estado{$extra} FROM {$t}
                 WHERE id_empresa = :e AND eliminado = false"
             . ($soloActivos ? " AND estado = 'activo'" : '')
             . " ORDER BY nombre, id";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(string $catalogo, int $id, int $idEmpresa): ?array
    {
        $t = $this->tabla($catalogo);
        $st = $this->db->prepare("SELECT * FROM {$t} WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Busca por nombre sin distinguir mayúsculas (evita duplicados). */
    public function existeNombre(string $catalogo, int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $t = $this->tabla($catalogo);
        $sql = "SELECT 1 FROM {$t} WHERE id_empresa = :e AND eliminado = false AND lower(nombre) = lower(:n)";
        $p = [':e' => $idEmpresa, ':n' => trim($nombre)];
        if ($excluirId !== null) { $sql .= " AND id <> :x"; $p[':x'] = $excluirId; }
        $st = $this->db->prepare($sql . ' LIMIT 1');
        $st->execute($p);
        return (bool) $st->fetchColumn();
    }

    public function insertar(string $catalogo, int $idEmpresa, string $nombre, ?string $tipoCanal, int $idUsuario): int
    {
        $t = $this->tabla($catalogo);
        if ($catalogo === 'origenes') {
            $st = $this->db->prepare(
                "INSERT INTO {$t} (id_empresa, nombre, tipo_canal, estado, created_by, updated_by)
                 VALUES (:e, :n, :c, 'activo', :u, :u2) RETURNING id"
            );
            $st->execute([':e' => $idEmpresa, ':n' => $this->caparTexto('nombre', $nombre, $t),
                          ':c' => $tipoCanal, ':u' => $idUsuario, ':u2' => $idUsuario]);
        } else {
            $st = $this->db->prepare(
                "INSERT INTO {$t} (id_empresa, nombre, estado, created_by, updated_by)
                 VALUES (:e, :n, 'activo', :u, :u2) RETURNING id"
            );
            $st->execute([':e' => $idEmpresa, ':n' => $this->caparTexto('nombre', $nombre, $t),
                          ':u' => $idUsuario, ':u2' => $idUsuario]);
        }
        return (int) $st->fetchColumn();
    }

    public function actualizar(string $catalogo, int $id, int $idEmpresa, string $nombre, string $estado, int $idUsuario): void
    {
        $t = $this->tabla($catalogo);
        $st = $this->db->prepare(
            "UPDATE {$t} SET nombre = :n, estado = :s, updated_at = NOW(), updated_by = :u
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':n' => $this->caparTexto('nombre', $nombre, $t), ':s' => $estado,
                      ':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    public function eliminar(string $catalogo, int $id, int $idEmpresa, int $idUsuario): void
    {
        $t = $this->tabla($catalogo);
        $st = $this->db->prepare(
            "UPDATE {$t} SET eliminado = true, deleted_at = NOW(), deleted_by = :u, updated_at = NOW(), updated_by = :u2
              WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':u' => $idUsuario, ':u2' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Origen activo de la empresa para un tipo de canal (p. ej. instagram_dm), o null. */
    public function origenPorCanal(int $idEmpresa, string $tipoCanal): ?int
    {
        $st = $this->db->prepare(
            "SELECT id FROM crm_origenes WHERE id_empresa = :e AND tipo_canal = :t AND eliminado = false AND estado = 'activo'
              ORDER BY id LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':t' => $tipoCanal]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    // ── Tablas de otros módulos (solo lectura) ──────────────────────────────

    /**
     * Respuestas rápidas de la empresa y las personales del usuario. Son las mismas
     * del chat de WhatsApp (whatsapp_respuestas_rapidas): se administran allí.
     */
    public function respuestasRapidas(int $idEmpresa, int $idUsuario): array
    {
        if (!$this->tablaExiste('whatsapp_respuestas_rapidas')) {
            return [];
        }
        $st = $this->db->prepare(
            'SELECT id, titulo, contenido, (id_usuario IS NOT NULL) AS personal
               FROM whatsapp_respuestas_rapidas
              WHERE id_empresa = :e AND eliminado = false AND (id_usuario IS NULL OR id_usuario = :u)
              ORDER BY id_usuario NULLS FIRST, orden, titulo'
        );
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Usuarios activos asignados a la empresa (selector de responsable). Los de
     * nivel 3 no se listan: no están en empresa_asignada y no atienden leads.
     */
    public function usuariosEmpresa(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT u.id, u.nombre
               FROM empresa_asignada ea
               JOIN usuarios u ON u.id = ea.id_usuario
              WHERE ea.id_empresa = :e AND u.eliminado = false AND u.estado = '1'
              ORDER BY u.nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿El usuario puede ser responsable en esta empresa? (asignado y activo). */
    public function usuarioPerteneceEmpresa(int $idUsuario, int $idEmpresa): bool
    {
        $st = $this->db->prepare(
            "SELECT 1 FROM empresa_asignada ea
               JOIN usuarios u ON u.id = ea.id_usuario
              WHERE ea.id_empresa = :e AND ea.id_usuario = :u AND u.eliminado = false AND u.estado = '1'
              LIMIT 1"
        );
        $st->execute([':e' => $idEmpresa, ':u' => $idUsuario]);
        return (bool) $st->fetchColumn();
    }

    public function vendedores(int $idEmpresa): array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre FROM vendedores
              WHERE id_empresa = :e AND eliminado = false AND COALESCE(status, 1) = 1
              ORDER BY nombre"
        );
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vendedorExiste(int $id, int $idEmpresa): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM vendedores WHERE id = :id AND id_empresa = :e AND eliminado = false");
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    public function getCliente(int $id, int $idEmpresa): ?array
    {
        $st = $this->db->prepare(
            "SELECT id, nombre, identificacion, telefono, email
               FROM clientes WHERE id = :id AND id_empresa = :e AND eliminado = false"
        );
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
