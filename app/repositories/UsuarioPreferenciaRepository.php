<?php

declare(strict_types=1);

namespace App\repositories;

use PDO;

class UsuarioPreferenciaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('usuarios_preferencias');
    }

    /**
     * Guarda o actualiza una preferencia (llave-valor en JSON) para un usuario, empresa y módulo.
     */
    public function guardarPreferencia(int $idUsuario, int $idEmpresa, string $modulo, string $campo, $valor): void
    {
        // Verificamos si ya existe la fila por usuario, empresa y módulo
        $sqlCheck = "SELECT id, preferencias FROM {$this->table} 
                     WHERE id_usuario = :id_u AND id_empresa = :id_e AND modulo = :mod LIMIT 1";
        $stCheck = $this->db->prepare($sqlCheck);
        $stCheck->execute([
            ':id_u' => $idUsuario,
            ':id_e' => $idEmpresa,
            ':mod'  => $modulo
        ]);

        $row = $stCheck->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $prefsString = is_string($row['preferencias']) ? $row['preferencias'] : '{}';
            $prefs = json_decode($prefsString, true);
            if (!is_array($prefs)) {
                $prefs = [];
            }
            if ($valor === '' || $valor === null) {
                unset($prefs[$campo]);
            } else {
                $prefs[$campo] = $valor;
            }

            $sqlUpdate = "UPDATE {$this->table} SET preferencias = :prefs 
                          WHERE id = :id";
            $stUpdate = $this->db->prepare($sqlUpdate);
            $stUpdate->execute([
                ':prefs' => json_encode($prefs, JSON_UNESCAPED_UNICODE),
                ':id'    => $row['id']
            ]);
        } else {
            // Si el valor es vacío en una fila inexistente, no hacemos nada
            if ($valor === '' || $valor === null) {
                return;
            }

            $prefs = [$campo => $valor];
            $sqlInsert = "INSERT INTO {$this->table} (id_usuario, id_empresa, modulo, preferencias) 
                           VALUES (:id_u, :id_e, :mod, :prefs)";
            $stInsert = $this->db->prepare($sqlInsert);
            $stInsert->execute([
                ':id_u'  => $idUsuario,
                ':id_e'  => $idEmpresa,
                ':mod'   => $modulo,
                ':prefs' => json_encode($prefs, JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    /**
     * Obtiene el JSON (array php) de las preferencias
     */
    public function obtenerPreferencias(int $idUsuario, int $idEmpresa, string $modulo): array
    {
        $sql = "SELECT preferencias FROM {$this->table} 
                WHERE id_usuario = :id_u AND id_empresa = :id_e AND modulo = :mod LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_u' => $idUsuario,
            ':id_e' => $idEmpresa,
            ':mod'  => $modulo
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }

        $prefsString = is_string($row['preferencias']) ? $row['preferencias'] : '{}';
        $prefs = json_decode($prefsString, true);
        return is_array($prefs) ? $prefs : [];
    }
}
