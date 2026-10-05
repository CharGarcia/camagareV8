<?php
declare(strict_types=1);

namespace App\repositories;

use PDO;

/**
 * Acceso a guardados_formulario (database/20261005_guardados_formulario.sql): qué documento
 * creó cada clave de formulario. Lo usa únicamente App\Services\GuardadoUnicoService.
 */
class GuardadoFormularioRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('guardados_formulario');
    }

    /** ¿Ya se aplicó el SQL? (BaseRepository::tablaExiste: to_regclass, cacheado, seguro en transacción). */
    public function disponible(): bool
    {
        return $this->tablaExiste('guardados_formulario');
    }

    /** Candado transaccional por clave (CLAUDE.md §8): exige una transacción abierta. */
    public function lock(int $idEmpresa, string $modulo, string $token): void
    {
        $st = $this->db->prepare("SELECT pg_advisory_xact_lock(hashtext('guardado_form:' || :e || ':' || :m || ':' || :t))");
        $st->execute([':e' => (string) $idEmpresa, ':m' => $modulo, ':t' => $token]);
    }

    public function buscar(int $idEmpresa, string $modulo, string $token): ?array
    {
        $st = $this->db->prepare("SELECT id_registro, numero, respuesta
                                    FROM guardados_formulario
                                   WHERE id_empresa = :e AND modulo = :m AND token = :t AND eliminado = false
                                   LIMIT 1");
        $st->execute([':e' => $idEmpresa, ':m' => $modulo, ':t' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertar(int $idEmpresa, string $modulo, string $token, int $idRegistro, ?string $numero, ?string $respuesta, int $idUsuario): void
    {
        $st = $this->db->prepare("INSERT INTO guardados_formulario
                                         (id_empresa, modulo, token, id_registro, numero, respuesta, created_by)
                                  VALUES (:e, :m, :t, :r, :n, :resp, :u)");
        $st->execute([
            ':e' => $idEmpresa, ':m' => $modulo, ':t' => $token, ':r' => $idRegistro,
            ':n' => $numero, ':resp' => $respuesta, ':u' => $idUsuario,
        ]);
    }
}
