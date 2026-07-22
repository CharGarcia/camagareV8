<?php
/**
 * Modelo ComboSubmodulo - Catálogo global de "combos" (paquetes) de submódulos.
 * Un combo agrupa N submódulos para asignarlos de un clic (acceso total) a un
 * usuario+empresa desde /config/permisos-modulos. No lleva id_empresa: es un
 * catálogo del sistema (planes), no un dato operativo por empresa.
 */

declare(strict_types=1);

namespace App\models;

class ComboSubmodulo extends BaseModel
{
    /**
     * Invalida la caché del aviso "submódulo nuevo" tras aplicar un combo.
     */
    private function invalidarAvisoNuevo(int $idUsuario, int $idEmpresa): void
    {
        try {
            \App\Services\ContadoresNavbarService::invalidarSubmodulosNuevos($idUsuario, $idEmpresa);
        } catch (\Throwable $e) {
            // Silencioso a propósito.
        }
    }

    /**
     * Lista combos con búsqueda por nombre/descripción y conteo de submódulos incluidos.
     */
    public function getAll(string $buscar = ''): array
    {
        $where = ' WHERE c.eliminado = false ';
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (c.nombre ILIKE '%{$b}%' OR COALESCE(c.descripcion,'') ILIKE '%{$b}%') ";
        }
        $sql = "SELECT c.*, COUNT(i.id) AS total_submodulos
                FROM combos_submodulos c
                LEFT JOIN combos_submodulos_items i ON i.id_combo = c.id
                {$where}
                GROUP BY c.id
                ORDER BY c.orden ASC, c.nombre ASC";
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Combos activos para el selector "Aplicar combo" en permisos-modulos.
     */
    public function getActivos(): array
    {
        $sql = "SELECT c.id, c.nombre, c.precio, COUNT(i.id) AS total_submodulos
                FROM combos_submodulos c
                LEFT JOIN combos_submodulos_items i ON i.id_combo = c.id
                WHERE c.eliminado = false AND c.activo = true
                GROUP BY c.id
                ORDER BY c.orden ASC, c.nombre ASC";
        try {
            return $this->query($sql);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        $id = (int) $id;
        $rows = $this->query("SELECT * FROM combos_submodulos WHERE id = {$id} AND eliminado = false");
        return $rows[0] ?? null;
    }

    /**
     * Submódulos incluidos en un combo (id_modulo, id_submodulo + nombres para mostrar).
     */
    public function getItems(int $idCombo): array
    {
        $id = (int) $idCombo;
        $queries = [
            "SELECT i.id_modulo, i.id_submodulo, mm.nombre_modulo, sm.nombre_submodulo
                FROM combos_submodulos_items i
                INNER JOIN submodulos_menu sm ON sm.id = i.id_submodulo
                INNER JOIN modulos_menu mm ON mm.id = i.id_modulo
                WHERE i.id_combo = {$id}
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
            "SELECT i.id_modulo, i.id_submodulo, mm.nombre_modulo, sm.nombre_submodulo
                FROM combos_submodulos_items i
                INNER JOIN submodulos_menu sm ON sm.id_submodulo = i.id_submodulo
                INNER JOIN modulos_menu mm ON mm.id_modulo = i.id_modulo
                WHERE i.id_combo = {$id}
                ORDER BY mm.nombre_modulo, sm.nombre_submodulo",
        ];
        foreach ($queries as $sql) {
            try {
                return $this->query($sql);
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    /**
     * Crea un combo y sus items. $items = [['id_modulo'=>, 'id_submodulo'=>], ...]
     */
    public function crear(array $data, array $items, int $idUsuario): int
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del combo es obligatorio.');
        }
        if (empty($items)) {
            throw new \InvalidArgumentException('El combo debe incluir al menos un submódulo.');
        }

        $n = $this->escape($nombre);
        $desc = $this->escape(trim($data['descripcion'] ?? ''));
        $precio = $data['precio'] !== null && $data['precio'] !== '' ? (float) $data['precio'] : null;
        $precioSql = $precio !== null ? number_format($precio, 2, '.', '') : 'NULL';
        $color = $this->escape(trim($data['clase_color'] ?? 'primary') ?: 'primary');
        $orden = (int) ($data['orden'] ?? 0);
        $activo = !empty($data['activo']) ? 'true' : 'false';
        $idU = (int) $idUsuario;

        $this->db->beginTransaction();
        try {
            $this->execute("INSERT INTO combos_submodulos (nombre, descripcion, precio, clase_color, orden, activo, created_by, updated_by)
                VALUES ('{$n}', '{$desc}', {$precioSql}, '{$color}', {$orden}, {$activo}, {$idU}, {$idU})");
            $id = $this->lastInsertId('combos_submodulos_id_seq');

            $this->insertarItems($id, $items);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data, array $items, int $idUsuario): bool
    {
        $id = (int) $id;
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del combo es obligatorio.');
        }
        if (empty($items)) {
            throw new \InvalidArgumentException('El combo debe incluir al menos un submódulo.');
        }

        $n = $this->escape($nombre);
        $desc = $this->escape(trim($data['descripcion'] ?? ''));
        $precio = $data['precio'] !== null && $data['precio'] !== '' ? (float) $data['precio'] : null;
        $precioSql = $precio !== null ? number_format($precio, 2, '.', '') : 'NULL';
        $color = $this->escape(trim($data['clase_color'] ?? 'primary') ?: 'primary');
        $orden = (int) ($data['orden'] ?? 0);
        $activo = !empty($data['activo']) ? 'true' : 'false';
        $idU = (int) $idUsuario;

        $this->db->beginTransaction();
        try {
            $this->execute("UPDATE combos_submodulos
                SET nombre = '{$n}', descripcion = '{$desc}', precio = {$precioSql}, clase_color = '{$color}',
                    orden = {$orden}, activo = {$activo}, updated_by = {$idU}, updated_at = CURRENT_TIMESTAMP
                WHERE id = {$id}");

            $this->execute("DELETE FROM combos_submodulos_items WHERE id_combo = {$id}");
            $this->insertarItems($id, $items);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function insertarItems(int $idCombo, array $items): void
    {
        $vistos = [];
        foreach ($items as $item) {
            $idMod = (int) ($item['id_modulo'] ?? 0);
            $idSub = (int) ($item['id_submodulo'] ?? 0);
            if ($idMod <= 0 || $idSub <= 0 || isset($vistos[$idSub])) continue;
            $vistos[$idSub] = true;
            $this->execute("INSERT INTO combos_submodulos_items (id_combo, id_modulo, id_submodulo)
                VALUES ({$idCombo}, {$idMod}, {$idSub})");
        }
    }

    public function eliminar(int $id, int $idUsuario): bool
    {
        $id = (int) $id;
        $idU = (int) $idUsuario;
        return $this->execute("UPDATE combos_submodulos
            SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = {$idU}
            WHERE id = {$id} AND eliminado = false");
    }

    /**
     * Aplica todos los submódulos de un combo a un usuario+empresa con acceso total
     * (r=1,w=1,u=1,d=1,t=1). SUMA sobre lo que el usuario ya tenía: nunca quita
     * permisos existentes de submódulos fuera del combo. Transaccional.
     *
     * @return array{ok:bool,aplicados:int,combo?:array}
     */
    public function aplicarAUsuario(int $idCombo, int $idUsuario, int $idEmpresa, int $idQuienAplica): array
    {
        $idC = (int) $idCombo;
        $idU = (int) $idUsuario;
        $idE = (int) $idEmpresa;

        $combo = $this->getById($idC);
        if (!$combo || empty($combo['activo'])) {
            return ['ok' => false, 'aplicados' => 0];
        }

        $items = $this->getItems($idC);
        if (empty($items)) {
            return ['ok' => false, 'aplicados' => 0];
        }

        $this->db->beginTransaction();
        try {
            $aplicados = 0;
            foreach ($items as $item) {
                $idMod = (int) $item['id_modulo'];
                $idSub = (int) $item['id_submodulo'];
                if ($idMod <= 0 || $idSub <= 0) continue;

                $existe = $this->query("SELECT 1 FROM modulos_asignados WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                if (!empty($existe)) {
                    $this->execute("UPDATE modulos_asignados SET r = 1, w = 1, u = 1, d = 1, t = 1
                        WHERE id_usuario = {$idU} AND id_empresa = {$idE} AND id_submodulo = {$idSub}");
                } else {
                    $this->execute("INSERT INTO modulos_asignados (id_usuario, id_empresa, id_modulo, id_submodulo, r, w, u, d, t)
                        VALUES ({$idU}, {$idE}, {$idMod}, {$idSub}, 1, 1, 1, 1, 1)");
                }
                $aplicados++;
            }
            $this->db->commit();
            $this->invalidarAvisoNuevo($idU, $idE);

            try {
                (new \App\Services\LogSistemaService())->registrar(
                    (int) $idQuienAplica,
                    $idE,
                    'aplicar_combo',
                    'modulos_asignados',
                    $idC,
                    null,
                    ['combo' => $combo['nombre'], 'id_usuario_destino' => $idU, 'submodulos_aplicados' => $aplicados]
                );
            } catch (\Throwable $e) {
                // Auditoría no debe bloquear la operación.
            }

            return ['ok' => true, 'aplicados' => $aplicados, 'combo' => $combo];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'aplicados' => 0];
        }
    }
}
